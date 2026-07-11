<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
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

            'discount_type' => [
                'nullable',

                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],

            'status' => [
                'nullable',

                Rule::in([
                    'active',
                    'upcoming',
                    'expired',
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
            (string) (
                $validated['search'] ?? ''
            )
        );

        $status =
            $validated['status'] ?? null;

        $perPage = (int) (
            $validated['per_page'] ?? 10
        );

        $promotions =
            Promotion::query()
                ->with([
                    'creator:id,name',
                ])
                ->when(
                    $search !== '',
                    function ($query) use ($search) {
                        $query->where(
                            function ($promotionQuery) use ($search) {
                                $promotionQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                    }
                )
                ->when(
                    isset(
                        $validated[
                            'discount_type'
                        ]
                    ),
                    fn ($query) => $query->where(
                        'discount_type',
                        $validated[
                            'discount_type'
                        ]
                    )
                )
                ->when(
                    $status === 'active',
                    fn ($query) => $query
                        ->currentlyAvailable()
                )
                ->when(
                    $status === 'upcoming',
                    fn ($query) => $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->where(
                            'starts_at',
                            '>',
                            now()
                        )
                )
                ->when(
                    $status === 'expired',
                    fn ($query) => $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->where(
                            'ends_at',
                            '<',
                            now()
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
                ->appends(
                    $request->query()
                );

        return PromotionResource::collection(
            $promotions
        );
    }

    public function available(): AnonymousResourceCollection
    {
        $promotions =
            Promotion::query()
                ->currentlyAvailable()
                ->with([
                    'creator:id,name',
                ])
                ->orderBy('name')
                ->get();

        return PromotionResource::collection(
            $promotions
        );
    }

    public function store(
        SavePromotionRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $promotion =
            Promotion::create([
                ...$validated,

                'created_by' => $request->user()->id,

                'is_active' => $validated[
                        'is_active'
                    ] ?? true,
            ]);

        $promotion->load([
            'creator:id,name',
        ]);

        return response()->json([
            'message' => 'Promo berhasil ditambahkan.',

            'promotion' => new PromotionResource(
                $promotion
            ),
        ], 201);
    }

    public function show(
        Promotion $promotion
    ): PromotionResource {
        $promotion->load([
            'creator:id,name',
        ]);

        return new PromotionResource(
            $promotion
        );
    }

    public function update(
        SavePromotionRequest $request,
        Promotion $promotion
    ): JsonResponse {
        $promotion->update(
            $request->validated()
        );

        $promotion->load([
            'creator:id,name',
        ]);

        return response()->json([
            'message' => 'Promo berhasil diperbarui.',

            'promotion' => new PromotionResource(
                $promotion
            ),
        ]);
    }

    public function toggleStatus(
        Promotion $promotion
    ): JsonResponse {
        $promotion->update([
            'is_active' => ! $promotion->is_active,
        ]);

        $promotion->load([
            'creator:id,name',
        ]);

        return response()->json([
            'message' => $promotion->is_active
                    ? 'Promo berhasil diaktifkan.'
                    : 'Promo berhasil dinonaktifkan.',

            'promotion' => new PromotionResource(
                $promotion
            ),
        ]);
    }
}
