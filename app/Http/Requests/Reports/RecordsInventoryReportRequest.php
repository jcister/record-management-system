<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordsInventoryReportRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [20, 30, 50, 100];

    public const STATUS_OPTIONS = ['pending', 'approved', 'rejected'];

    public const RETENTION_STATUS_OPTIONS = ['active', 'archived', 'ready_for_disposal', 'disposed'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'folder_id' => ['nullable', 'uuid', 'exists:folders,id'],
            'status' => ['nullable', Rule::in(self::STATUS_OPTIONS)],
            'retention_status' => ['nullable', Rule::in(self::RETENTION_STATUS_OPTIONS)],
            'is_record' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'metadata_tags' => ['nullable', 'array'],
            'metadata_tags.*' => ['integer', 'exists:tags,id'],
            'physical_location_tags' => ['nullable', 'array'],
            'physical_location_tags.*' => ['integer', 'exists:tags,id'],
            'metadata_tag_search' => ['nullable', 'string', 'max:191'],
            'physical_location_tag_search' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'search',
            'department_id',
            'folder_id',
            'status',
            'retention_status',
            'is_record',
            'date_from',
            'date_to',
            'metadata_tags',
            'physical_location_tags',
            'metadata_tag_search',
            'physical_location_tag_search',
            'per_page',
        ]);
    }
}
