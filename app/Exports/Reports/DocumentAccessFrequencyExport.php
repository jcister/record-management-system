<?php

namespace App\Exports\Reports;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentAccessFrequencyExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMultipleSheets, WithStyles, WithTitle
{
    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        $filterLabels = [];
        if (! empty($this->filters['top_n'])) {
            $filterLabels['Top Results'] = 'Top '.$this->filters['top_n'];
        }
        if (! empty($this->filters['department_id'])) {
            $filterLabels['Division ID'] = $this->filters['department_id'];
        }

        return [
            new ReportInfoSheet('Document Access Frequency Report', [], $filterLabels),
            $this,
        ];
    }

    public function collection(): Collection
    {
        $topN = (int) ($this->filters['top_n'] ?? 20);

        return File::query()
            ->with(['department:id,name'])
            ->when(! empty($this->filters['department_id']), fn ($q) => $q->where('department_id', $this->filters['department_id']))
            ->when(! empty($this->filters['metadata_tags']), fn ($q) => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $this->filters['metadata_tags'])->where('tags.type', 'metadata')))
            ->when(! empty($this->filters['physical_location_tags']), fn ($q) => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $this->filters['physical_location_tags'])->where('tags.type', 'physical_location')))
            ->orderByDesc('download_count')
            ->limit($topN)
            ->get()
            ->map(fn (File $f) => [
                $f->record_id,
                $f->file_name,
                $f->department?->name,
                $f->download_count ?? 0,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Record ID', 'Document Name', 'Division', 'Times Opened'];
    }

    public function title(): string
    {
        return 'Document Access Frequency';
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1e293b');
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(): array
    {
        $rows = $this->collection()->toArray();
        $totalDownloads = array_sum(array_column($rows, 3));
        $maxDownloads = $rows ? max(array_column($rows, 3)) : 0;

        return [
            'rows' => $rows,
            'summary_stats' => [
                'total_files' => count($rows),
                'total_downloads' => $totalDownloads,
                'max_downloads' => $maxDownloads,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
