<?php

namespace App\Services;

use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Requests;
use App\Models\User;
use App\Notifications\DisposalBatchScheduleReminder;
use App\Notifications\NewRequestSubmitted;
use App\Notifications\RecordDisposalApproaching;
use App\Notifications\RecordUpdated;
use App\Notifications\RequestStatusUpdated;
use App\Notifications\RequestWillReturnCopyChanged;
use App\Notifications\RetentionDurationReached;
use App\Notifications\ScanApproved;
use App\Notifications\ScanRejected;
use App\Notifications\ScanSubmittedForReview;

class NotificationService
{
    public function notifyNewRequest(Requests $request, ?User $requestor = null): void
    {
        // Only notify during authenticated (web/API) requests, not during seeding or console commands.
        if ($requestor === null) {
            return;
        }

        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'manage-requests'))
            ->when($requestor !== null, fn ($q) => $q->where('id', '!=', $requestor->id))
            ->get();

        $requestorName = $requestor?->name ?? 'Unknown';

        foreach ($recipients as $recipient) {
            $recipient->notify(new NewRequestSubmitted($request, $requestorName));
        }
    }

    public function notifyRequestStatusUpdated(Requests $request, string $oldStatus, string $newStatus, ?User $actor = null): void
    {
        if ($actor === null) {
            return;
        }

        $requestor = $request->requestedBy;

        if ($requestor === null) {
            return;
        }

        // Don't self-notify if the actor is the requestor.
        if ($actor !== null && $actor->id === $requestor->id) {
            return;
        }

        $actorName = $actor?->name ?? 'Unknown';

        $requestor->notify(new RequestStatusUpdated($request, $oldStatus, $newStatus, $actorName));
    }

    public function notifyRecordUpdated(File $file, ?User $actor = null): void
    {
        if ($actor === null || $file->uploaded_by === null) {
            return;
        }

        $uploader = $file->uploader;

        if ($uploader === null || $uploader->status !== 'active') {
            return;
        }

        if ($actor->id === $uploader->id) {
            return;
        }

        $uploader->notify(new RecordUpdated($file, $actor->name ?? 'Unknown'));
    }

    public function notifyApproachingDisposals(File $file): void
    {
        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'disposal-management'))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new RecordDisposalApproaching($file));
        }
    }

    public function notifyRequestWillReturnCopyChanged(Requests $request, bool $oldValue, bool $newValue, ?User $actor = null): void
    {
        if ($actor === null) {
            return;
        }

        $request->loadMissing('requestedBy');

        $actorId = $actor->id;
        $actorName = $actor->name ?? 'Unknown';

        // Notify users with manage-requests permission (archives team)
        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', function ($q) {
                $q->where('slug', 'manage-requests');
            })
            ->where('id', '!=', $actorId)
            ->get();

        $requestor = $request->requestedBy;

        if ($requestor !== null && $requestor->id !== $actorId) {
            $recipients->push($requestor);
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $recipient) {
            $isOwner = $requestor !== null && $recipient->id === $requestor->id;
            $hasManagePermission = $recipient->hasPermission('manage-requests');
            $basePath = ($hasManagePermission && ! $isOwner) ? '/requests' : '/requests/myrequests';

            $recipient->notify(new RequestWillReturnCopyChanged(
                $request,
                $oldValue,
                $newValue,
                $actorName,
                $this->buildRequestHighlightLink($request, $basePath)
            ));
        }
    }

    public function notifyScanSubmittedForReview(Requests $request, File $scanFile, ?User $uploader = null): void
    {
        if ($uploader === null) {
            return;
        }

        $uploaderName = $uploader->name ?? 'Unknown';
        $uploaderId = $uploader->id;

        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'review-content'))
            ->where('id', '!=', $uploaderId)
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new ScanSubmittedForReview($request, $scanFile, $uploaderName));
        }
    }

    public function notifyScanApproved(Requests $request, File $targetFile, ?User $approver = null): void
    {
        if ($approver === null) {
            return;
        }

        $targetFile->loadMissing('uploader');

        $approverName = $approver->name ?? 'Unknown';
        $approverId = $approver->id;

        $uploader = $targetFile->uploader;
        if ($uploader !== null && $uploader->id !== $approverId && $uploader->status === 'active') {
            $uploader->notify(new ScanApproved(
                $request,
                $targetFile,
                $approverName,
                $this->buildRequestHighlightLink($request, '/requests')
            ));
        }
    }

    public function notifyScanRejected(Requests $request, File $pendingFile, string $reason, ?User $rejector = null): void
    {
        if ($rejector === null) {
            return;
        }

        $pendingFile->loadMissing('uploader');

        $rejectorName = $rejector->name ?? 'Unknown';
        $rejectorId = $rejector->id;

        $uploader = $pendingFile->uploader;
        if ($uploader !== null && $uploader->id !== $rejectorId && $uploader->status === 'active') {
            $uploader->notify(new ScanRejected(
                $request,
                $rejectorName,
                $reason,
                $this->buildRequestHighlightLink($request, '/requests')
            ));
        }
    }

    public function notifyRetentionDurationReached(File $file): void
    {
        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'disposal-management'))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new RetentionDurationReached($file));
        }
    }

    public function notifyDisposalBatchSchedule(DisposalBatch $batch, int $daysUntilScheduled): void
    {
        $recipients = User::query()
            ->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'disposal-management'))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new DisposalBatchScheduleReminder($batch, $daysUntilScheduled));
        }
    }

    private function buildRequestHighlightLink(Requests $request, string $basePath): string
    {
        $requestCode = (string) ($request->request_id ?? '');

        if ($requestCode === '') {
            return $basePath;
        }

        return $basePath.'?highlight='.rawurlencode($requestCode);
    }
}
