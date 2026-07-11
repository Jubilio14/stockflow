<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $discountType =
            $this->input('discount_type');

        $this->merge([
            'name' => trim(
                (string) $this->input('name')
            ),

            'code' => Str::upper(
                trim(
                    (string) $this->input('code')
                )
            ),

            'minimum_purchase' => $this->filled('minimum_purchase')
                    ? $this->input('minimum_purchase')
                    : 0,

            'maximum_discount' => $discountType === 'percentage'
                && $this->filled('maximum_discount')
                    ? $this->input('maximum_discount')
                    : null,
        ]);
    }

    public function rules(): array
    {
        $promotion =
            $this->route('promotion');

        $promotionId =
            $promotion instanceof Promotion
                ? $promotion->id
                : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'code' => [
                'required',
                'string',
                'max:50',

                'regex:/^[A-Z0-9_-]+$/',

                Rule::unique(
                    'promotions',
                    'code'
                )->ignore($promotionId),
            ],

            'discount_type' => [
                'required',

                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],

            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'minimum_purchase' => [
                'required',
                'numeric',
                'min:0',
            ],

            'maximum_discount' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'starts_at' => [
                'required',
                'date',
            ],

            'ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $discountType =
                    $this->input('discount_type');

                $discountValue = (float)
                    $this->input(
                        'discount_value',
                        0
                    );

                if (
                    $discountType === 'percentage'
                    && $discountValue > 100
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'discount_value',
                            'Diskon persentase tidak boleh melebihi 100%.'
                        );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama promo wajib diisi.',

            'code.required' => 'Kode promo wajib diisi.',

            'code.regex' => 'Kode promo hanya boleh berisi huruf, angka, tanda hubung, dan underscore.',

            'code.unique' => 'Kode promo sudah digunakan.',

            'discount_type.required' => 'Jenis diskon wajib dipilih.',

            'discount_type.in' => 'Jenis diskon tidak valid.',

            'discount_value.required' => 'Nilai diskon wajib diisi.',

            'discount_value.gt' => 'Nilai diskon harus lebih dari nol.',

            'minimum_purchase.min' => 'Minimal pembelian tidak boleh negatif.',

            'maximum_discount.gt' => 'Maksimal diskon harus lebih dari nol.',

            'starts_at.required' => 'Waktu mulai promo wajib diisi.',

            'ends_at.required' => 'Waktu berakhir promo wajib diisi.',

            'ends_at.after' => 'Waktu berakhir harus setelah waktu mulai.',
        ];
    }
}
