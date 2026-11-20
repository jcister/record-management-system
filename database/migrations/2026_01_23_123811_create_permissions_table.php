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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); // Primary identifier for each permission
            $table->string('name')->unique(); // Descriptive name used in the UI
            $table->string('slug')->unique(); // Stable key leveraged in code checks
            $table->string('description')->nullable(); // Optional explanation of the capability
            $table->boolean('is_system')->default(true); // Indicates if the permission is core
            $table->timestamps(); // Record when the permission was added or updated
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
