@extends('hr::master')

{{--
    إعداداتُ الموظفين والهيكل التنظيمي — على تصميم «الدوام والحضور»
    (`sales::partials.settings-theme`، البادئة `sl-`): ترويسةٌ بزرّ الحفظ،
    بطاقاتُ إحصاء، شريطُ «ما المضبوط الآن»، إرشاداتٌ مطويّة، ثم بطاقةٌ واحدة
    بثلاثة تبويبات.

    التبويباتُ الثلاثة نموذجٌ واحد (`#empSettings`) يُحفَظ كلُّه بزرٍّ واحد —
    فمن يضبط إلزاميّةَ «القسم» في ملفّ الموظف وإلزاميّةَ وصفِ القسم في شاشته
    يفعلها في حفظةٍ واحدة.

    والتبديلُ بين التبويبات بسكربتٍ صغيرٍ هنا لا بـ`data-toggle`: الشاشة تحمل
    Bootstrap 4 و5 معاً، ولوحةٌ خارج حاوية أختها لا يُخفيها محرّكُ 4.

    كلُّ مفتاحٍ هنا معرَّفٌ في `App\Support\HrSettings::definitions()` ويُحفَظ
    بخريطة `EmployeeSettingsController` — فلا اسمَ حقلٍ مكتوبٌ في موضعين.
--}}

@section('title', 'إعدادات الموظفين والهيكل التنظيمي')

@section('content')
    @include('sales::partials.settings-theme')

    @php
        $S = \App\Support\HrSettings::class;

        /* قيمةُ المفتاح بعد فشل التحقّق: الخانةُ المطفأة تصل بقيمة `0` من
           الحقل المخفيّ في `sales::components.switch`، فـ`old()` وحدها تكفي —
           لكن `old($f, $default)` كانت تعيدها مفعّلةً لو لم يُرسَل النموذج
           أصلاً. فالتمييزُ بـ`hasOldInput()`. */
        $resubmitted = session()->hasOldInput();
        $on = fn (string $field, bool $current) => $resubmitted ? (bool) old($field) : $current;
        $flag = fn (string $group, string $key) => $S::flag($group, $key);
        $num = fn (string $group, string $key) => $S::integer($group, $key);

        /* أقسامُ تبويب «حقول ملفّ الموظف» — ترتيبُها ترتيبُ الظهور، وحقولُ
           كلٍّ منها تُقرأ من `HrSettings::EMPLOYEE_FIELDS` بمجموعتها. */
        $fieldSections = [
            'employee_core_required' => [
                'icon' => 'key',
                'label' => 'البريد والفرع والدور',
                'lead' => 'الثلاثةُ مشغَّلةٌ افتراضاً، وإطفاءُ أيٍّ منها أثقلُ من إطفاء حقلٍ عادي — أثرُه يتجاوز النموذج إلى الدخول ونطاق الرؤية والصلاحيات. اقرأ شرحَ كلٍّ قبل إطفائه.',
                'warn' => true,
            ],
            'employee_required_identity' => [
                'icon' => 'user',
                'label' => 'الهوية والبيانات الشخصية',
                'lead' => 'مشغَّلان افتراضاً — كانا إلزاميَّين مثبَّتَين في الشيفرة قبل هذه الشاشة.',
            ],
            'employee_required_contact' => [
                'icon' => 'phone',
                'label' => 'بيانات التواصل وجهة الطوارئ',
                'lead' => 'الهاتفان مشغَّلان افتراضاً. وجهةُ الطوارئ ثلاثةُ حقولٍ مستقلّة فيمكن إلزامُ الاسم والهاتف وترك الصلة.',
            ],
            'employee_required_address' => [
                'icon' => 'map-pin',
                'label' => 'العنوان الحالي',
                'lead' => 'يظهر في مستندات الموظف ومراسلاته. «عنوان 2» يبقى اختياريّاً دائماً.',
            ],
            'employee_required_bank' => [
                'icon' => 'credit-card',
                'label' => 'البيانات البنكية',
                'lead' => 'يُبنى عليها ملفُّ التحويل البنكي للرواتب. وهي تُعرَض لحاملي صلاحية «البيانات الحسّاسة» وحدهم — ومن لا يراها لا يُطالَب بها، فإلزامُها لا يُقفل عليه النموذج.',
            ],
            'employee_required_job' => [
                'icon' => 'briefcase',
                'label' => 'البيانات الوظيفية',
                'lead' => 'الأربعةُ الأولى من الهيكل التنظيمي. والمديرُ المباشر تقوم عليه الشجرةُ التنظيمية ومسارُ الاعتماد، وتاريخُ الالتحاق أساسُ مكافأة نهاية الخدمة ورصيدِ الإجازات.',
            ],
        ];

        /* شرحُ كلّ حقل — سطرٌ يقول ما يفعله الإلزامُ وأين يسري، لا ترديدٌ
           لاسم الحقل. */
        $fieldHints = [
            'require_email' => 'البريدُ هو معرّفُ الدخول: يُكتب في حساب الموظف (users.email) وبه يدخل. بإطفائه يُسجَّل موظفٌ بلا بريد — ولا يستطيع الدخول، ولا تصله بياناتُ حسابه ولا قسيمةُ راتبه بالبريد. والفرادةُ تبقى: بريدان متماثلان مرفوضان، وموظفان بلا بريدٍ مقبولان.',
            'require_branch' => 'الفرعُ محورُ نطاق الرؤية: موظفٌ بلا فرعٍ لا يظهر لمن نطاقُه «فرعي»، ولا في تقريرٍ مبنيٍّ على الفروع، ولا تصله حصصُ المخزون والخزائن المربوطة بالفرع. وأوّلُ الفروع المختارة هو الفرعُ الأساسي.',
            'require_login_role' => 'الدورُ مصدرُ صلاحيات حساب الدخول كلِّها. بإطفائه يُنشأ الحسابُ بلا دور: يدخل الموظفُ ولا يرى شيئاً حتى يُسنَد له دورٌ من شاشة الأدوار. ويظهر هذا الحقلُ لحاملي «إدارة أدوار الموظفين» وحدهم — ومن سواهم لا يُطالَب به أصلاً.',
            'require_date_of_birth' => 'يُحسب منه السنُّ في تقارير الموظفين، وعليه يقوم «أقل عمر عند التعيين» في تبويب القواعد.',
            'require_country' => 'بلدُ الموظف كما يظهر في ملفّه وتقارير الجنسيات.',
            'require_mobile_number' => 'الرقمُ الذي تصل إليه الرسائلُ وإشعاراتُ الحضور والإجازات.',
            'require_phone_number' => 'هاتفٌ بديلٌ أو ثابت — مستقلٌّ عن الجوّال في النموذج.',
            'require_personal_email' => 'بريدٌ غير بريد الدخول، للتواصل خارج النظام.',
            'require_emergency_contact_name' => 'من يُتَّصل به إن وقع مكروه في العمل.',
            'require_emergency_contact_phone' => 'رقمُ جهة الطوارئ — يُفحَص بصيغة الرقم السعودي.',
            'require_emergency_contact_relation' => 'صلةُ جهة الطوارئ بالموظف (أب، أخ، زوج…).',
            'require_current_address_1' => 'السطرُ الأول من العنوان — يظهر في المستندات الرسمية للموظف.',
            'require_city' => 'المدينةُ — تُستعمل في تصفية الموظفين والتقارير الجغرافية.',
            'require_region' => 'المنطقةُ الإدارية.',
            'require_postal_code' => 'الرمزُ البريدي — يطلبه بعضُ النماذج الحكومية.',
            'require_bank_name' => 'اسمُ البنك الذي يُحوَّل إليه الراتب.',
            'require_bank_account_holder' => 'اسمُ صاحب الحساب كما هو في البنك — يُطابقه البنكُ قبل التحويل.',
            'require_iban' => 'بلا آيبانٍ صحيحٍ لا يدخل الموظفُ في ملفّ التحويل البنكي للرواتب. الصيغةُ تُفحَص دائماً (ISO 13616 مع خانتَي التحقّق) سواءٌ أُلزم الحقلُ أم لا.',
            'require_department' => 'القسمُ أساسُ التقارير المبنيّة على الأقسام ونطاقِ الرؤية «قسمي».',
            'require_job_title' => 'المسمّى يظهر في العقد والقسيمة والمستندات.',
            'require_job_level' => 'الدرجةُ في التدرّج الوظيفي.',
            'require_job_type' => 'طبيعةُ الارتباط: دوام كامل، جزئي، تعاقد…',
            'require_direct_manager' => 'عليه تقوم الشجرةُ التنظيمية ومسارُ اعتماد الإجازات والسلف — ومن لا مديرَ له لا يجد من يعتمد طلبَه.',
            'require_hire_date' => 'أساسُ حساب مكافأة نهاية الخدمة ورصيدِ الإجازات ومدّةِ الخدمة في التقارير.',
        ];

        // عددُ الحقول المشغَّلة الآن (قبل الحفظ) من أصل الخريطة كلِّها
        $allFields = $S::EMPLOYEE_FIELDS;
        $enabledCount = 0;
        foreach ($allFields as [$g, $k]) {
            if ($flag($g, $k)) {
                $enabledCount++;
            }
        }
        $totalFields = count($allFields);

        $minAge = $num('employee_validation_rules', 'min_hire_age_years');
        $pwdMin = $num('employee_validation_rules', 'password_min_length') ?? 8;
        $uniqueMobile = $flag('employee_validation_rules', 'unique_mobile_number');
        $futureHire = $flag('employee_validation_rules', 'allow_future_hire_date');
        $newNodeActive = $flag('org_defaults', 'new_node_active');

        // أقسامُ تبويب الهيكل: المجموعة ⇦ [الأيقونة، العنوان، مفتاحُ عدّ التكرار]
        $orgSections = [
            'org_department_rules' => ['grid', 'الأقسام', 'departments'],
            'org_job_title_rules' => ['tag', 'المسمّيات الوظيفية', 'job_titles'],
            'org_job_level_rules' => ['bar-chart', 'المستويات الوظيفية', 'job_levels'],
            'org_job_type_rules' => ['layers', 'أنواع الوظائف', 'job_types'],
        ];

        $tabs = [
            'tab-fields' => ['icon' => 'check-square', 'label' => 'حقول ملفّ الموظف', 'badge' => $enabledCount],
            'tab-rules' => ['icon' => 'shield', 'label' => 'قواعد التحقّق', 'badge' => null],
            'tab-org' => ['icon' => 'share-2', 'label' => 'الهيكل التنظيمي', 'badge' => null],
        ];

        $activePane = 'tab-'.($activeTab ?? 'fields');
    @endphp

<div class="content-body sl-scope">

    @section('page-actions')
        <button type="submit" form="empSettings" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
        @permission('employees.index')
            <a href="{{ route('employee.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-users"></i> الموظفون
            </a>
        @endpermission
    @endsection

    @include('sales::components.page-head', [
        'title' => 'إعدادات الموظفين والهيكل التنظيمي',
        'icon' => 'sliders',
        'sub' => 'أيُّ حقولِ ملفّ الموظف إلزاميٌّ، وقواعدُ التحقّق فيه، وإلزاميّةُ حقول الأقسام والمسمّيات والمستويات والأنواع وفرادةُ أسمائها.',
        'crumbs' => ['الرئيسية' => url('/'), 'الموظفون' => route('employee.index'), 'الإعدادات' => null],
    ])

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- ══════════ الإحصاء ══════════ --}}
    <div class="sl-stats">
        <div class="sl-stat">
            <div class="sl-stat__icon"><i class="feather icon-users"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ number_format($employeesCount) }}</p>
                <p class="sl-stat__label">موظف قائم (غير مؤرشف)</p>
            </div>
        </div>
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-check-square"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $enabledCount }} / {{ $totalFields }}</p>
                <p class="sl-stat__label">حقل إلزامي مشغَّل في ملفّ الموظف</p>
            </div>
        </div>
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $incompleteCount ? 'warning' : 'success' }}"><i class="feather icon-alert-triangle"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ number_format($incompleteCount) }}</p>
                <p class="sl-stat__label">ملفّ ينقصه حقل إلزامي مشغَّل</p>
            </div>
        </div>
        @php $dupTotal = ($duplicates['departments'] ?? 0) + ($duplicates['job_titles'] ?? 0) + ($duplicates['job_levels'] ?? 0) + ($duplicates['job_types'] ?? 0); @endphp
        <div class="sl-stat">
            <div class="sl-stat__icon sl-stat__icon--{{ $dupTotal ? 'warning' : 'success' }}"><i class="feather icon-copy"></i></div>
            <div class="sl-stat__body">
                <p class="sl-stat__value sl-num">{{ $dupTotal }}</p>
                <p class="sl-stat__label">اسم مكرَّر قائم في الهيكل التنظيمي</p>
            </div>
        </div>
    </div>

    {{-- ══════════ ما المضبوط الآن ══════════ --}}
    <section class="sl-ctx" aria-label="ما المضبوط الآن">
        <div class="sl-ctx__head">
            <h6 class="sl-ctx__title"><i class="feather icon-sliders" aria-hidden="true"></i> ما المضبوط الآن</h6>
            <p class="sl-ctx__hint">ملخّص ما تعمل به شاشاتُ الموظفين والهيكل حالياً — اضغط أي بند لفتح تبويبه.</p>
        </div>
        <div class="sl-ctx__grid">
            @foreach ([
                ['tab' => 'tab-fields', 'icon' => 'check-square', 'label' => 'حقول إلزامية في ملفّ الموظف',
                    'tone' => $enabledCount ? 'on' : 'off', 'value' => $enabledCount.' من '.$totalFields],
                ['tab' => 'tab-fields', 'icon' => 'key', 'label' => 'البريد / الفرع / الدور',
                    'tone' => ($flag('employee_core_required', 'require_email') && $flag('employee_core_required', 'require_branch') && $flag('employee_core_required', 'require_login_role')) ? 'on' : 'off',
                    'value' => collect([
                        'البريد' => $flag('employee_core_required', 'require_email'),
                        'الفرع' => $flag('employee_core_required', 'require_branch'),
                        'الدور' => $flag('employee_core_required', 'require_login_role'),
                    ])->filter()->keys()->implode(' · ') ?: 'لا شيء إلزامي'],
                ['tab' => 'tab-rules', 'icon' => 'smartphone', 'label' => 'فرادة رقم الجوال',
                    'tone' => $uniqueMobile ? 'on' : 'off', 'value' => $uniqueMobile ? 'مشغَّلة' : 'متوقفة'],
                ['tab' => 'tab-rules', 'icon' => 'calendar', 'label' => 'تاريخ التحاق في المستقبل',
                    'tone' => $futureHire ? 'on' : 'off', 'value' => $futureHire ? 'مسموح' : 'ممنوع'],
                ['tab' => 'tab-rules', 'icon' => 'user-check', 'label' => 'أقل عمر عند التعيين',
                    'tone' => $minAge ? 'on' : 'off', 'value' => $minAge ? $minAge.' سنة' : 'بلا قيد'],
                ['tab' => 'tab-rules', 'icon' => 'lock', 'label' => 'أقل طول لكلمة المرور',
                    'tone' => 'on', 'value' => $pwdMin.' محرف'],
                ['tab' => 'tab-org', 'icon' => 'grid', 'label' => 'فرادة أسماء الأقسام',
                    'tone' => $flag('org_department_rules', 'unique_name') ? 'on' : 'off',
                    'value' => $flag('org_department_rules', 'unique_name') ? 'مشغَّلة' : 'متوقفة'],
                ['tab' => 'tab-org', 'icon' => 'tag', 'label' => 'القسم في المسمّى الوظيفي',
                    'tone' => $flag('org_job_title_rules', 'require_department') ? 'on' : 'off',
                    'value' => $flag('org_job_title_rules', 'require_department') ? 'إلزامي' : 'اختياري'],
                ['tab' => 'tab-org', 'icon' => 'power', 'label' => 'حالة ما يُضاف إلى الهيكل',
                    'tone' => $newNodeActive ? 'on' : 'off', 'value' => $newNodeActive ? 'نشط' : 'غير نشط'],
            ] as $item)
                <a href="#{{ $item['tab'] }}" class="sl-ctx__item sl-ctx__item--{{ $item['tone'] }}" data-emp-tab="{{ $item['tab'] }}">
                    <span class="sl-ctx__icon" aria-hidden="true"><i class="feather icon-{{ $item['icon'] }}"></i></span>
                    <span class="sl-ctx__body">
                        <span class="sl-ctx__label">{{ $item['label'] }}</span>
                        <span class="sl-ctx__value" title="{{ $item['value'] }}">{{ $item['value'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ══════════ الإرشادات (مطويّة) ══════════ --}}
    <details class="sl-guide">
        <summary><i class="feather icon-info"></i> <span>كيف تعمل هذه الإعدادات؟</span></summary>
        <div class="sl-guide__body">
            <h6>الإلزامُ يسري على الإضافة دائماً، وعلى التعديل بحسابٍ</h6>
            <p>الحقلُ المشغَّلُ مطلوبٌ عند إضافة موظفٍ جديد. وعند تعديل موظفٍ قائم يُطلب <strong>إن كان محفوظاً له فعلاً</strong> — فمن فُتح ملفُّه لتصحيح اسمٍ لا يُردّ بـ«الحقل مطلوب» في حقلٍ لم يُمسّ ولم يكن مسجَّلاً من قبل. بهذا لا يُقفَل ملفٌّ قديمٌ ناقصٌ عن كلّ تعديل، ولا يُمحى ما سُجّل بحفظةٍ ساهية.</p>
            <p class="sl-guide__fact"><i class="feather icon-check"></i> الرقمُ بجانب كلّ حقلٍ أدناه هو عددُ الملفّات القائمة التي ينقصها ذلك الحقل — تنبيهٌ لا تحذير.</p>

            <h6>ثلاثةٌ أثقلُ من غيرها: البريد والفرع والدور</h6>
            <p>هذه الثلاثةُ تُطفَأ من هنا كغيرها، لكنّ أثرَها يتجاوز النموذج: <strong>البريدُ</strong> معرّفُ الدخول — ومن لا بريدَ له لا يدخل ولا تصله بياناتُ حسابه؛ <strong>والفرعُ</strong> محورُ نطاق الرؤية — وموظفٌ بلا فرعٍ لا يظهر لمن نطاقُه «فرعي» ولا في تقريرٍ مبنيٍّ على الفروع؛ <strong>والدورُ</strong> مصدرُ صلاحيات الحساب — وحسابٌ بلا دورٍ يدخل ولا يرى شيئاً. فلا تُطفَأ إلّا بقرارٍ صريح.</p>

            <h6>حقولٌ لا تُطفَأ من هنا</h6>
            <p>الاسمُ الأول، والحالة، ونوعُ الموظف. إلزاميّتُها بنيويّةٌ لا قاعدةُ عمل: سجلٌّ بلا اسمٍ لا يُعرَف في أيّ منتقٍ، والحالةُ ونوعُ الموظف يحكمان مسارَ الحفظ نفسَه (حسابُ الدخول، الخزينةُ والمستودعُ الافتراضيّان). و«النوع» و«حالة المواطنة» لهما قيمةٌ افتراضيةٌ دائماً في قاعدة البيانات فلا معنى لإلزامهما.</p>

            <h6>الفرادةُ والبياناتُ القائمة</h6>
            <p>فرادةُ الاسم أو رقم الجوال تمنع التكرارَ الجديد، ولا تُصلح القائم. وتشغيلُها على بياناتٍ فيها تكرارٌ يُقفل الصفوفَ المكرَّرة عن التعديل حتى يُفرَّق بينها — والعددُ معروضٌ قبل التشغيل في كلّ موضع.</p>

            <h6>النجمةُ في النموذج تتبع الإعداد</h6>
            <p>نجمةُ الإلزام على التسمية في نموذجَي إضافة الموظف وتعديله، وفي نماذج الهيكل التنظيمي، تُقرأ من المفاتيح نفسِها التي يفحصها الخادم — فلا يُلزَم حقلٌ بلا علامةٍ عليه، ولا تظهر نجمةٌ على حقلٍ غيرِ مطلوب.</p>
        </div>
    </details>

    {{-- ══════════ التبويبات ══════════ --}}
    <div class="card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs emp-tabs w-100" role="tablist">
                @foreach ($tabs as $tabId => $tab)
                    <li class="nav-item">
                        <a class="nav-link {{ $tabId === $activePane ? 'active' : '' }}" href="#{{ $tabId }}" data-emp-tab="{{ $tabId }}" role="tab">
                            <i class="feather icon-{{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                            @if (! is_null($tab['badge']))
                                <span class="badge badge-light-secondary sl-num">{{ $tab['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <form id="empSettings" method="POST" action="{{ route('hr.employee_settings.update') }}">
            @csrf
            <input type="hidden" name="active_tab" id="empActiveTab" value="{{ $activeTab ?? 'fields' }}">

            {{-- ────────── حقول ملفّ الموظف ────────── --}}
            <div class="emp-pane {{ $activePane === 'tab-fields' ? 'active' : '' }}" id="tab-fields" role="tabpanel">
                <div class="card-body">
                    <p class="emp-lead">كلُّ مفتاحٍ هنا يجعل حقلَه مطلوباً في نموذج إضافة الموظف — ويضع نجمةً على تسميته في الشاشة.</p>

                    @foreach ($fieldSections as $group => $section)
                        <div class="sl-section">
                            <div class="sl-section__head">
                                <i class="feather icon-{{ $section['icon'] }}"></i>
                                <h6 class="sl-section__title">{{ $section['label'] }}</h6>
                            </div>
                            @if (! empty($section['warn']))
                                <p class="emp-warn"><i class="feather icon-alert-triangle"></i> {{ $section['lead'] }}</p>
                            @else
                                <p class="emp-lead">{{ $section['lead'] }}</p>
                            @endif

                            @foreach ($allFields as $column => [$fieldGroup, $key, $label])
                                @continue($fieldGroup !== $group)
                                <div class="sl-switch-row">
                                    @include('sales::components.switch', [
                                        'name' => $key,
                                        'checked' => $on($key, $flag($group, $key)),
                                        'title' => $label,
                                        'hint' => $fieldHints[$key] ?? null,
                                    ])
                                    @if (($gaps[$key] ?? 0) > 0)
                                        <span class="emp-gap" title="عددُ الملفّات القائمة التي ينقصها هذا الحقل">
                                            <i class="feather icon-alert-circle"></i>
                                            <span class="sl-num">{{ number_format($gaps[$key]) }}</span> ملفّ ناقص
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ────────── قواعد التحقّق ────────── --}}
            <div class="emp-pane {{ $activePane === 'tab-rules' ? 'active' : '' }}" id="tab-rules" role="tabpanel">
                <div class="card-body">
                    <p class="emp-lead">قواعدٌ تُفحَص على ما يُكتَب في ملفّ الموظف — لا على إلزاميّة الحقل.</p>

                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-copy"></i>
                            <h6 class="sl-section__title">الفرادة</h6>
                        </div>

                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'unique_mobile_number',
                                'checked' => $on('unique_mobile_number', $uniqueMobile),
                                'title' => 'رقم الجوال فريد بين الموظفين',
                                'hint' => 'يمنع حفظَ رقمِ جوّالٍ مسجَّلٍ لموظفٍ آخر. لا فهرسَ فريداً على العمود في قاعدة البيانات، فالتكرارُ القائم يبقى كما هو.',
                            ])
                            @if (($duplicates['mobile_numbers'] ?? 0) > 0)
                                <span class="emp-gap" title="أرقامٌ مسجَّلةٌ لأكثر من موظف">
                                    <i class="feather icon-alert-circle"></i>
                                    <span class="sl-num">{{ $duplicates['mobile_numbers'] }}</span> رقم مكرَّر قائم
                                </span>
                            @endif
                        </div>
                        @if (($duplicates['mobile_numbers'] ?? 0) > 0)
                            <p class="sl-help" data-hint-keep>بالتشغيل تُقفَل ملفّاتُ هؤلاء عن التعديل حتى يُفرَّق بين أرقامهم. البريدُ الإلكتروني وبريدُ الموظف الشخصي فريدان دائماً بلا مفتاح.</p>
                        @endif
                    </div>

                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-calendar"></i>
                            <h6 class="sl-section__title">التواريخ</h6>
                        </div>

                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'allow_future_hire_date',
                                'checked' => $on('allow_future_hire_date', $futureHire),
                                'title' => 'السماح بتاريخ التحاق في المستقبل',
                                'hint' => 'مفيدٌ لمن يسجّل الموظفَ قبل مباشرته العمل. بإطفائه لا يُقبل تاريخُ التحاقٍ بعد اليوم.',
                            ])
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6 form-group">
                                <label for="min_hire_age_years">أقل عمر عند التعيين (سنة)</label>
                                <input type="number" min="14" max="40" step="1" class="form-control sl-num"
                                       id="min_hire_age_years" name="min_hire_age_years"
                                       value="{{ old('min_hire_age_years', $minAge) }}" placeholder="بلا قيد">
                                <span class="sl-help">يُفحَص على تاريخ الميلاد كلَّما أُرسل — ولو كان الحقلُ اختيارياً. فارغٌ = بلا قيد. وتاريخُ ميلادٍ محفوظٌ يخالف الحدَّ يمنع حفظَ ذلك الملفّ حتى يُصحَّح.</span>
                            </div>
                        </div>
                    </div>

                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-lock"></i>
                            <h6 class="sl-section__title">حساب الدخول</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group mb-0">
                                <label for="password_min_length" class="sl-req">الحد الأدنى لطول كلمة المرور</label>
                                <input type="number" min="6" max="64" step="1" required class="form-control sl-num"
                                       id="password_min_length" name="password_min_length"
                                       value="{{ old('password_min_length', $pwdMin) }}">
                                <span class="sl-help">يسري على كلمة المرور التي تُكتب في نموذج الموظف وعلى شاشة تغييرها. كلمةُ المرور نفسُها اختياريّةٌ دائماً — الفراغُ يُبقي ما كان.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ────────── الهيكل التنظيمي ────────── --}}
            <div class="emp-pane {{ $activePane === 'tab-org' ? 'active' : '' }}" id="tab-org" role="tabpanel">
                <div class="card-body">
                    <p class="emp-lead">قواعدُ شاشات الأقسام والمسمّيات والمستويات والأنواع. والاسمُ والحالةُ إلزاميّان دائماً في الأربع — عمودان <code>NOT NULL</code>، وسجلٌّ بلا اسمٍ لا يُعرَف في أيّ منتقٍ.</p>

                    @foreach ($orgSections as $group => [$icon, $label, $dupKey])
                        <div class="sl-section">
                            <div class="sl-section__head">
                                <i class="feather icon-{{ $icon }}"></i>
                                <h6 class="sl-section__title">{{ $label }}</h6>
                            </div>

                            @if ($group === 'org_department_rules')
                                <div class="sl-switch-row">
                                    @include('sales::components.switch', [
                                        'name' => 'department_require_short_name',
                                        'checked' => $on('department_require_short_name', $flag($group, 'require_short_name')),
                                        'title' => 'الاختصار مطلوب',
                                        'hint' => 'رمزٌ قصيرٌ يظهر تحت اسم القسم في القوائم ويُصدَّر معه — مثل «MKT».',
                                    ])
                                </div>
                                <div class="sl-switch-row">
                                    @include('sales::components.switch', [
                                        'name' => 'department_require_manager',
                                        'checked' => $on('department_require_manager', $flag($group, 'require_manager')),
                                        'title' => 'مدير واحد على الأقل مطلوب',
                                        'hint' => 'قائمةُ «المديرون المعنيّون» في شاشة القسم — مستقلّةٌ عن موظفي القسم الذين ينتسبون إليه من ملفّاتهم.',
                                    ])
                                </div>
                            @endif

                            @if ($group === 'org_job_title_rules')
                                <div class="sl-switch-row">
                                    @include('sales::components.switch', [
                                        'name' => 'job_title_require_department',
                                        'checked' => $on('job_title_require_department', $flag($group, 'require_department')),
                                        'title' => 'القسم مطلوب للمسمّى الوظيفي',
                                        'hint' => 'مشغَّلٌ افتراضاً — كان إلزاميّاً مثبَّتاً. وبإطفائه تستطيع منشأةٌ تعريفَ مسمّياتها قبل أقسامها.',
                                    ])
                                </div>
                            @endif

                            <div class="sl-switch-row">
                                @include('sales::components.switch', [
                                    'name' => str_replace(['org_', '_rules'], '', $group).'_require_description',
                                    'checked' => $on(str_replace(['org_', '_rules'], '', $group).'_require_description', $flag($group, 'require_description')),
                                    'title' => 'الوصف مطلوب',
                                    'hint' => 'سطرٌ يقول متى يُستعمَل ولمن — يظهر في شاشة التفاصيل.',
                                ])
                            </div>

                            @php
                                $uniqueField = $group === 'org_job_title_rules'
                                    ? 'job_title_unique_name_per_department'
                                    : str_replace(['org_', '_rules'], '', $group).'_unique_name';
                                $uniqueKey = $group === 'org_job_title_rules' ? 'unique_name_per_department' : 'unique_name';
                            @endphp
                            <div class="sl-switch-row">
                                @include('sales::components.switch', [
                                    'name' => $uniqueField,
                                    'checked' => $on($uniqueField, $flag($group, $uniqueKey)),
                                    'title' => $group === 'org_job_title_rules' ? 'الاسم فريد داخل القسم' : 'الاسم فريد',
                                    'hint' => $group === 'org_job_title_rules'
                                        ? '«محاسب» في المالية وفي المشتريات مسمّيان مختلفان، و«محاسب» مرّتين في المالية خطأُ إدخال — فالفرادةُ تُقاس داخل القسم.'
                                        : 'يمنع حفظَ اسمٍ موجودٍ سلفاً. لا فهرسَ فريداً على العمود، فالتكرارُ القائم يبقى.',
                                ])
                                @if (($duplicates[$dupKey] ?? 0) > 0)
                                    <span class="emp-gap" title="أسماءٌ مكرَّرةٌ قائمة">
                                        <i class="feather icon-alert-circle"></i>
                                        <span class="sl-num">{{ $duplicates[$dupKey] }}</span> اسم مكرَّر قائم
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="sl-section">
                        <div class="sl-section__head">
                            <i class="feather icon-power"></i>
                            <h6 class="sl-section__title">افتراضيات الإضافة</h6>
                        </div>
                        <div class="sl-switch-row">
                            @include('sales::components.switch', [
                                'name' => 'org_new_node_active',
                                'checked' => $on('org_new_node_active', $newNodeActive),
                                'title' => 'ما يُضاف إلى الهيكل يكون «نشطاً»',
                                'hint' => 'الحالةُ المختارةُ سلفاً في نماذج إضافة القسم والمسمّى والمستوى والنوع. بإطفائه يُضاف كلُّ جديدٍ «غير نشط» حتى يُراجَع — ولا يمسّ القائمَ ولا يمنع تغييرَ الحالة باليد.',
                            ])
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .emp-tabs { border-bottom: 1px solid var(--app-border, #ebe9f1); padding: 0 .75rem; flex-wrap: wrap; }
    .emp-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; padding: .9rem 1rem; color: var(--app-muted, #6e6b7b); font-weight: 600; background: none; }
    .emp-tabs .nav-link:hover { color: var(--app-primary, #7367f0); }
    .emp-tabs .nav-link.active { color: var(--app-primary, #7367f0); border-bottom-color: var(--app-primary, #7367f0); background: none; }
    .emp-tabs .nav-link .badge { margin-inline-start: .3rem; }

    .emp-pane { display: none; }
    .emp-pane.active { display: block; }

    .emp-lead { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin-bottom: .85rem; }

    /* عددُ الملفّات الناقصة بجانب المفتاح — تنبيهٌ لا تحذير، فلا لونَ خطر */
    .emp-gap {
        display: inline-flex; align-items: center; gap: .3rem;
        flex: 0 0 auto; margin-inline-start: auto; white-space: nowrap;
        font-size: .72rem; font-weight: 600;
        color: var(--app-warning, #ff9f43);
        background: color-mix(in srgb, var(--app-warning, #ff9f43) 12%, transparent);
        border-radius: 999px; padding: .2rem .55rem;
    }
    .emp-gap i { font-size: .8rem; }

    /* رأسُ القسم الذي يتجاوز أثرُه النموذجَ — البريد والفرع والدور */
    .emp-warn {
        display: flex; align-items: flex-start; gap: .45rem;
        font-size: .78rem; line-height: 1.7; margin-bottom: .85rem;
        padding: .6rem .75rem; border-radius: var(--app-radius, .357rem);
        color: var(--app-warning, #ff9f43);
        background: color-mix(in srgb, var(--app-warning, #ff9f43) 10%, transparent);
        border: 1px solid color-mix(in srgb, var(--app-warning, #ff9f43) 26%, transparent);
    }
    .emp-warn i { margin-top: .15rem; flex: 0 0 auto; }
</style>
@endpush

@push('scripts')
<script>
/*
    التبويبات — مبدِّلٌ صغير بدل data-toggle (الشاشة تحمل Bootstrap 4 و5 معاً).
    و`active_tab` المخفيّ يُحدَّث معه، فالحفظُ يعيدك إلى التبويب الذي كنت فيه.
*/
function openEmpTab(id) {
    const pane = document.getElementById(id);
    if (! pane || ! pane.classList.contains('emp-pane')) return false;
    document.querySelectorAll('.emp-pane').forEach(p => p.classList.toggle('active', p === pane));
    document.querySelectorAll('.emp-tabs .nav-link').forEach(a => a.classList.toggle('active', a.dataset.empTab === id));
    const hidden = document.getElementById('empActiveTab');
    if (hidden) hidden.value = id.replace(/^tab-/, '');
    if (history.replaceState) history.replaceState(null, '', '#' + id);
    return true;
}

document.addEventListener('click', function (e) {
    const link = e.target.closest('[data-emp-tab]');
    if (! link) return;
    e.preventDefault();
    openEmpTab(link.dataset.empTab);
    if (link.classList.contains('sl-ctx__item')) {
        document.querySelector('.emp-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

(function () {
    // بعد خطأ تحقّقٍ يُفتح تبويبُ أوّل حقلٍ فيه خطأ، وإلا فالتبويبُ المطلوب
    const invalid = @json(array_keys($errors->getMessages()));
    let target = window.location.hash.slice(1) || @json($activePane);
    if (invalid.length) {
        const field = document.querySelector('#empSettings [name="' + invalid[0] + '"]');
        const pane = field && field.closest('.emp-pane');
        if (pane) target = pane.id;
    }
    if (! openEmpTab(target)) openEmpTab('tab-fields');
})();
</script>
@endpush
