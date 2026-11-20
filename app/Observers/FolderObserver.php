<?php

namespace App\Observers;

use App\Models\Folder;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class FolderObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Folder $folder): void
    {
        $this->audit->log(
            'folder_created',
            $this->actorPrefix().' created folder "'.$folder->name.'".',
            $folder
        );

        $this->invalidateSearchCache();
    }

    public function updated(Folder $folder): void
    {
        $log = $this->audit->logModelUpdate($folder, 'folder_updated', $folder->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated folder "'.$folder->name.'".',
        ]);

        $this->invalidateSearchCache();
    }

    public function deleted(Folder $folder): void
    {
        $this->audit->log(
            'folder_deleted',
            $this->actorPrefix().' moved folder "'.$folder->name.'" to trash.',
            $folder
        );

        $this->invalidateSearchCache();
    }

    public function restored(Folder $folder): void
    {
        $this->audit->log(
            'folder_restored',
            $this->actorPrefix().' restored folder "'.$folder->name.'".',
            $folder
        );
    }

    public function forceDeleted(Folder $folder): void
    {
        $this->audit->log(
            'folder_force_deleted',
            $this->actorPrefix().' permanently deleted folder "'.$folder->name.'".',
            $folder
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

    private function invalidateSearchCache(): void
    {
        $cacheService = app(\App\Services\CacheService::class);
        $cacheService->flushTags(['search:suggestions'], 'search:suggestions:*');
    }
}
