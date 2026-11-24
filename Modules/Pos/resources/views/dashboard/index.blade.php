{{--
    الشاشةُ الرئيسية لنقاط البيع.

    كان `/dashboard/pos` مُحوِّلاً لا شاشة: يقفز إلى أوّل مسارٍ يملك المستخدمُ
    صلاحيتَه. فلا صفحةَ تُقرأ منها حالُ الوحدة، والمشرفُ يفتح أربعَ شاشاتٍ
    ويجمع بنفسه.

    ═══════════════ الطبقتان ═══════════════

    • **صلاحيةُ النظام تُخفي**: البطاقةُ التي لا يملك صاحبُها صلاحيتَها لا
      تُحسب في المتحكّم أصلاً ولا تُعرض هنا — فلا يُستعلَم عن بياناتٍ لن تُقرأ.

    • **ميزةُ الباقة تُقفل ولا تُخفي**: البلاطةُ خارج الباقة تبقى ظاهرةً
      وتقود إلى شاشة الاشتراك، لأن إخفاءها يجعل المنشأة لا تعرف أنها موجودة.
--}}
@extends('pos::master')

@section('title', 'نقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Models\PosSession;
    use App\Support\PlanLock;

    $states = [
        PosSession::STATUS_ACTIVE => ['stk-state--on', 'نشطة'],
        PosSession::STATUS_CLOSED => ['stk-state--neutral', 'مغلقة'],
        PosSession::STATUS_SUSPENDED => ['stk-state--warn', 'معلَّقة'],
        PosSession::STATUS_PENDING_REVIEW => ['stk-state--warn', 'بانتظار التأكيد'],
    ];

    /*
        بلاطاتُ الوصول السريع.

        `perm` صلاحياتٌ بديلة لا شرطٌ واحد — ومن لا يملك شيئاً منها لا تُعرض
        له البلاطة. و`feature` ميزةُ الباقة: تُقفل ولا تُخفي.
    */
    $tiles = [
        [
            'perm' => ['pos.sell'],
            'feature' => PosPlanFeatures::TERMINAL,
            'route' => 'POS.sales_start.index',
            'icon' => 'shopping-cart',
            'title' => 'بدء البيع',
            'hint' => 'شاشةُ الكاشير — الفواتيرُ والمرتجعاتُ والتحصيل',
        ],
        [
            'perm' => ['points_sale_view_own_sessions'],
            'feature' => PosPlanFeatures::SESSIONS,
            'route' => 'pos.sessions.index',
            'icon' => 'monitor',
            'title' => 'الجلسات',
            'hint' => 'فتحُ الوردية وإقفالُها وتأكيدُ العهدة',
        ],
        [
            'perm' => ['pos.cash_drawer.view'],
            'feature' => PosPlanFeatures::CASH_DRAWER,
            'route' => 'pos.cash_drawer.index',
            'icon' => 'dollar-sign',
            'title' => 'الدرج النقدي',
            'hint' => 'الفكّةُ والمصروفُ النثري والترحيلُ إلى الخزينة',
        ],
        [
            'perm' => ['pos_view_reports', 'pos_reports_view'],
            'feature' => PosPlanFeatures::REPORTS,
            'route' => 'pos_reports.index',
            'icon' => 'bar-chart-2',
            'title' => 'التقارير',
            'hint' => 'المبيعاتُ والتحصيلُ والهامشُ وفروقاتُ الصندوق',
        ],
        [
            'perm' => ['pos_manage_settings'],
            'feature' => PosPlanFeatures::SETTINGS,
            'route' => 'pos.settings.index',
            'icon' => 'sliders',
            'title' => 'الإعدادات',
            'hint' => 'الأجهزةُ والطابعاتُ والورديات',
        ],
    ];
@endphp

<div class="content-body">

    @section('page-actions')
        @permission('pos.sell')
            <a href="{{ $plan['terminal'] ? route('POS.sales_start.index') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($plan['terminal']) title="{{ PlanLock::reason(PosPlanFeatures::TERMINAL) }}" @endunless>
                <i class="feather icon-{{ $plan['terminal'] ? 'shopping-cart' : 'lock' }}"></i> بدء البيع
            </a>
        @endpermission
    @endsection

    @include('pos::components.page-head', [
        'title' => 'نقاط البيع',
        'icon' => 'monitor',
        'sub' => 'مبيعاتُ اليوم والجلساتُ المفتوحة وما ينتظر اعتمادك',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
        ],
    ])

    {{--
        تنبيهاتُ الإعداد أوّلاً: هذه أشياءُ تعمل صامتةً بالخطأ — قيدُ فرقٍ
        لا يُكتب، وجهازٌ لا يُباع منه — ولا يظهر أثرُها إلا بعد فوات الأوان.
    --}}
    @foreach ($alerts as $alert)
        <div class="alert alert-{{ $alert['level'] }}">
            <i class="feather icon-alert-triangle"></i>
            {{ $alert['text'] }}
            @if ($alert['url'])
                <a href="{{ $alert['url'] }}" class="alert-link">معالجة</a>
            @endif
        </div>
    @endforeach

    {{-- ═══════════ ما ينتظرك ═══════════ --}}
    @if ($pendingSessions > 0 || $pendingMovements > 0)
        <div class="alert alert-warning">
            <i class="feather icon-clock"></i>
            <strong>بانتظار اعتمادك:</strong>

            @if ($pendingSessions > 0)
                <a href="{{ route('pos.sessions.index', ['status' => 'pending_review']) }}" class="alert-link">
                    {{ $pendingSessions }} جلسة لتأكيد الإقفال
                </a>
            @endif

            @if ($pendingSessions > 0 && $pendingMovements > 0) · @endif

            @if ($pendingMovements > 0)
                <a href="{{ route('pos.cash_drawer.index', ['status' => 'pending']) }}" class="alert-link">
                    {{ $pendingMovements }} حركة درجٍ للاعتماد
                </a>
            @endif
        </div>
    @endif

    {{-- ═══════════ أرقام اليوم ═══════════ --}}
    <div class="stk-metrics">
        <div class="stk-metric stk-metric--success">
            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
            <span class="stk-metric__label">مبيعات اليوم</span>
            <span class="stk-metric__value">{{ number_format($stats['today_sales'], 2) }}</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
            <span class="stk-metric__label">فواتير اليوم</span>
            <span class="stk-metric__value">{{ number_format($stats['today_invoices']) }}</span>
        </div>

        <div class="stk-metric stk-metric--info">
            <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
            <span class="stk-metric__label">متوسّط الفاتورة</span>
            <span class="stk-metric__value">{{ number_format($stats['today_avg'], 2) }}</span>
        </div>

        <div class="stk-metric stk-metric--warning">
            <span class="stk-metric__icon"><i class="feather icon-play-circle"></i></span>
            <span class="stk-metric__label">جلسات مفتوحة الآن</span>
            <span class="stk-metric__value">{{ $openSessions->count() }}</span>
        </div>

        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
            <span class="stk-metric__label">{{ $period['label'] ?? 'مبيعات الفترة' }}</span>
            <span class="stk-metric__value">{{ number_format($stats['period_sales'], 2) }}</span>
        </div>

        <div class="stk-metric stk-metric--danger">
            <span class="stk-metric__icon"><i class="feather icon-rotate-ccw"></i></span>
            <span class="stk-metric__label">مرتجعات الفترة</span>
            <span class="stk-metric__value">{{ number_format($stats['returns'], 2) }}</span>
        </div>
    </div>

    {{-- ═══════════ الوصول السريع ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-grid"></i> شاشات الوحدة</h6>
            </div>

            <div class="stk-tiles">
                @foreach ($tiles as $tile)
                    @continue (! Route::has($tile['route']) || ! canAny($tile['perm']))

                    @php $locked = PlanLock::locked($tile['feature']); @endphp

                    <a href="{{ $locked ? PlanLock::url() : route($tile['route']) }}"
                       class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                       @if ($locked) title="{{ PlanLock::reason($tile['feature']) }}" @endif>
                        <span class="stk-tile__icon">
                            <i class="feather icon-{{ $locked ? 'lock' : $tile['icon'] }}"></i>
                        </span>
                        <span class="stk-tile__body">
                            <span class="stk-tile__title d-block">
                                {{ $tile['title'] }}
                                @if ($locked)
                                    <span class="stk-plan-badge">
                                        <i class="feather icon-lock"></i> خارج الباقة
                                    </span>
                                @endif
                            </span>
                            <span class="stk-tile__hint d-block">
                                {{ $locked
                                    ? 'غير مشمولة بباقتك الحالية — اضغط لعرض الاشتراك والترقية.'
                                    : $tile['hint'] }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">

        {{-- ═══════════ الجلسات المفتوحة ═══════════ --}}
        @if ($openSessions->isNotEmpty() || $recentSessions->isNotEmpty())
            <div class="col-lg-7 col-12">
                <div class="card">
                    <div class="card-body">
                        @if ($openSessions->isNotEmpty())
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-play-circle"></i> جلسات مفتوحة الآن
                                    <span class="stk-count">{{ $openSessions->count() }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الجلسة</th>
                                            <th>الكاشير</th>
                                            <th class="text-center">المبيعات</th>
                                            <th class="text-center">منذ</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($openSessions as $session)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('pos.sessions.show', $session->id) }}">
                                                        {{ $session->session_number }}
                                                    </a>
                                                    <span class="stk-media__sub d-block">
                                                        {{ $session->device->device_name ?? 'بلا جهاز' }}
                                                    </span>
                                                </td>
                                                <td>{{ $session->user->name ?? '—' }}</td>
                                                <td class="text-center">
                                                    <span class="stk-num">
                                                        {{ number_format((float) $session->total_sales, 2) }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    {{ $session->started_at?->diffForHumans(null, true) ?? '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($recentSessions->isNotEmpty())
                            <div class="stk-table-head {{ $openSessions->isNotEmpty() ? 'mt-2' : '' }}">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-check-square"></i> آخر ما أُقفل
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الجلسة</th>
                                            <th>الكاشير</th>
                                            <th class="text-center">الفرق</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($recentSessions as $session)
                                            @php
                                                [$cls, $label] = $states[$session->status]
                                                    ?? ['stk-state--neutral', $session->status];

                                                $variance = (float) $session->difference;
                                            @endphp

                                            <tr>
                                                <td>
                                                    <a href="{{ route('pos.sessions.summary', $session->id) }}">
                                                        {{ $session->session_number }}
                                                    </a>
                                                    <span class="stk-media__sub d-block">
                                                        {{ $session->ended_at?->format('Y-m-d H:i') ?? '—' }}
                                                    </span>
                                                </td>

                                                <td>{{ $session->user->name ?? '—' }}</td>

                                                {{-- الفائضُ والعجزُ مسألتان مختلفتان، ودمجُهما يُخفي أيَّهما وقع --}}
                                                <td class="text-center">
                                                    @if (abs($variance) <= 0.01)
                                                        <span class="text-muted">مطابق</span>
                                                    @else
                                                        <span class="stk-move {{ $variance > 0 ? 'stk-move--success' : 'stk-move--danger' }}">
                                                            {{ $variance > 0 ? 'فائض' : 'عجز' }}
                                                            {{ number_format(abs($variance), 2) }}
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="text-center">
                                                    <span class="stk-state {{ $cls }}">{{ $label }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════ أكثر الأصناف مبيعاً ═══════════ --}}
        @if ($topProducts->isNotEmpty())
            <div class="col-lg-5 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-trending-up"></i> أكثر الأصناف مبيعاً
                            </h6>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الصنف</th>
                                        <th class="text-center">الكمية</th>
                                        <th class="text-center">القيمة</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($topProducts as $row)
                                        <tr>
                                            <td>{{ $row->name ?: 'غير محدَّد' }}</td>
                                            <td class="text-center">
                                                <span class="stk-num">{{ number_format((float) $row->qty, 2) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="stk-num">{{ number_format((float) $row->total, 2) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{--
        لا شيءَ يُعرض؟ الشاشةُ لا تُترك بيضاء.

        هذا يقع لمن يملك صلاحيةً واحدة لا تُنتج أرقاماً — وهو بالضبط من
        فتحته البوّابةُ عمداً، فلا يصحّ أن يجد فراغاً بلا تفسير.
    --}}
    @if ($openSessions->isEmpty() && $recentSessions->isEmpty() && $topProducts->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-monitor"></i></span>
                    <h6 class="stk-empty__title">لا نشاط بعد</h6>
                    <p class="stk-empty__hint">
                        لم تُفتح جلسةٌ ولم تُسجَّل فاتورة — ابدأ بفتح جلسةٍ على جهاز كاشير.
                    </p>

                    @if (canAny(['points_sale_open_sessions_own', 'points_sale_open_sessions_all']))
                        <a href="{{ route('pos.sessions.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> بدء جلسة
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

@endsection
