<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login_id' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('tour_leaders', 'login_id')->ignore($this->route('tour_leader')),
            ],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'certification_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('tour_leaders', 'certification_number')->ignore($this->route('tour_leader')),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'experience' => ['sometimes', 'nullable', 'string'],
            'performance' => ['sometimes', 'nullable', 'string'],
            'status' => [
                'sometimes',
                'in:active,resting,standby,inactive',
            ],
        ];
    }
}