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
        Schema::table('file_versions', function (Blueprint $table) {
            $table->string('thumbnail_disk')->nullable()->after('assembly_status');
            $table->string('thumbnail_path_webp')->nullable()->after('thumbnail_disk');
            $table->string('thumbnail_path_jpeg')->nullable()->after('thumbnail_path_webp');
            $table->timestamp('thumbnail_generated_at')->nullable()->after('thumbnail_path_jpeg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropColumn([
                'thumbnail_disk',
                'thumbnail_path_webp',
                'thumbnail_path_jpeg',
                'thumbnail_generated_at',
            ]);
        });
    }
};
