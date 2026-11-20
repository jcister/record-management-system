<?php

use App\Http\Requests\ApproveDisposalBatchRequest;
use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\Tag;
use App\Models\User;
use App\Services\ApprovalLetterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Test marking a completed batch as inspected
it('can mark a completed batch as inspected', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'completed_by' => $this->user->id,
        'completed_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    // Mark the file as disposed
    $file->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Physical inspection completed successfully',
            'inspector_name' => 'Jane Inspector',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_INSPECTED)
        ->and($batch->inspected_by)->toBe($this->user->id)
        ->and($batch->inspector_name)->toBe('Jane Inspector')
        ->and($batch->inspected_at)->not->toBeNull()
        ->and($batch->inspection_notes)->toBe('Physical inspection completed successfully');

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_inspected',
        'user_id' => $this->user->id,
    ]);
});

it('requires nap form 3 download before inspection', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => null,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Attempt without download',
            'inspector_name' => 'Audit Team',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Download the NAP Form 3 before marking this batch as inspected.');
});

it('requires nap form 1 download before inspection', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
        'nap_form_1_generated_at' => now(),
        'nap_form_1_downloaded_at' => null,
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Attempt without form 1 download',
            'inspector_name' => 'Audit Team',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Download the NAP Form 1 before marking this batch as inspected.');
});

it('cannot mark a non-completed batch as inspected', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Test notes',
            'inspector_name' => 'Audit Team',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_SCHEDULED)
        ->and($batch->inspected_at)->toBeNull();
});

it('can schedule an inspection for a completed batch', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'completed_by' => $this->user->id,
        'completed_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
        'request_letter_generated_at' => now(),
        'request_letter_downloaded_at' => now(),
    ]);

    $scheduledAt = now()->addDays(3)->format('Y-m-d\TH:i');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => $scheduledAt,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->inspection_scheduled_at)->not->toBeNull()
        ->and($batch->inspection_scheduled_by)->toBe($this->user->id);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'inspection_scheduled',
        'user_id' => $this->user->id,
    ]);
});

it('cannot schedule an inspection for a non-completed batch', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'request_letter_generated_at' => now(),
        'request_letter_downloaded_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Only verified batches can have an inspection scheduled.');
});

it('requires nap form downloads before scheduling inspection', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_downloaded_at' => null,
        'nap_form_1_downloaded_at' => now(),
        'request_letter_generated_at' => now(),
        'request_letter_downloaded_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/schedule-inspection", [
            'inspection_scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Download the NAP Form 3 before scheduling an inspection.');
});

it('can mark a batch as inspected after scheduling inspection', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'completed_by' => $this->user->id,
        'completed_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
        'inspection_scheduled_at' => now()->subHour(),
        'inspection_scheduled_by' => $this->user->id,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Inspection completed on-site',
            'inspector_name' => 'John Doe',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_INSPECTED)
        ->and($batch->inspected_at)->not->toBeNull()
        ->and($batch->inspector_name)->toBe('John Doe')
        ->and($batch->inspection_scheduled_at)->not->toBeNull();
});

it('approves batch when nap authority is confirmed', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
        'inspected_by' => $this->user->id,
        'inspected_at' => now(),
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/nap-authority", [
            'actual_mode_of_disposal' => DisposalBatch::MODE_SALE,
            'nap_form_5_received' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->approved_by)->toBe($this->user->id)
        ->and($batch->approved_at)->not->toBeNull()
        ->and($batch->nap_authority_issued_at)->not->toBeNull()
        ->and($batch->actual_mode_of_disposal)->toBe(DisposalBatch::MODE_SALE);

    expect($file->fresh()->retention_status)->toBe(\App\Support\RetentionStatus::DISPOSED);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_approved',
        'user_id' => $this->user->id,
    ]);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'nap_authority_confirmed',
        'user_id' => $this->user->id,
    ]);
});

it('can reject a batch from inspection result', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
        'nap_form_1_generated_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspector_name' => 'Records Officer',
            'inspection_notes' => 'Failed during physical verification',
            'inspection_result' => 'rejected',
            'rejection_reason' => 'Disposed records did not match inspected inventory.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_REJECTED)
        ->and($batch->inspected_at)->not->toBeNull()
        ->and($batch->rejected_at)->not->toBeNull()
        ->and($batch->rejection_reason)->toBe('Disposed records did not match inspected inventory.');

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_rejected',
        'user_id' => $this->user->id,
    ]);
});

it('can approve an inspected batch', function () {
    Storage::fake('public');

    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
        'inspected_by' => $this->user->id,
        'inspected_at' => now(),
        'approval_documents' => [],
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    $picture = UploadedFile::fake()->image('evidence.jpg');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/approve", [
            'approval_notes' => 'Approved - all requirements met',
            'coa_name' => 'Jane COA',
            'coa_position' => 'Supervising Auditor',
            'coa_designation' => 'Team Leader',
            'pictures' => [$picture],
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    $batch->refresh();

    $document = $batch->approval_documents[0];
    Storage::disk('public')->assertExists(str_replace('public/', '', $document['path']));

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->approved_by)->toBe($this->user->id)
        ->and($batch->approved_at)->not->toBeNull()
        ->and($batch->approval_notes)->toBe('Approved - all requirements met')
        ->and(data_get($batch->metadata, 'approval_review.coa_name'))->toBe('Jane COA')
        ->and(data_get($batch->metadata, 'approval_review.coa_position'))->toBe('Supervising Auditor')
        ->and(data_get($batch->metadata, 'approval_review.coa_designation'))->toBe('Team Leader')
        ->and($batch->coa_name)->toBe('Jane COA')
        ->and($batch->coa_position)->toBe('Supervising Auditor')
        ->and($batch->coa_designation)->toBe('Team Leader')
        ->and(count($batch->approval_documents))->toBe(1);

    expect($file->fresh()->retention_status)->toBe(\App\Support\RetentionStatus::DISPOSED);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_approved',
        'user_id' => $this->user->id,
    ]);
});

it('cannot approve a non-inspected batch', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/approve", [
            'approval_notes' => 'Test notes',
            'coa_name' => 'Test COA',
            'coa_position' => 'State Auditor IV',
            'coa_designation' => 'Team Leader',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->approved_at)->toBeNull();
});

it('can record submission to coa for approved batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approved_by' => $this->user->id,
        'approved_at' => now()->subHour(),
        'approval_letter_path' => 'public/approval-letters/endorsement.pdf',
        'approval_letter_generated_at' => now()->subMinutes(30),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/report-submission", [
            'report_submitted' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETE)
        ->and($batch->report_submitted_to_coa_at)->not->toBeNull()
        ->and($batch->report_submitted_by)->toBe($this->user->id);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'report_submitted_to_coa',
        'user_id' => $this->user->id,
    ]);
});

it('requires endorsement before recording coa submission', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approved_by' => $this->user->id,
        'approved_at' => now()->subHour(),
        'approval_letter_path' => null,
        'approval_letter_generated_at' => null,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/report-submission", [
            'report_submitted' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Finalize the Endorsement Letter to COA before recording submission.');

    expect($batch->fresh()->status)->toBe(DisposalBatch::STATUS_APPROVED);
});

it('does not overwrite existing coa submission metadata', function () {
    $coordinator = User::factory()->create();
    $timestamp = now()->subDay();

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
        'report_submitted_to_coa_at' => $timestamp,
        'report_submitted_by' => $coordinator->id,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/report-submission", [
            'report_submitted' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->report_submitted_to_coa_at?->isSameSecond($timestamp))->toBeTrue()
        ->and($batch->report_submitted_by)->toBe($coordinator->id);
});

it('clears coa submission when the endorsement letter is regenerated', function () {
    Storage::fake('public');

    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
        'report_submitted_to_coa_at' => now()->subDay(),
        'report_submitted_by' => $this->user->id,
        'approval_documents' => [[
            'name' => 'Attachment 1',
            'path' => 'public/approval-documents/'.$file->id.'/existing.jpg',
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $this->user->name,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]],
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    $letterDate = now()->format('F d, Y');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'Updated data for COA packet',
            'letter_reference' => 'ENDORSE-'.$batch->reference,
            'letter_date' => $letterDate,
            'actual_disposal_date' => $letterDate,
            'recipient_first_name' => 'Kobe',
            'recipient_middle_name' => 'Q.',
            'recipient_last_name' => 'Cruz',
            'recipient_extension' => null,
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Regional Office',
            'honorific' => 'Dr.',
            'nap_documents' => [[
                'id' => 'nap-form-3',
                'label' => 'NAP Form 3',
                'existing_path' => 'public/nap-forms/'.$batch->id.'/nap-form-3.pdf',
                'original_name' => 'nap-form-3.pdf',
                'uploaded_at' => now()->toIso8601String(),
            ]],
            'removed_documents' => [],
            'pictures' => [],
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success')
        ->assertSessionHas('warning', 'COA submission confirmation was cleared because a new Endorsement Letter to COA was generated. Please record the submission again after resending the packet to COA.');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->report_submitted_to_coa_at)->toBeNull()
        ->and($batch->report_submitted_by)->toBeNull();

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'coa_submission_reset',
    ]);
});

it('allows recording the coa submission again after regeneration resets it', function () {
    Storage::fake('public');

    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
        'report_submitted_to_coa_at' => now()->subHours(3),
        'report_submitted_by' => $this->user->id,
        'approval_documents' => [[
            'name' => 'Existing Attachment',
            'path' => 'public/approval-documents/'.$file->id.'/existing.jpg',
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $this->user->name,
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ]],
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    $letterDate = now()->format('F d, Y');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'Need to resend packet',
            'letter_reference' => 'ENDORSE-'.$batch->reference,
            'letter_date' => $letterDate,
            'actual_disposal_date' => $letterDate,
            'recipient_first_name' => 'Jamie',
            'recipient_middle_name' => 'K.',
            'recipient_last_name' => 'Diaz',
            'recipient_extension' => null,
            'recipient_position' => 'Supervising Auditor',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Complex',
            'honorific' => 'Mr.',
            'nap_documents' => [[
                'id' => 'nap-form-3',
                'label' => 'NAP Form 3',
                'existing_path' => 'public/nap-forms/'.$batch->id.'/nap-form-3.pdf',
                'original_name' => 'nap-form-3.pdf',
                'uploaded_at' => now()->toIso8601String(),
            ]],
            'removed_documents' => [],
            'pictures' => [],
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('warning');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->report_submitted_to_coa_at)->toBeNull()
        ->and($batch->report_submitted_by)->toBeNull();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/report-submission", [
            'report_submitted' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETE)
        ->and($batch->report_submitted_to_coa_at)->not->toBeNull()
        ->and($batch->report_submitted_by)->toBe($this->user->id);
});

it('can reject an inspected batch', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
        'inspected_by' => $this->user->id,
        'inspected_at' => now(),
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => 'Disposal process did not follow proper procedures',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_REJECTED)
        ->and($batch->rejected_by)->toBe($this->user->id)
        ->and($batch->rejected_at)->not->toBeNull()
        ->and($batch->rejection_reason)->toBe('Disposal process did not follow proper procedures');

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_rejected',
        'user_id' => $this->user->id,
    ]);
});

it('cannot reject a batch without a rejection reason', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => '',
        ])
        ->assertSessionHasErrors(['rejection_reason']);

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_INSPECTED);
});

it('cannot reject a non-inspected batch', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => 'Test reason',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->rejected_at)->toBeNull();
});

it('can return a rejected batch to draft and clear workflow fields', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_REJECTED,
        'completed_by' => $this->user->id,
        'completed_at' => now()->subDays(3),
        'nap_report_path' => 'public/nap-reports/sample.pdf',
        'nap_report_stage' => 'generated',
        'nap_report_generated_by' => $this->user->id,
        'nap_report_generated_at' => now()->subDays(2),
        'nap_report_downloaded_at' => now()->subDays(2),
        'nap_form_1_path' => 'public/nap-form-1s/sample.pdf',
        'nap_form_1_stage' => 'generated',
        'nap_form_1_generated_by' => $this->user->id,
        'nap_form_1_generated_at' => now()->subDays(2),
        'nap_form_1_downloaded_at' => now()->subDays(2),
        'request_letter_path' => 'public/request-letters/sample.pdf',
        'request_letter_generated_by' => $this->user->id,
        'request_letter_generated_at' => now()->subDays(2),
        'request_letter_downloaded_at' => now()->subDays(2),
        'inspection_scheduled_at' => now()->subDays(2),
        'inspection_scheduled_by' => $this->user->id,
        'inspected_by' => $this->user->id,
        'inspector_name' => 'Inspector One',
        'inspected_at' => now()->subDays(2),
        'inspection_notes' => 'Inspection note',
        'actual_mode_of_disposal' => DisposalBatch::MODE_SALE,
        'nap_authority_issued_at' => now()->subDays(1),
        'nap_authority_confirmed_by' => $this->user->id,
        'approved_by' => $this->user->id,
        'approved_at' => now()->subDay(),
        'approval_notes' => 'Approval note',
        'approval_documents' => [['name' => 'sample.jpg', 'path' => 'public/approval-documents/sample.jpg']],
        'approval_letter_path' => 'public/approval-letters/sample.pdf',
        'approval_letter_generated_at' => now()->subDay(),
        'approval_letter_generated_by' => $this->user->id,
        'coa_name' => 'COA Name',
        'coa_position' => 'COA Position',
        'coa_designation' => 'COA Designation',
        'coa_address' => 'COA Address',
        'report_submitted_to_coa_at' => now()->subHours(12),
        'report_submitted_by' => $this->user->id,
        'rejected_by' => $this->user->id,
        'rejected_at' => now()->subHour(),
        'rejection_reason' => 'Original rejection reason',
        'metadata' => [
            'approval_review' => [
                'coa_name' => 'COA Name',
                'coa_position' => 'COA Position',
                'coa_designation' => 'COA Designation',
            ],
            'approval_letter' => [
                'date' => now()->toDateString(),
            ],
            'nap_certificate' => [
                'certificate_date' => now()->toDateString(),
            ],
            'nap_form_1' => [
                'date_prepared' => now()->toDateString(),
            ],
            'request_letter' => [
                'letter_date' => now()->toDateString(),
            ],
            'source' => 'test',
        ],
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/return-to-draft", [
            'reopen_reason' => 'Need minor file and schedule corrections',
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_SCHEDULED)
        ->and($batch->completed_by)->toBeNull()
        ->and($batch->completed_at)->toBeNull()
        ->and($batch->nap_report_path)->toBeNull()
        ->and($batch->nap_report_stage)->toBeNull()
        ->and($batch->nap_report_generated_by)->toBeNull()
        ->and($batch->nap_report_generated_at)->toBeNull()
        ->and($batch->nap_report_downloaded_at)->toBeNull()
        ->and($batch->nap_form_1_path)->toBeNull()
        ->and($batch->nap_form_1_stage)->toBeNull()
        ->and($batch->nap_form_1_generated_by)->toBeNull()
        ->and($batch->nap_form_1_generated_at)->toBeNull()
        ->and($batch->nap_form_1_downloaded_at)->toBeNull()
        ->and($batch->request_letter_path)->toBeNull()
        ->and($batch->request_letter_generated_by)->toBeNull()
        ->and($batch->request_letter_generated_at)->toBeNull()
        ->and($batch->request_letter_downloaded_at)->toBeNull()
        ->and($batch->inspection_scheduled_at)->toBeNull()
        ->and($batch->inspection_scheduled_by)->toBeNull()
        ->and($batch->inspected_by)->toBeNull()
        ->and($batch->inspector_name)->toBeNull()
        ->and($batch->inspected_at)->toBeNull()
        ->and($batch->inspection_notes)->toBeNull()
        ->and($batch->actual_mode_of_disposal)->toBeNull()
        ->and($batch->nap_authority_issued_at)->toBeNull()
        ->and($batch->nap_authority_confirmed_by)->toBeNull()
        ->and($batch->approved_by)->toBeNull()
        ->and($batch->approved_at)->toBeNull()
        ->and($batch->approval_notes)->toBeNull()
        ->and($batch->approval_documents)->toBeNull()
        ->and($batch->approval_letter_path)->toBeNull()
        ->and($batch->approval_letter_generated_at)->toBeNull()
        ->and($batch->approval_letter_generated_by)->toBeNull()
        ->and($batch->coa_name)->toBeNull()
        ->and($batch->coa_position)->toBeNull()
        ->and($batch->coa_designation)->toBeNull()
        ->and($batch->coa_address)->toBeNull()
        ->and($batch->report_submitted_to_coa_at)->toBeNull()
        ->and($batch->report_submitted_by)->toBeNull()
        ->and($batch->rejected_by)->toBeNull()
        ->and($batch->rejected_at)->toBeNull()
        ->and($batch->rejection_reason)->toBeNull()
        ->and(data_get($batch->metadata, 'source'))->toBe('test')
        ->and(data_get($batch->metadata, 'approval_review'))->toBeNull()
        ->and(data_get($batch->metadata, 'request_letter'))->toBeNull();

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_returned_to_draft',
        'user_id' => $this->user->id,
    ]);
});

it('requires a reason when returning a rejected batch to draft', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_REJECTED,
        'rejected_by' => $this->user->id,
        'rejected_at' => now(),
        'rejection_reason' => 'Missing documentation',
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/return-to-draft", [
            'reopen_reason' => '',
        ])
        ->assertSessionHasErrors(['reopen_reason']);

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_REJECTED);
});

it('cannot return non-rejected batches to draft', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/return-to-draft", [
            'reopen_reason' => 'Minor corrections needed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Only rejected batches can be returned to draft.');

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_INSPECTED);
});

it('creates an audit trail when scheduled details are edited', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => now()->addDay(),
        'scheduled_note' => 'Original note',
    ]);

    $newScheduledAt = now()->addDays(2)->format('Y-m-d H:i:s');

    actingAs($this->user)
        ->patch("/disposal-batches/{$batch->id}", [
            'scheduled_at' => $newScheduledAt,
            'scheduled_note' => 'Updated note',
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'batch_updated',
        'user_id' => $this->user->id,
    ]);
});

it('shows inspection and approval data on batch detail page', function () {
    $approver = User::factory()->create(['name' => 'Approver User']);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'inspected_by' => $this->user->id,
        'inspected_at' => now(),
        'inspector_name' => 'Audit Officer',
        'inspection_notes' => 'Inspection complete',
        'approved_by' => $approver->id,
        'approved_at' => now(),
        'approval_notes' => 'Fully approved',
    ]);

    $response = actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Disposal/DisposalBatchDetail')
        ->where('batch.status', DisposalBatch::STATUS_APPROVED)
        ->where('batch.inspector_name', 'Audit Officer')
        ->where('batch.inspection_notes', 'Inspection complete')
        ->where('batch.approval_notes', 'Fully approved')
        ->has('batch.inspected_by')
        ->has('batch.inspected_at')
        ->has('batch.approved_by')
        ->has('batch.approved_at')
    );
});

it('flags missing generated nap form 3 files on batch detail page', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-reports/missing-generated-file.pdf',
        'nap_report_generated_at' => now(),
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatchDetail')
            ->where('batch.nap_report_path', 'public/nap-reports/missing-generated-file.pdf')
            ->where('batch.nap_report_file_exists', false)
        );
});

it('detects existing generated nap form 3 files on batch detail page', function () {
    config()->set('filesystems.default', 'public');

    $path = 'public/nap-reports/existing-generated-file.pdf';
    $fullPath = storage_path('app/'.$path);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, 'PDF content');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => $path,
        'nap_report_generated_at' => now(),
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatchDetail')
            ->where('batch.nap_report_file_exists', true)
        );
});

it('flags missing generated approval letter files on batch detail page', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_letter_path' => 'public/approval-letters/missing-generated-file.pdf',
        'approval_letter_generated_at' => now(),
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatchDetail')
            ->where('batch.approval_letter_path', 'public/approval-letters/missing-generated-file.pdf')
            ->where('batch.approval_letter_file_exists', false)
        );
});

it('includes new statuses in the index page filter options', function () {
    actingAs($this->user)
        ->get('/disposal-batches')
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatches')
            ->has('statusOptions', 6)
            ->has('statusOptions.0', fn ($option) => $option
                ->where('value', 'scheduled')
                ->where('label', 'Scheduled')
            )
            ->has('statusOptions.4', fn ($option) => $option
                ->where('value', 'complete')
                ->where('label', 'Complete')
            )
            ->has('statusOptions.5', fn ($option) => $option
                ->where('value', 'rejected')
                ->where('label', 'Rejected')
            )
        );
});

it('includes coa submission recorder details on disposal batches index', function () {
    $recorder = User::factory()->create(['name' => 'COA Workflow Recorder']);
    $submittedAt = now()->subHour();

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
        'report_submitted_by' => $recorder->id,
        'report_submitted_to_coa_at' => $submittedAt,
    ]);

    actingAs($this->user)
        ->get('/disposal-batches')
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatches')
            ->where('batches.data.0.report_submitted_by.name', 'COA Workflow Recorder')
            ->where('batches.data.0.report_submitted_to_coa_at', $submittedAt->toIso8601String())
        );
});

it('restores files to appropriate retention status when batch is rejected', function () {
    // Create files with different expiry dates
    $fileExpired = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subWeek()->toDateString(),
        'physical_location' => 'Box 5',
    ]);

    $fileActive = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::ACTIVE,
        'retention_expires_at' => now()->addYear()->toDateString(),
        'physical_location' => 'Box 10',
    ]);

    // Create batch and add files
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 2,
    ]);

    // Create batch items with physical location snapshots
    $batch->items()->create([
        'file_id' => $fileExpired->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
        'physical_snapshot' => [
            'location' => 'Box 5',
            'tags' => [],
        ],
    ]);

    $batch->items()->create([
        'file_id' => $fileActive->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
        'physical_snapshot' => [
            'location' => 'Box 10',
            'tags' => [],
        ],
    ]);

    // Mark files as disposed (as happens during completion)
    $fileExpired->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();
    $fileActive->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();

    // Mark batch as inspected
    $batch->update(['status' => DisposalBatch::STATUS_INSPECTED]);

    // Reject the batch
    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => 'Disposal not completed properly',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // Verify files are restored with correct statuses
    $fileExpired->refresh();
    $fileActive->refresh();

    expect($fileExpired->deleted_at)->toBeNull()
        ->and($fileExpired->retention_status)->toBe(\App\Support\RetentionStatus::READY_FOR_DISPOSAL)
        ->and($fileExpired->physical_location)->toBe('Box 5');

    expect($fileActive->deleted_at)->toBeNull()
        ->and($fileActive->retention_status)->toBe(\App\Support\RetentionStatus::ACTIVE)
        ->and($fileActive->physical_location)->toBe('Box 10');

    // Verify audit trail
    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'file_id' => $fileExpired->id,
        'action' => 'file_restored_from_rejection',
    ]);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'file_id' => $fileActive->id,
        'action' => 'file_restored_from_rejection',
    ]);
});

it('restores physical location tags when batch is rejected', function () {
    $tag1 = Tag::factory()->physicalLocation()->create(['name' => 'Cabinet A']);
    $tag2 = Tag::factory()->physicalLocation()->create(['name' => 'Shelf 3']);

    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subWeek()->toDateString(),
        'physical_location' => 'Storage Room',
    ]);

    $file->physicalLocationTags()->attach([$tag1->id, $tag2->id]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    // Create batch item with snapshot including tags
    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
        'physical_snapshot' => [
            'location' => 'Storage Room',
            'tags' => [
                ['id' => $tag1->id, 'name' => 'Cabinet A'],
                ['id' => $tag2->id, 'name' => 'Shelf 3'],
            ],
        ],
    ]);

    // Mark file as disposed
    $file->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();

    // Mark batch as inspected and reject
    $batch->update(['status' => DisposalBatch::STATUS_INSPECTED]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => 'Need to redo disposal',
        ]);

    // Verify tags are restored (fresh from database)
    $file = \App\Models\File::find($file->id);
    expect($file->physicalLocationTags)->toHaveCount(2)
        ->and($file->physicalLocationTags->pluck('id')->sort()->values()->all())->toBe([$tag1->id, $tag2->id]);
});

it('includes restored_at timestamp when files are restored in batch detail', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    // Mark file as disposed then restore to active workflow state
    $file->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();
    $file->forceFill(['retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL])->save();

    // View batch detail page
    $response = actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}");

    $response->assertOk();

    // Verify restored_at is included in the response
    $batchData = $response->viewData('page')['props']['batch'];
    $fileItem = collect($batchData['items'])->firstWhere('file_id', $file->id);

    expect($fileItem)->not->toBeNull()
        ->and($fileItem['file']['restored_at'])->toBeNull()
        ->and($fileItem['file']['deleted_at'])->toBeNull();
});

it('makes files available in disposal queue after batch rejection', function () {
    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subWeek()->toDateString(),
    ]);

    // Create batch and complete disposal
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
        'physical_snapshot' => [
            'location' => 'Storage Room',
            'tags' => [],
        ],
    ]);

    // Mark the file as disposed
    $file->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();

    // Create a new scheduled batch to check available files
    $newBatch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
    ]);

    // File should NOT be in available files (archived)
    $responseBeforeRejection = actingAs($this->user)
        ->getJson("/disposal-batches/{$newBatch->id}/available-files");

    $availableFilesBeforeRejection = collect($responseBeforeRejection->json('files'));
    expect($availableFilesBeforeRejection->pluck('id')->contains($file->id))->toBeFalse();

    // Mark original batch as inspected and reject
    $batch->update(['status' => DisposalBatch::STATUS_INSPECTED]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/reject", [
            'rejection_reason' => 'Need to redo disposal',
        ]);

    // File status should be restored after rejection
    $responseAfterRejection = actingAs($this->user)
        ->getJson("/disposal-batches/{$newBatch->id}/available-files");

    $availableFilesAfterRejection = collect($responseAfterRejection->json('files'));
    expect($responseAfterRejection->isOk())->toBeTrue()
        ->and($availableFilesAfterRejection)->toBeInstanceOf(\Illuminate\Support\Collection::class);

    // Verify file status
    $file->refresh();
    expect($file->deleted_at)->toBeNull()
        ->and($file->retention_status)->toBe(\App\Support\RetentionStatus::READY_FOR_DISPOSAL);
});

it('cannot mark batch as inspected if batch has no files', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 0,
        'nap_form_1_generated_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
    ]);

    // Attempt to mark as inspected - should fail
    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Attempting inspection',
            'inspector_name' => 'Records Officer',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Cannot inspect batch: this batch has no files.');

    // Verify batch is still completed (not inspected)
    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->inspected_at)->toBeNull();
});

it('can mark batch as inspected if some files remain disposed', function () {
    $file1 = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $file2 = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    // Create and complete batch
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 2,
        'nap_form_1_generated_at' => now(),
        'nap_form_1_downloaded_at' => now(),
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => now(),
    ]);

    $batch->items()->create([
        'file_id' => $file1->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    $batch->items()->create([
        'file_id' => $file2->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    // Mark both files disposed, then restore only file1
    $file1->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();
    $file2->forceFill(['retention_status' => \App\Support\RetentionStatus::DISPOSED])->save();
    $file1->forceFill(['retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL])->save();

    // file2 remains disposed

    // Should be able to mark as inspected (file2 is still archived)
    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/mark-as-inspected", [
            'inspection_notes' => 'Inspection completed',
            'inspector_name' => 'Records Officer',
            'inspection_result' => 'passed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // Verify batch is now inspected
    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_INSPECTED)
        ->and($batch->inspected_at)->not->toBeNull();
});

it('shows endorsement finalize page for approved batches with prefilled recipient defaults', function () {
    $metadata = [
        'approval_review' => [
            'coa_name' => 'Existing COA',
            'coa_position' => 'State Auditor IV',
            'coa_designation' => 'Team Leader',
        ],
    ];

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'metadata' => $metadata,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/endorsement-letter/finalize")
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/EndorsementFinalize')
            ->where('batch.reference', $batch->reference)
            ->where('defaultData.coa_name', 'Existing COA')
            ->where('defaultData.coa_position', 'State Auditor IV')
        );
});

it('forbids endorsement finalize page for completed batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/endorsement-letter/finalize")
        ->assertForbidden();
});

it('finalizes endorsement in one request and generates letter metadata', function () {
    Storage::fake('public');

    $file = \App\Models\File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approved_by' => $this->user->id,
        'approved_at' => now()->subHour(),
        'file_count' => 4,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $this->user->id,
        'scheduled_at' => now(),
    ]);

    $pdfPath = 'public/approval-letters/generated.pdf';

    mock(ApprovalLetterService::class)
        ->shouldReceive('generatePdf')
        ->once()
        ->andReturn($pdfPath);

    $payload = [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => now()->format('F j, Y'),
        'actual_disposal_date' => now()->format('F j, Y'),
        'recipient_first_name' => 'COA',
        'recipient_middle_name' => 'Region',
        'recipient_last_name' => 'XI',
        'recipient_extension' => 'Jr.',
        'recipient_name' => 'COA Region XI, Jr.',
        'recipient_position' => 'Regional Director',
        'recipient_designation' => 'Commission on Audit Region XI',
        'recipient_address' => 'COA Regional Office XI',
        'honorific' => 'Ms.',
    ];

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'Approved from unified flow',
            ...$payload,
            'pictures' => [UploadedFile::fake()->image('proof.jpg')],
            'nap_documents' => napDocumentsPayload(),
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    $batch->refresh();

    $metadata = $batch->metadata;

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->approved_by)->toBe($this->user->id)
        ->and($batch->approval_notes)->toBe('Approved from unified flow')
        ->and($batch->approval_letter_path)->toBe($pdfPath)
        ->and($batch->approval_letter_generated_by)->toBe($this->user->id)
        ->and($batch->coa_name)->toBe($payload['recipient_name'])
        ->and($batch->coa_address)->toBe($payload['recipient_address'])
        ->and(data_get($metadata, 'approval_letter.reference'))->toBe($payload['letter_reference'])
        ->and(data_get($metadata, 'approval_letter.date'))->toBe($payload['letter_date'])
        ->and(data_get($metadata, 'approval_letter.actual_disposal_date'))->toBe($payload['actual_disposal_date'])
        ->and(data_get($metadata, 'approval_letter.honorific'))->toBe($payload['honorific'])
        ->and(data_get($metadata, 'approval_letter.recipient_first_name'))->toBe($payload['recipient_first_name'])
        ->and(data_get($metadata, 'approval_letter.recipient_middle_name'))->toBe($payload['recipient_middle_name'])
        ->and(data_get($metadata, 'approval_letter.recipient_last_name'))->toBe($payload['recipient_last_name'])
        ->and(data_get($metadata, 'approval_letter.recipient_extension'))->toBe($payload['recipient_extension'])
        ->and(data_get($metadata, 'approval_letter.recipient_address'))->toBe($payload['recipient_address'])
        ->and(data_get($metadata, 'approval_review.recipient_first_name'))->toBe($payload['recipient_first_name'])
        ->and(data_get($metadata, 'approval_review.recipient_middle_name'))->toBe($payload['recipient_middle_name'])
        ->and(data_get($metadata, 'approval_review.recipient_last_name'))->toBe($payload['recipient_last_name'])
        ->and(data_get($metadata, 'approval_review.recipient_extension'))->toBe($payload['recipient_extension'])
        ->and(data_get($metadata, 'approval_review.honorific'))->toBe($payload['honorific'])
        ->and(data_get($metadata, 'approval_review.coa_address'))->toBe($payload['recipient_address'])
        ->and(data_get($metadata, 'nap_forms'))->not->toBeEmpty();

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'approval_letter_generated',
    ]);
});

it('regenerates endorsement package for approved batches without duplicating approval audits', function () {
    Storage::fake('public');

    $approvedAt = now()->subDay();

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approved_by' => $this->user->id,
        'approved_at' => $approvedAt,
        'approval_documents' => [[
            'name' => 'existing-photo.jpg',
            'path' => 'public/approval-documents/existing-photo.jpg',
            'uploaded_at' => now()->toIso8601String(),
            'mime_type' => 'image/jpeg',
        ]],
        'metadata' => [
            'nap_forms' => [
                ['id' => 'nap-form-1', 'label' => 'NAP Form 1', 'path' => 'public/nap-forms/nap-form-1.pdf'],
                ['id' => 'nap-form-3', 'label' => 'NAP Form 3', 'path' => 'public/nap-forms/nap-form-3.pdf'],
            ],
        ],
    ]);

    $existingAuditCount = DisposalAudit::where('batch_id', $batch->id)
        ->where('action', 'batch_approved')
        ->count();

    $payload = [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => now()->format('F j, Y'),
        'actual_disposal_date' => now()->format('F j, Y'),
        'recipient_first_name' => 'Maria',
        'recipient_last_name' => 'Auditor',
        'recipient_name' => 'Maria Auditor',
        'recipient_position' => 'State Auditor IV',
        'recipient_designation' => 'Team Leader',
        'recipient_address' => 'COA Regional Office XI',
        'honorific' => 'Ms.',
    ];

    $reorderedDocuments = [
        [
            'id' => 'custom-doc',
            'label' => 'Additional Attachment',
            'existing_path' => 'public/custom/custom.pdf',
        ],
        [
            'id' => 'nap-form-1',
            'label' => 'NAP Form 1: Records Inventory and Appraisal',
            'existing_path' => 'public/nap-forms/nap-form-1.pdf',
            'original_name' => 'nap-form-1.pdf',
        ],
    ];

    mock(ApprovalLetterService::class)
        ->shouldReceive('generatePdf')
        ->once()
        ->andReturn('public/approval-letters/regenerated.pdf');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'Refreshed endorsement package',
            ...$payload,
            'nap_documents' => $reorderedDocuments,
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->approved_at?->format('Y-m-d H:i:s'))->toBe($approvedAt->format('Y-m-d H:i:s'))
        ->and($batch->approval_notes)->toBe('Refreshed endorsement package')
        ->and(data_get($batch->metadata, 'nap_forms.0.label'))->toBe('Additional Attachment');

    $newAuditCount = DisposalAudit::where('batch_id', $batch->id)
        ->where('action', 'batch_approved')
        ->count();

    expect($newAuditCount)->toBe($existingAuditCount);
});

it('prevents endorsement finalization for non approved batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    mock(ApprovalLetterService::class)
        ->shouldNotReceive('generatePdf');

    $payload = [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => now()->format('F j, Y'),
        'actual_disposal_date' => now()->format('F j, Y'),
        'recipient_first_name' => 'COA',
        'recipient_middle_name' => 'Region',
        'recipient_last_name' => 'XI',
        'recipient_extension' => 'Jr.',
        'recipient_name' => 'COA Region XI, Jr.',
        'recipient_position' => 'Regional Director',
        'recipient_designation' => 'Commission on Audit Region XI',
        'recipient_address' => 'COA Regional Office XI',
        'honorific' => 'Ms.',
    ];

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => null,
            ...$payload,
            'nap_documents' => napDocumentsPayload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $batch->refresh();

    expect($batch->approval_letter_generated_at)->toBeNull()
        ->and($batch->approval_letter_path)->toBeNull();
});

it('enforces endorsement picture limit', function () {
    Storage::fake('public');

    $existing = collect(range(1, ApproveDisposalBatchRequest::MAX_PICTURES))->map(function ($index) {
        return [
            'name' => "Existing {$index}",
            'path' => "public/approval-documents/existing-{$index}.jpg",
            'uploaded_at' => now()->toIso8601String(),
        ];
    })->all();

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_documents' => $existing,
    ]);

    $extraPicture = UploadedFile::fake()->image('extra.jpg');

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => null,
            'letter_reference' => 'CHED-ROXI-'.$batch->reference,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'Jane',
            'recipient_last_name' => 'COA',
            'recipient_name' => 'Jane COA',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'pictures' => [$extraPicture],
            'nap_documents' => napDocumentsPayload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You can upload a maximum of '.ApproveDisposalBatchRequest::MAX_PICTURES.' pictures.');

    $batch->refresh();
    expect(count($batch->approval_documents))->toBe(ApproveDisposalBatchRequest::MAX_PICTURES);
});

it('allows removing existing documentation before finalizing the endorsement', function () {
    Storage::fake('public');

    $existingDocuments = [
        [
            'name' => 'Keep Attachment',
            'path' => 'public/approval-documents/keep.jpg',
            'uploaded_at' => now()->subDay()->toIso8601String(),
        ],
        [
            'name' => 'Remove Attachment',
            'path' => 'public/approval-documents/remove.jpg',
            'uploaded_at' => now()->subDays(2)->toIso8601String(),
        ],
    ];

    Storage::disk('public')->put('approval-documents/keep.jpg', 'keep');
    Storage::disk('public')->put('approval-documents/remove.jpg', 'remove');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_documents' => $existingDocuments,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'Ready for endorsement',
            'letter_reference' => 'ENDORSEMENT-LETTER-COA-'.$batch->reference,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'Jane',
            'recipient_last_name' => 'Doe',
            'recipient_name' => 'Jane Doe',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Regional Office XI',
            'pictures' => [],
            'removed_documents' => ['public/approval-documents/remove.jpg'],
            'nap_documents' => napDocumentsPayload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect(collect($batch->approval_documents)->pluck('path')->all())
        ->toBe(['public/approval-documents/keep.jpg']);

    Storage::disk('public')->assertExists('approval-documents/keep.jpg');
    Storage::disk('public')->assertMissing('approval-documents/remove.jpg');
});

it('requires at least one documentation picture when finalizing the endorsement', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_documents' => [],
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/endorsement-letter/finalize", [
            'approval_notes' => 'No picture submitted',
            'letter_reference' => 'ENDORSEMENT-LETTER-COA-'.$batch->reference,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'Juan',
            'recipient_last_name' => 'Dela Cruz',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'nap_documents' => napDocumentsPayload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'At least one documentation picture is required before finalizing the endorsement.');

    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_APPROVED)
        ->and($batch->approval_letter_generated_at)->toBeNull();
});

it('downloads approval letter file when available', function () {
    $path = 'public/approval-letters/example.pdf';
    $fullPath = storage_path('app/'.$path);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, 'PDF content');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_letter_path' => $path,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/approval-letter/download")
        ->assertOk()
        ->assertDownload('example.pdf');
});

it('shows endorsement finalize page for approved batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/endorsement-letter/finalize")
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/EndorsementFinalize')
            ->where('batch.id', $batch->id)
            ->where('batch.status', DisposalBatch::STATUS_APPROVED)
        );
});

it('forbids endorsement finalize page for non approved batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/endorsement-letter/finalize")
        ->assertForbidden();
});

it('generates approval letter preview for approved batches with staged attachments', function () {
    Storage::fake('public');

    $existingDocuments = [
        [
            'name' => 'Keep Attachment',
            'path' => 'public/approval-documents/keep.jpg',
            'mime_type' => 'image/jpeg',
            'uploaded_at' => now()->subDay()->toIso8601String(),
        ],
        [
            'name' => 'Remove Attachment',
            'path' => 'public/approval-documents/remove.jpg',
            'mime_type' => 'image/jpeg',
            'uploaded_at' => now()->subDays(2)->toIso8601String(),
        ],
    ];

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_documents' => $existingDocuments,
    ]);

    $photo = UploadedFile::fake()->image('new-proof.jpg', 1024, 768);
    $supportingPdf = UploadedFile::fake()->create('support.pdf', 50, 'application/pdf');

    $napDocuments = [
        [
            'id' => 'nap-form-1',
            'label' => 'NAP Form 1: Records Inventory and Appraisal',
            'existing_path' => 'public/nap-forms/form-1.pdf',
            'original_name' => 'form-1.pdf',
            'uploaded_at' => now()->subDay()->toIso8601String(),
        ],
        [
            'id' => 'custom-doc',
            'label' => 'Custom Supporting Document',
            'file' => $supportingPdf,
        ],
    ];

    $responsePath = 'public/approval-letter-previews/sample.pdf';
    $capturedPhotoPath = null;
    $capturedPdfPath = null;

    mock(ApprovalLetterService::class)
        ->shouldReceive('generatePreviewPdf')
        ->once()
        ->withArgs(function ($actualBatch, $data, $napDocumentsOverride, $documentsOverride) use ($batch, &$capturedPhotoPath, &$capturedPdfPath) {
            expect($actualBatch->is($batch))->toBeTrue()
                ->and($data['letter_reference'])->toBe('ENDORSEMENT-LETTER-COA-'.$batch->reference)
                ->and($data['recipient_name'])->toBe('Juan Santos Dela Cruz, Jr.')
                ->and($documentsOverride)->toHaveCount(2)
                ->and(collect($documentsOverride)->pluck('path'))
                ->not->toContain('public/approval-documents/remove.jpg')
                ->and($napDocumentsOverride)->toHaveCount(2)
                ->and($napDocumentsOverride[0]['existing_path'])
                ->toBe('public/nap-forms/form-1.pdf');

            $photoDocument = collect($documentsOverride)->first(function ($doc) {
                return ($doc['name'] ?? null) === 'new-proof.jpg';
            });
            $capturedPhotoPath = $photoDocument['path'] ?? null;
            $capturedPdfPath = $napDocumentsOverride[1]['existing_path'] ?? null;

            expect($capturedPhotoPath)->not->toBeNull()
                ->and($capturedPdfPath)->toContain('public/nap-form-previews/');

            return true;
        })
        ->andReturn($responsePath);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/approval-letter/preview", [
            'letter_reference' => null,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'Juan',
            'recipient_middle_name' => 'Santos',
            'recipient_last_name' => 'Dela Cruz',
            'recipient_extension' => 'Jr.',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Regional Office XI',
            'honorific' => 'Mr.',
            'pictures' => [$photo],
            'removed_documents' => ['public/approval-documents/remove.jpg'],
            'nap_documents' => $napDocuments,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Preview generated successfully.')
        ->assertJsonPath('preview_path', $responsePath);

    expect($capturedPhotoPath)->not->toBeNull()
        ->and($capturedPdfPath)->not->toBeNull();

    $photoRelativePath = Str::after($capturedPhotoPath, 'public/');
    $pdfRelativePath = Str::after($capturedPdfPath, 'public/');

    Storage::disk('public')->assertMissing($photoRelativePath);
    Storage::disk('public')->assertMissing($pdfRelativePath);
});

it('forbids approval letter preview for invalid statuses', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    $payload = [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => now()->format('F j, Y'),
        'actual_disposal_date' => now()->format('F j, Y'),
        'recipient_first_name' => 'Maria',
        'recipient_middle_name' => 'Juana',
        'recipient_last_name' => 'Dela Cruz',
        'recipient_extension' => null,
        'recipient_position' => 'Regional Director',
        'recipient_designation' => 'Commission on Audit Region XI',
        'recipient_address' => 'COA Regional Office XI',
        'honorific' => 'Ms.',
        'nap_documents' => [
            [
                'id' => 'nap-form-1',
                'label' => 'NAP Form 1: Records Inventory and Appraisal',
                'existing_path' => 'public/nap-forms/form-1.pdf',
                'original_name' => 'form-1.pdf',
                'uploaded_at' => now()->subDay()->toIso8601String(),
            ],
        ],
    ];

    actingAs($this->user)
        ->postJson("/disposal-batches/{$batch->id}/approval-letter/preview", $payload)
        ->assertForbidden();
});

it('allows approval letter preview for completed batches', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
    ]);

    $payload = [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => now()->format('F j, Y'),
        'actual_disposal_date' => now()->format('F j, Y'),
        'recipient_first_name' => 'Alex',
        'recipient_middle_name' => 'Lopez',
        'recipient_last_name' => 'Rivera',
        'recipient_extension' => null,
        'recipient_position' => 'Regional Director',
        'recipient_designation' => 'Commission on Audit Region XI',
        'recipient_address' => 'COA Regional Office XI',
        'honorific' => 'Mr.',
        'nap_documents' => [
            [
                'id' => 'nap-form-3',
                'label' => 'NAP Form 3: Certification of Disposal',
                'existing_path' => 'public/nap-forms/form-3.pdf',
                'original_name' => 'form-3.pdf',
                'uploaded_at' => now()->subDay()->toIso8601String(),
            ],
        ],
    ];

    $previewPath = 'public/approval-letter-previews/complete.pdf';

    mock(ApprovalLetterService::class)
        ->shouldReceive('generatePreviewPdf')
        ->once()
        ->andReturn($previewPath);

    actingAs($this->user)
        ->postJson("/disposal-batches/{$batch->id}/approval-letter/preview", $payload)
        ->assertOk()
        ->assertJsonPath('preview_path', $previewPath)
        ->assertJsonPath('message', 'Preview generated successfully.');
});

it('can preview approval letter inline when requested', function () {
    $path = 'public/approval-letters/example-inline.pdf';
    $fullPath = storage_path('app/'.$path);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, 'PDF content');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_letter_path' => $path,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/approval-letter/download?disposition=inline")
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=example-inline.pdf');
});

it('can preview nap form 3 inline without marking it as downloaded', function () {
    $path = 'public/nap-reports/nap-inline.pdf';
    $fullPath = storage_path('app/'.$path);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, 'PDF content');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => $path,
        'nap_report_generated_at' => now(),
        'nap_report_downloaded_at' => null,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/nap-form-3/download?disposition=inline")
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=nap-inline.pdf');

    expect($batch->fresh()->nap_report_downloaded_at)->toBeNull();
});

it('returns not found when approval letter file is missing', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_letter_path' => 'public/approval-letters/missing.pdf',
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/approval-letter/download")
        ->assertNotFound();
});

it('regenerating approval letter updates approval review metadata', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'metadata' => [
            'approval_review' => [
                'coa_name' => 'Old COA',
                'coa_position' => 'Old Position',
                'coa_designation' => 'Old Designation',
            ],
        ],
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/approval-letter/generate", [
            'letter_reference' => 'CHED-ROXI-'.$batch->reference,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'New',
            'recipient_middle_name' => 'COA',
            'recipient_last_name' => 'Contact',
            'recipient_extension' => 'Jr.',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Regional Office XI',
            'honorific' => 'Ms.',
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    $batch->refresh();

    expect(data_get($batch->metadata, 'approval_review.coa_name'))->toBe('New COA Contact, Jr.')
        ->and(data_get($batch->metadata, 'approval_review.coa_position'))->toBe('State Auditor IV')
        ->and(data_get($batch->metadata, 'approval_review.coa_designation'))->toBe('Team Leader')
        ->and(data_get($batch->metadata, 'approval_review.coa_address'))->toBe('COA Regional Office XI')
        ->and(data_get($batch->metadata, 'approval_review.honorific'))->toBe('Ms.')
        ->and(data_get($batch->metadata, 'approval_review.recipient_first_name'))->toBe('New')
        ->and(data_get($batch->metadata, 'approval_review.recipient_middle_name'))->toBe('COA')
        ->and(data_get($batch->metadata, 'approval_review.recipient_last_name'))->toBe('Contact')
        ->and(data_get($batch->metadata, 'approval_review.recipient_extension'))->toBe('Jr.');
});

it('regenerating approval letter updates approval notes', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_notes' => 'Old approval note',
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/approval-letter/generate", [
            'approval_notes' => 'Updated approval note from preview',
            'letter_reference' => 'CHED-ROXI-'.$batch->reference,
            'letter_date' => now()->format('F j, Y'),
            'actual_disposal_date' => now()->format('F j, Y'),
            'recipient_first_name' => 'New',
            'recipient_last_name' => 'Contact',
            'recipient_position' => 'State Auditor IV',
            'recipient_designation' => 'Team Leader',
            'recipient_address' => 'COA Regional Office XI',
        ])
        ->assertRedirect("/disposal-batches/{$batch->id}")
        ->assertSessionHas('success');

    expect($batch->fresh()->approval_notes)->toBe('Updated approval note from preview');
});

function napDocumentsPayload(?UploadedFile $file = null): array
{
    return [[
        'id' => 'nap-form-1',
        'label' => 'NAP Form 1: Records Inventory and Appraisal',
        'existing_path' => $file ? null : 'public/nap-forms/sample.pdf',
        'original_name' => $file ? null : 'nap-form-1.pdf',
        'uploaded_at' => $file ? null : now()->toIso8601String(),
        'file' => $file,
    ]];
}
