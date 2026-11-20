<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCoaSubmissionRequest extends FormRequest
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
            'report_submitted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array{report_submitted: bool}
     */
    public function payload(): array
    {
        /** @var array{report_submitted: bool|string|int} $validated */
        $validated = $this->validated();

        $validated['report_submitted'] = (bool) $validated['report_submitted'];

        return $validated;
    }
}
