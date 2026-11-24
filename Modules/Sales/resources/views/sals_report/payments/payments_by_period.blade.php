@extends('sales::master')

@section('title')
    تقرير المدفوعات حسب الفترة الزمنية
@stop

{{--
    القسم هنا `css` لا `styles`: `sales::master` يكدّس `styles` بـ `@stack`
    ولا يلتقط الأقسام، ويمرّر `@yield('css')` عبر `layouts.head_rtl` — فكان
    كل CSS هذه الشاشة يسقط صامتاً ولا يصل إلى الصفحة.
--}}
@section('css')
@include('sales::sals_report.report_partials.styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
<style>
    .period-tabs {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    .stat-card {
        border-radius: 12px;
        box-shadow: 0 4px 20px 0 rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .income-card { border-right: 4px solid #28c76f; }
    .expense-card { border-right: 4px solid #ea5455; }
    .account-card { border-right: 4px solid #ff9f43; }
    .treasury-card { border-right: 4px solid #7367f0; }
    
    @media print {
        .no-print { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .content-body { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endsection

@section('content')

    {{-- من أجل `.sl-guide` وحدها؛ شاشاتُ التقارير على ورقة `rpt-*` الخاصّة
         بها ولا تتقاطع معها في محدِّد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- ══════════ إرشادُ الشاشة ══════════
         مطويّةٌ افتراضياً: من فتح تقريراً جاء لأرقامه --}}
    <div class="no-print">
        @include('sales::components.guide', [
            'key' => 'sales-reports.payments-by-period',
            'title' => __('sales_reports.guide.hub.title'),
            'lead' => __('sales_reports.guide.hub.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_reports.guide.hub.a'),
                    __('sales_reports.guide.hub.b'),
                    __('sales_reports.guide.hub.c'),
                    __('sales_reports.guide.hub.d'),
                ],
            ],
        ])
    </div>
    <div class="content-header row no-print">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير المدفوعات حسب الفترة الزمنية</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('salesReports.index') }}">التقارير</a></li>
                            <li class="breadcrumb-item active">المدفوعات حسب الفترة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body rpt">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('salesReports.paymentsByPeriod') }}" id="filterForm">
                        <input type="hidden" name="period_type" id="periodTypeInput" value="{{ $periodType }}">
            
                    <div class="row">
                        <div class="col-md-12">
                            <div class="period-tabs">
                                <a href="{{ route('salesReports.paymentsByPeriod', ['period_type' => 'daily', 'employee_id' => request('employee_id'), 'client_id' => request('client_id'), 'branch_id' => request('branch_id')]) }}" 
                                   class="btn {{ $periodType == 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-day"></i> يومي
                                </a>
                                <a href="{{ route('salesReports.paymentsByPeriod', ['period_type' => 'weekly', 'employee_id' => request('employee_id'), 'client_id' => request('client_id'), 'branch_id' => request('branch_id')]) }}" 
                                   class="btn {{ $periodType == 'weekly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-week"></i> أسبوعي
                                </a>
                                <a href="{{ route('salesReports.paymentsByPeriod', ['period_type' => 'monthly', 'employee_id' => request('employee_id'), 'client_id' => request('client_id'), 'branch_id' => request('branch_id')]) }}" 
                                   class="btn {{ $periodType == 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-alt"></i> شهري
                                </a>
                                <a href="{{ route('salesReports.paymentsByPeriod', ['period_type' => 'yearly', 'employee_id' => request('employee_id'), 'client_id' => request('client_id'), 'branch_id' => request('branch_id')]) }}" 
                                   class="btn {{ $periodType == 'yearly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar"></i> سنوي
                                </a>
                            </div>
                        </div>
                    </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>من تاريخ</label>
                                <input type="date" name="date_from" class="form-control" value="{{ $fromDate->format('Y-m-d') }}">
                            </div>

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="{{ $toDate->format('Y-m-d') }}">
                            </div>

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الفرع</label>
                                <select name="branch_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الموظف</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>العميل</label>
                                <select name="client_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name ?? $client->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الخزينة</label>
                                <select name="treasury_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($treasuries as $treasury)
                                        {{-- القيمةُ معرّفُ الحساب: هو ما يحفظه عمود السند --}}
                                        <option value="{{ $treasury->account_id }}" {{ request('treasury_id') == $treasury->account_id ? 'selected' : '' }}>
                                            {{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع العرض</label>
                                <div class="btn-group d-block" role="group">
                                    <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa fa-list"></i> الملخص
                                    </button>
                                    <button type="submit" name="view_type" value="details" class="btn {{ !$isSummary ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa fa-th-list"></i> التفاصيل
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-6 col-sm-6 mb-1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-search"></i> بحث
                                </button>
                                <a href="{{ route('salesReports.paymentsByPeriod') }}" class="btn btn-secondary">
                                    <i class="fa fa-refresh"></i> إعادة تعيين
                                </a>
                            </div>

                            <div class="col-md-6 col-sm-6 mb-1 text-right">
                                <button type="button" class="btn btn-success" onclick="window.print()">
                                    <i class="fa fa-print"></i> طباعة
                                </button>
                                <button type="button" class="btn btn-info" onclick="exportToExcel()">
                                    <i class="fa fa-file-excel-o"></i> Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-6 col-md-6 col-12">
                <div class="card stat-card income-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-check-circle text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">{{ $totals['count'] }}</h2>
                        <p class="mb-0">عدد عمليات التحصيل</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-12">
                <div class="card stat-card treasury-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-dollar-sign text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">{{ number_format($totals['amount'], 2) }}</h2>
                        <p class="mb-0">إجمالي المبلغ المحصل (ريال)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        @if(count($reportData) > 0)
        <div class="card mt-2 no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-chart-bar"></i> الرسم البياني للمدفوعات ({{ $periodTypes[$periodType] }})</h4>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:400px;">
                    <canvas id="paymentsChart"></canvas>
                </div>
            </div>
        </div>
        @endif

        <!-- Report Table -->
        <div class="card mt-2">
            <div class="card-header">
                <h4 class="card-title">بيانات التقرير</h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover-animation mb-0" id="reportTable">
                            <thead>
                                @if($isSummary)
                                    <tr>
                                        <th>الفترة</th>
                                        <th>عدد العمليات</th>
                                        <th>إجمالي المبلغ</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th>تاريخ التحصيل</th>
                                        <th>العميل / الموظف</th>
                                        <th>الخزينة / الفرع</th>
                                        <th>رقم الفاتورة</th>
                                        <th>طريقة الدفع</th>
                                        <th>المبلغ</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @forelse($reportData as $periodKey => $period)
                                    @if($isSummary)
                                        <tr class="table-primary">
                                            <td class="text-bold-600">{{ $period['name'] }}</td>
                                            <td>{{ $period['count'] }}</td>
                                            <td class="text-success text-bold-600">{{ number_format($period['amount'], 2) }}</td>
                                        </tr>
                                    @else
                                        <tr class="table-light">
                                            <td colspan="6" class="text-bold-600">{{ $period['name'] }} ({{ $period['count'] }} عملية تحصيل)</td>
                                        </tr>
                                        @foreach($period['items'] as $payment)
                                            <tr>
                                                <td>
                                                    {{ Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}<br>
                                                    <small class="text-muted">{{ Carbon\Carbon::parse($payment->payment_date)->format('H:i') }}</small>
                                                </td>
                                                <td>
                                                    {{ $payment->client->trade_name ?? '-' }}<br>
                                                    <small class="text-muted">{{ $payment->employee->name ?? '-' }}</small>
                                                </td>
                                                <td>
                                                    {{ $payment->treasury_name ?: '-' }}<br>
                                                    <small class="text-muted">{{ $payment->branch->name ?? '-' }}</small>
                                                </td>
                                                <td>
                                                    @if($payment->invoice)
                                                        <a href="{{ route('sales.invoices.show', $payment->invoice->id) }}" target="_blank">
                                                            {{ $payment->invoice->reference_number }}
                                                        </a>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $payment->payment_method_name ?? '-' }}
                                                </td>
                                                <td class="text-success text-bold-600">
                                                    {{ number_format($payment->amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="table-secondary">
                                            <td colspan="{{ $isSummary ? 2 : 5 }}" class="text-right text-bold-600">إجمالي {{ $period['name'] }}</td>
                                            <td class="text-bold-600 text-success">{{ number_format($period['amount'], 2) }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">لا توجد بيانات للفترة المحددة</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($reportData) > 0)
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="{{ $isSummary ? 1 : 5 }}" class="text-right text-bold-700">الإجمالي العام</td>
                                    @if($isSummary)
                                        <td class="text-bold-700">{{ $totals['count'] }}</td>
                                    @endif
                                    <td class="text-bold-700">{{ number_format($totals['amount'], 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    @if(count($reportData) > 0)
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('paymentsChart').getContext('2d');
        const chartData = @json($chartData);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'المبالغ المحصلة',
                        data: chartData.amounts,
                        borderColor: '#28c76f',
                        backgroundColor: 'rgba(40, 199, 111, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#28c76f',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#28c76f',
                        pointRadius: 5,
                        pointHoverRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' ' + window.AppCurrency.symbol;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toLocaleString() + ' ' + window.AppCurrency.symbol;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
    @endif

    function exportToExcel() {
        alert('جاري البدء في تصدير ملف Excel...');
    }
</script>
@endsection
