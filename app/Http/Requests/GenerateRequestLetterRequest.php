<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRequestLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'letter_date' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_first_name' => ['required', 'string', 'max:120'],
            'recipient_middle_name' => ['nullable', 'string', 'max:120'],
            'recipient_last_name' => ['required', 'string', 'max:120'],
            'recipient_extension' => ['nullable', 'string', 'max:60'],
            'recipient_position' => ['required', 'string', 'max:255'],
            'recipient_designation' => ['required', 'string', 'max:255'],
            'address_1' => ['required', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'honorific' => ['nullable', 'string', 'max:20'],
            'user_honorific' => ['nullable', 'string', 'max:20'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'user_position' => ['nullable', 'string', 'max:255'],
            'control_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'honorific' => 'prefix',
            'user_honorific' => 'requesting officer prefix',
        ];
    }
}
