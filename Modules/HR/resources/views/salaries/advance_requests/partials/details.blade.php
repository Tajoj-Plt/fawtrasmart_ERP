{{--
    تفاصيلُ السلفة ومراحلُها — مشتركةٌ بين «طلبات السلف» و«إدارة السلف».

    الوسائط: $request (SalaryAdvanceRequest)
--}}
@php
    $adv = $request;
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);
@endphp

<div class="row">
    <div class="col-lg-6">
        <dl class="stk-def">
            <div class="stk-def__row">
                <dt>رقم السلفة</dt>
                <dd class="stk-num">#{{ $adv->id }}</dd>
            </div>
            <div class="stk-def__row">
                <dt>الموظف</dt>
                <dd>{{ $adv->employee->full_name ?? '—' }}</dd>
            </div>
            <div class="stk-def__row">
                <dt>المبلغ</dt>
                <dd><strong>{{ $money($adv->amount) }}</strong></dd>
            </div>
            <div class="stk-def__row">
                <dt>النوع</dt>
                <dd>
                    @if ($adv->isScheduled())
                        مجدولة على <span class="stk-num">{{ $adv->installments_count }}</span> قسط
                        ({{ $money($adv->installment_amount) }} شهرياً من {{ $adv->start_month }})
                    @else
                        عادية — تُسترَدّ دفعةً واحدة
                    @endif
                </dd>
            </div>
            <div class="stk-def__row">
                <dt>تاريخ الطلب</dt>
                <dd>{{ optional($adv->requested_date)->format('Y-m-d') }}</dd>
            </div>
            <div class="stk-def__row">
                <dt>المعتمِد المختار</dt>
                <dd>{{ $adv->approver->name ?? '—' }}</dd>
            </div>
            <div class="stk-def__row">
                <dt>السبب</dt>
                <dd>{{ $adv->reason ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="col-lg-6">
        <dl class="stk-def">
            <div class="stk-def__row">
                <dt>المرحلة</dt>
                <dd><span class="stk-state {{ $adv->stageClass() }}">{{ $adv->stageLabel() }}</span></dd>
            </div>
            @if ($adv->approved_date && in_array($adv->status, ['approved', 'rejected', 'cancelled'], true) && $adv->approved_by)
                <div class="stk-def__row">
                    <dt>{{ $adv->status === 'rejected' ? 'رُفضت' : 'اعتُمدت' }}</dt>
                    <dd>{{ $adv->approved_date->format('Y-m-d H:i') }} — {{ $adv->approvedBy->name ?? '—' }}</dd>
                </div>
            @endif
            @if ($adv->disbursed_at)
                <div class="stk-def__row">
                    <dt>صُرفت</dt>
                    <dd>{{ $adv->disbursed_at->format('Y-m-d H:i') }} — من {{ $adv->treasury->name ?? '—' }}
                        @if ($adv->disbursedBy) <span class="text-muted">({{ $adv->disbursedBy->name }})</span> @endif
                    </dd>
                </div>
                <div class="stk-def__row">
                    <dt>المسدَّد / المتبقّي</dt>
                    <dd>
                        {{ $money($adv->repaidAmount()) }} / <strong>{{ $money($adv->remainingAmount()) }}</strong>
                        @if ($adv->isScheduled())
                            <span class="text-muted">— أقساط {{ (int) $adv->paid_installments }}/{{ $adv->installments_count }}</span>
                        @endif
                    </dd>
                </div>
                @if ($adv->is_settled)
                    <div class="stk-def__row">
                        <dt>أُقفلت</dt>
                        <dd>{{ optional($adv->settled_date)->format('Y-m-d') }}
                            — {{ $adv->settlement_type === 'salary' ? 'باقتطاع من الراتب' : 'بسدادٍ نقدي' }}
                            @if ($adv->salary_slip_id) <span class="text-muted">(قسيمة #{{ $adv->salary_slip_id }})</span> @endif
                        </dd>
                    </div>
                @endif
            @endif
            @if ($adv->status === 'rejected' && $adv->rejection_reason)
                <div class="stk-def__row">
                    <dt>سبب الرفض</dt>
                    <dd class="text-danger">{{ $adv->rejection_reason }}</dd>
                </div>
            @endif
            @if ($adv->status === 'cancelled')
                <div class="stk-def__row">
                    <dt>أُلغيت</dt>
                    <dd>{{ optional($adv->cancelled_at)->format('Y-m-d H:i') }} — {{ $adv->cancelledBy->name ?? '—' }}</dd>
                </div>
                <div class="stk-def__row">
                    <dt>سبب الإلغاء</dt>
                    <dd class="text-danger">{{ $adv->cancellation_reason ?: '—' }}</dd>
                </div>
            @endif
            @if ($adv->admin_notes)
                <div class="stk-def__row">
                    <dt>ملاحظات الإدارة</dt>
                    <dd>{{ $adv->admin_notes }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>

@if ($adv->isScheduled() && $adv->installments_count)
    @php
        $start = \Carbon\Carbon::parse(($adv->start_month ?: now()->format('Y-m')).'-01');
        $paid = (int) $adv->paid_installments;
    @endphp
    <div class="stk-section mt-3 mb-0">
        <div class="stk-section__head">
            <i class="feather icon-calendar"></i>
            <h6 class="stk-section__title">جدول الأقساط</h6>
            <span class="stk-count">{{ $paid }}/{{ $adv->installments_count }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الشهر</th>
                        <th class="text-center">القسط</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $adv->installments_count; $i++)
                        @php $month = $start->copy()->addMonths($i); @endphp
                        <tr>
                            <td class="stk-num">{{ $i + 1 }}</td>
                            <td>{{ $month->format('Y-m') }}</td>
                            <td class="text-center">{{ $money($adv->installment_amount) }}</td>
                            <td class="text-center">
                                @if ($i < $paid)
                                    <span class="stk-state stk-state--neutral">مسدَّد</span>
                                @elseif ($adv->status === 'cancelled')
                                    <span class="stk-state stk-state--off">ملغى</span>
                                @elseif ($month->format('Y-m') <= now()->format('Y-m'))
                                    <span class="stk-state stk-state--warn">مستحق</span>
                                @else
                                    <span class="stk-state stk-state--on">قادم</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
@endif
