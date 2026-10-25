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
                'in:critical,health,accident,lost',
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
                'nullable',
                'numeric',
                'between:-90,90',
                'required_with:longitude',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
                'required_with:latitude',
            ],
        ];
    }
}