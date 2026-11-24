@extends('hr::master')

@section('title', 'سجل عمليات الخصمية ' . ($deduction->code ?: '#' . $deduction->id))

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $empName = trim(($deduction->employee->first_name ?? '') . ' ' . ($deduction->employee->middle_name ?? '')) ?: '—';
    $dots = [
        'created' => ['log', 'plus'], 'updated' => ['log', 'edit-2'], 'approved' => ['in', 'check'],
        'rejected' => ['out', 'x'], 'cancelled' => ['out', 'slash'],
        'settled' => ['in', 'check-circle'], 'partially_settled' => ['in', 'percent'],
    ];
    $fieldLabels = [
        'employee_id' => 'الموظف', 'deduction_type' => 'النوع', 'amount' => 'المبلغ',
        'date' => 'التاريخ', 'reason' => 'السبب', 'notes' => 'ملاحظات',
    ];
@endphp

@section('content')
    @include('stock::components.page-head', [
        'title' => 'سجل عمليات الخصمية ' . ($deduction->code ?: '#' . $deduction->id),
        'icon' => 'clock',
        'crumbs' => [
            'الخصميات' => route('hr.deductions.index'),
            ($deduction->code ?: '#' . $deduction->id) => route('hr.deductions.show', $deduction->id),
            'السجل' => null,
        ],
        'actions' => '<a href="' . route('hr.deductions.show', $deduction->id) . '" class="btn btn-outline-secondary"><i class="feather icon-arrow-right"></i> عودة للتفاصيل</a>',
    ])

    <div class="content-body">
        <div class="card">
            <div class="card-body">
                <div class="stk-def stk-def--inline">
                    <span class="stk-def__row"><i class="feather icon-user"></i> <b>{{ $empName }}</b></span>
                    <span class="stk-def__row"><i class="feather icon-tag"></i> {{ $deduction->type_label }}</span>
                    <span class="stk-def__row"><i class="feather icon-dollar-sign"></i> <b class="stk-num">{{ $money($deduction->amount) }}</b></span>
                    <span class="stk-def__row">{{ $deduction->status_label }}</span>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> العمليات
                    <span class="stk-count">{{ $deduction->histories->count() }}</span></h6>
            </div>
            <div class="card-body">
                @if ($deduction->histories->isEmpty())
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                        <h5 class="stk-empty__title">لا توجد عمليات مسجّلة بعد</h5>
                    </div>
                @else
                    <ul class="stk-timeline">
                        @foreach ($deduction->histories as $h)
                            @php [$dot, $icon] = $dots[$h->action] ?? ['log', 'circle']; @endphp
                            <li class="stk-timeline__item">
                                <span class="stk-timeline__dot stk-timeline__dot--{{ $dot }}"><i class="feather icon-{{ $icon }}"></i></span>
                                <div class="stk-timeline__body">
                                    <div class="stk-timeline__head">
                                        <span class="stk-tag">{{ $h->action_label }}</span>
                                        <span>{{ $h->description }}</span>
                                        @if ($h->amount)<span class="stk-timeline__qty">{{ $money($h->amount) }}</span>@endif
                                        <span class="stk-timeline__time">{{ optional($h->created_at)->format('Y-m-d H:i') }}</span>
                                    </div>
                                    <div class="stk-timeline__meta small text-muted">
                                        <i class="feather icon-user"></i> {{ optional($h->user)->name ?? 'النظام' }}
                                        @if ($h->notes) — {{ $h->notes }} @endif
                                    </div>
                                    @if (! empty($h->changes))
                                        <details class="mt-1">
                                            <summary class="small">التغييرات ({{ count($h->changes) }})</summary>
                                            <table class="table table-sm mb-0 mt-1">
                                                <thead><tr><th>الحقل</th><th>قبل</th><th>بعد</th></tr></thead>
                                                <tbody>
                                                    @foreach ($h->changes as $field => $diff)
                                                        <tr>
                                                            <td>{{ $fieldLabels[$field] ?? $field }}</td>
                                                            <td class="text-muted"><s>{{ is_array($diff['old'] ?? null) ? json_encode($diff['old'], JSON_UNESCAPED_UNICODE) : ($diff['old'] ?? '—') }}</s></td>
                                                            <td class="text-success">{{ is_array($diff['new'] ?? null) ? json_encode($diff['new'], JSON_UNESCAPED_UNICODE) : ($diff['new'] ?? '—') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
