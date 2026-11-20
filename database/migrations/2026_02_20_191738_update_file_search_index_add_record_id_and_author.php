<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->usingSqlite()) {
            // Drop existing triggers
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_ai');
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_au');
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_ad');

            // Drop existing FTS table
            DB::statement('DROP TABLE IF EXISTS file_search_index');

            // Recreate with record_id and author
            DB::statement('CREATE VIRTUAL TABLE file_search_index USING fts5(file_id UNINDEXED, record_id, author, file_name, document_number, series, remarks, ocr_text)');

            // Recreate triggers with record_id and author
            DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_ai
AFTER INSERT ON files
BEGIN
    INSERT INTO file_search_index (file_id, record_id, author, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.record_id, new.author, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

            DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_au
AFTER UPDATE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
    INSERT INTO file_search_index (file_id, record_id, author, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.record_id, new.author, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

            DB::statement(<<<'SQL'
CREATE TRIGGER file_search_index_ad
AFTER DELETE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
END;
SQL);

            // Rebuild the index with existing data
            DB::statement('INSERT INTO file_search_index (file_id, record_id, author, file_name, document_number, series, remarks, ocr_text) SELECT id, record_id, author, file_name, document_number, series, remarks, ocr_text FROM files');
        } elseif ($this->usingMysql()) {
            DB::statement('ALTER TABLE files DROP INDEX file_fulltext_index');
            DB::statement('ALTER TABLE files ADD FULLTEXT INDEX file_fulltext_index (file_name, document_number, series, remarks, ocr_text, record_id, author)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->usingSqlite()) {
            // Drop new triggers
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_ai');
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_au');
            DB::statement('DROP TRIGGER IF EXISTS file_search_index_ad');

            // Drop new FTS table
            DB::statement('DROP TABLE IF EXISTS file_search_index');

            // Recreate old version without record_id and author
            DB::statement('CREATE VIRTUAL TABLE file_search_index USING fts5(file_id UNINDEXED, file_name, document_number, series, remarks, ocr_text)');

            // Recreate old triggers
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

            // Rebuild the old index
            DB::statement('INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text) SELECT id, file_name, document_number, series, remarks, ocr_text FROM files');
        } elseif ($this->usingMysql()) {
            DB::statement('ALTER TABLE files DROP INDEX file_fulltext_index');
            DB::statement('ALTER TABLE files ADD FULLTEXT INDEX file_fulltext_index (file_name, document_number, series, remarks, ocr_text)');
        }
    }

    private function usingSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function usingMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb']);
    }
};
