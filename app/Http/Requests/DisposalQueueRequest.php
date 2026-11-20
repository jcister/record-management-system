<?php

namespace App\Http\Requests;

use App\Support\RetentionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisposalQueueRequest extends FormRequest
{
    /**
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [15, 30, 50];

    /**
     * @var list<string>
     */
    public const RETENTION_DURATION_OPTIONS = [
        RetentionSchedule::DURATION_ONE_YEAR,
        RetentionSchedule::DURATION_TEN_YEARS,
        RetentionSchedule::DURATION_PERPETUAL,
    ];

    /**
     * @var list<string>
     */
    public const URGENCY_OPTIONS = [
        'all',
        'immediate',
        'soon',
        'scheduled',
        'perpetual',
    ];

    /**
     * @var list<string>
     */
    public const SORT_BY_OPTIONS = [
        'record_id',
        'file_name',
        'author',
        'date_released',
        'retention_expires',
        'urgency',
    ];

    /**
     * @var list<string>
     */
    public const SORT_DIRECTION_OPTIONS = ['asc', 'desc'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'root_folder_id' => ['nullable', 'uuid', 'exists:folders,id'],
            'retention_duration' => ['nullable', Rule::in(self::RETENTION_DURATION_OPTIONS)],
            'physical_location' => ['nullable', 'string', 'max:255'],
            'expires_from' => ['nullable', 'date'],
            'expires_to' => ['nullable', 'date'],
            'released_from' => ['nullable', 'date'],
            'released_to' => ['nullable', 'date'],
            'urgency' => ['nullable', Rule::in(self::URGENCY_OPTIONS)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'per_page' => ['required', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
            'sort_by' => ['nullable', Rule::in(self::SORT_BY_OPTIONS)],
            'sort_direction' => ['nullable', Rule::in(self::SORT_DIRECTION_OPTIONS)],
        ];
    }

    /**
     * Normalize input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeString($this->input('search')),
            'root_folder_id' => $this->normalizeUuid($this->input('root_folder_id')),
            'retention_duration' => $this->normalizeString($this->input('retention_duration')),
            'physical_location' => $this->normalizeString($this->input('physical_location')),
            'expires_from' => $this->normalizeDate($this->input('expires_from')),
            'expires_to' => $this->normalizeDate($this->input('expires_to')),
            'released_from' => $this->normalizeDate($this->input('released_from')),
            'released_to' => $this->normalizeDate($this->input('released_to')),
            'urgency' => $this->normalizeString($this->input('urgency')),
            'department_id' => $this->normalizeInteger($this->input('department_id')),
            'per_page' => $this->normalizePerPage($this->input('per_page')),
            'sort_by' => $this->normalizeString($this->input('sort_by')),
            'sort_direction' => $this->normalizeString($this->input('sort_direction')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search' => $data['search'] ?? null,
            'root_folder_id' => $data['root_folder_id'] ?? null,
            'retention_duration' => $data['retention_duration'] ?? null,
            'physical_location' => $data['physical_location'] ?? null,
            'expires_from' => $data['expires_from'] ?? null,
            'expires_to' => $data['expires_to'] ?? null,
            'released_from' => $data['released_from'] ?? null,
            'released_to' => $data['released_to'] ?? null,
            'urgency' => $data['urgency'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'per_page' => $data['per_page'] ?? self::PER_PAGE_OPTIONS[0],
            'sort_by' => $data['sort_by'] ?? null,
            'sort_direction' => $data['sort_direction'] ?? null,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeUuid(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        return $trimmed;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePerPage(mixed $value): int
    {
        $options = self::PER_PAGE_OPTIONS;
        $default = $options[0];

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = (int) $value;

        return in_array($normalized, $options, true) ? $normalized : $default;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }
}
