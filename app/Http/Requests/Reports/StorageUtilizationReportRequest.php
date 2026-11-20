<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorageUtilizationReportRequest extends FormRequest
{
    public const GROUP_BY_OPTIONS = ['department', 'folder'];

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
            'folder_id' => ['nullable', 'uuid', 'exists:folders,id'],
            'group_by' => ['nullable', Rule::in(self::GROUP_BY_OPTIONS)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only(['department_id', 'folder_id', 'group_by']);
    }
}
