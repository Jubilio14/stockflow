<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar produk.
     */
    public function index(
        Request $request,
    ): AnonymousResourceCollection {
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'stock_status' => [
                'nullable',
                Rule::in([
                    'available',
                    'low_stock',
                    'out_of_stock',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:100',
            ],
        ]);

        $search = $filters['search'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $status = $filters['status'] ?? null;
        $stockStatus =
            $filters['stock_status'] ?? null;

        $perPage = (int) (
            $filters['per_page'] ?? 10
        );

        $products = Product::query()
            ->with('category')

            ->when(
                $search,
                function ($query, string $search) {
                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'sku',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )

            ->when(
                $categoryId,
                function ($query, int $categoryId) {
                    $query->where(
                        'category_id',
                        $categoryId,
                    );
                },
            )

            ->when(
                $status,
                function ($query, string $status) {
                    $query->where(
                        'is_active',
                        $status === 'active',
                    );
                },
            )

            ->when(
                $stockStatus,
                function (
                    $query,
                    string $stockStatus,
                ) {
                    if ($stockStatus === 'out_of_stock') {
                        $query->where(
                            'current_stock',
                            0,
                        );

                        return;
                    }

                    if ($stockStatus === 'low_stock') {
                        $query
                            ->where(
                                'current_stock',
                                '>',
                                0,
                            )
                            ->whereColumn(
                                'current_stock',
                                '<=',
                                'minimum_stock',
                            );

                        return;
                    }

                    $query->whereColumn(
                        'current_stock',
                        '>',
                        'minimum_stock',
                    );
                },
            )

            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ProductResource::collection(
            $products,
        );
    }

    /**
     * Menambahkan produk baru.
     */
    public function store(
        StoreProductRequest $request,
    ): JsonResponse {
        $data = $request->validated();

        $imagePath = $request->hasFile('image')
            ? $request
                ->file('image')
                ->store('products', 'public')
            : null;

        $product = Product::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'unit' => $data['unit'],

            'selling_price' => $data['selling_price'],

            'average_cost' => 0,
            'current_stock' => 0,

            'minimum_stock' => $data['minimum_stock'],

            'image_path' => $imagePath,

            'is_active' => $data['is_active'] ?? true,
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',

            'product' => new ProductResource($product),
        ], 201);
    }

    /**
     * Memperbarui informasi produk.
     */
    public function update(
        UpdateProductRequest $request,
        Product $product,
    ): JsonResponse {
        $data = $request->validated();

        $oldImagePath = $product->image_path;

        $newImagePath = $request->hasFile('image')
            ? $request
                ->file('image')
                ->store('products', 'public')
            : null;

        $updateData = [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'unit' => $data['unit'],

            'selling_price' => $data['selling_price'],

            'minimum_stock' => $data['minimum_stock'],
        ];

        if ($newImagePath) {
            $updateData['image_path'] =
                $newImagePath;
        }

        $product->update($updateData);

        if (
            $newImagePath &&
            $oldImagePath
        ) {
            Storage::disk('public')
                ->delete($oldImagePath);
        }

        $product->load('category');

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',

            'product' => new ProductResource(
                $product->fresh(['category']),
            ),
        ]);
    }

    /**
     * Mengaktifkan atau menonaktifkan produk.
     */
    public function toggleStatus(
        Product $product,
    ): JsonResponse {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return response()->json([
            'message' => $product->is_active
                ? 'Produk berhasil diaktifkan.'
                : 'Produk berhasil dinonaktifkan.',

            'product' => new ProductResource(
                $product->fresh(['category']),
            ),
        ]);
    }
}
