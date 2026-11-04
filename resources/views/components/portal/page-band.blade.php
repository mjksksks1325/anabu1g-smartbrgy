@props([
    'title',
    'titleId' => 'page-title',
    'parent' => null,
    'parentUrl' => null,
    'eyebrow' => null,
    'titleParams' => [],
])

<div {{ $attributes->class(['page-band']) }}>
    <div class="page-band-inner">
        <div class="page-band-text">
            @if($parent)
                <a class="v2-back" href="{{ $parentUrl }}"><span aria-hidden="true">&#8249;</span> <span data-portal-i18n="{{ $parent }}">{{ __($parent) }}</span></a>
            @endif
            @if($eyebrow)
                <p class="v2-page-eyebrow"><span data-portal-i18n="{{ $eyebrow }}">{{ __($eyebrow) }}</span></p>
            @endif
            <h1 id="{{ $titleId }}"><span data-portal-i18n="{{ $title }}" @if($titleParams) data-portal-params="{{ json_encode($titleParams) }}" @endif>{{ __($title, $titleParams) }}</span></h1>
            @if($slot->isNotEmpty())
                <div class="page-band-lead">{{ $slot }}</div>
            @endif
        </div>
        @isset($actions)
            <div class="page-band-actions">{{ $actions }}</div>
        @endisset
    </div>
    @isset($below)
        <div class="page-band-below">{{ $below }}</div>
    @endisset
</div>
