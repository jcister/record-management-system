<?php

use App\Models\DisposalBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows disposal certificates page with only batches that have generated pdf paths', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $withNap = DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-NAP-001',
        'status' => DisposalBatch::STATUS_COMPLETED,
        'mode_of_disposal' => DisposalBatch::MODE_LANDFILL,
        'actual_mode_of_disposal' => DisposalBatch::MODE_SALE,
        'nap_report_path' => 'public/nap-form-3s/test-nap-001.pdf',
    ]);

    $withApproval = DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-APP-001',
        'status' => DisposalBatch::STATUS_APPROVED,
        'approval_letter_path' => 'public/approval-letters/test-approval-001.pdf',
    ]);

    $withNapForm1 = DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-NAP1-001',
        'status' => DisposalBatch::STATUS_REQUEST_LETTER_READY,
        'nap_form_1_path' => 'public/nap-form-1s/test-nap-1-001.pdf',
    ]);

    $withRequestLetter = DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-REQ-001',
        'status' => DisposalBatch::STATUS_COMPLETE,
        'request_letter_path' => 'public/request-letters/test-request-letter-001.pdf',
    ]);

    DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-EMPTY-001',
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'nap_report_path' => null,
        'approval_letter_path' => null,
    ]);

    actingAs($user)
        ->get('/disposal-management/certificates')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Disposal/DisposalCertificates')
            ->where('filters.status', 'all')
            ->where('batches.data', function ($rows) use ($withNap, $withApproval): bool {
                $references = collect($rows)->pluck('reference')->all();

                return in_array($withNap->reference, $references, true)
                    && in_array($withApproval->reference, $references, true)
                    && in_array('DB-TEST-NAP1-001', $references, true)
                    && in_array('DB-TEST-REQ-001', $references, true)
                    && ! in_array('DB-TEST-EMPTY-001', $references, true);
            })
            ->where('batches.data', function ($rows) use ($withNap): bool {
                $napRow = collect($rows)->firstWhere('reference', $withNap->reference);

                return is_array($napRow)
                    && ($napRow['actual_mode_of_disposal_label'] ?? null) === 'Sale';
            })
        );
});

it('filters disposal certificates by status', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-NAP-ONLY',
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test-completed.pdf',
        'approval_letter_path' => null,
    ]);

    DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-APPROVED',
        'status' => DisposalBatch::STATUS_APPROVED,
        'nap_report_path' => null,
        'approval_letter_path' => 'public/approval-letters/test-approved.pdf',
    ]);

    actingAs($user)
        ->get('/disposal-management/certificates?status=approved')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Disposal/DisposalCertificates')
            ->where('filters.status', 'approved')
            ->where('batches.data', function ($rows): bool {
                $references = collect($rows)->pluck('reference')->all();

                return ! in_array('DB-TEST-NAP-ONLY', $references, true)
                    && in_array('DB-TEST-APPROVED', $references, true);
            })
        );
});

it('builds status filter options from statuses present in the certificate repository', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-STATUS-VERIFIED',
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_report_path' => 'public/nap-form-3s/test-status-verified.pdf',
    ]);

    DisposalBatch::factory()->create([
        'reference' => 'DB-TEST-STATUS-COMPLETE',
        'status' => DisposalBatch::STATUS_COMPLETE,
        'request_letter_path' => 'public/request-letters/test-status-complete.pdf',
    ]);

    actingAs($user)
        ->get('/disposal-management/certificates')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Disposal/DisposalCertificates')
            ->where('options.status', function ($options): bool {
                $values = collect($options)->pluck('value')->all();

                return in_array('all', $values, true)
                    && in_array('completed', $values, true)
                    && in_array('complete', $values, true)
                    && ! in_array('inspected', $values, true);
            })
        );
});
