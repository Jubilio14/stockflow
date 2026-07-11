<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',

        'selling_price',
        'average_cost',

        'subtotal',
        'allocated_discount',
        'net_sales',

        'cost_total',
        'gross_profit',

        'stock_before',
        'stock_after',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',

            'selling_price' => 'decimal:2',

            'average_cost' => 'decimal:4',

            'subtotal' => 'decimal:2',

            'allocated_discount' => 'decimal:2',

            'net_sales' => 'decimal:2',

            'cost_total' => 'decimal:4',

            'gross_profit' => 'decimal:4',

            'stock_before' => 'integer',

            'stock_after' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }
}
