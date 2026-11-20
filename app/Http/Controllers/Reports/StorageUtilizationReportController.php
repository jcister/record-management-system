<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StorageUtilizationReportRequest;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use Inertia\Inertia;
use Inertia\Response;

class StorageUtilizationReportController extends Controller
{
    public function __invoke(StorageUtilizationReportRequest $request): Response
    {
        $filters = $request->filters();
        $groupBy = $filters['group_by'] ?? 'department';

        $query = File::query()->whereNotNull('file_size');

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        $totalSize = (clone $query)->sum('file_size');

        $byDepartment = File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.id, departments.name as label, SUM(files.file_size) as total_size, COUNT(*) as file_count')
            ->when(! empty($filters['folder_id']), fn ($q) => $q->where('files.folder_id', $filters['folder_id']))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_size')
            ->get()
            ->map(fn ($r) => [
                'label' => $r->label,
                'total_size' => (int) $r->total_size,
                'file_count' => (int) $r->file_count,
                'percentage' => $totalSize > 0 ? round(($r->total_size / $totalSize) * 100, 2) : 0,
            ]);

        $byFolder = File::query()
            ->join('folders as current_folders', 'files.folder_id', '=', 'current_folders.id')
            ->leftJoin('folders as parent_folders', 'current_folders.parent_id', '=', 'parent_folders.id')
            ->selectRaw('current_folders.id, current_folders.name as folder_name, parent_folders.name as parent_name, SUM(files.file_size) as total_size, COUNT(*) as file_count')
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('files.department_id', $filters['department_id']))
            ->whereNotNull('files.folder_id')
            ->groupBy('current_folders.id', 'current_folders.name', 'parent_folders.name')
            ->orderByDesc('total_size')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->parent_name ? $r->parent_name.' / '.$r->folder_name : $r->folder_name,
                'total_size' => (int) $r->total_size,
                'file_count' => (int) $r->file_count,
                'percentage' => $totalSize > 0 ? round(($r->total_size / $totalSize) * 100, 2) : 0,
            ]);

        return Inertia::render('Reports/StorageUtilization', [
            'filters' => $filters,
            'total_size' => (int) $totalSize,
            'by_department' => $byDepartment,
            'by_folder' => $byFolder,
            'options' => [
                'departments' => Department::query()->select('id', 'name')->orderBy('name')->get(),
                'root_folders' => Folder::query()->whereNull('parent_id')->select('id', 'name')->orderBy('name')->get(),
                'group_by' => StorageUtilizationReportRequest::GROUP_BY_OPTIONS,
            ],
        ]);
    }
}
