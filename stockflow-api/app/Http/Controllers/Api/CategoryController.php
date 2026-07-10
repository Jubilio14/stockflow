<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar kategori.
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

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
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
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);

        $categories = Category::query()
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
                                    'description',
                                    'like',
                                    "%{$search}%",
                                );
                        },
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
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    /**
     * Menambahkan kategori baru.
     */
    public function store(
        StoreCategoryRequest $request,
    ): JsonResponse {
        $data = $request->validated();

        $category = Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'category' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Memperbarui kategori.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category,
    ): JsonResponse {
        $category->update(
            $request->validated(),
        );

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'category' => new CategoryResource(
                $category->fresh(),
            ),
        ]);
    }

    /**
     * Mengaktifkan atau menonaktifkan kategori.
     */
    public function toggleStatus(
        Category $category,
    ): JsonResponse {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return response()->json([
            'message' => $category->is_active
                ? 'Kategori berhasil diaktifkan.'
                : 'Kategori berhasil dinonaktifkan.',

            'category' => new CategoryResource(
                $category->fresh(),
            ),
        ]);
    }
}
