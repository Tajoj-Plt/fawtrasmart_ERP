{{--
    قالبٌ واحد لكل تقارير نقاط البيع.

    كانت ستّةُ قوالبَ منفصلة، كلٌّ منها يُعيد كتابة شريط الفلاتر نفسِه
    وجدولِ الإجماليات نفسِه. والتقريرُ يصف نفسَه الآن (أعمدةً وتجميعاً
    وفلاتر) فيبنيه هذا القالب.

    ═══════════════ ما كان في التصميم ═══════════════

    ترميزُ Vuexy القديم: `content-header row` وترويسةٌ ومسارٌ مكتوبان بيدهما
    بدل مكوّن `page-head`، و`card-header`/`card-title`، و`form-select`
    و`badge badge-light-primary` — ولا شيء منها من نظام `stk-` الذي عليه
    بقيّةُ الوحدة.

    **والإجمالياتُ تُطبع بمفاتيحها الإنجليزية**: `invoices_count` و
    `net_amount` و`margin_percent` عناوينَ تحت أرقامٍ عربية. والهامشُ يُعرض
    «12.50» بلا علامةٍ مئوية فيُقرأ ريالاً.

    ═══════════════ والتصدير ═══════════════

    كان رابطاً واحداً إلى `pos.reports.export` يُنزّل CSV خاماً — بلا
    ترويسة، ولا بطاقات، ولا تنسيقِ أعمدة. **ولا طباعةَ إطلاقاً.**

    صار كما في تقارير الحسابات العامة والمخزون: `report-tools` يُنتج ملفَّ
    إكسل منسَّقاً بترويسةٍ وبطاقاتٍ وأعمدةٍ مضبوطة العرض، وورقةَ طباعةٍ
    بنفس الترويسة. و`CSV` يبقى زرّاً ثانوياً لمن يريد الخام.
--}}
@extends('pos::master')

@section('title'){{ $title }}@stop

@section('content')

@php
    /*
        الفلاترُ المطبَّقة فعلاً — تُقرأ في ترويسة الورقة المطبوعة وفي
        ملفّ الإكسل، فيُعرف على أيّ قيدٍ بُني الرقم.
    */
    $activeFilters = ['الفترة' => $period['period']['label'] ?? 'الفترة الجارية'];

    $filterMap = [
        'branch_id' => ['الفرع', $filters['branches'], 'name'],
        'device_id' => ['الجهاز', $filters['devices'], 'device_name'],
        'employee_id' => ['الكاشير', $filters['employees'], 'name'],
        'session_id' => ['الجلسة', $filters['sessions'], 'session_number'],
        'category_id' => ['التصنيف', $filters['categories'], 'name'],
        'store_house_id' => ['المخزن', $filters['storeHouses'], 'name'],
    ];

    foreach ($filterMap as $key => [$label, $options, $field]) {
        if (filled(request($key)) && ($match = collect($options)->firstWhere('id', (int) request($key)))) {
            $activeFilters[$label] = $match->{$field};
        }
    }

    if (filled(request('date_from')) || filled(request('date_to'))) {
        $activeFilters['المدى'] = (request('date_from') ?: '…').' ← '.(request('date_to') ?: '…');
    }

    if (filled($currency)) {
        $activeFilters['العملة'] = $currency;
    }

    $activeFilters['التجميع'] = $groupings[$group_by] ?? $group_by;

    // ما يُمسح بزرّ «مسح الفلاتر» — والفترةُ ليست فلتراً يُمسح
    $hasFilters = collect(array_keys($filterMap))
        ->merge(['date_from', 'date_to', 'currency'])
        ->contains(fn ($k) => filled(request($k)));

    /*
        الإجمالياتُ للعرض: تسميةٌ عربية وتنسيقٌ بالنوع.

        `rows_count` مستبعَدٌ — يظهر بجانب عنوان الجدول لا كبطاقةٍ مستقلّة.
    */
    $labels = $totalLabels ?? [];

    $cards = collect($totals)
        ->except(['rows_count'])
        ->map(function ($value, $key) use ($labels, $symbol) {
            [$label, $kind] = $labels[$key] ?? [$key, 'money'];

            $text = match ($kind) {
                'count' => number_format((float) $value),
                'qty' => number_format((float) $value, 3),
                'percent' => number_format((float) $value, 2).'٪',
                default => number_format((float) $value, 2).' '.$symbol,
            };

            /*
                الأيقونةُ واللونُ من معنى الرقم لا من ترتيبه.

                فالعجزُ والفائضُ يُقرآن بالنظرة، والربحُ يُميَّز عن التكلفة —
                وبطاقاتٌ متطابقةُ الشكل تُقرأ بالتدقيق لا بالنظر.
            */
            $tone = match (true) {
                in_array($key, ['profit', 'net_revenue', 'net_amount', 'settled_amount', 'over_total'], true) => 'stk-metric--success',
                in_array($key, ['returns_amount', 'returns_count', 'short_total', 'unsettled_amount'], true) => 'stk-metric--danger',
                in_array($key, ['variance_sessions', 'unconfirmed_sessions', 'variance_rate', 'variance_total', 'net_variance', 'estimated_rows'], true) => 'stk-metric--warning',
                default => '',
            };

            $icon = match ($kind) {
                'count' => 'hash',
                'qty' => 'package',
                'percent' => 'percent',
                default => 'dollar-sign',
            };

            return [
                'label' => $label,
                'text' => $text,
                'kind' => $kind,
                'tone' => $tone,
                'icon' => $icon,
            ];
        })
        ->values();

    /*
        الأعمدةُ الرقمية — تُصدَّر أرقاماً لا نصّاً، فيُجمع عليها في إكسل.

        العمودُ الأول هو البُعد (اسمُ الصنف أو الجلسة) وما بعده أرقام.
    */
    $headers = array_keys($exportColumns ?? []);
    $numericColumns = range(1, max(count($headers) - 1, 1));
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos_reports.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> التقارير
        </a>

        @permission('pos.reports.export')
            <button type="button" id="exportExcel" class="btn btn-outline-success" @disabled(! count($rows))>
                <i class="feather icon-file-text"></i> تصدير إكسل
            </button>
        @endpermission

        <button type="button" id="printReport" class="btn btn-primary" @disabled(! count($rows))>
            <i class="feather icon-printer"></i> طباعة
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => $title,
        'icon' => 'bar-chart-2',
        'sub' => $activeFilters['الفترة'].' · '.($groupings[$group_by] ?? ''),
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
            'التقارير' => route('pos_reports.index'),
            $title => null,
        ],
    ])

    {{-- ═══════════ الفلاتر ═══════════ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title">
                    <i class="feather icon-filter"></i> تصفية التقرير
                    <span class="stk-tag">{{ $activeFilters['الفترة'] }}</span>
                </h6>

                @if ($hasFilters)
                    <a href="{{ route('pos.reports.show', $report) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" class="form">
                <div class="row">
                    @php
                        $selects = [
                            ['group_by', 'التجميع', $groupings, null, false],
                            ['branch_id', 'الفرع', $filters['branches'], 'name', true],
                            ['device_id', 'الجهاز', $filters['devices'], 'device_name', true],
                            ['employee_id', 'الكاشير', $filters['employees'], 'name', true],
                            ['category_id', 'التصنيف', $filters['categories'], 'name', true],
                            ['store_house_id', 'المخزن', $filters['storeHouses'], 'name', true],
                        ];
                    @endphp

                    @foreach ($selects as [$name, $label, $options, $field, $blank])
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f-{{ $name }}">{{ $label }}</label>
                                <select id="f-{{ $name }}" name="{{ $name }}" class="form-control select2">
                                    @if ($blank)
                                        <option value="">الكل</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option->id }}"
                                                @selected(request($name) == $option->id)>{{ $option->{$field} }}</option>
                                        @endforeach
                                    @else
                                        @foreach ($options as $key => $text)
                                            <option value="{{ $key }}" @selected($group_by === $key)>{{ $text }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                    @endforeach

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-session">الجلسة</label>
                            <select id="f-session" name="session_id" class="form-control select2">
                                <option value="">كل الجلسات</option>
                                @foreach ($filters['sessions'] as $session)
                                    <option value="{{ $session->id }}" @selected(request('session_id') == $session->id)>
                                        {{ $session->session_number }} — {{ $session->user->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-currency">العملة</label>
                            <select id="f-currency" name="currency" class="form-control select2">
                                <option value="">كل العملات</option>
                                @foreach ($filters['currencies'] as $item)
                                    <option value="{{ $item->code }}" @selected($currency === $item->code)>
                                        {{ $item->arabic_name ?: $item->name }} ({{ $item->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{--
                        مدًى تاريخيّ حرّ إلى جانب الفترة المحاسبية.

                        الفترةُ هي الأصل، والمدى يتقدّم عليها حين يُكتب — فسؤالُ
                        «مبيعات أمس بعد الظهر» يوميٌّ ولا فترةَ محاسبية تُجيبه.
                    --}}
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-from">من تاريخ</label>
                            <input type="date" id="f-from" name="date_from"
                                   value="{{ request('date_from') }}" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-to">إلى تاريخ</label>
                            <input type="date" id="f-to" name="date_to"
                                   value="{{ request('date_to') }}" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="f-dir">الترتيب</label>
                            <select id="f-dir" name="sort_direction" class="form-control">
                                <option value="desc" @selected(request('sort_direction', 'desc') === 'desc')>تنازلي</option>
                                <option value="asc" @selected(request('sort_direction') === 'asc')>تصاعدي</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> عرض</button>

                @permission('pos.reports.export')
                    <a href="{{ route('pos.reports.export', ['report' => $report] + request()->query()) }}"
                       class="btn btn-outline-secondary" title="ملف CSV خام بلا تنسيق">
                        <i class="feather icon-download"></i> CSV
                    </a>
                @endpermission
            </form>
        </div>
    </div>

    {{-- ═══════════ الإجماليات ═══════════ --}}
    @if ($cards->isNotEmpty())
        <div class="stk-metrics">
            @foreach ($cards as $card)
                <div class="stk-metric {{ $card['tone'] }}">
                    <span class="stk-metric__icon"><i class="feather icon-{{ $card['icon'] }}"></i></span>
                    <span class="stk-metric__value stk-num">{{ $card['text'] }}</span>
                    <span class="stk-metric__label">{{ $card['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> النتائج
                    <span class="stk-count">{{ $totals['rows_count'] ?? 0 }}</span>
                </h6>

                <span class="stk-tag">{{ $symbol }}</span>
            </div>

            @if (count($rows))
                <div class="table-responsive">
                    <table id="reportTable" class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                @foreach ($headers as $i => $header)
                                    <th @class(['text-center' => $i > 0])>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach (array_values($exportColumns ?? []) as $i => $field)
                                        <td @class(['text-center stk-num' => $i > 0])>
                                            @if (is_callable($field))
                                                {{ $field($row) }}
                                            @else
                                                {{ $row->{$field} ?? '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></span>
                    <h6 class="stk-empty__title">لا بيانات بهذه الفلاتر</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم تُسجَّل حركةٌ في {{ $activeFilters['الفترة'] }} — جرّب فترةً أخرى أو مدًى تاريخياً.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.reports.show', $report) }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@include('pos::components.report-tools')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof stkReportTools !== 'function') {
            return;
        }

        stkReportTools({
            table: '#reportTable',
            fileName: @json($title, JSON_UNESCAPED_UNICODE),
            title: @json($title, JSON_UNESCAPED_UNICODE),
            subTitle: @json($activeFilters['الفترة'].' · '.($groupings[$group_by] ?? ''), JSON_UNESCAPED_UNICODE),
            info: @json($activeFilters, JSON_UNESCAPED_UNICODE),
            cards: @json($cards->map(fn ($c) => ['title' => $c['label'], 'value' => $c['text']])->all(), JSON_UNESCAPED_UNICODE),
            numericColumns: @json($numericColumns),
            descriptionColumn: 0,
            exportButton: '#exportExcel',
            printButton: '#printReport',
        });
    });
</script>

@endsection
