<?php

namespace App\Exports\Reports;

use App\Models\File;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StorageUtilizationExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly array $filters = []) {}

    public function collection(): Collection
    {
        $groupBy = $this->filters['group_by'] ?? 'department';

        if ($groupBy === 'folder') {
            return File::query()
                ->join('folders as current_folders', 'files.folder_id', '=', 'current_folders.id')
                ->leftJoin('folders as parent_folders', 'current_folders.parent_id', '=', 'parent_folders.id')
                ->selectRaw('current_folders.id, current_folders.name as folder_name, parent_folders.name as parent_name, SUM(files.file_size) as total_size, COUNT(*) as file_count')
                ->whereNotNull('files.folder_id')
                ->when(! empty($this->filters['department_id']), fn ($q) => $q->where('files.department_id', $this->filters['department_id']))
                ->groupBy('current_folders.id', 'current_folders.name', 'parent_folders.name')
                ->orderByDesc('total_size')
                ->get()
                ->map(fn ($r) => [
                    $r->parent_name ? $r->parent_name.' / '.$r->folder_name : $r->folder_name,
                    $this->formatBytes((int) $r->total_size),
                    (int) $r->file_count,
                ]);
        }

        return File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, SUM(files.file_size) as total_size, COUNT(*) as file_count')
            ->when(! empty($this->filters['folder_id']), fn ($q) => $q->where('files.folder_id', $this->filters['folder_id']))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_size')
            ->get()
            ->map(fn ($r) => [
                $r->label,
                $this->formatBytes((int) $r->total_size),
                (int) $r->file_count,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $label = ($this->filters['group_by'] ?? 'department') === 'folder' ? 'Folder' : 'Department';

        return [$label, 'Total Size', 'File Count'];
    }

    public function title(): string
    {
        return 'Storage Utilization';
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        return round($bytes / 1024, 2).' KB';
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(): array
    {
        $groupBy = $this->filters['group_by'] ?? 'department';

        // Fetch raw data for richer PDF rows
        $rawQuery = $groupBy === 'folder'
            ? File::query()
                ->join('folders as current_folders', 'files.folder_id', '=', 'current_folders.id')
                ->leftJoin('folders as parent_folders', 'current_folders.parent_id', '=', 'parent_folders.id')
                ->selectRaw('current_folders.id, current_folders.name as folder_name, parent_folders.name as parent_name, SUM(files.file_size) as total_size, COUNT(*) as file_count')
                ->whereNotNull('files.folder_id')
                ->when(! empty($this->filters['department_id']), fn ($q) => $q->where('files.department_id', $this->filters['department_id']))
                ->groupBy('current_folders.id', 'current_folders.name', 'parent_folders.name')
                ->orderByDesc('total_size')
                ->get()
            : File::query()
                ->join('departments', 'files.department_id', '=', 'departments.id')
                ->selectRaw('departments.name as label, SUM(files.file_size) as total_size, COUNT(*) as file_count')
                ->when(! empty($this->filters['folder_id']), fn ($q) => $q->where('files.folder_id', $this->filters['folder_id']))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('total_size')
                ->get();

        $totalSize = $rawQuery->sum('total_size');

        $rows = $rawQuery->map(fn ($r) => [
            'label' => $groupBy === 'folder'
                ? ($r->parent_name ? $r->parent_name.' / '.$r->folder_name : $r->folder_name)
                : $r->label,
            'total_size_formatted' => $this->formatBytes((int) $r->total_size),
            'file_count' => (int) $r->file_count,
            'percentage' => $totalSize > 0 ? round(($r->total_size / $totalSize) * 100, 1) : 0,
        ])->toArray();

        return [
            'rows' => $rows,
            'total_size_formatted' => $this->formatBytes((int) $totalSize),
            'group_by_label' => $groupBy === 'folder' ? 'Folder' : 'Division',
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
