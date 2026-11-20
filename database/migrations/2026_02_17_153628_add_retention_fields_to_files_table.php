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
        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'retention_duration')) {
                $table->string('retention_duration')->nullable()->after('status');
            }

            if (! Schema::hasColumn('files', 'retention_expires_at')) {
                $table->date('retention_expires_at')->nullable()->after('expiration_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'retention_duration')) {
                $table->dropColumn('retention_duration');
            }

            if (Schema::hasColumn('files', 'retention_expires_at')) {
                $table->dropColumn('retention_expires_at');
            }
        });
    }
};
