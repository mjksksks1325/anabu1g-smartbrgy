<section class="w-full">
    @if (! $embedded)
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
    @else
        <form wire:submit="updateProfileInformation" class="settings-profile-form">
            <div class="profile-form-field"><label class="form-label" for="profile-name">{{ __('Name') }}</label><input @class(['form-input', 'field-invalid' => $errors->has('name')]) wire:model="name" id="profile-name" type="text" required autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<span class="field-feedback-message">{{ $message }}</span>@enderror</div>
            <div class="profile-form-field"><label class="form-label" for="profile-email">{{ __('Email') }}</label><input @class(['form-input', 'field-invalid' => $errors->has('email')]) wire:model="email" id="profile-email" type="email" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">@error('email')<span class="field-feedback-message">{{ $message }}</span>@enderror
                @if ($this->hasUnverifiedEmail)
                    <span class="profile-form-hint">{{ __('Your email address is unverified.') }} <button class="profile-resend-link" type="button" wire:click="resendVerificationNotification">{{ __('Click here to re-send the verification email.') }}</button></span>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn" onclick="closeModal('modal-edit-profile')">Cancel</button><button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="updateProfileInformation">{{ __('Save') }}</button></div>
        </form>
        @script
        <script>
            $wire.on('profile-updated', () => {
                window.closeModal('modal-edit-profile');
                window.showToast('Profile updated.', 'green');
            });
            $wire.on('profile-notification', (event) => window.showToast(event.message, 'green'));
        </script>
        @endscript
    @endif
</section>
