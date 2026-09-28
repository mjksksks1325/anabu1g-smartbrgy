<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6 staff-login-form">
        <p class="staff-login-eyebrow">Staff sign in</p>
        <x-auth-header :title="__('Welcome back')" :description="__('Use your barangay-issued staff credentials.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Staff email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="name@anabu1g.gov.ph"
            />

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember this authorized device')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Sign in to staff portal') }}
                </flux:button>
            </div>
        </form>
        <div class="staff-login-links">
            @if (Route::has('password.request'))
                <flux:link :href="route('password.request')" wire:navigate>{{ __('Forgot your password?') }}</flux:link>
            @endif
            <a href="{{ route('home') }}#office">Contact barangay office</a>
        </div>
        <p class="staff-login-security">This system contains protected resident information. Access is logged and monitored.</p>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
