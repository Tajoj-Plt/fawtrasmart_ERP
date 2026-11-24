{{--
    نتائج تقرير الهدف — قالبٌ واحد يخدم «أهداف المبيعات» و«أهداف التحصيلات».

    **ما كان:** ملفّان (٢٧٦٣٩ بايت و٢٥٨٩٧) لا يفترقان إلّا في أعمدة
    التفصيل: المبيعاتُ تعرض «إجمالي — مرتجعات — إشعارات ← صافي»،
    والتحصيلاتُ «مدفوعات الفواتير — سندات القبض ← إجمالي التحصيل». وما
    عدا ذلك مكرَّرٌ حرفاً بحرف، بما فيه ثمانون سطراً من الأنماط بألوانٍ
    مثبَّتة (`#667eea`، `#f8fafc`، `#e8edff`) لا تعرف «مظهر النظام»،
    وترويسةُ `content-header row` يدويّة، وطباعةٌ بـ`window.print()`
    تطبع الصفحة كما هي بشريطها الجانبي.

    **ما صار:** الفرقُ بيانٌ في `TargetScreen::MAP['metrics']`، والقالبُ
    واحد على لغة `sl-` — `page-head` و`sl-stats` و`sl-table-card` وشريطُ
    التقدّم المشترك `targets.partials.progress`. والطباعةُ والتصديرُ من
    `sales::components.report-tools` كما في فواتير المبيعات وإشعاراتها:
    يقرآن الجدول كما هو معروض، فلا تُطبع الصفحةُ بأثاثها.

    المتغيّرات المتوقَّعة:
      $screen, $target, $results, $summary, $fromDate, $toDate,
      $isSettled, $methodLabel (اختياري)
--}}
@php
    $r = $screen['routes'];

    /* المبالغ بإعدادات «العملات»: الفواصل والكسور والرمز كما ضُبطت، بدل
       `number_format` و«ر.س» مكتوبةً في القالب. */
    $money = fn ($value, $decimals = 0) => \App\Support\CurrencySettings::number($value, $decimals);
    $currencyName = \App\Support\CurrencySettings::name();
    $dateText = fn ($v) => $v ? \App\Support\LanguageSettings::formatDate($v) : '—';

    // التسويةُ تحتسب العمولةَ وتصرفها — انظر SalesTargetController::__construct
    $canSettle = hasPermission('sales.targets.settle') && canAll(['commissions.create', 'commissions.disburse']);

    // أعمدةُ التفصيل التي تفرّق النوعين — انظر TargetScreen
    $metrics = $screen['metrics'];
    $achieved = $screen['achieved'];

    $hasCommission = ($summary['total_commission'] ?? 0) > 0;

    /* أعمدةُ الأرقام للتصدير: الهدف، ثمّ التفصيل، ثمّ الإنجاز والمتبقّي.
       تُحسب هنا لأنّ عددها يتبع النوع — ثلاثةُ تفاصيل في المبيعات
       واثنان في التحصيلات. */
    $firstMetricColumn = 4;
    $achievedColumn = $firstMetricColumn + count($metrics);
    $remainingColumn = $achievedColumn + 1;
    $progressColumn = $remainingColumn + 1;

    $numericColumns = range(3, $remainingColumn);
    if ($hasCommission) {
        $numericColumns[] = $progressColumn + 1;
    }

    $columnCount = $progressColumn + 1 + ($hasCommission ? 1 : 0);

    // ترويسةُ ملفّ التصدير والمطبوعة — تُبنى هنا لا في وسيط `@json`:
    // الموجِّه لا يقبل مصفوفةً مفاتيحُها نداءاتُ ترجمة
    $exportInfo = [
        __('sales_targets.report.period') => $fromDate.' → '.$toDate,
        __('sales_targets.report.target_value') => \App\Support\CurrencySettings::format((float) $target->target_value),
        __('sales_targets.report.method') => $methodLabel ?? '—',
    ];

    // الموظفُ الذي صُفّي عليه التقرير — التسويةُ تخصّه وحده حينها
    $employeeFilter = $employeeFilter ?? null;
    $filteredName = $employeeFilter
        ? ($target->achievements->firstWhere('employee_id', $employeeFilter)?->employee?->name)
        : null;

    // نصُّ تأكيد التسوية — يُبنى هنا لا داخل `@json` لنفس السبب
    $settleConfirmHtml = '<p>'.e(__('sales_targets.report.settle.confirm_body')).'</p>'
        .'<p><strong>'.e(__('sales_targets.report.settle.confirm_period', [
            'from' => $fromDate,
            'to' => $toDate,
        ])).'</strong></p>'
        .($employeeFilter
            ? '<p>'.e(__('sales_targets.report.settle.confirm_employee', ['name' => $filteredName ?: '#'.$employeeFilter])).'</p>'
            : '<p>'.e(__('sales_targets.report.settle.confirm_all')).'</p>');
@endphp

<div class="content-body sl-scope">

    {{-- يُعرَّف قبل الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
    @section('page-actions')
        <button type="button" class="btn btn-outline-secondary" id="printReport">
            <i class="feather icon-printer"></i> {{ __('sales_targets.report.table.print') }}
        </button>

        <button type="button" class="btn btn-outline-secondary" id="exportExcel">
            <i class="feather icon-download"></i> {{ __('sales_invoice.actions.export_visible') }}
        </button>

        <a href="{{ route($r['report'], $target->id) }}" class="btn btn-outline-primary">
            <i class="feather icon-calendar"></i> {{ __('sales_targets.report.settle.other_period') }}
        </a>

        <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
            {{ __('sales_targets.report.back') }}
        </a>
    @endsection

    @include('sales::components.page-head', [
        'title' => __('sales_targets.report.heading'),
        'icon' => 'bar-chart-2',
        'sub' => $target->name.' — '.$dateText($fromDate).' → '.$dateText($toDate),
        'crumbs' => [
            __('sales_settings.ui.home') => route('dashboard'),
            $screen['title'] => route($r['index']),
            $target->name => route($r['show'], $target->id),
            __('sales_targets.report.crumb_results') => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ══════════ ما يقرؤه القارئ قبل الأرقام ══════════
         الفترةُ المحسوبة، وطريقةُ الاحتساب، وحالةُ التسوية — ثلاثةُ أشياء
         تتغيّر بها كلُّ قيمةٍ تحتها، فتُقال قبلها لا بعدها --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-calendar"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.05rem">
                    {{ $dateText($fromDate) }} → {{ $dateText($toDate) }}
                </p>
                <p class="sl-stat__label">{{ __('sales_targets.report.period') }}</p>
            </div>
        </div>

        @isset($methodLabel)
            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-sliders"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.05rem">{{ $methodLabel }}</p>
                    <p class="sl-stat__label">
                        {{ __('sales_targets.report.method') }} — {{ __('sales_targets.report.method_hint') }}
                    </p>
                </div>
            </div>
        @endisset

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $isSettled ? 'success' : 'warning' }}">
                <i class="feather icon-{{ $isSettled ? 'check-circle' : 'clock' }}"></i>
            </div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.05rem">
                    <span class="sl-state {{ $isSettled ? 'sl-state--on' : 'sl-state--off' }}">
                        {{ $isSettled
                            ? __('sales_targets.report.settled_badge')
                            : (! empty($partlySettled)
                                ? __('sales_targets.report.partial_badge')
                                : __('sales_targets.report.pending_badge')) }}
                    </span>
                </p>
                <p class="sl-stat__label">{{ __('sales_targets.report.settlement') }}</p>
            </div>
        </div>
    </div>

    {{-- ══════════ الإجماليات ══════════
         الهدف، ثمّ تفصيلُ ما بُني عليه الإنجاز، ثمّ الإنجازُ والمتبقّي
         والنسبة والعمولة. ترتيبُ البطاقات هو ترتيبُ المعادلة نفسها --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-{{ $screen['icon'] }}"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.35rem">{{ $money($summary['total_target']) }}</p>
                <p class="sl-stat__label">
                    {{ __('sales_targets.report.cards.total_target') }} — {{ $currencyName }}
                </p>
            </div>
        </div>

        @foreach ($metrics as $metric)
            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--{{ $metric['tone'] }}">
                    <i class="feather icon-{{ $metric['icon'] }}"></i>
                </div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.35rem">
                        {{ $metric['minus'] ? '−' : '' }}{{ $money($summary[$metric['total']] ?? 0) }}
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_targets.report.cards.'.$metric['text']) }} — {{ $currencyName }}
                        @if ($metric['hint'])
                            <br>{{ __('sales_targets.report.cards.'.$metric['hint']) }}
                        @endif
                    </p>
                </div>
            </div>
        @endforeach

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.35rem">{{ $money($summary['total_achieved']) }}</p>
                <p class="sl-stat__label">
                    {{ __('sales_targets.report.cards.'.$achieved['text']) }}
                    <br>{{ __('sales_targets.report.cards.'.$achieved['hint']) }}
                </p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-trending-up"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.35rem">{{ $money($summary['total_remaining']) }}</p>
                <p class="sl-stat__label">
                    {{ __('sales_targets.report.cards.remaining') }} — {{ $currencyName }}
                </p>
            </div>
        </div>

        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-percent"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value" style="font-size:1.35rem">{{ $money($summary['average_percentage'], 1) }}%</p>
                <p class="sl-stat__label">
                    {{ __('sales_targets.report.cards.percentage') }} —
                    {{ $summary['average_percentage'] >= 100
                        ? __('sales_targets.report.cards.achieved_label')
                        : __('sales_targets.report.cards.in_progress') }}
                </p>
            </div>
        </div>

        {{-- سطرٌ لا يُرسم بصفر: هدفٌ لم يبلغ أحدٌ فيه مستوًى لا عمولةَ فيه --}}
        @if ($hasCommission)
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-award"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.35rem">{{ $money($summary['total_commission'], 2) }}</p>
                    <p class="sl-stat__label">
                        {{ __('sales_targets.report.cards.commission') }} — {{ $currencyName }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- ══════════ إنجازات الموظفين ══════════ --}}
    <div class="card sl-table-card">
        <div class="sl-table-head">
            <h6 class="sl-table-head__title">
                <i class="feather icon-users"></i> {{ __('sales_targets.report.table.title') }}
            </h6>
            <span class="sl-count">{{ \App\Support\CurrencySettings::number(count($results), 0) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('sales_targets.report.table.employee') }}</th>
                        <th>{{ __('sales_targets.report.table.treasury') }}</th>
                        <th class="text-center">{{ __('sales_targets.report.table.target') }}</th>

                        @foreach ($metrics as $metric)
                            <th class="text-center">{{ __('sales_targets.report.table.'.$metric['text']) }}</th>
                        @endforeach

                        <th class="text-center">{{ __('sales_targets.report.table.'.$achieved['text']) }}</th>
                        <th class="text-center">{{ __('sales_targets.report.table.remaining') }}</th>
                        <th class="text-center" style="width:150px">{{ __('sales_targets.report.table.progress') }}</th>

                        @if ($hasCommission)
                            <th class="text-center">{{ __('sales_targets.report.table.commission') }}</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @forelse ($results as $index => $result)
                        <tr>
                            <td class="sl-num">{{ $index + 1 }}</td>

                            <td>
                                <div class="sl-media">
                                    <span class="sl-media__thumb" aria-hidden="true">
                                        {{ mb_substr($result['employee_name'], 0, 1, 'UTF-8') }}
                                    </span>
                                    <div class="sl-media__body">
                                        <span class="sl-media__title">
                                            {{ $result['employee_name'] }}
                                            @if (! empty($result['settled']))
                                                <span class="sl-state sl-state--on">{{ __('sales_targets.report.settled_badge') }}</span>
                                            @endif
                                        </span>
                                        @if ($result['applied_tier'])
                                            <span class="sl-media__sub">{{ $result['applied_tier'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td>{{ $result['treasury_name'] }}</td>

                            <td class="text-center sl-num">{{ $money($result['target_amount']) }}</td>

                            @foreach ($metrics as $metric)
                                <td class="text-center sl-num">
                                    @php $value = (float) ($result[$metric['key']] ?? 0); @endphp

                                    @if ($metric['minus'] && $value <= 0)
                                        {{-- صفرٌ لا يُخصم: يُكتب صفراً باهتاً لا سالباً --}}
                                        <span class="text-muted">{{ $money(0) }}</span>
                                    @else
                                        {{ $metric['minus'] ? '−' : '' }}{{ $money($value) }}
                                    @endif
                                </td>
                            @endforeach

                            <td class="text-center sl-num"><strong>{{ $money($result['achieved_amount']) }}</strong></td>

                            <td class="text-center sl-num">
                                @if ($result['remaining'] > 0)
                                    {{ $money($result['remaining']) }}
                                @else
                                    <span class="sl-state sl-state--on">{{ __('sales_targets.report.table.done') }}</span>
                                @endif
                            </td>

                            <td class="text-center">
                                @include('sales::targets.partials.progress', ['percentage' => $result['percentage']])
                            </td>

                            @if ($hasCommission)
                                <td class="text-center sl-num">
                                    <strong>{{ $money($result['commission_amount'], 2) }}</strong>
                                    <span class="sl-help">{{ $money($result['commission_percentage'], 1) }}%</span>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columnCount }}">
                                @include('sales::components.empty-state', [
                                    'icon' => 'inbox',
                                    'title' => __('sales_targets.report.table.empty'),
                                    'hint' => __('sales_targets.report.min_date', [
                                        'date' => $target->created_at?->format('Y-m-d'),
                                    ]),
                                    'action' => '<a href="'.route($r['report'], $target->id).'" class="btn btn-outline-secondary">'
                                        .'<i class="feather icon-calendar"></i> '
                                        .e(__('sales_targets.report.settle.other_period')).'</a>',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (count($results) > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3">{{ __('sales_targets.report.table.total') }}</td>
                            <td class="text-center sl-num">{{ $money($summary['total_target']) }}</td>

                            @foreach ($metrics as $metric)
                                <td class="text-center sl-num">
                                    {{ $metric['minus'] ? '−' : '' }}{{ $money($summary[$metric['total']] ?? 0) }}
                                </td>
                            @endforeach

                            <td class="text-center sl-num">{{ $money($summary['total_achieved']) }}</td>
                            <td class="text-center sl-num">{{ $money($summary['total_remaining']) }}</td>
                            <td class="text-center sl-num">{{ $money($summary['average_percentage'], 1) }}%</td>

                            @if ($hasCommission)
                                <td class="text-center sl-num">{{ $money($summary['total_commission'], 2) }}</td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ══════════ التسوية ══════════
         الزرّ محروسٌ بالصلاحية كما في بقيّة الشاشة: المسار يردّ من لا يملك
         «تسوية الأهداف»، فعرضُ زرٍّ يَعِد بما يُرفَض خطأُ واجهةٍ لا حراسة --}}
    @if ($isSettled)
        <div class="alert alert-success">
            <i class="feather icon-check-circle"></i> {{ __('sales_targets.report.settle.already') }}
        </div>
    @elseif ($target->status !== 'active')
        {{-- الهدف الموقوف لا يُسوّى — المتحكّم يردّه، فلا يُعرض زرٌّ يَعِد به --}}
        <div class="alert alert-warning">
            <i class="feather icon-pause-circle"></i> {{ __('sales_targets.messages.settle_inactive') }}
        </div>
    @elseif ($canSettle && count($results) > 0)
        <div class="sl-form-foot">
            <span class="sl-help" style="margin:0">
                <i class="feather icon-info"></i>
                {{ ! empty($partlySettled)
                    ? __('sales_targets.report.settle.partial')
                    : __('sales_targets.report.settle.hint') }}
            </span>

            <button type="button" class="btn btn-primary" id="settleTarget">
                <i class="feather icon-check-circle"></i> {{ __('sales_targets.report.settle.button') }}
            </button>
        </div>

        <form id="settleForm" action="{{ route($r['settle'], $target->id) }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="from_date" value="{{ $fromDate }}">
            <input type="hidden" name="to_date" value="{{ $toDate }}">
            @if ($employeeFilter)
                <input type="hidden" name="employee_id" value="{{ $employeeFilter }}">
            @endif
            <input type="hidden" name="notes" id="settleNotes">
        </form>
    @endif
</div>

@push('scripts')
    @include('sales::components.report-tools')

    <script>
        $(function () {
            slReportTools({
                table: '#reportTable',
                fileName: @json(__('sales_targets.report.results_title', ['name' => $target->name])),
                title: @json(__('sales_targets.report.heading')),
                subTitle: @json($target->name),
                info: @json($exportInfo),
                numericColumns: @json($numericColumns),
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });

            // `sweetalert2` محمَّلة في `sales::master` — لا تُجلب ثانيةً
            $('#settleTarget').on('click', function () {
                Swal.fire({
                    title: @json(__('sales_targets.report.settle.confirm_title')),
                    html: @json($settleConfirmHtml),
                    input: 'textarea',
                    inputLabel: @json(__('sales_targets.report.settle.notes_label')),
                    inputPlaceholder: @json(__('sales_targets.report.settle.notes_placeholder')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_targets.report.settle.confirm_yes')),
                    cancelButtonText: @json(__('sales_targets.report.settle.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (! result.isConfirmed) return;

                    document.getElementById('settleNotes').value = result.value || '';
                    document.getElementById('settleForm').submit();
                });
            });
        });
    </script>
@endpush
