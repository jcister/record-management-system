<?php

namespace App\Services;

use App\Models\DisposalBatch;
use App\Models\SystemConfiguration;
use App\Support\CoaContactDefaults;
use App\Support\DateTimeFormatter;
use App\Support\PersonNameFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class ApprovalLetterService
{
    private const PHOTO_IMAGES_PER_PAGE = 2;

    private const DEFAULT_NAP_DOCUMENTS = [
        ['id' => 'nap-form-1', 'label' => 'NAP Form 1: Records Inventory and Appraisal'],
        ['id' => 'nap-form-3', 'label' => 'NAP Form 3: Request for Authority to Dispose'],
        ['id' => 'nap-form-5', 'label' => 'NAP Form 5: Authority to Dispose of Records'],
    ];

    /**
     * Get the default NAP documents list with empty file placeholders.
     *
     * @return list<array{id: string, label: string, path: string|null, original_name: string|null, uploaded_at: string|null}>
     */
    public function defaultNapDocuments(): array
    {
        return array_map(
            static fn (array $document): array => [
                'id' => $document['id'],
                'label' => $document['label'],
                'path' => null,
                'original_name' => null,
                'uploaded_at' => null,
            ],
            self::DEFAULT_NAP_DOCUMENTS,
        );
    }

    /**
     * Normalize stored NAP documents for a batch.
     *
     * @return list<array{id: string, label: string, path: string|null, original_name: string|null, uploaded_at: string|null}>
     */
    public function napDocumentsFor(DisposalBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];

        return $this->normalizeNapDocuments($metadata['nap_forms'] ?? null);
    }

    /**
     * Prepare default Endorsement Letter to COA data for the preview form.
     */
    public function prepareDefaultData(DisposalBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];
        $letterMetadata = [];

        if (is_array($metadata)) {
            $letterMetadata = $metadata['approval_letter'] ?? [];
        }

        $nameSource = $letterMetadata['recipient_name'] ?? $batch->coa_name ?? '';
        $parts = PersonNameFormatter::split(
            $letterMetadata['recipient_first_name'] ?? null,
            $letterMetadata['recipient_middle_name'] ?? null,
            $letterMetadata['recipient_last_name'] ?? null,
            $letterMetadata['recipient_extension'] ?? null,
            $nameSource,
        );

        $composedName = PersonNameFormatter::compose($parts['first'], $parts['middle'], $parts['last'], $parts['extension']);
        $defaultActualDisposalDate = $letterMetadata['actual_disposal_date']
            ?? ($batch->completed_at
                ? Carbon::parse($batch->completed_at)->timezone(DateTimeFormatter::timezone())->format('F j, Y')
                : Carbon::now(DateTimeFormatter::timezone())->format('F j, Y'));

        return [
            'letter_reference' => $letterMetadata['reference'] ?? 'ENDORSEMENT-LETTER-COA-'.$batch->reference,
            'letter_date' => $letterMetadata['date'] ?? now()->format('F j, Y'),
            'actual_disposal_date' => $defaultActualDisposalDate,
            'honorific' => $letterMetadata['honorific'] ?? '',
            'recipient_name' => $composedName !== '' ? $composedName : $nameSource,
            'recipient_first_name' => $parts['first'],
            'recipient_middle_name' => $parts['middle'],
            'recipient_last_name' => $parts['last'],
            'recipient_extension' => $parts['extension'],
            'recipient_position' => $batch->coa_position ?? CoaContactDefaults::position(),
            'recipient_designation' => $batch->coa_designation ?? CoaContactDefaults::designation(),
            'recipient_address' => $batch->coa_address ?? '',
        ];
    }

    /**
     * Generate the Endorsement Letter to COA PDF and return the relative storage path.
     */
    public function generatePdf(DisposalBatch $batch, array $data, ?string $timestamp = null, string $directory = 'approval-letters', ?array $napDocumentsOverride = null, ?array $approvalDocumentsOverride = null): string
    {
        $payload = $this->buildPayload($batch, $data, $napDocumentsOverride, $approvalDocumentsOverride);

        $timestamp = $timestamp ?? now()->format('YmdHis');
        $outputFileName = 'endorsement-letter-to-coa-'.$batch->reference.'-'.$timestamp.'.pdf';
        $relativeDirectory = 'public/'.$directory;
        $directoryPath = storage_path('app/'.$relativeDirectory);
        $fullPath = $directoryPath.'/'.$outputFileName;

        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        // 1. Generate the endorsement letter page with DomPDF
        $letterPdf = Pdf::loadView('pdf.approval-letter', $payload);
        $letterPdf->setPaper('a4', 'portrait');
        $letterTempPath = $directoryPath.'/temp-letter-'.$timestamp.'.pdf';
        file_put_contents($letterTempPath, $letterPdf->output());

        // 2. Generate the images documentation page(s) with DomPDF (if images exist)
        $imagesTempPath = null;
        if (! empty($payload['imageDataUris'])) {
            $imagesPdf = Pdf::loadView('pdf.approval-letter-images', $payload);
            $imagesPdf->setPaper('a4', 'portrait');
            $imagesTempPath = $directoryPath.'/temp-images-'.$timestamp.'.pdf';
            file_put_contents($imagesTempPath, $imagesPdf->output());
        }

        // 3. Collect NAP form PDF paths
        $napFormPaths = $payload['napFormPaths'];
        $napPdfs = [];
        foreach ($napFormPaths as $path) {
            if ($path && file_exists($path)) {
                $napPdfs[] = $path;
            }
        }

        // 4. Merge all PDFs using FPDI
        $hasAdditionalPages = $imagesTempPath || ! empty($napPdfs);

        if ($hasAdditionalPages) {
            $merger = new Fpdi;

            // Import endorsement letter pages
            $this->importPdfPages($merger, $letterTempPath);

            // Import each NAP form PDF
            foreach ($napPdfs as $napPdfPath) {
                $this->importPdfPages($merger, $napPdfPath);
            }

            // Import photo documentation pages last
            if ($imagesTempPath) {
                $this->importPdfPages($merger, $imagesTempPath);
            }

            $merger->Output('F', $fullPath);

            // Clean up temp files
            @unlink($letterTempPath);
            if ($imagesTempPath) {
                @unlink($imagesTempPath);
            }
        } else {
            // No additional pages, just rename the letter
            rename($letterTempPath, $fullPath);
        }

        return $relativeDirectory.'/'.$outputFileName;
    }

    public function generatePreviewPdf(DisposalBatch $batch, array $data, ?array $napDocumentsOverride = null, ?array $approvalDocumentsOverride = null): string
    {
        $timestamp = now()->format('YmdHis');

        return $this->generatePdf($batch, $data, $timestamp, 'approval-letter-previews', $napDocumentsOverride, $approvalDocumentsOverride);
    }

    /**
     * Import all pages from a source PDF into the FPDI merger.
     */
    private function importPdfPages(Fpdi $merger, string $sourcePath): void
    {
        $pageCount = $merger->setSourceFile($sourcePath);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $merger->importPage($pageNumber);
            $size = $merger->getTemplateSize($templateId);

            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($templateId);
        }
    }

    /**
     * Build the payload passed to the PDF view.
     */
    public function buildPayload(DisposalBatch $batch, array $data, ?array $napDocumentsOverride = null, ?array $approvalDocumentsOverride = null): array
    {
        $approver = $batch->approvedBy;
        $inspector = $batch->inspectedBy;
        $scheduler = $batch->scheduledBy;
        $inspectorName = $batch->inspector_name ?? $inspector?->name;

        // Get system configuration for director information
        $config = SystemConfiguration::getInstance();

        $documents = $approvalDocumentsOverride ?? ($batch->approval_documents ?? []);

        // Collect image data URIs for embedding into the PDF
        $imageDataUris = $this->collectImageDataUris($documents);
        $imagePages = $this->chunkImagePages($imageDataUris);

        // Collect NAP form PDF paths
        $napDocuments = $napDocumentsOverride !== null
            ? $this->resolveNapDocumentsOverride($batch, $napDocumentsOverride)
            : $this->napDocumentsFor($batch);
        $napFormPaths = $this->collectNapFormPaths($napDocuments);

        return [
            'letter' => $this->buildLetterDetails($data),
            'summary' => [
                'batch_reference' => $batch->reference,
                'file_count' => $batch->file_count,
                'mode' => DisposalBatch::modeLabel($batch->mode_of_disposal) ?? 'Not specified',
                'scheduled_at' => DateTimeFormatter::toDateString($batch->scheduled_at),
                'completed_at' => DateTimeFormatter::toDateString($batch->completed_at),
                'inspected_at' => DateTimeFormatter::toDateString($batch->inspected_at),
                'approved_at' => DateTimeFormatter::toDateString($batch->approved_at),
                'nap_generated_at' => DateTimeFormatter::toDateString($batch->nap_report_generated_at),
                'nap_downloaded_at' => DateTimeFormatter::toDateString($batch->nap_report_downloaded_at),
                'actual_disposal_date' => $data['actual_disposal_date'] ?? null,
            ],
            'people' => [
                'approver' => $approver ? [
                    'name' => $approver->name,
                    'email' => $approver->email,
                    'position' => $approver->position?->name,
                ] : null,
                'inspector' => $inspectorName ? [
                    'name' => $inspectorName,
                    'email' => $inspector?->email,
                    'position' => $inspector?->position?->name,
                ] : null,
                'scheduler' => $scheduler ? [
                    'name' => $scheduler->name,
                    'email' => $scheduler->email,
                    'position' => $scheduler->position?->name,
                ] : null,
            ],
            'director' => [
                'name' => $config->regional_director_name ?? 'MARICAR R. CASQUEJO, Ph.D., CESO III',
                'designation' => $config->regional_director_designation ?? 'Director IV',
            ],
            'attachments' => collect($documents)->map(function ($document) {
                return [
                    'name' => $document['name'] ?? 'Attachment',
                    'uploaded_at' => $document['uploaded_at'] ?? null,
                    'mime_type' => $document['mime_type'] ?? null,
                ];
            })->values()->all(),
            'attachmentSummary' => $this->buildAttachmentSummary($batch, $documents, $napDocuments, $napFormPaths, $data),
            'imageDataUris' => $imageDataUris,
            'imagePages' => $imagePages,
            'napFormPaths' => $napFormPaths,
            'napDocuments' => $napDocuments,
        ];
    }

    /**
     * Collect base64-encoded data URIs for approval document images.
     *
     * @return list<array{data_uri: string, name: string, width: int, height: int}>
     */
    private function collectImageDataUris(array $documents): array
    {
        $dataUris = [];

        foreach ($documents as $document) {
            $path = $document['path'] ?? null;
            $mimeType = $document['mime_type'] ?? null;

            if (! $path || ! $mimeType || ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $relativePath = preg_replace('#^public/#', '', $path);
            $absolutePath = Storage::disk('public')->path($relativePath);

            if (! file_exists($absolutePath)) {
                continue;
            }

            $imageData = file_get_contents($absolutePath);
            if ($imageData === false) {
                continue;
            }

            $imageSize = getimagesize($absolutePath);
            $width = $imageSize ? $imageSize[0] : 0;
            $height = $imageSize ? $imageSize[1] : 0;

            $dataUris[] = [
                'data_uri' => 'data:'.$mimeType.';base64,'.base64_encode($imageData),
                'name' => $document['name'] ?? 'Image',
                'width' => $width,
                'height' => $height,
            ];
        }

        return $dataUris;
    }

    /**
     * @param  list<array{data_uri: string, name: string, width: int, height: int}>  $images
     * @return list<list<array{data_uri: string, name: string, width: int, height: int}>>
     */
    private function chunkImagePages(array $images): array
    {
        if ($images === []) {
            return [];
        }

        $chunks = array_chunk($images, self::PHOTO_IMAGES_PER_PAGE);

        return array_values($chunks);
    }

    /**
     * Normalize stored nap form metadata into a predictable structure.
     *
     * @return list<array{id: string, label: string, path: string|null, original_name: string|null, uploaded_at: string|null}>
     */
    private function normalizeNapDocuments(mixed $napForms): array
    {
        if (! is_array($napForms) || $napForms === []) {
            return $this->defaultNapDocuments();
        }

        if (array_is_list($napForms)) {
            $documents = [];
            foreach ($napForms as $index => $document) {
                if (! is_array($document)) {
                    continue;
                }

                $label = trim((string) ($document['label'] ?? ''));
                $documents[] = [
                    'id' => is_string($document['id'] ?? null) && $document['id'] !== ''
                        ? $document['id']
                        : 'nap-doc-'.$index,
                    'label' => $label !== '' ? $label : 'Supporting Document',
                    'path' => $document['path'] ?? $document['existing_path'] ?? null,
                    'original_name' => $document['original_name'] ?? null,
                    'uploaded_at' => $document['uploaded_at'] ?? null,
                ];
            }

            return array_values($documents);
        }

        $documents = [];
        foreach ($napForms as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $documents[] = [
                'id' => $this->normalizeLegacyNapId($key),
                'label' => $this->legacyNapLabel($key),
                'path' => is_string($value) ? $value : null,
                'original_name' => null,
                'uploaded_at' => null,
            ];
        }

        return array_values($documents) ?: $this->defaultNapDocuments();
    }

    /**
     * Prefer request-provided nap documents while falling back to stored metadata.
     *
     * @return list<array{id: string, label: string, path: string|null, original_name: string|null, uploaded_at: string|null}>
     */
    private function resolveNapDocumentsOverride(DisposalBatch $batch, mixed $override): array
    {
        if (! is_array($override) || $override === []) {
            return $this->napDocumentsFor($batch);
        }

        $documents = [];

        foreach ($override as $index => $document) {
            if (! is_array($document)) {
                continue;
            }

            $id = is_string($document['id'] ?? null) && $document['id'] !== ''
                ? $document['id']
                : 'nap-doc-'.$index;

            $label = trim((string) ($document['label'] ?? ''));

            $documents[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : 'Supporting Document',
                'path' => $document['path'] ?? $document['existing_path'] ?? null,
                'original_name' => $document['original_name'] ?? null,
                'uploaded_at' => $document['uploaded_at'] ?? null,
            ];
        }

        if ($documents === []) {
            return $this->napDocumentsFor($batch);
        }

        return array_values($documents);
    }

    private function normalizeLegacyNapId(string $key): string
    {
        $normalized = str_replace('_', '-', strtolower($key));

        return str_starts_with($normalized, 'nap-form-') ? $normalized : 'nap-form-'.$normalized;
    }

    private function legacyNapLabel(string $key): string
    {
        return match ($key) {
            'nap_form_1', 'nap-form-1' => 'NAP Form 1: Records Inventory and Appraisal',
            'nap_form_3', 'nap-form-3' => 'NAP Form 3: Request for Authority to Dispose',
            'nap_form_5', 'nap-form-5' => 'NAP Form 5: Authority to Dispose of Records',
            default => Str::headline(str_replace(['nap_', 'nap-form-'], 'NAP Form ', $key)),
        };
    }

    /**
     * Collect NAP form PDF absolute file paths stored on the batch metadata.
     *
     * @return array<string, string|null>
     */
    private function collectNapFormPaths(array $napDocuments): array
    {
        $paths = [];

        foreach ($napDocuments as $document) {
            if (! isset($document['id'])) {
                continue;
            }

            $storedPath = $document['path'] ?? null;
            if (! is_string($storedPath) || $storedPath === '') {
                $paths[$document['id']] = null;

                continue;
            }

            $relativePath = preg_replace('#^public/#', '', $storedPath);
            if ($relativePath && Storage::disk('public')->exists($relativePath)) {
                $paths[$document['id']] = Storage::disk('public')->path($relativePath);
            } else {
                $paths[$document['id']] = null;
            }
        }

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLetterDetails(array $data): array
    {
        $parts = PersonNameFormatter::split(
            $data['recipient_first_name'] ?? null,
            $data['recipient_middle_name'] ?? null,
            $data['recipient_last_name'] ?? null,
            $data['recipient_extension'] ?? null,
            $data['recipient_name'] ?? null,
        );

        $composedName = PersonNameFormatter::compose($parts['first'], $parts['middle'], $parts['last'], $parts['extension']);

        return [
            'reference' => $data['letter_reference'] ?? null,
            'date' => $data['letter_date'],
            'honorific' => $data['honorific'] ?? '',
            'recipient_name' => $composedName !== '' ? $composedName : ($data['recipient_name'] ?? ''),
            'recipient_first_name' => $parts['first'],
            'recipient_middle_name' => $parts['middle'],
            'recipient_last_name' => $parts['last'],
            'recipient_extension' => $parts['extension'],
            'recipient_position' => $data['recipient_position'] ?? null,
            'recipient_designation' => $data['recipient_designation'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? null,
            'notes' => trim((string) ($data['approval_notes'] ?? '')),
        ];
    }

    /**
     * @return list<array{label: string, key: string, present: bool, children?: list<array{label: string, key: string, present: bool}>}>
     */
    private function buildAttachmentSummary(DisposalBatch $batch, array $documents, array $napDocuments, array $napFormPaths, array $letterData): array
    {
        $hasPictures = collect($documents)->contains(function ($document) {
            $mimeType = $document['mime_type'] ?? null;

            return is_string($mimeType) && str_starts_with($mimeType, 'image/');
        });

        $summary = [];

        foreach ($napDocuments as $document) {
            $id = is_string($document['id'] ?? null) ? $document['id'] : Str::uuid()->toString();
            $label = trim((string) ($document['label'] ?? ''));
            if ($label === '') {
                $label = 'Supporting Document';
            }

            $summary[] = [
                'label' => $label,
                'key' => $id,
                'present' => ! empty($napFormPaths[$id]),
            ];
        }

        $summary[] = [
            'label' => 'Photo Documentations',
            'key' => 'pictures',
            'present' => $hasPictures,
            'children' => [
                [
                    'label' => 'Actual Inspection',
                    'key' => 'actual_inspection',
                    'present' => (bool) $batch->inspected_at,
                ],
                [
                    'label' => 'Actual Disposal',
                    'key' => 'actual_disposal',
                    'present' => ! empty($letterData['actual_disposal_date']),
                ],
            ],
        ];

        return $summary;
    }
}
