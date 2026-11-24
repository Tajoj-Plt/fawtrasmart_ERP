{{--
    ترقيم أذون المستودع — تُحمَّل الصفحات بـAjax عبر `loadData(page)`.

    حُذف من هنا بلوك `<style>` كان يُخفي أشرطة التمرير في الصفحة كلها
    (`body::-webkit-scrollbar { display: none }`) — أثرٌ عامّ على كل الشاشة
    لأجل جدول واحد.
--}}

@if ($wareHousePermits->hasPages())
    @php
        $current = $wareHousePermits->currentPage();
        $last = $wareHousePermits->lastPage();
        $from = max(1, $current - 2);
        $to = min($last, $current + 2);

        $atStart = $wareHousePermits->onFirstPage();
        $atEnd = ! $wareHousePermits->hasMorePages();

        $steps = [
            'before' => [
                ['chevrons-right', 1, 'الصفحة الأولى', $atStart],
                ['chevron-right', $current - 1, 'السابقة', $atStart],
            ],
            'after' => [
                ['chevron-left', $current + 1, 'التالية', $atEnd],
                ['chevrons-left', $last, 'الصفحة الأخيرة', $atEnd],
            ],
        ];
    @endphp

    <div class="stk-table-foot">
        <p class="stk-table-foot__count">
            عرض <span class="stk-num">{{ $wareHousePermits->firstItem() }}</span>–<span class="stk-num">{{ $wareHousePermits->lastItem() }}</span>
            من <span class="stk-num">{{ $wareHousePermits->total() }}</span> إذناً
        </p>

        <nav aria-label="صفحات النتائج">
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
