@if ($paginator->hasPages())
    <nav aria-label="Page navigation" class="simple-pagination mt-4">
        <ul class="pagination justify-content-center align-items-center gap-2">
            {{-- First Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true" title="الأول">
                        <i class="fa fa-angle-double-right"></i>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->url(1) }}" rel="first" title="الأول">
                        <i class="fa fa-angle-double-right"></i>
                    </a>
                </li>
            @endif

            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true" title="السابق">
                        <i class="fa fa-angle-right"></i>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" title="السابق">
                        <i class="fa fa-angle-right"></i>
                    </a>
                </li>
            @endif

            {{-- Current Page / Total Pages --}}
            <li class="page-item">
                <span class="page-info">
                    <span class="current-page">{{ $paginator->currentPage() }}</span>
                    <span class="separator">/</span>
                    <span class="total-pages">{{ $paginator->lastPage() }}</span>
                </span>
            </li>

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->nextPageUrl() }}" rel="next" title="التالي">
                        <i class="fa fa-angle-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true" title="التالي">
                        <i class="fa fa-angle-left"></i>
                    </span>
                </li>
            @endif

            {{-- Last Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link icon-link" href="{{ $paginator->url($paginator->lastPage()) }}" rel="last" title="الأخير">
                        <i class="fa fa-angle-double-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link icon-link" aria-hidden="true" title="الأخير">
                        <i class="fa fa-angle-double-left"></i>
                    </span>
                </li>
            @endif
        </ul>
    </nav>

    <style>
        .simple-pagination .pagination {
            margin: 0;
        }

        .simple-pagination .page-link {
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            color: #7367f0;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .simple-pagination .page-link:hover {
            background-color: #7367f0;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(115, 103, 240, 0.3);
        }

        .simple-pagination .page-item.disabled .page-link {
            background-color: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .simple-pagination .icon-link i {
            font-size: 16px;
        }

        .simple-pagination .page-info {
            background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
            color: #fff;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 4px 10px rgba(115, 103, 240, 0.3);
        }

        .simple-pagination .page-info .current-page {
            font-size: 18px;
            font-weight: 700;
        }

        .simple-pagination .page-info .separator {
            opacity: 0.7;
        }

        .simple-pagination .page-info .total-pages {
            opacity: 0.9;
        }
    </style>
@endif
