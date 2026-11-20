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
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete(); // Permission being granted to a role
            $table->foreignId('role_id')->constrained()->cascadeOnDelete(); // Role receiving access to the permission
            $table->timestamps(); // Track when the mapping was created or updated

            $table->unique(['permission_id', 'role_id']); // Ensure each permission/role pair is only stored once
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
