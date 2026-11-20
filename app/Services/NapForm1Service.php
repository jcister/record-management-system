<?php

namespace App\Services;

use App\Models\DisposalBatch;
use App\Models\SystemConfiguration;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class NapForm1Service
{
    public function generatePdf(DisposalBatch $batch, array $data, ?string $timestamp = null): string
    {
        $pdf = Pdf::loadView('pdf.nap-form-1', [
            'batch' => $batch,
            'data' => $data,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_text(
            $canvas->get_width() - 80,
            $canvas->get_height() - 18,
            '{PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0, 0, 0]
        );

        $timestamp = $timestamp ?? now()->format('YmdHisv').'-'.bin2hex(random_bytes(3));
        $outputFileName = "nap-form-1-{$batch->reference}-{$timestamp}.pdf";
        $directoryPath = storage_path('app/public/nap-form-1s');
        $fullPath = $directoryPath.'/'.$outputFileName;

        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        file_put_contents($fullPath, $dompdf->output());

        return "public/nap-form-1s/{$outputFileName}";
    }

    public function prepareDefaultData(DisposalBatch $batch, User $currentUser): array
    {
        $batch->load([
            'items.file' => function ($query) {
                $query->withTrashed()->select([
                    'id',
                    'record_id',
                    'file_name',
                    'date_created',
                    'date_released',
                    'physical_location',
                    'retention_duration',
                    'deleted_at',
                ]);
            },
        ]);

        $currentUser->loadMissing(['position:id,name', 'roles:id,name']);

        $config = SystemConfiguration::getInstance();

        $locationFromTags = $batch->items
            ->flatMap(function ($item) {
                $snapshot = $item->physical_snapshot;

                if (! is_array($snapshot)) {
                    return [];
                }

                $tags = $snapshot['tags'] ?? [];

                return collect($tags)
                    ->pluck('name')
                    ->filter()
                    ->values();
            })
            ->unique()
            ->values()
            ->implode(', ');

        $items = $batch->items
            ->filter(fn ($item) => $item->file !== null)
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

                $snapshot = $item->physical_snapshot;
                $location = $file?->physical_location ?? '';

                if (is_array($snapshot)) {
                    $tagNames = collect($snapshot['tags'] ?? [])->pluck('name')->filter()->values()->implode(', ');
                    if ($tagNames !== '') {
                        $location = $tagNames;
                    }
                }

                $durationLabel = match ($file?->retention_duration) {
                    'one_year' => '1 year',
                    'ten_years' => '10 years',
                    'perpetual' => 'Perpetual',
                    default => $file?->retention_duration ?? '',
                };

                return [
                    'title' => $file?->file_name ?? '',
                    'period' => $periodCovered,
                    'volume' => '',
                    'location' => $location,
                    'frequency' => '',
                    'duplication' => '',
                    'time_value' => '',
                    'utility_value' => '',
                    'retention_active' => '',
                    'retention_storage' => '',
                    'retention_total' => $durationLabel,
                    'provision' => '',
                ];
            })
            ->values()
            ->all();

        return [
            'agency' => $config->agency_name ?? 'Commission on Higher Education Regional Office XI',
            'organizational_unit' => 'ADMIN DIV – RECORDS UNIT',
            'telephone' => $config->telephone ?? '',
            'address' => $config->address ?? 'Loyola Street, Barrio Obrero, Davao City, 8000 Davao del Sur',
            'person_in_charge' => $currentUser->position?->name ?? '',
            'date_prepared' => now()->format('F j, Y'),
            'prepared_by_name' => $currentUser->name,
            'prepared_by_position' => $currentUser->position?->name ?? '',
            'prepared_by_role' => $currentUser->roles->pluck('name')->filter()->values()->implode(', '),
            'assisted_by_name' => '',
            'assisted_by_designation' => '',
            'assisted_by_role' => '',
            'approved_by_name' => '',
            'approved_by_designation' => '',
            'approved_by_role' => '',
            'volume_in_cubic_meter' => '',
            'location_of_records' => $locationFromTags,
            'frequency_of_use' => '',
            'time_value' => '',
            'utility_value' => '',
            'retention_period' => '',
            'disposition_provision' => '',
            'items' => $items,
        ];
    }
}
