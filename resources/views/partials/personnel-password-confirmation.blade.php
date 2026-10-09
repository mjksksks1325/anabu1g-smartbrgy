@can('access-employee-settings')
<dialog id="personnel-password-confirmation" class="personnel-password-dialog" aria-labelledby="personnel-confirm-title" aria-describedby="personnel-confirm-description" data-security-url="{{ route('security.edit') }}" data-status-url="{{ route('password.confirmation') }}">
    <h2 id="personnel-confirm-title">Confirm password</h2>
    <p id="personnel-confirm-description">Please confirm your password before opening Account Security.</p>
    <form method="POST" action="{{ route('staff.password.confirm') }}">
        @csrf
        <label for="personnel-confirm-password">Password</label>
        <input id="personnel-confirm-password" name="password" type="password" autocomplete="current-password" required autofocus>
        <p class="personnel-confirm-error" role="alert" hidden></p>
        <div class="personnel-confirm-actions">
            <button type="button" data-confirm-cancel>Cancel</button>
            <button type="submit">Confirm</button>
        </div>
    </form>
</dialog>
@endcan
