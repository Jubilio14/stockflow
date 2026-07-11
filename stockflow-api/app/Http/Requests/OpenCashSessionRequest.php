<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_notes' => $this->filled('opening_notes')
                    ? trim(
                        (string) $this->input(
                            'opening_notes'
                        )
                    )
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'opening_cash' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],

            'opening_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Modal awal wajib diisi.',

            'opening_cash.numeric' => 'Modal awal harus berupa angka.',

            'opening_cash.min' => 'Modal awal tidak boleh negatif.',

            'opening_cash.decimal' => 'Modal awal maksimal memiliki dua angka desimal.',

            'opening_notes.max' => 'Catatan pembukaan maksimal 1000 karakter.',
        ];
    }
}
