{{--
    جلسات نقطة البيع.

    ═══════════════ ما كان ═══════════════

    • **التعليقُ والاستكمالُ مكسوران**: الـJS ينادي
      `/pos/sessions/${id}/suspend` والمسارُ الحقيقي
      `/POS/session/${id}/suspend` — بادئةٌ مختلفة وجمعٌ مقابل مفرد، فكلُّ
      ضغطةٍ تُردّ بـ404 ثم تقول «حدث خطأ» بلا بيان. والتفاصيلُ في الملف نفسه
      تنادي البادئة الصحيحة — أي أن العنوانين كُتبا يدوياً في وقتين.

    • **`closeSessionConfirm()`** تشير إلى `/pos/sessions/${id}` — مكسورةٌ
      كذلك، ولا يناديها شيءٌ في الشاشة أصلاً.

    • **`data-toggle` و`data-dismiss`** (بوتستراب ٤) مع `$(...).modal()` عبر
      jQuery، وبقيّةُ الوحدة على `data-bs-*`.

    • **حالةُ الفراغ** تعرض «بدء جلسة جديدة» لمن لا يملك صلاحيتها.

    ═══════════════ والتصميم ═══════════════

    نظامُ `stk-` نفسُه المستعمل في سندات الصرف، وكلُّ عنوانٍ من `route()`
    فلا يكسره تغييرُ بادئةٍ في التوجيه.
--}}
@extends('pos::master')

@section('title', 'جلسات نقطة البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Models\PosSession;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;

    $planOn = SubscriptionGate::permits(PosPlanFeatures::SESSIONS);
    $limit = app(SubscriptionGate::class)->limit(PosPlanFeatures::LIMIT_SESSIONS);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    $canOpen = canAny(['points_sale_open_sessions_own', 'points_sale_open_sessions_all']);
    $canCreate = $canOpen && $planOn && ! ($limit['exhausted'] ?? false);
    $canReports = canAny(['pos_view_reports', 'pos_reports_view']);
    $reportsOn = SubscriptionGate::permits(PosPlanFeatures::REPORTS);

    $states = [
        PosSession::STATUS_ACTIVE => ['stk-state--on', 'نشطة'],
        PosSession::STATUS_CLOSED => ['stk-state--neutral', 'مغلقة'],
        PosSession::STATUS_SUSPENDED => ['stk-state--warn', 'معلَّقة'],
        PosSession::STATUS_PENDING_REVIEW => ['stk-state--warn', 'بانتظار التأكيد'],
    ];

    $hasFilters = collect(['device_id', 'shift_id', 'status', 'search', 'branch_id', 'period_id', 'currency'])
        ->contains(fn ($k) => filled(request($k)));
@endphp

<div class="content-body">

    @section('page-actions')
        @if ($canOpen)
            <a href="{{ $canCreate ? route('pos.sessions.create') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($canCreate)
                   title="{{ $planOn
                       ? 'بلغتَ حدَّ الجلسات في باقتك: '.(int) $limit['used'].' من '.(int) $limit['allowance']
                       : PlanLock::reason(PosPlanFeatures::SESSIONS) }}"
               @endunless>
                <i class="feather icon-{{ $canCreate ? 'plus' : 'lock' }}"></i> بدء جلسة جديدة
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'جلسات نقطة البيع',
        'icon' => 'monitor',
        'sub' => 'الوردياتُ المفتوحة والمغلقة، وعُهدتُها ومبيعاتُها',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
            'الجلسات' => null,
        ],
    ])

    @unless ($planOn)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>إدارةُ الجلسات خارج باقتك الحالية.</strong>
            الجلساتُ السابقة تبقى معروضةً، ولا تُفتح جلسةٌ جديدة.
            <a href="{{ PlanLock::url() }}" class="alert-link">عرض الاشتراك والترقية</a>
        </div>
    @endunless

    {{-- ═══════════ الإحصائيات ═══════════ --}}
    @isset($stats)
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-monitor"></i></span>
                <span class="stk-metric__label">إجمالي الجلسات</span>
                <span class="stk-metric__value">{{ $stats['total_sessions'] ?? 0 }}</span>
            </div>

            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي المبيعات</span>
                <span class="stk-metric__value">{{ number_format($stats['total_sales'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-play-circle"></i></span>
                <span class="stk-metric__label">الجلسات النشطة</span>
                <span class="stk-metric__value">{{ $stats['active_sessions'] ?? 0 }}</span>
            </div>

            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">المعاملات</span>
                <span class="stk-metric__value">{{ number_format($stats['total_transactions'] ?? 0) }}</span>
            </div>
        </div>
    @endisset

    {{-- ═══════════ الفلاتر ═══════════ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                @if ($hasFilters)
                    <a href="{{ route('pos.sessions.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('pos.sessions.index') }}" class="form">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="search">رقم الجلسة</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   value="{{ request('search') }}" placeholder="رقم الجلسة…">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="device_id">الجهاز</label>
                            <select id="device_id" name="device_id" class="form-control select2">
                                <option value="">كل الأجهزة</option>
                                @foreach ($devices ?? [] as $device)
                                    <option value="{{ $device->id }}" @selected(request('device_id') == $device->id)>
                                        {{ $device->device_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="shift_id">الوردية</label>
                            <select id="shift_id" name="shift_id" class="form-control select2">
                                <option value="">كل الورديات</option>
                                @foreach ($shifts ?? [] as $shift)
                                    <option value="{{ $shift->id }}" @selected(request('shift_id') == $shift->id)>
                                        {{ $shift->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">كل الحالات</option>
                                @foreach ($states as $key => [$cls, $label])
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{--
                        الفرعُ والفترةُ والعملة: يمرّرها المتحكّم ويُقيّد بها
                        الاستعلام، ولم تكن الشاشةُ تعرض منها شيئاً — تُفلتَر
                        النتائجُ بما لا يراه المستخدم ولا يستطيع تغييره.
                    --}}
                    @if (($branches ?? collect())->count() > 1)
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="branch_id">الفرع</label>
                                <select id="branch_id" name="branch_id" class="form-control select2">
                                    <option value="">كل الفروع</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    @include('partials.accounting-period-filter', [
                        'colClass' => 'col-md-3 col-sm-6',
                        'period' => $period ?? [],
                    ])

                    @if (($currencies ?? collect())->count() > 1)
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="currency">العملة</label>
                                <select id="currency" name="currency" class="form-control">
                                    <option value="">كل العملات</option>
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->code }}"
                                            @selected(($selectedCurrency ?? null) === $currency->code)>
                                            {{ $currency->name ?? $currency->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>

                @permission('pos.sessions.export')
                    <button type="submit" name="export" value="1" class="btn btn-outline-secondary">
                        <i class="feather icon-download"></i> تصدير
                    </button>
                @endpermission
            </form>
        </div>
    </div>

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if ($sessions->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الجلسات
                        <span class="stk-count">{{ $sessions->total() }}</span>
                        @if ($limitBadge)
                            <span class="stk-plan-badge {{ $limitBadge['full'] ? 'stk-plan-badge--full' : 'stk-plan-badge--warn' }}"
                                  title="{{ $limitBadge['title'] }}">{{ $limitBadge['text'] }}</span>
                        @endif
                    </h6>

                    @include('stock::components.per-page', [
                        'id' => 'perPage',
                        'current' => request('per_page', 15),
                        'onchange' => 'const u = new URL(location.href); u.searchParams.set("per_page", this.value); u.searchParams.delete("page"); location.href = u;',
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الجلسة</th>
                                <th>الوردية والموظف</th>
                                <th class="text-center">المبيعات</th>
                                <th>الفتح والإقفال</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($sessions as $session)
                                @php
                                    [$stateClass, $stateLabel] = $states[$session->status]
                                        ?? ['stk-state--neutral', $session->status];

                                    /*
                                        صلاحياتُ الإجراءات تُفحص لكل جلسة على حدة:
                                        `..._own` لجلستي، و`..._all` لجلسات غيري —
                                        وهو نفسُ ما يفحصه المتحكّم.
                                    */
                                    $isOwn = (int) $session->user_id === (int) auth()->id();

                                    $mayClose = $isOwn
                                        ? hasPermission('points_sale_close_sessions_own')
                                        : hasPermission('points_sale_close_sessions_all');
                                @endphp

                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb"><i class="feather icon-monitor"></i></span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">{{ $session->session_number }}</span>
                                                <span class="stk-media__sub">
                                                    {{ $session->device->device_name ?? 'بلا جهاز' }}
                                                </span>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        {{ $session->shift->name ?? '—' }}
                                        <span class="stk-media__sub d-block">{{ $session->user->name ?? '—' }}</span>
                                    </td>

                                    <td class="text-center">
                                        <strong class="stk-num">{{ number_format((float) $session->total_sales, 2) }}</strong>
                                        <span class="stk-media__sub d-block">
                                            {{ $session->total_transactions }} معاملة
                                        </span>
                                    </td>

                                    <td>
                                        <span class="stk-num">{{ $session->started_at?->format('Y-m-d H:i') ?? '—' }}</span>
                                        <span class="stk-media__sub d-block">
                                            @if ($session->ended_at)
                                                أُقفلت {{ $session->ended_at->format('Y-m-d H:i') }}
                                            @else
                                                ما زالت مفتوحة
                                            @endif
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                                    </td>

                                    <td class="text-center">
                                        <div class="dropdown stk-actions">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    aria-label="إجراءات">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($session->status === PosSession::STATUS_ACTIVE)
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.sessions.show', $session->id) }}">
                                                        <i class="feather icon-eye"></i> عرض الجلسة
                                                    </a>

                                                    @permission('pos.sell')
                                                        <a class="dropdown-item" href="{{ route('POS.sales_start.index') }}">
                                                            <i class="feather icon-shopping-cart"></i> نقطة البيع
                                                        </a>
                                                    @endpermission

                                                    @if ($canReports && $reportsOn)
                                                        <a class="dropdown-item" target="_blank" rel="noopener"
                                                           href="{{ route('pos.reports.z', $session->id) }}">
                                                            <i class="feather icon-file-text"></i> تقرير X
                                                        </a>
                                                    @endif

                                                    @if ($mayClose || hasPermission('pos.sessions.suspend'))
                                                        <div class="dropdown-divider"></div>
                                                    @endif

                                                    @permission('pos.sessions.suspend')
                                                        <button type="submit" form="suspend-{{ $session->id }}"
                                                                class="dropdown-item"
                                                                onclick="return confirm('تعليق الجلسة {{ $session->session_number }}؟');">
                                                            <i class="feather icon-pause"></i> تعليق
                                                        </button>
                                                    @endpermission

                                                    @if ($mayClose)
                                                        <a class="dropdown-item text-danger"
                                                           href="{{ route('pos.sessions.close-form', $session->id) }}">
                                                            <i class="feather icon-stop-circle"></i> إقفال
                                                        </a>
                                                    @endif

                                                @elseif ($session->status === PosSession::STATUS_PENDING_REVIEW)
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.sessions.summary', $session->id) }}">
                                                        <i class="feather icon-file-text"></i> عرض الملخّص
                                                    </a>

                                                    @if ($canReports && $reportsOn)
                                                        <a class="dropdown-item" target="_blank" rel="noopener"
                                                           href="{{ route('pos.reports.z', $session->id) }}">
                                                            <i class="feather icon-printer"></i> تقرير Z
                                                        </a>
                                                    @endif

                                                    {{-- التأكيدُ هو ما تنتظره هذه الجلسة --}}
                                                    @if ($canConfirm)
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-success"
                                                           href="{{ route('pos.sessions.summary', $session->id) }}">
                                                            <i class="feather icon-check-circle"></i> تأكيد الإقفال
                                                        </a>
                                                    @endif

                                                @elseif ($session->status === PosSession::STATUS_CLOSED)
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.sessions.summary', $session->id) }}">
                                                        <i class="feather icon-file-text"></i> عرض الملخّص
                                                    </a>

                                                    @if ($canReports && $reportsOn)
                                                        <a class="dropdown-item" target="_blank" rel="noopener"
                                                           href="{{ route('pos.reports.z', $session->id) }}">
                                                            <i class="feather icon-printer"></i> تقرير Z
                                                        </a>
                                                    @endif

                                                    <a class="dropdown-item" target="_blank" rel="noopener"
                                                       href="{{ route('pos.sessions.print', $session->id) }}">
                                                        <i class="feather icon-printer"></i> طباعة
                                                    </a>

                                                @elseif ($session->status === PosSession::STATUS_SUSPENDED)
                                                    @permission('pos.sessions.suspend')
                                                        <button type="submit" form="resume-{{ $session->id }}"
                                                                class="dropdown-item"
                                                                onclick="return confirm('استكمال الجلسة {{ $session->session_number }}؟');">
                                                            <i class="feather icon-play"></i> استكمال
                                                        </button>
                                                    @endpermission

                                                    @if ($mayClose)
                                                        <a class="dropdown-item text-danger"
                                                           href="{{ route('pos.sessions.close-form', $session->id) }}">
                                                            <i class="feather icon-stop-circle"></i> إقفال
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                        {{--
                                            النماذجُ خارج القائمة المنسدلة — `dropdown-menu` يُنقل في
                                            DOM عند الفتح — وعناوينُها من `route()`.

                                            وهذا هو الإصلاح: الـJS كان ينادي
                                            `/pos/sessions/${id}/suspend` والمسارُ
                                            `/POS/session/${id}/suspend`، فيُردّ بـ404 ثم تقول
                                            الشاشة «حدث خطأ» بلا بيان.
                                        --}}
                                        @permission('pos.sessions.suspend')
                                            @if ($session->status === PosSession::STATUS_ACTIVE)
                                                <form id="suspend-{{ $session->id }}" class="d-none" method="POST"
                                                      action="{{ route('pos.sessions.suspend', $session->id) }}">@csrf</form>
                                            @elseif ($session->status === PosSession::STATUS_SUSPENDED)
                                                <form id="resume-{{ $session->id }}" class="d-none" method="POST"
                                                      action="{{ route('pos.sessions.resume', $session->id) }}">@csrf</form>
                                            @endif
                                        @endpermission
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">{{ $sessions->withQueryString()->links() }}</div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-monitor"></i></span>
                    <h6 class="stk-empty__title">لا جلسات مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم تُفتح جلسةٌ بعد — والبيعُ لا يبدأ بلا جلسةٍ تحمل عهدتَه.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.sessions.index') }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @elseif ($canCreate)
                        <a href="{{ route('pos.sessions.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> بدء جلسة جديدة
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
