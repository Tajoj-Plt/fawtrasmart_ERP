@extends('rentalmanagement::realestate.master')

@section('title', 'الدفعة '.$payment->code)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateMoney;
        use Modules\RentalManagement\Support\RealEstateSettings;
        // كل مبالغ هذه الصفحة بعملة الدفعة المسجَّلة وقت القبض
        $money = fn ($v) => RealEstateMoney::format($v, $payment->currency);
        $num = fn ($v) => RealEstateMoney::number($v);
        $cancelled = $payment->posting_status === 'cancelled';
        $unposted = $payment->posting_status === 'unposted';
        $contract = $payment->contract;
        $anyOwner = $contract?->effectiveOwner();
        $showSettle = $canSettle && $depositHeld > 0;
    @endphp

    <div class="content-body">
        @section('page-actions')
            <a href="{{ route('realestate.payments.receipt', $payment->id) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="feather icon-printer"></i> إيصال</a>
            {{-- دفعةٌ حُصِّل شيكها لا تُلغى من هنا: المال في البنك، وردّه بسند صرف --}}
            @unless ($cancelled || ($payment->received_cheque_id && $payment->posting_status === 'posted'))
                @permission('realestate.payments.cancel')
                    <button type="button" class="btn btn-outline-danger re-action" data-reload="1" data-tone="danger" data-url="{{ route('realestate.payments.cancel', $payment->id) }}"
                            data-confirm="إلغاء الدفعة{{ $payment->journal_entry_id ? ' وعكس قيدها' : '' }}" data-prompt="سبب الإلغاء"><i class="feather icon-rotate-ccw"></i> إلغاء الدفعة</button>
                @endpermission
            @endunless
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الدفعة '.$payment->code,
            'icon' => 'credit-card',
            'sub' => ($payment->client->display_name ?? '').' — عقد '.($contract->code ?? ''),
            'crumbs' => ['الرئيسية' => url('/'), 'الدفعات' => route('realestate.payments.index'), $payment->code => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if ($cancelled)
            <div class="alert alert-danger"><strong>دفعة ملغاة</strong> بتاريخ {{ $payment->cancelled_at?->format('Y-m-d H:i') }} — {{ $payment->cancel_reason }}.@if ($payment->journal_entry_id) الأصل باقٍ في الدفاتر ومعه قيده العكسي.@endif</div>
        @elseif ($unposted)
            <div class="alert alert-warning"><strong>دفعة غير مرحّلة</strong> — سُجّلت والترحيل المحاسبي معطَّل: لا قيد يومية لها ولا حركة في دفتر المالك، ولا تدخل إجماليات «المرحَّل».</div>
        @endif

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span><span class="stk-metric__value"><span class="badge badge-light-{{ $payment->status_meta['tone'] }}">{{ $payment->status_meta['label'] }}</span></span><span class="stk-metric__label">الترحيل</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span><span class="stk-metric__value stk-num">{{ $money($payment->amount) }}</span><span class="stk-metric__label">المبلغ</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-calendar"></i></span><span class="stk-metric__value stk-num">{{ $payment->payment_date->format('Y-m-d') }}</span><span class="stk-metric__label">تاريخ الدفع</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span><span class="stk-metric__value">{{ $payment->method_label }}</span><span class="stk-metric__label">الطريقة</span></div>
        </div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> التفاصيل</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>العميل</dt><dd>{{ $payment->client->display_name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>العقد</dt><dd>
                                @if ($contract && hasPermission('realestate.contracts.show')) <a href="{{ route('realestate.contracts.show', $payment->contract_id) }}">{{ $contract->code }}</a> @else {{ $contract->code ?? '—' }} @endif
                            </dd></div>
                            <div class="stk-def__row"><dt>الوحدة</dt><dd>{{ $contract->unit->full_label ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>ملكية المبنى</dt><dd>
                                @if ($managedOwner) <span class="badge badge-light-info">مُدار لمالكٍ خارجي</span> @else <span class="badge badge-light-primary">مملوك للمنشأة</span> @endif
                            </dd></div>
                            @if ($managedOwner)
                                <div class="stk-def__row"><dt>المالك</dt><dd>@if (hasPermission('realestate.owners.show'))<a href="{{ route('realestate.owners.show', $managedOwner->id) }}">{{ $managedOwner->name }}</a>@else{{ $managedOwner->name }}@endif <small class="text-muted">{{ $managedOwner->agreement_label }}</small></dd></div>
                                <div class="stk-def__row"><dt>حساب المالك الجاري</dt><dd>@include('rentalmanagement::realestate.components.account-link', ['account' => $managedOwner->account])</dd></div>
                            @elseif ($anyOwner)
                                <div class="stk-def__row"><dt>المالك المسجَّل</dt><dd>{{ $anyOwner->name }} <small class="text-muted">— المبنى مملوك للمنشأة فالإيجار إيرادٌ لها</small></dd></div>
                            @endif
                            <div class="stk-def__row"><dt>الخزينة / البنك</dt><dd>{{ $payment->treasuryAccount->name ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>طريقة الدفع</dt><dd>{{ $payment->method_label }}</dd></div>
                            <div class="stk-def__row"><dt>{{ $payment->received_cheque_id ? 'رقم الشيك' : 'المرجع' }}</dt><dd class="stk-num">{{ $payment->reference_no ?: '—' }}</dd></div>
                            @if ($payment->received_cheque_id)
                                @php $cheque = $payment->receivedCheque; $chequeStatus = $cheque?->status instanceof \App\Enums\ChequeStatus ? $cheque->status->getName() : ($cheque ? (string) $cheque->status : 'سُحب من الشيكات'); @endphp
                                <div class="stk-def__row"><dt>الشيك في الحسابات</dt><dd>
                                    <span class="badge badge-light-info">{{ $chequeStatus }}</span>
                                    @if ($payment->cheque_due_date) <small class="text-muted stk-num">يستحق {{ \Illuminate\Support\Carbon::parse($payment->cheque_due_date)->format('Y-m-d') }}</small> @endif
                                    @if ($payment->cheque_bank) <small class="text-muted">— {{ $payment->cheque_bank }}</small> @endif
                                    @if ($cheque && Route::has('received_cheques.show') && hasPermission('account.received_cheques.show'))
                                        <a href="{{ route('received_cheques.show', $cheque->id) }}" target="_blank" class="d-block">فتح الشيك (إيداع / تحصيل / ارتجاع)</a>
                                    @endif
                                </dd></div>
                            @endif
                            <div class="stk-def__row"><dt>العملة</dt><dd class="stk-num">{{ $payment->currency ?: RealEstateMoney::code() }}</dd></div>
                            <div class="stk-def__row"><dt>سجّلها</dt><dd>{{ $payment->creator->name ?? '—' }} <small class="text-muted stk-num">{{ $payment->created_at->format('Y-m-d H:i') }}</small></dd></div>
                        </dl>
                        @if ($payment->notes) <p class="mt-1 mb-0">{{ $payment->notes }}</p> @endif
                        <h6 class="mt-1">المرفقات</h6>
                        @include('rentalmanagement::realestate.components.file-list', ['paths' => $payment->filePaths('attachments')])
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-pie-chart"></i> مكوّنات الدفعة</h4></div>
                    <div class="card-body">
                        <dl class="stk-def mb-0">
                            @if ($parts['rent'] > 0)
                                <div class="stk-def__row"><dt>صافي الإيجار</dt><dd class="stk-num">{{ $money($parts['net_rent']) }}</dd></div>
                                @if ($parts['tax'] > 0)
                                    <div class="stk-def__row"><dt>ضريبة القيمة المضافة ({{ rtrim(rtrim(number_format((float) $contract->tax_rate, 2), '0'), '.') }}%)</dt>
                                        <dd class="stk-num">{{ $money($parts['tax']) }} <small class="text-muted d-block">{{ $managedOwner ? 'محصَّلة لحساب المالك' : 'في حساب ضريبة المنشأة' }}</small></dd></div>
                                @endif
                            @endif
                            @if ($managedOwner && $parts['commission'] > 0)
                                <div class="stk-def__row"><dt>عمولة الإدارة المخصومة من المالك</dt><dd class="stk-num">{{ $money($parts['commission']) }}</dd></div>
                                @if ($parts['commission_tax'] > 0)
                                    <div class="stk-def__row"><dt>ضريبة العمولة (على المالك)</dt><dd class="stk-num">{{ $money($parts['commission_tax']) }}</dd></div>
                                @endif
                                <div class="stk-def__row"><dt>صافي ما قُيِّد للمالك</dt><dd class="stk-num"><strong>{{ $money($parts['net_rent'] + $parts['tax'] + $parts['maintenance'] - $parts['commission'] - $parts['commission_tax']) }}</strong></dd></div>
                            @endif
                            @if ($parts['deposit'] > 0) <div class="stk-def__row"><dt>تأمين</dt><dd class="stk-num">{{ $money($parts['deposit']) }}</dd></div> @endif
                            @if ($parts['maintenance'] > 0) <div class="stk-def__row"><dt>صيانة على المستأجر</dt><dd class="stk-num">{{ $money($parts['maintenance']) }}</dd></div> @endif
                            @if ($parts['advance'] > 0) <div class="stk-def__row"><dt>دفعة مقدّمة</dt><dd class="stk-num">{{ $money($parts['advance']) }}</dd></div> @endif
                        </dl>
                    </div>
                </div>

                {{-- تأمين العقد: المحتجَز وتسوياته — الخصم والردّ بقيدٍ واحد --}}
                @if ($depositHeld > 0 || $settlements->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-shield"></i> تأمين العقد</h4>
                            @if ($showSettle)
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-bs-toggle="modal" data-target="#settleModal" data-bs-target="#settleModal"><i class="feather icon-check-square"></i> تسوية التأمين</button>
                            @endif
                        </div>
                        <div class="card-body">
                            <dl class="stk-def {{ $settlements->isEmpty() ? 'mb-0' : '' }}">
                                <div class="stk-def__row"><dt>التأمين المحتجَز في الدفاتر</dt><dd class="stk-num"><strong>{{ RealEstateMoney::format($depositHeld, $contract->currency) }}</strong></dd></div>
                            </dl>
                            @if ($depositHeld > 0)
                                <small class="text-muted d-block">يبقى التأمين التزاماً في «تأمينات المستأجرين» حتى يُسوّى: يُردّ للمستأجر من خزينة، ويُقيَّد المخصوم للمالك أو إيراداً للمنشأة.</small>
                            @endif
                            @foreach ($settlements as $settlement)
                                <div class="border-top pt-1 mt-1">
                                    <strong class="stk-num">{{ $settlement->code }}</strong> <small class="text-muted stk-num">{{ $settlement->settled_at->format('Y-m-d') }}</small>
                                    <div class="small">
                                        محتجَز {{ RealEstateMoney::format($settlement->held_amount, $settlement->currency) }} ·
                                        مردود {{ RealEstateMoney::format($settlement->refunded_amount, $settlement->currency) }}@if ($settlement->treasuryAccount) من {{ $settlement->treasuryAccount->name }}@endif ·
                                        مخصوم {{ RealEstateMoney::format($settlement->deducted_amount, $settlement->currency) }}@if ($settlement->deducted_amount > 0) لصالح {{ $settlement->deduct_to_label }}@endif
                                    </div>
                                    @if ($settlement->reason) <div class="small text-muted">{{ $settlement->reason }}</div> @endif
                                    @if ($settlement->journalEntry)
                                        <table class="table table-sm mb-0 mt-50">
                                            @foreach ($settlement->journalEntry->details as $detail)
                                                <tr><td>{{ $detail->account->name ?? '#'.$detail->account_id }}</td>
                                                    <td class="text-end re-num">{{ (float) $detail->debit ? $num($detail->debit) : '' }}</td>
                                                    <td class="text-end re-num">{{ (float) $detail->credit ? $num($detail->credit) : '' }}</td></tr>
                                            @endforeach
                                        </table>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-git-merge"></i> التوزيع على الاستحقاقات</h4></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>القسط</th><th>النوع</th><th>الاستحقاق</th><th class="text-end">الموزَّع</th></tr></thead>
                            <tbody>
                                @forelse ($payment->allocations as $allocation)
                                    <tr>
                                        <td class="stk-num">{{ $allocation->schedule->installment_no ?: '—' }}</td>
                                        <td>{{ $allocation->schedule->kind_label ?? '—' }}@if ($allocation->schedule?->description) <small class="text-muted d-block">{{ $allocation->schedule->description }}</small> @endif</td>
                                        <td class="stk-num">{{ $allocation->schedule?->due_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td class="text-end re-num">{{ $money($allocation->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">بلا توزيع.</td></tr>
                                @endforelse
                                @if ((float) $payment->unallocated_amount > 0)
                                    <tr><td colspan="3">دفعة مقدّمة (غير موزَّعة)</td><td class="text-end re-num">{{ $money($payment->unallocated_amount) }}</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-book"></i> القيد المحاسبي</h4>
                        <small class="text-muted">{{ RealEstateMoney::symbol($payment->currency) }}</small></div>
                    @if ($payment->journalEntry)
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>الحساب</th><th>البيان</th><th class="text-end">مدين ({{ $payment->currency ?: \Modules\RentalManagement\Support\RealEstateMoney::code() }})</th><th class="text-end">دائن</th></tr></thead>
                                <tbody>
                                    @foreach ($payment->journalEntry->details as $detail)
                                        <tr>
                                            <td>{{ $detail->account->name ?? '#'.$detail->account_id }}</td>
                                            <td>{{ $detail->description }}</td>
                                            <td class="text-end re-num">{{ (float) $detail->debit ? $num($detail->debit) : '' }}</td>
                                            <td class="text-end re-num">{{ (float) $detail->credit ? $num($detail->credit) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr class="stk-total-row"><td colspan="2">قيد رقم {{ $payment->journalEntry->reference_number }}</td>
                                    <td class="text-end re-num">{{ $num($payment->journalEntry->details->sum('debit')) }}</td><td class="text-end re-num">{{ $num($payment->journalEntry->details->sum('credit')) }}</td></tr></tfoot>
                            </table>
                        </div>
                        @if ($payment->reversal_journal_entry_id) <div class="card-body pt-1"><small class="text-danger">عُكس بالقيد {{ $payment->code }}-REV.</small></div> @endif
                    @else
                        <div class="card-body"><p class="text-muted mb-0">لا قيد — الترحيل المحاسبي كان معطّلاً عند تسجيل هذه الدفعة (غير مرحّلة).</p></div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-activity"></i> أثر التدقيق</h4></div>
                    <div class="card-body">@include('rentalmanagement::realestate.components.timeline', ['logs' => $logs])</div>
                </div>
            </div>
        </div>

        @if ($showSettle)
            <div class="modal fade" id="settleModal" tabindex="-1" aria-labelledby="settleTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content re-ajax-form" data-url="{{ route('realestate.payments.deposits.settle', $contract->id) }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="settleTitle">تسوية تأمين العقد {{ $contract->code }}</h5>
                            <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none re-ajax-errors"></div>
                            <p class="text-muted" style="font-size:.85rem">
                                المحتجَز <strong class="stk-num">{{ RealEstateMoney::format($depositHeld, $contract->currency) }}</strong> —
                                القيد: مدين تأمينات المستأجرين بالمحتجَز، دائن الخزينة بالمردود، ودائن {{ $managedOwner ? 'جاري المالك أو إيرادات خصم التأمينات' : 'إيرادات خصم التأمينات' }} بالمخصوم.
                            </p>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="settle_deducted">المخصوم من التأمين ({{ $contract->currency_code }})</label>
                                        <input type="number" step="0.01" min="0" max="{{ $depositHeld }}" id="settle_deducted" name="deducted_amount" class="form-control" value="0">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>المردود للمستأجر ({{ $contract->currency_code }})</label>
                                        <input type="text" id="settle_refund" class="form-control stk-num" value="{{ number_format($depositHeld, 2) }}" readonly tabindex="-1">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="settle_to">المخصوم لصالح</label>
                                <select id="settle_to" name="deduct_to" class="form-control">
                                    @if ($managedOwner) <option value="owner">المالك {{ $managedOwner->name }} (حسابه الجاري)</option> @endif
                                    <option value="office">المنشأة (إيرادات خصم التأمينات)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="settle_reason">سبب الخصم</label>
                                <input type="text" id="settle_reason" name="reason" class="form-control" maxlength="1000" placeholder="أضرار، متأخرات خدمات…">
                            </div>
                            <div class="form-group">
                                <label for="settle_treasury">الخزينة / الحساب البنكي الذي يُردّ منه</label>
                                <select id="settle_treasury" name="treasury_account_id" class="form-control">
                                    <option value="">اختر…</option>
                                    @foreach ($withdrawable as $treasury) <option value="{{ $treasury->id }}" @selected((int) $payment->treasury_account_id === $treasury->id)>{{ $treasury->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="settle_date">تاريخ التسوية</label>
                                <input type="date" id="settle_date" name="settled_at" class="form-control" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> تسوية وقيد</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
    @if ($showSettle)
        <script>
            $(function () {
                const held = @json((float) $depositHeld);
                $('#settle_deducted').on('input', function () {
                    const deducted = Math.min(held, Math.max(0, parseFloat(this.value) || 0));
                    $('#settle_refund').val((held - deducted).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                });
            });
        </script>
    @endif
@endsection
