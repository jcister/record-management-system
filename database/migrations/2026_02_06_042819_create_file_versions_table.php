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
        Schema::create('file_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('file_id');
            $table->unsignedInteger('version_number');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->uuid('uploaded_by');
            $table->string('checksum', 128)->nullable();
            $table->string('version_label')->nullable();
            $table->text('change_summary')->nullable();
            $table->enum('ocr_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('ocr_text')->nullable();
            $table->string('ocr_language', 50)->nullable();
            $table->text('ocr_error')->nullable();
            $table->enum('assembly_status', ['processing', 'completed', 'failed'])->nullable();
            $table->timestamps();

            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['file_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
