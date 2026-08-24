<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKloterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'uuid', 'exists:packages,id'],
            'code' => ['nullable', 'string', 'max:30', 'unique:kloters,code'],
            'departure_date' => ['required', 'date'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'hotel_makkah_id' => ['nullable', 'uuid', 'exists:hotels,id'],
            'hotel_madinah_id' => ['nullable', 'uuid', 'exists:hotels,id'],
            'status' => ['nullable', 'in:draft,ready,active,completed,cancelled'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
