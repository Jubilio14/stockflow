<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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

            'sale_number' => $this->sale_number,

            'cash_session' => $this->whenLoaded(
                'cashSession',
                function () {
                    return [
                        'id' => $this->cashSession->id,

                        'session_number' => $this->cashSession
                            ->session_number,
                    ];
                }
            ),

            'cashier' => $this->whenLoaded(
                'cashier',
                function () {
                    return [
                        'id' => $this->cashier->id,

                        'name' => $this->cashier->name,
                    ];
                }
            ),

            'promotion' => $this->promotion_name
                    ? [
                    'id' => $this->promotion_id,

                    'name' => $this->promotion_name,

                    'code' => $this->promotion_code,

                    'discount_type' => $this
                        ->promotion_discount_type,

                    'discount_value' => (float) $this
                        ->promotion_discount_value,
                ]
                    : null,

            'subtotal' => (float) $this->subtotal,

            'discount_amount' => (float) $this
                ->discount_amount,

            'total_amount' => (float) $this->total_amount,

            'total_cost' => $this->when(
                $canViewProfit,
                (float) $this->total_cost
            ),

            'gross_profit' => $this->when(
                $canViewProfit,
                (float) $this->gross_profit
            ),

            'payment_method' => $this->payment_method,

            'amount_paid' => (float) $this->amount_paid,

            'change_amount' => (float) $this->change_amount,

            'status' => $this->status,

            'sold_at' => $this->sold_at
                ?->toISOString(),

            'notes' => $this->notes,

            'items_count' => $this->whenCounted('items'),

            'items' => SaleItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at
                ?->toISOString(),
        ];
    }
}
