<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJamaahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jamaahId = $this->route('jamaah')?->id;

        return [
            'nik' => ['sometimes', 'string', 'size:16', 'unique:jamaah,nik,' . $jamaahId],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'date'],
            'gender' => ['sometimes', 'in:L,P'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'passport_expiry_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,archived'],
        ];
    }
}
