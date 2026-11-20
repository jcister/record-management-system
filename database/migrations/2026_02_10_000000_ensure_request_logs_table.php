<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_logs')) {
            return;
        }

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

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
