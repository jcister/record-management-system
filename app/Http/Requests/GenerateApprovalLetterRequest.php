<?php

namespace App\Http\Requests;

use App\Support\PersonNameFormatter;
use Illuminate\Foundation\Http\FormRequest;

class GenerateApprovalLetterRequest extends FormRequest
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
            'approval_notes' => ['nullable', 'string', 'max:1000'],
            'letter_reference' => ['nullable', 'string', 'max:100'],
            'letter_date' => ['required', 'string', 'max:100'],
            'actual_disposal_date' => ['required', 'string', 'max:100'],
            'recipient_first_name' => ['required', 'string', 'max:255'],
            'recipient_middle_name' => ['nullable', 'string', 'max:255'],
            'recipient_last_name' => ['required', 'string', 'max:255'],
            'recipient_extension' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_position' => ['nullable', 'string', 'max:255'],
            'recipient_designation' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:500'],
            'honorific' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array{
     *     approval_notes: string|null,
     *     letter_reference: string|null,
     *     letter_date: string,
     *     actual_disposal_date: string,
     *     recipient_first_name: string,
     *     recipient_middle_name: string|null,
     *     recipient_last_name: string,
     *     recipient_extension: string|null,
     *     recipient_name: string,
     *     recipient_position: string|null,
     *     recipient_designation: string|null,
     *     recipient_address: string|null,
     *     honorific: string|null
     * }
     */
    public function letterData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $recipientName = PersonNameFormatter::compose(
            $validated['recipient_first_name'] ?? '',
            $validated['recipient_middle_name'] ?? null,
            $validated['recipient_last_name'] ?? '',
            $validated['recipient_extension'] ?? null,
        );

        return [
            'approval_notes' => $validated['approval_notes'] ?? null,
            'letter_reference' => $validated['letter_reference'] ?? null,
            'letter_date' => $validated['letter_date'],
            'actual_disposal_date' => $validated['actual_disposal_date'],
            'recipient_first_name' => $validated['recipient_first_name'],
            'recipient_middle_name' => $validated['recipient_middle_name'] ?? null,
            'recipient_last_name' => $validated['recipient_last_name'],
            'recipient_extension' => $validated['recipient_extension'] ?? null,
            'recipient_name' => $recipientName,
            'recipient_position' => $validated['recipient_position'] ?? null,
            'recipient_designation' => $validated['recipient_designation'] ?? null,
            'recipient_address' => $validated['recipient_address'] ?? null,
            'honorific' => $validated['honorific'] ?? null,
        ];
    }
}
