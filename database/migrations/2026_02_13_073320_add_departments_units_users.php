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
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number')->nullable();

            // use foreignId to match departments/units id() type and allow nulls
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullable();
            // unit_id added without FK here — units table doesn't exist yet at this migration timestamp.
            // The FK constraint is added in create_units_table migration (080512).
            $table->unsignedBigInteger('unit_id')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }

            if (Schema::hasColumn('users', 'unit_id')) {
                // FK is managed by create_units_table migration — only drop the column here
                $table->dropColumn('unit_id');
            }

            if (Schema::hasColumn('users', 'contact_number')) {
                $table->dropColumn('contact_number');
            }
        });
    }
};
