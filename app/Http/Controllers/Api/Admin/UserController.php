<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $users = $this->applyListQuery(
            $query,
            $request,
            ['name', 'email', 'phone'],
            ['status'],
        );

        return response()->json(['users' => $users]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,'.$id,
            'phone' => 'sometimes|nullable|string|max:20|unique:users,phone,'.$id,
            'role' => 'sometimes|string|in:customer,doctor,admin',
            'status' => 'sometimes|string|in:active,inactive',
            'specialization' => 'sometimes|nullable|string|max:255',
            'experience' => 'sometimes|nullable|string|max:50',
            'bio' => 'sometimes|nullable|string',
            'focus_areas' => 'sometimes|nullable|array',
            'password' => 'sometimes|string|min:8',
        ]);

        $user = User::findOrFail($id);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['role']) && $validated['role'] !== $user->role) {
            $role = Role::firstOrCreate(['name' => $validated['role'], 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
