<?php

use App\Models\File;
use App\Models\Requests;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REQUEST_ACTION_MAP = [
        'created' => 'request_created',
        'status_changed' => 'request_status_changed',
        'file_attached' => 'request_file_attached',
        'file_detached' => 'request_file_detached',
        'scan_uploaded' => 'request_scan_uploaded',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $this->migrateRequestLogs();
        $this->migrateFileDownloadLogs();
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->whereIn('action', array_values(self::REQUEST_ACTION_MAP))
            ->orWhereIn('action', ['file_downloaded'])
            ->delete();
    }

    private function migrateRequestLogs(): void
    {
        if (! Schema::hasTable('request_logs')) {
            return;
        }

        DB::table('request_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $userIds = collect($rows)->pluck('user_id')->filter()->unique()->all();
                $userNames = $userIds === []
                    ? []
                    : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

                $requestIds = collect($rows)->pluck('request_id')->filter()->unique()->all();
                $requestTitles = $requestIds === []
                    ? []
                    : DB::table('requests')->whereIn('id', $requestIds)->pluck('request_title', 'id');

                $payload = collect($rows)->map(function ($row) use ($userNames, $requestTitles) {
                    $action = self::REQUEST_ACTION_MAP[$row->action] ?? $row->action;
                    $userName = $userNames[$row->user_id] ?? \App\Services\AuditService::SYSTEM_ACTOR_NAME;
                    $requestTitle = $requestTitles[$row->request_id] ?? 'Request';

                    $description = match ($action) {
                        'request_status_changed' => $userName.' changed request "'.$requestTitle.'" status.',
                        'request_file_attached' => $userName.' attached a file to "'.$requestTitle.'".',
                        'request_file_detached' => $userName.' removed a file from "'.$requestTitle.'".',
                        'request_scan_uploaded' => $userName.' uploaded a scan for "'.$requestTitle.'".',
                        default => $userName.' updated "'.$requestTitle.'".',
                    };

                    return [
                        'user_id' => $row->user_id,
                        'user_name' => $userName,
                        'action' => $action,
                        'description' => $description,
                        'auditable_type' => Requests::class,
                        'auditable_id' => $row->request_id,
                        'auditable_name' => $requestTitle,
                        'properties' => $row->details,
                        'ip_address' => null,
                        'user_agent' => null,
                        'created_at' => $row->created_at,
                    ];
                })->all();

                if ($payload !== []) {
                    DB::table('audit_logs')->insert($payload);
                }
            });
    }

    private function migrateFileDownloadLogs(): void
    {
        if (! Schema::hasTable('file_download_logs')) {
            return;
        }

        DB::table('file_download_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $userIds = collect($rows)->pluck('downloaded_by')->filter()->unique()->all();
                $userNames = $userIds === []
                    ? []
                    : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

                $fileIds = collect($rows)->pluck('file_id')->filter()->unique()->all();
                $files = $fileIds === []
                    ? []
                    : DB::table('files')->whereIn('id', $fileIds)->get(['id', 'file_name'])->keyBy('id');

                $payload = collect($rows)->map(function ($row) use ($userNames, $files) {
                    $file = $files[$row->file_id] ?? null;
                    $fileName = $file?->file_name ?: 'File';
                    $userName = $userNames[$row->downloaded_by] ?? \App\Services\AuditService::SYSTEM_ACTOR_NAME;

                    return [
                        'user_id' => $row->downloaded_by,
                        'user_name' => $userName,
                        'action' => 'file_downloaded',
                        'description' => $userName.' downloaded "'.$fileName.'".',
                        'auditable_type' => File::class,
                        'auditable_id' => $row->file_id,
                        'auditable_name' => $fileName,
                        'properties' => $row->remarks ? ['remarks' => $row->remarks] : null,
                        'ip_address' => $row->ip_address,
                        'user_agent' => $row->user_agent,
                        'created_at' => $row->downloaded_at ?? $row->created_at,
                    ];
                })->all();

                if ($payload !== []) {
                    DB::table('audit_logs')->insert($payload);
                }
            });
    }
};
