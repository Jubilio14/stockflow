<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\CashSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CashSessionController extends Controller
{
    public function __construct(
        private readonly CashSessionService $cashSessionService
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

            'status' => [
                'nullable',
                Rule::in([
                    'open',
                    'closed',
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

        $cashSessions =
            CashSession::query()
                ->with([
                    'cashier:id,name',
                ])

                ->when(
                    $user->role === 'cashier',
                    fn ($query) => $query->where(
                        'cashier_id',
                        $user->id
                    )
                )

                ->when(
                    $search !== '',
                    function ($query) use ($search) {
                        $query->where(
                            function ($sessionQuery) use ($search) {
                                $sessionQuery
                                    ->where(
                                        'session_number',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'opening_notes',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'closing_notes',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereHas(
                                        'cashier',
                                        function ($cashierQuery) use ($search) {
                                            $cashierQuery->where(
                                                'name',
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
                    isset($validated['status']),
                    fn ($query) => $query->where(
                        'status',
                        $validated['status']
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
                        $validated['cashier_id']
                    )
                )

                ->when(
                    isset($validated['date_from']),
                    fn ($query) => $query->whereDate(
                        'opened_at',
                        '>=',
                        $validated['date_from']
                    )
                )

                ->when(
                    isset($validated['date_to']),
                    fn ($query) => $query->whereDate(
                        'opened_at',
                        '<=',
                        $validated['date_to']
                    )
                )

                ->latest('opened_at')
                ->latest('id')

                ->paginate($perPage)

                ->appends(
                    $request->query()
                );

        return CashSessionResource::collection(
            $cashSessions
        );
    }

    public function current(
        Request $request
    ): JsonResponse {
        $cashSession =
            CashSession::query()
                ->with([
                    'cashier:id,name',
                ])
                ->open()
                ->first();

        return response()->json([
            'session' => $cashSession
                    ? new CashSessionResource(
                        $cashSession
                    )
                    : null,

            'register_in_use' => $cashSession !== null,

            'message' => $cashSession
                    ? 'Meja kasir sedang digunakan.'
                    : 'Belum ada sesi kasir yang terbuka.',
        ]);
    }

    public function open(
        OpenCashSessionRequest $request
    ): JsonResponse {
        $cashSession =
            $this->cashSessionService
                ->open(
                    $request->validated(),
                    $request->user()
                );

        return response()->json([
            'message' => 'Sesi kasir berhasil dibuka.',

            'session' => new CashSessionResource(
                $cashSession
            ),
        ], 201);
    }

    public function show(
        Request $request,
        CashSession $cashSession
    ): CashSessionResource {
        $user = $request->user();

        if (
            $user->role === 'cashier'
            &&
            $cashSession->cashier_id !==
                $user->id
        ) {
            abort(
                403,
                'Anda tidak memiliki akses ke sesi kasir ini.'
            );
        }

        $cashSession->load([
            'cashier:id,name',
        ]);

        return new CashSessionResource(
            $cashSession
        );
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession
    ): JsonResponse {
        $closedSession =
            $this->cashSessionService
                ->close(
                    $cashSession,
                    $request->validated(),
                    $request->user()
                );

        return response()->json([
            'message' => 'Sesi kasir berhasil ditutup.',

            'session' => new CashSessionResource(
                $closedSession
            ),
        ]);
    }
}
