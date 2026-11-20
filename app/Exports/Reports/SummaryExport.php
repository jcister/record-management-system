<?php

namespace App\Exports\Reports;

use App\Models\File;
use App\Models\Requests;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummaryExport implements FromCollection, ShouldAutoSize, WithMultipleSheets, WithStyles, WithTitle
{
    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        $periodFrom = ! empty($this->filters['period_from']) ? Carbon::parse($this->filters['period_from']) : now()->startOfMonth();
        $periodTo = ! empty($this->filters['period_to']) ? Carbon::parse($this->filters['period_to']) : now();

        $info = [
            'Period' => $periodFrom->toDateString().' to '.$periodTo->toDateString(),
        ];

        $filters = [];
        if (! empty($this->filters['period_from'])) {
            $filters['Period From'] = $this->filters['period_from'];
        }
        if (! empty($this->filters['period_to'])) {
            $filters['Period To'] = $this->filters['period_to'];
        }

        return [
            new ReportInfoSheet('Summary Report', $info, $filters),
            $this,
        ];
    }

    public function collection(): Collection
    {
        $periodFrom = ! empty($this->filters['period_from']) ? Carbon::parse($this->filters['period_from']) : now()->startOfMonth();
        $periodTo = ! empty($this->filters['period_to']) ? Carbon::parse($this->filters['period_to']) : now();

        $activeCount = File::query()
            ->whereIn('retention_status', ['active', 'archived'])
            ->count();

        $readyForDisposalCount = File::query()
            ->where('retention_status', 'ready_for_disposal')
            ->count();

        $disposedCount = File::query()
            ->where('retention_status', 'disposed')
            ->count();

        return collect([
            ['Section', 'Metric', 'Value'],
            ['', '', ''],
            ['At a Glance', 'Total Documents', File::query()->count()],
            ['', 'Official Records', File::query()->where('is_record', true)->count()],
            ['', '', ''],
            ['What Happened This Period', 'Period', $periodFrom->toDateString().' → '.$periodTo->toDateString()],
            ['', 'Documents Added', File::query()->whereBetween('created_at', [$periodFrom, $periodTo])->count()],
            ['', 'Documents Removed', File::query()->where('retention_status', 'disposed')->whereBetween('updated_at', [$periodFrom, $periodTo])->count()],
            ['', 'Retrieval Requests', Requests::query()->whereBetween('created_at', [$periodFrom, $periodTo])->count()],
            ['', 'Requests Fulfilled', Requests::query()->where('status', 'approved')->whereBetween('updated_at', [$periodFrom, $periodTo])->count()],
            ['', '', ''],
            ['Records Health', 'ACTIVE', $activeCount],
            ['', 'READY TO BE DISPOSED', $readyForDisposalCount],
            ['', 'DISPOSED', $disposedCount],
        ]);
    }

    public function title(): string
    {
        return 'Summary Data';
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A:A')->getFont()->setBold(true);

        // Highlight section header rows
        $lastRow = $sheet->getHighestRow();
        $sections = ['At a Glance', 'What Happened This Period', 'Records Health'];
        for ($i = 1; $i <= $lastRow; $i++) {
            $val = $sheet->getCell("A{$i}")->getValue();
            if (in_array($val, $sections)) {
                $sheet->getStyle("A{$i}:C{$i}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF1e293b');
                $sheet->getStyle("A{$i}:C{$i}")->getFont()
                    ->setBold(true)
                    ->getColor()->setARGB('FFFFFFFF');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(): array
    {
        $periodFrom = ! empty($this->filters['period_from']) ? Carbon::parse($this->filters['period_from']) : now()->startOfMonth();
        $periodTo = ! empty($this->filters['period_to']) ? Carbon::parse($this->filters['period_to']) : now();

        $activeCount = File::query()
            ->whereIn('retention_status', ['active', 'archived'])
            ->count();

        $readyForDisposalCount = File::query()
            ->where('retention_status', 'ready_for_disposal')
            ->count();

        $disposedCount = File::query()
            ->where('retention_status', 'disposed')
            ->count();

        $byDepartment = File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, COUNT(*) as count')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
            ->toArray();

        return [
            'total_files' => File::query()->count(),
            'total_records' => File::query()->where('is_record', true)->count(),
            'period_from' => $periodFrom->toDateString(),
            'period_to' => $periodTo->toDateString(),
            'added_in_period' => File::query()->whereBetween('created_at', [$periodFrom, $periodTo])->count(),
            'disposed_in_period' => File::query()->where('retention_status', 'disposed')->whereBetween('updated_at', [$periodFrom, $periodTo])->count(),
            'requests_in_period' => Requests::query()->whereBetween('created_at', [$periodFrom, $periodTo])->count(),
            'active' => $activeCount,
            'ready_for_disposal' => $readyForDisposalCount,
            'disposed' => $disposedCount,
            'by_department' => $byDepartment,
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
