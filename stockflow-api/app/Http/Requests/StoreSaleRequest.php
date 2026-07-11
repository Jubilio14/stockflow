<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect(
            $this->input('items', [])
        )
            ->map(function ($item) {
                return [
                    'product_id' => $item['product_id'] ?? null,

                    'quantity' => $item['quantity'] ?? null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'notes' => $this->filled('notes')
                    ? trim(
                        (string) $this->input(
                            'notes'
                        )
                    )
                    : null,

            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'promotion_id' => [
                'nullable',
                'integer',
                'exists:promotions,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Sekarang hanya cash
            |--------------------------------------------------------------------------
            |
            | Kolom database tetap fleksibel agar nanti bisa ditambah:
            | qris, transfer, dan debit.
            |
            */

            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                ]),
            ],

            'amount_paid' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'promotion_id.exists' => 'Promo tidak ditemukan.',

            'payment_method.required' => 'Metode pembayaran wajib dipilih.',

            'payment_method.in' => 'Metode pembayaran belum tersedia.',

            'amount_paid.required' => 'Uang diterima wajib diisi.',

            'amount_paid.numeric' => 'Uang diterima harus berupa angka.',

            'amount_paid.min' => 'Uang diterima tidak boleh negatif.',

            'items.required' => 'Keranjang belanja tidak boleh kosong.',

            'items.min' => 'Keranjang belanja tidak boleh kosong.',

            'items.*.product_id.required' => 'Produk wajib dipilih.',

            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dikirim dua kali.',

            'items.*.product_id.exists' => 'Produk tidak ditemukan.',

            'items.*.quantity.required' => 'Jumlah produk wajib diisi.',

            'items.*.quantity.integer' => 'Jumlah produk harus berupa bilangan bulat.',

            'items.*.quantity.min' => 'Jumlah produk minimal 1.',
        ];
    }
}
