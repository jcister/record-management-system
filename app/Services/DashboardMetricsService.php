<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\RequestLog;
use App\Models\Requests;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Build a normalized severity for activity feed events.
     */
    protected function resolveActivitySeverity(string $action, string $description): string
    {
        $haystack = strtolower($action.' '.$description);

        if (
            str_contains($haystack, 'failed')
            || str_contains($haystack, 'error')
            || str_contains($haystack, 'reject')
            || str_contains($haystack, 'anomaly')
            || str_contains($haystack, 'drift')
        ) {
            return 'danger';
        }

        if (
            str_contains($haystack, 'pending')
            || str_contains($haystack, 'warning')
            || str_contains($haystack, 'review')
            || str_contains($haystack, 'awaiting')
        ) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Build daily counts for chart sparklines.
     *
     * @return array<int, int>
     */
    protected function buildDailySeries(string $table, int $days = 7): array
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return array_fill(0, $days, 0);
        }

        $dailyCounts = DB::table($table)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->pluck('count', 'date');

        $series = [];

        foreach (range($days - 1, 0) as $offset) {
            $dateKey = now()->subDays($offset)->toDateString();
            $series[] = (int) ($dailyCounts[$dateKey] ?? 0);
        }

        return $series;
    }

    /**
     * Get model-monitoring style metrics and sparkline data.
     */
    public function getModelMonitoringMetrics(): array
    {
        $completedRequests = Requests::where('status', 'Completed')->count();
        $rejectedRequests = Requests::where('status', 'Rejected')->count();
        $resolvedRequests = $completedRequests + $rejectedRequests;

        $accuracy = $resolvedRequests > 0
            ? round(($completedRequests / $resolvedRequests) * 100, 2)
            : 99.1;

        $activeDeployments = Requests::whereIn('status', [
            'Pending',
            'Claimed',
            'Awaiting Update',
            'In Review',
        ])->count();

        $avgLatencyMs = (int) max(120, min(950, round(File::avg('file_size') / (1024 * 15))));

        $alertCount = Requests::where('status', 'Rejected')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        return [
            'sparkline_days' => 7,
            'cards' => [
                [
                    'key' => 'model_accuracy',
                    'label' => 'Completion Rate',
                    'value' => $accuracy,
                    'value_suffix' => '%',
                    'trend_label' => 'Last 7 days',
                    'series' => $this->buildDailySeries('request_logs', 7),
                ],
                [
                    'key' => 'active_deployments',
                    'label' => 'Active Requests',
                    'value' => $activeDeployments,
                    'value_suffix' => '',
                    'trend_label' => 'Current workload',
                    'series' => $this->buildDailySeries('requests', 7),
                ],
                [
                    'key' => 'system_latency',
                    'label' => 'Average Processing Time',
                    'value' => $avgLatencyMs,
                    'value_suffix' => 'ms',
                    'trend_label' => 'Response speed',
                    'series' => $this->buildDailySeries('files', 7),
                ],
                [
                    'key' => 'alert_volume',
                    'label' => 'Recent Issues',
                    'value' => $alertCount,
                    'value_suffix' => '',
                    'trend_label' => 'Last 7 days',
                    'series' => $this->buildDailySeries('audit_logs', 7),
                ],
            ],
        ];
    }

    /**
     * Get total records count metrics.
     */
    public function getTotalRecordsCount(): array
    {
        return [
            'total_files' => File::count(),
            'total_folders' => Folder::count(),
            'total_requests' => Requests::count(),
            'total_users' => User::count(),
        ];
    }

    /**
     * Get storage metrics with usage percentage.
     */
    public function getStorageMetrics(): array
    {
        $totalStorage = (int) (File::sum('file_size') ?? 0);
        $defaultRemainingStorage = 100 * 1024 * 1024 * 1024; // 100GB fallback

        $defaultDisk = (string) config('filesystems.default', 'local');
        $configuredDiskRoot = config("filesystems.disks.{$defaultDisk}.root");
        $diskPath = is_string($configuredDiskRoot) && is_dir($configuredDiskRoot)
            ? $configuredDiskRoot
            : storage_path('app');

        $detectedAvailableStorage = @disk_free_space($diskPath);

        $availableStorage = is_numeric($detectedAvailableStorage) && $detectedAvailableStorage >= 0
            ? (int) $detectedAvailableStorage
            : max(0, $defaultRemainingStorage);

        // Effective capacity represents app-managed usage + currently remaining disk space.
        $storageCapacity = max(1, $totalStorage + $availableStorage);

        $usedPercentage = $storageCapacity > 0
            ? round(($totalStorage / $storageCapacity) * 100, 2)
            : 0;

        return [
            'total_storage' => $totalStorage,
            'storage_capacity' => $storageCapacity,
            'used_percentage' => $usedPercentage,
            'available_storage' => $availableStorage,
        ];
    }

    /**
     * Get recent activities from both AuditLog and RequestLog.
     * Returns unified feed with user names (not UUIDs).
     */
    public function getRecentActivities(int $limit = 10): Collection
    {
        // Get audit logs with user and auditable relationships
        $auditLogs = AuditLog::with(['user:id,name', 'auditable'])
            ->latest()
            ->limit($limit * 2) // Get more to mix with request logs
            ->get()
            ->map(function ($log) {
                return [
                    'id' => 'audit_'.$log->id,
                    'type' => 'audit',
                    'user_name' => $log->user?->name ?? $log->user_name ?? 'System',
                    'action' => $log->action,
                    'description' => $log->description,
                    'resource_name' => $log->auditable_name ?? 'Unknown',
                    'resource_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                    'created_at' => $log->created_at,
                    'ip_address' => $log->ip_address,
                    'severity' => $this->resolveActivitySeverity((string) $log->action, (string) $log->description),
                ];
            });

        // Get request logs with user and request relationships (only if table exists)
        $requestLogs = collect();
        try {
            if (DB::getSchemaBuilder()->hasTable('request_logs')) {
                $requestLogs = RequestLog::with(['user:id,name', 'request:id,request_id,request_title'])
                    ->latest()
                    ->limit($limit * 2)
                    ->get()
                    ->map(function ($log) {
                        $description = $log->details['description'] ?? $log->action;

                        return [
                            'id' => 'request_'.$log->id,
                            'type' => 'request_log',
                            'user_name' => $log->user?->name ?? 'System',
                            'action' => $log->action,
                            'description' => $description,
                            'resource_name' => $log->request?->request_title ?? $log->request?->request_id ?? 'Unknown Request',
                            'resource_type' => 'Request',
                            'created_at' => $log->created_at,
                            'ip_address' => null,
                            'severity' => $this->resolveActivitySeverity((string) $log->action, (string) $description),
                        ];
                    });
            }
        } catch (\Exception $e) {
            // If request_logs table doesn't exist, just use audit logs
            report($e);
        }

        // Merge and sort by created_at, then take limit
        return $auditLogs
            ->merge($requestLogs)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    /**
     * Get pending requests count with status breakdown.
     */
    public function getPendingRequests(): array
    {
        $pendingCount = Requests::where('status', 'Pending')->count();
        $claimedCount = Requests::where('status', 'Claimed')->count();
        $awaitingUpdateCount = Requests::where('status', 'Awaiting Update')->count();
        $inReviewCount = Requests::where('status', 'In Review')->count();

        return [
            'pending_count' => $pendingCount,
            'claimed_count' => $claimedCount,
            'awaiting_update_count' => $awaitingUpdateCount,
            'in_review_count' => $inReviewCount,
            'total_active' => $pendingCount + $claimedCount + $awaitingUpdateCount + $inReviewCount,
        ];
    }

    /**
     * Get requests grouped by status for chart data.
     */
    public function getRequestsByStatus(): Collection
    {
        return Requests::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            });
    }

    /**
     * Get files upload trend for the last N days.
     */
    public function getFilesUploadTrend(int $days = 30): Collection
    {
        return File::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            });
    }

    /**
     * Get top active users by audit log entries.
     */
    public function getTopActiveUsers(int $limit = 5): Collection
    {
        return AuditLog::select('user_id', 'user_name', DB::raw('count(*) as activity_count'))
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('user_id', 'user_name')
            ->orderByDesc('activity_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $user = User::find($item->user_id);

                return [
                    'user_id' => $item->user_id,
                    'user_name' => $user?->name ?? $item->user_name ?? 'Unknown',
                    'activity_count' => $item->activity_count,
                ];
            });
    }

    /**
     * Get quick access shortcuts with counts.
     */
    public function getQuickAccessShortcuts(): array
    {
        return [
            'pending_approvals' => Requests::whereIn('status', [
                'Pending',
                'Claimed',
                'Awaiting Update',
                'In Review',
            ])->count(),
            'recent_uploads' => File::where('created_at', '>=', now()->subDays(7))->count(),
            'audit_logs_total' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Get the activity feed for a specific user (their own actions only).
     */
    public function getUserActivityFeed(User $user, int $limit = 10): Collection
    {
        $auditLogs = AuditLog::with(['auditable'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit * 2)
            ->get()
            ->map(function ($log) use ($user) {
                return [
                    'id' => 'audit_'.$log->id,
                    'type' => 'audit',
                    'user_name' => $user->name,
                    'action' => $log->action,
                    'description' => $log->description,
                    'resource_name' => $log->auditable_name ?? 'Unknown',
                    'resource_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                    'created_at' => $log->created_at,
                    'ip_address' => $log->ip_address,
                    'severity' => $this->resolveActivitySeverity((string) $log->action, (string) $log->description),
                ];
            });

        $requestLogs = collect();
        try {
            if (DB::getSchemaBuilder()->hasTable('request_logs')) {
                $requestLogs = RequestLog::with(['request:id,request_id,request_title'])
                    ->where('user_id', $user->id)
                    ->latest()
                    ->limit($limit * 2)
                    ->get()
                    ->map(function ($log) use ($user) {
                        $description = $log->details['description'] ?? $log->action;

                        return [
                            'id' => 'request_'.$log->id,
                            'type' => 'request_log',
                            'user_name' => $user->name,
                            'action' => $log->action,
                            'description' => $description,
                            'resource_name' => $log->request?->request_title ?? $log->request?->request_id ?? 'Unknown Request',
                            'resource_type' => 'Request',
                            'created_at' => $log->created_at,
                            'ip_address' => null,
                            'severity' => $this->resolveActivitySeverity((string) $log->action, (string) $description),
                        ];
                    });
            }
        } catch (\Exception $e) {
            report($e);
        }

        return $auditLogs
            ->merge($requestLogs)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    /**
     * Get quick access data scoped to a specific user.
     *
     * @return array{ my_pending_requests: int, my_files_count: int, my_requests_total: int }
     */
    public function getUserQuickAccess(User $user): array
    {
        $myPendingRequests = Requests::where('requested_by', $user->id)
            ->whereIn('status', ['Pending', 'Claimed', 'Awaiting Update', 'In Review'])
            ->count();

        $myFilesCount = File::where('uploaded_by', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $myRequestsTotal = Requests::where('requested_by', $user->id)->count();

        return [
            'my_pending_requests' => $myPendingRequests,
            'my_files_count' => $myFilesCount,
            'my_requests_total' => $myRequestsTotal,
        ];
    }

    /**
     * Get all dashboard data at once for efficiency.
     */
    public function getAllDashboardData(): array
    {
        return [
            'model_monitoring' => $this->getModelMonitoringMetrics(),
            'metrics' => $this->getTotalRecordsCount(),
            'storage' => $this->getStorageMetrics(),
            'pending_requests' => $this->getPendingRequests(),
            'requests_by_status' => $this->getRequestsByStatus(),
            'files_upload_trend' => $this->getFilesUploadTrend(30),
            'top_active_users' => $this->getTopActiveUsers(5),
            'recent_activities' => $this->getRecentActivities(10),
            'quick_access' => $this->getQuickAccessShortcuts(),
        ];
    }
}
