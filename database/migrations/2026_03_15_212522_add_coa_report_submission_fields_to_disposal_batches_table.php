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
            $table->timestamp('report_submitted_to_coa_at')->nullable()->after('approved_at');
            $table->uuid('report_submitted_by')->nullable()->after('report_submitted_to_coa_at');

            $table->foreign('report_submitted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['report_submitted_by']);

            $table->dropColumn([
                'report_submitted_to_coa_at',
                'report_submitted_by',
            ]);
        });
    }
};
