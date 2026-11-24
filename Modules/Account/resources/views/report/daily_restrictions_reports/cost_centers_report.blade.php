@extends('account::master')

@section('title')
    تقرير مراكز التكلفة
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير مراكز التكلفة',
        'crumbs' => [
            'الرئيسية' => null,
            'تقرير مراكز التكلفة' => null,
        ],
    ])

    <div class="content-body">
        <!-- قسم الفلاتر -->
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>بحث وفلترة</div>
                            <div>
                                <button class="btn btn-outline-success" id="exportExcel">
                                    <i class="fa fa-file-excel me-2"></i>تصدير إكسل
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.CostCentersReport') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <label for="">مركز التكلفة الرئيسي</label>
                                <select multiple name="primary_cost_center_id[]" id="primary_cost_center" class="form-control select2">
                                    <option value="-1">الكل</option>
                                    @foreach($costCenters->where('is_main', 1) as $center)
                                        <option value="{{ $center->id }}" {{ in_array($center->id, request('primary_cost_center_id', [])) ? 'selected' : '' }}>
                                            {{ $center->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">مركز التكلفة الفرعي</label>
                                <select multiple name="secondary_cost_center_id[]" id="secondary_cost_center" class="form-control select2">
                                    <option value="-1">الكل</option>
                                    @foreach($costCenters->where('is_main', 0) as $center)
                                        <option value="{{ $center->id }}" {{ in_array($center->id, request('secondary_cost_center_id', [])) ? 'selected' : '' }}>
                                            {{ $center->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'date_from',
                                'toName' => 'date_to',
                                'colClass' => 'form-group col-md-3',
                            ])

                            <div class="form-group col-md-3">
                                <label for="">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" 
                                       value="{{ $fromDate ?? now()->startOfYear()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" 
                                       value="{{ $toDate ?? now()->format('Y-m-d') }}">
                            </div>
                        </div>

                        <!-- البحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="form-body row">
                                <div class="form-group col-md-4">
                                    <label for="">الحساب</label>
                                    <select multiple name="journal_account_id[]" id="journal_account" class="form-control select2">
                                        <option value="">الحساب الافتراضي</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ in_array($account->id, request('journal_account_id', [])) ? 'selected' : '' }}>
                                                {{ $account->code }} - {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="">الفرع</label>
                                    <select multiple name="data[branch_id][]" id="branch_id" class="form-control select2">
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ in_array($branch->id, request('data.branch_id', [])) ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="">نوع العرض</label>
                                    <select name="group_by" id="group_by" class="form-control">
                                        <option value="cost_center" {{ ($groupBy ?? 'cost_center') == 'cost_center' ? 'selected' : '' }}>مركز التكلفة</option>
                                        <option value="journal_account" {{ ($groupBy ?? '') == 'journal_account' ? 'selected' : '' }}>الحساب الفرعي</option>
                                        <option value="daily" {{ ($groupBy ?? '') == 'daily' ? 'selected' : '' }}>يومياً</option>
                                        <option value="monthly" {{ ($groupBy ?? '') == 'monthly' ? 'selected' : '' }}>شهري</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="is_summary" id="is_summary" value="{{ ($isSummary ?? false) ? '1' : '' }}">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">
                                <i class="fa fa-search"></i> بحث
                            </button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                                <i class="fa fa-sliders"></i> بحث متقدم
                            </a>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">
                                <i class="fa fa-refresh"></i> إلغاء الفلترة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">إجمالي المدين (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_debit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">إجمالي الدائن (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_credit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__label">الرصيد {{ ($totals['balance'] ?? 0) >= 0 ? '(دائن)' : '(مدين)' }}</span>
                <span class="stk-metric__value">{{ number_format(abs($totals['balance'] ?? 0), 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-target"></i></span>
                <span class="stk-metric__label">عدد مراكز التكلفة</span>
                <span class="stk-metric__value">{{ $totals['count'] ?? 0 }}</span>
            </div>
        </div>

        <!-- الرسم البياني -->
        @if(!empty($reportData))
        <div class="card mt-2">
            <div class="card-header">
                <h6 class="stk-table-head__title">مراكز التكلفة - الرسم البياني</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="costCenterBarChart" height="300"></canvas>
                    </div>
                    <div class="col-md-4">
                        <canvas id="costCenterPieChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- View Toggle Buttons -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group" role="group">
                        <a href="{{ request()->fullUrlWithQuery(['is_summary' => '']) }}" 
                           class="btn {{ !($isSummary ?? false) ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="fa fa-list"></i> التفاصيل
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['is_summary' => '1']) }}" 
                           class="btn {{ ($isSummary ?? false) ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="fa fa-bars"></i> الملخص
                        </a>
                    </div>
                    <div>
                        <span class="text-muted">
                            <i class="fa fa-calendar"></i>
                            {{ \Carbon\Carbon::parse($fromDate ?? now()->startOfYear())->format('d/m/Y') }} - 
                            {{ \Carbon\Carbon::parse($toDate ?? now())->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary ?? false)
                        {{-- جدول الملخص --}}
                        <div class="table-responsive">
                            <table class="table report reports_currency no-collapse" id="reportTable">
                                <thead>
                                    <tr class="report-results-head">
                                        <th rowspan="2" class="first-column">الكود</th>
                                        <th rowspan="2" class="first-column">مراكز التكلفة</th>
                                        <th colspan="2" class="first-column text-center">العملية</th>
                                        <th colspan="2" class="first-column text-center">الرصيد</th>
                                    </tr>
                                    <tr class="report-results-head">
                                        <th class="first-column">مدين</th>
                                        <th class="first-column">دائن</th>
                                        <th class="first-column">مدين</th>
                                        <th class="first-column">دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData ?? [] as $costCenterId => $costCenterData)
                                        @php 
                                            $ccBalance = $costCenterData['total_debit'] - $costCenterData['total_credit']; 
                                        @endphp
                                        <tr>
                                            <td>{{ $costCenterId }}</td>
                                            <td>{{ $costCenterData['name'] }}</td>
                                            <td>{{ number_format($costCenterData['total_debit'], 2) }}</td>
                                            <td>{{ number_format($costCenterData['total_credit'], 2) }}</td>
                                            <td>{{ $ccBalance > 0 ? number_format($ccBalance, 2) : '-' }}</td>
                                            <td>{{ $ccBalance <= 0 ? number_format(abs($ccBalance), 2) : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="stk-empty">
                                                    <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                    <h6 class="stk-empty__title">لا توجد بيانات للعرض</h6>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($reportData))
                                @php $grandBalance = ($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0); @endphp
                                <tfoot>
                                    <tr class="section-net">
                                        <td class="first-column">العدد الكلي</td>
                                        <td></td>
                                        <td>{{ number_format($totals['total_debit'] ?? 0, 2) }} <span class="sar_symbol">{{ \App\Support\CurrencySettings::symbol() }}</span></td>
                                        <td>{{ number_format($totals['total_credit'] ?? 0, 2) }} <span class="sar_symbol">{{ \App\Support\CurrencySettings::symbol() }}</span></td>
                                        <td>{!! $grandBalance > 0 ? \App\Support\CurrencySettings::number($grandBalance) . ' <span class="sar_symbol">' . e(\App\Support\CurrencySettings::symbol()) . '</span>' : '-' !!}</td>
                                        <td>{!! $grandBalance <= 0 ? \App\Support\CurrencySettings::number(abs($grandBalance)) . ' <span class="sar_symbol">' . e(\App\Support\CurrencySettings::symbol()) . '</span>' : '-' !!}</td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل حركات مراكز التكلفة</h6>
                            <div class="text-muted">
                                الفترة: {{ \Carbon\Carbon::parse($fromDate ?? now()->startOfYear())->format('d/m/Y') }} إلى {{ \Carbon\Carbon::parse($toDate ?? now())->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle">#</th>
                                        <th rowspan="2" class="text-center align-middle">التاريخ</th>
                                        <th colspan="2" class="text-center">الحساب الفرعي</th>
                                        <th rowspan="2" class="text-center align-middle">رقم القيد</th>
                                        <th rowspan="2" class="text-center align-middle">الوصف</th>
                                        <th rowspan="2" class="text-center align-middle">نسبة</th>
                                        <th colspan="2" class="text-center">العملية</th>
                                        <th colspan="2" class="text-center">الرصيد</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">الكود</th>
                                        <th class="text-center">الاسم</th>
                                        <th class="text-center">مدين</th>
                                        <th class="text-center">دائن</th>
                                        <th class="text-center">مدين</th>
                                        <th class="text-center">دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData ?? [] as $costCenterId => $costCenterData)
                                        <!-- رأس مركز التكلفة -->
                                        <tr class="table-secondary">
                                            <td colspan="11"><strong>{{ $costCenterData['name'] }}</strong></td>
                                        </tr>
                                        
                                        @php 
                                            $runningDebitBalance = 0;
                                            $runningCreditBalance = 0;
                                            $entryIndex = 0;
                                        @endphp
                                        @foreach($costCenterData['entries'] as $index => $entry)
                                            @php
                                                $entryIndex++;
                                                $runningDebitBalance += $entry['debit'];
                                                $runningCreditBalance += $entry['credit'];
                                                $netBalance = $runningDebitBalance - $runningCreditBalance;
                                            @endphp
                                            <tr>
                                                <td>{{ $entryIndex }}</td>
                                                <td>{{ \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') }}</td>
                                                <td>{{ $entry['account_code'] }}</td>
                                                <td>{{ $entry['account_name'] }}</td>
                                                <td>
                                                    <a href="{{ route('journal.show', $entry['journal_entry_id']) }}" class="text-primary">
                                                        {{ $entry['reference_number'] ?? $entry['journal_entry_id'] }}
                                                    </a>
                                                </td>
                                                <td>{{ Str::limit($entry['description'] ?? '', 50) }}</td>
                                                <td class="text-center">100%</td>
                                                <td class="text-success fw-bold">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' }}</td>
                                                <td class="text-danger fw-bold">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' }}</td>
                                                <td>{{ $netBalance > 0 ? number_format($netBalance, 2) : '-' }}</td>
                                                <td>{{ $netBalance <= 0 ? number_format(abs($netBalance), 2) : '-' }}</td>
                                            </tr>
                                        @endforeach
                                        
                                        <!-- صف المجموع لمركز التكلفة -->
                                        @php 
                                            $ccBalance = $costCenterData['total_debit'] - $costCenterData['total_credit']; 
                                        @endphp
                                        <tr class="table-light">
                                            <td colspan="7"><strong>المجموع</strong></td>
                                            <td class="text-success"><strong>{{ number_format($costCenterData['total_debit'], 2) }}</strong></td>
                                            <td class="text-danger"><strong>{{ number_format($costCenterData['total_credit'], 2) }}</strong></td>
                                            <td><strong>{{ $ccBalance > 0 ? number_format($ccBalance, 2) : '-' }}</strong></td>
                                            <td><strong>{{ $ccBalance <= 0 ? number_format(abs($ccBalance), 2) : '-' }}</strong></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى اختيار مركز تكلفة والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($reportData))
                                @php $grandBalance = ($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0); @endphp
                                <tfoot>
                                    <tr class="table-warning">
                                        <td colspan="7"><strong>الإجمالي</strong></td>
                                        <td class="text-success"><strong>@money($totals['total_debit'] ?? 0)</strong></td>
                                        <td class="text-danger"><strong>@money($totals['total_credit'] ?? 0)</strong></td>
                                        <td><strong>{{ $grandBalance > 0 ? \App\Support\CurrencySettings::format($grandBalance) : '-' }}</strong></td>
                                        <td><strong>{{ $grandBalance <= 0 ? \App\Support\CurrencySettings::format(abs($grandBalance)) : '-' }}</strong></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        $(document).ready(function() {
            // تهيئة Select2
            if ($.fn.select2) {
                $('.select2').select2({
                    dir: 'rtl',
                    language: {
                        noResults: function() {
                            return "لا توجد نتائج";
                        }
                    },
                    allowClear: true,
                    width: '100%'
                });
            }

            // إعادة تعيين الفلاتر
            $('#resetBtn').click(function() {
                $('#reportForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#date_from').val('{{ now()->startOfYear()->format('Y-m-d') }}');
                $('#date_to').val('{{ now()->format('Y-m-d') }}');
                $('#reportForm').submit();
            });

            // طباعة التقرير
            $('#printBtn').click(function() {
                printCostCentersReport();
            });

            // تصدير إلى Excel
            $('#exportExcel').click(function() {
                exportCostCentersReport();
            });

            // الرسم البياني
            @if(!empty($reportData))
            initCharts();
            @endif
        });

        // تهيئة الرسوم البيانية
        function initCharts() {
            const chartLabels = [];
            const debitData = [];
            const creditData = [];
            const backgroundColors = [
                '#23b7e5', '#0abb87', '#fd7e14', '#e83e8c', '#6f42c1',
                '#20c997', '#17a2b8', '#ffc107', '#dc3545', '#28a745'
            ];

            @foreach($reportData ?? [] as $costCenterId => $costCenterData)
                chartLabels.push('{{ $costCenterData["name"] }}');
                debitData.push({{ $costCenterData['total_debit'] }});
                creditData.push({{ $costCenterData['total_credit'] }});
            @endforeach

            // الرسم البياني الشريطي
            const barCtx = document.getElementById('costCenterBarChart');
            if (barCtx) {
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [
                            {
                                label: 'مدين',
                                data: debitData,
                                backgroundColor: '#23b7e5',
                                borderColor: '#23b7e5',
                                borderWidth: 1
                            },
                            {
                                label: 'دائن',
                                data: creditData,
                                backgroundColor: '#0abb87',
                                borderColor: '#0abb87',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'مراكز التكلفة - مقارنة المدين والدائن'
                            },
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('en-US');
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // الرسم البياني الدائري
            const pieCtx = document.getElementById('costCenterPieChart');
            if (pieCtx) {
                const netBalances = creditData.map((credit, index) => Math.abs(credit - debitData[index]));
                
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            data: netBalances,
                            backgroundColor: backgroundColors.slice(0, chartLabels.length),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'توزيع الأرصدة'
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }
        }

        // تنسيق الأرقام
        function formatNumber(number) {
            return parseFloat(number || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // =====================================
        // تصدير Excel باستخدام الملف المساعد
        // =====================================
        function exportCostCentersReport() {
            var totals = {
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                balance: {{ $totals['balance'] ?? 0 }},
                count: {{ $totals['count'] ?? 0 }}
            };

            var fromDate = '{{ $fromDate ?? now()->startOfYear()->format("Y-m-d") }}';
            var toDate = '{{ $toDate ?? now()->format("Y-m-d") }}';

            // جمع بيانات الجدول
            var tableData = [];
            
            @if(!empty($reportData))
            @foreach($reportData as $costCenterId => $costCenterData)
                // إضافة رأس لكل مركز تكلفة
                tableData.push({
                    values: [
                        '{{ $costCenterData["name"] }}',
                        '',
                        '',
                        formatNumber({{ $costCenterData['total_debit'] }}),
                        formatNumber({{ $costCenterData['total_credit'] }}),
                        formatNumber({{ $costCenterData['total_credit'] - $costCenterData['total_debit'] }})
                    ],
                    cellStyles: [
                        { bold: true, color: 'primary' },
                        null,
                        null,
                        { bold: true, color: 'success' },
                        { bold: true, color: 'danger' },
                        { bold: true }
                    ]
                });
                
                // إضافة بيانات المدخلات
                @foreach($costCenterData['entries'] as $entry)
                    tableData.push({
                        values: [
                            '  {{ $entry["account_name"] ?? "" }}',
                            '{{ $entry["date"] }}',
                            '{{ addslashes($entry["description"] ?? "") }}',
                            formatNumber({{ $entry['debit'] }}),
                            formatNumber({{ $entry['credit'] }}),
                            ''
                        ]
                    });
                @endforeach
            @endforeach
            @endif

            // إعداد التقرير
            var config = {
                fileName: 'تقرير_مراكز_التكلفة',
                mainTitle: 'تقرير مراكز التكلفة',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': 'من ' + fromDate + ' إلى ' + toDate,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي المدين',
                        value: formatNumber(totals.total_debit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي الدائن',
                        value: formatNumber(totals.total_credit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'الرصيد',
                        value: formatNumber(Math.abs(totals.balance)),
                        unit: ' ' + window.AppCurrency.symbol + ' ' + (totals.balance >= 0 ? '(دائن)' : '(مدين)'),
                        titleColor: 'warning',
                        valueColor: totals.balance >= 0 ? 'danger' : 'success',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'عدد مراكز التكلفة',
                        value: totals.count.toString(),
                        unit: ' مركز',
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'تفاصيل مراكز التكلفة',
                tableHeaders: ['الحساب / مركز التكلفة', 'التاريخ', 'الوصف', 'مدين', 'دائن', 'الرصيد'],
                columnWidths: [30, 12, 30, 12, 12, 12],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'الإجمالي',
                        values: [
                            'الإجمالي',
                            '',
                            '',
                            formatNumber(totals.total_debit),
                            formatNumber(totals.total_credit),
                            formatNumber(Math.abs(totals.balance))
                        ],
                        color: 'primary',
                        cellStyles: [
                            { bold: true },
                            null,
                            null,
                            { color: 'success', bold: true },
                            { color: 'danger', bold: true },
                            { bold: true }
                        ]
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص مراكز التكلفة',
                    tables: [
                        {
                            title: 'إحصائيات مراكز التكلفة',
                            data: [
                                { label: 'إجمالي المدين', value: formatNumber(totals.total_debit), unit: window.AppCurrency.symbol },
                                { label: 'إجمالي الدائن', value: formatNumber(totals.total_credit), unit: window.AppCurrency.symbol },
                                { label: 'صافي الرصيد', value: formatNumber(Math.abs(totals.balance)), unit: window.AppCurrency.symbol, highlight: true },
                                { label: 'عدد مراكز التكلفة', value: totals.count.toString(), unit: 'مركز' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير مراكز التكلفة بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }

        // =====================================
        // طباعة التقرير
        // =====================================
        function printCostCentersReport() {
            printTable({
                tableSelector: '#reportCard tbody tr',
                tfootSelector: null,
                title: 'تقرير مراكز التكلفة',
                infoCards: [
                    { label: 'إجمالي المدين', value: '@money($totals["total_debit"] ?? 0)', class: 'text-success' },
                    { label: 'إجمالي الدائن', value: '@money($totals["total_credit"] ?? 0)', class: 'text-danger' },
                    { label: 'الرصيد', value: '@money(abs($totals["balance"] ?? 0)) {{ ($totals["balance"] ?? 0) >= 0 ? "(دائن)" : "(مدين)" }}' },
                    { label: 'عدد مراكز التكلفة', value: '{{ $totals["count"] ?? 0 }} مركز' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate ?? now()->startOfYear()->format("Y-m-d") }} إلى {{ $toDate ?? now()->format("Y-m-d") }}'
                },
                tableHeaders: ['مركز التكلفة / الحساب', 'التاريخ', 'الوصف', 'مدين', 'دائن', 'الرصيد'],
                columnWidths: ['25%', '10%', '30%', '12%', '12%', '11%'],
                descriptionColumn: 3,
                windowWidth: 1400,
                windowHeight: 900
            });
        }

        // تصدير إلى Excel (الطريقة القديمة - للتوافق العكسي)
        function exportToExcel() {
            const wb = XLSX.utils.book_new();

            // Summary data
            const summaryData = [
                ['تقرير مراكز التكلفة'],
                ['التاريخ:', 'من {{ $fromDate ?? now()->startOfYear()->format("Y-m-d") }} إلى {{ $toDate ?? now()->format("Y-m-d") }}'],
                [''],
                ['البيان', 'المبلغ (ريال)'],
                ['إجمالي المدين', '{{ number_format($totals["total_debit"] ?? 0, 2) }}'],
                ['إجمالي الدائن', '{{ number_format($totals["total_credit"] ?? 0, 2) }}'],
                ['صافي الرصيد', '{{ number_format(abs($totals["balance"] ?? 0), 2) }}'],
                ['عدد مراكز التكلفة', '{{ $totals["count"] ?? 0 }}']
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Details data
            @if(!empty($reportData))
            @foreach($reportData as $costCenterId => $costCenterData)
            const details{{ $costCenterId }} = [
                ['{{ $costCenterData["name"] }}'],
                ['المعرف', 'التاريخ', 'كود الحساب', 'اسم الحساب', 'رقم القيد', 'الوصف', 'نسبة', 'مدين', 'دائن', 'رصيد مدين', 'رصيد دائن'],
                @foreach($costCenterData['entries'] as $index => $entry)
                ['{{ $entry["journal_entry_id"] }}', '{{ $entry["date"] }}', '{{ $entry["account_code"] ?? "" }}', '{{ $entry["account_name"] ?? "" }}', '{{ $entry["reference_number"] ?? $entry["journal_entry_id"] }}', '{{ addslashes($entry["description"] ?? "") }}', '100%', {{ $entry['debit'] }}, {{ $entry['credit'] }}, '', ''],
                @endforeach
                ['المجموع', '', '', '', '', '', '', {{ $costCenterData['total_debit'] }}, {{ $costCenterData['total_credit'] }}, '', {{ $costCenterData['total_credit'] - $costCenterData['total_debit'] }}]
            ];
            const ws{{ $costCenterId }} = XLSX.utils.aoa_to_sheet(details{{ $costCenterId }});
            XLSX.utils.book_append_sheet(wb, ws{{ $costCenterId }}, '{{ Str::limit($costCenterData["name"], 20) }}');
            @endforeach
            @endif

            // Save file
            const today = new Date();
            const fileName = `تقرير_مراكز_التكلفة_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
        }
    </script>
@endsection
