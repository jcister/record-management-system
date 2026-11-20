<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DisposalAudit;
use App\Services\AuditService;
use App\Support\DateTimeFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = $this->applyFilters($request)
            ->with(['user:id,name,email'])
            ->latest('created_at')
            ->paginate(50);

        return response()->json($logs);
    }

    public function page(Request $request): Response
    {
        $perPage = 25;
        $currentPage = max(1, (int) $request->input('page', 1));

        // Regular audit logs
        $auditLogs = $this->applyFilters($request)
            ->with(['user:id,name,email'])
            ->latest('created_at')
            ->get()
            ->map(fn (AuditLog $log) => (object) [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'auditable_name' => $log->auditable_name,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
                'source' => 'audit',
            ]);

        // Disposal audit logs — normalized to the same shape
        $disposalLogs = $this->applyDisposalFilters($request)
            ->latest('created_at')
            ->get()
            ->map(function (DisposalAudit $d): object {
                $batchName = $d->batch
                    ? ($d->batch->reference ?? 'Batch #'.$d->batch->sequence)
                    : null;

                return (object) [
                    'id' => 'disposal_'.$d->id,
                    'user_id' => $d->user_id,
                    'user_name' => $d->user?->name,
                    'action' => 'disposal_'.$d->action,
                    'description' => $d->description ?? '',
                    'properties' => $d->meta,
                    'auditable_type' => 'App\\Models\\DisposalBatch',
                    'auditable_id' => $d->batch_id,
                    'auditable_name' => $batchName,
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => $d->created_at?->toISOString(),
                    'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => null] : null,
                    'source' => 'disposal',
                ];
            });

        // Merge, sort newest-first, paginate
        $merged = $auditLogs
            ->concat($disposalLogs)
            ->sortByDesc('created_at')
            ->values();

        $total = $merged->count();
        $pageItems = $merged->forPage($currentPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Available actions grouped by business category
        $availableActions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->pluck('action')
            ->concat(
                DisposalAudit::query()
                    ->select('action')
                    ->distinct()
                    ->pluck('action')
                    ->map(fn (string $a) => 'disposal_'.$a)
            )
            ->unique()
            ->all();

        $actionGroups = $this->buildActionGroups($availableActions);

        $users = AuditLog::query()
            ->select('user_id', 'user_name')
            ->distinct()
            ->get()
            ->map(fn ($row) => [
                'id' => $row->user_id ?? 'system',
                'name' => $row->user_name ?? AuditService::SYSTEM_ACTOR_NAME,
            ])
            ->unique('id')
            ->values();

        $subjects = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->filter()
            ->push('App\\Models\\DisposalBatch')
            ->unique()
            ->values();

        return Inertia::render('AuditTrail', [
            'logs' => $paginator,
            'filters' => [
                'action' => $request->string('action')->toString(),
                'user_id' => $request->string('user_id')->toString(),
                'auditable_type' => $request->string('auditable_type')->toString(),
                'search' => $request->string('search')->toString(),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'my_activity' => $request->boolean('my_activity'),
            ],
            'actionGroups' => $actionGroups,
            'users' => $users,
            'subjects' => $subjects,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Timestamp',
                'User',
                'Action',
                'Description',
                'Subject Type',
                'Subject',
                'Details',
                'IP Address',
                'User Agent',
            ]);

            // Stream audit_logs rows
            $this->applyFilters($request)
                ->with(['user:id,name'])
                ->orderBy('id')
                ->chunkById(250, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            DateTimeFormatter::toDateTimeString($log->created_at),
                            $log->user_name ?? $log->user?->name ?? '—',
                            $log->action,
                            $log->description,
                            $this->presentSubjectType($log->auditable_type),
                            $log->auditable_name ?? '—',
                            $this->stringifyProperties($log->properties),
                            $log->ip_address,
                            $log->user_agent,
                        ]);
                    }
                });

            // Stream disposal_audits rows
            $this->applyDisposalFilters($request)
                ->latest('created_at')
                ->get()
                ->each(function (DisposalAudit $d) use ($handle): void {
                    $batchName = $d->batch
                        ? ($d->batch->reference ?? 'Batch #'.$d->batch->sequence)
                        : null;

                    fputcsv($handle, [
                        DateTimeFormatter::toDateTimeString($d->created_at),
                        $d->user?->name ?? '—',
                        'disposal_'.$d->action,
                        $d->description ?? '',
                        'Disposal Batch',
                        $batchName ?? '—',
                        $this->stringifyProperties($d->meta),
                        '—',
                        '—',
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function applyFilters(Request $request): Builder
    {
        $query = AuditLog::query();

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        // my_activity overrides the user_id filter and bypasses role scoping
        if ($request->boolean('my_activity')) {
            $query->where('user_id', $request->user()?->id);

            return $this->applyDateFilters($this->applySearchFilter($query, $request), $request);
        }

        if ($request->filled('user_id')) {
            $userId = $request->string('user_id')->toString();
            if ($userId === 'system') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $userId);
            }
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type'));
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->string('auditable_id'));
        }

        $query = $this->applySearchFilter($query, $request);
        $query = $this->applyDateFilters($query, $request);

        return $this->applyRoleScope($query, $request);
    }

    private function applyDisposalFilters(Request $request): Builder
    {
        $query = DisposalAudit::query()->with(['user:id,name', 'batch:id,sequence,reference']);

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where('description', 'like', $search);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('auditable_type')) {
            $type = $request->string('auditable_type')->toString();
            if (! in_array($type, ['App\\Models\\DisposalBatch', 'App\\Models\\DisposalBatchFile'], true)) {
                $query->whereRaw('0=1');
            }
        }

        if ($request->filled('action')) {
            $action = $request->string('action')->toString();
            if (! str_starts_with($action, 'disposal_')) {
                $query->whereRaw('0=1');
            } else {
                $query->where('action', Str::after($action, 'disposal_'));
            }
        }

        // my_activity overrides user_id and bypasses role scoping for disposal rows
        if ($request->boolean('my_activity')) {
            $query->where('user_id', $request->user()?->id);

            return $query;
        }

        if ($request->filled('user_id')) {
            $userId = $request->string('user_id')->toString();
            if ($userId === 'system') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $userId);
            }
        }

        // Role scope: only manage-users or disposal-management can see all disposal rows
        $user = $request->user();
        if ($user && ! $user->can('manage-users') && ! $user->can('disposal-management')) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function applySearchFilter(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('description', 'like', $search)
                    ->orWhere('user_name', 'like', $search)
                    ->orWhere('auditable_name', 'like', $search);
            });
        }

        return $query;
    }

    private function applyDateFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $query;
    }

    private function applyRoleScope(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('0=1');
        }

        // Full access for user management
        if ($user->can('manage-users')) {
            return $query;
        }

        $allowedActions = [];

        if ($user->can('organize-content')) {
            array_push(
                $allowedActions,
                'file_created', 'file_updated', 'file_approved', 'file_rejected',
                'file_locked', 'file_unlocked', 'file_visibility_changed', 'file_deleted',
                'file_restored', 'file_force_deleted', 'file_downloaded', 'file_viewed',
                'file_version_restored', 'file_version_created', 'file_ocr_completed',
                'file_ocr_failed', 'chunked_upload_assembled', 'chunked_upload_failed',
                'folder_created', 'folder_updated', 'folder_moved', 'folder_deleted',
                'folder_restored', 'folder_force_deleted',
            );
        }

        if ($user->can('review-content')) {
            array_push($allowedActions, 'file_approved', 'file_rejected');
        }

        if ($user->can('manage-requests')) {
            array_push(
                $allowedActions,
                'request_created', 'request_status_changed', 'request_file_attached',
                'request_file_detached', 'request_scan_uploaded',
            );
        }

        $allowedActions = array_unique($allowedActions);

        // No domain permissions — restrict to own activity only
        if (empty($allowedActions)) {
            return $query->where('user_id', $user->id);
        }

        // Show domain-scoped entries OR the user's own activity
        return $query->where(function (Builder $q) use ($allowedActions, $user): void {
            $q->whereIn('action', $allowedActions)
                ->orWhere('user_id', $user->id);
        });
    }

    /**
     * @param  array<int, string>  $availableActions
     * @return array<int, array{label: string, actions: list<string>}>
     */
    private function buildActionGroups(array $availableActions): array
    {
        $groups = [
            [
                'label' => 'Content',
                'actions' => [
                    'file_created', 'file_updated', 'file_approved', 'file_rejected',
                    'file_locked', 'file_unlocked', 'file_visibility_changed', 'file_deleted',
                    'file_restored', 'file_force_deleted', 'file_downloaded', 'file_viewed',
                    'file_version_restored', 'file_version_created', 'file_ocr_completed',
                    'file_ocr_failed', 'chunked_upload_assembled', 'chunked_upload_failed',
                    'folder_created', 'folder_updated', 'folder_moved', 'folder_deleted',
                    'folder_restored', 'folder_force_deleted',
                ],
            ],
            [
                'label' => 'Requests',
                'actions' => [
                    'request_created', 'request_status_changed', 'request_file_attached',
                    'request_file_detached', 'request_scan_uploaded',
                ],
            ],
            [
                'label' => 'Disposal',
                'actions' => [
                    'disposal_scheduled', 'disposal_completed', 'disposal_inspected',
                    'disposal_approved', 'disposal_rejected', 'disposal_unarchive',
                ],
            ],
            [
                'label' => 'User & Access',
                'actions' => [
                    'user_created', 'user_deleted', 'user_updated', 'profile_updated',
                    'password_updated', 'role_created', 'role_updated', 'role_deleted',
                    'permission_assigned', 'permission_revoked',
                ],
            ],
            [
                'label' => 'Authentication',
                'actions' => [
                    'login', 'login_success', 'logout', 'login_failed',
                    'password_reset', 'email_verified',
                ],
            ],
        ];

        return array_values(array_filter(
            array_map(function (array $group) use ($availableActions): ?array {
                $filtered = array_values(array_filter(
                    $group['actions'],
                    fn (string $action) => in_array($action, $availableActions, true)
                ));

                if (empty($filtered)) {
                    return null;
                }

                return ['label' => $group['label'], 'actions' => $filtered];
            },
                $groups
            )));
    }

    private function presentSubjectType(?string $auditableType): string
    {
        if ($auditableType === null) {
            return '—';
        }

        return Str::headline(class_basename($auditableType));
    }

    /**
     * @param  array<string, mixed>|string|null  $properties
     */
    private function stringifyProperties(array|string|null $properties): string
    {
        if ($properties === null || $properties === []) {
            return '';
        }

        if (is_string($properties)) {
            return $properties;
        }

        return (string) json_encode($properties);
    }
}
