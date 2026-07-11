<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService
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

            'reason' => [
                'nullable',

                Rule::in([
                    'stock_opname',
                    'damaged',
                    'lost',
                    'expired',
                    'correction',
                    'other',
                ]),
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

        $adjustments =
            StockAdjustment::query()
                ->with([
                    'creator:id,name',
                ])
                ->withCount('items')
                ->when(
                    $search !== '',
                    function ($query) use ($search) {
                        $query->where(
                            function ($adjustmentQuery) use ($search) {
                                $adjustmentQuery
                                    ->where(
                                        'adjustment_number',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'notes',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereHas(
                                        'items.product',
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
                    isset($validated['reason']),
                    fn ($query) => $query->where(
                        'reason',
                        $validated['reason']
                    )
                )
                ->when(
                    isset($validated['date_from']),
                    fn ($query) => $query->whereDate(
                        'adjustment_date',
                        '>=',
                        $validated['date_from']
                    )
                )
                ->when(
                    isset($validated['date_to']),
                    fn ($query) => $query->whereDate(
                        'adjustment_date',
                        '<=',
                        $validated['date_to']
                    )
                )
                ->latest('adjustment_date')
                ->latest('id')
                ->paginate($perPage)
                ->appends($request->query());

        return StockAdjustmentResource::collection(
            $adjustments
        );
    }

    public function store(
        StoreStockAdjustmentRequest $request
    ): JsonResponse {
        $adjustment =
            $this->stockAdjustmentService->create(
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'message' => 'Penyesuaian stok berhasil disimpan.',

            'adjustment' => new StockAdjustmentResource(
                $adjustment
            ),
        ], 201);
    }

    public function show(
        StockAdjustment $stockAdjustment
    ): StockAdjustmentResource {
        $stockAdjustment
            ->load([
                'creator:id,name',

                'items.product:id,name,sku,unit,image_path',
            ])
            ->loadCount('items');

        return new StockAdjustmentResource(
            $stockAdjustment
        );
    }
}
