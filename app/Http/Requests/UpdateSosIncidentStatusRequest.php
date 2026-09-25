<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSosIncidentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'triggered',
                    'acknowledged',
                    'in_action',
                    'resolved',
                    'false_alarm',
                ]),
            ],
        ];
    }
}