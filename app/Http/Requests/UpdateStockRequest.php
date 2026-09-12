<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:200',
            ],

            'category' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'quantity' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'min_stock' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'unit' => [
                'sometimes',
                'string',
                'max:30',
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'sizes' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'sizes.*.size' => [
                'required',
                'string',
                'max:20',
            ],

            'sizes.*.quantity' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}