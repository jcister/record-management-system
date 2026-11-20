<?php

namespace App\Http\Requests;

use App\Support\CoaContactDefaults;
use Illuminate\Foundation\Http\FormRequest;

class ApproveDisposalBatchRequest extends FormRequest
{
    public const MAX_PICTURES = 5;

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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:1000'],
            'coa_name' => ['required', 'string', 'max:255'],
            'coa_position' => ['required', 'string', 'max:255'],
            'coa_designation' => ['required', 'string', 'max:255'],
            'pictures' => ['nullable', 'array', 'max:'.self::MAX_PICTURES],
            'pictures.*' => ['file', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array{
     *     approval_notes: string|null,
     *     coa_name: string,
     *     coa_position: string,
     *     coa_designation: string,
     *     pictures: list<\Illuminate\Http\UploadedFile>|null
     * }
     */
    public function approvalData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'approval_notes' => $validated['approval_notes'] ?? null,
            'coa_name' => $validated['coa_name'],
            'coa_position' => $validated['coa_position'] ?? CoaContactDefaults::position(),
            'coa_designation' => $validated['coa_designation'] ?? CoaContactDefaults::designation(),
            'pictures' => $this->file('pictures', []),
        ];
    }
}
