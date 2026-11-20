<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisposalBatchRequest extends FormRequest
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
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'completed_at' => [
                'nullable',
                'date',
                'before_or_equal:now',
                function ($attribute, $value, $fail) {
                    $scheduledAt = $this->input('scheduled_at');
                    if ($value && $scheduledAt && $value < $scheduledAt) {
                        $fail('The completion date must be after the scheduled date.');
                    }
                },
            ],
            'scheduled_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function scheduledAt(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['scheduled_at'] ?? null;
    }

    public function completedAt(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['completed_at'] ?? null;
    }

    public function scheduledNote(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['scheduled_note'] ?? null;
    }
}
