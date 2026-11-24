@extends('sales::master')

{{-- داشبورد التبديلات.

     كانت الشاشة على تخطيطٍ خاصٍّ بها لا يشبه شاشات المبيعات: بطاقاتٌ
     بتدرّجاتٍ بنفسجية مكتوبةٍ في الوسم (`#7367f0`)، ونصوصٌ عربية في
     القالب لا تمرّ بملفّ لغة، ومبالغُ بـ`number_format(..., 2)` يليها
     «ريال سعودي» — فتقرأ المنشأةُ التي تعمل بالدرهم مبالغَها بالريال،
     وتقرأ الواجهةُ الإنجليزية عربيةً.

     صارت على لغة `sl-` نفسها التي تعمل بها «إدارة التبديلات» وبقيّة
     الوحدة: ترويسةٌ بفتات مسار، ثمّ بطاقةُ تصفية، ثمّ بطاقاتُ إحصاء،
     ثمّ الرسوم، ثمّ الجداول. والألوان من متغيّرات `--app-*` التي يبثّها
     «مظهر النظام»، فتبديل الثيم يسري عليها بلا لمس هذا الملف.

     **عقد الـDOM محفوظ**: مُعرِّفات الرسوم (`#dual-axis-trend-chart`،
     `#top-products-chart`، `#reason-donut-chart`) ومُعرِّفات نافذتَي
     الحدود وحقولِها كما كانت — السكربت في آخر الملف يعتمد عليها. --}}

@section('title', __('sales_exchanges.board.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $money = fn ($v) => CurrencySettings::format((float) $v);
        $num = fn ($v, $d = 0) => CurrencySettings::number($v, $d);
        $date = fn ($v) => $v ? LanguageSettings::formatDate($v) : '—';

        $periods = [
            '1' => __('sales_exchanges.board.period.today'),
            '7' => __('sales_exchanges.board.period.last7'),
            '30' => __('sales_exchanges.board.period.last30'),
            '90' => __('sales_exchanges.board.period.last90'),
            'custom' => __('sales_exchanges.board.period.custom'),
        ];
    @endphp

    {{-- أزرارُ المدّة عناصرُ `button` و`.sl-tab` مكتوبةٌ للروابط:
         بلا هذا تظهر بخلفيةِ المتصفّح الرمادية وحدِّه الافتراضي --}}
    <style>
        #exchange-dashboard button.sl-tab {
            background: none;
            border: 0;
            border-bottom: 3px solid transparent;
            cursor: pointer;
        }
    </style>

    <div class="content-body sl-scope" id="exchange-dashboard">

        @section('page-actions')
            <a href="{{ route('product-exchanges.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_exchanges.board.back') }}
            </a>

            @if ($isManager)
                @permission('sales.exchanges.manage_limits')
                    <button type="button" class="btn btn-outline-primary" id="btnManageLimits">
                        <i class="feather icon-users"></i> {{ __('sales_exchanges.board.limits.employees_btn') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btnManageClientLimits">
                        <i class="feather icon-user"></i> {{ __('sales_exchanges.board.limits.clients_btn') }}
                    </button>
                @endpermission
            @endif
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_exchanges.board.title'),
            'icon' => 'bar-chart-2',
            'sub' => __('sales_exchanges.board.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_exchanges.title') => route('product-exchanges.index'),
                __('sales_exchanges.board.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchanges.dashboard',
            'title' => __('sales_exchanges.guide.dashboard.title'),
            'lead' => __('sales_exchanges.guide.lead_dashboard'),
            'open' => false,
            'blocks' => [
                __('sales_exchanges.guide.limits.title') => [
                    __('sales_exchanges.guide.limits.a'),
                    __('sales_exchanges.guide.limits.b'),
                    __('sales_exchanges.guide.limits.c'),
                ],
                __('sales_exchanges.guide.settings.title') => [
                    __('sales_exchanges.guide.settings.a'),
                    __('sales_exchanges.guide.settings.b'),
                ],
            ],
        ])

        {{-- ══════════ حدُّك اليومي ══════════
             يُعرَض للموظّف المحدود وحده — من لا حدَّ عليه لا يُعرَض له
             شريطُ استهلاكٍ فارغ يُقرأ تحذيراً بلا سبب --}}
        @if ($dailyLimitInfo)
            <div class="card sl-filters mb-1">
                <div class="card-body py-1">
                    <div class="d-flex flex-wrap align-items-center" style="gap:1rem">
                        <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-alert-circle"></i></div>

                        <div>
                            <h6 class="mb-0">{{ __('sales_exchanges.board.limit.title') }}</h6>
                            <small class="sl-help">{{ __('sales_exchanges.board.limit.hint') }}</small>
                        </div>

                        <div class="flex-grow-1" style="min-width:14rem;max-width:26rem">
                            <div class="d-flex justify-content-between mb-25 sl-help">
                                <span>{{ __('sales_exchanges.board.limit.used') }}:
                                    <b class="sl-num">{{ $num($dailyLimitInfo['used']) }}</b></span>
                                <span>{{ __('sales_exchanges.board.limit.remaining') }}:
                                    <b class="sl-num">{{ $num($dailyLimitInfo['remaining']) }}</b></span>
                            </div>

                            <div class="progress" style="height:8px">
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ min(100, (float) $dailyLimitInfo['percentage']) }}%;
                                            background: var(--app-warning, #E7A33C)"
                                     aria-valuenow="{{ $dailyLimitInfo['percentage'] }}"
                                     aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="text-center">
                            <p class="sl-stat__value mb-0">{{ $num($dailyLimitInfo['limit']) }}</p>
                            <small class="sl-help">{{ __('sales_exchanges.board.limit.total') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════ نطاق التحليل ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-calendar"></i> {{ __('sales_exchanges.board.period.title') }}
                    </h6>

                    <span class="sl-count">
                        {{ __('sales_exchanges.board.period.range', [
                            'from' => $date($startDate),
                            'to' => $date($endDate),
                        ]) }}
                    </span>
                </div>

                <form id="filterForm" action="{{ route('product-exchanges.dashboard') }}" method="GET">
                    <input type="hidden" name="period" id="periodInput" value="{{ $period }}">

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-group mb-0">
                                <div class="sl-tabs">
                                    @foreach ($periods as $key => $label)
                                        <button type="button"
                                                class="sl-tab period-btn {{ (string) $period === (string) $key ? 'is-active' : '' }}"
                                                data-period="{{ $key }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @if ($isManager)
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="branch_id">{{ __('sales_exchanges.board.branch.label') }}</label>
                                    <select name="branch_id" id="branch_id" class="form-control"
                                            onchange="this.form.submit()">
                                        <option value="all">{{ __('sales_exchanges.board.branch.all') }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($selectedBranchId == $branch->id)>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- حقول المدّة المخصّصة — تظهر مع اختيارها وحده --}}
                    <div class="row {{ $period === 'custom' ? '' : 'd-none' }}" id="customInputs">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="start_date">{{ __('sales_exchanges.board.period.from') }}</label>
                                <input type="date" id="start_date" name="start_date" class="form-control"
                                       value="{{ $startDate->toDateString() }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="end_date">{{ __('sales_exchanges.board.period.to') }}</label>
                                <input type="date" id="end_date" name="end_date" class="form-control"
                                       value="{{ $endDate->toDateString() }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 d-flex align-items-end">
                            <div class="form-group w-100">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="feather icon-search"></i>
                                    {{ __('sales_exchanges.board.period.apply') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ بطاقات الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-repeat"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($stats->count) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.board.kpi.operations') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-dollar-sign"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($stats->total) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.board.kpi.value') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-package"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($totalQuantity) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.board.kpi.quantity') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-users"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($periodClientsCount) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.board.kpi.clients') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ الاتجاه الزمني ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-trending-up"></i> {{ __('sales_exchanges.board.charts.trend') }}
                </h6>
            </div>
            <div class="card-body">
                <div id="dual-axis-trend-chart"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-package"></i> {{ __('sales_exchanges.board.charts.top_products') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="top-products-chart"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-pie-chart"></i> {{ __('sales_exchanges.board.charts.reasons') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="reason-donut-chart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- أداء الموظفين --}}
            <div class="col-lg-4">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-user-check"></i> {{ __('sales_exchanges.board.tables.employees') }}
                        </h6>
                        <span class="sl-count">{{ __('sales_exchanges.board.tables.top20') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_exchanges.board.tables.employee') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.board.tables.operations') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.board.tables.quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($detailedEmployees as $emp)
                                    <tr>
                                        <td>{{ $emp->name }}</td>
                                        <td class="text-center sl-num">{{ $num($emp->count) }}</td>
                                        <td class="text-center sl-num">{{ $num($emp->total_qty) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center sl-help py-2">
                                            {{ __('sales_exchanges.board.tables.empty') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- أكثر العملاء تبديلاً --}}
            <div class="col-lg-4">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-briefcase"></i> {{ __('sales_exchanges.board.tables.clients') }}
                        </h6>
                        <span class="sl-count">{{ __('sales_exchanges.board.tables.top20') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_exchanges.board.tables.client') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.board.tables.operations') }}</th>
                                    <th class="text-center">{{ __('sales_exchanges.board.tables.quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topClients as $client)
                                    <tr>
                                        <td>{{ $client->trade_name ?: trim($client->first_name.' '.$client->last_name) }}</td>
                                        <td class="text-center sl-num">{{ $num($client->exchange_count) }}</td>
                                        <td class="text-center sl-num">{{ $num($client->total_qty) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center sl-help py-2">
                                            {{ __('sales_exchanges.board.tables.empty') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- آخر عمليات اليوم --}}
            <div class="col-lg-4">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-clock"></i> {{ __('sales_exchanges.board.tables.latest') }}
                        </h6>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_exchanges.board.tables.code') }}</th>
                                    <th>{{ __('sales_exchanges.board.tables.employee') }}</th>
                                    <th>{{ __('sales_exchanges.board.tables.client') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($todayExchanges->take(20) as $ex)
                                    <tr>
                                        <td class="sl-num">
                                            @permission('sales.exchanges.view')
                                                <a href="{{ route('product-exchanges.show', $ex->id) }}">{{ $ex->code }}</a>
                                            @else
                                                {{ $ex->code }}
                                            @endpermission
                                        </td>
                                        <td>{{ $ex->createdByUser->name ?? '—' }}</td>
                                        <td>{{ Str::limit($ex->client->trade_name ?? '—', 20) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center sl-help py-2">
                                            {{ __('sales_exchanges.board.tables.empty_today') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($isManager)
            {{-- ══════════ حدود الموظفين ══════════ --}}
            <div class="modal fade" id="limitsModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content sl-scope">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="feather icon-sliders"></i>
                                {{ __('sales_exchanges.board.limits.employees_title') }}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body p-0">
                            <p class="sl-help px-2 py-1 mb-0">
                                <i class="feather icon-info"></i>
                                {{ __('sales_exchanges.board.limits.employees_hint') }}
                            </p>

                            <div class="table-responsive" style="max-height:26rem">
                                <table class="table mb-0" id="employeesLimitsTable">
                                    <thead>
                                        <tr>
                                            <th>{{ __('sales_exchanges.board.limits.employee_name') }}</th>
                                            <th style="width:14rem">{{ __('sales_exchanges.board.limits.per_day') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody id="globalLimitRow">
                                        <tr>
                                            <td class="align-middle">
                                                <i class="feather icon-zap"></i>
                                                {{ __('sales_exchanges.board.limits.apply_all') }}
                                            </td>
                                            <td>
                                                <input type="number" id="globalLimitSetter" class="form-control" min="0"
                                                       placeholder="{{ __('sales_exchanges.board.limits.apply_all_placeholder') }}">
                                            </td>
                                        </tr>
                                    </tbody>

                                    <tbody id="employeesLimitsTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center sl-help py-2">
                                                {{ __('sales_exchanges.board.limits.loading') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('sales_exchanges.board.limits.close') }}
                            </button>
                            <button type="button" class="btn btn-primary" id="saveLimitsBtn">
                                <i class="feather icon-save"></i> {{ __('sales_exchanges.board.limits.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ حدود العملاء ══════════ --}}
            <div class="modal fade" id="clientLimitsModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content sl-scope">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="feather icon-shield"></i>
                                {{ __('sales_exchanges.board.limits.clients_title') }}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body p-0">
                            <div class="px-2 py-1">
                                <div class="row align-items-end">
                                    <div class="col-md-7">
                                        <label for="globalClientLimit" class="mb-0">
                                            {{ __('sales_exchanges.board.limits.global_client') }}
                                        </label>
                                        <small class="sl-help d-block">
                                            {{ __('sales_exchanges.board.limits.global_client_hint') }}
                                        </small>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="number" id="globalClientLimit" class="form-control" min="0"
                                               placeholder="{{ __('sales_exchanges.board.limits.no_limit') }}">
                                    </div>
                                </div>

                                <div class="form-group mt-1 mb-0">
                                    <input type="text" id="searchClientLimit" class="form-control"
                                           placeholder="{{ __('sales_exchanges.board.limits.search_client') }}">
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height:24rem">
                                <table class="table mb-0" id="clientsLimitsTable">
                                    <thead>
                                        <tr>
                                            <th>{{ __('sales_exchanges.board.limits.client_name') }}</th>
                                            <th style="width:14rem">{{ __('sales_exchanges.board.limits.client_limit') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="clientsLimitsTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center sl-help py-2">
                                                {{ __('sales_exchanges.board.limits.loading') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('sales_exchanges.board.limits.close') }}
                            </button>
                            <button type="button" class="btn btn-primary" id="saveClientLimitsBtn">
                                <i class="feather icon-save"></i> {{ __('sales_exchanges.board.limits.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    @php
        /* النصوص من ملفّ الترجمة لا مكتوبةً في السكربت. وتُجمَع في مصفوفةٍ
           هنا لا داخل `@json(...)` مباشرةً: موجّهُ بليد يقرأ وسيطَه سطراً
           واحداً، فمصفوفةٌ متعدّدة الأسطر داخله تُوقف تصريف القالب. */
        $exchangeText = [
            'value' => __('sales_exchanges.board.charts.trend_value'),
            'qty' => __('sales_exchanges.board.charts.trend_qty'),
            'products' => __('sales_exchanges.board.charts.top_products_series'),
            'reasons_total' => __('sales_exchanges.board.charts.reasons_total'),
            'piece' => __('sales_exchanges.board.piece'),
            'no_limit' => __('sales_exchanges.board.limits.no_limit'),
            'uses_global' => __('sales_exchanges.board.limits.uses_global'),
            'has_custom' => __('sales_exchanges.board.limits.has_custom'),
            'loading' => __('sales_exchanges.board.limits.loading'),
            'saving' => __('sales_exchanges.board.limits.saving'),
            'save' => __('sales_exchanges.board.limits.save'),
            'empty' => __('sales_exchanges.board.tables.empty'),
            'fetch_failed' => __('sales_exchanges.board.limits.fetch_failed'),
            'save_failed' => __('sales_exchanges.board.limits.save_failed'),
        ];
    @endphp

    <script>
        const EX_TEXT = @json($exchangeText);

        /** لونٌ من متغيّرات `--app-*` مع قيمةٍ احتياطية قبل ضبط الثيم */
        function exColor(name, fallback) {
            const value = getComputedStyle(document.documentElement).getPropertyValue(name);

            return (value || '').trim() || fallback;
        }

        $(document).ready(function () {
            const primary = exColor('--app-primary', '#1B6CA8');
            const success = exColor('--app-success', '#20A97B');
            const info = exColor('--app-info', '#2AA9CF');
            const warning = exColor('--app-warning', '#E7A33C');
            const danger = exColor('--app-danger', '#DC4C4C');

            const chartBase = { fontFamily: 'inherit', toolbar: { show: false } };

            // اختيار المدّة — «مخصّصة» تفتح الحقلين ولا تُرسل
            $('.period-btn').on('click', function () {
                const chosen = $(this).data('period');

                $('#periodInput').val(chosen);

                if (chosen === 'custom') {
                    $('#customInputs').removeClass('d-none');
                    $('.period-btn').removeClass('is-active');
                    $(this).addClass('is-active');
                } else {
                    $('#filterForm').trigger('submit');
                }
            });

            // ١) الاتجاه الزمني — قيمةٌ وكمية على محورين
            new ApexCharts(document.querySelector('#dual-axis-trend-chart'), {
                series: [
                    { name: EX_TEXT.value, type: 'area', data: @json($trendData->pluck('daily_value')) },
                    { name: EX_TEXT.qty, type: 'bar', data: @json($trendData->pluck('daily_qty')) },
                ],
                chart: Object.assign({ height: 320, type: 'line' }, chartBase),
                stroke: { width: [3, 0], curve: 'smooth' },
                plotOptions: { bar: { columnWidth: '35%', borderRadius: 4 } },
                colors: [primary, success],
                fill: {
                    type: ['gradient', 'solid'],
                    gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.45, opacityTo: 0.05 },
                },
                labels: @json($trendData->pluck('date')),
                yaxis: [
                    { title: { text: EX_TEXT.value }, labels: { formatter: (v) => Number(v).toLocaleString() } },
                    { opposite: true, title: { text: EX_TEXT.qty } },
                ],
                legend: { position: 'bottom' },
            }).render();

            // ٢) أكثر الأصناف تبديلاً
            new ApexCharts(document.querySelector('#top-products-chart'), {
                series: [{ name: EX_TEXT.products, data: @json($topProducts->pluck('total_qty')) }],
                chart: Object.assign({ height: 340, type: 'bar' }, chartBase),
                plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '55%' } },
                colors: [info],
                xaxis: { categories: @json($topProducts->pluck('name')) },
                dataLabels: { enabled: true, formatter: (v) => v + ' ' + EX_TEXT.piece },
            }).render();

            // ٣) أسباب التبديل
            new ApexCharts(document.querySelector('#reason-donut-chart'), {
                series: @json($reasonStats->pluck('count')),
                labels: @json($reasonStats->pluck('name')),
                chart: Object.assign({ height: 340, type: 'donut' }, chartBase),
                colors: [primary, success, warning, danger, info],
                legend: { position: 'bottom' },
                plotOptions: {
                    pie: { donut: { labels: { show: true, total: { show: true, label: EX_TEXT.reasons_total } } } },
                },
            }).render();

            @if ($isManager)
                /* ══════════ حدود الموظفين ══════════ */
                const $limitsModal = $('#limitsModal');
                const $limitsBody = $('#employeesLimitsTableBody');
                const $saveLimits = $('#saveLimitsBtn');
                const $globalLimit = $('#globalLimitSetter');

                $('#btnManageLimits').on('click', function () {
                    $limitsModal.modal('show');
                    $globalLimit.val('');
                    $limitsBody.html('<tr><td colspan="2" class="text-center py-2">' + EX_TEXT.loading + '</td></tr>');

                    $.get("{{ route('product-exchanges.get-limits') }}", function (response) {
                        if (!response.success) {
                            $limitsBody.html('<tr><td colspan="2" class="text-center text-danger py-2">'
                                + EX_TEXT.fetch_failed + '</td></tr>');

                            return;
                        }

                        const rows = response.employees.map(function (emp) {
                            return '<tr><td class="align-middle">' + $('<div>').text(emp.name).html() + '</td>'
                                + '<td><input type="number" min="0" class="form-control emp-limit-input" '
                                + 'data-id="' + emp.id + '" value="' + (emp.limit || '')
                                + '" placeholder="' + EX_TEXT.no_limit + '"></td></tr>';
                        });

                        $limitsBody.html(rows.join('') || '<tr><td colspan="2" class="text-center py-2">'
                            + EX_TEXT.empty + '</td></tr>');
                    });
                });

                // تعميم الحدّ على كل الصفوف
                $globalLimit.on('input', function () {
                    $('.emp-limit-input').val($(this).val());
                });

                $saveLimits.on('click', function () {
                    const limits = {};

                    $('.emp-limit-input').each(function () {
                        limits[$(this).data('id')] = $(this).val();
                    });

                    $saveLimits.prop('disabled', true).text(EX_TEXT.saving);

                    $.post("{{ route('product-exchanges.update-limits') }}", {
                        _token: "{{ csrf_token() }}",
                        limits: limits,
                    }, function (response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $limitsModal.modal('hide');
                            setTimeout(() => location.reload(), 500);
                        } else {
                            toastr.error(response.message || EX_TEXT.save_failed);
                        }
                    }).fail(function (xhr) {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || EX_TEXT.save_failed);
                    }).always(function () {
                        $saveLimits.prop('disabled', false).text(EX_TEXT.save);
                    });
                });

                /* ══════════ حدود العملاء ══════════ */
                const $clientModal = $('#clientLimitsModal');
                const $clientsBody = $('#clientsLimitsTableBody');
                const $saveClients = $('#saveClientLimitsBtn');
                const $globalClient = $('#globalClientLimit');

                $('#btnManageClientLimits').on('click', function () {
                    $clientModal.modal('show');
                    $clientsBody.html('<tr><td colspan="2" class="text-center py-2">' + EX_TEXT.loading + '</td></tr>');

                    $.get("{{ route('product-exchanges.get-client-limits') }}", function (response) {
                        if (!response.success) {
                            $clientsBody.html('<tr><td colspan="2" class="text-center text-danger py-2">'
                                + EX_TEXT.fetch_failed + '</td></tr>');

                            return;
                        }

                        $globalClient.val(response.globalLimit || '');
                        renderClientRows(response.clients);
                    });
                });

                function renderClientRows(clients) {
                    const rows = clients.map(function (client) {
                        const limit = (client.limit === null || client.limit === undefined) ? '' : client.limit;
                        const tag = limit === '' ? EX_TEXT.uses_global : EX_TEXT.has_custom;

                        return '<tr class="client-limit-row"><td class="align-middle">'
                            + $('<div>').text(client.name).html()
                            + '<small class="sl-help d-block">' + tag + '</small></td>'
                            + '<td><input type="number" min="0" class="form-control client-limit-input" '
                            + 'data-id="' + client.id + '" value="' + limit + '"></td></tr>';
                    });

                    $clientsBody.html(rows.join('') || '<tr><td colspan="2" class="text-center py-2">'
                        + EX_TEXT.empty + '</td></tr>');
                }

                $globalClient.on('input', function () {
                    $('.client-limit-input').val($(this).val());
                });

                $('#searchClientLimit').on('keyup', function () {
                    const term = $(this).val().toLowerCase();

                    $('.client-limit-row').each(function () {
                        $(this).toggle($(this).text().toLowerCase().indexOf(term) > -1);
                    });
                });

                $saveClients.on('click', function () {
                    const limits = {};

                    // تُرسَل القيمة ولو فارغةً: الفراغ هو ما يمسح الحدَّ الخاص
                    $('.client-limit-input').each(function () {
                        limits[$(this).data('id')] = $(this).val();
                    });

                    $saveClients.prop('disabled', true).text(EX_TEXT.saving);

                    $.post("{{ route('product-exchanges.update-client-limits') }}", {
                        _token: "{{ csrf_token() }}",
                        global_limit: $globalClient.val(),
                        limits: limits,
                    }, function (response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $clientModal.modal('hide');
                            setTimeout(() => location.reload(), 500);
                        } else {
                            toastr.error(response.message || EX_TEXT.save_failed);
                        }
                    }).fail(function (xhr) {
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || EX_TEXT.save_failed);
                    }).always(function () {
                        $saveClients.prop('disabled', false).text(EX_TEXT.save);
                    });
                });
            @endif
        });
    </script>
@endsection
