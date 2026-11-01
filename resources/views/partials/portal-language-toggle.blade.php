<form method="POST" action="{{ route('portal.locale') }}" class="portal-language-toggle" data-portal-language-form aria-label="{{ __('Language') }}" data-portal-i18n-aria-label="Language">
    @csrf
    <svg class="portal-language-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
    <button type="submit" name="locale" value="en" data-portal-locale="en" lang="en" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</button>
    <span aria-hidden="true">|</span>
    <button type="submit" name="locale" value="fil" data-portal-locale="fil" lang="fil" aria-pressed="{{ app()->getLocale() === 'fil' ? 'true' : 'false' }}">Filipino</button>
</form>
<span id="portal-language-status" class="sr-only" role="status" aria-live="polite"></span>
