<dialog class="personnel-logout-dialog" id="logout-dialog" aria-labelledby="logout-title" aria-describedby="logout-description" data-logout-url="{{ route('logout') }}" data-login-url="{{ route('login') }}">
    <button type="button" class="personnel-logout-close" data-logout-cancel aria-label="Cancel logout">&times;</button>
    <div class="personnel-logout-symbol" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h13"/></svg></div>
    <p class="personnel-logout-eyebrow">Staff workspace</p>
    <h2 id="logout-title">Log out of SmartBrgy?</h2>
    <p id="logout-description">You're about to end your staff session. You can sign in again anytime.</p>
    <p id="logout-error" role="alert" hidden></p>
    <form method="POST" action="{{ route('logout') }}" class="personnel-logout-actions">
        @csrf
        <button type="button" data-logout-cancel autofocus>Cancel</button>
        <button type="submit" id="logout-confirm">Log out</button>
    </form>
</dialog>
