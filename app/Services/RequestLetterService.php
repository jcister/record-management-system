<?php

namespace App\Services;

use App\Models\DisposalBatch;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Support\PersonNameFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class RequestLetterService
{
    public function formatRequestingOfficerName(?string $rawName): string
    {
        $value = trim((string) $rawName);

        if ($value === '') {
            return '';
        }

        [$namePortion, $extension] = array_pad(array_map('trim', explode(',', $value, 2)), 2, '');
        $tokens = array_values(array_filter(preg_split('/\s+/', $namePortion) ?: []));

        if ($tokens === []) {
            return $extension !== '' ? ', '.$extension : '';
        }

        $supportedPrefixes = ['mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'dr', 'dr.', 'engr', 'engr.', 'atty', 'atty.', 'prof', 'prof.', 'hon', 'hon.'];
        $hasPrefix = in_array(mb_strtolower($tokens[0]), $supportedPrefixes, true);

        $prefix = '';
        $nameTokens = $tokens;

        if ($hasPrefix) {
            $prefix = array_shift($nameTokens) ?? '';
        }

        $firstName = '';
        $middleName = '';
        $lastName = '';

        if (count($nameTokens) >= 3) {
            $lastName = $nameTokens[count($nameTokens) - 1];
            $middleName = $nameTokens[count($nameTokens) - 2];
            $firstName = implode(' ', array_slice($nameTokens, 0, -2));
        } elseif (count($nameTokens) === 2) {
            [$firstName, $lastName] = $nameTokens;
        } elseif (count($nameTokens) === 1) {
            $firstName = $nameTokens[0];
        }

        $middleInitial = '';
        $trimmedMiddle = trim($middleName);
        if ($trimmedMiddle !== '') {
            $middleInitial = mb_strtoupper(mb_substr($trimmedMiddle, 0, 1)).'.';
        }

        $coreName = trim(implode(' ', array_filter([
            $prefix,
            $firstName,
            $middleInitial,
            $lastName,
        ], fn (string $segment): bool => $segment !== '')));

        if ($coreName === '') {
            $coreName = $value;
        }

        if ($extension === '') {
            return $coreName;
        }

        $suffix = ', '.$extension;

        if (str_ends_with(strtolower($coreName), strtolower($suffix))) {
            return $coreName;
        }

        return $coreName.$suffix;
    }

    /**
     * Prepare default Request Letter data for the preview form.
     */
    public function prepareDefaultData(DisposalBatch $batch, ?array $authUser = null): array
    {
        $metadata = $batch->metadata ?? [];
        $letterMetadata = is_array($metadata) ? ($metadata['request_letter'] ?? []) : [];
        $napForm1Data = is_array($metadata) ? ($metadata['nap_form_1'] ?? []) : [];

        $parts = PersonNameFormatter::split(
            $letterMetadata['recipient_first_name'] ?? null,
            $letterMetadata['recipient_middle_name'] ?? null,
            $letterMetadata['recipient_last_name'] ?? null,
            $letterMetadata['recipient_extension'] ?? null,
            $letterMetadata['recipient_name'] ?? null,
        );

        $napPreparedByName = $napForm1Data['prepared_by_name'] ?? null;
        $napPreparedByPosition = $napForm1Data['prepared_by_position'] ?? null;
        $authName = $authUser['name'] ?? '';
        $authPosition = $authUser['position'] ?? '';

        $preparedByUserId = $napForm1Data['prepared_by_user_id'] ?? null;
        $preparedByUser = null;

        if (is_string($preparedByUserId) && $preparedByUserId !== '') {
            $preparedByUser = User::query()
                ->with('position:id,name')
                ->select(['id', 'name', 'position_id'])
                ->find($preparedByUserId);
        }

        $requestingOfficerName = $this->formatRequestingOfficerName($preparedByUser?->name
            ?? $napPreparedByName
            ?? ($letterMetadata['user_name'] ?? $authName));
        $requestingOfficerPosition = $preparedByUser?->position?->name
            ?? $napPreparedByPosition
            ?? ($letterMetadata['user_position'] ?? $authPosition);

        return [
            'letter_date' => $letterMetadata['letter_date'] ?? now()->format('F j, Y'),
            'recipient_name' => $letterMetadata['recipient_name']
                ?? PersonNameFormatter::compose($parts['first'], $parts['middle'], $parts['last'], $parts['extension']),
            'recipient_first_name' => $parts['first'],
            'recipient_middle_name' => $parts['middle'],
            'recipient_last_name' => $parts['last'],
            'recipient_extension' => $parts['extension'],
            'recipient_position' => $letterMetadata['recipient_position'] ?? '',
            'recipient_designation' => $letterMetadata['recipient_designation'] ?? '',
            'address_1' => $letterMetadata['address_1'] ?? '',
            'address_2' => $letterMetadata['address_2'] ?? '',
            'telephone' => $letterMetadata['telephone'] ?? '',
            'honorific' => $letterMetadata['honorific'] ?? '',
            'user_honorific' => $letterMetadata['user_honorific'] ?? 'Ms.',
            'user_name' => $requestingOfficerName,
            'user_position' => $requestingOfficerPosition,
            'control_id' => $letterMetadata['control_id'] ?? '',
        ];
    }

    /**
     * Generate the Request Letter PDF, append the persisted NAP Form 3 PDF, and
     * return the relative storage path.
     */
    public function generatePdf(
        DisposalBatch $batch,
        array $letterData,
        string $napForm3Path,
        ?string $napForm3AbsolutePath = null,
    ): string {
        $this->ensureFpdfIsAvailable();

        $letterPayload = $this->buildLetterPayload($batch, $letterData);

        $timestamp = now()->format('YmdHisv').'-'.bin2hex(random_bytes(3));
        $outputFileName = "request-letter-{$batch->reference}-{$timestamp}.pdf";
        $directoryPath = storage_path('app/public/request-letters');
        $fullPath = $directoryPath.'/'.$outputFileName;

        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $letterPdf = Pdf::loadView('pdf.request-letter', $letterPayload);
        $letterPdf->setPaper('a4', 'portrait');

        $letterTempPath = $directoryPath.'/temp-request-letter-'.$timestamp.'.pdf';
        file_put_contents($letterTempPath, $letterPdf->output());

        $napFormAbsolutePath = $napForm3AbsolutePath && file_exists($napForm3AbsolutePath)
            ? $napForm3AbsolutePath
            : $this->resolveNapFormPath($napForm3Path);

        if (! $napFormAbsolutePath || ! file_exists($napFormAbsolutePath)) {
            throw new \RuntimeException('NAP Form 3 PDF not found for merging.');
        }

        $merger = new Fpdi;

        $this->importPdfPages($merger, $letterTempPath);
        $this->importPdfPages($merger, $napFormAbsolutePath);

        $merger->Output('F', $fullPath);
        @unlink($letterTempPath);

        return "public/request-letters/{$outputFileName}";
    }

    /**
     * Load FPDF only when needed so artisan commands that do not generate PDFs
     * are not coupled to the optional setasign/fpdf package path.
     */
    private function ensureFpdfIsAvailable(): void
    {
        if (class_exists('FPDF')) {
            return;
        }

        $possiblePaths = [
            base_path('vendor/setasign/fpdf/fpdf.php'),
            base_path('vendor/setasign/fpdf/src/Fpdf/Fpdf.php'),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                require_once $path;

                if (class_exists('FPDF')) {
                    return;
                }
            }
        }

        throw new \RuntimeException('FPDF class is not available. Install setasign/fpdf to enable Request Letter PDF merging.');
    }

    /**
     * Import all pages from the source PDF into the FPDI merger instance.
     */
    private function importPdfPages(Fpdi $merger, string $sourcePath): void
    {
        $pageCount = $merger->setSourceFile($sourcePath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $merger->importPage($page);
            $size = $merger->getTemplateSize($templateId);

            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($templateId);
        }
    }

    private function resolveNapFormPath(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        $relativePath = preg_replace('#^public/#', '', $storedPath);
        if (! $relativePath) {
            return null;
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('public')->path($relativePath);
    }

    /**
     * Build the payload passed to the PDF view for the request letter.
     *
     * @return array{letter: array<string, mixed>, director: array{name: string, designation: string}}
     */
    public function buildLetterPayload(DisposalBatch $batch, array $data): array
    {
        $config = SystemConfiguration::getInstance();
        $composedName = PersonNameFormatter::compose(
            $data['recipient_first_name'] ?? '',
            $data['recipient_middle_name'] ?? '',
            $data['recipient_last_name'] ?? '',
            $data['recipient_extension'] ?? '',
        );

        return [
            'letter' => [
                'date' => $data['letter_date'] ?? now()->format('F j, Y'),
                'recipient_name' => $data['recipient_name'] ?? $composedName,
                'recipient_position' => $data['recipient_position'] ?? '',
                'recipient_designation' => $data['recipient_designation'] ?? '',
                'recipient_first_name' => $data['recipient_first_name'] ?? '',
                'recipient_middle_name' => $data['recipient_middle_name'] ?? '',
                'recipient_last_name' => $data['recipient_last_name'] ?? '',
                'recipient_extension' => $data['recipient_extension'] ?? '',
                'address_1' => $data['address_1'] ?? '',
                'address_2' => $data['address_2'] ?? '',
                'telephone' => $data['telephone'] ?? '',
                'honorific' => $data['honorific'] ?? '',
                'user_honorific' => $data['user_honorific'] ?? '',
                'user_name' => $data['user_name'] ?? '',
                'user_position' => $data['user_position'] ?? '',
                'control_id' => $data['control_id'] ?? '',
            ],
            'director' => [
                'name' => $config->regional_director_name ?? 'MARICAR R. CASQUEJO, Ph.D., CESO III',
                'designation' => $config->regional_director_designation ?? 'Director IV',
            ],
        ];
    }

    public function composeRecipientName(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        ?string $extension
    ): string {
        return PersonNameFormatter::compose(
            $firstName ?? '',
            $middleName ?? '',
            $lastName ?? '',
            $extension ?? '',
        );
    }
}
