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
            $table->boolean('requires_scan')->default(false)->after('required_format');
            $table->string('scan_review_status', 32)->nullable()->after('status');
            $table->text('scan_review_notes')->nullable()->after('scan_review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['requires_scan', 'scan_review_status', 'scan_review_notes']);
        });
    }
};
