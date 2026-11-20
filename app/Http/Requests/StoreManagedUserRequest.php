<?php

namespace App\Http\Requests;

use App\Support\PersonNameFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prefix = $this->normalizeOptionalString($this->input('prefix'));
        $firstName = $this->normalizeOptionalString($this->input('first_name'));
        $middleName = $this->normalizeOptionalString($this->input('middle_name'));
        $lastName = $this->normalizeOptionalString($this->input('last_name'));
        $extension = $this->normalizeOptionalString($this->input('extension'));

        $baseName = PersonNameFormatter::compose($firstName, $middleName, $lastName, $extension);
        $name = trim(implode(' ', array_filter([$prefix, $baseName], fn ($segment): bool => $segment !== null && $segment !== '')));

        $payload = [
            'prefix' => $prefix,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'extension' => $extension,
            'name' => $name,
        ];

        if ($this->has('email')) {
            $payload['email'] = Str::lower((string) $this->input('email'));
        }

        $this->merge($payload);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
