<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registrationId = $this->route('registration')?->id;

        return [
            'full_name' => ['sometimes', 'string', 'max:150'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'nik' => [
                'sometimes',
                'string',
                'size:16',
                'unique:registrations,nik,' . $registrationId,
            ],
            'phone' => ['sometimes', 'string', 'max:20'],
            'birth_date' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', 'in:L,P'],
            'registration_date' => ['sometimes', 'date'],
            'departure_date' => ['nullable', 'date'],
            'package_id' => ['sometimes', 'uuid', 'exists:packages,id'],
            'kloter_id' => ['nullable', 'uuid', 'exists:kloters,id'],
            'meningitis_vaccine_status' => ['sometimes', 'in:belum_vaksin,sudah_vaksin'],
            'photo_status' => ['sometimes', 'in:belum_ada,sudah_menyerahkan'],
            'total_package_cost' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:unpaid,dp,dp_paid,paid,fully_paid,cancelled,converted'],
            'equipments' => ['nullable', 'array'],
            'equipments.*.equipment_name' => ['required_with:equipments', 'string', 'max:100'],
            'equipments.*.is_received' => ['required_with:equipments', 'boolean'],
        ];
    }
}
