{{-- Barangay Hall phone and office hours from config('portal.barangay_hall'), as <dl> rows. Office days were not provided. --}}
@php($hall = config('portal.barangay_hall'))
<div><dt><span data-portal-i18n="Telepono">{{ __('Telepono') }}</span></dt><dd><a href="tel:{{ $hall['phone']['tel'] }}">{{ $hall['phone']['display'] }}</a></dd></div>
<div><dt><span data-portal-i18n="Office hours">{{ __('Office hours') }}</span></dt><dd>{{ $hall['office_hours'] }}</dd></div>
