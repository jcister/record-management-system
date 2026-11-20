<?php

use App\Support\PersonNameFormatter;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('prefix');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('extension')->nullable()->after('last_name');
        });

        foreach (DB::table('users')->select(['id', 'name'])->cursor() as $user) {
            $parts = PersonNameFormatter::split(null, null, null, null, (string) $user->name);

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'first_name' => $parts['first'] !== '' ? $parts['first'] : null,
                    'middle_name' => $parts['middle'] !== '' ? $parts['middle'] : null,
                    'last_name' => $parts['last'] !== '' ? $parts['last'] : null,
                    'extension' => $parts['extension'] !== '' ? $parts['extension'] : null,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'first_name', 'middle_name', 'last_name', 'extension']);
        });
    }
};
