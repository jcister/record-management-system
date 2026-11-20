<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteDisposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['required', 'uuid', 'distinct', 'exists:files,id'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return list<string>
     */
    public function fileIds(): array
    {
        return $this->validated()['file_ids'];
    }

    public function completedAt(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['completed_at'] ?? null;
    }
}
