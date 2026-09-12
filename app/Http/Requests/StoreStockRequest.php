<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:200',
            ],

            'category' => [
                'required',
                'string',
                'max:50',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:0',
            ],

            'min_stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'unit' => [
                'required',
                'string',
                'max:30',
            ],

            'location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'sizes' => [
                'nullable',
                'array',
                'min:1',
            ],

            'sizes.*.size' => [
                'required_with:sizes',
                'string',
                'max:20',
            ],

            'sizes.*.quantity' => [
                'required_with:sizes',
                'integer',
                'min:0',
            ],
        ];
    }
}