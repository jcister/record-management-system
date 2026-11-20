<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsFileSearchSnippet;
use App\Concerns\NormalizesSearchTerm;
use App\Http\Requests\StoreRequestFormRequest;
use App\Http\Requests\UpdateRequestFormRequest;
use App\Jobs\ProcessFileOcr;
use App\Models\File;
use App\Models\Folder;
use App\Models\RequestLog;
use App\Models\Requests;
use App\Services\AuditService;
use App\Services\FileVersionService;
use App\Services\NotificationService;
use App\Support\DateTimeFormatter;
use App\Support\FileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    use BuildsFileSearchSnippet;
    use NormalizesSearchTerm;

    public function __construct(
        private FileVersionService $fileVersionService,
        private AuditService $audit
    ) {}

    /**
     * List all requests (for Pending Requests page).
     */
    public function index(): JsonResponse
    {
        $requests = Requests::query()
            ->with($this->requestRelations())
            ->latest()
            ->get();

        return response()->json($requests);
    }

    /**
     * List the authenticated user's own requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = Requests::query()
            ->where('requested_by', $request->user()->id)
            ->with($this->requestRelations())
            ->latest()
            ->get();

        return response()->json($requests);
    }

    /**
     * Get document types (root-level folders) for request creation.
     * Available to all authenticated users who can make requests.
     */
    public function getDocumentTypes(): JsonResponse
    {
        $folders = Folder::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $folders]);
    }

    /**
     * Store a new document request.
     */
    public function store(StoreRequestFormRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $metadataTagIds = collect($validated['metadata_tag_ids'] ?? [])
            ->filter(static fn ($id) => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();

        unset($validated['metadata_tag_ids']);

        $documentRequest = Requests::create([
            ...$validated,
            'requested_by' => $request->user()->id,
            'status' => 'Pending',
            'requires_scan' => $request->boolean('requires_scan'),
            'scan_review_status' => null,
            'scan_review_notes' => null,
        ]);

        $documentRequest->metadataTags()->sync($metadataTagIds);

        $documentRequest->load($this->requestRelations());

        return response()->json($documentRequest, 201);
    }

    /**
     * Show a single request with all relations.
     */
    public function show(int $id): JsonResponse
    {
        $documentRequest = Requests::with([
            'requestedBy:id,name,email',
            'department:id,name',
            'files',
            'metadataTags:id,name,type',
            'logs.user:id,name',
        ])->findOrFail($id);

        return response()->json($documentRequest);
    }

    /**
     * Update a request (requires_scan field only).
     */
    public function update(UpdateRequestFormRequest $request, int $id): JsonResponse
    {
        $documentRequest = Requests::findOrFail($id);

        Gate::authorize('update', $documentRequest);

        $validated = $request->validated();
        $oldValue = (bool) $documentRequest->requires_scan;
        $newValue = (bool) $validated['requires_scan'];

        // Check if value actually changed
        if ($oldValue === $newValue) {
            return response()->json($documentRequest->load($this->requestRelations()));
        }

        $documentRequest->update([
            'requires_scan' => $newValue,
        ]);

        // Notify archives team of the change
        app(NotificationService::class)->notifyRequestWillReturnCopyChanged(
            $documentRequest,
            $oldValue,
            $newValue,
            Auth::user()
        );

        return response()->json($documentRequest->load($this->requestRelations()));
    }

    /**
     * Update the status of a request.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Claimed', 'Awaiting Update', 'In Review', 'Complete', 'Rejected'])],
            'notes' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('status') === 'Rejected')],
        ], [
            'notes.required_if' => 'Notes are required when rejecting a request.',
        ]);

        $documentRequest = Requests::findOrFail($id);

        if ($validated['status'] === 'Awaiting Update' && ! $documentRequest->requires_scan) {
            throw ValidationException::withMessages([
                'status' => 'Awaiting Update is only applicable to requests that require scans.',
            ]);
        }

        $finalStatus = $this->resolveStatusTransition($documentRequest, $validated['status']);

        $payload = ['status' => $finalStatus];

        if ($request->has('notes') || $finalStatus === 'Rejected') {
            $payload['notes'] = $validated['notes'] ?? null;
        }

        if ($finalStatus !== 'Rejected' && $request->has('notes') && $validated['notes'] === null) {
            $payload['notes'] = null;
        }

        if ($finalStatus === 'Complete' && ! $documentRequest->requires_scan) {
            $payload['scan_review_status'] = null;
            $payload['scan_review_notes'] = null;
        }

        $documentRequest->update($payload);

        return response()->json($documentRequest->load($this->requestRelations()));
    }

    /**
     * Attach a file from All Records to a request.
     */
    /**
     * Search for files that can be attached to a request.
     * Returns files with OCR search support and excludes already-attached files.
     */
    public function searchableFiles(Request $request, int $id): JsonResponse
    {
        $search = (string) $request->query('search', '');
        $normalizedSearch = $this->normalizeSearchTerm($search);

        $documentRequest = Requests::findOrFail($id);

        // Get IDs of files already attached to this request
        $attachedFileIds = $documentRequest->files()->pluck('files.id')->all();

        if ($normalizedSearch === '') {
            return response()->json([
                'data' => [],
                'has_more' => false,
            ]);
        }

        $limit = (int) $request->integer('limit', 10);
        $limit = max(1, min($limit, 50));

        $files = File::query()
            ->with(['folder', 'currentVersion'])
            ->search($normalizedSearch)
            ->where('is_record', true)
            ->whereNotNull('folder_id')
            ->where(function ($query) {
                $query->where('record_id', 'not like', 'SCAN-%')
                    ->orWhereNull('record_id');
            })
            ->whereNotIn('id', $attachedFileIds)
            ->latest()
            ->limit($limit + 1)
            ->get();

        $results = $files->take($limit)->map(function (File $file) use ($search) {
            return [
                'id' => $file->id,
                'file_name' => $file->displayName(),
                'record_id' => $file->record_id,
                'retention_status' => $file->retention_status,
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
                'file_path' => $file->file_path,
                'created_at' => $file->created_at?->toIso8601String(),
                'folder' => $file->folder
                    ? [
                        'id' => $file->folder->id,
                        'name' => $file->folder->name,
                    ]
                    : null,
                'snippet' => $this->buildFileSearchSnippet($file, $search),
                'is_record' => $file->is_record,
                'ocr_status' => $file->ocr_status,
                'ocr_language' => $file->ocr_language,
            ];
        });

        return response()->json([
            'data' => $results,
            'has_more' => $files->count() > $limit,
        ]);
    }

    public function attachFile(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file_id' => ['nullable', 'uuid', 'exists:files,id', 'required_without:file_ids'],
            'file_ids' => ['nullable', 'array', 'max:1', 'required_without:file_id'],
            'file_ids.*' => ['uuid', 'exists:files,id'],
            'disposed_acknowledged' => ['nullable', 'boolean'],
        ]);

        $documentRequest = Requests::findOrFail($id);

        $fileIds = $request->has('file_ids')
            ? $request->file_ids
            : [$request->file_id];

        $attachedCount = 0;
        $skippedCount = 0;

        if (count($fileIds) > 1) {
            throw ValidationException::withMessages([
                'file_ids' => 'Only one linked file is allowed per request.',
            ]);
        }

        $linkedRecordCount = $documentRequest->files()
            ->where('files.record_id', 'not like', 'SCAN-%')
            ->count();

        foreach ($fileIds as $fileId) {
            if ($documentRequest->files()->where('file_id', $fileId)->exists()) {
                $skippedCount++;

                continue;
            }

            if ($linkedRecordCount >= 1) {
                throw ValidationException::withMessages([
                    'file_id' => 'Only one linked file is allowed per request.',
                ]);
            }

            $file = File::find($fileId);

            if (! $file) {
                throw ValidationException::withMessages([
                    'file_id' => 'The selected file could not be found.',
                ]);
            }

            if ($file->retention_status === \App\Support\RetentionStatus::DISPOSED && ! $request->boolean('disposed_acknowledged')) {
                throw ValidationException::withMessages([
                    'disposed_acknowledged' => 'Disposed records require acknowledgement before linking to a request.',
                ]);
            }

            if ($file->folder_id === null) {
                throw ValidationException::withMessages([
                    'file_id' => 'This record is still pending organization and cannot be linked yet. Move it into a folder first.',
                ]);
            }

            $documentRequest->files()->attach($fileId);
            $attachedCount++;
            $linkedRecordCount++;

            $this->audit->log(
                'request_file_attached',
                $request->user()->name.' attached "'.($file?->displayName() ?? 'file').'" to request "'.$documentRequest->request_title.'".',
                $documentRequest,
                [
                    'file_id' => $fileId,
                    'file_name' => $file?->displayName(),
                    'retention_status' => $file->retention_status,
                    'disposed_acknowledged' => $request->boolean('disposed_acknowledged'),
                ]
            );
        }

        if ($attachedCount === 0 && $skippedCount > 0) {
            return response()->json([
                'message' => $skippedCount === 1
                    ? 'File already attached.'
                    : 'All files are already attached.',
            ], 422);
        }

        return response()->json([
            'message' => $attachedCount === 1
                ? '1 file attached successfully.'
                : "{$attachedCount} files attached successfully.",
            'data' => $documentRequest->load('files'),
        ]);
    }

    /**
     * Detach a file from a request.
     */
    public function detachFile(Request $request, int $id, string $fileId): JsonResponse
    {
        $documentRequest = Requests::findOrFail($id);

        $file = File::find($fileId);

        $documentRequest->files()->detach($fileId);

        $this->audit->log(
            'request_file_detached',
            $request->user()->name.' removed "'.($file?->displayName() ?? 'file').'" from request "'.$documentRequest->request_title.'".',
            $documentRequest,
            [
                'file_id' => $fileId,
                'file_name' => $file?->displayName(),
            ]
        );

        return response()->json($documentRequest->load('files'));
    }

    /**
     * Upload a scanned file (PDF or image) and link it to the request.
     * The file goes to Import Records (no folder_id, status pending) for admin review.
     */
    public function uploadScan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tif,tiff,bmp,webp,heic,heif', 'max:102400'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'target_file_id' => ['required', 'uuid', 'exists:files,id'],
        ]);

        $documentRequest = Requests::with('files')->findOrFail($id);
        $targetFile = $this->findTargetFileForRequest($documentRequest, $validated['target_file_id']);
        $this->assertUploadCapacityAvailable($documentRequest, $targetFile);

        $uploadedFile = $request->file('file');
        $originalFileName = $uploadedFile->getClientOriginalName();
        $this->ensureUploadedFileMatchesTarget($targetFile, $originalFileName);
        $path = $uploadedFile->store('scans', 'local');

        $scanFileName = FileName::withoutExtension($originalFileName);

        if ($scanFileName === null || $scanFileName === '') {
            $scanFileName = FileName::withoutExtension($targetFile->file_name) ?: 'scan upload';
        }

        $file = File::create([
            'uploaded_by' => $request->user()->id,
            'author' => $request->user()->name,
            'department_id' => $documentRequest->department_id,
            'record_id' => 'SCAN-'.strtoupper(uniqid()),
            'physical_location' => 'Digital',
            'file_name' => $scanFileName,
            'file_path' => $path,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'folder_id' => null,
            'visibility' => 'restricted',
            'status' => 'draft',
        ]);

        $file->update([
            'remarks' => json_encode($this->buildPendingScanMetadata($documentRequest, $targetFile), JSON_UNESCAPED_SLASHES),
        ]);

        $documentRequest->files()->attach($file->id);

        if ($request->notes) {
            $documentRequest->update(['notes' => $request->notes]);
        }

        $this->audit->log(
            'request_scan_uploaded',
            $request->user()->name.' uploaded draft scan "'.$file->displayName().'" for request "'.$documentRequest->request_title.'".',
            $documentRequest,
            [
                'file_id' => $file->id,
                'file_name' => $file->displayName(),
                'target_file_id' => $targetFile->id,
                'target_file_record_id' => $targetFile->record_id,
            ]
        );

        if ($documentRequest->requires_scan) {
            $documentRequest->forceFill([
                'scan_review_status' => 'In Review',
                'scan_review_notes' => null,
            ])->save();
        }

        return response()->json($documentRequest->load('files'), 201);
    }

    public function submitScansForReview(Request $request, int $id): JsonResponse
    {
        $documentRequest = Requests::with('files')->findOrFail($id);

        $draftScans = $documentRequest->files->filter(function (File $file) {
            return str_starts_with((string) $file->record_id, 'SCAN-')
                && $file->status === 'draft';
        });

        if ($draftScans->isEmpty()) {
            throw ValidationException::withMessages([
                'scans' => 'No draft scans found to submit.',
            ]);
        }

        foreach ($draftScans as $draftScan) {
            $draftScan->update(['status' => 'pending']);
        }

        if ($documentRequest->requires_scan) {
            $documentRequest->forceFill([
                'scan_review_status' => 'In Review',
                'scan_review_notes' => null,
            ])->save();
        }

        $this->audit->log(
            'request_scans_submitted',
            $request->user()->name.' submitted '.$draftScans->count().' scan(s) for review on request "'.$documentRequest->request_title.'".',
            $documentRequest,
            [
                'scan_count' => $draftScans->count(),
                'file_ids' => $draftScans->pluck('id')->toArray(),
            ]
        );

        app(NotificationService::class)->notifyScanSubmittedForReview(
            $documentRequest,
            $draftScans->first(),
            $request->user()
        );

        return response()->json($documentRequest->load('files'), 200);
    }

    public function approveScan(Request $request, int $requestId, string $fileId): JsonResponse
    {
        if (! $request->user()->can('review-content')) {
            abort(403);
        }

        $documentRequest = Requests::with('files')->findOrFail($requestId);

        /** @var File|null $pendingFile */
        $pendingFile = $documentRequest->files->firstWhere('id', $fileId);

        if ($pendingFile === null || ! str_starts_with((string) $pendingFile->record_id, 'SCAN-')) {
            abort(404);
        }

        $metadata = $pendingFile->pending_scan_metadata;

        if (! is_array($metadata) || empty($metadata['target_file_id'])) {
            throw ValidationException::withMessages([
                'file' => 'This scanned upload is missing its target record metadata.',
            ]);
        }

        $targetFileId = $metadata['target_file_id'];

        $targetFile = $documentRequest->files->firstWhere('id', $targetFileId)
            ?? File::find($targetFileId);

        if (! $targetFile) {
            throw ValidationException::withMessages([
                'file' => 'The selected target record could not be found.',
            ]);
        }

        $destinationPath = $this->movePendingScan($pendingFile);
        $disk = config('filesystems.default', 'local');

        $version = $this->fileVersionService->createVersion(
            $targetFile,
            [
                'file_path' => $destinationPath,
                'file_name' => $pendingFile->file_name,
                'mime_type' => $pendingFile->mime_type,
                'file_size' => $pendingFile->file_size,
                'uploaded_by' => $pendingFile->uploaded_by,
                'assembly_status' => 'completed',
                'ocr_status' => 'pending',
                'ocr_language' => $targetFile->ocr_language,
            ],
            $disk,
        );

        ProcessFileOcr::dispatch($version->id);

        $documentRequest->files()->detach($pendingFile->id);
        $pendingFile->forceDelete();

        $documentRequest->forceFill([
            'status' => 'Complete',
            'scan_review_status' => 'Approved',
            'scan_review_notes' => null,
        ])->save();

        RequestLog::create([
            'request_id' => $documentRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'scan_approved',
            'details' => [
                'pending_file_id' => $fileId,
                'target_file_id' => $targetFile->id,
                'target_file_record_id' => $targetFile->record_id,
                'version_number' => $version->version_number,
            ],
        ]);

        $this->audit->log(
            'file_approved',
            $request->user()->name.' approved scanned file for record "'.($targetFile->record_id ?? $targetFile->id).'".',
            $targetFile,
            [
                'old_status' => 'pending',
                'new_status' => 'approved',
                'approved_by' => $request->user()->id,
                'scan_approval' => true,
                'version_number' => $version->version_number,
            ]
        );

        app(NotificationService::class)->notifyScanApproved(
            $documentRequest,
            $targetFile,
            $request->user()
        );

        return response()->json([
            'message' => 'Scan approved successfully.',
            'request' => $documentRequest->fresh(['files']),
        ]);
    }

    public function rejectScan(Request $request, int $requestId, string $fileId): JsonResponse
    {
        if (! $request->user()->can('review-content')) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $documentRequest = Requests::with('files')->findOrFail($requestId);

        /** @var File|null $pendingFile */
        $pendingFile = $documentRequest->files->firstWhere('id', $fileId);

        if ($pendingFile === null || ! str_starts_with((string) $pendingFile->record_id, 'SCAN-')) {
            abort(404);
        }

        if ($pendingFile->status !== 'pending') {
            throw ValidationException::withMessages([
                'file' => 'This scan has already been reviewed.',
            ]);
        }

        // Detach the rejected scan from the request
        $documentRequest->files()->detach($pendingFile->id);

        // Delete the physical file from storage
        if ($pendingFile->file_path) {
            Storage::disk('local')->delete($pendingFile->file_path);
        }

        // Log rejection before deleting the record
        RequestLog::create([
            'request_id' => $documentRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'scan_rejected',
            'details' => [
                'pending_file_id' => $fileId,
                'pending_file_name' => $pendingFile->file_name,
                'reason' => $validated['reason'],
            ],
        ]);

        // Notify before deleting the file record
        app(NotificationService::class)->notifyScanRejected(
            $documentRequest,
            $pendingFile,
            $validated['reason'],
            $request->user()
        );

        // Hard delete the database record (skip soft delete)
        $pendingFile->forceDelete();

        $documentRequest->forceFill([
            'status' => 'Awaiting Update',
            'scan_review_status' => 'Rejected',
            'scan_review_notes' => $validated['reason'],
        ])->save();

        return response()->json([
            'message' => 'Scan rejected successfully.',
            'request' => $documentRequest->fresh(['files']),
        ]);
    }

    public function destroyDraftScan(Request $request, int $requestId, string $fileId): JsonResponse
    {
        $documentRequest = Requests::with('files')->findOrFail($requestId);

        /** @var File|null $draftScan */
        $draftScan = $documentRequest->files->firstWhere('id', $fileId);

        if ($draftScan === null || ! str_starts_with((string) $draftScan->record_id, 'SCAN-')) {
            abort(404);
        }

        if (($draftScan->status ?? 'draft') !== 'draft') {
            throw ValidationException::withMessages([
                'file' => 'Only draft scans can be removed by request owners.',
            ]);
        }

        $hasOtherScans = $documentRequest->files->contains(function (File $file) use ($draftScan): bool {
            return $file->id !== $draftScan->id
                && str_starts_with((string) $file->record_id, 'SCAN-');
        });

        $documentRequest->files()->detach($draftScan->id);

        if ($draftScan->file_path) {
            Storage::disk('local')->delete($draftScan->file_path);
        }

        $this->audit->log(
            'request_scan_deleted',
            $request->user()->name.' removed draft scan "'.$draftScan->displayName().'" from request "'.$documentRequest->request_title.'".',
            $documentRequest,
            [
                'file_id' => $draftScan->id,
                'file_name' => $draftScan->displayName(),
            ]
        );

        RequestLog::create([
            'request_id' => $documentRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'scan_deleted',
            'details' => [
                'pending_file_id' => $draftScan->id,
                'pending_file_name' => $draftScan->file_name,
            ],
        ]);

        $draftScan->forceDelete();

        if ($documentRequest->requires_scan && ! $hasOtherScans) {
            $documentRequest->forceFill([
                'scan_review_status' => null,
                'scan_review_notes' => null,
            ])->save();
        }

        return response()->json([
            'message' => 'Draft scan removed successfully.',
            'request' => $documentRequest->fresh(['files']),
        ]);
    }

    /**
     * Return paginated request history logs.
     */
    public function history(): JsonResponse
    {
        $logs = $this->getRequestLogs();

        return response()->json($logs);
    }

    /**
     * @return array<int, string>
     */
    private function requestRelations(): array
    {
        return [
            'requestedBy:id,name,email',
            'department:id,name',
            'files',
            'metadataTags:id,name,type',
        ];
    }

    private function resolveStatusTransition(Requests $documentRequest, string $requestedStatus): string
    {
        if ($requestedStatus === 'Claimed') {
            return $documentRequest->requires_scan ? 'Awaiting Update' : 'Complete';
        }

        if ($requestedStatus === 'Awaiting Update' && ! $documentRequest->requires_scan) {
            return 'Complete';
        }

        return $requestedStatus;
    }

    private function findTargetFileForRequest(Requests $documentRequest, string $targetFileId): File
    {
        $targetFile = $documentRequest->files
            ->firstWhere('id', $targetFileId)
            ?? File::find($targetFileId);

        if (! $targetFile) {
            throw ValidationException::withMessages([
                'target_file_id' => 'Selected record must be linked to this request.',
            ]);
        }

        if (str_starts_with((string) $targetFile->record_id, 'SCAN-')) {
            throw ValidationException::withMessages([
                'target_file_id' => 'Uploaded scans cannot be selected as targets.',
            ]);
        }

        if (! $documentRequest->files->contains(fn ($file) => $file->id === $targetFile->id && ! str_starts_with((string) $file->record_id, 'SCAN-'))) {
            throw ValidationException::withMessages([
                'target_file_id' => 'Selected record is no longer linked to this request.',
            ]);
        }

        return $targetFile;
    }

    private function buildPendingScanMetadata(Requests $documentRequest, File $targetFile): array
    {
        return [
            'type' => 'request_scan',
            'request_id' => $documentRequest->id,
            'request_number' => $documentRequest->request_id,
            'request_title' => $documentRequest->request_title,
            'target_file_id' => $targetFile->id,
            'target_file_record_id' => $targetFile->record_id,
            'target_file_name' => $targetFile->displayName(),
        ];
    }

    private function assertUploadCapacityAvailable(Requests $documentRequest, File $targetFile): void
    {
        $linkedRecords = $documentRequest->files->filter(fn (File $file) => ! str_starts_with((string) $file->record_id, 'SCAN-'));

        if ($linkedRecords->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'Link a record before uploading scans.',
            ]);
        }

        $existingScans = $documentRequest->files->filter(
            fn (File $file) => str_starts_with((string) $file->record_id, 'SCAN-')
        );

        if ($existingScans->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'Only one scanned document is allowed per request. Remove the existing scan first.',
            ]);
        }

        $pendingScans = $documentRequest->files->filter(function (File $file) {
            return str_starts_with((string) $file->record_id, 'SCAN-')
                && ($file->status === null || $file->status === 'pending');
        });

        $targetAlreadyClaimed = $pendingScans->first(function (File $pending) use ($targetFile) {
            $metadata = $pending->pending_scan_metadata;

            return is_array($metadata)
                && ($metadata['target_file_id'] ?? null) === $targetFile->id;
        });

        if ($targetAlreadyClaimed) {
            throw ValidationException::withMessages([
                'target_file_id' => 'Each linked record can only have one pending scan. Remove the existing upload first.',
            ]);
        }

        if ($pendingScans->count() >= $linkedRecords->count()) {
            throw ValidationException::withMessages([
                'file' => 'All linked records already have pending scans. Remove one before uploading again.',
            ]);
        }
    }

    private function ensureUploadedFileMatchesTarget(File $targetFile, string $originalFileName): void
    {
        $targetName = $this->normalizeBaseFileName($targetFile->file_name ?? '');
        $incomingName = $this->normalizeBaseFileName($originalFileName);

        if ($targetName === '') {
            return;
        }

        if ($incomingName !== $targetName) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded file name must match the linked record name ('.$targetFile->file_name.').',
            ]);
        }
    }

    private function normalizeBaseFileName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $trimmed = trim($name);

        if ($trimmed === '') {
            return '';
        }

        $lastDot = strrpos($trimmed, '.');

        if ($lastDot !== false && $lastDot > 0) {
            $possibleExtension = substr($trimmed, $lastDot + 1);

            // Only strip when the trailing segment looks like a real extension
            // (short, alphanumeric, and contains at least one letter). This keeps
            // dotted numeric suffixes like ".2022" intact for matching.
            if ($possibleExtension !== ''
                && strlen($possibleExtension) <= 8
                && ctype_alnum($possibleExtension)
                && preg_match('/[A-Za-z]/', $possibleExtension)
            ) {
                $base = substr($trimmed, 0, $lastDot);
            } else {
                $base = $trimmed;
            }
        } else {
            $base = $trimmed;
        }

        return Str::lower($base);
    }

    private function movePendingScan(File $pendingFile): string
    {
        if (! $pendingFile->file_path) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded scan is missing its file path.',
            ]);
        }

        $sourceDisk = Storage::disk('local');
        $defaultDisk = config('filesystems.default', 'local');
        $destinationDisk = Storage::disk($defaultDisk);

        if (! $sourceDisk->exists($pendingFile->file_path)) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded scan could not be located on disk.',
            ]);
        }

        $extension = $this->resolvePendingFileExtension($pendingFile);
        $destinationPath = 'private/uploads/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

        if ($defaultDisk === 'local') {
            $sourceDisk->move($pendingFile->file_path, $destinationPath);
        } else {
            $stream = $sourceDisk->readStream($pendingFile->file_path);

            if ($stream === false) {
                throw ValidationException::withMessages([
                    'file' => 'Unable to read uploaded scan contents.',
                ]);
            }

            $destinationDisk->writeStream($destinationPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $sourceDisk->delete($pendingFile->file_path);
        }

        return $destinationPath;
    }

    private function resolvePendingFileExtension(File $pendingFile): string
    {
        $pathExtension = Str::lower((string) pathinfo((string) $pendingFile->file_path, PATHINFO_EXTENSION));

        if ($pathExtension !== '') {
            return $pathExtension;
        }

        $nameExtension = Str::lower((string) pathinfo((string) $pendingFile->file_name, PATHINFO_EXTENSION));

        if ($nameExtension !== '') {
            return $nameExtension;
        }

        $mime = Str::lower($pendingFile->mime_type ?? '');

        $mimeMap = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/heif' => 'heif',
            'image/heic' => 'heic',
        ];

        return $mimeMap[$mime] ?? '';
    }

    private function getRequestLogs(): array
    {
        if (! Schema::hasTable('request_logs')) {
            return [];
        }

        return RequestLog::with([
            'request:id,request_id,request_title,status',
            'user:id,name,email',
        ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (RequestLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'details' => $log->details,
                    'created_at' => DateTimeFormatter::toIso8601($log->created_at),
                    'request' => $log->request ? [
                        'id' => $log->request->id,
                        'request_id' => $log->request->request_id,
                        'request_title' => $log->request->request_title,
                        'status' => $log->request->status,
                    ] : null,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                ];
            })
            ->toArray();
    }
}
