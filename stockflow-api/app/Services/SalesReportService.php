<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesReportService
{
    public function generate(
        array $filters
    ): array {
        $dateFrom =
            CarbonImmutable::parse(
                $filters['date_from'],
                config('app.timezone')
            )->startOfDay();

        $dateTo =
            CarbonImmutable::parse(
                $filters['date_to'],
                config('app.timezone')
            )->startOfDay();

        $dateToEnd =
            $dateTo->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Ringkasan transaksi
        |--------------------------------------------------------------------------
        */

        $summaryRow =
            $this->applySaleFilters(
                Sale::query(),
                $dateFrom,
                $dateToEnd,
                $filters
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

        /*
        |--------------------------------------------------------------------------
        | Jumlah produk terjual
        |--------------------------------------------------------------------------
        |
        | Perhitungan ini berasal dari sale_items.quantity, bukan jumlah
        | jenis produk atau jumlah transaksi.
        |
        */

        $totalItemsSold =
            $this->applySaleFilters(
                SaleItem::query()
                    ->join(
                        'sales',
                        'sales.id',
                        '=',
                        'sale_items.sale_id'
                    ),
                $dateFrom,
                $dateToEnd,
                $filters
            )
                ->sum(
                    'sale_items.quantity'
                );

        $summary =
            $this->buildSummary(
                $summaryRow,
                (int) $totalItemsSold
            );

        /*
        |--------------------------------------------------------------------------
        | Rekap transaksi per hari
        |--------------------------------------------------------------------------
        */

        $dailySales =
            $this->getDailySales(
                $dateFrom,
                $dateToEnd,
                $filters
            );

        $dailyItems =
            $this->getDailyItems(
                $dateFrom,
                $dateToEnd,
                $filters
            );

        $daily =
            $this->buildDailyReport(
                $dateFrom,
                $dateTo,
                $dailySales,
                $dailyItems
            );

        return [
            'filters' => [
                'date_from' => $dateFrom
                    ->toDateString(),

                'date_to' => $dateTo
                    ->toDateString(),

                'cashier_id' => isset(
                    $filters[
                        'cashier_id'
                    ]
                )
                        ? (int) $filters[
                            'cashier_id'
                        ]
                        : null,

                'payment_method' => $filters[
                        'payment_method'
                    ] ?? null,

                'total_days' => $dateFrom
                    ->diffInDays(
                        $dateTo
                    ) + 1,
            ],

            'summary' => $summary,

            'daily' => $daily,
        ];
    }

    private function applySaleFilters(
        Builder $query,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        array $filters
    ): Builder {
        return $query
            /*
             * Hanya transaksi yang sudah selesai
             * yang masuk ke laporan pendapatan.
             */

            ->where(
                'sales.status',
                'completed'
            )

            ->whereBetween(
                'sales.sold_at',
                [
                    $dateFrom,
                    $dateTo,
                ]
            )

            ->when(
                isset(
                    $filters['cashier_id']
                ),
                fn (Builder $query) => $query->where(
                    'sales.cashier_id',
                    $filters[
                        'cashier_id'
                    ]
                )
            )

            ->when(
                isset(
                    $filters[
                        'payment_method'
                    ]
                ),
                fn (Builder $query) => $query->where(
                    'sales.payment_method',
                    $filters[
                        'payment_method'
                    ]
                )
            );
    }

    private function getDailySales(
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        array $filters
    ): Collection {
        return $this->applySaleFilters(
            Sale::query(),
            $dateFrom,
            $dateTo,
            $filters
        )
            ->selectRaw(
                '
                DATE(sales.sold_at) AS report_date,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(sales.subtotal), 0) AS total_subtotal,
                COALESCE(SUM(sales.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sales.total_amount), 0) AS total_revenue,
                COALESCE(SUM(sales.total_cost), 0) AS total_cost,
                COALESCE(SUM(sales.gross_profit), 0) AS gross_profit
                '
            )
            ->groupByRaw(
                'DATE(sales.sold_at)'
            )
            ->orderBy('report_date')
            ->get()
            ->keyBy(
                fn ($row) => (string) $row
                    ->report_date
            );
    }

    private function getDailyItems(
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        array $filters
    ): Collection {
        return $this->applySaleFilters(
            SaleItem::query()
                ->join(
                    'sales',
                    'sales.id',
                    '=',
                    'sale_items.sale_id'
                ),
            $dateFrom,
            $dateTo,
            $filters
        )
            ->selectRaw(
                '
                DATE(sales.sold_at) AS report_date,
                COALESCE(
                    SUM(sale_items.quantity),
                    0
                ) AS total_items_sold
                '
            )
            ->groupByRaw(
                'DATE(sales.sold_at)'
            )
            ->orderBy('report_date')
            ->get()
            ->keyBy(
                fn ($row) => (string) $row
                    ->report_date
            );
    }

    private function buildSummary(
        object $summaryRow,
        int $totalItemsSold
    ): array {
        $totalTransactions =
            (int) (
                $summaryRow
                    ->total_transactions ?? 0
            );

        $totalRevenue =
            $this->money(
                $summaryRow
                    ->total_revenue ?? 0
            );

        $grossProfit =
            $this->money(
                $summaryRow
                    ->gross_profit ?? 0
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

        return [
            'total_transactions' => $totalTransactions,

            'total_items_sold' => $totalItemsSold,

            'total_subtotal' => $this->money(
                $summaryRow
                    ->total_subtotal ?? 0
            ),

            'total_discount' => $this->money(
                $summaryRow
                    ->total_discount ?? 0
            ),

            'total_revenue' => $totalRevenue,

            'total_cost' => $this->money(
                $summaryRow
                    ->total_cost ?? 0
            ),

            'gross_profit' => $grossProfit,

            'average_transaction' => $averageTransaction,

            'gross_margin_percentage' => $grossMarginPercentage,
        ];
    }

    private function buildDailyReport(
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        Collection $dailySales,
        Collection $dailyItems
    ): array {
        $rows = [];

        $currentDate =
            $dateFrom;

        while (
            $currentDate->lessThanOrEqualTo(
                $dateTo
            )
        ) {
            $date =
                $currentDate
                    ->toDateString();

            $salesRow =
                $dailySales->get(
                    $date
                );

            $itemsRow =
                $dailyItems->get(
                    $date
                );

            $totalTransactions =
                (int) (
                    $salesRow
                        ?->total_transactions
                    ?? 0
                );

            $totalRevenue =
                $this->money(
                    $salesRow
                        ?->total_revenue
                    ?? 0
                );

            $grossProfit =
                $this->money(
                    $salesRow
                        ?->gross_profit
                    ?? 0
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

            $rows[] = [
                'date' => $date,

                'total_transactions' => $totalTransactions,

                'total_items_sold' => (int) (
                    $itemsRow
                        ?->total_items_sold
                    ?? 0
                ),

                'total_subtotal' => $this->money(
                    $salesRow
                        ?->total_subtotal
                    ?? 0
                ),

                'total_discount' => $this->money(
                    $salesRow
                        ?->total_discount
                    ?? 0
                ),

                'total_revenue' => $totalRevenue,

                'total_cost' => $this->money(
                    $salesRow
                        ?->total_cost
                    ?? 0
                ),

                'gross_profit' => $grossProfit,

                'average_transaction' => $averageTransaction,

                'gross_margin_percentage' => $grossMarginPercentage,
            ];

            $currentDate =
                $currentDate->addDay();
        }

        return $rows;
    }

    private function money(
        mixed $value
    ): float {
        return round(
            (float) $value,
            2
        );
    }
}
