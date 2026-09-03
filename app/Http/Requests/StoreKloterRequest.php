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
            // WAJIB
            'name'             => ['required', 'string', 'max:150'],

            // NULLABLE — boleh diisi belakangan sesuai keputusan klien
            'package_id'       => ['nullable', 'uuid', 'exists:packages,id'],
            'code'             => ['nullable', 'string', 'max:30', 'unique:kloters,code'],
            'flight_code'      => ['nullable', 'string', 'max:50'],
            'departure_date'   => ['nullable', 'date'],
            'return_date'      => ['nullable', 'date', 'after_or_equal:departure_date'],
            'hotel_makkah_id'  => ['nullable', 'uuid', 'exists:hotels,id'],
            'hotel_madinah_id' => ['nullable', 'uuid', 'exists:hotels,id'],
            'status'           => ['nullable', 'in:draft,active,ready,completed,cancelled'],

            // Staf kloter — plain text, bukan FK
            'tour_leader'      => ['nullable', 'string', 'max:200'],
            'mutawif_local'    => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Nama kloter wajib diisi.',
            'package_id.exists'       => 'Paket umrah yang dipilih tidak ditemukan.',
            'hotel_makkah_id.exists'  => 'Hotel Makkah yang dipilih tidak ditemukan.',
            'hotel_madinah_id.exists' => 'Hotel Madinah yang dipilih tidak ditemukan.',
            'return_date.after_or_equal' => 'Tanggal kepulangan harus sama dengan atau setelah tanggal keberangkatan.',
        ];
    }
}
