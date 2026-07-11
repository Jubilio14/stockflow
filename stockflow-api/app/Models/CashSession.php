<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'session_number',
        'cashier_id',
        'opened_at',
        'opening_cash',
        'cash_sales_total',
        'closed_at',
        'closed_by',
        'expected_closing_cash',
        'actual_closing_cash',
        'difference',
        'status',
        'open_guard',
        'opening_notes',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',

            'opening_cash' => 'decimal:2',
            'cash_sales_total' => 'decimal:2',

            'expected_closing_cash' => 'decimal:2',

            'actual_closing_cash' => 'decimal:2',

            'difference' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cashier_id'
        );
    }

    public function scopeOpen(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'open'
        );
    }

    public function scopeClosed(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'closed'
        );
    }

    public function sales(): HasMany
    {
        return $this->hasMany(
            Sale::class
        );
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }
}
