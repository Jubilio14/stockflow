<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user dengan filter dan pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'role' => [
                'nullable',
                Rule::in([
                    'owner',
                    'admin',
                    'cashier',
                ]),
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
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);

        $users = User::query()
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($query, string $role) {
                $query->where('role', $role);
            })
            ->when($status, function ($query, string $status) {
                $query->where(
                    'is_active',
                    $status === 'active',
                );
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Membuat akun Admin atau Cashier.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Memperbarui akun Admin atau Cashier.
     */
    public function update(UpdateUserRequest $request,
        User $user,
    ): JsonResponse {
        if ($user->role === 'owner') {
            return response()->json([
                'message' => 'Akun Owner tidak dapat diubah melalui User Management.',
            ], 409);
        }

        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return response()->json([
            'message' => 'Data user berhasil diperbarui.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Mengaktifkan atau menonaktifkan akun user.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        if ($user->role === 'owner') {
            return response()->json([
                'message' => 'Akun Owner tidak dapat dinonaktifkan.',
            ], 409);
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return response()->json([
            'message' => $user->is_active
                ? 'User berhasil diaktifkan.'
                : 'User berhasil dinonaktifkan.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
