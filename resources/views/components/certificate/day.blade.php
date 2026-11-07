@props(['date'])
{{-- "20th day of October, 2026", as every template writes the issue date. --}}
<strong>{{ $date->format('d') }}<sup>{{ $date->format('S') }}</sup></strong> day of <strong>{{ $date->format('F, Y') }}</strong>