<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'closing_notes' => $this->filled('closing_notes')
                    ? trim(
                        (string) $this->input(
                            'closing_notes'
                        )
                    )
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'actual_closing_cash' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],

            'closing_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_closing_cash.required' => 'Jumlah uang fisik wajib diisi.',

            'actual_closing_cash.numeric' => 'Jumlah uang fisik harus berupa angka.',

            'actual_closing_cash.min' => 'Jumlah uang fisik tidak boleh negatif.',

            'actual_closing_cash.decimal' => 'Jumlah uang fisik maksimal memiliki dua angka desimal.',

            'closing_notes.max' => 'Catatan penutupan maksimal 1000 karakter.',
        ];
    }
}
