<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Periode default
        |--------------------------------------------------------------------------
        |
        | Jika frontend tidak mengirim tanggal, laporan otomatis menampilkan
        | data hari ini.
        |
        */

        $today = now(
            config('app.timezone')
        )->toDateString();

        $this->merge([
            'date_from' => $this->input('date_from')
                    ?: $today,

            'date_to' => $this->input('date_to')
                    ?: $today,
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'required',
                'date',
            ],

            'date_to' => [
                'required',
                'date',
                'after_or_equal:date_from',
            ],

            'cashier_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'payment_method' => [
                'nullable',

                Rule::in([
                    'cash',
                    'qris',
                    'transfer',
                    'debit',
                ]),
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                if (
                    $validator
                        ->errors()
                        ->isNotEmpty()
                ) {
                    return;
                }

                $dateFrom =
                    CarbonImmutable::parse(
                        $this->string(
                            'date_from'
                        )->toString()
                    )->startOfDay();

                $dateTo =
                    CarbonImmutable::parse(
                        $this->string(
                            'date_to'
                        )->toString()
                    )->startOfDay();

                /*
                 * Maksimal 366 hari agar query laporan
                 * tidak mengambil rentang terlalu besar.
                 */

                if (
                    $dateFrom->diffInDays(
                        $dateTo
                    ) > 365
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'date_to',
                            'Rentang laporan maksimal 366 hari.'
                        );
                }
            }
        );
    }

    public function messages(): array
    {
        return [
            'date_from.required' => 'Tanggal awal wajib diisi.',

            'date_from.date' => 'Tanggal awal tidak valid.',

            'date_to.required' => 'Tanggal akhir wajib diisi.',

            'date_to.date' => 'Tanggal akhir tidak valid.',

            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',

            'cashier_id.exists' => 'Kasir tidak ditemukan.',

            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ];
    }
}
