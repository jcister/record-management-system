# Redis setup for developers

This guide shows simple ways to run Redis locally for development on Windows (recommended via WSL or Docker) and macOS. It also includes a Docker option that works on both platforms.

Prerequisites

- PHP, Composer, and this repo checked out.
- If using Docker, Docker Desktop installed.

Important Laravel notes

- If you don't want to run Redis locally, set `CACHE_STORE=file` in your `.env` and run:

```bash
php artisan config:clear
php artisan cache:clear
```

That requires no DB migration and is the simplest way to make the app run without Redis.

Recommended approaches

1. WSL (Windows) — recommended for Windows devs

- Install WSL and an Ubuntu distro (if not already):

```powershell
wsl --install -d ubuntu
```

- Open WSL shell and install Redis:

```bash
sudo apt update
sudo apt install redis-server
sudo systemctl enable --now redis-server
```

- Verify Redis is running:

```bash
redis-cli ping
# PONG
```

- Default Redis listens on `127.0.0.1:6379` inside WSL. To access from Windows-hosted PHP, bind Redis to the correct interface or use the WSL hostname (or run PHP in WSL). Using Docker is an easier cross-platform alternative.

# Redis setup for developers (no Docker)

This guide shows how to run Redis locally for development without Docker. It covers the recommended WSL approach for Windows and native Homebrew install for macOS. If you prefer not to run Redis at all, see the "No Redis" section.

## Prerequisites

- PHP, Composer, and this repository checked out.

## No Redis? quick option

- If you don't want to run Redis locally, set `CACHE_STORE=file` in your `.env` and run:

```bash
php artisan config:clear
php artisan cache:clear
```

This requires no DB migration and is the simplest way to make the app run without Redis. It stores cache files under `storage/framework/cache`.

## 1) Windows — WSL (recommended)

- Install WSL and Ubuntu (if not already):

```powershell
wsl --install -d ubuntu
```

- Open the Ubuntu shell and install Redis:

```bash
sudo apt update
sudo apt install redis-server
sudo systemctl enable --now redis-server
```

- Verify Redis is running inside WSL:

```bash
redis-cli ping
# PONG
```

- Notes about Windows <> WSL networking:
    - If your PHP runs on Windows (outside WSL) and Redis is inside WSL, either run PHP inside WSL or bind Redis to an interface reachable from Windows. Running PHP inside WSL (e.g. using `php artisan serve` in WSL) avoids cross-host issues.

## 2) macOS — Homebrew

- Install and start Redis via Homebrew:

```bash
brew install redis
brew services start redis
redis-cli ping
# PONG
```

## Verification & common commands

- List app cache keys (replace pattern to match your `CACHE_PREFIX`):

```bash
redis-cli -h 127.0.0.1 -p 6379 -n 0 --scan --pattern 'laravel-database-laravel-cache*'
```

- Inspect the trending ZSET:

```bash
redis-cli -h 127.0.0.1 -p 6379 -n 0 ZREVRANGE 'laravel-database-laravel-cache-trending:files' 0 50 WITHSCORES
```

## Laravel config recommendations

- In `.env` (recommended):

```text
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CACHE_DB=0
CACHE_PREFIX=laravel-cache-
```

- If you will not run Redis locally, use `CACHE_STORE=file` instead.

## Making Redis available to Windows-hosted PHP

- If you must run Redis in WSL but keep PHP on Windows, configure Redis to bind to a host address accessible from Windows (for example `0.0.0.0`) in `/etc/redis/redis.conf` and restart the service. This has security implications — prefer running PHP in WSL during development.

## Security (optional)

- To enable authentication, set `requirepass <password>` in `/etc/redis/redis.conf` and update `.env` `REDIS_PASSWORD` accordingly.

## Troubleshooting

- `redis-cli ping` returns nothing: ensure the Redis service is `active` and listening on the configured host/port.
- `Wrongtype` or missing data in redis-cli: ensure you're querying the correct logical DB (`-n N`). Laravel's `cache` connection may use a different DB index than the `default` connection.

## Notes for teammates

- Keep `.env.example` and `.env` aligned for any Redis-related variables you rely on (e.g., `REDIS_CACHE_DB`, `CACHE_PREFIX`).
