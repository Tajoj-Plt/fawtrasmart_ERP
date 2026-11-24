@extends('account::master')

@section('title')
    تقارير الشيكات
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقارير الشيكات',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'التقارير' => route('GeneralAccountReports.index'),
            'تقارير الشيكات' => null,
        ],
    ])

    <div class="content-body">
        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-download"></i></span>
                <span class="stk-metric__label">إجمالي الشيكات المستلمة — {{ $receivedStats['total_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($receivedStats['total_amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-upload"></i></span>
                <span class="stk-metric__label">إجمالي الشيكات المدفوعة — {{ $payableStats['total_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($payableStats['total_amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                <span class="stk-metric__label">الشيكات المتأخرة — {{ ($receivedStats['overdue_count'] ?? 0) + ($payableStats['overdue_count'] ?? 0) }} شيك</span>
                <span class="stk-metric__value">{{ number_format(($receivedStats['overdue_amount'] ?? 0) + ($payableStats['overdue_amount'] ?? 0), 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">تستحق اليوم — {{ ($receivedStats['due_today_count'] ?? 0) + ($payableStats['due_today_count'] ?? 0) }} شيك</span>
                <span class="stk-metric__value">{{ number_format(($receivedStats['due_today_amount'] ?? 0) + ($payableStats['due_today_amount'] ?? 0), 2) }}</span>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary mr-1">لوحة تحكم الشيكات</span>
                        <span class="text-muted">نظرة شاملة على جميع الشيكات المستلمة والمدفوعة</span>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card mt-2">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fa fa-chart-bar"></i> تحليل الشيكات الشهري</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge bg-light text-dark-success mr-2">
                        <i class="fa fa-circle"></i> الشيكات المستلمة
                    </span>
                    <span class="badge bg-light text-dark-danger">
                        <i class="fa fa-circle"></i> الشيكات المدفوعة
                    </span>
                </div>
            </div>
        </div>

        <!-- Reports Navigation Cards -->
        <div class="row mt-2">
            {{-- Received Cheques Card --}}
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title"><i class="fa fa-file-invoice-dollar text-success"></i> الشيكات المستلمة</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <td><i class="fa fa-hourglass-half text-warning"></i> قيد الانتظار</td>
                                        <td class="text-center">{{ $receivedStats['pending_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold">{{ number_format($receivedStats['pending_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-university text-info"></i> برسم التحصيل</td>
                                        <td class="text-center">{{ $receivedStats['under_collection_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold">{{ number_format($receivedStats['under_collection_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-check-circle text-success"></i> محصّلة</td>
                                        <td class="text-center">{{ $receivedStats['collected_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold text-success">{{ number_format($receivedStats['collected_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-times-circle text-danger"></i> مرفوضة</td>
                                        <td class="text-center">{{ $receivedStats['rejected_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold text-danger">{{ number_format($receivedStats['rejected_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td><strong>هذا الشهر</strong></td>
                                        <td class="text-center"><strong>{{ $receivedStats['month_count'] ?? 0 }}</strong></td>
                                        <td class="text-left"><strong class="text-success">{{ number_format($receivedStats['month_amount'] ?? 0, 2) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="text-right mt-2">
                            <a href="{{ route('checksReports.received') }}" class="btn btn-success">
                                <i class="fa fa-chart-line"></i> عرض التقرير التفصيلي
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payable Cheques Card --}}
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title"><i class="fa fa-money-check-alt text-danger"></i> الشيكات المدفوعة</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <tbody>
                                    <tr>
                                        <td><i class="fa fa-paper-plane text-primary"></i> صادرة</td>
                                        <td class="text-center">{{ $payableStats['issued_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold">{{ number_format($payableStats['issued_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-check-circle text-success"></i> مصروفة</td>
                                        <td class="text-center">{{ $payableStats['cashed_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold text-success">{{ number_format($payableStats['cashed_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-times-circle text-danger"></i> مرفوضة</td>
                                        <td class="text-center">{{ $payableStats['rejected_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold text-danger">{{ number_format($payableStats['rejected_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-ban text-secondary"></i> ملغاة</td>
                                        <td class="text-center">{{ $payableStats['cancelled_count'] ?? 0 }}</td>
                                        <td class="text-left font-weight-bold text-secondary">{{ number_format($payableStats['cancelled_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td><strong>هذا الشهر</strong></td>
                                        <td class="text-center"><strong>{{ $payableStats['month_count'] ?? 0 }}</strong></td>
                                        <td class="text-left"><strong class="text-danger">{{ number_format($payableStats['month_amount'] ?? 0, 2) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="text-right mt-2">
                            <a href="{{ route('checksReports.deliveredChecks') }}" class="btn btn-danger">
                                <i class="fa fa-chart-line"></i> عرض التقرير التفصيلي
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Due Today & Overdue Tables -->
        <div class="row mt-2">
            {{-- الشيكات المستحقة اليوم --}}
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title"><i class="fa fa-bell text-warning"></i> الشيكات المستحقة اليوم</h6>
                    </div>
                    <div class="card-body p-0">
                        @if(count($dueTodayReceived) > 0 || count($dueTodayPayable) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>النوع</th>
                                            <th>رقم الشيك</th>
                                            <th>المبلغ</th>
                                            <th>الجهة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dueTodayReceived as $cheque)
                                            <tr>
                                                <td><span class="badge bg-success">مستلم</span></td>
                                                <td>{{ $cheque->cheque_number }}</td>
                                                <td class="font-weight-bold">{{ number_format($cheque->amount, 2) }}</td>
                                                <td>{{ $cheque->client->name ?? $cheque->payee_name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($dueTodayPayable as $cheque)
                                            <tr>
                                                <td><span class="badge bg-danger">مدفوع</span></td>
                                                <td>{{ $cheque->cheque_number }}</td>
                                                <td class="font-weight-bold">{{ number_format($cheque->amount, 2) }}</td>
                                                <td>{{ $cheque->recipientAccount->name ?? $cheque->payee_name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="fa fa-check-circle text-success fa-3x mb-2"></i>
                                <p class="text-muted mb-0">لا توجد شيكات مستحقة اليوم</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- الشيكات المتأخرة --}}
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title"><i class="fa fa-exclamation-circle text-danger"></i> الشيكات المتأخرة</h6>
                    </div>
                    <div class="card-body p-0">
                        @if(count($overdueReceived) > 0 || count($overduePayable) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>النوع</th>
                                            <th>رقم الشيك</th>
                                            <th>المبلغ</th>
                                            <th>تاريخ الاستحقاق</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($overdueReceived as $cheque)
                                            <tr class="table-danger">
                                                <td><span class="badge bg-success">مستلم</span></td>
                                                <td>{{ $cheque->cheque_number }}</td>
                                                <td class="font-weight-bold">{{ number_format($cheque->amount, 2) }}</td>
                                                <td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '-' }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($overduePayable as $cheque)
                                            <tr class="table-danger">
                                                <td><span class="badge bg-danger">مدفوع</span></td>
                                                <td>{{ $cheque->cheque_number }}</td>
                                                <td class="font-weight-bold">{{ number_format($cheque->amount, 2) }}</td>
                                                <td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="fa fa-check-circle text-success fa-3x mb-2"></i>
                                <p class="text-muted mb-0">لا توجد شيكات متأخرة</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Week Stats -->
        <div class="row mt-2">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">شيكات مستلمة تستحق خلال أسبوع — {{ $receivedStats['upcoming_week_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($receivedStats['upcoming_week_amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">شيكات مدفوعة تستحق خلال أسبوع — {{ $payableStats['upcoming_week_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($payableStats['upcoming_week_amount'] ?? 0, 2) }}</span>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthlyChartData = @json($monthlyChartData);
        
        const ctx = document.getElementById('monthlyChart');
        if (!ctx) return;
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyChartData.labels,
                datasets: [
                    {
                        label: 'الشيكات المستلمة',
                        data: monthlyChartData.received,
                        backgroundColor: 'rgba(40, 199, 111, 0.7)',
                        borderColor: 'rgba(40, 199, 111, 1)',
                        borderWidth: 1,
                        order: 2
                    },
                    {
                        label: 'الشيكات المدفوعة',
                        data: monthlyChartData.payable,
                        backgroundColor: 'rgba(234, 84, 85, 0.7)',
                        borderColor: 'rgba(234, 84, 85, 1)',
                        borderWidth: 1,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { size: 12 },
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatNumber(context.raw) + ' ' + window.AppCurrency.symbol;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatNumber(value) + ' ' + window.AppCurrency.symbol;
                            }
                        }
                    }
                }
            }
        });
    });

    function formatNumber(n) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
</script>
@endsection
