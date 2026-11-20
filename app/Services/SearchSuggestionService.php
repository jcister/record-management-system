<?php

namespace App\Services;

use App\Concerns\BuildsFileSearchSnippet;
use App\Concerns\NormalizesSearchTerm;
use App\Models\File;
use App\Models\Folder;
use App\Support\DateTimeFormatter;
use Illuminate\Support\Collection;

class SearchSuggestionService
{
    use BuildsFileSearchSnippet;
    use NormalizesSearchTerm;

    private const MAX_CACHED_RESULTS = 10;

    public function __construct(
        private CacheService $cache
    ) {}

    /**
     * Get the cache TTL in minutes for search suggestions.
     */
    private function getCacheTtlMinutes(): int
    {
        return (int) config('cache.search_suggestions_ttl', 60);
    }

    /**
     * Get search suggestions for a given search term.
     */
    public function getSuggestions(string $search, int $limit): array
    {
        $normalizedSearch = $this->normalizeSearchTerm($search);

        if ($normalizedSearch === '') {
            return [
                'data' => [],
                'has_more' => false,
            ];
        }

        $cacheKey = 'search:suggestions:v2:'.md5($normalizedSearch);

        // Use cache abstraction that handles both Redis and database drivers
        $cachedResults = $this->cache->rememberWithTags(
            ['search:suggestions'],
            $cacheKey,
            now()->addMinutes($this->getCacheTtlMinutes()),
            fn () => $this->fetchSuggestionsFromDatabase($normalizedSearch, self::MAX_CACHED_RESULTS)
        );

        return $this->sliceResults($cachedResults, $limit);
    }

    /**
     * Fetch suggestions from the database.
     */
    private function fetchSuggestionsFromDatabase(string $normalizedSearch, int $limit): array
    {
        $files = File::query()
            ->with(['folder', 'currentVersion'])
            ->where('status', 'approved')
            ->where('retention_status', '!=', 'archived')
            ->search($normalizedSearch)
            ->latest()
            ->limit($limit + 1)
            ->get();

        $folders = Folder::query()
            ->where('name', 'like', "%{$normalizedSearch}%")
            ->latest()
            ->limit($limit + 1)
            ->get();

        $fileSuggestions = $files
            ->map(fn (File $file) => [
                'type' => 'file',
                'id' => $file->id,
                'file_name' => $file->displayName(),
                'mime_type' => $file->mime_type,
                'created_at' => DateTimeFormatter::toIso8601($file->created_at),
                'folder' => $file->folder
                    ? [
                        'id' => $file->folder->id,
                        'name' => $file->folder->name,
                        'viewer_url' => route('records.show', $file->folder),
                    ]
                    : null,
                'viewer_url' => route('file.view', $file),
                'snippet' => $this->buildFileSearchSnippet($file, $normalizedSearch),
            ])
            ->values();

        $folderSuggestions = $folders
            ->map(fn (Folder $folder) => [
                'type' => 'folder',
                'id' => $folder->id,
                'file_name' => $folder->name,
                'mime_type' => null,
                'created_at' => DateTimeFormatter::toIso8601($folder->created_at),
                'folder' => null,
                'viewer_url' => route('records.show', $folder),
                'snippet' => null,
            ])
            ->values();

        [$suggestions, $fileIndex, $folderIndex] = $this->mergeSuggestions(
            $fileSuggestions,
            $folderSuggestions,
            $limit
        );

        $hasMore = ($fileSuggestions->count() > $fileIndex) || ($folderSuggestions->count() > $folderIndex);

        return [
            'data' => $suggestions->toArray(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * Merge file and folder suggestions in an alternating pattern.
     */
    private function mergeSuggestions(Collection $files, Collection $folders, int $limit): array
    {
        $results = collect();
        $fileIndex = 0;
        $folderIndex = 0;

        while ($results->count() < $limit && ($fileIndex < $files->count() || $folderIndex < $folders->count())) {
            if ($fileIndex < $files->count()) {
                $results->push($files->get($fileIndex));
                $fileIndex++;
            }

            if ($results->count() >= $limit) {
                break;
            }

            if ($folderIndex < $folders->count()) {
                $results->push($folders->get($folderIndex));
                $folderIndex++;
            }
        }

        return [$results, $fileIndex, $folderIndex];
    }

    /**
     * Slice cached results to the requested limit.
     */
    private function sliceResults(array $cachedResults, int $limit): array
    {
        $data = $cachedResults['data'] ?? [];
        $totalAvailable = count($data);

        return [
            'data' => array_slice($data, 0, $limit),
            'has_more' => $totalAvailable > $limit || $cachedResults['has_more'],
        ];
    }
}
