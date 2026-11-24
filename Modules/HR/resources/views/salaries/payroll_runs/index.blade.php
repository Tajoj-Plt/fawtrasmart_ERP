@extends('hr::master')

@section('title', __('payroll_runs.page_title'))

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $filtered = request()->hasAny(['employee_id', 'status', 'period_from', 'period_to']);
    // النصوص من resources/lang/{ar,en,ur}/payroll_runs.php — كانت قاموساً مضمَّناً هنا
    $stageText = fn (string $key) => __('payroll_runs.stages.'.$key);
    // كلُّ زرٍّ بصلاحيته — وعلى من يحدّده النطاق (المتحكّمُ قصر القائمة عليه)
    $prCanShow = hasPermission('disbursements.show');
    $prCanSlip = hasPermission('payslips.show');
@endphp

@section('content')
    @include('stock::components.page-head', [
        'title' => __('payroll_runs.title'),
        'icon' => 'rotate-ccw',
        'sub' => __('payroll_runs.subtitle'),
        'actions' => hasPermission('payroll.index')
            ? '<a href="' . route('PayrollProcess.index') . '" class="btn btn-outline-primary"><i class="fas fa-layer-group"></i> ' . e(__('payroll_runs.payrolls')) . '</a>'
            : '',
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-payroll-runs.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه صرفاتُ الرواتب: صرفةٌ لكلّ موظفٍ في كلّ دورة، مشتقّةٌ من قسيمة راتبه. وهنا وحدَه يقع الأثرُ الماليّ — القسيمةُ تضبط والصرفةُ تصرف.',
            'blocks' => [
                'مراحلُ السجل' => [
                    '**بانتظار اعتماد مسير الرواتب**: مسيرُ رواتب قسيمتها لم يُعتمد — اعتمادُ المسير هو الاعتمادُ الوحيد، ولا «مسير صرفات» بعده.',
                    '**معتمدة — قبل يوم الصرف**: اعتُمد مسيرُها ولم يحلّ يومُ صرف القسيمة؛ الصرفُ قبله «صرفٌ مبكر» بتأكيدٍ صريح من سجلّها.',
                    '**مستحقة الصرف**: حلّ يومُ الصرف — تُصرف من هنا أو من شاشة المسير («صرف المستحق») أو تلقائياً.',
                    '**مدفوعة**: خرج النقدُ من الخزينة وأُنشئ السندُ والقيود. **موقوفة**: جُمِّدت بقرارٍ إداريّ حتى يُرفع الإيقاف.',
                ],
                'ما يقع عند الصرف' => [
                    'يُفحص **رصيدُ الخزينة** تحت قفلٍ قبل أيِّ قيد — فلا تصير الخزينةُ سالبة، ويُرفض الصرفُ إن لم يكفِ الرصيد.',
                    'يُنشأ **سندُ صرفٍ وقيدان**: استحقاقٌ بإجمالي الراتب، وصرفٌ يُقفل ذمّةَ الموظف بالصافي والسلف والتأمينات.',
                    'الخصمياتُ **قُيِّدت عند اعتمادها** وتُقتطع هنا من الصافي دون قيدٍ ثانٍ.',
                ],
                'بعد الدفع' => [
                    'المدفوعةُ لا تُضاف إليها تسويةٌ ولا تُوقف ولا تُلغى — تصحيحُها بالعكس: قيدٌ مضادٌّ بسببٍ مكتوب يعيدها معلّقة.',
                ],
            ],
            'facts' => [
                'الرصيدُ يُفحص قبل القيد ولا يصير سالباً',
                'المدفوعُ يُعكَس ولا يُحذف',
                'فترةٌ محاسبيّةٌ مقفلةٌ تمنع الصرف',
            ],
        ])

        <div class="stk-metrics mb-2">
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">{{ $stageText('awaiting_approval') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['awaiting_approval'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">{{ $stageText('scheduled') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['scheduled'] }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-check-square"></i></span>
                <span class="stk-metric__label">{{ $stageText('ready') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['ready'] }}</span>
                @if ($statistics['due_today'] > 0)
                    <span class="stk-metric__note"><i class="feather icon-alert-circle"></i> {{ __('payroll_runs.due_today', ['count' => $statistics['due_today']]) }}</span>
                @endif
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-pause-circle"></i></span>
                <span class="stk-metric__label">{{ $stageText('held') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['held'] }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">{{ $stageText('paid') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['disbursed'] }}</span>
                <span class="stk-metric__note">{{ __('payroll_runs.this_month', ['amount' => $money($statistics['net_total_this_month'])]) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">{{ $stageText('cancelled') }}</span>
                <span class="stk-metric__value stk-num">{{ $statistics['cancelled'] }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> {{ __('payroll_runs.filters') }}</h6>
                    @if ($filtered)
                        <span class="stk-count">{{ __('payroll_runs.filters_active') }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('payroll-runs.index') }}">
                    <div class="row">
                        @if ($employees->isNotEmpty())
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="employee_id">{{ __('payroll_runs.employee') }}</label>
                                    <select name="employee_id" id="employee_id" class="form-control select2">
                                        <option value="">{{ __('payroll_runs.all') }}</option>
                                        @foreach ($employees as $emp)
                                            <option value="{{ $emp->id }}" {{ (string) request('employee_id') === (string) $emp->id ? 'selected' : '' }}>
                                                {{ trim($emp->first_name.' '.$emp->middle_name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">{{ __('payroll_runs.stage') }}</label>
                                <select name="status" id="status" class="form-control hr-s2">
                                    <option value="">{{ __('payroll_runs.all') }}</option>
                                    @foreach ($statuses as $k => $v)
                                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $stageText($k) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-{{ $employees->isNotEmpty() ? '2' : '4' }} col-sm-6">
                            <div class="form-group">
                                <label for="period_from">{{ __('payroll_runs.period_from') }}</label>
                                <input type="date" id="period_from" name="period_from" class="form-control" value="{{ request('period_from') }}">
                            </div>
                        </div>
                        <div class="col-md-{{ $employees->isNotEmpty() ? '3' : '5' }} col-sm-6">
                            <div class="form-group">
                                <label for="period_to">{{ __('payroll_runs.period_to') }}</label>
                                <input type="date" id="period_to" name="period_to" class="form-control" value="{{ request('period_to') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('payroll-runs.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> {{ __('payroll_runs.reset') }}
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> {{ __('payroll_runs.search') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($runs->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> {{ __('payroll_runs.list') }}
                        <span class="stk-count">{{ $runs->total() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('payroll_runs.record') }}</th>
                                <th>{{ __('payroll_runs.employee') }}</th>
                                <th>{{ __('payroll_runs.period') }}</th>
                                <th>{{ __('payroll_runs.disbursed_date') }}</th>
                                <th class="text-end">{{ __('payroll_runs.earnings') }}</th>
                                <th class="text-end">{{ __('payroll_runs.deductions') }}</th>
                                <th class="text-end">{{ __('payroll_runs.advances') }}</th>
                                <th class="text-end">{{ __('payroll_runs.net') }}</th>
                                <th class="text-center">{{ __('payroll_runs.stage') }}</th>
                                <th class="text-center" style="width:70px">{{ __('payroll_runs.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                @php $emp = $run->employee; @endphp
                                <tr>
                                    <td>
                                        <span class="stk-strong">{{ $run->code ?: '#'.$run->id }}</span>
                                        @if ($run->slip && $run->slip->payroll)
                                            <span class="stk-media__sub d-block"><i class="feather icon-layers"></i> {{ $run->slip->payroll->name }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="stk-media__title">{{ trim(($emp->first_name ?? '').' '.($emp->middle_name ?? '')) ?: '—' }}</span>
                                        @if ($run->salary_slip_id && $prCanSlip)
                                            <a href="{{ route('salarySlip.show', $run->salary_slip_id) }}" class="stk-media__sub d-block">{{ __('payroll_runs.slip_no', ['id' => $run->salary_slip_id]) }}</a>
                                        @elseif ($run->salary_slip_id)
                                            <span class="stk-media__sub d-block">{{ __('payroll_runs.slip_no', ['id' => $run->salary_slip_id]) }}</span>
                                        @endif
                                    </td>
                                    <td class="stk-num"><small>{{ $run->period_from->format('Y-m-d') }}<br>→ {{ $run->period_to->format('Y-m-d') }}</small></td>
                                    <td class="stk-num">
                                        @if ($run->disbursed_at)
                                            <span class="stk-tag"><i class="feather icon-check"></i> {{ $run->disbursed_at->format('Y-m-d') }}</span>
                                            <small class="d-block text-muted">{{ $run->trigger_source === 'cron' ? __('payroll_runs.auto') : __('payroll_runs.manual') }}</small>
                                        @elseif ($run->scheduled_date)
                                            <span class="stk-tag"><i class="feather icon-clock"></i> {{ $run->scheduled_date->format('Y-m-d') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end stk-num">{{ $money($run->basic_amount + $run->total_additions) }}</td>
                                    <td class="text-end stk-num text-danger">{{ $money($run->total_deductions) }}</td>
                                    <td class="text-end stk-num text-warning">{{ $money($run->total_advances_deducted) }}</td>
                                    <td class="text-end stk-num"><strong>{{ $money($run->net_salary) }}</strong></td>
                                    <td class="text-center">
                                        <span class="stk-state stk-state--{{ $run->stage_tone }}">{{ $stageText($run->stage()) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($prCanShow || ($prCanSlip && $run->salary_slip_id))
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="{{ __('payroll_runs.options') }}">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($prCanShow)
                                                <a class="dropdown-item" href="{{ route('payroll-runs.show', $run->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>{{ __('payroll_runs.view') }}
                                                </a>
                                                @endif
                                                @if ($run->salary_slip_id && $prCanSlip)
                                                    <a class="dropdown-item" href="{{ route('salarySlip.show', $run->salary_slip_id) }}">
                                                        <i class="fa fa-file-invoice-dollar me-2 text-info"></i>{{ __('payroll_runs.view_slip') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($runs->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">{{ __('payroll_runs.showing', ['from' => $runs->firstItem(), 'to' => $runs->lastItem(), 'total' => $runs->total()]) }}</p>
                        {{ $runs->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                        <h5 class="stk-empty__title">{{ __('payroll_runs.empty_title') }}</h5>
                        <p class="stk-empty__hint">
                            @if ($filtered)
                                {{ __('payroll_runs.empty_filtered') }}
                            @else
                                {{ __('payroll_runs.empty_hint') }}
                            @endif
                        </p>
                        @if ($filtered)
                            <a href="{{ route('payroll-runs.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> {{ __('payroll_runs.clear_filters') }}</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
@endpush
