<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $barcode = trim(
            (string) $this->input('barcode'),
        );

        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),

            'sku' => mb_strtoupper(
                trim((string) $this->input('sku')),
            ),

            'barcode' => $barcode !== ''
                ? $barcode
                : null,

            'unit' => mb_strtolower(
                trim((string) $this->input('unit')),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => [
                'required',
                'integer',

                Rule::exists('categories', 'id')
                    ->where(function ($query) use ($product) {
                        $query->where(
                            'is_active',
                            true,
                        );

                        if ($product instanceof Product) {
                            $query->orWhere(
                                'id',
                                $product->category_id,
                            );
                        }
                    }),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'sku' => [
                'required',
                'string',
                'max:100',

                Rule::unique('products', 'sku')
                    ->ignore($product),
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',

                Rule::unique('products', 'barcode')
                    ->ignore($product),
            ],

            'unit' => [
                'required',
                'string',
                'max:30',
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'minimum_stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',

            'category_id.exists' => 'Kategori tidak tersedia atau sudah nonaktif.',

            'name.required' => 'Nama produk wajib diisi.',

            'sku.required' => 'SKU wajib diisi.',

            'sku.unique' => 'SKU sudah digunakan oleh produk lain.',

            'barcode.unique' => 'Barcode sudah digunakan oleh produk lain.',

            'unit.required' => 'Satuan produk wajib diisi.',

            'selling_price.required' => 'Harga jual wajib diisi.',

            'selling_price.numeric' => 'Harga jual harus berupa angka.',

            'selling_price.min' => 'Harga jual tidak boleh negatif.',

            'minimum_stock.required' => 'Stok minimum wajib diisi.',

            'minimum_stock.integer' => 'Stok minimum harus berupa bilangan bulat.',

            'minimum_stock.min' => 'Stok minimum tidak boleh negatif.',

            'image.image' => 'File yang dipilih harus berupa gambar.',

            'image.mimes' => 'Gambar harus berformat JPG, JPEG, PNG, atau WEBP.',

            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
