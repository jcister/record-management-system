<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite requires rebuilding the table, MySQL/PostgreSQL can use ALTER
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite: temporarily change to string, update values, then change to enum
            Schema::table('requests', function (Blueprint $table) {
                $table->string('priority_temp')->nullable();
            });

            // Copy and convert values
            DB::table('requests')
                ->where('priority', 'normal')
                ->update(['priority_temp' => 'standard']);

            DB::table('requests')
                ->where('priority', 'urgent')
                ->update(['priority_temp' => 'urgent']);

            // Drop old column and rename temp
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('priority');
            });

            Schema::table('requests', function (Blueprint $table) {
                $table->renameColumn('priority_temp', 'priority');
            });

            // Update default
            DB::statement("UPDATE requests SET priority = 'standard' WHERE priority IS NULL");
        } else {
            // For MySQL/PostgreSQL
            DB::table('requests')
                ->where('priority', 'normal')
                ->update(['priority' => 'standard']);

            Schema::table('requests', function (Blueprint $table) {
                $table->enum('priority', ['standard', 'urgent'])->default('standard')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('priority_temp')->nullable();
            });

            DB::table('requests')
                ->where('priority', 'standard')
                ->update(['priority_temp' => 'normal']);

            DB::table('requests')
                ->where('priority', 'urgent')
                ->update(['priority_temp' => 'urgent']);

            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('priority');
            });

            Schema::table('requests', function (Blueprint $table) {
                $table->renameColumn('priority_temp', 'priority');
            });

            DB::statement("UPDATE requests SET priority = 'normal' WHERE priority IS NULL");
        } else {
            DB::table('requests')
                ->where('priority', 'standard')
                ->update(['priority' => 'normal']);

            Schema::table('requests', function (Blueprint $table) {
                $table->enum('priority', ['normal', 'urgent'])->default('normal')->change();
            });
        }
    }
};
