<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->normalizeMonthYearInput($this->input('start_date')),
            'end_date' => $this->normalizeMonthYearInput($this->input('end_date'), true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:255'],
            'file_number' => ['nullable', 'string', 'max:255'],
            'request_title' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'purpose' => ['required', 'string', 'max:1000'],
            'priority' => ['required', Rule::in(['standard', 'urgent'])],
            'required_format' => ['required', Rule::in([
                'Retrieve Original Copy',
                'Reproduce Hardcopy',
                'Electronic Copy Only',
            ])],
            'department_id' => ['required', 'string', 'exists:departments,id'],
            'request_id' => ['required', 'uuid', 'unique:requests,request_id'],
            'requires_scan' => ['required', 'boolean'],
            'metadata_tag_ids' => ['nullable', 'array'],
            'metadata_tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('type', 'metadata'),
            ],
        ];
    }

    private function normalizeMonthYearInput(mixed $value, bool $useEndOfMonth = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^(0?[1-9]|1[0-2])\/\d{4}$/', $trimmed) !== 1) {
            return $trimmed;
        }

        [$month, $year] = explode('/', $trimmed, 2);
        $month = (int) $month;
        $year = (int) $year;

        try {
            $date = CarbonImmutable::create($year, $month, 1);
        } catch (\Exception) {
            return $trimmed;
        }

        if ($useEndOfMonth) {
            return $date->endOfMonth()->toDateString();
        }

        return $date->toDateString();
    }
}
