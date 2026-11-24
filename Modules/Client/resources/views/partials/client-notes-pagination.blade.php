@if ($paginator->hasPages())
    <nav aria-label="Page navigation" class="custom-notes-pagination mt-4">
        <ul class="pagination justify-content-center gap-2">
            {{-- First Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true">
                        <i class="fa fa-angle-double-right"></i>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->url(1) }}" rel="first" aria-label="@lang('pagination.first')">
                        <i class="fa fa-angle-double-right"></i>
                    </a>
                </li>
            @endif

            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true">
                        <i class="fa fa-angle-right"></i>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                        <i class="fa fa-angle-right"></i>
                    </a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                        <i class="fa fa-angle-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true">
                        <i class="fa fa-angle-left"></i>
                    </span>
                </li>
            @endif

            {{-- Last Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->url($paginator->lastPage()) }}" rel="last" aria-label="@lang('pagination.last')">
                        <i class="fa fa-angle-double-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true">
                        <i class="fa fa-angle-double-left"></i>
                    </span>
                </li>
            @endif
        </ul>
    </nav>

    <style>
        .custom-notes-pagination .page-link {
            border: none;
            border-radius: 50%; /* Circular buttons */
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            color: var(--primary-color, #4361ee);
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .custom-notes-pagination .page-link:hover {
            background-color: var(--primary-color, #4361ee);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .custom-notes-pagination .page-item.active .page-link {
            background-color: var(--primary-color, #4361ee);
            color: #fff;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.3);
        }

        .custom-notes-pagination .page-item.disabled .page-link {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .custom-notes-pagination .icon-link i {
            font-size: 14px;
        }
    </style>
@endif
