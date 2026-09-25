<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSosIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jamaah_id' => [
                'nullable',
                'uuid',
                Rule::exists('jamaah', 'id'),
            ],

            'type' => [
                'required',
                'string',
                'max:50',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'location_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}