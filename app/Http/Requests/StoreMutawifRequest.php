<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMutawifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'unique:mutawifs,code',
            ],
            'name' => [
                'required',
                'string',
                'max:200',
            ],
            'language' => [
                'required',
                'string',
                'max:250',
            ],
            'experience' => [
                'nullable',
                'string',
                'max:250',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'standby']),
            ],
        ];
    }
}