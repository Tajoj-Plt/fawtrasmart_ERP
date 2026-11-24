@extends('account::master')

@section('title', 'سند صرف جديد')

@section('content')
    {{--
        لا سنةَ مالية ⇒ المستندُ يُحفَظ بلا نسبة. يُقال ذلك قبل الإنشاء لا بعده.
    --}}
    @include('partials.no-fiscal-year-notice')

    @php
        /*
            حسابا الضريبة كانا رقمين مكتوبين في القالب عبر ثابت `SystemAccountIds`،
            فما يضبطه المحاسب في شاشة «الحسابات المحاسبية» لا أثر له هنا.
            يُقرآن الآن من الإعدادات، والثابت احتياطٌ قبل الزرع.
        */
        $vatPayableId = \App\Support\AccountingSettings::id('vat_payable', \App\Constants\SystemAccountIds::VAT_PAYABLE);
        $vatPaidId = \App\Support\AccountingSettings::id('vat_paid', \App\Constants\SystemAccountIds::VAT_PAID);

        $userBranchId = auth()->user()->branch_id ?? null;
        $isAdmin = ! auth()->user()->isEmployee();

        /*
            «سند صرف لمورد» — قرار صاحب النظام (2026-09-23): ليس حصراً على
            المدير ($isAdmin) بل يشمل أيضاً من يملك data_scope_all ولو
            كان موظفاً، والموظف الذي لا يديره أحد في التسلسل الإداري
            (direct_manager_id فارغ) — أعلى الهرم الوظيفي وإن كان موظفاً.
        */
        $canExpenseToSupplier = $isAdmin
            || auth()->user()->hasRolePermission('data_scope_all')
            || (auth()->user()->employee && is_null(auth()->user()->employee->direct_manager_id));
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="expenses_form" class="btn btn-primary" id="submitBtn">
                <i class="feather icon-save"></i> حفظ السند
            </button>
        @endsection

        @include('account::components.page-head', [
            'title' => 'سند صرف جديد',
            'icon' => 'file-minus',
            'sub' => 'تسجيل مصروف وصرفه من خزينة — يُنشأ له قيدٌ محاسبي بتاريخ السند',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'سندات الصرف' => route('expenses.index'),
                'سند جديد' => null,
            ],
        ])


        <form id="expenses_form" action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="created_by" value="{{ auth()->id() }}">
            {{-- «صرف إلى» يُملأ تلقائياً من المورد أو الحساب أو التصنيف --}}
            <input type="hidden" id="seller" name="seller" value="{{ old('seller') }}">

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
                            <input type="text" class="form-control form-control-lg" id="amount_input"
                                name="amount" value="{{ old('amount') }}" placeholder="0.00" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="date" class="stk-req">التاريخ</label>
                            <input type="date" class="form-control" id="date" name="date"
                                value="{{ old('date', date('Y-m-d')) }}" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="treasury_id" class="stk-req">الخزينة</label>
                            {{--
                                كان الموظف مقفلاً على خزينته المعيَّنة له وحدها
                                (حقلٌ معطَّل + `hidden`) بلا خيار إطلاقاً. صار
                                بإمكانه اختيار خزينته هو أو خزينة أيّ موظفٍ يديره
                                — `$treasuries` مبنيّةٌ في المتحكّم على هذا
                                النطاق بالضبط (ScopesToEmployeeHierarchy)، فلا
                                حاجة لفرعٍ خاصٍّ به هنا؛ المدير يرى كل الخزائن
                                بنفس القائمة.
                            --}}
                            <select name="treasury_id" id="treasury_id" class="form-control select2" required>
                                <option value="" disabled
                                    {{ empty(old('treasury_id', $MainTreasury->id ?? '')) ? 'selected' : '' }}>
                                    اختر خزينة
                                </option>
                                @foreach ($treasuries as $treasury)
                                    <option value="{{ $treasury->id }}"
                                        @selected((string) old('treasury_id', $MainTreasury->id ?? '') === (string) $treasury->id)>
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
                            <small class="stk-help">الخزينة التي يُصرف منها المبلغ.</small>
                        </div>

                        <div class="form-group col-md-4">
                            @include('account::components.payment-method-select', [
                                'name' => 'payment_method_id',
                                'label' => 'طريقة الدفع',
                                'class' => 'form-control select2',
                                'selected' => old('payment_method_id'),
                            ])
                            <small class="stk-help">من شاشة «طرق الدفع» في الإعدادات.</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="code-number">رقم السند</label>
                            <input type="text" class="form-control stk-num" id="code-number" name="code"
                                value="{{ $code }}" readonly>
                            <small class="stk-help">يُولَّد تلقائياً حسب تسلسل المستندات.</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="branch_id">الفرع</label>
                            @if ($isAdmin)
                                <select id="branch_id" class="form-control select2" name="branch_id">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches ?? [] as $branch)
                                        <option value="{{ $branch->id }}"
                                            @selected(old('branch_id', $userBranchId) == $branch->id)>
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
                                <select id="branch_id" class="form-control" disabled>
                                    @foreach ($branches ?? [] as $branch)
                                        @if ($branch->id == $userBranchId)
                                            <option value="{{ $branch->id }}" selected>{{ $branch->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <input type="hidden" name="branch_id" value="{{ $userBranchId }}">
                                <small class="stk-help">فرعك المعيّن — للقراءة فقط.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-tag"></i> التصنيف والجهة المستفيدة
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="category" class="stk-req">التصنيف</label>
                            {{-- `$preselectCategoryId`: «التصنيف الافتراضي لسند صرف العميل» حين
                                 تُفتح الشاشة لعميلٍ من زيارته (راجع `ExpensesController::create()`) --}}
                            <select id="category" class="form-control select2" name="expenses_category_id" required
                                data-client-default="{{ $clientDefaultCategoryId ?? '' }}">
                                <option value="">اختر تصنيفاً</option>
                                @foreach ($expenses_categories as $expenses_category)
                                    <option value="{{ $expenses_category->id }}"
                                        @selected(old('expenses_category_id', $preselectCategoryId ?? null) == $expenses_category->id)>
                                        {{ $expenses_category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($expenses_categories ?? [])"
                                route="finance_settings.expenses_category"
                                permission="account.settings.expense_categories"
                                :plan="\App\Constants\planFeatures\AccountPlanFeatures::TREASURY"
                                label="تصنيف مصروفات"
                                hint="تصنيفات المصروفات تُعرَّف في الإعدادات المالية، ولا تصنيف بعد." />
                        </div>

                        {{--
                            مركز التكلفة اختياري: لا يُخزَّن في السند بل يُكتب في
                            السطر المدين من قيده — هناك يقرؤه تقرير مراكز التكلفة.
                        --}}
                                                {{-- يظهر بحسب ضابط «مركز التكلفة» في الإعدادات العامة للمالية --}}
                        @accountingOn('show_cost_center')
<div class="form-group col-md-4">
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
                        <div class="form-group col-md-4">
                            <label for="expense_type" class="stk-req">نوع السند</label>
                            <select name="expense_type" id="expense_type" class="form-control" required>
                                {{-- `$preselectType`: «لعميل» حين تُفتح الشاشة بـ`?client_id=` من مساحة عمل الزيارة --}}
                                <option value="general" @selected(old('expense_type', $preselectType ?? 'general') == 'general')>سند صرف عام</option>
                                <option value="client" @selected(old('expense_type', $preselectType ?? null) == 'client')>سند صرف لعميل</option>
                                @if ($canExpenseToSupplier)
                                    <option value="supplier" @selected(old('expense_type') == 'supplier')>سند صرف لمورد</option>
                                @endif
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="description">وذلك مقابل (البيان)</label>
                            <textarea class="form-control" id="description" rows="2" name="description"
                                placeholder="بيان السند / الغرض من الصرف">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="row" id="expense_type_alert_row" style="display:none">
                        <div class="form-group col-md-12">
                            <div class="alert alert-info mb-0" id="expense_type_alert">
                                <i class="feather icon-info"></i> <span id="expense_type_alert_text"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6" id="supplier_field_wrapper" style="display:none">
                            <label for="items">
                                المورد <span class="text-danger" id="supplier_required_star" style="display:none">*</span>
                            </label>
                            <select id="items" class="form-control select2" name="supplier_id">
                                <option value="" selected disabled>اختر مورداً</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->trade_name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($suppliers ?? [])"
                                route="purchases.supplier_settings.create"
                                label="مورّداً"
                                hint="الموردون يُدارون في وحدة المشتريات، ولا مورّد مسجَّل بعد." />
                        </div>

                        <div class="form-group col-md-6" id="account_field_wrapper" style="display:none">
                            <label for="account_id">
                                <span id="account_field_label">الحساب</span>
                                <span class="text-danger" id="account_required_star" style="display:none">*</span>
                            </label>
                            <select class="form-control select2" name="account_id" id="account_id">
                                <option value="">اختر الحساب</option>

                                {{-- حسابات العملاء — تظهر لنوع «سند صرف لعميل» --}}
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" data-account-kind="client" data-is-client="1"
                                        @selected(old('account_id', $preselectAccountId ?? null) == $account->id)
                                        data-branch-id="{{ $account->branch_id ?? '' }}" data-code="{{ $account->code }}"
                                        data-search="{{ trim(($account->code ? $account->code . ' ' : '') . $account->name . ' ' . ($account->client->trade_name ?? '') . ' ' . ($account->client->full_name ?? '') . ' ' . ($account->client->code ?? '')) }}">
                                        {{ $account->code ? $account->code . ' - ' : '' }}{{ $account->name }}{{ $account->client ? ' -- ' . $account->client->code : '' }}
                                    </option>
                                @endforeach

                                {{-- حسابات المصروفات الطرفية — تظهر لنوع «سند صرف عام» --}}
                                {{-- بلا «إظهار كل الحسابات في سند الصرف»: كلُّ حسابٍ يحمل تصنيفاته،
                                     والمُطابِق يُبقي حسابات التصنيف المختار وحدها --}}
                                @php
                                    $accountCategories = [];
                                    foreach (($categoryAccountMap ?? []) as $categoryId => $accountIds) {
                                        foreach ($accountIds as $linkedAccountId) {
                                            $accountCategories[$linkedAccountId][] = $categoryId;
                                        }
                                    }
                                @endphp
                                @foreach ($expenseLeafAccounts ?? [] as $exp)
                                    <option value="{{ $exp->id }}" data-account-kind="expense_leaf" data-is-client="0"
                                        data-categories="{{ implode(',', $accountCategories[$exp->id] ?? []) }}"
                                        data-code="{{ $exp->code }}"
                                        data-search="{{ trim(($exp->code ? $exp->code . ' ' : '') . $exp->name) }}">
                                        {{ $exp->code ? $exp->code . ' - ' : '' }}{{ $exp->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($expenseLeafAccounts ?? [])"
                                route="accounts_chart.view_root"
                                permission="account.chart.view"
                                :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                                label="حساب مصروف"
                                hint="الحساب يُنشأ من دليل الحسابات، ولا حساب مصروفٍ طرفيّ بعد." />

                            {{--
                                إرشادُ العملاء: يظهر متى اختِير «سند صرف لعميل»
                                ولا عميلَ في القائمة — يُظهره ويُخفيه
                                `filterAccountsByKind`، لأن الشرطَ يتبدّل مع
                                نوع السند لا مع تحميل الصفحة.
                            --}}
                            <div id="client_create_first" style="display:none">
                                <x-create-first
                                    :count="0"
                                    route="clients.create"
                                    permission="clients_add_client"
                                    label="عميلاً"
                                    hint="سندُ الصرف لعميل يحتاج عميلاً مسجَّلاً، ولا عميل بعد." />
                            </div>
                            <small class="stk-help" id="account_help_text"></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-percent"></i> الضرائب والإجمالي
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleTaxFields()">
                        <i class="feather icon-plus"></i> إضافة ضرائب
                    </button>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="total_amount">المبلغ الإجمالي بعد الضريبة</label>
                            <input type="text" class="form-control" name="total_amount" id="total_amount" readonly>
                            {{--
                                طريقةُ الضريبة تُقال صراحةً: «شاملة» تعني أن المبلغ
                                المُدخَل يحملها فيُردّ إلى وعائه، و«مضافة» تعني أنها
                                فوقه. والفرقُ يمسّ الرقم، فلا يصحّ أن يُستنتَج.
                            --}}
                            <small class="text-muted d-block mt-1" id="taxMethodHint"
                                style="display:none"></small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="unit">الوحدة</label>
                            <select id="unit" class="form-control" name="unit_id">
                                <option value="" selected disabled>حدّد الوحدة</option>
                                <option value="1">وحدة 1</option>
                                <option value="2">وحدة 2</option>
                            </select>
                        </div>
                    </div>

                    <div id="tax-fields" style="display:none">
                        <div class="stk-section__head mt-2">
                            <i class="feather icon-percent"></i>
                            <h6 class="stk-section__title">الضرائب المطبَّقة</h6>
                            <button type="button" class="btn btn-sm btn-link text-danger ms-auto"
                                onclick="removeTaxFields()">
                                <i class="feather icon-x"></i> إزالة الضرائب
                            </button>
                        </div>

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

                                @include('account::finance.partials.tax-accounts', [
                                    'index' => 1,
                                    'preferred' => 'paid',
                                    'selected' => null,
                                ])
                                <input type="text" class="form-control mt-2" placeholder="المبلغ" name="tax1_amount"
                                    id="tax1_amount" readonly>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="tax2">الضريبة الثانية</label>
                                <select id="tax2" class="form-control" name="tax2">
                                    <option value="">اختر الضريبة</option>
                                    @foreach ($taxs as $tax)
                                        <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}">{{ $tax->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format($tax->tax < 1 ? $tax->tax * 100 : $tax->tax, 2), '0'), '.') }}%)</option>
                                    @endforeach
                                </select>

                                @include('account::finance.partials.tax-accounts', [
                                    'index' => 2,
                                    'preferred' => 'paid',
                                    'selected' => null,
                                ])
                                <input type="text" class="form-control mt-2" placeholder="المبلغ" name="tax2_amount"
                                    id="tax2_amount" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-paperclip"></i> المرفقات والتكرار
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="attachments">المرفقات</label>
                            <input id="attachments" type="file" name="attachments[]" class="form-control"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" multiple>
                            <small class="stk-help">PDF أو صور أو Word أو Excel — حتى 5 ميجابايت للملف.</small>
                        </div>

                        <div class="form-group col-md-6">
                            <label>معاينة المرفقات</label>
                            <div id="attachments-preview" class="stk-choice" style="min-height:60px;max-height:150px;overflow-y:auto">
                                <small class="stk-help">لم تُختَر مرفقات بعد.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            {{--
                                `.custom-switch .custom-control-label` في تصميم القالب
                                (Vuexy) مربّعٌ صغير ثابت العرض يمثّل شكل المفتاح نفسه
                                لا غير — وليس وعاءً لنصّ. النموذج المعتمد الآن في كل
                                سندات الصرف والقبض: تسمية المفتاح فارغة (شكل المفتاح
                                وحده)، والنص عنصرٌ منفصل بجانبه — كلاهما
                                `for="checkbox"` فالنقر على النص يبدّل المفتاح أيضاً.
                                (كان هنا مكوّناً مخصّصاً `stk-switch` استُبدل توحيداً
                                مع سند القبض بعد أن ظهر هذا العطل هناك.)
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

                        <div class="form-group col-md-4" id="duplicate-options-container" style="display:none">
                            <label for="duplicate-options">دورية التكرار</label>
                            <select id="duplicate-options" class="form-control" name="recurring_frequency">
                                <option value="">حدّد التكرار</option>
                                <option value="daily">يومي</option>
                                <option value="weekly">أسبوعي</option>
                                <option value="bi-weekly">كل أسبوعين</option>
                                <option value="monthly">شهري</option>
                                <option value="yearly">سنوي</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4" id="end-date-container" style="display:none">
                            <label for="end-date">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" id="end-date" value="{{ date('Y-m-d') }}"
                                name="end_date">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('account::finance.partials.tax-math')
    <script>
        /**
         * التبديل بين أنواع السند: إظهار الحقول المناسبة وتصفير ما لا ينتمي للنوع الجديد.
         */
        function handleExpenseTypeChange(skipReset) {
            var expenseTypeEl = document.getElementById('expense_type');
            var expenseType = expenseTypeEl ? expenseTypeEl.value : 'general';
            var alertRow = document.getElementById('expense_type_alert_row');
            var alertText = document.getElementById('expense_type_alert_text');
            var supplierWrapper = document.getElementById('supplier_field_wrapper');
            var accountWrapper = document.getElementById('account_field_wrapper');
            var accountSelect = document.getElementById('account_id');
            var supplierSelect = document.getElementById('items');
            var categorySelect = document.getElementById('category');
            var supplierStar = document.getElementById('supplier_required_star');
            var accountStar = document.getElementById('account_required_star');
            var accountLabel = document.getElementById('account_field_label');
            var accountHelp = document.getElementById('account_help_text');

            alertRow.style.display = 'none';
            supplierWrapper.style.display = 'none';
            accountWrapper.style.display = 'none';
            if (accountSelect) accountSelect.removeAttribute('required');
            if (supplierSelect) supplierSelect.removeAttribute('required');
            if (supplierStar) supplierStar.style.display = 'none';
            if (accountStar) accountStar.style.display = 'none';

            if (!skipReset) {
                if (expenseType === 'general') {
                    if (supplierSelect) {
                        supplierSelect.value = '';
                        if ($.fn.select2) $(supplierSelect).trigger('change');
                    }
                    if (accountSelect) {
                        var sel = accountSelect.options[accountSelect.selectedIndex];
                        if (sel && sel.getAttribute('data-account-kind') !== 'expense_leaf') {
                            accountSelect.value = '';
                            if ($.fn.select2) $(accountSelect).trigger('change');
                        }
                    }
                } else if (expenseType === 'client') {
                    if (supplierSelect) {
                        supplierSelect.value = '';
                        if ($.fn.select2) $(supplierSelect).trigger('change');
                    }
                    if (accountSelect) {
                        var sel2 = accountSelect.options[accountSelect.selectedIndex];
                        if (sel2 && sel2.getAttribute('data-account-kind') !== 'client') {
                            accountSelect.value = '';
                            if ($.fn.select2) $(accountSelect).trigger('change');
                        }
                    }
                } else if (expenseType === 'supplier') {
                    if (accountSelect) {
                        accountSelect.value = '';
                        if ($.fn.select2) $(accountSelect).trigger('change');
                    }
                }
            }

            if (expenseType === 'client') {
                // تصنيفٌ فارغ ⇒ «التصنيف الافتراضي لسند صرف العميل» من الإعدادات؛
                // اختيارُ المستخدم لا يُمسّ
                if (categorySelect && !categorySelect.value && categorySelect.dataset.clientDefault) {
                    categorySelect.value = categorySelect.dataset.clientDefault;
                    if ($.fn.select2) $(categorySelect).trigger('change');
                }

                alertRow.style.display = 'block';
                alertText.textContent = 'سند صرف لعميل: تُعرض حسابات العملاء فقط.';
                accountWrapper.style.display = 'block';
                if (accountSelect) accountSelect.setAttribute('required', 'required');
                if (accountStar) accountStar.style.display = 'inline';
                if (accountLabel) accountLabel.textContent = 'حساب العميل';
                if (accountHelp) accountHelp.textContent = 'اختر حساب العميل المراد الصرف له.';
                filterAccountsByKind('client');
            } else if (expenseType === 'supplier') {
                alertRow.style.display = 'block';
                alertText.textContent = 'سند صرف لمورد: يجب اختيار المورد المستفيد.';
                supplierWrapper.style.display = 'block';
                if (supplierSelect) supplierSelect.setAttribute('required', 'required');
                if (supplierStar) supplierStar.style.display = 'inline';
            } else {
                alertRow.style.display = 'block';
                alertText.textContent =
                    'سند صرف عام: اختر التصنيف والحساب المراد الصرف له — ويجب أن يكون حساباً طرفياً.';
                accountWrapper.style.display = 'block';
                if (accountSelect) accountSelect.setAttribute('required', 'required');
                if (accountStar) accountStar.style.display = 'inline';
                if (accountLabel) accountLabel.textContent = 'الحساب الثاني للقيد';
                if (accountHelp) accountHelp.textContent = 'الحساب الذي يُسجَّل في الجانب المدين من القيد.';
                filterAccountsByKind('expense_leaf');
                if (window.__restrictExpenseAccounts && accountHelp) {
                    accountHelp.textContent = 'اختر التصنيف أولاً — تظهر حسابات التصنيف المختار فقط.';
                }
            }

            /*
                التصنيف إلزامي في «عام» و«لمورد»، اختياري في «لعميل».

                النجمة على التسمية كانت ثابتة لا تتبع ذلك — فتقول للمستخدم
                إن الحقل مطلوبٌ وهو ليس كذلك. صنف `stk-req` هو ما يرسمها،
                فيُضاف ويُزال معها.
            */
            if (categorySelect) {
                var categoryLabel = document.querySelector('label[for="category"]');
                var categoryRequired = expenseType !== 'client';

                if (categoryRequired) {
                    categorySelect.setAttribute('required', 'required');
                } else {
                    categorySelect.removeAttribute('required');
                }

                if (categoryLabel) {
                    categoryLabel.classList.toggle('stk-req', categoryRequired);
                }
            }
        }

        document.getElementById('expense_type').addEventListener('change', function() {
            handleExpenseTypeChange(false);
        });

        /** فلترة الحسابات بحسب نوعها: client أو expense_leaf */
        function filterAccountsByKind(kind) {
            var accountSelect = document.getElementById('account_id');
            if (!accountSelect) return;

            // يقرؤه مُطابِقُ select2 ليُقصي ما ليس من النوع المطلوب
            window.__expenseAccountKind = kind;

            // زرُّ «أضف عميلاً» يظهر متى طُلب العميل ولا عميلَ في القائمة
            var clientHint = document.getElementById('client_create_first');
            if (clientHint) {
                var hasClients = accountSelect.querySelector('option[data-account-kind="client"]') !== null;
                clientHint.style.display = (kind === 'client' && !hasClients) ? '' : 'none';
            }

            accountSelect.querySelectorAll('option').forEach(function(opt) {
                if (opt.value === '') {
                    opt.hidden = false;
                    return;
                }
                opt.hidden = opt.getAttribute('data-account-kind') !== kind;
            });

            var sel = accountSelect.querySelector('option:checked');
            if (sel && sel.hidden) {
                accountSelect.value = '';
                if ($.fn.select2) $(accountSelect).trigger('change');
            }

            if ($.fn.select2 && $(accountSelect).hasClass('select2-hidden-accessible')) {
                $(accountSelect).select2('destroy');
                initAccountSelect2();
            }
        }

        // من لا يملك «إظهار كل الحسابات في سند الصرف» يرى حسابات التصنيف المختار وحدها
        window.__restrictExpenseAccounts = @json(! empty($restrictExpenseAccounts));

        function expenseAccountInCategory(option) {
            var category = document.getElementById('category');
            var chosen = category ? category.value : '';
            if (!chosen) return false;

            return (option.getAttribute('data-categories') || '').split(',').indexOf(chosen) !== -1;
        }

        /** تغيّر التصنيف: يُفرَّغ حسابٌ ليس منه، ويُختار الحسابُ الوحيد تلقائياً */
        function syncExpenseAccountWithCategory() {
            var accountSelect = document.getElementById('account_id');
            if (!window.__restrictExpenseAccounts || !accountSelect || window.__expenseAccountKind !== 'expense_leaf') return;

            var matches = Array.prototype.filter.call(
                accountSelect.querySelectorAll('option[data-account-kind="expense_leaf"]'),
                expenseAccountInCategory
            );
            var current = accountSelect.options[accountSelect.selectedIndex];
            var keep = current && current.value !== '' && matches.indexOf(current) !== -1;

            if (!keep) {
                accountSelect.value = matches.length === 1 ? matches[0].value : '';
                if ($.fn.select2) $(accountSelect).trigger('change');
            }
        }

        $(function() {
            $('#category').on('change', syncExpenseAccountWithCategory);
        });

        function initAccountSelect2() {
            if ($('#account_id').length && $.fn.select2) {
                /*
                    الإلغاء أوّلاً.

                    القالب الرئيسي يُهيّئ كلّ `.select2` في `form-select2.js`
                    قبل أن يعمل هذا، وselect2 يتجاهل تهيئةً ثانية على عنصرٍ
                    مُهيَّأ — فيبقى المُطابِق الافتراضي ويضيع المكتوب هنا.
                */
                if ($('#account_id').hasClass('select2-hidden-accessible')) {
                    $('#account_id').select2('destroy');
                }

                $('#account_id').select2({
                    placeholder: 'اختر الحساب',
                    allowClear: true,
                    dir: 'rtl',
                    matcher: function(params, data) {
                        /*
                            **الفلترةُ بالنوع تقع هنا لا بـ`option.hidden`.**

                            select2 يرسم قائمتَه بنفسه من الخيارات ولا يقرأ
                            `hidden` — فكان اختيارُ «سند صرف لعميل» يُخفي
                            الخياراتِ في القائمة الأصلية ويُبقيها كلَّها
                            ظاهرةً في قائمة select2، فتظهر حساباتُ المصروفات
                            بين العملاء.
                        */
                        var wantKind = window.__expenseAccountKind || null;
                        if (wantKind && data.element
                            && data.element.getAttribute('data-account-kind') !== wantKind) {
                            return null;
                        }

                        // مقيَّدٌ بحسابات التصنيف: ما ليس من التصنيف المختار لا يُعرض
                        if (window.__restrictExpenseAccounts && wantKind === 'expense_leaf'
                            && data.element && data.element.value !== '' && !data.element.selected
                            && !expenseAccountInCategory(data.element)) {
                            return null;
                        }

                        if ($.trim(params.term) === '') return data;

                        /*
                            البحث على `data-search` — وفيه الكود واسم الحساب
                            واسم العميل وكوده — لا على نصّ الخيار وحده: اسم
                            العميل لا يُعرض في النصّ، فالبحث به كان لا يُرجع
                            شيئاً.
                        */
                        var needle = $.trim(params.term).toLowerCase();
                        var element = data.element;
                        var haystack = element ? (element.getAttribute('data-search') || '') : '';

                        haystack = (haystack + ' ' + (data.text || '')).toLowerCase();

                        return haystack.indexOf(needle) > -1 ? data : null;
                    }
                });
            }
        }

        function toggleTaxFields() {
            document.getElementById('tax-fields').style.display = 'block';
        }

        function removeTaxFields() {
            document.getElementById('tax-fields').style.display = 'none';
            ['tax1', 'tax2', 'tax1_amount', 'tax2_amount', 'tax1_account_id', 'tax2_account_id'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            calculateTax();
        }

        /*
            الحسابُ في `account::finance.partials.tax-math` — موضعٌ واحد
            يقابل `App\Support\TaxCalculator` على الخادم، بالطريقتين معاً:
            «مضافة» تُزاد على المبلغ، و«شاملة» تُستخرَج منه. وكان الحسابُ
            هنا يضرب في النسبة وحدها، أي يفترض «مضافة» دائماً.
        */
        function calculateTax() {
            window.VoucherTax.apply(
                document.getElementById('amount_input').value,
                document.getElementById('tax1'),
                document.getElementById('tax2'),
                'taxMethodHint'
            );
        }

        document.addEventListener('DOMContentLoaded', function() {
            handleExpenseTypeChange(true);
            initAccountSelect2();

            document.getElementById('checkbox').addEventListener('change', function() {
                var display = this.checked ? 'block' : 'none';
                document.getElementById('duplicate-options-container').style.display = display;
                document.getElementById('end-date-container').style.display = display;
            });

            document.getElementById('amount_input').addEventListener('input', calculateTax);
            document.getElementById('tax1').addEventListener('change', calculateTax);
            document.getElementById('tax2').addEventListener('change', calculateTax);

            document.getElementById('amount_input').addEventListener('blur', function() {
                this.value = (parseFloat(this.value) || 0).toFixed(2);
                calculateTax();
            });

            document.getElementById('expenses_form').addEventListener('submit', function(e) {
                e.preventDefault();

                /*
                    حارسٌ على الحدث نفسه لا على الزرّ وحده.

                    **ما كان**

                    الزرّ لا يُعطَّل إلا بعد أن يُؤكِّد المندوب في نافذة
                    Swal — فنقرتان سريعتان على «حفظ السند»، أو ضغط Enter
                    مرّتين في حقل المبلغ، كانتا تُطلقان حَدَثَي `submit`
                    قبل أن يظهر أثر التعطيل، فتُفتح نافذتا تأكيدٍ (أو يُرسل
                    الطلب مرّتين) لعمليةٍ واحدة فعلاً — وهو بالضبط ما كان
                    يُنشئ سندَي صرفٍ متطابقين.

                    **ما صار**

                    عَلَمٌ واحد يُغلق البابَ من لحظة أول حدثٍ مقبول حتى
                    ينتهي أثره تماماً — نجاحاً أو فشلاً أو إلغاءً — بصرف
                    النظر عن مصدر الحدث الثاني.
                */
                if (window.__expenseVoucherSubmitting) {
                    return;
                }

                var form = this;
                var submitBtn = document.getElementById('submitBtn');
                var amount = document.getElementById('amount_input').value;
                var description = document.getElementById('description').value;
                var categoryEl = document.getElementById('category');
                var category = categoryEl.options[categoryEl.selectedIndex].text;
                var date = document.getElementById('date').value;

                window.__expenseVoucherSubmitting = true;
                submitBtn.disabled = true;

                Swal.fire({
                    title: 'تأكيد إضافة سند الصرف',
                    html: '<div class="text-end" dir="rtl">' +
                        '<p><strong>المبلغ:</strong> ' + window.AppCurrency.format(amount) + '</p>' +
                        '<p><strong>التاريخ:</strong> ' + date + '</p>' +
                        (description ? '<p><strong>الوصف:</strong> ' + description + '</p>' : '') +
                        (category ? '<p><strong>التصنيف:</strong> ' + category + '</p>' : '') +
                        '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        // إلغاءٌ لا يُبقي الزرّ مقفلاً على أثر حدثٍ لم يكتمل
                        window.__expenseVoucherSubmitting = false;
                        submitBtn.disabled = false;
                        return;
                    }

                    submitBtn.innerHTML = '<i class="feather icon-loader"></i> جارٍ الحفظ…';

                    fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                            }
                        })
                        .then(function(response) {
                            var contentType = response.headers.get('content-type');

                            if (contentType && contentType.includes('application/json')) {
                                return response.json();
                            }

                            if (response.ok) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تمّ',
                                    text: 'أُضيف سند الصرف.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function() {
                                    window.location.href = '{{ route('expenses.index') }}';
                                });
                                return null;
                            }

                            throw new Error('استجابة غير متوقعة من الخادم');
                        })
                        .then(function(data) {
                            if (!data) return;

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تمّ',
                                    text: data.message || 'أُضيف سند الصرف.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function() {
                                    window.location.href = data.redirect || '{{ route('expenses.index') }}';
                                });
                                return;
                            }

                            var errorDetails = data.error_details || '';

                            if (!errorDetails && data.errors) {
                                errorDetails = Object.values(data.errors).map(function(messages) {
                                    return messages.join('، ');
                                }).join('\n');
                            }

                            /*
                                الخادم يرسل الرسالة مرّتين حين يكون الخطأ واحداً:
                                في `message` وفي `errors.<field>` معاً — فكانت
                                تُطبع سطرين متطابقين تحت بعضهما. تُسقَط الثانية
                                حين يكون نصّها نفسه.
                            */
                            if (errorDetails.trim() === (data.message || '').trim()) {
                                errorDetails = '';
                            }

                            /*
                                رفضُ الخادم لتكرار نقرةٍ سابقة (حارس التكرار في
                                `AccountingOperations::guardDuplicateSubmission`)
                                ليس خطأً في بياناتٍ أدخلها المندوب — فلا يُعرض
                                بأيقونة الخطأ الحمراء نفسها التي توحي بأن رقماً
                                أو حقلاً خاطئ، بل بأيقونة تهدئ لا تُنذر.

                                رسالةُ الحارس تصل داخل `errors.amount` لا في
                                `message` العام — كلّ خطأ تحقّقٍ هنا يحمل نفس
                                نصّ `message` («خطأ في البيانات المدخلة»)
                                بصرف النظر عن الحقل الذي رفضه الخادم فعلاً.
                            */
                            var isDuplicateNotice = errorDetails.indexOf('تم استلام هذا الطلب بالفعل') !== -1;

                            Swal.fire(isDuplicateNotice ? {
                                icon: 'info',
                                title: 'السند قيد الحفظ بالفعل',
                                html: '<div dir="rtl" class="text-end"><p>' + errorDetails + '</p></div>',
                                confirmButtonText: 'حسناً',
                                width: '500px'
                            } : {
                                icon: 'error',
                                title: 'خطأ في البيانات',
                                html: '<div dir="rtl" class="text-end">' +
                                    '<p><strong>' + (data.message || 'حدث خطأ أثناء حفظ البيانات') + '</strong></p>' +
                                    (errorDetails ? '<p class="text-danger mt-2">' + errorDetails + '</p>' : '') +
                                    '</div>',
                                confirmButtonText: 'موافق',
                                width: '600px'
                            });
                        })
                        .catch(function(error) {
                            console.error(error);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ في الاتصال',
                                html: '<div dir="rtl" class="text-end">' +
                                    '<p>حدث خطأ أثناء إرسال البيانات.</p>' +
                                    '<p class="text-danger mt-2">' + error.message + '</p>' +
                                    '</div>',
                                confirmButtonText: 'موافق',
                                width: '600px'
                            });
                        })
                        .finally(function() {
                            window.__expenseVoucherSubmitting = false;
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="feather icon-save"></i> حفظ السند';
                        });
                });
            });

            /** «صرف إلى» يتبع المورد ثم الحساب ثم التصنيف */
            function updateSellerField() {
                var sellerInput = document.getElementById('seller');
                var candidates = [
                    [document.getElementById('items'), 'اختر مورداً'],
                    [document.getElementById('account_id'), 'اختر الحساب'],
                    [document.getElementById('category'), 'اختر تصنيفاً']
                ];

                for (var i = 0; i < candidates.length; i++) {
                    var el = candidates[i][0];

                    if (el && el.value && el.selectedOptions[0]) {
                        var label = el.selectedOptions[0].text;

                        if (label && label !== candidates[i][1]) {
                            sellerInput.value = label;
                            return;
                        }
                    }
                }
            }

            ['items', 'account_id', 'category'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', updateSellerField);
            });

            document.getElementById('attachments').addEventListener('change', function() {
                var preview = document.getElementById('attachments-preview');
                preview.innerHTML = '';

                if (this.files.length === 0) {
                    preview.innerHTML = '<small class="stk-help">لم تُختَر مرفقات بعد.</small>';
                    return;
                }

                Array.from(this.files).forEach(function(file) {
                    var row = document.createElement('div');
                    row.className = 'stk-media mb-1';
                    row.innerHTML =
                        '<span class="stk-media__thumb"><i class="feather icon-' + fileIcon(file.type) + '"></i></span>' +
                        '<span class="stk-media__body">' +
                        '<span class="stk-media__title">' + file.name + '</span>' +
                        '<span class="stk-media__sub">' + (file.size / 1024 / 1024).toFixed(2) + ' م.ب</span>' +
                        '</span>';
                    preview.appendChild(row);
                });
            });

            function fileIcon(mimeType) {
                if (mimeType.includes('image')) return 'image';
                if (mimeType.includes('pdf')) return 'file-text';
                if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'grid';
                return 'file';
            }
        });
    </script>
@endsection
