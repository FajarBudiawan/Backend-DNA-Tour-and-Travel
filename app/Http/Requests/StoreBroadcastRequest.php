<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi dievaluasi di controller/policy
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', Rule::in(['normal', 'urgent'])],
            'target_type' => ['required', 'string', Rule::in(['all', 'kloter'])],
            'kloter_ids' => ['nullable', 'array'],
            'kloter_ids.*' => ['string'],
            'send_to_family' => ['nullable', 'boolean'],
        ];
    }
}
