@extends('onlinestore::master')

@section('title', 'إحصائيات البريد الإلكتروني')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <div class="btn-group">
                <a href="{{ route('email_logs.statistics', ['period' => 'week']) }}"
                   class="btn {{ $period == 'week' ? 'btn-primary' : 'btn-outline-primary' }}">
                    أسبوع
                </a>
                <a href="{{ route('email_logs.statistics', ['period' => 'month']) }}"
                   class="btn {{ $period == 'month' ? 'btn-primary' : 'btn-outline-primary' }}">
                    شهر
                </a>
                <a href="{{ route('email_logs.statistics', ['period' => 'year']) }}"
                   class="btn {{ $period == 'year' ? 'btn-primary' : 'btn-outline-primary' }}">
                    سنة
                </a>
            </div>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إحصائيات البريد الإلكتروني',
            'icon' => 'bar-chart-2',
            'sub' => 'منذ ' . $startDate->format('Y-m-d'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'سجلات البريد' => route('email_logs.index'),
                'الإحصائيات' => null,
            ],
        ])

        {{-- معدل النجاح وحالات الإرسال --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">معدل النجاح</span>
                <span class="stk-metric__value">{{ number_format($successRate, 1) }}%</span>
            </div>
            @foreach ($statusStats as $stat)
                @php
                    $statusEnum = $stat->status instanceof \App\Enums\EmailStatus ? $stat->status : \App\Enums\EmailStatus::tryFrom((string) $stat->status);
                @endphp
                <div class="stk-metric">
                    <span class="stk-metric__icon">
                        <i class="feather icon-{{ $stat->status == 'sent' ? 'check-circle' : ($stat->status == 'failed' ? 'x-circle' : 'clock') }}"></i>
                    </span>
                    <span class="stk-metric__label">{{ $statusEnum ? $statusEnum->label() : $stat->status }}</span>
                    <span class="stk-metric__value">{{ number_format($stat->count) }}</span>
                </div>
            @endforeach
        </div>

        <div class="row">
            {{-- إحصائيات يومية --}}
            <div class="col-md-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> الإرسال اليومي</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            {{-- أكثر القوالب استخداماً --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أكثر القوالب استخداماً</h6>
                    </div>
                    <div class="card-body p-0">
                        @if ($templateStats->count())
                            <div class="list-group list-group-flush">
                                @foreach ($templateStats as $stat)
                                    @if ($stat->template && hasPermission('online_store.email_templates.view'))
                                        <a href="{{ route('email_templates.show', $stat->template) }}"
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <strong>{{ $stat->template->template_name }}</strong>
                                            <span class="badge badge-light-primary">{{ number_format($stat->count) }}</span>
                                        </a>
                                    @else
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <strong>{{ $stat->template?->template_name ?? 'قالب محذوف' }}</strong>
                                            <span class="badge badge-light-primary">{{ number_format($stat->count) }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="stk-empty">
                                <p class="stk-empty__hint mb-0">لا توجد بيانات</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- رسم بياني للحالات --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> توزيع الحالات</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملخص الفترة</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الفترة</dt>
                                <dd>
                                    @switch($period)
                                        @case('week') آخر أسبوع @break
                                        @case('month') آخر شهر @break
                                        @case('year') آخر سنة @break
                                    @endswitch
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ البداية</dt>
                                <dd class="stk-num">{{ $startDate->format('Y-m-d') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>إجمالي الرسائل</dt>
                                <dd class="stk-num">{{ $statusStats->sum('count') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>معدل النجاح</dt>
                                <dd>{{ number_format($successRate, 1) }}%</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>متوسط الإرسال اليومي</dt>
                                <dd>
                                    @php
                                        $days = max(1, $dailyStats->count());
                                        $avg = $statusStats->sum('count') / $days;
                                    @endphp
                                    {{ number_format($avg, 1) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // الرسم البياني اليومي
    const dailyData = @json($dailyStats);
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');

    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [
                {
                    label: 'تم الإرسال',
                    data: dailyData.map(d => d.sent),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'فشل',
                    data: dailyData.map(d => d.failed),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // الرسم البياني للحالات
    const statusData = @json($statusStats);
    const statusCtx = document.getElementById('statusChart').getContext('2d');

    const statusColors = {
        'sent': '#28a745',
        'failed': '#dc3545',
        'pending': '#ffc107'
    };

    const statusLabels = {
        'sent': 'تم الإرسال',
        'failed': 'فشل',
        'pending': 'في الانتظار'
    };

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(s => statusLabels[s.status] || s.status),
            datasets: [{
                data: statusData.map(s => s.count),
                backgroundColor: statusData.map(s => statusColors[s.status] || '#6c757d'),
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
});
</script>
@endsection
