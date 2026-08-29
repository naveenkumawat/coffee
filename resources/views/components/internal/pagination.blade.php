@if ($paginator->hasPages())
    <div class="internal-pagination d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-4 pt-10">
        <div class="fs-7 fw-semibold text-gray-700 py-2 me-md-4">
            Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} results
        </div>

        <ul class="pagination pagination-sm flex-wrap mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item previous disabled"><span class="page-link"><i class="previous"></i></span></li>
            @else
                <li class="page-item previous">
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="page-link"><i class="previous"></i></a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="page-item {{ $page === $paginator->currentPage() ? 'active' : '' }}">
                            <a href="{{ $url }}" class="page-link">{{ $page }}</a>
                        </li>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item next">
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="page-link"><i class="next"></i></a>
                </li>
            @else
                <li class="page-item next disabled"><span class="page-link"><i class="next"></i></span></li>
            @endif
        </ul>
    </div>
@endif
