<section class="w-full">
    @if ($enrollmentRequired)
        <div data-two-factor-required class="staff-enrollment-content">
            @include('livewire.settings.two-factor.setup-content')
        </div>
    @else
        @include('partials.settings-heading')

        <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

        <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
            <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
                <flux:input
                    wire:model="current_password"
                    :label="__('Current password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
                <flux:input
                    wire:model="password"
                    :label="__('New password')"
                    :description="__('At least 12 characters, with an uppercase and a lowercase letter, a number and a symbol (a period counts).').(app()->isProduction() ? ' '.__('It must not appear in a known data breach.') : '')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="update-password-button">{{ __('Save') }}</flux:button>
                </div>
            </form>

            @if ($canManageTwoFactor)
                <section class="mt-12">
                    <flux:heading>{{ __('Two-factor authentication') }}</flux:heading>
                    <flux:subheading>{{ __('Manage your two-factor authentication settings') }}</flux:subheading>
                    @php($twoFactorRequired = config('access.privileged_two_factor') && auth()->user()->requiresTwoFactorAuthentication())
                    @if ($twoFactorRequired && ! $twoFactorEnabled)
                        <flux:callout variant="warning" icon="shield-exclamation" class="mt-4" data-two-factor-required>
                            <flux:callout.heading>{{ __('Two-factor authentication is required for your account') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Your account has privileged access (confidential cases, resident records, certificates or administration). Set up two-factor authentication with an authenticator app to continue using the workspace. Keep the recovery codes somewhere safe.') }}</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div class="space-y-4">
                                <flux:text>
                                    {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                                </flux:text>

                                @if ($twoFactorRequired)
                                    <flux:text variant="subtle">{{ __('Two-factor authentication is required for your account, so it cannot be turned off.') }}</flux:text>
                                @else
                                    <div class="flex justify-start">
                                        <flux:button
                                            variant="danger"
                                            wire:click="disable"
                                        >
                                            {{ __('Disable 2FA') }}
                                        </flux:button>
                                    </div>
                                @endif

                                <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div class="space-y-4">
                                <flux:text variant="subtle">
                                    {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                                </flux:text>

                                <flux:button
                                    variant="primary"
                                    wire:click="enable"
                                >
                                    {{ __('Enable 2FA') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($canManageTwoFactor)
                @include('livewire.settings.two-factor.setup-modal')
            @endif

        </x-settings.layout>

    @endif
</section>
