<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RecordsInventoryReportRequest;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class RecordsInventoryReportController extends Controller
{
    public function __invoke(RecordsInventoryReportRequest $request): Response
    {
        $filters = $request->filters();
        $perPage = $filters['per_page'] ?? 20;

        $query = File::query()
            ->with([
                'department:id,name',
                'folder:id,name',
                'tags:id,name,type',
            ])
            ->orderBy('file_name');

        $this->applyFilters($query, $filters);

        $paginator = $query->paginate($perPage)->withQueryString();

        $records = $paginator->through(fn (File $file) => $this->transformFile($file));

        // Pre-aggregated chart data (runs on unfiltered or lightly filtered query for charts)
        $chartQuery = File::query();
        $this->applyFilters($chartQuery, $filters);

        $byDepartment = (clone $chartQuery)
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, COUNT(*) as count')
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count]);

        $byRetentionStatus = (clone $chartQuery)
            ->selectRaw("CASE WHEN retention_status = 'archived' THEN 'active' ELSE retention_status END as label, COUNT(*) as count")
            ->groupByRaw("CASE WHEN retention_status = 'archived' THEN 'active' ELSE retention_status END")
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count]);

        return Inertia::render('Reports/RecordsInventory', [
            'records' => $records,
            'filters' => $filters,
            'charts' => [
                'by_department' => $byDepartment,
                'by_retention_status' => $byRetentionStatus,
            ],
            'options' => [
                'departments' => Department::query()->select('id', 'name')->orderBy('name')->get(),
                'root_folders' => Folder::query()
                    ->whereNull('parent_id')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'per_page' => RecordsInventoryReportRequest::PER_PAGE_OPTIONS,
                'statuses' => RecordsInventoryReportRequest::STATUS_OPTIONS,
                'retention_statuses' => RecordsInventoryReportRequest::RETENTION_STATUS_OPTIONS,
            ],
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['retention_status'])) {
            // Treat archived as part of ACTIVE for reporting consistency.
            $bucket = (string) $filters['retention_status'];
            if ($bucket === 'active' || $bucket === 'archived') {
                $query->whereIn('retention_status', ['active', 'archived']);
            } else {
                $query->where('retention_status', $bucket);
            }
        }

        if (isset($filters['is_record'])) {
            $query->where('is_record', (bool) $filters['is_record']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_created', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date_created', '<=', $filters['date_to']);
        }

        if (! empty($filters['metadata_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $filters['metadata_tags'])->where('tags.type', 'metadata'));
        }

        if (! empty($filters['physical_location_tags'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $filters['physical_location_tags'])->where('tags.type', 'physical_location'));
        }

        if (! empty($filters['metadata_tag_search'])) {
            $term = (string) $filters['metadata_tag_search'];

            $query->whereHas('tags', function (Builder $t) use ($term): void {
                $t->where('tags.type', 'metadata')
                    ->where('tags.name', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['physical_location_tag_search'])) {
            $term = (string) $filters['physical_location_tag_search'];

            $query->whereHas('tags', function (Builder $t) use ($term): void {
                $t->where('tags.type', 'physical_location')
                    ->where('tags.name', 'like', "%{$term}%");
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformFile(File $file): array
    {
        return [
            'id' => $file->id,
            'record_id' => $file->record_id,
            'file_name' => $file->file_name,
            'author' => $file->author,
            'department' => $file->department?->name,
            'folder' => $file->folder?->name,
            'tags' => $file->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'type' => $tag->type]),
            'status' => $file->status,
            'retention_status' => $file->retention_status,
            'retention_duration' => $file->retention_duration,
            'date_created' => $file->date_created?->toDateString(),
            'date_released' => $file->date_released?->toDateString(),
            'retention_expires_at' => $file->retention_expires_at?->toDateString(),
            'is_record' => $file->is_record,
        ];
    }
}
