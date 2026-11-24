<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FunctionalLevels;
use App\Models\JopTitle;
use App\Models\TypesJobs;
use App\Support\HrSettings;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * شاشةُ «إعدادات الموظفين والهيكل التنظيمي».
 *
 * **لماذا شاشةٌ واحدة لهما**
 *
 * القسمُ والمسمّى والمستوى ونوعُ الوظيفة حقولٌ في ملفّ الموظف قبل أن تكون
 * شاشاتٍ قائمةً بنفسها. فمن يسأل «هل يُسجَّل موظفٌ بلا قسم؟» يسأل عن
 * إلزاميّةِ حقلٍ في ملفّ الموظف وعن قاعدةٍ في شاشة الأقسام معاً — وفصلُهما
 * في شاشتين يجعل الجوابَ في موضعين لا رابطَ بينهما.
 *
 * ثلاثةُ تبويبات:
 *
 *   • **حقول ملفّ الموظف** — أيُّ الحقول إلزاميٌّ عند الإضافة. والنجمةُ على
 *     التسمية في نموذجَي الإضافة والتعديل تتبع المفتاحَ نفسَه، فلا يُلزَم
 *     حقلٌ في الخادم بلا علامةٍ عليه في الشاشة.
 *   • **قواعد التحقّق** — فرادةُ الجوّال، وتاريخُ التحاقٍ مستقبليّ، وأقلُّ
 *     عمرٍ عند التعيين، وطولُ كلمة المرور.
 *   • **الهيكل التنظيمي** — إلزاميّةُ حقولِ الأقسام والمسمّيات والمستويات
 *     والأنواع، وفرادةُ أسمائها، وحالةُ ما يُضاف.
 *
 * **مصدرُ التعريف واحد**: `App\Support\HrSettings::definitions()`، وخريطةُ
 * {@see FIELDS} هنا تربط اسمَ الحقل في النموذج بموضعه في تلك المجموعة
 * وبقاعدة تحقّقه. فإضافةُ إعدادٍ سطران لا أربعةُ مواضع.
 *
 * **ما لا تُطفئه هذه الشاشة**: الاسمُ الأول والبريدُ والحالةُ ونوعُ الموظف
 * والفرعُ — إلزاميّتُها بنيويّةٌ لا قاعدةُ عمل. والسببُ في `HrSettings`.
 *
 * @see HrSettings التعريفُ والافتراضات
 * @see EmployeeRequest أكبرُ مستهلكٍ للمفاتيح
 */
class EmployeeSettingsController extends Controller
{
    /** مفتاحُ الوحدة في السجلّات. */
    private const LOG_MODULE = 'HR.EmployeeSettings';

    /** تبويباتُ الشاشة — وحدُها تُقبل في `active_tab`. */
    private const TABS = ['fields', 'rules', 'org'];

    /**
     * خريطةُ النموذج: اسمُ الحقل ⇦ [المجموعة، المفتاحُ في الحقيبة، النوع،
     * قاعدةُ التحقّق].
     *
     * *النوعُ* يحكم التحويلَ قبل الحفظ: `bool` غيابُه إطفاء، و`int?` تُبقي
     * `null` على حالها — «بلا قيد» ليست صفراً.
     *
     * مفاتيحُ الإلزام لا تُكتب هنا يداً: تُشتقّ من
     * `HrSettings::EMPLOYEE_FIELDS` في {@see fieldMap()} — فحقلٌ يُضاف إلى
     * تلك الخريطة يظهر في الشاشة ويُحفَظ بلا سطرٍ إضافي.
     *
     * @var array<string, array{0:string,1:string,2:string,3:mixed}>
     */
    private const FIELDS = [
        // ─────────── قواعد التحقّق ───────────
        'unique_mobile_number' => ['employee_validation_rules', 'unique_mobile_number', 'bool', 'nullable|boolean'],
        'allow_future_hire_date' => ['employee_validation_rules', 'allow_future_hire_date', 'bool', 'nullable|boolean'],
        'min_hire_age_years' => ['employee_validation_rules', 'min_hire_age_years', 'int?', 'nullable|integer|min:14|max:40'],
        'password_min_length' => ['employee_validation_rules', 'password_min_length', 'int', 'nullable|integer|min:6|max:64'],

        // ─────────── الهيكل التنظيمي ───────────
        'department_require_short_name' => ['org_department_rules', 'require_short_name', 'bool', 'nullable|boolean'],
        'department_require_description' => ['org_department_rules', 'require_description', 'bool', 'nullable|boolean'],
        'department_require_manager' => ['org_department_rules', 'require_manager', 'bool', 'nullable|boolean'],
        'department_unique_name' => ['org_department_rules', 'unique_name', 'bool', 'nullable|boolean'],

        'job_title_require_department' => ['org_job_title_rules', 'require_department', 'bool', 'nullable|boolean'],
        'job_title_require_description' => ['org_job_title_rules', 'require_description', 'bool', 'nullable|boolean'],
        'job_title_unique_name_per_department' => ['org_job_title_rules', 'unique_name_per_department', 'bool', 'nullable|boolean'],

        'job_level_require_description' => ['org_job_level_rules', 'require_description', 'bool', 'nullable|boolean'],
        'job_level_unique_name' => ['org_job_level_rules', 'unique_name', 'bool', 'nullable|boolean'],

        'job_type_require_description' => ['org_job_type_rules', 'require_description', 'bool', 'nullable|boolean'],
        'job_type_unique_name' => ['org_job_type_rules', 'unique_name', 'bool', 'nullable|boolean'],

        'org_new_node_active' => ['org_defaults', 'new_node_active', 'bool', 'nullable|boolean'],
    ];

    public function __construct()
    {
        $this->middleware('permission.role:employee_settings.manage');
    }

    /**
     * الشاشةُ — وبصحبتها ما يُقاس عليه أثرُ الإعداد: كم موظفاً ينقصه كلُّ
     * حقلٍ إلزاميّ، وهل في الأسماء تكرارٌ قائمٌ يمنع تشغيلَ الفرادة.
     */
    public function index(Request $request)
    {
        $fields = $this->fieldMap();

        return view('hr::employee.settings', [
            'activeTab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'fields',
            // القالبُ يقرأ `HrSettings::EMPLOYEE_FIELDS` مباشرةً للتسميات —
            // والخريطةُ هنا لحدود ما يُعَدّ نقصُه وحده
            'gaps' => $this->employeeGaps(array_keys($fields)),
            'employeesCount' => Employee::count(),
            'incompleteCount' => $this->incompleteCount(),
            'duplicates' => $this->duplicateNames(),
        ]);
    }

    /**
     * الحفظُ — كلُّ التبويبات بنموذجٍ واحد.
     *
     * **الغائبُ لا يُمسّ**: لكلّ مفتاحٍ منطقيٍّ حقلٌ مخفيٌّ بقيمة `0` يسبقه
     * في `sales::components.switch`، فالإطفاءُ يصل كما يصل التشغيل. وغيابُ
     * الاسم بالكلّية يعني أنّ هذا الحفظ لم يحمل الحقلَ أصلاً (نداءٌ آخر، أو
     * نموذجٌ اختُصر لاحقاً) لا أنّ المستخدم أطفأه.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules(), [], $this->attributeNames());

        try {
            $changes = DB::transaction(fn () => $this->saveGroups($data));
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update');

            return back()->withInput()->with('error', 'تعذّر حفظ الإعدادات — راجع سجلّ الأخطاء.');
        }

        HrSettings::forget();

        OperationLog::record('updated', self::LOG_MODULE, null, 'تحديث إعدادات الموظفين والهيكل التنظيمي', $changes);

        return redirect()
            ->route('hr.employee_settings.index', ['tab' => $request->input('active_tab', 'fields')])
            ->with('success', 'تم حفظ الإعدادات');
    }

    /**
     * الخريطةُ الكاملة: مفاتيحُ الإلزام المشتقّةُ من `HrSettings` ثمّ
     * المفاتيحُ المكتوبةُ في {@see FIELDS}.
     *
     * اسمُ الحقل في النموذج هو مفتاحُ الحقيبة نفسُه للإلزام
     * (`require_date_of_birth`) — فلا اسمَ ثانٍ يُحفَظ في موضعين.
     *
     * @return array<string, array{0:string,1:string,2:string,3:mixed}>
     */
    private function fieldMap(): array
    {
        $map = [];

        foreach (HrSettings::EMPLOYEE_FIELDS as [$group, $key]) {
            $map[$key] = [$group, $key, 'bool', 'nullable|boolean'];
        }

        return $map + self::FIELDS;
    }

    /**
     * قواعدُ التحقّق مشتقّةً من الخريطة — لا تُكتب مرّتين.
     *
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        $rules = ['active_tab' => ['nullable', Rule::in(self::TABS)]];

        foreach ($this->fieldMap() as $input => [$group, $field, $type, $rule]) {
            $rules[$input] = $rule;
        }

        return $rules;
    }

    /**
     * أسماءُ الحقول في رسائل الأخطاء — بلا هذا تُركّب الرسالةُ مفتاحَ
     * الحقيبة كما هو: «الحقل min_hire_age_years يجب أن يكون…».
     *
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        $names = [
            'min_hire_age_years' => 'أقل عمر عند التعيين',
            'password_min_length' => 'الحد الأدنى لطول كلمة المرور',
        ];

        foreach (HrSettings::EMPLOYEE_FIELDS as [$group, $key, $label]) {
            $names[$key] = 'إلزامية «'.$label.'»';
        }

        return $names;
    }

    /**
     * حفظُ المجموعات — تُجمَّع بالمجموعة ثمّ يُحفَظ صفٌّ واحدٌ لكلٍّ، مع فرقِ
     * القيم لسجلّ النشاط. الحقلُ الذي لم يتغيّر لا يدخل السجلّ.
     *
     * @param  array<string, mixed>  $data
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function saveGroups(array $data): array
    {
        $byGroup = [];

        foreach ($this->fieldMap() as $input => [$group, $field, $type, $rule]) {
            if (! array_key_exists($input, $data)) {
                continue;
            }

            $byGroup[$group][$field] = $this->castValue($data[$input], $type);
        }

        $old = [];
        $new = [];

        foreach ($byGroup as $group => $options) {
            $row = HrSettings::group($group);

            $before = [];
            foreach (array_keys($options) as $field) {
                $before[$field] = $row->option($field);
            }

            $row->mergeOptions($options)->save();

            foreach ($options as $field => $value) {
                if (($before[$field] ?? null) !== $value) {
                    $old[$group.'.'.$field] = $before[$field] ?? null;
                    $new[$group.'.'.$field] = $value;
                }
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * تحويلُ قيمةِ النموذج إلى نوعها المخزَّن. `int?` تُبقي الفراغَ `null`
     * — «بلا قيد» لا صفراً.
     */
    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => $value === null || $value === '' ? null : (int) $value,
            'int?' => $value === null || $value === '' ? null : (int) $value,
            default => $value === '' ? null : $value,
        };
    }

    /**
     * كم موظفاً قائماً ينقصه كلُّ حقل — ليعرف المستخدمُ قبل التشغيل أنّ
     * الإلزامَ سيُطالب به عند أوّلِ تعديلٍ لأولئك.
     *
     * الإلزامُ يسري على الإضافة دائماً، وعلى التعديل لمن كان الحقلُ محفوظاً
     * له فعلاً (انظر `EmployeeRequest::requiredOnCreateOrIfFilled`) — فالرقمُ
     * هنا تنبيهٌ لا تحذير: هؤلاء لن يُقفَلوا عن التعديل، لكن ملفَّهم يبقى
     * ناقصاً حتى يُملأ.
     *
     * @param  array<int, string>  $keys  مفاتيحُ الحقيبة المطلوبةُ وحدها
     * @return array<string, int> مفتاحُ الإلزام ⇦ عددُ الناقصين
     */
    private function employeeGaps(array $keys): array
    {
        $gaps = [];

        foreach (HrSettings::EMPLOYEE_FIELDS as $column => [$group, $key]) {
            if (! in_array($key, $keys, true)) {
                continue;
            }

            try {
                $gaps[$key] = Employee::query()
                    ->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''))
                    ->count();
            } catch (Throwable $e) {
                // عمودٌ غائبٌ في قاعدةٍ قديمة لا يُسقط الشاشة
                $gaps[$key] = 0;
            }
        }

        return $gaps;
    }

    /**
     * عددُ الموظفين الذين ينقصهم حقلٌ إلزاميٌّ **مشغَّلٌ** واحدٌ على الأقل.
     *
     * رقمٌ واحدٌ يُقاس عليه أثرُ الإعداد كلِّه: صفرٌ يعني أنّ القواعدَ
     * المشغَّلة مستوفاةٌ في كلّ الملفّات القائمة.
     */
    private function incompleteCount(): int
    {
        $columns = [];

        foreach (HrSettings::EMPLOYEE_FIELDS as $column => [$group, $key]) {
            if (HrSettings::flag($group, $key)) {
                $columns[] = $column;
            }
        }

        if (! $columns) {
            return 0;
        }

        try {
            return Employee::query()
                ->where(function ($q) use ($columns) {
                    foreach ($columns as $column) {
                        $q->orWhereNull($column)->orWhere($column, '');
                    }
                })
                ->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * التكرارُ القائم — فرادةٌ تُشغَّل على بياناتٍ فيها تكرارٌ تُقفل الصفوفَ
     * المكرَّرة عن التعديل، فيُعرَض العددُ قبل التشغيل.
     *
     * @return array<string, int>
     */
    private function duplicateNames(): array
    {
        return [
            // أرقامُ الجوّال الفارغةُ تُستثنى: تجمّعُها معاً ليس تكراراً
            'mobile_numbers' => $this->duplicateCount(
                Employee::query()->whereNotNull('mobile_number')->where('mobile_number', '!=', ''),
                ['mobile_number']
            ),
            'departments' => $this->duplicateCount(Department::query(), ['name']),
            'job_titles' => $this->duplicateCount(JopTitle::query(), ['name', 'department_id']),
            'job_levels' => $this->duplicateCount(FunctionalLevels::query(), ['name']),
            'job_types' => $this->duplicateCount(TypesJobs::query(), ['name']),
        ];
    }

    /**
     * عددُ القيم المتكرّرة في مجموعةِ أعمدة — لا عددُ الصفوف: «محاسب»
     * ثلاثَ مرّاتٍ تكرارٌ واحدٌ يُعرَض لا ثلاثة.
     *
     * @param  array<int, string>  $columns
     */
    private function duplicateCount(mixed $query, array $columns): int
    {
        try {
            return $query
                ->select($columns)
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
