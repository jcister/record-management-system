<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->timestamp('inspection_scheduled_at')->nullable()->after('inspection_notes');
            $table->foreignUuid('inspection_scheduled_by')->nullable()->after('inspection_scheduled_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['inspection_scheduled_by']);
            $table->dropColumn(['inspection_scheduled_at', 'inspection_scheduled_by']);
        });
    }
};
