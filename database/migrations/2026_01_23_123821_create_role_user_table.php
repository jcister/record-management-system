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
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete(); // Link to the role being granted
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // Link to the account receiving the role
            $table->timestamp('assigned_at')->useCurrent(); // Capture when the assignment was made
            $table->timestamps(); // Track creation/update lifecycle for auditing

            $table->unique(['role_id', 'user_id']); // Prevent duplicate assignments for the same user/role combo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
