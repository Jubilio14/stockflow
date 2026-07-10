<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService
    ) {}

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'supplier_id' => [
                'nullable',
                'integer',
                'exists:suppliers,id',
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

        $purchases = Purchase::query()
            ->with([
                'supplier:id,code,name',
                'creator:id,name',
            ])
            ->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($purchaseQuery) use ($search) {
                            $purchaseQuery
                                ->where(
                                    'purchase_number',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'invoice_number',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                isset($validated['supplier_id']),
                fn ($query) => $query->where(
                    'supplier_id',
                    $validated['supplier_id']
                )
            )
            ->when(
                isset($validated['date_from']),
                fn ($query) => $query->whereDate(
                    'purchase_date',
                    '>=',
                    $validated['date_from']
                )
            )
            ->when(
                isset($validated['date_to']),
                fn ($query) => $query->whereDate(
                    'purchase_date',
                    '<=',
                    $validated['date_to']
                )
            )
            ->latest('purchase_date')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return PurchaseResource::collection(
            $purchases
        );
    }

    public function store(
        StorePurchaseRequest $request
    ): JsonResponse {
        $purchase = $this->purchaseService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Pembelian berhasil dicatat.',

            'purchase' => new PurchaseResource($purchase),
        ], 201);
    }

    public function show(
        Purchase $purchase
    ): PurchaseResource {
        $purchase->load([
            'supplier:id,code,name',
            'creator:id,name',
            'items.product:id,category_id,name,sku,unit,image_path',
        ]);

        return new PurchaseResource($purchase);
    }
}
