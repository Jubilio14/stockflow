<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $canViewProfit = in_array(
            $request->user()?->role,
            [
                'owner',
                'admin',
            ],
            true
        );

        return [
            'id' => $this->id,

            'product_id' => $this->product_id,

            'product' => $this->whenLoaded(
                'product',
                function () {
                    return [
                        'id' => $this->product->id,

                        'name' => $this->product->name,

                        'sku' => $this->product->sku,

                        'unit' => $this->product->unit,

                        'image_url' => $this->product->image_path
                                ? asset(
                                    'storage/'.
                                    $this->product
                                        ->image_path
                                )
                                : null,
                    ];
                }
            ),

            'quantity' => $this->quantity,

            'selling_price' => (float) $this->selling_price,

            'subtotal' => (float) $this->subtotal,

            'allocated_discount' => (float) $this
                ->allocated_discount,

            'net_sales' => (float) $this->net_sales,

            /*
             * Data HPP dan profit hanya boleh
             * dilihat owner dan admin.
             */

            'average_cost' => $this->when(
                $canViewProfit,
                (float) $this->average_cost
            ),

            'cost_total' => $this->when(
                $canViewProfit,
                (float) $this->cost_total
            ),

            'gross_profit' => $this->when(
                $canViewProfit,
                (float) $this->gross_profit
            ),

            'stock_before' => $this->stock_before,

            'stock_after' => $this->stock_after,
        ];
    }
}
