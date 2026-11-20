<?php

/**
 * Redis migration script
 *
 * Usage:
 *  php scripts/redis-migrate.php --from-db=1 --to-db=0 --pattern='*trending*' [--host=127.0.0.1] [--port=6379] [--password=] [--dry-run] [--delete-source]
 */

require __DIR__.'/../vendor/autoload.php';

use Predis\Client as PredisClient;

$opts = getopt('', ['from-db:', 'to-db:', 'pattern::', 'host::', 'port::', 'password::', 'dry-run', 'delete-source', 'delete-only']);

function usage(): void
{
    echo "Usage: php scripts/redis-migrate.php --from-db=1 --to-db=0 --pattern='*' [--host=127.0.0.1] [--port=6379] [--password=] [--dry-run] [--delete-source]\n";
    exit(1);
}

if (! isset($opts['from-db']) || (! isset($opts['to-db']) && ! isset($opts['delete-only']))) {
    usage();
}

$fromDb = (int) $opts['from-db'];
$toDb = (int) $opts['to-db'];
$pattern = $opts['pattern'] ?? '*';
$host = $opts['host'] ?? '127.0.0.1';
$port = (int) ($opts['port'] ?? 6379);
$password = $opts['password'] ?? null;
$dryRun = isset($opts['dry-run']);
$deleteSource = isset($opts['delete-source']);
$deleteOnly = isset($opts['delete-only']);

echo "Redis migrate: from DB={$fromDb} -> DB={$toDb} pattern={$pattern}\n";
echo $dryRun ? "DRY RUN (no writes)\n" : "LIVE RUN\n";

// Check Predis availability
if (! class_exists(PredisClient::class)) {
    fwrite(STDERR, "Predis Client not found. Please ensure predis/predis is installed via Composer.\n");
    exit(2);
}

$common = ['scheme' => 'tcp', 'host' => $host, 'port' => $port];
$from = new PredisClient(array_merge($common, ['database' => $fromDb, 'password' => $password]));
$to = new PredisClient(array_merge($common, ['database' => $toDb, 'password' => $password]));

try {
    // Simple ping to validate
    $from->ping();
    $to->ping();
} catch (Exception $e) {
    fwrite(STDERR, 'Connection failed: '.$e->getMessage()."\n");
    exit(3);
}

$deleteMode = $deleteOnly ? ' (delete-only)' : '';
echo $deleteOnly ? "Mode: delete-only\n" : '';

// Manual SCAN loop to avoid Predis iterator option compatibility issues
$cursor = '0';
$count = 0;
do {
    $response = $from->executeRaw(['SCAN', $cursor, 'MATCH', $pattern, 'COUNT', '1000']);
    if (! is_array($response) || count($response) < 2) {
        break;
    }

    $cursor = (string) $response[0];
    $keys = $response[1];

    foreach ($keys as $key) {
        $count++;
        echo "Processing key: {$key}\n";

        if ($deleteOnly) {
            if ($dryRun) {
                echo " - DEL {$key} [DRY]\n";
            } else {
                try {
                    $from->del([$key]);
                    echo " - deleted\n";
                } catch (Exception $e) {
                    echo ' - failed to delete: '.$e->getMessage()."\n";
                }
            }

            continue;
        }

        $dump = $from->executeRaw(['DUMP', $key]);
        if ($dump === null) {
            echo " - skipped (no dump returned)\n";

            continue;
        }

        $pttl = $from->executeRaw(['PTTL', $key]);
        $ttl = 0;
        if (is_numeric($pttl) && (int) $pttl > 0) {
            $ttl = (int) $pttl;
        }

        if ($dryRun) {
            echo " - RESTORE {$key} (ttl_ms={$ttl}) [DRY]\n";
            if ($deleteSource) {
                echo " - DEL source {$key} [DRY]\n";
            }

            continue;
        }

        try {
            $to->executeRaw(['RESTORE', $key, (string) $ttl, $dump, 'REPLACE']);
            echo " - restored\n";
            if ($deleteSource) {
                $from->del([$key]);
                echo " - deleted source key\n";
            }
        } catch (Exception $e) {
            echo ' - failed to restore: '.$e->getMessage()."\n";
        }
    }

} while ($cursor !== '0');

echo "Done. Processed {$count} keys.\n";
