<?php

namespace App\Exports\Reports;

use App\Models\AuditLog;
use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Requests;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private const TAG_COLUMNS = ['metadata_tags', 'physical_location_tags'];

    private const AVAILABLE_FIELDS = [
        'files' => [
            'record_id' => ['label' => 'Record ID', 'type' => 'string'],
            'file_name' => ['label' => 'File Name', 'type' => 'string'],
            'author' => ['label' => 'Author', 'type' => 'string'],
            'status' => ['label' => 'Status', 'type' => 'string'],
            'retention_status' => ['label' => 'Retention Status', 'type' => 'string'],
            'retention_duration' => ['label' => 'Retention Duration', 'type' => 'string'],
            'is_record' => ['label' => 'Document Type', 'type' => 'classification', 'options' => [['value' => '1', 'label' => 'Record'], ['value' => '0', 'label' => 'File']]],
            'metadata_tags' => ['label' => 'Keywords / Metadata Tags', 'type' => 'tags'],
            'physical_location_tags' => ['label' => 'Physical Location Tags', 'type' => 'tags'],
            'date_created' => ['label' => 'Date Created', 'type' => 'date'],
            'date_released' => ['label' => 'Date Released', 'type' => 'date'],
            'retention_expires_at' => ['label' => 'Retention Expires At', 'type' => 'date'],
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

    private readonly string $source;

    /** @var array<int, string> */
    private readonly array $safeColumns;

    /** @var array<string, array<string, string>> */
    private readonly array $availableFields;

    /** @var array<int, array<string, mixed>> */
    private readonly array $conditions;

    private readonly ?string $groupBy;

    private readonly string $sortBy;

    private readonly string $sortDir;

    public function __construct(private readonly array $filters = [])
    {
        $this->source = $filters['source'] ?? 'files';
        $this->availableFields = self::AVAILABLE_FIELDS[$this->source] ?? [];
        $validColumns = array_keys($this->availableFields);

        // columns arrives as a comma-separated string from the export URL
        $requestedColumns = $filters['columns'] ?? '';
        $columnList = is_array($requestedColumns)
            ? $requestedColumns
            : array_filter(array_map('trim', explode(',', (string) $requestedColumns)));

        $this->safeColumns = array_values(array_intersect($columnList, $validColumns))
            ?: array_slice($validColumns, 0, 5);

        // conditions arrives as a JSON-encoded string from the export URL
        $rawConditions = $filters['conditions'] ?? '[]';
        $this->conditions = is_array($rawConditions)
            ? $rawConditions
            : (json_decode((string) $rawConditions, true) ?? []);

        $this->groupBy = ! empty($filters['group_by']) ? (string) $filters['group_by'] : null;
        $this->sortBy = ! empty($filters['sort_by']) ? (string) $filters['sort_by'] : 'created_at';
        $this->sortDir = in_array($filters['sort_dir'] ?? 'desc', ['asc', 'desc'], true)
            ? (string) $filters['sort_dir']
            : 'desc';
    }

    public function query(): Builder
    {
        return $this->buildQuery();
    }

    public function map(mixed $row): array
    {
        return collect($this->safeColumns)
            ->map(fn (string $col) => $this->formatValue($col, null, $row))
            ->toArray();
    }

    public function headings(): array
    {
        return collect($this->safeColumns)
            ->map(fn (string $col) => $this->availableFields[$col]['label'] ?? $col)
            ->toArray();
    }

    public function title(): string
    {
        return 'Custom Report';
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($this->safeColumns));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(): array
    {
        $rows = $this->buildQuery()
            ->limit(500)
            ->get()
            ->map(fn (mixed $row) => collect($this->safeColumns)
                ->mapWithKeys(fn (string $col) => [$col => $this->formatValue($col, null, $row)])
                ->toArray()
            );

        return [
            'columns' => collect($this->safeColumns)->map(fn (string $col) => [
                'key' => $col,
                'label' => $this->availableFields[$col]['label'] ?? $col,
            ])->toArray(),
            'rows' => $rows->toArray(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    private function buildQuery(): Builder
    {
        $validColumns = array_keys($this->availableFields);

        // Separate tag (relationship) columns from real DB columns
        $requestedTagColumns = array_values(array_intersect($this->safeColumns, self::TAG_COLUMNS));
        $dbColumns = array_values(array_diff($this->safeColumns, self::TAG_COLUMNS));

        /** @var Builder $query */
        $query = match ($this->source) {
            'requests' => Requests::query(),
            'users' => User::withTrashed(),
            'disposal_batches' => DisposalBatch::query(),
            'audit_logs' => AuditLog::query(),
            default => File::query(),
        };

        // Always include 'id' when tags are requested so the relationship can load
        $selectColumns = $dbColumns;
        if (! empty($requestedTagColumns) && $this->source === 'files') {
            $query->with('tags:id,name,type');
            if (! in_array('id', $selectColumns, true)) {
                array_unshift($selectColumns, 'id');
            }
        }

        $query->select($selectColumns);

        foreach ($this->conditions as $condition) {
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

        if ($this->groupBy && in_array($this->groupBy, $validColumns, true) && ! in_array($this->groupBy, self::TAG_COLUMNS, true)) {
            $query->groupBy($this->groupBy);
        }

        if ($this->sortBy && in_array($this->sortBy, $validColumns, true) && ! in_array($this->sortBy, self::TAG_COLUMNS, true)) {
            $query->orderBy($this->sortBy, $this->sortDir);
        }

        return $query;
    }

    private function formatValue(string $column, mixed $value, mixed $row = null): mixed
    {
        // Handle tag columns from the loaded relationship
        if (in_array($column, self::TAG_COLUMNS, true)) {
            if (! $row || ! $row->relationLoaded('tags')) {
                return '';
            }
            $tagType = $column === 'metadata_tags' ? 'metadata' : 'physical_location';

            return $row->tags->where('type', $tagType)->pluck('name')->implode(', ');
        }

        $value ??= $row->{$column} ?? null;

        if ($value === null) {
            return null;
        }

        // Blank out retention_status if retention_duration is perpetual
        if ($column === 'retention_status' && $row && isset($row->retention_duration) && $row->retention_duration === 'perpetual') {
            return '';
        }

        // Format retention_duration
        if ($column === 'retention_duration') {
            return match ($value) {
                'one_year' => '1 Year',
                'ten_years' => '10 Years',
                'perpetual' => 'Perpetual',
                default => $value,
            };
        }

        // Format is_record as classification
        if ($column === 'is_record') {
            return $value ? 'Record' : 'File';
        }

        // Format retention_status into canonical disposal buckets
        if ($column === 'retention_status') {
            $normalized = strtolower((string) $value);

            return match ($normalized) {
                'active', 'archived' => 'ACTIVE',
                'ready_for_disposal' => 'READY TO BE DISPOSED',
                'disposed' => 'DISPOSED',
                default => $value,
            };
        }

        // Format status fields with Pascal Case
        if (in_array($column, ['status', 'retention_status', 'priority', 'mode_of_disposal'], true)) {
            return str_replace('_', ' ', ucwords(str_replace('_', ' ', (string) $value)));
        }

        return $value;
    }
}
