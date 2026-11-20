<?php

namespace App\Models;

use App\Support\FileName;
use App\Support\RetentionStatus;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'is_record',
        'uploaded_by',
        'author',
        'department_id',
        'date_created',
        'date_released',
        'record_id',
        'physical_location',
        'folder_id',
        'institution_id',
        'document_number',
        'series',
        'remarks',
        'scan_review_notes',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'locked_at',
        'visibility',
        'status',
        'retention_status',
        'approved_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'download_count',
        'expiration_date',
        'retention_duration',
        'retention_expires_at',
        'ocr_text',
        'ocr_status',
        'ocr_language',
        'ocr_error',
        'ocr_retry_count',
        'current_version_id',
        'latest_version_number',
        'restored_at',
    ];

    /**
     * @var list<string>
     */
    protected $with = ['tags'];

    protected $appends = ['pending_scan_metadata', 'metadata_tag_ids', 'physical_location_tag_ids', 'disposal_date'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_record' => 'boolean',
            'locked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'date_created' => 'date',
            'date_released' => 'date',
            'download_count' => 'int',
            'ocr_retry_count' => 'int',
            'expiration_date' => 'datetime',
            'retention_expires_at' => 'date',
            'latest_version_number' => 'int',
            'restored_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::restored(function (File $file) {
            $file->forceFill(['restored_at' => now()])->saveQuietly();
        });
    }

    /**
     * Prepare dates for JSON serialization - ensure consistent YYYY-MM-DD format
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    protected function fileName(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value) => FileName::normalize($value)
        );
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $sanitized = $this->sanitizeSearchTerm($search);

        if ($sanitized === '') {
            return $query;
        }

        if ($this->usingSqlite()) {
            try {
                return $query->where(function (Builder $inner) use ($sanitized): void {
                    $inner->whereIn('id', function ($sub) use ($sanitized): void {
                        $sub->select('file_id')
                            ->from('file_search_index')
                            ->whereRaw('file_search_index match ?', [$sanitized]);
                    })->orWhere(function (Builder $fallback) use ($sanitized): void {
                        $this->applyFallbackLikeSearch($fallback, $sanitized);
                    });
                });
            } catch (QueryException) {
                // Fall back to LIKE search if FTS is unavailable.
            }
        } elseif ($this->usingMysql()) {
            try {
                $query->whereRaw(
                    'MATCH(files.file_name, files.document_number, files.series, files.remarks, files.ocr_text, files.record_id, files.author) AGAINST(? IN BOOLEAN MODE)',
                    [$sanitized]
                );

                return $query->orWhereHas('tags', function (Builder $tagQuery) use ($sanitized): void {
                    $tagQuery
                        ->whereIn('tags.type', ['metadata', 'physical_location'])
                        ->where('tags.name', 'like', "%{$sanitized}%");
                });
            } catch (QueryException) {
                // Fall back to LIKE search if FULLTEXT is unavailable.
            }
        }

        return $this->applyFallbackLikeSearch($query, $sanitized)->orWhereHas('tags', function (Builder $tagQuery) use ($sanitized): void {
            $tagQuery
                ->whereIn('tags.type', ['metadata', 'physical_location'])
                ->where('tags.name', 'like', "%{$sanitized}%");
        });
    }

    private function applyFallbackLikeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $inner) use ($search): void {
            $inner
                ->where('files.record_id', 'like', "%{$search}%")
                ->orWhere('files.author', 'like', "%{$search}%")
                ->orWhere('files.file_name', 'like', "%{$search}%")
                ->orWhere('files.document_number', 'like', "%{$search}%")
                ->orWhere('files.series', 'like', "%{$search}%")
                ->orWhere('files.remarks', 'like', "%{$search}%")
                ->orWhere('files.ocr_text', 'like', "%{$search}%")
                ->orWhereHas('folder', function (Builder $folderQuery) use ($search): void {
                    $folderQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('department', function (Builder $departmentQuery) use ($search): void {
                    $departmentQuery->where('name', 'like', "%{$search}%");
                });
        });
    }

    private function sanitizeSearchTerm(string $search): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9\s]/', ' ', $search) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }

    public function displayName(): string
    {
        $fileName = trim((string) ($this->file_name ?? ''));

        return $fileName === '' ? 'file' : $fileName;
    }

    private function usingSqlite(): bool
    {
        return $this->getConnection()->getDriverName() === 'sqlite';
    }

    private function usingMysql(): bool
    {
        return in_array($this->getConnection()->getDriverName(), ['mysql', 'mariadb']);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function downloads(): MorphMany
    {
        return $this->auditLogs()->whereIn('action', ['file_viewed', 'file_downloaded']);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class, 'current_version_id');
    }

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(Requests::class, 'file_request', 'file_id', 'request_id')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'file_tag_pivot')->withTimestamps();
    }

    public function metadataTags(): BelongsToMany
    {
        return $this->tags()->where('type', 'metadata');
    }

    public function physicalLocationTags(): BelongsToMany
    {
        return $this->tags()->where('type', 'physical_location');
    }

    public function disposalBatchFiles(): HasMany
    {
        return $this->hasMany(DisposalBatchFile::class, 'file_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPendingScanMetadataAttribute(): ?array
    {
        $recordId = (string) ($this->record_id ?? '');

        if ($recordId === '' || ! str_starts_with($recordId, 'SCAN-')) {
            return null;
        }

        if (! is_string($this->remarks) || $this->remarks === '') {
            return null;
        }

        $decoded = json_decode($this->remarks, true);

        if (! is_array($decoded)) {
            return null;
        }

        if (($decoded['type'] ?? null) !== 'request_scan') {
            return null;
        }

        return $decoded;
    }

    protected function metadataTagIds(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->extractTagIdsByType('metadata')
        );
    }

    protected function physicalLocationTagIds(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->extractTagIdsByType('physical_location')
        );
    }

    /**
     * Get the disposal date from the approved disposal batch.
     */
    protected function disposalDate(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->retention_status !== RetentionStatus::DISPOSED) {
                    return null;
                }

                $approvedBatch = $this->disposalBatchFiles()
                    ->with('batch')
                    ->whereHas('batch', function ($query) {
                        $query->where('status', DisposalBatch::STATUS_APPROVED);
                    })
                    ->first();

                return $approvedBatch?->batch->approved_at?->toIso8601String();
            }
        );
    }

    public function syncTags(?array $metadataTagIds = null, ?array $physicalLocationTagIds = null): void
    {
        $metadataTagIds ??= $this->metadata_tag_ids ?? [];
        $physicalLocationTagIds ??= $this->physical_location_tag_ids ?? [];

        $tagIds = collect([$metadataTagIds, $physicalLocationTagIds])
            ->flatten()
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($tagIds)) {
            $this->tags()->detach();

            $this->unsetRelation('tags');

            return;
        }

        $this->tags()->sync($tagIds);
        $this->load('tags');
    }

    private function extractTagIdsByType(string $type): array
    {
        $tags = $this->relationLoaded('tags') ? $this->tags : $this->tags()->get();

        return $tags
            ->where('type', $type)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
