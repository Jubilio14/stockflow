<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
                                    $this->product->image_path
                                )
                                : null,
                    ];
                }
            ),

            'quantity' => $this->quantity,

            'unit_cost' => (float) $this->unit_cost,

            'subtotal' => (float) $this->subtotal,

            'stock_before' => $this->stock_before,

            'stock_after' => $this->stock_after,

            'average_cost_before' => (float) $this->average_cost_before,

            'average_cost_after' => (float) $this->average_cost_after,
        ];
    }
}
