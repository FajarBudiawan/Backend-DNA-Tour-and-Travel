<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'message' => ['sometimes', 'required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'in:normal,urgent'],
        ];
    }
}
