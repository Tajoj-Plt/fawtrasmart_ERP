{{--
    ترقيم المستودعات — نفس بنية `products/partials/pagination.blade.php` بلا
    تكرار المنطق: روابط بـ href حقيقي تعمل بلا جافاسكربت، ويلتقطها معالج
    `.pagination a` في صفحة المستودعات فيحوّلها إلى طلب Ajax.
--}}

@if ($storehouses->hasPages())
    @php
        $links = $storehouses->appends(request()->except('page'));
        $current = $links->currentPage();
        $last = $links->lastPage();
        $from = max(1, $current - 2);
        $to = min($last, $current + 2);

        $atStart = $links->onFirstPage();
        $atEnd = ! $links->hasMorePages();

        // [الأيقونة، الصفحة، الوصف، أمُعطَّل؟]
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
            عرض <span class="stk-num">{{ $links->firstItem() }}</span>–<span class="stk-num">{{ $links->lastItem() }}</span>
            من <span class="stk-num">{{ $links->total() }}</span> مستودع
        </p>

        <nav aria-label="تصفح المستودعات">
            <ul class="pagination mb-0">
                @foreach ($steps['before'] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link pagination-link" href="{{ $links->url($page) }}" data-page="{{ $page }}"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach

                @if ($from > 1)
                    <li class="page-item">
                        <a class="page-link pagination-link" href="{{ $links->url(1) }}" data-page="1">1</a>
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
                            <a class="page-link pagination-link" href="{{ $links->url($page) }}" data-page="{{ $page }}">{{ $page }}</a>
                        @endif
                    </li>
                @endfor

                @if ($to < $last)
                    @if ($to < $last - 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item">
                        <a class="page-link pagination-link" href="{{ $links->url($last) }}" data-page="{{ $last }}">{{ $last }}</a>
                    </li>
                @endif

                @foreach ($steps['after'] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link pagination-link" href="{{ $links->url($page) }}" data-page="{{ $page }}"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
@endif
