<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
    ];

    /**
     * Scope to filter metadata tags only.
     */
    public function scopeMetadata($query)
    {
        return $query->where('type', 'metadata');
    }

    /**
     * Scope to filter physical location tags only.
     */
    public function scopePhysicalLocation($query)
    {
        return $query->where('type', 'physical_location');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'file_tag_pivot')->withTimestamps();
    }

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(Requests::class, 'request_tag_pivot', 'tag_id', 'request_id')->withTimestamps();
    }
}
