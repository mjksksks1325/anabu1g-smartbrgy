@props([
    'title',
    'titleId' => 'page-title',
    'parent' => null,
    'parentUrl' => null,
])

<div {{ $attributes->class(['page-band']) }}>
    <div class="page-band-inner">
        <div class="page-band-text">
            <nav class="breadcrumb" data-portal-i18n-aria-label="Breadcrumb" aria-label="{{ __('Breadcrumb') }}">
                <ol>
                    <li><a href="{{ route('home') }}"><span data-portal-i18n="Home">{{ __('Home') }}</span></a></li>
                    @if($parent)
                        <li><a href="{{ $parentUrl }}"><span data-portal-i18n="{{ $parent }}">{{ __($parent) }}</span></a></li>
                    @endif
                    <li aria-current="page"><span data-portal-i18n="{{ $title }}">{{ __($title) }}</span></li>
                </ol>
            </nav>
            <h1 id="{{ $titleId }}"><span data-portal-i18n="{{ $title }}">{{ __($title) }}</span></h1>
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
