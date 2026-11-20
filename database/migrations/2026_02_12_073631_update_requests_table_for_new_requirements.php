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
        Schema::table('requests', function (Blueprint $table) {
            // Remove old fields
            $table->dropColumn(['file_name', 'description_request']);

            // Add new fields
            $table->string('document_type')->nullable()->after('department_id');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('required_format');

            // Modify required_format values - we'll need to update existing data separately
            $table->string('required_format')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('file_name');
            $table->string('description_request');
            $table->dropColumn(['document_type', 'priority']);
        });
    }
};
