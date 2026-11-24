<?php

namespace App\Http\Requests;

use App\Constants\Banks;
use App\Models\Employee;
use App\Models\User;
use App\Rules\Iban;
use App\Rules\SaudiPhone;
use App\Support\HrSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الفرعُ الأساسي مشتقٌّ من الفروع المختارة، لا حقلٌ مستقلٌّ في النموذج.
     *
     * الشاشةُ صارت تُرسل `branch_ids[]` (اختيارٌ متعدّد)، بينما
     * `employees.branch_id` و`users.branch_id` عمودان مفردان يقرأهما نصفُ
     * النظام (تحديدُ نطاق العملاء، نقاطُ البيع، التقارير). فيُشتقّ أوّلُ
     * المختارة فرعاً أساسياً هنا — في موضعٍ واحدٍ يمرّ منه الإنشاءُ
     * والتعديل — فيبقى `$request->branch_id` صحيحاً لكلّ ما بعده.
     */
    protected function prepareForValidation(): void
    {
        /*
            الآيبان يُطبَّع قبل التحقّق: كان النمطُ يرفض الآيبان المكتوب بفراغات
            (SA03 8000 …) أو المنسوخَ بعلامات اتجاهٍ خفيّة — وهو صحيح. فيُنظَّف
            هنا ويُحفظ بلا فراغات، ويُعرض مجزّأً حيث يلزم (Banks::formatIban).
        */
        // الأرقام تُطبَّع قبل التحقّق: مفتاحُ الدولة يُردّ صفراً والأرقامُ
        // الهندية تُحوَّل — انظر SaudiPhone
        SaudiPhone::normalizeRequest($this, ['mobile_number', 'phone_number', 'emergency_contact_phone']);

        if ($this->filled('iban')) {
            $this->merge(['iban' => Banks::normalize($this->input('iban'))]);
        }

        $branchIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input('branch_ids', []))
        )));

        if (! $branchIds) {
            return;
        }

        $this->merge([
            'branch_ids' => $branchIds,
            'branch_id' => $branchIds[0],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // معلومات الموظف
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'employee_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'notes' => 'nullable|string',
            /*
                بريدُ الموظف هو بريدُ دخوله: `store()` و`update()` يكتبانه في
                `users.email` أيضاً. ففحصُ `employees` وحدَه لا يكفي —

                  • بريدٌ يملكه مستخدمٌ ليس موظفاً (مديرُ نظامٍ مثلاً) يعبر
                    فحصَ `employees` ثم يصطدم بفهرس `users_email_unique`،
                    فيعود للمستخدم خطأُ SQL خام لا رسالةٌ مفهومة.
                  • و`update()` لم يكن يفحص `users` إطلاقاً.

                فالفحصان معاً هنا، في موضعٍ واحدٍ يمرّ منه الإنشاءُ والتعديل.
                و`ignore` في الثاني على حساب هذا الموظف نفسه لا على رقمه:
                مفتاحُ `users` غيرُ مفتاح `employees`.

                و`'email'` مضافةٌ كذلك: كانت في `store()` وحدها، فالتعديلُ
                كان يقبل «abc» بريداً — ثمّ لا يصل الموظفَ شيء.
            */
            /*
                وإلزاميّتُه صارت إعداداً (`require_email`، مشغَّلٌ افتراضاً كما
                كان). مطفأً يبقى الفحصُ على الصيغة والفرادة وحدهما — وموظفٌ
                بلا بريدٍ لا يستطيع الدخول ولا تصله بياناتُ حسابه، وذلك هو
                المعنى: `allow_system_access` مفتاحٌ مستقلٌّ عن وجود البريد.
                والعمودان صارا يقبلان الفراغ في
                `2026_10_01_170000_make_employee_email_and_role_nullable`.
            */
            'email' => $this->coreFieldRules('email', [
                'email',
                Rule::unique('employees', 'email')->ignore($this->id),
                Rule::unique('users', 'email')->ignore($this->loginAccountId()),
            ]),
            /*
                كلمةُ مرور الدخول — اختياريّةٌ دائماً.

                إضافةً: إن تُركت فارغةً بقي ما كان (رقمُ الهاتف، أو عشوائيّةٌ
                تُرسَل بالبريد متى طُلب إرسالُ بيانات الدخول).
                تعديلاً: الفراغُ يُبقي كلمةَ المرور الحالية على حالها — فمن
                يفتح الموظفَ ليصحّح اسماً لا يُعيد تعيين دخوله بلا قصد.

                و`confirmed` تطلب حقل `password_confirmation` معها: كلمةٌ
                تُكتب مرّةً واحدةً وتُخزَّن مُعمّاةً لا سبيل إلى قراءتها
                بعدها، فخطأٌ مطبعيٌّ فيها يُقفل الحساب بلا أن يُعرف السبب.
            */
            // الحدُّ الأدنى للطول من «إعدادات الموظفين» — كان 8 مثبَّتاً
            'password' => ['nullable', 'string', 'min:'.HrSettings::integer('employee_validation_rules', 'password_min_length', 8), 'confirmed'],
            'employee_type' => 'required|string',
            'status' => 'required',
            'gender' => 'nullable|in:1,2',

            /*
                لم تعد الشاشةُ تعرض حقل لغةٍ مستقلّاً — يتبع الموظفُ اللغةَ
                الافتراضية المحددة من إعدادات النظام. تبقى القاعدةُ هنا
                نافذةً لا مطلوبةً حفاظاً على العمود، إن وصلت قيمةٌ من مصدرٍ
                آخر (استيراد، API).
            */
            'language' => 'nullable|string|max:2',
            /*
                دورُ حساب الدخول يمنح صلاحياتِه كلَّها: يختاره حاملُ
                `employee_accounts.roles` وحده، ومن سواه لا يُطلب منه ولا
                يُقبل (يتجاهله المتحكّم فيكتب `null`). وإلزاميّتُه على حامل
                الصلاحية صارت إعداداً (`require_login_role`) — ومطفأةً يُنشأ
                الحسابُ بلا دورٍ فيدخل ولا يرى شيئاً حتى يُسنَد له دور.
            */
            'Job_role_id' => hasPermission('employee_accounts.roles')
                ? $this->coreFieldRules('Job_role_id', ['exists:roles,id'])
                : 'nullable',
            /*
                الفرعُ: إلزاميٌّ عند الإضافة إن كان `require_branch` مشغَّلاً
                (وهو الافتراض كما كان)، وعند التعديل متروكٌ دائماً — واجهةُ
                التعديل تسمح ببقائه كما هو لموظفين قدامى بلا فرع.

                وموظفٌ بلا فرعٍ لا يظهر في شاشةٍ مُنطَقةٍ بالفرع ولا في
                تقريرٍ مبنيٍّ عليه — فالإطفاءُ قرارٌ في نطاق الرؤية لا في
                النموذج وحده.
            */
            'branch_id' => [$this->branchRequirement(), 'exists:branches,id'],
            // الفروعُ المختارة — أوّلُها هو `branch_id` أعلاه (انظر prepareForValidation)
            'branch_ids' => [$this->branchRequirement(), 'array'],
            'branch_ids.*' => 'exists:branches,id',
            // مواقعُ تلك الفروع: العقدُ المعلّقة تحتها في الشجرة
            'allowed_branches' => 'nullable|array',
            'allowed_branches.*' => 'exists:branches,id',
            // 'access_branches_id' => 'nullable|exists:branches,id',

            // معلومات شخصية
            /*
                كالهاتفَين أدناه: إلزاميّتُهما من «إعدادات الموظفين» (مشغَّلةٌ
                افتراضاً كما كانت)، وحين تُشغَّل فهي إلزامٌ عند الإضافة، وعند
                التعديل إلزامٌ إن كان الحقلُ محفوظاً فعلاً وإلا فمتروك —
                عشرةٌ من أحد عشر موظفاً قائماً بلا تاريخ ميلادٍ أو بلدٍ
                محفوظَين، فـ`required` المطلقة كانت تُقفل تعديلَهم كلِّه عن
                أوّل حفظة. انظر {@see fieldRules()}.
            */
            'date_of_birth' => $this->fieldRules('date_of_birth', $this->birthDateFormat()),
            'nationality_status' => 'nullable|string|max:255',
            'country' => $this->fieldRules('country', ['string', 'max:255']),

            // التأمينات الاجتماعية — فارغ = حسب حالة المواطنة
            'gosi_subject' => 'nullable|in:0,1',
            'gosi_number' => 'nullable|string|max:50',
            'gosi_start_date' => 'nullable|date',

            // معلومات تواصل
            /*
                مطلوبان عند الإضافة، ومطلوبان في التعديل **إن كانا مسجَّلَين
                فعلاً** — لا مطلقاً.

                كانا `required` في الحالين، فسجلٌّ قديمٌ لا رقمَ فيه (بذورٌ،
                أو استيراد، أو موظفٌ سبق أن صار الحقلان إلزاميَّين) يُقفَل عن
                التعديل كلِّه: من فتحه ليضيف صورةً أو يصحّح اسماً يُردّ
                بـ«الحقل مطلوب» في حقلين لم يمسّهما، ولا سبيل إلى الحفظ إلا
                باختلاق رقمين.

                وهو عينُ ما عولج به `branch_id` أعلاه ولنفس السبب. والشرطُ
                هنا أدقّ من `nullable` المطلقة: رقمٌ مسجَّلٌ يبقى مطلوباً فلا
                يُمحى بحفظٍ ساهٍ.
            */
            'mobile_number' => $this->contactNumberRules('mobile_number'),
            'phone_number' => $this->contactNumberRules('phone_number'),
            /*
                كانت هذه القاعدة معطَّلةً بتعليق، ولا فهرسَ فريداً على العمود
                في قاعدة البيانات — فالبريد الشخصي يُقبل مكرَّراً بين موظفين
                بلا أيّ اعتراض، وهو أحدُ حقلَي البريد في الشاشة نفسها.
            */
            'personal_email' => $this->fieldRules('personal_email', ['email', Rule::unique('employees', 'personal_email')->ignore($this->id)]),
            'emergency_contact_name' => $this->fieldRules('emergency_contact_name', ['string', 'max:255']),
            'emergency_contact_phone' => $this->fieldRules('emergency_contact_phone', ['string', 'max:20', $this->saudiPhone('emergency_contact_phone')]),
            'emergency_contact_relation' => $this->fieldRules('emergency_contact_relation', ['string', 'max:255']),
            /*
                بياناتٌ بنكيّة تُستعمل في التحويل البنكي للرواتب — كانت بلا أيّ
                قاعدة تحقّقٍ رغم كونها `fillable` ومعروضةً في النموذج، فتُحفظ
                أيَّ نصٍّ بأيّ طول. الآيبانُ بصيغته العامّة (ISO 13616): حرفا
                بلدٍ فرقمَا تحقّقٍ فحتى ٣٠ محرفاً أبجدياً رقمياً.
            */
            'bank_name' => $this->fieldRules('bank_name', ['string', 'max:255']),
            'bank_account_holder' => $this->fieldRules('bank_account_holder', ['string', 'max:255']),
            // الصيغة + طولُ الدولة + خانتا التحقّق (mod-97) — القاعدة نفسها في الخزائن
            'iban' => $this->fieldRules('iban', ['string', 'max:34', new Iban]),

            // العنوان الحالي
            'current_address_1' => $this->fieldRules('current_address_1', ['string', 'max:255']),
            'current_address_2' => 'nullable|string|max:255',
            'city' => $this->fieldRules('city', ['string', 'max:255']),
            'region' => $this->fieldRules('region', ['string', 'max:255']),
            'postal_code' => $this->fieldRules('postal_code', ['string', 'max:10']),

            /*
                معلومات الوظيفة — كانت القواعدُ هنا تفحص `job_title` و`department`
                و`job_level` و`job_type`، ولا نموذجَ يرسل هذه الأسماء إطلاقاً:
                الشاشتان ترسلان `job_title_id` و`department_id` و`job_level_id`
                و`job_type_id` (وهي أعمدةٌ حقيقيّةٌ في `employees.$fillable`)،
                فكانت هذه الحقول الأربعة تُحفظ بلا أيّ تحقّقٍ فعليّ.
            */
            'job_title_id' => $this->fieldRules('job_title_id', ['exists:jop_titles,id']),
            'department_id' => $this->fieldRules('department_id', ['exists:departments,id']),
            'job_level_id' => $this->fieldRules('job_level_id', ['exists:functional_levels,id']),
            'job_type_id' => $this->fieldRules('job_type_id', ['exists:types_jobs,id']),
            // 'branch_id' => 'required|exists:branches,id',
            /*
                `exists:employees,id` وحدها كانت تسمح بثلاث حالات فاسدة:
                موظفٌ مديرٌ لنفسه، حلقةٌ في التسلسل الإداري (مرؤوسٌ يُصبح
                مدير مديره)، ومديرٌ مؤرشَف (`exists` يفحص الجدول الخام
                بلا وعيٍ بـ`SoftDeletes`). الإغلاق الثلاثي هنا يمنعها.
            */
            'direct_manager_id' => $this->fieldRules('direct_manager_id', [
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
                function ($attribute, $value, $fail) {
                    if (! $value || ! $this->id) {
                        return;
                    }

                    if ((int) $value === (int) $this->id) {
                        $fail('لا يمكن أن يكون الموظف مديره المباشر.');

                        return;
                    }

                    $employee = Employee::find($this->id);

                    if ($employee && in_array((int) $value, $employee->getDescendantIds(), true)) {
                        $fail('لا يمكن اختيار موظفٍ يتبع لهذا الموظف (مباشرةً أو عبر من يتبعه) مديراً مباشراً له — هذا يُنشئ حلقة في التسلسل الإداري.');
                    }
                },
            ]),
            'hire_date' => $this->fieldRules('hire_date', $this->hireDateFormat()),
            // 'shift_id' => 'nullable|exists:shifts,id',
            'custom_financial_month' => 'nullable|integer|between:1,12',
            'custom_financial_day' => 'nullable|integer|between:1,31',
            'create_default_treasury' => 'nullable|boolean',
            'default_treasury_name' => 'nullable|string|max:255',
            'create_default_storehouse' => 'nullable|boolean',
            'default_storehouse_names' => 'nullable|array',
            'default_storehouse_names.*' => 'nullable|string|max:255',
            // معرّفاتُ تعييناتِ المستودعات القائمة، بترتيب صفوف الأسماء نفسه —
            // فارغةٌ للصفّ المُضاف حديثاً. عليها تُطابِق المزامنةُ في `update()`.
            'default_storehouse_ids' => 'nullable|array',
            'default_storehouse_ids.*' => 'nullable|integer',
            'credit_limit' => 'nullable|numeric|min:0',
            // أب حساب سلف الموظف — أي حساب نشط في الشجرة (لا حساب السلف نفسه)
            'existing_advance_account_id' => 'nullable|exists:accounts,id',
            'advance_parent_account_id' => 'nullable|exists:accounts,id',
        ];
    }

    /**
     * Customize the error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'حقل الاسم الأول مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يجب أن يكون البريد الإلكتروني صالحًا.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل — لموظفٍ آخر أو لحساب دخولٍ قائم.',
            'personal_email.email' => 'يجب أن يكون البريد الإلكتروني الشخصي صالحًا.',
            'personal_email.unique' => 'البريد الإلكتروني الشخصي مستخدم لموظفٍ آخر.',
            // تظهر وحدها إن شُغّلت «فرادة رقم الجوال» في إعدادات الموظفين
            'mobile_number.unique' => 'رقم الجوال مستخدم لموظفٍ آخر.',
            'date_of_birth.before_or_equal' => 'تاريخ الميلاد يخالف أقل عمر مسموح عند التعيين المحدَّد في إعدادات الموظفين.',
            'hire_date.before_or_equal' => 'تاريخ الالتحاق لا يمكن أن يكون في المستقبل — حسب إعدادات الموظفين.',
            'password.min' => 'كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور لا يطابقها.',
            'gender.in' => 'القيمة المحددة للجنس غير صحيحة.',
            'branch_id' => 'حقل الفرع مطلوب',
            'branch_id.exists' => 'الفرع المحدد غير موجود.',
            'branch_ids.required' => 'يجب اختيار فرعٍ واحدٍ على الأقل.',
            'branch_ids.*.exists' => 'أحد الفروع المحددة غير موجود.',
            'allowed_branches.*.exists' => 'أحد المواقع المحددة غير موجود.',
            'direct_manager_id.exists' => 'المدير المباشر المحدد غير موجود أو تم أرشفته.',
            'custom_financial_month.between' => 'الشهر المالي يجب أن يكون بين 1 و 12.',
            'custom_financial_day.between' => 'اليوم المالي يجب أن يكون بين 1 و 31.',
            'employee_photo.image' => 'يجب أن يكون الملف صورة من نوع JPEG أو PNG أو JPG.',
            'employee_photo.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'iban.regex' => 'صيغة الآيبان (IBAN) غير صحيحة.',
        ];
    }

    /**
     * أسماءُ الحقول كما تُقرأ في الشاشة.
     *
     * بدونها تُركّب الرسائلُ العامّة اسمَ العمود كما هو: «الحقل
     * mobile_number مطلوب» — نصفُه عربيٌّ ونصفُه اسمُ عمودٍ لا يعرفه من
     * يملأ النموذج. و`resources/lang/ar/validation.php` يعرف `phone`
     * و`mobile` لا `phone_number` و`mobile_number`، وتسميتُها هنا تخصّ هذا
     * النموذج وحده فلا تُزاحم بقيّةَ الشاشات.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'الاسم الأول',
            'middle_name' => 'الاسم الأوسط',
            'nickname' => 'اللقب',
            'employee_photo' => 'صورة الموظف',
            'notes' => 'الملاحظات',
            'email' => 'البريد الإلكتروني',
            'personal_email' => 'البريد الإلكتروني الشخصي',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'employee_type' => 'نوع الموظف',
            'status' => 'الحالة',
            'gender' => 'النوع',
            'language' => 'اللغة',
            'Job_role_id' => 'الدور الوظيفي',
            'branch_id' => 'الفرع',
            'branch_ids' => 'الفروع',
            'allowed_branches' => 'المواقع التابعة للفروع',
            'date_of_birth' => 'تاريخ الميلاد',
            'nationality_status' => 'حالة المواطنة',
            'country' => 'البلد',
            'gosi_subject' => 'الخضوع للتأمينات',
            'gosi_number' => 'رقم الاشتراك في التأمينات',
            'gosi_start_date' => 'تاريخ بدء الاشتراك',
            'mobile_number' => 'رقم الجوال',
            'phone_number' => 'رقم الهاتف',
            'emergency_contact_name' => 'اسم جهة الطوارئ',
            'emergency_contact_phone' => 'هاتف جهة الطوارئ',
            'emergency_contact_relation' => 'صلة القرابة',
            'bank_name' => 'البنك',
            'bank_account_holder' => 'اسم صاحب الحساب',
            'iban' => 'الآيبان (IBAN)',
            'current_address_1' => 'العنوان الحالي',
            'current_address_2' => 'العنوان الحالي (تتمّة)',
            'city' => 'المدينة',
            'region' => 'المنطقة',
            'postal_code' => 'الرمز البريدي',
            'job_title_id' => 'المسمّى الوظيفي',
            'department_id' => 'القسم',
            'job_level_id' => 'المستوى الوظيفي',
            'job_type_id' => 'نوع الوظيفة',
            'direct_manager_id' => 'المدير المباشر',
            'hire_date' => 'تاريخ الالتحاق',
            'custom_financial_month' => 'الشهر المالي',
            'custom_financial_day' => 'اليوم المالي',
            'create_default_treasury' => 'تفعيل الخزينة الافتراضية',
            'default_treasury_name' => 'اسم الخزينة الافتراضية',
            'create_default_storehouse' => 'تفعيل المستودعات الافتراضية',
            'default_storehouse_names' => 'أسماء المستودعات الافتراضية',
            'default_storehouse_ids' => 'معرّفات المستودعات الافتراضية',
            'credit_limit' => 'حدّ الائتمان',
            'existing_advance_account_id' => 'الحساب الأب لحساب السلف',
            'advance_parent_account_id' => 'الحساب الأب لحساب السلف',
        ];
    }

    /**
     * قواعدُ حقلٍ إلزاميّتُه من «إعدادات الموظفين والهيكل التنظيمي».
     *
     * مطفأةً: `nullable` ثمّ قواعدُ الصيغة — الصيغةُ تُفحَص دائماً، فرقمٌ
     * خاطئٌ يُرفض سواءٌ كان الحقلُ مطلوباً أم لا.
     * مشغَّلةً: إلزامٌ عند الإضافة، وعند التعديل إلزامٌ إن كان الحقلُ
     * محفوظاً فعلاً — انظر {@see requiredOnCreateOrIfFilled()}.
     *
     * الخريطةُ في `HrSettings::EMPLOYEE_FIELDS`، وهي نفسُها التي يقرؤها
     * نموذجا الإضافة والتعديل لرسم نجمة الإلزام.
     *
     * **والحقلُ المحجوبُ لا يُطلَب**: البياناتُ البنكية
     * (`Employee::sensitiveAttributes()`) لا تُعرَض لمن لا يملك
     * `employees.sensitive`، والمتحكّمُ يُسقطها من حمولته. فإلزامُها عليه
     * يعني شرطاً لا سبيلَ إلى استيفائه — نموذجٌ لا يُحفَظ أبداً.
     *
     * @param  array<int, mixed>  $format
     * @return array<int, mixed>
     */
    private function fieldRules(string $column, array $format): array
    {
        $hidden = in_array($column, Employee::sensitiveAttributes(), true)
            && ! hasPermission('employees.sensitive');

        if ($hidden || ! HrSettings::employeeFieldRequired($column)) {
            return array_merge(['nullable'], $format);
        }

        return $this->requiredOnCreateOrIfFilled($column, $format);
    }

    /**
     * قواعدُ حقلٍ من «حقول الحساب والانتماء» (البريد، الدور) — إلزاميّتُه
     * من الإعداد، وصيغتُه تُفحَص دائماً.
     *
     * تُفصَل عن {@see fieldRules()} لأنّ هذين الحقلين لا يُقاسان على
     * `requiredOnCreateOrIfFilled()`: البريدُ معرّفُ الدخول، فإن كان مسجَّلاً
     * فعلاً بقي مطلوباً كي لا يُمحى بحفظةٍ ساهية — وهو عينُ ما تفعله تلك
     * الدالّة، فتُستعمل هنا كذلك. والدورُ مثلُه.
     *
     * @param  array<int, mixed>  $format
     * @return array<int, mixed>
     */
    private function coreFieldRules(string $column, array $format): array
    {
        if (! HrSettings::employeeFieldRequired($column)) {
            return array_merge(['nullable'], $format);
        }

        return $this->requiredOnCreateOrIfFilled($column, $format);
    }

    /**
     * `required` أو `nullable` للفرع: إلزامٌ عند الإضافة وحدها، وبحسب
     * الإعداد. التعديلُ متروكٌ دائماً — موظفون قدامى بلا فرعٍ محفوظ.
     */
    private function branchRequirement(): string
    {
        if ($this->id) {
            return 'nullable';
        }

        return HrSettings::employeeFieldRequired('branch_id') ? 'required' : 'nullable';
    }

    /**
     * صيغةُ تاريخ الميلاد — وأقلُّ عمرٍ عند التعيين إن ضُبط.
     *
     * القيدُ يسري على كلّ تاريخِ ميلادٍ يُرسَل، لا على المطلوب وحده: من
     * يكتب تاريخاً يخالف الحدَّ يُنبَّه ولو كان الحقلُ اختياريّاً.
     *
     * @return array<int, mixed>
     */
    private function birthDateFormat(): array
    {
        $minAge = HrSettings::integer('employee_validation_rules', 'min_hire_age_years');

        return $minAge
            ? ['date', 'before_or_equal:'.now()->subYears($minAge)->toDateString()]
            : ['date'];
    }

    /**
     * صيغةُ تاريخ الالتحاق — ومنعُ المستقبل إن أُطفئ السماحُ به.
     *
     * @return array<int, mixed>
     */
    private function hireDateFormat(): array
    {
        return HrSettings::flag('employee_validation_rules', 'allow_future_hire_date', true)
            ? ['date']
            : ['date', 'before_or_equal:today'];
    }

    /**
     * قاعدةٌ عامّة: إلزاميٌّ عند الإضافة، وعند التعديل إلزاميٌّ إن كان
     * محفوظاً فعلاً وإلا فمتروك — كي لا يَحبس فراغٌ قديمٌ السجلَّ عن كلّ
     * تعديل. `$format` قواعدُ الصيغة الثابتة التي تُلحَق بعد الإلزام.
     */
    private function requiredOnCreateOrIfFilled(string $column, array $format): array
    {
        if (! $this->id) {
            return array_merge(['required'], $format);
        }

        $stored = Employee::whereKey($this->id)->value($column);

        return array_merge([filled($stored) ? 'required' : 'nullable'], $format);
    }

    /**
     * قواعدُ رقم تواصلٍ: الصيغةُ واحدةٌ دائماً، والإلزامُ يتبع الإعداد.
     *
     * وفرادةُ الجوّال إعدادٌ كذلك: لا فهرسَ فريداً على العمود في قاعدة
     * البيانات، فرقمٌ واحدٌ كان يُقبل لموظفَين. وتبقى مطفأةً افتراضاً لأنّ
     * تشغيلَها على بياناتٍ فيها تكرارٌ قائمٌ يُقفل الصفوفَ المكرَّرة عن
     * التعديل — وعددُ المكرَّر معروضٌ في الشاشة قبل التشغيل.
     */
    private function contactNumberRules(string $column): array
    {
        $format = ['string', 'max:20', $this->saudiPhone($column)];

        if ($column === 'mobile_number' && HrSettings::flag('employee_validation_rules', 'unique_mobile_number')) {
            $format[] = Rule::unique('employees', 'mobile_number')->ignore($this->id);
        }

        return $this->fieldRules($column, $format);
    }

    /**
     * صيغةُ الرقم السعودي (صفرٌ ثم تسعُ خانات). الرقمُ المحفوظُ قبل القاعدة
     * يُقبل ما لم يتغيّر — لنفس علّة {@see requiredOnCreateOrIfFilled()}:
     * لا يُحبس سجلٌّ قديمٌ عن كلّ تعديل بحقلٍ لم يُمسّ.
     */
    private function saudiPhone(string $column): SaudiPhone
    {
        return new SaudiPhone($this->id ? Employee::whereKey($this->id)->value($column) : null);
    }

    /**
     * رقمُ حساب الدخول العائد للموظف قيد التعديل — أو عدمٌ عند الإضافة.
     *
     * يُستثنى من فحص فرادة `users.email` كي لا يشكوَ التعديلُ من بريدٍ
     * لم يتغيّر أصلاً.
     */
    private function loginAccountId(): ?int
    {
        if (! $this->id) {
            return null;
        }

        // اختباراتٌ تبني جدول `employees` وحدها تستدعي `rules()` — بلا `users`.
        if (! Schema::hasTable('users')) {
            return null;
        }

        return User::where('employee_id', $this->id)->value('id');
    }
}
