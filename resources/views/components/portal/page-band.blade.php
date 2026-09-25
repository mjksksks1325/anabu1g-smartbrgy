@props([
    'title',
    'titleId' => 'page-title',
    'parent' => null,
    'parentUrl' => null,
])

<div {{ $attributes->class(['page-band']) }}>
    <div class="page-band-inner">
        <div class="page-band-text">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="{{ route('home') }}">Home</a></li>
                    @if($parent)
                        <li><a href="{{ $parentUrl }}">{{ $parent }}</a></li>
                    @endif
                    <li aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
            <h1 id="{{ $titleId }}">{{ $title }}</h1>
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
