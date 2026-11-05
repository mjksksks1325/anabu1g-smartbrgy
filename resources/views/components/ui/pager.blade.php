{{--
    Link pager for server-rendered staff lists, the Flux-styled twin of partials/staff-pager:
    "Showing x–y of n", then Previous · Page x of y · Next. (flux:pagination only works inside Livewire components.)
--}}
@props([
    'paginator',
    'singular',
    'plural',
])

<nav {{ $attributes->class('flex flex-wrap items-center justify-between gap-3') }} aria-label="Pagination" data-ui-pager>
    <flux:text class="text-sm">Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }} <span>{{ $paginator->total() === 1 ? $singular : $plural }}</span></flux:text>
    <div class="flex items-center gap-2">
        @if ($paginator->onFirstPage())
            <flux:button size="sm" variant="outline" icon="chevron-left" disabled>Previous</flux:button>
        @else
            <flux:button size="sm" variant="outline" icon="chevron-left" :href="$paginator->previousPageUrl()" rel="prev">Previous</flux:button>
        @endif
        <flux:text class="px-1 text-sm tabular-nums" aria-live="polite">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</flux:text>
        @if ($paginator->hasMorePages())
            <flux:button size="sm" variant="outline" icon:trailing="chevron-right" :href="$paginator->nextPageUrl()" rel="next">Next</flux:button>
        @else
            <flux:button size="sm" variant="outline" icon:trailing="chevron-right" disabled>Next</flux:button>
        @endif
    </div>
</nav>
