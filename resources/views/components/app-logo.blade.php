@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Anabu I-G" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay seal" class="size-8 rounded-full">
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Anabu I-G" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay seal" class="size-8 rounded-full">
        </x-slot>
    </flux:brand>
@endif
