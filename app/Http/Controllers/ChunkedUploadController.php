<?php

namespace App\Http\Controllers;

use App\Jobs\AssembleChunkedFile;
use App\Models\File;
use App\Services\AuditService;
use App\Services\FileChunkHandler;
use App\Support\DateInput;
use App\Support\DateTimeFormatter;
use App\Support\FileName;
use App\Support\PhysicalLocationResolver;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChunkedUploadController extends Controller
{
    public function __construct(
        protected FileChunkHandler $chunkHandler,
        protected AuditService $audit
    ) {}

    public function start(Request $request): JsonResponse
    {
        $request->merge([
            'date_created' => DateInput::normalize($request->input('date_created')),
            'date_released' => DateInput::normalize($request->input('date_released')),
        ]);

        $isFileOnly = $request->input('upload_mode') === 'file_only';
        $allowedDurations = [
            RetentionSchedule::DURATION_ONE_YEAR,
            RetentionSchedule::DURATION_TEN_YEARS,
            RetentionSchedule::DURATION_PERPETUAL,
        ];
        $allowedStatuses = RetentionStatus::options();

        $rules = [
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1|max:104857600', // 100MB max
            'total_chunks' => 'required|integer|min:1',
            'author' => 'nullable|string|max:255',
            'folder_id' => 'nullable|exists:folders,id',
            'date_created' => ['nullable', 'date'],
            'date_released' => $isFileOnly
                ? ['nullable', 'date', 'after_or_equal:date_created']
                : ['required', 'date', 'after_or_equal:date_created'],
            'visibility' => 'required|in:public,restricted,private',
            'status' => 'required|in:pending,approved,rejected',
            'retention_status' => ['sometimes', 'nullable', 'string', Rule::in($allowedStatuses)],
            'metadata_tag_ids' => ['nullable', 'array'],
            'metadata_tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('type', 'metadata')),
            ],
            'retention_duration' => $isFileOnly
                ? ['sometimes', 'nullable', 'string', Rule::in($allowedDurations)]
                : ['required', 'string', Rule::in($allowedDurations)],
            'physical_location_tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('type', 'physical_location')),
            ],
        ];

        if ($isFileOnly) {
            $rules['upload_mode'] = ['sometimes', 'in:file_only'];
            $rules['department_id'] = ['nullable', 'exists:departments,id'];
            $rules['record_id'] = ['nullable', 'string', 'max:255'];
            $rules['physical_location_tag_ids'] = ['sometimes', 'nullable', 'array'];
        } else {
            $rules['department_id'] = ['required', 'exists:departments,id'];
            $rules['record_id'] = ['nullable', 'string', 'max:255'];
            $rules['physical_location_tag_ids'] = ['required', 'array', 'min:1'];
        }

        $request->validate($rules);

        $metadataTagIds = $this->normalizeTagIds($request->input('metadata_tag_ids', []));
        $physicalLocationTagIds = $this->normalizeTagIds($request->input('physical_location_tag_ids', []));

        $physicalLocation = null;
        if (! empty($physicalLocationTagIds)) {
            $physicalLocation = PhysicalLocationResolver::resolve($physicalLocationTagIds);
        }
        if ($physicalLocation === null && ! $isFileOnly) {
            throw ValidationException::withMessages([
                'physical_location_tag_ids' => ['Select at least one valid physical location tag.'],
            ]);
        }

        $displayName = $this->resolveFileName($request->input('file_name'));
        $originalExtension = $this->extractOriginalExtension($request->input('file_name'));
        $retentionStatus = $request->input('retention_status') ?? RetentionStatus::ACTIVE;

        $uploadSessionId = (string) Str::uuid();

        $sessionData = [
            'upload_session_id' => $uploadSessionId,
            'user_id' => $request->user()->id,
            'file_name' => $displayName,
            'original_extension' => $originalExtension,
            'file_size' => $request->input('file_size'),
            'total_chunks' => $request->input('total_chunks'),
            'received_chunks' => [],
            'metadata' => [
                'author' => $request->input('author'),
                'department_id' => $request->input('department_id'),
                'record_id' => $request->input('record_id'),
                'date_created' => $request->input('date_created'),
                'date_released' => $request->input('date_released'),
                'physical_location' => $physicalLocation,
                'folder_id' => $request->input('folder_id'),
                'visibility' => $request->input('visibility'),
                'status' => $request->input('status'),
                'retention_status' => $retentionStatus,
                'retention_duration' => $request->input('retention_duration'),
                'retention_expires_at' => RetentionSchedule::expiresAt(
                    $request->input('date_released'),
                    $request->input('retention_duration')
                ),
            ],
            'metadata_tag_ids' => $metadataTagIds,
            'physical_location_tag_ids' => $physicalLocationTagIds,
            'started_at' => DateTimeFormatter::toIso8601(now()),
        ];

        Cache::put(
            "chunk_upload:{$uploadSessionId}",
            $sessionData,
            now()->addHours(2)
        );

        $this->audit->log(
            'chunk_upload_started',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' started uploading "'.$displayName.'".',
            null,
            [
                'upload_session_id' => $uploadSessionId,
                'file_name' => $displayName,
                'file_size' => $request->input('file_size'),
                'total_chunks' => $request->input('total_chunks'),
            ]
        );

        return response()->json([
            'upload_session_id' => $uploadSessionId,
            'message' => 'Upload session initialized',
        ], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'upload_session_id' => 'required|uuid',
        ]);

        $uploadSessionId = $request->input('upload_session_id');

        $sessionData = Cache::get("chunk_upload:{$uploadSessionId}");

        if (! $sessionData) {
            return response()->json([
                'error' => 'Upload session not found or expired',
            ], 404);
        }

        try {
            $file = $request->file('file');

            if (! $file) {
                return response()->json([
                    'error' => 'No file uploaded',
                ], 400);
            }

            $chunkIndex = (int) $request->input('chunk_index', 0);

            $this->chunkHandler->storeChunk(
                $uploadSessionId,
                $chunkIndex,
                $file
            );

            $sessionData['received_chunks'][] = $chunkIndex;
            $sessionData['received_chunks'] = array_unique($sessionData['received_chunks']);
            sort($sessionData['received_chunks']);

            Cache::put(
                "chunk_upload:{$uploadSessionId}",
                $sessionData,
                now()->addHours(2)
            );

            $totalChunks = $sessionData['total_chunks'];
            $receivedCount = count($sessionData['received_chunks']);

            return response()->json([
                'message' => 'Chunk received successfully',
                'chunk_index' => $chunkIndex,
                'received_chunks' => $receivedCount,
                'total_chunks' => $totalChunks,
                'completed' => $receivedCount === $totalChunks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Chunk upload failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'upload_session_id' => 'required|uuid',
        ]);

        $uploadSessionId = $request->input('upload_session_id');

        $sessionData = Cache::get("chunk_upload:{$uploadSessionId}");

        if (! $sessionData) {
            return response()->json([
                'error' => 'Upload session not found or expired',
            ], 404);
        }

        $receivedCount = count($sessionData['received_chunks']);
        $totalChunks = $sessionData['total_chunks'];

        if ($receivedCount !== $totalChunks) {
            return response()->json([
                'error' => 'Not all chunks received',
                'received' => $receivedCount,
                'expected' => $totalChunks,
            ], 400);
        }

        // Immediately persist the File record so the frontend can display it
        // while assembly and OCR run asynchronously in the background.
        $metadata = array_filter($sessionData['metadata']);
        $metadata['retention_status'] = $metadata['retention_status'] ?? RetentionStatus::ACTIVE;
        $targetFolderId = $metadata['folder_id'] ?? null;
        $candidateName = $this->resolveFileName($sessionData['file_name'] ?? null);

        $duplicate = $this->findCreateDuplicateFile($targetFolderId, $candidateName);

        if ($duplicate !== null) {
            $scopeLabel = $targetFolderId === null ? 'Unorganized Records' : 'the selected folder';

            return response()->json([
                'message' => "A file named '{$candidateName}' already exists in {$scopeLabel}. Use the suggested name or provide a different file name.",
                'code' => 'duplicate_filename',
                'duplicate_id' => $duplicate->id,
                'duplicate_name' => $duplicate->file_name,
                'folder_id' => $targetFolderId,
                'conflict_scope' => $targetFolderId === null ? 'unorganized_records' : 'folder',
                'suggested_name' => $this->generateUniqueCreateFileName($targetFolderId, $candidateName),
            ], 422);
        }

        $file = File::query()->create(array_merge($metadata, [
            'file_name' => $candidateName,
            'uploaded_by' => $sessionData['user_id'],
            'assembly_status' => 'processing',
            'ocr_status' => 'pending',
        ]));

        $file->syncTags(
            $sessionData['metadata_tag_ids'] ?? [],
            $sessionData['physical_location_tag_ids'] ?? []
        );

        AssembleChunkedFile::dispatch($uploadSessionId, $file->id);

        $this->audit->log(
            'chunk_upload_completed',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' finished uploading "'.$sessionData['file_name'].'".',
            $file,
            [
                'upload_session_id' => $uploadSessionId,
                'file_name' => $sessionData['file_name'] ?? null,
                'file_id' => $file->id,
            ]
        );

        return response()->json([
            'message' => 'File assembly started',
            'upload_session_id' => $uploadSessionId,
            'data' => $file->load(['folder', 'uploader', 'department', 'currentVersion']),
        ], 202);
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
            $query->where(function (Builder $builder): void {
                $builder->where('record_id', 'NOT LIKE', 'SCAN-%')
                    ->orWhere('status', '!=', 'pending')
                    ->orWhereNull('status');
            });
        }

        return $query;
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

    private function resolveFileName(?string $preferred, ?string $fallback = null): string
    {
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

    private function extractOriginalExtension(?string $fileName): string
    {
        if ($fileName === null || $fileName === '') {
            return '';
        }

        $normalized = FileName::normalize($fileName);
        if ($normalized === null || $normalized === '') {
            return '';
        }

        $lastDot = strrpos($normalized, '.');
        if ($lastDot === false || $lastDot === 0) {
            return '';
        }

        $possibleExt = substr($normalized, $lastDot + 1);
        if (preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{1,8}$/', $possibleExt)) {
            return strtolower($possibleExt);
        }

        return '';
    }
}
