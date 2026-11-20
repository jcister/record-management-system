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
        Schema::table('disposal_batch_files', function (Blueprint $table) {
            $table->uuid('unarchived_by')->nullable()->after('completed_at');
            $table->timestamp('unarchived_at')->nullable()->after('unarchived_by');

            $table->foreign('unarchived_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batch_files', function (Blueprint $table) {
            $table->dropForeign(['unarchived_by']);
            $table->dropColumn(['unarchived_by', 'unarchived_at']);
        });
    }
};
