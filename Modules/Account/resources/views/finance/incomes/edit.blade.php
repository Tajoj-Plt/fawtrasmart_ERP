@extends('account::master')

@section('title')
    تعديل سند قبض
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('incomes.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="incomes_form" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ التغييرات
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'تعديل سند قبض',
        'icon' => 'download',
        'sub' => 'سند رقم ' . $income->code,
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'سندات القبض' => route('incomes.index'),
            'تعديل' => null,
        ],
    ])

    <div class="content-body">
        {{--
            **ما كان**

            صفوفٌ متجاورة بلا أقسام، وفيها ثلاثة حقولٍ لا وجود لها أصلاً:
            «الوحدة» و«البائع» و«المورد» — قوائمُ خياراتها مكتوبةٌ في القالب
            (`وحدة 1`، `بائع 2`) وجدول `receipts` لا يحمل أعمدةً لها ولا
            يقرؤها المتحكّم. وحقلان مختلفان يحملان الاسم `vendor_id` نفسه،
            فيدهس أحدهما الآخر عند الإرسال. والضريبتان قائمتان مكتوبتان
            (`ضريبة 1`) بينما شاشة الإضافة تقرأ الضرائب المعرَّفة فعلاً.

            **ما صار**

            البنية نفسها التي في شاشة الإضافة — أربع بطاقاتٍ معنونة —
            والحقول التي لا تُحفَظ حُذفت، والضرائب من جدولها.
        --}}
        <form id="incomes_form" action="{{ route('incomes.update', $income->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <input type="hidden" name="created_by" value="{{ auth()->id() }}">

            {{--
                «القابض» يُعاد كما هو.

                المتحكّم يكتب `$income->seller = $request->input('seller')`،
                والنموذج لم يحمل حقلاً بهذا الاسم قطّ — فكلّ تعديلٍ كان يمسح
                القيمة المسجَّلة.
            --}}
            <input type="hidden" name="seller" value="{{ old('seller', $income->seller) }}">

            @php
                $userBranchId = auth()->user()->branch_id ?? null;
                $currentBranchId = old('branch_id', $income->branch_id ?? $userBranchId);
                $isAdmin = ! auth()->user()->isEmployee();
                $hasTax = old('tax1', $income->tax1) || old('tax2', $income->tax2);
                $isRecurring = old('is_recurring', $income->is_recurring) == 1;
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
                            <label for="amount" class="stk-req">المبلغ</label>
                            {{--
                                **خانةُ المبلغ تعني ما أدخله المستخدمُ أوّلاً.**

                                و`amount` المخزَّن هو الوعاءُ قبل الضريبة دائماً. فسندٌ
                                ضريبتُه «شاملة» أُدخل بإجماليه ثم رُدّ إلى وعائه عند
                                الحفظ؛ فإن أُعيد فتحُه بوعائه استُخرجت منه الضريبةُ
                                مرّةً ثانية، وانكمش المبلغُ في كلّ تعديل.

                                فالشاملُ يُفتح بإجماليه، والمضافُ بوعائه — وكلاهما
                                يعود من الحفظ إلى ما كان.
                            --}}
                            <input type="text" class="form-control form-control-lg stk-num" id="amount" name="amount"
                                value="{{ old('amount', \App\Support\TaxCalculator::isInclusive($income->tax1, $income->tax2) ? ($income->total_amount ?: $income->amount) : $income->amount) }}"
                                placeholder="0.00" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="total_amount">الإجمالي بعد الضريبة</label>
                            <input type="text" class="form-control form-control-lg stk-num" id="total_amount" readonly>
                            {{--
                                طريقةُ الضريبة تُقال صراحةً: «شاملة» تعني أن المبلغ
                                المُدخَل يحملها فيُردّ إلى وعائه، و«مضافة» تعني أنها
                                فوقه. والفرقُ يمسّ الرقم، فلا يصحّ أن يُستنتَج.
                            --}}
                            <small class="text-muted d-block mt-1" id="taxMethodHint"
                                style="display:none"></small>
                            <small class="stk-help">يُحسب تلقائياً من المبلغ والضرائب</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="code-number">رقم السند</label>
                            <input type="text" class="form-control stk-num" id="code-number" name="code"
                                value="{{ old('code', $income->code) }}" readonly>
                            <small class="stk-help">الرقم ثابت بعد الحفظ</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="date" class="stk-req">التاريخ</label>
                            <input type="date" class="form-control" id="date" name="date"
                                value="{{ old('date', $income->date) }}" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="category" class="stk-req">التصنيف</label>
                            <select id="category" class="form-control select2" name="incomes_category_id" required>
                                <option value="">-- اختر تصنيفاً --</option>
                                @foreach ($incomes_categories as $incomes_category)
                                    <option value="{{ $incomes_category->id }}"
                                        @selected(old('incomes_category_id', $income->incomes_category_id) == $incomes_category->id)>
                                        {{ $incomes_category->name }}
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
                                'selected' => old('payment_method_id', $income->payment_method_id),
                            ])
                            <small class="stk-help">من شاشة «طرق الدفع» في الإعدادات</small>
                        </div>

                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea class="form-control" id="description" rows="2" name="description">{{ old('description', $income->description) }}</textarea>
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
                    <span class="stk-help">تعديل الطرفين يُعيد كتابة القيد المرتبط بالسند</span>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="account_id" class="stk-req">الحساب المقابل</label>
                            {{-- مجموعتان كما في شاشة الإضافة --}}
                            <select id="account_id" class="form-control select2" name="account_id" required>
                                <option value="">اختر الحساب أو ابحث بالكود</option>

                                @if (count($accounts ?? []))
                                    <optgroup label="حسابات العملاء">
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" data-code="{{ $account->code }}"
                                                data-search="{{ trim(($account->code ? $account->code . ' ' : '') . $account->name . ' ' . ($account->client->trade_name ?? '') . ' ' . ($account->client->full_name ?? '') . ' ' . ($account->client->code ?? '')) }}"
                                                @selected(old('account_id', $income->account_id) == $account->id)>
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
                                                @selected(old('account_id', $income->account_id) == $account->id)>
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
                            {{--
                                كان الموظف مقفلاً على خزينته المعيَّنة له وحدها
                                بلا خيار — وحتى بعد اختيارٍ افتراضي كهذا، المتحكّم
                                لم يكن يكتب الخزينة الجديدة أصلاً عند الحفظ
                                (IncomesController::update). صار بإمكانه اختيار
                                خزينته هو أو خزينة أيّ موظفٍ يديره — `$treasuries`
                                مبنيّةٌ في المتحكّم على هذا النطاق بالضبط
                                (ScopesToEmployeeHierarchy).
                            --}}
                            <select id="treasury_id" class="form-control select2" name="treasury_id" required>
                                <option value="">-- اختر الخزينة --</option>
                                @foreach ($treasuries ?? [] as $treasury)
                                    <option value="{{ $treasury->id }}"
                                        @selected(old('treasury_id', $income->treasury_id ?? ($MainTreasury->id ?? '')) == $treasury->id)>
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

                        {{--
                            مركز التكلفة اختياري: لا يُخزَّن في السند بل يُكتب
                            في السطر الدائن من قيده — هناك يقرؤه تقرير مراكز
                            التكلفة. وإفراغه يمسحه من القيد.
                        --}}
                                                {{-- يظهر بحسب ضابط «مركز التكلفة» في الإعدادات العامة للمالية --}}
                        @accountingOn('show_cost_center')
<div class="form-group col-md-6">
                            <label for="cost_center_id">مركز التكلفة</label>
                            <select id="cost_center_id" class="form-control select2" name="cost_center_id">
                                <option value="">بلا مركز</option>
                                @foreach (\App\Support\AccountingPeriodOptions::costCenters() as $center)
                                    <option value="{{ $center->id }}"
                                        @selected(old('cost_center_id', $currentCostCenterId ?? null) == $center->id)>
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
                                        <option value="{{ $branch->id }}" @selected($currentBranchId == $branch->id)>
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
                    <button type="button" class="btn btn-sm btn-outline-primary" id="toggleTaxBtn"
                        @if ($hasTax) style="display:none" @endif>
                        <i class="feather icon-plus"></i> إضافة ضرائب
                    </button>
                </div>

                <div class="card-body">
                    <div id="tax-fields" @unless ($hasTax) style="display: none;" @endunless>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="tax1">الضريبة الأولى</label>
                                <select id="tax1" class="form-control" name="tax1">
                                    <option value="">اختر الضريبة</option>
                                    @foreach ($taxs as $tax)
                                        <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}" @selected((int) old('tax1', $income->tax1) === (int) $tax->id)>
                                            {{ $tax->name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($taxs ?? [])"
                                    route="TaxSitting.index"
                                    label="إعداد ضريبة"
                                    hint="الضرائب تُعرَّف في إعدادات الضرائب، ولا ضريبة معرَّفة بعد." />
                                <input type="text" class="form-control stk-num mt-1" placeholder="المبلغ"
                                    name="tax1_amount" id="tax1_amount"
                                    value="{{ old('tax1_amount', $income->tax1_amount) }}" readonly>

                                @include('account::finance.partials.tax-accounts', [
                                    'index' => 1,
                                    'preferred' => 'payable',
                                    'selected' => old('tax1_account_id', $income->tax1_account_id),
                                ])
                            </div>

                            <div class="form-group col-md-6">
                                <label for="tax2">الضريبة الثانية</label>
                                <select id="tax2" class="form-control" name="tax2">
                                    <option value="">اختر الضريبة</option>
                                    @foreach ($taxs as $tax)
                                        <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}" data-type="{{ $tax->type }}" @selected((int) old('tax2', $income->tax2) === (int) $tax->id)>
                                            {{ $tax->name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="text" class="form-control stk-num mt-1" placeholder="المبلغ"
                                    name="tax2_amount" id="tax2_amount"
                                    value="{{ old('tax2_amount', $income->tax2_amount) }}" readonly>

                                @include('account::finance.partials.tax-accounts', [
                                    'index' => 2,
                                    'preferred' => 'payable',
                                    'selected' => old('tax2_account_id', $income->tax2_account_id),
                                ])
                            </div>

                            <div class="col-12">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="removeTaxBtn">
                                    <i class="feather icon-x"></i> إزالة الضرائب
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="stk-def mb-0" id="taxEmptyHint" @if ($hasTax) style="display:none" @endif>
                        لا ضرائب على هذا السند.
                    </p>
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

                        أربعة حقول (المرفق، سند مكرَّر، التكرار، تاريخ الانتهاء)
                        في صفٍّ واحد بعرض `col-md-4` لكلٍّ منها — أي ١٦ وحدة من
                        أصل ١٢. فيسع الصفُّ ثلاثةً فقط، ويفيض الرابع (تاريخ
                        الانتهاء) إلى صفٍّ جديد وحده، تاركاً ثُلثي عرض الشاشة
                        فارغاً بجانبه.

                        **ما صار**

                        صفٌّ للمرفق (`col-md-6` + المرفق الحالي)، وصفٌّ مستقلٌّ
                        لإعدادات التكرار الثلاثة (`col-md-4` × ٣ = ١٢ بالضبط
                        متى ظهرت معاً) — على نسق سند الصرف تماماً.
                    --}}
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="attachments">مرفق جديد</label>
                            <input id="attachments" type="file" name="attachments" class="form-control"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="stk-help">
                                PDF أو صورة — بحدّ أقصى ٢ ميجابايت.
                                @if ($income->attachments)
                                    اختيار ملفٍّ جديد يستبدل الحالي.
                                @endif
                            </small>
                        </div>

                        <div class="form-group col-md-6">
                            <label>المرفق الحالي</label>
                            <div class="stk-choice" style="min-height:60px;max-height:150px;overflow-y:auto">
                                @if ($income->attachments)
                                    <div class="stk-media mb-1">
                                        <span class="stk-media__thumb"><i class="feather icon-file"></i></span>
                                        <span class="stk-media__body">
                                            <a class="stk-media__title"
                                                href="{{ asset('assets/uploads/incomes/'.$income->attachments) }}"
                                                target="_blank">
                                                {{ $income->attachments }}
                                            </a>
                                        </span>
                                    </div>
                                @else
                                    <small class="stk-help">لا مرفق على هذا السند.</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            {{--
                                `.custom-switch .custom-control-label` في تصميم Vuexy
                                مربّعٌ صغير ثابت العرض (`3.1rem`، `padding:0`) يمثّل
                                شكل المفتاح نفسه لا غير — وليس وعاءً لنصّ. وضعُ
                                العنوان والشرح داخله يحشرهما في ذلك المربّع الصغير
                                فيتقطّع النص ويتراكب مع شكل المفتاح.

                                الحل: تسمية المفتاح تبقى فارغة (شكل المفتاح وحده)،
                                والنص عنصرٌ منفصل بجانبه — كلاهما `for="checkbox"`
                                فالنقر على النص يبدّل المفتاح أيضاً.
                            --}}
                            <div class="d-flex align-items-center" style="gap: .6rem;">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="checkbox" name="is_recurring" @checked($isRecurring)>
                                    <label class="custom-control-label" for="checkbox"></label>
                                </div>
                                <label for="checkbox" class="mb-0" style="cursor: pointer;">
                                    <span style="display: block; font-weight: 600;">سند مكرَّر</span>
                                    <small class="text-muted" style="display: block;">يُعاد إنشاؤه تلقائياً على فترات.</small>
                                </label>
                            </div>
                        </div>

                        <div class="form-group col-md-4" id="duplicate-options-container"
                            @unless ($isRecurring) style="display: none;" @endunless>
                            <label for="duplicate-options">التكرار</label>
                            <select id="duplicate-options" class="form-control" name="recurring_frequency">
                                <option value="">حدّد التكرار</option>
                                @foreach (['daily' => 'يومي', 'weekly' => 'أسبوعي', 'bi-weekly' => 'كل أسبوعين', 'monthly' => 'شهري', 'yearly' => 'سنوي'] as $value => $labelText)
                                    <option value="{{ $value }}"
                                        @selected(old('recurring_frequency', $income->recurring_frequency) === $value)>
                                        {{ $labelText }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-4" id="end-date-container"
                            @unless ($isRecurring) style="display: none;" @endunless>
                            <label for="end-date">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" id="end-date"
                                value="{{ old('end_date', $income->end_date) }}" name="end_date">
                        </div>
                    </div>
                </div>
            </div>

            <div class="stk-form-foot">
                <a href="{{ route('incomes.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{--
        jQuery يحمّله القالب الرئيسي.

        كانت هذه الشاشة تجلب `jquery-3.6.0` من CDN بعده، فيستبدل الكائن
        الجديد `window.$` ويمحو ما سُجِّل عليه من إضافات — ومنها `select2`،
        فيظهر الحساب قائمةً عاديةً بلا بحث.
    --}}
    @include('account::finance.partials.tax-math')
    <script>
        (function () {
            'use strict';

            var amountInput = document.getElementById('amount');
            var tax1 = document.getElementById('tax1');
            var tax2 = document.getElementById('tax2');
            var taxFields = document.getElementById('tax-fields');
            var taxHint = document.getElementById('taxEmptyHint');
            var toggleTaxBtn = document.getElementById('toggleTaxBtn');

            /** المبلغ الإجمالي = المبلغ + الضريبتين */
            /** نسبةُ الضريبة من الخيار المختار — القيمةُ معرّفٌ لا نسبة. */
            function taxRateOf(selectEl) {
                if (!selectEl) return 0;
                var opt = selectEl.options[selectEl.selectedIndex];
                return opt ? (parseFloat(opt.getAttribute('data-rate')) || 0) : 0;
            }

            /*
                الحسابُ في `account::finance.partials.tax-math` — موضعٌ واحد
                يقابل `App\Support\TaxCalculator` على الخادم، بالطريقتين
                معاً: «مضافة» تُزاد على المبلغ، و«شاملة» تُستخرَج منه. وكان
                الحسابُ هنا يضرب في النسبة وحدها، أي يفترض «مضافة» دائماً.
            */
            function calculateTax() {
                window.VoucherTax.apply(amountInput.value, tax1, tax2, 'taxMethodHint');
            }

            toggleTaxBtn.addEventListener('click', function () {
                taxFields.style.display = 'block';
                taxHint.style.display = 'none';
                toggleTaxBtn.style.display = 'none';
            });

            document.getElementById('removeTaxBtn').addEventListener('click', function () {
                taxFields.style.display = 'none';
                taxHint.style.display = '';
                toggleTaxBtn.style.display = '';
                tax1.value = '';
                tax2.value = '';
                // وحسابا الترحيل: إبقاؤهما يترك القيد يشير إلى ضريبةٍ أُزيلت
                document.getElementById('tax1_account_id').value = '';
                document.getElementById('tax2_account_id').value = '';
                calculateTax();
            });

            amountInput.addEventListener('input', calculateTax);
            tax1.addEventListener('change', calculateTax);
            tax2.addEventListener('change', calculateTax);

            amountInput.addEventListener('blur', function () {
                this.value = (parseFloat(this.value) || 0).toFixed(2);
                calculateTax();
            });

            // الإجمالي يُعرض محسوباً عند فتح الشاشة لا فارغاً
            calculateTax();

            document.getElementById('checkbox').addEventListener('change', function () {
                var display = this.checked ? 'block' : 'none';
                document.getElementById('duplicate-options-container').style.display = display;
                document.getElementById('end-date-container').style.display = display;
            });

            /*
                بحثٌ بالاسم وبالكود في قائمة الحسابات — البحث الافتراضي يقارن
                نصّ الخيار وحده، والمحاسب يكتب كود الحساب لا اسمه.
            */
            if (window.jQuery && jQuery.fn.select2) {
                var accountSelect = jQuery('#account_id');

                /*
                    التهيئة تُلغي ما سبقها.

                    القالب الرئيسي يُهيّئ كلّ `.select2` في `form-select2.js`
                    قبل أن يصل هذا السطر، وselect2 يتجاهل تهيئةً ثانية على
                    عنصرٍ مُهيَّأ — فيبقى المُطابِق الافتراضي ويضيع المُطابِق
                    المكتوب هنا. الإلغاء أوّلاً يجعل هذه التهيئة هي العاملة.
                */
                if (accountSelect.hasClass('select2-hidden-accessible')) {
                    accountSelect.select2('destroy');
                }

                accountSelect.select2({
                    dir: 'rtl',
                    width: '100%',
                    placeholder: 'ابحث بالكود أو باسم الحساب أو باسم العميل',
                    allowClear: true,
                    matcher: function (params, data) {
                        if (jQuery.trim(params.term) === '') {
                            return data;
                        }

                        // المجموعات تُطابَق بأبنائها لا بعنوانها
                        if (data.children && data.children.length) {
                            var matched = [];

                            for (var i = 0; i < data.children.length; i++) {
                                if (matchesTerm(params.term, data.children[i])) {
                                    matched.push(data.children[i]);
                                }
                            }

                            if (! matched.length) {
                                return null;
                            }

                            var group = jQuery.extend({}, data);
                            group.children = matched;

                            return group;
                        }

                        return matchesTerm(params.term, data) ? data : null;
                    }
                });
            }

            /**
             * هل يوافق الخيارُ ما كُتب؟
             *
             * البحث يقع على `data-search` — وفيه الكود واسم الحساب واسم
             * العميل وكوده — لا على نصّ الخيار وحده، فاسم العميل لا يُعرض
             * في النصّ أصلاً وكان البحث به لا يُرجع شيئاً.
             */
            function matchesTerm(term, option) {
                var needle = jQuery.trim(term).toLowerCase();
                var element = option.element;
                var haystack = element ? (element.getAttribute('data-search') || '') : '';

                haystack = (haystack + ' ' + (option.text || '')).toLowerCase();

                return haystack.indexOf(needle) > -1;
            }
        })();
    </script>
@endsection
