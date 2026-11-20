<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Search Debug Panel</title>
    <style>
        :root {
            color-scheme: dark;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0b1120;
            color: #e2e8f0;
            margin: 0;
            padding: 32px 16px 48px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0 0 4px;
            font-size: 1.6rem;
        }

        .timestamp {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            margin-bottom: 16px;
        }

        .card {
            background: #111b2e;
            border: 1px solid #1f2a44;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(2, 6, 23, 0.45);
        }

        .card h2 {
            margin: 0 0 12px;
            font-size: 1.1rem;
            color: #bfdbfe;
        }

        .stats {
            display: grid;
            gap: 8px;
            margin: 0 0 12px;
        }

        .stats div {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
        }

        .stats dt {
            color: #94a3b8;
        }

        .stats dd {
            margin: 0;
            font-weight: 600;
            color: #f1f5f9;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        .badge-green {
            background: #0f766e;
            color: #ecfdf5;
        }

        .badge-blue {
            background: #1d4ed8;
            color: #e0f2fe;
        }

        .badge-red {
            background: #b91c1c;
            color: #fee2e2;
        }

        .list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .list-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: #0f172a;
            border: 1px solid #1e293b;
        }

        .muted {
            display: block;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }

        .btn {
            cursor: pointer;
            border: none;
            border-radius: 999px;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-secondary {
            background: transparent;
            color: #cbd5f5;
            border: 1px solid #334155;
        }

        code {
            background: #0f172a;
            border: 1px solid #1e293b;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .empty {
            color: #94a3b8;
            font-style: italic;
            margin: 6px 0 0;
        }

        ol {
            margin: 0;
            padding-left: 20px;
            color: #cbd5f5;
        }

        ol li {
            margin-bottom: 6px;
        }

        @media (max-width: 640px) {
            body {
                padding: 24px 12px 36px;
            }

            .card {
                border-radius: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1>🔍 Search System Debug Panel</h1>
            <div class="timestamp">Last updated: {{ $timestamp }}</div>
        </header>

        @if ($message)
            <p class="badge badge-blue" style="margin-bottom: 16px;">{{ $message }}</p>
        @endif

        <div class="actions">
            <button class="btn btn-danger" onclick="location.href='?clear=1'">Clear All Caches</button>
            <button class="btn btn-secondary" onclick="location.href='?'">Refresh Data</button>
            <button class="btn btn-secondary" onclick="location.reload(true)">Hard Refresh Page</button>
        </div>

        <section class="grid">
            @php
                $redisCliHost = $config['redis_host'] ?? '127.0.0.1';
                $redisCliPort = $config['redis_port'] ?? 6379;
                $redisCliDb = $config['redis_database'] ?? 0;
                // Redis client prefix (may be applied by the Redis client itself)
                $redisClientPrefix = $config['redis_prefix'] ?? config('database.redis.options.prefix', '');
                // The actual key stored in Redis includes the Redis client prefix plus the Laravel cache key
                $rawRedisKey = $redisClientPrefix . $panelMeta['cache_key'];
            @endphp

            <article class="card">
                <h2>Configuration</h2>
                <dl class="stats">
                    <div>
                        <dt>Cache Driver</dt>
                        <dd>{{ $config['cache_driver'] }}</dd>
                    </div>
                    <div>
                        <dt>Search Cache TTL</dt>
                        <dd>{{ $config['search_ttl'] }} min</dd>
                    </div>
                    <div>
                        <dt>Trending TTL</dt>
                        <dd>
                            @if (!empty($config['trending_ttl']))
                                {{ ceil($config['trending_ttl'] / 60) }} min
                            @else
                                n/a
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Trending Cache TTL</dt>
                        <dd>
                            @if (!empty($config['trending_cache_ttl']))
                                {{ $config['trending_cache_ttl'] }} min
                            @else
                                n/a
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Redis Connected</dt>
                        <dd><span
                                class="badge {{ $redisConnected ? 'badge-green' : 'badge-red' }}">{{ $redisConnected ? 'Yes' : 'No' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Redis Connection</dt>
                        <dd>{{ $config['redis_connection'] ?? 'n/a' }}</dd>
                    </div>
                    <div>
                        <dt>Redis Host</dt>
                        <dd>{{ $redisCliHost }}</dd>
                    </div>
                    <div>
                        <dt>Redis Port</dt>
                        <dd>{{ $redisCliPort }}</dd>
                    </div>
                    <div>
                        <dt>Redis DB</dt>
                        <dd>{{ $redisCliDb }}</dd>
                    </div>
                    <div>
                        <dt>Cache Prefix</dt>
                        <dd>{{ $config['cache_prefix'] ?? config('cache.prefix') }}</dd>
                    </div>
                    <div>
                        <dt>Search Cached</dt>
                        <dd><span
                                class="badge {{ $isCached ? 'badge-blue' : 'badge-green' }}">{{ $isCached ? 'Cached' : 'Fresh' }}</span>
                        </dd>
                    </div>
                </dl>
            </article>

            <article class="card">
                <h2>Frequently Accessed Cache</h2>
                <dl class="stats">
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $panelCached ? 'Has view data' : 'Empty (waiting for file views)' }}</dd>
                    </div>
                    <div>
                        <dt>Redis Key</dt>
                        <dd><code>{{ $panelMeta['cache_key'] }}</code></dd>
                    </div>
                    <div>
                        <dt>TTL</dt>
                        <dd>
                            @if (!empty($panelMeta['ttl']))
                                {{ ceil($panelMeta['ttl'] / 60) }} min
                            @else
                                n/a
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Members Tracked</dt>
                        <dd>{{ $panelMeta['total_members'] }}</dd>
                    </div>
                    <div>
                        <dt>Pruned Entries</dt>
                        <dd>{{ count($panelMeta['removed_members'] ?? []) }}</dd>
                    </div>
                    <div>
                        <dt>Last Activity</dt>
                        <dd>
                            @if (!empty($panelMeta['last_recorded_at']))
                                {{ \Illuminate\Support\Carbon::parse($panelMeta['last_recorded_at'])->timezone('Asia/Manila')->diffForHumans() }}
                            @else
                                n/a
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>CLI Check</dt>
                        <dd>
                            <code>
                                redis-cli -h {{ $redisCliHost }} -p {{ $redisCliPort }} -n {{ $redisCliDb }}
                                ZREVRANGE '{{ $rawRedisKey }}' 0 50 WITHSCORES
                            </code>
                            <div class="muted">(raw Redis key shown; Laravel's cache key is
                                <code>{{ $panelMeta['cache_key'] }}</code>)</div>
                        </dd>
                    </div>
                </dl>

                <h3 style="margin: 0 0 10px;">Current Items ({{ count($frequentlyAccessed) }})</h3>
                @if (count($frequentlyAccessed))
                    <ul class="list">
                        @foreach ($frequentlyAccessed as $item)
                            <li class="list-item">
                                <strong>{{ $item['name'] }}</strong>
                                <span class="muted">
                                    @if (!empty($item['folder']))
                                        in {{ $item['folder']['name'] }}
                                    @else
                                        standalone
                                    @endif
                                    @if (!empty($item['views']))
                                        • {{ $item['views'] }} views (last hour)
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="empty">
                        Open any record (Records → View a file or folder). Refresh this page to see it show up.
                    </p>
                @endif
            </article>
        </section>
        </section>

        <section class="card">
            <h2>How to warm & inspect Redis</h2>
            <ol>
                <li>From the UI, open any file (`Records → Folder → View`). Each view increments the sorted set.</li>
                <li>Run <code>redis-cli -h {{ $redisCliHost }} -p {{ $redisCliPort }} -n {{ $redisCliDb }}
                        ZREVRANGE '{{ $rawRedisKey }}' 0 50 WITHSCORES</code> to confirm Redis stored it (the raw
                    Redis key includes the client prefix).</li>
                <li>Or, in tinker:<br><code>$s =
                        app(App\Services\SearchExperienceService::class);</code><br><code>$s->trendingFilesCacheKey();</code><br><code>Redis::zrevrange($s->trendingFilesCacheKey(),
                        0, 5, ['withscores' => true]);</code></li>
                <li>Use the “Clear All Caches” button above to reset and repeat.</li>
                <li>
                    @if (!empty($panelMeta['ttl']))
                        If no one opens a file for ~{{ ceil($panelMeta['ttl'] / 60) }} minutes, the key expires
                        automatically.
                    @else
                        Set <code>CACHE_TRENDING_DATA_TTL</code> to control how long this cache stays alive.
                    @endif
                </li>
            </ol>
        </section>
    </div>
</body>

</html>
