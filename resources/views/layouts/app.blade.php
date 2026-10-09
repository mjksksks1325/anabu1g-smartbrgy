<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="account-content" role="main" tabindex="-1" class="civic-settings-main">
        <header class="staff-settings-topbar">
            <div class="staff-settings-topbar-title"><span>STAFF WORKSPACE</span><strong>{{ request()->routeIs('security.edit') ? 'Account security' : (request()->routeIs('appearance.edit') ? 'Appearance' : 'My profile') }}</strong></div>
            <div class="staff-settings-topbar-actions">
                <button id="staff-settings-theme-toggle" type="button">Dark Mode</button>
                <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.smart-cabinet.index') : route('staff.rfid-files.index') }}">IoT status</a>
                <span title="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</span>
            </div>
        </header>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
