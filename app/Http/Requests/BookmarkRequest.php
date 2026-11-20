<?php

namespace App\Http\Requests;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookmarkRequest extends FormRequest
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
        return [
            'bookmarkable_type' => [
                'required',
                'string',
                Rule::in([Folder::class, File::class]),
            ],
            'bookmarkable_id' => ['required', 'uuid'],
        ];
    }
}
