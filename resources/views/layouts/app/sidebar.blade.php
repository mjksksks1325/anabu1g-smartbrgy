<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script src="{{ asset('js/staff-settings-theme.js') }}?v={{ filemtime(public_path('js/staff-settings-theme.js')) }}"></script>
        @include('partials.head')
        <link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.admin-localization-scripts')
        <script src="{{ asset('js/personnel-navigation.js') }}?v={{ filemtime(public_path('js/personnel-navigation.js')) }}" data-navigate-once></script>
        <script defer data-navigate-once src="{{ asset('js/personnel-password-confirmation.js') }}?v={{ filemtime(public_path('js/personnel-password-confirmation.js')) }}"></script>
        <link rel="stylesheet" href="{{ asset('css/password-visibility.css') }}?v={{ filemtime(public_path('css/password-visibility.css')) }}">
        <script defer data-navigate-once src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
        <link rel="stylesheet" href="{{ asset('css/form-feedback.css') }}?v={{ filemtime(public_path('css/form-feedback.css')) }}">
        <script defer data-navigate-once src="{{ asset('js/form-feedback.js') }}?v={{ filemtime(public_path('js/form-feedback.js')) }}"></script>
    </head>
    <body class="civic-settings min-h-screen bg-white dark:bg-zinc-800" data-admin-localization data-personnel-layout="settings" data-personnel-super-admin="{{ auth()->user()->hasAdministrativeAccess() ? 'true' : 'false' }}">
        <script>window.PersonnelNavigation.syncStyles();</script>
        <flux:sidebar sticky collapsible="mobile" class="civic-settings-sidebar border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a wire:navigate.hover class="staff-settings-brand" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}">
                    <span class="staff-settings-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G logo"></span>
                    <span class="staff-settings-brand-text"><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <nav class="staff-settings-navigation" aria-label="Staff navigation">
                @if(in_array(auth()->user()->role, ['admin', 'captain', 'staff'], true))
                    @if(auth()->user()->hasAnyPermission(['dashboard.view', 'demographics.view']))
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Overview</div>
                        @if(auth()->user()->hasAnyPermission(['dashboard.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.dashboard', 'staff.dashboard')]) aria-current="{{ request()->routeIs('admin.dashboard', 'staff.dashboard') ? 'page' : 'false' }}" href="{{ route(auth()->user()->hasAdministrativeAccess() ? 'admin.dashboard' : 'staff.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>@endif
                        @if(auth()->user()->hasAnyPermission(['demographics.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.demographics')]) aria-current="{{ request()->routeIs('staff.demographics') ? 'page' : 'false' }}" href="{{ route('staff.demographics') }}"><x-staff-icon name="people" />Demographics</a>@endif
                    </div>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['records.view', 'households.view', 'voters.view', 'documents.view', 'eligibility.view', 'incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Records</div>
                        @if(auth()->user()->hasPermission('households.view') && !auth()->user()->hasPermission('records.view'))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.households.*')]) aria-current="{{ request()->routeIs('staff.households.*') ? 'page' : 'false' }}" data-screen="records" href="{{ route('staff.households.page') }}"><x-staff-icon name="people" />Households</a>@endif
                        @if(auth()->user()->hasAnyPermission(['records.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.residents.*', 'staff.resident-applications.*')]) aria-current="{{ request()->routeIs('staff.residents.*', 'staff.resident-applications.*') ? 'page' : 'false' }}" href="{{ route('staff.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>@endif
                        @if(auth()->user()->hasAnyPermission(['voters.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.voters', 'staff.voter-registrations.*')]) aria-current="{{ request()->routeIs('staff.voters', 'staff.voter-registrations.*') ? 'page' : 'false' }}" href="{{ route('staff.voters') }}"><x-staff-icon name="voters" />Voters</a>@endif
                        @if(auth()->user()->hasAnyPermission(['documents.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.document-requests.*', 'staff.issued-certificates.*')]) aria-current="{{ request()->routeIs('staff.document-requests.*', 'staff.issued-certificates.*') ? 'page' : 'false' }}" href="{{ route('staff.document-requests.index') }}"><x-staff-icon name="certificate" />Certificates &amp; Clearances</a>@endif
                        @if(auth()->user()->hasAnyPermission(['eligibility.view', 'documents.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.request-eligibility')]) aria-current="{{ request()->routeIs('staff.request-eligibility') ? 'page' : 'false' }}" href="{{ route('staff.request-eligibility') }}"><x-staff-icon name="clipboard" />Request Eligibility</a>@endif
                        @if(auth()->user()->hasAnyPermission(['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.incidents.*')]) aria-current="{{ request()->routeIs('staff.incidents.*') ? 'page' : 'false' }}" href="{{ route('staff.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>@endif
                    </div>
                    @endif
                @endif
                @if(auth()->user()->canTrackRfidFiles())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">IoT Security</div>
                        @if(auth()->user()->hasAnyPermission(['rfid.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.rfid-files.*')]) aria-current="{{ request()->routeIs('staff.rfid-files.*') ? 'page' : 'false' }}" href="{{ route('staff.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>@endif
                    </div>
                @endif
                @if(auth()->user()->hasAdministrativeAccess())
                    <div class="sidebar-sec">
                        <div class="sidebar-label">Administration</div>
                        <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.smart-cabinet.*')]) aria-current="{{ request()->routeIs('admin.smart-cabinet.*') ? 'page' : 'false' }}" href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                        <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.cabinet-access.*')]) aria-current="{{ request()->routeIs('admin.cabinet-access.*') ? 'page' : 'false' }}" href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                        <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.audit', 'admin.audit.*')]) aria-current="{{ request()->routeIs('admin.audit', 'admin.audit.*') ? 'page' : 'false' }}" href="{{ route('admin.audit') }}"><x-staff-icon name="audit" />Audit Log</a>
                        <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.users.*')]) aria-current="{{ request()->routeIs('admin.users.*') ? 'page' : 'false' }}" href="{{ route('admin.users.index') }}"><x-staff-icon name="user" />User Management</a>
                        <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.settings', 'profile.*', 'settings.*')]) aria-current="{{ request()->routeIs('admin.settings', 'profile.*', 'settings.*') ? 'page' : 'false' }}" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
                    </div>
                @endif
                <div class="sidebar-sec staff-settings-account-links">
                    <a class="nav-item staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Portal ng Residente</a>
                </div>

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
