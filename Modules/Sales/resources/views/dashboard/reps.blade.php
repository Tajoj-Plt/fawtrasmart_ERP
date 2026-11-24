{{--
    أداء المندوبين — جدولُ «أداء المندوبين» من لوحة المبيعات في شاشةٍ مستقلّة.

    الأجزاءُ كلُّها مشتركةٌ مع اللوحة (`sales::dashboard.partials.*`): الأنماطُ،
    شريطُ الفلترة، الجدولُ، اللوحةُ الجانبية والسكربت — فما يُصلَح هناك يُصلَح
    هنا. الفرقُ ثلاثة: كلُّ المندوبين لا أعلى ٢٥، الترتيبُ بالأكثر تحصيلاً
    (أو بما يختاره المستخدم)، وبطاقةُ «أفضل مندوب» مع المركزين التاليين
    ومجموعِ الفريق في الصدارة.

    كل رقمٍ يأتي جاهزاً من `SalesDashboardMetrics::salesByEmployee()` — والشاشة
    لا تحسب شيئاً سوى مجموع الفريق الذي حسبه المتحكّم من الصفوف نفسِها.
--}}
@extends('sales::master')

@section('title', __('sales_dashboard.reps.title'))

{{-- سكربتُ الصفحة الرئيسية التجريبي يبحث عن عناصر ليست هنا --}}
@section('disable-dashboard-ecommerce', true)

@php
    /*
     * لا رقم يُنسَّق هنا يدوياً ولا لونَ مكتوب:
     *   المبلغ  ← CurrencySettings (الرمز والمنازل والفواصل من الإعدادات)
     *   اللون   ← متغيّرات --app-* التي يبثّها ThemeSettings
     *   النصّ   ← __() فيتبع اللغة المفعَّلة
     */
    $money = fn ($value) => \App\Support\CurrencySettings::number($value);
    $currency = \App\Support\CurrencySettings::symbol();

    $periodTabs = $periodTabs + ['custom' => __('sales_dashboard.toolbar.custom')];
    $isCustom = $period->key === 'custom';
    $infoLabel = __('sales_dashboard.info.toggle');

    $toolbarTitle = __('sales_dashboard.reps.title');
    $toolbarIcon = 'users';

    $repMonth = $employeeTargetMonth->translatedFormat('F Y');
    // ١٠٠٪ فأكثر محقَّق، و٧٠٪ فأكثر قريب، وما دونه متأخّر
    $targetTone = fn ($percent) => $percent >= 100 ? 'var(--sd-success)' : ($percent >= 70 ? 'var(--sd-warning)' : 'var(--sd-danger)');

    // العمودُ المرتَّب به: اسمُه، وهل هو مبلغٌ أم عدد، وأيُّ قائمةٍ يفتح
    $metricLabel = $sortOptions[$sort] ?? $sort;
    $moneyMetrics = ['collections', 'sales', 'net_sales', 'returns_total'];
    $metricValue = fn (array $row, string $key) => in_array($key, $moneyMetrics, true) ? $money($row[$key]) : number_format($row[$key]);
    $metricUnit = fn (string $key) => in_array($key, $moneyMetrics, true) ? $currency : null;
    $segmentFor = function (array $row, string $key): ?string {
        $empKey = (int) ($row['employee_id'] ?? 0);

        return match ($key) {
            'collections' => "emp.$empKey.collection",
            'sales', 'invoices' => "emp.$empKey.sale",
            'returns_total' => "emp.$empKey.returns",
            'net_sales' => "emp.$empKey.all",
            'visits' => $row['employee_id'] && $row['visits'] > 0 ? 'visits.'.$row['employee_id'] : null,
            default => null,
        };
    };
    $metricColor = match ($sort) {
        'collections' => 'var(--sd-success)',
        'returns_total' => 'var(--sd-danger)',
        'visits' => 'var(--sd-info)',
        default => 'var(--sd-primary)',
    };

    // بطاقاتُ الصدارة: الأوّلُ كبيراً، والثاني والثالث مختصرَين
    $podium = $reps->take(3)->values();
    $best = $podium->first();
    $runnersUp = $podium->slice(1)->values();

    // ما يُعرض تحت اسم أفضل مندوب — بترتيب الأهمّية، والعمودُ المرتَّب به أوّلاً بلا تكرار
    $heroStats = collect(['collections', 'sales', 'net_sales', 'returns_total', 'invoices', 'visits'])
        ->reject(fn ($key) => $key === $sort)
        ->values();
@endphp

@push('styles')
    @include('sales::dashboard.partials.styles')
    <style>
        /* الصدارة: أفضلُ مندوبٍ والمركزان التاليان ومجموعُ الفريق */
        #sales-dashboard .sd-hero {
            height: 100%;
            background: linear-gradient(135deg, color-mix(in srgb, var(--sd-primary) 12%, var(--sd-surface)), var(--sd-surface) 70%);
            border: 1px solid color-mix(in srgb, var(--sd-primary) 30%, var(--sd-border));
            border-radius: calc(var(--sd-radius) + 4px);
            padding: 1.1rem 1.25rem;
            display: flex; flex-direction: column;
        }
        #sales-dashboard .sd-hero-head { display: flex; align-items: center; gap: .85rem; }
        #sales-dashboard .sd-hero-crown {
            width: 46px; height: 46px; border-radius: 50%; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center; font-size: 1.35rem;
            background: color-mix(in srgb, var(--sd-warning) 18%, transparent); color: var(--sd-warning);
        }
        #sales-dashboard .sd-hero-label { margin: 0; font-size: .78rem; color: var(--sd-muted); }
        #sales-dashboard .sd-hero-name { margin: .1rem 0 0; font-size: 1.25rem; font-weight: 800; color: var(--sd-text); cursor: pointer; }
        #sales-dashboard .sd-hero-name:hover { color: var(--sd-primary); text-decoration: underline; }
        #sales-dashboard .sd-hero-metric { margin: .9rem 0 0; cursor: pointer; }
        #sales-dashboard .sd-hero-metric b { font-size: 1.9rem; font-weight: 800; line-height: 1.1; }
        #sales-dashboard .sd-hero-metric small { font-size: .85rem; color: var(--sd-muted); margin-inline-start: .3rem; }
        #sales-dashboard .sd-hero-metric p { margin: .15rem 0 0; font-size: .78rem; color: var(--sd-muted); }
        #sales-dashboard .sd-hero-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin-top: .9rem; }
        #sales-dashboard .sd-hero-stat {
            background: color-mix(in srgb, var(--sd-surface) 75%, transparent);
            border: 1px solid var(--sd-border); border-radius: var(--sd-radius); padding: .5rem .6rem;
        }
        #sales-dashboard .sd-hero-stat[data-segment] { cursor: pointer; }
        #sales-dashboard .sd-hero-stat[data-segment]:hover { border-color: var(--sd-primary); }
        #sales-dashboard .sd-hero-stat small { display: block; color: var(--sd-muted); font-size: .72rem; white-space: nowrap; }
        #sales-dashboard .sd-hero-stat b { font-size: .95rem; color: var(--sd-text); }
        #sales-dashboard .sd-hero-targets { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-top: .9rem; }
        #sales-dashboard .sd-hero-target small { display: block; color: var(--sd-muted); font-size: .72rem; }

        #sales-dashboard .sd-podium { display: flex; flex-direction: column; gap: .75rem; height: 100%; }
        #sales-dashboard .sd-podium-card {
            flex: 1; background: var(--sd-surface); border: 1px solid var(--sd-border);
            border-radius: calc(var(--sd-radius) + 4px); padding: .85rem 1rem;
            display: flex; align-items: center; gap: .75rem; min-width: 0;
        }
        #sales-dashboard .sd-podium-card > div { min-width: 0; flex: 1; }
        #sales-dashboard .sd-podium-name { font-weight: 700; color: var(--sd-text); cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #sales-dashboard .sd-podium-name:hover { color: var(--sd-primary); text-decoration: underline; }
        #sales-dashboard .sd-podium-metric { font-weight: 800; font-size: 1.05rem; cursor: pointer; }
        #sales-dashboard .sd-podium-sub { font-size: .72rem; color: var(--sd-muted); }
        #sales-dashboard .sd-rank-badge { width: 36px; height: 36px; border-radius: 50%; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        #sales-dashboard .sd-rank-1 { background: color-mix(in srgb, var(--sd-success) 20%, transparent); color: var(--sd-success); }
        #sales-dashboard .sd-rank-2 { background: color-mix(in srgb, var(--sd-muted) 20%, transparent); color: var(--sd-muted); }
        #sales-dashboard .sd-rank-3 { background: color-mix(in srgb, var(--sd-danger) 16%, transparent); color: var(--sd-danger); }
        #sales-dashboard .sd-rank-plain { background: color-mix(in srgb, var(--sd-muted) 10%, transparent); color: var(--sd-muted); }

        #sales-dashboard .sd-team { height: 100%; display: flex; flex-direction: column; }
        #sales-dashboard .sd-team-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
        #sales-dashboard .sd-team-stat { border: 1px solid var(--sd-border); border-radius: var(--sd-radius); padding: .5rem .6rem; }
        #sales-dashboard .sd-team-stat small { display: block; color: var(--sd-muted); font-size: .72rem; }
        #sales-dashboard .sd-team-stat b { font-size: .95rem; }

        /* جدولُ أداء المندوبين: شارةُ المركز بحجم خليّة الجدول */
        #sales-dashboard .sd-rep-table .sd-rank-badge { width: 30px; height: 30px; font-size: .8rem; margin: 0 auto; }

        @media (max-width: 991.98px) {
            #sales-dashboard .sd-hero, #sales-dashboard .sd-podium, #sales-dashboard .sd-team { height: auto; margin-bottom: 1rem; }
        }
    </style>
@endpush

@section('content')
    <div class="content-body">
        <section id="sales-dashboard">

            @include('sales::partials.settings-theme')
            @include('components.hint-tips')

            {{-- إرشادُ الشاشة — مطويٌّ افتراضياً كلوحة المؤشّرات: من فتحها جاء لأرقامها --}}
            @include('sales::components.guide', [
                'key' => 'sales-dashboard.reps',
                'title' => __('sales_dashboard.reps.guide.title'),
                'lead' => __('sales_dashboard.reps.guide.lead'),
                'open' => false,
                'blocks' => [
                    __('sales_dashboard.reps.guide.ranking.title') => [
                        __('sales_dashboard.reps.guide.ranking.a'),
                        __('sales_dashboard.reps.guide.ranking.b'),
                    ],
                    __('sales_dashboard.reps.guide.collections.title') => [
                        __('sales_dashboard.reps.guide.collections.a'),
                        __('sales_dashboard.reps.guide.collections.b'),
                    ],
                    __('sales_dashboard.reps.guide.scope.title') => [
                        __('sales_dashboard.reps.guide.scope.a'),
                        __('sales_dashboard.reps.guide.scope.b'),
                    ],
                ],
            ])

            {{-- ==================== الفلترة (الفترة، الفرع، الترتيب) ==================== --}}
            @include('sales::dashboard.partials.toolbar')

            @if ($periodNotice)
                <div class="sd-notice {{ $periodNotice['level'] }}">{{ $periodNotice['message'] }}</div>
            @endif

            @if ($reps->isEmpty())
                <div class="sd-card">
                    <div class="sd-card-body">
                        <div class="sd-empty"><i class="feather icon-user"></i> {{ __('sales_dashboard.reps.no_reps') }}</div>
                    </div>
                </div>
            @else
                {{-- ==================== الصدارة ==================== --}}
                @php
                    $bestKey = (int) ($best['employee_id'] ?? 0);
                    $bestSegment = $segmentFor($best, $sort);
                @endphp
                <div class="row">
                    <div class="col-xl-5 col-lg-6 col-12 mb-1">
                        <div class="sd-hero">
                            <div class="sd-hero-head">
                                <div class="sd-hero-crown"><i class="feather icon-award"></i></div>
                                <div>
                                    <p class="sd-hero-label">{{ __('sales_dashboard.reps.best') }} — {{ __('sales_dashboard.reps.best_hint', ['metric' => $metricLabel, 'period' => $period->label]) }}</p>
                                    <h3 class="sd-hero-name" data-segment="emp.{{ $bestKey }}.all" role="button" tabindex="0">{{ $best['name'] }}</h3>
                                </div>
                            </div>

                            <div class="sd-hero-metric" @if ($bestSegment) data-segment="{{ $bestSegment }}" role="button" tabindex="0" title="{{ __('sales_dashboard.drill.open') }}" @endif>
                                <b class="sd-num" style="color: {{ $metricColor }};">{{ $metricValue($best, $sort) }}</b>
                                @if ($metricUnit($sort))<small>{{ $metricUnit($sort) }}</small>@endif
                                <p>{{ $metricLabel }}</p>
                            </div>

                            <div class="sd-hero-grid">
                                @foreach ($heroStats as $statKey)
                                    @php $statSegment = $segmentFor($best, $statKey); @endphp
                                    <div class="sd-hero-stat" @if ($statSegment) data-segment="{{ $statSegment }}" role="button" tabindex="0" @endif>
                                        <small>{{ __('sales_dashboard.reps.stats.'.$statKey) }}</small>
                                        <b class="sd-num">{{ $metricValue($best, $statKey) }}</b>
                                    </div>
                                @endforeach
                                @if ($best['refunds_count'] > 0)
                                    <div class="sd-hero-stat" data-segment="emp.{{ $bestKey }}.refund" role="button" tabindex="0">
                                        <small>{{ __('sales_dashboard.reps.stats.refunds') }}</small>
                                        <b class="sd-num" style="color: var(--sd-danger);">{{ $money($best['refunds']) }}</b>
                                    </div>
                                @endif
                            </div>

                            {{-- الهدفان الشهريان — كما في الجدول: النسبةُ والمحقَّق من الهدف --}}
                            <div class="sd-hero-targets">
                                @foreach (['sales_target' => 'sales_target', 'collections_target' => 'collections_target'] as $targetKey => $labelKey)
                                    <div class="sd-hero-target">
                                        <small>{{ __('sales_dashboard.table.'.$labelKey) }} · {{ $repMonth }}</small>
                                        @if ($target = $best[$targetKey])
                                            <span class="sd-num font-weight-bold" style="color: {{ $targetTone($target['percent']) }};">{{ number_format($target['percent'], 1) }}%</span>
                                            <div class="sd-bar"><span style="width: {{ min(100, max(2, $target['percent'])) }}%; background: {{ $targetTone($target['percent']) }};"></span></div>
                                            <small class="sd-num">{{ $money($target['achieved']) }} / {{ $money($target['target']) }}</small>
                                        @else
                                            <small>{{ __('sales_dashboard.rep.no_target') }}</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-6 col-12 mb-1">
                        <div class="sd-podium">
                            @foreach ($runnersUp as $index => $rep)
                                @php
                                    $rank = $index + 2;
                                    $repKey = (int) ($rep['employee_id'] ?? 0);
                                    $repSegment = $segmentFor($rep, $sort);
                                @endphp
                                <div class="sd-podium-card">
                                    <span class="sd-rank-badge sd-rank-{{ $rank }}">{{ $rank }}</span>
                                    <div>
                                        <div class="sd-podium-sub">{{ __('sales_dashboard.reps.rank', ['rank' => $rank]) }}</div>
                                        <div class="sd-podium-name" data-segment="emp.{{ $repKey }}.all" role="button" tabindex="0">{{ $rep['name'] }}</div>
                                        <div class="sd-podium-metric sd-num" style="color: {{ $metricColor }};" @if ($repSegment) data-segment="{{ $repSegment }}" role="button" tabindex="0" @endif>
                                            {{ $metricValue($rep, $sort) }} @if ($metricUnit($sort))<small class="sd-podium-sub">{{ $metricUnit($sort) }}</small>@endif
                                        </div>
                                        <div class="sd-podium-sub">{{ __('sales_dashboard.reps.stats.sales') }} {{ $money($rep['sales']) }} · {{ __('sales_dashboard.reps.stats.collections') }} {{ $money($rep['collections']) }}</div>
                                    </div>
                                </div>
                            @endforeach
                            @if ($runnersUp->isEmpty())
                                <div class="sd-podium-card"><div class="sd-empty" style="padding: .5rem 0;"><i class="feather icon-users"></i> {{ __('sales_dashboard.reps.subtitle', ['count' => $reps->count(), 'metric' => $metricLabel]) }}</div></div>
                            @endif
                        </div>
                    </div>

                    <div class="col-xl-4 col-12 mb-1">
                        <div class="sd-card sd-team">
                            <div class="sd-card-head">
                                <div>
                                    <h4><i class="feather icon-users"></i> {{ __('sales_dashboard.reps.team') }}</h4>
                                    <small>{{ __('sales_dashboard.reps.team_hint', ['count' => $reps->count()]) }}</small>
                                </div>
                            </div>
                            <div class="sd-card-body">
                                <div class="sd-team-grid">
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.sales') }}</small><b class="sd-num">{{ $money($team['sales']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.collections') }}</small><b class="sd-num" style="color: var(--sd-success);">{{ $money($team['collections']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.returns_total') }}</small><b class="sd-num" style="color: var(--sd-danger);">{{ $money($team['returns_total']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.net_sales') }}</small><b class="sd-num">{{ $money($team['net_sales']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.invoices') }}</small><b class="sd-num">{{ number_format($team['invoices']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.visits') }}</small><b class="sd-num">{{ number_format($team['visits']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.expenses') }}</small><b class="sd-num" style="color: var(--sd-warning);">{{ $money($team['expenses']) }}</b></div>
                                    <div class="sd-team-stat"><small>{{ __('sales_dashboard.reps.stats.refunds') }}</small><b class="sd-num" style="color: var(--sd-danger);">{{ $money($team['refunds']) }}</b></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== كل المندوبين ==================== --}}
                <div class="row">
                    <div class="col-12">
                        <div class="sd-card">
                            <div class="sd-card-head">
                                <div>
                                    <h4><i class="feather icon-list"></i> {{ __('sales_dashboard.reps.table_title') }}</h4>
                                    <small>{{ __('sales_dashboard.reps.subtitle', ['count' => $reps->count(), 'metric' => $metricLabel]) }} — {{ __('sales_dashboard.reps.table_hint', ['month' => $repMonth]) }}</small>
                                </div>
                                <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                            </div>
                            <div class="sd-card-body">
                                <div class="sd-info-panel" hidden>{{ __('sales_dashboard.reps.info') }}</div>
                                @include('sales::dashboard.partials.rep-table', [
                                    'salesByEmployee' => $reps,
                                    'showRank' => true,
                                    'sortBy' => $sort,
                                    'repTotals' => $team,
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ==================== اللوحة الجانبية: ما وراء الرقم ==================== --}}
            @include('sales::dashboard.partials.drawer')

        </section>
    </div>
@endsection

@push('scripts')
    @include('sales::dashboard.partials.script')
@endpush
