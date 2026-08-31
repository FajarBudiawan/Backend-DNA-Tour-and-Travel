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
            'nik'               => ['sometimes', 'string', 'size:16', 'unique:jamaah,nik,' . $jamaahId],
            'full_name'         => ['sometimes', 'string', 'max:150'],
            'birth_date'        => ['sometimes', 'date'],
            'gender'            => ['sometimes', 'in:L,P'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:200'],
            'status'            => ['sometimes', 'in:active,archived'],
        ];
    }
}
