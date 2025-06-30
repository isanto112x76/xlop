<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by user name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by user email (partial match)
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        // Filter by status (e.g. active/inactive account)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // (Optional) Filter by role if roles are stored as a field
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        // (If using a roles relationship, use whereHas on roles instead)

        $users = $query->paginate($request->input('per_page', 15));
        return UserResource::collection($users);
    }


    public function store(StoreUserRequest $request): UserResource
    {
        $validatedData = $request->validated();
        $validatedData['password'] = Hash::make($validatedData['password']);

        $user = User::create($validatedData);
        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $validatedData = $request->validated();
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']); // Nie aktualizuj hasła, jeśli nie zostało podane
        }
        $user->update($validatedData);
        return new UserResource($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        // TODO: Logika sprawdzająca, czy użytkownik nie usuwa samego siebie
        // lub czy ma odpowiednie uprawnienia.
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'Nie możesz usunąć samego siebie.'], 422);
        }
        $user->delete();
        return response()->json(null, 204);
    }
}
