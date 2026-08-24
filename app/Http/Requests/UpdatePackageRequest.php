<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:draft,published,inactive'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}
