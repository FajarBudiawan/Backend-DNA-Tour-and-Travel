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
            // ── Identitas Login ───────────────────────────────────────────────
            // Wajib, diisi manual oleh admin. Max 10 karakter, harus unik.
            'login_id' => [
                'required',
                'string',
                'max:10',
                'unique:jamaah,login_id',
            ],

            // ── Identitas Pribadi ─────────────────────────────────────────────
            'nik'               => ['required', 'string', 'size:16', 'unique:jamaah,nik'],
            'full_name'         => ['required', 'string', 'max:150'],
            'birth_date'        => ['nullable', 'date'],
            'gender'            => ['nullable', 'in:L,P'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:200'],

            // ── Dokumen Perjalanan ────────────────────────────────────────────
            'passport_number'   => ['nullable', 'string', 'max:30'],
            'visa_number'       => ['nullable', 'string', 'max:50'],
            'nationality'       => ['nullable', 'string', 'max:100'],

            // ── Relasi Paket & Kloter ─────────────────────────────────────────
            'package_id'        => ['nullable', 'uuid', 'exists:packages,id'],
            'kloter_id'         => ['nullable', 'uuid', 'exists:kloters,id'],

            // ── Logistik Perjalanan ───────────────────────────────────────────
            'hotel_makkah'      => ['nullable', 'string', 'max:200'],
            'hotel_madinah'     => ['nullable', 'string', 'max:200'],
            'departure_date'    => ['nullable', 'date'],
            'return_date'       => ['nullable', 'date', 'after_or_equal:departure_date'],

            // ── Pembimbing (plain text) ───────────────────────────────────────
            'tour_leader'       => ['nullable', 'string', 'max:200'],
            'mutawif_local'     => ['nullable', 'string', 'max:200'],

            // ── Status ────────────────────────────────────────────────────────
            'status'            => ['nullable', 'in:active,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'login_id.required'     => 'ID Login Jamaah wajib diisi.',
            'login_id.max'          => 'ID Login maksimal 10 karakter.',
            'login_id.unique'       => 'ID Login ini sudah digunakan, pilih ID lain.',
            'nik.required'          => 'NIK wajib diisi.',
            'nik.size'              => 'NIK harus tepat 16 digit.',
            'nik.unique'            => 'NIK ini sudah terdaftar sebagai Jamaah.',
            'full_name.required'    => 'Nama lengkap wajib diisi.',
            'gender.in'             => 'Jenis kelamin harus L (Laki-laki) atau P (Perempuan).',
            'package_id.exists'     => 'Paket tidak ditemukan.',
            'kloter_id.exists'      => 'Kloter tidak ditemukan.',
            'return_date.after_or_equal' => 'Tanggal kepulangan tidak boleh sebelum tanggal keberangkatan.',
        ];
    }
}
