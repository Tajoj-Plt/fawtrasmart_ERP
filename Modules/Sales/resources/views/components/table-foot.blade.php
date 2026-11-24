{{--
    ذيل بطاقة الجدول: عدّاد النتائج، ومُنتقي عدد السجلّات، وشريط الترقيم —
    بنفس ترميز ذيل جدول «الأذون المخزنية» وشكله.

    الترقيم مبنيّ بترميزنا لا بـ`$paginator->links()`: قالب الترقيم
    الافتراضي في لارافيل يخرج بأصناف Bootstrap عارية لا تعرفها ورقة
    الأنماط هنا، ونافذة الصفحات فيه لا تُقصَر على المحيط القريب. وهذه
    الشاشات صفحاتها GET لا Ajax، فالروابط عناوين حقيقية تُفتح في تبويب
    جديد وتُحفظ في المفضّلة — لا `href="#"` كما في نظيرتها في المخزون.

    البند 6.2 في تقرير التحسين يرصد ضياع الفلاتر عند التنقّل:
    `$paginator->url()` يحمل معه ما مُرِّر إلى `appends()` في المتحكّم.

    الوسائط:
      paginator  كائن `LengthAwarePaginator`
      sizes      خيارات عدد السجلّات (اختياري)
--}}
@php
    $sizes = (isset($sizes) && is_array($sizes) && $sizes !== []) ? $sizes : [10, 25, 50, 100];

    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // نافذة صفحتين حول الحالية: شريطٌ بمئة رقم لا يُقرأ ولا يُضغط
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);

    $atStart = $paginator->onFirstPage();
    $atEnd = ! $paginator->hasMorePages();

    $isRtl = $languageIsRtl ?? true;

    // السهم يتبع اتجاه اللغة: «التالية» إلى اليسار في العربية وإلى اليمين
    // في الإنجليزية، وأيقونة ثابتة تعني في إحداهما عكس ما تعنيه في الأخرى
    $steps = [
        'before' => [
            [$isRtl ? 'chevrons-right' : 'chevrons-left', 1, __('sales_settings.ui.page_first'), $atStart],
            [$isRtl ? 'chevron-right' : 'chevron-left', $current - 1, __('sales_settings.ui.page_prev'), $atStart],
        ],
        'after' => [
            [$isRtl ? 'chevron-left' : 'chevron-right', $current + 1, __('sales_settings.ui.page_next'), $atEnd],
            [$isRtl ? 'chevrons-left' : 'chevrons-right', $last, __('sales_settings.ui.page_last'), $atEnd],
        ],
    ];
@endphp

@if ($paginator->total() > 0)
    <div class="sl-table-foot">
        <p class="sl-table-foot__count">
            {{ __('sales_settings.ui.showing', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="d-flex align-items-center" style="gap:.75rem;flex-wrap:wrap">
            @include('sales::components.per-page', [
                'id' => 'slPerPage',
                'sizes' => $sizes,
                'current' => $paginator->perPage(),
                'onchange' => 'window.slGoPerPage(this.value)',
            ])

            @if ($paginator->hasPages())
                <nav aria-label="{{ __('sales_settings.ui.pages') }}">
                    <ul class="pagination mb-0">
                        @foreach ($steps['before'] as [$icon, $page, $label, $off])
                            <li class="page-item {{ $off ? 'disabled' : '' }}">
                                @if ($off)
                                    <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                                @else
                                    <a class="page-link" href="{{ $paginator->url($page) }}"
                                       aria-label="{{ $label }}" title="{{ $label }}">
                                        <i class="feather icon-{{ $icon }}"></i>
                                    </a>
                                @endif
                            </li>
                        @endforeach

                        @if ($from > 1)
                            <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
                            @if ($from > 2)
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            @endif
                        @endif

                        @for ($page = $from; $page <= $to; $page++)
                            <li class="page-item {{ $page === $current ? 'active' : '' }}">
                                @if ($page === $current)
                                    <span class="page-link" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endfor

                        @if ($to < $last)
                            @if ($to < $last - 1)
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            @endif
                            <li class="page-item"><a class="page-link" href="{{ $paginator->url($last) }}">{{ $last }}</a></li>
                        @endif

                        @foreach ($steps['after'] as [$icon, $page, $label, $off])
                            <li class="page-item {{ $off ? 'disabled' : '' }}">
                                @if ($off)
                                    <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                                @else
                                    <a class="page-link" href="{{ $paginator->url($page) }}"
                                       aria-label="{{ $label }}" title="{{ $label }}">
                                        <i class="feather icon-{{ $icon }}"></i>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>

    @once
        <script>
            /* تغيير عدد السجلّات يُعيد إلى الصفحة الأولى: البقاء على الصفحة
               الخامسة بعد توسيع الصفحة يعرض نتائج لم يطلبها أحد */
            window.slGoPerPage = function (value) {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', value);
                url.searchParams.delete('page');
                window.location.assign(url.toString());
            };
        </script>
    @endonce
@endif
