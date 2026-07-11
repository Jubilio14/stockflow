<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'sale_number',
        'cash_session_id',
        'cashier_id',
        'promotion_id',

        'promotion_name',
        'promotion_code',
        'promotion_discount_type',
        'promotion_discount_value',

        'subtotal',
        'discount_amount',
        'total_amount',

        'total_cost',
        'gross_profit',

        'payment_method',
        'amount_paid',
        'change_amount',

        'status',
        'sold_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'promotion_discount_value' => 'decimal:2',

            'subtotal' => 'decimal:2',

            'discount_amount' => 'decimal:2',

            'total_amount' => 'decimal:2',

            'total_cost' => 'decimal:4',

            'gross_profit' => 'decimal:4',

            'amount_paid' => 'decimal:2',

            'change_amount' => 'decimal:2',

            'sold_at' => 'datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(
            CashSession::class
        );
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cashier_id'
        );
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(
            Promotion::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }
}
