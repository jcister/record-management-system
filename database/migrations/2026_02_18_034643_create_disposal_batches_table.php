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
        Schema::create('disposal_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('reference')->unique();
            $table->string('status');
            $table->unsignedInteger('file_count');
            $table->uuid('scheduled_by');
            $table->timestamp('scheduled_at');
            $table->text('scheduled_note')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('nap_report_path')->nullable();
            $table->string('nap_report_stage')->nullable();
            $table->uuid('nap_report_generated_by')->nullable();
            $table->timestamp('nap_report_generated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('scheduled_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nap_report_generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disposal_batches');
    }
};
