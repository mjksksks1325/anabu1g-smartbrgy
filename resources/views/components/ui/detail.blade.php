{{-- One label and value inside x-ui.details. `wide` spans the full row (addresses, purposes, notes, lists). Classes apply to the value. --}}
@props([
    'label',
    'wide' => false,
])

<div @class(['min-w-0', 'col-span-full' => $wide])>
    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
    <dd {{ $attributes->class('mt-1 break-words text-sm text-zinc-800 dark:text-zinc-100') }}>{{ $slot }}</dd>
</div>
