<?php

use App\Support\RetentionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'retention_status')) {
                $table->string('retention_status')->default(RetentionStatus::ACTIVE)->after('status');
            }
        });

        DB::table('files')
            ->whereNull('retention_status')
            ->update(['retention_status' => RetentionStatus::ACTIVE]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'retention_status')) {
                $table->dropColumn('retention_status');
            }
        });
    }
};
