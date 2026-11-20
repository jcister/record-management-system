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
        Schema::table('files', function (Blueprint $table) {
            $table->unsignedTinyInteger('ocr_retry_count')->default(0)->after('ocr_error');
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->unsignedTinyInteger('ocr_retry_count')->default(0)->after('ocr_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('ocr_retry_count');
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropColumn('ocr_retry_count');
        });
    }
};
