<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisposalQueueRequest;
use App\Models\Department;
use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Folder;
use App\Support\DateTimeFormatter;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DisposalManagementController extends Controller
{
    public function __invoke(DisposalQueueRequest $request): Response
    {
        $filters = $request->filters();
        $perPage = $filters['per_page'] ?? DisposalQueueRequest::PER_PAGE_OPTIONS[0];

        $folderGraph = $this->folderGraph();

        $query = File::query()
            ->with([
                'folder:id,name,parent_id',
                'institution:id,name',
                'department:id,name',
                'uploader:id,name',
                'currentVersion:id,file_id,file_name,file_size,version_number,mime_type',
            ])
            ->withCount('versions')
            ->whereIn('retention_status', [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL])
            ->orderBy(...$this->resolveOrder($filters));

        $this->excludeFilesAssignedToBatchWorkflow($query);
        $this->restrictToDisposalEligible($query);

        [$primaryColumn] = $this->resolveOrder($filters);
        if ($primaryColumn !== 'file_name') {
            $query->orderBy('file_name');
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply urgency filter (default to 'all' if not specified)
        $urgency = $filters['urgency'] ?? 'all';
        if ($urgency !== 'all') {
            $this->applyUrgencyFilter($query, $urgency);
        }

        $this->applyFilters($query, $filters, $folderGraph['children']);

        $paginator = $query->paginate($perPage)->withQueryString();

        $queue = $paginator->through(
            fn (File $file) => $this->transformFile($file, $folderGraph['byId'])
        );

        // Ensure urgency is always in filters for frontend sync
        $filters['urgency'] = $urgency;

        return Inertia::render('Disposal/DisposalManagement', [
            'queue' => $queue,
            'filters' => $filters,
            'options' => [
                'root_folders' => $this->rootFolderOptions($folderGraph['nodes']),
                'retention_durations' => $this->retentionDurationOptions(),
                'per_page' => DisposalQueueRequest::PER_PAGE_OPTIONS,
                'departments' => Department::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Department $dept) => [
                        'id' => (string) $dept->id,
                        'name' => $dept->name,
                    ])
                    ->all(),
                'mode_of_disposal' => DisposalBatch::modeOptions(),
            ],
            'metrics' => [
                'totals' => $this->statusTotals(),
                'upcoming' => $this->upcomingExpirations(),
                'urgency' => $this->urgencyStats(),
            ],
        ]);
    }

    public function filterOptions(): \Illuminate\Http\JsonResponse
    {
        $folderGraph = $this->folderGraph();

        return response()->json([
            'retention_durations' => $this->retentionDurationOptions(),
            'root_folders' => $this->rootFolderOptions($folderGraph['nodes']),
            'departments' => Department::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $dept) => [
                    'id' => (string) $dept->id,
                    'name' => $dept->name,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array{
     *     nodes: Collection<int, Folder>,
     *     byId: Collection<string, Folder>,
     *     children: Collection<string|null, Collection<int, Folder>>
     * }
     */
    private function folderGraph(): array
    {
        $nodes = Folder::query()
            ->select('id', 'parent_id', 'name')
            ->orderBy('name')
            ->get();

        return [
            'nodes' => $nodes,
            'byId' => $nodes->keyBy('id'),
            'children' => $nodes->groupBy('parent_id'),
        ];
    }

    /**
     * @param  Builder<File>  $query
     */
    private function applyUrgencyFilter(Builder $query, string $urgency): void
    {
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        match ($urgency) {
            'immediate' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '<', $tomorrow),
            'soon' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>=', $tomorrow)
                ->where('retention_expires_at', '<=', $today->copy()->addDays(30)),
            'scheduled' => $query->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>', $today->copy()->addDays(30)),
            'perpetual' => $query->where('retention_duration', RetentionSchedule::DURATION_PERPETUAL)
                ->whereNull('retention_expires_at'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{string, string}
     */
    private function resolveOrder(array $filters): array
    {
        $columnMap = [
            'record_id' => 'record_id',
            'file_name' => 'file_name',
            'author' => 'author',
            'date_released' => 'date_released',
            'retention_expires' => 'retention_expires_at',
            'urgency' => 'retention_expires_at',
        ];

        $column = $columnMap[$filters['sort_by'] ?? ''] ?? 'retention_expires_at';
        $direction = in_array($filters['sort_direction'] ?? '', ['asc', 'desc'], true)
            ? $filters['sort_direction']
            : 'asc';

        return [$column, $direction];
    }

    /**
     * @param  Builder<File>  $query
     * @param  array<string, mixed>  $filters
     * @param  Collection<string|null, Collection<int, Folder>>  $children
     */
    private function applyFilters(Builder $query, array $filters, Collection $children): void
    {
        if (! empty($filters['retention_duration'])) {
            $query->where('retention_duration', $filters['retention_duration']);
        }

        if (! empty($filters['physical_location'])) {
            $query->where('physical_location', 'like', '%'.$filters['physical_location'].'%');
        }

        if (! empty($filters['expires_from'])) {
            $query->whereDate('retention_expires_at', '>=', $filters['expires_from']);
        }

        if (! empty($filters['expires_to'])) {
            $query->whereDate('retention_expires_at', '<=', $filters['expires_to']);
        }

        if (! empty($filters['released_from'])) {
            $query->whereDate('date_released', '>=', $filters['released_from']);
        }

        if (! empty($filters['released_to'])) {
            $query->whereDate('date_released', '<=', $filters['released_to']);
        }

        if (! empty($filters['root_folder_id'])) {
            $query->whereIn(
                'folder_id',
                $this->descendantFolderIds($filters['root_folder_id'], $children)
            );
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
    }

    /**
     * @param  Collection<string|null, Collection<int, Folder>>  $children
     * @return list<string>
     */
    private function descendantFolderIds(string $rootId, Collection $children): array
    {
        $ids = [$rootId];
        $stack = [$rootId];

        while (! empty($stack)) {
            $current = array_pop($stack);

            /** @var Collection<int, Folder>|null $childGroup */
            $childGroup = $children->get($current);

            if ($childGroup === null) {
                continue;
            }

            foreach ($childGroup as $child) {
                $ids[] = (string) $child->id;
                $stack[] = (string) $child->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Collection<string, Folder>  $folderIndex
     * @return array{
     *     id: string,
     *     record_id: string,
     *     file_name: string,
     *     author: string,
     *     folder: array{
     *         trail: list<array{id: string, name: string}>,
     *         root: array{id: string, name: string}|null
     *     },
     *     institution: array{id: string, name: string}|null,
     *     department: array{id: string|int, name: string}|null,
     *     uploader: array{id: string, name: string|null}|null,
     *     retention: array{
     *         duration: string|null,
     *         expires_at: string|null
     *     },
     *     physical: array{
     *         location: string|null,
     *         tags: list<array{id: int, name: string}>
     *     },
     *     electronic: array{
     *         version_count: int,
     *         latest_version: int|null,
     *         current_version: array{
     *             name: string|null,
     *             size: int|null,
     *             mime_type: string|null,
     *             version_number: int|null
     *         }
     *     },
     *     date_created: string|null,
     *     date_released: string|null,
     *     retention_status: string
     * }
     */
    private function transformFile(File $file, Collection $folderIndex): array
    {
        $trail = $this->folderTrail($file->folder_id, $folderIndex);
        $root = $trail[0] ?? null;

        $physicalTags = $file->tags
            ->where('type', 'physical_location')
            ->map(fn ($tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
            ])
            ->values()
            ->all();

        $currentVersion = $file->currentVersion;

        // Calculate urgency
        $urgency = $this->calculateUrgency($file);
        $daysUntilExpiry = $this->calculateDaysUntilExpiry($file);

        return [
            'id' => (string) $file->id,
            'record_id' => (string) $file->record_id,
            'file_name' => $file->displayName(),
            'author' => $file->author,
            'file_path' => $file->file_path,
            'folder' => [
                'trail' => $trail,
                'root' => $root,
            ],
            'institution' => $file->institution?->only(['id', 'name']),
            'department' => $file->department?->only(['id', 'name']),
            'uploader' => $file->uploader?->only(['id', 'name']),
            'retention' => [
                'duration' => $file->retention_duration,
                'expires_at' => DateTimeFormatter::toDateString($file->retention_expires_at),
            ],
            'physical' => [
                'location' => $file->physical_location,
                'tags' => $physicalTags,
            ],
            'electronic' => [
                'version_count' => (int) $file->versions_count,
                'latest_version' => $file->latest_version_number,
                'current_version' => [
                    'name' => $currentVersion?->file_name,
                    'size' => $currentVersion?->file_size ?? $file->file_size,
                    'mime_type' => $currentVersion?->mime_type ?? $file->mime_type,
                    'version_number' => $currentVersion?->version_number,
                ],
            ],
            'date_created' => DateTimeFormatter::toDateString($file->date_created),
            'date_released' => DateTimeFormatter::toDateString($file->date_released),
            'retention_status' => $file->retention_status,
            'urgency' => $urgency,
            'days_until_expiry' => $daysUntilExpiry,
        ];
    }

    private function calculateUrgency(File $file): string
    {
        if ($file->retention_duration === RetentionSchedule::DURATION_PERPETUAL || $file->retention_expires_at === null) {
            return 'perpetual';
        }

        $now = now()->startOfDay();
        $expiresAt = \Carbon\Carbon::parse($file->retention_expires_at)->startOfDay();
        $daysUntilExpiry = $now->diffInDays($expiresAt, false);

        // Immediate: overdue + today (expires before tomorrow)
        if ($daysUntilExpiry < 1) {
            return 'immediate';
        }

        // Soon: tomorrow through 30 days
        if ($daysUntilExpiry <= 30) {
            return 'soon';
        }

        // Scheduled: > 30 days
        return 'scheduled';
    }

    private function calculateDaysUntilExpiry(File $file): ?int
    {
        if ($file->retention_duration === RetentionSchedule::DURATION_PERPETUAL || $file->retention_expires_at === null) {
            return null;
        }

        $now = now()->startOfDay();
        $expiresAt = \Carbon\Carbon::parse($file->retention_expires_at)->startOfDay();

        return (int) $now->diffInDays($expiresAt, false);
    }

    /**
     * @return array{
     *     overdue: int,
     *     critical: int,
     *     urgent: int,
     *     soon: int,
     *     perpetual: int
     * }
     */
    private function urgencyStats(): array
    {
        $today = now()->startOfDay();

        $baseQuery = File::query()
            ->whereIn('retention_status', [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL]);

        $this->excludeFilesAssignedToBatchWorkflow($baseQuery);

        $this->restrictToDisposalEligible($baseQuery);

        $tomorrow = $today->copy()->addDay();

        return [
            'all' => (clone $baseQuery)->count(),
            'immediate' => (clone $baseQuery)
                ->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '<', $tomorrow)
                ->count(),
            'soon' => (clone $baseQuery)
                ->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>=', $tomorrow)
                ->where('retention_expires_at', '<=', $today->copy()->addDays(30))
                ->count(),
            'scheduled' => (clone $baseQuery)
                ->whereNotNull('retention_expires_at')
                ->where('retention_expires_at', '>', $today->copy()->addDays(30))
                ->count(),
            'perpetual' => (clone $baseQuery)
                ->where('retention_duration', RetentionSchedule::DURATION_PERPETUAL)
                ->whereNull('retention_expires_at')
                ->count(),
        ];
    }

    /**
     * @param  Collection<string, Folder>  $folderIndex
     * @return list<array{id: string, name: string}>
     */
    private function folderTrail(?string $folderId, Collection $folderIndex): array
    {
        if ($folderId === null) {
            return [];
        }

        $segments = [];
        $current = $folderId;

        while ($current !== null) {
            /** @var Folder|null $folder */
            $folder = $folderIndex->get($current);

            if ($folder === null) {
                break;
            }

            $segments[] = [
                'id' => (string) $folder->id,
                'name' => $folder->name,
            ];

            $current = $folder->parent_id;
        }

        return array_reverse($segments);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function rootFolderOptions(Collection $folders): array
    {
        return $folders
            ->whereNull('parent_id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Folder $folder) => [
                'id' => (string) $folder->id,
                'name' => $folder->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function retentionDurationOptions(): array
    {
        return [
            [
                'value' => RetentionSchedule::DURATION_ONE_YEAR,
                'label' => '1 year hold',
                'description' => 'High-churn reference records that expire quickly.',
            ],
            [
                'value' => RetentionSchedule::DURATION_TEN_YEARS,
                'label' => '10 year hold',
                'description' => 'Long-form permits, contracts, or compliance files.',
            ],
        ];
    }

    /**
     * @param  Builder<File>  $query
     */
    private function restrictToDisposalEligible(Builder $query): void
    {
        $query->where('is_record', true)
            ->where(function (Builder $eligible) {
                $eligible
                    ->whereNull('retention_duration')
                    ->orWhere('retention_duration', '!=', RetentionSchedule::DURATION_PERPETUAL);
            })
            // Exclude scan files awaiting review
            ->whereNotIn('status', ['draft', 'pending'])
            // Additional safety: exclude files with SCAN- record IDs
            ->where('record_id', 'NOT LIKE', 'SCAN-%');
    }

    /**
     * @return array{
     *     active: int,
     *     ready_for_disposal: int,
     *     disposed: int,
     *     scheduled_batches: int
     * }
     */
    private function statusTotals(): array
    {
        $base = [
            RetentionStatus::ACTIVE => 0,
            RetentionStatus::READY_FOR_DISPOSAL => 0,
            RetentionStatus::DISPOSED => 0,
        ];

        $countsQuery = File::query()
            ->selectRaw('retention_status, COUNT(*) as total')
            ->whereIn('retention_status', array_keys($base));

        $this->restrictToDisposalEligible($countsQuery);

        $counts = $countsQuery
            ->groupBy('retention_status')
            ->pluck('total', 'retention_status')
            ->all();

        foreach ($counts as $status => $total) {
            if (array_key_exists($status, $base)) {
                $base[$status] = (int) $total;
            }
        }

        $disposedCount = File::query()
            ->where('retention_status', RetentionStatus::DISPOSED)
            ->count();

        $scheduledBatchStatuses = array_values(array_filter(
            DisposalBatch::fileReservationStatuses(),
            fn (string $status): bool => $status !== DisposalBatch::STATUS_COMPLETE,
        ));

        $scheduledBatches = DisposalBatch::query()
            ->whereIn('status', $scheduledBatchStatuses)
            ->count();

        // Count files currently owned by any active disposal workflow batch.
        $filesInWorkflowBatches = \Illuminate\Support\Facades\DB::table('disposal_batch_files')
            ->join('disposal_batches', 'disposal_batch_files.batch_id', '=', 'disposal_batches.id')
            ->join('files', 'disposal_batch_files.file_id', '=', 'files.id')
            ->whereIn('disposal_batches.status', DisposalBatch::fileReservationStatuses())
            ->where('files.retention_status', RetentionStatus::READY_FOR_DISPOSAL)
            ->where(function ($eligible) {
                $eligible
                    ->whereNull('files.retention_duration')
                    ->orWhere('files.retention_duration', '!=', RetentionSchedule::DURATION_PERPETUAL);
            })
            ->whereNull('files.deleted_at')
            ->count();

        return [
            'active' => $base[RetentionStatus::ACTIVE],
            'ready_for_disposal' => max(0, $base[RetentionStatus::READY_FOR_DISPOSAL] - $filesInWorkflowBatches),
            'disposed' => $disposedCount,
            'scheduled_batches' => $scheduledBatches,
        ];
    }

    /**
     * @return array{
     *     next_30_days: int,
     *     next_90_days: int,
     *     overdue: int
     * }
     */
    private function upcomingExpirations(): array
    {
        $today = now()->startOfDay();

        return [
            'next_30_days' => $this->countExpiringWithinDays($today->copy(), 30),
            'next_90_days' => $this->countExpiringWithinDays($today->copy(), 90),
            'overdue' => File::query()
                ->where('retention_status', RetentionStatus::READY_FOR_DISPOSAL)
                ->whereNotNull('retention_expires_at')
                ->whereDate('retention_expires_at', '<', $today->toDateString())
                ->whereNotExists(function ($subquery) {
                    $subquery->select('file_id')
                        ->from('disposal_batch_files')
                        ->join('disposal_batches', 'disposal_batch_files.batch_id', '=', 'disposal_batches.id')
                        ->whereColumn('disposal_batch_files.file_id', 'files.id')
                        ->whereIn('disposal_batches.status', DisposalBatch::fileReservationStatuses());
                })
                ->count(),
        ];
    }

    /**
     * @param  Builder<File>  $query
     */
    private function excludeFilesAssignedToBatchWorkflow(Builder $query): void
    {
        $query->whereNotExists(function ($subquery) {
            $subquery->select('file_id')
                ->from('disposal_batch_files')
                ->join('disposal_batches', 'disposal_batch_files.batch_id', '=', 'disposal_batches.id')
                ->whereColumn('disposal_batch_files.file_id', 'files.id')
                ->whereIn('disposal_batches.status', DisposalBatch::fileReservationStatuses());
        });
    }

    private function countExpiringWithinDays(CarbonInterface $start, int $days): int
    {
        $end = $start->copy()->addDays($days);

        $countQuery = File::query()
            ->where('retention_status', RetentionStatus::ACTIVE)
            ->whereNotNull('retention_expires_at')
            ->whereBetween('retention_expires_at', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        $this->restrictToDisposalEligible($countQuery);

        return $countQuery->count();
    }
}
