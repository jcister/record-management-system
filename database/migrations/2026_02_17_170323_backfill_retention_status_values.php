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
        DB::table('files')->update([
            'retention_status' => DB::raw(
                'CASE WHEN retention_expires_at IS NOT NULL AND retention_expires_at <= CURRENT_DATE '
                ."THEN '".RetentionStatus::READY_FOR_DISPOSAL."' "
                ."ELSE '".RetentionStatus::ACTIVE."' END"
            ),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('files')->update([
            'retention_status' => RetentionStatus::ACTIVE,
        ]);
    }
};
