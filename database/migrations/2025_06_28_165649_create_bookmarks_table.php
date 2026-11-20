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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation (can bookmark files, folders, etc.)
            $table->uuid('bookmarkable_id');
            $table->string('bookmarkable_type');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'bookmarkable_id', 'bookmarkable_type']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
