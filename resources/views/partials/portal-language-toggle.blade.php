<form method="POST" action="{{ route('portal.locale') }}" class="portal-language-toggle" data-portal-language-form aria-label="{{ __('Language') }}" data-portal-i18n-aria-label="Language">
    @csrf
    <button type="submit" name="locale" value="en" data-portal-locale="en" lang="en" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</button>
    <span aria-hidden="true">|</span>
    <button type="submit" name="locale" value="fil" data-portal-locale="fil" lang="fil" aria-pressed="{{ app()->getLocale() === 'fil' ? 'true' : 'false' }}">Filipino</button>
</form>
<span id="portal-language-status" class="sr-only" role="status" aria-live="polite"></span>
