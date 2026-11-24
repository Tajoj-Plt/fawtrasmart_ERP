@extends('rentalmanagement::realestate.master')

@section('title', $meta['title'])

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;

        $needsClient = ($meta['needs'] ?? null) === 'client_id';
        $needsOwner = ($meta['needs'] ?? null) === 'owner_id';
        $needsSubject = $needsClient || $needsOwner;
        $columns = $report['columns'] ?? [];
        $rows = $report['rows'] ?? [];
        $totals = $report['totals'] ?? [];
        $firstKey = array_key_first($columns);

        // الفلاتر المفعّلة فعلاً (من الرابط) — تُمرَّر كما هي للتصدير والحفظ
        $query = array_filter(request()->only(array_keys($filters)), 'filled');
        $hasFilters = request()->hasAny(['building_id', 'owner_id', 'branch_id', 'usage_type', 'occupancy_status']);

        /*
         * قاعدة تنسيق الأرقام — تُحسم للعمود كلّه لا للخلية، فلا يختلط في عمودٍ واحد «3» و«3.00»:
         *   نسبة : عنوان العمود فيه «%» → خانة عشرية واحدة.
         *   عدد  : كلّ قيم العمود أعدادٌ صحيحة (int أو نصٌّ رقمي بلا فاصلة) → بلا كسور.
         *   مبلغ : ما عدا ذلك (الخدمة تُرجع المبالغ float دائماً) → خانتان.
         * والإجمالي يتبع نوع عموده — لأن `sum()` في الخدمة تُرجع float حتى لمجموع الأعداد.
         */
        $kinds = [];
        foreach ($columns as $colKey => $column) {
            if (! $column['numeric']) {
                continue;
            }
            $values = array_filter(array_column($rows, $colKey), fn ($v) => $v !== null && $v !== '');
            $allInt = $values !== [] && count(array_filter($values, fn ($v) => is_int($v) || (is_string($v) && ctype_digit(ltrim($v, '-'))))) === count($values);
            $kinds[$colKey] = str_contains($column['label'], '%') ? 'pct' : ($allInt ? 'int' : 'money');
        }

        // المبلغ يُعرض بعملته المسجَّلة على السطر (`_currency`)، والإجماليّ بعملة النظام
        $moneyClass = \Modules\RentalManagement\Support\RealEstateMoney::class;
        $fmt = function ($value, string $colKey, ?string $currency = null) use ($kinds, $moneyClass) {
            if ($value === null || $value === '') {
                return '—';
            }
            if (! is_numeric($value)) {
                return $value;
            }

            $kind = $kinds[$colKey] ?? 'money';

            if ($kind === 'money') {
                return class_exists($moneyClass)
                    ? $moneyClass::format($value, $currency)
                    : number_format((float) $value, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
            }

            return number_format((float) $value, ['int' => 0, 'pct' => 1][$kind]);
        };

        $numericIndexes = array_keys(array_filter(array_values($columns), fn ($c) => $c['numeric']));

        /* ── ما يُطبع ويُصدَّر مع الجدول: بطاقات الملخّص، ومعايير التقرير، وصفّ الإجمالي ── */
        $currencyCode = class_exists($moneyClass) ? $moneyClass::code() : (\App\Support\CurrencySettings::code() ?? '');
        $periodText = ($filters['period_label'] ?? null)
            ? $filters['period_label'].' ('.$filters['from_date'].' ← '.$filters['to_date'].')'
            : 'من '.$filters['from_date'].' إلى '.$filters['to_date'];

        // البطاقات: عدد السجلّات ثم إجماليات الأعمدة (المبالغ أولاً) — حتى أربع بطاقات
        $cards = [['title' => 'عدد السجلّات', 'value' => number_format(count($rows)), 'unit' => '', 'icon' => 'list']];
        $totalKeys = array_keys($totals);
        usort($totalKeys, fn ($a, $b) => (($kinds[$a] ?? '') === 'money' ? 0 : 1) <=> (($kinds[$b] ?? '') === 'money' ? 0 : 1));
        foreach (array_slice($totalKeys, 0, 3) as $totalKey) {
            if (! isset($columns[$totalKey]) || ! is_numeric($totals[$totalKey])) {
                continue;
            }
            $kind = $kinds[$totalKey] ?? 'money';
            $cards[] = [
                'title' => 'إجمالي '.$columns[$totalKey]['label'],
                'value' => number_format((float) $totals[$totalKey], ['int' => 0, 'pct' => 1, 'money' => 2][$kind]),
                'unit' => $kind === 'money' ? ' '.$currencyCode : '',
                'icon' => $kind === 'money' ? 'dollar-sign' : 'hash',
            ];
        }

        // معايير التقرير بأسمائها لا بأرقامها
        $info = ['الفترة' => $periodText];
        if (! empty($filters['building_id'])) { $info['العقار'] = optional($buildings->firstWhere('id', (int) $filters['building_id']))->name; }
        if (! empty($filters['owner_id'])) { $info['المالك'] = optional($owners->firstWhere('id', (int) $filters['owner_id']))->name; }
        if (! empty($filters['client_id'])) { $info['العميل'] = optional(\App\Models\Client::query()->select('id', 'trade_name', 'first_name', 'last_name')->find($filters['client_id']))->display_name; }
        if (! empty($filters['branch_id'])) { $info['الفرع'] = optional($branches->firstWhere('id', (int) $filters['branch_id']))->name; }
        if (! empty($filters['usage_type'])) { $info['الاستخدام'] = V::label(V::USAGE_TYPES, $filters['usage_type']); }
        if (! empty($filters['occupancy_status'])) { $info['حالة الإشغال'] = V::label(V::OCCUPANCY, $filters['occupancy_status']); }
        $info = array_filter($info, 'filled');

        // ترويسة التصدير تسمّي العملة لأن خلاياه أرقامٌ مجرّدة
        $exportHeaders = [];
        foreach ($columns as $colKey => $column) {
            $exportHeaders[] = $column['label'].(($kinds[$colKey] ?? null) === 'money' ? ' ('.$currencyCode.')' : '');
        }

        // صفّ الإجمالي في ملف الإكسل (الطباعة تقرأ `tfoot` مباشرة)
        $summaryRow = null;
        if ($totals) {
            $summaryValues = [];
            foreach ($columns as $colKey => $column) {
                $kind = $kinds[$colKey] ?? 'money';
                $summaryValues[] = array_key_exists($colKey, $totals) && is_numeric($totals[$colKey])
                    ? number_format((float) $totals[$colKey], ['int' => 0, 'pct' => 1, 'money' => 2][$kind])
                    : ($colKey === $firstKey ? 'الإجمالي' : '');
            }
            $summaryRow = [
                'values' => $summaryValues,
                'alignments' => array_map(fn ($i) => $i === 0 ? 'right' : 'center', array_keys($summaryValues)),
                'cellStyles' => array_map(fn () => ['bold' => true], $summaryValues),
            ];
        }

        // عرض الأعمدة: العمود الأول (البيان/الاسم) أعرض من البقيّة
        $columnCount = max(1, count($columns));
        $excelWidths = array_map(fn ($i) => $i === 0 ? 28 : 18, range(0, $columnCount - 1));
        $firstWidth = $columnCount > 1 ? max(14, (int) round(100 / $columnCount * 1.6)) : 100;
        $restWidth = $columnCount > 1 ? round((100 - $firstWidth) / ($columnCount - 1), 2) : 0;
        $printWidths = array_map(fn ($i) => ($i === 0 ? $firstWidth : $restWidth).'%', range(0, $columnCount - 1));

        $toolCards = array_map(fn ($c) => ['title' => $c['title'], 'value' => $c['value'], 'unit' => $c['unit']], $cards);
        $toolSummary = $summaryRow ? [$summaryRow] : [];
    @endphp

    <div class="content-body">

        @section('page-actions')
            {{-- الطباعة والتصدير المخصَّصان — المكتبتان نفساهما في تقارير الحسابات (`print-table.js` و`excel-export-helper.js`) --}}
            @if ($ready && $rows)
                <button type="button" class="btn btn-outline-primary" id="printBtn"><i class="fa fa-print me-2"></i> طباعة</button>
                @permission('realestate.reports.export')
                    <button type="button" class="btn btn-outline-success" id="exportExcel"><i class="fa fa-file-excel me-2"></i> تصدير إكسل</button>
                @endpermission
            @endif
            @permission('realestate.reports.export')
                @if ($ready)
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-bs-toggle="modal" data-target="#saveReportModal" data-bs-target="#saveReportModal">
                        <i class="feather icon-bookmark"></i> حفظ الإعداد
                    </button>
                @endif
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $meta['title'],
            'icon' => $meta['icon'],
            'sub' => $meta['description'],
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'التقارير' => route('realestate.reports.index'), $meta['title'] => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title">
                        <i class="feather icon-filter"></i> معايير التقرير
                        @if ($meta['financial']) <span class="badge badge-light-warning">مالي</span> @endif
                    </h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters) <span class="stk-count">فلاتر مفعّلة</span> @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-bs-toggle="collapse"
                                data-target="#advancedFilters" data-bs-target="#advancedFilters" aria-expanded="false">
                            <i class="feather icon-sliders"></i> فلاتر إضافية
                        </button>
                    </div>
                </div>

                {{-- GET عادي لا ajax: رابط التقرير بفلاتره قابلٌ للمشاركة والحفظ --}}
                <form id="reportFilters" method="GET" action="{{ route('realestate.reports.show', $key) }}" novalidate data-re-validate>
                    <div class="row">
                        @if ($needsClient)
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="client_id" class="stk-req">العميل</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => $filters['client_id'] ?? null, 'required' => true, 'tenantsOnly' => true, 'placeholder' => 'ابحث عن المستأجر…'])
                                </div>
                            </div>
                        @endif
                        @if ($needsOwner)
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="owner_id" class="stk-req">المالك</label>
                                    <select id="owner_id" name="owner_id" class="form-control select2" data-required="1">
                                        <option value="">اختر المالك…</option>
                                        @foreach ($owners as $owner)
                                            <option value="{{ $owner->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $owner->id)>{{ $owner->name }} — {{ $owner->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        @include('partials.accounting-period-filter', ['period' => $period ?? [], 'colClass' => 'col-md-'.($needsSubject ? 4 : 3).' col-sm-6', 'fromName' => 'from_date', 'toName' => 'to_date'])
                        <div class="col-md-{{ $needsSubject ? 4 : 3 }} col-sm-6">
                            <div class="form-group">
                                <label for="from_date">من تاريخ</label>
                                <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                            </div>
                        </div>
                        <div class="col-md-{{ $needsSubject ? 4 : 3 }} col-sm-6">
                            <div class="form-group">
                                <label for="to_date">إلى تاريخ</label>
                                <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                                <small class="stk-help">مدًى حرّ — يتقدّم على الفترة المختارة.</small>
                            </div>
                        </div>
                        @unless ($needsSubject)
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="building_id">العقار</label>
                                    <select id="building_id" name="building_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) ($filters['building_id'] ?? '') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="client_id">العميل</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => $filters['client_id'] ?? null, 'placeholder' => 'الكل'])
                                </div>
                            </div>
                        @endunless
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedFilters">
                        <div class="row">
                            @if ($needsSubject)
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="building_id">العقار</label>
                                        <select id="building_id" name="building_id" class="form-control select2">
                                            <option value="">الكل</option>
                                            @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) ($filters['building_id'] ?? '') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
@unless ($needsOwner)
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="owner_id">المالك</label>
                                    <select id="owner_id" name="owner_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($owners as $owner) <option value="{{ $owner->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
@endunless
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="branch_id">الفرع</label>
                                    <select id="branch_id" name="branch_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($branches as $branch) <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="usage_type">الاستخدام</label>
                                    <select id="usage_type" name="usage_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::USAGE_TYPES as $value => $option) <option value="{{ $value }}" @selected(($filters['usage_type'] ?? '') === $value)>{{ $option['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="occupancy_status">حالة الإشغال</label>
                                    <select id="occupancy_status" name="occupancy_status" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::OCCUPANCY as $value => $option) <option value="{{ $value }}" @selected(($filters['occupancy_status'] ?? '') === $value)>{{ $option['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('realestate.reports.show', $key) }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-play"></i> عرض التقرير</button>
                    </div>
                </form>
            </div>
        </div>

        @if (! $ready)
            <div class="card">
                @include('rentalmanagement::realestate.components.empty', ['icon' => 'user', 'title' => 'اختر العميل لعرض الكشف', 'hint' => 'حدّد العميل والفترة من معايير التقرير أعلاه ثم اضغط «عرض التقرير»'])
            </div>
        @else
            @if (! empty($report['note']))
                <div class="alert alert-info re-no-print" role="alert">
                    <div class="alert-body text-muted"><i class="feather icon-info"></i> {{ $report['note'] }}</div>
                </div>
            @endif

            @if ($rows)
                <div class="stk-metrics">
                    @foreach ($cards as $card)
                        <div class="stk-metric">
                            <span class="stk-metric__icon"><i class="feather icon-{{ $card['icon'] }}"></i></span>
                            <span class="stk-metric__label">{{ $card['title'] }}</span>
                            <span class="stk-metric__value">{{ $card['value'] }}{{ $card['unit'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card stk-table-card" id="reportCard">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-{{ $meta['icon'] }}"></i> {{ $meta['title'] }}
                        <span class="stk-count">{{ number_format(count($rows)) }}</span>
                    </h6>
                    <span class="text-muted small stk-num">{{ $periodText }}</span>
                </div>

                @if (! $rows)
                    @include('rentalmanagement::realestate.components.empty', ['icon' => $meta['icon'], 'title' => 'لا بيانات', 'hint' => 'لا شيء يطابق معايير التقرير في الفترة المختارة'])
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="reportTable">
                            <thead>
                                <tr>
                                    @foreach ($columns as $column)
                                        <th @class(['text-right' => $column['numeric']])>{{ $column['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        @foreach ($columns as $colKey => $column)
                                            @if ($column['numeric'])
                                                <td class="text-right re-num">{{ $fmt($row[$colKey] ?? null, $colKey, $row['_currency'] ?? null) }}</td>
                                            @elseif ($colKey === $firstKey && ! empty($row['_url']))
                                                {{-- التنقيب: الخلية الأولى تفتح السجلّ المكوِّن للسطر --}}
                                                <td><a href="{{ $row['_url'] }}" class="font-weight-bold">{{ $row[$colKey] ?? '—' }}</a></td>
                                            @else
                                                <td>{{ $row[$colKey] ?? '—' }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($totals)
                                <tfoot>
                                    <tr class="stk-total-row">
                                        @foreach ($columns as $colKey => $column)
                                            @if (array_key_exists($colKey, $totals))
                                                <td @class(['re-num', 'text-right' => $column['numeric']])>{{ $fmt($totals[$colKey], $colKey) }}</td>
                                            @elseif ($colKey === $firstKey)
                                                <td>الإجمالي</td>
                                            @else
                                                <td></td>
                                            @endif
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @permission('realestate.reports.export')
        <div class="modal fade" id="saveReportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('realestate.reports.save', $key) }}" class="modal-content" novalidate data-re-validate>
                    @csrf
                    {{-- الفلاتر الحالية تُحفظ مع الاسم — والمتحكّم يُسقط الفارغ منها --}}
                    @foreach ($query as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="feather icon-bookmark"></i> حفظ إعداد التقرير</h5>
                        <button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label for="saved_name" class="stk-req">اسم التقرير المحفوظ</label>
                            <input type="text" id="saved_name" name="name" class="form-control" data-required="1" maxlength="100" value="{{ old('name') }}" placeholder="مثال: {{ $meta['title'] }} — الربع الأول">
                            <small class="text-muted">يُحفظ التقرير بفلاتره الحالية ويظهر في «تقاريري المحفوظة» بمركز التقارير.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    @endpermission
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')

    @if ($ready && $rows)
        @include('stock::components.report-tools')

        <script>
            $(function () {
                stkReportTools({
                    table: '#reportTable',
                    fileName: @json(str_replace(' ', '_', $meta['title'])),
                    title: @json($meta['title']),
                    subTitle: 'إدارة العقارات',
                    tableTitle: @json($meta['title']),
                    info: {!! json_encode((object) $info, JSON_UNESCAPED_UNICODE) !!},
                    cards: {!! json_encode($toolCards, JSON_UNESCAPED_UNICODE) !!},
                    headers: @json($exportHeaders),
                    numericColumns: @json($numericIndexes),
                    columnWidths: @json($excelWidths),
                    printWidths: @json($printWidths),
                    summaryRows: {!! json_encode($toolSummary, JSON_UNESCAPED_UNICODE) !!},
                    descriptionColumn: 0,
                    exportButton: '#exportExcel',
                    printButton: '#printBtn',
                });
            });
        </script>
    @endif
@endsection
