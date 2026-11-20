<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class SearchFilterOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Always fetch fresh data from database - no caching
        $options = [
            'authors' => $this->getAuthors(),
            'rootFolders' => $this->getRootFolders(),
            'departments' => $this->getDepartments(),
            'metadataTags' => $this->getMetadataTags(),
            'physicalLocationTags' => $this->getPhysicalLocationTags(),
            'retentionDurations' => $this->getRetentionDurations(),
        ];

        return response()->json($options);
    }

    private function getAuthors(): array
    {
        return File::query()
            ->whereNotNull('author')
            ->where('author', '!=', '')
            ->distinct()
            ->pluck('author')
            ->sort()
            ->values()
            ->map(fn ($author) => [
                'id' => $author,
                'name' => $author,
            ])
            ->all();
    }

    private function getRootFolders(): array
    {
        return Folder::query()
            ->whereNull('parent_id')
            ->where('is_basic', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
            ])
            ->all();
    }

    private function getDepartments(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($department) => [
                'id' => (string) $department->id,
                'name' => $department->name,
            ])
            ->all();
    }

    private function getMetadataTags(): array
    {
        return Tag::query()
            ->metadata()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])
            ->all();
    }

    private function getPhysicalLocationTags(): array
    {
        return Tag::query()
            ->physicalLocation()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])
            ->all();
    }

    private function getRetentionDurations(): array
    {
        return [
            ['value' => 'one_year', 'label' => '1 Year'],
            ['value' => 'ten_years', 'label' => '10 Years'],
            ['value' => 'perpetual', 'label' => 'Perpetual'],
        ];
    }
}
