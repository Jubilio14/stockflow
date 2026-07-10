<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi melalui mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'unit',
        'selling_price',
        'average_cost',
        'current_stock',
        'minimum_stock',
        'image_path',
        'is_active',
    ];

    /**
     * Konversi tipe data atribut.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'average_cost' => 'decimal:4',
            'current_stock' => 'integer',
            'minimum_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kategori yang dimiliki produk.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(
            PurchaseItem::class
        );
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(
            StockMovement::class
        );
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(
            StockAdjustmentItem::class
        );
    }
}
