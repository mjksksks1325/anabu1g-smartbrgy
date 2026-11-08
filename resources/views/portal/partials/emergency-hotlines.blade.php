{{-- Emergency hotlines from config('portal.emergency_hotlines'); $listClass picks the page's list style. --}}
<ul class="{{ $listClass }} rp-hotlines">
    @foreach(config('portal.emergency_hotlines', []) as $hotline)
        <li>
            <span class="rp-hotline-label">
                <span data-portal-i18n="{{ $hotline['label'] }}">{{ __($hotline['label']) }}</span>
                @if(! empty($hotline['note']))<small class="rp-hotline-note"><span data-portal-i18n="{{ $hotline['note'] }}">{{ __($hotline['note']) }}</span></small>@endif

            </span>
            <span class="rp-hotline-numbers">
                @foreach($hotline['numbers'] as $number)<a href="tel:{{ $number['tel'] }}">{{ $number['display'] }}</a>@endforeach
            </span>
        </li>
    @endforeach
</ul>
