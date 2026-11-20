<?php

namespace App\Http\Requests;

use App\Rules\ValidUploadedFile;
use App\Support\DateInput;
use App\Support\FileName;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileRequest extends FormRequest
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
        if ($this->has('file_name')) {
            $this->merge([
                'file_name' => FileName::normalize($this->input('file_name')),
            ]);
        }

        foreach (['date_created', 'date_released'] as $dateField) {
            if ($this->has($dateField)) {
                $normalized = DateInput::normalize($this->input($dateField));

                if ($normalized !== null) {
                    $this->merge([$dateField => $normalized]);
                } elseif ($this->input($dateField) === '') {
                    $this->merge([$dateField => null]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $isFileOnly = $this->input('upload_mode') === 'file_only';
        $allowedRetentionDurations = [
            RetentionSchedule::DURATION_ONE_YEAR,
            RetentionSchedule::DURATION_TEN_YEARS,
            RetentionSchedule::DURATION_PERPETUAL,
        ];
        $allowedRetentionStatuses = RetentionStatus::options();

        $departmentIdRules = $isFileOnly
            ? ['nullable', 'string', 'max:255', 'exists:departments,id']
            : [$isCreate ? 'required' : 'sometimes', 'string', 'max:255', 'exists:departments,id'];

        $recordIdRules = $isFileOnly
            ? ['nullable', 'string', 'max:255']
            : [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('files', 'record_id')->ignore($this->route('file')),
            ];

        $physicalLocationTagIdsRules = $isFileOnly
            ? ['sometimes', 'nullable', 'array']
            : [$isCreate ? 'required' : 'sometimes', 'array', 'min:1'];

        $retentionDurationRules = $isFileOnly
            ? ['sometimes', 'nullable', 'string', Rule::in($allowedRetentionDurations)]
            : [$isCreate ? 'required' : 'sometimes', 'string', Rule::in($allowedRetentionDurations)];

        $retentionStatusRules = ['sometimes', 'nullable', 'string', Rule::in($allowedRetentionStatuses)];

        return [
            'author' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'department_id' => $departmentIdRules,
            'record_id' => $recordIdRules,
            'upload_mode' => ['sometimes', 'nullable', 'in:file_only'],
            'physical_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder_id' => ['sometimes', 'nullable', 'uuid', 'exists:folders,id'],
            'institution_id' => ['sometimes', 'nullable', 'uuid', 'exists:institutions,id'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'series' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'date_created' => ['sometimes', 'nullable', 'date'],
            'date_released' => $isFileOnly
                ? ['sometimes', 'nullable', 'date', 'after_or_equal:date_created']
                : [$isCreate ? 'required' : 'sometimes', 'date', 'after_or_equal:date_created'],
            'file' => [
                $isCreate ? 'nullable' : 'sometimes',
                'nullable',
                'file',
                'max:102400', // Max size 100mb
                new ValidUploadedFile,
            ],
            'file_path' => ['sometimes', 'string', 'max:2048'],
            'file_name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'mime_type' => ['sometimes', 'string', 'max:255'],
            'file_size' => ['sometimes', 'integer', 'min:0'],
            'locked_at' => ['sometimes', 'nullable', 'date'],
            'visibility' => ['sometimes', 'nullable', 'in:public,restricted,private'],
            'status' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'retention_status' => $retentionStatusRules,
            'retention_duration' => $retentionDurationRules,
            'approved_by' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'reviewed_by' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'reviewed_at' => ['sometimes', 'nullable', 'date'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
            'download_count' => ['sometimes', 'integer', 'min:0'],
            'expiration_date' => ['sometimes', 'nullable', 'date'],
            'metadata_tag_ids' => ['sometimes', 'array'],
            'metadata_tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('type', 'metadata')),
            ],
            'physical_location_tag_ids' => $physicalLocationTagIdsRules,
            'physical_location_tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('type', 'physical_location')),
            ],
        ];
    }
}
