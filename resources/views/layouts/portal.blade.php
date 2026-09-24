<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Resident Portal') | Barangay Anabu I-G</title>
<link rel="icon" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head><body class="resident-account-page">
<a class="skip-link" href="#resident-main">Skip to main content</a>
<div class="portal-government-bar"><span>Republic of the Philippines</span><span>City of Imus, Cavite</span></div>
<header class="header"><div class="header-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G"></div><div class="header-info"><h1>Barangay Anabu I-G</h1><p>Official Resident Services Portal</p></div><div class="header-badge"><span></span> Resident services</div><button class="btn btn-outline" type="button" data-resident-theme>Change theme</button></header>
@include('partials.resident-nav')
<main id="resident-main" class="container" tabindex="-1">
@if(session('status'))<p class="alert alert-green" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="alert alert-red" role="alert"><div><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
@yield('content')
</main>
<footer class="footer portal-footer"><span>Barangay Anabu I-G &middot; City of Imus, Cavite</span><a href="{{ route('portal.information') }}#help">Resident assistance &rarr;</a></footer>
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
</body></html>
