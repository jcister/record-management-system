<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Services\SearchExperienceService;
use App\Services\SearchSuggestionService;
use Illuminate\Http\Request;

class SearchDebugController extends Controller
{
    public function __construct(
        private CacheService $cache
    ) {}

    public function __invoke(Request $request, SearchSuggestionService $service, SearchExperienceService $searchExperience)
    {
        $clearCache = $request->boolean('clear', false);

        if ($clearCache) {
            $this->cache->flushTags(['search:suggestions'], 'search:suggestions:*');
            $searchExperience->clearPanelCache();
            $message = '✓ All search caches cleared!';
        } else {
            $message = null;
        }

        // Get current data
        $searchTerm = $request->get('search', 'doc');
        $suggestions = $service->getSuggestions($searchTerm, 10);

        // Get cache info
        $cacheKey = 'search:suggestions:'.md5(strtolower(trim($searchTerm)));
        $isCached = $this->cache->hasTagged(['search:suggestions'], $cacheKey);

        // Get Redis stats if Redis is available
        $redisConnected = $this->cache->isRedisAvailable();
        $panelSnapshot = $searchExperience->getPanelDiagnostics();

        $redisConnectionName = config('cache.stores.redis.connection', 'default');
        $redisConnectionConfig = config("database.redis.{$redisConnectionName}", []);
        $redisHost = data_get($redisConnectionConfig, 'host', data_get($redisConnectionConfig, 'url'));
        $redisPort = data_get($redisConnectionConfig, 'port');
        $redisDatabase = data_get($redisConnectionConfig, 'database');

        return response()->view('debug-search', [
            'message' => $message,
            'searchTerm' => $searchTerm,
            'suggestions' => $suggestions,
            'isCached' => $isCached,
            'cacheKey' => $cacheKey,
            'redisConnected' => $redisConnected,
            'frequentlyAccessed' => $panelSnapshot['items'],
            'panelMeta' => [
                'cache_key' => $panelSnapshot['cache_key'],
                'ttl' => $panelSnapshot['ttl'],
                'scores' => $panelSnapshot['scores'],
                'total_members' => $panelSnapshot['total_members'],
                'removed_members' => $panelSnapshot['removed_members'],
                'last_recorded_at' => $panelSnapshot['last_recorded_at'],
                'limit' => $panelSnapshot['limit'],
            ],
            'panelCached' => $panelSnapshot['has_data'],
            'config' => [
                'cache_driver' => config('cache.default'),
                'redis_connection' => $redisConnectionName,
                'redis_host' => $redisHost,
                'redis_port' => $redisPort,
                'redis_database' => $redisDatabase,
                'search_ttl' => config('cache.search_suggestions_ttl'),
                'trending_ttl' => config('cache.trending_ttl'),
                'trending_cache_ttl' => config('cache.trending_cache_ttl'),
            ],
            'trendingKey' => $panelSnapshot['cache_key'],
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
