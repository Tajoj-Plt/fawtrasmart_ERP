{{--
    نموذج حركة محفظة العميل — تشترك فيه شاشتا الإضافة والتعديل.

    الحقول تتبدّل بنوع الحركة: الخزينة للنقدية، والفاتورة للسداد والردّ.
    والقيد الذي سيُكتب معروضٌ تحت المُنتقي — فمن يبدّل النوع يرى أثره
    المحاسبي قبل الحفظ لا بعده.

    المُعرّفات `wal_*` و`data-wal-*` يقرؤها السكربت أدناه — تُبقى كما هي
    مع أيّ تعديلٍ في الشكل.

    المتغيّرات: $client $balance $types $allowedTypes $treasuries $invoices
                $treasuryTypes $invoiceTypes $type $transaction (في التعديل)
--}}

@php
    use App\Support\CurrencySettings;

    $tx = $transaction ?? null;

    /** القيد المرافق لكل نوع — يُعرَض تحت المُنتقي */
    $entries = [
        'deposit' => ['من ح/ الخزينة', 'إلى ح/ محفظة العميل', 'النقد يدخل الخزينة ويُقيَّد ديناً علينا للعميل. لا إيراد ولا مبيعات.'],
        'withdraw' => ['من ح/ محفظة العميل', 'إلى ح/ الخزينة', 'المحفظة تنقص والنقد يخرج من الخزينة.'],
        'invoice_paid' => ['من ح/ محفظة العميل', 'إلى ح/ ذمم العميل', 'المحفظة تنقص وذمّة العميل تنقص معها. لا تمرّ الخزينة بالقيد — النقد دخل يوم الإيداع.'],
        'refund' => ['من ح/ ذمم العميل', 'إلى ح/ محفظة العميل', 'عكسُ السداد: يُردّ ما خُصم من المحفظة وتعود الفاتورة مستحقّة.'],
    ];

    $oldType = old('type', $type);
@endphp

@csrf

<div class="row">
    {{-- ══════ الحقول ══════ --}}
    <div class="col-lg-8 col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-edit-3"></i> بيانات الحركة</h4>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="wal_type" class="cl-req">نوع الحركة</label>
                        <select name="type" id="wal_type" class="form-control @error('type') is-invalid @enderror" required>
                            @foreach ($types as $key => $label)
                                @continue(! in_array($key, $allowedTypes, true))
                                <option value="{{ $key }}" @selected($oldType === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 form-group">
                        <label for="wal_amount" class="cl-req">المبلغ</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.01" name="amount" id="wal_amount"
                                class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old('amount', $tx?->amount) }}" required>
                            <span class="input-group-text">{{ CurrencySettings::symbol() }}</span>
                        </div>
                        <small class="cl-help" id="wal_amount_hint"></small>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- الخزينة — للإيداع والسحب وحدهما --}}
                <div class="form-group" data-wal-field="treasury">
                    <label for="wal_treasury" class="cl-req">الخزينة</label>
                    <select name="treasury_account_id" id="wal_treasury"
                        class="form-control @error('treasury_account_id') is-invalid @enderror">
                        <option value="">اختر الخزينة</option>
                        @foreach ($treasuries as $treasury)
                            <option value="{{ $treasury->id }}"
                                @selected((int) old('treasury_account_id', $tx?->treasury_account_id) === (int) $treasury->id)>
                                {{ $treasury->name }} ({{ CurrencySettings::format($treasury->balance) }})
                            </option>
                        @endforeach
                    </select>
                    @error('treasury_account_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- الفاتورة — للسداد والردّ وحدهما. تعديلٌ فيصلٌ بفاتورةٍ
                     واحدة (الحركة المخزَّنة سطرٌ واحد)، وإضافةٌ تقبل عدّة
                     فواتير معاً — «سداد كل الفواتير» يوزّع المبلغ عليها. --}}
                <div class="form-group" data-wal-field="invoice">
                    <label for="wal_invoice" class="cl-req">الفاتورة</label>
                    @php
                        $oldInvoiceIds = array_map('intval', (array) old('invoice_ids', []));
                    @endphp
                    <select name="{{ $tx ? 'invoice_id' : 'invoice_ids[]' }}" id="wal_invoice"
                        class="form-control select2" {{ $tx ? '' : 'multiple' }}>
                        @if ($tx)
                            <option value="">اختر الفاتورة</option>
                        @endif
                        @foreach ($invoices as $invoice)
                            @php
                                $due = (float) ($invoice->due_value ?? $invoice->grand_total);
                                $fromWallet = (float) ($invoice->wallet_paid_amount ?? 0);
                                $isSelected = $tx
                                    ? (int) old('invoice_id', $tx?->invoice_id) === (int) $invoice->id
                                    : in_array((int) $invoice->id, $oldInvoiceIds, true);
                            @endphp
                            <option value="{{ $invoice->id }}" data-due="{{ $due }}" data-wallet="{{ $fromWallet }}"
                                @selected($isSelected)>
                                {{ $invoice->code }} — الإجمالي {{ CurrencySettings::number($invoice->grand_total) }}
                                · المستحقّ {{ CurrencySettings::number($due) }}
                                @if ($fromWallet > 0)
                                    · من المحفظة {{ CurrencySettings::number($fromWallet) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @unless ($tx)
                        <small class="cl-help">يمكن اختيار أكثر من فاتورة — يُوزَّع المبلغ عليها من الأقدم.</small>
                    @endunless
                    @if ($invoices->isEmpty())
                        <small class="cl-help" data-hint-keep>لا فواتير لهذا العميل.</small>
                    @endif
                    @unless ($tx)
                        <button type="button" id="wal_pay_all" class="btn btn-sm btn-outline-primary mt-2"
                            {{ $invoices->isEmpty() ? 'disabled' : '' }}>
                            <i class="feather icon-check-square"></i>
                            <span id="wal_pay_all_label">تحديد كل الفواتير وتعبئة المبلغ</span>
                        </button>
                    @endunless
                    @error('invoice_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('invoice_ids')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-0">
                    <label for="wal_note">ملاحظة</label>
                    <input type="text" name="note" id="wal_note" class="form-control" maxlength="500"
                        value="{{ old('note', $tx?->note) }}">
                    <small class="cl-help">تُكتب في وصف القيد وفي كشف الحركة.</small>
                </div>
            </div>

            <div class="cl-form-foot">
                <button type="submit" class="btn btn-primary" {{ $walletsParentMapped ? '' : 'disabled' }}>
                    <i class="feather icon-check"></i> حفظ
                </button>
                <a href="{{ route('clients.wallet.show', $client->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
            </div>
        </div>
    </div>

    {{-- ══════ الرصيد والقيد ══════ --}}
    <div class="col-lg-4 col-12">
        <div class="cl-stat" style="margin-bottom: var(--cl-4)">
            <span class="cl-stat__icon {{ $balance > 0 ? 'cl-stat__icon--success' : '' }}">
                <i class="feather icon-credit-card"></i>
            </span>
            <div class="cl-stat__body">
                <p class="cl-stat__value {{ $balance > 0 ? 'text-success' : '' }}">
                    {{ CurrencySettings::format($balance) }}
                </p>
                <p class="cl-stat__label">رصيد المحفظة الحالي — لا يُسحَب على المكشوف</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-book-open"></i> القيد الذي سيُكتب</h4>
            </div>
            <div class="card-body">
                @foreach ($entries as $key => $entry)
                    <div data-wal-entry="{{ $key }}" style="display:none">
                        <table class="table table-sm mb-2">
                            <thead>
                                <tr>
                                    <th>الحساب</th>
                                    <th style="text-align:end">المبلغ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $entry[0] }}</td>
                                    <td style="text-align:end" data-num><span data-wal-preview>—</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-inline-start: var(--cl-5)">{{ $entry[1] }}</td>
                                    <td style="text-align:end" data-num><span data-wal-preview>—</span></td>
                                </tr>
                            </tbody>
                        </table>
                        <small class="cl-help">{{ $entry[2] }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var TREASURY = @json($treasuryTypes);
            var INVOICE = @json($invoiceTypes);

            var typeEl = document.getElementById('wal_type');
            var amountEl = document.getElementById('wal_amount');
            var hintEl = document.getElementById('wal_amount_hint');
            var invoiceEl = document.getElementById('wal_invoice');
            var payAllBtn = document.getElementById('wal_pay_all');
            var payAllLabel = document.getElementById('wal_pay_all_label');
            var walletBalance = {{ (float) $balance }};

            if (!typeEl) { return; }

            /* مجموع حقلٍ (due أو wallet) عبر الفواتير المختارة — واحدةً في
               التعديل، وربما عدّة في الإضافة. */
            function sumSelected(key) {
                if (!invoiceEl) { return 0; }

                var selected = invoiceEl.multiple
                    ? Array.prototype.slice.call(invoiceEl.selectedOptions)
                    : (invoiceEl.selectedOptions[0] ? [invoiceEl.selectedOptions[0]] : []);

                return selected.reduce(function (sum, option) {
                    return sum + parseFloat(option.dataset[key] || '0');
                }, 0);
            }

            /* الحقل المخفيّ يفقد `required` كذلك: متصفّحٌ يرفض إرسال نموذجٍ
               فيه حقلٌ مطلوبٌ لا يُرى، ولا يقول للمستخدم أين هو. */
            function toggle() {
                var type = typeEl.value;
                var needsTreasury = TREASURY.indexOf(type) !== -1;
                var needsInvoice = INVOICE.indexOf(type) !== -1;

                document.querySelectorAll('[data-wal-field]').forEach(function (box) {
                    var want = box.dataset.walField === 'treasury' ? needsTreasury : needsInvoice;
                    box.style.display = want ? '' : 'none';

                    var input = box.querySelector('select');
                    if (input) { input.required = want; }
                });

                document.querySelectorAll('[data-wal-entry]').forEach(function (box) {
                    box.style.display = box.dataset.walEntry === type ? '' : 'none';
                });

                hint();
            }

            /* تلميحُ السقف: المحفظة لما ينقصها، والفاتورة لما يُسدَّد منها */
            function hint() {
                if (!hintEl) { return; }

                var type = typeEl.value;

                if (type === 'withdraw') {
                    hintEl.textContent = 'لا يتجاوز رصيد المحفظة: {{ number_format($balance, 2) }}';
                    return;
                }

                if (type === 'invoice_paid') {
                    var due = sumSelected('due');
                    hintEl.textContent = 'لا يتجاوز رصيد المحفظة ({{ number_format($balance, 2) }}) ولا مجموع المستحقّ على الفواتير المختارة'
                        + (due > 0 ? ' (' + due.toFixed(2) + ')' : '');
                    return;
                }

                if (type === 'refund') {
                    var fromWallet = sumSelected('wallet');
                    hintEl.textContent = 'لا يتجاوز مجموع ما سُدِّد من المحفظة على الفواتير المختارة'
                        + (fromWallet > 0 ? ' (' + fromWallet.toFixed(2) + ')' : '');
                    return;
                }

                hintEl.textContent = '';
            }

            /* معاينة المبلغ في طرفَي القيد — الرقم نفسه مديناً ودائناً */
            function preview() {
                var value = parseFloat(amountEl.value || '0');
                var text = value > 0 ? value.toFixed(2) : '—';

                document.querySelectorAll('[data-wal-preview]').forEach(function (cell) {
                    cell.textContent = text;
                });
            }

            /* تسمية زرّ «تحديد الكل» تتبع النوع: سداد أو ردّ */
            function updatePayAllLabel() {
                if (!payAllLabel) { return; }

                payAllLabel.textContent = typeEl.value === 'refund'
                    ? 'تحديد كل الفواتير وتعبئة مبلغ الردّ'
                    : 'تحديد كل الفواتير وتعبئة مبلغ السداد';
            }

            /* تحديد كل خيارات القائمة، ثم تعبئة المبلغ بمجموع حصصها —
               للسداد يُحدّ المجموع برصيد المحفظة، وللردّ لا سقف غيره. */
            if (payAllBtn && invoiceEl) {
                payAllBtn.addEventListener('click', function () {
                    var options = Array.prototype.slice.call(invoiceEl.options);
                    var ids = options.map(function (option) { return option.value; });

                    if (window.jQuery) {
                        window.jQuery(invoiceEl).val(ids).trigger('change');
                    } else {
                        options.forEach(function (option) { option.selected = true; });
                        invoiceEl.dispatchEvent(new Event('change'));
                    }

                    var isRefund = typeEl.value === 'refund';
                    var total = options.reduce(function (sum, option) {
                        return sum + parseFloat((isRefund ? option.dataset.wallet : option.dataset.due) || '0');
                    }, 0);

                    var cap = isRefund ? total : Math.min(total, walletBalance);
                    amountEl.value = cap > 0 ? cap.toFixed(2) : '';

                    preview();
                    hint();
                });
            }

            typeEl.addEventListener('change', function () {
                toggle();
                updatePayAllLabel();
            });
            amountEl.addEventListener('input', preview);
            if (invoiceEl) { invoiceEl.addEventListener('change', hint); }

            toggle();
            preview();
            updatePayAllLabel();
        })();
    </script>
@endpush
