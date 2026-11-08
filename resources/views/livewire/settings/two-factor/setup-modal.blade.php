<flux:modal
    name="two-factor-setup-modal"
    :dismissible="! $enrollmentRequired"
    :escapable="! $enrollmentRequired"
    :closable="! $enrollmentRequired"
    :class="$enrollmentRequired ? 'w-[calc(100vw-2rem)] max-w-md min-w-0! max-h-[calc(100dvh-2rem)] overflow-y-auto' : 'max-w-md md:min-w-md'"
    @close="closeModal"
    wire:model="showModal"
>
    @include('livewire.settings.two-factor.setup-content')
</flux:modal>
