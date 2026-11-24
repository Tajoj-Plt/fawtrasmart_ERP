@extends('rentalmanagement::realestate.master')

@section('title', 'العقد '.$contract->code)

@section('content')
    @php
        use Modules\RentalManagement\Models\Contract;
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;

        // كل مبلغٍ بعملة العقد المسجَّلة وقت إنشائه
        $money = fn ($v) => $contract->money($v);
        $num = fn ($v) => number_format((float) $v, 2);
        $status = $contract->status;
        $live = in_array($status, ['active', 'renewing'], true);
        $closed = $contract->isClosed();
        // ما استحقّ قبل الإغلاق يبقى ديناً يُحصَّل — إلا الملغى
        $collectible = in_array($status, Contract::COLLECTIBLE_STATUSES, true);
        $pendingRenewal = $contract->renewals->firstWhere('renewal_status', 'review');
        $paidPct = $totals['due'] > 0 ? min(100, round($totals['paid'] / $totals['due'] * 100)) : 0;
        $tenantMobile = $contract->tenant?->mobile ?: $contract->tenant?->phone;
        $carriedOut = $contract->renewedInto && (float) $contract->renewedInto->deposit_carried > 0 ? (float) $contract->renewedInto->deposit_carried : 0;
        $tabs = [['overview', 'info', 'التفاصيل'], ['schedule', 'calendar', 'جدول الاستحقاقات'], ['payments', 'credit-card', 'الدفعات'], ['terms', 'list', 'الشروط والمستندات'], ['history', 'git-commit', 'التجديدات والتعديلات']];
        if ($maintenance !== null) { $tabs[] = ['maintenance', 'tool', 'الصيانة'.($maintenance->count() ? ' ('.$maintenance->count().')' : '')]; }
        $tabs[] = ['activity', 'activity', 'سجل النشاط'];
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if ($status === 'draft')
                @permission('realestate.contracts.edit')
                    <button type="button" class="btn btn-success re-action" data-reload="1" data-url="{{ route('realestate.contracts.submit', $contract->id) }}" data-confirm="إرسال العقد في مساره (اعتماد أو تفعيل)؟"><i class="feather icon-send"></i> إرسال / تفعيل</button>
                @endpermission
            @endif
            @if ($status === 'pending_approval')
                @permission('realestate.contracts.approve')
                    <button type="button" class="btn btn-success re-action" data-reload="1" data-url="{{ route('realestate.contracts.approve', $contract->id) }}" data-confirm="اعتماد العقد ليصير سارياً؟"><i class="feather icon-check-circle"></i> اعتماد</button>
                    <button type="button" class="btn btn-outline-warning re-action" data-reload="1" data-tone="warning" data-url="{{ route('realestate.contracts.reject', $contract->id) }}" data-confirm="إعادة العقد إلى المسودة" data-prompt="سبب الرفض"><i class="feather icon-corner-up-right"></i> رفض</button>
                @endpermission
            @endif
            @if ($collectible && $totals['balance'] + $totals['deposit_open'] > 0)
                @permission('realestate.payments.create')
                    <a href="{{ route('realestate.payments.create', ['contract_id' => $contract->id]) }}" class="btn btn-primary"><i class="feather icon-credit-card"></i> تسجيل دفعة</a>
                @endpermission
            @endif

            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="feather icon-more-horizontal"></i> المزيد</button>
                <div class="dropdown-menu dropdown-menu-end">
                    @if ($contract->isEditable())
                        @permission('realestate.contracts.edit')
                            <a class="dropdown-item" href="{{ route('realestate.contracts.edit', $contract->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                        @endpermission
                    @endif
                    @permission('realestate.contracts.print')
                        <a class="dropdown-item" href="{{ route('realestate.contracts.print', $contract->id) }}" target="_blank" rel="noopener"><i class="feather icon-printer"></i> طباعة العقد</a>
                    @endpermission
                    @if (in_array($status, ['active', 'expired'], true) && ! $contract->renewedInto && ! $pendingRenewal)
                        @permission('realestate.contracts.renew')
                            <a class="dropdown-item" href="{{ route('realestate.contracts.renew', $contract->id) }}"><i class="feather icon-refresh-cw"></i> تجديد العقد</a>
                        @endpermission
                    @endif
                    @if ($live)
                        @permission('realestate.contracts.amend')
                            <a class="dropdown-item" href="#" data-toggle="modal" data-bs-toggle="modal" data-target="#amendModal" data-bs-target="#amendModal"><i class="feather icon-edit-3"></i> تعديل عقد معتمد</a>
                        @endpermission
                        @permission('realestate.contracts.terminate')
                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-bs-toggle="modal" data-target="#terminateModal" data-bs-target="#terminateModal"><i class="feather icon-log-out"></i> إنهاء العقد</a>
                        @endpermission
                    @endif
                    @if (! $closed)
                        @permission('realestate.contracts.cancel')
                            <a class="dropdown-item text-danger re-action" href="#" data-reload="1" data-tone="danger" data-url="{{ route('realestate.contracts.cancel', $contract->id) }}" data-confirm="إلغاء العقد {{ $contract->code }}" data-prompt="سبب الإلغاء"><i class="feather icon-slash"></i> إلغاء العقد</a>
                        @endpermission
                    @endif
                    @if ($status === 'draft')
                        @permission('realestate.contracts.delete')
                            <a class="dropdown-item text-danger re-action" href="#" data-tone="danger" data-method="DELETE" data-url="{{ route('realestate.contracts.destroy', $contract->id) }}" data-confirm="حذف هذه المسودة نهائياً؟"><i class="feather icon-trash-2"></i> حذف المسودة</a>
                        @endpermission
                    @endif
                </div>
            </div>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'العقد '.$contract->code,
            'icon' => 'file-text',
            'sub' => $contract->type_label.' — '.($contract->tenant->display_name ?? '').' — '.($contract->unit->full_label ?? ''),
            'crumbs' => ['الرئيسية' => url('/'), 'العقود' => route('realestate.contracts.index'), $contract->code => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{-- سلسلة التجديد: السابق ← هذا ← اللاحق --}}
        @if ($contract->previous || $contract->renewedInto)
            <div class="alert alert-info d-flex align-items-center flex-wrap" style="gap:.5rem">
                <i class="feather icon-git-commit"></i>
                @if ($contract->previous) تجديدٌ للعقد <a href="{{ route('realestate.contracts.show', $contract->previous->id) }}"><strong>{{ $contract->previous->code }}</strong></a> @endif
                @if ($contract->previous && $contract->renewedInto) · @endif
                @if ($contract->renewedInto) جُدّد بالعقد <a href="{{ route('realestate.contracts.show', $contract->renewedInto->id) }}"><strong>{{ $contract->renewedInto->code }}</strong></a> @endif
            </div>
        @endif

        @if ($pendingRenewal)
            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem">
                <span><i class="feather icon-refresh-cw"></i> طلب تجديد بانتظار الموافقة: {{ $pendingRenewal->new_start_date->format('Y-m-d') }} ← {{ $pendingRenewal->new_end_date->format('Y-m-d') }}
                    بقيمة <strong class="stk-num">{{ $money($pendingRenewal->new_rent_value) }}</strong> ({{ $pendingRenewal->increase_percentage >= 0 ? '+' : '' }}{{ $pendingRenewal->increase_percentage }}%)</span>
                @permission('realestate.contracts.approve_renewal')
                    <span>
                        <button type="button" class="btn btn-sm btn-success re-action" data-url="{{ route('realestate.contracts.renewals.approve', [$contract->id, $pendingRenewal->id]) }}" data-confirm="اعتماد التجديد وإنشاء العقد الجديد؟">اعتماد التجديد</button>
                        <button type="button" class="btn btn-sm btn-outline-danger re-action" data-reload="1" data-tone="danger" data-url="{{ route('realestate.contracts.renewals.reject', [$contract->id, $pendingRenewal->id]) }}" data-confirm="رفض طلب التجديد؟">رفض</button>
                    </span>
                @endpermission
            </div>
        @endif

        {{-- تنبيهاتٌ لا تمنع العمل: هوية منتهية، مالك مختلف، تجاري بلا ضريبة أو بلا رقم ضريبي --}}
        @if (! $closed && $tenantProfile?->idExpired())
            <div class="alert alert-warning"><i class="feather icon-alert-triangle"></i> هوية المستأجر ({{ $tenantProfile->id_type_label }} {{ $tenantProfile->id_number }}) منتهية منذ <strong class="stk-num">{{ $tenantProfile->id_expiry_date->format('Y-m-d') }}</strong> — حدّثها من ملف المستأجر.</div>
        @endif
        @if ($ownerMismatch)
            <div class="alert alert-warning"><i class="feather icon-alert-triangle"></i> المالك المسجَّل على العقد (<strong>{{ $contract->owner->display_name ?? '—' }}</strong>) غير مالك المبنى (<strong>{{ $contract->unit->building->owner->display_name ?? '—' }}</strong>).</div>
        @endif
        @if (! $closed && $contract->contract_type === 'commercial_lease' && blank($contract->tenant?->tax_number))
            <div class="alert alert-warning"><i class="feather icon-alert-triangle"></i> عقد تجاري والمستأجر بلا رقمٍ ضريبي — أكمله من ملف المستأجر لتصحّ الفاتورة الضريبية.</div>
        @endif

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span></span><span class="stk-metric__label">الحالة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span><span class="stk-metric__value stk-num">{{ $money($contract->annual_value) }}</span><span class="stk-metric__label">القيمة السنوية</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span><span class="stk-metric__value stk-num text-success">{{ $money($totals['paid']) }}</span><span class="stk-metric__label">المدفوع من الإيجار ({{ $paidPct }}%)</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-clock"></i></span><span class="stk-metric__value stk-num">{{ $money($totals['balance']) }}</span><span class="stk-metric__label">المتبقّي</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span><span class="stk-metric__value stk-num {{ $totals['overdue'] > 0 ? 'text-danger' : '' }}">{{ $money($totals['overdue']) }}</span><span class="stk-metric__label">متأخرات</span></div>
            @if ($live)
                <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-calendar"></i></span><span class="stk-metric__value stk-num">{{ max(0, $contract->days_left) }} يوماً</span><span class="stk-metric__label">حتى النهاية</span></div>
            @endif
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    @foreach ($tabs as $i => [$id, $icon, $label])
                        <li class="nav-item"><a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-toggle="tab" data-bs-toggle="tab" href="#tab-{{ $id }}" role="tab"><i class="feather icon-{{ $icon }}"></i> {{ $label }}</a></li>
                    @endforeach
                </ul>

                <div class="tab-content pt-2">
                    <div class="tab-pane active" id="tab-overview" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <h6>الأطراف والوحدة</h6>
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>المستأجر</dt><dd>
                                        @if (hasPermission('realestate.relationships.view')) <a href="{{ route('realestate.relationships.client', $contract->tenant_client_id) }}">{{ $contract->tenant->display_name ?? '—' }}</a> @else {{ $contract->tenant->display_name ?? '—' }} @endif
                                        @if ($tenantMobile) <small class="text-muted stk-num d-block">{{ $tenantMobile }}</small> @endif
                                        @if ($tenantProfile?->id_number) <small class="text-muted d-block">{{ $tenantProfile->id_type_label }}: <span class="stk-num">{{ $tenantProfile->id_number }}</span></small> @endif
                                    </dd></div>
                                    <div class="stk-def__row"><dt>المالك / المؤجِّر</dt><dd>
                                        {{ $contract->owner->display_name ?? ($ownership === 'own' ? 'المنشأة (مبنى مملوك)' : '—') }}
                                        <small class="text-muted d-block">{{ $ownership === 'own' ? 'مبنى مملوك للمنشأة' : 'مبنى مُدار لمالكٍ خارجي' }}</small>
                                    </dd></div>
                                    <div class="stk-def__row"><dt>الوحدة</dt><dd>
                                        @if ($contract->unit && hasPermission('realestate.units.show')) <a href="{{ route('realestate.units.show', $contract->unit_id) }}">{{ $contract->unit->full_label }}</a> @else {{ $contract->unit->full_label ?? '—' }} @endif
                                    </dd></div>
                                    {{-- أين يُقيَّد الإيجار: جاري المالك للمبنى المُدار، وإيرادات الإيجار للمملوك --}}
                                    <div class="stk-def__row"><dt>{{ $postingAccount['label'] }}</dt><dd>@include('rentalmanagement::realestate.components.account-link', ['account' => $postingAccount['account']])</dd></div>
                                    @if ($contract->listing) <div class="stk-def__row"><dt>العرض المصدر</dt><dd>{{ $contract->listing->code }} — {{ $contract->listing->title }}</dd></div> @endif
                                </dl>
                            </div>
                            <div class="col-lg-6">
                                <h6>المدّة والقيمة</h6>
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>الفترة</dt><dd class="stk-num">{{ $contract->start_date->format('Y-m-d') }} ← {{ $contract->end_date->format('Y-m-d') }} ({{ $contract->duration_label }})
                                        @if ($contract->is_historical) <span class="badge badge-light-secondary">تسجيل تاريخي</span> @endif</dd></div>
                                    <div class="stk-def__row"><dt>العملة</dt><dd class="stk-num">{{ $contract->currency_code }}</dd></div>
                                    <div class="stk-def__row"><dt>إجمالي قيمة العقد</dt><dd class="stk-num">{{ $money($contract->total_value) }}
                                        @if ($contract->taxFactor() > 1) <small class="text-muted d-block">شاملاً الضريبة: {{ $money($contract->gross_total) }}</small> @endif</dd></div>
                                    <div class="stk-def__row"><dt>دورية السداد</dt><dd>{{ V::label(V::PAYMENT_FREQUENCIES, $contract->payment_frequency) }}</dd></div>
                                    <div class="stk-def__row"><dt>الضريبة</dt><dd>{{ $contract->tax_label }}</dd></div>
                                    <div class="stk-def__row"><dt>التأمين</dt><dd class="stk-num">{{ $money($contract->security_deposit) }} <small class="text-muted">(مقبوض {{ $money($totals['deposit_paid']) }})</small>
                                        @if ((float) $contract->deposit_carried > 0) <small class="text-muted d-block">+ مرحَّل من العقد السابق: {{ $money($contract->deposit_carried) }}</small> @endif
                                        @if ($carriedOut > 0) <small class="text-muted d-block">رُحِّل إلى عقد التجديد: {{ $money($carriedOut) }}</small> @endif
                                        @if (! $closed) <small class="d-block">المحتجز الآن: <strong>{{ $money($totals['deposit_held']) }}</strong></small> @endif
                                    </dd></div>
                                    <div class="stk-def__row"><dt>التوقيع</dt><dd class="stk-num">{{ $contract->signed_at?->format('Y-m-d') ?? '—' }}</dd></div>
                                    <div class="stk-def__row"><dt>الاعتماد</dt><dd>{{ $contract->approver->name ?? '—' }} <small class="text-muted stk-num">{{ $contract->approved_at?->format('Y-m-d H:i') }}</small></dd></div>
                                    <div class="stk-def__row"><dt>أنشأه</dt><dd>{{ $contract->creator->name ?? '—' }} <small class="text-muted stk-num">{{ $contract->created_at->format('Y-m-d') }}</small></dd></div>
                                </dl>
                            </div>
                        </div>
                        @if ($contract->terminated_at)
                            <div class="alert alert-secondary mt-1 mb-0">
                                <strong>{{ $status === 'cancelled' ? 'أُلغي' : 'أُنهي' }} بتاريخ {{ $contract->terminated_at->format('Y-m-d') }}</strong> — {{ $contract->termination_reason }}
                                @if ($status !== 'cancelled') <span class="d-block stk-num">تسوية التأمين: خُصم {{ $money($contract->deposit_deducted) }} · رُدّ للمستأجر {{ $money($contract->deposit_refunded) }}</span> @endif
                            </div>
                        @endif
                        {{-- عقدٌ أُغلق وتأمينه ما زال في «تأمينات المستأجرين» بلا قيد تسوية (ومنه ما أُنهي قبل إلزام القيد) --}}
                        @php
                            $unsettledDeposit = $closed || $status === 'expired' ? app(\Modules\RentalManagement\Services\DepositService::class)->held($contract) : 0;
                            $depositPaymentId = $unsettledDeposit > 0 ? \Illuminate\Support\Facades\DB::table('re_payment_allocations as a')
                                ->join('re_payments as p', 'p.id', '=', 'a.payment_id')->join('re_payment_schedules as s', 's.id', '=', 'a.schedule_id')
                                ->where('s.contract_id', $contract->id)->where('s.kind', 'deposit')->where('p.posting_status', 'posted')->value('p.id') : null;
                        @endphp
                        @if ($unsettledDeposit > 0)
                            <div class="alert alert-warning mt-1 mb-0">
                                <i class="feather icon-alert-triangle"></i> تأمين <strong class="stk-num">{{ $money($unsettledDeposit) }}</strong> ما زال محتجَزاً في الدفاتر بلا قيد تسوية.
                                @if ($depositPaymentId && hasPermission('realestate.payments.show'))
                                    <a href="{{ route('realestate.payments.show', $depositPaymentId) }}" class="alert-link">سوِّ التأمين (ردّ/خصم) من صفحة دفعته</a>
                                @endif
                            </div>
                        @endif
                        @if ($contract->notes) <p class="mt-1 mb-0">{{ $contract->notes }}</p> @endif
                    </div>

                    <div class="tab-pane" id="tab-schedule" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap" style="gap:.5rem">
                            <div class="flex-grow-1" style="max-width:420px">
                                <div class="d-flex justify-content-between"><small>المسدَّد من الإيجار</small><small class="stk-num">{{ $paidPct }}%</small></div>
                                <div class="re-progress"><span class="bg-success" style="width:{{ $paidPct }}%"></span></div>
                            </div>
                            {{-- لا إعادة بناء لعقدٍ أُغلق (ملغى/منتهي/مُنهى) ولا بعد أول تحصيل — بعده يُستعمل «تعديل عقد معتمد» --}}
                            @if (! $closed && ! $contract->payments->count())
                                @permission('realestate.payments.regenerate_schedule')
                                    <button type="button" class="btn btn-sm btn-outline-secondary re-action" data-reload="1" data-tone="warning" data-url="{{ route('realestate.contracts.schedule.regenerate', $contract->id) }}" data-confirm="إعادة بناء الجدول من شروط العقد الحالية؟"><i class="feather icon-refresh-cw"></i> إعادة بناء الجدول</button>
                                @endpermission
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>#</th><th>النوع</th><th>الاستحقاق</th><th class="text-end">المستحق ({{ $contract->currency_code }})</th><th class="text-end">المدفوع</th><th class="text-end">المتبقّي</th><th class="text-center">الحالة</th><th></th></tr></thead>
                                <tbody>
                                    @forelse ($contract->schedules as $schedule)
                                        <tr>
                                            <td class="stk-num">{{ $schedule->installment_no ?: '—' }}</td>
                                            <td>{{ $schedule->kind_label }}</td>
                                            <td class="stk-num">{{ $schedule->due_date->format('Y-m-d') }}</td>
                                            <td class="text-end re-num">{{ $money($schedule->amount_due) }}</td>
                                            <td class="text-end re-num">{{ $money($schedule->amount_paid) }}</td>
                                            <td class="text-end re-num">{{ $money($schedule->status === 'cancelled' ? 0 : $schedule->balance) }}</td>
                                            <td class="text-center"><span class="badge badge-light-{{ $schedule->status_meta['tone'] }}">{{ $schedule->status_meta['label'] }}</span></td>
                                            <td class="text-end">
                                                @if ($schedule->balance > 0 && $schedule->status !== 'cancelled' && $collectible)
                                                    @permission('realestate.payments.create')
                                                        <a href="{{ route('realestate.payments.create', ['contract_id' => $contract->id, 'schedule_id' => $schedule->id]) }}" class="btn btn-sm btn-outline-primary">سداد</a>
                                                    @endpermission
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted">لا استحقاقات.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($contract->schedules->isNotEmpty())
                                    @php $open = $contract->schedules->where('status', '!=', 'cancelled'); @endphp
                                    <tfoot><tr class="stk-total-row"><td colspan="3">الإجمالي (بلا الملغاة)</td>
                                        <td class="text-end re-num">{{ $money($open->sum('amount_due')) }}</td><td class="text-end re-num">{{ $money($open->sum('amount_paid')) }}</td>
                                        <td class="text-end re-num">{{ $money($open->sum('amount_due') - $open->sum('amount_paid')) }}</td><td colspan="2"></td></tr></tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="tab-payments" role="tabpanel">
                        @if (! hasPermission('realestate.payments.view'))
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'lock', 'title' => 'الدفعات محجوبة', 'hint' => 'عرضها يتطلّب صلاحية «عرض الدفعات»'])
                        @elseif ($contract->payments->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'credit-card', 'title' => 'لا دفعات', 'hint' => 'لم تُسجَّل دفعة على هذا العقد بعد'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>الدفعة</th><th>التاريخ</th><th>الطريقة</th><th>المرجع</th><th class="text-end">المبلغ</th><th class="text-center">الترحيل</th></tr></thead>
                                    <tbody>
                                        @foreach ($contract->payments as $payment)
                                            <tr>
                                                <td><a href="{{ route('realestate.payments.show', $payment->id) }}">{{ $payment->code }}</a></td>
                                                <td class="stk-num">{{ $payment->payment_date->format('Y-m-d') }}</td>
                                                <td>{{ $payment->method_label }}</td>
                                                <td class="stk-num">{{ $payment->reference_no ?: '—' }}</td>
                                                <td class="text-end re-num">{{ $money($payment->amount) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $payment->status_meta['tone'] }}">{{ $payment->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-terms" role="tabpanel">
                        @forelse ($contract->terms as $i => $term)
                            <div class="mb-1"><strong>{{ $i + 1 }}. {{ $term->title }}</strong>@if ($term->body)<p class="mb-0 text-muted" style="white-space:pre-line">{{ $term->body }}</p>@endif</div>
                        @empty
                            <p class="text-muted">لا بنود إضافية على هذا العقد.</p>
                        @endforelse
                        <h6 class="mt-2">المستندات</h6>
                        @include('rentalmanagement::realestate.components.file-list', ['paths' => $contract->filePaths('attachments')])
                    </div>

                    <div class="tab-pane" id="tab-history" role="tabpanel">
                        <h6>طلبات التجديد</h6>
                        @if ($contract->renewals->isEmpty())
                            <p class="text-muted">لا طلبات تجديد.</p>
                        @else
                            <div class="table-responsive mb-2">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>التاريخ</th><th>طلبه</th><th>الفترة الجديدة</th><th class="text-end">القيمة الجديدة</th><th class="text-end">الزيادة</th><th>السداد</th><th class="text-end">زيادة التأمين</th><th>العقد الناتج</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($contract->renewals as $renewal)
                                            <tr>
                                                <td class="stk-num">{{ $renewal->created_at->format('Y-m-d') }}</td>
                                                <td>{{ $renewal->creator->name ?? '—' }}</td>
                                                <td class="stk-num">{{ $renewal->new_start_date->format('Y-m-d') }} ← {{ $renewal->new_end_date->format('Y-m-d') }}</td>
                                                <td class="text-end re-num">{{ $money($renewal->new_rent_value) }}</td>
                                                <td class="text-end re-num">{{ $renewal->increase_percentage }}%</td>
                                                <td>{{ V::label(V::PAYMENT_FREQUENCIES, $renewal->payment_frequency) }}</td>
                                                <td class="text-end re-num">{{ $money($renewal->deposit_adjustment) }}</td>
                                                <td>@if ($renewal->renewedContract) <a href="{{ route('realestate.contracts.show', $renewal->renewed_contract_id) }}">{{ $renewal->renewedContract->code }}</a> @else — @endif</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $renewal->status_meta['tone'] }}">{{ $renewal->status_meta['label'] }}</span></td>
                                            </tr>
                                            @if ($renewal->notes)
                                                <tr><td colspan="9" class="text-muted border-top-0 pt-0"><small><i class="feather icon-message-square"></i> {{ $renewal->notes }}</small></td></tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <h6>تعديلات بعد الاعتماد</h6>
                        @if ($contract->amendments->isEmpty())
                            <p class="text-muted mb-0">لم يُعدَّل العقد بعد اعتماده.</p>
                        @else
                            @php $fieldLabels = ['end_date' => 'تاريخ النهاية', 'annual_value' => 'القيمة السنوية', 'notes' => 'الملاحظات']; @endphp
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>التاريخ</th><th>المستخدم</th><th>الحقل</th><th>من</th><th>إلى</th><th>يسري من</th><th>السبب</th></tr></thead>
                                    <tbody>
                                        @foreach ($contract->amendments as $amendment)
                                            <tr>
                                                <td class="stk-num">{{ $amendment->created_at->format('Y-m-d H:i') }}</td>
                                                <td>{{ $amendment->user->name ?? '—' }}</td>
                                                <td>{{ $fieldLabels[$amendment->field] ?? $amendment->field }}</td>
                                                <td class="stk-num">{{ \Illuminate\Support\Str::limit($amendment->old_value, 40) ?: '—' }}</td>
                                                <td class="stk-num">{{ \Illuminate\Support\Str::limit($amendment->new_value, 40) ?: '—' }}</td>
                                                <td class="stk-num">{{ $amendment->effective_date?->format('Y-m-d') ?? '—' }}</td>
                                                <td>{{ $amendment->reason }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- بلاغات الصيانة المرتبطة بهذا العقد — للقراءة فقط --}}
                    @if ($maintenance !== null)
                        <div class="tab-pane" id="tab-maintenance" role="tabpanel">
                            @if ($maintenance->isEmpty())
                                @include('rentalmanagement::realestate.components.empty', ['icon' => 'tool', 'title' => 'لا طلبات صيانة', 'hint' => 'لم يُسجَّل طلب صيانة مرتبط بهذا العقد'])
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead><tr><th>الطلب</th><th>العنوان</th><th>التصنيف</th><th>التاريخ</th><th>التكلفة على</th><th class="text-end">التكلفة الفعلية</th><th class="text-center">الحالة</th></tr></thead>
                                        <tbody>
                                            @foreach ($maintenance as $request)
                                                <tr>
                                                    <td>@if (hasPermission('realestate.maintenance.show') || hasPermission('realestate.maintenance.view')) <a href="{{ route('realestate.maintenance.show', $request->id) }}">{{ $request->code }}</a> @else {{ $request->code }} @endif</td>
                                                    <td>{{ $request->title }}</td>
                                                    <td>{{ $request->category_label }}</td>
                                                    <td class="stk-num">{{ $request->created_at?->format('Y-m-d') }}</td>
                                                    <td>{{ ['owner' => 'المالك', 'tenant' => 'المستأجر', 'company' => 'المنشأة'][$request->charge_to] ?? $request->charge_to }}</td>
                                                    <td class="text-end re-num">{{ $money($request->actual_cost) }}</td>
                                                    <td class="text-center"><span class="badge badge-light-{{ $request->status_meta['tone'] }}">{{ $request->status_meta['label'] }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="tab-pane" id="tab-activity" role="tabpanel">
                        @include('rentalmanagement::realestate.components.timeline', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- إنهاء العقد: مراجعة المستحقات ← تسوية التأمين ← إغلاق ← الوحدة شاغرة --}}
    @if ($live)
        @permission('realestate.contracts.terminate')
            @php $latestEnd = $contract->end_date->lt(now()->startOfDay()) ? $contract->end_date->format('Y-m-d') : now()->format('Y-m-d'); @endphp
            <div class="modal fade" id="terminateModal" tabindex="-1" aria-labelledby="terminateTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content re-ajax-form" data-url="{{ route('realestate.contracts.terminate', $contract->id) }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="terminateTitle">إنهاء العقد {{ $contract->code }}</h5>
                            <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none re-ajax-errors"></div>
                            <dl class="stk-def mb-1">
                                <div class="stk-def__row"><dt>متأخرات قائمة</dt><dd class="stk-num {{ $totals['overdue'] > 0 ? 'text-danger' : '' }}">{{ $money($totals['overdue']) }}</dd></div>
                                <div class="stk-def__row"><dt>تأمين محتجز</dt><dd class="stk-num">{{ $money($totals['deposit_held']) }}@if ((float) $contract->deposit_carried > 0) <small class="text-muted">(منه مرحَّل {{ $money($contract->deposit_carried) }})</small>@endif</dd></div>
                                <div class="stk-def__row"><dt>يُردّ للمستأجر</dt><dd class="stk-num"><strong id="terminateRefund">{{ $num($totals['deposit_held']) }}</strong> {{ $contract->currency_code }}</dd></div>
                            </dl>
                            <p class="text-muted" style="font-size:.85rem">الأقساط غير المسدَّدة التي يحلّ أجلها بعد تاريخ الإنهاء تُلغى، وكذلك قسط التأمين غير المقبوض؛ وما استحقّ قبله يبقى ديناً على المستأجر.</p>
                            <div class="form-group">
                                <label for="terminated_at" class="stk-req">تاريخ الإنهاء</label>
                                <input type="date" id="terminated_at" name="terminated_at" class="form-control" value="{{ $latestEnd }}" min="{{ $contract->start_date->format('Y-m-d') }}" max="{{ $latestEnd }}">
                                <small class="text-muted">لا يتجاوز اليوم ولا نهاية العقد ({{ $contract->end_date->format('Y-m-d') }}).</small>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="deposit_deducted">المخصوم من التأمين (أضرار / مستحقات) — {{ $contract->currency_code }}</label>
                                        <input type="number" step="0.01" min="0" max="{{ $totals['deposit_held'] }}" id="deposit_deducted" name="deposit_deducted" class="form-control" value="0" data-held="{{ $totals['deposit_held'] }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="deduct_to">المخصوم لصالح</label>
                                        <select id="deduct_to" name="deduct_to" class="form-control">
                                            @if ($ownership !== 'own') <option value="owner">المالك</option> @endif
                                            <option value="office">المنشأة</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="treasury_account_id">خزينة / بنك ردّ التأمين</label>
                                <select id="treasury_account_id" name="treasury_account_id" class="form-control">
                                    <option value="">— اختر —</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->account_id }}" @selected((int) $treasury->account_id === (int) ($contract->unit?->building?->default_treasury_account_id))>{{ $treasury->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">يُصرف منها ما يُردّ للمستأجر ويُقيَّد قيد التسوية — مطلوبة متى كان هناك مبلغ يُردّ.</small>
                            </div>
                            <div class="form-group mb-0">
                                <label for="terminate_reason" class="stk-req">سبب الإنهاء ونتيجة فحص الوحدة</label>
                                <textarea id="terminate_reason" name="reason" rows="3" class="form-control" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-danger"><i class="feather icon-log-out"></i> إنهاء العقد</button>
                        </div>
                    </form>
                </div>
            </div>
        @endpermission

        @permission('realestate.contracts.amend')
            <div class="modal fade" id="amendModal" tabindex="-1" aria-labelledby="amendTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content re-ajax-form" data-url="{{ route('realestate.contracts.amend', $contract->id) }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="amendTitle">تعديل عقد معتمد</h5>
                            <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none re-ajax-errors"></div>
                            <p class="text-muted" style="font-size:.85rem">كلّ تغييرٍ يُكتب في سجلّ تعديلات العقد بقيمتَيه ومن غيّره وسببه. تغيير القيمة أو النهاية <strong>يعيد حساب الأقساط غير المدفوعة</strong> المستحقّة من تاريخ السريان ويحدّث إجمالي العقد؛ الأقساط المدفوعة (كلياً أو جزئياً) لا تُمسّ.</p>
                            <div class="form-group">
                                <label for="amend_end_date" class="stk-req">تاريخ النهاية</label>
                                <input type="date" id="amend_end_date" name="end_date" class="form-control" value="{{ $contract->end_date->format('Y-m-d') }}">
                            </div>
                            <div class="form-group">
                                <label for="amend_annual_value" class="stk-req">القيمة السنوية ({{ $contract->currency_code }})</label>
                                <input type="number" step="0.01" min="0" id="amend_annual_value" name="annual_value" class="form-control" value="{{ $contract->annual_value }}">
                            </div>
                            <div class="form-group">
                                <label for="amend_effective_date" class="stk-req">يسري من تاريخ</label>
                                <input type="date" id="amend_effective_date" name="effective_date" class="form-control" value="{{ now()->format('Y-m-d') }}" min="{{ $contract->start_date->format('Y-m-d') }}">
                                <small class="text-muted">الأقساط غير المدفوعة المستحقّة من هذا التاريخ تأخذ القيمة الجديدة؛ ما قبله يبقى بقيمته.</small>
                            </div>
                            <div class="form-group">
                                <label for="amend_notes">الملاحظات</label>
                                <textarea id="amend_notes" name="notes" rows="2" class="form-control">{{ $contract->notes }}</textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label for="amend_reason" class="stk-req">سبب التعديل</label>
                                <textarea id="amend_reason" name="reason" rows="2" class="form-control" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> تسجيل التعديل</button>
                        </div>
                    </form>
                </div>
            </div>
        @endpermission
    @endif
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
    <script>
        $(function () {
            // ما يُردّ للمستأجر = المحتجز − المخصوم
            $('#deposit_deducted').on('input', function () {
                const held = parseFloat($(this).data('held')) || 0;
                const refund = Math.max(0, held - (parseFloat(this.value) || 0));
                $('#terminateRefund').text(refund.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            });
        });
    </script>
@endsection
