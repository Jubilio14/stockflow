<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
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

                'quantity' => $item['quantity'] ?? null,

                'unit_cost' => $item['unit_cost'] ?? null,
            ];
        })->values()->all();

        $this->merge([
            'invoice_number' => $this->filled('invoice_number')
                    ? trim((string) $this->input(
                        'invoice_number'
                    ))
                    : null,

            'notes' => $this->filled('notes')
                    ? trim((string) $this->input(
                        'notes'
                    ))
                    : null,

            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',

                Rule::exists(
                    'suppliers',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],

            'invoice_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'purchase_date' => [
                'required',
                'date',
                'before_or_equal:today',
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

                Rule::exists(
                    'products',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',

            'supplier_id.exists' => 'Supplier tidak tersedia atau sudah tidak aktif.',

            'purchase_date.required' => 'Tanggal pembelian wajib diisi.',

            'purchase_date.before_or_equal' => 'Tanggal pembelian tidak boleh melebihi hari ini.',

            'items.required' => 'Minimal harus ada satu produk.',

            'items.min' => 'Minimal harus ada satu produk.',

            'items.*.product_id.required' => 'Produk wajib dipilih.',

            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dimasukkan dua kali.',

            'items.*.product_id.exists' => 'Produk tidak tersedia atau sudah tidak aktif.',

            'items.*.quantity.required' => 'Jumlah pembelian wajib diisi.',

            'items.*.quantity.min' => 'Jumlah pembelian minimal satu.',

            'items.*.unit_cost.required' => 'Harga beli wajib diisi.',

            'items.*.unit_cost.gt' => 'Harga beli harus lebih dari nol.',
        ];
    }
}
