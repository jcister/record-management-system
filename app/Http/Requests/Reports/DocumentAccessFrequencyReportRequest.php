<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentAccessFrequencyReportRequest extends FormRequest
{
    public const TOP_N_OPTIONS = [10, 20, 50, 100];

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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'top_n' => ['nullable', Rule::in(self::TOP_N_OPTIONS)],
            'metadata_tags' => ['nullable', 'array'],
            'metadata_tags.*' => ['integer', 'exists:tags,id'],
            'physical_location_tags' => ['nullable', 'array'],
            'physical_location_tags.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'date_from',
            'date_to',
            'department_id',
            'top_n',
            'metadata_tags',
            'physical_location_tags',
        ]);
    }
}
