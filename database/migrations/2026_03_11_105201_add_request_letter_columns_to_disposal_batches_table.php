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
            $table->string('request_letter_path')->nullable()->after('nap_report_downloaded_at');
            $table->timestamp('request_letter_generated_at')->nullable()->after('request_letter_path');
            $table->uuid('request_letter_generated_by')->nullable()->after('request_letter_generated_at');
            $table->timestamp('request_letter_downloaded_at')->nullable()->after('request_letter_generated_by');

            $table->foreign('request_letter_generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['request_letter_generated_by']);

            $table->dropColumn([
                'request_letter_path',
                'request_letter_generated_at',
                'request_letter_generated_by',
                'request_letter_downloaded_at',
            ]);
        });
    }
};
