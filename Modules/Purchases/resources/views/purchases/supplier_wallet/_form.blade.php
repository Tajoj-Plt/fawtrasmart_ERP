{{--
    نموذج حركة محفظة المورد — تشترك فيه شاشتا الإضافة والتعديل.

    على قياس `client::wallet._form` بطلب المستخدم: بطاقةٌ للحقول بذيلٍ
    للأزرار (`sl-form-foot`)، وبطاقةُ رصيدٍ وبطاقةُ قيدٍ بجانبها. وكانت
    بأصناف `po-` فتخرج الشاشتان المتناظرتان بمقاسين مختلفين.

    والبادئة `sl-` نسخةُ الوحدة من نظام التصميم نفسِه، فلا يشير قالبُ
    المشتريات إلى وحدة العملاء.

    نظير `client::wallet._form`، والفرق اتجاهُ المحفظة: هذه **أصل** (مالٌ
    لنا لدى المورد) فالدفعة المقدَّمة تزيدها والسدادُ ينقصها.

    المتغيّرات: $supplier $balance $types $allowedTypes $treasuries $invoices
                $treasuryTypes $invoiceTypes $type $transaction (في التعديل)
--}}

@php
    use App\Support\CurrencySettings;

    $tx = $transaction ?? null;

    /** القيد المرافق لكل نوع — يُعرَض بجانب النموذج */
    $entries = [
        'deposit' => ['من ح/ محفظة المورد', 'إلى ح/ الخزينة', 'النقد يخرج من الخزينة ويُقيَّد أصلاً لنا لدى المورد. لا مشتريات ولا مصروف.'],
        'withdraw' => ['من ح/ الخزينة', 'إلى ح/ محفظة المورد', 'المورد يردّ ما لنا لديه، فيدخل النقد وتنقص المحفظة.'],
        'invoice_paid' => ['من ح/ المورد (ذمم دائنة)', 'إلى ح/ محفظة المورد', 'ما علينا للمورد ينقص، وما لنا لديه ينقص معه. لا يخرج ريال من الخزينة.'],
        'refund' => ['من ح/ محفظة المورد', 'إلى ح/ المورد (ذمم دائنة)', 'عكسُ السداد: يعود الرصيد إلى المحفظة وتعود الفاتورة مستحقّة.'],
    ];

    $oldType = old('type', $type);
@endphp

@csrf

{{-- القائمة المتعدّدة على مقاس حقول `sl-scope` — النسق يضبط المفردة وحدها --}}
<style>
    .sl-scope .select2-container--default .select2-selection--multiple {
        min-height: 40px; padding: 2px var(--sl-3, .75rem);
        background: var(--app-surface, #fff); border: 1px solid var(--sl-line, #e2e2e8);
        border-radius: var(--sl-r, 8px); box-shadow: none;
    }
    .sl-scope .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--app-primary, #7367f0);
        box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--app-primary-soft, rgba(115, 103, 240, .12)); color: var(--app-text, #4b4b4b);
        border: 0; border-radius: 6px; font-size: .8rem; padding: 2px 8px; margin: 4px 0 0 4px;
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--app-muted, #6e6b7b); margin-left: 4px;
    }
    .sl-scope .select2-container--default .select2-search--inline .select2-search__field { margin-top: 6px; }
</style>

<div class="row">
    {{-- ══════ الحقول ══════ --}}
    <div class="col-lg-8 col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="fas fa-pen"></i> بيانات الحركة</h4>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label for="wal_type" class="sl-req">نوع الحركة</label>
                    <select name="type" id="wal_type" class="form-control" required>
                        @foreach ($types as $key => $label)
                            @continue(! in_array($key, $allowedTypes, true))
                            <option value="{{ $key }}" @selected($oldType === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="wal_amount" class="sl-req">المبلغ</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="wal_amount"
                        class="form-control" value="{{ old('amount', $tx?->amount) }}" required>
                    <small class="sl-help" id="wal_amount_hint"></small>
                    @error('amount')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                </div>

                {{-- الخزينة — للدفعة والاسترداد وحدهما --}}
                <div class="form-group" data-wal-field="treasury">
                    <label for="wal_treasury" class="sl-req">الخزينة</label>
                    <select name="treasury_account_id" id="wal_treasury" class="form-control">
                        <option value="">اختر الخزينة</option>
                        @foreach ($treasuries as $treasury)
                            <option value="{{ $treasury->id }}"
                                @selected((int) old('treasury_account_id', $tx?->treasury_account_id) === (int) $treasury->id)>
                                {{ $treasury->name }} ({{ CurrencySettings::format($treasury->balance) }})
                            </option>
                        @endforeach
                    </select>
                    @error('treasury_account_id')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                </div>

                {{-- الفاتورة — للسداد والردّ وحدهما. تعديلٌ فيصلٌ بفاتورةٍ
                     واحدة (الحركة المخزَّنة سطرٌ واحد)، وإضافةٌ تقبل عدّة
                     فواتير معاً — يُوزَّع المبلغ عليها من الأقدم، حركةٌ وقيدٌ
                     لكلّ فاتورة. نظير `client::wallet._form`. --}}
                <div class="form-group" data-wal-field="invoice">
                    <label for="wal_invoice" class="sl-req">{{ $tx ? 'فاتورة الشراء' : 'فواتير الشراء' }}</label>
                    @php
                        $oldInvoiceIds = array_map('intval', (array) old('purchase_invoice_ids', []));
                    @endphp
                    <select name="{{ $tx ? 'purchase_invoice_id' : 'purchase_invoice_ids[]' }}" id="wal_invoice"
                        class="form-control select2" {{ $tx ? '' : 'multiple' }}
                        data-placeholder="اختر فاتورة أو أكثر">
                        @if ($tx)
                            <option value="">اختر الفاتورة</option>
                        @endif
                        @foreach ($invoices as $invoice)
                            @php
                                $due = (float) ($invoice->due_value ?? $invoice->grand_total);
                                $fromWallet = (float) ($invoice->wallet_paid_amount ?? 0);
                                $isSelected = $tx
                                    ? (int) old('purchase_invoice_id', $tx?->purchase_invoice_id) === (int) $invoice->id
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
                        <small class="sl-help">يمكن اختيار أكثر من فاتورة — يُوزَّع المبلغ عليها من الأقدم، وتُكتب حركةٌ وقيدٌ لكلّ فاتورة.</small>
                    @endunless
                    @if ($invoices->isEmpty())
                        <small class="sl-help" data-hint-keep>لا فواتير شراء لهذا المورد.</small>
                    @endif
                    @unless ($tx)
                        <button type="button" id="wal_pay_all" class="btn btn-sm btn-outline-primary mt-2"
                            {{ $invoices->isEmpty() ? 'disabled' : '' }}>
                            <i class="fas fa-check-double"></i>
                            <span id="wal_pay_all_label">تحديد كل الفواتير وتعبئة مبلغ السداد</span>
                        </button>
                    @endunless
                    @error('purchase_invoice_id')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                    @error('purchase_invoice_ids')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                    @error('purchase_invoice_ids.*')
                        <small class="sl-help text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="wal_note">ملاحظة</label>
                    <input type="text" name="note" id="wal_note" class="form-control" maxlength="500"
                        value="{{ old('note', $tx?->note) }}">
                    <small class="sl-help">تُكتب في وصف القيد وفي كشف الحركة.</small>
                </div>

            </div>

            <div class="sl-form-foot">
                <button type="submit" class="btn btn-primary" {{ $walletsParentMapped ? '' : 'disabled' }}>
                    <i class="fas fa-check"></i> حفظ
                </button>
                <a href="{{ route('purchases.supplier-wallet.show', $supplier->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </div>
    </div>

    {{-- ══════ الرصيد والقيد ══════ --}}
    <div class="col-lg-4 col-12">
        <div class="sl-stat" style="margin-bottom: var(--sl-4)">
            <span class="sl-stat__icon {{ $balance > 0 ? 'sl-stat__icon--success' : '' }}">
                <i class="fas fa-wallet"></i>
            </span>
            <div class="sl-stat__body">
                <p class="sl-stat__value {{ $balance > 0 ? 'text-success' : '' }}">
                    {{ CurrencySettings::format($balance) }}
                </p>
                <p class="sl-stat__label">رصيد المحفظة الحالي — مالٌ لنا لدى المورد، لا يُسحَب على المكشوف</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="fas fa-book-open"></i> القيد الذي سيُكتب</h4>
            </div>
            <div class="card-body">
                @foreach ($entries as $key => $entry)
                    <div data-wal-entry="{{ $key }}" style="display:none">
                        <table class="table table-sm mb-1">
                            <tr>
                                <td>{{ $entry[0] }}</td>
                                <td class="text-left"><span data-wal-preview>—</span></td>
                            </tr>
                            <tr>
                                <td class="pr-4">{{ $entry[1] }}</td>
                                <td class="text-left"><span data-wal-preview>—</span></td>
                            </tr>
                        </table>
                        <small class="sl-help">{{ $entry[2] }}</small>
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
                    : (invoiceEl.selectedOptions[0] && invoiceEl.selectedOptions[0].value
                        ? [invoiceEl.selectedOptions[0]] : []);

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
                    var isRefund = typeEl.value === 'refund';
                    var key = isRefund ? 'wallet' : 'due';

                    // فاتورةٌ لا حصّة لها في هذا النوع لا تُحدَّد — كي لا يفشل الحفظ بها
                    var options = Array.prototype.slice.call(invoiceEl.options).filter(function (option) {
                        return option.value && parseFloat(option.dataset[key] || '0') > 0;
                    });
                    var ids = options.map(function (option) { return option.value; });

                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(invoiceEl).val(ids).trigger('change');
                    } else {
                        Array.prototype.forEach.call(invoiceEl.options, function (option) {
                            option.selected = ids.indexOf(option.value) !== -1;
                        });
                        invoiceEl.dispatchEvent(new Event('change'));
                    }

                    var total = options.reduce(function (sum, option) {
                        return sum + parseFloat(option.dataset[key] || '0');
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
            if (invoiceEl) {
                invoiceEl.addEventListener('change', hint);
                // select2 يطلق حدثه عبر jQuery لا DOM — فيُسمَع من هناك
                if (window.jQuery) { window.jQuery(invoiceEl).on('change', hint); }
            }

            toggle();
            preview();
            updatePayAllLabel();
        })();
    </script>
@endpush
