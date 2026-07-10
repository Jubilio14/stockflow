<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'type' => [
                'nullable',
                Rule::in([
                    'purchase',
                    'sale',
                    'adjustment',
                ]),
            ],

            'product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:100',
            ],
        ]);

        $search = trim(
            (string) ($validated['search'] ?? '')
        );

        $perPage = (int) (
            $validated['per_page'] ?? 10
        );

        $stockMovements = StockMovement::query()
            ->with([
                'product:id,name,sku,unit,image_path',
                'creator:id,name',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($movementQuery) use ($search) {
                            $movementQuery
                                ->where(
                                    'notes',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'product',
                                    function ($productQuery) use ($search) {
                                        $productQuery
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'sku',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                isset($validated['type']),
                fn ($query) => $query->where(
                    'type',
                    $validated['type']
                )
            )
            ->when(
                isset($validated['product_id']),
                fn ($query) => $query->where(
                    'product_id',
                    $validated['product_id']
                )
            )
            ->when(
                isset($validated['date_from']),
                fn ($query) => $query->whereDate(
                    'movement_at',
                    '>=',
                    $validated['date_from']
                )
            )
            ->when(
                isset($validated['date_to']),
                fn ($query) => $query->whereDate(
                    'movement_at',
                    '<=',
                    $validated['date_to']
                )
            )
            ->latest('movement_at')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return StockMovementResource::collection(
            $stockMovements
        );
    }
}
