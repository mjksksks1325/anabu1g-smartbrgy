{{--
    Data Privacy Notice shown before a new resident application. It sits inside the application form so the
    consent box is submitted with it and checked on the server. Without JavaScript the dialog renders open
    and inline above the form; with JavaScript it opens as a modal (focus stays inside) when the page loads.
    The text states only what this form collects and what RA 10173 itself provides; it does not state
    retention periods, data sharing or a privacy officer, which the barangay has not published.
--}}
@php($accepted = (bool) old('privacy_notice_accepted'))
<dialog class="rp-privacy-dialog" id="privacy-notice" open aria-labelledby="privacy-notice-title" aria-describedby="privacy-notice-intro" data-privacy-notice @if($accepted) data-accepted @endif>
    <div class="rp-privacy-dialog-inner">
        <header class="rp-privacy-dialog-header">
            <p class="rp-privacy-dialog-eyebrow"><span data-portal-i18n="Republic Act 10173 (Data Privacy Act of 2012)">{{ __('Republic Act 10173 (Data Privacy Act of 2012)') }}</span></p>
            <h2 id="privacy-notice-title"><span data-portal-i18n="Data Privacy Notice">{{ __('Data Privacy Notice') }}</span></h2>
            <p id="privacy-notice-intro"><span data-portal-i18n="Please read this notice before you apply as a new resident.">{{ __('Please read this notice before you apply as a new resident.') }}</span></p>
        </header>
        <div class="rp-privacy-dialog-body" tabindex="0" role="region" aria-labelledby="privacy-notice-title" data-privacy-notice-body>
            <h3><span data-portal-i18n="What we collect">{{ __('What we collect') }}</span></h3>
            <p><span data-portal-i18n="This application asks for your name, date of birth, gender, civil status, purok, address, residency type, email address, mobile number and photo.">{{ __('This application asks for your name, date of birth, gender, civil status, purok, address, residency type, email address, mobile number and photo.') }}</span></p>
            <h3><span data-portal-i18n="Why we collect it">{{ __('Why we collect it') }}</span></h3>
            <p><span data-portal-i18n="Barangay Anabu I-G uses this information to review your application for a barangay resident record, to contact you about the review, and, if approved, to keep your record in the official barangay records.">{{ __('Barangay Anabu I-G uses this information to review your application for a barangay resident record, to contact you about the review, and, if approved, to keep your record in the official barangay records.') }}</span></p>
            <h3><span data-portal-i18n="Who sees it">{{ __('Who sees it') }}</span></h3>
            <p><span data-portal-i18n="Barangay staff who are allowed to review resident applications. Submitting this application does not create a portal account.">{{ __('Barangay staff who are allowed to review resident applications. Submitting this application does not create a portal account.') }}</span></p>
            <h3><span data-portal-i18n="Your rights">{{ __('Your rights') }}</span></h3>
            <p><span data-portal-i18n="Your personal information is processed in accordance with Republic Act 10173, the Data Privacy Act of 2012. Under this law you have the right to be informed, to access and correct your personal information, to object to its processing, and to file a complaint with the National Privacy Commission.">{{ __('Your personal information is processed in accordance with Republic Act 10173, the Data Privacy Act of 2012. Under this law you have the right to be informed, to access and correct your personal information, to object to its processing, and to file a complaint with the National Privacy Commission.') }}</span></p>
            <h3><span data-portal-i18n="Questions">{{ __('Questions') }}</span></h3>
            <p><span data-portal-i18n="To ask about your personal information, how long it is kept, or to correct it, visit the Barangay Hall.">{{ __('To ask about your personal information, how long it is kept, or to correct it, visit the Barangay Hall.') }}</span></p>
        </div>
        <p class="rp-privacy-dialog-hint" id="privacy-notice-hint" aria-live="polite" data-privacy-notice-hint><span data-portal-i18n="Scroll to the end of the notice to continue.">{{ __('Scroll to the end of the notice to continue.') }}</span></p>
        <div class="checkbox-row rp-privacy-dialog-consent">
            <input type="checkbox" id="privacy-notice-accepted" name="privacy_notice_accepted" value="1" @checked($accepted) aria-describedby="privacy-notice-hint" data-privacy-notice-checkbox>
            <label for="privacy-notice-accepted"><span data-portal-i18n="I have read this Data Privacy Notice and I agree to the collection and use of my personal information for this application.">{{ __('I have read this Data Privacy Notice and I agree to the collection and use of my personal information for this application.') }}</span></label>
        </div>
        @error('privacy_notice_accepted')<p class="field-error" id="privacy-notice-error" data-portal-message>{{ $message }}</p>@enderror
        <div class="rp-privacy-dialog-actions">
            <a class="btn btn-outline" href="{{ route('portal.register') }}" data-privacy-notice-cancel><span data-portal-i18n="Cancel">{{ __('Cancel') }}</span></a>
            <button class="btn btn-green" type="button" data-privacy-notice-continue><span data-portal-i18n="Continue">{{ __('Continue') }}</span></button>
        </div>
    </div>
</dialog>
