<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return response()->json(['users' => User::query()->whereNull('resident_id')->whereIn('role', ['admin', 'staff', 'viewer'])->orderBy('name')->get([
            'id', 'name', 'email', 'role', 'is_active', 'is_super_admin', 'created_at', 'two_factor_confirmed_at',
        ])]);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = $request->validated('role');
        $user->is_active = $request->boolean('is_active');
        $user->save();

        return response()->json(['message' => 'User account created.', 'id' => $user->id], 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        abort_if($user->isResidentAccount() || $user->resident_id !== null, 403);

        DB::transaction(function () use ($request, $user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->is_super_admin && ($request->validated('role') !== 'admin' || ! $request->boolean('is_active'))) {
                throw ValidationException::withMessages(['role' => 'Revoke Super Admin access with the local command before changing this account.']);
            }

            $locked->fill($request->safe()->only(['name', 'email']));
            $locked->role = $request->validated('role');
            $locked->is_active = $request->boolean('is_active');

            if ($request->filled('password')) {
                $locked->password = $request->validated('password');
                $locked->remember_token = null;
            }

            if (! $locked->is_active) {
                $locked->remember_token = null;
            }

            $locked->save();
        });

        return response()->json(['message' => 'User account updated.']);
    }
}
