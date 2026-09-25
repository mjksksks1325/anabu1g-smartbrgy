@extends('layouts.portal')
@section('title', 'Log in')
@section('band')
<x-portal.page-band title="Log in" title-id="login-title">
    <p>Gamitin ang email at password ng resident account ninyo.</p>
</x-portal.page-band>
@endsection
@section('content')
<div class="auth-layout">
    <section class="card card-accent" aria-labelledby="login-title">
        <form class="card-body" method="POST" action="{{ route('portal.login.store') }}" data-resident-form>
            @csrf
            <div class="form-group">
                <label class="form-label" for="resident-email">Email address</label>
                <input class="form-input" id="resident-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="resident-email-error" @enderror>
                @error('email')<p class="field-error" id="resident-email-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="resident-password">Password</label>
                <input class="form-input" id="resident-password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="resident-remember"><input name="remember" type="checkbox" value="1"> Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.</label>
            <button class="btn btn-green btn-full" type="submit">Log in</button>
            <p class="auth-links"><a href="{{ route('password.request') }}">Nakalimutan ang password?</a></p>
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
            <p class="auth-links"><a class="btn btn-outline btn-full" href="{{ route('portal.register') }}">Create account</a></p>
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
