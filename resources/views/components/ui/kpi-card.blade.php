{{--
    Compact KPI card: label, value, optional hint and a tinted icon. With an href it becomes a link,
    for example a status filter, and `current` marks the selected one (aria-current="page").
    Tones: neutral, brand, info, success, warning, danger. The colour backs up the label and never replaces it.
--}}
@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'neutral',
    'href' => null,
    'current' => false,
])

@php
    $iconTone = match ($tone) {
        'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300',
        'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
        'success' => 'bg-green-50 text-green-700 dark:bg-green-950/60 dark:text-green-300',
        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
        'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-300',
        default => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @if ($current) aria-current="page" @endif @endif
    {{ $attributes->class([
        'flex min-w-0 items-start justify-between gap-3 rounded-xl border bg-white p-4 shadow-xs dark:bg-zinc-800',
        'border-zinc-200 dark:border-zinc-700' => ! $current,
        'border-accent ring-1 ring-accent dark:border-accent-content dark:ring-accent-content' => $current,
        'transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:hover:border-zinc-600 dark:hover:bg-white/5' => $href && ! $current,
    ]) }}
    data-ui-kpi
>
    <span class="min-w-0">
        <span class="block truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
        <span class="mt-1 block text-2xl font-semibold leading-tight tabular-nums text-zinc-800 dark:text-white">{{ $value }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</span>
        @endif
    </span>

    @if ($icon)
        <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $iconTone }}" aria-hidden="true">
            <flux:icon :name="$icon" variant="mini" class="size-5" />
        </span>
    @endif
</{{ $tag }}>
