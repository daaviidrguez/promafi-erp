@if ($paginator->hasPages())
    <nav class="pagination-promafi" role="navigation" aria-label="Paginación">
        <p class="pagination-promafi__info pagination-promafi__info--muted">
            Página {{ $paginator->currentPage() }}
        </p>
        <ul class="pagination-promafi__list">
            @if ($paginator->onFirstPage())
                <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true">‹</span></li>
            @else
                <li><a class="pagination-promafi__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li><a class="pagination-promafi__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a></li>
            @else
                <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true">›</span></li>
            @endif
        </ul>
    </nav>
@endif
