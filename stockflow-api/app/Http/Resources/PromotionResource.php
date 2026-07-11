<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->resolveStatus();

        return [
            'id' => $this->id,

            'name' => $this->name,

            'code' => $this->code,

            'discount_type' => $this->discount_type,

            'discount_value' => (float) $this->discount_value,

            'minimum_purchase' => (float) $this->minimum_purchase,

            'maximum_discount' => $this->maximum_discount !== null
                    ? (float) $this->maximum_discount
                    : null,

            'starts_at' => $this->starts_at?->toISOString(),

            'ends_at' => $this->ends_at?->toISOString(),

            'is_active' => $this->is_active,

            'status' => $status,

            'is_currently_available' => $status === 'active',

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

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function resolveStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if (now()->lt($this->starts_at)) {
            return 'upcoming';
        }

        if (now()->gt($this->ends_at)) {
            return 'expired';
        }

        return 'active';
    }
}
