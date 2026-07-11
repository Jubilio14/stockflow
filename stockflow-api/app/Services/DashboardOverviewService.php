<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;

class DashboardOverviewService
{
    public function generate(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Periode hari ini
        |--------------------------------------------------------------------------
        |
        | Mengikuti timezone aplikasi, yaitu Asia/Jakarta.
        |
        */

        $today = CarbonImmutable::now(
            config('app.timezone')
        );

        $startOfDay = $today->startOfDay();
        $endOfDay = $today->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Ringkasan penjualan hari ini
        |--------------------------------------------------------------------------
        */

        $summaryRow = Sale::query()
            ->where(
                'status',
                'completed'
            )
            ->whereBetween(
                'sold_at',
                [
                    $startOfDay,
                    $endOfDay,
                ]
            )
            ->selectRaw(
                '
                COUNT(*) AS total_transactions,
                COALESCE(SUM(subtotal), 0) AS total_subtotal,
                COALESCE(SUM(discount_amount), 0) AS total_discount,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(SUM(total_cost), 0) AS total_cost,
                COALESCE(SUM(gross_profit), 0) AS gross_profit
                '
            )
            ->first();

        $totalTransactions = (int) (
            $summaryRow?->total_transactions ?? 0
        );

        $totalRevenue = $this->money(
            $summaryRow?->total_revenue ?? 0
        );

        $grossProfit = $this->money(
            $summaryRow?->gross_profit ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Total unit produk terjual hari ini
        |--------------------------------------------------------------------------
        |
        | Menghitung SUM(quantity), bukan jumlah jenis produk.
        |
        */

        $totalItemsSold = SaleItem::query()
            ->join(
                'sales',
                'sales.id',
                '=',
                'sale_items.sale_id'
            )
            ->where(
                'sales.status',
                'completed'
            )
            ->whereBetween(
                'sales.sold_at',
                [
                    $startOfDay,
                    $endOfDay,
                ]
            )
            ->sum(
                'sale_items.quantity'
            );

        $averageTransaction =
            $totalTransactions > 0
                ? $this->money(
                    $totalRevenue /
                    $totalTransactions
                )
                : 0.0;

        $grossMarginPercentage =
            $totalRevenue > 0
                ? round(
                    (
                        $grossProfit /
                        $totalRevenue
                    ) * 100,
                    2
                )
                : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Produk stok menipis
        |--------------------------------------------------------------------------
        |
        | Sebuah produk disebut menipis ketika:
        |
        | current_stock <= minimum_stock
        |
        */

        $lowStockQuery = Product::query()
            ->where(
                'is_active',
                true
            )
            ->whereColumn(
                'current_stock',
                '<=',
                'minimum_stock'
            );

        $lowStockCount =
            (clone $lowStockQuery)
                ->count();

        $lowStockProducts =
            (clone $lowStockQuery)
                ->with([
                    'category:id,name',
                ])
                ->select([
                    'id',
                    'category_id',
                    'name',
                    'sku',
                    'unit',
                    'current_stock',
                    'minimum_stock',
                    'image_path',
                ])
                ->orderBy(
                    'current_stock'
                )
                ->orderBy('name')
                ->limit(5)
                ->get()
                ->map(
                    fn (Product $product) => [
                        'id' => $product->id,

                        'name' => $product->name,

                        'sku' => $product->sku,

                        'unit' => $product->unit,

                        'current_stock' => (int) $product
                            ->current_stock,

                        'minimum_stock' => (int) $product
                            ->minimum_stock,

                        'stock_shortage' => max(
                            0,
                            (int) $product
                                ->minimum_stock
                            -
                            (int) $product
                                ->current_stock
                        ),

                        'category' => $product->category
                                ? [
                                'id' => $product
                                    ->category
                                    ->id,

                                'name' => $product
                                    ->category
                                    ->name,
                            ]
                                : null,

                        'image_url' => $product->image_path
                                ? asset(
                                    'storage/'.
                                    $product
                                        ->image_path
                                )
                                : null,
                    ]
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Sesi kasir aktif
        |--------------------------------------------------------------------------
        |
        | StockFlow MVP hanya memiliki satu meja kasir global, sehingga
        | maksimal hanya ada satu sesi berstatus open.
        |
        */

        $activeCashSession =
            CashSession::query()
                ->with([
                    'cashier:id,name',
                ])
                ->withCount('sales')
                ->open()
                ->first();

        $activeSessionData =
            $activeCashSession
                ? [
                    'id' => $activeCashSession->id,

                    'session_number' => $activeCashSession
                        ->session_number,

                    'cashier' => [
                        'id' => $activeCashSession
                            ->cashier
                            ->id,

                        'name' => $activeCashSession
                            ->cashier
                            ->name,
                    ],

                    'opened_at' => $activeCashSession
                        ->opened_at
                        ?->toISOString(),

                    'duration_minutes' => $activeCashSession
                        ->opened_at
                        ? (int) $activeCashSession
                            ->opened_at
                            ->diffInMinutes(
                                now()
                            )
                            : 0,

                    'opening_cash' => $this->money(
                        $activeCashSession
                            ->opening_cash
                    ),

                    'cash_sales_total' => $this->money(
                        $activeCashSession
                            ->cash_sales_total
                    ),

                    'expected_cash_now' => $this->money(
                        (
                            (float) $activeCashSession
                                ->opening_cash
                        )
                        +
                        (
                            (float) $activeCashSession
                                ->cash_sales_total
                        )
                    ),

                    'sales_count' => (int) $activeCashSession
                        ->sales_count,
                ]
                : null;

        /*
        |--------------------------------------------------------------------------
        | Sesi kasir terakhir yang sudah ditutup
        |--------------------------------------------------------------------------
        |
        | Data ini digunakan untuk menampilkan selisih kas terakhir:
        | balanced, over, atau short.
        |
        */

        $latestClosedSession =
            CashSession::query()
                ->with([
                    'cashier:id,name',
                    'closer:id,name',
                ])
                ->withCount('sales')
                ->closed()
                ->latest('closed_at')
                ->latest('id')
                ->first();

        $latestCashDifference =
            $latestClosedSession
                ? [
                    'id' => $latestClosedSession->id,

                    'session_number' => $latestClosedSession
                        ->session_number,

                    'cashier' => [
                        'id' => $latestClosedSession
                            ->cashier
                            ->id,

                        'name' => $latestClosedSession
                            ->cashier
                            ->name,
                    ],

                    'closer' => $latestClosedSession
                        ->closer
                                ? [
                                    'id' => $latestClosedSession
                                        ->closer
                                        ->id,

                                    'name' => $latestClosedSession
                                        ->closer
                                        ->name,
                                ]
                                : null,

                    'closed_at' => $latestClosedSession
                        ->closed_at
                        ?->toISOString(),

                    'opening_cash' => $this->money(
                        $latestClosedSession
                            ->opening_cash
                    ),

                    'cash_sales_total' => $this->money(
                        $latestClosedSession
                            ->cash_sales_total
                    ),

                    'expected_closing_cash' => $this->nullableMoney(
                        $latestClosedSession
                            ->expected_closing_cash
                    ),

                    'actual_closing_cash' => $this->nullableMoney(
                        $latestClosedSession
                            ->actual_closing_cash
                    ),

                    'difference' => $this->nullableMoney(
                        $latestClosedSession
                            ->difference
                    ),

                    'difference_status' => $this->differenceStatus(
                        $latestClosedSession
                            ->difference
                    ),

                    'sales_count' => (int) $latestClosedSession
                        ->sales_count,
                ]
                : null;

        /*
        |--------------------------------------------------------------------------
        | Lima transaksi terbaru
        |--------------------------------------------------------------------------
        */

        $recentSales = Sale::query()
            ->with([
                'cashier:id,name',
                'cashSession:id,session_number',
            ])
            ->withCount('items')
            ->withSum(
                'items as total_items',
                'quantity'
            )
            ->where(
                'status',
                'completed'
            )
            ->latest('sold_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(
                fn (Sale $sale) => [
                    'id' => $sale->id,

                    'sale_number' => $sale->sale_number,

                    'sold_at' => $sale->sold_at
                        ?->toISOString(),

                    'cashier' => [
                        'id' => $sale->cashier->id,

                        'name' => $sale->cashier->name,
                    ],

                    'cash_session' => [
                        'id' => $sale
                            ->cashSession
                            ->id,

                        'session_number' => $sale
                            ->cashSession
                            ->session_number,
                    ],

                    'items_count' => (int) $sale
                        ->items_count,

                    'total_items' => (int) (
                        $sale
                            ->total_items
                        ?? 0
                    ),

                    'subtotal' => $this->money(
                        $sale->subtotal
                    ),

                    'discount_amount' => $this->money(
                        $sale
                            ->discount_amount
                    ),

                    'total_amount' => $this->money(
                        $sale->total_amount
                    ),

                    'gross_profit' => $this->money(
                        $sale->gross_profit
                    ),

                    'payment_method' => $sale->payment_method,
                ]
            )
            ->values();

        return [
            'generated_at' => now()->toISOString(),

            'today' => [
                'date' => $today->toDateString(),

                'total_transactions' => $totalTransactions,

                'total_items_sold' => (int) $totalItemsSold,

                'total_subtotal' => $this->money(
                    $summaryRow
                        ?->total_subtotal
                    ?? 0
                ),

                'total_discount' => $this->money(
                    $summaryRow
                        ?->total_discount
                    ?? 0
                ),

                'total_revenue' => $totalRevenue,

                'total_cost' => $this->money(
                    $summaryRow
                        ?->total_cost
                    ?? 0
                ),

                'gross_profit' => $grossProfit,

                'average_transaction' => $averageTransaction,

                'gross_margin_percentage' => $grossMarginPercentage,
            ],

            'inventory' => [
                'low_stock_count' => $lowStockCount,

                'low_stock_products' => $lowStockProducts,
            ],

            'active_cash_session' => $activeSessionData,

            'latest_cash_difference' => $latestCashDifference,

            'recent_sales' => $recentSales,
        ];
    }

    private function differenceStatus(
        mixed $difference
    ): ?string {
        if ($difference === null) {
            return null;
        }

        $value = round(
            (float) $difference,
            2
        );

        if ($value > 0) {
            return 'over';
        }

        if ($value < 0) {
            return 'short';
        }

        return 'balanced';
    }

    private function money(
        mixed $value
    ): float {
        return round(
            (float) $value,
            2
        );
    }

    private function nullableMoney(
        mixed $value
    ): ?float {
        if ($value === null) {
            return null;
        }

        return $this->money($value);
    }
}
