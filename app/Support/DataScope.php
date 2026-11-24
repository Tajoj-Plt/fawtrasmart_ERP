<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * نطاقُ الرؤية مُحَلّاً مرّةً واحدة — المحورُ الثاني للصلاحية في التقارير.
 *
 * ══════════ لماذا وُجد هذا الصنف ══════════
 *
 * الصلاحياتُ الخمس في {@see \App\Constants\permissions\DataScopePermissions}
 * كانت معرَّفةً في الكتالوج ومُسنَدةً للأدوار، ولا يقرؤها **متحكّمُ تقريرٍ
 * واحد** من الستّة والعشرين في المبيعات والمشتريات والعملاء. فكان مندوبٌ
 * يملك `data_scope_own` ويُفتح له تقريرُ «المبيعات حسب الموظف» يقرأ أرقامَ
 * زملائه كلِّهم — الصلاحيةُ تقول «بياناته هو»، والشاشةُ تُعطيه كلَّ شيء.
 *
 * الحراسةُ الموجودة كانت على محورِ **العملية** وحدَه (`permission:sales.reports.*`):
 * تُجيب «هل يفتح هذا التقرير؟» ولا تُجيب «على أيّ صفوفٍ منه؟».
 *
 * ══════════ لماذا هنا لا في كلّ وحدة ══════════
 *
 * ثلاثُ عائلاتٍ من التقارير لها ثلاثةُ traits مشتركة أصلاً
 * (`SalesReportTrait`، `PurchaseReportScope`، `FiltersCustomerReports`)،
 * ولو كُتب السلَّمُ في كلٍّ منها لاختلفت الثلاثةُ بعد تعديلين — وهو بعينه
 * ما وقع في فلتر الفرع قبل توحيده في {@see AffiliationTree}. فالسلَّمُ
 * هنا، والـtraits تستدعيه.
 *
 * و{@see Scopes::applyDataScope()} تحلّ المسألةَ نفسَها لاستعلامٍ واحدٍ
 * بعمودين معلومين (`created_by` + `branch_id`)، وهذا يكفي في شاشة قائمة.
 * أمّا التقريرُ فيسأل ثلاثةَ أسئلةٍ مختلفة عن النطاق الواحد:
 *
 *   • أيُّ **فروعٍ** أرى؟           → لقصّ مُنتقي «تابع لـ» وفلترِ الفرع.
 *   • أيُّ **مستخدمين** أرى؟        → لعمود `created_by` في المستندات.
 *   • أيُّ **موظّفين** أرى؟          → لعمود `employee_id` وقوائمِ المندوبين.
 *
 * فلا يكفي أن تُقيَّد الاستعلاماتُ وتبقى القوائمُ المنسدلةُ كاملةً: من رأى
 * اسمَ زميله في المُنتقي وضغطه خرج بشاشةٍ فارغة بلا تفسير، وعَلِم من
 * القائمة وحدَها ما لا يحقّ له.
 *
 * ══════════ السلَّم ══════════
 *
 * من الأوسع إلى الأضيق، وأوّلُ ما يملكه المستخدم يحسم — كما ينصّ الكتالوج:
 * «تُمنح واحدةً لا أكثر لكل دور، الأوسعُ يُغني عمّا دونه».
 *
 *   • `data_scope_all`         بلا قيد.
 *   • `data_scope_region`      فرعُه وما تحته في الشجرة.
 *   • `data_scope_branch`      فرعُه وحدَه.
 *   • `data_scope_department`  قسمُه.
 *   • `data_scope_team`        هو ومن يتبعه إدارياً (`direct_manager_id`).
 *   • `data_scope_own`         هو وحدَه.
 *
 * ══════════ ومن لا نطاقَ له إطلاقاً ══════════
 *
 * الأضيقُ (`own`) هو الافتراض، لا الأوسع: صلاحيةٌ ناقصةٌ يجب أن تُغلق لا
 * أن تفتح. ويُستثنى **من ليس موظفاً** فيبقى بلا قيدٍ هنا وتضبطه صلاحياتُ
 * العملية وحدَها — وهو استثناءُ {@see \Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees}
 * نفسُه وللسبب نفسِه: مسؤولُ نظامٍ لا سجلَّ موظفٍ له لا محورَ لتقييده،
 * وتقييدُه بـ`own` يُغلق الشاشة في وجهه بلا سبب.
 *
 * ولذلك أُسنِد النطاقُ للدورين اللذين كانا بلا نطاقٍ إطلاقاً («مندوب
 * مبيعات» ← `own`، و«مشرف» ← `team`) في مُهاجرٍ مرافق — وهما بعينهما
 * الدوران اللذان يسمّيهما الكتالوجُ في وصف الصلاحيتين.
 *
 * ══════════ `null` تعني «بلا قيد» ══════════
 *
 * لا مصفوفةً فارغة. الفارغةُ تعني «لا أحد»، والخلطُ بينهما يقلب الحارسَ
 * إلى بابٍ مفتوح أو إلى شاشةٍ فارغةٍ دائماً — وهي القاعدةُ التي تلتزمها
 * نظائرُه في الموارد البشرية ونقاط البيع.
 */
class DataScope
{
    public const ALL = 'all';

    public const REGION = 'region';

    public const BRANCH = 'branch';

    public const DEPARTMENT = 'department';

    public const TEAM = 'team';

    public const OWN = 'own';

    /**
     * ذاكرةُ الطلب الواحد — مفتاحُها معرّفُ المستخدم.
     *
     * التقريرُ الواحد يسأل عن النطاق مرّاتٍ: للاستعلام، ولكلّ قائمةٍ
     * منسدلة، ولترويسة الطباعة. وحسابُه يمسّ `branches` و`employees`
     * و`users`، فتركُه بلا ذاكرةٍ يضرب الاستعلامات في عدد الأسئلة.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $memo = [];

    /**
     * ذاكرةُ نطاق المستندات — مفتاحُها معرّفُ المستخدم.
     *
     * {@see documentEmployeeIds()} تمشي شجرةَ التسلسل الإداري
     * (`Employee::getDescendantIds()`) باستعلامٍ لكلّ مستوى، وشاشةُ
     * الفواتير تستدعيها ثلاثَ مرّاتٍ في الطلب الواحد (الجدول، وبطاقةُ
     * المرتجعات، وبطاقةُ الإشعارات) — فتُضرب الاستعلاماتُ بلا داعٍ.
     *
     * @var array<int, array{employees: array<int, int>|null, users: array<int, int>|null}>
     */
    private static array $documentMemo = [];

    public static function user(): ?User
    {
        return Auth::user();
    }

    /** إفراغُ الذاكرة — للاختبارات ولمن يبدّل المستخدم داخل الطلب. */
    public static function flush(): void
    {
        static::$memo = [];
        static::$documentMemo = [];
    }

    /** مستوى النطاق الفعلي لهذا المستخدم — أوّلُ ما يملكه من السلَّم. */
    public static function level(): string
    {
        return static::resolve()['level'];
    }

    /** هل هذا المستخدم بلا قيدٍ إطلاقاً؟ */
    public static function isUnrestricted(): bool
    {
        return static::resolve()['unrestricted'];
    }

    /**
     * الفروعُ التي يحقّ له رؤيتُها — أو `null` بلا قيد.
     *
     * `team` و`own` لا يُنتجان قيداً فرعياً: هما محورُ **أشخاص** لا محورُ
     * مكان، والقيدُ عليهما يقع على `created_by`/`employee_id`. ولو قُيّد
     * الفرعُ معهما لظهر المندوبُ محصوراً في فرعه ولو أُسنِد له عميلٌ في
     * فرعٍ آخر — وهو ما يقع فعلاً في المنشآت متعدّدة الفروع.
     *
     * @return array<int, int>|null
     */
    public static function branchIds(): ?array
    {
        return static::resolve()['branch_ids'];
    }

    /**
     * معرّفاتُ المستخدمين المشمولين — لعمود `created_by` وما يجري مجراه.
     *
     * @return array<int, int>|null
     */
    public static function userIds(): ?array
    {
        return static::resolve()['user_ids'];
    }

    /**
     * معرّفاتُ الموظفين المشمولين — لعمود `employee_id` وقوائمِ المندوبين.
     *
     * @return array<int, int>|null
     */
    public static function employeeIds(): ?array
    {
        return static::resolve()['employee_ids'];
    }

    /**
     * هل مُنح هذا المستخدم نطاقاً صراحةً في دوره؟
     *
     * الفرقُ بين «مُنح `own`» و«لم يُمنح شيئاً» ليس لفظياً: الأولى قرارٌ
     * مكتوبٌ في شاشة الأدوار يُحترم كما هو، والثانية فراغٌ يملؤه
     * {@see documentEmployeeIds()} بالتسلسل الإداري في شاشات المستندات.
     */
    public static function hasExplicitScope(): bool
    {
        return hasPermission([
            'data_scope_all', 'data_scope_region', 'data_scope_branch',
            'data_scope_department', 'data_scope_team', 'data_scope_own',
        ]);
    }

    /**
     * ══════════ نطاقُ شاشات المستندات ══════════
     *
     * السلَّمُ نفسُه، وفرقٌ واحد: الموظفُ الذي **لا نطاقَ له في دوره
     * إطلاقاً** يُقاس على التسلسل الإداري — هو ومن يتبعه — لا على نفسه
     * وحدَه.
     *
     * ولماذا هنا لا في {@see employeeIds()}: التقريرُ يقرأ الصلاحيةَ
     * المكتوبة ولا يفترض شيئاً. أمّا شاشاتُ المستندات فقامت في هذا النظام
     * على التسلسل الإداري منذ سنداتِ القبض وأذونِ المخزن وفواتيرِ المشتريات
     * ({@see \Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy}
     * ونظيراه في المخزون والمشتريات) — يرى فيها المديرُ مرؤوسيه بكونه
     * مديرَهم، بلا صلاحيةٍ إضافية.
     *
     * فكان المشرفُ يفتح «سندات القبض» فيجد سنداتِ فريقه، ويفتح «فواتير
     * المبيعات» فيجد فواتيرَه هو وحدَها — والفرقُ لا تفسّره الشاشتان.
     *
     * ومن مُنح نطاقاً صراحةً يُحكم بما مُنح: «بياناته هو» تعني بياناته هو،
     * ولا يُوسَّع عليه من خلفِ الصلاحية.
     *
     * @return array<int, int>|null
     */
    public static function documentEmployeeIds(): ?array
    {
        return static::documentScope()['employees'];
    }

    /** @return array{employees: array<int, int>|null, users: array<int, int>|null} */
    private static function documentScope(): array
    {
        $key = (int) (static::user()?->id ?? 0);

        if (isset(static::$documentMemo[$key])) {
            return static::$documentMemo[$key];
        }

        $employees = static::resolveDocumentEmployeeIds();

        return static::$documentMemo[$key] = [
            'employees' => $employees,
            'users' => static::resolveDocumentUserIds($employees),
        ];
    }

    /** @return array<int, int>|null */
    private static function resolveDocumentEmployeeIds(): ?array
    {
        $ids = static::employeeIds();

        // بلا قيد، أو محورُ مكانٍ (فرع/منطقة): لا شأنَ للتسلسل بواحدةٍ منهما.
        if ($ids === null || ! static::isPeopleAxis()) {
            return $ids;
        }

        // `team` محسوبٌ على التسلسل أصلاً، و`own` المصرَّحُ بها قرارٌ يُحترم.
        if (static::level() !== static::OWN || static::hasExplicitScope()) {
            return $ids;
        }

        $employee = static::user()?->employee;

        if (! $employee) {
            return $ids;
        }

        return array_values(array_unique(array_map(
            'intval',
            array_merge([$employee->id], $employee->getDescendantIds())
        )));
    }

    /**
     * معرّفاتُ المستخدمين المقابلة لـ{@see documentEmployeeIds()} — لعمود
     * `created_by` في المستندات.
     *
     * والمستخدمُ نفسُه مضمومٌ دائماً: أكثرُ مستنداتِ هذه المنشأة لا
     * `employee_id` لها (٤٦٢ إشعاراً دائناً من ٤٦٣)، فالقيدُ العمليُّ عليها
     * هو `created_by` وحدَه — ولو سقط منه صاحبُه لغابت عنه مستنداتُه هو.
     *
     * @return array<int, int>|null
     */
    public static function documentUserIds(): ?array
    {
        return static::documentScope()['users'];
    }

    /**
     * @param  array<int, int>|null  $employeeIds
     * @return array<int, int>|null
     */
    private static function resolveDocumentUserIds(?array $employeeIds): ?array
    {
        if ($employeeIds === null) {
            return null;
        }

        if (! static::isPeopleAxis()) {
            return static::userIds();
        }

        $ids = User::whereIn('employee_id', $employeeIds)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        if ($self = (int) (static::user()?->id ?? 0)) {
            $ids[] = $self;
        }

        return array_values(array_unique($ids));
    }

    /**
     * قصُّ عقدةٍ مطلوبةٍ إلى ما يحقّ للمستخدم رؤيتُه.
     *
     * ══════════ الثغرةُ التي يسدّها ══════════
     *
     * `SalesReportTrait::getEffectiveBranchId()` كانت تُعيد العقدةَ المطلوبة
     * كما جاءت من الطلب **قبل** أيّ تحقّق، فيكفي أن يُبدَّل `tree_node` في
     * شريط العنوان ليقرأ موظّفُ فرعٍ أرقامَ منطقةٍ أخرى كاملةً. وتقاريرُ
     * العملاء أوسعُ ثغرةً: بلا عقدةٍ في الطلب لا فلترَ إطلاقاً — فالقائمةُ
     * كلُّ عملاء المنشأة لأيّ مستخدمٍ يفتح الشاشة.
     *
     * المطلوبُ يُقبل إن كان داخل نطاقه؛ وإلّا رُدّ إلى جذر نطاقه — لا إلى
     * `null`: `null` تعني «كلّ الفروع» في هذه التقارير، فردُّ المتجاوز
     * إليها يمنحه ما حاول أخذه.
     *
     * ══════════ ولماذا على `branchIds()` لا `selectableBranchIds()` ══════════
     *
     * هذه تقصّ **صفوفاً** لا خيارات، فتتبع محورَ المكان وحدَه. ولو قُصّت
     * على شجرة المستخدم في محور الأشخاص لاختفى من نتيجة المندوب كلُّ
     * عميلٍ أُسنِد إليه خارج فرعه — أي لحُجبت عنه بياناتُه هو، وهو نقيضُ
     * المقصود. صفوفُه مقيَّدةٌ بشخصه أصلاً، فلا حاجة إلى قيدٍ مكانيٍّ فوقه.
     */
    public static function clampNode(?int $requested): ?int
    {
        $allowed = static::branchIds();

        if ($allowed === null) {
            return $requested;
        }

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return static::resolve()['root_branch_id'];
    }

    /**
     * الفروعُ التي تُعرض في **القوائم المنسدلة** — لا التي تُقيَّد بها الصفوف.
     *
     * ══════════ لماذا تختلف عن {@see branchIds()} ══════════
     *
     * `branchIds()` تُجيب «أيّ صفوفٍ أرى؟»، وتعود `null` على محور الأشخاص
     * عمداً: مندوبٌ قد يُسنَد إليه عميلٌ في أيّ فرع، فقصرُ **صفوفه** على
     * فرعه يُخفي عملاءه هم.
     *
     * أمّا المنسدلةُ فسؤالٌ آخر: «أيّ فرعٍ يحقّ له أن **يختار**؟». وهي
     * أداةُ تصفيةٍ لا حدُّ صلاحية — الصفوفُ مقيَّدةٌ قبلها على أيّ حال —
     * فقصرُها على شجرة المستخدم لا يُخفي عنه صفّاً واحداً: تركُ الفلتر
     * فارغاً يظلّ يعرض كلَّ ما يحقّ له عبر الفروع كلِّها.
     *
     * ومكسبُها مزدوج: لا تُفشي المنسدلةُ أسماءَ فروع المنشأة ومناطقها
     * ومنافذها كلَّها (١٦٧ عقدة هنا) لمن لا يرى منها إلّا فرعه، ولا تعرض
     * خياراً ضغطُه يُخرج شاشةً فارغةً لا تفسّر نفسها.
     *
     * فالقاعدةُ: نطاقٌ مكانيٌّ ⇒ فروعُه؛ ونطاقٌ شخصيٌّ ⇒ شجرةُ فرعه هو؛
     * وبلا قيدٍ ⇒ `null`.
     *
     * @return array<int, int>|null
     */
    public static function selectableBranchIds(): ?array
    {
        if (static::isUnrestricted()) {
            return null;
        }

        if (($allowed = static::branchIds()) !== null) {
            return $allowed;
        }

        // محورُ أشخاص: شجرةُ فرعه هو. ومن لا فرعَ له لا شجرةَ تُقصّ عليه.
        $root = static::resolve()['root_branch_id'];

        return $root ? static::branchSubtree((int) $root) : null;
    }

    /**
     * قائمةُ الفروع لمنسدلةٍ، مقصورةً على ما يحقّ للمستخدم اختيارُه.
     *
     * تُمرَّر استعلاماً جاهزاً كي تحتفظ كلُّ شاشةٍ بشروطها هي
     * (`status = active`، الترتيب، الأعمدة) ولا يُفرض عليها شرطٌ ليس لها.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     */
    public static function branchOptions($query = null, array $columns = ['*'])
    {
        $query ??= Branch::query()->orderBy('name');

        $allowed = static::selectableBranchIds();

        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query->get($columns);
    }

    /**
     * قائمةُ الموظفين لمُنتقي «مسؤول المبيعات»، مقصورةً على نطاقه.
     *
     * ══════════ الثغرةُ التي تسدّها ══════════
     *
     * شاشةُ إنشاء الفاتورة كانت تعرف حالتين لا ثالثة: من يملك
     * `data_scope_all` يرى الموظفين **كلَّهم**، ومن لا يملكها يرى **نفسَه
     * وحدَه** — `Employee::where('id', $user->employee_id)`. فالمشرفُ الذي
     * يدير فريقاً، وله `data_scope_team` صراحةً، لا يستطيع أن ينسب فاتورةً
     * إلى مندوبٍ من مندوبيه: يفتح المُنتقي فيجد اسمَه وحدَه. ومديرُ الفرع
     * الذي له `data_scope_branch` مثلُه.
     *
     * والسلَّمُ موجودٌ ومحسوبٌ هنا أصلاً ({@see employeeIds()})، ويقرؤه
     * ستةٌ وعشرون تقريراً — ولا تقرؤه شاشةُ إنشاء المستند التي تكتب
     * `employee_id` من الأصل.
     *
     * ══════════ ولماذا الترتيبُ بالنفس أوّلاً ══════════
     *
     * أغلبُ الفواتير ينسبها المستخدمُ إلى نفسِه، فاسمُه أوّلَ القائمة
     * يوفّر بحثاً في كل مستند — وهو ما كان يصنعه `sortBy` في الشاشة
     * القديمة، فلا يضيع بتوحيد المصدر.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function employeeOptions($query = null)
    {
        $query ??= Employee::query();

        /* `documentEmployeeIds()` لا `employeeIds()`: هذه منسدلةُ شاشةِ
           **مستند**، فتتبع نطاقَ المستندات — ولو تبعت نطاقَ التقارير لرأى
           المشرفُ في الجدول فواتيرَ فريقه ولم يجد أسماءهم في المُنتقي. */
        if (($allowed = static::documentEmployeeIds()) !== null) {
            $query->whereIn('id', $allowed);
        }

        $selfId = (int) (static::user()?->employee_id ?? 0);

        /* مرّتان لا مفتاحٌ مركَّب: ترتيبُ PHP مستقرٌّ منذ الثامنة، فترتيبُ
           الاسم يبقى محفوظاً داخل كلٍّ من المجموعتين. */
        return $query->get()
            ->sortBy(fn ($employee) => mb_strtolower(trim((string) $employee->full_name)))
            ->sortBy(fn ($employee) => (int) $employee->id === $selfId ? 0 : 1)
            ->values();
    }

    /**
     * قصُّ «مسؤول المبيعات» المُرسَل إلى ما يحقّ للمستخدم إسنادُه.
     *
     * المُنتقي مقصورٌ بـ{@see employeeOptions()}، والمُنتقي وعدٌ لا حارس:
     * `employee_id` يصل من النموذج ويُكتب في المستند كما هو، فيكفي تبديلُ
     * القيمة في المتصفّح لنسب فاتورةٍ إلى موظفٍ خارج النطاق — ومن ذلك
     * الإسناد تُحسب عمولتُه ومستهدفُه وتقاريرُ مبيعاته.
     *
     * والمتجاوزُ يؤول إلى الموظف نفسِه لا إلى `null`: مستندٌ بلا مسؤولٍ
     * يسقط من تقارير المندوبين كلِّها بلا أثرٍ يقول لماذا. ومن لا سجلَّ
     * موظفٍ له يبقى على `null` — لا محورَ يُنسب إليه.
     */
    public static function clampEmployee($requested): ?int
    {
        $requested = is_numeric($requested) ? (int) $requested : null;

        // نفسُ قائمةِ {@see employeeOptions()} حرفياً: ما يُعرض يُقبل.
        $allowed = static::documentEmployeeIds();

        if ($allowed === null) {
            return $requested;
        }

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        $self = (int) (static::user()?->employee_id ?? 0);

        return $self ?: null;
    }

    /**
     * قصُّ عقدِ مُنتقي «تابع لـ» على ما يحقّ للمستخدم اختيارُه.
     *
     * المُنتقي يُبنى من {@see AffiliationTree::nodes()} وهي **كلُّ** عقد
     * المنشأة. فمن نطاقُه فرعٌ واحد كان يرى في المنسدلة شجرةَ المنشأة
     * كاملةً بأسماء فروعها ومناطقها ومنافذها — وذلك إفشاءٌ في ذاته، ثمّ
     * هو دعوةٌ لضغط عقدةٍ تُخرج شاشةً فارغةً بلا تفسير بعد أن صار
     * {@see clampNode()} يردّها.
     *
     * والقياسُ على {@see selectableBranchIds()} لا على `branchIds()`:
     * المُنتقي منسدلةٌ كبقيّة المنسدلات، فيتبع قاعدتها.
     *
     * @param  array<int, array{id:int, ...}>  $nodes
     * @return array<int, array{id:int, ...}>
     */
    public static function filterNodes(array $nodes): array
    {
        $allowed = static::selectableBranchIds();

        if ($allowed === null) {
            return $nodes;
        }

        return array_values(array_filter(
            $nodes,
            fn ($node) => in_array((int) ($node['id'] ?? 0), $allowed, true)
        ));
    }

    /**
     * تقاطعُ قائمةِ فروعٍ مطلوبةٍ مع المسموح — لمن يبني قائمتَه بنفسه.
     *
     * @param  array<int, int>|null  $requested
     * @return array<int, int>|null
     */
    public static function intersectBranchIds(?array $requested): ?array
    {
        $allowed = static::branchIds();

        if ($allowed === null) {
            return $requested;
        }

        if ($requested === null) {
            return $allowed;
        }

        $intersection = array_values(array_intersect(
            array_map('intval', $requested),
            $allowed
        ));

        // فارغةٌ تعني «لا شيء» صراحةً، ولا تُترك لتُقرأ «الكلّ».
        return $intersection ?: [0];
    }

    /**
     * تقييدُ استعلامٍ بعمود فرع.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainBranch($query, string $column = 'branch_id')
    {
        $ids = static::branchIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * تقييدُ استعلامٍ بعمود منشئ.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainCreator($query, string $column = 'created_by')
    {
        $ids = static::userIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * تقييدُ استعلامٍ بعمود موظف.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainEmployee($query, string $column = 'employee_id')
    {
        $ids = static::employeeIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * هل النطاقُ محورُ **أشخاص** (`own`/`team`) لا محورُ **مكان**؟
     *
     * السلَّمُ محورانِ لا محورٌ واحد، والتمييزُ بينهما ليس تجميلاً:
     *
     *   • `branch`/`region` محورُ مكان — القيدُ على `branch_id`، وكلُّ ما
     *     في الفرع مرئيٌّ بصرف النظر عن صاحبه.
     *   • `own`/`team` محورُ أشخاص — القيدُ على `created_by`/`employee_id`،
     *     بصرف النظر عن الفرع.
     *
     * وتطبيقُ الاثنين معاً على استعلامٍ واحد يطرح صفوفاً مشروعة: عميلٌ
     * في فرعِ المستخدم بلا مندوبٍ مُسنَد (`employee_id = null`) يسقط من
     * تقرير مديرِ الفرع لو قُيّد بالموظفين أيضاً — وهم في هذه المنشأة
     * ليسوا قلّة.
     */
    public static function isPeopleAxis(): bool
    {
        $state = static::resolve();

        return ! $state['unrestricted'] && $state['branch_ids'] === null;
    }

    /**
     * تقييدُ استعلامٍ بمالكِه — على محور الأشخاص وحدَه.
     *
     * تُستعمل حيث يكون قيدُ الفرع مطبَّقاً أصلاً بطريقٍ آخر (شجرةُ «تابع
     * لـ» مثلاً)، فلا يبقى إلّا أن يُضاف قيدُ الشخص لمن نطاقُه شخصيّ.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainOwner($query, string $column = 'employee_id')
    {
        return static::isPeopleAxis()
            ? $query->whereIn($column, static::employeeIds() ?? [0])
            : $query;
    }

    // =================================================================
    // الحلّ
    // =================================================================

    /** @return array<string, mixed> */
    private static function resolve(): array
    {
        $user = static::user();

        if (! $user) {
            return static::deny();
        }

        $key = (int) $user->id;

        return static::$memo[$key] ??= static::compute($user);
    }

    /** @return array<string, mixed> */
    private static function compute(User $user): array
    {
        $rootBranchId = Scopes::resolveBranchId($user);

        if (hasPermission('data_scope_all')) {
            return static::unrestricted(static::ALL, $rootBranchId);
        }

        $hasAny = hasPermission([
            'data_scope_region', 'data_scope_branch', 'data_scope_department', 'data_scope_team', 'data_scope_own',
        ]);

        /* بلا نطاقٍ إطلاقاً ولا سجلِّ موظف: لا محورَ للتقييد — تضبطه
           صلاحياتُ العملية وحدَها. أمّا الموظفُ بلا نطاق فيؤول إلى `own`
           أدناه، لأنّ له محوراً واضحاً ولأنّ الناقصَ يُغلق لا يفتح.

           ويُستثنى من الاستثناء **من لا دورَ له أصلاً** (`role_id` فارغ):
           في هذه القاعدة خمسةٌ وعشرون حساباً منها، كلُّها حساباتُ عملاءَ
           في بوّابة العملاء لا مسؤولي أنظمة. صلاحياتُهم صفرٌ فلا يبلغون
           شاشةً على أيّ حال، لكنّ «بلا قيد» وصفٌ خاطئ لهم، والخطأُ في
           هذا الموضع لا يُكتشف إلّا بعد أن يُقرأ في مكانٍ لا حارسَ فيه.

           ويبقى المديرُ المستورَد بلا `role_id` (عمودُ `users.role` النصّي)
           بلا قيدٍ كما كان — {@see isLegacyManager()}. */
        if (! $hasAny && ! $user->employee_id) {
            return $user->role_id || static::isLegacyManager($user)
                ? static::unrestricted(static::ALL, $rootBranchId)
                : static::personal($user, $rootBranchId);
        }

        $isRegion = hasPermission('data_scope_region');

        /* ══════════ الفرعُ الرئيسيّ: المنشأةُ كلُّها — لمن هو منه فقط ══════════

           الوقوفُ على الرئيسيّ يعني المنشأةَ كلَّها ({@see HeaderBranchScope}) —
           لكن **لمن فرعُه الأساسيّ هو الرئيسيّ** (`employees.branch_id`) ويملك
           نطاقَ فرعٍ أو منطقة، أي مديرُ المركز الرئيسيّ.

           كان الشرطُ الوقوفَ وحدَه، والوقوفُ لا يدلّ على الانتماء: مبدّلُ الشريط
           ({@see BranchScope::switchable()}) يعرض كلَّ ما في `employee_branch`،
           وأسلافُ فرع الموظف تُضاف إليه تلقائياً، ويعرض فروعَ من يديرهم أيضاً.
           فمديرُ فرعٍ فرعيٍّ يبدّل إلى الرئيسيّ من الشريط يصير بلا قيدٍ ويقرأ
           سنداتِ المنشأة كلِّها ومصروفاتِها.

           ومن وقف على الرئيسيّ وفرعُه الأساسيّ غيرُه يُحسب نطاقُه على فرعه
           الأساسيّ — لا يتّسع بالوقوف ولا يضيق. ومن لا سجلَّ موظفٍ له أو لا
           فرعَ في سجلّه فلا فرعَ أساسيَّ يُقاس عليه، فيبقى على ما في الشريط. */
        if ($rootBranchId && Branch::findCached((int) $rootBranchId)?->is_main) {
            $homeBranchId = (int) ($user->employee?->branch_id ?? 0);

            if ($homeBranchId && $homeBranchId !== (int) $rootBranchId) {
                $rootBranchId = $homeBranchId;
            } elseif ($isRegion || hasPermission('data_scope_branch')) {
                return static::unrestricted($isRegion ? static::REGION : static::BRANCH, (int) $rootBranchId);
            }
        }

        if (($isRegion || hasPermission('data_scope_branch')) && $rootBranchId) {
            /* «الفرع» = الفرعُ وما يتبعه **تنظيمياً** (مجموعاتُه ومنافذُه)،
               لا فروعٌ أخرى تحته. كان `[$rootBranchId]` وحدَه: الأشخاصُ
               يُحسبون من المنافذ (أدناه) بينما المكانُ لا — فالمستنداتُ
               الموسومة بمنفذٍ تحت الفرع تسقط عن صاحبه، ومُنتقي «تابع لـ»
               يعرض له عقدةً واحدةً بلا أبناء فلا شجرةَ يتنقّل فيها. */
            $branchIds = $isRegion
                ? static::branchSubtree((int) $rootBranchId)
                : AffiliationTree::withOrganisationalChildren([(int) $rootBranchId]);

            // الأشخاصُ من العقد نفسِها — محورا المكان والأشخاص على شجرةٍ واحدة
            $peopleNodeIds = $branchIds;

            return [
                'level' => $isRegion ? static::REGION : static::BRANCH,
                'unrestricted' => false,
                'root_branch_id' => (int) $rootBranchId,
                'branch_ids' => $branchIds,
                'user_ids' => User::whereIn('branch_id', $peopleNodeIds)->pluck('id')
                    ->map(fn ($id) => (int) $id)->all(),
                'employee_ids' => Employee::whereIn('branch_id', $peopleNodeIds)->pluck('id')
                    ->map(fn ($id) => (int) $id)->all(),
            ];
        }

        /* نطاقٌ فرعيٌّ بلا فرع: يُعامَل كـ«هو فقط» بدل أن يُفتح له كلُّ
           شيء — وهو اختيارُ `Scopes::applyDataScope()` نفسُه. */

        $employee = $user->employee;

        if (hasPermission('data_scope_department') && $employee?->department_id) {
            $employeeIds = Employee::where('department_id', $employee->department_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'level' => static::DEPARTMENT,
                'unrestricted' => false,
                'root_branch_id' => $rootBranchId ? (int) $rootBranchId : null,
                'branch_ids' => null,
                'user_ids' => User::whereIn('employee_id', $employeeIds)->pluck('id')
                    ->map(fn ($id) => (int) $id)->all(),
                'employee_ids' => $employeeIds,
            ];
        }

        if (hasPermission('data_scope_team') && $employee) {
            $employeeIds = array_values(array_unique(array_map(
                'intval',
                array_merge([$employee->id], $employee->getDescendantIds())
            )));

            return [
                'level' => static::TEAM,
                'unrestricted' => false,
                'root_branch_id' => $rootBranchId ? (int) $rootBranchId : null,
                'branch_ids' => null,
                'user_ids' => User::whereIn('employee_id', $employeeIds)->pluck('id')
                    ->map(fn ($id) => (int) $id)->all(),
                'employee_ids' => $employeeIds,
            ];
        }

        return static::personal($user, $rootBranchId);
    }

    /**
     * مديرٌ مستورَدٌ بلا دورٍ مربوط — يُقرأ عمودُ `users.role` النصّي.
     *
     * `hasRolePermission()` تقرأ `role_id` وحدَه وتُرجع `false` فوراً إن
     * كان فارغاً، وقاعدةُ بياناتٍ مستوردة تصل وأغلبُ مستخدميها بلا
     * `role_id` — فيسقط مديرُ المنشأة إلى «بياناته هو» وهو لم يُدخل
     * مستنداً بنفسه، فتفتح الشاشاتُ في وجهه فارغة.
     *
     * وهي القاعدةُ نفسُها في
     * {@see \Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility::seesAllSalesDocuments()}
     * و`ScopesToEmployeeHierarchy::usesLegacyRoleFallback()` — تُكتب هنا
     * مرّةً ويُحال إليها.
     */
    public static function isLegacyManager(?User $user = null): bool
    {
        $user ??= static::user();

        return $user
            && ! $user->role_id
            && in_array(strtolower(trim((string) $user->role)), ['manager', 'admin', 'owner'], true);
    }

    /** النطاقُ الشخصيّ: هو وحدَه. @return array<string, mixed> */
    private static function personal(User $user, $rootBranchId): array
    {
        return [
            'level' => static::OWN,
            'unrestricted' => false,
            'root_branch_id' => $rootBranchId ? (int) $rootBranchId : null,
            'branch_ids' => null,
            'user_ids' => [(int) $user->id],
            'employee_ids' => $user->employee_id ? [(int) $user->employee_id] : [0],
        ];
    }

    /**
     * الفرعُ وما تحته في شجرة «تابع لـ».
     *
     * `descendantBranchIds()` لا `AffiliationTree::descendantIds()`: الأولى
     * على `branches.parent_id` مباشرةً وتشمل المناطقَ والمنافذ باعتبارها
     * عقداً في الجدول نفسِه، وهي التي يستعملها نظيرُه في الموارد البشرية —
     * فيبقى النطاقُ الواحد يعني الشيءَ نفسَه في الوحدتين.
     *
     * @return array<int, int>
     */
    private static function branchSubtree(int $branchId): array
    {
        $descendants = Branch::findCached($branchId)?->descendantBranchIds();

        return $descendants
            ? $descendants->push($branchId)->unique()->map(fn ($id) => (int) $id)->values()->all()
            : [$branchId];
    }

    /** @return array<string, mixed> */
    private static function unrestricted(string $level, ?int $rootBranchId): array
    {
        return [
            'level' => $level,
            'unrestricted' => true,
            'root_branch_id' => $rootBranchId ? (int) $rootBranchId : null,
            'branch_ids' => null,
            'user_ids' => null,
            'employee_ids' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function deny(): array
    {
        return [
            'level' => static::OWN,
            'unrestricted' => false,
            'root_branch_id' => null,
            'branch_ids' => [0],
            'user_ids' => [0],
            'employee_ids' => [0],
        ];
    }
}
