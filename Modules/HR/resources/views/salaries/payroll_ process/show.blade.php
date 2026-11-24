@extends('hr::master')

@section('title', 'مسير الرواتب — '.$payroll->name)

@php
    $pStatus = $payroll->status ?? 'active';
    $ppStates = ['active' => ['on', 'نشط (معتمد)'], 'draft' => ['warn', 'مسودة'], 'inactive' => ['off', 'موقوف']];
    $pState = $ppStates[$pStatus] ?? ['neutral', $payroll->status_label];
    $slipStates = [
        \App\Models\SalarySlip::STATUS_DRAFT => 'warn',
        \App\Models\SalarySlip::STATUS_APPROVED => 'neutral',
        \App\Models\SalarySlip::STATUS_ACTIVE => 'on',
        \App\Models\SalarySlip::STATUS_STOPPED => 'off',
        \App\Models\SalarySlip::STATUS_EXPIRED => 'off',
    ];
    $slipLabels = \App\Models\SalarySlip::statuses();
    $cycles = [1 => 'شهري', 2 => 'أسبوعي', 3 => 'سنوي', 4 => 'ربع سنوي', 5 => 'كل أسبوعين'];
    $fmt = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
    $slipByEmployee = $slips->groupBy('employee_id');
    $canViewSlips = hasPermission('payslips.show');
    // طباعةُ المسير (payroll.print) غيرُ طباعة القسيمة (payslips.print)
    $canPrint = hasPermission('payroll.print');
    $canPrintSlip = hasPermission('payslips.print');
    $canCreateSlip = hasPermission('payslips.create') && $pStatus === 'active';
    $running = (int) ($slipCounts[\App\Models\SalarySlip::STATUS_APPROVED] ?? 0) + (int) ($slipCounts[\App\Models\SalarySlip::STATUS_ACTIVE] ?? 0);
    $canPayRuns = hasPermission('payslips.disburse');
    $canViewRuns = hasPermission('disbursements.show');
    $dueNet = (float) $dueRuns->sum('net_salary');
@endphp

@section('content')
    <div class="content-body">

        @section('page-actions')
            @php
                $ppCanApprove = hasPermission('payroll.approve');
                $ppCanApproveSlips = hasPermission('payslips.approve');
                $ppCanExport = hasPermission('payroll.export');
                $ppCanDelete = hasPermission('payroll.destroy') && $slips->isEmpty() && ! \App\Models\SalarySlip::where('payroll_id', $payroll->id)->exists();
                $ppMoreApprove = $ppCanApprove && $pStatus === 'active';
            @endphp

            {{-- الترتيب الموحّد: [إجراء سير العمل] [تعديل] [طباعة ▾] [المزيد ▾ … حذف] --}}
            @if ($pStatus === 'active' && $canPayRuns && $dueRuns->isNotEmpty())
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#disburseDueModal">
                    <i class="fas fa-money-bill-wave"></i> صرف المستحق ({{ $dueRuns->count() }})
                </button>
            @endif
            @if ($ppCanApprove && $pStatus !== 'active')
                    <form method="POST" action="{{ route($pStatus === 'inactive' ? 'PayrollProcess.activate' : 'PayrollProcess.approve', $payroll->id) }}" class="d-inline"
                          onsubmit="return confirm('اعتماد المسير؟ هذا هو الاعتماد الوحيد للصرف: تُنشأ صرفات الفترة الجارية وتُصرف في يوم صرف كل قسيمة بلا اعتماد آخر.');">
                        @csrf
                        <button class="btn btn-success"><i class="fa fa-check-double"></i> {{ $pStatus === 'inactive' ? 'إعادة التفعيل' : 'اعتماد المسير' }}</button>
                    </form>
            @elseif ($ppCanApproveSlips && $pStatus === 'active' && $countDraft > 0)
                    <a href="{{ route('PayrollProcess.approveAll', $payroll->id) }}" class="btn btn-success"
                       onclick="return confirm('اعتماد {{ $countDraft }} قسيمة مسودة في هذا المسير؟');">
                        <i class="fa fa-check"></i> اعتماد القسائم ({{ $countDraft }})
                    </a>
            @endif

            @permission('payroll.edit')
                <a href="{{ route('PayrollProcess.edit', $payroll->id) }}" class="btn btn-primary">
                    <i class="fa fa-edit"></i> تعديل
                </a>
            @endpermission

            @if ($canPrint)
                @if ($printTemplates->count() > 1)
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-print"></i> طباعة
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">قوالب الطباعة</h6></li>
                            @foreach ($printTemplates as $tpl)
                                <li>
                                    <a class="dropdown-item" target="_blank" href="{{ route('PayrollProcess.print', ['id' => $payroll->id, 'template' => $tpl->id]) }}">
                                        <i class="fa fa-file-alt me-2 text-info"></i>{{ $tpl->name }} @if ($tpl->is_default)<small class="text-muted">(افتراضي)</small>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a href="{{ route('PayrollProcess.print', $payroll->id) }}" target="_blank" class="btn btn-outline-primary">
                        <i class="fa fa-print"></i> طباعة
                    </a>
                @endif
            @endif

            @if ($ppMoreApprove || $ppCanExport || $ppCanDelete)
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-h"></i> المزيد
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if ($ppMoreApprove)
                            <li><h6 class="dropdown-header">الاعتماد</h6></li>
                            <li>
                                <form method="POST" action="{{ route('PayrollProcess.approveRuns', $payroll->id) }}"
                                      onsubmit="return confirm('إنشاء ما نقص من صرفات الفترة الجارية لقسائم المسير السارية؟');">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="fa fa-sync-alt me-2 text-success"></i>تحديث صرفات الفترة</button>
                                </form>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('PayrollProcess.deactivate', $payroll->id) }}"
                                      onsubmit="return confirm('إيقاف المسير؟ لن تُولَّد صرفاتٌ جديدة من قسائمه.');">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="fa fa-pause me-2 text-warning"></i>إيقاف المسير</button>
                                </form>
                            </li>
                        @endif

                        @if ($ppCanExport)
                            @if ($ppMoreApprove)
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><h6 class="dropdown-header">التصدير</h6></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('PayrollProcess.exportSlips', $payroll->id) }}">
                                    <i class="fa fa-file-excel me-2 text-success"></i>تصدير القسائم Excel
                                </a>
                            </li>
                        @endif

                        @if ($ppCanDelete)
                            @if ($ppMoreApprove || $ppCanExport)
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <form method="POST" action="{{ route('PayrollProcess.destroy', $payroll->id) }}"
                                      onsubmit="return confirm('حذف المسير «{{ $payroll->name }}»؟ سيُفكّ ارتباطه بموظفيه.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف المسير</button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => $payroll->name,
            'icon' => 'layers',
            'sub' => 'مسير الرواتب #'.$payroll->id.' — مظلة تغطية، لا سند صرف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسيرات الرواتب' => route('PayrollProcess.index'),
                '#'.$payroll->id => null,
            ],
        ])

        @include('hr::components.guide', [
            'key' => 'hr-payroll-process.show',
            'title' => 'ما الذي تعرضه هذه الشاشة؟',
            'lead' => 'الموظفون المحفوظون في المسير، والقسائمُ المنشأة تحته —والطباعةُ وملفُّ Excel يُخرجان هذه القوائمَ نفسَها بلا زيادةٍ ولا نقص.',
            'blocks' => [
                'الأزرار' => [
                    '«اعتماد المسير» هو **الاعتمادُ الوحيد للصرف**: يسمح بإنشاء قسائم تحته، ويُنشئ صرفات الفترة الجارية لقسائمه السارية. و«اعتماد القسائم» يعتمد مسوّداتها دفعةً واحدة ويُنشئ صرفاتها كذلك.',
                    'في يوم صرف كلّ قسيمة تصير صرفتُها «مستحقة الصرف»: اصرفها من تبويب «الصرفات» فرداً، أو كلَّها بـ«صرف المستحق» — لا «مسير صرفات» يُعتمد بعد ذلك.',
                    'الصرفُ قبل يوم الصرف «صرفٌ مبكر» يُطلب فرداً بتأكيدٍ صريح. والصرفةُ الموقوفة أو قسيمتُها موقوفة لا تُصرف.',
                ],
                'الأرقام' => [
                    'الإجماليُّ والخصوماتُ والصافي هنا أرقامُ القسائم (الاتفاق الشهري)، لا ما صُرف فعلاً: الصرفةُ تخصم عند وقتها السلفَ والاستقطاعاتِ والتأمينات.',
                ],
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        {{-- البيانات --}}
        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات المسير</h6>
                <span class="stk-state stk-state--{{ $pState[0] }}">{{ $pState[1] }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>المعرّف</dt><dd class="stk-num">#{{ $payroll->id }}</dd></div>
                            <div class="stk-def__row"><dt>الفترة</dt><dd class="stk-num">{{ optional($payroll->start_date)->format('Y-m-d') ?? '—' }} ← {{ optional($payroll->end_date)->format('Y-m-d') ?? 'مفتوح' }}</dd></div>
                            <div class="stk-def__row"><dt>تاريخ التسجيل</dt><dd class="stk-num">{{ optional($payroll->registration_date)->format('Y-m-d') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>دورة القبض</dt><dd>{{ $cycles[$payroll->receiving_cycle] ?? '—' }}</dd></div>
                            @if ($payroll->approved_at)
                                <div class="stk-def__row"><dt>اعتُمد</dt><dd>{{ optional($payroll->approver)->name ?? '—' }} <span class="text-muted stk-num">— {{ $payroll->approved_at->format('Y-m-d H:i') }}</span></dd></div>
                            @endif
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>طريقة الاختيار</dt><dd>{{ (int) $payroll->select_emp_role === 2 ? 'موظفون محدَّدون' : 'بالقواعد' }}</dd></div>
                            @if ((int) $payroll->select_emp_role !== 2)
                                <div class="stk-def__row"><dt>الفرع</dt><dd>{{ $payroll->branch->name ?? 'كل الفروع' }} @if ($payroll->branch)<small class="text-muted">(وما تحته)</small>@endif</dd></div>
                                <div class="stk-def__row"><dt>القسم</dt><dd>{{ $payroll->department->name ?? 'كل الأقسام' }}</dd></div>
                                <div class="stk-def__row"><dt>المسمى الوظيفي</dt><dd>{{ $payroll->jobTitle->name ?? 'كل المسميات' }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__value">{{ $members->count() }}</span>
                    <span class="stk-metric__label">الموظفون</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__value">{{ $slips->count() }}</span>
                    <span class="stk-metric__label">القسائم</span>
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__value">{{ $countDraft }}</span>
                    <span class="stk-metric__label">مسودة</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__value">{{ $running }}</span>
                    <span class="stk-metric__label">سارية</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__value stk-num">{{ $fmt($totals['gross']) }}</span>
                    <span class="stk-metric__label">إجمالي الرواتب</span>
                </div>
                <div class="stk-metric stk-metric--danger">
                    <span class="stk-metric__value stk-num">{{ $fmt($totals['deductions']) }}</span>
                    <span class="stk-metric__label">الخصومات</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__value stk-num">{{ $fmt($totals['net']) }}</span>
                    <span class="stk-metric__label">الصافي</span>
                </div>
            </div>
        </div>

        {{-- القوائم --}}
        <div class="card stk-table-card">
            <div class="stk-table-head">
                <ul class="nav stk-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#pp_slips" role="tab"><i class="fa fa-file-invoice"></i> القسائم <span class="stk-count">{{ $slips->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#pp_runs" role="tab"><i class="fas fa-money-bill-wave"></i> الصرفات <span class="stk-count">{{ $runs->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#pp_members" role="tab"><i class="feather icon-users"></i> الموظفون <span class="stk-count">{{ $members->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#pp_missing" role="tab"><i class="feather icon-user-x"></i> بلا قسيمة <span class="stk-count">{{ $membersWithoutSlip->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#pp_activity" role="tab"><i class="feather icon-rotate-ccw"></i> السجل</a></li>
                </ul>
            </div>

            <div class="tab-content">
                {{-- القسائم --}}
                <div class="tab-pane fade show active" id="pp_slips" role="tabpanel">
                    @if ($slips->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>القسيمة</th>
                                        <th>الموظف</th>
                                        <th>الفترة</th>
                                        <th class="text-end">الأساسي</th>
                                        <th class="text-end">الاستحقاقات</th>
                                        <th class="text-end">الإجمالي</th>
                                        <th class="text-end">الخصومات</th>
                                        <th class="text-end">الصافي</th>
                                        <th class="text-center">الحالة</th>
                                        <th class="text-center" style="width:100px">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($slips as $slip)
                                        @php
                                            $emp = $slip->employee;
                                            $additions = (float) $slip->salaryItems->where('type', \App\Models\SalaryItem::TYPE_ADDITION)->sum('amount');
                                        @endphp
                                        <tr>
                                            <td class="stk-num">{{ $slip->code ?: '#'.$slip->id }}</td>
                                            <td>
                                                <span class="stk-media__body">
                                                    <span class="stk-media__title">{{ $emp ? trim($emp->first_name.' '.$emp->middle_name) : '—' }}</span>
                                                    <span class="stk-media__sub">{{ implode(' · ', array_filter([optional(optional($emp)->department)->name, optional(optional($emp)->job_title)->name])) ?: '#'.$slip->employee_id }}</span>
                                                </span>
                                            </td>
                                            <td class="stk-num"><small>{{ optional($slip->start_date)->format('Y-m-d') ?? '—' }} ← {{ optional($slip->end_date)->format('Y-m-d') ?? 'مفتوحة' }}</small></td>
                                            <td class="text-end stk-num">{{ $fmt($slip->basic_amount) }}</td>
                                            <td class="text-end stk-num">{{ $fmt($additions) }}</td>
                                            <td class="text-end stk-num">{{ $fmt($slip->total_salary) }}</td>
                                            <td class="text-end stk-num text-danger">{{ (float) $slip->total_deductions > 0 ? '−'.$fmt($slip->total_deductions) : '—' }}</td>
                                            <td class="text-end stk-num"><strong>{{ $fmt($slip->net_salary) }}</strong></td>
                                            <td class="text-center"><span class="stk-state stk-state--{{ $slipStates[$slip->status] ?? 'neutral' }}">{{ $slipLabels[$slip->status] ?? $slip->status }}</span></td>
                                            <td class="text-center">
                                                @if ($canViewSlips || $canPrintSlip)
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            data-bs-toggle="dropdown" data-bs-display="static"
                                                            aria-expanded="false" aria-label="خيارات">
                                                            <i class="fa fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            @if ($canViewSlips)
                                                                <a class="dropdown-item" href="{{ route('salarySlip.show', $slip->id) }}">
                                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض القسيمة
                                                                </a>
                                                            @endif
                                                            @if ($canPrintSlip)
                                                                <a class="dropdown-item" href="{{ route('salarySlip.print', $slip->id) }}" target="_blank">
                                                                    <i class="fa fa-print me-2 text-info"></i>طباعة
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="stk-total-row">
                                        <th colspan="3">الإجمالي ({{ $slips->count() }} قسيمة)</th>
                                        <th class="text-end stk-num">{{ $fmt($totals['basic']) }}</th>
                                        <th class="text-end stk-num">{{ $fmt($totals['gross'] - $totals['basic']) }}</th>
                                        <th class="text-end stk-num">{{ $fmt($totals['gross']) }}</th>
                                        <th class="text-end stk-num text-danger">{{ $totals['deductions'] > 0 ? '−'.$fmt($totals['deductions']) : '—' }}</th>
                                        <th class="text-end stk-num">{{ $fmt($totals['net']) }}</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="fa fa-file-invoice"></i></div>
                                <h5 class="stk-empty__title">لا توجد قسائم في هذا المسير</h5>
                                <p class="stk-empty__hint">
                                    {{ $pStatus !== 'active' ? 'اعتمد المسير أولاً، ثم أنشئ قسائم موظفيه.' : 'أنشئ قسيمةً لكلّ موظفٍ في المسير.' }}
                                </p>
                                @if ($canCreateSlip)
                                    <a href="{{ route('salarySlip.create', ['payroll_id' => $payroll->id]) }}" class="btn btn-primary"><i class="feather icon-plus"></i> إنشاء قسيمة</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- الصرفات --}}
                <div class="tab-pane fade" id="pp_runs" role="tabpanel">
                    @include('hr::salaries.payroll_ process.partials.runs')
                </div>

                {{-- الموظفون --}}
                <div class="tab-pane fade" id="pp_members" role="tabpanel">
                    @include('hr::salaries.payroll_ process.partials.members', ['rows' => $members])
                </div>

                {{-- بلا قسيمة --}}
                <div class="tab-pane fade" id="pp_missing" role="tabpanel">
                    @include('hr::salaries.payroll_ process.partials.members', ['rows' => $membersWithoutSlip])
                </div>

                {{-- السجل --}}
                <div class="tab-pane fade" id="pp_activity" role="tabpanel">
                    <div class="card-body">
                        @if ($logs->isNotEmpty())
                            <ul class="stk-timeline">
                                @foreach ($logs as $date => $dayLogs)
                                    @foreach ($dayLogs as $log)
                                        <li class="stk-timeline__item">
                                            <span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-activity"></i></span>
                                            <div class="stk-timeline__body">
                                                <div class="stk-timeline__head">
                                                    <span class="stk-timeline__text">{{ $log->description }}</span>
                                                </div>
                                                <div class="stk-timeline__meta">
                                                    {{ $log->user->name ?? 'مستخدم غير معروف' }} · <span class="stk-num">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ul>
                        @else
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-rotate-ccw"></i></div>
                                <h5 class="stk-empty__title">لا توجد نشاطات</h5>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($pStatus === 'active' && $canPayRuns && $dueRuns->isNotEmpty())
        <div class="modal fade" id="disburseDueModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('PayrollProcess.disburseDue', $payroll->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-money-bill-wave text-success"></i> صرف المستحق</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">
                                سيُصرف <strong class="stk-num">{{ $dueRuns->count() }}</strong> راتباً حلّ يومُ صرفها في هذا المسير
                                (صافي القسائم تقديراً: <strong class="stk-num">{{ $fmt($dueRuns->sum(fn ($r) => (float) optional($r->slip)->net_salary)) }}</strong>).
                            </p>
                            <div class="form-group">
                                <label for="due_treasury_id">خزينة الصرف
                                    <i class="feather icon-help-circle stk-tip" title="اتركها على الافتراضي ليُصرف كل راتب من خزينة قسيمته ← خزينة الموظف ← الرئيسية. يُفحص الرصيد لكل صرفة تحت قفل، والصرفة التي لا يكفيها الرصيد تُرفض وحدها."></i>
                                </label>
                                <select name="treasury_id" id="due_treasury_id" class="form-control hr-s2">
                                    <option value="">— الخزينة الافتراضية لكل صرفة —</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}">{{ $treasury->name }} — {{ $fmt($treasury->balance) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="alert alert-warning small mb-0">
                                <i class="feather icon-alert-triangle"></i>
                                لكل صرفة سند صرف وقيدا الاستحقاق والصرف في معاملة مستقلة: ما يتعذّر صرفه يُذكر في النتيجة ولا يُسقط ما صُرف قبله.
                                الصرفات التي لم يحل يوم صرفها لا تدخل هنا — اصرفها فرداً «صرفاً مبكراً».
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-money-bill-wave"></i> تأكيد الصرف</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @include('hr::components.table-menu')
@endpush
