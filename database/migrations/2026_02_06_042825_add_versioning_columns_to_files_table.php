<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->uuid('current_version_id')->nullable()->after('file_path');
            $table->unsignedInteger('latest_version_number')->default(0)->after('current_version_id');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('file_versions')
                ->nullOnDelete();
        });

        $this->backfillExistingFiles();

        if (Schema::hasColumn('files', 'version')) {
            Schema::table('files', function (Blueprint $table): void {
                $table->dropColumn('version');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('files', 'version')) {
            Schema::table('files', function (Blueprint $table): void {
                $table->string('version')->default('1.0')->after('locked_at');
            });
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
            $table->dropColumn(['current_version_id', 'latest_version_number']);
        });
    }

    private function backfillExistingFiles(): void
    {
        DB::transaction(function (): void {
            DB::table('files')
                ->orderBy('created_at')
                ->chunk(100, function ($files): void {
                    foreach ($files as $file) {
                        if (empty($file->file_path)) {
                            continue;
                        }

                        $versionId = (string) Str::uuid();

                        DB::table('file_versions')->insert([
                            'id' => $versionId,
                            'file_id' => $file->id,
                            'version_number' => 1,
                            'file_name' => $file->file_name,
                            'file_path' => $file->file_path,
                            'mime_type' => $file->mime_type,
                            'file_size' => $file->file_size,
                            'uploaded_by' => $file->uploaded_by,
                            'checksum' => null,
                            'version_label' => $file->version ?? 'v1',
                            'change_summary' => 'Initial import',
                            'ocr_status' => $file->ocr_status ?? 'pending',
                            'ocr_text' => $file->ocr_text,
                            'ocr_language' => $file->ocr_language,
                            'ocr_error' => $file->ocr_error,
                            'assembly_status' => $file->assembly_status,
                            'created_at' => $file->created_at,
                            'updated_at' => $file->updated_at,
                        ]);

                        DB::table('files')
                            ->where('id', $file->id)
                            ->update([
                                'current_version_id' => $versionId,
                                'latest_version_number' => 1,
                            ]);
                    }
                }, 'id');
        });
    }
};
