<?php

use App\Models\File;
use App\Models\Folder;
use App\Services\CacheService;
use App\Services\SearchExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('tracks frequently accessed files using the database cache driver', function () {
    config(['cache.default' => 'database']);
    CacheService::resetDriverState();

    $folder = Folder::factory()->create();
    $topFile = File::factory()->create(['folder_id' => $folder->id]);
    $secondaryFile = File::factory()->create();

    /** @var SearchExperienceService $service */
    $service = app(SearchExperienceService::class);
    $service->clearPanelCache();

    $service->recordFileView($secondaryFile);
    $service->recordFileView($topFile);
    $service->recordFileView($topFile);

    $panel = $service->getSharedData();
    $ids = collect($panel['frequently_accessed'])->pluck('id')->all();

    expect($panel['frequently_accessed'][0]['id'])->toBe($topFile->id)
        ->and($panel['frequently_accessed'][0]['views'])->toBe(2)
        ->and($panel['frequently_accessed'][0]['folder']['name'])->toBe($folder->name)
        ->and($ids)->toBe([$topFile->id, $secondaryFile->id]);
});

it('tracks frequently accessed files using an in-memory redis simulation', function () {
    config(['cache.trending_ttl' => 1800]);

    $fakeCache = new class extends CacheService
    {
        public array $sortedSets = [];

        public array $expirations = [];

        public function incrementSortedSet(string $key, float $increment, string $member): bool
        {
            $this->sortedSets[$key][$member] = ($this->sortedSets[$key][$member] ?? 0) + $increment;

            return true;
        }

        public function expireSortedSet(string $key, int $seconds): bool
        {
            $this->expirations[$key] = $seconds;

            return true;
        }

        public function getSortedSetTop(string $key, int $start, int $stop, bool $withScores = true): array
        {
            if (! isset($this->sortedSets[$key])) {
                return [];
            }

            arsort($this->sortedSets[$key]);
            $slice = array_slice($this->sortedSets[$key], $start, ($stop - $start) + 1, true);

            return $slice;
        }

        public function deleteSortedSet(string $key): bool
        {
            unset($this->sortedSets[$key]);

            return true;
        }
    };

    $service = new SearchExperienceService($fakeCache);

    $file = File::factory()->create();

    $service->recordFileView($file);

    $panel = $service->getSharedData();

    expect($panel['frequently_accessed'][0]['id'])->toBe($file->id)
        ->and($panel['frequently_accessed'][0]['views'])->toBe(1);

    $filesKey = $service->trendingFilesCacheKey();
    expect($fakeCache->expirations[$filesKey] ?? null)->toBe(1800);

    $service->clearPanelCache();

    expect($service->getSharedData()['frequently_accessed'])->toBeEmpty();
});

it('tracks frequently accessed folders', function () {
    config(['cache.default' => 'database']);
    CacheService::resetDriverState();

    $parent = Folder::factory()->create();
    $child = Folder::factory()->create(['parent_id' => $parent->id]);

    /** @var SearchExperienceService $service */
    $service = app(SearchExperienceService::class);
    $service->clearPanelCache();

    $service->recordFolderView($child);

    $panel = $service->getSharedData();
    $entry = $panel['frequently_accessed'][0];

    expect($entry['type'])->toBe('folder')
        ->and($entry['id'])->toBe($child->id)
        ->and($entry['folder']['id'])->toBe($parent->id)
        ->and($entry['views'])->toBe(1);
});

it('removes stale cache members when source records are gone', function () {
    config(['cache.default' => 'database']);
    CacheService::resetDriverState();

    /** @var SearchExperienceService $service */
    $service = app(SearchExperienceService::class);
    $service->clearPanelCache();

    $file = File::factory()->create();
    $service->recordFileView($file);

    $key = $service->trendingFilesCacheKey();
    expect(app(CacheService::class)->getSortedSetTop($key, 0, 0))->not->toBe([]);

    $file->forceDelete();
    $service->getSharedData();

    expect(app(CacheService::class)->getSortedSetTop($key, 0, 0))->toBe([]);
});
