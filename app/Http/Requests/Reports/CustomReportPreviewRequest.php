<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomReportPreviewRequest extends FormRequest
{
    public const SOURCES = ['files', 'requests', 'users', 'disposal_batches', 'audit_logs'];

    public const OPERATORS = ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'between', 'in', 'is_null', 'is_not_null'];

    public const SORT_DIRECTIONS = ['asc', 'desc'];

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
            'source' => ['required', Rule::in(self::SOURCES)],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', Rule::in(self::OPERATORS)],
            'conditions.*.value' => ['nullable'],
            'group_by' => ['nullable', 'string'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', Rule::in(self::SORT_DIRECTIONS)],
            'per_page' => ['nullable', 'integer', 'in:15,30,50,100'],
        ];
    }
}
