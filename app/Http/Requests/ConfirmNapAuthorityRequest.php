<?php

namespace App\Http\Requests;

use App\Models\DisposalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmNapAuthorityRequest extends FormRequest
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
            'actual_mode_of_disposal' => ['required', 'string', Rule::in(array_keys(DisposalBatch::modeDictionary()))],
            'nap_form_5_received' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{actual_mode_of_disposal: string, nap_form_5_received: bool}
     */
    public function payload(): array
    {
        /** @var array{actual_mode_of_disposal: string, nap_form_5_received: bool|int|string} $validated */
        $validated = $this->validated();

        $validated['nap_form_5_received'] = (bool) $validated['nap_form_5_received'];

        return $validated;
    }
}
