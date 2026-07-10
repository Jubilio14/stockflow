<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
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

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                ]),
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

        $status = $validated['status'] ?? null;

        $perPage = (int) (
            $validated['per_page'] ?? 10
        );

        $suppliers = Supplier::query()
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($supplierQuery) use ($search) {
                            $supplierQuery
                                ->where(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'contact_person',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $status === 'active',
                fn ($query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where(
                    'is_active',
                    false
                )
            )
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return SupplierResource::collection(
            $suppliers
        );
    }

    public function store(
        StoreSupplierRequest $request
    ): JsonResponse {
        $supplier = Supplier::create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan.',
            'supplier' => new SupplierResource(
                $supplier
            ),
        ], 201);
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier
    ): JsonResponse {
        $supplier->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil diperbarui.',
            'supplier' => new SupplierResource(
                $supplier->fresh()
            ),
        ]);
    }

    public function toggleStatus(
        Supplier $supplier
    ): JsonResponse {
        $supplier->update([
            'is_active' => ! $supplier->is_active,
        ]);

        $message = $supplier->is_active
            ? 'Supplier berhasil diaktifkan.'
            : 'Supplier berhasil dinonaktifkan.';

        return response()->json([
            'message' => $message,
            'supplier' => new SupplierResource(
                $supplier->fresh()
            ),
        ]);
    }
}
