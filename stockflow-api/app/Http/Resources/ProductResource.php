<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'category_id' => $this->category_id,

            'category' => new CategoryResource(
                $this->whenLoaded('category'),
            ),

            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit' => $this->unit,

            'selling_price' => (float) $this->selling_price,

            'average_cost' => (float) $this->average_cost,

            'current_stock' => $this->current_stock,

            'minimum_stock' => $this->minimum_stock,

            'stock_status' => match (true) {
                $this->current_stock === 0 => 'out_of_stock',

                $this->current_stock <=
                    $this->minimum_stock => 'low_stock',

                default => 'available',
            },

            'image_url' => $this->image_path
                ? asset('storage/'.$this->image_path)
                : null,

            'is_active' => $this->is_active,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
