@extends('layouts.portal')
@section('title', 'Log in')
@section('band')
<x-portal.page-band title="Welcome back" title-id="login-title">
    <p>Mag-log in gamit ang inyong verified resident account.</p>
</x-portal.page-band>
@endsection
@section('content')
<div class="auth-layout">
    <section class="card card-accent" aria-labelledby="resident-login-card-title">
        <form class="card-body" method="POST" action="{{ route('portal.login.store') }}" data-resident-form>
            @csrf
            <div class="resident-auth-heading">
                <span class="resident-auth-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg></span>
                <div><h2 id="resident-login-card-title">Resident account login</h2><p>Protected access to your document requests and resident record.</p></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="resident-email">Email address</label>
                <input class="form-input" id="resident-email" name="email" type="email" value="{{ old('email') }}" placeholder="juan.delacruz@email.com" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="resident-email-error" @enderror>
                @error('email')<p class="field-error" id="resident-email-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="resident-password">Password</label>
                <span class="resident-password-field"><input class="form-input" id="resident-password" name="password" type="password" autocomplete="current-password" required><button class="resident-password-toggle" type="button" data-password-toggle="resident-password" aria-controls="resident-password" aria-pressed="false">Show</button></span>
            </div>
            <label class="resident-remember"><input name="remember" type="checkbox" value="1"> Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.</label>
            <button class="btn btn-green btn-full" type="submit">Log in securely <span aria-hidden="true">&#8594;</span></button>
            <p class="auth-links"><a href="{{ route('password.request') }}">Nakalimutan ang password?</a></p>
            <p class="resident-auth-security">Secure at encrypted ang inyong login information.</p>
        </form>
    </section>
    <aside class="auth-aside" aria-label="Tungkol sa resident account">
        <section>
            <h2>Wala pang account?</h2>
            <p>Para makagawa ng account, kailangang nasa Resident Records na kayo ng Barangay Anabu I-G. Hahanapin ang record ninyo gamit ang:</p>
            <ul class="plain-list">
                <li>buong pangalan</li>
                <li>petsa ng kapanganakan</li>
                <li>huling 4 na digit ng contact number na nasa record</li>
            </ul>
            <p class="auth-links"><a class="btn btn-outline btn-full" href="{{ route('portal.register') }}">Create resident account <span aria-hidden="true">&#8594;</span></a></p>
        </section>
        <section>
            <h2>Pagka-log in</h2>
            <p>Puwede nang mag-request ng dokumento at makita ang status nito sa My requests.</p>
        </section>
        <section>
            <h2>Hindi makapag-log in?</h2>
            <p>Kung hindi gumagana ang account o may maling detalye sa record, pumunta sa Barangay Hall para magpatulong. <a href="{{ route('portal.information') }}#help">Get help</a></p>
        </section>
    </aside>
</div>
@endsection
