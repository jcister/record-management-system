<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnDisposalBatchToDraftRequest extends FormRequest
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
            'reopen_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reopen_reason.required' => 'Please provide a reason before returning this batch to draft.',
        ];
    }

    public function reopenReason(): string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return trim((string) $validated['reopen_reason']);
    }
}
