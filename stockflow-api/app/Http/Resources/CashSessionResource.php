<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $expectedCashNow = round(
            (float) $this->opening_cash +
            (float) $this->cash_sales_total,
            2
        );

        $currentUser =
            $request->user();

        $canViewRunningTotals =
            $this->status === 'closed'
            ||
            in_array(
                $currentUser?->role,
                [
                    'owner',
                    'admin',
                ],
                true
            );

        $isOwnedByCurrentUser =
            $currentUser?->id ===
            $this->cashier_id;

        $canClose =
            $this->status === 'open'
            &&
            (
                $isOwnedByCurrentUser
                ||
                $currentUser?->role ===
                    'owner'
            );

        return [
            'id' => $this->id,

            'session_number' => $this->session_number,

            'cashier' => $this->whenLoaded(
                'cashier',
                function () {
                    return [
                        'id' => $this->cashier->id,

                        'name' => $this->cashier->name,
                    ];
                }
            ),

            'opened_at' => $this->opened_at
                ?->toISOString(),

            'opening_cash' => (float) $this->opening_cash,

            'cash_sales_total' =>
                $canViewRunningTotals
                    ? (float) $this->cash_sales_total
                    : null,

            'expected_cash_now' =>
                $canViewRunningTotals
                    ? $expectedCashNow
                    : null,

            'closed_at' => $this->closed_at
                ?->toISOString(),

            'expected_closing_cash' => $this->expected_closing_cash !==
                null
                    ? (float) $this
                        ->expected_closing_cash
                    : null,

            'actual_closing_cash' => $this->actual_closing_cash !==
                null
                    ? (float) $this
                        ->actual_closing_cash
                    : null,

            'difference' => $this->difference !== null
                    ? (float) $this->difference
                    : null,

            'difference_status' => $this->resolveDifferenceStatus(),

            'status' => $this->status,

            'opening_notes' => $this->opening_notes,

            'closing_notes' => $this->closing_notes,

            'is_owned_by_current_user' => $isOwnedByCurrentUser,

            'can_use_pos' => $this->status === 'open'
                &&
                $isOwnedByCurrentUser,

            'can_close' => $canClose,

            'created_at' => $this->created_at
                ?->toISOString(),

            'updated_at' => $this->updated_at
                ?->toISOString(),
        ];
    }

    private function resolveDifferenceStatus(): ?string
    {
        if (
            $this->status !== 'closed'
            ||
            $this->difference === null
        ) {
            return null;
        }

        $difference =
            (float) $this->difference;

        if ($difference > 0) {
            return 'over';
        }

        if ($difference < 0) {
            return 'short';
        }

        return 'balanced';
    }
}
