<?php

use App\Models\File;
use App\Models\Permission;
use App\Models\Requests;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ScanApproved;
use App\Notifications\ScanRejected;
use App\Notifications\ScanSubmittedForReview;
use App\Services\NotificationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $reviewPermission = Permission::where('slug', 'review-content')->first();
    $this->reviewerRole = Role::factory()->create();
    $this->reviewerRole->permissions()->attach($reviewPermission);

    $this->reviewer = User::factory()->create(['status' => 'active']);
    $this->reviewer->roles()->attach($this->reviewerRole);

    $this->requestor = User::factory()->create(['status' => 'active']);
    $this->uploader = User::factory()->create(['status' => 'active']);
});

describe('notifyScanSubmittedForReview', function (): void {
    it('notifies users with review-content permission when scan is submitted', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $scanFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanSubmittedForReview(
            $request,
            $scanFile,
            $this->uploader
        );

        Notification::assertSentTo($this->reviewer, ScanSubmittedForReview::class);
    });

    it('does not self-notify the uploader', function (): void {
        Notification::fake();

        // Give uploader review-content permission
        $this->uploader->roles()->attach($this->reviewerRole);

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $scanFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanSubmittedForReview(
            $request,
            $scanFile,
            $this->uploader
        );

        Notification::assertNotSentTo($this->uploader, ScanSubmittedForReview::class);
    });

    it('does not notify when uploader is null', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $scanFile = File::factory()->create(['record_id' => 'SCAN-TEST-001']);

        app(NotificationService::class)->notifyScanSubmittedForReview(
            $request,
            $scanFile,
            null
        );

        Notification::assertNothingSent();
    });

    it('links to pending-unorganized page with scan file highlighted', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $scanFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanSubmittedForReview(
            $request,
            $scanFile,
            $this->uploader
        );

        Notification::assertSentTo($this->reviewer, ScanSubmittedForReview::class, function (ScanSubmittedForReview $notification) use ($scanFile) {
            $data = $notification->toArray($this->reviewer);

            return $data['link'] === '/records/pending-unorganized?highlight='.rawurlencode($scanFile->id);
        });
    });
});

describe('notifyScanApproved', function (): void {
    it('does not notify request owner when scan is approved', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $targetFile = File::factory()->create([
            'is_record' => true,
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanApproved(
            $request,
            $targetFile,
            $this->reviewer
        );

        Notification::assertNotSentTo($this->requestor, ScanApproved::class);
    });

    it('notifies scan uploader when scan is approved', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $targetFile = File::factory()->create([
            'is_record' => true,
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanApproved(
            $request,
            $targetFile,
            $this->reviewer
        );

        Notification::assertSentTo($this->uploader, ScanApproved::class);
    });

    it('does not self-notify the approver', function (): void {
        Notification::fake();

        // Reviewer is also the request owner
        $request = Requests::factory()->create(['requested_by' => $this->reviewer->id]);
        $targetFile = File::factory()->create([
            'is_record' => true,
            'uploaded_by' => $this->reviewer->id,
        ]);

        app(NotificationService::class)->notifyScanApproved(
            $request,
            $targetFile,
            $this->reviewer
        );

        Notification::assertNotSentTo($this->reviewer, ScanApproved::class);
    });

    it('does not notify when approver is null', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $targetFile = File::factory()->create(['is_record' => true]);

        app(NotificationService::class)->notifyScanApproved(
            $request,
            $targetFile,
            null
        );

        Notification::assertNothingSent();
    });
});

describe('notifyScanRejected', function (): void {
    it('does not notify request owner when scan is rejected', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $pendingFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanRejected(
            $request,
            $pendingFile,
            'Poor scan quality',
            $this->reviewer
        );

        Notification::assertNotSentTo($this->requestor, ScanRejected::class);
    });

    it('notifies scan uploader when scan is rejected', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $pendingFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanRejected(
            $request,
            $pendingFile,
            'Poor scan quality',
            $this->reviewer
        );

        Notification::assertSentTo($this->uploader, ScanRejected::class);
    });

    it('includes rejection reason in notification', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $pendingFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->uploader->id,
        ]);

        app(NotificationService::class)->notifyScanRejected(
            $request,
            $pendingFile,
            'Poor scan quality',
            $this->reviewer
        );

        Notification::assertSentTo($this->uploader, ScanRejected::class, function (ScanRejected $notification) {
            $data = $notification->toArray($this->uploader);

            return $data['reason'] === 'Poor scan quality';
        });
    });

    it('does not self-notify the rejector', function (): void {
        Notification::fake();

        // Reviewer is also the request owner
        $request = Requests::factory()->create(['requested_by' => $this->reviewer->id]);
        $pendingFile = File::factory()->create([
            'record_id' => 'SCAN-TEST-001',
            'uploaded_by' => $this->reviewer->id,
        ]);

        app(NotificationService::class)->notifyScanRejected(
            $request,
            $pendingFile,
            'Poor scan quality',
            $this->reviewer
        );

        Notification::assertNotSentTo($this->reviewer, ScanRejected::class);
    });

    it('does not notify when rejector is null', function (): void {
        Notification::fake();

        $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);
        $pendingFile = File::factory()->create(['record_id' => 'SCAN-TEST-001']);

        app(NotificationService::class)->notifyScanRejected(
            $request,
            $pendingFile,
            'Poor scan quality',
            null
        );

        Notification::assertNothingSent();
    });
});
