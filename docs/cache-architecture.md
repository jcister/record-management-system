# Cache Architecture - Dynamic Driver Support

## Overview

This application uses a **unified cache abstraction layer** that seamlessly supports both **Redis** and **database** cache drivers during development and production. This architecture allows you to switch between cache drivers without code changes and ensures features gracefully degrade when advanced features aren't available.

## Problem Solved

### Before (Issues):

1. **Cache Tags Not Supported by Database Driver**: Direct use of `Cache::tags()` would fail when using database cache
2. **Direct Redis Facade Usage**: Code directly using `Redis::zscore()`, `Redis::zincrby()`, etc. would break when Redis wasn't available
3. **Development Friction**: Switching between Redis and database required code changes
4. **Inconsistent Behavior**: Features would break unpredictably when cache drivers changed

### After (Solution):

1. **Unified API**: Single `CacheService` class handles all cache operations
2. **Automatic Detection**: Detects available cache driver and features
3. **Graceful Degradation**: Features fall back appropriately when not supported
4. **Zero Configuration**: Works seamlessly regardless of `CACHE_STORE` setting

## Architecture

### CacheService (`app/Services/CacheService.php`)

The central cache abstraction layer providing:

#### Feature Detection

- `isRedisAvailable()`: Checks if Redis is configured and accessible
- `isTaggingSupported()`: Checks if current cache driver supports tags

#### Tagged Cache Operations

- `rememberWithTags()`: Cache with tags (falls back to untagged)
- `getTagged()`: Get tagged cache value
- `hasTagged()`: Check if tagged cache exists
- `flushTags()`: Flush by tags or key pattern

#### Sorted Set Operations (Redis-specific)

- `incrementSortedSet()`: Increment score in sorted set
- `expireSortedSet()`: Set TTL on sorted set
- `getSortedSetTop()`: Get top items from sorted set
- `getSortedSetScore()`: Get score of a member
- `getSortedSetCount()`: Get count of members
- `deleteSortedSet()`: Delete a sorted set

### Integration Points

#### 1. SearchSuggestionService

Uses `CacheService` for:

- **Search suggestions caching** with automatic tag support
- **Trending searches tracking** via sorted sets (feature-flagged)

#### 2. SearchExperienceService

- Tracks **frequently accessed files** in the last hour using the `trending:files` Redis sorted set (prefixed by `cache.prefix`)
- Exposes helper methods to clear or inspect the key for debugging panels

#### 3. Observers (FileObserver, FolderObserver)

Uses `CacheService` to invalidate search cache when data changes

#### 3. SearchDebugController

Uses `CacheService` for cache inspection and debugging

## Cache Driver Support Matrix

| Feature           | Redis | Database | Array | Memcached | DynamoDB |
| ----------------- | ----- | -------- | ----- | --------- | -------- |
| Basic Cache       | ✅    | ✅       | ✅    | ✅        | ✅       |
| Cache Tags        | ✅    | ❌       | ✅    | ✅        | ✅       |
| Sorted Sets       | ✅    | ❌       | ❌    | ❌        | ❌       |
| Trending Features | ✅    | ❌       | ❌    | ❌        | ❌       |

## Configuration

### Environment Variables

```env
# Switch between drivers seamlessly
CACHE_STORE=redis      # For production (full features)
CACHE_STORE=database   # For development (limited features)

# Cache TTL Configuration
CACHE_SEARCH_TTL=5          # Search suggestions cache (minutes)
CACHE_TRENDING_TTL=60       # Trending sidebar cache (minutes)
CACHE_TRENDING_DATA_TTL=3600  # Trending data retention (seconds, 1 hour)
```

### Cache Configuration (`config/cache.php`)

```php
'default' => env('CACHE_STORE', 'database'),

'search_suggestions_ttl' => env('CACHE_SEARCH_TTL', 60),
'trending_cache_ttl' => env('CACHE_TRENDING_TTL', 60),
'trending_ttl' => env('CACHE_TRENDING_DATA_TTL', 3600),
```

## Behavior by Driver

### With Redis (`CACHE_STORE=redis`)

✅ Full functionality:

- Search suggestions cached with tags
- Trending searches tracked and displayed
- Trending files tracked and displayed
- Cache invalidation by tags
- Optimal performance

### With Database (`CACHE_STORE=database`)

⚠️ Limited functionality:

- Search suggestions cached (without tags)
- Trending features gracefully disabled
- Cache invalidation less efficient
- Slightly slower performance

## Development Workflow

### Switching Drivers During Development

```bash
# Switch to Redis (full features)
# Edit .env: CACHE_STORE=redis
php artisan cache:clear

# Switch to Database (no Redis required)
# Edit .env: CACHE_STORE=database
php artisan cache:clear
```

**No code changes required!** The `CacheService` automatically adapts.

### Testing Different Drivers

```php
// In tests, easily switch drivers
config(['cache.default' => 'database']);
CacheService::resetCache();

config(['cache.default' => 'redis']);
CacheService::resetCache();
```

## Best Practices

### 1. Always Use CacheService for Cache Operations

❌ **Don't:**

```php
Cache::tags(['search:suggestions'])->remember(/* ... */);
Redis::zincrby('trending:searches', 1, $term);
```

✅ **Do:**

```php
$this->cache->rememberWithTags(['search:suggestions'], $key, $ttl, $callback);
$this->cache->incrementSortedSet('trending:searches', 1, $term);
```

### 2. Handle Features Gracefully

When Redis features aren't available, features should degrade gracefully:

```php
// Trending features won't break - just return empty arrays
$trending = $service->getTrendingSearches(); // [] if Redis unavailable
```

### 3. Clear Cache After Driver Changes

```bash
php artisan cache:clear
```

### 4. Monitor Cache Driver in Production

Ensure Redis is available in production for optimal features:

```php
// In SearchDebugController
$redisConnected = $this->cache->isRedisAvailable();
```

### Inspecting Trending Files

- Determine the key via `app(SearchExperienceService::class)->trendingFilesCacheKey()` (defaults to `<cache.prefix>trending:files`).
- Run `redis-cli ZREVRANGE <key> 0 5 WITHSCORES` to see the hottest files and their view counts for the past hour.
- If no one opens files for roughly one hour (`CACHE_TRENDING_DATA_TTL`), the key naturally expires and the panel empties.

## Testing

Run the cache service unit tests:

```bash
php artisan test --filter=CacheServiceTest
```

Test search functionality with both drivers:

```bash
# Test with database
php artisan test tests/Feature/SearchSuggestionCachingTest.php

# Test with Redis (if available)
CACHE_STORE=redis php artisan test tests/Feature/SearchSuggestionCachingTest.php
```

## Performance Considerations

### Redis (Production)

- **Search suggestions**: Fast (in-memory)
- **Trending features**: Very fast (sorted sets)
- **Tag invalidation**: Instant

### Database (Development)

- **Search suggestions**: Moderate (database queries)
- **Trending features**: Disabled (gracefully)
- **Cache invalidation**: Less efficient (no wildcard support)

## Troubleshooting

### Issue: Cache not working after driver switch

**Solution:** Clear cache after changing `CACHE_STORE`

```bash
php artisan cache:clear
```

### Issue: Trending features not showing

**Check:**

1. Verify `CACHE_STORE=redis` in `.env`
2. Ensure Redis is running: `redis-cli ping`
3. Check Redis connection in `.env`:
    ```env
    REDIS_HOST=127.0.0.1
    REDIS_PORT=6379
    ```

### Issue: "Cache tags not supported" error

**Solution:** This should no longer occur with `CacheService`. If you see this, ensure you're using `CacheService` instead of direct `Cache::tags()` calls.

## Migration Guide

If you have existing code using direct cache/Redis calls:

### Before:

```php
// Direct cache tags
Cache::tags(['search:suggestions'])->remember($key, $ttl, $callback);

// Direct Redis
Redis::zincrby('trending:searches', 1, $term);
Redis::zrevrange('trending:searches', 0, 10, ['withscores' => true]);
```

### After:

```php
// Inject CacheService
public function __construct(private CacheService $cache) {}

// Use abstraction
$this->cache->rememberWithTags(['search:suggestions'], $key, $ttl, $callback);
$this->cache->incrementSortedSet('trending:searches', 1, $term);
$this->cache->getSortedSetTop('trending:searches', 0, 10, true);
```

## Future Enhancements

Potential improvements:

1. **Fallback Trending with Database**: Implement basic trending using database queries when Redis unavailable
2. **Cache Warming**: Pre-populate cache on deployment
3. **Multi-tier Caching**: Use Redis as primary, database as fallback
4. **Metrics**: Track cache hit rates and performance by driver

## Summary

The `CacheService` abstraction provides:

- ✅ **Seamless driver switching** during development
- ✅ **No code changes** when switching between Redis and database
- ✅ **Graceful degradation** of features
- ✅ **Clean, consistent API** for all cache operations
- ✅ **Production-ready** with optimal Redis performance
- ✅ **Developer-friendly** with database fallback
