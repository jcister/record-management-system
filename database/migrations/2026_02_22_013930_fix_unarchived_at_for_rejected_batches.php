<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mark all files in rejected batches as unarchived
        // This fixes historical data where rejected batches didn't set unarchived_at

        // Get all rejected batches
        $rejectedBatches = \Illuminate\Support\Facades\DB::table('disposal_batches')
            ->where('status', 'rejected')
            ->select('id', 'rejected_at', 'rejected_by')
            ->get();

        foreach ($rejectedBatches as $batch) {
            \Illuminate\Support\Facades\DB::table('disposal_batch_files')
                ->where('batch_id', $batch->id)
                ->whereNull('unarchived_at')
                ->update([
                    'unarchived_at' => $batch->rejected_at ?? now(),
                    'unarchived_by' => $batch->rejected_by,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration
        // as we can't distinguish between files that were manually unarchived
        // and files that were unarchived due to batch rejection
    }
};
