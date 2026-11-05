{{-- Empty state for a list or panel: icon, short title, one line on what to do next, and an optional action. --}}
@props([
    'title',
    'icon' => 'inbox',
])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }} data-ui-empty-state>
    <span class="mb-3 grid size-11 place-items-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400" aria-hidden="true">
        <flux:icon :name="$icon" class="size-6" />
    </span>
    <flux:heading level="3">{{ $title }}</flux:heading>
    @if ($slot->isNotEmpty())
        <flux:text class="mt-1 max-w-sm">{{ $slot }}</flux:text>
    @endif
    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
