<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_number' => ['nullable', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:255'],
            'status_override' => ['nullable', 'in:completed,in_progress,upcoming'],
        ];
    }
}