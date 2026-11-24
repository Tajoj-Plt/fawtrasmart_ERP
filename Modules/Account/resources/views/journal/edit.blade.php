@extends('account::master')

@section('title', 'تعديل قيد يومية')

@section('content')
    @php
        $taxRates = collect($taxs ?? []);
        $isApproved = $journal->status == 1;
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('journal.show', $journal->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-eye"></i> عرض
            </a>
            <a href="{{ route('journal.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="journal_form" id="submitBtn" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التعديل
            </button>
        @endsection

        @include('account::components.page-head', [
            'title' => 'تعديل قيد ' . ($journal->reference_number ?: $journal->id),
            'icon' => 'edit-2',
            'sub' =>
                'تعديل السطور يُعيد بناء القيد كاملاً — ' .
                ($isApproved ? 'القيد معتمد' : 'القيد غير معتمد'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قيود اليومية' => route('journal.index'),
                'تعديل' => null,
            ],
        ])

        {{--
            **ما كان**

            بطاقتان بلا عنوانٍ ولا أيقونة، وزرّا الحفظ والإلغاء مرسومان
            مرّتين — في ترويسة الصفحة وفي تذييل النموذج معاً. والعملة
            `value="SAR"` مكتوبةً في القالب، ونسب الضريبة ثلاثةَ خياراتٍ
            ثابتة لا صلة لها بشاشة «إعدادات الضرائب».

            وقائمة الحساب كانت تطبع **كل** حسابات الشجرة في كل سطر
            (`$accounts = Account::all()`)، فقيدٌ بعشرة سطور يحمل عشر نسخٍ
            من الشجرة كاملة في صفحةٍ واحدة. صارت تبحث في الخادم كما في شاشة
            الإضافة، ولا تُطبع إلّا الحسابات المختارة فعلاً.
        --}}
        <form id="journal_form" action="{{ route('journal.update', $journal->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-file-text"></i> بيانات القيد
                    </h6>

                    <span class="stk-state {{ $isApproved ? 'stk-state--on' : 'stk-state--neutral' }}">
                        {{ $isApproved ? 'معتمد' : 'غير معتمد' }}
                    </span>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="reference_type" class="stk-req">نوع المرجع</label>
                            <select id="reference_type" name="journal_entry[reference_type]" class="form-control" required>
                                <option value="">اختر نوع المرجع</option>
                                @foreach ($referenceTypes as $code => $type)
                                    <option value="{{ $code }}"
                                        @selected(($journal->reference_type instanceof \App\Enums\ReferenceType ? $journal->reference_type->value : $journal->reference_type) === $code)>
                                        {{ $type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="reference_number">الرقم المرجعي</label>
                            <input type="text" id="reference_number" name="journal_entry[reference_number]"
                                class="form-control stk-num" readonly value="{{ $journal->reference_number }}">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="date" class="stk-req">التاريخ</label>
                            <input type="date" id="date" name="journal_entry[date]" class="form-control" required
                                value="{{ old('journal_entry.date', $journal->date->format('Y-m-d')) }}">
                            <small class="stk-help">التاريخان القديم والجديد يجب أن يقعا في فترةٍ مفتوحة.</small>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="currency">العملة</label>
                            <input type="text" id="currency" name="journal_entry[currency]" class="form-control"
                                value="{{ $journal->currency ?: \App\Support\CurrencySettings::code() }}" readonly>
                        </div>

                        <div class="form-group col-12">
                            <label for="description" class="stk-req">البيان</label>
                            <textarea id="description" name="journal_entry[description]" class="form-control" rows="2" required
                                placeholder="وصف القيد…">{{ old('journal_entry.description', $journal->description) }}</textarea>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="attachments">المرفق</label>
                            <input id="attachments" type="file" name="journal_entry[attachment]" class="form-control"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="stk-help">PDF أو صورة — بحدّ أقصى ٢ ميجابايت.</small>

                            @if ($journal->attachment)
                                <a href="{{ asset('storage/' . $journal->attachment) }}" target="_blank"
                                    class="d-block mt-1">
                                    <i class="feather icon-paperclip"></i> المرفق الحالي
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> سطور القيد
                        <span class="stk-count">{{ $journal->details->where('is_tax_line', false)->count() }}</span>
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-row">
                        <i class="feather icon-plus"></i> سطر جديد
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="entries-table">
                        <thead>
                            <tr>
                                <th style="min-width:220px">الحساب</th>
                                <th class="text-center" style="width:130px">مدين</th>
                                <th class="text-center" style="width:130px">دائن</th>
                                <th style="min-width:160px">البيان</th>
                                {{--
                                    عمود مركز التكلفة يظهر بحسب ضابط «مركز التكلفة» في الإعدادات
                                    العامة للمالية. والعمود ثلاثةُ مواضع لا موضع: ترويسته، وخليّةُ
                                    السطر الأول، وقالبُ السطر الذي يبنيه JS — فإخفاءُ واحدٍ منها
                                    وحده يُخرج جدولاً بأعمدةٍ غير متساوية.
                                --}}
                                @accountingOn('show_cost_center')
                                <th style="min-width:150px">مركز التكلفة</th>
                                @endaccountingOn
                                <th style="min-width:150px">نوع الضريبة</th>
                                <th style="width:120px">النسبة</th>
                                <th class="text-center" style="width:120px">قيمة الضريبة</th>
                                <th style="width:56px"></th>
                            </tr>
                        </thead>

                        <tbody>
                            {{--
                                **سطورُ الضريبة المولَّدة لا تُعرَض للتحرير.**

                                كان سطرُ الضريبة الذي وُلِّد عند الحفظ يظهر هنا سطراً
                                عادياً. فمن عدّل القيدَ وحفظه خرج بسطرَي ضريبة: القديمُ
                                المعروض والجديدُ المولَّد من الاختيار — والقيدُ يتضخّم
                                بمقدارها في كلّ تعديل.

                                والسطرُ المولَّد يُعاد بناؤه من اختيار سطره الأصل، فلا
                                حاجة إلى عرضه ولا يصحّ تحريره.
                            --}}
                            @foreach ($journal->details->where('is_tax_line', false)->values() as $index => $detail)
                                <tr>
                                    <td>
                                        {{-- الخيار المحفوظ وحده؛ الباقي يُبحث في الخادم --}}
                                        <select name="details[{{ $index }}][account_id]"
                                            class="form-control account-search" required>
                                            <option value="">اختر الحساب</option>
                                            @if ($detail->account)
                                                <option value="{{ $detail->account->id }}" selected>
                                                    {{ $detail->account->name }} ({{ $detail->account->code }})
                                                </option>
                                            @endif
                                        </select>
                                    </td>

                                    {{--
                                        **الخانةُ تعني ما أدخله المحاسبُ أوّلاً، لا ما خُزِّن.**

                                        السطرُ المحفوظ يحمل الوعاءَ بعد نزع الضريبة
                                        الشاملة منه. فعرضُ الوعاء هنا يجعل الحفظ يستخرجها
                                        مرّةً ثانية: ينكمش المبلغ، ويُردّ القيدُ «غير
                                        متوازن» لأنّ الجانب المقابل بقي على إجماليه.

                                        فالشاملُ يُفتح بإجماليه، والمضافُ بوعائه.
                                    --}}
                                    <td>
                                        <input type="number" name="details[{{ $index }}][debit]"
                                            class="form-control debit calc-tax" min="0" step="0.01"
                                            value="{{ \App\Support\TaxCalculator::grossOf($detail->debit, $detail->tax_id) }}">
                                    </td>

                                    <td>
                                        <input type="number" name="details[{{ $index }}][credit]"
                                            class="form-control credit calc-tax" min="0" step="0.01"
                                            value="{{ \App\Support\TaxCalculator::grossOf($detail->credit, $detail->tax_id) }}">
                                    </td>

                                    <td>
                                        <input type="text" name="details[{{ $index }}][description]"
                                            class="form-control" value="{{ $detail->description }}">
                                    </td>

                                    @accountingOn('show_cost_center')
                                    <td>
                                        <select name="details[{{ $index }}][cost_center_id]"
                                            class="form-control cost-center-select">
                                            <option value="">بلا مركز</option>
                                            @foreach ($costCenters as $center)
                                                <option value="{{ $center->id }}"
                                                    @selected($detail->cost_center_id == $center->id)>
                                                    {{ $center->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-create-first
                                            :count="count($costCenters ?? [])"
                                            route="cost_centers.index"
                                            permission="account.cost_centers.view"
                                            :plan="\App\Constants\planFeatures\AccountPlanFeatures::COST_CENTERS"
                                            label="مركز تكلفة"
                                            hint="مراكز التكلفة اختيارية للقيد، ولا مركز مسجَّل بعد." />
                                    </td>
                                    @endaccountingOn

                                    <td>
                                        <select name="details[{{ $index }}][tax_type]"
                                            class="form-control tax-type calc-tax">
                                            <option value="">بلا ضريبة</option>
                                            {{-- `tax` تبقى مقبولةً للسجلّات القديمة، وتُعرض كـ«خاضعة» --}}
                                            <option value="vat" @selected(in_array($detail->tax_type, ['vat', 'tax'], true))>
                                                خاضعة للضريبة
                                            </option>
                                            <option value="exempt" @selected($detail->tax_type === 'exempt')>معفاة</option>
                                            <option value="zero" @selected($detail->tax_type === 'zero')>صفرية</option>
                                        </select>
                                    </td>

                                    <td>
                                        {{--
                                            الحقل يرسل **معرّف الضريبة** لا نسبتها: النسبةُ
                                            وطريقتُها (شاملة/مضافة) تُقرآن من الإعدادات على
                                            الخادم، فلا يُحفَظ رقمٌ حسبَه المتصفّح.
                                        --}}
                                        <select name="details[{{ $index }}][tax_id]"
                                            class="form-control tax-rate calc-tax">
                                            <option value="" data-rate="0" data-type=""
                                                @selected(empty($detail->tax_id))>بلا ضريبة</option>

                                            @foreach ($taxRates as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                    data-type="{{ $tax->type }}"
                                                    @selected((int) $detail->tax_id === (int) $tax->id)>
                                                    {{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%)
                                                    — {{ \App\Support\TaxCalculator::typeLabel($tax->type) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted tax-note d-block mt-1" style="display:none"></small>
                                    </td>

                                    <td>
                                        {{-- تُحسب في الشاشة، ويُعاد حسابُها على الخادم من المعرّف --}}
                                        <input type="number" name="details[{{ $index }}][tax_amount]"
                                            class="form-control tax-amount" readonly value="0">
                                    </td>

                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row"
                                            aria-label="حذف السطر">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="stk-total-row">
                                <td><strong>المجموع</strong></td>
                                <td class="text-center"><strong id="total-debit">0.00</strong></td>
                                <td class="text-center"><strong id="total-credit">0.00</strong></td>
                                <td colspan="4"></td>
                                <td class="text-center"><strong id="total-tax">0.00</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="stk-table-foot">
                    <span class="stk-table-foot__count" id="balanceHint">
                        <i class="feather icon-check-circle"></i> القيد متوازن
                    </span>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{-- select2 من الشبكة: القالب الرئيسي لا يحمّله --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            let rowCount = {{ $journal->details->where('is_tax_line', false)->count() }};

            const accountSelect2 = {
                placeholder: 'اختر الحساب…',
                allowClear: true,
                width: '100%',
                dir: 'rtl',
                minimumInputLength: 1,

                /*
                    رسائلُ select2 بالعربية.

                    المكتبةُ تُحمَّل من الشبكة بلا ملفّ لغة، فتظهر رسائلُها
                    الإنجليزية في شاشةٍ عربية: «Please enter 1 or more
                    characters» و«No results found» و«Searching…».

                    وتحميلُ ملفّ اللغة من الشبكة يزيد طلباً ويقع تحت رحمته؛
                    والرسائلُ خمسٌ تُكتب هنا مرّةً.
                */
                language: {
                    inputTooShort: function (args) {
                        var n = args.minimum - args.input.length;
                        return 'أدخل ' + n + ' ' + (n === 1 ? 'حرفاً' : 'أحرف') + ' على الأقل للبحث';
                    },
                    inputTooLong: function (args) {
                        var n = args.input.length - args.maximum;
                        return 'احذف ' + n + ' ' + (n === 1 ? 'حرفاً' : 'أحرف');
                    },
                    noResults: function () { return 'لا نتائج مطابقة'; },
                    searching: function () { return 'جارٍ البحث…'; },
                    loadingMore: function () { return 'تحميل المزيد…'; },
                    errorLoading: function () { return 'تعذّر تحميل النتائج'; },
                    maximumSelected: function (args) {
                        return 'لا يمكن اختيار أكثر من ' + args.maximum;
                    },
                },
                ajax: {
                    url: '{{ route('journal.search.accounts') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term, page: params.page || 1 };
                    },
                    processResults: function(data, params) {
                        params.page = params.page || 1;

                        return {
                            results: $.map(data, function(item) {
                                return {
                                    id: item.id,
                                    text: (item.level ? '—'.repeat(item.level) + ' ' : '') +
                                        item.name + ' (' + item.code + ')',
                                };
                            }),
                            pagination: { more: (params.page * 50) < data.length },
                        };
                    },
                    cache: true,
                },
            };

            $('.account-search').select2(accountSelect2);

            let costCenterOptions = '<option value="">بلا مركز</option>';
            @foreach ($costCenters as $center)
                costCenterOptions += '<option value="{{ $center->id }}">{{ $center->name }}</option>';
            @endforeach

            let taxRateOptions = '<option value="" data-rate="0" data-type="">بلا ضريبة</option>';
            @foreach ($taxRates as $tax)
                taxRateOptions +=
                    '<option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}">{{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%) — {{ \App\Support\TaxCalculator::typeLabel($tax->type) }}</option>';
            @endforeach

            $('#add-row').click(function() {
                const row = $(`
                    <tr>
                        <td>
                            <select name="details[${rowCount}][account_id]" class="form-control account-search" required>
                                <option value="">اختر الحساب</option>
                            </select>
                        </td>
                        <td><input type="number" name="details[${rowCount}][debit]" class="form-control debit calc-tax" min="0" step="0.01" value="0"></td>
                        <td><input type="number" name="details[${rowCount}][credit]" class="form-control credit calc-tax" min="0" step="0.01" value="0"></td>
                        <td><input type="text" name="details[${rowCount}][description]" class="form-control"></td>
                        @accountingOn('show_cost_center')<td><select name="details[${rowCount}][cost_center_id]" class="form-control cost-center-select">${costCenterOptions}</select></td>@endaccountingOn
                        <td>
                            <select name="details[${rowCount}][tax_type]" class="form-control tax-type calc-tax">
                                <option value="">بلا ضريبة</option>
                                <option value="vat">خاضعة للضريبة</option>
                                <option value="exempt">معفاة</option>
                                <option value="zero">صفرية</option>
                            </select>
                        </td>
                        <td><select name="details[${rowCount}][tax_id]" class="form-control tax-rate calc-tax">${taxRateOptions}</select><small class="text-muted tax-note d-block mt-1" style="display:none"></small></td>
                        <td><input type="number" name="details[${rowCount}][tax_amount]" class="form-control tax-amount" readonly value="0"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="حذف السطر">
                                <i class="feather icon-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `);

                $('#entries-table tbody').append(row);
                row.find('.account-search').select2(accountSelect2);

                rowCount++;
                updateTotals();
            });

            $(document).on('click', '.remove-row', function() {
                if ($('#entries-table tbody tr').length <= 1) {
                    Swal.fire('تنبيه', 'القيد يحتاج سطراً واحداً على الأقل.', 'info');
                    return;
                }

                $(this).closest('tr').remove();
                updateTotals();
            });

            $(document).on('input', '.debit, .credit', function() {
                calculateRowTax($(this).closest('tr'));
                updateTotals();
            });

        /** يقبل `0.15` و`15` معاً ويعيد الكسر. */
        function taxRateToFraction(rate) {
            var value = parseFloat(rate) || 0;

            if (value <= 0) {
                return 0;
            }

            // ما دون الواحد كسرٌ أصلاً؛ وما فوقه نسبةٌ مئوية
            return value < 1 ? value : value / 100;
        }

        /*
            **طريقةُ الضريبة تُقرأ من الإعدادات، لا تُفترَض.**

            كان الحسابُ ضرباً في النسبة وحدها — أي كأنّ كلّ ضريبةٍ «مضافة».
            فمن ضبط ضريبةً «شاملة» (`included`) أُضيفت له فوق مبلغٍ يحملها
            أصلاً، فتضخّم الإجماليُّ بمقدارها مرّتين.

            و«شاملة» تعني أن ما أُدخل في خانة المدين أو الدائن هو الإجمالي،
            فتُستخرَج الضريبةُ منه: `المبلغ × النسبة ÷ (١ + النسبة)`.
        */
        function taxOf(row) {
            var option = row.find('.tax-rate option:selected');
            var rate = taxRateToFraction(option.data('rate'));
            var inclusive = option.data('type') === 'included';
            var taxType = row.find('.tax-type').val();

            /*
                «خاضعة» تُحتسب، و«معفاة» و«صفرية» لا. والقائمةُ ترسل `vat`،
                و`tax` تبقى مقبولةً لسجلّاتٍ قديمة.
            */
            var taxable = (taxType === 'vat' || taxType === 'tax') && rate > 0;

            var debit = parseFloat(row.find('.debit').val()) || 0;
            var credit = parseFloat(row.find('.credit').val()) || 0;
            var amount = debit > 0 ? debit : credit;

            if (!taxable || amount <= 0) {
                return { tax: 0, base: amount, inclusive: false };
            }

            var base = inclusive ? amount / (1 + rate) : amount;

            return {
                tax: Math.round(base * rate * 100) / 100,
                base: Math.round(base * 100) / 100,
                inclusive: inclusive
            };
        }

        function calculateRowTax(row) {
            var line = taxOf(row);

            row.find('.tax-amount').val(line.tax.toFixed(2));

            /*
                الفرقُ بين الطريقتين يمسّ المبلغ، فحقُّه أن يُقرأ لا أن
                يُستنتَج: السطرُ الشامل يُعلن وعاءَه بعد نزع الضريبة.

                **تنبيه للمعدِّل:** السطرُ المحفوظ يحمل أساسَه لا إجماليه —
                فالضريبةُ نُزعت منه عند الحفظ الأوّل. فإن أُعيد حفظُه بلا
                تغيير بقي كما هو، ولا تُنزع منه مرّةً ثانية.
            */
            var note = row.find('.tax-note');

            if (line.inclusive && line.tax > 0) {
                note.html('شاملة — الوعاء ' + window.AppCurrency.number(line.base)).show();
            } else {
                note.hide();
            }
        }

            $(document).on('change', '.calc-tax', function() {
                calculateRowTax($(this).closest('tr'));
                updateTotals();
            });

            function updateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;
                let totalTax = 0;

                $('#entries-table tbody tr').each(function() {
                    const row = $(this);
                    const line = taxOf(row);
                    const debit = parseFloat(row.find('.debit').val()) || 0;
                    const credit = parseFloat(row.find('.credit').val()) || 0;

                    totalTax += line.tax;

                    /*
                        **الضريبة الشاملة لا تُضاف إلى الإجمالي.**

                        المبلغُ المُدخَل يحملها أصلاً، فالمجموعُ هو هو،
                        وإنما ينقسم سطرُه إلى وعاءٍ وضريبة عند الحفظ.
                        والمضافةُ وحدها تزيد جانبَها.
                    */
                    const extra = line.inclusive ? 0 : line.tax;

                    totalDebit += debit + (debit > 0 ? extra : 0);
                    totalCredit += credit + (debit > 0 ? 0 : extra);
                });

                $('#total-debit').text(window.AppCurrency.number(totalDebit));
                $('#total-credit').text(window.AppCurrency.number(totalCredit));
                $('#total-tax').text(window.AppCurrency.number(totalTax));

                const difference = Math.round((totalDebit - totalCredit) * 100) / 100;
                const balanced = difference === 0;

                $('#total-debit, #total-credit').toggleClass('text-danger', !balanced);

                $('#balanceHint').html(balanced ?
                    '<i class="feather icon-check-circle"></i> القيد متوازن' :
                    '<i class="feather icon-alert-triangle"></i> فرق ' +
                    window.AppCurrency.format(Math.abs(difference)) +
                    (difference > 0 ? ' زيادة في المدين' : ' زيادة في الدائن'));
            }

            $('#journal_form').submit(function(e) {
                e.preventDefault();

                const form = this;
                const submitBtn = $('#submitBtn');

                const totalDebit = parseFloat($('#total-debit').text().replace(/[^\d.\-]/g, '')) || 0;
                const totalCredit = parseFloat($('#total-credit').text().replace(/[^\d.\-]/g, '')) || 0;

                if (Math.round((totalDebit - totalCredit) * 100) !== 0) {
                    Swal.fire('القيد غير متوازن', 'مجموع المدين يجب أن يساوي مجموع الدائن.', 'error');
                    return;
                }

                Swal.fire({
                    title: 'تأكيد تعديل القيد',
                    html: '<div class="text-end" dir="rtl">' +
                        '<p><strong>التاريخ:</strong> ' + $('#date').val() + '</p>' +
                        '<p><strong>البيان:</strong> ' + $('#description').val() + '</p>' +
                        '<p><strong>الإجمالي:</strong> ' + window.AppCurrency.format(totalDebit) + '</p>' +
                        '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(function(result) {
                    if (result.isConfirmed) {
                        submitBtn.prop('disabled', true);
                        submitBtn.html('<i class="feather icon-loader"></i> جارٍ الحفظ…');
                        form.submit();
                    }
                });
            });

            updateTotals();
        });
    </script>
@endsection
