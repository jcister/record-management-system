<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metricsService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        // Check if user has admin/superadmin role for advanced dashboard
        $user = $request->user();
        $isAdmin = $user->roles->whereIn('slug', ['admin', 'super-admin'])->isNotEmpty();

        if ($isAdmin) {
            // Return advanced admin dashboard with metrics
            $dashboardData = $this->metricsService->getAllDashboardData();

            return Inertia::render('Dashboard', [
                'isAdmin' => true,
                'dashboardData' => $dashboardData,
                // Keep basic data for backward compatibility
                'folders' => Folder::with('parent')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'files' => File::with('currentVersion')
                    ->where('retention_status', '!=', 'archived')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ]);
        }

        // Return basic dashboard for non-admin users with their personal activity data
        return Inertia::render('Dashboard', [
            'isAdmin' => false,
            'folders' => Folder::with('parent')
                ->latest()
                ->limit(10)
                ->get(),
            'files' => File::with('currentVersion')
                ->where('retention_status', '!=', 'archived')
                ->latest()
                ->limit(10)
                ->get(),
            'recent_activities' => $this->metricsService->getUserActivityFeed($user, 10),
            'quick_access' => $this->metricsService->getUserQuickAccess($user),
        ]);
    }
}
