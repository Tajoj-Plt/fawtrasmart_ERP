<?php

namespace App\Support;

use App\Http\Requests\EmployeeRequest;
use App\Models\HrSetting;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\EmployeeSettingsController;
use Throwable;

/**
 * سجلُّ إعدادات الموظفين والهيكل التنظيمي — المصدرُ الوحيد لتعريفها وقراءتها.
 *
 * **ما الذي كان مثبَّتاً في الشيفرة**
 *
 * إلزاميّةُ حقول ملفّ الموظف كانت قراراً مكتوباً في `EmployeeRequest`: الاسمُ
 * الأول والبريدُ والفرعُ والحالةُ ونوعُ الموظف، ومعها — بلا سببٍ ظاهر —
 * تاريخُ الميلاد والبلدُ والجوّالُ والهاتف. وما سواها `nullable` كلُّه: قسمٌ،
 * ومسمّى، ومستوى، ونوعُ وظيفة، ومديرٌ مباشر، وتاريخُ التحاق، وآيبانٌ تُصرف
 * به الرواتب. فمنشأةٌ لا تحفظ تواريخَ ميلادِ موظفيها لا تستطيع تسجيلهم، ومنشأةٌ
 * لا تقبل موظفاً بلا قسمٍ ولا تاريخِ التحاقٍ لا تستطيع إلزامَه — والقرارُ
 * قرارُها لا قرارَ الشيفرة.
 *
 * والهيكلُ التنظيمي مثلُه: وصفُ القسم اختياريٌّ دائماً، ومديرُه كذلك، واسمُه
 * يُقبل مكرَّراً — لا فهرسَ فريداً عليه في قاعدة البيانات ولا قاعدةَ تحقّق.
 *
 * **ثلاثُ طبقاتٍ لا أكثر**
 *
 *   التعريفُ هنا (الاسمُ والتبويبُ والمفاتيحُ وافتراضاتُها)
 *   ← الشاشةُ في `EmployeeSettingsController` تشتقّ منه تحقّقَها وحفظَها
 *   ← والمستهلكون (`EmployeeRequest`، نماذجُ الهيكل) يقرؤون بـ`flag()`.
 *
 * فإضافةُ إعدادٍ سطرٌ في `definitions()` وسطرٌ في خريطة الشاشة، لا أربعةُ
 * مواضعَ يُنسى أحدُها.
 *
 * **الافتراضاتُ هي السلوكُ القائم**
 *
 * كلُّ افتراضٍ هنا يساوي ما كان النظامُ يفعله قبل الشاشة: `require_date_of_birth`
 * و`require_country` والهاتفان `true` لأنّها كانت `required`، وما سواها `false`
 * لأنّه كان `nullable`. فتشغيلُ الترحيل لا يغيّر شيئاً حتى يغيّره المستخدم.
 *
 * **والبريدُ والفرعُ والدور** في السجلّ كذلك (مجموعة `employee_core_required`)،
 * وإطفاءُ أيٍّ منها أثقلُ من إطفاء حقلٍ عادي: البريدُ معرّفُ الدخول، والفرعُ
 * محورُ نطاق الرؤية، والدورُ مصدرُ الصلاحيات. والعمودان `employees.email`
 * و`employees.Job_role_id` صارا يقبلان الفراغ بالترحيل
 * `2026_10_01_170000_make_employee_email_and_role_nullable` — فبلا ذلك يعبر
 * الطلبُ التحقّقَ ثمّ يرتدّ خطأَ قاعدةِ بياناتٍ خاماً.
 *
 * **ما تُرِك خارج السجلّ عمداً**
 *
 *   • الاسمُ الأول والحالةُ ونوعُ الموظف — إلزاميّةُ هذه بنيويّةٌ لا قاعدةُ
 *     عمل: سجلٌّ بلا اسمٍ لا يُعرَف في أيّ منتقٍ، والحالةُ ونوعُ الموظف
 *     يحكمان مسارَ الحفظ نفسَه (حسابُ الدخول، الخزينة، المستودع). مفتاحُ
 *     إطفاءٍ لها يكسر التسجيل لا يُخفّف شرطاً.
 *   • «النوع» و«حالة المواطنة» — عمودان `NOT NULL` بقيمةٍ افتراضية في
 *     `employees`، فهما مملوءان دائماً ولا معنى لإلزامهما.
 *   • صورةُ الموظف — لا حقلَ لها في نموذجَي الإضافة والتعديل (المرفقاتُ
 *     العامّة غيرُها)، فإلزامُها إعدادٌ لا يسري على شيء.
 *
 * @see EmployeeSettingsController الشاشة
 * @see EmployeeRequest أكبرُ مستهلك
 */
class HrSettings
{
    /** @var array<string, HrSetting> memoized لعمر الطلب */
    private static array $rows = [];

    /**
     * حقولُ ملفّ الموظف القابلةُ للإلزام: اسمُ الحقل في النموذج ⇦
     * [المجموعة، مفتاحُ الإلزام، التسميةُ كما تُقرأ في الشاشة].
     *
     * الخريطةُ واحدةٌ يقرؤها ثلاثةٌ: `EmployeeRequest` (قاعدةُ التحقّق)،
     * ونموذجا الإضافة والتعديل (نجمةُ الإلزام على التسمية)، والشاشةُ
     * (أسماءُ المفاتيح وتسمياتُها). ولولا ذلك لكان حقلٌ يُلزَم في الخادم
     * ولا نجمةَ عليه في الشاشة — أو العكس.
     *
     * @var array<string, array{0:string,1:string,2:string}>
     */
    public const EMPLOYEE_FIELDS = [
        /* ── حقولُ الحساب والانتماء ──
           ليست كبقيّتها: البريدُ معرّفُ حساب الدخول، والدورُ مصدرُ صلاحياته،
           والفرعُ محورُ نطاق الرؤية. فقواعدُها في `EmployeeRequest` مكتوبةٌ
           بيدها لا تمرّ بـ`fieldRules()` — وتسكن الخريطةَ هنا كي يقرأ منها
           القالبُ نجمتَها والشاشةُ مفتاحَها كبقيّتها. */
        'email' => ['employee_core_required', 'require_email', 'البريد الإلكتروني (حساب الدخول)'],
        'branch_id' => ['employee_core_required', 'require_branch', 'الفرع'],
        'Job_role_id' => ['employee_core_required', 'require_login_role', 'الدور الوظيفي لحساب الدخول'],

        // ── الهوية والبيانات الشخصية ──
        'date_of_birth' => ['employee_required_identity', 'require_date_of_birth', 'تاريخ الميلاد'],
        'country' => ['employee_required_identity', 'require_country', 'البلد'],

        // ── بيانات التواصل ──
        'mobile_number' => ['employee_required_contact', 'require_mobile_number', 'رقم الجوال'],
        'phone_number' => ['employee_required_contact', 'require_phone_number', 'رقم الهاتف'],
        'personal_email' => ['employee_required_contact', 'require_personal_email', 'البريد الإلكتروني الشخصي'],
        'emergency_contact_name' => ['employee_required_contact', 'require_emergency_contact_name', 'اسم جهة الطوارئ'],
        'emergency_contact_phone' => ['employee_required_contact', 'require_emergency_contact_phone', 'هاتف جهة الطوارئ'],
        'emergency_contact_relation' => ['employee_required_contact', 'require_emergency_contact_relation', 'صلة القرابة'],

        // ── العنوان ──
        'current_address_1' => ['employee_required_address', 'require_current_address_1', 'العنوان الحالي'],
        'city' => ['employee_required_address', 'require_city', 'المدينة'],
        'region' => ['employee_required_address', 'require_region', 'المنطقة'],
        'postal_code' => ['employee_required_address', 'require_postal_code', 'الرمز البريدي'],

        // ── البيانات البنكية ──
        'bank_name' => ['employee_required_bank', 'require_bank_name', 'البنك'],
        'bank_account_holder' => ['employee_required_bank', 'require_bank_account_holder', 'اسم صاحب الحساب'],
        'iban' => ['employee_required_bank', 'require_iban', 'الآيبان (IBAN)'],

        // ── البيانات الوظيفية ──
        'department_id' => ['employee_required_job', 'require_department', 'القسم'],
        'job_title_id' => ['employee_required_job', 'require_job_title', 'المسمّى الوظيفي'],
        'job_level_id' => ['employee_required_job', 'require_job_level', 'المستوى الوظيفي'],
        'job_type_id' => ['employee_required_job', 'require_job_type', 'نوع الوظيفة'],
        'direct_manager_id' => ['employee_required_job', 'require_direct_manager', 'المدير المباشر'],
        'hire_date' => ['employee_required_job', 'require_hire_date', 'تاريخ الالتحاق'],
    ];

    /**
     * تعريفُ كلّ مجموعة: اسمُها المعروض، وتبويبُها في الشاشة، ومفاتيحُها
     * بافتراضاتها. الترتيبُ هنا هو ترتيبُ الظهور داخل التبويب.
     *
     * @return array<string, array{name: string, tab: string, options: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        return [
            /* ══════════ تبويب «حقول ملفّ الموظف» ══════════ */

            /* البريدُ والفرعُ والدور — ثلاثةٌ كانت `required` مثبَّتةً، وهي
               أخطرُ ما في هذه الشاشة: لكلٍّ منها أثرٌ يتجاوز النموذج.

                 • البريدُ معرّفُ الدخول (`users.email`)؛ ومن لا بريدَ له لا
                   يستطيع الدخول ولا تصله بياناتُ حسابه.
                 • الفرعُ محورُ نطاق الرؤية؛ وموظفٌ بلا فرعٍ لا يظهر في شاشةٍ
                   مُنطَقةٍ بالفرع ولا في تقريرٍ مبنيٍّ عليه.
                 • الدورُ مصدرُ صلاحيات الحساب؛ وحسابٌ بلا دورٍ يدخل ولا يرى
                   شيئاً.

               ولذلك `true` افتراضاً جميعاً — وما في الشاشة من تحذيرٍ ليس
               زينةً: الإطفاءُ قرارٌ صريحٌ لا تخفيفُ شرطٍ. */
            'employee_core_required' => [
                'name' => 'إلزامية البريد والفرع والدور',
                'tab' => 'fields',
                'options' => [
                    'require_email' => true,
                    'require_branch' => true,
                    'require_login_role' => true,
                ],
            ],

            'employee_required_identity' => [
                'name' => 'إلزامية الهوية والبيانات الشخصية',
                'tab' => 'fields',
                'options' => [
                    // `true` لأنّهما كانتا `required` مثبَّتةً في `EmployeeRequest`
                    'require_date_of_birth' => true,
                    'require_country' => true,
                ],
            ],

            'employee_required_contact' => [
                'name' => 'إلزامية بيانات التواصل',
                'tab' => 'fields',
                'options' => [
                    'require_mobile_number' => true,
                    'require_phone_number' => true,
                    'require_personal_email' => false,
                    'require_emergency_contact_name' => false,
                    'require_emergency_contact_phone' => false,
                    'require_emergency_contact_relation' => false,
                ],
            ],

            'employee_required_address' => [
                'name' => 'إلزامية العنوان',
                'tab' => 'fields',
                'options' => [
                    'require_current_address_1' => false,
                    'require_city' => false,
                    'require_region' => false,
                    'require_postal_code' => false,
                ],
            ],

            'employee_required_bank' => [
                'name' => 'إلزامية البيانات البنكية',
                'tab' => 'fields',
                'options' => [
                    'require_bank_name' => false,
                    'require_bank_account_holder' => false,
                    // الآيبانُ هو ما يُبنى عليه ملفُّ التحويل البنكي للرواتب
                    'require_iban' => false,
                ],
            ],

            'employee_required_job' => [
                'name' => 'إلزامية البيانات الوظيفية',
                'tab' => 'fields',
                'options' => [
                    'require_department' => false,
                    'require_job_title' => false,
                    'require_job_level' => false,
                    'require_job_type' => false,
                    // المديرُ المباشر هو ما تُبنى عليه الشجرةُ التنظيمية ومسارُ
                    // اعتماد الإجازات — ومن لا مديرَ له لا يجد من يعتمد طلبَه
                    'require_direct_manager' => false,
                    // تاريخُ الالتحاق أساسُ حساب مكافأة نهاية الخدمة ورصيدِ الإجازات
                    'require_hire_date' => false,
                ],
            ],

            /* ══════════ تبويب «قواعد التحقّق» ══════════ */

            'employee_validation_rules' => [
                'name' => 'قواعد التحقّق في ملفّ الموظف',
                'tab' => 'rules',
                'options' => [
                    /* فرادةُ الجوّال: لا فهرسَ فريداً على العمود ولا قاعدةَ
                       تحقّق، فرقمٌ واحدٌ يُقبل لموظفَين. و`false` افتراضاً لا
                       تهاوناً: قاعدةٌ بياناتٌ فيها تكرارٌ قائمٌ تُقفَل كلُّ
                       صفوفها المكرَّرة عن التعديل لو شُغّلت بلا علم المستخدم. */
                    'unique_mobile_number' => false,
                    // تاريخُ التحاقٍ في المستقبل مفيدٌ لمن يسجّل موظفاً قبل مباشرته
                    'allow_future_hire_date' => true,
                    // أقلُّ عمرٍ عند التعيين — `null` بلا قيد
                    'min_hire_age_years' => null,
                    // الحدُّ الأدنى لكلمة مرور حساب الدخول — كان 8 مثبَّتاً
                    'password_min_length' => 8,
                ],
            ],

            /* ══════════ تبويب «الهيكل التنظيمي» ══════════ */

            'org_department_rules' => [
                'name' => 'قواعد الأقسام',
                'tab' => 'org',
                'options' => [
                    'require_short_name' => false,
                    'require_description' => false,
                    // «مديرو القسم» — علاقةُ `department_employee`
                    'require_manager' => false,
                    'unique_name' => false,
                ],
            ],

            'org_job_title_rules' => [
                'name' => 'قواعد المسمّيات الوظيفية',
                'tab' => 'org',
                'options' => [
                    // `true` لأنّها كانت `required|exists` مثبَّتةً في المتحكّم
                    'require_department' => true,
                    'require_description' => false,
                    // التكرارُ يُقاس داخل القسم: «محاسب» في المالية وفي المشتريات
                    // مسمّيان مختلفان، و«محاسب» مرّتين في المالية خطأُ إدخال
                    'unique_name_per_department' => false,
                ],
            ],

            'org_job_level_rules' => [
                'name' => 'قواعد المستويات الوظيفية',
                'tab' => 'org',
                'options' => [
                    'require_description' => false,
                    'unique_name' => false,
                ],
            ],

            'org_job_type_rules' => [
                'name' => 'قواعد أنواع الوظائف',
                'tab' => 'org',
                'options' => [
                    'require_description' => false,
                    'unique_name' => false,
                ],
            ],

            'org_defaults' => [
                'name' => 'افتراضيات الهيكل التنظيمي',
                'tab' => 'org',
                'options' => [
                    /* الحالةُ المختارةُ سلفاً في نماذج الإضافة الأربعة.
                       `true` = «نشط» وهو ما كانت النماذجُ تختاره. وإطفاؤها
                       يجعل كلَّ ما يُضاف «غير نشط» حتى يُراجَع — ولا يمسّ
                       القائمَ ولا يمنع تغييرَ الحالة باليد. */
                    'new_node_active' => true,
                ],
            ],
        ];
    }

    /**
     * صفُّ مجموعة — يُنشأ بافتراضاتها إن لم يوجد، ومحفوظٌ لعمر الطلب.
     *
     * الافتراضاتُ تُكتب في الصفّ عند إنشائه أوّلَ مرّةٍ فقط: تغييرُ افتراضٍ
     * في هذا الملف لا يعيد كتابةَ ما ضبطه المستخدم.
     *
     * وبلا جدولٍ (ترحيلٌ لم يُشغَّل بعد، أو اختبارٌ يبني `employees` وحدها)
     * يُعاد صفٌّ غيرُ محفوظٍ بالافتراضات: `EmployeeRequest::rules()` تُقرأ في
     * اختباراتٍ لا تملك هذا الجدول، فاستثناءٌ هنا يُسقطها كلَّها.
     */
    public static function group(string $key): HrSetting
    {
        if (isset(self::$rows[$key])) {
            return self::$rows[$key];
        }

        $definition = self::definitions()[$key] ?? [];
        $defaults = $definition['options'] ?? [];
        $name = $definition['name'] ?? $key;

        try {
            if (! Schema::hasTable('hr_settings')) {
                return self::$rows[$key] = new HrSetting(['key' => $key, 'name' => $name, 'options' => $defaults]);
            }

            return self::$rows[$key] = HrSetting::getOrCreateByKey($key, $name, $defaults);
        } catch (Throwable $e) {
            return self::$rows[$key] = new HrSetting(['key' => $key, 'name' => $name, 'options' => $defaults]);
        }
    }

    /**
     * قيمةُ مفتاحٍ في مجموعة — والافتراضُ يُقرأ من التعريف لا يُمرَّر في
     * النداء: مفتاحٌ أُضيف إلى التعريف بعد أن حُفظ الصفُّ لا يوجد في حقيبته،
     * ولو قرأه كلُّ مستهلكٍ بافتراضه هو لاختلفت الافتراضاتُ بين موضعين.
     */
    public static function option(string $group, string $key, mixed $default = null): mixed
    {
        $value = self::group($group)->option($key, null);

        if ($value !== null) {
            return $value;
        }

        if (array_key_exists($key, self::definitions()[$group]['options'] ?? [])) {
            return self::definitions()[$group]['options'][$key];
        }

        return $default;
    }

    /** مفتاحٌ منطقي — القيمُ العائدةُ من JSON قد تكون `"0"`/`"1"`. */
    public static function flag(string $group, string $key, bool $default = false): bool
    {
        return (bool) self::option($group, $key, $default);
    }

    /** مفتاحٌ عددي، و`null` تبقى `null` — فرقٌ بين «صفر» و«بلا قيد». */
    public static function integer(string $group, string $key, ?int $default = null): ?int
    {
        $value = self::option($group, $key, $default);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * أحقلٌ إلزاميٌّ في ملفّ الموظف؟ — بالاسم كما يُرسله النموذج.
     *
     * حقلٌ ليس في {@see EMPLOYEE_FIELDS} يُعاد `false`: الخريطةُ هي حدودُ
     * ما تحكمه الشاشة، وما خارجَها تحكمه قاعدةُ `EmployeeRequest` وحدها.
     */
    public static function employeeFieldRequired(string $field): bool
    {
        $map = self::EMPLOYEE_FIELDS[$field] ?? null;

        if (! $map) {
            return false;
        }

        [$group, $key] = $map;

        return self::flag($group, $key);
    }

    /** إسقاطُ الحفظ لعمر الطلب — يُستدعى بعد الحفظ في الشاشة. */
    public static function forget(): void
    {
        self::$rows = [];
    }
}
