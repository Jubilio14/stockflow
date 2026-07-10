<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(
                trim((string) $this->input('code'))
            ),

            'name' => trim(
                (string) $this->input('name')
            ),

            'contact_person' => $this->filled('contact_person')
                ? trim((string) $this->input('contact_person'))
                : null,

            'phone' => $this->filled('phone')
                ? trim((string) $this->input('phone'))
                : null,

            'email' => $this->filled('email')
                ? strtolower(trim((string) $this->input('email')))
                : null,

            'address' => $this->filled('address')
                ? trim((string) $this->input('address'))
                : null,
        ]);
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');

        $supplierId = $supplier instanceof Supplier
            ? $supplier->id
            : $supplier;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')
                    ->ignore($supplierId),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'contact_person' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')
                    ->ignore($supplierId),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode supplier wajib diisi.',
            'code.unique' => 'Kode supplier sudah digunakan.',
            'name.required' => 'Nama supplier wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh supplier lain.',
        ];
    }
}
