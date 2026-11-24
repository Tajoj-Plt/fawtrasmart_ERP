{{--
    اختيار فترة التقرير — قالبٌ واحد يخدم «أهداف المبيعات» و«أهداف التحصيلات».

    **ما كان:** ملفّان (`targets/report` و`targets/collections/report`،
    ١٨٧٢٢ بايت و١٨٧٥٢) لا يفترقان إلّا في ثلاثة أسماء مسارات ومفتاح
    إرشاد — على تخطيطٍ لا يمتّ إلى بقيّة الوحدة بصلة: ترويسةُ
    `content-header row` يدويّة، ومئةُ سطرٍ من الأنماط في `@push('styles')`
    فيها ألوانٌ مثبَّتة (`#667eea`، `#e2e8f0`، `#f8fafc`) لا تعرف «مظهر
    النظام»، و`border-right` صريح ينقلب خطأً في الإنجليزية، وثلاثةُ ملفّات
    من `cdn.jsdelivr.net` لأجل مُنتقي تاريخٍ (flatpickr) لا تستعمله شاشةٌ
    أخرى في المشروع كلّه.

    **ما صار:** لغةُ `sl-` نفسها التي تعمل بها القائمة والتفاصيل
    والإعدادات — `page-head` و`sl-stats` و`sl-filters`، وألوانُها من
    `--app-*` فتتبع «مظهر النظام»، واتجاهُها منطقيّ فينقلب مع `dir`.
    ومُنتقي التاريخ `<input type="date">` بحدَّي `min`/`max` كما في بقيّة
    تقارير المبيعات — بلا شبكةٍ ولا مكتبة.

    ما يفترق فيه النوعان يصل في `$screen` من `Modules\Sales\Support\TargetScreen`.

    المتغيّرات المتوقَّعة:
      $screen   وصفُ النوع — انظر TargetScreen::config()
      $target   الهدف
--}}
@php
    $r = $screen['routes'];

    $money = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
    // التاريخ بتنسيق اللغة النشطة كما في بقيّة شاشات الأهداف
    $dateText = fn ($v) => $v ? \App\Support\LanguageSettings::formatDate($v) : '—';

    // لا حدَّ أدنى من تاريخ الإنشاء (أُزيل 2026-09-26): هدفٌ أُنشئ في آخر
    // الشهر يُقاس على الشهر كلّه. تاريخُ الإنشاء يبقى لزرّ «منذ إنشاء الهدف»
    $createdAt = $target->created_at?->format('Y-m-d');
    $today = now()->format('Y-m-d');
    $monthStart = now()->startOfMonth()->format('Y-m-d');

    /* هدفٌ له مدّة: التقريرُ يفتح عليها ولا يخرج عنها — فالتسويةُ من هذا
       التقرير تُردّ عن أيّ فترةٍ خارج مدّة الهدف. */
    $periodStart = $target->hasPeriod() ? $target->start_date->format('Y-m-d') : null;
    $periodEnd = $target->hasPeriod() ? $target->end_date->format('Y-m-d') : null;
    $defaultFrom = $periodStart ?: $monthStart;
    $defaultTo = $periodEnd ? min($periodEnd, max($today, $periodStart)) : $today;

    $tiers = $target->commissionTiers ? $target->commissionTiers->sortBy('achievement_percentage') : collect();
    $settlements = $target->settlements()->orderBy('from_date', 'desc')->limit(5)->get();

    $isActive = $target->status === 'active';
    $employeeCount = $target->achievements->count();
@endphp

<div class="content-body sl-scope">

    {{-- يُعرَّف قبل الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
    @section('page-actions')
        <a href="{{ route($r['show'], $target->id) }}" class="btn btn-outline-primary">
            <i class="feather icon-eye"></i> {{ __('sales_settings.ui.view') }}
        </a>

        <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
            {{ __('sales_targets.actions.back_to_list') }}
        </a>
    @endsection

    @include('sales::components.page-head', [
        'title' => __('sales_targets.actions.report'),
        'icon' => 'bar-chart-2',
        'sub' => $target->name,
        'crumbs' => [
            __('sales_settings.ui.home') => route('dashboard'),
            $screen['title'] => route($r['index']),
            $target->name => route($r['show'], $target->id),
            __('sales_targets.actions.report') => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('sales::components.form-errors')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('sales::components.guide', [
        'key' => $screen['guide'].'.report',
        'title' => __('sales_targets.guide.report.title'),
        'lead' => __('sales_targets.guide.report.lead'),
        'open' => false,
        'blocks' => [
            [
                __('sales_targets.guide.report.a'),
                __('sales_targets.guide.report.b'),
                __('sales_targets.guide.report.c'),
            ],
        ],
    ])

    {{-- التقرير يُقرأ بعملة النظام، ويُحتسب بالطريقة المضبوطة في إعدادات
         الأهداف، وتردّه الفترةُ المحاسبية عند التسوية — فتُذكر مصادرُه --}}
    @include('sales::components.system-context', [
        'only' => ['currency', 'accounts', 'period', 'language'],
        'hint' => $screen['achievement_source'],
    ])

    {{-- ══════════ ملخّص الهدف ══════════ --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-{{ $screen['icon'] }}"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($target->target_value) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.report.target_value') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-users"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value">{{ \App\Support\CurrencySettings::number($employeeCount, 0) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.report.employees') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-calendar"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.15rem">{{ $dateText($target->created_at) }}</p>
                <p class="sl-stat__label">{{ __('sales_targets.report.created') }}</p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $isActive ? 'success' : 'danger' }}">
                <i class="feather icon-{{ $isActive ? 'check-circle' : 'pause-circle' }}"></i>
            </div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.15rem">
                    <span class="sl-state {{ $isActive ? 'sl-state--on' : 'sl-state--off' }}">
                        {{ $isActive ? __('sales_targets.report.active') : __('sales_targets.report.inactive') }}
                    </span>
                </p>
                <p class="sl-stat__label">{{ __('sales_targets.report.status') }}</p>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- ══════════ فترة التقرير ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card sl-filters">
                <div class="card-body">
                    <div class="sl-filters__head">
                        <h6 class="sl-filters__title">
                            <i class="feather icon-calendar"></i> {{ __('sales_targets.report.select_period') }}
                        </h6>
                    </div>

                    <form action="{{ route($r['generate'], $target->id) }}" method="POST" id="targetReportForm">
                        @csrf

                        @if ($periodStart)
                            <div class="alert alert-info">
                                <i class="feather icon-calendar"></i>
                                {{ __('sales_targets.duration.report_note', ['from' => $dateText($target->start_date), 'to' => $dateText($target->end_date)]) }}
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="from_date">
                                        {{ __('sales_targets.report.from_date') }} <span class="sl-req">*</span>
                                    </label>

                                    <input type="date" name="from_date" id="from_date"
                                           class="form-control @error('from_date') is-invalid @enderror"
                                           value="{{ old('from_date', $defaultFrom) }}"
                                           @if ($periodStart) min="{{ $periodStart }}" @endif
                                           max="{{ $periodEnd ?: $today }}" required>

                                    @error('from_date')
                                        <span class="invalid-feedback d-block">{{ $message }}</span>
                                    @enderror

                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="to_date">
                                        {{ __('sales_targets.report.to_date') }} <span class="sl-req">*</span>
                                    </label>

                                    <input type="date" name="to_date" id="to_date"
                                           class="form-control @error('to_date') is-invalid @enderror"
                                           value="{{ old('to_date', $defaultTo) }}"
                                           @if ($periodEnd) max="{{ $periodEnd }}" @endif
                                           required>

                                    @error('to_date')
                                        <span class="invalid-feedback d-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label for="employee_id">{{ __('sales_targets.report.filter_employee') }}</label>

                                    <select name="employee_id" id="employee_id" class="form-control select2">
                                        <option value="">{{ __('sales_targets.report.all_employees') }}</option>
                                        @foreach ($target->achievements as $achievement)
                                            <option value="{{ $achievement->employee_id }}"
                                                @selected(old('employee_id') == $achievement->employee_id)>
                                                {{ $achievement->employee->name ?? '—' }}
                                                @if ($achievement->treasury)
                                                    — {{ $achievement->treasury->name }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="sl-filters__foot">
                            <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                                {{ __('sales_targets.report.back') }}
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-bar-chart-2"></i> {{ __('sales_targets.report.generate') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ══════════ التسويات السابقة ══════════
                 تُعرَض قبل اختيار الفترة لا بعده: فترةٌ سُوّيت من قبل تُردّ
                 عند محاولة تسويتها ثانيةً، فمعرفتُها تسبق الاختيار --}}
            @if ($settlements->isNotEmpty())
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-clock"></i> {{ __('sales_targets.report.previous_settlements') }}
                        </h6>
                        <span class="sl-count">{{ \App\Support\CurrencySettings::number($settlements->count(), 0) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_targets.report.from_date') }}</th>
                                    <th>{{ __('sales_targets.report.to_date') }}</th>
                                    <th>{{ __('sales_targets.report.settlement') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($settlements as $settlement)
                                    <tr>
                                        <td class="sl-num">{{ $dateText($settlement->from_date) }}</td>
                                        <td class="sl-num">{{ $dateText($settlement->to_date) }}</td>
                                        <td>{{ __('sales_targets.report.settled_by', ['name' => $settlement->settler->name ?? '—']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════ فترات سريعة ومستويات العمولة ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-zap"></i> {{ __('sales_targets.report.quick_periods') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="sl-tags">
                        @if ($periodStart)
                            <button type="button" class="btn btn-primary btn-sm" data-quick-period="target_period">
                                {{ __('sales_targets.duration.whole') }}
                            </button>
                        @endif
                        @foreach (['today', 'week', 'month', 'quarter', 'year', 'since_creation'] as $period)
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-quick-period="{{ $period }}">
                                {{ __('sales_targets.report.quick.'.$period) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($tiers->isNotEmpty())
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-layers"></i> {{ __('sales_targets.report.tiers_title') }}
                        </h6>
                        <span class="sl-count">{{ \App\Support\CurrencySettings::number($tiers->count(), 0) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_targets.report.tier_achievement') }}</th>
                                    <th class="text-center">{{ __('sales_targets.report.tier_commission') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tiers as $tier)
                                    <tr>
                                        <td class="sl-num">
                                            {{ \App\Support\CurrencySettings::number($tier->achievement_percentage, 1) }}%
                                        </td>
                                        <td class="text-center">
                                            <span class="sl-state sl-state--neutral sl-num">
                                                {{ \App\Support\CurrencySettings::number($tier->commission_percentage, 2) }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            // `select2` — نفس تهيئة بقيّة شاشات المبيعات
            if ($.fn.select2) {
                $('#employee_id').select2({
                    dir: '{{ ($languageIsRtl ?? true) ? 'rtl' : 'ltr' }}',
                    width: '100%',
                });
            }

            const created = @json($createdAt);
            const periodStart = @json($periodStart) ? new Date(@json($periodStart) + 'T00:00:00') : null;
            const periodEnd = @json($periodEnd) ? new Date(@json($periodEnd) + 'T00:00:00') : null;
            const from = document.getElementById('from_date');
            const to = document.getElementById('to_date');

            const stamp = (d) => d.getFullYear()
                + '-' + String(d.getMonth() + 1).padStart(2, '0')
                + '-' + String(d.getDate()).padStart(2, '0');

            // «إلى» لا يسبق «من»: الحدُّ يتحرّك مع الاختيار بدل أن يُردّ
            // الطلبُ بعد إرساله
            from.addEventListener('change', function () {
                if (from.value) to.min = from.value;
                if (to.value && to.value < from.value) to.value = from.value;
            });

            document.querySelectorAll('[data-quick-period]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const today = new Date();
                    const birth = new Date(created);
                    let start, end;

                    switch (button.dataset.quickPeriod) {
                        case 'today':
                            start = end = today;
                            break;
                        case 'week':
                            start = new Date(today);
                            start.setDate(today.getDate() - today.getDay());
                            end = new Date(start);
                            end.setDate(start.getDate() + 6);
                            break;
                        case 'month':
                            start = new Date(today.getFullYear(), today.getMonth(), 1);
                            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                            break;
                        case 'quarter':
                            const q = Math.floor(today.getMonth() / 3);
                            start = new Date(today.getFullYear(), q * 3, 1);
                            end = new Date(today.getFullYear(), q * 3 + 3, 0);
                            break;
                        case 'year':
                            start = new Date(today.getFullYear(), 0, 1);
                            end = new Date(today.getFullYear(), 11, 31);
                            break;
                        case 'since_creation':
                            start = birth;
                            end = today;
                            break;
                        case 'target_period':
                            start = periodStart;
                            end = periodEnd;
                            break;
                    }

                    // لا تقرير بعد اليوم — وقبل ميلاد الهدف مسموح: الشهرُ يُقاس كلُّه
                    if (end > today) end = today;

                    // مدّةُ الهدف تقصّ الفترةَ السريعة: «هذه السنة» لهدفِ ستّة
                    // أشهر هي أشهرُه الستّة
                    if (periodStart && start < periodStart) start = periodStart;
                    if (periodEnd && end > periodEnd) end = periodEnd;
                    if (end < start) end = start;

                    from.value = stamp(start);
                    to.min = from.value;
                    to.value = stamp(end);
                });
            });
        });
    </script>
@endpush
