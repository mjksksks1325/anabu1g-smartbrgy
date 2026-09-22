<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="account-content" role="main" tabindex="-1">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
