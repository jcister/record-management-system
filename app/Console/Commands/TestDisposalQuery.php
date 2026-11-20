<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Support\RetentionStatus;
use Illuminate\Console\Command;

class TestDisposalQuery extends Command
{
    protected $signature = 'test:disposal-query';

    protected $description = 'Test the exact disposal management query';

    public function handle(): void
    {
        $this->info('Testing Disposal Management Query...');
        $this->newLine();

        // This is the EXACT query from DisposalManagementController
        $query = File::query()
            ->select('id', 'record_id', 'file_name', 'retention_status', 'retention_expires_at', 'folder_id', 'institution_id')
            ->where('retention_status', RetentionStatus::READY_FOR_DISPOSAL)
            ->whereNotNull('retention_expires_at')
            ->orderBy('retention_expires_at')
            ->orderBy('file_name');

        $count = $query->count();
        $this->info("Found {$count} records matching disposal criteria");
        $this->newLine();

        if ($count > 0) {
            $records = $query->get();

            $this->table(
                ['ID', 'Record ID', 'File Name', 'Expires At', 'Institution ID'],
                $records->map(fn ($file) => [
                    substr($file->id, 0, 8).'...',
                    $file->record_id,
                    substr($file->file_name, 0, 40),
                    $file->retention_expires_at,
                    $file->institution_id ?? 'NULL',
                ])
            );
            $this->newLine();

            // Now test with pagination
            $paginator = File::query()
                ->where('retention_status', RetentionStatus::READY_FOR_DISPOSAL)
                ->whereNotNull('retention_expires_at')
                ->orderBy('retention_expires_at')
                ->paginate(15);

            $this->info('Paginator stats:');
            $this->line("  Total: {$paginator->total()}");
            $this->line("  Per page: {$paginator->perPage()}");
            $this->line("  Current page: {$paginator->currentPage()}");
            $this->line("  Last page: {$paginator->lastPage()}");
            $this->line("  Items on current page: {$paginator->count()}");
        } else {
            $this->warn('No records found!');
            $this->newLine();
            $this->info('Checking for READY_FOR_DISPOSAL without expires_at...');

            $missingExpiry = File::query()
                ->where('retention_status', RetentionStatus::READY_FOR_DISPOSAL)
                ->whereNull('retention_expires_at')
                ->count();

            if ($missingExpiry > 0) {
                $this->warn("  Found {$missingExpiry} READY_FOR_DISPOSAL records without expiry date!");
                $this->warn("  These records won't show in the disposal cockpit.");
            }
        }
    }
}
