<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Requests extends Model
{
    /** @use HasFactory<\Database\Factories\RequestsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'request_id',
        'requested_by',
        'document_type',
        'file_number',
        'start_date',
        'end_date',
        'request_title',
        'purpose',
        'status',
        'notes',
        'required_format',
        'priority',
        'requires_scan',
        'scan_review_status',
        'scan_review_notes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'metadata_tag_ids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'requires_scan' => 'boolean',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'file_request', 'request_id', 'file_id')->withTimestamps();
    }

    public function metadataTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'request_tag_pivot', 'request_id', 'tag_id')->withTimestamps();
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    protected function metadataTagIds(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->relationLoaded('metadataTags')) {
                $this->load('metadataTags');
            }

            return $this->metadataTags->pluck('id')->map(fn ($id) => (int) $id)->all();
        });
    }
}
