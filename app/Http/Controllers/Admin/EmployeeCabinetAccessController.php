<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeCabinetAccessController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;
        $employees = User::query()
            ->whereNull('resident_id')
            ->whereIn('role', ['admin', 'captain', 'staff', 'viewer'])
            ->with('cabinetAccess')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.trim($search).'%')
                ->orWhere('email', 'like', '%'.trim($search).'%')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        $hasCompletedEnrollment = EmployeeCabinetAccess::query()
            ->where('rfid_enrollment_status', EmployeeCabinetAccess::ENROLLED)
            ->where('face_enrollment_status', EmployeeCabinetAccess::ENROLLED)
            ->exists();

        return view('admin.iot.employee-cabinet-access', compact('employees', 'search', 'hasCompletedEnrollment'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->ensureEmployee($user);
        if ($validated['is_active'] && ! $user->is_active) {
            throw ValidationException::withMessages(['is_active' => 'Activate the website account before enabling cabinet access.']);
        }

        DB::transaction(function () use ($user, $validated): void {
            $access = EmployeeCabinetAccess::query()->firstOrCreate(['user_id' => $user->id]);
            $access->is_active = (bool) $validated['is_active'];
            $access->authorization_version++;
            $access->save();
        });

        return back()->with('status', $validated['is_active'] ? 'Cabinet access enabled. Enrollment is still required.' : 'Cabinet access deactivated. Historical records are retained.');
    }

    public function updateDrawerAccess(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'drawer_access' => ['required', Rule::in([
                EmployeeCabinetAccess::DRAWER_1,
                EmployeeCabinetAccess::DRAWER_2,
                EmployeeCabinetAccess::DRAWERS_ALL,
                EmployeeCabinetAccess::DRAWERS_NONE,
            ])],
        ]);
        $this->ensureEmployee($user);

        DB::transaction(function () use ($request, $user, $validated): void {
            $access = EmployeeCabinetAccess::query()->firstOrCreate(['user_id' => $user->id]);
            $before = $access->drawer_access ?? EmployeeCabinetAccess::DRAWERS_NONE;
            if ($before === $validated['drawer_access']) {
                return;
            }

            $access->drawer_access = $validated['drawer_access'];
            $access->authorization_version++;
            $access->save();

            DB::table('administrative_audits')->insert([
                'user_id' => $request->user()->id,
                'actor' => $request->user()->name,
                'action' => 'admin.cabinet-access.drawer-assignment-changed',
                'type' => 'security',
                'record' => (string) $user->id,
                'target_user_id' => $user->id,
                'before_assignments' => json_encode(['drawer_access' => $before], JSON_THROW_ON_ERROR),
                'after_assignments' => json_encode(['drawer_access' => $validated['drawer_access']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Drawer access assignment saved. The main door remains automatic after enrollment.');
    }

    public function updateRpiEmployeeId(Request $request, User $user): RedirectResponse
    {
        $this->ensureEmployee($user);

        $input = $request->input('rpi_employee_id');
        if (is_string($input)) {
            $normalizedId = Str::upper(trim($input));
            $request->merge(['rpi_employee_id' => $normalizedId === '' ? null : $normalizedId]);
        }

        $validated = $request->validate([
            'rpi_employee_id' => [
                'present', 'nullable', 'string', 'max:32',
                'regex:/\A[A-Z][A-Z0-9_-]*\z/',
                Rule::unique('employee_cabinet_access', 'rpi_employee_id')->ignore($user->id, 'user_id'),
            ],
        ]);

        $access = EmployeeCabinetAccess::query()->firstOrCreate(['user_id' => $user->id]);
        $access->rpi_employee_id = $validated['rpi_employee_id'];
        $access->save();

        return back()->with('status', $access->rpi_employee_id === null ? 'RPi Employee ID unlinked.' : 'RPi Employee ID saved.');
    }

    public function enroll(User $user, string $method): RedirectResponse
    {
        $this->ensureEmployee($user);
        abort_unless(in_array($method, ['rfid', 'face'], true), 404);
        $access = $user->cabinetAccess;
        if (! $user->is_active || ! $access?->is_active) {
            throw ValidationException::withMessages(['enrollment' => 'Enable the employee account and cabinet access first.']);
        }

        $attribute = $method.'_enrollment_status';
        if ($access->{$attribute} !== EmployeeCabinetAccess::NOT_STARTED) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment has already been requested or completed.']);
        }

        $access->{$attribute} = EmployeeCabinetAccess::PENDING;
        $access->save();

        return back()->with('status', strtoupper($method).' enrollment request recorded. Waiting for Raspberry Pi integration.');
    }

    private function ensureEmployee(User $user): void
    {
        abort_if($user->isResidentAccount() || $user->resident_id !== null || ! in_array($user->role, ['admin', 'captain', 'staff', 'viewer'], true), 404);
    }
}
