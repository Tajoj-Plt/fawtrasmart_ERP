{{--
    ذيل جدول عروض الأسعار: العدّاد، ومُنتقي عدد السجلّات، وشريط الترقيم.

    لا `$paginator->links()` هنا: الشاشة تُبدّل جدولها بـAjax، فالروابط
    ليست عناوين تُفتح بل أرقام صفحاتٍ يلتقطها `loadData()` من
    `data-page`. وقالب لارافيل الافتراضي يخرج بروابط حقيقية وبأصنافٍ
    عارية لا تعرفها `sl-`.

    ولمَ لا `sales::components.table-foot`؟ لأنّ ذاك مبنيٌّ لشاشات
    الإعدادات: روابطه `$paginator->url()` تُعيد تحميل الصفحة كلّها.
    الاختلاف في السلوك لا في الشكل — والشكل واحد.

    الوسائط:
      paginator  كائن `LengthAwarePaginator`
--}}

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // نافذة صفحتين حول الحالية: شريطٌ بمئة رقم لا يُقرأ ولا يُضغط
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);

    $atStart = $paginator->onFirstPage();
    $atEnd = ! $paginator->hasMorePages();

    /* `$languageIsRtl` يشاركه مُركِّب عروضٍ عامّ، لكنّه غير مضمون في
       جزءٍ يُصيَّر وحده في نداء Ajax — فالمصدر هو الإعداد نفسه */
    $isRtl = $languageIsRtl ?? \App\Support\LanguageSettings::isRtl();

    /* السهم يتبع اتجاه اللغة: أيقونةٌ ثابتة تعني في العربية عكس ما تعنيه
       في الإنجليزية */
    $steps = [
        'before' => [
            [$isRtl ? 'chevrons-right' : 'chevrons-left', 1, $doc['t']('ui.page_first'), $atStart],
            [$isRtl ? 'chevron-right' : 'chevron-left', $current - 1, $doc['t']('ui.page_prev'), $atStart],
        ],
        'after' => [
            [$isRtl ? 'chevron-left' : 'chevron-right', $current + 1, $doc['t']('ui.page_next'), $atEnd],
            [$isRtl ? 'chevrons-left' : 'chevrons-right', $last, $doc['t']('ui.page_last'), $atEnd],
        ],
    ];
@endphp

@if ($paginator->total() > 0)
    <div class="sl-table-foot">
        <p class="sl-table-foot__count">
            {{ $doc['t']('ui.showing', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="d-flex align-items-center" style="gap:.75rem;flex-wrap:wrap">
            @include('sales::components.per-page', [
                'id' => 'perPage',
                'sizes' => [10, 25, 50, 100],
                'current' => $paginator->perPage(),
                'onchange' => 'loadData(1)',
            ])

            @if ($paginator->hasPages())
                <nav aria-label="{{ $doc['t']('ui.pages') }}">
                    <ul class="pagination mb-0">
                        @foreach ($steps['before'] as [$stepIcon, $stepPage, $stepLabel, $stepOff])
                            <li class="page-item {{ $stepOff ? 'disabled' : '' }}">
                                @if ($stepOff)
                                    <span class="page-link" aria-hidden="true">
                                        <i class="feather icon-{{ $stepIcon }}"></i>
                                    </span>
                                @else
                                    <a class="page-link sl-page-link" href="#" data-page="{{ $stepPage }}"
                                       aria-label="{{ $stepLabel }}" title="{{ $stepLabel }}">
                                        <i class="feather icon-{{ $stepIcon }}"></i>
                                    </a>
                                @endif
                            </li>
                        @endforeach

                        @for ($page = $from; $page <= $to; $page++)
                            <li class="page-item {{ $page === $current ? 'active' : '' }}">
                                @if ($page === $current)
                                    <span class="page-link" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="page-link sl-page-link" href="#" data-page="{{ $page }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endfor

                        @foreach ($steps['after'] as [$stepIcon, $stepPage, $stepLabel, $stepOff])
                            <li class="page-item {{ $stepOff ? 'disabled' : '' }}">
                                @if ($stepOff)
                                    <span class="page-link" aria-hidden="true">
                                        <i class="feather icon-{{ $stepIcon }}"></i>
                                    </span>
                                @else
                                    <a class="page-link sl-page-link" href="#" data-page="{{ $stepPage }}"
                                       aria-label="{{ $stepLabel }}" title="{{ $stepLabel }}">
                                        <i class="feather icon-{{ $stepIcon }}"></i>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
@endif
