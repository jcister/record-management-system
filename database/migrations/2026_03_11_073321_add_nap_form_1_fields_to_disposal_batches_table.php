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
            $table->string('nap_form_1_path')->nullable()->after('nap_report_downloaded_at');
            $table->string('nap_form_1_stage')->nullable()->after('nap_form_1_path');
            $table->uuid('nap_form_1_generated_by')->nullable()->after('nap_form_1_stage');
            $table->timestamp('nap_form_1_generated_at')->nullable()->after('nap_form_1_generated_by');
            $table->timestamp('nap_form_1_downloaded_at')->nullable()->after('nap_form_1_generated_at');

            $table->foreign('nap_form_1_generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['nap_form_1_generated_by']);
            $table->dropColumn([
                'nap_form_1_path',
                'nap_form_1_stage',
                'nap_form_1_generated_by',
                'nap_form_1_generated_at',
                'nap_form_1_downloaded_at',
            ]);
        });
    }
};
