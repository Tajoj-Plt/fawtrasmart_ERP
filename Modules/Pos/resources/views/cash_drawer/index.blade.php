{{--
    شاشةُ الدرج النقدي — لم تكن موجودة.

    الجلسةُ كانت تُفتح برصيدٍ افتتاحي وتُقفل برصيدٍ فعلي، وبينهما لا سبيل
    لإخراج ريالٍ أو إدخاله. فكانت الفكّةُ والسحبُ والترحيلُ إلى الخزينة تقع
    خارج النظام ثم تظهر فرقاً في الإقفال لا تفسير له.

    ═══════════════ والتصميم ═══════════════

    كانت على ترميزها الخاصّ: `content-header row` مكتوبةً بيدها، وبطاقاتُ
    أرقامٍ بـ`bg-light-success` لا تتبع سمة النظام، وجدولٌ بلا رأسٍ ولا
    عدّادٍ ولا حالةِ فراغ — تقول «لا توجد حركات» في خليّةٍ رماديّة.

    صارت على نظام `stk-` نفسِه المستعمل في سندات الصرف والمخزون: الترويسةُ
    مكوّنٌ واحد، والأرقامُ `stk-metric`، والجدولُ برأسٍ وعدّادٍ ومُنتقي
    صفحات، والفراغُ `stk-empty` يقول ما العمل لا أنّ لا شيء.
--}}
@extends('pos::master')

@section('title', 'الدرج النقدي')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;
    use Modules\Pos\Models\PosCashMovement;

    /*
        ═══════════ الطبقتان لا تختلطان ═══════════

        • **صلاحيةُ النظام** تقول *من* يفعل — وتُخفي ما ليس له.
        • **ميزةُ الباقة** تقول *ماذا اشترت المنشأة* — وتُقفل ولا تُخفي،
          لأن إخفاءها يجعل المنشأة لا تعرف أنها موجودة فلا تسأل عنها.

        وحدُّ الاستخدام لا يفعل أيّاً منهما: شارةٌ فقط، لأن من بلغ حدَّه
        يحتاج الشاشة أكثرَ من غيره — ليراجع ما سجَّل قبل أن يشتري إضافة.
    */
    $planOn = SubscriptionGate::permits(PosPlanFeatures::CASH_DRAWER);
    $gate = app(SubscriptionGate::class);
    $limit = $gate->limit(PosPlanFeatures::LIMIT_CASH_MOVEMENTS);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    /*
        ═══════════ أنواعُ الحركة تتبع صلاحيةَ كل نوع ═══════════

        الشاشةُ تُفتح بـ`pos.cash_drawer.view`، والتسجيلُ يطلب واحدةً من ثلاث.
        فكان النموذجُ يُعرض لكل من يفتح الشاشة ولو لم يملك أيّاً منها، وقائمةُ
        الأنواع تعرض الثلاثة لمن لا يملك إلا واحداً — فيسجّل ما لا يُقبل
        ويُردّ بـ403 بعد أن كتب المبلغ والسبب.

        والتمييزُ مقصود: من يُدخل الفكّة ليس بالضرورة من يُخرج المصروف
        النثري، ولا من يُرحّل النقد إلى الخزينة.
    */
    $drawerTypes = array_filter(
        $types,
        fn ($key) => $key !== 'count' && hasPermission('pos.cash_drawer.'.$key),
        ARRAY_FILTER_USE_KEY
    );

    $canRecord = $planOn && $activeSession && $drawerTypes && ! ($limit['exhausted'] ?? false);

    $states = [
        PosCashMovement::STATUS_APPROVED => ['stk-state--on', 'معتمدة'],
        PosCashMovement::STATUS_PENDING => ['stk-state--warn', 'بانتظار الاعتماد'],
        PosCashMovement::STATUS_REJECTED => ['stk-state--off', 'مرفوضة'],
    ];

    $icons = ['pay_in' => 'log-in', 'pay_out' => 'log-out', 'drop' => 'archive', 'count' => 'hash'];

    $hasFilters = collect(['type', 'status', 'branch_id', 'date_from', 'date_to', 'currency', 'period_id'])
        ->contains(fn ($k) => filled(request($k)));
@endphp

<div class="content-body">

    @section('page-actions')
        @if ($activeSession)
            <a href="{{ route('pos.sessions.show', $activeSession->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-monitor"></i> الجلسة {{ $activeSession->session_number }}
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'الدرج النقدي',
        'icon' => 'dollar-sign',
        'sub' => 'الإيداعات والسحوبات والترحيل إلى الخزينة، وقيودُها المحاسبية',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
            'الدرج النقدي' => null,
        ],
    ])

    {{--
        الميزةُ خارج الباقة: الشاشةُ تبقى ظاهرةً بأرقامها التاريخية، ويُمنع
        التسجيلُ وحده. فمن سجّل حركاتٍ ثم نزل إلى باقةٍ أدنى لا تُحجب عنه
        دفاترُه — تُحجب الكتابةُ لا القراءة.
    --}}
    @unless ($planOn)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>الدرج النقدي خارج باقتك الحالية.</strong>
            الحركاتُ المسجَّلة تبقى معروضةً هنا، ولا يُقبل تسجيلُ جديد.
            <a href="{{ \App\Support\PlanLock::url() }}" class="alert-link">عرض الاشتراك والترقية</a>
        </div>
    @endunless

    {{-- ═══════════ الإجماليات ═══════════ --}}
    <div class="stk-metrics">
        @foreach ([
            ['label' => 'إيداعات', 'key' => 'pay_in', 'icon' => 'log-in', 'mod' => 'stk-metric--success'],
            ['label' => 'سحوبات', 'key' => 'pay_out', 'icon' => 'log-out', 'mod' => 'stk-metric--danger'],
            ['label' => 'ترحيل للخزينة', 'key' => 'drop', 'icon' => 'archive', 'mod' => 'stk-metric--warning'],
            ['label' => 'الصافي في الدرج', 'key' => 'net', 'icon' => 'dollar-sign', 'mod' => ''],
        ] as $card)
            <div class="stk-metric {{ $card['mod'] }}">
                <span class="stk-metric__icon"><i class="feather icon-{{ $card['icon'] }}"></i></span>
                <span class="stk-metric__label">{{ $card['label'] }}</span>
                <span class="stk-metric__value">{{ number_format((float) $totals[$card['key']], 2) }}</span>
            </div>
        @endforeach
    </div>

    @if ($totals['pending'] > 0 && $canApprove)
        <div class="alert alert-warning">
            <i class="feather icon-clock"></i>
            <strong>{{ $totals['pending'] }}</strong> حركة بانتظار اعتمادك —
            ولا تدخل الرصيدَ المتوقَّع للجلسة حتى تُعتمد.
        </div>
    @endif

    {{-- ═══════════ تسجيل حركة ═══════════ --}}
    @if ($drawerTypes)
        <div class="card">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title">
                        <i class="feather icon-plus-circle"></i> تسجيل حركة
                        @if ($activeSession)
                            <span class="stk-count">{{ $activeSession->session_number }}</span>
                        @endif
                        @if ($limitBadge)
                            <span class="stk-plan-badge {{ $limitBadge['full'] ? 'stk-plan-badge--full' : 'stk-plan-badge--warn' }}"
                                  title="{{ $limitBadge['title'] }}">{{ $limitBadge['text'] }}</span>
                        @endif
                    </h6>

                        {{--
                            ═══════════ أيُّ درجٍ يُدار ═══════════

                            كان النموذجُ مربوطاً بجلسة المستخدم النشطة وحدها.
                            فمشرفٌ يدير خمسةَ كاشيرين ولا جلسةَ له لا يرى
                            النموذجَ إطلاقاً، ولا يُودع فكّةً في درجِ أحدهم.
                            وكذلك مديرٌ ليس موظّفاً أصلاً.

                            والقائمةُ تظهر حين يبلغ أكثرَ من جلسة — وصاحبُ
                            الجلسة الواحدة لا يرى منتقياً لا معنى له.
                        --}}
                        @if ($openSessions->count() > 1)
                            <form method="GET" class="stk-session-pick">
                                @foreach (request()->except(['session_id', 'page']) as $k => $v)
                                    @if (! is_array($v))
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach

                                <label for="drawerSession">الدرج:</label>

                                <select id="drawerSession" name="session_id" class="form-control"
                                        onchange="this.form.submit()">
                                    @foreach ($openSessions as $s)
                                        <option value="{{ $s->id }}"
                                            @selected($activeSession && (int) $activeSession->id === (int) $s->id)>
                                            {{ $s->session_number }} — {{ $s->user->name ?? '—' }}@if ($s->status === 'suspended') (معلَّقة)@endif
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                </div>

                @if ($canRecord)
                    <form action="{{ route('pos.cash_drawer.store') }}" method="POST" class="form">
                        @csrf
                        <input type="hidden" name="session_id" value="{{ $activeSession->id }}">

                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="type" class="stk-req">نوع الحركة</label>
                                    <select name="type" id="type" class="form-control" required>
                                        @foreach ($drawerTypes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="amount" class="stk-req">المبلغ</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="amount"
                                           class="form-control" required>
                                </div>
                            </div>

                            {{--
                                ═══════════ الحسابُ المقابل ═══════════

                                لم يكن له مُنتقٍ في الشاشة إطلاقاً، والحقلُ
                                `nullable` في التحقّق. فتُنشأ حركةُ سحبٍ بلا
                                حساب، وتبقى «بانتظار الاعتماد»، **ويستحيل
                                اعتمادُها**: `postJournal()` ترفض قيدَ سحبٍ
                                بلا حسابٍ مقابل، فتُردّ كلُّ محاولةٍ بالرسالة
                                نفسِها وتبقى الحركةُ عالقة.

                                والقائمةُ تتبدّل بنوع الحركة: السحبُ طرفُه
                                حسابُ مصروف، والترحيلُ والإيداعُ طرفُهما
                                خزينة (ولهما افتراضٌ فيُترك اختيارياً).
                            --}}
                            <div class="col-md-4 col-sm-6" id="counterpartWrap">
                                <div class="form-group">
                                    <label for="counterpart_account_id" id="counterpartLabel">الحساب المقابل</label>

                                    <select name="counterpart_account_id" id="counterpart_account_id"
                                            class="form-control select2">
                                        <option value="">—</option>

                                        <optgroup label="حسابات المصروف" id="grpExpense">
                                            @foreach ($expenseAccounts as $acc)
                                                <option value="{{ $acc->id }}" data-for="pay_out">
                                                    {{ $acc->code }} — {{ $acc->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>

                                        <optgroup label="الخزائن" id="grpTreasury">
                                            @foreach ($treasuries as $acc)
                                                <option value="{{ $acc->id }}" data-for="treasury">
                                                    {{ $acc->code }} — {{ $acc->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    </select>

                                    <p class="stk-help mb-0" id="counterpartHint"></p>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="reason">السبب</label>
                                    <input type="text" name="reason" id="reason" class="form-control"
                                           maxlength="1000" placeholder="فكّة، مصروف نثري، ترحيل…">
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label class="d-none d-md-block">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="feather icon-check"></i> تسجيل
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="stk-help mb-0">
                            السحبُ والترحيلُ يحتاجان اعتماد مشرف قبل أن يخرج المال ويُقيَّد محاسبياً.
                        </p>
                    </form>
                @else
                    {{--
                        سببُ التعطيل يُقال صريحاً.

                        كانت الشاشة تعرض «لا توجد جلسة نشطة» عن كل حالة —
                        فمن مُنع لبلوغ حدّ الباقة يبحث عن جلسةٍ ليست العلّة.
                    --}}
                    <p class="stk-help {{ $planOn ? '' : 'stk-help--locked' }} mb-0">
                        @if (! $planOn)
                            <i class="feather icon-lock"></i> التسجيلُ خارج باقتك الحالية.
                        @elseif ($limit['exhausted'] ?? false)
                            <i class="feather icon-alert-circle"></i>
                            بلغتَ حدَّ «{{ $limit['label'] }}» في باقتك: {{ (int) $limit['used'] }}
                            من {{ (int) $limit['allowance'] }}.
                        @else
                            <i class="feather icon-info"></i>
                            لا توجد جلسة نشطة — افتح جلسةً لتسجيل حركات الدرج.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════ الفلاتر ═══════════ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                @if ($hasFilters)
                    <a href="{{ route('pos.cash_drawer.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" class="form">
                <div class="row">
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="f_type">النوع</label>
                            <select name="type" id="f_type" class="form-control">
                                <option value="">الكل</option>
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="f_status">الحالة</label>
                            <select name="status" id="f_status" class="form-control">
                                <option value="">الكل</option>
                                @foreach ($states as $key => [$cls, $label])
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f_branch">الفرع</label>
                            <select name="branch_id" id="f_branch" class="form-control select2">
                                <option value="">جميع الفروع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{--
                        الفترةُ المحاسبية — كان المتحكّم يحسبها ويمرّرها
                        (`posPeriod()` ويُقيّد بها الاستعلام) والشاشةُ لا تعرضها
                        إطلاقاً: تُفلتَر النتائجُ بفترةٍ لا يراها المستخدم ولا
                        يستطيع تغييرها، فيقرأ أرقاماً لا يعرف مداها.
                    --}}
                    @include('partials.accounting-period-filter', [
                        'colClass' => 'col-md-3 col-sm-6',
                        'period' => $period ?? [],
                    ])

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="f_from">من تاريخ</label>
                            <input type="date" name="date_from" id="f_from"
                                   value="{{ request('date_from') }}" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="f_to">إلى تاريخ</label>
                            <input type="date" name="date_to" id="f_to"
                                   value="{{ request('date_to') }}" class="form-control">
                        </div>
                    </div>

                    {{-- العملةُ كذلك: يُقيَّد بها الاستعلام ولا حقل لها --}}
                    @if (($currencies ?? collect())->count() > 1)
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="f_currency">العملة</label>
                                <select name="currency" id="f_currency" class="form-control">
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

                    <div class="col-md-1 col-sm-6">
                        <div class="form-group">
                            <label class="d-none d-md-block">&nbsp;</label>
                            <button class="btn btn-primary w-100" aria-label="تصفية">
                                <i class="feather icon-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if ($movements->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> حركات الدرج
                        <span class="stk-count">{{ $movements->total() }}</span>
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
                                <th>الحركة</th>
                                <th class="text-center">المبلغ</th>
                                <th>الجلسة والفرع</th>
                                <th>السبب</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center">القيد</th>
                                @if ($canApprove)
                                    <th class="text-center" style="width:90px">إجراءات</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($movements as $movement)
                                @php
                                    [$stateClass, $stateLabel] = $states[$movement->status]
                                        ?? ['stk-state--neutral', $movement->status];
                                @endphp

                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb">
                                                <i class="feather icon-{{ $icons[$movement->type] ?? 'circle' }}"></i>
                                            </span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ PosCashMovement::typeLabel($movement->type) }}
                                                </span>
                                                <span class="stk-media__sub">
                                                    {{ $movement->reference_number }}
                                                    · {{ $movement->movement_time?->format('Y-m-d H:i') ?? '—' }}
                                                </span>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <strong class="stk-num">{{ number_format((float) $movement->amount, 2) }}</strong>
                                        <span class="stk-media__sub d-block">{{ $movement->currency }}</span>
                                    </td>

                                    <td>
                                        {{ $movement->session->session_number ?? '—' }}
                                        <span class="stk-media__sub d-block">{{ $movement->branch->name ?? '—' }}</span>
                                    </td>

                                    <td>{{ $movement->reason ?: '—' }}</td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                                        @if ($movement->status !== PosCashMovement::STATUS_PENDING && $movement->approver)
                                            <span class="stk-media__sub d-block">{{ $movement->approver->name }}</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @if ($movement->journal_entry_id)
                                            <span class="stk-state stk-state--on">مُقيَّدة</span>
                                        @else
                                            <span class="stk-state stk-state--neutral">—</span>
                                        @endif
                                    </td>

                                    @if ($canApprove)
                                        <td class="text-center">
                                            @if ($movement->status === PosCashMovement::STATUS_PENDING)
                                                <div class="dropdown stk-actions">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            data-bs-toggle="dropdown" aria-expanded="false"
                                                            aria-label="إجراءات">
                                                        <i class="feather icon-more-vertical"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <button type="submit" form="approve-{{ $movement->id }}"
                                                                class="dropdown-item">
                                                            <i class="feather icon-check"></i> اعتماد
                                                        </button>
                                                        <div class="dropdown-divider"></div>
                                                        <button type="submit" form="reject-{{ $movement->id }}"
                                                                class="dropdown-item text-danger">
                                                            <i class="feather icon-x"></i> رفض
                                                        </button>
                                                    </div>
                                                </div>

                                                {{--
                                                    النموذجان خارج القائمة المنسدلة: `dropdown-menu`
                                                    يُنقل في DOM عند الفتح (Popper)، فنموذجٌ داخله
                                                    قد يخرج من سياقه — والزرُّ يشير إليه بـ`form=`
                                                    فيعمل أينما كان.
                                                --}}
                                                {{--
                                                    حركةُ سحبٍ بلا حسابٍ مقابل لا تُعتمد — فتُسأل
                                                    عنه هنا بدل أن تُردّ برسالةٍ لا مخرجَ منها.
                                                    وهذه الحالةُ لا تقع في الحركات الجديدة:
                                                    `store()` ترفضها عند الإنشاء.
                                                --}}
                                                <form id="approve-{{ $movement->id }}" class="d-none" method="POST"
                                                      action="{{ route('pos.cash_drawer.approve', $movement->id) }}">
                                                    @csrf

                                                    @if ($movement->type === PosCashMovement::TYPE_PAY_OUT && ! $movement->counterpart_account_id)
                                                        <select name="counterpart_account_id"
                                                                data-needs-account="{{ $movement->id }}">
                                                            @foreach ($expenseAccounts as $acc)
                                                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </form>
                                                <form id="reject-{{ $movement->id }}" class="d-none" method="POST"
                                                      action="{{ route('pos.cash_drawer.reject', $movement->id) }}">@csrf</form>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">
                    {{ $movements->withQueryString()->links() }}
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-dollar-sign"></i></span>
                    <h6 class="stk-empty__title">لا حركات درجٍ مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم تُسجَّل حركةٌ بعد — الفكّةُ والمصروفُ النثري والترحيلُ إلى الخزينة تُسجَّل هنا.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.cash_drawer.index') }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /*
        الحسابُ المقابل يتبدّل بنوع الحركة.

        السحبُ طرفُه حسابُ مصروف **ومطلوب** — بدونه لا يُعتمد ولا يُقيَّد.
        والترحيلُ والإيداعُ طرفُهما خزينة، ولهما افتراضٌ («الخزينة الرئيسية»)
        فيبقيان اختياريَّين. والعدُّ لا طرفَ له فيُخفى الحقل.
    */
    document.addEventListener('DOMContentLoaded', function () {
        var type = document.getElementById('type');
        var wrap = document.getElementById('counterpartWrap');
        var select = document.getElementById('counterpart_account_id');
        var label = document.getElementById('counterpartLabel');
        var hint = document.getElementById('counterpartHint');

        if (! type || ! select) {
            return;
        }

        var expense = document.getElementById('grpExpense');
        var treasury = document.getElementById('grpTreasury');

        function sync() {
            var v = type.value;

            wrap.style.display = v === 'count' ? 'none' : '';

            if (expense) expense.style.display = v === 'pay_out' ? '' : 'none';
            if (treasury) treasury.style.display = v === 'pay_out' ? 'none' : '';

            select.required = v === 'pay_out';
            label.classList.toggle('stk-req', v === 'pay_out');

            label.textContent = v === 'pay_out' ? 'حساب المصروف' : 'الخزينة المقابلة';

            hint.textContent = v === 'pay_out'
                ? 'مطلوب — بدونه لا يمكن اعتماد الحركة ولا ترحيل قيدها.'
                : 'اختياري — يُرحَّل إلى «الخزينة الرئيسية» إن تُرك فارغاً.';

            // اختيارٌ من المجموعة المخفيّة يُمسح
            var picked = select.selectedOptions[0];

            if (picked && picked.parentElement && picked.parentElement.style.display === 'none') {
                select.value = '';
            }
        }

        type.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
