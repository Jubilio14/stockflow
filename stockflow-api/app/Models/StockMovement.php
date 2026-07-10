<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'created_by',
        'type',
        'reference_type',
        'reference_id',
        'quantity_change',
        'stock_before',
        'stock_after',
        'unit_cost',
        'average_cost_before',
        'average_cost_after',
        'notes',
        'movement_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'unit_cost' => 'decimal:4',
            'average_cost_before' => 'decimal:4',
            'average_cost_after' => 'decimal:4',
            'movement_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
