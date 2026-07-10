<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function create(
        array $data,
        User $user
    ): Purchase {
        return DB::transaction(
            function () use ($data, $user) {
                $purchase = Purchase::create([
                    'purchase_number' => $this->generatePurchaseNumber(),

                    'supplier_id' => $data['supplier_id'],

                    'created_by' => $user->id,

                    'invoice_number' => $data['invoice_number'] ?? null,

                    'purchase_date' => $data['purchase_date'],

                    'total_amount' => 0,

                    'status' => 'completed',

                    'notes' => $data['notes'] ?? null,
                ]);

                $totalAmount = 0;

                foreach ($data['items'] as $item) {
                    $product = Product::query()
                        ->whereKey(
                            $item['product_id']
                        )
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => [
                                'Salah satu produk sudah tidak tersedia.',
                            ],
                        ]);
                    }

                    $quantity = (int) $item['quantity'];

                    $unitCost = round(
                        (float) $item['unit_cost'],
                        2
                    );

                    $stockBefore =
                        (int) $product->current_stock;

                    $averageCostBefore =
                        (float) $product->average_cost;

                    $stockAfter =
                        $stockBefore + $quantity;

                    $oldStockValue =
                        $stockBefore *
                        $averageCostBefore;

                    $newPurchaseValue =
                        $quantity * $unitCost;

                    $averageCostAfter = round(
                        (
                            $oldStockValue +
                            $newPurchaseValue
                        ) / $stockAfter,
                        4
                    );

                    $subtotal = round(
                        $quantity * $unitCost,
                        2
                    );

                    $purchase->items()->create([
                        'product_id' => $product->id,

                        'quantity' => $quantity,

                        'unit_cost' => $unitCost,

                        'subtotal' => $subtotal,

                        'stock_before' => $stockBefore,

                        'stock_after' => $stockAfter,

                        'average_cost_before' => $averageCostBefore,

                        'average_cost_after' => $averageCostAfter,
                    ]);

                    $product->update([
                        'current_stock' => $stockAfter,

                        'average_cost' => $averageCostAfter,
                    ]);

                    StockMovement::create([
                        'product_id' => $product->id,

                        'created_by' => $user->id,

                        'type' => 'purchase',

                        'reference_type' => 'purchase',

                        'reference_id' => $purchase->id,

                        'quantity_change' => $quantity,

                        'stock_before' => $stockBefore,

                        'stock_after' => $stockAfter,

                        'unit_cost' => $unitCost,

                        'average_cost_before' => $averageCostBefore,

                        'average_cost_after' => $averageCostAfter,

                        'notes' => "Pembelian {$purchase->purchase_number}",

                        'movement_at' => now(),
                    ]);

                    $totalAmount += $subtotal;
                }

                $purchase->update([
                    'total_amount' => round(
                        $totalAmount,
                        2
                    ),
                ]);

                return $purchase->load([
                    'supplier',
                    'creator',
                    'items.product.category',
                ]);
            }
        );
    }

    private function generatePurchaseNumber(): string
    {
        do {
            $purchaseNumber = sprintf(
                'PUR-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (
            Purchase::query()
                ->where(
                    'purchase_number',
                    $purchaseNumber
                )
                ->exists()
        );

        return $purchaseNumber;
    }
}
