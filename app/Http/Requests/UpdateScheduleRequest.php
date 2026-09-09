<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_number' => ['sometimes', 'integer', 'min:1'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'date_format:H:i'],
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'keterangan' => ['sometimes', 'nullable', 'string'],
            'pic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status_override' => [
                'sometimes',
                'nullable',
                'in:completed,in_progress,upcoming',
            ],
        ];
    }
}