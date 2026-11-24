@extends('hr::master')

@section('title', 'قسيمة '.($slip->code ?: '#'.$slip->id))

@php
    $emp = $slip->employee;
    $empName = trim(($emp->first_name ?? '').' '.($emp->middle_name ?? '').' '.($emp->nickname ?? ''));
    $fmt = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
    $slipStates = [
        \App\Models\SalarySlip::STATUS_DRAFT => 'warn',
        \App\Models\SalarySlip::STATUS_APPROVED => 'neutral',
        \App\Models\SalarySlip::STATUS_ACTIVE => 'on',
        \App\Models\SalarySlip::STATUS_STOPPED => 'off',
        \App\Models\SalarySlip::STATUS_EXPIRED => 'off',
    ];

    $additionItemsList = $slip->salaryItems->where('type', \App\Models\SalaryItem::TYPE_ADDITION);
    $deductionItemsList = $slip->salaryItems->where('type', \App\Models\SalaryItem::TYPE_DEDUCTION);

    $basic = (float) $slip->basic_amount;
    $totalAdditions = (float) $additionItemsList->sum('amount');
    // بندُ التأمينات المولَّد يُعاد احتسابه عند كلّ صرف (مواطن/مقيم/معفى) — فيُقدَّر من الخدمة لا من آخر قيمةٍ كُتبت
    $gosiShare = (float) ($gosiEmployeeShare ?? 0);
    $totalManualDeds = (float) $deductionItemsList
        ->where('origin', '!=', \App\Models\SalaryItem::ORIGIN_SOCIAL_INSURANCE)
        ->sum('amount');
    $totalGross = $basic + $totalAdditions;
    // الخصومات المعتمدة غير المسواة والسلف المستحقة — تقديرُ ما يُخصم وقت الصرف
    $totalApprovedDeds = (float) $unsettledDeductions->sum(fn ($d) => $d->remaining_amount ?? 0);
    $totalAdvancesDue = (float) $unsettledAdvances->sum('amount');
    $totalAllDeductions = $totalManualDeds + $gosiShare + $totalApprovedDeds;
    $netAfterDeductions = $totalGross - $totalAllDeductions;
    $finalNet = $netAfterDeductions - $totalAdvancesDue;

    $canPrint = hasPermission('payslips.print');
@endphp

@section('content')
    <div class="content-body">

        @section('page-actions')
            @php
                $psCanStop = hasPermission('payslips.approve') && $slip->canBeStopped();
                $psCanDelete = hasPermission('payslips.destroy') && $slip->canBeDeleted();
            @endphp

            {{-- الترتيب الموحّد: [إجراء سير العمل] [تعديل] [طباعة ▾] [المزيد ▾ … حذف] --}}
            @permission('payslips.approve')
                @if ($slip->canBeApproved())
                    <form method="POST" action="{{ route('salarySlip.approve', $slip->id) }}" class="d-inline"
                          onsubmit="return confirm('اعتماد القسيمة؟ ستبدأ بتوليد رواتب شهرية تلقائياً.')">
                        @csrf
                        <button class="btn btn-success"><i class="fa fa-check"></i> اعتماد القسيمة</button>
                    </form>
                @endif
            @endpermission

            @if ($dueRun)
                @permission('payslips.disburse')
                    <button type="button" class="btn btn-success" data-toggle="modal" data-bs-toggle="modal" data-target="#disburseModal" data-bs-target="#disburseModal">
                        <i class="fa fa-money-bill-wave"></i> صرف راتب {{ $dueRun->period_from->format('Y-m') }}
                    </button>
                @endpermission
            @endif

            @permission('payslips.edit')
                @if ($slip->canBeEdited())
                    <a href="{{ route('salarySlip.edit', $slip->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> تعديل</a>
                @endif
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
                                    <a class="dropdown-item" target="_blank" href="{{ route('salarySlip.print', ['id' => $slip->id, 'template' => $tpl->id]) }}">
                                        <i class="fa fa-file-alt me-2 text-info"></i>{{ $tpl->name }} @if ($tpl->is_default)<small class="text-muted">(افتراضي)</small>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a href="{{ route('salarySlip.print', $slip->id) }}" target="_blank" class="btn btn-outline-primary"><i class="fa fa-print"></i> طباعة</a>
                @endif
            @endif

            @if ($psCanStop || $psCanDelete)
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-h"></i> المزيد
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if ($psCanStop)
                            <li>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-bs-toggle="modal" data-target="#stopModal" data-bs-target="#stopModal">
                                    <i class="fa fa-ban me-2 text-warning"></i>إيقاف القسيمة
                                </a>
                            </li>
                        @endif
                        @if ($psCanDelete)
                            @if ($psCanStop)
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <form method="POST" action="{{ route('salarySlip.destroy', $slip->id) }}" onsubmit="return confirm('حذف القسيمة؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف القسيمة</button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => 'قسيمة '.($slip->code ?: '#'.$slip->id).' — '.$empName,
            'icon' => 'file-text',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قسائم الرواتب' => route('salarySlip.index'),
                ($slip->code ?: '#'.$slip->id) => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if (! $dueRun)
            @php $nextRun = $slip->payrollRuns->where('status', 'pending')->sortBy('scheduled_date')->first(); @endphp
            @if ($nextRun)
                <div class="alert alert-info">
                    <i class="feather icon-clock"></i> الصرف التالي مجدولٌ في <strong class="stk-num">{{ $nextRun->scheduled_date->format('Y-m-d') }}</strong> — يتفعّل زرّ الصرف اليدوي عندها.
                </div>
            @endif
        @endif

        <div class="row">
            <div class="col-lg-8">
                {{-- بيانات القسيمة --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات القسيمة</h6>
                        <span class="stk-state stk-state--{{ $slipStates[$slip->status] ?? 'neutral' }}">{{ $slip->status_label }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>رقم القسيمة</dt><dd class="stk-num">{{ $slip->code ?: '—' }}</dd></div>
                                    <div class="stk-def__row"><dt>الموظف</dt><dd>{{ $empName ?: '—' }}
                                        @if ($emp)<small class="text-muted d-block">{{ implode(' · ', array_filter([optional($emp->department)->name, optional($emp->job_title)->name])) }}</small>@endif
                                    </dd></div>
                                    <div class="stk-def__row"><dt>العقد</dt><dd>{{ $slip->contract ? '#'.$slip->contract->id.' '.($slip->contract->code ?? '') : 'بدون عقد — الراتب من البنود' }}</dd></div>
                                    <div class="stk-def__row"><dt>مسير الرواتب</dt><dd>
                                        @if ($slip->payroll)
                                            @if (hasPermission('payroll.show'))
                                                <a href="{{ route('PayrollProcess.show', $slip->payroll->id) }}">{{ $slip->payroll->name }}</a>
                                            @else
                                                {{ $slip->payroll->name }}
                                            @endif
                                            <span class="stk-state stk-state--{{ $slip->payroll->isActive() ? 'on' : 'off' }}">{{ $slip->payroll->isActive() ? 'نشط' : $slip->payroll->status_label }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </dd></div>
                                    <div class="stk-def__row"><dt>الفترة</dt><dd class="stk-num">{{ optional($slip->start_date)->format('Y-m-d') ?? '—' }} ← {{ optional($slip->end_date)->format('Y-m-d') ?? 'مفتوحة' }}</dd></div>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>التكرار</dt><dd>{{ $slip->frequency === 'weekly' ? 'أسبوعي' : 'شهري' }}</dd></div>
                                    <div class="stk-def__row"><dt>يوم الصرف</dt><dd>{{ $slip->getEffectivePaymentDay() }} من كل شهر</dd></div>
                                    <div class="stk-def__row"><dt>الخزينة</dt><dd>{{ optional($slip->treasury)->name ?? 'افتراضية' }}</dd></div>
                                    <div class="stk-def__row"><dt>العملة</dt><dd>{{ $slip->currency ?: \App\Support\CurrencySettings::code() }}</dd></div>
                                    <div class="stk-def__row"><dt>أنشأها</dt><dd>{{ optional($slip->creator)->name ?? '—' }}</dd></div>
                                    <div class="stk-def__row"><dt>اعتمدها</dt><dd>{{ optional($slip->approver)->name ?? '—' }} @if ($slip->approved_at)<small class="text-muted stk-num">— {{ $slip->approved_at->format('Y-m-d H:i') }}</small>@endif</dd></div>
                                </dl>
                            </div>
                        </div>
                        @if ($slip->notes)
                            <div class="stk-choice mt-3"><strong>ملاحظات:</strong> {{ $slip->notes }}</div>
                        @endif
                    </div>
                </div>

                {{-- بنود القسيمة --}}
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="fa fa-calculator"></i> بنود القسيمة</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr><th>البند</th><th>الصيغة</th><th class="text-end">المبلغ</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>الراتب الأساسي</strong></td>
                                    <td class="text-muted">—</td>
                                    <td class="text-end stk-num">{{ $fmt($basic) }}</td>
                                </tr>
                                @foreach ($additionItemsList as $item)
                                    <tr>
                                        <td><i class="feather icon-plus-circle text-success"></i> {{ $item->name }}
                                            @if ($item->origin === \App\Models\SalaryItem::ORIGIN_REWARD_REDEMPTION)
                                                <span class="badge badge-light-warning">مكافأة لمرّة واحدة</span>
                                                <i class="feather icon-help-circle stk-tip" title="من كتالوج المكافآت (استبدال نقاط) — تُصرف مع الصرفة التالية وحدها ثم تُزال من القسيمة. ليست بدلاً متكرّراً."></i>
                                            @endif
                                        </td>
                                        <td class="text-muted" dir="ltr">{{ $item->calculation_formula ?: '—' }}</td>
                                        <td class="text-end stk-num text-success">+{{ $fmt($item->amount) }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($deductionItemsList as $item)
                                    <tr>
                                        <td><i class="feather icon-minus-circle text-danger"></i> {{ $item->name }}
                                            @if ($item->origin === \App\Models\SalaryItem::ORIGIN_SOCIAL_INSURANCE)
                                                <small class="d-block text-muted">مولَّدٌ في آخر صرف — يُعاد احتسابه عند الصرف التالي{{ $item->description ? ' ('.$item->description.')' : '' }}</small>
                                            @endif
                                        </td>
                                        <td class="text-muted" dir="ltr">{{ $item->calculation_formula ?: '—' }}</td>
                                        <td class="text-end stk-num text-danger">−{{ $fmt($item->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="stk-total-row"><th colspan="2">إجمالي الراتب</th><th class="text-end stk-num">{{ $fmt($slip->total_salary) }}</th></tr>
                                <tr class="stk-total-row"><th colspan="2">الخصومات اليدوية</th><th class="text-end stk-num text-danger">{{ (float) $slip->total_deductions > 0 ? '−'.$fmt($slip->total_deductions) : '—' }}</th></tr>
                                <tr class="stk-total-row"><th colspan="2">صافي القسيمة</th><th class="text-end stk-num">{{ $fmt($slip->net_salary) }}</th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- التقدير عند الصرف --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="fa fa-wallet"></i> الصافي المتوقّع عند الصرف</h6>
                        <small class="stk-help m-0">يشمل الخصميات المعتمدة والسلف المستحقة اليوم</small>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>إجمالي الراتب</dt><dd class="stk-num">{{ $fmt($totalGross) }}</dd></div>
                            @if ($totalManualDeds > 0)
                                <div class="stk-def__row"><dt>خصومات القسيمة</dt><dd class="stk-num text-danger">−{{ $fmt($totalManualDeds) }}</dd></div>
                            @endif
                            @if ($gosiShare > 0)
                                <div class="stk-def__row"><dt>التأمينات (حصة الموظف)</dt><dd class="stk-num text-danger">−{{ $fmt($gosiShare) }}</dd></div>
                            @endif
                            @if ($totalApprovedDeds > 0)
                                <div class="stk-def__row"><dt>خصميات معتمدة ({{ $unsettledDeductions->count() }})</dt><dd class="stk-num text-danger">−{{ $fmt($totalApprovedDeds) }}</dd></div>
                            @endif
                            @if ($totalAdvancesDue > 0)
                                <div class="stk-def__row"><dt>سلف مستحقة ({{ $unsettledAdvances->count() }})</dt><dd class="stk-num text-danger">−{{ $fmt($totalAdvancesDue) }}</dd></div>
                            @endif
                            <div class="stk-def__row"><dt><strong>الصافي المتوقّع</strong></dt><dd class="stk-num"><strong>{{ $fmt(max(0, $finalNet)) }}</strong></dd></div>
                        </dl>
                        @if (! empty($gosiExplanation))
                            <p class="stk-help mb-0 mt-1">
                                <i class="feather icon-shield"></i> <strong>التأمينات:</strong> {{ $gosiExplanation }}
                                <i class="feather icon-help-circle stk-tip" title="الخضوعُ من «حالة المواطنة» وخانة «الخضوع للتأمينات» في بطاقة الموظف، والنسبُ من إعدادات التأمينات الاجتماعية. يُعاد احتسابه لحظةَ الصرف."></i>
                            </p>
                        @endif
                        @if ($finalNet < 0)
                            <div class="alert alert-warning small mt-2 mb-0">
                                <i class="feather icon-alert-triangle"></i>
                                المخصومات أكبر من الراتب بمقدار <strong>{{ $fmt(abs($finalNet)) }}</strong> — يُجعل الصافي صفراً ويُرحّل الباقي للدورة التالية.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- سجل الصرفات --}}
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-rotate-ccw"></i> سجل الصرفات <span class="stk-count">{{ $slip->payrollRuns->count() }}</span></h6>
                    </div>
                    @if ($slip->payrollRuns->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr><th>#</th><th>الفترة</th><th>تاريخ الصرف</th><th class="text-end">الصافي</th><th class="text-center">الحالة</th><th class="text-center">إجراء</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($slip->payrollRuns as $r)
                                        <tr>
                                            <td class="stk-num">{{ $r->code ?? '#'.$r->id }}</td>
                                            <td class="stk-num"><small>{{ $r->period_from->format('Y-m-d') }} ← {{ $r->period_to->format('Y-m-d') }}</small></td>
                                            <td class="stk-num"><small>{{ $r->disbursed_at ? $r->disbursed_at->format('Y-m-d') : $r->scheduled_date->format('Y-m-d') }}</small></td>
                                            <td class="text-end stk-num">{{ $fmt($r->net_salary) }}</td>
                                            <td class="text-center"><span class="stk-state stk-state--{{ $r->status === 'disbursed' ? 'on' : ($r->status === 'pending' ? 'warn' : 'off') }}">{{ $r->status_label }}</span></td>
                                            <td class="text-center">
                                                @if (hasPermission('disbursements.show'))
                                                    <a href="{{ route('payroll-runs.show', $r->id) }}" class="btn btn-sm btn-outline-secondary" title="عرض"><i class="feather icon-eye"></i></a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-clock"></i></div>
                                <p class="stk-empty__hint">لم تُولَّد أيّ صرفة بعد — يبدأ التوليد تلقائياً بعد اعتماد القسيمة.</p>
                            </div>
                        </div>
                    @endif
                </div>

                @include('hr::salaries.payroll_runs.partials.journal_trail', [
                    'trail' => $journalTrail ?? collect(),
                    'pendingDeductions' => $pendingDeductions ?? collect(),
                    'trailMoney' => $fmt,
                ])
            </div>

            <div class="col-lg-4">
                {{-- حسابات الموظف --}}
                @if ($advanceSummary)
                    <div class="card">
                        <div class="stk-table-head"><h6 class="stk-table-head__title"><i class="fa fa-university"></i> حسابات الموظف</h6></div>
                        <div class="card-body">
                            <dl class="stk-def stk-def--compact">
                                <div class="stk-def__row"><dt>المستحقات</dt><dd>{{ optional(optional($emp)->salaryPayableAccount)->name ?? 'غير مرتبط' }}</dd></div>
                                <div class="stk-def__row"><dt>السلف</dt><dd>{{ optional(optional($emp)->advanceAccount)->name ?? 'غير مرتبط' }}</dd></div>
                                <div class="stk-def__row"><dt>رصيد السلف</dt><dd class="stk-num {{ $advanceSummary['remaining'] > 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($advanceSummary['remaining']) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                @endif

                <div class="card stk-table-card">
                    <div class="stk-table-head"><h6 class="stk-table-head__title"><i class="fa fa-hand-holding-usd"></i> السلف الحالية <span class="stk-count">{{ $unsettledAdvances->count() }}</span></h6></div>
                    @if ($unsettledAdvances->count())
                        <table class="table mb-0">
                            <tbody>
                                @foreach ($unsettledAdvances as $a)
                                    <tr><td><small>{{ $a['name'] }}</small></td><td class="text-end stk-num">{{ $fmt($a['amount']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="card-body"><p class="stk-empty__hint mb-0">لا توجد سلف غير مسوّاة</p></div>
                    @endif
                </div>

                <div class="card stk-table-card">
                    <div class="stk-table-head"><h6 class="stk-table-head__title"><i class="feather icon-minus-circle"></i> الخصميات المعلّقة <span class="stk-count">{{ $unsettledDeductions->count() }}</span></h6></div>
                    @if ($unsettledDeductions->count())
                        <table class="table mb-0">
                            <tbody>
                                @foreach ($unsettledDeductions as $d)
                                    <tr>
                                        <td><small>{{ $d->type_label ?? $d->deduction_type }}<br><span class="text-muted">{{ \Illuminate\Support\Str::limit($d->reason, 40) }}</span></small></td>
                                        <td class="text-end stk-num text-danger">{{ $fmt($d->remaining_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="card-body"><p class="stk-empty__hint mb-0">لا توجد خصميات معلّقة</p></div>
                    @endif
                </div>

                @if ($logs->count())
                    <div class="card">
                        <div class="stk-table-head"><h6 class="stk-table-head__title"><i class="feather icon-activity"></i> سجل العمليات</h6></div>
                        <div class="card-body">
                            <ul class="stk-timeline">
                                @foreach ($logs->take(8) as $log)
                                    <li class="stk-timeline__item">
                                        <span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-activity"></i></span>
                                        <div class="stk-timeline__body">
                                            <div class="stk-timeline__text">{{ $log->description }}</div>
                                            <div class="stk-timeline__meta">{{ optional($log->user)->name ?? '—' }} · <span class="stk-num">{{ $log->created_at->format('Y-m-d H:i') }}</span></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- نافذة الصرف — اختيار خزينة الصرف --}}
    @if ($dueRun)
        @permission('payslips.disburse')
            <div class="modal fade" id="disburseModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('payroll-runs.disburse', $dueRun->id) }}" class="w-100">
                        @csrf
                        <div class="modal-content text-right">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fa fa-money-bill-wave text-success"></i> صرف راتب {{ $dueRun->period_from->format('Y-m') }}</h5>
                                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="disburse_treasury_id">خزينة الصرف</label>
                                    <select name="treasury_id" id="disburse_treasury_id" class="form-control hr-s2">
                                        @foreach ($treasuries as $treasury)
                                            <option value="{{ $treasury->id }}" {{ $slip->treasury_id == $treasury->id ? 'selected' : '' }}>
                                                {{ $treasury->name }}{{ $slip->treasury_id == $treasury->id ? ' (خزينة القسيمة)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- صافي الصرفة المعلّقة صفرٌ حتى لحظة الصرف — يُعرض صافي القسيمة تقديراً --}}
                                <div class="alert alert-warning small mb-0">
                                    <i class="feather icon-alert-triangle"></i> سيُنشأ سند صرف وقيد محاسبي — الصافي التقديري {{ $fmt($slip->net_salary) }}، وقد يختلف بعد احتساب الخصميات والسلف والتأمينات وقت الصرف.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-success"><i class="fa fa-money-bill-wave"></i> تأكيد الصرف</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endpermission
    @endif

    {{-- نافذة الإيقاف --}}
    @permission('payslips.approve')
        <div class="modal fade" id="stopModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('salarySlip.stop', $slip->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">إيقاف القسيمة</h5>
                            <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">بعد الإيقاف لن تُولَّد صرفاتٌ جديدة من القسيمة.</div>
                            <div class="form-group mb-0">
                                <label for="stop_reason">سبب الإيقاف</label>
                                <textarea id="stop_reason" name="stop_reason" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                            <button class="btn btn-warning">تأكيد الإيقاف</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endpermission
@endsection
