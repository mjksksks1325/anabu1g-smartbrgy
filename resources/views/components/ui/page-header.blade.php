{{--
    Staff page header: optional eyebrow (module group), the page title, a one-line description, and the
    page's primary actions on the right. It wraps under the title on narrow screens.
--}}
@props([
    'title',
    'eyebrow' => null,
    'description' => null,
])

<header {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4') }} data-ui-page-header>
    <div class="min-w-0 max-w-3xl">
        @if ($eyebrow)
            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-accent-content">{{ $eyebrow }}</p>
        @endif
        <flux:heading level="1" size="xl" class="text-2xl! font-semibold tracking-tight">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text class="mt-1.5 max-w-prose">{{ $description }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
