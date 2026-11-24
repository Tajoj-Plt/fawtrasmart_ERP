{{--
    فهرسُ تقارير نقاط البيع.

    كلُّ بلاطةٍ تظهر لمن يملك صلاحيتها وحده — والمتحكّم يبني القائمة بفحص
    `permission()` لكل تقرير، فلا يُخفى الرابطُ هنا ويُترك المسارُ مفتوحاً
    هناك.

    ═══════════════ والتصميم ═══════════════

    كانت بطاقاتٍ ثلاثيةَ الأعمدة بارتفاعاتٍ متفاوتة، وترويسةً مكتوبةً بيدها،
    وأيقوناتِ `data-feather` تحتاج تشغيلَ JS بعد الرسم — فإن لم يُستدعَ
    `feather.replace()` بقيت الوسومُ فارغةً بلا أيقونة.

    صارت `stk-tiles` نفسَها المستعملة في فهرس تقارير الحسابات، وأيقوناتُها
    `<i class="feather icon-…">` تُرسم بالـCSS بلا JS.
--}}
@extends('pos::master')

@section('title', 'تقارير نقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Support\PlanLock;

    $meta = [
        'category' => ['grid', 'مبيعاتُ التصنيفات صافيةً بعد المرتجعات، مجمَّعةً بالتصنيف أو التاريخ أو الجلسة أو الفرع.', PosPlanFeatures::REPORTS],
        'product' => ['package', 'مبيعاتُ الأصناف — بالصنف وحده أو بالصنف والوحدة، فلا تختلط كمّياتُ الحبّة بالكرتون.', PosPlanFeatures::REPORTS],
        'shift' => ['clock', 'أداءُ الجلسات والورديات: عددُ الفواتير ومتوسّطُها والتحصيلُ نقداً وبطاقة.', PosPlanFeatures::REPORTS],
        'payment' => ['credit-card', 'التحصيلُ حسب طريقة الدفع، وما سُوّي منه وما لم يُسوَّ بعدُ مع البنك.', PosPlanFeatures::REPORTS],
        'profit' => ['trending-up', 'التكلفةُ والهامش — بتكلفةٍ مجمَّدةٍ لحظةَ البيع، فلا يتغيّر ربحُ الأمس بتغيير سعر اليوم.', PosPlanFeatures::PROFIT_REPORTS],
        'variance' => ['alert-triangle', 'فروقاتُ الصندوق: الفائضُ والعجزُ منفصلَين، ونسبةُ الجلسات ذات الفرق لكل كاشير.', PosPlanFeatures::VARIANCE_REPORTS],
    ];

    /*
        تقريرا الربح والفروقات ميزتان مستقلّتان في الباقة، لا تتبعان
        «التقارير» العامّة — فمنشأةٌ اشترت التقارير لا تملك بالضرورة تحليلَ
        الهامش. والوسيطُ `plan:` على مسارَيهما يفرض ذلك، والبلاطةُ تعكسه.
    */
@endphp

<div class="content-body">

    @include('pos::components.page-head', [
        'title' => 'تقارير نقاط البيع',
        'icon' => 'bar-chart-2',
        'sub' => 'المبيعات والتحصيل والهامش وفروقات الصندوق',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
            'التقارير' => null,
        ],
    ])

    {{-- ═══════════ تقريرا التشغيل اليومي ═══════════ --}}
    @if ($canViewZ)
        @php $zLocked = PlanLock::locked(PosPlanFeatures::REPORTS); @endphp

        <div class="card">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title">
                        <i class="feather icon-file-text"></i> تقارير الوردية
                        @if ($zLocked)
                            <span class="stk-plan-badge"><i class="feather icon-lock"></i> خارج الباقة</span>
                        @endif
                    </h6>
                </div>

                <p class="stk-help">
                    تقريرُ <strong>X</strong> يُقرأ أثناء الوردية ولا يُقفلها.
                    وتقريرُ <strong>Z</strong> هو الملخّص النهائي الذي يُطبع عند الإقفال ويُسلَّم مع العهدة.
                </p>

                <a href="{{ $zLocked ? PlanLock::url() : route('pos.reports.x') }}"
                   class="btn btn-primary"
                   @if ($zLocked) title="{{ PlanLock::reason(PosPlanFeatures::REPORTS) }}" @endif>
                    <i class="feather icon-{{ $zLocked ? 'lock' : 'eye' }}"></i> تقرير X — جلستي الحالية
                </a>

                <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-primary">
                    <i class="feather icon-printer"></i> تقرير Z — اختر جلسة
                </a>
            </div>
        </div>
    @endif

    {{-- ═══════════ التقارير التحليلية ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if (! empty($reports))
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-pie-chart"></i> التقارير التحليلية
                        <span class="stk-count">{{ count($reports) }}</span>
                    </h6>
                </div>

                <div class="stk-tiles">
                    @foreach ($reports as $key => $report)
                        @php
                            [$icon, $hint, $feature] = $meta[$key] ?? ['file-text', '', PosPlanFeatures::REPORTS];
                            $locked = PlanLock::locked($feature);
                        @endphp

                        <a href="{{ $locked ? PlanLock::url() : route('pos.reports.show', $key) }}"
                           class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                           @if ($locked) title="{{ PlanLock::reason($feature) }}" @endif>
                            <span class="stk-tile__icon">
                                <i class="feather icon-{{ $locked ? 'lock' : $icon }}"></i>
                            </span>
                            <span class="stk-tile__body">
                                <span class="stk-tile__title d-block">
                                    {{ $report['title'] }}
                                    @if ($locked)
                                        <span class="stk-plan-badge">
                                            <i class="feather icon-lock"></i> خارج الباقة
                                        </span>
                                    @endif
                                </span>
                                <span class="stk-tile__hint d-block">
                                    {{ $locked
                                        ? 'غير مشمول بباقتك الحالية — اضغط لعرض الاشتراك والترقية.'
                                        : $hint }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></span>
                    <h6 class="stk-empty__title">لا تقارير متاحة لك</h6>
                    <p class="stk-empty__hint">
                        لا تملك صلاحية عرض أيٍّ من تقارير نقاط البيع —
                        راجع مدير النظام لمنحك ما يلزم عملك.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
