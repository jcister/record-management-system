<?php

namespace App\Http\Requests\Reports;

use App\Support\RetentionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetentionComplianceReportRequest extends FormRequest
{
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
            'retention_duration' => ['nullable', Rule::in([
                RetentionSchedule::DURATION_ONE_YEAR,
                RetentionSchedule::DURATION_TEN_YEARS,
                RetentionSchedule::DURATION_PERPETUAL,
            ])],
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
            'retention_duration',
            'metadata_tags',
            'physical_location_tags',
            'per_page',
        ]);
    }
}
