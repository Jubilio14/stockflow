<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function create(
        array $data,
        User $user
    ): Sale {
        return DB::transaction(
            function () use ($data, $user) {
                /*
                |--------------------------------------------------------------------------
                | 1. Kunci dan validasi sesi kasir
                |--------------------------------------------------------------------------
                */

                $cashSession =
                    CashSession::query()
                        ->open()
                        ->lockForUpdate()
                        ->first();

                if (! $cashSession) {
                    throw ValidationException::withMessages([
                        'session' => [
                            'Belum ada sesi kasir yang terbuka.',
                        ],
                    ]);
                }

                if (
                    $cashSession->cashier_id !==
                    $user->id
                ) {
                    throw ValidationException::withMessages([
                        'session' => [
                            'Sesi kasir aktif bukan milik user yang sedang login.',
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 2. Kunci seluruh produk
                |--------------------------------------------------------------------------
                |
                | Product ID diurutkan agar urutan penguncian konsisten dan
                | mengurangi kemungkinan database deadlock.
                |
                */

                $productIds = collect(
                    $data['items']
                )
                    ->pluck('product_id')
                    ->map(
                        fn ($id) => (int) $id
                    )
                    ->sort()
                    ->values();

                $products = Product::query()
                    ->whereIn(
                        'id',
                        $productIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $preparedItems = [];

                $subtotalCents = 0;
                $totalCost = 0.0;

                foreach (
                    $data['items'] as $index => $item
                ) {
                    $productId =
                        (int) $item['product_id'];

                    $quantity =
                        (int) $item['quantity'];

                    $product =
                        $products->get(
                            $productId
                        );

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => [
                                'Produk tidak ditemukan.',
                            ],
                        ]);
                    }

                    if (! $product->is_active) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => [
                                "{$product->name} sedang nonaktif.",
                            ],
                        ]);
                    }

                    $sellingPriceCents =
                        $this->moneyToCents(
                            $product->selling_price
                        );

                    if ($sellingPriceCents <= 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => [
                                "{$product->name} belum memiliki harga jual yang valid.",
                            ],
                        ]);
                    }

                    $stockBefore =
                        (int) $product->current_stock;

                    if ($stockBefore < $quantity) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => [
                                sprintf(
                                    'Stok %s tidak mencukupi. Tersedia %d %s.',
                                    $product->name,
                                    $stockBefore,
                                    $product->unit
                                ),
                            ],
                        ]);
                    }

                    $stockAfter =
                        $stockBefore -
                        $quantity;

                    $lineSubtotalCents =
                        $sellingPriceCents *
                        $quantity;

                    $averageCost =
                        round(
                            (float) $product
                                ->average_cost,
                            4
                        );

                    $costTotal =
                        round(
                            $averageCost *
                            $quantity,
                            4
                        );

                    $subtotalCents +=
                        $lineSubtotalCents;

                    $totalCost +=
                        $costTotal;

                    $preparedItems[] = [
                        'product' => $product,

                        'quantity' => $quantity,

                        'selling_price_cents' => $sellingPriceCents,

                        'average_cost' => $averageCost,

                        'subtotal_cents' => $lineSubtotalCents,

                        'cost_total' => $costTotal,

                        'stock_before' => $stockBefore,

                        'stock_after' => $stockAfter,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | 3. Validasi dan hitung promo
                |--------------------------------------------------------------------------
                */

                $promotion = null;
                $discountCents = 0;

                if (
                    ! empty(
                        $data['promotion_id']
                    )
                ) {
                    $promotion =
                        Promotion::query()
                            ->whereKey(
                                $data[
                                    'promotion_id'
                                ]
                            )
                            ->lockForUpdate()
                            ->first();

                    if (! $promotion) {
                        throw ValidationException::withMessages([
                            'promotion_id' => [
                                'Promo tidak ditemukan.',
                            ],
                        ]);
                    }

                    $discountCents =
                        $this->calculatePromotionDiscount(
                            $promotion,
                            $subtotalCents
                        );
                }

                $totalAmountCents =
                    $subtotalCents -
                    $discountCents;

                /*
                |--------------------------------------------------------------------------
                | 4. Validasi pembayaran cash
                |--------------------------------------------------------------------------
                */

                $amountPaidCents =
                    $this->moneyToCents(
                        $data['amount_paid']
                    );

                if (
                    $amountPaidCents <
                    $totalAmountCents
                ) {
                    throw ValidationException::withMessages([
                        'amount_paid' => [
                            sprintf(
                                'Uang diterima kurang %s.',
                                $this->formatRupiah(
                                    $totalAmountCents -
                                    $amountPaidCents
                                )
                            ),
                        ],
                    ]);
                }

                $changeAmountCents =
                    $amountPaidCents -
                    $totalAmountCents;

                $totalCost = round(
                    $totalCost,
                    4
                );

                $grossProfit = round(
                    $this->centsToMoney(
                        $totalAmountCents
                    ) - $totalCost,
                    4
                );

                /*
                |--------------------------------------------------------------------------
                | 5. Buat header transaksi
                |--------------------------------------------------------------------------
                */

                $sale = Sale::create([
                    'sale_number' => $this->generateSaleNumber(),

                    'cash_session_id' => $cashSession->id,

                    'cashier_id' => $user->id,

                    'promotion_id' => $promotion?->id,

                    'promotion_name' => $promotion?->name,

                    'promotion_code' => $promotion?->code,

                    'promotion_discount_type' => $promotion?->discount_type,

                    'promotion_discount_value' => $promotion?->discount_value,

                    'subtotal' => $this->centsToMoney(
                        $subtotalCents
                    ),

                    'discount_amount' => $this->centsToMoney(
                        $discountCents
                    ),

                    'total_amount' => $this->centsToMoney(
                        $totalAmountCents
                    ),

                    'total_cost' => $totalCost,

                    'gross_profit' => $grossProfit,

                    'payment_method' => 'cash',

                    'amount_paid' => $this->centsToMoney(
                        $amountPaidCents
                    ),

                    'change_amount' => $this->centsToMoney(
                        $changeAmountCents
                    ),

                    'status' => 'completed',

                    'sold_at' => now(),

                    'notes' => $data['notes'] ?? null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | 6. Bagi diskon transaksi ke setiap item
                |--------------------------------------------------------------------------
                */

                $remainingDiscountCents =
                    $discountCents;

                $lastItemIndex =
                    count($preparedItems) - 1;

                foreach (
                    $preparedItems as $index => $preparedItem
                ) {
                    if (
                        $index === $lastItemIndex
                    ) {
                        $allocatedDiscountCents =
                            $remainingDiscountCents;
                    } elseif (
                        $subtotalCents > 0
                        &&
                        $discountCents > 0
                    ) {
                        $allocatedDiscountCents =
                            intdiv(
                                $preparedItem[
                                    'subtotal_cents'
                                ] *
                                $discountCents,

                                $subtotalCents
                            );
                    } else {
                        $allocatedDiscountCents =
                            0;
                    }

                    $remainingDiscountCents -=
                        $allocatedDiscountCents;

                    $netSalesCents =
                        $preparedItem[
                            'subtotal_cents'
                        ] -
                        $allocatedDiscountCents;

                    $itemGrossProfit = round(
                        $this->centsToMoney(
                            $netSalesCents
                        ) -
                        $preparedItem[
                            'cost_total'
                        ],
                        4
                    );

                    $product =
                        $preparedItem[
                            'product'
                        ];

                    /*
                    |--------------------------------------------------------------------------
                    | 7. Buat sale item
                    |--------------------------------------------------------------------------
                    */

                    $sale->items()->create([
                        'product_id' => $product->id,

                        'quantity' => $preparedItem[
                                'quantity'
                            ],

                        'selling_price' => $this->centsToMoney(
                            $preparedItem[
                                'selling_price_cents'
                            ]
                        ),

                        'average_cost' => $preparedItem[
                                'average_cost'
                            ],

                        'subtotal' => $this->centsToMoney(
                            $preparedItem[
                                'subtotal_cents'
                            ]
                        ),

                        'allocated_discount' => $this->centsToMoney(
                            $allocatedDiscountCents
                        ),

                        'net_sales' => $this->centsToMoney(
                            $netSalesCents
                        ),

                        'cost_total' => $preparedItem[
                                'cost_total'
                            ],

                        'gross_profit' => $itemGrossProfit,

                        'stock_before' => $preparedItem[
                                'stock_before'
                            ],

                        'stock_after' => $preparedItem[
                                'stock_after'
                            ],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 8. Kurangi stok produk
                    |--------------------------------------------------------------------------
                    */

                    $product->update([
                        'current_stock' => $preparedItem[
                                'stock_after'
                            ],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 9. Catat stock movement
                    |--------------------------------------------------------------------------
                    */

                    StockMovement::create([
                        'product_id' => $product->id,

                        'created_by' => $user->id,

                        'type' => 'sale',

                        'reference_type' => 'sale',

                        'reference_id' => $sale->id,

                        'quantity_change' => -$preparedItem[
                                'quantity'
                            ],

                        'stock_before' => $preparedItem[
                                'stock_before'
                            ],

                        'stock_after' => $preparedItem[
                                'stock_after'
                            ],

                        /*
                         * Untuk penjualan, unit_cost berisi
                         * average cost/HPP per satuan.
                         */

                        'unit_cost' => $preparedItem[
                                'average_cost'
                            ],

                        'average_cost_before' => $preparedItem[
                                'average_cost'
                            ],

                        'average_cost_after' => $preparedItem[
                                'average_cost'
                            ],

                        'notes' => "Penjualan {$sale->sale_number}",

                        'movement_at' => $sale->sold_at,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 10. Tambahkan penjualan cash ke sesi kasir
                |--------------------------------------------------------------------------
                |
                | Yang masuk ke kas adalah total transaksi, bukan amount_paid.
                |
                | Contoh:
                | pelanggan membayar Rp50.000
                | total belanja Rp30.000
                | kembalian Rp20.000
                |
                | uang bersih yang bertambah di laci = Rp30.000
                |
                */

                $newCashSalesTotal = round(
                    (float) $cashSession
                        ->cash_sales_total
                    +
                    $this->centsToMoney(
                        $totalAmountCents
                    ),
                    2
                );

                $cashSession->update([
                    'cash_sales_total' => $newCashSalesTotal,
                ]);

                return $sale->load([
                    'cashSession:id,session_number',
                    'cashier:id,name',
                    'items.product:id,name,sku,unit,image_path',
                ]);
            }
        );
    }

    private function calculatePromotionDiscount(
        Promotion $promotion,
        int $subtotalCents
    ): int {
        if (! $promotion->is_active) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'Promo sedang dinonaktifkan.',
                ],
            ]);
        }

        if (now()->lt($promotion->starts_at)) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'Promo belum mulai berlaku.',
                ],
            ]);
        }

        if (now()->gt($promotion->ends_at)) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'Promo sudah berakhir.',
                ],
            ]);
        }

        $minimumPurchaseCents =
            $this->moneyToCents(
                $promotion->minimum_purchase
            );

        if (
            $subtotalCents <
            $minimumPurchaseCents
        ) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    sprintf(
                        'Minimal pembelian promo adalah %s.',
                        $this->formatRupiah(
                            $minimumPurchaseCents
                        )
                    ),
                ],
            ]);
        }

        if (
            $promotion->discount_type ===
            'percentage'
        ) {
            $discountCents = (int) round(
                $subtotalCents *
                (
                    (float) $promotion
                        ->discount_value
                    /
                    100
                )
            );

            if (
                $promotion->maximum_discount !==
                null
            ) {
                $maximumDiscountCents =
                    $this->moneyToCents(
                        $promotion
                            ->maximum_discount
                    );

                $discountCents = min(
                    $discountCents,
                    $maximumDiscountCents
                );
            }
        } else {
            $discountCents =
                $this->moneyToCents(
                    $promotion->discount_value
                );
        }

        /*
         * Diskon tidak boleh menghasilkan total negatif.
         */

        return min(
            $discountCents,
            $subtotalCents
        );
    }

    private function generateSaleNumber(): string
    {
        do {
            $saleNumber = sprintf(
                'SALE-%s-%s',
                now()->format('Ymd'),
                Str::upper(
                    Str::random(6)
                )
            );
        } while (
            Sale::query()
                ->where(
                    'sale_number',
                    $saleNumber
                )
                ->exists()
        );

        return $saleNumber;
    }

    private function moneyToCents(
        string|int|float $value
    ): int {
        return (int) round(
            (float) $value * 100
        );
    }

    private function centsToMoney(
        int $cents
    ): float {
        return round(
            $cents / 100,
            2
        );
    }

    private function formatRupiah(
        int $cents
    ): string {
        return 'Rp'.
            number_format(
                $this->centsToMoney($cents),
                0,
                ',',
                '.'
            );
    }
}
