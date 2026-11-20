<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Requests;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth();

        // KPI: total documents
        $totalDocuments = File::query()->count();

        // KPI: lifecycle buckets (derived from disposal lifecycle)
        $activeCount = File::query()
            ->whereIn('retention_status', ['active', 'archived'])
            ->count();

        $readyForDisposalCount = File::query()
            ->where('retention_status', 'ready_for_disposal')
            ->count();

        // KPI: pending retrieval requests
        $pendingRequests = Requests::query()
            ->where('status', 'pending')
            ->count();

        // KPI: disposed this month
        $disposedThisMonth = File::query()
            ->where('retention_status', 'disposed')
            ->where('updated_at', '>=', $startOfMonth)
            ->count();

        // Weekly trend (last 30 days)
        $weeklyTrend = File::query()
            ->select(DB::raw('DATE(created_at) as date, COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        // Compliance breakdown (same buckets as lifecycle stage)
        $disposed = File::query()
            ->where('retention_status', 'disposed')
            ->count();

        $complianceActive = $activeCount;
        $complianceReadyForDisposal = $readyForDisposalCount;

        // Top divisions by document count
        $byDepartment = File::query()
            ->join('departments', 'files.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, COUNT(*) as count')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count]);

        return Inertia::render('Reports/Index', [
            'stats' => [
                'total_documents' => $totalDocuments,
                'active_count' => $activeCount,
                'ready_for_disposal_count' => $readyForDisposalCount,
                'pending_requests' => $pendingRequests,
                'disposed_this_month' => $disposedThisMonth,
            ],
            'weekly_trend' => $weeklyTrend,
            'compliance' => [
                'active' => $complianceActive,
                'ready_for_disposal' => $complianceReadyForDisposal,
                'disposed' => $disposed,
            ],
            'by_department' => $byDepartment,
        ]);
    }
}
