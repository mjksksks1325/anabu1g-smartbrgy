<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.'Barangay Anabu I-G' : 'Barangay Anabu I-G' }}
</title>

<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}?v={{ filemtime(public_path('css/government.css')) }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
