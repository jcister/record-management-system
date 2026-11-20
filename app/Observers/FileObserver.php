<?php

namespace App\Observers;

use App\Models\File;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class FileObserver
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function created(File $file): void
    {
        $actor = Auth::user() ?? User::find($file->uploaded_by);

        $this->audit->log(
            'file_created',
            ($actor?->name ?? AuditService::SYSTEM_ACTOR_NAME).' uploaded file "'.$file->displayName().'".',
            $file,
            [],
            $actor
        );

        $this->invalidateSearchCache();
    }

    public function updated(File $file): void
    {
        $changes = $file->getChanges();
        $meaningful = array_diff_key($changes, array_flip(['updated_at']));

        if (empty($meaningful)) {
            return;
        }

        // Notify the record's author of any meaningful change, regardless of type.
        $this->notifications->notifyRecordUpdated($file, Auth::user());

        // Track specific status changes
        if (array_key_exists('status', $changes)) {
            $oldStatus = $file->getOriginal('status');
            $newStatus = $changes['status'];

            if ($newStatus === 'approved') {
                $this->audit->log(
                    'file_approved',
                    $this->actorPrefix().' approved file "'.$file->displayName().'".',
                    $file,
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'approved_by' => $file->approved_by,
                    ]
                );
            } elseif ($newStatus === 'rejected') {
                $this->audit->log(
                    'file_rejected',
                    $this->actorPrefix().' rejected file "'.$file->displayName().'".',
                    $file,
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'rejection_reason' => $file->rejection_reason,
                    ]
                );
            }
        }

        // Track visibility changes
        if (array_key_exists('visibility', $changes)) {
            $oldVisibility = $file->getOriginal('visibility');
            $newVisibility = $changes['visibility'];

            $this->audit->log(
                'file_visibility_changed',
                $this->actorPrefix().' changed file "'.$file->displayName().'" visibility from "'.$oldVisibility.'" to "'.$newVisibility.'".',
                $file,
                [
                    'old_visibility' => $oldVisibility,
                    'new_visibility' => $newVisibility,
                ]
            );
        }

        // Track lock/unlock events
        if (array_key_exists('locked_at', $changes)) {
            $wasLocked = $file->getOriginal('locked_at') !== null;
            $isLocked = $changes['locked_at'] !== null;

            if ($isLocked && ! $wasLocked) {
                $this->audit->log(
                    'file_locked',
                    $this->actorPrefix().' locked file "'.$file->displayName().'".',
                    $file
                );
            } elseif (! $isLocked && $wasLocked) {
                $this->audit->log(
                    'file_unlocked',
                    $this->actorPrefix().' unlocked file "'.$file->displayName().'".',
                    $file
                );
            }
        }

        // Strip keys already handled by specific events above so that
        // logModelUpdate does NOT produce a redundant file_updated row
        // alongside file_approved, file_rejected, file_visibility_changed, etc.
        $remainingChanges = array_diff_key($changes, array_flip(['status', 'visibility', 'locked_at']));

        // Track general updates with change details (only genuinely new fields)
        $log = $this->audit->logModelUpdate($file, 'file_updated', $remainingChanges);

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated file "'.$file->displayName().'".',
        ]);

        $this->invalidateSearchCache();
    }

    public function deleted(File $file): void
    {
        $this->audit->log(
            'file_deleted',
            $this->actorPrefix().' moved file "'.$file->displayName().'" to trash.',
            $file
        );

        $this->invalidateSearchCache();
    }

    public function restored(File $file): void
    {
        $this->audit->log(
            'file_restored',
            $this->actorPrefix().' restored file "'.$file->displayName().'".',
            $file
        );
    }

    public function forceDeleted(File $file): void
    {
        $this->audit->log(
            'file_force_deleted',
            $this->actorPrefix().' permanently deleted file "'.$file->displayName().'".',
            $file
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
