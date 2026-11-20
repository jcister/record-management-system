<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folder extends Model
{
    /** @use HasFactory<\Database\Factories\FolderFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'parent_id',
        'is_basic',
        'is_locked',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_basic' => 'bool',
            'is_locked' => 'bool',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class)
            ->where('retention_status', '!=', 'archived');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $sanitized = trim($search);

        if ($sanitized === '') {
            return $query;
        }

        return $query->where('name', 'like', "%{$sanitized}%");
    }

    protected static function booted(): void
    {
        static::deleting(function (Folder $folder): void {
            if ($folder->isForceDeleting()) {
                File::query()
                    ->where('folder_id', $folder->id)
                    ->withTrashed()
                    ->get()
                    ->each
                    ->forceDelete();

                $folder->children()->withTrashed()->get()->each->forceDelete();

                return;
            }

            File::query()
                ->where('folder_id', $folder->id)
                ->delete();

            $folder->children()->get()->each(function (Folder $child): void {
                $child->delete();
            });
        });

        static::restored(function (Folder $folder): void {
            File::query()
                ->where('folder_id', $folder->id)
                ->where('retention_status', '!=', 'archived')
                ->onlyTrashed()
                ->get()
                ->each(function (File $file): void {
                    $file->restore();
                });

            $folder->children()
                ->onlyTrashed()
                ->get()
                ->each(function (Folder $child): void {
                    $child->restore();
                });
        });
    }
}
