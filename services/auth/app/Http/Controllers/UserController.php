<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request)
    {
        $data = $this->users->paginate(
            $request->only(['search', 'role', 'is_active']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'data' => UserResource::collection($data->items()),
            'meta' => [
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());

        return response()->json([
            'message' => 'User dibuat.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(string $id)
    {
        $user = User::find($id);

        if (! $user) {
            throw ApiError::make(404, 'NOT_FOUND', 'User tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Detail user.',
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::find($id);

        if (! $user) {
            throw ApiError::make(404, 'NOT_FOUND', 'User tidak ditemukan.');
        }

        return response()->json([
            'message' => 'User diperbarui.',
            'data' => new UserResource($this->users->update($user, $request->validated())),
        ]);
    }

    public function setStatus(UserStatusRequest $request, string $id)
    {
        $user = User::find($id);

        if (! $user) {
            throw ApiError::make(404, 'NOT_FOUND', 'User tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Status user diperbarui.',
            'data' => new UserResource(
                $this->users->setStatus(
                    $request->attributes->get('authUser'),
                    $user,
                    $request->boolean('is_active')
                )
            ),
        ]);
    }
}
