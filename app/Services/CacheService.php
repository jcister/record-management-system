<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Unified cache abstraction layer that handles both Redis and database cache drivers.
 * Provides consistent API and automatic fallback for features not supported by all drivers.
 */
class CacheService
{
    private static ?bool $redisAvailable = null;

    private static ?bool $taggingSupported = null;

    public static function resetDriverState(): void
    {
        self::$redisAvailable = null;
        self::$taggingSupported = null;
    }

    /**
     * Check if Redis is available and enabled.
     */
    public function isRedisAvailable(): bool
    {
        if (self::$redisAvailable !== null) {
            return self::$redisAvailable;
        }

        // Check if cache driver is redis
        if (config('cache.default') !== 'redis') {
            self::$redisAvailable = false;

            return false;
        }

        $connection = config('cache.stores.redis.connection', 'default');

        try {
            // Test actual connection using the cache Redis connection
            Redis::connection($connection)->ping();
            self::$redisAvailable = true;

            return true;
        } catch (\Exception $e) {
            self::$redisAvailable = false;

            return false;
        }
    }

    /**
     * Get the Redis connection used for cache operations.
     */
    private function redisConnection()
    {
        return Redis::connection(config('cache.stores.redis.connection', 'default'));
    }

    /**
     * Check if cache tagging is supported by the current driver.
     */
    public function isTaggingSupported(): bool
    {
        if (self::$taggingSupported !== null) {
            return self::$taggingSupported;
        }

        $driver = config('cache.default');

        // Only redis, memcached, dynamodb, and array drivers support tagging
        self::$taggingSupported = in_array($driver, ['redis', 'memcached', 'dynamodb', 'array']);

        return self::$taggingSupported;
    }

    /**
     * Remember a value with optional tagging support.
     * Automatically falls back to untagged cache if tagging isn't supported.
     *
     * @param  string|array  $tags  Tags to apply (ignored if tagging not supported)
     * @param  \DateTimeInterface|\DateInterval|int  $ttl  Time to live
     * @param  \Closure  $callback  Callback to get the value
     */
    public function rememberWithTags(string|array $tags, string $key, $ttl, \Closure $callback): mixed
    {
        try {
            if ($this->isTaggingSupported()) {
                return Cache::tags($tags)->remember($key, $ttl, $callback);
            } else {
                // Fallback: use simple cache without tags
                return Cache::remember($key, $ttl, $callback);
            }
        } catch (\Exception $e) {
            // If tagging fails unexpectedly, fall back to direct callback
            report($e);

            return $callback();
        }
    }

    /**
     * Flush cache by tags if supported, or flush by key pattern if not.
     *
     * @param  string|array  $tags  Tags to flush
     * @param  string|null  $keyPattern  Optional key pattern for fallback (e.g., 'search:suggestions:*')
     */
    public function flushTags(string|array $tags, ?string $keyPattern = null): bool
    {
        try {
            if ($this->isTaggingSupported()) {
                Cache::tags($tags)->flush();

                return true;
            } else {
                // Fallback: try to forget by key pattern
                if ($keyPattern && $this->isRedisAvailable()) {
                    // For Redis, we can use pattern matching
                    $keys = $this->redisConnection()->keys($keyPattern);
                    foreach ($keys as $key) {
                        Cache::forget($key);
                    }

                    return true;
                } elseif ($keyPattern) {
                    // For database cache, we can only forget the exact key without wildcard
                    // This is a limitation - log it
                    Log::debug("Cache flush by pattern not fully supported on database driver: {$keyPattern}");
                    Cache::forget(rtrim($keyPattern, '*'));

                    return true;
                }

                return false;
            }
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Get a value from cache with tag support if available.
     */
    public function getTagged(string|array $tags, string $key): mixed
    {
        try {
            if ($this->isTaggingSupported()) {
                return Cache::tags($tags)->get($key);
            } else {
                return Cache::get($key);
            }
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Check if a tagged cache key exists.
     */
    public function hasTagged(string|array $tags, string $key): bool
    {
        try {
            if ($this->isTaggingSupported()) {
                return Cache::tags($tags)->has($key);
            } else {
                return Cache::has($key);
            }
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Increment a value in a Redis sorted set while providing a graceful fallback when Redis is unavailable.
     *
     * @return bool Success status
     */
    public function incrementSortedSet(string $key, float $increment, string $member): bool
    {
        if (! $this->isRedisAvailable()) {
            $this->incrementFallbackSortedSet($key, $member, $increment);

            return true;
        }

        try {
            $this->redisConnection()->zincrby($key, $increment, $member);

            return true;
        } catch (\Exception $e) {
            // Fail silently - tracking is not critical but persist via fallback
            $this->incrementFallbackSortedSet($key, $member, $increment);

            return false;
        }
    }

    /**
     * Set expiration on a Redis key.
     *
     * @return bool Success status
     */
    public function expireSortedSet(string $key, int $seconds): bool
    {
        if (! $this->isRedisAvailable()) {
            return $this->expireFallbackSortedSet($key, $seconds);
        }

        try {
            $this->redisConnection()->expire($key, $seconds);

            return true;
        } catch (\Exception $e) {
            return $this->expireFallbackSortedSet($key, $seconds);
        }
    }

    /**
     * Get top items from a Redis sorted set.
     *
     * @return array Array of items with scores
     */
    public function getSortedSetTop(string $key, int $start, int $stop, bool $withScores = true): array
    {
        if (! $this->isRedisAvailable()) {
            return $this->getFallbackSortedSetTop($key, $start, $stop, $withScores);
        }

        try {
            $options = $withScores ? ['withscores' => true] : [];

            return $this->redisConnection()->zrevrange($key, $start, $stop, $options);
        } catch (\Exception $e) {
            return $this->getFallbackSortedSetTop($key, $start, $stop, $withScores);
        }
    }

    /**
     * Delete a Redis key.
     *
     * @return bool Success status
     */
    public function deleteSortedSet(string $key): bool
    {
        if (! $this->isRedisAvailable()) {
            return $this->deleteFallbackSortedSet($key);
        }

        try {
            $this->redisConnection()->del($key);

            return true;
        } catch (\Exception $e) {
            return $this->deleteFallbackSortedSet($key);
        }
    }

    /**
     * Remove one or more members from a sorted set.
     */
    public function removeSortedSetMembers(string $key, array $members): bool
    {
        if ($members === []) {
            return true;
        }

        if (! $this->isRedisAvailable()) {
            return $this->removeFallbackSortedSetMembers($key, $members);
        }

        try {
            $this->redisConnection()->zrem($key, ...$members);

            return true;
        } catch (\Exception $e) {
            return $this->removeFallbackSortedSetMembers($key, $members);
        }
    }

    /**
     * Get score of a member in a sorted set.
     *
     * @return float|null Score or null if not found
     */
    public function getSortedSetScore(string $key, string $member): ?float
    {
        if (! $this->isRedisAvailable()) {
            return $this->getFallbackSortedSetScore($key, $member);
        }

        try {
            $score = $this->redisConnection()->zscore($key, $member);

            return $score !== false ? (float) $score : null;
        } catch (\Exception $e) {
            return $this->getFallbackSortedSetScore($key, $member);
        }
    }

    /**
     * Get cardinality (count) of a sorted set.
     *
     * @return int Count of members
     */
    public function getSortedSetCount(string $key): int
    {
        if (! $this->isRedisAvailable()) {
            return $this->getFallbackSortedSetCount($key);
        }

        try {
            return (int) $this->redisConnection()->zcard($key);
        } catch (\Exception $e) {
            return $this->getFallbackSortedSetCount($key);
        }
    }

    private function fallbackSortedSetKey(string $key): string
    {
        return "zset:fallback:{$key}";
    }

    private function fallbackMetaKey(string $key): string
    {
        return "zset:fallback:meta:{$key}";
    }

    private function incrementFallbackSortedSet(string $key, string $member, float $increment): void
    {
        $cacheKey = $this->fallbackSortedSetKey($key);
        $data = Cache::get($cacheKey, []);
        $data[$member] = ($data[$member] ?? 0) + $increment;

        $expiresAt = $this->getFallbackExpiration($key);

        if ($expiresAt) {
            Cache::put($cacheKey, $data, $expiresAt);
        } else {
            Cache::forever($cacheKey, $data);
        }
    }

    private function expireFallbackSortedSet(string $key, int $seconds): bool
    {
        $expiresAt = Carbon::now()->addSeconds($seconds);

        Cache::put($this->fallbackMetaKey($key), $expiresAt->timestamp, $expiresAt);

        $cacheKey = $this->fallbackSortedSetKey($key);
        $data = Cache::get($cacheKey);

        if ($data === null) {
            return true;
        }

        Cache::put($cacheKey, $data, $expiresAt);

        return true;
    }

    private function getFallbackExpiration(string $key): ?Carbon
    {
        $timestamp = Cache::get($this->fallbackMetaKey($key));

        if (! $timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function getFallbackSortedSetTop(string $key, int $start, int $stop, bool $withScores): array
    {
        $data = Cache::get($this->fallbackSortedSetKey($key), []);

        if (! is_array($data) || empty($data)) {
            return [];
        }

        arsort($data);

        if ($stop < $start) {
            return [];
        }

        $length = max(0, ($stop - $start) + 1);

        if ($length === 0) {
            return [];
        }

        $slice = array_slice($data, $start, $length, true);

        if ($withScores) {
            return $slice;
        }

        return array_keys($slice);
    }

    private function deleteFallbackSortedSet(string $key): bool
    {
        $cacheKey = $this->fallbackSortedSetKey($key);
        $metaKey = $this->fallbackMetaKey($key);

        $forgotData = Cache::forget($cacheKey);
        $forgotMeta = Cache::forget($metaKey);

        return $forgotData || $forgotMeta;
    }

    private function removeFallbackSortedSetMembers(string $key, array $members): bool
    {
        $cacheKey = $this->fallbackSortedSetKey($key);
        $data = Cache::get($cacheKey, []);

        if (! is_array($data) || $data === []) {
            return true;
        }

        $modified = false;

        foreach ($members as $member) {
            if (array_key_exists($member, $data)) {
                unset($data[$member]);
                $modified = true;
            }
        }

        if (! $modified) {
            return true;
        }

        if ($data === []) {
            Cache::forget($cacheKey);

            return true;
        }

        $expiresAt = $this->getFallbackExpiration($key);

        if ($expiresAt) {
            Cache::put($cacheKey, $data, $expiresAt);
        } else {
            Cache::forever($cacheKey, $data);
        }

        return true;
    }

    private function getFallbackSortedSetScore(string $key, string $member): ?float
    {
        $data = Cache::get($this->fallbackSortedSetKey($key), []);

        if (! is_array($data) || ! array_key_exists($member, $data)) {
            return null;
        }

        return (float) $data[$member];
    }

    private function getFallbackSortedSetCount(string $key): int
    {
        $data = Cache::get($this->fallbackSortedSetKey($key), []);

        if (! is_array($data)) {
            return 0;
        }

        return count($data);
    }

    /**
     * Reset the static cache state (useful for testing).
     */
    public static function resetCache(): void
    {
        self::$redisAvailable = null;
        self::$taggingSupported = null;
    }
}
