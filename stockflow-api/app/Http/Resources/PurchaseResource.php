<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'purchase_number' => $this->purchase_number,

            'invoice_number' => $this->invoice_number,

            'purchase_date' => $this->purchase_date?->format(
                'Y-m-d'
            ),

            'total_amount' => (float) $this->total_amount,

            'status' => $this->status,

            'notes' => $this->notes,

            'supplier' => $this->whenLoaded(
                'supplier',
                function () {
                    return [
                        'id' => $this->supplier->id,
                        'code' => $this->supplier->code,
                        'name' => $this->supplier->name,
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

            'items_count' => $this->whenCounted('items'),

            'items' => PurchaseItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
