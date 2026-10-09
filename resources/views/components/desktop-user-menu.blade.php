@props(['personnel' => false])

@if ($personnel)
    <details class="personnel-account-menu">
        <summary class="staff-settings-user" data-test="sidebar-menu-button" aria-label="{{ __('Open account menu') }}">
            <span class="staff-settings-user-avatar" id="sidebar-user-avatar">{{ auth()->user()->initials() }}</span>
            <span class="staff-settings-user-details">
                <span class="staff-settings-user-name" id="sidebar-user-name">{{ auth()->user()->name }}</span>
                <span class="staff-settings-user-role" id="sidebar-user-role">{{ auth()->user()->role }}</span>
            </span>
        </summary>
        <div class="personnel-account-options">
            <div class="personnel-account-identity"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
            <a href="{{ route('profile.edit') }}" wire:navigate><x-staff-icon name="settings" />Settings</a>
            <form method="POST" action="{{ route('logout') }}" data-personnel-logout>
                @csrf
                <button type="submit" data-test="logout-button" aria-haspopup="dialog" aria-controls="logout-dialog">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h13"/></svg>Log out
                </button>
            </form>
        </div>
    </details>
@else
<flux:dropdown position="bottom" align="start">
        <flux:sidebar.profile
            :name="auth()->user()->name"
            :initials="auth()->user()->initials()"
            icon:trailing="chevrons-up-down"
            data-test="sidebar-menu-button"
        />

    <flux:menu>
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
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
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
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>

@endif
