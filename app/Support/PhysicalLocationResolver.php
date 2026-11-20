<?php

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

class PhysicalLocationResolver
{
    /**
     * Resolve a displayable physical location string from the provided tag identifiers.
     *
     * @param  array<int|string|null>  $tagIds
     */
    public static function resolve(array $tagIds): ?string
    {
        $ids = collect($tagIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $namesById = Tag::query()
            ->physicalLocation()
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        $orderedNames = $ids
            ->map(fn (int $id) => $namesById[$id] ?? null)
            ->filter()
            ->values();

        if ($orderedNames->isEmpty()) {
            return null;
        }

        return Str::limit($orderedNames->implode(', '), 255, '');
    }
}
