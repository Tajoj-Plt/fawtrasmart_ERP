@extends('hr::master')

@section('title', 'مسير صرف #' . $batch->id)

@php $money = fn ($v) => \App\Support\CurrencySettings::format($v); @endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [إجراء سير العمل] … --}}
    @if ($batch->isPending() && hasPermission('payroll.approve'))
        <form method="POST" action="{{ route('hr.payroll-batches.approve', $batch->id) }}" class="d-inline"
              onsubmit="return confirm('اعتماد صرفات هذا المسير؟ تصير «معتمدة — بانتظار الصرف».');">
            @csrf
            <button class="btn btn-success"><i class="fa fa-check"></i> اعتماد المسير</button>
        </form>
    @endif
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => $batch->name,
        'icon' => 'layers',
        'crumbs' => [
            'مسير الصرفات' => route('hr.payroll-batches.index'),
            $batch->name => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
                    <dl class="stk-def stk-def--compact mb-0 flex-grow-1">
                        <div class="stk-def__row"><dt>حال الصرف</dt><dd><span class="stk-state stk-state--{{ $state['tone'] }}">{{ $state['label'] }}</span></dd></div>
                        <div class="stk-def__row"><dt>الفترة</dt><dd class="stk-num">{{ $batch->period_from ? $batch->period_from->format('Y-m-d') . ' → ' . optional($batch->period_to)->format('Y-m-d') : '—' }}</dd></div>
                        <div class="stk-def__row"><dt>مسير الرواتب</dt><dd>{{ optional($batch->payroll)->name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>أنشأه</dt><dd>{{ optional($batch->creator)->name ?? '—' }}</dd></div>
                        <div class="stk-def__row"><dt>الاعتماد</dt><dd>{{ $batch->approved_at ? $batch->approved_at->format('Y-m-d H:i') . ' — ' . optional($batch->approver)->name : '—' }}</dd></div>
                        @if ($batch->notes)
                            <div class="stk-def__row"><dt>ملاحظات</dt><dd>{{ $batch->notes }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__label">الصرفات</span>
                    <span class="stk-metric__value stk-num">{{ $state['total'] }}</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__label">مدفوعة</span>
                    <span class="stk-metric__value stk-num">{{ $state['paid'] }}</span>
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__label">لم تُصرف بعد</span>
                    <span class="stk-metric__value stk-num">{{ $state['open'] }}</span>
                </div>
                <div class="stk-metric stk-metric--info">
                    <span class="stk-metric__label">صافي المدفوع</span>
                    <span class="stk-metric__value stk-num">{{ $money($visibleRuns->where('status', 'disbursed')->sum('net_salary')) }}</span>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> صرفات المسير
                    <span class="stk-count">{{ $visibleRuns->count() }}</span></h6>
                @if ($hiddenCount > 0)
                    <small class="text-muted">{{ $hiddenCount }} صرفة خارج نطاقك غير معروضة</small>
                @endif
            </div>
            @if ($visibleRuns->isEmpty())
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                        <h5 class="stk-empty__title">لا صرفات</h5>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>السجل</th>
                                <th>الموظف</th>
                                <th>الفترة</th>
                                <th class="text-end">الصافي</th>
                                <th class="text-center">المرحلة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($visibleRuns as $run)
                                <tr>
                                    <td>
                                        @if (hasPermission('disbursements.show'))
                                            <a href="{{ route('payroll-runs.show', $run->id) }}" class="stk-strong">{{ $run->code ?: '#' . $run->id }}</a>
                                        @else
                                            {{ $run->code ?: '#' . $run->id }}
                                        @endif
                                    </td>
                                    <td>{{ trim(($run->employee->first_name ?? '') . ' ' . ($run->employee->middle_name ?? '')) ?: '—' }}</td>
                                    <td class="stk-num">{{ optional($run->period_from)->format('Y-m-d') }} → {{ optional($run->period_to)->format('Y-m-d') }}</td>
                                    <td class="text-end stk-num">{{ $run->isDisbursed() ? $money($run->net_salary) : '—' }}</td>
                                    <td class="text-center"><span class="stk-state stk-state--{{ $run->stage_tone }}">{{ $run->stage_label }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
