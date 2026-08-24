<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKloterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kloterId = $this->route('kloter')?->id;

        return [
            'package_id' => ['sometimes', 'uuid', 'exists:packages,id'],
            'code' => ['sometimes', 'string', 'max:30', 'unique:kloters,code,' . $kloterId],
            'departure_date' => ['sometimes', 'date'],
            'return_date' => ['sometimes', 'date', 'after_or_equal:departure_date'],
            'hotel_makkah_id' => ['nullable', 'uuid', 'exists:hotels,id'],
            'hotel_madinah_id' => ['nullable', 'uuid', 'exists:hotels,id'],
            'status' => ['sometimes', 'in:draft,ready,active,completed,cancelled'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
