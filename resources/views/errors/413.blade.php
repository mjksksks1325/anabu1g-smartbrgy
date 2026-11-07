{{-- Shown when a form, usually with a photo, is larger than the server accepts (PHP post_max_size): PHP discards it before
     the application can validate it. In the visitor's language (bootstrap/app.php sets it from the cookie). Going back with
     the browser usually keeps the details already typed; the photo has to be chosen again. --}}
@extends('errors.minimal')

@section('title', __('File too large'))
@section('code', '413')
@section('eyebrow', __('File too large'))
@section('message', __('The file you sent is too large.'))
@section('lead', __('Nothing was saved. Go back, choose a smaller photo or file, and submit the form again. Resident photos can be up to 10 MB. If a smaller photo also fails, contact the barangay office.'))
@section('actions')
    <p class="civic-lead">{{ __('Going back usually keeps the details you already typed; choose the photo again before submitting.') }}</p>
    <a class="civic-link" href="{{ $backUrl ?? route('home') }}" onclick="if (history.length > 1) { history.back(); return false; }">{{ __('Go back to the form') }}</a>
    &nbsp;·&nbsp;
    <a class="civic-link" href="{{ route('home') }}">{{ __('Return to resident services') }}</a>
@endsection
