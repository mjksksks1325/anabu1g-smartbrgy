<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script src="{{ asset('js/staff-settings-theme.js') }}?v={{ filemtime(public_path('js/staff-settings-theme.js')) }}"></script>
        @include('partials.head')
    </head>
    <body class="civic-settings min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="civic-settings-sidebar border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a class="staff-settings-brand" href="{{ in_array(auth()->user()->role, ['admin', 'staff'], true) ? route('admin.dashboard') : route('admin.rfid-files.index') }}">
                    <span class="staff-settings-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G logo"></span>
                    <span class="staff-settings-brand-text"><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <nav class="staff-settings-navigation" aria-label="Staff navigation">
                @if(in_array(auth()->user()->role, ['admin', 'staff'], true))
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Overview</div>
                        <a class="nav-item" href="{{ route('admin.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>
                        <a class="nav-item" href="{{ route('admin.demographics') }}"><x-staff-icon name="people" />Demographics</a>
                    </div>
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Records</div>
                        <a class="nav-item" href="{{ route('admin.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>
                        <a class="nav-item" href="{{ route('admin.voters') }}"><x-staff-icon name="check" />Voters</a>
                        <a class="nav-item" href="{{ route('admin.document-requests.index') }}"><x-staff-icon name="card" />Certificates &amp; Clearances</a>
                        <a class="nav-item" href="{{ route('admin.request-eligibility') }}"><x-staff-icon name="document" />Request Eligibility</a>
                        <a class="nav-item" href="{{ route('admin.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>
                    </div>
                @endif
                @if(auth()->user()->canTrackRfidFiles())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">IoT Security</div>
                        <a class="nav-item" href="{{ route('admin.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>
                    </div>
                @endif
                @if(auth()->user()->isSuperAdmin())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Administration</div>
                        <a class="nav-item" href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                        <a class="nav-item" href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                        <a class="nav-item" href="{{ route('admin.audit') }}"><x-staff-icon name="document" />Audit Log</a>
                        <a class="nav-item" href="{{ route('admin.users.index') }}"><x-staff-icon name="people" />User Management</a>
                        <a class="nav-item" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
                    </div>
                @endif
                <div class="sidebar-sec staff-settings-account-links">
                    <a @class(['nav-item', 'active' => request()->routeIs('profile.edit')]) href="{{ route('profile.edit') }}" aria-current="{{ request()->routeIs('profile.edit') ? 'page' : 'false' }}"><x-staff-icon name="people" />My profile</a>
                    <a @class(['nav-item', 'active' => request()->routeIs('security.edit')]) href="{{ route('security.edit') }}" aria-current="{{ request()->routeIs('security.edit') ? 'page' : 'false' }}"><x-staff-icon name="shield" />Account security</a>
                    <a class="nav-item staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Portal ng Residente</a>
                </div>
            </nav>

            <div class="staff-settings-footer hidden lg:flex">
                <x-desktop-user-menu :name="auth()->user()->name" />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <a href="#account-content" class="skip-link">Skip to account settings</a>
        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
