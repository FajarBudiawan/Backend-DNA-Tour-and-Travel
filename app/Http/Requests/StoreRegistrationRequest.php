<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Informasi Diri Jamaah
            |--------------------------------------------------------------------------
            */

            'registration_number' => [
                'nullable',
                'string',
                'max:30',
                'unique:registrations,registration_number',
            ],

            'full_name' => [
                'required',
                'string',
                'max:150',
            ],

            'passport_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'nik' => [
                'required',
                'string',
                'size:16',
                'unique:registrations,nik',
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
            ],

            'birth_date' => [
                'required',
                'date',
                'before:today',
            ],

            'gender' => [
                'required',
                'in:L,P',
            ],


            /*
            |--------------------------------------------------------------------------
            | Administrasi
            |--------------------------------------------------------------------------
            */

            'registration_date' => [
                'required',
                'date',
            ],

            'departure_date' => [
                'nullable',
                'date',
                'after_or_equal:registration_date',
            ],

            'package_id' => [
                'required',
                'uuid',
                'exists:packages,id',
            ],

            'kloter_id' => [
                'nullable',
                'uuid',
                'exists:kloters,id',
            ],

            'meningitis_vaccine_status' => [
                'required',
                'in:belum_vaksin,sudah_vaksin',
            ],

            'photo_status' => [
                'required',
                'in:belum_ada,sudah_menyerahkan',
            ],


            /*
            |--------------------------------------------------------------------------
            | Biaya Paket
            |--------------------------------------------------------------------------
            */

            'total_package_cost' => [
                'required',
                'numeric',
                'min:0',
            ],

            'status' => [
                'nullable',
                'string',
                'in:unpaid,dp,dp_paid,paid,fully_paid,cancelled,converted',
            ],

            /*
            |--------------------------------------------------------------------------
            | Pembayaran Awal (Optional saat Registrasi)
            |--------------------------------------------------------------------------
            */

            'initial_payment' => [
                'nullable',
                'array',
            ],

            'initial_payment.amount' => [
                'required_with:initial_payment',
                'numeric',
                'gt:0',
            ],

            'initial_payment.payment_type' => [
                'nullable',
                'in:down_payment,full_payment',
            ],

            'initial_payment.payment_method' => [
                'nullable',
                'in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris',
            ],

            'initial_payment.payment_date' => [
                'nullable',
                'date',
            ],


            /*
            |--------------------------------------------------------------------------
            | Perlengkapan
            |--------------------------------------------------------------------------
            */

            'equipments' => [
                'nullable',
                'array',
            ],

            'equipments.*.equipment_name' => [
                'required_with:equipments',
                'string',
                'max:100',
            ],

            'equipments.*.size' => [
                'nullable',
                'string',
                'max:20',
            ],

            'equipments.*.is_received' => [
                'required_with:equipments',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.max' => 'Nama lengkap maksimal 150 karakter.',

            'nik.required' => 'NIK wajib diisi.',
            'nik.size' => 'NIK harus terdiri dari 16 digit.',
            'nik.unique' => 'NIK sudah terdaftar.',

            'phone.required' => 'Nomor telepon wajib diisi.',

            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',

            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin harus L atau P.',

            'registration_date.required' => 'Tanggal pendaftaran wajib diisi.',

            'departure_date.after_or_equal' =>
                'Tanggal keberangkatan tidak boleh sebelum tanggal pendaftaran.',

            'package_id.required' => 'Paket wajib dipilih.',
            'package_id.exists' => 'Paket yang dipilih tidak ditemukan.',

            'kloter_id.exists' => 'Kloter yang dipilih tidak ditemukan.',

            'meningitis_vaccine_status.required' =>
                'Status vaksin meningitis wajib dipilih.',

            'meningitis_vaccine_status.in' =>
                'Status vaksin meningitis tidak valid.',

            'photo_status.required' =>
                'Status pas foto wajib dipilih.',

            'photo_status.in' =>
                'Status pas foto tidak valid.',

            'total_package_cost.required' =>
                'Total biaya paket wajib diisi.',

            'total_package_cost.numeric' =>
                'Total biaya paket harus berupa angka.',

            'total_package_cost.min' =>
                'Total biaya paket tidak boleh kurang dari 0.',

            'equipments.array' =>
                'Data perlengkapan harus berupa array.',

            'equipments.*.equipment_name.required_with' =>
                'Nama perlengkapan wajib diisi.',

            'equipments.*.is_received.required_with' =>
                'Status perlengkapan wajib diisi.',
        ];
    }
}