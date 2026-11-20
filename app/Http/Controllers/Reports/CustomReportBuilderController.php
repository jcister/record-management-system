<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CustomReportPreviewRequest;
use App\Models\AuditLog;
use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Requests;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomReportBuilderController extends Controller
{
    /**
     * Available fields per source, with their labels and types.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const TAG_COLUMNS = ['metadata_tags', 'physical_location_tags'];

    private const AVAILABLE_FIELDS = [
        'files' => [
            'record_id' => ['label' => 'Record ID', 'type' => 'string'],
            'file_name' => ['label' => 'File Name', 'type' => 'string'],
            'author' => ['label' => 'Author', 'type' => 'string'],
            'status' => ['label' => 'Status', 'type' => 'string'],
            'retention_status' => ['label' => 'Retention Status', 'type' => 'string'],
            'retention_duration' => ['label' => 'Retention Duration', 'type' => 'string'],
            'visibility' => ['label' => 'Visibility', 'type' => 'string'],
            'is_record' => ['label' => 'Document Type', 'type' => 'classification', 'options' => [['value' => '1', 'label' => 'Record'], ['value' => '0', 'label' => 'File']]],
            'metadata_tags' => ['label' => 'Keywords / Metadata Tags', 'type' => 'tags'],
            'physical_location_tags' => ['label' => 'Physical Location Tags', 'type' => 'tags'],
            'date_created' => ['label' => 'Date Created', 'type' => 'date'],
            'date_released' => ['label' => 'Date Released', 'type' => 'date'],
            'retention_expires_at' => ['label' => 'Retention Expires At', 'type' => 'date'],
            'file_size' => ['label' => 'File Size (bytes)', 'type' => 'number'],
            'download_count' => ['label' => 'Download Count', 'type' => 'number'],
            'created_at' => ['label' => 'Uploaded At', 'type' => 'datetime'],
        ],
        'requests' => [
            'file_number' => ['label' => 'File Number', 'type' => 'string'],
            'request_title' => ['label' => 'Title', 'type' => 'string'],
            'purpose' => ['label' => 'Purpose', 'type' => 'string'],
            'status' => ['label' => 'Status', 'type' => 'string'],
            'priority' => ['label' => 'Priority', 'type' => 'string'],
            'start_date' => ['label' => 'Start Date', 'type' => 'date'],
            'end_date' => ['label' => 'End Date', 'type' => 'date'],
            'created_at' => ['label' => 'Created At', 'type' => 'datetime'],
        ],
        'users' => [
            'name' => ['label' => 'Full Name', 'type' => 'string'],
            'email' => ['label' => 'Email', 'type' => 'string'],
            'created_at' => ['label' => 'Registered At', 'type' => 'datetime'],
            'deleted_at' => ['label' => 'Deactivated At', 'type' => 'datetime'],
        ],
        'disposal_batches' => [
            'reference' => ['label' => 'Batch Reference', 'type' => 'string'],
            'status' => ['label' => 'Status', 'type' => 'string'],
            'mode_of_disposal' => ['label' => 'Mode of Disposal', 'type' => 'string'],
            'file_count' => ['label' => 'File Count', 'type' => 'number'],
            'scheduled_at' => ['label' => 'Scheduled At', 'type' => 'datetime'],
            'completed_at' => ['label' => 'Completed At', 'type' => 'datetime'],
            'created_at' => ['label' => 'Created At', 'type' => 'datetime'],
        ],
        'audit_logs' => [
            'user_name' => ['label' => 'User', 'type' => 'string'],
            'action' => ['label' => 'Action', 'type' => 'string'],
            'description' => ['label' => 'Description', 'type' => 'string'],
            'auditable_type' => ['label' => 'Subject Type', 'type' => 'string'],
            'ip_address' => ['label' => 'IP Address', 'type' => 'string'],
            'created_at' => ['label' => 'Logged At', 'type' => 'datetime'],
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('Reports/CustomBuilder', [
            'available_fields' => self::AVAILABLE_FIELDS,
        ]);
    }

    public function preview(CustomReportPreviewRequest $request): JsonResponse
    {
        $source = $request->input('source', 'files');
        $columns = $request->input('columns', []);
        $conditions = $request->input('conditions', []);
        $groupBy = $request->input('group_by');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $availableFields = self::AVAILABLE_FIELDS[$source] ?? [];
        $validColumns = array_keys($availableFields);

        // Sanitize columns to only allow known fields
        $safeColumns = array_values(array_intersect($columns, $validColumns));

        if (empty($safeColumns)) {
            $safeColumns = array_slice($validColumns, 0, 5);
        }

        // Separate tag (relationship) columns from real DB columns
        $requestedTagColumns = array_values(array_intersect($safeColumns, self::TAG_COLUMNS));
        $dbColumns = array_values(array_diff($safeColumns, self::TAG_COLUMNS));

        /** @var Builder $query */
        $query = match ($source) {
            'requests' => Requests::query(),
            'users' => User::withTrashed(),
            'disposal_batches' => DisposalBatch::query(),
            'audit_logs' => AuditLog::query(),
            default => File::query(),
        };

        // Always include 'id' when tags are requested so the relationship can load
        $selectColumns = $dbColumns;
        if (! empty($requestedTagColumns) && $source === 'files') {
            $query->with('tags:id,name,type');
            if (! in_array('id', $selectColumns, true)) {
                array_unshift($selectColumns, 'id');
            }
        }

        $query->select($selectColumns);

        /** @var array<int, string>|null $appliedGroupColumns */
        $appliedGroupColumns = null;

        // Apply conditions
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (! $field || ! in_array($field, $validColumns, true)) {
                continue;
            }

            // Tag columns require relationship-based queries
            if (in_array($field, self::TAG_COLUMNS, true)) {
                $tagType = $field === 'metadata_tags' ? 'metadata' : 'physical_location';
                match ($operator) {
                    'contains' => $query->whereHas('tags', fn (Builder $t) => $t->where('tags.type', $tagType)->where('tags.name', 'LIKE', "%{$value}%")),
                    'not_contains' => $query->whereDoesntHave('tags', fn (Builder $t) => $t->where('tags.type', $tagType)->where('tags.name', 'LIKE', "%{$value}%")),
                    'equals' => $query->whereHas('tags', fn (Builder $t) => $t->where('tags.type', $tagType)->where('tags.name', $value)),
                    'not_equals' => $query->whereDoesntHave('tags', fn (Builder $t) => $t->where('tags.type', $tagType)->where('tags.name', $value)),
                    'is_null' => $query->whereDoesntHave('tags', fn (Builder $t) => $t->where('tags.type', $tagType)),
                    'is_not_null' => $query->whereHas('tags', fn (Builder $t) => $t->where('tags.type', $tagType)),
                    default => null,
                };

                continue;
            }

            match ($operator) {
                'equals' => $query->where($field, $value),
                'not_equals' => $query->where($field, '!=', $value),
                'contains' => $query->where($field, 'LIKE', "%{$value}%"),
                'not_contains' => $query->where($field, 'NOT LIKE', "%{$value}%"),
                'greater_than' => $query->where($field, '>', $value),
                'less_than' => $query->where($field, '<', $value),
                'between' => is_array($value) && count($value) === 2 ? $query->whereBetween($field, $value) : null,
                'in' => is_array($value) ? $query->whereIn($field, $value) : null,
                'is_null' => $query->whereNull($field),
                'is_not_null' => $query->whereNotNull($field),
                default => null,
            };
        }

        // Apply groupBy (only for real DB columns)
        if ($groupBy && in_array($groupBy, $validColumns, true) && ! in_array($groupBy, self::TAG_COLUMNS, true)) {
            // Keep grouped queries compatible with only_full_group_by.
            $appliedGroupColumns = $selectColumns;

            if (empty($appliedGroupColumns)) {
                $appliedGroupColumns = [$groupBy];
            }

            if (! in_array($groupBy, $appliedGroupColumns, true)) {
                $appliedGroupColumns[] = $groupBy;
            }

            $query->groupBy($appliedGroupColumns);
        }

        // Apply sorting (only for real DB columns)
        if ($sortBy && in_array($sortBy, $validColumns, true) && ! in_array($sortBy, self::TAG_COLUMNS, true)) {
            // For grouped queries, only sort by grouped columns to avoid SQL mode errors.
            if ($appliedGroupColumns !== null && ! in_array($sortBy, $appliedGroupColumns, true)) {
                $sortBy = $groupBy && in_array($groupBy, $appliedGroupColumns, true) ? $groupBy : null;
            }

            if ($sortBy) {
                $query->orderBy($sortBy, $sortDir);
            }
        }

        $results = $query->paginate($perPage)->withQueryString();

        // Append tag values as comma-separated strings to each row
        $items = collect($results->items())->map(function ($row) use ($requestedTagColumns) {
            $item = $row->toArray();

            if (in_array('metadata_tags', $requestedTagColumns, true)) {
                $item['metadata_tags'] = $row->tags?->where('type', 'metadata')->pluck('name')->implode(', ') ?? '';
            }
            if (in_array('physical_location_tags', $requestedTagColumns, true)) {
                $item['physical_location_tags'] = $row->tags?->where('type', 'physical_location')->pluck('name')->implode(', ') ?? '';
            }

            // Remove internal fields not requested by the user
            unset($item['id'], $item['tags']);

            return $item;
        })->toArray();

        return response()->json([
            'data' => $items,
            'columns' => collect($safeColumns)->map(fn ($col) => [
                'key' => $col,
                'label' => $availableFields[$col]['label'] ?? $col,
                'type' => $availableFields[$col]['type'] ?? 'string',
            ])->values(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
                'per_page' => $results->perPage(),
            ],
        ]);
    }
}
