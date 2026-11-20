<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('file_download_logs');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (! Schema::hasTable('request_logs')) {
            Schema::create('request_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->uuid('user_id');
                $table->string('action');
                $table->json('details')->nullable();
                $table->timestamps();

                $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('file_download_logs')) {
            Schema::create('file_download_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('file_id');
                $table->uuid('downloaded_by')->nullable();
                $table->timestamp('downloaded_at')->useCurrent();
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
                $table->foreign('downloaded_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }
};
