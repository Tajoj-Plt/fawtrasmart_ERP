@extends('hr::master')

@section('title')
اضافة موظف
@stop

@push('styles')
    {{-- نظام تصميم «stk» نفسه المعتمد في وحدة الحسابات (سندات الصرف) —
         مقصورٌ على هذه الصفحة عبر stack بدل تعميمه على hr::master كله. --}}
    @include('stock::layouts.partials.stock-theme')

    <style>
        /* ===== عناصر خاصة بنموذج الموظف لا يغطّيها نظام stk العام ===== */

        /* كارد الخزينة/المستودع الافتراضي والحساب الأب للسلف */
        .ts-card {
            border: 1px solid var(--stk-line);
            border-radius: var(--stk-r-lg);
            background: var(--stk-tint);
            margin-top: var(--stk-5);
            padding: var(--stk-5);
        }
        .ts-card .stk-section__head { margin-bottom: var(--stk-4); }
        .ts-panel {
            background: var(--app-surface);
            border: 1px solid var(--stk-line);
            border-radius: var(--stk-r);
            padding: var(--stk-4);
            height: 100%;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .ts-panel.is-active {
            border-color: color-mix(in srgb, var(--app-success) 45%, transparent);
            box-shadow: 0 4px 16px color-mix(in srgb, var(--app-success) 12%, transparent);
        }
        .ts-panel-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 700;
            font-size: .88rem;
            color: var(--app-text);
            margin-bottom: .25rem;
        }
        .ts-panel-title i { color: var(--app-success); }
        .ts-panel small { display: block; color: var(--app-muted); margin-bottom: .65rem; font-size: .78rem; }

        .info-banner {
            background: var(--app-primary-soft);
            border-inline-start: 3px solid var(--app-primary);
            color: var(--app-text);
            padding: .6rem .9rem;
            border-radius: var(--app-radius);
            font-size: .82rem;
            margin-bottom: var(--stk-4);
        }

        /* مواقع الفروع المختارة */
        .location-group + .location-group {
            margin-top: .75rem;
        }

        .location-group__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .35rem .5rem;
            font-weight: 600;
            background: var(--stk-line);
            border-radius: var(--stk-r);
        }

        .location-group__toggle {
            font-size: .8rem;
            font-weight: 400;
        }

        .branches-box {
            max-height: 320px;
            overflow-y: auto;
            background: var(--stk-tint);
            border: 1px solid var(--stk-line);
            border-radius: var(--stk-r);
            padding: .5rem;
        }

        /* منطقة رفع الملفات */
        .file-upload-zone {
            position: relative;
            border: 2px dashed var(--stk-line);
            border-radius: var(--stk-r-lg);
            padding: 2rem 1.5rem;
            text-align: center;
            background: var(--stk-tint);
            transition: all .25s ease;
            cursor: pointer;
            overflow: hidden;
        }
        .file-upload-zone:hover {
            border-color: var(--app-primary);
            box-shadow: var(--stk-e3);
        }
        .file-upload-zone.dragover {
            border-color: var(--app-success);
            background: var(--app-success-soft);
        }
        .file-upload-zone .file-upload-input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-zone .file-upload-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--app-primary-soft);
            color: var(--app-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: .75rem;
        }
        .file-upload-zone h5 { color: var(--app-text); font-weight: 700; margin-bottom: .3rem; font-size: .95rem; }
        .file-upload-zone p { color: var(--app-muted); font-size: .82rem; margin-bottom: 0; }

        .selected-files-list { margin-top: 1rem; }
        .selected-files-list:empty { display: none; }
        .selected-file {
            display: flex;
            align-items: center;
            background: var(--app-surface);
            border: 1px solid var(--stk-line);
            border-radius: var(--stk-r);
            padding: .6rem .85rem;
            margin-bottom: .5rem;
        }
        .selected-file .file-icon {
            width: 36px; height: 36px; border-radius: var(--app-radius);
            display: inline-flex; align-items: center; justify-content: center;
            margin-inline-end: .7rem; font-size: 1rem; flex-shrink: 0;
        }
        .selected-file .file-icon.icon-image  { background: var(--app-success-soft); color: var(--app-success); }
        .selected-file .file-icon.icon-pdf    { background: var(--app-danger-soft);  color: var(--app-danger); }
        .selected-file .file-icon.icon-word   { background: color-mix(in srgb, var(--app-info) 12%, transparent); color: var(--app-info); }
        .selected-file .file-icon.icon-excel  { background: var(--app-success-soft); color: var(--app-success); }
        .selected-file .file-icon.icon-other  { background: var(--app-primary-soft); color: var(--app-primary); }
        .selected-file .file-info { flex: 1; min-width: 0; }
        .selected-file .file-name { font-weight: 600; color: var(--app-text); word-break: break-all; font-size: .86rem; }
        .selected-file .file-size { color: var(--app-muted); font-size: .74rem; margin-top: .1rem; }
        .selected-file .remove-file {
            background: transparent; border: 0; color: var(--app-danger);
            cursor: pointer; padding: .3rem .5rem; border-radius: var(--app-radius);
        }
        .selected-file .remove-file:hover { background: var(--app-danger-soft); }

        /* شريط الحفظ/الإلغاء الثابت أعلى الصفحة */
        .stk-form-topbar {
            position: sticky; top: 70px; z-index: 5;
        }
    </style>
@endpush

@section('content')

    @php
        /*
            نجمةُ الإلزام تتبع «إعدادات الموظفين والهيكل التنظيمي» لا سطراً
            مثبَّتاً هنا: `$req('date_of_birth')` تقرأ المفتاحَ نفسَه الذي
            يفحصه `EmployeeRequest::fieldRules()`، فلا يُلزَم حقلٌ في الخادم
            بلا علامةٍ عليه في الشاشة — ولا العكس.

            وحقولٌ تبقى إلزاميّةً دائماً بنجمتها المثبَّتة (الاسمُ الأول،
            البريد، الحالة، نوعُ الموظف، الفرع): إلزاميّتُها بنيويّةٌ لا
            قاعدةُ عمل، والسببُ في `App\Support\HrSettings`.
        */
        $req = fn (string $field) => \App\Support\HrSettings::employeeFieldRequired($field);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('employees.index')
                <a href="{{ route('employee.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-slash"></i> إلغاء
                </a>
            @endpermission
            <button type="submit" form="employee_form" class="btn btn-primary" id="submitBtn">
                <i class="feather icon-save"></i> حفظ الموظف
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'إضافة موظف',
            'icon' => 'user-plus',
            'sub' => 'تسجيل موظف جديد وبياناته الوظيفية والمالية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الموظفون' => route('employee.index'),
                'إضافة' => null,
            ],
        ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-employees.create',
            'title' => 'قبل أن تحفظ الموظف — ما الذي يترتّب على الحفظ؟',
            'lead' => 'حفظُ هذا النموذج لا يُنشئ صفّاً في جدول الموظفين وحده: يترتّب عليه حسابُ دخولٍ وحساباتٌ محاسبيّةٌ ورقمٌ من التسلسل — وبعضُها لا يُستردّ بحذف الموظف.',
            'blocks' => [
                'حسابُ الدخول' => [
                    'يُنشأ حسابٌ ببريد الموظف. والبريدُ لا يتكرّر بين موظفَين ولا مع أيّ حسابٍ قائمٍ في النظام — ولو كان لمديرٍ ليس موظفاً.',
                    'وكلمةُ المرور: ما تكتبه في حقلها، فإن تركته فارغاً فرقمُ الهاتف. و«إرسالُ بيانات الدخول» قناةٌ لا مصدر: يُرسِل المكتوبةَ إن كُتبت، ويولّد عشوائيّةً ويُرسلها إن تُرك الحقلُ فارغاً.',
                    'و«السماح بالدخول» إذنٌ لا حساب: بلا تأشيرٍ عليه يُنشأ الحسابُ ولا يُقبل دخولُه.',
                    'والدورُ الوظيفي يُسنَد إلى الحساب، وهو مصدرُ صلاحياته كلِّها — فبلا دورٍ يدخل الموظفُ إلى «ليس لديك صلاحية» في كل شاشة.',
                ],
                'الأثرُ المحاسبيّ' => [
                    'يُنشأ للموظف حسابُ ذمّةٍ وحسابُ سلفٍ تحت الحساب الأب المختار — وهما ما يُقيَّد عليهما راتبُه وسلفُه لاحقاً.',
                    'ومن كان نوعُه «مستخدم» جاز أن تُنشأ له خزينةٌ ومستودعٌ افتراضيّان، ولكلٍّ منهما حسابُه في الشجرة.',
                ],
                'الرقمُ والفرع' => [
                    'رقمُ الموظف معاينةٌ لا إدخال: يُصرَف من «تسلسل أرقام المستندات» لحظةَ الحفظ، فمن فتح الشاشة وتركها لم يستهلك رقماً.',
                    'والفرعُ الأساسي يُلحَق بكلّ آبائه في الشجرة تلقائياً، فلا حاجة إلى اختيارهم واحداً واحداً.',
                ],
                'ما يبقى بعد الحذف' => [
                    'الأرشفةُ تُخفي الموظفَ ولا تحذف حسابَ دخوله ولا حساباتِه في الشجرة ولا رقمَه من التسلسل. فالتراجعُ عن إضافةٍ خاطئةٍ ليس تنظيفاً كاملاً.',
                ],
            ],
            'facts' => [
                'بريدُ الموظف هو بريدُ دخوله ولا يتكرّر',
                '«إرسال بيانات الدخول» يُرسِل الكلمة المكتوبة إن كُتبت',
                'رقمُ الموظف يُستهلَك عند الحفظ',
            ],
        ])

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @permission('employees.create')
            <form id="employee_form" action="{{ route('employee.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
    
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> معلومات الموظف</h6>
                    </div>
                    <div class="card-body">
                        {{--
                            رقمُ الموظف معاينةٌ لا إدخال: يُولَّد عند الحفظ من
                            «الترقيم المتسلسل»، ولا يُقرأ من الطلب — وإلّا مَلَك
                            المستخدمُ فرضَ أيّ رقم على التسلسل. ولا `name` له
                            لهذا السبب، فلا يُرسَل أصلاً.
                        --}}
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="employee_code">رقم الموظف</label>
                                <input type="text" class="form-control" id="employee_code"
                                    value="{{ $nextEmployeeCode ?: 'يُولَّد عند الحفظ' }}" readonly disabled>
                                <small class="text-muted">من إعدادات الترقيم المتسلسل</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="first_name" class="stk-req">الاسم الأول</label>
                                <input type="text" name="first_name" class="form-control" placeholder="أدخل الاسم الأول" value="{{ old('first_name') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="middle_name">الاسم الأوسط</label>
                                <input type="text" name="middle_name" class="form-control" placeholder="أدخل الاسم الأوسط" value="{{ old('middle_name') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="last_name">اللقب</label>
                                <input type="text" name="nickname" class="form-control" placeholder="أدخل اللقب" value="{{ old('nickname') }}">
                            </div>
                        </div>
    
                        {{--
                            حقلٌ واحدٌ فقط للمرفقات — لا حقل صورةٍ مستقلّ بجانبه.
                            صورةُ الموظف (الشخصية، الهوية، وغيرها) تُرفع من هنا
                            كأيّ مرفقٍ آخر.
                        --}}
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label class="stk-req">إضافة مرفق</label>
                                <div class="file-upload-zone" id="file-upload-zone">
                                    <input type="file" name="attachments[]" id="attachments" class="file-upload-input" multiple accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="file-upload-content">
                                        <div class="file-upload-icon"><i class="fa fa-cloud-upload"></i></div>
                                        <h5>اسحب الملفات هنا أو انقر للاختيار</h5>
                                        <p>صور الموظف، الهوية، العقد، وأي مستندات أخرى — (PDF، Word، Excel، صور)</p>
                                    </div>
                                </div>
                                <div id="selected-files-list" class="selected-files-list"></div>
                            </div>
                        </div>
    
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="notes">الملاحظات</label>
                                <textarea name="notes" class="form-control" id="notes" rows="2" placeholder="أدخل ملاحظات">{{ old('notes') }}</textarea>
                            </div>
                        </div>
    
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="email" class="{{ $req('email') ? 'stk-req' : '' }}">البريد الإلكتروني</label>
                                <input @required($req('email')) type="email" name="email" class="form-control" id="email" placeholder="example@email.com" value="{{ old('email') }}">
                            </div>
    
                            <div class="form-group col-md-4">
                                <label for="manager" class="{{ $req('direct_manager_id') ? 'stk-req' : '' }}">المدير المباشر</label>
                                <select class="form-control select2" name="direct_manager_id" data-placeholder="اختر المدير المباشر">
                                    <option value="">اختر المدير المباشر</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ old('direct_manager_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($employees ?? [])"
                                    route="employee.create"
                                    permission="employees.create"
                                    :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                                    label="موظفاً"
                                    hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                            </div>
    
                            <div class="form-group col-md-2">
                                <label for="employee_type">نوع الموظف</label>
                                <select name="employee_type" class="form-control">
                                    <option value="1" {{ old('employee_type') == 1 ? 'selected' : '' }}>موظف</option>
                                    <option value="2" {{ old('employee_type') == 2 ? 'selected' : '' }}>مستخدم</option>
                                </select>
                            </div>
    
                            <div class="form-group col-md-2">
                                <label for="status" class="stk-req">الحالة</label>
                                <select name="status" class="form-control" id="status" required>
                                    <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>نشط</option>
                                    <option value="2" {{ old('status') == 2 ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>
    
                        {{-- حسابُ الدخول بصلاحيتيه: فتحُ الدخول (`employee_accounts.access`)
                             وكلمةُ المرور وإرسالُها (`employee_accounts.password`). ومن لا يملكهما
                             يُضيف الموظفَ ودخولُه مغلق — والخادمُ يُسقط الحقلين كذلك. --}}
                        @if (canAny(['employee_accounts.access', 'employee_accounts.password']))
                        <div class="stk-choice d-flex flex-wrap gap-4 pb-2 mb-3">
                            @permission('employee_accounts.access')
                            <div class="d-flex align-items-center gap-2">
                                <input name="allow_system_access" class="form-check-input mt-0" type="checkbox" id="allow_access">
                                <label class="form-check-label mb-0" for="allow_access">السماح بالدخول الى النظام</label>
                            </div>
                            @endpermission
                            @permission('employee_accounts.password')
                            <div class="d-flex align-items-center gap-2">
                                <input name="send_credentials" class="form-check-input mt-0" type="checkbox" id="send_data">
                                <label class="form-check-label mb-0" for="send_data">إرسال بيانات الدخول عبر البريد الإلكتروني</label>
                            </div>
                            @endpermission
                        </div>
                        @endif

                        {{-- كلمةُ مرور الدخول: تُكتب هنا فتصير كلمةَ الحساب.
                             ولا تُملأ من المخزون: مخزَّنةٌ مُعمّاةً لا تُقرأ. --}}
                        @permission('employee_accounts.password')
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="password">كلمة المرور</label>
                                <input type="password" name="password" id="password" class="form-control"
                                       autocomplete="new-password" placeholder="8 أحرف على الأقل">
                                <small class="stk-help">اختياريّة. إن تُركت فارغةً كانت كلمةُ المرور رقمَ الهاتف.</small>
                                <small class="stk-help d-none text-warning" id="password_send_note">
                                    «إرسال بيانات الدخول» مُفعَّل: الكلمةُ التي تكتبها هنا هي المعتمدة وهي التي تُرسَل للموظف. واتركِ الحقل فارغاً لتُولَّد كلمةٌ عشوائيّةٌ وتُرسَل.
                                </small>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="password_confirmation">تأكيد كلمة المرور</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                       class="form-control" autocomplete="new-password" placeholder="أعِد كتابتها">
                            </div>
                        </div>
                        @endpermission
    
    
                        @php
                            $employeeTypeValue = (int) old('employee_type', 1);
                            $treasuryStorehouseHidden = $employeeTypeValue === 2 ? '' : 'd-none';
                            $treasuryCheckedCreate = (bool) old('create_default_treasury');
                            $storehouseCheckedCreate = (bool) old('create_default_storehouse');

                            // شجرة الفروع كاملة (منطقة ← فرع ← مجموعة ← منفذ) كما في شاشة العميل
                            $rootBranches = $branches->where('parent_id', null)->values();
                            $childrenByRoot = $branches->filter(fn ($b) => $b->parent_id !== null)->groupBy('root_id');
                            $oldBranchIds = array_map('intval', (array) old('branch_ids', []));
                            $oldAllowed = array_map('intval', (array) old('allowed_branches', []));
                        @endphp
                        <div class="ts-card {{ $treasuryStorehouseHidden }}" id="treasury-storehouse-card">
                            <div class="stk-section__head">
                                <i class="fa fa-cubes"></i>
                                <h6 class="stk-section__title">الخزينة والمستودع الافتراضي للمستخدم</h6>
                            </div>
                            <div class="info-banner">
                                <i class="feather icon-info"></i>
                                يظهر هذا القسم عند اختيار نوع «مستخدم». عند التفعيل يتم إنشاء خزينة و/أو مستودعات وربطها بهذا المستخدم بحساباتها المحاسبية.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="ts-panel {{ $treasuryCheckedCreate ? 'is-active' : '' }}" id="treasury-panel">
                                        <div class="ts-panel-title"><i class="fa fa-money"></i> خزينة افتراضية</div>
                                        <small>أنشئ خزينة جديدة وحساباً محاسبياً مرتبطاً بها للمستخدم.</small>
                                        <label class="stk-switch" for="create_default_treasury">
                                            <input type="checkbox" id="create_default_treasury" name="create_default_treasury" value="1" {{ $treasuryCheckedCreate ? 'checked' : '' }}>
                                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                            <span class="stk-switch__label">
                                                <span class="stk-switch__title">تفعيل إنشاء الخزينة</span>
                                            </span>
                                        </label>
                                        <div id="default-treasury-fields" class="mt-2 {{ $treasuryCheckedCreate ? '' : 'd-none' }}">
                                            <label for="default_treasury_name">اسم الخزينة</label>
                                            <input type="text" class="form-control" id="default_treasury_name" name="default_treasury_name" value="{{ old('default_treasury_name') }}" placeholder="مثال: خزينة محمد">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ts-panel {{ $storehouseCheckedCreate ? 'is-active' : '' }}" id="storehouse-panel">
                                        <div class="ts-panel-title"><i class="fa fa-cube"></i> مستودعات افتراضية</div>
                                        <small>أضف مستودعاً واحداً أو أكثر، ولكلٍ منها سيُنشأ حساب محاسبي مرتبط.</small>
                                        <label class="stk-switch" for="create_default_storehouse">
                                            <input type="checkbox" id="create_default_storehouse" name="create_default_storehouse" value="1" {{ $storehouseCheckedCreate ? 'checked' : '' }}>
                                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                            <span class="stk-switch__label">
                                                <span class="stk-switch__title">تفعيل إنشاء المستودعات</span>
                                            </span>
                                        </label>
                                        <div id="default-storehouse-fields" class="mt-2 {{ $storehouseCheckedCreate ? '' : 'd-none' }}">
                                            <label>أسماء المستودعات</label>
                                            <div id="storehouse-names-container">
                                                @if(old('default_storehouse_names'))
                                                    @foreach(old('default_storehouse_names') as $name)
                                                        <div class="input-group mb-2">
                                                            <input type="text" class="form-control default-storehouse-name" name="default_storehouse_names[]" value="{{ $name }}" placeholder="اسم المستودع">
                                                            <div class="input-group-append">
                                                                <button type="button" class="btn btn-outline-danger remove-storehouse-name"><i class="feather icon-trash-2"></i></button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="input-group mb-2">
                                                        <input type="text" class="form-control default-storehouse-name" name="default_storehouse_names[]" placeholder="اسم المستودع">
                                                    </div>
                                                @endif
                                            </div>
                                            <button type="button" class="btn btn-outline-success btn-sm" id="add-storehouse-name">
                                                <i class="feather icon-plus"></i> إضافة مستودع آخر
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        {{--
                            لا حقل لغة عرضٍ هنا: لغةُ الموظف تتبع اللغة الافتراضية
                            المحددة من إعدادات النظام (لغات النظام)، ولا تُختار من
                            جديد لكل موظف.
                        --}}
                        <div class="row mt-3">
                            @permission('employee_accounts.roles')
                            <div class="form-group col-md-6">
                                <label for="Job_role_id" class="{{ $req('Job_role_id') ? 'stk-req' : '' }}">الدور الوظيفي</label>
                                {{-- الدورُ يُختار ولا يُفترَض: بلا خيارٍ فارغٍ أوّل كان
                                     المتصفّح يُعلّم أوّلَ الأدوار في القائمة (وهو «admin»
                                     غالباً) فيُرسَل بلا أن يقصده أحد — والدورُ مصدرُ
                                     صلاحيات حساب الدخول كلِّها. --}}
                                <select name="Job_role_id" id="Job_role_id" class="form-control" @required($req('Job_role_id'))>
                                    <option value="" disabled {{ old('Job_role_id') ? '' : 'selected' }}>-- اختر الدور الوظيفي --</option>
                                    @foreach($job_roles as $job_role)
                                        <option value="{{ $job_role->id }}" {{ old('Job_role_id') == $job_role->id ? 'selected' : '' }}>{{ $job_role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @else
                            <div class="form-group col-md-6">
                                <label>الدور الوظيفي</label>
                                <p class="form-control-plaintext stk-help mb-0">يُسنِده مَن يملك «إدارة أدوار الموظفين» — لا يتغيّر من هنا.</p>
                            </div>
                            @endpermission
                            <div class="form-group col-md-6">
                                <label for="branch_ids" class="{{ $req('branch_id') ? 'stk-req' : '' }}">الفروع</label>
                                {{-- بلا required: عنصرٌ يخفيه select2 لا يمكن للمتصفح عرض رسالة تحقّقه --}}
                                <select class="form-control select2" name="branch_ids[]" id="branch_ids" multiple data-placeholder="-- اختر من شجرة الفروع --">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                            data-is-main="{{ $branch->is_main ? 1 : 0 }}"
                                            data-root-id="{{ $branch->root_id ?? $branch->id }}"
                                            {{ in_array((int) $branch->id, $oldBranchIds, true) ? 'selected' : '' }}>
                                            {{ $branch->tree_label ?? $branch->name }}{{ !empty($branch->type_label) ? ' — '.$branch->type_label : '' }}{{ $branch->is_main ? ' (الفرع الرئيسي)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- `employees.branch_id` و`users.branch_id` عمودان مفردان يقرأهما
                                     نصفُ النظام، فيبقى واحدٌ من المختارة أساسياً — وهو أوّلُها في
                                     ترتيب القائمة. يُعلَن هنا صراحةً كي لا يُخمَّن. --}}
                                <small class="stk-help">اختر منطقة أو فرعاً أو مجموعة منافذ أو منفذاً من الشجرة — اكتب للبحث. يمكن اختيار أكثر من عقدة، والافتراضي عند الدخول: <strong id="primary_branch_hint">—</strong></small>
                                <x-create-first
                                    :count="count($branches ?? [])"
                                    route="branches.create"
                                    label="فرعاً"
                                    hint="الفروع تُدار في وحدة الفروع، ولا فرع مسجَّل بعد." />
                            </div>
                        </div>
    
                        <div class="row">
                            <div class="form-group col-md-12 mb-0">
                                <label class="d-block">المواقع التابعة للفروع المختارة</label>
                                <small class="stk-help mb-1">العقد المعلّقة تحت كل فرع مختار (منطقة / مجموعة عملاء / منفذ بيع / فرع فرعي).</small>
                                <div class="branches-box">
                                    @foreach ($rootBranches as $root)
                                        @php $rootChildren = $childrenByRoot->get($root->id, collect()); @endphp
                                        @if ($rootChildren->isNotEmpty())
                                            <div class="location-group" data-root-id="{{ $root->id }}" style="display: none;">
                                                <div class="location-group__head">
                                                    <span><i class="feather icon-git-branch"></i> {{ $root->name }}</span>
                                                    <a href="#" class="location-group__toggle" data-root-id="{{ $root->id }}">تحديد الكل</a>
                                                </div>
                                                <div class="row m-0">
                                                    @foreach ($rootChildren as $branch)
                                                        <div class="col-lg-6 col-md-12 py-1 border-bottom">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input allowed-branch-cb" id="allowed_branch_{{ $branch->id }}" name="allowed_branches[]" value="{{ $branch->id }}"
                                                                    data-root-id="{{ $root->id }}"
                                                                    {{ in_array((int) $branch->id, $oldAllowed, true) ? 'checked' : '' }}>
                                                                <label class="custom-control-label text-break" for="allowed_branch_{{ $branch->id }}">{{ $branch->tree_label ?? $branch->name }}</label>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                    <div class="location-empty text-muted text-center py-3">اختر فرعاً أو أكثر أعلاه لعرض مواقعه.</div>
                                </div>
                            </div>
                        </div>
    
                        @php
                            $selectedExistingAdvance = old('existing_advance_account_id');
                        @endphp
                        <div class="ts-card">
                            <div class="stk-section__head">
                                <i class="feather icon-git-branch"></i>
                                <h6 class="stk-section__title">الحساب الأب لحساب السلف</h6>
                            </div>
                            <div class="info-banner">
                                <i class="feather icon-info"></i>
                                اختر الحساب الذي سيكون أباً لحساب سلف هذا الموظف. سيُنشأ حساب سلف فرعي للموظف تحت هذا الحساب.
                            </div>
                            <div class="ts-panel is-active">
                                <div class="ts-panel-title"><i class="feather icon-link"></i> الحساب الأب</div>
                                <small>حساب سلف الموظف سيُنشأ كحساب فرعي (ابن) تحت الحساب المختار.</small>
                                <select name="existing_advance_account_id" id="existing_advance_account_id" class="form-control select2">
                                    <option value="">-- افتراضي (سلف الموظفين) --</option>
                                    @foreach(($existing_advance_accounts ?? []) as $acc)
                                        <option value="{{ $acc->id }}" {{ (string) $selectedExistingAdvance === (string) $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
    
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="fa fa-id-card-o"></i> معلومات شخصية</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-3">
                                <label for="dob" class="{{ $req('date_of_birth') ? 'stk-req' : '' }}">تاريخ الميلاد</label>
                                <input @required($req('date_of_birth')) type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="type">النوع</label>
                                <select class="form-control" name="gender">
                                    <option value="1" {{ old('gender') == 1 ? 'selected' : '' }}>ذكر</option>
                                    <option value="2" {{ old('gender') == 2 ? 'selected' : '' }}>انثى</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="nationalityStatus">حالة المواطنة</label>
                                <select class="form-control" name="nationality_status">
                                    <option selected disabled>من فضلك اختر</option>
                                    <option value="1" {{ old('nationality_status') == 1 ? 'selected' : '' }}>مواطن</option>
                                    <option value="2" {{ old('nationality_status') == 2 ? 'selected' : '' }}>مقيم</option>
                                    <option value="3" {{ old('nationality_status') == 3 ? 'selected' : '' }}>زائر</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="country" class="{{ $req('country') ? 'stk-req' : '' }}">البلد</label>
                                <select class="form-control select2" name="country">
                                    <option value="" disabled selected>اختر البلد</option>
                                    @php
                                        $arabic_countries = [
                                            1 => 'المملكة العربية السعودية',
                                            3 => 'الكويت',
                                            18 => 'السودان',
                                            4 => 'قطر',
                                            13 => 'اليمن',
                                            5 => 'البحرين',
                                            6 => 'سلطنة عمان',
                                            7 => 'مصر',
                                            8 => 'الأردن',
                                            9 => 'لبنان',
                                            10 => 'سوريا',
                                            11 => 'العراق',
                                            12 => 'فلسطين',
                                            2 => 'الإمارات العربية المتحدة',
                                            14 => 'الجزائر',
                                            15 => 'المغرب',
                                            16 => 'تونس',
                                            17 => 'ليبيا',
                                            19 => 'موريتانيا',
                                            20 => 'جيبوتي',
                                            21 => 'الصومال',
                                            22 => 'جزر القمر',
                                        ];
                                    @endphp
                                    @foreach ($arabic_countries as $key => $country)
                                        <option value="{{ $key }}" {{ old('country') == $key ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
    
                @include('hr::employee.partials.gosi_fields', ['employee' => null])

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-phone"></i> معلومات تواصل</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="mobile_number" class="{{ $req('mobile_number') ? 'stk-req' : '' }}">رقم الجوال</label>
                                <input @required($req('mobile_number')) type="text" class="form-control" name="mobile_number" value="{{ old('mobile_number') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="phone_number" class="{{ $req('phone_number') ? 'stk-req' : '' }}">رقم الهاتف</label>
                                <input @required($req('phone_number')) type="text" class="form-control" name="phone_number" value="{{ old('phone_number') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="personalEmail" class="{{ $req('personal_email') ? 'stk-req' : '' }}">البريد الإلكتروني الشخصي</label>
                                <input @required($req('personal_email')) type="email" class="form-control" name="personal_email" value="{{ old('personal_email') }}">
                            </div>
                        </div>

                        {{-- جهةُ الطوارئ: لا حقلَ كان يُرجَع إليه إن وقع مكروه --}}
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="{{ $req('emergency_contact_name') ? 'stk-req' : '' }}">اسم جهة الطوارئ</label>
                                <input @required($req('emergency_contact_name')) type="text" class="form-control" name="emergency_contact_name"
                                    value="{{ old('emergency_contact_name') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="{{ $req('emergency_contact_phone') ? 'stk-req' : '' }}">هاتف جهة الطوارئ</label>
                                <input @required($req('emergency_contact_phone')) type="text" class="form-control" name="emergency_contact_phone"
                                    value="{{ old('emergency_contact_phone') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="{{ $req('emergency_contact_relation') ? 'stk-req' : '' }}">صلة القرابة</label>
                                <input @required($req('emergency_contact_relation')) type="text" class="form-control" name="emergency_contact_relation"
                                    value="{{ old('emergency_contact_relation') }}">
                            </div>
                        </div>

                        {{--
                            البياناتُ البنكية بياناتُ دفع — تُعرَض بصلاحيتها وحدها.
                            وإخفاءُ الحقول هنا ليس زينة: لو عُرضت فارغةً لمن لا يراها
                            لَمَحا حفظُ النموذج حسابَ الموظف. والمتحكّم يُسقطها من
                            الحمولة كذلك، فلا يكتبها طلبٌ مصوغٌ يدوياً.
                        --}}
                        @permission('employees.sensitive')
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="{{ $req('bank_name') ? 'stk-req' : '' }}">البنك</label>
                                    <input @required($req('bank_name')) type="text" class="form-control" name="bank_name"
                                        value="{{ old('bank_name') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="{{ $req('bank_account_holder') ? 'stk-req' : '' }}">اسم صاحب الحساب</label>
                                    <input @required($req('bank_account_holder')) type="text" class="form-control" name="bank_account_holder"
                                        value="{{ old('bank_account_holder') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="{{ $req('iban') ? 'stk-req' : '' }}">الآيبان (IBAN)</label>
                                    <input @required($req('iban')) type="text" class="form-control @error('iban') is-invalid @enderror" name="iban" maxlength="42" dir="ltr"
                                        placeholder="SA03 8000 0000 6080 1016 7519" autocomplete="off"
                                        value="{{ old('iban') }}">
                                    <small class="text-muted">يُستعمل في التحويل البنكي للرواتب — اكتبه أو الصقه بالفراغات أو بدونها</small>
                                    @error('iban')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endpermission
                    </div>
                </div>
    
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> العنوان الحالي</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="address1" class="{{ $req('current_address_1') ? 'stk-req' : '' }}">عنوان 1</label>
                                <input @required($req('current_address_1')) type="text" class="form-control" name="current_address_1" value="{{ old('current_address_1') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="address2">عنوان 2</label>
                                <input type="text" class="form-control" name="current_address_2" value="{{ old('current_address_2') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="city" class="{{ $req('city') ? 'stk-req' : '' }}">المدينة</label>
                                <input @required($req('city')) type="text" class="form-control" name="city" value="{{ old('city') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="region" class="{{ $req('region') ? 'stk-req' : '' }}">المنطقة</label>
                                <input @required($req('region')) type="text" class="form-control" name="region" value="{{ old('region') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="postalCode" class="{{ $req('postal_code') ? 'stk-req' : '' }}">الرمز البريدي</label>
                                <input @required($req('postal_code')) type="text" class="form-control" name="postal_code" value="{{ old('postal_code') }}">
                            </div>
                        </div>
                    </div>
                </div>
    
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-briefcase"></i> معلومات وظيفة</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="jobTitle" class="{{ $req('job_title_id') ? 'stk-req' : '' }}">المسمى الوظيفي</label>
                                <select @required($req('job_title_id')) class="form-control" name="job_title_id">
                                    <option selected value="">اختر المسمى الوظيفي</option>
                                    @foreach ($job_titles as $job_title)
                                        <option value="{{ $job_title->id }}" {{ old('job_title_id') == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($job_titles ?? [])"
                                    route="JobTitles.create"
                                    permission="job_titles.create"
                                    :plan="\App\Constants\planFeatures\HrPlanFeatures::JOB_TITLES"
                                    label="مسمّى وظيفياً"
                                    hint="المسمّيات تُعرَّف في الهيكل التنظيمي، ولا مسمّى بعد." />
                            </div>
                            <div class="form-group col-md-4">
                                <label for="department" class="{{ $req('department_id') ? 'stk-req' : '' }}">القسم</label>
                                <select @required($req('department_id')) class="form-control" name="department_id">
                                    <option disabled selected value="">اختر قسم</option>
                                    @foreach ($departments as $department )
                                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($departments ?? [])"
                                    route="department.create"
                                    permission="departments.create"
                                    :plan="\App\Constants\planFeatures\HrPlanFeatures::DEPARTMENTS"
                                    label="قسماً"
                                    hint="الأقسام جزءٌ من الهيكل التنظيمي، ولا قسم مسجَّل بعد." />
                            </div>
                            <div class="form-group col-md-4">
                                <label class="{{ $req('job_level_id') ? 'stk-req' : '' }}">المستوى الوظيفي</label>
                                <select @required($req('job_level_id')) class="form-control" name="job_level_id">
                                    <option selected value="">اختر المستوى الوظيفي</option>
                                    @foreach ($job_levels as $job_level)
                                        <option value="{{ $job_level->id }}" {{ old('job_level_id') == $job_level->id ? 'selected' : '' }}>{{ $job_level->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="{{ $req('job_type_id') ? 'stk-req' : '' }}">نوع وظيفة</label>
                                <select @required($req('job_type_id')) class="form-control" name="job_type_id">
                                    <option selected value="">اختر نوع وظيفة</option>
                                    @foreach ($job_types as $job_type)
                                        <option value="{{ $job_type->id }}" {{ old('job_type_id') == $job_type->id ? 'selected' : '' }}>{{ $job_type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="{{ $req('hire_date') ? 'stk-req' : '' }}">تاريخ الالتحاق</label>
                                <input @required($req('hire_date')) type="date" class="form-control" name="hire_date" value="{{ old('hire_date') }}">
                            </div>
                            {{--
                                لا حقل وردية هنا: الاختيار الوحيد للوردية أصبح ضمن
                                كارد «معلومات الحضور» أدناه (`shift_id`)، فلا يتكرّر.
                            --}}
                            <div class="form-group col-md-4">
                                <label for="daily_qty_limit" class="text-danger">الحد اليومي لكمية التبديل</label>
                                <input type="number" class="form-control" name="daily_qty_limit"
                                    value="{{ old('daily_qty_limit') }}" placeholder="مثال: 50">
                                <small class="stk-help">أقصى عدد قطع مسموح بتبديلها يومياً.</small>
                            </div>
    
                            @php
                                $hasCustomFinancial = old('custom_financial_month') || old('custom_financial_day');
                                $financialMode = old('financial_date_mode', $hasCustomFinancial ? 'custom' : 'default');
                            @endphp
                            <div class="form-group col-md-12">
                                <div class="stk-choice d-flex flex-wrap gap-4">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input financial-date-mode" type="radio" id="defaultDate" name="financial_date_mode" value="default" {{ $financialMode === 'default' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="defaultDate">استخدام التاريخ المالي الافتراضي</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input financial-date-mode" type="radio" id="customDate" name="financial_date_mode" value="custom" {{ $financialMode === 'custom' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="customDate">تاريخ مالي مخصص</label>
                                    </div>
                                </div>
                            </div>
    
                            <div class="form-group col-md-6 custom-financial-field">
                                <label for="month">الشهر</label>
                                <select class="custom-select" id="month" name="custom_financial_month">
                                    <option value="" disabled selected>اختر الشهر</option>
                                    @php
                                        $months = [
                                            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                                            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                                            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
                                        ];
                                    @endphp
                                    @foreach ($months as $key => $value)
                                        <option value="{{ $key }}" {{ old('custom_financial_month') == $key ? 'selected' : '' }}>{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
    
                            <div class="form-group col-md-6 custom-financial-field">
                                <label for="day">يوم</label>
                                <select class="custom-select" id="day" name="custom_financial_day">
                                    <option value="" disabled selected>اختر اليوم</option>
                                    @for ($i = 1; $i <= 31; $i++)
                                        <option value="{{ $i }}" {{ old('custom_financial_day') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
    
                {{-- عقدُ الموظف (اختياري) — يُنشأ مع حفظ الموظف في المعاملة نفسِها --}}
                @include('hr::employee.partials.contract-section')

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="fa fa-calendar-check-o"></i> معلومات الحضور</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="attendancePolicy">سياسة الإجازات</label>
                                <select class="custom-select" id="attendancePolicy">
                                    <option selected>اختر سياسة الإجازات</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="attendanceSettings">معدلات الحضور</label>
                                <select class="custom-select" id="attendanceSettings">
                                    <option selected>اختر قيد الحضور</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="shift_id">ورديات الحضور</label>
                                {{-- خيارُ الوردية الوحيد في الشاشة — تُجلب منه الورديات
                                     المُعرَّفة فعلاً، لا حقلٌ عرضيٌّ ثانٍ يكرّره. --}}
                                <select class="form-control" name="shift_id" id="shift_id">
                                    <option selected value="">اختر وردية</option>
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}" {{ old('shift_id') == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                                    @endforeach
                                </select>
                                <x-create-first
                                    :count="count($shifts ?? [])"
                                    route="custom_shifts.create"
                                    permission="shifts.create"
                                    :plan="\App\Constants\planFeatures\HrPlanFeatures::SHIFTS"
                                    label="وردية"
                                    hint="الورديات تُعرَّف في إعدادات الحضور، ولا وردية بعد." />
                            </div>
                            <div class="form-group col-md-6">
                                <label for="holidayList">قوائم العطلات</label>
                                <select class="custom-select" id="holidayList">
                                    <option selected>اختر قائمة العطلات</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endpermission

    </div>

@endsection

@section('scripts')
    {{-- حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه 10 — نظيرُ App\Rules\SaudiPhone --}}
    <script src="{{ asset('assets/js/saudi-phone.js') }}?v=1"></script>
<script>
    /* حقلا الكلمة مفتوحان مع «إرسال بيانات الدخول»: المكتوبُ يتقدّم في
       الخادم وهو نفسُه ما يُرسَل، فلا كلمةَ تُهمَل بصمت.

       كانا يُعطَّلان متى أُشِّر على الإرسال — لأنّ الإرسال كان يولّد عشوائيّةً
       تُهمِل المكتوب — فامتنع تعديلُ الكلمة يدوياً كلّما أُريد إرسالُها.
       والباقي هنا تنبيهٌ يشرح أيُّ كلمةٍ ستُرسَل. */
    (function () {
        var send = document.getElementById('send_data');
        var pass = document.getElementById('password');
        var note = document.getElementById('password_send_note');

        if (! send || ! pass || ! note) {
            return;
        }

        var sync = function () {
            note.classList.toggle('d-none', ! send.checked);
        };

        send.addEventListener('change', sync);
        pass.addEventListener('input', sync);
        sync();
    })();
</script>
<script>
    $(document).ready(function() {
        // إضافة حقل اسم مستودع جديد
        $('#add-storehouse-name').click(function() {
            var newField = `
                <div class="input-group mb-2">
                    <input type="text" class="form-control default-storehouse-name" name="default_storehouse_names[]" placeholder="اسم المستودع">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-danger remove-storehouse-name"><i class="feather icon-trash-2"></i></button>
                    </div>
                </div>
            `;
            $('#storehouse-names-container').append(newField);
            var $input = $('#storehouse-names-container .default-storehouse-name').last();
            $input.data('autofill', true);
            updateAutoNames();
        });

        // حذف حقل اسم مستودع
        $(document).on('click', '.remove-storehouse-name', function() {
            $(this).closest('.input-group').remove();
        });

        const nameInputs = $('input[name="first_name"], input[name="middle_name"], input[name="nickname"]');
        const $treasuryToggle = $('#create_default_treasury');
        const $storehouseToggle = $('#create_default_storehouse');
        const $treasuryFields = $('#default-treasury-fields');
        const $storehouseFields = $('#default-storehouse-fields');
        const $treasuryInput = $('#default_treasury_name');

        $treasuryInput.data('autofill', $treasuryInput.val().trim() === '');
        $('.default-storehouse-name').each(function() {
            $(this).data('autofill', $(this).val().trim() === '');
        });

        function buildDefaultName(prefix) {
            const parts = [
                $('input[name="first_name"]').val(),
                $('input[name="middle_name"]').val(),
                $('input[name="nickname"]').val()
            ].map(v => v ? v.trim() : '').filter(v => v.length > 0);
            const base = parts.join(' ');
            return prefix + ' ' + (base || 'الموظف');
        }

        function updateAutoNames() {
            if ($treasuryToggle.is(':checked') && $treasuryInput.data('autofill')) {
                $treasuryInput.val(buildDefaultName('خزينة'));
            }
            if ($storehouseToggle.is(':checked')) {
                $('.default-storehouse-name').each(function() {
                    if ($(this).data('autofill')) {
                        $(this).val(buildDefaultName('مستودع'));
                    }
                });
            }
        }

        $treasuryToggle.on('change', function() {
            if ($(this).is(':checked')) {
                $treasuryFields.removeClass('d-none');
                $('#treasury-panel').addClass('is-active');
                if ($treasuryInput.data('autofill')) $treasuryInput.val(buildDefaultName('خزينة'));
            } else {
                $treasuryFields.addClass('d-none');
                $('#treasury-panel').removeClass('is-active');
            }
        });

        $storehouseToggle.on('change', function() {
            if ($(this).is(':checked')) {
                $storehouseFields.removeClass('d-none');
                $('#storehouse-panel').addClass('is-active');
                updateAutoNames();
            } else {
                $storehouseFields.addClass('d-none');
                $('#storehouse-panel').removeClass('is-active');
            }
        });

        nameInputs.on('input', updateAutoNames);
        $treasuryInput.on('input', () => $treasuryInput.data('autofill', false));
        $(document).on('input', '.default-storehouse-name', function() {
            $(this).data('autofill', false);
        });

        /*
            مواقع تحت الفروع المختارة: تظهر مجموعة كلّ شجرة جذرية عندما تُختار
            أيُّ عقدةٍ تنتمي إليها (منطقة/فرع/مجموعة/منفذ). الفرع الرئيسي
            (`is_main`) يفتح كلَّ المجموعات. وما يُخفى تُفرَّغ خاناتُه.
        */
        function filterLocationsBySelectedBranches() {
            var selected = ($('#branch_ids').val() || []).map(String);
            var $selectedOptions = $('#branch_ids option:selected');
            var showAll = $selectedOptions.filter(function () {
                return $(this).data('is-main') == 1;
            }).length > 0;

            var selectedRoots = {};
            $selectedOptions.each(function () {
                selectedRoots[String($(this).data('root-id') || $(this).val())] = true;
            });

            var visibleGroups = 0;
            $('.location-group').each(function () {
                var groupRoot = String($(this).data('root-id') || '');
                var show = showAll || !!selectedRoots[groupRoot] || selected.indexOf(groupRoot) !== -1;
                if (show) {
                    $(this).show();
                    visibleGroups++;
                } else {
                    $(this).hide();
                    $(this).find('.allowed-branch-cb').prop('checked', false);
                }
            });

            $('.location-empty').toggle(visibleGroups === 0);

            var $primary = $selectedOptions.first();
            $('#primary_branch_hint').text($primary.length ? $.trim($primary.text()) : '—');
        }

        $('#branch_ids').on('change', filterLocationsBySelectedBranches);
        filterLocationsBySelectedBranches();

        $(document).on('click', '.location-group__toggle', function (e) {
            e.preventDefault();
            var $group = $(this).closest('.location-group');
            var $boxes = $group.find('.allowed-branch-cb');
            var checkAll = $boxes.filter(':checked').length !== $boxes.length;
            $boxes.prop('checked', checkAll);
            $(this).text(checkAll ? 'إلغاء التحديد' : 'تحديد الكل');
        });

        /*
            الإرسال عبر AJAX — بنفس نمط شاشة سندات الصرف (fetch + JSON) بدل
            التحويل الكامل للصفحة. كان النموذج form-post عادياً: أي خطأ
            (استثناء، فشل تحقّق من الخادم) يعيد تحميل الصفحة كاملة وأحياناً
            يظهر بلا أي رسالة مفهومة. صار الخادم يرد JSON دائماً لطلبات
            AJAX (`$request->ajax() || $request->wantsJson()`)، فتُعرض
            الرسالة الفعلية مهما كان مصدرها.
        */
        $('#employee_form').on('submit', function (e) {
            e.preventDefault();

            var form = this;
            var $form = $(form);
            var $submitBtn = $('#submitBtn');
            var $branch = $('#branch_ids');

            /*
                select2 يُخفي عنصر <select> الأصلي، فحين يكون هذا العنصر
                required ولا قيمة له يرفض المتصفح إرسال النموذج بصمتٍ تام —
                رسالة التحقق الافتراضية لا يمكن عرضها على عنصرٍ مخفي.

                **والحارسُ يتبع الإعداد لا يسبقُه**: كان يطلب الفرعَ دائماً،
                فمن أطفأ إلزاميّةَ الفرع من «إعدادات الموظفين» يُمنَع من الحفظ
                برسالةٍ لا تقابلها قاعدةٌ في الخادم (انظر EmployeeRequest::branchRequirement).
            */
            if (@json(\App\Support\HrSettings::employeeFieldRequired('branch_id')) && ! ($branch.val() || []).length) {
                $branch.next('.select2-container').addClass('border border-danger rounded');
                Swal.fire({
                    icon: 'error',
                    title: 'الفرع مطلوب',
                    text: 'يرجى اختيار فرعٍ واحدٍ على الأقل للموظف قبل الحفظ.',
                    confirmButtonText: 'حسناً',
                });
                $('html, body').animate({ scrollTop: $branch.next('.select2-container').offset().top - 150 }, 400);
                return;
            }

            var originalBtnHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جارٍ الحفظ…');

            fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    },
                })
                .then(function (response) {
                    var contentType = response.headers.get('content-type');

                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    }

                    throw new Error('استجابة غير متوقعة من الخادم');
                })
                .then(function (data) {
                    if (data.success) {
                        var goOn = function () {
                            window.location.href = data.redirect || '{{ route('employee.index') }}';
                        };

                        /*
                            نجاحٌ ناقص: حُفِظ السجلّ ولم يُرسَل بريدُ بيانات
                            الدخول. كان الخادمُ يُرسل `warning` تشرحه بدقّة —
                            «الحسابُ الآن بكلمة مرورٍ عشوائية لم تصل أحداً» —
                            وهذه الدالّةُ تُهملها وتعرض «تمّ» خضراءَ تختفي بعد
                            ثانيتين. فيُقرأ الأمرُ نجاحاً تامّاً، ثمّ لا يدخل
                            الموظفُ بحسابٍ لا يعرف أحدٌ كلمتَه.

                            فمتى وردت لا تُطوى تلقائياً ولا تُغلَق إلا بإقرار.
                        */
                        if (data.warning) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'تمّ الحفظ — مع تنبيه',
                                html: '<div dir="rtl" class="text-end">' +
                                    '<p><strong>' + (data.message || 'تم إضافة الموظف بنجاح.') + '</strong></p>' +
                                    '<p class="text-warning mt-2">' + data.warning + '</p>' +
                                    '</div>',
                                confirmButtonText: 'فهمت',
                                width: '600px',
                            }).then(goOn);
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'تمّ',
                            text: data.message || 'تم إضافة الموظف بنجاح.',
                            timer: 2000,
                            showConfirmButton: false,
                        }).then(goOn);
                        return;
                    }

                    var errorDetails = data.error_details || '';

                    if (!errorDetails && data.errors) {
                        errorDetails = Object.values(data.errors).map(function (messages) {
                            return messages.join('، ');
                        }).join('\n');
                    }

                    if (errorDetails.trim() === (data.message || '').trim()) {
                        errorDetails = '';
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        html: '<div dir="rtl" class="text-end">' +
                            '<p><strong>' + (data.message || 'حدث خطأ أثناء حفظ البيانات') + '</strong></p>' +
                            (errorDetails ? '<p class="text-danger mt-2">' + errorDetails + '</p>' : '') +
                            '</div>',
                        confirmButtonText: 'موافق',
                        width: '600px',
                    });
                })
                .catch(function (error) {
                    console.error(error);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الاتصال',
                        html: '<div dir="rtl" class="text-end">' +
                            '<p>حدث خطأ أثناء إرسال البيانات.</p>' +
                            '<p class="text-danger mt-2">' + error.message + '</p>' +
                            '</div>',
                        confirmButtonText: 'موافق',
                    });
                })
                .finally(function () {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                });
        });

        // إظهار/إخفاء كارد الخزينة والمستودع حسب نوع الموظف
        const $employeeTypeSelect = $('select[name="employee_type"]');
        const $treasuryStorehouseCard = $('#treasury-storehouse-card');

        function syncTreasuryStorehouseCard() {
            var isUser = String($employeeTypeSelect.val()) === '2';
            if (isUser) {
                $treasuryStorehouseCard.removeClass('d-none');
            } else {
                $treasuryStorehouseCard.addClass('d-none');
                $treasuryToggle.prop('checked', false);
                $storehouseToggle.prop('checked', false);
                $treasuryFields.addClass('d-none');
                $storehouseFields.addClass('d-none');
            }
        }

        $employeeTypeSelect.on('change', syncTreasuryStorehouseCard);
        syncTreasuryStorehouseCard();

        // Initial setup
        if ($treasuryToggle.is(':checked')) $treasuryFields.removeClass('d-none');
        if ($storehouseToggle.is(':checked')) $storehouseFields.removeClass('d-none');
        updateAutoNames();

        // ===== منطقة رفع الملفات الاحترافية =====
        (function () {
            var $zone = $('#file-upload-zone');
            var $input = $('#attachments');
            var $list = $('#selected-files-list');
            if (!$zone.length || !$input.length) return;

            var dt = new DataTransfer();

            function fileIcon(name, mime) {
                var ext = (name.split('.').pop() || '').toLowerCase();
                if ((mime || '').indexOf('image/') === 0 || ['png','jpg','jpeg','gif','webp','bmp','svg'].indexOf(ext) !== -1) {
                    return { cls: 'icon-image', fa: 'fa-image' };
                }
                if (ext === 'pdf') return { cls: 'icon-pdf', fa: 'fa-file-pdf-o' };
                if (['doc','docx'].indexOf(ext) !== -1) return { cls: 'icon-word', fa: 'fa-file-word-o' };
                if (['xls','xlsx','csv'].indexOf(ext) !== -1) return { cls: 'icon-excel', fa: 'fa-file-excel-o' };
                return { cls: 'icon-other', fa: 'fa-file-o' };
            }

            function formatSize(bytes) {
                if (!bytes && bytes !== 0) return '';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1024 / 1024).toFixed(2) + ' MB';
            }

            function render() {
                $list.empty();
                Array.prototype.forEach.call(dt.files, function (file, idx) {
                    var ic = fileIcon(file.name, file.type);
                    var $row = $(
                        '<div class="selected-file">' +
                            '<span class="file-icon ' + ic.cls + '"><i class="fa ' + ic.fa + '"></i></span>' +
                            '<div class="file-info">' +
                                '<div class="file-name"></div>' +
                                '<div class="file-size">' + formatSize(file.size) + '</div>' +
                            '</div>' +
                            '<button type="button" class="remove-file" data-idx="' + idx + '" title="إزالة"><i class="feather icon-x"></i></button>' +
                        '</div>'
                    );
                    $row.find('.file-name').text(file.name);
                    $list.append($row);
                });
            }

            function appendFiles(files) {
                Array.prototype.forEach.call(files, function (f) { dt.items.add(f); });
                try { $input[0].files = dt.files; } catch (e) {}
                render();
            }

            // كل اختيارٍ من نافذة الملفات يستبدل FileList الأصلي بمختاراتِه
            // وحدها؛ فلو رُبط هذا مباشرةً بـ`dt` لمحا كلَّ ما أُضيف قبله —
            // فيبقى آخرُ ملفٍّ فقط مهما اختار المستخدم ثلاثةً على دفعات.
            // الإضافةُ لا الاستبدال هي ما يحفظ التراكم.
            $input.on('change', function () { appendFiles($input[0].files); });

            $list.on('click', '.remove-file', function () {
                var idx = parseInt($(this).data('idx'), 10);
                var newDt = new DataTransfer();
                Array.prototype.forEach.call(dt.files, function (f, i) {
                    if (i !== idx) newDt.items.add(f);
                });
                dt = newDt;
                try { $input[0].files = dt.files; } catch (e) {}
                render();
            });

            ['dragenter', 'dragover'].forEach(function (ev) {
                $zone[0].addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $zone.addClass('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                $zone[0].addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $zone.removeClass('dragover');
                });
            });
            $zone[0].addEventListener('drop', function (e) {
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) appendFiles(files);
            });
        })();

        // التاريخ المالي: تبديل بين الافتراضي والمخصص
        function syncFinancialDateMode() {
            var mode = $('input[name="financial_date_mode"]:checked').val() || 'default';
            var $fields = $('.custom-financial-field');
            var $month = $('#month');
            var $day = $('#day');
            if (mode === 'custom') {
                $fields.show();
                $month.prop('disabled', false);
                $day.prop('disabled', false);
            } else {
                $fields.hide();
                $month.val('').prop('disabled', true);
                $day.val('').prop('disabled', true);
            }
        }
        $(document).on('change', 'input[name="financial_date_mode"]', syncFinancialDateMode);
        syncFinancialDateMode();
    });
</script>
@endsection
