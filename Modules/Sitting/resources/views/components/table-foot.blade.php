{{--
    ذيل بطاقة الجدول — عدّادُ النتائج، ومُنتقي عدد السجلّات، وشريطُ الترقيم،
    بترميز ذيل جداول المبيعات (`sl-table-foot` + `sl-perpage`) حرفاً بحرف.

    الترميزُ مكتوبٌ هنا ولا يُستدعى `sales::components.table-foot`: وحدةٌ
    معطَّلة تُسقط قوالبها. والنصوصُ من ملفّ الترجمة العامّ فلا تتأثّر بذلك.

    قوائمُ هذه الوحدة كانت تُنهي جداولها بأربع صورٍ مختلفة: `links()` الخام
    بأصناف Bootstrap، وقالبُ `bootstrap-4`، وترقيمٌ مخصَّص، وبعضُها بلا عدّاد.
    وليس في أيٍّ منها اختيارُ عدد السجلّات.

    الوسائط:
      paginator  كائن `LengthAwarePaginator`
      sizes      خيارات عدد السجلّات (اختياري)

    وعددُ السجلّات يقرؤه المتحكّم بـ`Modules\Sitting\Support\PageSize::get()`.
--}}
@php
    $sizes = (isset($sizes) && is_array($sizes) && $sizes !== []) ? $sizes : \Modules\Sitting\Support\PageSize::SIZES;

    // الفلاترُ وعددُ السجلّات تنتقل مع روابط الصفحات
    $paginator->withQueryString();

    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $perPage = (int) $paginator->perPage();

    // عددُ الشاشة الافتراضيّ قد لا يكون من الخيارات (15، 20، 30): يُضاف
    // ليظهر مختاراً بدل أن يبدو المُنتقي على قيمةٍ ليست قيمتَه
    if (! in_array($perPage, $sizes, true)) {
        $sizes[] = $perPage;
        sort($sizes);
    }

    // نافذة صفحتين حول الحالية: شريطٌ بمئة رقم لا يُقرأ ولا يُضغط
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);

    $atStart = $paginator->onFirstPage();
    $atEnd = ! $paginator->hasMorePages();

    $isRtl = $languageIsRtl ?? true;

    // السهم يتبع اتجاه اللغة: «التالية» إلى اليسار في العربية وإلى اليمين
    // في الإنجليزية
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
            <div class="sl-perpage">
                <label for="slPerPage">{{ __('sales_settings.ui.per_page_show') }}</label>
                <select id="slPerPage" class="sl-perpage__select" onchange="window.slGoPerPage(this.value)"
                        aria-label="{{ __('sales_settings.ui.per_page_aria') }}">
                    @foreach ($sizes as $size)
                        <option value="{{ $size }}" {{ $perPage === (int) $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                <span class="sl-perpage__unit">{{ __('sales_settings.ui.per_page_unit') }}</span>
            </div>

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
