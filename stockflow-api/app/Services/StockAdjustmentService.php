<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function create(
        array $data,
        User $user
    ): StockAdjustment {
        return DB::transaction(
            function () use ($data, $user) {
                $adjustment =
                    StockAdjustment::create([
                        'adjustment_number' => $this->generateAdjustmentNumber(),

                        'created_by' => $user->id,

                        'adjustment_date' => $data['adjustment_date'],

                        'reason' => $data['reason'],

                        'status' => 'completed',

                        'notes' => $data['notes'] ?? null,
                    ]);

                $movementAt = Carbon::parse(
                    $data['adjustment_date'].' '.
                    now()->format('H:i:s'),
                    config('app.timezone')
                );

                foreach (
                    $data['items'] as $index => $item
                ) {
                    $product = Product::query()
                        ->whereKey(
                            $item['product_id']
                        )
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => [
                                'Produk tidak ditemukan.',
                            ],
                        ]);
                    }

                    $systemStock =
                        (int) $product->current_stock;

                    $actualStock =
                        (int) $item['actual_stock'];

                    $quantityChange =
                        $actualStock - $systemStock;

                    if ($quantityChange === 0) {
                        throw ValidationException::withMessages([
                            "items.{$index}.actual_stock" => [
                                'Stok fisik sama dengan stok sistem sehingga tidak perlu disesuaikan.',
                            ],
                        ]);
                    }

                    $averageCostBefore =
                        (float) $product->average_cost;

                    $averageCostAfter =
                        $averageCostBefore;

                    $inventoryValueChange = round(
                        $quantityChange *
                        $averageCostBefore,
                        4
                    );

                    $adjustment->items()->create([
                        'product_id' => $product->id,

                        'system_stock' => $systemStock,

                        'actual_stock' => $actualStock,

                        'quantity_change' => $quantityChange,

                        'average_cost_before' => $averageCostBefore,

                        'average_cost_after' => $averageCostAfter,

                        'inventory_value_change' => $inventoryValueChange,
                    ]);

                    $product->update([
                        'current_stock' => $actualStock,
                    ]);

                    StockMovement::create([
                        'product_id' => $product->id,

                        'created_by' => $user->id,

                        'type' => 'adjustment',

                        'reference_type' => 'stock_adjustment',

                        'reference_id' => $adjustment->id,

                        'quantity_change' => $quantityChange,

                        'stock_before' => $systemStock,

                        'stock_after' => $actualStock,

                        'unit_cost' => null,

                        'average_cost_before' => $averageCostBefore,

                        'average_cost_after' => $averageCostAfter,

                        'notes' => $this->movementNotes(
                            $adjustment->adjustment_number,
                            $data['reason'],
                            $data['notes'] ?? null
                        ),

                        'movement_at' => $movementAt,
                    ]);
                }

                return $adjustment->load([
                    'creator:id,name',
                    'items.product:id,name,sku,unit,image_path',
                ]);
            }
        );
    }

    private function generateAdjustmentNumber(): string
    {
        do {
            $adjustmentNumber = sprintf(
                'ADJ-%s-%s',
                now()->format('Ymd'),
                Str::upper(
                    Str::random(6)
                )
            );
        } while (
            StockAdjustment::query()
                ->where(
                    'adjustment_number',
                    $adjustmentNumber
                )
                ->exists()
        );

        return $adjustmentNumber;
    }

    private function movementNotes(
        string $adjustmentNumber,
        string $reason,
        ?string $notes
    ): string {
        $reasonLabel = match ($reason) {
            'stock_opname' => 'Stock opname',
            'damaged' => 'Barang rusak',
            'lost' => 'Barang hilang',
            'expired' => 'Barang kedaluwarsa',
            'correction' => 'Koreksi data',
            default => 'Lainnya',
        };

        $movementNotes =
            "Penyesuaian {$adjustmentNumber} - {$reasonLabel}";

        if ($notes) {
            $movementNotes .= ": {$notes}";
        }

        return $movementNotes;
    }
}
