<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect(
            $this->input('items', [])
        )->map(function ($item) {
            return [
                'product_id' => $item['product_id'] ?? null,

                'actual_stock' => $item['actual_stock'] ?? null,
            ];
        })->values()->all();

        $this->merge([
            'notes' => $this->filled('notes')
                ? trim(
                    (string) $this->input('notes')
                )
                : null,

            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'reason' => [
                'required',

                Rule::in([
                    'stock_opname',
                    'damaged',
                    'lost',
                    'expired',
                    'correction',
                    'other',
                ]),
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

            'items.*.actual_stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'adjustment_date.required' => 'Tanggal penyesuaian wajib diisi.',

            'adjustment_date.before_or_equal' => 'Tanggal penyesuaian tidak boleh melebihi hari ini.',

            'reason.required' => 'Alasan penyesuaian wajib dipilih.',

            'reason.in' => 'Alasan penyesuaian tidak valid.',

            'items.required' => 'Minimal harus ada satu produk.',

            'items.min' => 'Minimal harus ada satu produk.',

            'items.*.product_id.required' => 'Produk wajib dipilih.',

            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dipilih dua kali.',

            'items.*.product_id.exists' => 'Produk tidak ditemukan.',

            'items.*.actual_stock.required' => 'Stok fisik wajib diisi.',

            'items.*.actual_stock.integer' => 'Stok fisik harus berupa bilangan bulat.',

            'items.*.actual_stock.min' => 'Stok fisik tidak boleh negatif.',
        ];
    }
}
