<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'payment_type' => [
                'sometimes',
                'in:down_payment,full_payment',
            ],

            'payment_method' => [
                'sometimes',
                'in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris',
            ],

            'payment_date' => [
                'sometimes',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.numeric' =>
                'Nominal pembayaran harus berupa angka.',

            'amount.gt' =>
                'Nominal pembayaran harus lebih dari 0.',

            'payment_type.in' =>
                'Jenis pembayaran tidak valid.',

            'payment_method.in' =>
                'Metode pembayaran tidak valid.',

            'payment_date.date' =>
                'Format tanggal pembayaran tidak valid.',

            'notes.max' =>
                'Catatan maksimal 255 karakter.',
        ];
    }
}