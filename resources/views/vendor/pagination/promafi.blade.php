@if ($paginator->total() > 0)
    <nav class="pagination-promafi" role="navigation" aria-label="Paginación">
        <p class="pagination-promafi__info">
            Mostrando
            <strong>{{ number_format($paginator->firstItem() ?? 0) }}</strong>
            a
            <strong>{{ number_format($paginator->lastItem() ?? 0) }}</strong>
            de
            <strong>{{ number_format($paginator->total()) }}</strong>
            registros
        </p>

        @if ($paginator->hasPages())
            <ul class="pagination-promafi__list">
                {{-- Primera --}}
                @if ($paginator->onFirstPage())
                    <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true" title="Primera">«</span></li>
                    <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true" title="Anterior">‹</span></li>
                @else
                    <li><a class="pagination-promafi__btn" href="{{ $paginator->url(1) }}" title="Primera">«</a></li>
                    <li><a class="pagination-promafi__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" title="Anterior">‹</a></li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="pagination-promafi__btn is-ellipsis" aria-hidden="true">…</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li><span class="pagination-promafi__btn is-active" aria-current="page">{{ $page }}</span></li>
                            @else
                                <li><a class="pagination-promafi__btn" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Última --}}
                @if ($paginator->hasMorePages())
                    <li><a class="pagination-promafi__btn" href="{{ $paginator->nextPageUrl() }}" rel="next" title="Siguiente">›</a></li>
                    <li><a class="pagination-promafi__btn" href="{{ $paginator->url($paginator->lastPage()) }}" title="Última">»</a></li>
                @else
                    <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true" title="Siguiente">›</span></li>
                    <li><span class="pagination-promafi__btn is-disabled" aria-disabled="true" title="Última">»</span></li>
                @endif
            </ul>
        @endif
    </nav>
@endif
