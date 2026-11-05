{{--
    Content panel: a bordered surface with an optional header (title, optional description, right-aligned meta such
    as a count, and actions). Use one panel per list or form section; don't nest panels.
--}}
@props([
    'title' => null,
    'description' => null,
    'headingId' => null,
    'headingLevel' => 2,
])

<section {{ $attributes->class('overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800') }} @if ($title && $headingId) aria-labelledby="{{ $headingId }}" @endif data-ui-panel>
    @if ($title || isset($meta) || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-zinc-200 px-5 py-3.5 dark:border-zinc-700">
            <div class="min-w-0">
                @if ($title)
                    <flux:heading :level="$headingLevel" :id="$headingId">{{ $title }}</flux:heading>
                @endif
                @if ($description)
                    <flux:text class="mt-0.5 text-sm">{{ $description }}</flux:text>
                @endif
            </div>
            @if (isset($meta) || isset($actions))
                <div class="flex flex-wrap items-center gap-3">
                    @isset($meta)
                        <flux:text class="text-sm tabular-nums">{{ $meta }}</flux:text>
                    @endisset
                    {{ $actions ?? '' }}
                </div>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
