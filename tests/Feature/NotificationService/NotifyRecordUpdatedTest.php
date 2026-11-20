<?php

use App\Models\File;
use App\Models\User;
use App\Notifications\RecordUpdated;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->uploader = User::factory()->create(['status' => 'active']);
    $this->actor = User::factory()->create(['status' => 'active']);
});

it('notifies the uploader when record is updated', function (): void {
    Notification::fake();

    $file = File::factory()->create(['uploaded_by' => $this->uploader->id]);

    app(NotificationService::class)->notifyRecordUpdated($file, $this->actor);

    Notification::assertSentTo($this->uploader, RecordUpdated::class);
});

it('does not self-notify when actor is the uploader', function (): void {
    Notification::fake();

    $file = File::factory()->create(['uploaded_by' => $this->actor->id]);

    app(NotificationService::class)->notifyRecordUpdated($file, $this->actor);

    Notification::assertNotSentTo($this->actor, RecordUpdated::class);
});

it('does not notify when file has no uploader', function (): void {
    Notification::fake();

    $file = File::factory()->create(['uploaded_by' => null]);

    app(NotificationService::class)->notifyRecordUpdated($file, $this->actor);

    Notification::assertNothingSent();
});

it('does not notify inactive uploaders', function (): void {
    Notification::fake();

    $this->uploader->update(['status' => 'inactive']);
    $file = File::factory()->create(['uploaded_by' => $this->uploader->id]);

    app(NotificationService::class)->notifyRecordUpdated($file, $this->actor);

    Notification::assertNotSentTo($this->uploader, RecordUpdated::class);
});

it('does not notify when actor is null', function (): void {
    Notification::fake();

    $file = File::factory()->create(['uploaded_by' => $this->uploader->id]);

    app(NotificationService::class)->notifyRecordUpdated($file, null);

    Notification::assertNothingSent();
});

it('includes actor name in notification', function (): void {
    Notification::fake();

    $file = File::factory()->create(['uploaded_by' => $this->uploader->id]);

    app(NotificationService::class)->notifyRecordUpdated($file, $this->actor);

    Notification::assertSentTo($this->uploader, RecordUpdated::class, function (RecordUpdated $notification) {
        $data = $notification->toArray($this->uploader);

        return $data['actor'] === $this->actor->name;
    });
});
