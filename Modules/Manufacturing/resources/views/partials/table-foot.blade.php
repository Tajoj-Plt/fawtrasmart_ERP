{{--
    ذيل الجدول — العدّ وأرقام الصفحات، على قياس جداول المبيعات.

    الروابط تحمل `data-page` ويلتقطها سكربت الشاشة فيستبدل الجدول وحدَه.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
--}}

@php
    $tfCurrent = $paginator->currentPage();
    $tfLast = $paginator->lastPage();
    $tfFrom = max(1, $tfCurrent - 2);
    $tfTo = min($tfLast, $tfCurrent + 2);
@endphp

<div class="stk-table-foot">
    <p class="stk-table-foot__count">
        عرض <span class="stk-num">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="stk-num">{{ $paginator->lastItem() ?? 0 }}</span>
        من <span class="stk-num">{{ $paginator->total() }}</span>
    </p>

    @if ($tfLast > 1)
        <nav aria-label="الصفحات">
            <ul class="pagination mb-0">
                <li class="page-item {{ $tfCurrent === 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="#" data-page="{{ $tfCurrent - 1 }}" aria-label="السابق">
                        <i class="feather icon-chevron-right"></i>
                    </a>
                </li>

                @if ($tfFrom > 1)
                    <li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>
                    @if ($tfFrom > 2)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                @endif

                @for ($tfPage = $tfFrom; $tfPage <= $tfTo; $tfPage++)
                    <li class="page-item {{ $tfPage === $tfCurrent ? 'active' : '' }}">
                        <a class="page-link" href="#" data-page="{{ $tfPage }}">{{ $tfPage }}</a>
                    </li>
                @endfor

                @if ($tfTo < $tfLast)
                    @if ($tfTo < $tfLast - 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item"><a class="page-link" href="#" data-page="{{ $tfLast }}">{{ $tfLast }}</a></li>
                @endif

                <li class="page-item {{ $tfCurrent === $tfLast ? 'disabled' : '' }}">
                    <a class="page-link" href="#" data-page="{{ $tfCurrent + 1 }}" aria-label="التالي">
                        <i class="feather icon-chevron-left"></i>
                    </a>
                </li>
            </ul>
        </nav>
    @endif
</div>
