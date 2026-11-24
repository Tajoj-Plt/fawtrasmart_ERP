{{--
    حقول سند القبض — منتزَعةٌ من create.blade.php لتُستدعى من هناك ومن
    مودال سند القبض بمساحة عمل الزيارة (VisitWorkspaceController) معاً،
    فلا تتفرّق نسختان تختلفان عند أوّل تصحيح.

    تحتاج المتغيّرات نفسها التي تُعيدها IncomesController::formData():
    $nextCode $incomes_categories $accounts $otherAccounts $treasuries
    $MainTreasury $taxs $branches $selectedAccountId

    $compact = true يُسقط عنوانَي البطاقتين الثانويتين (الضرائب،
    التكرار/المرفق) إلى صفٍّ واحد أخفّ — للمودال حيث المساحة أضيق.
--}}

@php
    $userBranchId = auth()->user()->branch_id ?? null;
    $isAdmin = ! auth()->user()->isEmployee();
@endphp

{{-- ═══════════ بيانات السند ═══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-dollar-sign"></i> بيانات السند
        </h6>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-4">
                <label for="amount_input" class="stk-req">المبلغ</label>
                <input type="text" class="form-control form-control-lg stk-num" id="amount_input"
                    name="amount" value="{{ old('amount') }}" placeholder="0.00" required>
            </div>

            <div class="form-group col-md-4">
                <label for="total_amount">الإجمالي بعد الضريبة</label>
                <input type="text" class="form-control form-control-lg stk-num" name="total_amount"
                    id="total_amount" readonly>
                <small class="stk-help">يُحسب تلقائياً من المبلغ والضرائب</small>
                <small class="text-muted d-block mt-1" id="taxMethodHint" style="display:none"></small>
            </div>

            <div class="form-group col-md-4">
                <label for="code-number">رقم السند</label>
                <input type="text" class="form-control stk-num" id="code-number" name="code"
                    value="{{ $nextCode }}" readonly>
                <small class="stk-help">من تسلسل الترقيم في الإعدادات</small>
            </div>

            <div class="form-group col-md-4">
                <label for="date" class="stk-req">التاريخ</label>
                <input type="date" class="form-control" id="date" name="date"
                    value="{{ old('date', date('Y-m-d')) }}" required>
            </div>

            <div class="form-group col-md-4">
                <label for="incomes_category_id" class="stk-req">التصنيف</label>
                <select id="incomes_category_id" class="form-control select2" name="incomes_category_id" required>
                    <option value="">-- اختر تصنيفاً --</option>
                    @foreach ($incomes_categories as $category)
                        <option value="{{ $category->id }}" @selected(old('incomes_category_id') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <x-create-first
                    :count="count($incomes_categories ?? [])"
                    route="finance_settings.receipt_category"
                    permission="account.settings.receipt_categories"
                    :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                    label="تصنيف سندات قبض"
                    hint="تصنيفات سندات القبض تُعرَّف في الإعدادات المالية، ولا تصنيف بعد." />
            </div>

            <div class="form-group col-md-4">
                @include('account::components.payment-method-select', [
                    'name' => 'payment_method_id',
                    'label' => 'طريقة القبض',
                    'class' => 'form-control select2',
                    'selected' => old('payment_method_id'),
                ])
                <small class="stk-help">من شاشة «طرق الدفع» في الإعدادات</small>
            </div>

            <div class="form-group col-12">
                <label for="description">الوصف</label>
                <textarea class="form-control" id="description" rows="2" name="description">{{ old('description') }}</textarea>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════ طرفا القيد ═══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-git-merge"></i> طرفا القيد
        </h6>
        <span class="stk-help">الخزينة مدينة بالمبلغ، والحساب المقابل دائن به</span>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-6">
                <label for="account_id" class="stk-req">الحساب المقابل</label>
                <select id="account_id" class="form-control select2" name="account_id" required>
                    <option value="">اختر الحساب أو ابحث بالكود</option>

                    @if (count($accounts))
                        <optgroup label="حسابات العملاء">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-code="{{ $account->code }}"
                                    data-search="{{ trim(($account->code ? $account->code . ' ' : '') . $account->name . ' ' . ($account->client->trade_name ?? '') . ' ' . ($account->client->full_name ?? '') . ' ' . ($account->client->code ?? '')) }}"
                                    @selected(old('account_id', $selectedAccountId) == $account->id)>
                                    {{ $account->code ? $account->code . ' - ' : '' }}{{ $account->name }}
                                    @if (isset($account->client) && $account->client)
                                        ({{ $account->client->code ?? 'بدون كود' }})
                                    @endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endif

                    @if (count($otherAccounts ?? []))
                        <optgroup label="حسابات أخرى">
                            @foreach ($otherAccounts as $account)
                                <option value="{{ $account->id }}" data-code="{{ $account->code }}"
                                    data-search="{{ trim(($account->code ? $account->code . ' ' : '') . $account->name . ' ' . ($account->client->trade_name ?? '') . ' ' . ($account->client->full_name ?? '') . ' ' . ($account->client->code ?? '')) }}"
                                    @selected(old('account_id', $selectedAccountId) == $account->id)>
                                    {{ $account->code ? $account->code . ' - ' : '' }}{{ $account->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <x-create-first
                    :count="count($otherAccounts ?? []) + count($accounts ?? [])"
                    route="accounts_chart.view_root"
                    permission="account.chart.view"
                    :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                    label="حساباً"
                    hint="الحساب يُنشأ من دليل الحسابات، ولا حساب مطابق بعد." />
                <small class="stk-help">ابحث بالاسم أو بكود الحساب أو بكود العميل</small>
            </div>

            <div class="form-group col-md-6">
                <label for="treasury_id" class="stk-req">الخزينة</label>
                <select id="treasury_id" class="form-control select2" name="treasury_id" required>
                    <option value="">-- اختر الخزينة --</option>
                    @foreach ($treasuries ?? [] as $treasury)
                        <option value="{{ $treasury->id }}"
                            @selected(old('treasury_id', $MainTreasury->id ?? '') == $treasury->id)>
                            {{ $treasury->name }}
                        </option>
                    @endforeach
                </select>
                <x-create-first
                    :count="count($treasuries ?? [])"
                    route="treasury.create"
                    permission="account.treasury.create"
                    :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                    label="خزينة"
                    hint="لا خزائن مسجَّلة بعد — والعملية تحتاج خزينةً تُقيَّد عليها." />
            </div>

            @accountingOn('show_cost_center')
                <div class="form-group col-md-6">
                    <label for="cost_center_id">مركز التكلفة</label>
                    <select id="cost_center_id" class="form-control select2" name="cost_center_id">
                        <option value="">بلا مركز</option>
                        @foreach (\App\Support\AccountingPeriodOptions::costCenters() as $center)
                            <option value="{{ $center->id }}" @selected(old('cost_center_id') == $center->id)>
                                {{ $center->code ? $center->code . ' — ' : '' }}{{ $center->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endaccountingOn

            <div class="form-group col-md-6">
                <label for="branch_id">الفرع</label>
                @if ($isAdmin)
                    <select id="branch_id" class="form-control select2" name="branch_id">
                        <option value="">-- اختر الفرع --</option>
                        @foreach ($branches ?? [] as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $userBranchId) == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-create-first
                        :count="count($branches ?? [])"
                        route="branches.create"
                        label="فرعاً"
                        hint="الفروع تُدار في وحدة الفروع، ولا فرع مسجَّل بعد." />
                @else
                    <input type="text" class="form-control"
                        value="{{ optional(collect($branches ?? [])->firstWhere('id', $userBranchId))->name }}"
                        readonly>
                    <input type="hidden" name="branch_id" value="{{ $userBranchId }}">
                    <small class="stk-help">فرعك المعيَّن — للقراءة فقط</small>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══════════ الضرائب ═══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-percent"></i> الضرائب
        </h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="toggleTaxBtn">
            <i class="feather icon-plus"></i> إضافة ضرائب
        </button>
    </div>

    <div class="card-body">
        <div id="tax-fields" style="display: none;">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="tax1">الضريبة الأولى</label>
                    <select id="tax1" class="form-control" name="tax1">
                        <option value="">اختر الضريبة</option>
                        @foreach ($taxs as $tax)
                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}">{{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </select>
                    <x-create-first
                        :count="count($taxs ?? [])"
                        route="TaxSitting.index"
                        label="إعداد ضريبة"
                        hint="الضرائب تُعرَّف في إعدادات الضرائب، ولا ضريبة معرَّفة بعد." />
                    <input type="text" class="form-control stk-num mt-1" placeholder="المبلغ"
                        name="tax1_amount" id="tax1_amount" readonly>

                    @include('account::finance.partials.tax-accounts', [
                        'index' => 1,
                        'preferred' => 'payable',
                        'selected' => null,
                    ])
                </div>

                <div class="form-group col-md-6">
                    <label for="tax2">الضريبة الثانية</label>
                    <select id="tax2" class="form-control" name="tax2">
                        <option value="">اختر الضريبة</option>
                        @foreach ($taxs as $tax)
                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}">{{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control stk-num mt-1" placeholder="المبلغ"
                        name="tax2_amount" id="tax2_amount" readonly>

                    @include('account::finance.partials.tax-accounts', [
                        'index' => 2,
                        'preferred' => 'payable',
                        'selected' => null,
                    ])
                </div>

                <div class="col-12">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeTaxBtn">
                        <i class="feather icon-x"></i> إزالة الضرائب
                    </button>
                </div>
            </div>
        </div>

        <p class="stk-def mb-0" id="taxEmptyHint">لا ضرائب على هذا السند.</p>
    </div>
</div>

{{-- ═══════════ التكرار والمرفق ═══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-repeat"></i> التكرار والمرفق
        </h6>
    </div>

    <div class="card-body">
        {{--
            **ما كان**

            أربعة حقول (المرفق، سند مكرَّر، التكرار، تاريخ الانتهاء) في صفٍّ
            واحد بعرض `col-md-4` لكلٍّ منها — أي ١٦ وحدة من أصل ١٢. فيسع
            الصفُّ ثلاثةً فقط، ويفيض الرابع (تاريخ الانتهاء) إلى صفٍّ جديد
            وحده، تاركاً ثُلثي عرض الشاشة فارغاً بجانبه — كما في سند الصرف
            بالضبط، حيث المرفق في صفٍّ مستقلٍّ لا يشارك صفَّ إعدادات التكرار.

            **ما صار**

            صفٌّ للمرفق (`col-md-6` + معاينته)، وصفٌّ مستقلٌّ لإعدادات
            التكرار الثلاثة (`col-md-4` × ٣ = ١٢ بالضبط متى ظهرت معاً) —
            على نسق سند الصرف تماماً.
        --}}
        <div class="row">
            <div class="form-group col-md-6">
                <label for="attachments">المرفق</label>
                <input id="attachments" type="file" name="attachments" class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png">
                <small class="stk-help">PDF أو صورة — بحدّ أقصى ٢ ميجابايت</small>
            </div>

            <div class="form-group col-md-6">
                <label>معاينة المرفق</label>
                <div id="attachment-preview" class="stk-choice" style="min-height:60px;max-height:150px;overflow-y:auto">
                    <small class="stk-help">لم يُختَر مرفق بعد.</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="form-group col-md-4">
                {{--
                    `.custom-switch .custom-control-label` في تصميم Vuexy
                    مربّعٌ صغير ثابت العرض (`3.1rem`، `padding:0`) يمثّل شكل
                    المفتاح نفسه لا غير — وليس وعاءً لنصّ. وضعُ العنوان
                    والشرح داخله (كما فعلتُ قبلُ) يحشرهما في ذلك المربّع
                    الصغير فيتقطّع النص ويتراكب مع شكل المفتاح.

                    الحل: تسمية المفتاح تبقى فارغة (شكل المفتاح وحده)،
                    والنص عنصرٌ منفصل بجانبه — كلاهما `for="checkbox"`
                    فالنقر على النص يبدّل المفتاح أيضاً.
                --}}
                <div class="d-flex align-items-center" style="gap: .6rem;">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="checkbox" name="is_recurring">
                        <label class="custom-control-label" for="checkbox"></label>
                    </div>
                    <label for="checkbox" class="mb-0" style="cursor: pointer;">
                        <span style="display: block; font-weight: 600;">سند مكرَّر</span>
                        <small class="text-muted" style="display: block;">يُعاد إنشاؤه تلقائياً على فترات.</small>
                    </label>
                </div>
            </div>

            <div class="form-group col-md-4" id="duplicate-options-container" style="display: none;">
                <label for="duplicate-options">التكرار</label>
                <select id="duplicate-options" class="form-control" name="recurring_frequency">
                    <option value="">حدّد التكرار</option>
                    <option value="daily">يومي</option>
                    <option value="weekly">أسبوعي</option>
                    <option value="bi-weekly">كل أسبوعين</option>
                    <option value="monthly">شهري</option>
                    <option value="yearly">سنوي</option>
                </select>
            </div>

            <div class="form-group col-md-4" id="end-date-container" style="display: none;">
                <label for="end-date">تاريخ الانتهاء</label>
                <input type="date" class="form-control" id="end-date" value="{{ date('Y-m-d') }}"
                    name="end_date">
            </div>
        </div>
    </div>
</div>
