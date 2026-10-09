@if ($paginator->hasPages())
<nav aria-label="{{ __('Pagination') }}" data-portal-i18n-aria-label="Pagination">
    <ul class="pagination">
        <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
            @if ($paginator->onFirstPage())
                <span class="page-link" aria-disabled="true"><span data-portal-i18n="Previous">{{ __('Previous') }}</span></span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span data-portal-i18n="Previous">{{ __('Previous') }}</span></a>
            @endif
        </li>
        <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next"><span data-portal-i18n="Next">{{ __('Next') }}</span></a>
            @else
                <span class="page-link" aria-disabled="true"><span data-portal-i18n="Next">{{ __('Next') }}</span></span>
            @endif
        </li>
    </ul>
</nav>
@endif
