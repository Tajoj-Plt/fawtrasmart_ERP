{{--
    تذييل الجدول: عدّ النتائج وشريط التنقّل — مكوّنٌ واحد لكل الوحدات.

    **ما كان أوّلاً**

    كل شاشة تبني الشريط بيدها: «الأول» و«السابق» و«التالي» و«الأخير» بأربع
    كتل `@if` متكرّرة وأيقونات `fa-angle-double` بلا مراعاة لاتّجاه الصفحة.
    ثلاثةَ عشرَ موضعاً في وحدة الحسابات وحدها، ولا اثنان منها متطابقان.

    **ولماذا لا `links()`**

    استبدلتُها أوّلاً بـ`$paginator->links()` ظنّاً أنّها تتبع قالب النظام —
    وهي لا تفعل: المشروع لا يسجّل `Paginator::useBootstrapFive()` ولا يحمل
    `resources/views/vendor/pagination/`، فيصيّر لارافيل قالبه الافتراضي
    **بترميز Tailwind**: عناصر `<span class="relative inline-flex …">`
    وأصناف utility لا وجود لها في هذا المشروع أصلاً. فيخرج شريطٌ عارٍ بلا
    حدودٍ ولا تباعد، ولا يشبه شيئاً في بقية الشاشات.

    **ما صار**

    الترميز نفسه الذي تعرضه شاشة أذون المخزن ويصمّمه `stock-theme`:
    `ul.pagination` بعناصر `page-item`/`page-link`، وأيقونات feather تتبع
    اتّجاه الصفحة، ونافذةٌ من الأرقام حول الحالية مع الأولى والأخيرة
    دائماً وحذفٌ بينهما.

    الوسائط:
      paginator  الكائن المرقَّم (إلزامي)
      unit       وحدة العدّ: «قيداً»، «سنداً»، «حساباً»… (افتراضي: «نتيجة»)
      window     عدد الأرقام حول الصفحة الحالية (افتراضي 2)

    الاستعمال:
      @include('stock::components.table-foot', ['paginator' => $entries, 'unit' => 'قيداً'])
--}}

@php
    $paginator = $paginator ?? null;
    $unit = $unit ?? 'نتيجة';
    $window = (int) ($window ?? 2);

    $isPaginator =
        $paginator instanceof \Illuminate\Contracts\Pagination\Paginator ||
        $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    // `Paginator` البسيط لا يعرف الإجمالي ولا آخر صفحة
    $knowsTotal = $isPaginator && method_exists($paginator, 'total');
@endphp

@if ($isPaginator)
    @php
        $current = $paginator->currentPage();
        $last = $knowsTotal ? $paginator->lastPage() : $current;

        $from = max(1, $current - $window);
        $to = min($last, $current + $window);

        $atStart = $paginator->onFirstPage();
        $atEnd = ! $paginator->hasMorePages();

        // الروابط تحافظ على الفلاتر المطبَّقة
        $url = fn (int $page) => $paginator->appends(request()->except('page'))->url($page);
    @endphp

    <div class="stk-table-foot">
        <span class="stk-table-foot__count">
            @if ($knowsTotal && $paginator->total() > 0)
                عرض <strong class="stk-num">{{ $paginator->firstItem() }}</strong>–<strong
                    class="stk-num">{{ $paginator->lastItem() }}</strong>
                من <strong class="stk-num">{{ number_format($paginator->total()) }}</strong> {{ $unit }}
            @elseif (! $knowsTotal && $paginator->count() > 0)
                عرض <strong class="stk-num">{{ $paginator->firstItem() }}</strong>–<strong
                    class="stk-num">{{ $paginator->lastItem() }}</strong>
            @else
                لا نتائج
            @endif
        </span>

        @if ($paginator->hasPages())
            <nav aria-label="تصفح الصفحات">
                <ul class="pagination mb-0">
                    @foreach ([['chevrons-right', 1, 'الصفحة الأولى', $atStart], ['chevron-right', max(1, $current - 1), 'السابقة', $atStart]] as [$icon, $page, $label, $off])
                        <li class="page-item {{ $off ? 'disabled' : '' }}">
                            @if ($off)
                                <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                            @else
                                <a class="page-link" href="{{ $url($page) }}" aria-label="{{ $label }}"
                                    title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                            @endif
                        </li>
                    @endforeach

                    @if ($knowsTotal)
                        @if ($from > 1)
                            <li class="page-item"><a class="page-link" href="{{ $url(1) }}">1</a></li>

                            @if ($from > 2)
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            @endif
                        @endif

                        @for ($page = $from; $page <= $to; $page++)
                            <li class="page-item {{ $page === $current ? 'active' : '' }}">
                                @if ($page === $current)
                                    <span class="page-link" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="page-link" href="{{ $url($page) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endfor

                        @if ($to < $last)
                            @if ($to < $last - 1)
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            @endif

                            <li class="page-item"><a class="page-link" href="{{ $url($last) }}">{{ $last }}</a></li>
                        @endif
                    @else
                        {{-- بلا إجمالي: الصفحة الحالية وحدها تُعرَض --}}
                        <li class="page-item active"><span class="page-link">{{ $current }}</span></li>
                    @endif

                    @foreach ([['chevron-left', $current + 1, 'التالية', $atEnd], ['chevrons-left', $last, 'الصفحة الأخيرة', $atEnd || ! $knowsTotal]] as [$icon, $page, $label, $off])
                        <li class="page-item {{ $off ? 'disabled' : '' }}">
                            @if ($off)
                                <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                            @else
                                <a class="page-link" href="{{ $url($page) }}" aria-label="{{ $label }}"
                                    title="{{ $label }}"><i class="feather icon-{{ $icon }}"></i></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
@endif
