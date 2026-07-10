<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'system_stock',
        'actual_stock',
        'quantity_change',
        'average_cost_before',
        'average_cost_after',
        'inventory_value_change',
    ];

    protected function casts(): array
    {
        return [
            'system_stock' => 'integer',
            'actual_stock' => 'integer',
            'quantity_change' => 'integer',

            'average_cost_before' => 'decimal:4',

            'average_cost_after' => 'decimal:4',

            'inventory_value_change' => 'decimal:4',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(
            StockAdjustment::class,
            'stock_adjustment_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }
}
