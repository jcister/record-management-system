<?php

namespace App\Exports\Reports;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecordsInventoryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithMultipleSheets, WithStyles, WithTitle
{
    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        $filterLabels = [];
        if (! empty($this->filters['department_id'])) {
            $filterLabels['Division ID'] = $this->filters['department_id'];
        }
        if (! empty($this->filters['status'])) {
            $filterLabels['Status'] = ucwords(str_replace('_', ' ', $this->filters['status']));
        }
        if (! empty($this->filters['retention_status'])) {
            $filterLabels['Lifecycle Stage'] = $this->formatRetentionStatusLabel((string) $this->filters['retention_status']);
        }
        if (! empty($this->filters['date_from'])) {
            $filterLabels['Date From'] = $this->filters['date_from'];
        }
        if (! empty($this->filters['date_to'])) {
            $filterLabels['Date To'] = $this->filters['date_to'];
        }
        if (! empty($this->filters['metadata_tag_search'])) {
            $filterLabels['Metadata Tag Search'] = $this->filters['metadata_tag_search'];
        }
        if (! empty($this->filters['physical_location_tag_search'])) {
            $filterLabels['Location Tag Search'] = $this->filters['physical_location_tag_search'];
        }

        return [
            new ReportInfoSheet('Records Inventory', [], $filterLabels),
            $this,
        ];
    }

    public function query(): Builder
    {
        $query = File::query()->with(['department:id,name', 'folder:id,name', 'tags:id,name,type'])->orderBy('file_name');

        if (! empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }
        if (! empty($this->filters['department_id'])) {
            $query->where('department_id', $this->filters['department_id']);
        }
        if (! empty($this->filters['retention_status'])) {
            $bucket = (string) $this->filters['retention_status'];

            if ($bucket === 'active' || $bucket === 'archived') {
                $query->whereIn('retention_status', ['active', 'archived']);
            } else {
                $query->where('retention_status', $bucket);
            }
        }
        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (! empty($this->filters['metadata_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $this->filters['metadata_tags'])->where('tags.type', 'metadata'));
        }
        if (! empty($this->filters['physical_location_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $this->filters['physical_location_tags'])->where('tags.type', 'physical_location'));
        }
        if (! empty($this->filters['metadata_tag_search'])) {
            $term = (string) $this->filters['metadata_tag_search'];

            $query->whereHas('tags', function (Builder $t) use ($term): void {
                $t->where('tags.type', 'metadata')
                    ->where('tags.name', 'like', "%{$term}%");
            });
        }
        if (! empty($this->filters['physical_location_tag_search'])) {
            $term = (string) $this->filters['physical_location_tag_search'];

            $query->whereHas('tags', function (Builder $t) use ($term): void {
                $t->where('tags.type', 'physical_location')
                    ->where('tags.name', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * @param  File  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->record_id,
            $row->file_name,
            $row->author,
            $row->department?->name,
            $row->folder?->name,
            $row->tags->pluck('name')->implode(', '),
            $row->status,
            $this->formatRetentionStatusLabel($row->retention_status),
            $row->retention_duration,
            $row->date_created?->toDateString(),
            $row->date_released?->toDateString(),
            $row->retention_expires_at?->toDateString(),
            $row->is_record ? 'Yes' : 'No',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Record ID',
            'Document Name',
            'Author',
            'Division',
            'Folder',
            'Tags',
            'Status',
            'Lifecycle Stage',
            'Retention Duration',
            'Date Created',
            'Date Released',
            'Retention Deadline',
            'Is Official Record',
        ];
    }

    public function title(): string
    {
        return 'Records Inventory';
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:M1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1e293b');
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(): array
    {
        $records = $this->query()->limit(500)->get();

        $statusCounts = $records->groupBy('retention_status')->map(fn ($group) => $group->count());
        $activeCount = $statusCounts->get('active', 0) + $statusCounts->get('archived', 0);

        return [
            'records' => $records->map(fn (File $f) => [
                'record_id' => $f->record_id,
                'file_name' => $f->file_name,
                'author' => $f->author,
                'department' => $f->department?->name,
                'status' => $f->status,
                'retention_status' => $f->retention_status,
                'date_created' => $f->date_created?->toDateString(),
                'retention_expires_at' => $f->retention_expires_at?->toDateString(),
            ]),
            'summary_stats' => [
                'total' => $records->count(),
                'active' => $activeCount,
                'ready_for_disposal' => $statusCounts->get('ready_for_disposal', 0),
                'disposed' => $statusCounts->get('disposed', 0),
            ],
            'generated_at' => now()->toDateTimeString(),
            'filters' => $this->filters,
        ];
    }

    private function formatRetentionStatusLabel(?string $raw): string
    {
        $value = strtolower((string) $raw);

        return match ($value) {
            'active', 'archived' => 'ACTIVE',
            'ready_for_disposal' => 'READY TO BE DISPOSED',
            'disposed' => 'DISPOSED',
            default => $raw ? ucwords(str_replace('_', ' ', (string) $raw)) : '—',
        };
    }
}
