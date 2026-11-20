<?php

use App\Support\RetentionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('files')
            ->where('retention_status', RetentionStatus::ARCHIVED)
            ->update(['retention_status' => RetentionStatus::DISPOSED]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
