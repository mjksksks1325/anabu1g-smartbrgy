<form method="POST" action="{{ route('portal.register.verify') }}" data-resident-form autocomplete="off">
    @csrf
    <div class="form-group">
        <label class="form-label" for="resident-number">Resident number</label>
        <input class="form-input" id="resident-number" name="resident_number" required maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="resident-number-help" @error('resident_number') aria-invalid="true" @enderror>
        @error('resident_number')<p class="field-error">{{ $message }}</p>@enderror
        <p class="form-note" id="resident-number-help">Nasa email o ibinigay ng barangay staff.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="activation-code">Activation code</label>
        <input class="form-input" id="activation-code" name="activation_code" type="password" required maxlength="100" autocomplete="off" spellcheck="false" @error('activation_code') aria-invalid="true" @enderror>
        @error('activation_code')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <button class="btn btn-green btn-full" type="submit">Verify activation code</button>
</form>
