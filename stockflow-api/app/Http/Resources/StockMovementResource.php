<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'type' => $this->type,

            'reference_type' => $this->reference_type,

            'reference_id' => $this->reference_id,

            'quantity_change' => $this->quantity_change,

            'stock_before' => $this->stock_before,

            'stock_after' => $this->stock_after,

            'unit_cost' => $this->unit_cost !== null
                ? (float) $this->unit_cost
                : null,

            'average_cost_before' => (float) $this->average_cost_before,

            'average_cost_after' => (float) $this->average_cost_after,

            'notes' => $this->notes,

            'movement_at' => $this->movement_at?->toISOString(),

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

            'creator' => $this->whenLoaded(
                'creator',
                function () {
                    return [
                        'id' => $this->creator->id,
                        'name' => $this->creator->name,
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
