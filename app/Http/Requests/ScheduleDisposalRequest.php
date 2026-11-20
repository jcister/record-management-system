<?php

namespace App\Http\Requests;

use App\Models\DisposalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleDisposalRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'mode_of_disposal' => ['required', 'string', Rule::in([
                DisposalBatch::MODE_SALE,
                DisposalBatch::MODE_LANDFILL,
            ])],
        ];
    }

    /**
     * @return list<string>
     */
    public function fileIds(): array
    {
        return $this->validated()['file_ids'];
    }

    public function note(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['note'] ?? null;
    }

    public function scheduledAt(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['scheduled_at'] ?? null;
    }

    public function modeOfDisposal(): string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['mode_of_disposal'];
    }
}
