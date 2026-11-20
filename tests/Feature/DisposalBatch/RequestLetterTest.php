<?php

use App\Models\DisposalBatch;
use App\Models\User;
use App\Services\RequestLetterService;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

function ensureNapPdfExists(string $relativePath = 'public/nap-form-3s/test.pdf'): void
{
    $fullPath = storage_path('app/'.$relativePath);
    $directory = dirname($fullPath);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (! file_exists($fullPath)) {
        $pdf = Pdf::loadHTML('<html><body><h1>NAP Form 3 Attachment</h1><p>Combined for testing.</p></body></html>');
        file_put_contents($fullPath, $pdf->output());
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires nap form 3 to be generated before preview', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => null,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/request-letter/preview")
        ->assertForbidden();
});

it('can access request letter preview when nap form 3 exists', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'nap_report_generated_by' => $this->user->id,
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/request-letter/preview")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/RequestLetterPreview')
            ->has('batch')
            ->has('defaultData')
            ->has('director')
            ->has('currentUser')
        );
});

it('validates required fields when generating request letter', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'metadata' => ['nap_certificate' => ['items' => []]],
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [])
        ->assertSessionHasErrors([
            'letter_date',
            'recipient_name',
            'recipient_first_name',
            'recipient_last_name',
            'recipient_position',
            'recipient_designation',
            'address_1',
        ]);
});

it('requires nap form 3 to be generated before generating request letter', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => null,
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'address_2' => 'A. Bonifacio St., Poblacion, Davao City',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
            'user_position' => 'Administrative Officer',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('requires nap certificate data to exist before generating', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'metadata' => [],
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('requires the stored nap form 3 pdf to exist on disk before generating', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/missing.pdf',
        'nap_report_generated_at' => now(),
        'metadata' => [
            'nap_certificate' => [
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-999',
                        'record_series_title' => 'Test',
                        'period_covered' => '2023',
                        'retention_period' => '1 Year',
                    ],
                ],
            ],
        ],
    ]);

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'NAP Form 3 file is missing. Please regenerate NAP Form 3 before generating the Request Letter.');
});

it('generates request letter pdf and creates audit entry', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'nap_report_generated_by' => $this->user->id,
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'Commission on Higher Education Regional Office XI',
                'address' => 'Loyola Street, Barrio Obrero, Davao City',
                'date' => 'March 11, 2026',
                'telephone_number' => '',
                'location_of_records' => 'Storage Room',
                'volume_in_cubic_meter' => '',
                'prepared_by_name' => 'Test User',
                'prepared_by_position' => '',
                'certified_by_name' => 'Director',
                'certified_by_designation' => 'Director IV',
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-001',
                        'record_series_title' => 'Test Record',
                        'period_covered' => 'Jan 2020 - Dec 2020',
                        'retention_period' => '1 Year',
                    ],
                ],
            ],
            'nap_form_1' => [
                'prepared_by_name' => 'Prepared Officer',
                'prepared_by_position' => 'Records Officer',
            ],
        ],
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'address_2' => 'A. Bonifacio St., Poblacion, Davao City',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
            'user_position' => 'Administrative Officer',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    expect($batch->request_letter_path)->not->toBeNull()
        ->and($batch->request_letter_generated_at)->not->toBeNull()
        ->and($batch->request_letter_generated_by)->toBe($this->user->id)
        ->and($batch->status)->toBe(DisposalBatch::STATUS_REQUEST_LETTER_READY);

    assertDatabaseHas('disposal_audits', [
        'batch_id' => $batch->id,
        'action' => 'request_letter_generated',
        'user_id' => $this->user->id,
    ]);

    // Verify metadata was saved
    $metadata = $batch->metadata;
    expect($metadata['request_letter']['recipient_name'])->toBe('RONALDO C. FERARIZA, PhD')
        ->and($metadata['request_letter']['recipient_first_name'])->toBe('RONALDO')
        ->and($metadata['request_letter']['recipient_middle_name'])->toBe('C.')
        ->and($metadata['request_letter']['recipient_last_name'])->toBe('FERARIZA')
        ->and($metadata['request_letter']['recipient_extension'])->toBe('PhD')
        ->and($metadata['request_letter']['user_name'])->toBe('Prepared Officer')
        ->and($metadata['request_letter']['user_position'])->toBe('Records Officer')
        ->and($metadata['request_letter']['honorific'])->toBe('Dr.');
});

it('appends nap form 3 pages to the generated request letter pdf', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'CHED RO XI',
                'address' => 'Loyola Street, Barrio Obrero, Davao City',
                'date' => 'March 11, 2026',
                'telephone_number' => '',
                'location_of_records' => 'Storage Room',
                'volume_in_cubic_meter' => '',
                'prepared_by_name' => 'Test User',
                'prepared_by_position' => 'Records Officer',
                'certified_by_name' => 'Director',
                'certified_by_designation' => 'Director IV',
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-010',
                        'record_series_title' => 'Merged PDF Verification',
                        'period_covered' => '2025',
                        'retention_period' => '2 Years',
                    ],
                ],
            ],
            'nap_form_1' => [
                'prepared_by_name' => 'Prepared Officer',
                'prepared_by_position' => 'Records Officer',
            ],
        ],
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'address_2' => 'A. Bonifacio St., Poblacion, Davao City',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
            'user_position' => 'Administrative Officer',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();
    $fullPath = storage_path('app/'.$batch->request_letter_path);
    expect(file_exists($fullPath))->toBeTrue();

    $fpdi = new Fpdi;
    $pageCount = $fpdi->setSourceFile($fullPath);

    expect($pageCount)->toBeGreaterThan(1);
});

it('syncs request letter preview via ajax and returns a preview path', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'nap_report_generated_by' => $this->user->id,
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'CHED RO XI',
                'address' => 'Loyola Street, Barrio Obrero, Davao City',
                'date' => 'March 11, 2026',
                'telephone_number' => '',
                'location_of_records' => 'Storage Room',
                'volume_in_cubic_meter' => '',
                'prepared_by_name' => 'Test User',
                'prepared_by_position' => 'Records Officer',
                'certified_by_name' => 'Director',
                'certified_by_designation' => 'Director IV',
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-003',
                        'record_series_title' => 'Record Series',
                        'period_covered' => '2024',
                        'retention_period' => '2 Years',
                    ],
                ],
            ],
            'nap_form_1' => [
                'prepared_by_name' => 'Prepared Officer',
                'prepared_by_position' => 'Records Officer',
            ],
        ],
    ]);

    ensureNapPdfExists();

    $response = actingAs($this->user)
        ->postJson("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'address_2' => 'A. Bonifacio St., Poblacion, Davao City',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
            'user_position' => 'Administrative Officer',
            'preview_sync' => true,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'preview_path']);

    $previewPath = $response->json('preview_path');
    expect($previewPath)->toBeString()->toStartWith('public/request-letters/');
});

it('returns json payload when generating the request letter via ajax', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'nap_report_generated_by' => $this->user->id,
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'CHED RO XI',
                'address' => 'Loyola Street, Barrio Obrero, Davao City',
                'date' => 'March 11, 2026',
                'telephone_number' => '',
                'location_of_records' => 'Storage Room',
                'volume_in_cubic_meter' => '',
                'prepared_by_name' => 'Test User',
                'prepared_by_position' => 'Records Officer',
                'certified_by_name' => 'Director',
                'certified_by_designation' => 'Director IV',
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-004',
                        'record_series_title' => 'Record Series',
                        'period_covered' => '2024',
                        'retention_period' => '2 Years',
                    ],
                ],
            ],
            'nap_form_1' => [
                'prepared_by_name' => 'Prepared Officer',
                'prepared_by_position' => 'Records Officer',
            ],
        ],
    ]);

    ensureNapPdfExists();

    $response = actingAs($this->user)
        ->postJson("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'June 22, 2022',
            'recipient_name' => 'RONALDO C. FERARIZA, PhD',
            'recipient_first_name' => 'RONALDO',
            'recipient_middle_name' => 'C.',
            'recipient_last_name' => 'FERARIZA',
            'recipient_extension' => 'PhD',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => '2d Floor, KORE Devt. Bldg., Bgy. 004',
            'address_2' => 'A. Bonifacio St., Poblacion, Davao City',
            'telephone' => '(082) 297-4705',
            'honorific' => 'Dr.',
            'user_honorific' => 'Ms.',
            'user_position' => 'Administrative Officer',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'request_letter_path']);

    $batch->refresh();
    expect($batch->request_letter_path)->not->toBeNull();
});

it('returns 404 when downloading nonexistent request letter', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'request_letter_path' => null,
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/request-letter/download")
        ->assertNotFound();
});

it('returns 404 when request letter file is missing from disk', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'request_letter_path' => 'public/request-letters/nonexistent.pdf',
    ]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/request-letter/download")
        ->assertNotFound();
});

it('allows inline download of request letter previews', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
    ]);

    $previewPath = 'public/request-letters/preview.pdf';
    $fullPreviewPath = storage_path('app/'.$previewPath);
    if (! is_dir(dirname($fullPreviewPath))) {
        mkdir(dirname($fullPreviewPath), 0755, true);
    }
    file_put_contents($fullPreviewPath, 'preview');
    expect(file_exists($fullPreviewPath))->toBeTrue();
    $query = http_build_query(['preview_path' => $previewPath]);

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}/request-letter/download?{$query}")
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=preview.pdf');
});

it('includes request letter data in batch show page', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'request_letter_path' => 'public/request-letters/test.pdf',
        'request_letter_generated_at' => now(),
        'request_letter_generated_by' => $this->user->id,
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->get("/disposal-batches/{$batch->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Disposal/DisposalBatchDetail')
            ->has('batch.request_letter_path')
            ->has('batch.request_letter_generated_at')
            ->has('batch.request_letter_generated_by')
        );
});

it('falls back to current user when nap form data is missing', function () {
    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test.pdf',
        'nap_report_generated_at' => now(),
        'nap_report_generated_by' => $this->user->id,
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'CHED RO XI',
                'address' => 'Davao City',
                'date' => 'March 11, 2026',
                'telephone_number' => '',
                'location_of_records' => 'Records Room',
                'volume_in_cubic_meter' => '',
                'prepared_by_name' => 'Scheduler',
                'prepared_by_position' => 'Officer',
                'certified_by_name' => 'Director',
                'certified_by_designation' => 'Director IV',
                'items' => [
                    [
                        'grds_rds_item_no' => 'ITEM-002',
                        'record_series_title' => 'Sample Item',
                        'period_covered' => '2023',
                        'retention_period' => '1 Year',
                    ],
                ],
            ],
        ],
    ]);

    ensureNapPdfExists();

    actingAs($this->user)
        ->post("/disposal-batches/{$batch->id}/request-letter/generate", [
            'letter_date' => 'March 12, 2026',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_first_name' => 'Juan',
            'recipient_middle_name' => '',
            'recipient_last_name' => 'Dela Cruz',
            'recipient_extension' => '',
            'recipient_position' => 'Head-RAN Davao',
            'recipient_designation' => 'Regional Archival Network',
            'address_1' => 'Loyola Street',
            'telephone' => '123-4567',
            'honorific' => 'Mr.',
            'user_honorific' => 'Mr.',
            'user_position' => 'Records Officer',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch->refresh();

    $expectedRequestingOfficerName = app(RequestLetterService::class)
        ->formatRequestingOfficerName($this->user->name);

    expect($batch->metadata['request_letter']['user_name'])->toBe($expectedRequestingOfficerName)
        ->and($batch->metadata['request_letter']['user_position'])->toBe('Records Officer');
});
