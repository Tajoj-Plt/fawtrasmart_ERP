{{--
    ترقيم الإشعارات الدائنة — تُحمَّل الصفحات بـAjax عبر `loadData(page)`.

    نظير `sales::invoices.partials.pagination`.

    الروابط بـ`data-page` يلتقطها `loadData()` — لا `?page=` يلتقطه
    مستمعٌ ثانٍ ويقرأ منه الصفحة بـ`new URL(...)`.
--}}

@if ($credits->hasPages())
    @php
        $current = $credits->currentPage();
        $last = $credits->lastPage();
        $from = max(1, $current - 2);
        $to = min($last, $current + 2);

        $atStart = $credits->onFirstPage();
        $atEnd = ! $credits->hasMorePages();

        $steps = [
            'before' => [
                ['chevrons-right', 1, __('sales_settings.ui.page_first'), $atStart],
                ['chevron-right', $current - 1, __('sales_settings.ui.page_prev'), $atStart],
            ],
            'after' => [
                ['chevron-left', $current + 1, __('sales_settings.ui.page_next'), $atEnd],
                ['chevrons-left', $last, __('sales_settings.ui.page_last'), $atEnd],
            ],
        ];
    @endphp

    <div class="sl-table-foot">
        <p class="sl-table-foot__count">
            {{ __('sales_settings.ui.showing', [
                'from' => $credits->firstItem(),
                'to' => $credits->lastItem(),
                'total' => $credits->total(),
            ]) }}
        </p>

        <nav aria-label="{{ __('sales_settings.ui.pages') }}">
            <ul class="pagination mb-0">
                @foreach ($steps['before'] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link pagination-link" href="#" data-page="{{ $page }}"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach

                @if ($from > 1)
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#" data-page="1">1</a>
                    </li>
                    @if ($from > 2)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                @endif

                @for ($page = $from; $page <= $to; $page++)
                    <li class="page-item {{ $page === $current ? 'active' : '' }}">
                        @if ($page === $current)
                            <span class="page-link" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link pagination-link" href="#" data-page="{{ $page }}">{{ $page }}</a>
                        @endif
                    </li>
                @endfor

                @if ($to < $last)
                    @if ($to < $last - 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#" data-page="{{ $last }}">{{ $last }}</a>
                    </li>
                @endif

                @foreach ($steps['after'] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link pagination-link" href="#" data-page="{{ $page }}"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
@endif
