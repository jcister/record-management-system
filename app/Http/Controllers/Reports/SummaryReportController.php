<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SummaryReportRequest;
use App\Models\File;
use App\Models\Requests;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SummaryReportController extends Controller
{
    public function __invoke(SummaryReportRequest $request): Response
    {
        $filters = $request->filters();

        $periodFrom = ! empty($filters['period_from']) ? Carbon::parse($filters['period_from']) : now()->startOfMonth();
        $periodTo = ! empty($filters['period_to']) ? Carbon::parse($filters['period_to']) : now();

        // --- High-level counts ---
        $totalFiles = File::query()->count();
        $totalRecords = File::query()->where('is_record', true)->count();

        $byDepartment = File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, COUNT(*) as count')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count]);

        $byRetentionStatus = File::query()
            ->selectRaw("CASE WHEN retention_status = 'archived' THEN 'active' ELSE retention_status END as label, COUNT(*) as count")
            ->groupByRaw("CASE WHEN retention_status = 'archived' THEN 'active' ELSE retention_status END")
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?? 'unknown', 'count' => (int) $r->count]);

        $byStatus = File::query()
            ->selectRaw('status as label, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?? 'unknown', 'count' => (int) $r->count]);

        // --- Period-based activity ---
        $addedInPeriod = File::query()
            ->whereBetween('created_at', [$periodFrom, $periodTo])
            ->count();

        $disposedInPeriod = File::query()
            ->where('retention_status', 'disposed')
            ->whereBetween('updated_at', [$periodFrom, $periodTo])
            ->count();

        $requestsInPeriod = Requests::query()
            ->whereBetween('created_at', [$periodFrom, $periodTo])
            ->count();

        $requestsFulfilled = Requests::query()
            ->where('status', 'approved')
            ->whereBetween('updated_at', [$periodFrom, $periodTo])
            ->count();

        // Weekly trend for added files
        $weeklyTrend = File::query()
            ->select(DB::raw('DATE(created_at) as date, COUNT(*) as count'))
            ->whereBetween('created_at', [$periodFrom, $periodTo])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        // --- Compliance summary ---
        $active = File::query()
            ->whereIn('retention_status', ['active', 'archived'])
            ->count();

        $readyForDisposal = File::query()
            ->where('retention_status', 'ready_for_disposal')
            ->count();

        $disposed = File::query()
            ->where('retention_status', 'disposed')
            ->count();

        return Inertia::render('Reports/Summary', [
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
            ],
            'counts' => [
                'total_files' => $totalFiles,
                'total_records' => $totalRecords,
                'by_department' => $byDepartment,
                'by_retention_status' => $byRetentionStatus,
                'by_status' => $byStatus,
            ],
            'period' => [
                'added' => $addedInPeriod,
                'disposed' => $disposedInPeriod,
                'requests' => $requestsInPeriod,
                'requests_fulfilled' => $requestsFulfilled,
                'weekly_trend' => $weeklyTrend,
            ],
            'compliance' => [
                'active' => $active,
                'ready_for_disposal' => $readyForDisposal,
                'disposed' => $disposed,
            ],
        ]);
    }
}
