{{-- ترقيمٌ بـAjax — الروابط يلتقطها `reList` في `list-scripts`. الوسائط: $paginator، $unit (تمييز العدد). --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $from = max(1, $current - 2);
        $to = min($last, $current + 2);
        $pages = array_unique(array_merge([1], range($from, $to), [$last]));
    @endphp
    <div class="stk-table-foot">
        <p class="stk-table-foot__count">
            عرض <span class="stk-num">{{ $paginator->firstItem() }}</span>–<span class="stk-num">{{ $paginator->lastItem() }}</span>
            من <span class="stk-num">{{ $paginator->total() }}</span> {{ $unit ?? 'سجلاً' }}
        </p>
        <nav aria-label="صفحات النتائج">
            <ul class="pagination mb-0">
                <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link pagination-link" href="#" data-page="{{ max(1, $current - 1) }}" aria-label="السابقة"><i class="feather icon-chevron-right"></i></a>
                </li>
                @php $previous = 0; @endphp
                @foreach ($pages as $page)
                    @if ($page - $previous > 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item {{ $page === $current ? 'active' : '' }}">
                        <a class="page-link pagination-link" href="#" data-page="{{ $page }}" @if ($page === $current) aria-current="page" @endif>{{ $page }}</a>
                    </li>
                    @php $previous = $page; @endphp
                @endforeach
                <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link pagination-link" href="#" data-page="{{ min($last, $current + 1) }}" aria-label="التالية"><i class="feather icon-chevron-left"></i></a>
                </li>
            </ul>
        </nav>
    </div>
@elseif ($paginator->total() > 0)
    <div class="stk-table-foot">
        <p class="stk-table-foot__count"><span class="stk-num">{{ $paginator->total() }}</span> {{ $unit ?? 'سجلاً' }}</p>
    </div>
@endif
