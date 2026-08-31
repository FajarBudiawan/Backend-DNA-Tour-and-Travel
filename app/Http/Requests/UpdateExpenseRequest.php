<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $expenseId = $this->route('expense') ? ($this->route('expense')->id ?? $this->route('expense')) : null;

        return [
            'vendor' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'sometimes',
                'required',
                'in:akomodasi_tiket,perlengkapan,operasional_bus',
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

            'expense_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'reference_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('expenses', 'reference_number')->ignore($expenseId),
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
            'vendor.required' => 'Nama vendor/penerima wajib diisi.',
            'vendor.string' => 'Nama vendor/penerima harus berupa teks.',
            'vendor.max' => 'Nama vendor/penerima maksimal 150 karakter.',

            'category.required' => 'Kategori pengeluaran wajib dipilih.',
            'category.in' => 'Kategori pengeluaran tidak valid. Pilih: akomodasi_tiket, perlengkapan, atau operasional_bus.',

            'amount.required' => 'Nominal pengeluaran wajib diisi.',
            'amount.numeric' => 'Nominal pengeluaran harus berupa angka.',
            'amount.gt' => 'Nominal pengeluaran harus lebih besar dari 0.',

            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',

            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.date' => 'Format tanggal pengeluaran tidak valid.',

            'reference_number.max' => 'Nomor referensi maksimal 50 karakter.',
            'reference_number.unique' => 'Nomor referensi sudah digunakan.',

            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
