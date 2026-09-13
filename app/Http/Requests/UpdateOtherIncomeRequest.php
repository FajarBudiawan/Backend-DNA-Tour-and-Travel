<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOtherIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $incomeId = $this->route('other_income')
            ? ($this->route('other_income')->id ?? $this->route('other_income'))
            : null;

        return [
            'source' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'sometimes',
                'required',
                'in:commission,equipment_sales,administration,other',
            ],

            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'sometimes',
                'required',
                'in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris',
            ],

            'income_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'reference_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('other_incomes', 'reference_number')
                    ->ignore($incomeId),
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
            'source.required' => 'Sumber pemasukan wajib diisi.',
            'source.string' => 'Sumber pemasukan harus berupa teks.',
            'source.max' => 'Sumber pemasukan maksimal 150 karakter.',

            'category.required' => 'Kategori pemasukan wajib dipilih.',
            'category.in' => 'Kategori pemasukan tidak valid.',

            'amount.required' => 'Nominal pemasukan wajib diisi.',
            'amount.numeric' => 'Nominal pemasukan harus berupa angka.',
            'amount.gt' => 'Nominal pemasukan harus lebih besar dari 0.',

            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',

            'income_date.required' => 'Tanggal pemasukan wajib diisi.',
            'income_date.date' => 'Format tanggal pemasukan tidak valid.',

            'reference_number.max' => 'Nomor referensi maksimal 50 karakter.',
            'reference_number.unique' => 'Nomor referensi sudah digunakan.',

            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}