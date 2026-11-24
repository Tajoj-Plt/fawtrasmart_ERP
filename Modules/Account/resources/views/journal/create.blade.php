@extends('account::master')

@section('title', 'قيد يومية جديد')

@section('content')
    {{--
        لا سنةَ مالية ⇒ المستندُ يُحفَظ بلا نسبة. يُقال ذلك قبل الإنشاء لا بعده.
    --}}
    @include('partials.no-fiscal-year-notice')

    @php
        // الفترة المحاسبية الجارية: التاريخ يفتح عليها لا على «اليوم» مطلقاً
        $defaultDate = \App\Models\AccountingPeriod::forDate(today())
            ? today()->toDateString()
            : today()->toDateString();

        $taxRates = collect($taxs ?? []);
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('journal.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="journal_form" id="submitBtn" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ القيد
            </button>
        @endsection

        @include('account::components.page-head', [
            'title' => 'قيد يومية جديد',
            'icon' => 'edit-3',
            'sub' => 'سطور القيد ومراكز تكلفتها — لا يُحفَظ حتى يتوازن المدين مع الدائن',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قيود اليومية' => route('journal.index'),
                'قيد جديد' => null,
            ],
        ])

        {{--
            **ما كان**

            بطاقتان بلا عنوانٍ ولا أيقونة، وحقولٌ متجاورة بلا أقسام تفصل
            بيانات القيد عن سطوره. وزرّا الحفظ والإلغاء مرسومان مرّتين —
            في ترويسة الصفحة وفي تذييل النموذج معاً.

            والعملة كانت `value="SAR"` مكتوبةً في القالب، ونسب الضريبة
            ثلاثةَ خياراتٍ ثابتة (0% و15% و5%) — بينما لكلٍّ منهما شاشة
            إعداداتٍ تعرّفه.
        --}}
        <form id="journal_form" action="{{ route('journal.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-file-text"></i> بيانات القيد
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="reference_type" class="stk-req">نوع المرجع</label>
                            {{--
                                يفتح على «قيد يومية عام»: هو ما تُنشئه هذه
                                الشاشة، وكان الحقل يفتح فارغاً فيبقى الرقم
                                المرجعي فارغاً حتى يُختار نوعٌ ما.
                            --}}
                            <select id="reference_type" name="journal_entry[reference_type]" class="form-control" required>
                                <option value="">اختر نوع المرجع</option>
                                @foreach ($referenceTypes as $code => $type)
                                    <option value="{{ $code }}"
                                        @selected(old('journal_entry.reference_type', \App\Enums\ReferenceType::JOURNAL_VOUCHER->value) === $code)>
                                        {{ $type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="reference_number">الرقم المرجعي</label>
                            <input type="text" id="reference_number" name="journal_entry[reference_number]"
                                class="form-control stk-num" readonly
                                value="{{ peek_document_number('entry') }}" placeholder="يُولَّد تلقائياً">
                            <small class="stk-help">
                                معاينة من قسم «قيد يومية» في «ترقيم المستندات» — يُحجَز الرقم عند الحفظ.
                            </small>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="date" class="stk-req">التاريخ</label>
                            <input type="date" id="date" name="journal_entry[date]" class="form-control"
                                value="{{ old('journal_entry.date', $defaultDate) }}" required>
                            <small class="stk-help">يُرفَض إن وقع في فترةٍ مقفلة.</small>
                        </div>

                        <div class="form-group col-md-2">
                            <label for="currency">العملة</label>
                            {{-- كانت `SAR` مكتوبةً هنا، فلا يتبعها تبديل العملة في الإعدادات --}}
                            <input type="text" id="currency" name="journal_entry[currency]" class="form-control"
                                value="{{ \App\Support\CurrencySettings::code() }}" readonly>
                        </div>

                        <div class="form-group col-12">
                            <label for="description" class="stk-req">البيان</label>
                            <textarea id="description" name="journal_entry[description]" class="form-control" rows="2" required
                                placeholder="وصف القيد…">{{ old('journal_entry.description') }}</textarea>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="attachments">المرفق</label>
                            <input id="attachments" type="file" name="journal_entry[attachment]" class="form-control"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="stk-help">PDF أو صورة — بحدّ أقصى ٢ ميجابايت.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> سطور القيد
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
                            <tr>
                                <td>
                                    <select name="details[0][account_id]" class="form-control account-search" required>
                                        <option value="">اختر الحساب</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="details[0][debit]" class="form-control debit calc-tax"
                                        min="0" step="0.01" value="0">
                                </td>
                                <td>
                                    <input type="number" name="details[0][credit]" class="form-control credit calc-tax"
                                        min="0" step="0.01" value="0">
                                </td>
                                <td>
                                    <input type="text" name="details[0][description]" class="form-control">
                                </td>
                                @accountingOn('show_cost_center')
                                <td>
                                    <select name="details[0][cost_center_id]" class="form-control cost-center-select">
                                        <option value="">بلا مركز</option>
                                        @foreach ($costCenters as $center)
                                            <option value="{{ $center->id }}">{{ $center->name }}</option>
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
                                    <select name="details[0][tax_type]" class="form-control tax-type calc-tax">
                                        <option value="">بلا ضريبة</option>
                                        <option value="vat">خاضعة للضريبة</option>
                                        <option value="exempt">معفاة</option>
                                        <option value="zero">صفرية</option>
                                    </select>
                                </td>
                                <td>
                                    {{--
                                        الحقل يرسل **معرّف الضريبة** لا نسبتها: النسبةُ
                                        وطريقتُها (شاملة/مضافة) تُقرآن من الإعدادات على
                                        الخادم، فلا يُحفَظ رقمٌ حسبَه المتصفّح.
                                    --}}
                                    <select name="details[0][tax_id]" class="form-control tax-rate calc-tax">
                                        <option value="" data-rate="0" data-type="">بلا ضريبة</option>
                                        @foreach ($taxRates as $tax)
                                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                data-type="{{ $tax->type }}">
                                                {{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%)
                                                — {{ \App\Support\TaxCalculator::typeLabel($tax->type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted tax-note d-block mt-1" style="display:none"></small>
                                    <x-create-first
                                        :count="count($taxRates ?? [])"
                                        route="TaxSitting.index"
                                        label="إعداد ضريبة"
                                        hint="الضرائب تُعرَّف في إعدادات الضرائب، ولا ضريبة معرَّفة بعد." />
                                </td>
                                <td>
                                    <input type="number" name="details[0][tax_amount]" class="form-control tax-amount"
                                        readonly value="0">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-row"
                                        aria-label="حذف السطر">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
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
            let rowCount = 1;

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

            // توليد الرقم المرجعي من تسلسل الإعدادات عند اختيار النوع
            $('#reference_type').on('change', function() {
                const referenceType = $(this).val();
                const field = $('#reference_number');

                if (!referenceType) {
                    field.val('');
                    return;
                }

                field.val('جارٍ التوليد…');

                $.ajax({
                    url: '{{ route('journal.next_reference_number') }}',
                    type: 'GET',
                    data: { reference_type: referenceType },
                    success: function(response) {
                        field.val(response.success ? response.reference_number : '');
                    },
                    error: function() {
                        field.val('');
                    },
                });
            });

            // خيارات مراكز التكلفة للسطور الجديدة
            let costCenterOptions = '<option value="">بلا مركز</option>';
            @foreach ($costCenters as $center)
                costCenterOptions += '<option value="{{ $center->id }}">{{ $center->name }}</option>';
            @endforeach

            // نسب الضريبة من الإعدادات
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
                // آخر سطرٍ لا يُحذف: القيد بلا سطورٍ لا معنى له
                if ($('#entries-table tbody tr').length <= 1) {
                    Swal.fire('تنبيه', 'القيد يحتاج سطراً واحداً على الأقل.', 'info');
                    return;
                }

                $(this).closest('tr').remove();
                updateTotals();
            });

            $(document).on('input', '.debit, .credit', updateTotals);

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

            $(document).on('input', '.debit, .credit', function() {
                calculateRowTax($(this).closest('tr'));
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

                /*
                    القيد غير المتوازن كان يُعرَف بلونٍ أحمر على الرقمين وحدهما.
                    صار سطراً يقول الفرق صراحةً — والاختلال غالباً في خانتَي
                    القروش، فلا يظهر لعينٍ تقارن رقمين متجاورين.
                */
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

                const totalDebit = window.AppCurrency ?
                    parseFloat($('#total-debit').text().replace(/[^\d.\-]/g, '')) : 0;
                const totalCredit = window.AppCurrency ?
                    parseFloat($('#total-credit').text().replace(/[^\d.\-]/g, '')) : 0;

                if (Math.round((totalDebit - totalCredit) * 100) !== 0) {
                    Swal.fire('القيد غير متوازن', 'مجموع المدين يجب أن يساوي مجموع الدائن.', 'error');
                    return;
                }

                Swal.fire({
                    title: 'تأكيد إضافة القيد',
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
                    if (!result.isConfirmed) {
                        return;
                    }

                    submitBtn.prop('disabled', true);
                    submitBtn.html('<i class="feather icon-loader"></i> جارٍ الحفظ…');

                    fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': $('input[name="_token"]').val(),
                            },
                        })
                        .then(function(response) {
                            return response.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تمّ',
                                    text: data.message || 'أُضيف القيد.',
                                    timer: 2000,
                                    showConfirmButton: false,
                                }).then(function() {
                                    window.location.href = data.redirect || '{{ route('journal.index') }}';
                                });
                                return;
                            }

                            const errors = data.errors ? Object.values(data.errors).flat().join('\n') : '';

                            Swal.fire('خطأ في البيانات', errors || data.message ||
                                'حدث خطأ أثناء حفظ البيانات.', 'error');
                        })
                        .catch(function(error) {
                            console.error(error);
                            Swal.fire('خطأ في الاتصال', 'تعذّر إرسال البيانات — أعِد المحاولة.', 'error');
                        })
                        .finally(function() {
                            submitBtn.prop('disabled', false);
                            submitBtn.html('<i class="feather icon-save"></i> حفظ القيد');
                        });
                });
            });

            updateTotals();
        });
    </script>
@endsection
