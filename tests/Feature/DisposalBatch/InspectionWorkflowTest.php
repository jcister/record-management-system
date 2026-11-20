<?php

use App\Models\DisposalBatch;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

function makeInspectionReadyBatch(array $overrides = []): DisposalBatch
{
    $defaults = [
        'status' => DisposalBatch::STATUS_REQUEST_LETTER_READY,
        'nap_report_downloaded_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_form_1_path' => 'public/nap-form-1s/test.pdf',
        'request_letter_generated_at' => now(),
        'request_letter_downloaded_at' => now(),
        'request_letter_path' => 'public/request-letters/test.pdf',
        'metadata' => [
            'request_letter' => [
                'letter_date' => now()->subDay()->format('F d, Y'),
            ],
        ],
    ];

    return DisposalBatch::factory()->create(array_merge($defaults, $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires the request letter to be generated before scheduling an inspection', function () {
    $batch = makeInspectionReadyBatch([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'request_letter_generated_at' => null,
        'request_letter_downloaded_at' => null,
        'request_letter_path' => null,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Generate the Request Letter before scheduling an inspection.');
});

it('requires the request letter to be downloaded before scheduling an inspection', function () {
    $batch = makeInspectionReadyBatch([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'request_letter_downloaded_at' => null,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Download the Request Letter before scheduling an inspection.');
});

it('requires confirmation when the request letter date is in the future', function () {
    $batch = makeInspectionReadyBatch([
        'metadata' => [
            'request_letter' => [
                'letter_date' => now()->addWeek()->format('F d, Y'),
            ],
        ],
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Confirm that the Request Letter has already been sent to NAP before scheduling an inspection.');
});

it('schedules the inspection when the future-dated request letter is confirmed as sent to nap', function () {
    $batch = makeInspectionReadyBatch([
        'metadata' => [
            'request_letter' => [
                'letter_date' => now()->addWeek()->format('F d, Y'),
            ],
        ],
    ]);

    $scheduledAt = now()->addDays(3)->format('Y-m-d\TH:i');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => $scheduledAt,
            'request_letter_sent_to_nap' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Inspection scheduled successfully.');

    $batch->refresh();

    expect($batch->inspection_scheduled_at)->not->toBeNull()
        ->and($batch->inspection_scheduled_by)->toBe($this->user->id);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'inspection_scheduled',
    ]);
});
