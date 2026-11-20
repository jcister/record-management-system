<?php

use App\Models\DisposalBatch;
use App\Services\ApprovalLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds payload with attachment summary and approval notes', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('test.jpg', 48, 48);
    Storage::disk('public')->putFileAs('approval-documents', $image, 'test.jpg');
    Storage::disk('public')->put('nap-forms/nap-form-1.pdf', 'pdf-data');

    $batch = DisposalBatch::factory()->create([
        'approval_documents' => [[
            'name' => 'test.jpg',
            'path' => 'public/approval-documents/test.jpg',
            'mime_type' => 'image/jpeg',
        ]],
        'metadata' => [
            'nap_forms' => [
                'nap_form_1' => 'public/nap-forms/nap-form-1.pdf',
            ],
        ],
        'file_count' => 3,
    ]);

    $service = app(ApprovalLetterService::class);

    $payload = $service->buildPayload($batch, [
        'letter_reference' => 'CHED-ROXI-'.$batch->reference,
        'letter_date' => 'March 15, 2026',
        'approval_notes' => 'All files verified and documented.',
        'recipient_first_name' => 'Juan',
        'recipient_last_name' => 'Dela Cruz',
        'recipient_position' => 'Regional Director',
        'recipient_designation' => 'Commission on Audit XI',
    ]);

    expect($payload['letter']['notes'])->toBe('All files verified and documented.')
        ->and($payload['attachmentSummary'][0]['label'])->toBe('Pictures / Documentation')
        ->and($payload['attachmentSummary'][0]['present'])->toBeTrue();

    $napForm1 = collect($payload['attachmentSummary'])->firstWhere('key', 'nap_form_1');
    $napForm4 = collect($payload['attachmentSummary'])->firstWhere('key', 'nap_form_4');

    expect($napForm1['present'])->toBeTrue()
        ->and($napForm4['present'])->toBeFalse();
});
