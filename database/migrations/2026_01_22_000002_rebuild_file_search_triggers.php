<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->usingSqlite()) {
            return;
        }

        $this->rebuildTriggers();
    }

    public function down(): void
    {
        if (! $this->usingSqlite()) {
            return;
        }

        $this->rebuildTriggers();
    }

    private function rebuildTriggers(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_ai');
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_au');
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_ad');

        DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_ai
AFTER INSERT ON files
BEGIN
    INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_au
AFTER UPDATE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
    INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_ad
AFTER DELETE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
END;
SQL);
    }

    private function usingSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
