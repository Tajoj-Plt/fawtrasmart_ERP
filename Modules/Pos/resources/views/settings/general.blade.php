{{--
    الإعدادات العامة لنقاط البيع.

    ═══════════════ ما كان في التخطيط ═══════════════

    سبعُ بطاقاتٍ مرصوصةٌ رأسياً، في كلٍّ منها حقلٌ إلى خمسة — فالصفحةُ تطول
    ويُهدر عرضُها، والوصولُ إلى «الضوابط» يحتاج تمريرَ أربع شاشات.

    وتحت كلِّ حقلٍ سطرُ شرحٍ من سطرين، فالحقولُ تختنق بينها ويصير النصُّ
    أكثرَ من المُدخَلات — والعينُ تتوقّف عن قراءته أصلاً حين يكون بهذه الكثرة.

    ═══════════════ وما صار ═══════════════

    **أربعةُ تبويبات** بحسب السؤال الذي يُجيبه كلٌّ منها: ما يُباع · كيف
    يُحصَّل · أين يُقيَّد · ما يُسمح به. فكلُّ شاشةٍ تُقرأ بلا تمرير.

    **والشرحُ رُتّب في ثلاث مراتب:**

      • ما **يغيّر قراراً** يبقى تحت الحقل مختصَراً في سطر.
      • ما هو **تفصيلٌ للفهم** انتقل إلى `title` على أيقونة استفهام — يُقرأ
        عند الحاجة ولا يزاحم الحقل.
      • وما هو **تحذيرٌ لا يُفوَّت** يبقى تنبيهاً كامل العرض.

    ═══════════════ وخطرُ التبويب ═══════════════

    التبويبُ يُخفي أخطاءَ التحقّق: يُرفض الحفظُ لخطأٍ في تبويبٍ مغلق فيبدو
    الزرُّ معطَّلاً، فيعيد المستخدم الضغطَ ثم يغادر. فالسكربتُ في الذيل يفتح
    التبويبَ الذي فيه أوّلُ خطأ ويُمرّر إليه.

    ونطاقُ الفرع **فوق التبويبات لا داخلها**: هو يحدّد أيَّ إعداداتٍ تُحرَّر،
    فإخفاؤه في تبويبٍ يجعل المستخدم يحرّر فرعاً لا يراه.
--}}
@extends('pos::master')

@section('title', 'الإعدادات العامة لنقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Support\PlanLock;

    $locked = PlanLock::locked(PosPlanFeatures::SETTINGS);

    /** أيقونةُ شرحٍ تُقرأ عند التمرير — بدل سطرٍ دائمٍ تحت الحقل (`stk-tip` في نظام التصميم). */
    $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        @unless ($locked)
            <button type="submit" form="posSettingsForm" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ الإعدادات
            </button>
        @endunless
    @endsection

    @include('pos::components.page-head', [
        'title' => 'الإعدادات العامة',
        'icon' => 'settings',
        'sub' => 'ما يُباع، وكيف يُحصَّل، وأين يُقيَّد، وما يُسمح به',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'عام' => null,
        ],
    ])

    @if ($locked)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>ضبطُ إعدادات نقاط البيع خارج باقتك الحالية.</strong>
            الإعداداتُ الحالية معروضةٌ للقراءة، ولا يُقبل حفظُ تغيير.
            <a href="{{ PlanLock::url() }}" class="alert-link">عرض الاشتراك والترقية</a>
        </div>
    @endif

    <form action="{{ route('pos.settings.store') }}" method="POST" id="posSettingsForm">
        @csrf

        {{--
            نطاقُ الفرع فوق التبويبات: يحدّد **أيَّ إعداداتٍ** تُحرَّر، فلا
            يصحّ إخفاؤه في تبويب. وسطرٌ واحدٌ يكفيه — لا بطاقةٌ كاملة.
        --}}
        @if ($canSelectBranch && $branches->count() > 1)
            <div class="card">
                <div class="card-body py-1">
                    <div class="row align-items-end">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group mb-0">
                                <label for="branch_id" class="mb-25">
                                    <i class="feather icon-git-branch"></i> نطاق الإعدادات
                                </label>
                                <select id="branch_id" name="branch_id" class="form-control select2"
                                        onchange="location.href='{{ route('pos.settings.general') }}?branch_id=' + this.value">
                                    <option value="">الإعداد العام (كل الفروع)</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-8 col-sm-6">
                            <p class="stk-help mb-0 pb-50">
                                فرعٌ بلا إعدادٍ خاصّ يرث الإعدادَ العام، وتبديلُ الاختيار يُعيد
                                تحميلَ الشاشة على إعداداته.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
        @endif

        {{--
            ═══════════ ما يسري وما لا يسري ═══════════

            صفُّ الفرع يتقدّم على العام **كاملاً** لا حقلاً بحقل. فمن يفتح
            «الإعداد العام» ويختار مستودعاً ويحفظ يراه محفوظاً — ولا يبلغ
            فرعاً له صفُّه الخاصّ. وشاشةُ البيع تعرض حينها كمّياتِ مخزنٍ
            آخر بلا ما يدلّ على السبب.

            فالبيانُ هنا، عند الحفظ، لا بعد أن تُقرأ كمّيةٌ خاطئة.
        --}}
        @if ($branchId === null && $branchOverrides->isNotEmpty())
            <div class="alert alert-warning">
                <div class="mb-50">
                    <i class="feather icon-alert-triangle"></i>
                    <strong>هذه الإعداداتُ لا تسري على الفروع التالية</strong> — لكلٍّ منها
                    صفُّه الخاصّ الذي يتقدّم عليها كاملاً:
                </div>
                <ul class="mb-50 ps-3 pe-3">
                    @foreach ($branchOverrides as $override)
                        <li>
                            <a href="{{ route('pos.settings.general') }}?branch_id={{ $override->branch_id }}"
                               class="alert-link">{{ $override->branch_name }}</a>
                            — مستودعُه المطبَّق:
                            <strong>{{ $override->store_house_name ?: 'لم يُحدَّد' }}</strong>
                        </li>
                    @endforeach
                </ul>
                <div class="stk-help mb-0">
                    لتغيير مستودع أحد هذه الفروع افتح إعداداتِه من الرابط أعلاه. وما يُحفظ
                    هنا يسري على الفروع التي **لا** صفَّ لها وحدها.
                </div>
            </div>
        @endif

        @if ($branchId !== null && ! $settingsAreOwn)
            <div class="alert alert-info">
                <i class="feather icon-info"></i>
                هذا الفرعُ لا صفَّ إعداداتٍ له بعد، والمعروضُ هو <strong>الإعدادُ العام</strong>
                الذي يرثه. وأولُ حفظٍ هنا يُنشئ له صفّاً خاصّاً يتقدّم على العام من بعدُ.
            </div>
        @endif

        <div class="card">
            <div class="card-body">

                <ul class="nav nav-tabs stk-tabs mb-2" role="tablist">
                    @foreach ([
                        ['sell', 'shopping-cart', 'البيع'],
                        ['print', 'printer', 'المطبوعات'],
                        ['payment', 'credit-card', 'الدفع والتسوية'],
                        ['accounts', 'book', 'الحسابات'],
                        ['controls', 'shield', 'الضوابط'],
                    ] as $i => [$id, $icon, $label])
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $i === 0 ? 'active' : '' }}" id="tab-{{ $id }}"
                                    data-bs-toggle="tab" data-bs-target="#pane-{{ $id }}"
                                    type="button" role="tab" aria-controls="pane-{{ $id }}"
                                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                                <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">

                    {{-- ═══════════ ما يُباع ═══════════ --}}
                    <div class="tab-pane fade show active" id="pane-sell" role="tabpanel" aria-labelledby="tab-sell">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="default_customer_id">
                                        العميل الافتراضي
                                        {!! $tip('العملاءُ المرتبطون بحسابٍ محاسبي فقط — غيرُهم يكسر قيدَ الفاتورة.') !!}
                                    </label>
                                    <select id="default_customer_id" name="default_customer_id" class="form-control select2 @error('default_customer_id') is-invalid @enderror">
                                        <option value="">بلا عميل افتراضي</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}"
                                                @selected(old('default_customer_id', $posSettings->default_customer_id ?? '') == $customer->id)>
                                                {{ $customer->trade_name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-create-first :count="$customers->count()" route="clients.create" label="عميل"
                                        hint="لا عملاء مرتبطين بحسابٍ محاسبي بعد." />
                                </div>
                            </div>


                            {{--
                                ═══════════ مستودعُ نقطة البيع ═══════════

                                كان يُشتقّ من «مخزون المندوب»: المستودعُ
                                المربوطُ بالموظّف في شاشة الموظفين. فكاشيرٌ
                                بلا ربطٍ لا يبيع، وكاشيران بمستودعين يقسمان
                                مخزونَ الفرع بينهما — وحسابُ المخزون في
                                القيد يتغيّر بتغيّر من وقف على الجهاز.

                                وصار اختياراً واحداً للفرع: منه تُقرأ
                                الكمّياتُ المعروضة، وعليه يُخصم المباع،
                                وحسابُه هو الطرفُ الدائن في قيد التكلفة.
                            --}}
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="store_house_id" class="stk-req">
                                        مستودع نقطة البيع
                                        {!! $tip('منه تُقرأ الكمّياتُ المعروضة وعليه يُخصم المباع، وحسابُه المحاسبي هو الطرفُ الدائن في قيد تكلفة المبيعات. بلا اختيارٍ هنا لا تُفتح شاشةُ البيع.') !!}
                                    </label>
                                    <select id="store_house_id" name="store_house_id"
                                            class="form-control select2 @error('store_house_id') is-invalid @enderror">
                                        <option value="">— اختر مستودعاً —</option>
                                        @foreach ($storeHouses as $storeHouse)
                                            <option value="{{ $storeHouse->id }}"
                                                @selected(old('store_house_id', $posSettings->store_house_id ?? '') == $storeHouse->id)>
                                                {{ $storeHouse->name }}
                                                @if ($storeHouse->inventory_account)
                                                    — {{ $storeHouse->inventory_account }}
                                                @else
                                                    — ⚠ بلا حساب مخزون
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('store_house_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror

                                    <x-create-first :count="$storeHouses->count()" route="storehouse.create" label="مستودع"
                                        hint="لا مستودعات مسجَّلة — ولا بيعَ من نقطة البيع بلا مستودع." />
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label>
                                        عملة نقطة البيع
                                        {!! $tip('تُضبط من شاشة «العملات» في إعدادات النظام، وتُختم في كل فاتورة وجلسة.') !!}
                                    </label>
                                    <input type="text" class="form-control" readonly value="{{ $defaultCurrency ?: '—' }}">
                                </div>
                            </div>
                        </div>

                        <div class="stk-section__head mt-2">
                            <i class="feather icon-grid"></i>
                            <h6 class="stk-section__title">التصنيفات المعروضة</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="allowed_categories_type" class="stk-req">النطاق</label>
                                    <select id="allowed_categories_type" name="allowed_categories_type"
                                            class="form-control @error('allowed_categories_type') is-invalid @enderror" required>
                                        @foreach (['all' => 'كل التصنيفات', 'only' => 'المحدَّدة فقط', 'except' => 'كلُّها ما عدا المحدَّدة'] as $val => $label)
                                            <option value="{{ $val }}"
                                                @selected(old('allowed_categories_type', $posSettings->allowed_categories_type ?? 'all') === $val)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-8 col-sm-6" id="categories_selection_wrapper">
                                <div class="form-group">
                                    <label for="allowed_categories_ids">التصنيفات</label>
                                    <select id="allowed_categories_ids" name="allowed_categories_ids[]"
                                            class="form-control select2 @error('allowed_categories_ids') is-invalid @enderror" multiple>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected(in_array($category->id, old('allowed_categories_ids', $posSettings->allowed_categories_ids ?? [])))>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-create-first :count="$categories->count()" route="category.create"
                                        permission="stock.categories.create"
                                        :plan="\App\Constants\planFeatures\StockPlanFeatures::CATEGORIES"
                                        label="تصنيف"
                                        hint="لا تصنيفات مسجَّلة — اترك النطاق «كل التصنيفات»." />
                                </div>
                            </div>
                        </div>

                        <div class="stk-section__head mt-2">
                            <i class="feather icon-monitor"></i>
                            <h6 class="stk-section__title">شكل شاشة البيع</h6>
                        </div>

                        <div class="row">
                            @foreach ([
                                ['enable_departments', 'أزرار الأقسام', 'أقسامُ الأصناف أزراراً فوق قائمة المنتجات', true],
                                ['show_product_images', 'صور المنتجات', 'أسرعُ في الاختيار، وأبطأُ في الشبكات الضعيفة', true],
                            ] as [$name, $label, $hint, $default])
                                <div class="col-md-4 col-sm-6 mb-1">
                                    <label class="stk-switch" for="{{ $name }}">
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">{{ $label }}</span>
                                            <span class="stk-switch__hint">{{ $hint }}</span>
                                        </span>
                                        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1"
                                               @checked(old($name, $posSettings->{$name} ?? $default))>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ═══════════ كيف يُحصَّل ═══════════ --}}
                    {{-- ═══════════ ما يُطبَع وكيف يبدو ═══════════ --}}
                    <div class="tab-pane fade" id="pane-print" role="tabpanel" aria-labelledby="tab-print">

                        {{--
                            ═══════════ قوالبُ الطباعة ═══════════

                            كان هنا حقلٌ واحد بخيارين — «حرارية» و«إلكترونية» —
                            وهما وصفَا شكلٍ لا قالبان، **ولا يقرؤهما سطرٌ واحد
                            في الطباعة**: الشاشةُ تعرض قالبَ Blade مدمجاً مهما
                            كانت القيمة.

                            وفي «الإعدادات ← قوالب المستندات» مئتا قالبٍ
                            مصمَّم. فصار الاختيارُ منها، ولكلِّ مستندٍ قائمتُه:
                            الفاتورةُ نوعُها `simplified_invoice` والمرتجعُ
                            `sales_return`.
                        --}}
                        <div class="stk-section__head">
                            <h6 class="stk-section__title">
                                <i class="feather icon-file-text"></i> قوالب الطباعة
                            </h6>

                            <a href="{{ route('document-templates.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="feather icon-edit-2"></i> تصميم القوالب
                            </a>
                        </div>

                        <div class="row">
                            @foreach ([
                                ['invoice_template', 'قالب فاتورة الكاشير', $invoiceTemplates,
                                    'يُطبع عند إتمام البيع وعند إعادة الطباعة.'],
                                ['return_template', 'قالب المرتجع', $returnTemplates,
                                    'يُطبع عند إنشاء مرتجع وعند طباعته لاحقاً.'],
                            ] as [$field, $label, $options, $hint])
                                @php
                                    $current = (string) old($field, $posSettings->{$field} ?? 'default');
                                @endphp

                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group">
                                        <label for="{{ $field }}">{{ $label }}</label>

                                        <div class="tpl-pick">
                                            <select id="{{ $field }}" name="{{ $field }}"
                                                    class="form-control tpl-pick__select @error($field) is-invalid @enderror">
                                                <option value="default" @selected($current === 'default')>
                                                    الافتراضي المعيَّن في شاشة القوالب
                                                </option>

                                                @foreach ($options as $tpl)
                                                    <option value="{{ $tpl->id }}" @selected($current === (string) $tpl->id)>
                                                        {{ $tpl->name }}@if ($tpl->is_default) — (الافتراضي)@endif
                                                    </option>
                                                @endforeach

                                                {{--
                                                    قالبُ النظام المدمج يبقى خياراً صريحاً: منشأةٌ
                                                    تريد الإيصالَ الحراريَّ القديم لا يجوز أن تُجبَر
                                                    على قالبٍ مصمَّم.
                                                --}}
                                                <option value="builtin" @selected($current === 'builtin')>
                                                    قالب النظام المدمج
                                                </option>
                                            </select>

                                            {{--
                                                المعاينةُ بجانب الاختيار لا في شاشةٍ أخرى: من
                                                يختار قالباً من عشرةٍ بأسمائها وحدها يختار
                                                بالتخمين.
                                            --}}
                                            <button type="button" class="btn btn-outline-secondary tpl-pick__eye"
                                                    data-preview-for="{{ $field }}"
                                                    title="معاينة القالب المختار">
                                                <i class="feather icon-eye"></i> معاينة
                                            </button>
                                        </div>

                                        @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        <p class="stk-help mb-0">{{ $hint }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{--
                            ═══════════ المعاينةُ في مكانها ═══════════

                            كانت تفتح تبويباً جديداً — فيغادر المستخدمُ صفحةَ
                            الإعدادات قبل أن يحفظ، ويقارن بين قالبين بالتنقّل
                            بين تبويبات المتصفّح.

                            وصارت هنا: `iframe` بجانب الاختيار، يُبدَّل بتبديل
                            القائمة. والإطارُ لا الحقنُ المباشر: ورقةُ القالب
                            مستندٌ كامل بأنماطٍ عارية (`body`, `table`) — وحقنُها
                            في الصفحة يفعل بها ما فعله قالبُ القيد بشاشة
                            «عرض القيد».
                        --}}
                        <div class="tpl-preview" id="tplPreview" hidden>
                            <div class="tpl-preview__bar">
                                <span class="tpl-preview__title">
                                    <i class="feather icon-eye"></i>
                                    معاينة: <b id="tplPreviewName">—</b>
                                </span>

                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tplPreviewClose">
                                    <i class="feather icon-x"></i> إغلاق
                                </button>
                            </div>

                            <div class="tpl-preview__frame">
                                <iframe id="tplPreviewFrame" title="معاينة القالب" loading="lazy"></iframe>
                            </div>
                        </div>

                        <div class="stk-section__head">
                            <h6 class="stk-section__title">
                                <i class="feather icon-printer"></i> سلوك الطباعة
                            </h6>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <label class="stk-switch" for="show_print_window_after_confirm">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">فتح نافذة الطباعة بعد إتمام البيع</span>
                                        <span class="stk-switch__hint">
                                            إطفاؤها يحفظ الفاتورة بلا فتح نافذة — لمن يطبع لاحقاً أو لا يطبع.
                                        </span>
                                    </span>

                                    <input type="hidden" name="show_print_window_after_confirm" value="0">
                                    <input type="checkbox" id="show_print_window_after_confirm"
                                           name="show_print_window_after_confirm" value="1"
                                           @checked(old('show_print_window_after_confirm', $posSettings->show_print_window_after_confirm ?? true))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-payment" role="tabpanel" aria-labelledby="tab-payment">
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="active_payment_method_ids" class="stk-req">
                                        الطرق المفعَّلة
                                        {!! $tip('ما يظهر للكاشير في شاشة الدفع — وبلا طريقةٍ واحدة لا تُحصَّل فاتورة.') !!}
                                    </label>
                                    <select id="active_payment_method_ids" name="active_payment_method_ids[]"
                                            class="form-control select2 @error('active_payment_method_ids') is-invalid @enderror" multiple required>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method->id }}"
                                                @selected(in_array($method->id, old('active_payment_method_ids', $posSettings->active_payment_method_ids ?? [])))>
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-create-first :count="$paymentMethods->count()" route="payment_methods.create"
                                        label="طريقة دفع"
                                        hint="لا طرق دفعٍ مفعَّلة، ولا تُحصَّل فاتورةٌ بدونها." />
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="default_payment_method_id" class="stk-req">الطريقة الافتراضية</label>
                                    <select id="default_payment_method_id" name="default_payment_method_id"
                                            class="form-control select2 @error('default_payment_method_id') is-invalid @enderror" required>
                                        <option value="">اختر…</option>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method->id }}"
                                                @selected(old('default_payment_method_id', $posSettings->default_payment_method_id ?? '') == $method->id)>
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="stk-help mb-0">يجب أن تكون من ضمن المفعَّلة.</p>
                                </div>
                            </div>
                        </div>

                        <div class="stk-section__head mt-2">
                            <i class="feather icon-refresh-cw"></i>
                            <h6 class="stk-section__title">التسوية</h6>
                        </div>

                        <div class="row">
                            @foreach ([
                                ['enable_auto_settlement', 'التسوية التلقائية', 'تُسوّى مدفوعاتُ البطاقات مع البنك بلا تدخّل'],
                                ['enable_sales_settlement', 'تسوية المبيعات', 'تربط تحصيلَ نقطة البيع بدورة المبيعات'],
                            ] as [$name, $label, $hint])
                                <div class="col-md-6 col-sm-12 mb-1">
                                    <label class="stk-switch" for="{{ $name }}">
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">{{ $label }}</span>
                                            <span class="stk-switch__hint">{{ $hint }}</span>
                                        </span>
                                        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1"
                                               @checked(old($name, $posSettings->{$name} ?? false))>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <p class="stk-help mb-0">
                            التلقائيةُ لا تعمل إلا مع تسوية المبيعات معاً — وهو ما يفحصه مسارُ الحفظ.
                        </p>
                    </div>

                    {{-- ═══════════ أين يُقيَّد ═══════════ --}}
                    <div class="tab-pane fade" id="pane-accounts" role="tabpanel" aria-labelledby="tab-accounts">
                        @include('pos::settings._accounts')
                    </div>

                    {{-- ═══════════ ما يُسمح به ═══════════ --}}
                    <div class="tab-pane fade" id="pane-controls" role="tabpanel" aria-labelledby="tab-controls">
                        <div class="row">
                            @foreach ([
                                ['require_shift_open', 'إلزامُ جلسةٍ مفتوحة قبل البيع',
                                    'إطفاؤه يسمح بالبيع بلا جلسة — تُحفظ الفاتورةُ وقيدُها ولا تُنسب إلى عهدة.', true],
                                ['enforce_shift_window', 'إلزامُ وقت الوردية',
                                    'تعطيلُ الوردية وأيّامُها يُفرضان دائماً مهما كان هذا الضابط.', true],
                                ['allow_cash_refund', 'السماح بالردّ النقدي من الدرج',
                                    'وإلّا فإشعارٌ دائن على حساب العميل.', false],
                            ] as [$name, $label, $hint, $default])
                                <div class="col-md-4 col-sm-6 mb-1">
                                    <label class="stk-switch" for="{{ $name }}">
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">{{ $label }}</span>
                                            <span class="stk-switch__hint">{{ $hint }}</span>
                                        </span>
                                        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1"
                                               @checked(old($name, $posSettings->{$name} ?? $default))>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="stk-section__head mt-2">
                            <i class="feather icon-sliders"></i>
                            <h6 class="stk-section__title">الحدود</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="max_refund_amount">
                                        أقصى مرتجعٍ بلا اعتماد
                                        {!! $tip('صفرٌ يعني أن كلَّ مرتجعٍ يحتاج اعتماد مشرف — وهو ما يعمل به النظام الآن.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           id="max_refund_amount" name="max_refund_amount" class="form-control @error('max_refund_amount') is-invalid @enderror"
                                           value="{{ old('max_refund_amount', $posSettings->max_refund_amount ?? '') }}"
                                           placeholder="0 = كلُّها تحتاج اعتماداً">
                                    <p class="stk-help mb-0">فارغاً = بلا حدّ.</p>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="max_discount_percent">
                                        حدّ الخصم المسموح (٪)
                                        {!! $tip('أعلى نسبةِ خصمٍ يمنحها الكاشير بلا اعتماد، وتجاوزُه يُسجَّل في سجلّ التدقيق.') !!}
                                    </label>
                                    <input type="number" step="0.01" min="0" max="100"
                                           id="max_discount_percent" name="max_discount_percent" class="form-control @error('max_discount_percent') is-invalid @enderror"
                                           value="{{ old('max_discount_percent', $posSettings->max_discount_percent ?? '') }}"
                                           placeholder="فارغاً = بلا سقف">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stk-form-foot">
            <a href="{{ route('pos.settings.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            @unless ($locked)
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ الإعدادات
                </button>
            @endunless
        </div>
    </form>
</div>

@endsection

@section('scripts')
<style>
    /* `stk-tip` في نظام التصميم — وهذا ما تحتاجه لوحاتُ التبويب وحدها */
    .tab-content > .tab-pane { padding-top: .35rem; }
    .tab-content .stk-switch { margin-bottom: .35rem; }

    /* اختيارُ القالب ومعاينتُه في سطرٍ واحد — الاختيارُ بلا رؤيةٍ تخمين */
    .tpl-pick { display: flex; gap: 6px; }
    .tpl-pick__select { flex: 1 1 auto; }
    .tpl-pick__eye { flex: 0 0 auto; white-space: nowrap; }
    .tpl-pick__eye:disabled { opacity: .5; cursor: not-allowed; }

    /* لوحُ المعاينة — الإطارُ يعزل أنماطَ الورقة عن الصفحة */
    .tpl-preview {
        margin: 0 0 1rem;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        overflow: hidden;
        background: var(--app-surface);
    }

    .tpl-preview__bar {
        display: flex; justify-content: space-between; align-items: center;
        gap: .75rem;
        padding: .5rem .75rem;
        background: var(--app-body-bg);
        border-bottom: 1px solid var(--stk-line);
    }

    .tpl-preview__title { font-size: .85rem; font-weight: 600; color: var(--app-text); }
    .tpl-preview__title b { font-weight: 700; }

    .tpl-preview__frame { background: #f4f6fa; }

    .tpl-preview__frame iframe {
        display: block; width: 100%; height: 62vh; min-height: 380px;
        border: 0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        /*
            ═══════════ معاينةُ القالب المختار ═══════════

            الاختيارُ من عشرة قوالبَ بأسمائها وحدها تخمين. والمعاينةُ تفتح
            القالبَ نفسَه في تبويبٍ جديد ببيانات عيّنة — نفسُ المسار الذي
            تستعمله شاشةُ تصميم القوالب.

            و«الافتراضي» و«المدمج» لا معرّفَ لهما، فيُعطَّل الزرُّ ويُقال
            السبب بدل أن يُفتح تبويبٌ فارغ.
        */
        /*
            ═══════════ معاينةُ القالب في الصفحة ═══════════

            كانت تفتح تبويباً جديداً — فيغادر المستخدمُ إعداداتِه قبل الحفظ،
            ويقارن قالبين بالتنقّل بين تبويبات المتصفّح.

            والإطارُ يبقى في مكانه ويتبدّل بتبديل الاختيار، فتُقارَن
            القوالبُ بالنظر لا بالذاكرة.
        */
        /*
            العنوانُ من المُوجِّه لا مبنيّاً باليد.

            كان `url('/'.locale.'/settings/document-templates')` — وهو خطأٌ
            في موضعين: المسارُ ليس تحت `settings/`، ولا بادئةَ لغةٍ له.
            فيُحمَّل الإطارُ على عنوانٍ لا وجودَ له ويُعرض «404».

            والقالبُ يُبنى بمعرّفٍ نائبٍ يُستبدَل في الشيفرة — فأيُّ تغييرٍ
            في تعريف المسار يسري هنا بلا تعديل.
        */
        var previewUrl = @json(route('document-templates.preview', ['id' => '__ID__']));
        var panel = document.getElementById('tplPreview');
        var frame = document.getElementById('tplPreviewFrame');
        var nameEl = document.getElementById('tplPreviewName');
        var closeBtn = document.getElementById('tplPreviewClose');

        function showPreview(select) {
            if (! panel || ! frame) {
                return;
            }

            var id = select.value;

            if (! /^\d+$/.test(id)) {
                return;
            }

            frame.src = previewUrl.replace('__ID__', id);
            nameEl.textContent = select.selectedOptions[0] ? select.selectedOptions[0].textContent.trim() : '';

            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                panel.hidden = true;

                // يُفرَّغ المصدر: إطارٌ مخفيٌّ يبقى محمَّلاً بلا داعٍ
                frame.removeAttribute('src');
            });
        }

        document.querySelectorAll('[data-preview-for]').forEach(function (btn) {
            var select = document.getElementById(btn.dataset.previewFor);

            if (! select) {
                return;
            }

            var sync = function () {
                var numeric = /^\d+$/.test(select.value);

                btn.disabled = ! numeric;
                btn.title = numeric
                    ? 'معاينة القالب المختار'
                    : 'لا معاينة لهذا الخيار — اختر قالباً بعينه';

                /*
                    الإطارُ يتبع الاختيار حين يكون مفتوحاً — فتبديلُ القائمة
                    يُبدّل ما يُرى بلا ضغطةٍ ثانية.
                */
                if (panel && ! panel.hidden && numeric && frame.dataset.owner === btn.dataset.previewFor) {
                    showPreview(select);
                }
            };

            select.addEventListener('change', sync);
            sync();

            btn.addEventListener('click', function () {
                frame.dataset.owner = btn.dataset.previewFor;
                showPreview(select);
            });
        });

        var scope = document.getElementById('allowed_categories_type');
        var wrapper = document.getElementById('categories_selection_wrapper');

        if (scope && wrapper) {
            var syncCategories = function () {
                // «الكل» لا يحتاج قائمةً تُملأ — وعرضُها يوحي بأن الاختيار مؤثّر
                wrapper.style.display = scope.value === 'all' ? 'none' : '';
            };

            scope.addEventListener('change', syncCategories);
            syncCategories();
        }

        /*
            ═══════════ خطرُ التبويب: خطأٌ في تبويبٍ مغلق ═══════════

            يُرفض الحفظُ لخطأٍ لا يراه المستخدم، فيبدو الزرُّ معطَّلاً ويعيد
            الضغطَ ثم يغادر. فيُفتح التبويبُ الذي فيه أوّلُ خطأ ويُمرَّر إليه.
        */
        var invalid = document.querySelector('#posSettingsForm .is-invalid, #posSettingsForm .invalid-feedback');

        if (invalid) {
            var pane = invalid.closest('.tab-pane');
            var trigger = pane && document.querySelector('[data-bs-target="#' + pane.id + '"]');

            if (trigger && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();

                /*
                    `select2` يُخفي القائمةَ الأصلية ويرسم بديلَها أخاً لها،
                    والتمريرُ إلى عنصرٍ مخفيّ لا يُحرّك الشاشة. فيُمرَّر إلى
                    ما يُرى فعلاً.
                */
                var next = invalid.nextElementSibling;
                var visible = (next && next.classList.contains('select2-container')) ? next : invalid;

                visible.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        }

        /*
            الافتراضيةُ يجب أن تكون من ضمن المفعَّلة. الخادمُ يفرضها كذلك —
            وهذا يوفّر رحلةً كاملة ويقول السببَ عند الحقل.
        */
        var form = document.getElementById('posSettingsForm');

        if (form) {
            form.addEventListener('submit', function (e) {
                var activeEl = document.getElementById('active_payment_method_ids');
                var fallbackEl = document.getElementById('default_payment_method_id');

                if (! activeEl || ! fallbackEl) {
                    return;
                }

                var active = Array.prototype.map.call(activeEl.selectedOptions, function (o) { return o.value; });
                var fallback = fallbackEl.value;

                if (active.length === 0) {
                    e.preventDefault();
                    alert('اختر طريقة دفعٍ واحدة على الأقل.');
                } else if (fallback && active.indexOf(fallback) === -1) {
                    e.preventDefault();
                    alert('طريقةُ الدفع الافتراضية يجب أن تكون من ضمن الطرق المفعَّلة.');
                }
            });
        }
    });
</script>
@endsection
