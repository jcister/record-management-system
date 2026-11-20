<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovalLetterPreviewRequest;
use App\Http\Requests\ApproveDisposalBatchRequest;
use App\Http\Requests\ConfirmCoaSubmissionRequest;
use App\Http\Requests\ConfirmNapAuthorityRequest;
use App\Http\Requests\FinalizeApprovalRequest;
use App\Http\Requests\GenerateApprovalLetterRequest;
use App\Http\Requests\GenerateRequestLetterRequest;
use App\Http\Requests\ReturnDisposalBatchToDraftRequest;
use App\Http\Requests\UpdateDisposalBatchRequest;
use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Folder;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\ApprovalLetterService;
use App\Services\NapForm1Service;
use App\Services\NapForm3Service;
use App\Services\RequestLetterService;
use App\Support\CoaContactDefaults;
use App\Support\DateTimeFormatter;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use App\Support\RetentionStatusCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DisposalBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DisposalBatch::query()
            ->with([
                'scheduledBy:id,name',
                'reportSubmittedBy:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('created_at');

        $batches = $query->paginate(15)->withQueryString();

        return Inertia::render('Disposal/DisposalBatches', [
            'batches' => $batches->through(fn (DisposalBatch $batch) => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'items_count' => $batch->items_count,
                'scheduled_by' => $batch->scheduledBy?->only(['id', 'name']),
                'scheduled_at' => DateTimeFormatter::toIso8601($batch->scheduled_at),
                'report_submitted_by' => $batch->reportSubmittedBy?->only(['id', 'name']),
                'report_submitted_to_coa_at' => DateTimeFormatter::toIso8601($batch->report_submitted_to_coa_at),
                'scheduled_note' => $batch->scheduled_note,
                'mode_of_disposal' => $batch->mode_of_disposal,
                'mode_of_disposal_label' => DisposalBatch::modeLabel($batch->mode_of_disposal),
                'nap_report_path' => $batch->nap_report_path !== null,
                'created_at' => DateTimeFormatter::toIso8601($batch->created_at),
            ]),
            'statusOptions' => [
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'inspected', 'label' => 'Inspected'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'complete', 'label' => 'Complete'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['required', 'string', 'exists:files,id'],
            'scheduled_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $fileIds = $validated['file_ids'];

        // Verify files are available for disposal
        $files = File::query()
            ->with('physicalLocationTags:id,name')
            ->whereIn('id', $fileIds)
            ->where('is_record', true)
            ->whereIn('retention_status', [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL])
            ->where(function ($eligible) {
                $eligible
                    ->whereNull('retention_duration')
                    ->orWhere('retention_duration', '!=', RetentionSchedule::DURATION_PERPETUAL);
            })
            ->where('record_id', 'NOT LIKE', 'SCAN-%')
            ->whereDoesntHave('disposalBatchFiles')
            ->get();

        if ($files->count() !== count($fileIds)) {
            return redirect()
                ->back()
                ->with('error', 'Some files are not available for disposal.');
        }

        // Get next sequence number
        $sequence = DisposalBatch::max('sequence') + 1;
        $reference = 'DB-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

        // Create batch
        $batch = DisposalBatch::create([
            'sequence' => $sequence,
            'reference' => $reference,
            'status' => DisposalBatch::STATUS_SCHEDULED,
            'file_count' => $files->count(),
            'scheduled_by' => $user->id,
            'scheduled_at' => now(),
            'scheduled_note' => $validated['scheduled_note'] ?? null,
        ]);

        // Add files to batch
        foreach ($files as $file) {
            $batch->items()->create([
                'file_id' => $file->id,
                'status' => DisposalBatch::STATUS_SCHEDULED,
                'scheduled_by' => $user->id,
                'scheduled_at' => now(),
                'physical_snapshot' => [
                    'location' => $file->physical_location,
                    'tags' => $file->physicalLocationTags
                        ->map(fn ($tag) => [
                            'id' => (int) $tag->id,
                            'name' => $tag->name,
                        ])
                        ->values()
                        ->all(),
                ],
            ]);

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'user_id' => $user->id,
                'action' => 'added_to_batch',
                'description' => "File {$file->record_id} added to batch {$batch->reference}",
                'meta' => [
                    'batch_reference' => $batch->reference,
                    'retention_status' => $file->retention_status,
                ],
            ]);
        }

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', "Disposal batch {$batch->reference} created with {$files->count()} file(s).");
    }

    public function show(DisposalBatch $batch): Response
    {
        $batch->load([
            'scheduledBy:id,name,email,position_id',
            'scheduledBy.position:id,name',
            'completedBy:id,name,email',
            'napReportGeneratedBy:id,name,email',
            'napForm1GeneratedBy:id,name,email',
            'inspectionScheduledBy:id,name,email',
            'inspectedBy:id,name,email,position_id',
            'inspectedBy.position:id,name',
            'approvedBy:id,name,email,position_id',
            'approvedBy.position:id,name',
            'rejectedBy:id,name,email',
            'approvalLetterGeneratedBy:id,name,email',
            'requestLetterGeneratedBy:id,name,email',
            'items.file' => function ($query) {
                $query->withTrashed()->select(['id', 'record_id', 'file_name', 'author', 'physical_location', 'retention_status', 'file_path', 'deleted_at', 'restored_at']);
            },
            'items.file.physicalLocationTags:id,name',
            'audits.user:id,name',
            'napAuthorityConfirmedBy:id,name,email',
            'reportSubmittedBy:id,name,email',
        ]);

        $metadata = $batch->metadata ?? [];
        $approvalLetterDetails = [
            'letter_reference' => Arr::get($metadata, 'approval_letter.reference'),
            'letter_date' => Arr::get($metadata, 'approval_letter.date'),
            'honorific' => Arr::get($metadata, 'approval_letter.honorific'),
            'recipient_name' => Arr::get($metadata, 'approval_letter.recipient_name'),
            'recipient_first_name' => Arr::get($metadata, 'approval_letter.recipient_first_name'),
            'recipient_middle_name' => Arr::get($metadata, 'approval_letter.recipient_middle_name'),
            'recipient_last_name' => Arr::get($metadata, 'approval_letter.recipient_last_name'),
            'recipient_extension' => Arr::get($metadata, 'approval_letter.recipient_extension'),
            'recipient_position' => Arr::get($metadata, 'approval_letter.recipient_position'),
            'recipient_designation' => Arr::get($metadata, 'approval_letter.recipient_designation'),
            'recipient_address' => Arr::get($metadata, 'approval_letter.recipient_address'),
            'downloaded_at' => Arr::get($metadata, 'approval_letter.downloaded_at'),
        ];
        $napForm3Details = [
            'certificate_date' => Arr::get($metadata, 'nap_certificate.date'),
        ];
        $napForm1Details = [
            'date_prepared' => Arr::get($metadata, 'nap_form_1.date_prepared'),
        ];
        $requestLetterDetails = [
            'letter_date' => Arr::get($metadata, 'request_letter.letter_date'),
        ];
        $approvalReviewDetails = [
            'coa_name' => Arr::get($metadata, 'approval_review.coa_name') ?? $batch->coa_name,
            'coa_position' => Arr::get($metadata, 'approval_review.coa_position') ?? $batch->coa_position,
            'coa_designation' => Arr::get($metadata, 'approval_review.coa_designation') ?? $batch->coa_designation,
        ];
        $napReportFileExists = $batch->nap_report_path
            ? file_exists(storage_path('app/'.$batch->nap_report_path))
            : false;
        $approvalLetterFileExists = $batch->approval_letter_path
            ? file_exists(storage_path('app/'.$batch->approval_letter_path))
            : false;
        $requestLetterFileExists = $batch->request_letter_path
            ? file_exists(storage_path('app/'.$batch->request_letter_path))
            : false;

        $workflowFilesCount = $batch->items()
            ->whereHas('file', function ($query) {
                $query->whereNotNull('id');
            })
            ->count();

        return Inertia::render('Disposal/DisposalBatchDetail', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'sequence' => $batch->sequence,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'disposed_files_count' => $workflowFilesCount,
                'scheduled_by' => $batch->scheduledBy?->only(['id', 'name', 'email']),
                'scheduled_at' => DateTimeFormatter::toIso8601($batch->scheduled_at),
                'scheduled_note' => $batch->scheduled_note,
                'mode_of_disposal' => $batch->mode_of_disposal,
                'mode_of_disposal_label' => DisposalBatch::modeLabel($batch->mode_of_disposal),
                'actual_mode_of_disposal' => $batch->actual_mode_of_disposal,
                'actual_mode_of_disposal_label' => DisposalBatch::modeLabel($batch->actual_mode_of_disposal),
                'completed_by' => $batch->completedBy?->only(['id', 'name', 'email']),
                'completed_at' => DateTimeFormatter::toIso8601($batch->completed_at),
                'nap_report_path' => $batch->nap_report_path,
                'nap_report_file_exists' => $napReportFileExists,
                'nap_report_stage' => $batch->nap_report_stage,
                'nap_report_generated_at' => DateTimeFormatter::toIso8601($batch->nap_report_generated_at),
                'nap_report_generated_by' => $batch->napReportGeneratedBy?->only(['id', 'name', 'email']),
                'nap_report_downloaded_at' => DateTimeFormatter::toIso8601($batch->nap_report_downloaded_at),
                'nap_certificate_details' => $napForm3Details,
                'request_letter_path' => $batch->request_letter_path,
                'request_letter_file_exists' => $requestLetterFileExists,
                'request_letter_generated_at' => DateTimeFormatter::toIso8601($batch->request_letter_generated_at),
                'request_letter_generated_by' => $batch->requestLetterGeneratedBy?->only(['id', 'name', 'email']),
                'request_letter_downloaded_at' => DateTimeFormatter::toIso8601($batch->request_letter_downloaded_at),
                'request_letter_details' => $requestLetterDetails,
                'nap_form_1_path' => $batch->nap_form_1_path,
                'nap_form_1_stage' => $batch->nap_form_1_stage,
                'nap_form_1_generated_at' => DateTimeFormatter::toIso8601($batch->nap_form_1_generated_at),
                'nap_form_1_generated_by' => $batch->napForm1GeneratedBy?->only(['id', 'name', 'email']),
                'nap_form_1_downloaded_at' => DateTimeFormatter::toIso8601($batch->nap_form_1_downloaded_at),
                'nap_form_1_details' => $napForm1Details,
                'inspection_scheduled_at' => DateTimeFormatter::toIso8601($batch->inspection_scheduled_at),
                'inspection_scheduled_by' => $batch->inspectionScheduledBy?->only(['id', 'name', 'email']),
                'inspected_by' => $batch->inspectedBy?->only(['id', 'name', 'email']),
                'inspector_name' => $batch->inspector_name,
                'inspected_at' => DateTimeFormatter::toIso8601($batch->inspected_at),
                'inspection_notes' => $batch->inspection_notes,
                'approved_by' => $batch->approvedBy?->only(['id', 'name', 'email']),
                'approved_at' => DateTimeFormatter::toIso8601($batch->approved_at),
                'approval_notes' => $batch->approval_notes,
                'approval_documents' => collect($batch->approval_documents ?? [])->map(fn ($document) => [
                    'name' => $document['name'] ?? 'Attachment',
                    'path' => $document['path'] ? Storage::url($document['path']) : null,
                    'storage_path' => $document['path'] ?? null,
                    'uploaded_at' => $document['uploaded_at'] ?? null,
                    'uploaded_by' => $document['uploaded_by'] ?? null,
                    'mime_type' => $document['mime_type'] ?? null,
                    'size' => $document['size'] ?? null,
                ])->all(),
                'approval_letter_path' => $batch->approval_letter_path,
                'approval_letter_file_exists' => $approvalLetterFileExists,
                'approval_letter_generated_at' => DateTimeFormatter::toIso8601($batch->approval_letter_generated_at),
                'approval_letter_generated_by' => $batch->approvalLetterGeneratedBy?->only(['id', 'name', 'email']),
                'approval_letter_details' => $approvalLetterDetails,
                'nap_authority_issued_at' => DateTimeFormatter::toIso8601($batch->nap_authority_issued_at),
                'nap_authority_confirmed_by' => $batch->napAuthorityConfirmedBy?->only(['id', 'name', 'email']),
                'report_submitted_to_coa_at' => DateTimeFormatter::toIso8601($batch->report_submitted_to_coa_at),
                'report_submitted_by' => $batch->reportSubmittedBy?->only(['id', 'name', 'email']),
                'coa' => [
                    'name' => $batch->coa_name,
                    'position' => $batch->coa_position,
                    'designation' => $batch->coa_designation,
                ],
                'approval_review_details' => $approvalReviewDetails,
                'rejected_by' => $batch->rejectedBy?->only(['id', 'name', 'email']),
                'rejected_at' => DateTimeFormatter::toIso8601($batch->rejected_at),
                'rejection_reason' => $batch->rejection_reason,
                'created_at' => DateTimeFormatter::toIso8601($batch->created_at),
                'items' => $batch->items->map(fn ($item) => [
                    'id' => $item->id,
                    'file_id' => $item->file_id,
                    'file' => $item->file ? [
                        'id' => $item->file->id,
                        'record_id' => $item->file->record_id,
                        'file_name' => $item->file->file_name,
                        'author' => $item->file->author,
                        'physical_location' => $item->file->physical_location,
                        'physical_location_tags' => $item->file->physicalLocationTags?->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ])->all() ?? [],
                        'retention_status' => $item->file->retention_status,
                        'file_path' => $item->file->file_path,
                        'deleted_at' => DateTimeFormatter::toIso8601($item->file->deleted_at),
                        'restored_at' => DateTimeFormatter::toIso8601($item->file->restored_at),
                    ] : null,
                    'status' => $item->status,
                    'scheduled_at' => DateTimeFormatter::toIso8601($item->scheduled_at),
                    'completed_at' => DateTimeFormatter::toIso8601($item->completed_at),
                    'physical_snapshot' => $item->physical_snapshot,
                ]),
                'audits' => $batch->audits->map(fn ($audit) => [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'description' => $audit->description,
                    'user' => $audit->user?->only(['id', 'name']),
                    'meta' => $audit->meta,
                    'created_at' => DateTimeFormatter::toIso8601($audit->created_at),
                ]),
            ],
            'modeOptions' => DisposalBatch::modeOptions(),
        ]);
    }

    public function update(UpdateDisposalBatchRequest $request, DisposalBatch $batch): RedirectResponse
    {
        $existingScheduledAt = DateTimeFormatter::toIso8601($batch->scheduled_at);
        $existingCompletedAt = DateTimeFormatter::toIso8601($batch->completed_at);
        $existingScheduledNote = $batch->scheduled_note;
        $updates = [];

        if ($request->has('scheduled_at')) {
            $updates['scheduled_at'] = $request->scheduledAt()
                ? DateTimeFormatter::parse($request->scheduledAt())
                : $batch->scheduled_at;
        }

        if ($request->has('completed_at')) {
            $updates['completed_at'] = $request->completedAt()
                ? DateTimeFormatter::parse($request->completedAt())
                : $batch->completed_at;
        }

        if ($request->has('scheduled_note')) {
            $updates['scheduled_note'] = $request->scheduledNote();
        }

        if (! empty($updates)) {
            $batch->update($updates);
            $batch->refresh();

            $changes = [];
            $updatedScheduledAt = DateTimeFormatter::toIso8601($batch->scheduled_at);
            $updatedCompletedAt = DateTimeFormatter::toIso8601($batch->completed_at);
            $updatedScheduledNote = $batch->scheduled_note;

            if ($existingScheduledAt !== $updatedScheduledAt) {
                $changes['scheduled_at'] = [
                    'from' => $existingScheduledAt,
                    'to' => $updatedScheduledAt,
                ];
            }

            if ($existingCompletedAt !== $updatedCompletedAt) {
                $changes['completed_at'] = [
                    'from' => $existingCompletedAt,
                    'to' => $updatedCompletedAt,
                ];
            }

            if ($existingScheduledNote !== $updatedScheduledNote) {
                $changes['scheduled_note'] = [
                    'from' => $existingScheduledNote,
                    'to' => $updatedScheduledNote,
                ];
            }

            if ($changes !== []) {
                DisposalAudit::create([
                    'batch_id' => $batch->id,
                    'user_id' => $request->user()?->id,
                    'action' => 'batch_updated',
                    'description' => ($request->user()?->name ?? 'System').' updated batch schedule details.',
                    'meta' => [
                        'status' => $batch->status,
                        'changes' => $changes,
                    ],
                ]);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'Batch dates updated successfully.');
        }

        return redirect()->route('disposal-batches.show', $batch);
    }

    public function complete(Request $request, DisposalBatch $batch): RedirectResponse
    {
        if (in_array($batch->status, DisposalBatch::lockedWorkflowStatuses(), true)) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'This batch is already verified.');
        }

        $user = $request->user();
        $now = now();

        DB::transaction(function () use ($batch, $user, $now) {
            // Update batch status to verified
            $batch->update([
                'status' => DisposalBatch::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => $now,
            ]);

            // Load all files in the batch
            $files = $batch->files()->get();

            // Update batch items to verified
            $batch->items()->update([
                'status' => DisposalBatch::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => $now,
            ]);

            // Create audit log for each file
            foreach ($files as $file) {
                DisposalAudit::create([
                    'batch_id' => $batch->id,
                    'file_id' => $file->id,
                    'user_id' => $user->id,
                    'action' => 'completed',
                    'description' => sprintf('File %s verified for disposal.', $file->record_id ?? $file->id),
                    'meta' => [
                        'batch_reference' => $batch->reference,
                        'retention_status' => $file->retention_status,
                    ],
                ]);
            }

            // Create batch-level audit log
            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $user->id,
                'action' => 'completed',
                'description' => 'Batch verified and ready for disposal processing',
                'meta' => [
                    'completed_at' => DateTimeFormatter::toIso8601($now),
                    'file_count' => $batch->file_count,
                ],
            ]);
        });

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', 'Batch verified successfully and is ready for disposal processing.');
    }

    private function finalizeFile(File $file): void
    {
        // Mark as disposed while preserving uploaded file copies and metadata.
        $file->forceFill([
            'retention_status' => RetentionStatus::DISPOSED,
        ])->save();
    }

    public function availableFiles(Request $request): JsonResponse
    {
        $query = File::query()
            ->with([
                'folder:id,name,parent_id',
                'institution:id,name',
                'department:id,name',
                'uploader:id,name',
                'currentVersion:id,file_id,file_name,file_size,version_number,mime_type',
            ])
            ->withCount('versions')
            ->where('is_record', true)
            ->whereIn('retention_status', [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL])
            ->where(function ($eligible) {
                $eligible
                    ->whereNull('retention_duration')
                    ->orWhere('retention_duration', '!=', RetentionSchedule::DURATION_PERPETUAL);
            })
            // Exclude scan files awaiting review
            ->whereNotIn('status', ['draft', 'pending'])
            // Additional safety: exclude files with SCAN- record IDs
            ->where('record_id', 'NOT LIKE', 'SCAN-%')
            ->whereDoesntHave('disposalBatchFiles', function ($query) {
                $query->where(function ($q) {
                    // Exclude if in SCHEDULED batch (always exclude)
                    $q->whereHas('batch', function ($batchQuery) {
                        $batchQuery->where('status', DisposalBatch::STATUS_SCHEDULED);
                    });
                })->orWhere(function ($q) {
                    // Exclude files already tied to active/non-editable workflow batches.
                    $q->whereHas('batch', function ($batchQuery) {
                        $batchQuery->whereIn('status', DisposalBatch::lockedWorkflowStatuses());
                    });
                });
            });

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply urgency filter
        if ($request->filled('urgency') && $request->urgency !== 'all') {
            $this->applyUrgencyFilter($query, $request->urgency);
        }

        // Apply retention duration filter
        if ($request->filled('retention_duration')) {
            $query->where('retention_duration', $request->retention_duration);
        }

        // Apply folder filter
        if ($request->filled('root_folder_id')) {
            $query->whereIn('folder_id', $this->getDescendantFolderIds($request->root_folder_id));
        }

        // Apply department filter
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Apply expiry date filters
        if ($request->filled('expires_from')) {
            $query->whereDate('retention_expires_at', '>=', $request->expires_from);
        }

        if ($request->filled('expires_to')) {
            $query->whereDate('retention_expires_at', '<=', $request->expires_to);
        }

        // Apply released date filters
        if ($request->filled('released_from')) {
            $query->whereDate('date_released', '>=', $request->released_from);
        }

        if ($request->filled('released_to')) {
            $query->whereDate('date_released', '<=', $request->released_to);
        }

        // Get all folders for trail calculation
        $folders = Folder::query()
            ->select('id', 'parent_id', 'name')
            ->get()
            ->keyBy('id');

        // Paginate results (20 per page)
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination
        $files = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Sort with immediate urgency first
        $sortedFiles = $files->sortBy(function ($file) {
            $urgency = $this->calculateFileUrgency($file);
            $urgencyOrder = ['immediate' => 1, 'soon' => 2, 'scheduled' => 3, 'perpetual' => 4];

            return $urgencyOrder[$urgency] ?? 5;
        })->values();

        return response()->json([
            'files' => $sortedFiles->map(function ($file) use ($folders) {
                $urgency = $this->calculateFileUrgency($file);
                $daysUntilExpiry = $this->calculateFileDaysUntilExpiry($file);
                $trail = $this->buildFolderTrail($file->folder_id, $folders);
                $root = $trail[0] ?? null;

                $physicalTags = $file->tags
                    ->where('type', 'physical_location')
                    ->map(fn ($tag) => [
                        'id' => (int) $tag->id,
                        'name' => $tag->name,
                    ])
                    ->values()
                    ->all();

                $currentVersion = $file->currentVersion;

                return [
                    'id' => $file->id,
                    'record_id' => $file->record_id,
                    'file_name' => $file->displayName(),
                    'author' => $file->author,
                    'file_path' => $file->file_path,
                    'folder' => [
                        'trail' => $trail,
                        'root' => $root,
                    ],
                    'institution' => $file->institution?->only(['id', 'name']),
                    'department' => $file->department?->only(['id', 'name']),
                    'uploader' => $file->uploader?->only(['id', 'name']),
                    'retention' => [
                        'duration' => $file->retention_duration,
                        'expires_at' => DateTimeFormatter::toDateString($file->retention_expires_at),
                    ],
                    'physical' => [
                        'location' => $file->physical_location,
                        'tags' => $physicalTags,
                    ],
                    'electronic' => [
                        'version_count' => (int) $file->versions_count,
                        'latest_version' => $file->latest_version_number,
                        'current_version' => [
                            'name' => $currentVersion?->file_name,
                            'size' => $currentVersion?->file_size ?? $file->file_size,
                            'mime_type' => $currentVersion?->mime_type ?? $file->mime_type,
                            'version_number' => $currentVersion?->version_number,
                        ],
                    ],
                    'date_created' => DateTimeFormatter::toDateString($file->date_created),
                    'date_released' => DateTimeFormatter::toDateString($file->date_released),
                    'retention_status' => $file->retention_status,
                    'urgency' => $urgency,
                    'days_until_expiry' => $daysUntilExpiry,
                ];
            }),
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    private function applyUrgencyFilter($query, string $urgency): void
    {
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        match ($urgency) {
            'immediate' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '<', $tomorrow),
            'soon' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>=', $tomorrow)
                ->where('retention_expires_at', '<=', $today->copy()->addDays(30)),
            'scheduled' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>', $today->copy()->addDays(30)),
            'perpetual' => $query->where('retention_duration', RetentionSchedule::DURATION_PERPETUAL)
                ->whereNull('retention_expires_at'),
            default => null,
        };
    }

    private function calculateFileUrgency(File $file): string
    {
        if ($file->retention_duration === RetentionSchedule::DURATION_PERPETUAL || $file->retention_expires_at === null) {
            return 'perpetual';
        }

        $now = now()->startOfDay();
        $expiresAt = \Carbon\Carbon::parse($file->retention_expires_at)->startOfDay();
        $daysUntilExpiry = $now->diffInDays($expiresAt, false);

        if ($daysUntilExpiry < 1) {
            return 'immediate';
        }

        if ($daysUntilExpiry <= 30) {
            return 'soon';
        }

        return 'scheduled';
    }

    private function calculateFileDaysUntilExpiry(File $file): ?int
    {
        if ($file->retention_duration === RetentionSchedule::DURATION_PERPETUAL || $file->retention_expires_at === null) {
            return null;
        }

        $now = now()->startOfDay();
        $expiresAt = \Carbon\Carbon::parse($file->retention_expires_at)->startOfDay();

        return (int) $now->diffInDays($expiresAt, false);
    }

    private function getDescendantFolderIds(string $rootId): array
    {
        $folders = Folder::query()
            ->select('id', 'parent_id')
            ->get()
            ->groupBy('parent_id');

        $ids = [$rootId];
        $stack = [$rootId];

        while (! empty($stack)) {
            $current = array_pop($stack);
            $children = $folders->get($current);

            if ($children === null) {
                continue;
            }

            foreach ($children as $child) {
                $ids[] = (string) $child->id;
                $stack[] = (string) $child->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function buildFolderTrail(?string $folderId, $folderIndex): array
    {
        if ($folderId === null) {
            return [];
        }

        $segments = [];
        $current = $folderId;

        while ($current !== null) {
            $folder = $folderIndex->get($current);

            if ($folder === null) {
                break;
            }

            array_unshift($segments, [
                'id' => (string) $folder->id,
                'name' => $folder->name,
            ]);

            $current = $folder->parent_id;
        }

        return $segments;
    }

    public function addFiles(Request $request, DisposalBatch $batch): RedirectResponse
    {
        if (in_array($batch->status, DisposalBatch::lockedWorkflowStatuses(), true)) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Cannot add files to a batch that has already been verified or progressed.');
        }

        $validated = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['required', 'string', 'exists:files,id'],
        ]);

        $user = $request->user();
        $fileIds = $validated['file_ids'];

        $files = File::query()
            ->with('physicalLocationTags:id,name')
            ->whereIn('id', $fileIds)
            ->where('is_record', true)
            ->whereIn('retention_status', [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL])
            ->where(function ($eligible) {
                $eligible
                    ->whereNull('retention_duration')
                    ->orWhere('retention_duration', '!=', RetentionSchedule::DURATION_PERPETUAL);
            })
            ->where('record_id', 'NOT LIKE', 'SCAN-%')
            ->whereDoesntHave('disposalBatchFiles', function ($query) {
                $query->where(function ($q) {
                    // Exclude if in SCHEDULED batch (always exclude)
                    $q->whereHas('batch', function ($batchQuery) {
                        $batchQuery->where('status', DisposalBatch::STATUS_SCHEDULED);
                    });
                })->orWhere(function ($q) {
                    // Exclude files already tied to active/non-editable workflow batches.
                    $q->whereHas('batch', function ($batchQuery) {
                        $batchQuery->whereIn('status', DisposalBatch::lockedWorkflowStatuses());
                    });
                });
            })
            ->get();

        if ($files->count() !== count($fileIds)) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Some files are not available to add to this batch.');
        }

        $addedCount = 0;
        foreach ($files as $file) {
            $batch->items()->create([
                'file_id' => $file->id,
                'status' => $batch->status,
                'scheduled_by' => $user->id,
                'scheduled_at' => $batch->scheduled_at ?? now(),
                'physical_snapshot' => [
                    'location' => $file->physical_location,
                    'tags' => $file->physicalLocationTags
                        ->map(fn ($tag) => [
                            'id' => (int) $tag->id,
                            'name' => $tag->name,
                        ])
                        ->values()
                        ->all(),
                ],
            ]);

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'user_id' => $user->id,
                'action' => 'added_to_batch',
                'description' => "File {$file->record_id} added to batch {$batch->reference}",
                'meta' => [
                    'batch_reference' => $batch->reference,
                    'retention_status' => $file->retention_status,
                ],
            ]);

            $addedCount++;
        }

        $batch->update([
            'file_count' => $batch->items()->count(),
        ]);

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', "{$addedCount} file(s) added to batch successfully.");
    }

    public function removeFile(Request $request, DisposalBatch $batch, string $fileId): RedirectResponse
    {
        // Prevent removing files from locked batches (completed, inspected, approved, or rejected)
        $lockedStatuses = DisposalBatch::lockedWorkflowStatuses();

        if (in_array($batch->status, $lockedStatuses)) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Cannot remove files from a batch that has been completed, inspected, approved, or rejected.');
        }

        $batchFile = $batch->items()->where('file_id', $fileId)->first();

        if (! $batchFile) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'File not found in this batch.');
        }

        $user = $request->user();
        $file = $batchFile->file;

        // Remove the file from the batch
        $batchFile->delete();

        // Update batch file count
        $batch->update([
            'file_count' => $batch->items()->count(),
        ]);

        // Create audit log
        DisposalAudit::create([
            'batch_id' => $batch->id,
            'file_id' => $fileId,
            'user_id' => $user->id,
            'action' => 'removed_from_batch',
            'description' => "File {$file->record_id} removed from batch {$batch->reference}",
            'meta' => [
                'batch_reference' => $batch->reference,
                'retention_status' => $file->retention_status,
            ],
        ]);

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', 'File removed from batch successfully.');
    }

    public function destroy(Request $request, DisposalBatch $batch): RedirectResponse
    {
        // Only allow deleting scheduled batches
        // Completed, inspected, approved, and rejected batches must be preserved for historical records
        $lockedStatuses = DisposalBatch::lockedWorkflowStatuses();

        if (in_array($batch->status, $lockedStatuses)) {
            return redirect()
                ->route('disposal-batches.index')
                ->with('error', 'Cannot delete a batch that has been completed, inspected, approved, or rejected. These batches must be kept for audit trail.');
        }

        $user = $request->user();
        $batchId = $batch->id;
        $reference = $batch->reference;
        $fileCount = $batch->items()->count();

        // Create audit log before deletion (without batch_id to prevent cascade deletion)
        DisposalAudit::create([
            'batch_id' => null,
            'user_id' => $user->id,
            'action' => 'batch_deleted',
            'description' => "Batch {$reference} with {$fileCount} file(s) was deleted",
            'meta' => [
                'batch_id' => $batchId,
                'batch_reference' => $reference,
                'file_count' => $fileCount,
                'status' => $batch->status,
            ],
        ]);

        // Delete all batch items
        $batch->items()->delete();

        // Delete the batch
        $batch->delete();

        return redirect()
            ->route('disposal-batches.index')
            ->with('success', "Batch {$reference} has been deleted successfully.");
    }

    public function napForm3Preview(Request $request, DisposalBatch $batch, NapForm3Service $napService): Response
    {
        $allowedStatuses = [
            DisposalBatch::STATUS_COMPLETED,
            DisposalBatch::STATUS_REQUEST_LETTER_READY,
            DisposalBatch::STATUS_INSPECTED,
            DisposalBatch::STATUS_APPROVED,
            DisposalBatch::STATUS_COMPLETE,
            DisposalBatch::STATUS_REJECTED,
        ];

        if (! in_array($batch->status, $allowedStatuses)) {
            abort(403, 'NAP Form 3 can only be generated for completed, inspected, approved/complete, or rejected batches.');
        }

        $workflowFilesCount = $batch->items()->whereHas('file', function ($query) {
            $query->whereNotNull('id');
        })->count();
        if ($workflowFilesCount === 0) {
            abort(403, 'Cannot generate NAP Form 3: this batch has no files.');
        }

        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        $napForm1Data = Arr::get($metadata, 'nap_form_1');

        if (! $batch->nap_form_1_generated_at || ! is_array($napForm1Data) || empty($napForm1Data['prepared_by_user_id'])) {
            abort(403, 'NAP Form 1 must be finalized before generating NAP Form 3.');
        }

        $defaultData = $napService->prepareDefaultData($batch);
        $savedNapData = Arr::get($metadata, 'nap_certificate');

        $totalVolume = 0;
        if (is_array($napForm1Data) && isset($napForm1Data['items']) && is_array($napForm1Data['items'])) {
            foreach ($napForm1Data['items'] as $item) {
                if (isset($item['volume']) && is_numeric($item['volume'])) {
                    $totalVolume += (float) $item['volume'];
                }
            }
        }

        if (is_array($savedNapData)) {
            $defaultData = array_merge($defaultData, Arr::only($savedNapData, [
                'date',
                'location_of_records',
                'prepared_by_user_id',
                'prepared_by_name',
                'prepared_by_position',
            ]));

            if (array_key_exists('volume_in_cubic_meter', $savedNapData) && $savedNapData['volume_in_cubic_meter'] !== '') {
                $defaultData['volume_in_cubic_meter'] = $savedNapData['volume_in_cubic_meter'];
            } else {
                $defaultData['volume_in_cubic_meter'] = $totalVolume > 0 ? (string)$totalVolume : '';
            }

            if (isset($savedNapData['items']) && is_array($savedNapData['items'])) {
                $defaultData['items'] = $savedNapData['items'];
            }
        } else {
            $defaultData['volume_in_cubic_meter'] = $totalVolume > 0 ? (string)$totalVolume : '';
        }

        $defaultData['prepared_by_user_id'] = $napForm1Data['prepared_by_user_id'];
        $defaultData['prepared_by_name'] = $napForm1Data['prepared_by_name'] ?? '';
        $defaultData['prepared_by_position'] = $napForm1Data['prepared_by_position'] ?? '';

        $systemConfig = SystemConfiguration::getInstance();
        $defaultData['agency_name'] = $systemConfig->agency_name ?? $defaultData['agency_name'];
        $defaultData['address'] = $systemConfig->address ?? $defaultData['address'];
        $defaultData['telephone_number'] = $systemConfig->telephone ?? $defaultData['telephone_number'];
        $defaultData['certified_by_name'] = $systemConfig->regional_director_name ?? $defaultData['certified_by_name'];
        $defaultData['certified_by_designation'] = $systemConfig->regional_director_designation ?? $defaultData['certified_by_designation'];

        $napReportFileExists = $batch->nap_report_path
            ? file_exists(storage_path('app/'.$batch->nap_report_path))
            : false;

        return Inertia::render('NapForm3Preview', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'nap_report_generated_at' => $batch->nap_report_generated_at,
                'nap_report_file_exists' => $napReportFileExists,
            ],
            'defaultData' => $defaultData,
        ]);
    }

    public function napForm1Preview(Request $request, DisposalBatch $batch, NapForm1Service $napForm1Service): Response
    {
        $allowedStatuses = [
            DisposalBatch::STATUS_COMPLETED,
            DisposalBatch::STATUS_REQUEST_LETTER_READY,
            DisposalBatch::STATUS_INSPECTED,
            DisposalBatch::STATUS_APPROVED,
            DisposalBatch::STATUS_COMPLETE,
            DisposalBatch::STATUS_REJECTED,
        ];

        if (! in_array($batch->status, $allowedStatuses, true)) {
            abort(403, 'NAP Form 1 can only be generated for completed, inspected, approved/complete, or rejected batches.');
        }

        $workflowFilesCount = $batch->items()->whereHas('file', function ($query) {
            $query->whereNotNull('id');
        })->count();
        if ($workflowFilesCount === 0) {
            abort(403, 'Cannot generate NAP Form 1: this batch has no files.');
        }

        $defaultData = $napForm1Service->prepareDefaultData($batch, $request->user());
        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        $savedNapForm1Data = Arr::get($metadata, 'nap_form_1');

        if (is_array($savedNapForm1Data)) {
            $defaultData = array_merge($defaultData, Arr::only($savedNapForm1Data, [
                'organizational_unit',
                'section_unit',
                'email',
                'person_in_charge',
                'date_prepared',
                'prepared_by_user_id',
                'prepared_by_name',
                'prepared_by_position',
                'prepared_by_role',
                'assisted_by_name',
                'assisted_by_designation',
                'assisted_by_role',
                'approved_by_name',
                'approved_by_designation',
                'approved_by_role',
                'volume_in_cubic_meter',
                'location_of_records',
                'frequency_of_use',
                'time_value',
                'utility_value',
                'retention_period',
                'disposition_provision',
            ]));

            if (isset($savedNapForm1Data['items']) && is_array($savedNapForm1Data['items'])) {
                $mergedItems = [];
                foreach ($savedNapForm1Data['items'] as $index => $savedItem) {
                    $defaultItem = $defaultData['items'][$index] ?? [];
                    
                    $savedItem['retention_total'] = !empty($savedItem['retention_total']) 
                        ? $savedItem['retention_total'] 
                        : ($defaultItem['retention_total'] ?? '');
                        
                    $savedItem['medium'] = !empty($savedItem['medium']) 
                        ? $savedItem['medium'] 
                        : ($defaultItem['medium'] ?? '');
                        
                    $savedItem['restriction'] = !empty($savedItem['restriction']) 
                        ? $savedItem['restriction'] 
                        : ($defaultItem['restriction'] ?? '');
                        
                    $mergedItems[] = $savedItem;
                }
                $defaultData['items'] = $mergedItems;
            }
        }

        $systemConfig = SystemConfiguration::getInstance();
        $defaultData['agency'] = $systemConfig->agency_name ?? $defaultData['agency'];
        $defaultData['address'] = $systemConfig->address ?? $defaultData['address'];
        $defaultData['telephone'] = $systemConfig->telephone ?? $defaultData['telephone'];

        $users = User::query()
            ->whereHas('roles.permissions', function ($query) {
                $query->where('slug', 'disposal-management');
            })
            ->with(['position:id,name', 'roles:id,name'])
            ->select(['id', 'name', 'email', 'position_id'])
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position?->name,
                'role' => $user->roles->pluck('name')->filter()->values()->implode(', '),
            ]);

        $currentUser = tap($request->user())->loadMissing(['position:id,name', 'roles:id,name']);

        $napForm1FileExists = $batch->nap_form_1_path
            ? file_exists(storage_path('app/'.$batch->nap_form_1_path))
            : false;

        return Inertia::render('NapForm1Preview', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'nap_form_1_generated_at' => DateTimeFormatter::toIso8601($batch->nap_form_1_generated_at),
                'nap_form_1_file_exists' => $napForm1FileExists,
            ],
            'defaultData' => $defaultData,
            'users' => $users,
            'currentUser' => [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'position' => $currentUser->position?->name,
                'role' => $currentUser->roles->pluck('name')->filter()->values()->implode(', '),
            ],
        ]);
    }

    public function napForm1Generate(Request $request, DisposalBatch $batch, NapForm1Service $napForm1Service): RedirectResponse|JsonResponse
    {
        \Log::info('NAP Form 1 generate requested', [
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'user_id' => $request->user()?->id,
        ]);

        $allowedStatuses = [
            DisposalBatch::STATUS_COMPLETED,
            DisposalBatch::STATUS_REQUEST_LETTER_READY,
            DisposalBatch::STATUS_INSPECTED,
            DisposalBatch::STATUS_APPROVED,
            DisposalBatch::STATUS_COMPLETE,
            DisposalBatch::STATUS_REJECTED,
        ];
        $previewSyncMode = $request->boolean('preview_sync');

        if (! in_array($batch->status, $allowedStatuses, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'NAP Form 1 can only be generated for completed, inspected, approved/complete, or rejected batches.',
                ], 422);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'NAP Form 1 can only be generated for completed, inspected, approved/complete, or rejected batches.');
        }

        $workflowFilesCount = $batch->items()->whereHas('file', function ($query) {
            $query->whereNotNull('id');
        })->count();
        if ($workflowFilesCount === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cannot generate NAP Form 1: this batch has no files.',
                ], 422);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Cannot generate NAP Form 1: this batch has no files.');
        }

        $validated = $request->validate([
            'agency' => ['required', 'string', 'max:500'],
            'organizational_unit' => ['required', 'string', 'max:500'],
            'section_unit' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'string', 'max:500'],
            'telephone' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'person_in_charge' => ['required', 'string', 'max:255'],
            'date_prepared' => ['required', 'string', 'max:100'],
            'prepared_by_user_id' => ['required', 'exists:users,id'],
            'prepared_by_name' => ['required', 'string', 'max:255'],
            'prepared_by_position' => ['required', 'string', 'max:255'],
            'prepared_by_role' => ['required', 'string', 'max:255'],
            'assisted_by_name' => ['nullable', 'string', 'max:255'],
            'assisted_by_designation' => ['nullable', 'string', 'max:255'],
            'assisted_by_role' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_designation' => ['nullable', 'string', 'max:255'],
            'approved_by_role' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.title' => ['required', 'string'],
            'items.*.period' => ['nullable', 'string'],
            'items.*.volume' => ['nullable', 'integer', 'min:0'],
            'items.*.location' => ['nullable', 'string'],
            'items.*.frequency' => ['nullable', 'string'],
            'items.*.duplication' => ['nullable', 'string'],
            'items.*.time_value' => ['nullable', 'string'],
            'items.*.utility_value' => ['nullable', 'string'],
            'items.*.retention_active' => ['nullable', 'string'],
            'items.*.retention_storage' => ['nullable', 'string'],
            'items.*.retention_total' => ['nullable', 'string'],
            'items.*.provision' => ['nullable', 'string'],
            'items.*.medium' => ['nullable', 'string'],
            'items.*.restriction' => ['nullable', 'string'],
        ]);

        $systemConfig = SystemConfiguration::getInstance();
        $validated['agency'] = $systemConfig->agency_name ?? $validated['agency'];
        $validated['address'] = $systemConfig->address ?? $validated['address'];
        $validated['telephone'] = $systemConfig->telephone ?? $validated['telephone'];

        try {
            $pdfPath = $napForm1Service->generatePdf($batch, $validated);
            \Log::info('NAP Form 1 PDF generated', [
                'batch_id' => $batch->id,
                'pdf_path' => $pdfPath,
            ]);

            if ($request->expectsJson() && $previewSyncMode) {
                return response()->json([
                    'message' => 'NAP Form 1 preview PDF synced successfully.',
                    'preview_path' => $pdfPath,
                ]);
            }

            $metadata = is_array($batch->metadata) ? $batch->metadata : [];
            $metadata['nap_form_1'] = Arr::only($validated, [
                'agency',
                'organizational_unit',
                'section_unit',
                'email',
                'telephone',
                'address',
                'person_in_charge',
                'date_prepared',
                'prepared_by_user_id',
                'prepared_by_name',
                'prepared_by_position',
                'prepared_by_role',
                'assisted_by_name',
                'assisted_by_designation',
                'assisted_by_role',
                'approved_by_name',
                'approved_by_designation',
                'approved_by_role',
                'items',
            ]);

            $batch->update([
                'nap_form_1_path' => $pdfPath,
                'nap_form_1_stage' => 'generated',
                'nap_form_1_generated_by' => $request->user()->id,
                'nap_form_1_generated_at' => now(),
                'metadata' => $metadata,
            ]);

            \Log::info('NAP Form 1 batch updated', [
                'batch_id' => $batch->id,
                'nap_form_1_path' => $batch->fresh()->nap_form_1_path,
            ]);

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $request->user()->id,
                'action' => 'nap_form_1_generated',
                'description' => 'NAP Form 1 generated as PDF',
                'meta' => [
                    'pdf_path' => $pdfPath,
                ],
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'NAP Form 1 generated successfully.',
                ]);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'NAP Form 1 generated successfully.');
        } catch (\Exception $e) {
            \Log::error('NAP Form 1 generate failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to generate NAP Form 1: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to generate NAP Form 1: '.$e->getMessage());
        }
    }

    public function napForm1Download(DisposalBatch $batch, Request $request, NapForm1Service $napForm1Service): BinaryFileResponse
    {
        $previewPath = $request->query('preview_path');
        if (is_string($previewPath) && $previewPath !== '') {
            if (! str_starts_with($previewPath, 'public/nap-form-1s/')) {
                abort(403, 'Invalid preview path.');
            }

            $previewFullPath = storage_path('app/'.$previewPath);
            if (! file_exists($previewFullPath)) {
                abort(404, 'NAP Form 1 preview file not found.');
            }

            return response()->file($previewFullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($previewFullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        \Log::info('NAP Form 1 download requested', [
            'batch_id' => $batch->id,
            'nap_form_1_path' => $batch->nap_form_1_path,
        ]);

        if (! $batch->nap_form_1_path) {
            \Log::warning('NAP Form 1 path not set, attempting regeneration from metadata', ['batch_id' => $batch->id]);

            $metadata = is_array($batch->metadata) ? $batch->metadata : [];
            $savedNapForm1Data = Arr::get($metadata, 'nap_form_1');

            if (! is_array($savedNapForm1Data)) {
                \Log::error('NAP Form 1 metadata not available for regeneration', ['batch_id' => $batch->id]);
                abort(404, 'NAP Form 1 not found.');
            }

            $regeneratedPath = $napForm1Service->generatePdf($batch, $savedNapForm1Data);
            $batch->forceFill([
                'nap_form_1_path' => $regeneratedPath,
                'nap_form_1_stage' => 'generated',
                'nap_form_1_generated_at' => now(),
            ])->save();

            $batch->refresh();

            \Log::info('NAP Form 1 regenerated during download', [
                'batch_id' => $batch->id,
                'path' => $batch->nap_form_1_path,
            ]);
        }

        $fullPath = storage_path('app/'.$batch->nap_form_1_path);
        \Log::info('NAP Form 1 checking file', [
            'batch_id' => $batch->id,
            'path' => $fullPath,
            'exists' => file_exists($fullPath),
        ]);

        if (! file_exists($fullPath)) {
            \Log::warning('NAP Form 1 file missing, attempting regeneration from metadata', [
                'batch_id' => $batch->id,
                'path' => $fullPath,
            ]);

            $metadata = is_array($batch->metadata) ? $batch->metadata : [];
            $savedNapForm1Data = Arr::get($metadata, 'nap_form_1');

            if (! is_array($savedNapForm1Data)) {
                \Log::error('NAP Form 1 file missing and metadata unavailable', [
                    'batch_id' => $batch->id,
                ]);
                abort(404, 'NAP Form 1 file not found.');
            }

            $regeneratedPath = $napForm1Service->generatePdf($batch, $savedNapForm1Data);
            $batch->forceFill([
                'nap_form_1_path' => $regeneratedPath,
                'nap_form_1_stage' => 'generated',
                'nap_form_1_generated_at' => now(),
            ])->save();

            $batch->refresh();
            $fullPath = storage_path('app/'.$batch->nap_form_1_path);

            \Log::info('NAP Form 1 regenerated after missing file', [
                'batch_id' => $batch->id,
                'path' => $batch->nap_form_1_path,
                'exists' => file_exists($fullPath),
            ]);

            if (! file_exists($fullPath)) {
                \Log::error('NAP Form 1 regeneration failed to produce file', [
                    'batch_id' => $batch->id,
                    'path' => $fullPath,
                ]);
                abort(404, 'NAP Form 1 file not found.');
            }
        }

        if ($request->query('disposition') === 'inline') {
            return response()->file($fullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($fullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $batch->forceFill([
            'nap_form_1_downloaded_at' => now(),
        ])->save();

        $response = response()->download($fullPath);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function napForm3Generate(Request $request, DisposalBatch $batch, NapForm3Service $napService): RedirectResponse|JsonResponse
    {
        $allowedStatuses = [
            DisposalBatch::STATUS_COMPLETED,
            DisposalBatch::STATUS_REQUEST_LETTER_READY,
            DisposalBatch::STATUS_INSPECTED,
            DisposalBatch::STATUS_APPROVED,
            DisposalBatch::STATUS_COMPLETE,
            DisposalBatch::STATUS_REJECTED,
        ];

        $previewSyncMode = $request->boolean('preview_sync');

        if (! in_array($batch->status, $allowedStatuses, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'NAP Form 3 can only be generated for completed, inspected, approved/complete, or rejected batches.',
                ], 422);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'NAP Form 3 can only be generated for completed, inspected, approved/complete, or rejected batches.');
        }

        $workflowFilesCount = $batch->items()->whereHas('file', function ($query) {
            $query->whereNotNull('id');
        })->count();
        if ($workflowFilesCount === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cannot generate NAP Form 3: this batch has no files.',
                ], 422);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Cannot generate NAP Form 3: this batch has no files.');
        }

        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        $napForm1Data = Arr::get($metadata, 'nap_form_1');
        $napForm1Error = 'NAP Form 1 must be finalized before generating NAP Form 3.';

        if (! $batch->nap_form_1_generated_at || ! is_array($napForm1Data) || empty($napForm1Data['prepared_by_user_id'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $napForm1Error,
                ], 422);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', $napForm1Error);
        }

        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:500'],
            'address' => ['required', 'string', 'max:500'],
            'date' => ['required', 'string', 'max:100'],
            'telephone_number' => ['nullable', 'string', 'max:100'],
            'location_of_records' => ['required', 'string', 'max:500'],
            'volume_in_cubic_meter' => ['nullable', 'string', 'max:100'],
            'prepared_by_user_id' => ['required', 'exists:users,id'],
            'prepared_by_name' => ['required', 'string', 'max:255'],
            'prepared_by_position' => ['nullable', 'string', 'max:255'],
            'certified_by_name' => ['required', 'string', 'max:255'],
            'certified_by_designation' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.grds_rds_item_no' => ['required', 'string'],
            'items.*.record_series_title' => ['required', 'string'],
            'items.*.period_covered' => ['nullable', 'string'],
            'items.*.retention_period' => ['nullable', 'string'],
        ]);

        $validated['prepared_by_user_id'] = $napForm1Data['prepared_by_user_id'];
        $validated['prepared_by_name'] = $napForm1Data['prepared_by_name'] ?? '';
        $validated['prepared_by_position'] = $napForm1Data['prepared_by_position'] ?? '';

        $systemConfig = SystemConfiguration::getInstance();
        $validated['agency_name'] = $systemConfig->agency_name ?? $validated['agency_name'];
        $validated['address'] = $systemConfig->address ?? $validated['address'];
        $validated['telephone_number'] = $systemConfig->telephone ?? $validated['telephone_number'];
        $validated['certified_by_name'] = $systemConfig->regional_director_name ?? $validated['certified_by_name'];
        $validated['certified_by_designation'] = $systemConfig->regional_director_designation ?? $validated['certified_by_designation'];

        try {
            $pdfPath = $napService->generatePdf($batch, $validated);

            if ($request->expectsJson() && $previewSyncMode) {
                return response()->json([
                    'message' => 'NAP Form 3 preview PDF synced successfully.',
                    'preview_path' => $pdfPath,
                ]);
            }

            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['nap_certificate'] = Arr::only($validated, [
                'agency_name',
                'address',
                'date',
                'telephone_number',
                'location_of_records',
                'volume_in_cubic_meter',
                'prepared_by_user_id',
                'prepared_by_name',
                'prepared_by_position',
                'certified_by_name',
                'certified_by_designation',
                'items',
            ]);

            // Update batch with NAP report info
            $batch->update([
                'nap_report_path' => $pdfPath,
                'nap_report_stage' => 'generated',
                'nap_report_generated_by' => $request->user()->id,
                'nap_report_generated_at' => now(),
                'metadata' => $metadata,
            ]);

            // Create audit log
            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $request->user()->id,
                'action' => 'nap_certificate_generated',
                'description' => 'NAP Form 3 generated as PDF',
                'meta' => [
                    'pdf_path' => $pdfPath,
                ],
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'NAP Form 3 generated successfully.',
                    'nap_report_path' => $pdfPath,
                ]);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'NAP Form 3 generated successfully.');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to generate NAP Form 3: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to generate NAP Form 3: '.$e->getMessage());
        }
    }

    public function napForm3Download(DisposalBatch $batch, Request $request): BinaryFileResponse
    {
        $previewPath = $request->query('preview_path');
        if (is_string($previewPath) && $previewPath !== '') {
            if (! str_starts_with($previewPath, 'public/nap-form-3s/')) {
                abort(403, 'Invalid preview path.');
            }

            $previewFullPath = storage_path('app/'.$previewPath);
            if (! file_exists($previewFullPath)) {
                abort(404, 'NAP Form 3 preview file not found.');
            }

            return response()->file($previewFullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($previewFullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        \Log::info('Download requested', [
            'batch_id' => $batch->id,
            'nap_report_path' => $batch->nap_report_path,
        ]);

        if (! $batch->nap_report_path) {
            \Log::error('NAP Form 3 path not set', ['batch_id' => $batch->id]);
            abort(404, 'NAP Form 3 not found.');
        }

        $filePath = $batch->nap_report_path;
        $fullPath = storage_path('app/'.$filePath);

        \Log::info('Checking file', [
            'path' => $fullPath,
            'exists' => file_exists($fullPath),
        ]);

        if (! file_exists($fullPath)) {
            \Log::error('NAP Form 3 file does not exist', [
                'batch_id' => $batch->id,
                'path' => $fullPath,
            ]);
            abort(404, 'NAP Form 3 file not found.');
        }

        if ($request->query('disposition') === 'inline') {
            return response()->file($fullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($fullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $batch->forceFill([
            'nap_report_downloaded_at' => now(),
        ])->save();

        $response = response()->download($fullPath);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function requestLetterPreview(Request $request, DisposalBatch $batch, RequestLetterService $requestLetterService): Response
    {
        if (! $batch->nap_report_path) {
            abort(403, 'NAP Form 3 must be generated before creating the Request Letter.');
        }

        $currentUser = tap($request->user())->loadMissing('position');

        $defaultData = $requestLetterService->prepareDefaultData($batch, [
            'name' => $currentUser->name,
            'position' => $currentUser->position?->name ?? '',
        ]);

        $config = SystemConfiguration::getInstance();
        $requestLetterFileExists = $batch->request_letter_path
            ? file_exists(storage_path('app/'.$batch->request_letter_path))
            : false;

        return Inertia::render('Disposal/RequestLetterPreview', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'request_letter_path' => $batch->request_letter_path,
                'request_letter_generated_at' => DateTimeFormatter::toIso8601($batch->request_letter_generated_at),
                'request_letter_file_exists' => $requestLetterFileExists,
            ],
            'defaultData' => $defaultData,
            'director' => [
                'name' => $config->regional_director_name ?? 'MARICAR R. CASQUEJO, Ph.D., CESO III',
                'designation' => $config->regional_director_designation ?? 'Director IV',
            ],
            'currentUser' => [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'position' => $currentUser->position?->name,
            ],
        ]);
    }

    public function requestLetterGenerate(GenerateRequestLetterRequest $request, DisposalBatch $batch, RequestLetterService $requestLetterService): RedirectResponse|JsonResponse
    {
        if (! $batch->nap_report_path) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'NAP Form 3 must be generated before creating the Request Letter.');
        }

        $napReportRelativePath = $batch->nap_report_path;
        $napReportFullPath = storage_path('app/'.$napReportRelativePath);

        if (! file_exists($napReportFullPath)) {
            $missingNapMessage = 'NAP Form 3 file is missing. Please regenerate NAP Form 3 before generating the Request Letter.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $missingNapMessage,
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $missingNapMessage);
        }

        $previewSyncMode = $request->boolean('preview_sync');
        $validated = $request->validated();
        $user = tap($request->user())->loadMissing('position');

        $validated['recipient_name'] = $requestLetterService->composeRecipientName(
            $validated['recipient_first_name'] ?? '',
            $validated['recipient_middle_name'] ?? '',
            $validated['recipient_last_name'] ?? '',
            $validated['recipient_extension'] ?? '',
        );

        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        $napForm3Data = $metadata['nap_certificate'] ?? [];
        $napForm1Data = $metadata['nap_form_1'] ?? [];

        if (empty($napForm3Data)) {
            return redirect()
                ->back()
                ->with('error', 'NAP Form 3 data not found. Please regenerate NAP Form 3 first.');
        }

        if (is_array($napForm1Data)) {
            $preparedByUserId = $napForm1Data['prepared_by_user_id'] ?? null;
            $preparedByUser = null;

            if (is_string($preparedByUserId) && $preparedByUserId !== '') {
                $preparedByUser = User::query()
                    ->with('position:id,name')
                    ->select(['id', 'name', 'position_id'])
                    ->find($preparedByUserId);
            }

            $validated['user_name'] = $requestLetterService->formatRequestingOfficerName(
                $preparedByUser?->name
                    ?? ($napForm1Data['prepared_by_name'] ?? $user->name)
            );
            $validated['user_position'] = $preparedByUser?->position?->name
                ?? ($napForm1Data['prepared_by_position'] ?? ($validated['user_position'] ?? ($user->position?->name ?? '')));
        } else {
            $validated['user_name'] = $requestLetterService->formatRequestingOfficerName($user->name);
            $validated['user_position'] = $validated['user_position'] ?? ($user->position?->name ?? '');
        }

        try {
            $pdfPath = $requestLetterService->generatePdf(
                $batch,
                $validated,
                $napReportRelativePath,
                $napReportFullPath,
            );

            if ($request->expectsJson() && $previewSyncMode) {
                return response()->json([
                    'message' => 'Request Letter preview PDF synced successfully.',
                    'preview_path' => $pdfPath,
                ]);
            }

            $metadata['request_letter'] = Arr::only($validated, [
                'letter_date',
                'recipient_name',
                'recipient_first_name',
                'recipient_middle_name',
                'recipient_last_name',
                'recipient_extension',
                'recipient_position',
                'recipient_designation',
                'address_1',
                'address_2',
                'telephone',
                'honorific',
                'user_honorific',
                'user_name',
                'user_position',
                'control_id',
            ]);

            $updates = [
                'request_letter_path' => $pdfPath,
                'request_letter_generated_by' => $user->id,
                'request_letter_generated_at' => now(),
                'metadata' => $metadata,
            ];

            $statusTransition = null;
            if ($batch->status === DisposalBatch::STATUS_COMPLETED) {
                $statusTransition = [
                    'status_from' => DisposalBatch::STATUS_COMPLETED,
                    'status_to' => DisposalBatch::STATUS_REQUEST_LETTER_READY,
                ];
                $updates['status'] = DisposalBatch::STATUS_REQUEST_LETTER_READY;
            }

            $batch->update($updates);

            $meta = ['pdf_path' => $pdfPath];
            if ($statusTransition !== null) {
                $meta = array_merge($meta, $statusTransition);
            }

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $user->id,
                'action' => 'request_letter_generated',
                'description' => 'Request Letter generated as PDF (with NAP Form 3 attached)',
                'meta' => $meta,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Request Letter generated successfully.',
                    'request_letter_path' => $pdfPath,
                ]);
            }

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'Request Letter generated successfully.');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to generate Request Letter: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to generate Request Letter: '.$e->getMessage());
        }
    }

    public function requestLetterDownload(DisposalBatch $batch, Request $request): BinaryFileResponse
    {
        $previewPath = $request->query('preview_path');
        if (is_string($previewPath) && $previewPath !== '') {
            if (! str_starts_with($previewPath, 'public/request-letters/')) {
                abort(403, 'Invalid preview path.');
            }

            $previewFullPath = storage_path('app/'.$previewPath);
            if (! file_exists($previewFullPath)) {
                abort(404, 'Request Letter preview file not found.');
            }

            return response()->file($previewFullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($previewFullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        if (! $batch->request_letter_path) {
            abort(404, 'Request Letter not found.');
        }

        $fullPath = storage_path('app/'.$batch->request_letter_path);

        if (! file_exists($fullPath)) {
            abort(404, 'Request Letter file not found.');
        }

        if ($request->query('disposition') === 'inline') {
            return response()->file($fullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($fullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $batch->forceFill([
            'request_letter_downloaded_at' => now(),
        ])->save();

        $response = response()->download($fullPath);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function endorsementFinalize(Request $request, DisposalBatch $batch, ApprovalLetterService $approvalLetterService): Response
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_INSPECTED, DisposalBatch::STATUS_APPROVED, DisposalBatch::STATUS_COMPLETE], true)) {
            abort(403, 'Only inspected, approved, or completed batches can open approval finalization.');
        }

        $batch->loadMissing([
            'inspectedBy:id,name,email,position_id',
            'inspectedBy.position:id,name',
            'scheduledBy:id,name,email,position_id',
            'scheduledBy.position:id,name',
        ]);

        $metadata = $batch->metadata ?? [];
        $letterDefaults = $approvalLetterService->prepareDefaultData($batch);
        $napDocuments = $approvalLetterService->napDocumentsFor($batch);
        $config = SystemConfiguration::getInstance();
        $currentUser = tap($request->user())->loadMissing('position');
        $approvalLetterFileExists = $batch->approval_letter_path
            ? file_exists(storage_path('app/'.$batch->approval_letter_path))
            : false;

        return Inertia::render('Disposal/EndorsementFinalize', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
                'mode_of_disposal_label' => DisposalBatch::modeLabel($batch->mode_of_disposal),
                'inspected_at' => DateTimeFormatter::toIso8601($batch->inspected_at),
                'scheduled_at' => DateTimeFormatter::toIso8601($batch->scheduled_at),
                'approval_letter_path' => $batch->approval_letter_path,
                'approval_letter_generated_at' => DateTimeFormatter::toIso8601($batch->approval_letter_generated_at),
                'approval_letter_file_exists' => $approvalLetterFileExists,
            ],
            'defaultData' => [
                'approval_notes' => $batch->approval_notes ?? '',
                'letter_reference' => $letterDefaults['letter_reference'],
                'letter_date' => $letterDefaults['letter_date'],
                'actual_disposal_date' => $letterDefaults['actual_disposal_date'],
                'honorific' => $letterDefaults['honorific'],
                'recipient_name' => $letterDefaults['recipient_name'],
                'recipient_first_name' => $letterDefaults['recipient_first_name'],
                'recipient_middle_name' => $letterDefaults['recipient_middle_name'],
                'recipient_last_name' => $letterDefaults['recipient_last_name'],
                'recipient_extension' => $letterDefaults['recipient_extension'],
                'recipient_position' => $letterDefaults['recipient_position'],
                'recipient_designation' => $letterDefaults['recipient_designation'],
                'recipient_address' => $letterDefaults['recipient_address'],
                'coa_name' => Arr::get($metadata, 'approval_review.coa_name') ?? $batch->coa_name ?? $letterDefaults['recipient_name'],
                'coa_position' => Arr::get($metadata, 'approval_review.coa_position')
                    ?? $batch->coa_position
                    ?? CoaContactDefaults::position(),
                'coa_designation' => Arr::get($metadata, 'approval_review.coa_designation')
                    ?? $batch->coa_designation
                    ?? CoaContactDefaults::designation(),
            ],
            'currentUser' => [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'position' => $currentUser->position?->name,
            ],
            'people' => [
                'inspector' => $batch->inspector_name || $batch->inspectedBy ? [
                    'id' => $batch->inspectedBy?->id,
                    'name' => $batch->inspector_name ?? $batch->inspectedBy?->name,
                    'email' => $batch->inspectedBy?->email,
                    'position' => $batch->inspectedBy?->position?->name,
                ] : null,
                'scheduler' => $batch->scheduledBy ? [
                    'id' => $batch->scheduledBy->id,
                    'name' => $batch->scheduledBy->name,
                    'email' => $batch->scheduledBy->email,
                    'position' => $batch->scheduledBy->position?->name,
                ] : null,
            ],
            'director' => [
                'name' => $config->regional_director_name ?? 'MARICAR R. CASQUEJO, Ph.D., CESO III',
                'designation' => $config->regional_director_designation ?? 'Director IV',
            ],
            'maxPictures' => ApproveDisposalBatchRequest::MAX_PICTURES,
            'approvalDocuments' => collect($batch->approval_documents ?? [])->map(fn ($document) => [
                'name' => $document['name'] ?? 'Attachment',
                'path' => $document['path']
                    ? Storage::disk('public')->url(Str::after((string) $document['path'], 'public/'))
                    : null,
                'storage_path' => $document['path'] ?? null,
                'uploaded_at' => $document['uploaded_at'] ?? null,
                'uploaded_by' => $document['uploaded_by'] ?? null,
                'mime_type' => $document['mime_type'] ?? null,
                'size' => $document['size'] ?? null,
            ])->values()->all(),
            'napDocuments' => collect($napDocuments)->map(fn (array $document) => [
                'id' => $document['id'],
                'label' => $document['label'],
                'storage_path' => $document['path'],
                'download_url' => isset($document['path']) && is_string($document['path'])
                    ? Storage::disk('public')->url(Str::after($document['path'], 'public/'))
                    : null,
                'original_name' => $document['original_name'] ?? null,
                'uploaded_at' => $document['uploaded_at'] ?? null,
            ])->values()->all(),
        ]);
    }

    public function endorsementFinalizeSubmit(FinalizeApprovalRequest $request, DisposalBatch $batch, ApprovalLetterService $approvalLetterService): RedirectResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_APPROVED, DisposalBatch::STATUS_COMPLETE], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only approved or completed batches can generate the endorsement package.');
        }

        $data = $request->payload();
        if (! isset($data['letter_reference']) || blank($data['letter_reference'])) {
            $data['letter_reference'] = 'ENDORSEMENT-LETTER-COA-'.$batch->reference;
        }
        $pictures = $data['pictures'];
        $existingDocuments = is_array($batch->approval_documents) ? $batch->approval_documents : [];

        $removedDocumentPaths = collect($data['removed_documents'] ?? [])
            ->filter(static fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();

        if ($removedDocumentPaths !== []) {
            $existingDocuments = array_values(array_filter(
                $existingDocuments,
                static fn (array $document): bool => ! isset($document['path']) || ! in_array($document['path'], $removedDocumentPaths, true),
            ));

            foreach ($removedDocumentPaths as $removedPath) {
                Storage::delete($removedPath);

                if (Str::startsWith($removedPath, 'public/')) {
                    Storage::disk('public')->delete(Str::after($removedPath, 'public/'));
                }
            }
        }

        if (count($existingDocuments) + count($pictures) < 1) {
            return redirect()
                ->back()
                ->with('error', 'At least one documentation picture is required before finalizing the endorsement.');
        }

        if (count($existingDocuments) + count($pictures) > ApproveDisposalBatchRequest::MAX_PICTURES) {
            return redirect()
                ->back()
                ->with('error', 'You can upload a maximum of '.ApproveDisposalBatchRequest::MAX_PICTURES.' pictures.');
        }

        $shouldResetCoaSubmission = $batch->report_submitted_to_coa_at !== null;
        $previousCoaSubmissionAt = $batch->report_submitted_to_coa_at;
        $previousCoaSubmissionBy = $batch->report_submitted_by;
        $coaSubmissionReset = false;

        try {
            DB::transaction(function () use (
                $request,
                $batch,
                $approvalLetterService,
                $data,
                $pictures,
                $existingDocuments,
                $shouldResetCoaSubmission,
                $previousCoaSubmissionAt,
                $previousCoaSubmissionBy,
                &$coaSubmissionReset,
            ): void {
                $documents = $existingDocuments;
                foreach ($pictures as $picture) {
                    $storedPath = $picture->store('approval-documents/'.$batch->id, 'public');

                    $documents[] = [
                        'name' => $picture->getClientOriginalName() ?: $picture->hashName(),
                        'path' => 'public/'.$storedPath,
                        'uploaded_at' => now()->toIso8601String(),
                        'uploaded_by' => $request->user()->name,
                        'mime_type' => $picture->getClientMimeType(),
                        'size' => $picture->getSize(),
                    ];
                }

                $user = $request->user();
                $metadata = $batch->metadata;
                if (! is_array($metadata)) {
                    $metadata = [];
                }

                $metadata['approval_review'] = [
                    'coa_name' => $data['recipient_name'],
                    'coa_position' => $data['recipient_position'],
                    'coa_designation' => $data['recipient_designation'],
                    'coa_address' => $data['recipient_address'],
                    'honorific' => $data['honorific'],
                    'recipient_first_name' => $data['recipient_first_name'],
                    'recipient_middle_name' => $data['recipient_middle_name'],
                    'recipient_last_name' => $data['recipient_last_name'],
                    'recipient_extension' => $data['recipient_extension'],
                ];

                $metadata['approval_letter'] = [
                    'reference' => $data['letter_reference'],
                    'date' => $data['letter_date'],
                    'actual_disposal_date' => $data['actual_disposal_date'],
                    'honorific' => $data['honorific'],
                    'recipient_name' => $data['recipient_name'],
                    'recipient_first_name' => $data['recipient_first_name'],
                    'recipient_middle_name' => $data['recipient_middle_name'],
                    'recipient_last_name' => $data['recipient_last_name'],
                    'recipient_extension' => $data['recipient_extension'],
                    'recipient_position' => $data['recipient_position'],
                    'recipient_designation' => $data['recipient_designation'],
                    'recipient_address' => $data['recipient_address'],
                ];

                $napDocumentsPayload = $data['nap_documents'] ?? [];
                $napDocumentsMetadata = [];
                foreach ($napDocumentsPayload as $document) {
                    if (! is_array($document) || ! isset($document['id'], $document['label'])) {
                        continue;
                    }

                    $file = $document['file'] ?? null;
                    $storedPath = null;
                    if ($file instanceof UploadedFile) {
                        $storedPath = 'public/'.$file->store('nap-forms/'.$batch->id, 'public');
                    } elseif (isset($document['existing_path']) && is_string($document['existing_path'])) {
                        $storedPath = $document['existing_path'];
                    }

                    $napDocumentsMetadata[] = [
                        'id' => $document['id'],
                        'label' => trim((string) $document['label']) ?: 'Supporting Document',
                        'path' => $storedPath,
                        'original_name' => $file instanceof UploadedFile
                            ? ($file->getClientOriginalName() ?: $file->hashName())
                            : ($document['original_name'] ?? null),
                        'uploaded_at' => $file instanceof UploadedFile
                            ? now()->toIso8601String()
                            : ($document['uploaded_at'] ?? null),
                    ];
                }

                if (empty($napDocumentsMetadata)) {
                    $napDocumentsMetadata = $approvalLetterService->defaultNapDocuments();
                }

                $metadata['nap_forms'] = $napDocumentsMetadata;

                $targetStatus = $shouldResetCoaSubmission
                    ? DisposalBatch::STATUS_APPROVED
                    : $batch->status;

                $batch->forceFill([
                    'status' => $targetStatus,
                    'approved_by' => $batch->approved_by ?? $user->id,
                    'approved_at' => $batch->approved_at ?? now(),
                    'approval_notes' => $data['approval_notes'],
                    'approval_documents' => $documents,
                    'metadata' => $metadata,
                    'coa_name' => $data['recipient_name'],
                    'coa_position' => $data['recipient_position'],
                    'coa_designation' => $data['recipient_designation'],
                    'coa_address' => $data['recipient_address'],
                    'report_submitted_to_coa_at' => $shouldResetCoaSubmission ? null : $batch->report_submitted_to_coa_at,
                    'report_submitted_by' => $shouldResetCoaSubmission ? null : $batch->report_submitted_by,
                ])->save();

                $batch->loadMissing([
                    'approvedBy:id,name,email,position_id',
                    'approvedBy.position:id,name',
                    'inspectedBy:id,name,email,position_id',
                    'inspectedBy.position:id,name',
                    'scheduledBy:id,name,email,position_id',
                    'scheduledBy.position:id,name',
                ]);

                $timestamp = now()->format('YmdHis');
                $pdfPath = $approvalLetterService->generatePdf($batch, $data, $timestamp);

                $batch->forceFill([
                    'approval_letter_path' => $pdfPath,
                    'approval_letter_generated_by' => $user->id,
                    'approval_letter_generated_at' => now(),
                ])->save();

                if ($shouldResetCoaSubmission) {
                    $coaSubmissionReset = true;

                    DisposalAudit::create([
                        'batch_id' => $batch->id,
                        'user_id' => $user->id,
                        'action' => 'coa_submission_reset',
                        'description' => $user->name.' regenerated the Endorsement Letter to COA, clearing the previous COA submission record.',
                        'meta' => [
                            'previous_submission_at' => DateTimeFormatter::toIso8601($previousCoaSubmissionAt),
                            'previous_submission_by' => $previousCoaSubmissionBy,
                            'reason' => 'approval_letter_regenerated',
                        ],
                    ]);
                }

                DisposalAudit::create([
                    'batch_id' => $batch->id,
                    'user_id' => $user->id,
                    'action' => 'approval_letter_generated',
                    'description' => 'Endorsement Letter to COA generated as PDF',
                    'meta' => [
                        'pdf_path' => $pdfPath,
                        'letter_reference' => $data['letter_reference'],
                    ],
                ]);
            });

            $redirect = redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'Endorsement Letter to COA generated successfully.');

            if ($coaSubmissionReset) {
                $redirect->with('warning', 'COA submission confirmation was cleared because a new Endorsement Letter to COA was generated. Please record the submission again after resending the packet to COA.');
            }

            return $redirect;
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->with('error', 'Failed to finalize approval: '.$exception->getMessage());
        }
    }

    public function approvalLetterGenerate(GenerateApprovalLetterRequest $request, DisposalBatch $batch, ApprovalLetterService $approvalLetterService): RedirectResponse
    {
        if ($batch->status !== DisposalBatch::STATUS_APPROVED) {
            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('error', 'Endorsement Letter to COA can only be generated for approved batches.');
        }

        $batch->loadMissing([
            'approvedBy:id,name,email,position_id',
            'approvedBy.position:id,name',
            'inspectedBy:id,name,email,position_id',
            'inspectedBy.position:id,name',
            'scheduledBy:id,name,email,position_id',
            'scheduledBy.position:id,name',
        ]);

        $data = $request->letterData();

        try {
            $timestamp = now()->format('YmdHis');
            $pdfPath = $approvalLetterService->generatePdf($batch, $data, $timestamp);

            $metadata = $batch->metadata;
            if (! is_array($metadata)) {
                $metadata = [];
            }

            $metadata['approval_letter'] = [
                'reference' => $data['letter_reference'],
                'date' => $data['letter_date'],
                'actual_disposal_date' => $data['actual_disposal_date'],
                'honorific' => $data['honorific'],
                'recipient_name' => $data['recipient_name'],
                'recipient_first_name' => $data['recipient_first_name'],
                'recipient_middle_name' => $data['recipient_middle_name'],
                'recipient_last_name' => $data['recipient_last_name'],
                'recipient_extension' => $data['recipient_extension'],
                'recipient_position' => $data['recipient_position'],
                'recipient_designation' => $data['recipient_designation'],
                'recipient_address' => $data['recipient_address'],
            ];

            $metadata['approval_review'] = [
                'coa_name' => $data['recipient_name'],
                'coa_position' => $data['recipient_position'],
                'coa_designation' => $data['recipient_designation'],
                'coa_address' => $data['recipient_address'],
                'honorific' => $data['honorific'],
                'recipient_first_name' => $data['recipient_first_name'],
                'recipient_middle_name' => $data['recipient_middle_name'],
                'recipient_last_name' => $data['recipient_last_name'],
                'recipient_extension' => $data['recipient_extension'],
            ];

            $batch->forceFill([
                'coa_name' => $data['recipient_name'],
                'coa_position' => $data['recipient_position'],
                'coa_designation' => $data['recipient_designation'],
                'coa_address' => $data['recipient_address'],
                'approval_notes' => $data['approval_notes'],
                'approval_letter_path' => $pdfPath,
                'approval_letter_generated_by' => $request->user()->id,
                'approval_letter_generated_at' => now(),
                'metadata' => $metadata,
            ])->save();

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $request->user()->id,
                'action' => 'approval_letter_generated',
                'description' => 'Endorsement Letter to COA generated as PDF',
                'meta' => [
                    'pdf_path' => $pdfPath,
                    'letter_reference' => $data['letter_reference'],
                    'approval_notes' => $data['approval_notes'],
                ],
            ]);

            return redirect()
                ->route('disposal-batches.show', $batch)
                ->with('success', 'Endorsement Letter to COA generated successfully.');
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->with('error', 'Failed to generate Endorsement Letter to COA: '.$exception->getMessage());
        }
    }

    public function approvalLetterPreview(ApprovalLetterPreviewRequest $request, DisposalBatch $batch, ApprovalLetterService $approvalLetterService): JsonResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_INSPECTED, DisposalBatch::STATUS_APPROVED, DisposalBatch::STATUS_COMPLETE], true)) {
            abort(403, 'Only inspected, approved, or completed batches can preview the Endorsement Letter to COA.');
        }

        $data = $request->payload();
        if (! isset($data['letter_reference']) || blank($data['letter_reference'])) {
            $data['letter_reference'] = 'ENDORSEMENT-LETTER-COA-'.$batch->reference;
        }

        $existingDocuments = is_array($batch->approval_documents) ? $batch->approval_documents : [];
        $removedPaths = $data['removed_documents'] ?? [];

        if ($removedPaths !== []) {
            $existingDocuments = array_values(array_filter(
                $existingDocuments,
                static fn (array $document): bool => ! isset($document['path']) || ! in_array($document['path'], $removedPaths, true),
            ));
        }

        $documents = $existingDocuments;
        $napDocumentsPayload = $data['nap_documents'] ?? [];
        $previewNapDocuments = [];
        $temporaryAbsolutePaths = [];
        $publicDisk = Storage::disk('public');
        $timestamp = now()->format('YmdHis');
        $pictureDirectory = 'approval-document-previews/'.$batch->id.'/'.$timestamp;
        $napDirectory = 'nap-form-previews/'.$batch->id.'/'.$timestamp;
        $userName = $request->user()?->name;

        try {
            foreach ($data['pictures'] ?? [] as $picture) {
                if (! $picture instanceof UploadedFile) {
                    continue;
                }

                $storedRelativePath = $picture->store($pictureDirectory, 'public');
                $temporaryAbsolutePaths[] = $publicDisk->path($storedRelativePath);
                $documents[] = [
                    'name' => $picture->getClientOriginalName() ?: $picture->hashName(),
                    'path' => 'public/'.$storedRelativePath,
                    'uploaded_at' => now()->toIso8601String(),
                    'uploaded_by' => $userName,
                    'mime_type' => $picture->getClientMimeType(),
                    'size' => $picture->getSize(),
                ];
            }

            foreach ($napDocumentsPayload as $document) {
                if (! is_array($document) || ! isset($document['id'], $document['label'])) {
                    continue;
                }

                $entry = [
                    'id' => $document['id'],
                    'label' => $document['label'],
                    'existing_path' => $document['existing_path'] ?? null,
                    'original_name' => $document['original_name'] ?? null,
                    'uploaded_at' => $document['uploaded_at'] ?? null,
                ];

                $file = $document['file'] ?? null;
                if ($file instanceof UploadedFile) {
                    $storedRelativePath = $file->store($napDirectory, 'public');
                    $temporaryAbsolutePaths[] = $publicDisk->path($storedRelativePath);
                    $entry['existing_path'] = 'public/'.$storedRelativePath;
                    $entry['original_name'] = $file->getClientOriginalName() ?: $file->hashName();
                    $entry['uploaded_at'] = now()->toIso8601String();
                }

                $previewNapDocuments[] = $entry;
            }

            if ($previewNapDocuments === []) {
                $previewNapDocuments = $napDocumentsPayload;
            }

            $previewPath = $approvalLetterService->generatePreviewPdf($batch, $data, $previewNapDocuments, $documents);
        } finally {
            foreach ($temporaryAbsolutePaths as $path) {
                if (is_string($path) && file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        return response()->json([
            'message' => 'Preview generated successfully.',
            'preview_path' => $previewPath,
        ]);
    }

    public function approvalLetterDownload(DisposalBatch $batch, Request $request): BinaryFileResponse
    {
        $previewPath = $request->query('preview_path');
        if (is_string($previewPath) && $previewPath !== '') {
            if (! str_starts_with($previewPath, 'public/approval-letter-previews/')) {
                abort(403, 'Invalid preview path.');
            }

            $previewFullPath = storage_path('app/'.$previewPath);
            if (! file_exists($previewFullPath)) {
                abort(404, 'Endorsement Letter preview not found.');
            }

            return response()->file($previewFullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($previewFullPath)
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        if (! $batch->approval_letter_path) {
            abort(404, 'Endorsement Letter to COA not found.');
        }

        $fullPath = storage_path('app/'.$batch->approval_letter_path);

        if (! file_exists($fullPath)) {
            abort(404, 'Endorsement Letter to COA file not found.');
        }

        if ($request->query('disposition') === 'inline') {
            return response()->file($fullPath, [
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    basename($fullPath)
                ),
            ]);
        }

        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        if (! is_array(Arr::get($metadata, 'approval_letter'))) {
            $metadata['approval_letter'] = [];
        }
        $metadata['approval_letter']['downloaded_at'] = now()->toIso8601String();
        $batch->forceFill(['metadata' => $metadata])->save();

        return response()->download($fullPath);
    }

    public function approvalReview(Request $request, DisposalBatch $batch): Response
    {
        if ($batch->status !== DisposalBatch::STATUS_INSPECTED) {
            abort(403, 'Only inspected batches can proceed to approval review.');
        }

        $metadata = $batch->metadata ?? [];

        $defaultData = [
            'approval_notes' => $batch->approval_notes ?? '',
            'coa_name' => Arr::get($metadata, 'approval_review.coa_name') ?? $batch->coa_name ?? '',
            'coa_position' => Arr::get($metadata, 'approval_review.coa_position')
                ?? $batch->coa_position
                ?? CoaContactDefaults::position(),
            'coa_designation' => Arr::get($metadata, 'approval_review.coa_designation')
                ?? $batch->coa_designation
                ?? CoaContactDefaults::designation(),
        ];

        return Inertia::render('Disposal/ApprovalReview', [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'file_count' => $batch->file_count,
            ],
            'defaultData' => $defaultData,
            'maxPictures' => ApproveDisposalBatchRequest::MAX_PICTURES,
            'approvalDocuments' => collect($batch->approval_documents ?? [])->map(fn ($document) => [
                'name' => $document['name'] ?? 'Attachment',
                'path' => $document['path'] ? Storage::url($document['path']) : null,
                'storage_path' => $document['path'] ?? null,
                'uploaded_at' => $document['uploaded_at'] ?? null,
                'uploaded_by' => $document['uploaded_by'] ?? null,
                'mime_type' => $document['mime_type'] ?? null,
                'size' => $document['size'] ?? null,
            ])->values()->all(),
        ]);
    }

    public function scheduleInspection(Request $request, DisposalBatch $batch): RedirectResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_COMPLETED, DisposalBatch::STATUS_REQUEST_LETTER_READY, DisposalBatch::STATUS_WAITING_FOR_INSPECTION], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only verified batches can have an inspection scheduled.');
        }

        if ($batch->nap_report_downloaded_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Download the NAP Form 3 before scheduling an inspection.');
        }

        if ($batch->nap_form_1_downloaded_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Download the NAP Form 1 before scheduling an inspection.');
        }

        if ($batch->request_letter_generated_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Generate the Request Letter before scheduling an inspection.');
        }

        if ($batch->request_letter_downloaded_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Download the Request Letter before scheduling an inspection.');
        }

        $metadata = is_array($batch->metadata) ? $batch->metadata : [];
        $requestLetterDateString = Arr::get($metadata, 'request_letter.letter_date');
        $requestLetterDate = null;

        if (is_string($requestLetterDateString) && $requestLetterDateString !== '') {
            try {
                $requestLetterDate = Carbon::parse($requestLetterDateString)->startOfDay();
            } catch (\Exception) {
                $requestLetterDate = null;
            }
        }

        $validated = $request->validate([
            'inspection_scheduled_at' => ['required', 'date'],
            'request_letter_sent_to_nap' => ['nullable', 'boolean'],
        ]);

        $requestLetterSentToNap = filter_var($validated['request_letter_sent_to_nap'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($requestLetterDate instanceof Carbon && $requestLetterDate->isFuture() && ! $requestLetterSentToNap) {
            return redirect()
                ->back()
                ->with('error', 'Confirm that the Request Letter has already been sent to NAP before scheduling an inspection.');
        }

        $user = $request->user();
        $previousStatus = $batch->status;

        $batch->update([
            'status' => DisposalBatch::STATUS_WAITING_FOR_INSPECTION,
            'inspection_scheduled_at' => DateTimeFormatter::parse($validated['inspection_scheduled_at']),
            'inspection_scheduled_by' => $user->id,
        ]);

        DisposalAudit::create([
            'batch_id' => $batch->id,
            'action' => 'inspection_scheduled',
            'description' => $user->name.' scheduled an inspection.',
            'user_id' => $user->id,
            'meta' => [
                'status_from' => $previousStatus,
                'status_to' => DisposalBatch::STATUS_WAITING_FOR_INSPECTION,
                'inspection_scheduled_at' => $validated['inspection_scheduled_at'],
                'request_letter_sent_to_nap' => $requestLetterSentToNap,
            ],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Inspection scheduled successfully.');
    }

    public function markAsInspected(Request $request, DisposalBatch $batch): RedirectResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_COMPLETED, DisposalBatch::STATUS_REQUEST_LETTER_READY, DisposalBatch::STATUS_WAITING_FOR_INSPECTION], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only verified batches can be marked as inspected.');
        }

        if ($batch->nap_report_downloaded_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Download the NAP Form 3 before marking this batch as inspected.');
        }

        if ($batch->nap_form_1_downloaded_at === null) {
            return redirect()
                ->back()
                ->with('error', 'Download the NAP Form 1 before marking this batch as inspected.');
        }

        $workflowFilesCount = $batch->items()
            ->whereHas('file', function ($query) {
                $query->whereNotNull('id');
            })
            ->count();

        if ($workflowFilesCount === 0) {
            return redirect()
                ->back()
                ->with('error', 'Cannot inspect batch: this batch has no files.');
        }

        $validated = $request->validate([
            'inspection_notes' => ['nullable', 'string', 'max:1000'],
            'inspector_name' => ['required', 'string', 'max:255'],
            'inspection_result' => ['required', 'string', Rule::in(['passed', 'rejected'])],
            'rejection_reason' => ['nullable', 'required_if:inspection_result,rejected', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $inspectorName = trim($validated['inspector_name']);
        $inspectionResult = (string) $validated['inspection_result'];
        $rejectionReason = isset($validated['rejection_reason'])
            ? trim((string) $validated['rejection_reason'])
            : null;
        $previousStatus = $batch->status;

        if ($inspectionResult === 'rejected') {
            if ($rejectionReason === null || $rejectionReason === '') {
                return redirect()
                    ->back()
                    ->with('error', 'Provide a rejection reason when the inspection result is rejected.');
            }

            DB::transaction(function () use ($batch, $user, $inspectorName, $validated, $rejectionReason, $previousStatus): void {
                $batch->forceFill([
                    'inspected_by' => $user->id,
                    'inspector_name' => $inspectorName,
                    'inspected_at' => now(),
                    'inspection_notes' => $validated['inspection_notes'] ?? null,
                ])->save();

                $this->applyBatchRejection($batch, $user, $rejectionReason, $previousStatus);
            });

            return redirect()
                ->back()
                ->with('success', 'Batch rejected based on inspection result.');
        }

        $batch->update([
            'status' => DisposalBatch::STATUS_INSPECTED,
            'inspected_by' => $user->id,
            'inspector_name' => $inspectorName,
            'inspected_at' => now(),
            'inspection_notes' => $validated['inspection_notes'] ?? null,
        ]);

        // Log audit
        DisposalAudit::create([
            'batch_id' => $batch->id,
            'action' => 'batch_inspected',
            'description' => $user->name.' marked the batch as inspected.',
            'user_id' => $user->id,
            'meta' => [
                'status_from' => $previousStatus,
                'status_to' => DisposalBatch::STATUS_INSPECTED,
                'inspection_notes' => $validated['inspection_notes'] ?? null,
                'inspector_name' => $inspectorName,
                'inspection_result' => 'passed',
                'workflow_files_count' => $workflowFilesCount,
            ],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Batch marked as inspected successfully.');
    }

    public function confirmNapAuthority(ConfirmNapAuthorityRequest $request, DisposalBatch $batch): RedirectResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_INSPECTED, DisposalBatch::STATUS_APPROVED], true)) {
            return redirect()
                ->back()
                ->with('error', 'Authority confirmation is only available for inspected batches.');
        }

        $data = $request->payload();
        $user = $request->user();

        if ($user === null) {
            abort(403, 'User context required.');
        }

        if (! $data['nap_form_5_received']) {
            return redirect()
                ->back()
                ->with('error', 'Confirm that the NAP Form 5 has been issued before recording the authority to dispose.');
        }

        $issuedAt = now();

        $wasAlreadyApproved = $batch->status === DisposalBatch::STATUS_APPROVED;
        $previousStatus = $batch->status;

        DB::transaction(function () use ($batch, $data, $issuedAt, $user, $wasAlreadyApproved, $previousStatus): void {
            $batch->forceFill([
                'status' => DisposalBatch::STATUS_APPROVED,
                'actual_mode_of_disposal' => $data['actual_mode_of_disposal'],
                'nap_authority_issued_at' => $issuedAt,
                'nap_authority_confirmed_by' => $user->id,
                'approved_by' => $batch->approved_by ?? $user->id,
                'approved_at' => $batch->approved_at ?? $issuedAt,
            ])->save();

            if (! $wasAlreadyApproved) {
                $files = $batch->files()->get();

                foreach ($files as $file) {
                    $this->finalizeFile($file);
                }

                DisposalAudit::create([
                    'batch_id' => $batch->id,
                    'action' => 'batch_approved',
                    'description' => $user->name.' approved the batch after NAP authority confirmation.',
                    'user_id' => $user->id,
                    'meta' => [
                        'status_from' => $previousStatus,
                        'status_to' => DisposalBatch::STATUS_APPROVED,
                        'approved_by_nap_authority' => true,
                        'actual_mode_of_disposal' => $data['actual_mode_of_disposal'],
                        'actual_mode_of_disposal_label' => DisposalBatch::modeLabel($data['actual_mode_of_disposal']),
                        'files_disposed' => $files->count(),
                    ],
                ]);
            }

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'action' => 'nap_authority_confirmed',
                'description' => $user->name.' recorded the issued authority to dispose.',
                'user_id' => $user->id,
                'meta' => [
                    'actual_mode_of_disposal' => $data['actual_mode_of_disposal'],
                    'actual_mode_of_disposal_label' => DisposalBatch::modeLabel($data['actual_mode_of_disposal']),
                    'nap_authority_issued_at' => DateTimeFormatter::toIso8601($issuedAt),
                    'nap_form_5_received' => true,
                    'status_to' => DisposalBatch::STATUS_APPROVED,
                    'triggered_approval' => ! $wasAlreadyApproved,
                ],
            ]);
        });

        return redirect()
            ->back()
            ->with('success', 'NAP authority issuance confirmed, actual disposal mode recorded, and batch approved.');
    }

    public function confirmCoaSubmission(ConfirmCoaSubmissionRequest $request, DisposalBatch $batch): RedirectResponse
    {
        if (! in_array($batch->status, [DisposalBatch::STATUS_APPROVED, DisposalBatch::STATUS_COMPLETE], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only endorsed batches can record COA submissions.');
        }

        $user = $request->user();

        if ($user === null) {
            abort(403, 'User context required.');
        }

        if (! $request->payload()['report_submitted']) {
            return redirect()
                ->back()
                ->with('error', 'Confirm the report submission before proceeding.');
        }

        $hasEndorsementCompleted = $batch->approval_letter_path !== null
            && $batch->approval_letter_generated_at !== null;
        $hasExistingSubmission = $batch->report_submitted_to_coa_at !== null;

        if (! $hasExistingSubmission && ! $hasEndorsementCompleted) {
            return redirect()
                ->back()
                ->with('error', 'Finalize the Endorsement Letter to COA before recording submission.');
        }

        $wasAlreadyComplete = $batch->status === DisposalBatch::STATUS_COMPLETE;
        $previousStatus = $batch->status;
        $timestamp = $batch->report_submitted_to_coa_at ?? now();

        $batch->forceFill([
            'status' => DisposalBatch::STATUS_COMPLETE,
            'report_submitted_to_coa_at' => $batch->report_submitted_to_coa_at ?? $timestamp,
            'report_submitted_by' => $batch->report_submitted_by ?? $user->id,
        ])->save();

        DisposalAudit::create([
            'batch_id' => $batch->id,
            'user_id' => $user->id,
            'action' => 'report_submitted_to_coa',
            'description' => $user->name.' recorded the submission to COA.',
            'meta' => [
                'status_from' => $previousStatus,
                'status_to' => DisposalBatch::STATUS_COMPLETE,
                'was_already_complete' => $wasAlreadyComplete,
                'report_submitted_to_coa_at' => DateTimeFormatter::toIso8601($timestamp),
            ],
        ]);

        return redirect()
            ->back()
            ->with('success', 'COA submission recorded successfully.');
    }

    public function approve(ApproveDisposalBatchRequest $request, DisposalBatch $batch): RedirectResponse
    {
        if ($batch->status !== DisposalBatch::STATUS_INSPECTED) {
            return redirect()
                ->back()
                ->with('error', 'Only inspected batches can be approved.');
        }

        $data = $request->approvalData();
        $pictures = $data['pictures'] ?? [];
        if (! is_array($pictures)) {
            $pictures = [];
        }

        $existingDocuments = is_array($batch->approval_documents) ? $batch->approval_documents : [];

        if (count($existingDocuments) + count($pictures) < 1) {
            return redirect()
                ->back()
                ->with('error', 'At least one documentation picture is required before approval.');
        }

        if (count($existingDocuments) + count($pictures) > ApproveDisposalBatchRequest::MAX_PICTURES) {
            return redirect()
                ->back()
                ->with('error', 'You can upload a maximum of '.ApproveDisposalBatchRequest::MAX_PICTURES.' pictures.');
        }

        $documents = $existingDocuments;
        foreach ($pictures as $picture) {
            $storedPath = $picture->store('approval-documents/'.$batch->id, 'public');

            $documents[] = [
                'name' => $picture->getClientOriginalName() ?: $picture->hashName(),
                'path' => 'public/'.$storedPath,
                'uploaded_at' => now()->toIso8601String(),
                'uploaded_by' => $request->user()->name,
                'mime_type' => $picture->getClientMimeType(),
                'size' => $picture->getSize(),
            ];
        }

        $user = $request->user();
        $metadata = $batch->metadata ?? [];
        $metadata['approval_review'] = [
            'coa_name' => $data['coa_name'],
            'coa_position' => $data['coa_position'],
            'coa_designation' => $data['coa_designation'],
        ];

        DB::transaction(function () use ($batch, $user, $data, $documents, $metadata): void {
            $files = $batch->files()->get();

            foreach ($files as $file) {
                $this->finalizeFile($file);
            }

            $batch->update([
                'status' => DisposalBatch::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $data['approval_notes'] ?? null,
                'approval_documents' => $documents,
                'metadata' => $metadata,
                'coa_name' => $data['coa_name'],
                'coa_position' => $data['coa_position'],
                'coa_designation' => $data['coa_designation'],
            ]);

            // Log audit
            DisposalAudit::create([
                'batch_id' => $batch->id,
                'action' => 'batch_approved',
                'description' => $user->name.' approved the batch.',
                'user_id' => $user->id,
                'meta' => [
                    'status_from' => DisposalBatch::STATUS_INSPECTED,
                    'status_to' => DisposalBatch::STATUS_APPROVED,
                    'approval_notes' => $data['approval_notes'] ?? null,
                    'approval_review' => $metadata['approval_review'],
                    'files_disposed' => $files->count(),
                ],
            ]);
        });

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', 'Batch approved successfully.');
    }

    public function reject(Request $request, DisposalBatch $batch): RedirectResponse
    {
        if ($batch->status !== DisposalBatch::STATUS_INSPECTED) {
            return redirect()
                ->back()
                ->with('error', 'Only inspected batches can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($batch, $user, $validated): void {
            $this->applyBatchRejection($batch, $user, $validated['rejection_reason'], DisposalBatch::STATUS_INSPECTED);
        });

        return redirect()
            ->back()
            ->with('success', 'Batch rejected and files restored to their previous status.');
    }

    public function returnToDraft(ReturnDisposalBatchToDraftRequest $request, DisposalBatch $batch): RedirectResponse
    {
        if ($batch->status !== DisposalBatch::STATUS_REJECTED) {
            return redirect()
                ->back()
                ->with('error', 'Only rejected batches can be returned to draft.');
        }

        $user = $request->user();
        $reopenReason = $request->reopenReason();

        $previousRejectionReason = $batch->rejection_reason;
        $previousRejectedAt = DateTimeFormatter::toIso8601($batch->rejected_at);

        DB::transaction(function () use ($batch, $user, $reopenReason, $previousRejectionReason, $previousRejectedAt): void {
            $metadata = is_array($batch->metadata) ? $batch->metadata : [];
            $metadata = Arr::except($metadata, ['approval_review', 'approval_letter', 'nap_certificate', 'nap_form_1', 'request_letter']);

            $batch->forceFill([
                'status' => DisposalBatch::STATUS_SCHEDULED,
                'completed_by' => null,
                'completed_at' => null,
                'nap_report_path' => null,
                'nap_report_stage' => null,
                'nap_report_generated_by' => null,
                'nap_report_generated_at' => null,
                'nap_report_downloaded_at' => null,
                'nap_form_1_path' => null,
                'nap_form_1_stage' => null,
                'nap_form_1_generated_by' => null,
                'nap_form_1_generated_at' => null,
                'nap_form_1_downloaded_at' => null,
                'request_letter_path' => null,
                'request_letter_generated_by' => null,
                'request_letter_generated_at' => null,
                'request_letter_downloaded_at' => null,
                'inspection_scheduled_at' => null,
                'inspection_scheduled_by' => null,
                'inspected_by' => null,
                'inspector_name' => null,
                'inspected_at' => null,
                'inspection_notes' => null,
                'actual_mode_of_disposal' => null,
                'nap_authority_issued_at' => null,
                'nap_authority_confirmed_by' => null,
                'approved_by' => null,
                'approved_at' => null,
                'approval_notes' => null,
                'approval_documents' => null,
                'approval_letter_path' => null,
                'approval_letter_generated_at' => null,
                'approval_letter_generated_by' => null,
                'coa_name' => null,
                'coa_position' => null,
                'coa_designation' => null,
                'coa_address' => null,
                'report_submitted_to_coa_at' => null,
                'report_submitted_by' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'metadata' => $metadata === [] ? null : $metadata,
            ])->save();

            DisposalAudit::create([
                'batch_id' => $batch->id,
                'user_id' => $user?->id,
                'action' => 'batch_returned_to_draft',
                'description' => ($user?->name ?? 'System').' returned the batch to draft.',
                'meta' => [
                    'status_from' => DisposalBatch::STATUS_REJECTED,
                    'status_to' => DisposalBatch::STATUS_SCHEDULED,
                    'reopen_reason' => $reopenReason,
                    'previous_rejection_reason' => $previousRejectionReason,
                    'previous_rejected_at' => $previousRejectedAt,
                ],
            ]);
        });

        return redirect()
            ->route('disposal-batches.show', $batch)
            ->with('success', 'Batch returned to draft. You can now edit files and schedule details.');
    }

    private function applyBatchRejection(DisposalBatch $batch, User $user, string $rejectionReason, string $statusFrom): void
    {
        $batch->update([
            'status' => DisposalBatch::STATUS_REJECTED,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        $restoredFiles = 0;

        // Restore files to their appropriate retention status when needed.
        $files = $batch->files()->withTrashed()->get();

        foreach ($files as $file) {
            $requiresRestore = $file->trashed() || $file->retention_status === RetentionStatus::DISPOSED;

            if (! $requiresRestore) {
                continue;
            }

            // Restore file if soft-deleted.
            if ($file->trashed()) {
                $file->restore();
            }

            // Get the physical location snapshot from the batch item.
            $batchItem = $batch->items()->where('file_id', $file->id)->first();
            $physicalSnapshot = $batchItem?->physical_snapshot;

            // Recalculate retention status based on expiry date.
            $targetStatus = RetentionStatusCalculator::determine($file->retention_expires_at);

            // Restore physical location from snapshot if available.
            $physicalLocation = null;
            if ($physicalSnapshot && is_array($physicalSnapshot)) {
                $physicalLocation = $physicalSnapshot['location'] ?? null;
            }

            // Update file with restored status and location.
            $file->forceFill([
                'retention_status' => $targetStatus,
                'physical_location' => $physicalLocation,
            ])->save();

            // Restore physical location tags if they exist in snapshot.
            if ($physicalSnapshot && isset($physicalSnapshot['tags']) && is_array($physicalSnapshot['tags'])) {
                $tagIds = collect($physicalSnapshot['tags'])->pluck('id')->filter()->all();
                if (! empty($tagIds)) {
                    $file->physicalLocationTags()->sync($tagIds);
                }
            }

            // Log file restoration in audit trail.
            DisposalAudit::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'user_id' => $user->id,
                'action' => 'file_restored_from_rejection',
                'description' => sprintf('File %s restored to %s status due to batch rejection.', $file->record_id ?? $file->id, $targetStatus),
                'meta' => [
                    'batch_reference' => $batch->reference,
                    'restored_status' => $targetStatus,
                    'rejection_reason' => $rejectionReason,
                ],
            ]);

            $restoredFiles++;
        }

        // Log batch-level audit.
        DisposalAudit::create([
            'batch_id' => $batch->id,
            'action' => 'batch_rejected',
            'description' => $user->name.' rejected the batch.',
            'user_id' => $user->id,
            'meta' => [
                'status_from' => $statusFrom,
                'status_to' => DisposalBatch::STATUS_REJECTED,
                'rejection_reason' => $rejectionReason,
                'files_restored' => $restoredFiles,
            ],
        ]);
    }
}
