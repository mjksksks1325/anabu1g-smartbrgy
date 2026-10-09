<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script src="{{ asset('js/staff-settings-theme.js') }}?v={{ filemtime(public_path('js/staff-settings-theme.js')) }}"></script>
        @include('partials.head')
        <link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}">
        <script src="{{ asset('js/personnel-navigation.js') }}?v={{ filemtime(public_path('js/personnel-navigation.js')) }}" data-navigate-once></script>
        <script defer data-navigate-once src="{{ asset('js/personnel-password-confirmation.js') }}?v={{ filemtime(public_path('js/personnel-password-confirmation.js')) }}"></script>
    </head>
    <body class="civic-settings min-h-screen bg-white dark:bg-zinc-800" data-personnel-layout="settings" data-personnel-super-admin="{{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}">
        <script>window.PersonnelNavigation.syncStyles();</script>
        <flux:sidebar sticky collapsible="mobile" class="civic-settings-sidebar border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a wire:navigate class="staff-settings-brand" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}">
                    <span class="staff-settings-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G logo"></span>
                    <span class="staff-settings-brand-text"><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <nav class="staff-settings-navigation" aria-label="Staff navigation">
                @if(in_array(auth()->user()->role, ['admin', 'staff'], true))
                    @if(auth()->user()->hasAnyPermission(['dashboard.view', 'demographics.view']))
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Overview</div>
                        @if(auth()->user()->hasAnyPermission(['dashboard.view']))<a wire:navigate class="nav-item" href="{{ route(auth()->user()->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>@endif
                        @if(auth()->user()->hasAnyPermission(['demographics.view']))<a wire:navigate class="nav-item" href="{{ route('staff.demographics') }}"><x-staff-icon name="people" />Demographics</a>@endif
                    </div>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['records.view', 'voters.view', 'documents.view', 'eligibility.view', 'incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Records</div>
                        @if(auth()->user()->hasAnyPermission(['records.view']))<a wire:navigate class="nav-item" href="{{ route('staff.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>@endif
                        @if(auth()->user()->hasAnyPermission(['voters.view']))<a wire:navigate class="nav-item" href="{{ route('staff.voters') }}"><x-staff-icon name="voters" />Voters</a>@endif
                        @if(auth()->user()->hasAnyPermission(['documents.view']))<a wire:navigate class="nav-item" href="{{ route('staff.document-requests.index') }}"><x-staff-icon name="certificate" />Certificates &amp; Clearances</a>@endif
                        @if(auth()->user()->hasAnyPermission(['eligibility.view', 'documents.view']))<a wire:navigate class="nav-item" href="{{ route('staff.request-eligibility') }}"><x-staff-icon name="clipboard" />Request Eligibility</a>@endif
                        @if(auth()->user()->hasAnyPermission(['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))<a wire:navigate @class(['nav-item', 'current active' => request()->routeIs('staff.incidents.*')]) aria-current="{{ request()->routeIs('staff.incidents.*') ? 'page' : 'false' }}" href="{{ route('staff.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>@endif
                    </div>
                    @endif
                @endif
                @if(auth()->user()->canTrackRfidFiles())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">IoT Security</div>
                        @if(auth()->user()->hasAnyPermission(['rfid.view']))<a wire:navigate class="nav-item" href="{{ route('staff.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>@endif
                    </div>
                @endif
                @if(auth()->user()->isSuperAdmin())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Administration</div>
                        <a wire:navigate class="nav-item" href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                        <a wire:navigate class="nav-item" href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                        <a wire:navigate class="nav-item" href="{{ route('admin.audit') }}"><x-staff-icon name="audit" />Audit Log</a>
                        <a wire:navigate class="nav-item" href="{{ route('admin.users.index') }}"><x-staff-icon name="user" />User Management</a>
                        <a wire:navigate class="nav-item" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
                    </div>
                @endif
                <div class="sidebar-sec staff-settings-account-links">
                    <a wire:navigate @class(['nav-item', 'active' => request()->routeIs('profile.edit')]) href="{{ route('profile.edit') }}" aria-current="{{ request()->routeIs('profile.edit') ? 'page' : 'false' }}"><x-staff-icon name="people" />My profile</a>
                    <a wire:navigate @class(['nav-item', 'active' => request()->routeIs('security.edit')]) href="{{ route('security.edit') }}" aria-current="{{ request()->routeIs('security.edit') ? 'page' : 'false' }}"><x-staff-icon name="shield" />Account security</a>
                    <a class="nav-item staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Portal ng Residente</a>
                </div>
            @if(auth()->user()->hasPermission('households.view') && !auth()->user()->hasPermission('records.view'))
<a wire:navigate class="nav-item" data-screen="records" href="{{ route('staff.households.page') }}"><x-staff-icon name="people" />Households</a>
                    @endif

</nav>

            <div class="staff-settings-footer hidden lg:flex">
                <x-desktop-user-menu :personnel="true" />
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

                    <form method="POST" action="{{ route('logout') }}" class="w-full" data-personnel-logout>
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
        @include('partials.personnel-password-confirmation')
        @include('partials.personnel-logout-confirmation')
    </body>
</html>
