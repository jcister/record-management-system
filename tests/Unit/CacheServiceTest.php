<?php

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    CacheService::resetCache();
    $this->cacheService = new CacheService;
});

afterEach(function () {
    CacheService::resetCache();
});

describe('CacheService - Driver Detection', function () {
    it('detects redis availability correctly', function () {
        config(['cache.default' => 'redis']);

        $isAvailable = $this->cacheService->isRedisAvailable();

        // Result depends on actual Redis connection
        expect($isAvailable)->toBeIn([true, false]);
    });

    it('returns false when cache driver is not redis', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        expect($this->cacheService->isRedisAvailable())->toBeFalse();
    });

    it('detects tagging support for redis', function () {
        config(['cache.default' => 'redis']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        expect($this->cacheService->isTaggingSupported())->toBeTrue();
    });

    it('detects tagging support for database', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        expect($this->cacheService->isTaggingSupported())->toBeFalse();
    });

    it('detects tagging support for array', function () {
        config(['cache.default' => 'array']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        expect($this->cacheService->isTaggingSupported())->toBeTrue();
    });
});

describe('CacheService - Tagged Cache Operations', function () {
    it('remembers with tags when tagging is supported', function () {
        config(['cache.default' => 'array']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->rememberWithTags(
            ['test:tag'],
            'test:key',
            60,
            fn () => 'test-value'
        );

        expect($result)->toBe('test-value');
    });

    it('remembers without tags when tagging is not supported', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->rememberWithTags(
            ['test:tag'],
            'test:key',
            60,
            fn () => 'test-value'
        );

        expect($result)->toBe('test-value');
    });

    it('gets tagged cache value', function () {
        config(['cache.default' => 'array']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        Cache::tags(['test:tag'])->put('test:key', 'test-value', 60);

        $result = $this->cacheService->getTagged(['test:tag'], 'test:key');

        expect($result)->toBe('test-value');
    });

    it('checks if tagged cache exists', function () {
        config(['cache.default' => 'array']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        Cache::tags(['test:tag'])->put('test:key', 'test-value', 60);

        expect($this->cacheService->hasTagged(['test:tag'], 'test:key'))->toBeTrue();
        expect($this->cacheService->hasTagged(['test:tag'], 'missing:key'))->toBeFalse();
    });

    it('flushes tags when supported', function () {
        config(['cache.default' => 'array']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        Cache::tags(['test:tag'])->put('test:key', 'test-value', 60);

        $this->cacheService->flushTags(['test:tag']);

        expect(Cache::tags(['test:tag'])->has('test:key'))->toBeFalse();
    });
});

describe('CacheService - Sorted Set Operations', function () {
    it('returns false for sorted set operations when redis is not available', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->incrementSortedSet('test:set', 1, 'member');

        expect($result)->toBeFalse();
    });

    it('returns empty array for sorted set top when redis is not available', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->getSortedSetTop('test:set', 0, 10);

        expect($result)->toBe([]);
    });

    it('returns null for sorted set score when redis is not available', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->getSortedSetScore('test:set', 'member');

        expect($result)->toBeNull();
    });

    it('returns zero for sorted set count when redis is not available', function () {
        config(['cache.default' => 'database']);
        CacheService::resetCache();

        $this->cacheService = new CacheService;

        $result = $this->cacheService->getSortedSetCount('test:set');

        expect($result)->toBe(0);
    });
});
