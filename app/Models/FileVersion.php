<?php

namespace App\Models;

use App\Support\FileName;
use Database\Factories\FileVersionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileVersion extends Model
{
    /** @use HasFactory<FileVersionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_id',
        'version_number',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
        'checksum',
        'version_label',
        'change_summary',
        'ocr_status',
        'ocr_text',
        'ocr_language',
        'ocr_error',
        'ocr_retry_count',
        'assembly_status',
        'thumbnail_disk',
        'thumbnail_path_webp',
        'thumbnail_path_jpeg',
        'thumbnail_generated_at',
    ];

    protected $touches = ['file'];

    /**
     * @var list<string>
     */
    protected $appends = ['thumbnail_url', 'thumbnail_urls'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'int',
            'file_size' => 'int',
            'ocr_retry_count' => 'int',
            'thumbnail_generated_at' => 'datetime',
        ];
    }

    protected function fileName(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value) => FileName::normalize($value)
        );
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->buildThumbnailUrl(
                $this->thumbnail_path_webp !== null ? 'webp' : ($this->thumbnail_path_jpeg !== null ? 'jpeg' : null)
            )
        );
    }

    protected function thumbnailUrls(): Attribute
    {
        return Attribute::make(
            get: fn (): array => [
                'webp' => $this->thumbnail_path_webp !== null ? $this->buildThumbnailUrl('webp') : null,
                'jpeg' => $this->thumbnail_path_jpeg !== null ? $this->buildThumbnailUrl('jpeg') : null,
            ]
        );
    }

    private function buildThumbnailUrl(?string $format): ?string
    {
        if ($format === null || $this->file_id === null || $this->id === null) {
            return null;
        }

        try {
            return route('api.files.versions.thumbnail', [
                'file' => $this->file_id,
                'version' => $this->id,
                'format' => $format,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
