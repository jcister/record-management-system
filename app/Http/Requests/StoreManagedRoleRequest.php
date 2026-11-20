<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManagedRoleRequest extends FormRequest
{
    private const ORGANIZE_CONTENT_PERMISSION = 'organize-content';

    /**
     * @var array<int, string>
     */
    private const ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS = [
        'review-content',
        'delete-content',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_slugs' => ['sometimes', 'array'],
            'permission_slugs.*' => ['string', Rule::exists('permissions', 'slug')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slugSource = $this->input('slug') ?? $this->input('name');

        if ($slugSource) {
            $this->merge([
                'slug' => Str::slug((string) $slugSource),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $permissionSlugs = $this->permissionSlugs();

            if (! in_array(self::ORGANIZE_CONTENT_PERMISSION, $permissionSlugs, true)
                && $this->hasOrganizeContentDependentPermission($permissionSlugs)) {
                $validator->errors()->add(
                    'permission_slugs',
                    'Review Content and Delete Content permissions require Organize Content.'
                );
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function permissionSlugs(): array
    {
        $permissionSlugs = $this->input('permission_slugs', []);

        if (! is_array($permissionSlugs)) {
            return [];
        }

        return array_values(array_filter($permissionSlugs, static fn (mixed $slug): bool => is_string($slug)));
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function hasOrganizeContentDependentPermission(array $permissionSlugs): bool
    {
        foreach (self::ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS as $dependentPermission) {
            if (in_array($dependentPermission, $permissionSlugs, true)) {
                return true;
            }
        }

        return false;
    }
}
