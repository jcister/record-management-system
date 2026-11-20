<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public const TYPES = [
        'records-inventory',
        'summary',
        'storage-utilization',
        'document-access-frequency',
        'retrieval-frequency',
        'custom',
    ];

    public const FORMATS = ['excel', 'pdf'];

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
            'type' => ['required', Rule::in(self::TYPES)],
            'format' => ['required', Rule::in(self::FORMATS)],
            // Forward any additional filter params — validated loosely
            'search' => ['nullable', 'string', 'max:191'],
            'department_id' => ['nullable'],
            'folder_id' => ['nullable'],
            'status' => ['nullable', 'string'],
            'retention_status' => ['nullable', 'string'],
            'retention_duration' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
            'is_record' => ['nullable'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'metadata_tags' => ['nullable', 'array'],
            'metadata_tags.*' => ['integer'],
            'physical_location_tags' => ['nullable', 'array'],
            'physical_location_tags.*' => ['integer'],
            'severity' => ['nullable', 'string'],
            'top_n' => ['nullable', 'integer'],
            'group_by' => ['nullable', 'string'],
            // Custom report builder pass-through params
            'source' => ['nullable', 'string'],
            'columns' => ['nullable', 'string'],
            'conditions' => ['nullable', 'string'],
            // Export options
            'report_title' => ['nullable', 'string', 'max:191'],
            'include_cover' => ['nullable'],
            'include_filters' => ['nullable'],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
        ];
    }
}
