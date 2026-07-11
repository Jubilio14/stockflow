<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\PosProductResource;
use App\Http\Resources\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Produk yang tersedia di POS
    |--------------------------------------------------------------------------
    */

    public function products(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
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
            (string) (
                $validated['search'] ?? ''
            )
        );

        $perPage = (int) (
            $validated['per_page'] ?? 20
        );

        $products = Product::query()
            ->with([
                'category:id,name',
            ])
            ->where(
                'is_active',
                true
            )
            ->where(
                'current_stock',
                '>',
                0
            )
            ->where(
                'selling_price',
                '>',
                0
            )
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
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
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                isset(
                    $validated['category_id']
                ),
                fn ($query) => $query->where(
                    'category_id',
                    $validated[
                        'category_id'
                    ]
                )
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->appends(
                $request->query()
            );

        return PosProductResource::collection(
            $products
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Riwayat penjualan
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'payment_method' => [
                'nullable',
                Rule::in([
                    'cash',
                    'qris',
                    'transfer',
                    'debit',
                ]),
            ],

            'cashier_id' => [
                'nullable',
                'integer',
                'exists:users,id',
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

            'cash_session_id' => [
                'nullable',
                'integer',
                'exists:cash_sessions,id',
            ],
        ]);

        $user = $request->user();

        $search = trim(
            (string) (
                $validated['search'] ?? ''
            )
        );

        $perPage = (int) (
            $validated['per_page'] ?? 10
        );

        $sales = Sale::query()
            ->with([
                'cashSession:id,session_number',
                'cashier:id,name',
            ])
            ->withCount('items')

            /*
             * Cashier hanya melihat transaksi
             * yang dia proses sendiri.
             */

            ->when(
                $user->role === 'cashier',
                fn ($query) => $query->where(
                    'cashier_id',
                    $user->id
                )
            )

            ->when(
                isset(
                    $validated['cash_session_id']
                ),
                fn ($query) => $query->where(
                    'cash_session_id',
                    $validated[
                        'cash_session_id'
                    ]
                )
            )

            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($saleQuery) use ($search) {
                            $saleQuery
                                ->where(
                                    'sale_number',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'promotion_code',
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
                isset(
                    $validated[
                        'payment_method'
                    ]
                ),
                fn ($query) => $query->where(
                    'payment_method',
                    $validated[
                        'payment_method'
                    ]
                )
            )

            ->when(
                isset(
                    $validated['cashier_id']
                )
                &&
                $user->role !== 'cashier',
                fn ($query) => $query->where(
                    'cashier_id',
                    $validated[
                        'cashier_id'
                    ]
                )
            )

            ->when(
                isset(
                    $validated['date_from']
                ),
                fn ($query) => $query->whereDate(
                    'sold_at',
                    '>=',
                    $validated[
                        'date_from'
                    ]
                )
            )

            ->when(
                isset(
                    $validated['date_to']
                ),
                fn ($query) => $query->whereDate(
                    'sold_at',
                    '<=',
                    $validated[
                        'date_to'
                    ]
                )
            )

            ->latest('sold_at')
            ->latest('id')
            ->paginate($perPage)
            ->appends(
                $request->query()
            );

        return SaleResource::collection(
            $sales
        );
    }

    public function store(
        StoreSaleRequest $request
    ): JsonResponse {
        $sale =
            $this->saleService->create(
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'message' => 'Transaksi penjualan berhasil disimpan.',

            'sale' => new SaleResource(
                $sale
            ),
        ], 201);
    }

    public function show(
        Request $request,
        Sale $sale
    ): SaleResource {
        $user = $request->user();

        if (
            $user->role === 'cashier'
            &&
            $sale->cashier_id !==
                $user->id
        ) {
            abort(
                403,
                'Anda tidak memiliki akses ke transaksi ini.'
            );
        }

        $sale
            ->load([
                'cashSession:id,session_number',
                'cashier:id,name',
                'items.product:id,name,sku,unit,image_path',
            ])
            ->loadCount('items');

        return new SaleResource(
            $sale
        );
    }
}
