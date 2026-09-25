<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\EmployeeCabinetAccess;
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

        $users = User::query()
            ->whereNull('resident_id')
            ->whereIn('role', ['admin', 'staff', 'viewer'])
            ->with('cabinetAccess')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
                'is_active',
                'is_super_admin',
                'created_at',
                'two_factor_confirmed_at',
            ]);

        return response()->json(['users' => $users]);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = new User(
                $request->safe()->only(['name', 'email', 'password'])
            );

            $user->role = $request->validated('role');
            $user->is_active = $request->boolean('is_active');
            $user->save();

            if ($request->boolean('smart_cabinet_access')) {
                $access = new EmployeeCabinetAccess;
                $access->rpi_employee_id = $request->validated('rpi_employee_id');
                $access->is_active = true;
                $access->rfid_enrollment_status = EmployeeCabinetAccess::NOT_STARTED;
                $access->face_enrollment_status = EmployeeCabinetAccess::NOT_STARTED;
                $access->authorization_version = 1;
                $user->cabinetAccess()->save($access);
            }

            return $user;
        });

        return response()->json([
            'message' => 'User account created.',
            'id' => $user->id,
        ], 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        abort_if($user->isResidentAccount() || $user->resident_id !== null, 403);

        DB::transaction(function () use ($request, $user): void {
            $locked = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            if (
                $locked->is_super_admin
                && (
                    $request->validated('role') !== 'admin'
                    || ! $request->boolean('is_active')
                )
            ) {
                throw ValidationException::withMessages([
                    'role' => 'Revoke Super Admin access with the local command before changing this account.',
                ]);
            }

            $locked->fill(
                $request->safe()->only(['name', 'email'])
            );

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

            $access = EmployeeCabinetAccess::query()
                ->where('user_id', $locked->id)
                ->lockForUpdate()
                ->first();
            $isNewAccess = $access === null;

            if ($request->has('smart_cabinet_access') && $request->boolean('smart_cabinet_access')) {
                if ($access === null) {
                    $access = new EmployeeCabinetAccess;
                    $access->rfid_enrollment_status = EmployeeCabinetAccess::NOT_STARTED;
                    $access->face_enrollment_status = EmployeeCabinetAccess::NOT_STARTED;
                    $access->authorization_version = 1;
                } elseif (! $access->is_active) {
                    $access->authorization_version++;
                }

                $access->rpi_employee_id = $request->validated('rpi_employee_id');
                $access->is_active = true;
                if ($isNewAccess) {
                    $locked->cabinetAccess()->save($access);
                } else {
                    $access->save();
                }
            } elseif ($request->has('smart_cabinet_access') && $access !== null && $access->is_active) {
                $access->is_active = false;
                $access->authorization_version++;
                $access->save();
            }
        });

        return response()->json([
            'message' => 'User account updated.',
        ]);
    }
}
