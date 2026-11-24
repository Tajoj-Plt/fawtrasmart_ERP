@extends('hr::master')

@section('title', 'سجل صرف ' . ($run->code ?: '#' . $run->id))

@php
    $emp = $run->employee;
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $empName = trim(($emp->first_name ?? '') . ' ' . ($emp->middle_name ?? '')) ?: '—';

    $additions = $run->items->where('item_type', 'addition');
    $deductions = $run->items->where('item_type', 'deduction');
    $advances = $run->items->where('item_type', 'advance');
    $retros = $run->items->where('item_type', 'retro');
    $employer = $run->items->where('item_type', 'employer');

    $canPay = $run->canBeDisbursed() && hasPermission('payslips.disburse');
    // قبل يوم الصرف ومسيرُها معتمد: «صرف مبكر» بتأكيدٍ صريح في النافذة
    $canPayEarly = ! $canPay && $run->canBeDisbursedEarly() && hasPermission('payslips.disburse');
    $payroll = optional($run->slip)->payroll;
    $canHold = $run->canBeHeld() && hasPermission('disbursements.stop');
    $canRelease = $run->canBeReleased() && hasPermission('disbursements.stop');
    $canRetro = $run->canReceiveRetro() && hasPermission('disbursements.settle');
    $canCancel = $run->canBeCancelled() && hasPermission('disbursements.cancel');
    $canReverse = $run->canBeReversed() && hasPermission('disbursements.reverse');
    $blockReason = $run->isPending() && ! $run->canBeDisbursed() ? $run->disbursementBlockReason() : null;
    $showPayModal = $canPay || $canPayEarly;
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [إجراء سير العمل] [طباعة] [المزيد ▾ … الإلغاء/العكس] --}}
    @if ($canPay)
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#disburseModal">
            <i class="fas fa-money-bill-wave"></i> صرف الراتب
        </button>
    @elseif ($canPayEarly)
        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#disburseModal">
            <i class="fas fa-forward"></i> صرف مبكر
        </button>
    @endif
    @if ($canRelease)
        <form method="POST" action="{{ route('payroll-runs.release', $run->id) }}" class="d-inline">
            @csrf
            <button class="btn btn-success" type="submit"><i class="fa fa-play-circle"></i> رفع الإيقاف</button>
        </form>
    @endif

    @if ($run->isDisbursed() && hasPermission('payslips.print'))
        <a href="{{ route('payroll-runs.print', $run->id) }}" target="_blank" class="btn btn-outline-primary">
            <i class="fa fa-print"></i> طباعة قسيمة الصرف
        </a>
    @endif

    @if ($canHold || $canRetro || $canCancel || $canReverse)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($canHold)
                    <li>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#holdModal">
                            <i class="fa fa-pause-circle me-2 text-warning"></i>إيقاف الصرف
                        </a>
                    </li>
                @endif
                @if ($canRetro)
                    <li>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#retroModal">
                            <i class="fa fa-plus-circle me-2 text-primary"></i>تسوية رجعية
                        </a>
                    </li>
                @endif
                @if ($canCancel || $canReverse)
                    @if ($canHold || $canRetro)
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    @if ($canCancel)
                        <li>
                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#cancelModal">
                                <i class="fa fa-ban me-2"></i>إلغاء السجل
                            </a>
                        </li>
                    @endif
                    @if ($canReverse)
                        <li>
                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#reverseModal">
                                <i class="fa fa-undo me-2"></i>عكس الصرف
                            </a>
                        </li>
                    @endif
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'سجل صرف ' . ($run->code ?: '#' . $run->id),
        'icon' => 'credit-card',
        'crumbs' => [
            'سجلات الصرف' => route('payroll-runs.index'),
            ($run->code ?: '#' . $run->id) => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                {{-- الهوية والأرقام --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
                            <div>
                                <h4 class="mb-1">{{ $empName }}</h4>
                                <div class="stk-def--inline stk-def">
                                    <span class="stk-def__row"><i class="feather icon-calendar"></i> <b>{{ $run->period_label }}</b></span>
                                    @if ($run->salary_slip_id && hasPermission('payslips.show'))
                                        <span class="stk-def__row"><a href="{{ route('salarySlip.show', $run->salary_slip_id) }}"><i class="feather icon-link"></i> القسيمة #{{ $run->salary_slip_id }}</a></span>
                                    @endif
                                    @if ($payroll)
                                        <span class="stk-def__row">
                                            @if (hasPermission('payroll.show'))
                                                <a href="{{ route('PayrollProcess.show', $payroll->id) }}"><i class="feather icon-layers"></i> {{ $payroll->name }}</a>
                                            @else
                                                <i class="feather icon-layers"></i> {{ $payroll->name }}
                                            @endif
                                        </span>
                                    @endif
                                    @if ($run->scheduled_date)
                                        <span class="stk-def__row"><i class="feather icon-clock"></i> يوم الصرف <b class="stk-num">{{ $run->scheduled_date->format('Y-m-d') }}</b></span>
                                    @endif
                                </div>
                            </div>
                            <span class="stk-state stk-state--{{ $run->stage_tone }}">{{ $run->stage_label }}</span>
                        </div>
                    </div>
                    <div class="stk-metrics">
                        <div class="stk-metric">
                            <span class="stk-metric__label">الأساسي</span>
                            <span class="stk-metric__value stk-num">{{ $money($run->basic_amount) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--success">
                            <span class="stk-metric__label">البدلات والتسويات</span>
                            <span class="stk-metric__value stk-num">+{{ $money($run->total_additions) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--danger">
                            <span class="stk-metric__label">الخصميات</span>
                            <span class="stk-metric__value stk-num">−{{ $money($run->total_deductions) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--warning">
                            <span class="stk-metric__label">السلف</span>
                            <span class="stk-metric__value stk-num">−{{ $money($run->total_advances_deducted) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--info">
                            <span class="stk-metric__label">الصافي</span>
                            <span class="stk-metric__value stk-num">{{ $money($run->net_salary) }}</span>
                        </div>
                    </div>
                    @if (! empty($gosiExplanation))
                        <div class="card-body pt-1 pb-1">
                            <small class="stk-help m-0">
                                <i class="feather icon-shield"></i> <strong>التأمينات:</strong> {{ $gosiExplanation }}
                                @if ($run->isDisbursed())
                                    <span class="text-muted">(حسب بطاقة الموظف والإعدادات الحالية — المستقطَع فعلاً في البنود أدناه)</span>
                                @endif
                            </small>
                        </div>
                    @endif
                </div>

                @if ($run->isPending())
                    <div class="alert alert-info">
                        <i class="feather icon-info"></i>
                        الأرقامُ النهائية تُحتسب لحظةَ الصرف: الخصمياتُ المعتمدة والسلفُ المستحقّة والتأمينات تُقرأ حينها، لا عند توليد الصرفة.
                    </div>
                @endif

                {{-- البنود --}}
                @foreach ([
                    ['rows' => $additions, 'title' => 'الاستحقاقات', 'icon' => 'plus-circle', 'sign' => '+', 'cls' => ''],
                    ['rows' => $deductions, 'title' => 'الخصميات المقتطعة', 'icon' => 'minus-circle', 'sign' => '−', 'cls' => 'text-danger'],
                    ['rows' => $advances, 'title' => 'السلف المسدَّدة', 'icon' => 'corner-down-left', 'sign' => '−', 'cls' => 'text-warning'],
                    ['rows' => $employer, 'title' => 'مساهمة المنشأة (لا تُنقص الصافي)', 'icon' => 'shield', 'sign' => '', 'cls' => 'text-muted'],
                ] as $block)
                    @if ($block['rows']->count())
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-{{ $block['icon'] }}"></i> {{ $block['title'] }}
                                    <span class="stk-count">{{ $block['rows']->count() }}</span></h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>البند</th><th>المصدر</th><th class="text-end">المبلغ</th></tr></thead>
                                    <tbody>
                                        @foreach ($block['rows'] as $item)
                                            <tr>
                                                <td>{{ $item->name }}
                                                    @if ($item->description)<small class="d-block text-muted">{{ $item->description }}</small>@endif
                                                </td>
                                                <td>
                                                    @if ($item->source_table === 'deductions' && $item->source_id && Route::has('hr.deductions.show'))
                                                        <a href="{{ route('hr.deductions.show', $item->source_id) }}">خصمية #{{ $item->source_id }}</a>
                                                    @else
                                                        <small class="text-muted">{{ $item->source_table ?? '—' }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-end stk-num {{ $block['cls'] }}"><strong>{{ $block['sign'] }}{{ $money($item->amount) }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if ($retros->count())
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-rotate-cw"></i> تسويات رجعية
                                <span class="stk-count">{{ $retros->count() }}</span></h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>البند</th><th>السبب</th><th class="text-end">المبلغ</th>@if ($canRetro)<th style="width:60px"></th>@endif</tr></thead>
                                <tbody>
                                    @foreach ($retros as $item)
                                        <tr>
                                            <td>{{ $item->name }}</td>
                                            <td><small class="text-muted">{{ $item->description ?? '—' }}</small></td>
                                            <td class="text-end stk-num text-info"><strong>+{{ $money($item->amount) }}</strong></td>
                                            @if ($canRetro)
                                                <td class="text-center">
                                                    <form method="POST" action="{{ route('payroll-runs.retro.remove', [$run->id, $item->id]) }}" onsubmit="return confirm('حذف هذه التسوية؟');">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="حذف"><i class="feather icon-trash-2"></i></button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @include('hr::salaries.payroll_runs.partials.journal_trail', [
                    'trail' => $journalTrail ?? collect(),
                    'pendingDeductions' => $pendingDeductions ?? collect(),
                    'trailMoney' => $money,
                ])
            </div>

            <div class="col-lg-4">
                {{-- الإجراءاتُ انتقلت إلى ترويسة الصفحة؛ يبقى هنا سببُ ما لا يُتاح --}}
                @if ($blockReason || $run->isFinanciallyClosed())
                <div class="card">
                    <div class="card-body">
                        @if (! $canPay && ! $canPayEarly && $blockReason)
                            <div class="alert alert-warning small mb-1"><i class="feather icon-lock"></i> {{ $blockReason }}</div>
                        @endif

                        @if ($run->isFinanciallyClosed())
                            <p class="stk-help mb-0">
                                <i class="feather icon-lock"></i>
                                {{ $run->isDisbursed() ? 'سجلٌ مدفوع: لا تُضاف إليه تسويةٌ ولا يُوقف ولا يُلغى — تصحيحُه بالعكس.' : 'سجلٌ ملغى: لا عمليات مالية عليه.' }}
                            </p>
                        @endif
                    </div>
                </div>
                @endif

                {{-- تفاصيل الصرف --}}
                <div class="card">
                    <div class="card-body">
                        <div class="stk-section__head">
                            <i class="feather icon-info"></i>
                            <h6 class="stk-section__title">تفاصيل الصرف</h6>
                        </div>
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>تاريخ الصرف المجدول</dt><dd class="stk-num">{{ optional($run->scheduled_date)->format('Y-m-d') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>تاريخ الصرف الفعلي</dt><dd class="stk-num">{{ $run->disbursed_at ? $run->disbursed_at->format('Y-m-d H:i') : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>صُرف بواسطة</dt><dd>{{ optional($run->disburser)->name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>مصدر التشغيل</dt><dd>{{ $run->isDisbursed() ? ($run->trigger_source === 'cron' ? 'تلقائي (مجدول)' : 'يدوي') : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>الخزينة</dt><dd>{{ optional($run->treasury)->name ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                {{-- سند الصرف — القيودُ كلُّها في لوحة «القيود المحاسبية» بالعمود الرئيسي --}}
                @if ($run->expense)
                    <div class="card">
                        <div class="card-body">
                            <dl class="stk-def mb-0">
                                <div class="stk-def__row"><dt>سند الصرف</dt><dd>{{ $run->expense->code }}</dd></div>
                            </dl>
                        </div>
                    </div>
                @endif

                @if ($run->isHeld())
                    <div class="alert alert-warning">
                        <strong>موقوفة:</strong> {{ $run->hold_reason }}
                        <small class="d-block">{{ optional($run->held_at)->format('Y-m-d H:i') }}{{ $run->holder ? ' — ' . $run->holder->name : '' }}</small>
                    </div>
                @endif
                @if ($run->reversed_at)
                    <div class="alert alert-warning">
                        <strong>عُكس سابقاً:</strong> {{ $run->reversal_reason }}
                        <small class="d-block">{{ optional($run->reversed_at)->format('Y-m-d H:i') }}</small>
                    </div>
                @endif
                @if ($run->isCancelled() && $run->cancellation_reason)
                    <div class="alert alert-secondary"><strong>سبب الإلغاء:</strong> {{ $run->cancellation_reason }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════ النوافذ ═══════════════ --}}
    @if ($showPayModal)
        @php $defaultTreasuryId = old('treasury_id', $run->treasury_id ?? optional($run->slip)->treasury_id); @endphp
        <div class="modal fade" id="disburseModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('payroll-runs.disburse', $run->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-money-bill-wave text-success"></i> صرف الراتب</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="disburse_treasury_id">خزينة الصرف
                                    <i class="feather icon-help-circle stk-tip" title="القائمة تعرض الخزائن التي يجوز لك السحب منها. يُفحص رصيدها تحت قفلٍ لحظة الصرف، ويُرفض الصرف إن لم يكفِ."></i>
                                </label>
                                <select name="treasury_id" id="disburse_treasury_id" class="form-control">
                                    <option value="">— الخزينة الافتراضية (القسيمة ← خزينة الموظف ← الرئيسية) —</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}" {{ (string) $defaultTreasuryId === (string) $treasury->id ? 'selected' : '' }}>
                                            {{ $treasury->name }} — {{ $money($treasury->balance) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="stk-help">الرصيد المعروض تقريبي؛ القرار يُبنى على الرصيد المحتسب من القيود عند الصرف.</span>
                            </div>
                            @if ($canPayEarly)
                                <div class="alert alert-info small">
                                    <label class="mb-0 d-flex align-items-start" style="gap:8px">
                                        <input type="checkbox" name="early" value="1" required class="mt-1">
                                        <span><strong>صرف مبكر:</strong> يوم صرف هذه الصرفة <span class="stk-num">{{ optional($run->scheduled_date)->format('Y-m-d') }}</span> لم يحل بعد. أؤكّد صرفها الآن.</span>
                                    </label>
                                </div>
                            @endif
                            <div class="alert alert-warning small mb-0">
                                <i class="feather icon-alert-triangle"></i>
                                سيُنشأ سند صرف وقيدا الاستحقاق والصرف ويصير السجل «مدفوعاً». الصافي المعروض ({{ $money($run->net_salary) }}) تقديري — يُعاد احتسابه لحظة الصرف بالخصميات والسلف المستحقة.
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

    @if ($canCancel)
        <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('payroll-runs.cancel', $run->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">إلغاء سجل الصرف</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <label for="cancellation_reason">سبب الإلغاء</label>
                            <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" maxlength="500">{{ old('cancellation_reason') }}</textarea>
                            <span class="stk-help">لا قيد ولا سند لسجلٍّ معلّق، فالإلغاء لا يمسّ الدفاتر.</span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-danger">تأكيد الإلغاء</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canReverse)
        <div class="modal fade" id="reverseModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('payroll-runs.reverse', $run->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">عكس صرف الراتب</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <p class="stk-help">يُنشأ قيدٌ عكسي ولا يُحذف الأصلي، وتعود الخصميات والسلف غير مسوّاة، ويعود السجلّ معلّقاً لإعادة الصرف.</p>
                            <label for="reversal_reason" class="stk-req">سبب العكس</label>
                            <textarea name="reversal_reason" id="reversal_reason" class="form-control @error('reversal_reason') is-invalid @enderror" rows="3" required minlength="3" maxlength="500">{{ old('reversal_reason') }}</textarea>
                            @error('reversal_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-danger">تأكيد العكس</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canHold)
        <div class="modal fade" id="holdModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('payroll-runs.hold', $run->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">إيقاف صرف الراتب</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <p class="stk-help">يبقى السجلّ معلّقاً ولا يُصرف يدوياً ولا تلقائياً حتى يُرفع الإيقاف.</p>
                            <label for="hold_reason" class="stk-req">سبب الإيقاف</label>
                            <textarea name="hold_reason" id="hold_reason" class="form-control @error('hold_reason') is-invalid @enderror" rows="3" required minlength="3" maxlength="500">{{ old('hold_reason') }}</textarea>
                            @error('hold_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-warning">تأكيد الإيقاف</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canRetro)
        <div class="modal fade" id="retroModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="POST" action="{{ route('payroll-runs.retro.add', $run->id) }}" class="w-100">
                    @csrf
                    <div class="modal-content text-right">
                        <div class="modal-header"><h5 class="modal-title">تسوية رجعية</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button></div>
                        <div class="modal-body">
                            <p class="stk-help">فروقاتُ زيادةٍ عن فترةٍ سابقة تُصرف مع هذا السجلّ ولا تُخلط ببدلات الشهر.</p>
                            <div class="form-group">
                                <label for="retro_name" class="stk-req">اسم البند</label>
                                <input type="text" name="name" id="retro_name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', 'تسوية رجعية') }}" required maxlength="191">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label for="retro_amount" class="stk-req">المبلغ</label>
                                <input type="number" name="amount" id="retro_amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('amount') }}" required>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label for="retro_reason" class="stk-req">سبب التسوية</label>
                                <textarea name="reason" id="retro_reason" class="form-control @error('reason') is-invalid @enderror" rows="3" required minlength="3">{{ old('reason') }}</textarea>
                                @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="retro_from">الفترة من</label>
                                    <input type="date" name="period_from" id="retro_from" class="form-control" value="{{ old('period_from') }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="retro_to">إلى</label>
                                    <input type="date" name="period_to" id="retro_to" class="form-control @error('period_to') is-invalid @enderror" value="{{ old('period_to') }}">
                                    @error('period_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">رجوع</button>
                            <button class="btn btn-primary">إضافة</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // إعادةُ فتح النافذة التي رُدّ منها النموذجُ بخطأ تحقّق — فلا يضيع ما كُتب
        $(function () {
            @if ($errors->has('reversal_reason'))
                $('#reverseModal').modal('show');
            @elseif ($errors->has('hold_reason'))
                $('#holdModal').modal('show');
            @elseif ($errors->hasAny(['name', 'amount', 'reason', 'period_to']))
                $('#retroModal').modal('show');
            @endif
            $('#disburse_treasury_id').select2({ dir: 'rtl', width: '100%', dropdownParent: $('#disburseModal') });
        });
    </script>
@endpush
