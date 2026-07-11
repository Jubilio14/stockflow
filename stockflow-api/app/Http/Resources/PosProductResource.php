<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'sku' => $this->sku,

            'barcode' => $this->barcode,

            'unit' => $this->unit,

            'selling_price' => (float) $this->selling_price,

            'current_stock' => $this->current_stock,

            'image_url' => $this->image_path
                    ? asset(
                        'storage/'.
                        $this->image_path
                    )
                    : null,

            'category' => $this->whenLoaded(
                'category',
                function () {
                    return [
                        'id' => $this->category->id,

                        'name' => $this->category->name,
                    ];
                }
            ),
        ];
    }
}
