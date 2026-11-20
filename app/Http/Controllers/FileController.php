<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileMoveRequest;
use App\Http\Requests\FileRequest;
use App\Http\Requests\FileRestoreRequest;
use App\Jobs\ProcessFileOcr;
use App\Models\File;
use App\Models\Folder;
use App\Services\AuditService;
use App\Services\FileVersionService;
use App\Support\DateTimeFormatter;
use App\Support\FileName;
use App\Support\PhysicalLocationResolver;
use App\Support\RecordTypeHelper;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private FileVersionService $fileVersionService, private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = File::query()
            ->with(['folder', 'institution', 'uploader', 'department', 'currentVersion'])
            ->latest();

        if ($request->has('folder_id')) {
            $folderId = $request->query('folder_id');
            $query->where('folder_id', $folderId === 'null' ? null : $folderId);
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query = $query->search($search);
        }

        $files = $query->paginate(15);

        return response()->json($files->toArray());
    }

    public function createUploadToken(Request $request): JsonResponse
    {
        $token = $request->user()->createToken(
            'chunk-upload',
            ['upload-chunks'],
            now()->addHours(2)
        );

        $this->audit->log(
            'api_token_created',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' created an API token for chunked uploads.',
            $request->user(),
            [
                'token_name' => 'chunk-upload',
                'abilities' => ['upload-chunks'],
                'expires_at' => DateTimeFormatter::toIso8601(now()->addHours(2)),
            ]
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => DateTimeFormatter::toIso8601(now()->addHours(2)),
        ]);
    }

    public function store(FileRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $attributes['status'] = $attributes['status'] ?? 'approved';
        $attributes['retention_status'] = $attributes['retention_status'] ?? RetentionStatus::ACTIVE;
        $attributes['file_name'] = $this->resolveFileName($attributes['file_name'] ?? null, $request->file('file')?->getClientOriginalName());
        $metadataTagIds = $this->normalizeTagIds($attributes['metadata_tag_ids'] ?? []);
        $physicalLocationTagIds = $this->normalizeTagIds($attributes['physical_location_tag_ids'] ?? []);
        $isFileOnly = ($attributes['upload_mode'] ?? null) === 'file_only';
        $attributes['retention_expires_at'] = RetentionSchedule::expiresAt(
            $attributes['date_released'] ?? null,
            $attributes['retention_duration'] ?? null
        );

        $physicalLocation = null;
        if (! empty($physicalLocationTagIds)) {
            $physicalLocation = PhysicalLocationResolver::resolve($attributes['physical_location_tag_ids'] ?? []);
        }
        if ($physicalLocation === null && ! $isFileOnly) {
            throw ValidationException::withMessages([
                'physical_location_tag_ids' => ['Select at least one valid physical location tag.'],
            ]);
        }

        $attributes['physical_location'] = $physicalLocation;
        $attributes['uploaded_by'] = $request->user()->id;
        $uploadedFile = $request->file('file');
        $targetFolderId = $attributes['folder_id'] ?? null;
        $candidateName = $attributes['file_name'] ?? $request->file('file')?->getClientOriginalName() ?? 'file';

        $duplicate = $this->findCreateDuplicateFile($targetFolderId, $candidateName);

        if ($duplicate !== null) {
            return $this->duplicateCreateResponse($targetFolderId, $candidateName, $duplicate);
        }

        if ($uploadedFile !== null) {
            $mime = $uploadedFile->getClientMimeType() ?: '';
            $extension = $uploadedFile->getClientOriginalExtension() ?? '';

            if (! RecordTypeHelper::isRecordType($mime, $extension)) {
                $disk = config('filesystems.default', 'local');
                $storedPath = $uploadedFile->store('private/uploads', $disk);

                $storedAttributes = array_merge($attributes, [
                    'is_record' => false,
                    'record_id' => 'ATTACH-'.strtoupper(uniqid()),
                    'file_name' => $this->resolveFileName($attributes['file_name'] ?? null, $uploadedFile->getClientOriginalName()),
                    'department_id' => $attributes['department_id'] ?? null,
                    'physical_location' => $attributes['physical_location'] ?? null,
                ]);
                unset(
                    $storedAttributes['file'],
                    $storedAttributes['metadata_tag_ids'],
                    $storedAttributes['physical_location_tag_ids'],
                    $storedAttributes['upload_mode']
                );

                $file = File::query()->create($storedAttributes);
                $file->syncTags($metadataTagIds, $physicalLocationTagIds);

                $this->fileVersionService->createVersion(
                    $file,
                    [
                        'file_path' => $storedPath,
                        'file_name' => $storedAttributes['file_name'],
                        'mime_type' => $mime ?: null,
                        'file_size' => $uploadedFile->getSize(),
                        'uploaded_by' => $request->user()->id,
                        'assembly_status' => 'completed',
                        'ocr_status' => 'completed',
                    ],
                    $disk
                );

                return response()->json([
                    'message' => 'File stored. No record created (only PDFs and images become records).',
                    'record_created' => false,
                    'data' => $file->load(['folder', 'uploader', 'department', 'currentVersion']),
                ], 201);
            }
        }

        unset(
            $attributes['file'],
            $attributes['file_path'],
            $attributes['mime_type'],
            $attributes['file_size'],
            $attributes['metadata_tag_ids'],
            $attributes['physical_location_tag_ids'],
            $attributes['upload_mode']
        );

        $message = 'Record created. File pending upload.';

        $file = File::query()->create($attributes);
        $file->syncTags($metadataTagIds, $physicalLocationTagIds);

        if ($uploadedFile !== null) {
            $disk = config('filesystems.default', 'local');
            $storedPath = $uploadedFile->store('private/uploads', $disk);

            $fileVersion = $this->fileVersionService->createVersion(
                $file,
                [
                    'file_path' => $storedPath,
                    'file_name' => $this->resolveFileName($attributes['file_name'] ?? null, $uploadedFile->getClientOriginalName()),
                    'mime_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                    'file_size' => $uploadedFile->getSize(),
                    'uploaded_by' => $request->user()->id,
                    'assembly_status' => 'completed',
                    'ocr_status' => 'pending',
                    'ocr_language' => $file->ocr_language,
                ],
                $disk
            );

            ProcessFileOcr::dispatch($fileVersion->id);

            $message = 'File uploaded successfully.';
        }

        return response()->json([
            'message' => $message,
            'record_created' => true,
            'data' => $file->load(['folder', 'institution', 'uploader', 'department', 'currentVersion']),
        ], 201);
    }

    public function show(File $file): JsonResponse
    {
        return response()->json($file->load(['folder', 'institution', 'uploader', 'downloads', 'currentVersion']));
    }

    public function update(FileRequest $request, File $file): JsonResponse
    {
        $attributes = $request->validated();
        if (array_key_exists('file_name', $attributes)) {
            $attributes['file_name'] = $this->resolveFileName($attributes['file_name']);
        }
        $metadataTagIds = array_key_exists('metadata_tag_ids', $attributes)
            ? $this->normalizeTagIds($attributes['metadata_tag_ids'])
            : null;
        $physicalLocationTagIds = array_key_exists('physical_location_tag_ids', $attributes)
            ? $this->normalizeTagIds($attributes['physical_location_tag_ids'])
            : null;

        if (array_key_exists('retention_duration', $attributes) || array_key_exists('date_released', $attributes)) {
            $retentionDuration = $attributes['retention_duration'] ?? $file->retention_duration;
            $dateReleased = $attributes['date_released'] ?? DateTimeFormatter::toDateString($file->date_released);

            $attributes['retention_expires_at'] = RetentionSchedule::expiresAt($dateReleased, $retentionDuration);
        }

        if (array_key_exists('physical_location_tag_ids', $attributes)) {
            $tagIds = $attributes['physical_location_tag_ids'];
            $normalizedIds = is_array($tagIds) ? $this->normalizeTagIds($tagIds) : [];

            if ($file->is_record === false) {
                $attributes['physical_location'] = empty($normalizedIds)
                    ? null
                    : PhysicalLocationResolver::resolve($tagIds);
            } else {
                $physicalLocation = PhysicalLocationResolver::resolve($tagIds);
                if ($physicalLocation === null) {
                    throw ValidationException::withMessages([
                        'physical_location_tag_ids' => ['Select at least one valid physical location tag.'],
                    ]);
                }
                $attributes['physical_location'] = $physicalLocation;
            }
        }

        $uploadedFile = $request->file('file');
        unset(
            $attributes['file'],
            $attributes['file_path'],
            $attributes['mime_type'],
            $attributes['file_size'],
            $attributes['metadata_tag_ids'],
            $attributes['physical_location_tag_ids'],
            $attributes['upload_mode']
        );

        $targetFolderId = $attributes['folder_id'] ?? $file->folder_id;
        $candidateName = $attributes['file_name'] ?? $file->file_name;

        $duplicate = $this->findDuplicateFile($file, $targetFolderId, $candidateName);

        if ($duplicate !== null) {
            return $this->duplicateResponse($file, $targetFolderId, $candidateName, $duplicate);
        }

        $file->fill($attributes)->save();
        $file->syncTags($metadataTagIds, $physicalLocationTagIds);

        if ($uploadedFile !== null) {
            $disk = config('filesystems.default', 'local');
            $storedPath = $uploadedFile->store('private/uploads', $disk);

            $freshFile = $file->fresh();

            $fileVersion = $this->fileVersionService->createVersion(
                $freshFile,
                [
                    'file_path' => $storedPath,
                    'file_name' => $this->resolveFileName($freshFile->file_name, $uploadedFile->getClientOriginalName()),
                    'mime_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                    'file_size' => $uploadedFile->getSize(),
                    'uploaded_by' => $request->user()->id,
                    'assembly_status' => 'completed',
                    'ocr_status' => 'pending',
                    'ocr_language' => $freshFile->ocr_language,
                ],
                $disk
            );

            $mime = $uploadedFile->getClientMimeType() ?: '';
            $extension = $uploadedFile->getClientOriginalExtension() ?? '';
            if (RecordTypeHelper::isRecordType($mime, $extension)) {
                ProcessFileOcr::dispatch($fileVersion->id);
            }
        }

        return response()->json($file->refresh()->load(['folder', 'institution', 'uploader', 'department', 'currentVersion']));
    }

    public function destroy(File $file): JsonResponse
    {
        $file->delete();

        return response()->json(null, 204);
    }

    public function restore(FileRestoreRequest $request, string $file): JsonResponse
    {
        $file = File::onlyTrashed()->findOrFail($file);

        $attributes = $request->validated();

        $targetFolderId = array_key_exists('folder_id', $attributes)
            ? $this->normalizeFolderId($attributes['folder_id'])
            : $file->folder_id;

        if ($targetFolderId !== null) {
            $parentFolder = Folder::withTrashed()->find($targetFolderId);

            if ($parentFolder === null || $parentFolder->trashed()) {
                $targetFolderId = null;
            }
        }

        $newName = $this->resolveFileName($attributes['new_name'] ?? $file->file_name);
        $strategy = $attributes['strategy'] ?? 'fail';
        $duplicateCheckName = $this->resolveFileName($attributes['new_name'] ?? $file->file_name);

        $duplicate = $this->findDuplicateFile($file, $targetFolderId, $duplicateCheckName);

        if ($duplicate !== null) {
            if ($strategy === 'replace') {
                $this->replaceFileWithVersion($file, $duplicate, $targetFolderId);

                return response()->json([
                    'message' => 'File replaced successfully.',
                    'data' => $duplicate->refresh()->load(['folder', 'institution', 'uploader', 'currentVersion']),
                ]);
            } elseif ($strategy === 'rename') {
                $newName = $this->generateUniqueFileName($file, $targetFolderId, $newName);
            } else {
                return $this->duplicateResponse($file, $targetFolderId, $newName, $duplicate);
            }
        }

        $file->folder_id = $targetFolderId;
        $file->setAttribute('file_name', $newName);
        $file->restore();

        return response()->json($file->refresh()->load(['folder', 'institution', 'uploader', 'currentVersion']));
    }

    public function forceDestroy(string $file): JsonResponse
    {
        $file = File::onlyTrashed()->findOrFail($file);

        // Delete the physical file from storage
        if ($file->file_path) {
            $disk = config('filesystems.default', 'local');
            Storage::disk($disk)->delete($file->file_path);
        }

        $file->forceDelete();

        return response()->json(null, 204);
    }

    public function download(File $file): StreamedResponse
    {
        $disk = config('filesystems.default', 'local');
        $path = ltrim($file->file_path, '/');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false || $stream === null) {
            abort(404);
        }

        $downloadName = FileName::withPathExtension($file->file_name, $file->file_path, $file->mime_type);
        $contentType = FileName::resolveMimeType($file->mime_type, $file->file_path);

        return new StreamedResponse(function () use ($stream) {
            while (! feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $downloadName
            ),
        ]);
    }

    public function move(FileMoveRequest $request, File $file): JsonResponse
    {
        $attributes = $request->validated();

        if (! array_key_exists('folder_id', $attributes) || $attributes['folder_id'] === null) {
            return response()->json([
                'message' => 'Files can only be moved into folders.',
            ], 422);
        }

        $targetFolderId = $attributes['folder_id'];
        $newName = $this->resolveFileName($attributes['new_name'] ?? $file->file_name);
        $strategy = $attributes['strategy'] ?? 'fail';
        $duplicateCheckName = $this->resolveFileName($attributes['new_name'] ?? $file->file_name);

        $duplicate = $this->findDuplicateFile($file, $targetFolderId, $duplicateCheckName);

        if ($duplicate !== null) {
            if ($strategy === 'replace') {
                $this->replaceFileWithVersion($file, $duplicate, $targetFolderId);

                return response()->json([
                    'message' => 'File replaced successfully.',
                    'data' => $duplicate->refresh()->load(['folder', 'institution', 'uploader', 'currentVersion']),
                ]);
            } elseif ($strategy === 'rename') {
                $newName = $this->generateUniqueFileName($file, $targetFolderId, $newName);
            } else {
                return $this->duplicateResponse($file, $targetFolderId, $newName, $duplicate);
            }
        }

        $file->folder_id = $targetFolderId;
        $file->setAttribute('file_name', $newName);
        $file->save();

        $this->audit->log(
            'file_moved',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' moved file "'.$file->displayName().'".',
            $file,
            [
                'destination_folder_id' => $targetFolderId,
                'new_name' => $newName,
            ]
        );

        return response()->json([
            'message' => 'File moved successfully.',
            'data' => $file->refresh()->load(['folder', 'institution', 'uploader', 'currentVersion']),
        ]);
    }

    // Search helpers live on the model scope.

    private function duplicateResponse(File $file, ?string $folderId, string $fileName, File $duplicate): JsonResponse
    {
        $scopeLabel = $folderId === null ? 'Unorganized Records' : 'the selected folder';

        return response()->json([
            'message' => "A file named '{$fileName}' already exists in {$scopeLabel}. Rename automatically or replace the existing file to continue.",
            'code' => 'duplicate_filename',
            'duplicate_id' => $duplicate->id,
            'duplicate_name' => $duplicate->file_name,
            'folder_id' => $folderId,
            'conflict_scope' => $folderId === null ? 'unorganized_records' : 'folder',
            'suggested_name' => $this->generateUniqueFileName($file, $folderId, $fileName),
        ], 422);
    }

    private function duplicateCreateResponse(?string $folderId, string $fileName, File $duplicate): JsonResponse
    {
        $normalized = $this->resolveFileName($fileName);
        $scopeLabel = $folderId === null ? 'Unorganized Records' : 'the selected folder';

        return response()->json([
            'message' => "A file named '{$normalized}' already exists in {$scopeLabel}. Use the suggested name or provide a different file name.",
            'code' => 'duplicate_filename',
            'duplicate_id' => $duplicate->id,
            'duplicate_name' => $duplicate->file_name,
            'folder_id' => $folderId,
            'conflict_scope' => $folderId === null ? 'unorganized_records' : 'folder',
            'suggested_name' => $this->generateUniqueCreateFileName($folderId, $normalized),
        ], 422);
    }

    private function findDuplicateFile(File $file, ?string $folderId, string $fileName): ?File
    {
        return $this->duplicateFileQuery($folderId, $fileName)
            ->where('id', '!=', $file->id)
            ->first();
    }

    private function findCreateDuplicateFile(?string $folderId, string $fileName): ?File
    {
        return $this->duplicateFileQuery($folderId, $fileName)->first();
    }

    private function duplicateFileQuery(?string $folderId, string $fileName): Builder
    {
        $normalized = $this->resolveFileName($fileName);

        $query = File::query()
            ->where('folder_id', $folderId)
            ->where('file_name', $normalized);

        if ($folderId === null) {
            // Pending scan records are shown in a separate section from Unorganized
            // records and should not block same-name operations in the unorganized scope.
            $query->where(function (Builder $builder): void {
                $builder->where('record_id', 'NOT LIKE', 'SCAN-%')
                    ->orWhere('status', '!=', 'pending')
                    ->orWhereNull('status');
            });
        }

        return $query;
    }

    private function generateUniqueFileName(File $file, ?string $folderId, string $desiredName): string
    {
        $normalized = $this->resolveFileName($desiredName);
        $pathInfo = pathinfo($normalized);
        $baseName = $pathInfo['filename'] ?? $normalized;
        $extension = array_key_exists('extension', $pathInfo) ? '.'.$pathInfo['extension'] : '';

        $candidate = $normalized;
        $counter = 1;

        while ($this->findDuplicateFile($file, $folderId, $candidate) !== null) {
            $candidate = $baseName.' ('.$counter.')'.$extension;
            $counter++;
        }

        return $candidate;
    }

    private function generateUniqueCreateFileName(?string $folderId, string $desiredName): string
    {
        $normalized = $this->resolveFileName($desiredName);
        $pathInfo = pathinfo($normalized);
        $baseName = $pathInfo['filename'] ?? $normalized;
        $extension = array_key_exists('extension', $pathInfo) ? '.'.$pathInfo['extension'] : '';

        $candidate = $normalized;
        $counter = 1;

        while ($this->findCreateDuplicateFile($folderId, $candidate) !== null) {
            $candidate = $baseName.' ('.$counter.')'.$extension;
            $counter++;
        }

        return $candidate;
    }

    private function normalizeFolderId(string|int|null $folderId): ?string
    {
        if ($folderId === null || $folderId === 'null') {
            return null;
        }

        return (string) $folderId;
    }

    private function resolveFileName(?string $preferred, ?string $fallback = null): string
    {
        // Normalize incoming preferred name, then only strip a trailing
        // extension-like segment (short, alphanumeric). This mirrors the
        // client-side `normalizeFileNameInput` behavior and prevents
        // truncating names that legitimately contain internal dots.
        $candidate = FileName::normalize($preferred) ?? '';

        if ($candidate === '') {
            $candidate = FileName::normalize($fallback) ?? '';
        }

        if ($candidate === null || $candidate === '') {
            return 'file';
        }

        $lastDot = strrpos($candidate, '.');
        if ($lastDot !== false && $lastDot > 0) {
            if (($candidate[$lastDot - 1] ?? null) === '.') {
                return $candidate;
            }

            $possibleExt = substr($candidate, $lastDot + 1);
            if (preg_match('/^[A-Za-z0-9]{1,8}$/', $possibleExt)) {
                $candidate = substr($candidate, 0, $lastDot);
            }
        }

        return $candidate;
    }

    private function replaceFileWithVersion(File $source, File $target, ?string $targetFolderId): void
    {
        $source->loadMissing('currentVersion');

        $version = $source->currentVersion;

        $attributes = [
            'file_path' => $version->file_path ?? $source->file_path,
            'file_name' => $target->file_name,
            'mime_type' => $version->mime_type ?? $source->mime_type,
            'file_size' => $version->file_size ?? $source->file_size,
            'uploaded_by' => $version->uploaded_by ?? $source->uploaded_by,
            'assembly_status' => $version->assembly_status ?? $source->assembly_status,
            'ocr_status' => $version->ocr_status ?? $source->ocr_status,
            'ocr_language' => $version->ocr_language ?? $source->ocr_language,
            'ocr_text' => $version->ocr_text ?? $source->ocr_text,
            'ocr_error' => $version->ocr_error ?? $source->ocr_error,
        ];

        $this->fileVersionService->createVersion($target, $attributes);

        if ($targetFolderId !== null) {
            $target->folder_id = $targetFolderId;
            $target->save();
        }

        $source->delete();
    }

    /**
     * @param  array<int|string|null>|null  $ids
     * @return array<int>
     */
    private function normalizeTagIds(?array $ids): array
    {
        return collect($ids ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
