<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->text('ocr_text')->nullable();
            $table->enum('ocr_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('ocr_language', 50)->default('eng+fil');
        });

        if ($this->usingSqlite()) {
            $this->createSqliteFtsIndex();
        } elseif ($this->usingMysql()) {
            $this->createMysqlFulltextIndex();
        }
    }

    public function down(): void
    {
        if ($this->usingSqlite()) {
            $this->dropSqliteFtsIndex();
        } elseif ($this->usingMysql()) {
            $this->dropMysqlFulltextIndex();
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['ocr_text', 'ocr_status', 'ocr_language']);
        });
    }

    private function usingSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function createSqliteFtsIndex(): void
    {
        DB::statement('CREATE VIRTUAL TABLE IF NOT EXISTS file_search_index USING fts5(file_id UNINDEXED, file_name, document_number, series, remarks, ocr_text)');

        DB::statement(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS file_search_index_ai
AFTER INSERT ON files
BEGIN
    INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS file_search_index_au
AFTER UPDATE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
    INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text)
    VALUES (new.id, new.file_name, new.document_number, new.series, new.remarks, new.ocr_text);
END;
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS file_search_index_ad
AFTER DELETE ON files
BEGIN
    DELETE FROM file_search_index WHERE file_id = old.id;
END;
SQL);

        DB::statement('INSERT INTO file_search_index (file_id, file_name, document_number, series, remarks, ocr_text) SELECT id, file_name, document_number, series, remarks, ocr_text FROM files;');
    }

    private function dropSqliteFtsIndex(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_ai');
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_au');
        DB::statement('DROP TRIGGER IF EXISTS file_search_index_ad');
        DB::statement('DROP TABLE IF EXISTS file_search_index');
    }

    private function createMysqlFulltextIndex(): void
    {
        DB::statement('ALTER TABLE files ADD FULLTEXT INDEX file_fulltext_index (file_name, document_number, series, remarks, ocr_text)');
    }

    private function dropMysqlFulltextIndex(): void
    {
        DB::statement('ALTER TABLE files DROP INDEX file_fulltext_index');
    }

    private function usingMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb']);
    }
};
