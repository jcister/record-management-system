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
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('record_id')->unique();
            $table->string('author');
            $table->string('department_id');
            $table->uuid('uploaded_by');
            $table->uuid('folder_id')->nullable();
            $table->string('file_name');
            $table->uuid('institution_id')->nullable();
            $table->string('physical_location');
            $table->string('document_number')->nullable();
            $table->string('series')->nullable();
            $table->text('remarks')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('version')->default('1.0');
            $table->enum('visibility', ['public', 'restricted', 'private'])->default('restricted');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('expiration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            // department_id FK is added after departments table is created (type mismatch: string vs bigint on MySQL)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
