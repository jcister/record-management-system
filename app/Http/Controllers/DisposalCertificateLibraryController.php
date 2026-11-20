<?php

namespace App\Http\Controllers;

use App\Models\DisposalBatch;
use App\Support\DateTimeFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisposalCertificateLibraryController extends Controller
{
    /**
     * @var list<string>
     */
    private const FILTERABLE_STATUSES = [
        DisposalBatch::STATUS_SCHEDULED,
        DisposalBatch::STATUS_COMPLETED,
        DisposalBatch::STATUS_REQUEST_LETTER_READY,
        DisposalBatch::STATUS_INSPECTED,
        DisposalBatch::STATUS_APPROVED,
        DisposalBatch::STATUS_COMPLETE,
        DisposalBatch::STATUS_REJECTED,
    ];

    public function __invoke(Request $request): Response
    {
        $allowedStatusFilters = array_merge(['all'], self::FILTERABLE_STATUSES);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in($allowedStatusFilters)],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = DisposalBatch::query()
            ->with([
                'napForm1GeneratedBy:id,name',
                'napReportGeneratedBy:id,name',
                'requestLetterGeneratedBy:id,name',
                'approvalLetterGeneratedBy:id,name',
            ])
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('nap_form_1_path')
                    ->orWhereNotNull('nap_report_path')
                    ->orWhereNotNull('request_letter_path')
                    ->orWhereNotNull('approval_letter_path');
            })
            ->orderByDesc('created_at');

        $availableStatuses = DisposalBatch::query()
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('nap_form_1_path')
                    ->orWhereNotNull('nap_report_path')
                    ->orWhereNotNull('request_letter_path')
                    ->orWhereNotNull('approval_letter_path');
            })
            ->whereIn('status', self::FILTERABLE_STATUSES)
            ->distinct()
            ->pluck('status')
            ->map(fn (mixed $status): string => (string) $status)
            ->filter(fn (string $status): bool => in_array($status, self::FILTERABLE_STATUSES, true))
            ->values()
            ->all();

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('mode_of_disposal', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $batches = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (DisposalBatch $batch): array {
                $napForm1Exists = $this->fileExists($batch->nap_form_1_path);
                $napExists = $this->fileExists($batch->nap_report_path);
                $requestLetterExists = $this->fileExists($batch->request_letter_path);
                $approvalExists = $this->fileExists($batch->approval_letter_path);

                return [
                    'id' => $batch->id,
                    'reference' => $batch->reference,
                    'status' => $batch->status,
                    'status_label' => DisposalBatch::statusLabel($batch->status),
                    'file_count' => $batch->file_count,
                    'actual_mode_of_disposal_label' => DisposalBatch::modeLabel($batch->actual_mode_of_disposal)
                        ?? DisposalBatch::modeLabel($batch->mode_of_disposal),
                    'created_at' => DateTimeFormatter::toIso8601($batch->created_at),
                    'nap_form_1' => [
                        'has_document' => $batch->nap_form_1_path !== null,
                        'file_exists' => $napForm1Exists,
                        'generated_at' => DateTimeFormatter::toIso8601($batch->nap_form_1_generated_at),
                        'generated_by' => $batch->napForm1GeneratedBy?->only(['id', 'name']),
                        'preview_url' => $napForm1Exists
                            ? route('disposal-batches.nap-form-1.download', ['batch' => $batch->id, 'disposition' => 'inline'])
                            : null,
                        'download_url' => $napForm1Exists
                            ? route('disposal-batches.nap-form-1.download', ['batch' => $batch->id])
                            : null,
                    ],
                    'nap_form_3' => [
                        'has_document' => $batch->nap_report_path !== null,
                        'file_exists' => $napExists,
                        'generated_at' => DateTimeFormatter::toIso8601($batch->nap_report_generated_at),
                        'generated_by' => $batch->napReportGeneratedBy?->only(['id', 'name']),
                        'preview_url' => $napExists
                            ? route('disposal-batches.nap-form-3.download', ['batch' => $batch->id, 'disposition' => 'inline'])
                            : null,
                        'download_url' => $napExists
                            ? route('disposal-batches.nap-form-3.download', ['batch' => $batch->id])
                            : null,
                    ],
                    'request_letter_for_nap' => [
                        'has_document' => $batch->request_letter_path !== null,
                        'file_exists' => $requestLetterExists,
                        'generated_at' => DateTimeFormatter::toIso8601($batch->request_letter_generated_at),
                        'generated_by' => $batch->requestLetterGeneratedBy?->only(['id', 'name']),
                        'preview_url' => $requestLetterExists
                            ? route('disposal-batches.request-letter.download', ['batch' => $batch->id, 'disposition' => 'inline'])
                            : null,
                        'download_url' => $requestLetterExists
                            ? route('disposal-batches.request-letter.download', ['batch' => $batch->id])
                            : null,
                    ],
                    'endorsement_letter_to_coa' => [
                        'has_document' => $batch->approval_letter_path !== null,
                        'file_exists' => $approvalExists,
                        'generated_at' => DateTimeFormatter::toIso8601($batch->approval_letter_generated_at),
                        'generated_by' => $batch->approvalLetterGeneratedBy?->only(['id', 'name']),
                        'preview_url' => $approvalExists
                            ? route('disposal-batches.endorsement-letter.download', ['batch' => $batch->id, 'disposition' => 'inline'])
                            : null,
                        'download_url' => $approvalExists
                            ? route('disposal-batches.endorsement-letter.download', ['batch' => $batch->id])
                            : null,
                    ],
                ];
            });

        return Inertia::render('Disposal/DisposalCertificates', [
            'batches' => $batches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'options' => [
                'status' => $this->statusOptions($availableStatuses, $status),
                'per_page' => [10, 15, 25, 50],
            ],
        ]);
    }

    /**
     * @param  list<string>  $availableStatuses
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(array $availableStatuses, string $currentStatus): array
    {
        $statusSet = collect($availableStatuses);

        if ($currentStatus !== 'all' && in_array($currentStatus, self::FILTERABLE_STATUSES, true) && ! $statusSet->contains($currentStatus)) {
            $statusSet->push($currentStatus);
        }

        $dictionary = [
            DisposalBatch::STATUS_SCHEDULED => 'Scheduled',
            DisposalBatch::STATUS_COMPLETED => 'Verified',
            DisposalBatch::STATUS_REQUEST_LETTER_READY => 'Request Letter Ready',
            DisposalBatch::STATUS_INSPECTED => 'Inspected',
            DisposalBatch::STATUS_APPROVED => 'Approved',
            DisposalBatch::STATUS_COMPLETE => 'Complete',
            DisposalBatch::STATUS_REJECTED => 'Rejected',
        ];

        $orderedStatuses = collect(self::FILTERABLE_STATUSES)
            ->filter(fn (string $status): bool => $statusSet->contains($status))
            ->values()
            ->all();

        $options = [
            ['value' => 'all', 'label' => 'All statuses'],
        ];

        foreach ($orderedStatuses as $status) {
            $options[] = [
                'value' => $status,
                'label' => $dictionary[$status],
            ];
        }

        return $options;
    }

    private function fileExists(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        return file_exists(storage_path('app/'.$relativePath));
    }
}
