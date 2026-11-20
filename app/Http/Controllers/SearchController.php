<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsFileSearchSnippet;
use App\Concerns\NormalizesSearchTerm;
use App\Models\File;
use App\Models\Folder;
use App\Support\DateTimeFormatter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use BuildsFileSearchSnippet;
    use NormalizesSearchTerm;

    public function __invoke(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $normalizedSearch = $this->normalizeSearchTerm($search);
        $perPage = 12;
        $page = max(1, (int) $request->integer('page', 1));
        $limit = $perPage * $page;

        $fileBaseQuery = File::query()
            ->with(['folder.parent', 'currentVersion', 'tags'])
            ->where('status', 'approved')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest();

        $fileCount = (clone $fileBaseQuery)->count();
        $files = (clone $fileBaseQuery)
            ->limit($limit)
            ->get();

        $folderCount = 0;
        $folders = collect();

        if ($normalizedSearch !== '') {
            $folderBaseQuery = Folder::query()
                ->with('parent')
                ->where('name', 'like', "%{$normalizedSearch}%")
                ->latest();

            $folderCount = (clone $folderBaseQuery)->count();
            $folders = (clone $folderBaseQuery)
                ->limit($limit)
                ->get();
        }

        $results = $this->buildResultsPaginator(
            $files,
            $folders,
            $fileCount,
            $folderCount,
            $search,
            $page,
            $perPage,
            $request
        )->withQueryString();

        return Inertia::render('Search', [
            'search' => $search,
            'results' => $results,
        ]);
    }

    /**
     * @param  Collection<int, File>  $files
     * @param  Collection<int, Folder>  $folders
     */
    private function buildResultsPaginator(
        Collection $files,
        Collection $folders,
        int $fileCount,
        int $folderCount,
        string $search,
        int $page,
        int $perPage,
        Request $request
    ): LengthAwarePaginator {
        $combined = $files
            ->map(fn (File $file) => [
                'type' => 'file',
                'id' => $file->id,
                'name' => $file->displayName(),
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
                'created_at' => DateTimeFormatter::toIso8601($file->created_at),
                'folder' => $file->folder?->only(['id', 'name']),
                'snippet' => $this->buildFileSearchSnippet($file, $search),
                'url' => route('file.view', $file),
                'sort_value' => (int) ($file->created_at?->timestamp ?? 0),
                // Filter fields
                'date_created' => $file->date_created?->format('m/d/Y'),
                'date_released' => $file->date_released?->format('m/d/Y'),
                'author' => $file->author,
                'metadata_tag_ids' => $file->tags->where('type', 'metadata')->pluck('id')->toArray(),
                'physical_location_tag_ids' => $file->tags->where('type', 'physical_location')->pluck('id')->toArray(),
                'retention_duration' => $file->retention_duration,
                'retention_status' => $file->retention_status,
                'is_record' => $file->is_record,
                'folder_id' => $file->folder_id,
                'root_folder_id' => $this->getRootFolderId($file),
            ])
            ->concat($folders->map(fn (Folder $folder) => [
                'type' => 'folder',
                'id' => $folder->id,
                'name' => $folder->name,
                'created_at' => DateTimeFormatter::toIso8601($folder->created_at),
                'parent' => $folder->parent?->only(['id', 'name']),
                'url' => route('records.show', $folder),
                'sort_value' => (int) ($folder->created_at?->timestamp ?? 0),
            ]))
            ->sortByDesc(fn (array $item) => $item['sort_value'])
            ->values();

        $total = $fileCount + $folderCount;
        $offset = ($page - 1) * $perPage;

        $items = $combined
            ->slice($offset, $perPage)
            ->map(function (array $item) {
                unset($item['sort_value']);

                return $item;
            })
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Get the root folder ID for a file by traversing up the folder hierarchy.
     */
    private function getRootFolderId(File $file): ?string
    {
        if (! $file->folder) {
            return null;
        }

        $folder = $file->folder;

        // Traverse up the folder hierarchy until we find a folder with no parent (root folder)
        while ($folder->parent) {
            $folder = $folder->parent;
        }

        return $folder->id;
    }
}
