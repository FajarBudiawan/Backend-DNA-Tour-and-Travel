<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pilgrim_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'full_name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'passport_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'nik' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'phone' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'birth_date' => [
                'sometimes',
                'date',
            ],

            'gender' => [
                'sometimes',
                Rule::in(['L', 'P']),
            ],

            'registration_date' => [
                'sometimes',
                'date',
            ],

            'departure_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'package_id' => [
                'sometimes',
                'exists:packages,id',
            ],

            'kloter_id' => [
                'sometimes',
                'nullable',
                'exists:kloters,id',
            ],

            'meningitis_vaccine_status' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'photo_status' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'total_package_cost' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    'unpaid',
                    'dp_paid',
                    'fully_paid',
                    'cancelled',
                    'converted',
                ]),
            ],

            'equipments' => [
                'sometimes',
                'array',
            ],

            'equipments.*.id' => [
                'sometimes',
                'nullable',
                'uuid',
            ],

            'equipments.*.equipment_name' => [
                'required_with:equipments',
                'string',
                'max:255',
            ],

            'equipments.*.stock_id' => [
                'required_with:equipments',
                'uuid',
                'exists:stocks,id',
            ],

            'equipments.*.is_received' => [
                'required_with:equipments',
                'boolean',
            ],

            'equipments.*.size' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'initial_payment' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'initial_payment.amount' => [
                'required_with:initial_payment',
                'numeric',
                'min:0',
            ],

            'initial_payment.payment_type' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'initial_payment.payment_method' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'initial_payment.payment_date' => [
                'sometimes',
                'date',
            ],

            'initial_payment.notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}