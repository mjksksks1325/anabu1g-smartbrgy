<form method="POST" action="{{ route('portal.register.verify') }}" data-resident-form autocomplete="off">
    @csrf
    <div class="form-group"><label class="form-label" for="resident-number">Resident number</label><input class="form-input" id="resident-number" name="resident_number" required maxlength="40" autocomplete="off"></div>
    <div class="form-group"><label class="form-label" for="activation-code">Activation code</label><input class="form-input" id="activation-code" name="activation_code" type="password" required maxlength="100" autocomplete="off" spellcheck="false"></div>
    <button class="btn btn-green btn-full" type="submit">Verify Activation Code</button>
</form>
