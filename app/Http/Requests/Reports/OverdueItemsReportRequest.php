<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverdueItemsReportRequest extends FormRequest
{
    public const SEVERITY_OPTIONS = ['all', 'low', 'medium', 'high'];

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
            'department_id' => ['nullable', 'exists:departments,id'],
            'severity' => ['nullable', Rule::in(self::SEVERITY_OPTIONS)],
            'metadata_tags' => ['nullable', 'array'],
            'metadata_tags.*' => ['integer', 'exists:tags,id'],
            'physical_location_tags' => ['nullable', 'array'],
            'physical_location_tags.*' => ['integer', 'exists:tags,id'],
            'per_page' => ['nullable', 'integer', 'in:15,30,50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'department_id',
            'severity',
            'metadata_tags',
            'physical_location_tags',
            'per_page',
        ]);
    }
}
