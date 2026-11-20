<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DocumentAccessFrequencyReportRequest;
use App\Models\Department;
use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DocumentAccessFrequencyReportController extends Controller
{
    public function __invoke(DocumentAccessFrequencyReportRequest $request): Response
    {
        $filters = $request->filters();
        $topN = (int) ($filters['top_n'] ?? 20);

        $query = File::query()
            ->with(['department:id,name', 'tags:id,name,type'])
            ->orderByDesc('download_count')
            ->limit($topN);

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['metadata_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $filters['metadata_tags'])->where('tags.type', 'metadata'));
        }

        if (! empty($filters['physical_location_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $filters['physical_location_tags'])->where('tags.type', 'physical_location'));
        }

        $topFiles = $query->get()->map(fn (File $file) => [
            'id' => $file->id,
            'record_id' => $file->record_id,
            'file_name' => $file->file_name,
            'department' => $file->department?->name,
            'download_count' => $file->download_count ?? 0,
            'tags' => $file->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'type' => $t->type]),
        ]);

        $byDepartment = File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, SUM(files.download_count) as total_downloads')
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('files.department_id', $filters['department_id']))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_downloads')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->total_downloads]);

        return Inertia::render('Reports/DocumentAccessFrequency', [
            'filters' => $filters,
            'top_files' => $topFiles,
            'by_department' => $byDepartment,
            'options' => [
                'departments' => Department::query()->select('id', 'name')->orderBy('name')->get(),
                'top_n' => DocumentAccessFrequencyReportRequest::TOP_N_OPTIONS,
            ],
        ]);
    }
}
