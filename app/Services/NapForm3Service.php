<?php

namespace App\Services;

use App\Models\DisposalBatch;
use App\Models\SystemConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;

class NapForm3Service
{
    // DOCX generation has been disabled - only PDF generation is supported
    /*
    public function generateDocx(DisposalBatch $batch, array $data, ?string $timestamp = null): string
    {
        $templatePath = storage_path('templates/nap-form-3-template.docx');

        if (! file_exists($templatePath)) {
            throw new \RuntimeException('NAP Form 3 template not found');
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // Set static placeholders
        $templateProcessor->setValue('agency_name', $data['agency_name'] ?? '');
        $templateProcessor->setValue('address', $data['address'] ?? '');
        $templateProcessor->setValue('date', $data['date'] ?? '');
        $templateProcessor->setValue('telephone_number', $data['telephone_number'] ?? '');
        $templateProcessor->setValue('location_of_records', $data['location_of_records'] ?? '');
        $templateProcessor->setValue('volume_in_cubic_meter', $data['volume_in_cubic_meter'] ?? '');
        $templateProcessor->setValue('prepared_by_name', $data['prepared_by_name'] ?? '');
        $templateProcessor->setValue('prepared_by_position', $data['prepared_by_position'] ?? '');
        $templateProcessor->setValue('certified_by_name', $data['certified_by_name'] ?? '');
        $templateProcessor->setValue('certified_by_designation', $data['certified_by_designation'] ?? '');

        // Clone table row for each file in batch
        $items = $data['items'] ?? [];
        $templateProcessor->cloneRow('grds_rds_item_no', count($items));

        foreach ($items as $index => $item) {
            $rowNumber = $index + 1;
            $templateProcessor->setValue("grds_rds_item_no#{$rowNumber}", $item['grds_rds_item_no'] ?? '');
            $templateProcessor->setValue("record_series_title#{$rowNumber}", $item['record_series_title'] ?? '');
            $templateProcessor->setValue("period_covered#{$rowNumber}", $item['period_covered'] ?? '');
            $templateProcessor->setValue("retention_period#{$rowNumber}", $item['retention_period'] ?? '');
        }

        // Save to storage
        $timestamp = $timestamp ?? now()->format('YmdHis');
        $outputFileName = "nap-form-3-{$batch->reference}-{$timestamp}.docx";
        $outputPath = storage_path("app/public/nap-form-3s/{$outputFileName}");

        // Ensure directory exists
        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $templateProcessor->saveAs($outputPath);

        return "public/nap-form-3s/{$outputFileName}";
    }
    */

    /**
     * Generate NAP Form 3 PDF from data
     *
     * @param  array  $data  User-edited certificate data
     * @param  string|null  $timestamp  Optional timestamp for filename consistency
     * @return string Path to the generated PDF file
     */
    public function generatePdf(DisposalBatch $batch, array $data, ?string $timestamp = null): string
    {
        \Log::info('Starting PDF generation', [
            'batch_id' => $batch->id,
            'batch_reference' => $batch->reference,
        ]);

        try {
            $pdf = Pdf::loadView('pdf.nap-form-3', [
                'batch' => $batch,
                'data' => $data,
            ]);

            $pdf->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
            $canvas->page_text(530, 820, '{PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0, 0, 0]);

            $timestamp = $timestamp ?? now()->format('YmdHisv').'-'.bin2hex(random_bytes(3));
            $outputFileName = "nap-form-3-{$batch->reference}-{$timestamp}.pdf";
            $directoryPath = storage_path('app/public/nap-form-3s');
            $fullPath = $directoryPath.'/'.$outputFileName;

            \Log::info('PDF paths', [
                'directory' => $directoryPath,
                'full_path' => $fullPath,
            ]);

            // Ensure directory exists using native PHP
            if (! is_dir($directoryPath)) {
                \Log::info('Creating directory');
                mkdir($directoryPath, 0755, true);
            }

            $pdfOutput = $dompdf->output();
            \Log::info('PDF generated', ['size' => strlen($pdfOutput)]);

            // Write file directly
            $bytesWritten = file_put_contents($fullPath, $pdfOutput);

            \Log::info('PDF file written', [
                'bytes_written' => $bytesWritten,
                'full_path' => $fullPath,
                'exists' => file_exists($fullPath),
                'readable' => is_readable($fullPath),
            ]);

            // Return relative path for database storage
            return "public/nap-form-3s/{$outputFileName}";
        } catch (\Exception $e) {
            \Log::error('PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Prepare default NAP Form 3 data from batch
     */
    public function prepareDefaultData(DisposalBatch $batch): array
    {
        $batch->load([
            'items.file' => function ($query) {
                $query->withTrashed()->select([
                    'id',
                    'record_id',
                    'file_name',
                    'date_created',
                    'date_released',
                    'retention_duration',
                    'retention_status',
                    'deleted_at',
                ]);
            },
            'scheduledBy:id,name',
        ]);

        // Get system configuration for agency and director information
        $config = SystemConfiguration::getInstance();

        return [
            'agency_name' => $config->agency_name ?? 'Commission on Higher Education Regional Office XI',
            'address' => $config->address ?? 'Loyola Street, Barrio Obrero, Davao City, 8000 Davao del Sur',
            'date' => $batch->completed_at ? \Carbon\Carbon::parse($batch->completed_at)->format('F j, Y') : '',
            'telephone_number' => $config->telephone ?? '',
            'location_of_records' => 'Storage Room, Records Unit, Commission on Higher Education XI',
            'volume_in_cubic_meter' => '',
            'prepared_by_user_id' => $batch->scheduled_by,
            'prepared_by_name' => $batch->scheduledBy?->name ?? '',
            'prepared_by_position' => '',
            'certified_by_name' => $config->regional_director_name ?? 'Maricar R. Casquejo, Ph.D, CESO III',
            'certified_by_designation' => $config->regional_director_designation ?? 'Director IV',
            'items' => $batch->items
                ->filter(function ($item) {
                    return $item->file !== null;
                })
                ->map(function ($item) {
                    $file = $item->file;
                    $dateCreated = $file?->date_created ? \Carbon\Carbon::parse($file->date_created)->format('F j, Y') : '';
                    $dateReleased = $file?->date_released ? \Carbon\Carbon::parse($file->date_released)->format('F j, Y') : '';
                    $periodCovered = '';

                    if ($dateCreated && $dateReleased) {
                        $periodCovered = "{$dateCreated} - {$dateReleased}";
                    } elseif ($dateCreated) {
                        $periodCovered = $dateCreated;
                    }

                    return [
                        'grds_rds_item_no' => $file?->record_id ?? '',
                        'record_series_title' => $file?->file_name ?? '',
                        'period_covered' => $periodCovered,
                        'retention_period' => self::formatRetentionDuration($file?->retention_duration),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Format retention duration for display
     */
    private static function formatRetentionDuration(?string $duration): string
    {
        return match ($duration) {
            'one_year' => '1 Year',
            'ten_years' => '10 Years',
            'perpetual' => 'Perpetual',
            default => '',
        };
    }
}
