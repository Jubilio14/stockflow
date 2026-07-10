<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'adjustment_number' => $this->adjustment_number,

            'adjustment_date' => $this->adjustment_date?->format(
                'Y-m-d'
            ),

            'reason' => $this->reason,

            'status' => $this->status,

            'notes' => $this->notes,

            'creator' => $this->whenLoaded(
                'creator',
                function () {
                    return [
                        'id' => $this->creator->id,

                        'name' => $this->creator->name,
                    ];
                }
            ),

            'items_count' => $this->whenCounted('items'),

            'items' => StockAdjustmentItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
