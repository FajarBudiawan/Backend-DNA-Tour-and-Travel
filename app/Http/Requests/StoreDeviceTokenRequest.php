<?php

namespace App\Http\Requests;

use App\Models\DeviceToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceTokenRequest extends FormRequest
{
    /**
     * Otorisasi diselesaikan oleh middleware auth:sanctum + abilities pada route.
     * Semua user yang sudah terautentikasi boleh mendaftarkan token.
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
                // Panjang minimum realistis untuk FCM token (biasanya 140+ karakter)
                'min:20',
                // Batas atas aman; kolom DB adalah VARCHAR(500)
                'max:500',
            ],

            'platform' => [
                'required',
                'string',
                Rule::in(DeviceToken::PLATFORMS),
            ],

            'device_id' => [
                'nullable',
                'string',
                'max:255',
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

            'platform.required'  => 'Platform perangkat wajib disertakan.',
            'platform.string'    => 'Platform harus berupa string.',
            'platform.in'        => 'Platform tidak valid. Nilai yang diterima: android, ios.',

            'device_id.string'   => 'Device ID harus berupa string.',
            'device_id.max'      => 'Device ID maksimal 255 karakter.',
        ];
    }
}
