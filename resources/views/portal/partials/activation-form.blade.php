<form method="POST" action="{{ route('portal.register.verify') }}" data-resident-form autocomplete="off">
    @csrf
    <div class="form-group">
        <label class="form-label" for="resident-number"><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span></label>
        <input class="form-input" id="resident-number" name="resident_number" required maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="resident-number-help" @error('resident_number') aria-invalid="true" @enderror>
        @error('resident_number')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
        <p class="form-note" id="resident-number-help"><span data-portal-i18n="Nasa email o ibinigay ng barangay staff.">{{ __('Nasa email o ibinigay ng barangay staff.') }}</span></p>
    </div>
    <div class="form-group">
        <label class="form-label" for="activation-code"><span data-portal-i18n="Activation code">{{ __('Activation code') }}</span></label>
        <input class="form-input" id="activation-code" name="activation_code" type="text" required maxlength="100" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" @error('activation_code') aria-invalid="true" @enderror>
        @error('activation_code')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
    </div>
    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Verify activation code">{{ __('Verify activation code') }}</span></button>
</form>
