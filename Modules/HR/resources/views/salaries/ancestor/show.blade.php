@extends('hr::master')

@section('title')
    السلفة {{ $ancestor->code ?? '#'.$id }}
@stop

@section('content')
    @php
        $money = fn ($v) => \App\Support\CurrencySettings::format($v);
        $status = $ancestor->status ?? 'active';
        $isOpen = $status === 'active';
        $remaining = max(0, (float) $ancestor->amount - (float) $totalPaid);
        $stage = $status === 'cancelled' ? 'cancelled' : ($remaining <= 0.001 ? 'settled' : ($paidInstallments > 0 ? 'repaying' : 'disbursed'));
        [$stageLabel, $stageClass] = \App\Models\SalaryAdvanceRequest::STAGES[$stage];
        $nextPayment = $ancestor->payments->where('status', 'unpaid')->sortBy('due_date')->first();
    @endphp

    @section('page-actions')
        @php
            $canEditSchedule = $isOpen && $paidInstallments === 0 && hasPermission('loans.edit');
            $canCancelHere = $status !== 'cancelled' && hasPermission('loans.cancel') && ! $cancelBlock;
        @endphp
        @if ($isOpen && $nextPayment)
            @permission('loans.settle')
                <a href="{{ route('ancestor.pay', $id) }}" class="btn btn-primary">
                    <i class="fas fa-hand-holding-usd"></i> تحصيل قسط
                </a>
            @endpermission
        @endif
        @if ($canEditSchedule)
            <a href="{{ route('ancestor.edit', $id) }}" class="btn btn-outline-primary">
                <i class="fas fa-edit"></i> تعديل الجدول
            </a>
        @endif
        {{-- لا «طباعة» هنا: `hr.advances.print` يطبع طلبات السلف (salary_advance_requests)،
             وأرقامُ السلف القديمة (salary_advances) تتقاطع معها — فالرابط كان سيطبع سلفةً أخرى. --}}
        @if ($canCancelHere)
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i> المزيد
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-danger" href="#cancelLegacyForm"><i class="fas fa-ban me-2"></i>إلغاء السلفة</a></li>
                </ul>
            </div>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'السلفة ' . ($ancestor->code ?? '#' . $id) . ' — ' . ($ancestor->employee->full_name ?? ''),
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'إدارة السلف' => route('hr.advance-approvals.index'), 'سلفة قديمة' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up-circle"></i></span>
                <span class="stk-metric__label">مبلغ السلفة</span>
                <span class="stk-metric__value">{{ $money($ancestor->amount) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">المسدَّد ({{ $paidInstallments }} قسط)</span>
                <span class="stk-metric__value">{{ $money($totalPaid) }}</span>
            </div>
            <div class="stk-metric {{ $remaining > 0 && $isOpen ? 'stk-metric--danger' : '' }}">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">المتبقّي</span>
                <span class="stk-metric__value">{{ $isOpen ? $money($remaining) : '—' }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">القسط القادم</span>
                <span class="stk-metric__value" style="font-size:.95rem">
                    @if ($isOpen && $nextPayment)
                        {{ \Carbon\Carbon::parse($nextPayment->due_date)->format('Y-m-d') }}
                        @if (\Carbon\Carbon::parse($nextPayment->due_date)->isPast())
                            <small class="text-danger">(متأخر)</small>
                        @endif
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> تفاصيل السلفة</h6>
                        <span class="stk-state {{ $stageClass }}">{{ $stageLabel }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>الموظف</dt><dd>{{ $ancestor->employee->full_name ?? '—' }}</dd></div>
                                    <div class="stk-def__row"><dt>تاريخ التقديم</dt><dd>{{ optional($ancestor->submission_date)->format('Y-m-d') }}</dd></div>
                                    <div class="stk-def__row"><dt>حساب السلف</dt><dd>{{ optional(optional($ancestor->employee)->advanceAccount)->name ?: 'غير مرتبط' }}</dd></div>
                                    <div class="stk-def__row"><dt>الخزينة</dt><dd>{{ $ancestor->treasury->name ?? '—' }}</dd></div>
                                    <div class="stk-def__row">
                                        <dt>قيد الصرف</dt>
                                        <dd>
                                            @if ($disbursement)
                                                @permission('account.journal.view')
                                                    <a href="{{ route('journal.show', $disbursement['journal_id']) }}" target="_blank" class="stk-num">#{{ $disbursement['journal_id'] }}</a>
                                                @else
                                                    <span class="stk-num">#{{ $disbursement['journal_id'] }}</span>
                                                @endpermission
                                            @else
                                                <span class="text-muted">لم يُعثر عليه</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="col-lg-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>معدّل السداد</dt><dd>{{ $ancestor->payment_rate_text }}</dd></div>
                                    <div class="stk-def__row"><dt>قيمة القسط</dt><dd>{{ $money($ancestor->installment_amount) }} × <span class="stk-num">{{ $ancestor->total_installments }}</span></dd></div>
                                    <div class="stk-def__row"><dt>بدء الأقساط</dt><dd>{{ optional($ancestor->installment_start_date)->format('Y-m-d') }}</dd></div>
                                    <div class="stk-def__row">
                                        <dt>من قسيمة الراتب</dt>
                                        <dd>{{ $ancestor->pay_from_salary ? 'نعم — تُقتطع الأقساط المستحقّة مع الراتب' : 'لا — تُحصَّل نقداً' }}</dd>
                                    </div>
                                    @if ($status === 'cancelled')
                                        <div class="stk-def__row">
                                            <dt>أُلغيت</dt>
                                            <dd>{{ optional($ancestor->cancelled_at)->format('Y-m-d H:i') }}
                                                @if ($cancellationJournal) — قيد عكسي <span class="stk-num">#{{ $cancellationJournal->id }}</span> @endif
                                            </dd>
                                        </div>
                                        <div class="stk-def__row"><dt>سبب الإلغاء</dt><dd class="text-danger">{{ $ancestor->cancellation_reason ?: '—' }}</dd></div>
                                    @endif
                                    @if ($ancestor->note)
                                        <div class="stk-def__row"><dt>ملاحظة</dt><dd>{{ $ancestor->note }}</dd></div>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-list"></i> الأقساط
                            <span class="stk-count">{{ $ancestor->payments->count() }}</span>
                        </h6>
                    </div>
                    @if ($ancestor->payments->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th class="text-center">المبلغ</th>
                                        <th>الاستحقاق</th>
                                        <th>الدفع</th>
                                        <th class="text-center">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ancestor->payments->sortBy('due_date') as $payment)
                                        <tr>
                                            <td class="stk-num">{{ $payment->installment_number }}</td>
                                            <td class="text-center">{{ $money($payment->amount) }}</td>
                                            <td>{{ $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('Y-m-d') : '—' }}</td>
                                            <td>{{ $payment->status === 'paid' && $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : '—' }}</td>
                                            <td class="text-center">
                                                @if ($payment->status === 'paid')
                                                    <span class="stk-state stk-state--neutral">مسدَّد</span>
                                                @elseif ($payment->status === 'cancelled')
                                                    <span class="stk-state stk-state--off">ملغى</span>
                                                @elseif ($payment->due_date && \Carbon\Carbon::parse($payment->due_date)->isPast())
                                                    <span class="stk-state stk-state--warn">مستحق</span>
                                                @else
                                                    <span class="stk-state stk-state--on">قادم</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                            <h6 class="stk-empty__title">لا أقساط</h6>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-activity"></i> سجلّ النشاط</h6>
                    </div>
                    <div class="card-body">
                        @if ($logs && count($logs) > 0)
                            <div class="stk-timeline">
                                @foreach ($logs as $date => $dayLogs)
                                    @foreach ($dayLogs as $log)
                                        <div class="stk-timeline__item">
                                            <span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-activity"></i></span>
                                            <div class="stk-timeline__body">
                                                <div class="stk-timeline__text">{{ $log->description }}</div>
                                                <div class="stk-timeline__meta">
                                                    <span class="stk-timeline__day">{{ $log->created_at->format('Y-m-d') }}</span>
                                                    <span class="stk-timeline__time">{{ $log->created_at->format('H:i') }}</span>
                                                    <span class="text-muted">— {{ $log->user->name ?? 'مستخدم غير معروف' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        @else
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                <h6 class="stk-empty__title">لا نشاط مسجَّل</h6>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                @if ($status !== 'cancelled')
                    @permission('loans.cancel')
                        <div class="card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-slash"></i> إلغاء السلفة</h6>
                            </div>
                            @if ($cancelBlock)
                                <div class="card-body">
                                    <p class="stk-help mt-0 text-warning"><i class="feather icon-lock"></i> {{ $cancelBlock }}</p>
                                </div>
                            @else
                                <form action="{{ route('ancestor.cancel', $id) }}" method="POST" id="cancelLegacyForm">
                                    @csrf
                                    <div class="card-body">
                                        <p class="stk-help mt-0">
                                            @if ($disbursement)
                                                يُكتب قيدٌ عكسيّ يردّ {{ $money($ancestor->amount) }} إلى الخزينة التي صُرفت منها، وتُغلق الأقساطُ «ملغاة». السجلُّ يبقى.
                                            @else
                                                لم يُعثر على قيد صرفٍ لهذه السلفة — تُغلق وأقساطُها بلا قيد.
                                            @endif
                                        </p>
                                        <div class="form-group mb-0">
                                            <label for="cancellation_reason" class="stk-req">سبب الإلغاء</label>
                                            <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="2" maxlength="500" required>{{ old('cancellation_reason') }}</textarea>
                                        </div>
                                    </div>
                                    <div class="stk-form-foot">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="feather icon-slash"></i> إلغاء السلفة
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endpermission
                @endif

                @isset($advanceSummary)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-book"></i> حساب سلف الموظف
                                <i class="feather icon-help-circle stk-tip" title="من القيود المحاسبية — كلُّ سلف الموظف لا هذه وحدها"></i>
                            </h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>المصروف</dt><dd>{{ $money($advanceSummary['issued']) }}</dd></div>
                                <div class="stk-def__row"><dt>المسدَّد</dt><dd>{{ $money($advanceSummary['settled']) }}</dd></div>
                                <div class="stk-def__row"><dt>المتبقّي</dt><dd><strong>{{ $money($advanceSummary['remaining']) }}</strong></dd></div>
                            </dl>
                        </div>
                    </div>
                @endisset
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#cancelLegacyForm').on('submit', function () {
                if (this.checkValidity && ! this.checkValidity()) { return true; }
                return confirm('إلغاء السلفة وكتابة القيد العكسي؟ لا يمكن التراجع.');
            });
        });
    </script>
@endpush
