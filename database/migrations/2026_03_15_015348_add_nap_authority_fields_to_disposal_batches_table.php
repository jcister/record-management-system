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
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->string('actual_mode_of_disposal')->nullable()->after('mode_of_disposal');
            $table->timestamp('nap_authority_issued_at')->nullable()->after('actual_mode_of_disposal');
            $table->uuid('nap_authority_confirmed_by')->nullable()->after('nap_authority_issued_at');

            $table->foreign('nap_authority_confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposal_batches', function (Blueprint $table) {
            $table->dropForeign(['nap_authority_confirmed_by']);

            $table->dropColumn([
                'actual_mode_of_disposal',
                'nap_authority_issued_at',
                'nap_authority_confirmed_by',
            ]);
        });
    }
};
