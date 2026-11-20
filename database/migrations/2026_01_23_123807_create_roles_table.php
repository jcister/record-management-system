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
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); // Primary identifier for each role
            $table->string('name')->unique(); // Human-readable label users see
            $table->string('slug')->unique(); // Machine-friendly alias for lookups
            $table->string('description')->nullable(); // Optional summary of responsibilities
            $table->boolean('is_system')->default(false); // Flag to prevent deletion of core roles
            $table->json('metadata')->nullable(); // Arbitrary structured data (e.g., UI hints)
            $table->timestamps(); // Track creation and update times
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
