<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDeviceTokenRequest extends FormRequest
{
    /**
     * Otorisasi diselesaikan oleh middleware auth:sanctum + abilities pada route.
     * Ownership diverifikasi di controller — bukan di sini.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => [
                'required',
                'string',
                'min:20',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fcm_token.required' => 'FCM token wajib disertakan.',
            'fcm_token.string'   => 'FCM token harus berupa string.',
            'fcm_token.min'      => 'FCM token tidak valid (terlalu pendek).',
            'fcm_token.max'      => 'FCM token melebihi panjang maksimum yang diizinkan.',
        ];
    }
}
