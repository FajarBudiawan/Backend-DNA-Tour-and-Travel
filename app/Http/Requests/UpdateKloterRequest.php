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
            // sometimes = hanya divalidasi jika dikirim oleh client
            'name'             => ['sometimes', 'string', 'max:150'],
            'package_id'       => ['sometimes', 'nullable', 'uuid', 'exists:packages,id'],
            'code'             => ['sometimes', 'nullable', 'string', 'max:30', 'unique:kloters,code,' . $kloterId],
            'flight_code'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'departure_date'   => ['sometimes', 'nullable', 'date'],
            'return_date'      => ['sometimes', 'nullable', 'date', 'after_or_equal:departure_date'],
            'hotel_makkah_id'  => ['sometimes', 'nullable', 'uuid', 'exists:hotels,id'],
            'hotel_madinah_id' => ['sometimes', 'nullable', 'uuid', 'exists:hotels,id'],
            'status'           => ['sometimes', 'in:draft,active,ready,completed,cancelled'],

            // Staf kloter — plain text, bukan FK
            'tour_leader'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'mutawif_local'    => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.exists'       => 'Paket umrah yang dipilih tidak ditemukan.',
            'hotel_makkah_id.exists'  => 'Hotel Makkah yang dipilih tidak ditemukan.',
            'hotel_madinah_id.exists' => 'Hotel Madinah yang dipilih tidak ditemukan.',
            'return_date.after_or_equal' => 'Tanggal kepulangan harus sama dengan atau setelah tanggal keberangkatan.',
        ];
    }
}
