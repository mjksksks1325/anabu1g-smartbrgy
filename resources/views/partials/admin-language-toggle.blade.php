<form method="POST" action="{{ route('staff.locale') }}" class="admin-locale-toggle admin-language-inline" data-admin-language-form aria-label="{{ __('Language') }}">
    @csrf
    <button type="submit" name="locale" value="en" data-admin-locale="en" lang="en" aria-label="English" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</button>
    <span aria-hidden="true">|</span>
    <button type="submit" name="locale" value="fil" data-admin-locale="fil" lang="fil" aria-label="Filipino" aria-pressed="{{ app()->getLocale() === 'fil' ? 'true' : 'false' }}">Filipino</button>
    <span class="sr-only admin-locale-status" role="status" aria-live="polite"></span>
</form>
