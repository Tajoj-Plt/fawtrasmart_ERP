@extends('hr::master')

@section('title', 'مكافأة نهاية الخدمة')

@php
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
    $canApprove = hasPermission('eos.approve');
    $canSettle = hasPermission('eos.settle');
    $canRecalc = hasPermission('eos.create');
    $canPay = $benefit->canBeDisbursed() && hasPermission('eos.pay');
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [إجراء سير العمل] [المزيد ▾]. الصرفُ النقدي يبقى في
         البطاقة الجانبية لأنه يحتاج اختيار الخزينة. --}}
    @if ($canApprove && $benefit->isDraft())
        <form action="{{ route('hr.eos.approve', $benefit->id) }}" method="POST" class="d-inline"
              onsubmit="return confirm('اعتماد المكافأة وقيد المخصص؟');">
            @csrf
            <button class="btn btn-success" type="submit"><i class="fa fa-check"></i> اعتماد وقيد المخصص</button>
        </form>
    @endif
    @if ($canSettle && $benefit->canBeSettled())
        <form action="{{ route('hr.eos.settle', $benefit->id) }}" method="POST" class="d-inline"
              onsubmit="return confirm('تسوية المخصص إلى مستحقات الموظف؟');">
            @csrf
            <button class="btn btn-success" type="submit"><i class="fa fa-level-down-alt"></i> تسوية إلى مستحقات الموظف</button>
        </form>
    @endif
    @if ($canRecalc && $benefit->isDraft())
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <form action="{{ route('hr.eos.recalculate', $benefit->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="fa fa-sync-alt me-2 text-info"></i>إعادة الحساب</button>
                    </form>
                </li>
            </ul>
        </div>
    @endif
@endsection

@section('content')
    @include('stock::components.page-head', [
        'title' => 'مكافأة: ' . (optional($benefit->employee)->full_name ?? '#' . $benefit->employee_id),
        'icon' => 'award',
        'crumbs' => [
            'نهاية الخدمة' => route('hr.eos.index'),
            'عرض' => null,
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
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
                            <div>
                                <h4 class="mb-1">{{ optional($benefit->employee)->full_name ?? '#' . $benefit->employee_id }}</h4>
                                <span class="stk-tag">{{ $benefit->reasonLabel() }}</span>
                                <span class="text-muted small ml-1">النسبة المطبّقة {{ number_format((float) $benefit->fraction * 100, 0) }}٪</span>
                            </div>
                            <span class="stk-state stk-state--{{ $benefit->statusTone() }}">{{ $benefit->statusLabel() }}</span>
                        </div>
                    </div>
                    <div class="stk-metrics">
                        <div class="stk-metric">
                            <span class="stk-metric__label">سنوات الخدمة</span>
                            <span class="stk-metric__value stk-num">{{ number_format((float) $benefit->years_of_service, 2) }}</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__label">الأجر الشهري</span>
                            <span class="stk-metric__value stk-num">{{ $money($benefit->monthly_wage) }}</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__label">الاستحقاق الكامل</span>
                            <span class="stk-metric__value stk-num">{{ $money($benefit->full_amount) }}</span>
                        </div>
                        <div class="stk-metric stk-metric--info">
                            <span class="stk-metric__label">المبلغ المستحق</span>
                            <span class="stk-metric__value stk-num">{{ $money($benefit->payable_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-calendar"></i>
                                <h6 class="stk-section__title">الخدمة</h6>
                            </div>
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>تاريخ التعيين</dt><dd class="stk-num">{{ optional($benefit->hire_date)->format('Y-m-d') ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>آخر يوم عمل</dt><dd class="stk-num">{{ optional($benefit->last_working_day)->format('Y-m-d') ?? '—' }}</dd></div>
                                @if ($benefit->notes)
                                    <div class="stk-def__row"><dt>ملاحظات</dt><dd>{{ $benefit->notes }}</dd></div>
                                @endif
                            </dl>
                        </div>
                        <div class="stk-section">
                            <div class="stk-section__head">
                                <i class="feather icon-book"></i>
                                <h6 class="stk-section__title">القيود</h6>
                                <i class="feather icon-help-circle stk-tip" title="الاعتماد: مدين مصروف المكافأة / دائن المخصص. التسوية: مدين المخصص / دائن ذمّة الموظف. الصرف: مدين الذمّة / دائن الخزينة."></i>
                            </div>
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>قيد المخصص</dt><dd>{{ optional($benefit->accrualJournal)->reference_number ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>قيد التسوية</dt><dd>{{ optional($benefit->settlementJournal)->reference_number ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>قيد الصرف</dt><dd>{{ $benefit->payment_journal_entry_id ? 'EOS-P-' . $benefit->id : '—' }}
                                    @if ($benefit->expense) <small class="text-muted">· سند {{ $benefit->expense->code }}</small>@endif
                                    @if ($benefit->treasury) <small class="text-muted">· من {{ $benefit->treasury->name }}</small>@endif
                                </dd></div>
                            </dl>
                            @if ($benefit->isSettled())
                                <p class="stk-help mb-0">سُوِّيت إلى ذمّة الموظف ولم تُصرف نقداً بعد — المبلغ ما زال التزاماً على المنشأة.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                @if ($benefit->canBeDisbursed() || $benefit->isPaid())
                <div class="card">
                    <div class="card-body">
                        <div class="stk-section__head">
                            <i class="fas fa-money-bill-wave"></i>
                            <h6 class="stk-section__title">الصرف النقدي</h6>
                        </div>

                        @if ($canPay)
                            <form action="{{ route('hr.eos.pay', $benefit->id) }}" method="POST" class="mt-2"
                                  onsubmit="return confirm('صرف {{ $money($benefit->payable_amount) }} نقداً من الخزينة المختارة؟');">
                                @csrf
                                <div class="form-group">
                                    <label for="eos_treasury_id" class="stk-req">خزينة الصرف
                                        <i class="feather icon-help-circle stk-tip" title="الخزائن التي يجوز لك السحب منها. يُفحص رصيدها تحت قفلٍ لحظة الصرف ويُرفض إن لم يكفِ."></i>
                                    </label>
                                    <select name="treasury_id" id="eos_treasury_id" class="form-control select2 @error('treasury_id') is-invalid @enderror" required>
                                        <option value="">— اختر —</option>
                                        @foreach ($treasuries as $treasury)
                                            <option value="{{ $treasury->id }}" @selected((string) old('treasury_id') === (string) $treasury->id)>
                                                {{ $treasury->name }} — {{ $money($treasury->balance) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('treasury_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-success btn-block" type="submit"><i class="fas fa-money-bill-wave"></i> صرف نقداً</button>
                            </form>
                        @elseif ($benefit->canBeDisbursed())
                            <p class="stk-help mb-0"><i class="feather icon-lock"></i> الصرفُ النقدي يحتاج صلاحية «صرف مكافأة نهاية الخدمة».</p>
                        @endif

                        @if ($benefit->isPaid())
                            <p class="stk-help mb-0"><i class="feather icon-check-circle"></i> صُرفت بتاريخ {{ optional($benefit->paid_at)->format('Y-m-d') }} — لا عمليات مالية بعد الدفع.</p>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
