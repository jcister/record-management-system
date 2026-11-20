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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('request_id')->unique();
            $table->string('department_id');
            $table->uuid('requested_by');
            $table->string('file_name');
            $table->string('file_number');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('request_title');
            $table->string('description_request');
            $table->text('purpose');
            $table->string('status');
            $table->string('notes')->nullable();
            $table->string('required_format')->nullable();

            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            // department_id FK omitted: column is string but departments.id is bigint (incompatible types on MySQL)

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
