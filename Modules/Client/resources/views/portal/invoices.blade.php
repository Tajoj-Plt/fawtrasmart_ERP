@extends('client::portal.layout')

{{--
    فواتير العميل.

    التصفية بالحالة تقع في المتصفّح لا بطلبٍ للخادم: العدد هنا فواتيرُ عميلٍ
    واحد لا سجلُّ منشأة، فطلبٌ كاملٌ لتخفي صفوفاً مُحمَّلةً أصلاً انتظارٌ بلا
    مقابل. ولو كبر العدد يوماً فالمكان الصحيح صفحاتٌ من الخادم لا مكتبةُ
    جداولَ في المتصفّح.
--}}

@section('title', 'فواتيري')
@section('subtitle', 'كل فواتيرك عندنا وحالة سدادها')

@section('actions')
    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" onclick="window.print()">
        <i class="fa fa-print"></i> طباعة
    </button>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $money = fn ($v) => CurrencySettings::format(abs((float) $v));

        $total = $invoices->sum(fn ($i) => (float) ($i->grand_total ?? $i->total ?? 0));
        $due = $invoices->sum(fn ($i) => (float) ($i->due_value ?? 0));
        $unpaid = $invoices->filter(fn ($i) => (float) ($i->due_value ?? 0) > 0)->count();

        $canPay = $canPay ?? false;
        $paymentNotices = $paymentNotices ?? collect();
        $paymentMethods = $paymentMethods ?? collect();

        // بلا طرق دفعٍ مفعَّلة لا نموذجَ سداد: اختيارٌ فارغ لا يُرسِل شيئاً
        $canPay = $canPay && $paymentMethods->isNotEmpty();

        /* فاتورةٌ لها إشعارٌ معلّق لا تُعرض زرَّ سدادٍ ثانٍ — نفسُ القاعدة
           التي يفرضها المتحكّم عند الحفظ، معروضةً قبل أن تُردّ. */
        $pendingByInvoice = $paymentNotices
            ->where('status', \App\Models\ClientPaymentNotice::STATUS_PENDING)
            ->keyBy('invoice_id');

        $dueInvoices = $invoices->filter(fn ($i) => (float) ($i->due_value ?? 0) > 0);
    @endphp

    <div class="cp-stats">
        <div class="cp-stat">
            <span class="cp-stat__icon cp-stat__icon--info"><i class="fa fa-file-invoice"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ number_format($invoices->count()) }}</p>
                <p class="cp-stat__label">إجمالي الفواتير</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon"><i class="fa fa-receipt"></i></span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($total) }}</p>
                <p class="cp-stat__label">قيمتها الإجمالية</p>
            </div>
        </div>

        <div class="cp-stat">
            <span class="cp-stat__icon {{ $due > 0 ? 'cp-stat__icon--danger' : 'cp-stat__icon--success' }}">
                <i class="fa fa-{{ $due > 0 ? 'exclamation-circle' : 'check-circle' }}"></i>
            </span>
            <div class="cp-stat__body">
                <p class="cp-stat__value">{{ $money($due) }}</p>
                <p class="cp-stat__label">{{ $unpaid > 0 ? 'المستحقّ على '.$unpaid.' فاتورة' : 'لا مستحقّات' }}</p>
            </div>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-list"></i>الفواتير</h2>

            @if ($invoices->isNotEmpty())
                <div class="cp-noprint" style="display:flex;gap:.35rem">
                    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm is-active" data-cp-filter="all">الكل</button>
                    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" data-cp-filter="due">غير مسدَّدة</button>
                    <button type="button" class="cp-btn cp-btn--ghost cp-btn--sm" data-cp-filter="paid">مسدَّدة</button>
                </div>
            @endif
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($invoices->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-file-invoice"></i>
                    <p>لا توجد فواتير على حسابك بعد.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table" id="cpInvoices">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>الاستحقاق</th>
                                <th class="cp-num">الإجمالي</th>
                                <th class="cp-num">المتبقّي</th>
                                <th>الحالة</th>
                                @if ($canPay)
                                    <th class="cp-noprint">السداد</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                @php
                                    $invoiceDue = (float) ($invoice->due_value ?? 0);
                                    $invoiceTotal = (float) ($invoice->grand_total ?? $invoice->total ?? 0);
                                    $state = $invoiceDue <= 0 ? 'paid' : 'due';
                                @endphp

                                <tr data-cp-state="{{ $state }}">
                                    <td class="cp-num cp-strong">{{ $invoice->invoice_number ?? $invoice->code ?? $invoice->id }}</td>
                                    <td class="cp-num">{{ optional($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') ?? '—' }}</td>
                                    <td class="cp-num">{{ optional($invoice->due_date)->format('Y-m-d') ?? '—' }}</td>
                                    <td class="cp-num">{{ $money($invoiceTotal) }}</td>
                                    <td class="cp-num">{{ $invoiceDue > 0 ? $money($invoiceDue) : '—' }}</td>
                                    <td>
                                        @if ($invoiceDue <= 0)
                                            <span class="cp-badge cp-badge--success"><i class="fa fa-check"></i> مسدَّدة</span>
                                        @elseif ($invoiceDue < $invoiceTotal)
                                            <span class="cp-badge cp-badge--warning">مسدَّدة جزئياً</span>
                                        @else
                                            <span class="cp-badge cp-badge--danger">غير مسدَّدة</span>
                                        @endif
                                    </td>

                                    @if ($canPay)
                                        <td class="cp-noprint">
                                            @if ($invoiceDue > 0)
                                                @if (isset($pendingByInvoice[$invoice->id]))
                                                    <span class="cp-badge cp-badge--warning">إشعارٌ قيد المراجعة</span>
                                                @else
                                                    {{-- لا مودال: البوابة بلا bootstrap ولا jQuery. الزرّ يملأ
                                                         النموذجَ أسفل الصفحة ويقفز إليه. --}}
                                                    <button type="button" class="cp-btn cp-btn--sm"
                                                            data-cp-pay="{{ $invoice->id }}"
                                                            data-cp-due="{{ number_format($invoiceDue, 2, '.', '') }}">
                                                        <i class="fa fa-money-check"></i> سدّد
                                                    </button>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="cp-empty" id="cpNoMatch" hidden>
                    <i class="fa fa-filter"></i>
                    <p>لا فواتير في هذه الحالة.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════ إشعار سداد ══════════

         لا بوابةَ دفعٍ إلكترونيةٍ مربوطةً بعد: العميلُ يُعلن ما سدّده ويرفق
         إيصاله، ولا يتغيّر رصيدُه حتى يعتمد الموظّفُ الإشعارَ فيُسجَّل سندُ
         قبضٍ بمساره المعتاد. ويومَ تُربط البوابةُ يصير هذا النموذجُ خطوةً
         اختياريةً بجانب الدفع بالبطاقة. --}}
    @if ($canPay && $dueInvoices->isNotEmpty())
        <div class="cp-card cp-noprint" id="cpPayCard">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-money-check"></i>سداد فاتورة</h2>
            </div>

            <div class="cp-card__body">
                <p class="cp-muted" style="margin-top:0">
                    حوّل المبلغ ثم أبلغنا هنا بتفاصيل السداد وأرفق الإيصال. يراجعه موظّفونا
                    ويُسجَّل سندُ قبضٍ على حسابك بعد التحقّق.
                </p>

                <form method="POST" action="{{ route('clients.portal.payments.store') }}"
                      enctype="multipart/form-data" >
                    @csrf

                    <div class="cp-grid">
                        <div class="cp-field">
                            <label>الفاتورة</label>
                            <select name="invoice_id" id="cpPayInvoice" required>
                                <option value="">— اختر الفاتورة —</option>
                                @foreach ($dueInvoices as $invoice)
                                    @continue(isset($pendingByInvoice[$invoice->id]))
                                    <option value="{{ $invoice->id }}"
                                            data-due="{{ number_format((float) $invoice->due_value, 2, '.', '') }}"
                                            @selected(old('invoice_id') == $invoice->id)>
                                        {{ $invoice->invoice_number ?? $invoice->code ?? $invoice->id }}
                                        — المتبقّي {{ number_format((float) $invoice->due_value, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="cp-field">
                            <label>المبلغ المسدَّد</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="cpPayAmount"
                                   value="{{ old('amount') }}" required>
                            <span class="cp-field__hint" id="cpPayHint">لا يتجاوز متبقّي الفاتورة.</span>
                        </div>

                        <div class="cp-field">
                            <label for="cpPayMethod">طريقة السداد</label>
                            <select name="payment_method_id" id="cpPayMethod" required>
                                <option value="">— اختر الطريقة —</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}"
                                            data-hint="{{ trim(collect([
                                                $method->instructions,
                                                $method->bank_name ? 'البنك: '.$method->bank_name : null,
                                                $method->bank_account_name ? 'باسم: '.$method->bank_account_name : null,
                                                $method->bank_account_number ? 'الحساب: '.$method->bank_account_number : null,
                                                $method->bank_iban ? 'الآيبان: '.$method->bank_iban : null,
                                            ])->filter()->implode(' · ')) }}"
                                            @selected(old('payment_method_id') == $method->id)>
                                        {{ $method->name }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- تعليماتُ الطريقة وحسابُها البنكي من إعداداتها — يقرؤها
                                 العميلُ قبل التحويل بدل أن يسأل عنها --}}
                            <span class="cp-field__hint" id="cpMethodHint"></span>
                        </div>

                        <div class="cp-field">
                            <label>تاريخ السداد</label>
                            <input type="date" name="paid_at" max="{{ date('Y-m-d') }}"
                                   value="{{ old('paid_at', date('Y-m-d')) }}">
                        </div>

                        <div class="cp-field">
                            <label>رقم العملية / المرجع</label>
                            <input type="text" name="reference" maxlength="120"
                                   value="{{ old('reference') }}" placeholder="رقم الحوالة أو العملية">
                        </div>

                        <div class="cp-field">
                            <label>صورة الإيصال</label>
                            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf">
                            <span class="cp-field__hint">JPG أو PNG أو PDF، حتى ٤ ميغابايت.</span>
                        </div>

                        <div class="cp-field" style="grid-column:1/-1">
                            <label>ملاحظة (اختياري)</label>
                            <textarea name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    <div style="margin-top:.75rem">
                        <button type="submit" class="cp-btn"><i class="fa fa-paper-plane"></i> إرسال إشعار السداد</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($paymentNotices->isNotEmpty())
        <div class="cp-card">
            <div class="cp-card__head">
                <h2 class="cp-card__title"><i class="fa fa-inbox"></i>إشعارات السداد المُرسَلة</h2>
            </div>

            <div class="cp-card__body cp-card__body--flush">
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفاتورة</th>
                                <th class="cp-num">المبلغ</th>
                                <th>الطريقة</th>
                                <th>الحالة</th>
                                <th class="cp-noprint"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentNotices as $notice)
                                <tr>
                                    <td class="cp-num">{{ $notice->created_at?->format('Y-m-d') }}</td>
                                    <td class="cp-num">{{ $notice->invoice->invoice_number ?? $notice->invoice->code ?? '—' }}</td>
                                    <td class="cp-num">{{ $money($notice->amount) }}</td>
                                    <td>{{ $notice->methodLabel() }}</td>
                                    <td>
                                        <span class="cp-badge cp-badge--{{ ['success'=>'success','danger'=>'danger','secondary'=>'muted'][$notice->statusColor()] ?? 'warning' }}">{{ $notice->statusLabel() }}</span>
                                        @if ($notice->review_note)
                                            <br><small class="cp-muted">{{ $notice->review_note }}</small>
                                        @endif
                                    </td>
                                    <td class="cp-noprint">
                                        @if ($notice->isPending())
                                            <form method="POST"
                                                  action="{{ route('clients.portal.payments.cancel', $notice) }}"
                                                  onsubmit="return confirm('سحب إشعار السداد؟');">
                                                @csrf
                                                <button type="submit" class="cp-btn cp-btn--ghost cp-btn--sm">سحب</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        /* زرّ «سدّد» في الصفّ يملأ نموذج الإشعار أسفل الصفحة ويقفز إليه —
           البوابة بلا مكتبة نوافذ، ونافذةٌ مكتوبةٌ بيدٍ لصفٍّ واحد أكثرُ
           عطلاً من حقلٍ ينتقل إليه المستخدم. */
        (function () {
            var select = document.getElementById('cpPayInvoice');
            var amount = document.getElementById('cpPayAmount');
            var hint = document.getElementById('cpPayHint');
            var card = document.getElementById('cpPayCard');

            if (!select || !amount) {
                return;
            }

            function applyDue(due) {
                if (due === null || due === undefined || due === '') {
                    amount.removeAttribute('max');
                    return;
                }

                amount.max = due;
                amount.value = due;

                if (hint) {
                    hint.textContent = 'المتبقّي على هذه الفاتورة: ' + due;
                }
            }

            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                applyDue(option ? option.dataset.due : null);
            });

            var method = document.getElementById('cpPayMethod');
            var methodHint = document.getElementById('cpMethodHint');

            if (method && methodHint) {
                var showHint = function () {
                    var option = method.options[method.selectedIndex];
                    methodHint.textContent = (option && option.dataset.hint) || '';
                };

                method.addEventListener('change', showHint);
                showHint();
            }

            document.querySelectorAll('[data-cp-pay]').forEach(function (button) {
                button.addEventListener('click', function () {
                    select.value = button.dataset.cpPay;
                    applyDue(button.dataset.cpDue);

                    if (card) {
                        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }

                    amount.focus();
                });
            });
        })();

        (function () {
            var buttons = document.querySelectorAll('[data-cp-filter]');
            var table = document.getElementById('cpInvoices');
            var empty = document.getElementById('cpNoMatch');

            if (!table) {
                return;
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var wanted = button.dataset.cpFilter;
                    var shown = 0;

                    buttons.forEach(function (other) {
                        other.classList.toggle('is-active', other === button);
                        other.style.borderColor = other === button ? 'var(--cp-primary)' : '';
                        other.style.color = other === button ? 'var(--cp-primary)' : '';
                    });

                    table.querySelectorAll('tbody tr').forEach(function (row) {
                        var match = wanted === 'all' || row.dataset.cpState === wanted;
                        row.hidden = !match;
                        if (match) { shown++; }
                    });

                    // الجدول يبقى ظاهراً برأسه وحده لو أُخفيت كل صفوفه —
                    // رأسٌ بلا صفوف يبدو عطلاً لا نتيجةَ تصفية
                    table.hidden = shown === 0;
                    if (empty) { empty.hidden = shown !== 0; }
                });
            });
        })();
    </script>
@endsection
