<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            // 'registration_id' => [
            //     'required',
            //     'uuid',
            //     'exists:registrations,id',
            // ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_type' => [
                'required',
                'in:down_payment,full_payment',
            ],

            'payment_method' => [
                'required',
                'in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris',
            ],

            'payment_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'registration_id.required' =>
                'Data pendaftaran wajib dipilih.',

            'registration_id.uuid' =>
                'ID pendaftaran tidak valid.',

            'registration_id.exists' =>
                'Data pendaftaran tidak ditemukan.',

            'amount.required' =>
                'Nominal pembayaran wajib diisi.',

            'amount.numeric' =>
                'Nominal pembayaran harus berupa angka.',

            'amount.gt' =>
                'Nominal pembayaran harus lebih dari 0.',

            'payment_type.required' =>
                'Jenis pembayaran wajib dipilih.',

            'payment_type.in' =>
                'Jenis pembayaran tidak valid.',

            'payment_method.required' =>
                'Metode pembayaran wajib dipilih.',

            'payment_method.in' =>
                'Metode pembayaran tidak valid.',

            'payment_date.required' =>
                'Tanggal pembayaran wajib diisi.',

            'payment_date.date' =>
                'Format tanggal pembayaran tidak valid.',

            'notes.max' =>
                'Catatan maksimal 255 karakter.',
        ];
    }
}