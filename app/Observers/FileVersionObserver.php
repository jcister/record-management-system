<?php

namespace App\Observers;

use App\Models\FileVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class FileVersionObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(FileVersion $fileVersion): void
    {
        $actor = Auth::user() ?? User::find($fileVersion->uploaded_by);

        $this->audit->log(
            'file_version_created',
            ($actor?->name ?? AuditService::SYSTEM_ACTOR_NAME).' created version '.$fileVersion->version_number.' for file "'.$fileVersion->file_name.'".',
            $fileVersion,
            [
                'file_id' => $fileVersion->file_id,
                'version_number' => $fileVersion->version_number,
                'file_size' => $fileVersion->file_size,
            ],
            $actor
        );
    }

    public function updated(FileVersion $fileVersion): void
    {
        $log = $this->audit->logModelUpdate($fileVersion, 'file_version_updated', $fileVersion->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated version '.$fileVersion->version_number.' of "'.$fileVersion->file_name.'".',
        ]);
    }

    public function deleted(FileVersion $fileVersion): void
    {
        $this->audit->log(
            'file_version_deleted',
            $this->actorPrefix().' deleted version '.$fileVersion->version_number.' of "'.$fileVersion->file_name.'".',
            $fileVersion,
            [
                'file_id' => $fileVersion->file_id,
                'version_number' => $fileVersion->version_number,
            ]
        );
    }

    private function actorPrefix(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return AuditService::SYSTEM_ACTOR_NAME;
        }

        return $user->name;
    }
}
