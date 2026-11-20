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
            $table->string('mode_of_disposal')->nullable()->after('scheduled_note');
            $table->timestamp('nap_report_downloaded_at')->nullable()->after('nap_report_generated_at');

            $table->string('coa_name')->nullable()->after('inspection_notes');
            $table->string('coa_position')->nullable()->after('coa_name');
            $table->string('coa_designation')->nullable()->after('coa_position');
            $table->json('approval_documents')->nullable()->after('coa_designation');

            $table->string('approval_letter_path')->nullable()->after('approval_notes');
            $table->timestamp('approval_letter_generated_at')->nullable()->after('approval_letter_path');
            $table->uuid('approval_letter_generated_by')->nullable()->after('approval_letter_generated_at');

            $table->foreign('approval_letter_generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['approval_letter_generated_by']);

            $table->dropColumn([
                'mode_of_disposal',
                'nap_report_downloaded_at',
                'coa_name',
                'coa_position',
                'coa_designation',
                'approval_documents',
                'approval_letter_path',
                'approval_letter_generated_at',
                'approval_letter_generated_by',
            ]);
        });
    }
};
