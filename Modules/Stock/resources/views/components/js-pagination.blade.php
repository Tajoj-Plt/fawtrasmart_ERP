{{--
    ترقيم لقوائم تُحمَّل بـAjax عبر دالة جافاسكربت.

    القوائم داخل صفحة المنتج (السجلّ، الخطّ الزمني، حركة المخزون) لا تملك
    روابط حقيقية — كلٌّ منها ينادي دالته. فبدل أن يعيد كل قالبٍ بناء ستّة أزرار
    بيده، يمرّر هنا الصفحاتِ واسمَ دالته.

    الوسائط:
      $paginator — كائن الترقيم
      $callback  — اسم دالة جافاسكربت تقبل رقم الصفحة، مثل 'loadTimeline'
      $unit      — اسم المعدود في سطر العدّ (اختياري)
--}}

@php
    $unit = $unit ?? 'سجل';
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);

    $atStart = $paginator->onFirstPage();
    $atEnd = ! $paginator->hasMorePages();
@endphp

<div class="stk-table-foot">
    <p class="stk-table-foot__count">
        عرض <span class="stk-num">{{ $paginator->firstItem() }}</span>–<span class="stk-num">{{ $paginator->lastItem() }}</span>
        من <span class="stk-num">{{ $paginator->total() }}</span> {{ $unit }}
    </p>

    @if ($paginator->hasPages())
        <nav aria-label="تصفح الصفحات">
            <ul class="pagination mb-0">
                @foreach ([['chevrons-right', 1, 'الصفحة الأولى', $atStart], ['chevron-right', $current - 1, 'السابقة', $atStart]] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link" href="javascript:void(0)" onclick="{{ $callback }}({{ $page }})"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach

                @if ($from > 1)
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="{{ $callback }}(1)">1</a>
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
                            <a class="page-link" href="javascript:void(0)" onclick="{{ $callback }}({{ $page }})">{{ $page }}</a>
                        @endif
                    </li>
                @endfor

                @if ($to < $last)
                    @if ($to < $last - 1)
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="{{ $callback }}({{ $last }})">{{ $last }}</a>
                    </li>
                @endif

                @foreach ([['chevron-left', $current + 1, 'التالية', $atEnd], ['chevrons-left', $last, 'الصفحة الأخيرة', $atEnd]] as [$icon, $page, $label, $off])
                    <li class="page-item {{ $off ? 'disabled' : '' }}">
                        @if ($off)
                            <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                        @else
                            <a class="page-link" href="javascript:void(0)" onclick="{{ $callback }}({{ $page }})"
                               aria-label="{{ $label }}" title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
