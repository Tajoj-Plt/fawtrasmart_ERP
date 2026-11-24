{{--
    تذييل جدولٍ يُرقَّم بـ`page` و`totalPages` لا بكائن `Paginator`.

    بعض التقارير تُقسّم مجموعةً محسوبةً في الذاكرة، فلا `Paginator` لها.
    وكانت كلٌّ منها تبني خمسة أزرارٍ بيدها بأيقونات `fa-angle-double-right`
    — واتّجاهها مقلوبٌ في صفحةٍ من اليمين إلى اليسار — وتعرض «3 / 12» في
    خانةٍ واحدة بلا بقية الأرقام، فلا سبيل إلى القفز إلى صفحةٍ بعينها.

    الترميز هنا نسخةٌ من `table-foot`، فلا يفترق شكل التذييل باختلاف
    مصدر الترقيم.

    الوسائط:
      page        رقم الصفحة الحالية
      totalPages  عدد الصفحات
      total       إجمالي السجلّات (اختياري — للعدّ)
      unit        وحدة العدّ (اختياري)
      window      عدد الأرقام حول الحالية (افتراضي 2)
--}}

@php
    $page = max(1, (int) ($page ?? 1));
    $totalPages = max(1, (int) ($totalPages ?? 1));
    $total = $total ?? null;
    $unit = $unit ?? 'نتيجة';
    $window = (int) ($window ?? 2);

    $query = request()->except(['page']);
    $url = fn (int $target) => '?' . http_build_query(array_merge($query, ['page' => $target]));

    $from = max(1, $page - $window);
    $to = min($totalPages, $page + $window);

    $atStart = $page === 1;
    $atEnd = $page === $totalPages;
@endphp

<div class="stk-table-foot">
    <span class="stk-table-foot__count">
        @if ($total !== null)
            من <strong class="stk-num">{{ number_format($total) }}</strong> {{ $unit }} —
        @endif
        صفحة <strong class="stk-num">{{ $page }}</strong>
        من <strong class="stk-num">{{ $totalPages }}</strong>
    </span>

    @if ($totalPages > 1)
        <nav aria-label="تصفح الصفحات">
            <ul class="pagination mb-0">
                @foreach ([['chevrons-right', 1, 'الصفحة الأولى', $atStart], ['chevron-right', max(1, $page - 1), 'السابقة', $atStart]] as [$icon, $target, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link" href="{{ $url($target) }}" aria-label="{{ $label }}"
                                title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach

                @if ($from > 1)
                    <li class="page-item"><a class="page-link" href="{{ $url(1) }}">1</a></li>

                    @if ($from > 2)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                @endif

                @for ($target = $from; $target <= $to; $target++)
                    <li class="page-item {{ $target === $page ? 'active' : '' }}">
                        @if ($target === $page)
                            <span class="page-link" aria-current="page">{{ $target }}</span>
                        @else
                            <a class="page-link" href="{{ $url($target) }}">{{ $target }}</a>
                        @endif
                    </li>
                @endfor

                @if ($to < $totalPages)
                    @if ($to < $totalPages - 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif

                    <li class="page-item"><a class="page-link" href="{{ $url($totalPages) }}">{{ $totalPages }}</a></li>
                @endif

                @foreach ([['chevron-left', min($totalPages, $page + 1), 'التالية', $atEnd], ['chevrons-left', $totalPages, 'الصفحة الأخيرة', $atEnd]] as [$icon, $target, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link" href="{{ $url($target) }}" aria-label="{{ $label }}"
                                title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
