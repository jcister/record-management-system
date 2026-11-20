<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $institutionId = $this->route('institution')?->id;

        $isCreate = $this->isMethod('post');

        return [
            'uii' => [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('institutions', 'uii')->ignore($institutionId),
            ],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'region' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'contact_info' => ['sometimes', 'nullable', 'array'],
            'contact_info.email' => ['sometimes', 'nullable', 'email'],
            'contact_info.phone' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
