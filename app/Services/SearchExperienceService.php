<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SearchExperienceService
{
    private const FREQUENT_LIMIT = 10;

    private const FREQUENT_FILES_SUFFIX = 'trending:files';

    private const LAST_ACTIVITY_SUFFIX = 'last-activity';

    private const FILE_MEMBER = 'file';

    private const FOLDER_MEMBER = 'folder';

    private ?string $resolvedFilesKey = null;

    public function __construct(
        private CacheService $cache
    ) {}

    public function getSharedData(): array
    {
        $state = $this->buildFrequentlyAccessedState();

        return [
            'frequently_accessed' => $state['items'],
            'last_recorded_at' => $state['last_recorded_at'],
        ];
    }

    public function getPanelDiagnostics(): array
    {
        $state = $this->buildFrequentlyAccessedState();

        return [
            'items' => $state['items'],
            'scores' => $state['scores'],
            'cache_key' => $this->filesKey(),
            'ttl' => $this->trendingTtl(),
            'has_data' => ! empty($state['scores']),
            'last_recorded_at' => $state['last_recorded_at'],
            'total_members' => $state['total_members'],
            'removed_members' => $state['removed_members'],
            'limit' => self::FREQUENT_LIMIT,
        ];
    }

    public function clearPanelCache(): void
    {
        $this->cache->deleteSortedSet($this->filesKey());
        Cache::forget($this->lastActivityCacheKey());
    }

    public function recordFileView(File $file): void
    {
        $this->recordItemView(self::FILE_MEMBER, (string) $file->id);
    }

    public function recordFolderView(Folder $folder): void
    {
        $this->recordItemView(self::FOLDER_MEMBER, (string) $folder->id);
    }

    public function trendingFilesCacheKey(): string
    {
        return $this->filesKey();
    }

    private function buildFrequentlyAccessedState(): array
    {
        $scores = $this->cache->getSortedSetTop($this->filesKey(), 0, self::FREQUENT_LIMIT - 1);

        if ($scores === []) {
            return [
                'items' => [],
                'scores' => [],
                'removed_members' => [],
                'total_members' => 0,
                'last_recorded_at' => $this->lastActivityTimestamp(),
            ];
        }

        $decodedMembers = [];
        $fileIds = [];
        $folderIds = [];

        foreach ($scores as $member => $score) {
            $decoded = $this->decodeMember($member);
            $decodedMembers[$member] = $decoded;

            if ($decoded === null) {
                continue;
            }

            if ($decoded['type'] === self::FILE_MEMBER) {
                $fileIds[] = $decoded['id'];
            } elseif ($decoded['type'] === self::FOLDER_MEMBER) {
                $folderIds[] = $decoded['id'];
            }
        }

        $files = $fileIds === []
            ? collect()
            : File::query()
                ->with('folder:id,name')
                ->whereIn('id', $fileIds)
                ->get()
                ->keyBy('id');

        $folders = $folderIds === []
            ? collect()
            : Folder::query()
                ->with('parent:id,name')
                ->whereIn('id', $folderIds)
                ->get()
                ->keyBy('id');

        $results = [];
        $validScores = [];
        $removedMembers = [];

        foreach ($scores as $member => $score) {
            $decoded = $decodedMembers[$member];

            if ($decoded === null) {
                $removedMembers[] = $member;

                continue;
            }

            if ($decoded['type'] === self::FILE_MEMBER) {
                $file = $files->get($decoded['id']);

                if (! $file) {
                    $removedMembers[] = $member;

                    continue;
                }

                $results[] = [
                    'id' => $file->id,
                    'type' => self::FILE_MEMBER,
                    'name' => $file->displayName(),
                    'viewer_url' => route('file.view', $file),
                    'folder' => $file->folder
                        ? [
                            'id' => $file->folder->id,
                            'name' => $file->folder->name,
                            'viewer_url' => route('records.show', $file->folder),
                        ]
                        : null,
                    'views' => (int) $score,
                ];
                $validScores[$member] = $score;

                continue;
            }

            if ($decoded['type'] === self::FOLDER_MEMBER) {
                $folder = $folders->get($decoded['id']);

                if (! $folder) {
                    $removedMembers[] = $member;

                    continue;
                }

                $results[] = [
                    'id' => $folder->id,
                    'type' => self::FOLDER_MEMBER,
                    'name' => $folder->name,
                    'viewer_url' => route('records.show', $folder),
                    'folder' => $folder->parent
                        ? [
                            'id' => $folder->parent->id,
                            'name' => $folder->parent->name,
                            'viewer_url' => route('records.show', $folder->parent),
                        ]
                        : null,
                    'views' => (int) $score,
                ];
                $validScores[$member] = $score;

                continue;
            }

            $removedMembers[] = $member;
        }

        if ($removedMembers !== []) {
            $this->cache->removeSortedSetMembers($this->filesKey(), $removedMembers);
        }

        return [
            'items' => $results,
            'scores' => $validScores,
            'removed_members' => $removedMembers,
            'total_members' => count($scores),
            'last_recorded_at' => $this->lastActivityTimestamp(),
        ];
    }

    private function filesKey(): string
    {
        if ($this->resolvedFilesKey !== null) {
            return $this->resolvedFilesKey;
        }

        $prefix = (string) config('cache.prefix', '');

        if ($prefix === '') {
            return $this->resolvedFilesKey = self::FREQUENT_FILES_SUFFIX;
        }

        if (str_ends_with($prefix, '-') || str_ends_with($prefix, ':') || str_ends_with($prefix, '_')) {
            return $this->resolvedFilesKey = $prefix.self::FREQUENT_FILES_SUFFIX;
        }

        return $this->resolvedFilesKey = $prefix.'-'.self::FREQUENT_FILES_SUFFIX;
    }

    private function trendingTtl(): int
    {
        $configured = (int) config('cache.trending_ttl', 3600);

        return max(60, $configured);
    }

    private function recordItemView(string $type, string $id): void
    {
        $member = $this->encodeMember($type, $id);
        $key = $this->filesKey();
        $ttl = $this->trendingTtl();

        $this->cache->incrementSortedSet($key, 1, $member);
        $this->cache->expireSortedSet($key, $ttl);
        $this->recordLastActivityTimestamp($ttl);
    }

    private function encodeMember(string $type, string $id): string
    {
        return sprintf('%s:%s', $type, $id);
    }

    private function decodeMember(string $member): ?array
    {
        if ($member === '') {
            return null;
        }

        if (str_contains($member, ':')) {
            [$type, $id] = explode(':', $member, 2);

            if ($type === '' || $id === '') {
                return null;
            }

            return [
                'type' => $type,
                'id' => $id,
            ];
        }

        return [
            'type' => self::FILE_MEMBER,
            'id' => $member,
        ];
    }

    private function recordLastActivityTimestamp(int $ttl): void
    {
        $expiresAt = now()->addSeconds($ttl);
        Cache::put($this->lastActivityCacheKey(), now()->toIso8601String(), $expiresAt);
    }

    private function lastActivityTimestamp(): ?string
    {
        $value = Cache::get($this->lastActivityCacheKey());

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private function lastActivityCacheKey(): string
    {
        return $this->filesKey().':'.self::LAST_ACTIVITY_SUFFIX;
    }
}
