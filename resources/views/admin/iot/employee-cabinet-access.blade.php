@extends('layouts.admin-iot')
@section('title', 'Employee Cabinet Access')
@section('staff-ui', 'true')
@section('content')
@php
    $allErrors = $errors;
    $noErrors = new \Illuminate\Support\ViewErrorBag;
    $roleLabel = fn ($employee): string => $employee->role === 'captain' ? 'Barangay Captain' : ucfirst($employee->role);
    $enrollmentTone = fn (?string $status): string => match ($status) { 'enrolled' => 'success', 'pending' => 'warning', default => 'neutral' };
    $drawerLabel = fn (?string $drawer, string $none = 'No drawers'): string => match ($drawer ?? 'none') { 'drawer_1' => 'Drawer 1', 'drawer_2' => 'Drawer 2', 'all' => 'All drawers', default => $none };
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="Administration / Cabinet Authorization" title="Employee Cabinet Access" description="Manage cabinet permission separately from website roles. The main door is automatic after activation and enrollment; assign which drawers each employee may open." />

    <div class="mb-5 space-y-3">
        <flux:callout icon="information-circle" color="sky">
            <flux:callout.text>Enrollment requests are recorded as pending until Raspberry Pi integration is available. They do not enroll a credential or open a cabinet.</flux:callout.text>
        </flux:callout>
        @unless ($hasCompletedEnrollment)
            <flux:callout icon="exclamation-triangle" color="amber">
                <flux:callout.text>No employees have completed cabinet enrollment yet.</flux:callout.text>
            </flux:callout>
        @endunless
    </div>

    {{-- rfid-file-tracking.js filters in place: it swaps [data-filter-region] and the [data-filter-count] text. --}}
    <x-ui.panel title="Employee permissions" heading-id="employee-permissions-title" data-filter-results aria-busy="false">
        <x-slot:meta><span data-filter-count>{{ $employees->total() }} employees</span></x-slot:meta>
        <form class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 sm:flex-row sm:items-end dark:border-zinc-700" method="GET" action="{{ route('admin.cabinet-access.index') }}" autocomplete="off" role="search" aria-label="Find employees" data-auto-filter>
            <flux:field class="sm:w-72">
                <flux:label>Find employee</flux:label>
                <flux:input type="search" name="search" autocomplete="off" :value="$search" icon="magnifying-glass" placeholder="Name or email" />
            </flux:field>
            <flux:field class="sm:w-60">
                <flux:label>Cabinet access</flux:label>
                <flux:select name="access">
                    <flux:select.option value="">All employees</flux:select.option>
                    <flux:select.option value="effective" :selected="$accessFilter === 'effective'">Effective access</flux:select.option>
                    <flux:select.option value="pending" :selected="$accessFilter === 'pending'">Enabled, not yet effective</flux:select.option>
                    <flux:select.option value="disabled" :selected="$accessFilter === 'disabled'">Disabled</flux:select.option>
                </flux:select>
            </flux:field>
            <flux:button type="submit" variant="outline">Search</flux:button>
        </form>

        <div data-filter-region>
            @if ($employees->isEmpty())
                <x-ui.empty-state title="No employees found" icon="user-group">Try another name or email, or choose another access status.</x-ui.empty-state>
            @else
                <x-ui.table label="Employee cabinet permissions" table-class="lg:min-w-[56rem]">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col" class="hidden lg:table-cell">Website</th>
                            <th scope="col">Cabinet</th>
                            <th scope="col" class="hidden md:table-cell">RFID</th>
                            <th scope="col" class="hidden md:table-cell">Face</th>
                            <th scope="col" class="hidden lg:table-cell">Drawers</th>
                            <th scope="col" class="hidden md:table-cell">Access</th>
                            <th scope="col" class="text-end"><span class="sr-only">Details</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            @php($access = $employee->cabinetAccess)
                            <tr data-employee-row="{{ $employee->id }}">
                                <td>
                                    <span class="block font-medium text-zinc-800 dark:text-white" data-no-admin-translate>{{ $employee->name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400"><span data-no-admin-translate>{{ $employee->email }}</span> &middot; {{ $roleLabel($employee) }}</span>
                                    <span class="mt-1 block md:hidden"><x-ui.status-badge :tone="$access?->isEffective() ? 'success' : 'neutral'">{{ $access?->isEffective() ? 'Effective access' : 'No effective access' }}</x-ui.status-badge></span>
                                </td>
                                <td class="hidden lg:table-cell"><x-ui.status-badge :tone="$employee->is_active ? 'success' : 'neutral'">{{ $employee->is_active ? 'Active' : 'Inactive' }}</x-ui.status-badge></td>
                                <td><x-ui.status-badge :tone="$access?->is_active ? 'success' : 'neutral'">{{ $access?->is_active ? 'Enabled' : 'Disabled' }}</x-ui.status-badge></td>
                                <td class="hidden md:table-cell"><x-ui.status-badge :tone="$enrollmentTone($access?->rfid_enrollment_status)">{{ ucfirst(str_replace('_', ' ', $access?->rfid_enrollment_status ?? 'not_started')) }}</x-ui.status-badge></td>
                                <td class="hidden md:table-cell"><x-ui.status-badge :tone="$enrollmentTone($access?->face_enrollment_status)">{{ ucfirst(str_replace('_', ' ', $access?->face_enrollment_status ?? 'not_started')) }}</x-ui.status-badge></td>
                                <td class="hidden whitespace-nowrap lg:table-cell">{{ $drawerLabel($access?->drawer_access) }}</td>
                                <td class="hidden md:table-cell"><x-ui.status-badge :tone="$access?->isEffective() ? 'success' : 'neutral'" data-employee-effective>{{ $access?->isEffective() ? 'Effective access' : 'No effective access' }}</x-ui.status-badge></td>
                                <td class="text-end">
                                    <flux:modal.trigger name="employee-details-{{ $employee->id }}">
                                        <flux:button size="sm" variant="outline" aria-haspopup="dialog">Details<span class="sr-only">: {{ $employee->name }}</span></flux:button>
                                    </flux:modal.trigger>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                {{-- Details and changes open beside the list; deactivation asks for confirmation in its own dialog. --}}
                @foreach ($employees as $employee)
                    @php($access = $employee->cabinetAccess)
                    @php($failedHere = (string) old('_employee') === (string) $employee->id)
                    @scopeErrors($failedHere ? $allErrors : $noErrors)
                    <flux:modal flyout name="employee-details-{{ $employee->id }}" class="w-full md:w-[34rem]" data-employee-dialog="{{ $employee->id }}" data-labelledby="employee-details-title-{{ $employee->id }}" :data-open-on-load="$failedHere && $allErrors->any() ? '' : null">
                        <div class="space-y-6">
                            <div class="pe-8">
                                <flux:heading level="2" size="lg" id="employee-details-title-{{ $employee->id }}" data-no-admin-translate>{{ $employee->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm">Employee #{{ $employee->id }} &middot; <span data-no-admin-translate>{{ $employee->email }}</span> &middot; {{ $roleLabel($employee) }}</flux:text>
                            </div>

                            <x-ui.details>
                                <x-ui.detail label="Cabinet permission">{{ $access?->is_active ? 'Enabled' : 'Disabled' }}</x-ui.detail>
                                <x-ui.detail label="Authorization version">{{ $access?->authorization_version ?? '—' }} · Updated {{ $access?->updated_at?->format('M j, Y g:i A') ?? 'Never' }}</x-ui.detail>
                                <x-ui.detail label="RFID enrollment">{{ str_replace('_', ' ', ucfirst($access?->rfid_enrollment_status ?? 'not_started')) }} · {{ $access?->rfid_enrolled_at?->format('M j, Y g:i A') ?? 'No completion date' }}</x-ui.detail>
                                <x-ui.detail label="Face enrollment">{{ str_replace('_', ' ', ucfirst($access?->face_enrollment_status ?? 'not_started')) }} · {{ $access?->face_enrolled_at?->format('M j, Y g:i A') ?? 'No completion date' }}</x-ui.detail>
                            </x-ui.details>

                            <section class="space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-700" aria-labelledby="drawer-access-heading-{{ $employee->id }}">
                                <div>
                                    <flux:heading level="3" id="drawer-access-heading-{{ $employee->id }}">Current drawer access</flux:heading>
                                    <p class="mt-1 text-sm font-medium">{{ $drawerLabel($access?->drawer_access, 'No drawers assigned') }}</p>
                                    <flux:text class="mt-1 text-xs">Main door access is automatic after cabinet access is enabled and required enrollments are complete.</flux:text>
                                </div>
                                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" method="POST" action="{{ route('admin.cabinet-access.drawer-access.update', $employee) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_employee" value="{{ $employee->id }}">
                                    <flux:field class="flex-1">
                                        <flux:label>Assign drawer access</flux:label>
                                        <flux:select id="drawer-access-{{ $employee->id }}" name="drawer_access" aria-describedby="drawer-access-hint-{{ $employee->id }}">
                                            <flux:select.option value="none" :selected="($access?->drawer_access ?? 'none') === 'none'">No drawers assigned</flux:select.option>
                                            <flux:select.option value="drawer_1" :selected="($access?->drawer_access ?? 'none') === 'drawer_1'">Drawer 1</flux:select.option>
                                            <flux:select.option value="drawer_2" :selected="($access?->drawer_access ?? 'none') === 'drawer_2'">Drawer 2</flux:select.option>
                                            <flux:select.option value="all" :selected="($access?->drawer_access ?? 'none') === 'all'">All drawers (Drawer 1 and Drawer 2)</flux:select.option>
                                        </flux:select>
                                    </flux:field>
                                    <flux:button type="submit" variant="outline">Save drawer access</flux:button>
                                </form>
                                <flux:text id="drawer-access-hint-{{ $employee->id }}" class="text-xs">No drawers assigned means the employee may use only the main door after cabinet access and enrollment are complete.</flux:text>
                                <flux:error name="drawer_access" />
                            </section>

                            <section class="space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-700" aria-labelledby="rpi-heading-{{ $employee->id }}">
                                <div class="flex items-center justify-between gap-3">
                                    <flux:heading level="3" id="rpi-heading-{{ $employee->id }}">RPi Employee ID</flux:heading>
                                    <span class="font-mono text-sm">{{ $access?->rpi_employee_id ?? 'Not linked' }}</span>
                                </div>
                                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" method="POST" action="{{ route('admin.cabinet-access.rpi-employee-id.update', $employee) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_employee" value="{{ $employee->id }}">
                                    <flux:field class="flex-1">
                                        <flux:label>Link RPi Employee ID</flux:label>
                                        <flux:input id="rpi-employee-id-{{ $employee->id }}" name="rpi_employee_id" type="text" :value="$access?->rpi_employee_id" maxlength="32" placeholder="EMP001" autocomplete="off" aria-describedby="rpi-employee-hint-{{ $employee->id }}" />
                                    </flux:field>
                                    <flux:button type="submit" variant="outline">Save RPi ID</flux:button>
                                </form>
                                <flux:text id="rpi-employee-hint-{{ $employee->id }}" class="text-xs">Use the stable FaceLock employee ID, not the RFID card UID. Clear and save to unlink.</flux:text>
                                <flux:error name="rpi_employee_id" />
                            </section>

                            <section class="flex flex-wrap gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-700" aria-label="Cabinet access actions">
                                @if ($access?->is_active)
                                    <flux:modal.trigger name="cabinet-revoke-{{ $employee->id }}">
                                        <flux:button variant="outline" icon="no-symbol" class="text-red-700! dark:text-red-400!" aria-haspopup="dialog">Deactivate cabinet access</flux:button>
                                    </flux:modal.trigger>
                                @else
                                    <form method="POST" action="{{ route('admin.cabinet-access.update', $employee) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="1">
                                        <flux:button type="submit" variant="primary" :disabled="! $employee->is_active">Enable cabinet access</flux:button>
                                    </form>
                                @endif
                                @foreach (['rfid' => 'Request RFID enrollment', 'face' => 'Request face enrollment'] as $method => $label)
                                    <form method="POST" action="{{ route('admin.cabinet-access.enroll', [$employee, $method]) }}">
                                        @csrf
                                        <flux:button type="submit" variant="outline" :disabled="! $employee->is_active || ! $access?->is_active || $access->{$method.'_enrollment_status'} !== 'not_started'">{{ $label }}</flux:button>
                                    </form>
                                @endforeach
                            </section>
                        </div>
                    </flux:modal>

                    @if ($access?->is_active)
                        <flux:modal name="cabinet-revoke-{{ $employee->id }}" class="w-full max-w-md" data-labelledby="cabinet-revoke-title-{{ $employee->id }}">
                            <div class="space-y-5">
                                <div class="pe-8">
                                    <flux:heading level="2" size="lg" id="cabinet-revoke-title-{{ $employee->id }}">Deactivate cabinet access?</flux:heading>
                                    <flux:text class="mt-2"><span data-no-admin-translate>{{ $employee->name }}</span> will no longer have effective cabinet access. Their file movement and audit history will remain available.</flux:text>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <flux:modal.close><flux:button type="button" variant="ghost">Cancel</flux:button></flux:modal.close>
                                    <form method="POST" action="{{ route('admin.cabinet-access.update', $employee) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="0">
                                        <flux:button type="submit" variant="danger">Deactivate access</flux:button>
                                    </form>
                                </div>
                            </div>
                        </flux:modal>
                    @endif
                @endforeach
                @scopeErrors($allErrors)

                @if ($employees->hasPages())
                    <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                        <x-ui.pager :paginator="$employees" singular="employee" plural="employees" />
                    </div>
                @endif
            @endif
        </div>
    </x-ui.panel>
</div>
@endsection
