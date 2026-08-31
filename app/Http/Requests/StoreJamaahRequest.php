<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJamaahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik'               => ['required', 'string', 'size:16', 'unique:jamaah,nik'],
            'full_name'         => ['required', 'string', 'max:150'],
            'birth_date'        => ['nullable', 'date'],
            'gender'            => ['nullable', 'in:L,P'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:200'],
            'status'            => ['nullable', 'in:active,archived'],
        ];
    }
}
