{{-- Server-rendered twin of the workspace pager (renderPager in admin.js): range summary, then Previous · Page x of y · Next. --}}
@php($noun = $paginator->total() === 1 ? $singular : $plural)
<nav class="staff-pager" aria-label="Pagination">
    <span class="staff-pager-summary">Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }} <span>{{ $noun }}</span></span>
    <span class="staff-pager-controls">
        @if($paginator->onFirstPage())
            <span class="button quiet" aria-disabled="true">Previous</span>
        @else
            <a class="button quiet" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        <span class="staff-pager-status" aria-live="polite">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())
            <a class="button quiet" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="button quiet" aria-disabled="true">Next</span>
        @endif
    </span>
</nav>
