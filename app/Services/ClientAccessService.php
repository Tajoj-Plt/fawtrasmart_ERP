<?php

namespace App\Services;

use App\Helpers\WeekHelper;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\EmployeeGroup;
use App\Models\User;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * "من يرى أيّ عميل" — نقطة تجميع واحدة لمنطق كان مكرَّراً بالحرف أربع مرات
 * داخل ClientController (index، getMapData، getClientsForSelect، وقائمة
 * الاختيار الثانية داخل index نفسه).
 *
 * صلاحيتا «العرض» الحاليتان تُطابقان تماماً ما طُلب باسم view_all/view_assigned:
 *   - clients_view_all_clients  → كل العملاء (بحدود شجرة الفرع إن وُجدت)
 *   - clients_view_own_clients  → العملاء المسندون فعلياً عبر جدول client_employee
 * لا حاجة لأسماء صلاحياتٍ جديدة.
 *
 * @see \App\Constants\permissions\ClientsPermissions
 */
class ClientAccessService
{
    /**
     * النواة المشتركة الحرفية بين الشاشات الأربع: مجموعات الموظف ← خط
     * السير اليومي ← العملاء المسندون (pivot).
     *
     * تُعيد: true = طابقت صلاحيةً ووجدت نطاقاً غير فارغ (طُبِّق فوراً)،
     * false = يملك المستخدم صلاحيةً من الثلاث لكنّ نطاقها فارغ (طُبِّق
     * whereRaw('1=0') بالفعل — على المستدعي رفع علم "لا عملاء" الخاص به)،
     * null = لا يملك أيّاً من الصلاحيات الثلاث — عندها يقرّر المستدعي مسار
     * الاحتياط الخاص به (لا يوحَّد هنا لاختلافه فعلياً بين الشاشات الأربع).
     *
     * **مهمّ: لا انتقال بين الصلاحيات.** أوّلُ صلاحيةٍ يملكها المستخدم
     * بالترتيب (خط السير ← المجموعات ← عملاؤه) هي نطاقُه وحدها، ولو كان
     * نطاقُها فارغاً فلا عملاء. كان النطاقُ الفارغ ينتقل للصلاحية التالية:
     * مندوبٌ يملك «خط السير اليومي» و«عملائه هو فقط» معاً يرى في يومٍ بلا
     * خطِّ سير (عطلة، أو قبل التوليد) **كلَّ** عملائه المسندين — مئات —
     * بدل لا شيء. خطُّ السير أوّلاً لأنه الأضيق وقيدُ المندوب الميداني.
     */
    public function applyPermissionCascade(Builder $query, User $user, string $dayNameEn, int $year, int $week): ?bool
    {
        if (hasPermission('clients_view_by_daily_itinerary')) {
            $ids = $this->todayItineraryClientIds($user->id, $dayNameEn, $year, $week);
        } elseif (hasPermission('clients_view_by_employee_groups')) {
            // مجموعاتُ نطاقه لا مجموعاتِه هو وحدَه — انظر `scopeEmployeeIds()`
            $employeeGroupIds = EmployeeGroup::whereIn('employee_id', $this->scopeEmployeeIds($user))
                ->pluck('group_id');

            if ($employeeGroupIds->isNotEmpty()) {
                $query->whereHas('neighborhood.Region', function ($q) use ($employeeGroupIds) {
                    $q->whereIn('id', $employeeGroupIds);
                });

                return true;
            }

            $ids = collect();
        } elseif (hasPermission('clients_view_own_clients')) {
            // «عملاؤه» تعني عملاءَ نطاقه: المشرفُ يرى عملاءَ مندوبيه معه
            $ids = ClientEmployee::whereIn('employee_id', $this->scopeEmployeeIds($user))
                ->pluck('client_id');
        } else {
            return null;
        }

        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return false;
        }

        $query->whereIn('id', $ids);

        return true;
    }

    /**
     * عملاءُ خطّ سير اليوم — لصاحب الخطّ ولمن يديره.
     *
     * ══════════ لماذا `whereIn` لا `where` ══════════
     *
     * كان القيدُ على `employee_id = $userId` وحدَه، فالمشرفُ الذي مُنح
     * «عرض العملاء حسب خطّ السير اليومي» يفتح «إدارة العملاء» فلا يجد إلّا
     * خطَّ سيره هو — وهو لا يجول أصلاً، فالشاشةُ فارغة — بينما خطوطُ
     * مندوبيه، وهي عملُه كلُّه، محجوبةٌ عنه بلا تفسير.
     *
     * والنطاقُ من {@see scopeUserIds()}: هو ومن يتبعه إدارياً، أو فرعُه،
     * أو هو وحدَه — نفسُ السلَّم الذي تقرؤه شاشاتُ المستندات والتقارير.
     *
     * و`employee_client_visits.employee_id` يحمل **`users.id`** لا
     * `employees.id` (المتحكّمُ يمرّر `$user->id` منذ كُتب) — ولذلك
     * `scopeUserIds()` لا `scopeEmployeeIds()`.
     */
    public function todayItineraryClientIds(int $userId, string $dayNameEn, int $year, int $week): Collection
    {
        $query = EmployeeClientVisit::where('day_of_week', $dayNameEn)
            ->where('year', $year)
            ->where('week_number', $week);

        if (($userIds = $this->scopeUserIds()) !== null) {
            $query->whereIn('employee_id', array_values(array_unique(array_merge($userIds, [$userId]))));
        }

        return $query->pluck('client_id');
    }

    /**
     * معرّفاتُ المستخدمين في نطاق المستخدم الحالي — `null` بلا قيد.
     *
     * {@see DataScope::documentUserIds()} هي المصدر: سلَّمٌ واحد لا نسخةٌ
     * ثانية منه هنا. ومن لا سجلَّ موظفٍ له ولا نطاقَ في دوره لا محورَ
     * لتقييده، فتضبطه صلاحياتُ العرض وحدَها — وهو استثناءُ `DataScope`
     * نفسُه وللسبب نفسِه.
     *
     * @return array<int, int>|null
     */
    public function scopeUserIds(): ?array
    {
        return DataScope::documentUserIds();
    }

    /**
     * معرّفاتُ الموظفين في نطاقه — لجداولِ الإسناد (`client_employee`،
     * `employee_groups`) وهي على `employees.id`.
     *
     * ولا تُعيد `null`: المستدعي يُمرّرها إلى `whereIn` مباشرةً، فالفراغُ
     * هناك يعني «لا أحد» لا «الكلّ». فمن لا قيدَ عليه يُمرَّر له موظفوه
     * كلُّهم صراحةً.
     *
     * @return array<int, int>
     */
    public function scopeEmployeeIds(?User $user = null): array
    {
        if (($ids = DataScope::documentEmployeeIds()) !== null) {
            return $ids;
        }

        $user ??= auth()->user();

        // بلا قيد: لا يُقصَر على أحد — كلُّ الموظفين، ومعهم هو.
        return Employee::pluck('id')->map(fn ($id) => (int) $id)
            ->push((int) ($user->employee_id ?? 0))->filter()->unique()->values()->all();
    }

    /** يوم اليوم/الأسبوع/السنة بنفس الحساب المستخدم في الشاشات الأربع (سبت → جمعة). */
    public function todayItineraryContext(): array
    {
        $now = now();
        $weekInfo = WeekHelper::getWeekAndYear($now);
        $englishDays = WeekHelper::getEnglishDays();
        $adjustedDayOfWeek = ($now->dayOfWeek + 1) % 7;

        return [
            'dayNameEn' => $englishDays[$adjustedDayOfWeek],
            'year' => $weekInfo['year'],
            'week' => $weekInfo['week'],
        ];
    }

    /**
     * نطاق فرع المستخدم في الشجرة: الفرع نفسه + كل أحفاده. null = بلا حصر.
     *
     * @return array<int>|null
     */
    public function resolveUserBranchTreeScope(?User $user): ?array
    {
        if (! $user || ! $user->branch_id) {
            return null;
        }
        $branch = Branch::withoutGlobalScopes()->find((int) $user->branch_id);
        if (! $branch) {
            return null;
        }
        if ($branch->is_main) {
            return null;
        }

        return $branch->descendantBranchIds()
            ->push((int) $branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * تطبيق نطاق شجرة الفرع على استعلام عملاء: «تابع لـ» العميل ضمن الشجرة.
     *
     * نفسُ مطابقة `Client::scopeInTreeNode()` عبر `AffiliationTree` — التبعيّةُ
     * الصريحة (`tree_node_id`) أوّلاً، والأعمدةُ القديمة لمن لا تبعيّة له فقط.
     * كانت تطابق `branch_id` (الفرعَ المحاسبيّ المشتقّ) والحيَّ لكلّ عميل،
     * فيظهر عميلُ منطقةٍ تحت الفرع الذي يُحاسَب عليه ويختفي من منطقته حين
     * يُختار فرعٌ من الهيدر — ٤٥ عميلاً في الرياض والشرقية ومكة.
     *
     * @param  array<int>|null  $scopeIds
     */
    public function applyClientBranchTreeScope(Builder $query, ?array $scopeIds): void
    {
        if ($scopeIds === null) {
            return;
        }
        if (count($scopeIds) === 0) {
            $query->whereRaw('0 = 1');

            return;
        }
        $ids = array_values(array_unique(array_map('intval', $scopeIds)));

        AffiliationTree::constrainClients($query, $ids, Schema::hasColumn('clients', 'group_id'));
    }

    /**
     * معرّفات العملاء الذين يستطيع هذا المستخدم الوصول إليهم فعلياً — النسخة
     * الكاملة والصحيحة من التسلسل (تشمل احتياط الموظف العادي واحتياط الفرع)،
     * تُستخدم في تحقّقات الوصول الجديدة (canAccessClient) لا في شاشات العرض
     * القديمة التي لها خصوصياتها الحالية المحفوظة كما هي.
     */
    public function getAccessibleClientIds(User $user): Collection
    {
        if (hasPermission('clients_view_all_clients')) {
            $query = Client::query();
            $this->applyClientBranchTreeScope($query, $this->resolveUserBranchTreeScope($user));

            return $query->pluck('id');
        }

        $context = $this->todayItineraryContext();
        $query = Client::query();

        $matched = $this->applyPermissionCascade($query, $user, $context['dayNameEn'], $context['year'], $context['week']);

        if ($matched !== null) {
            // true = نطاق غير فارغ، false = طابقت لكن فارغة (whereRaw('1=0') مطبَّق بالفعل) — كلاهما نتيجة صحيحة هنا
            return $query->pluck('id');
        }

        if ($user->isEmployee()) {
            return $this->todayItineraryClientIds($user->id, $context['dayNameEn'], $context['year'], $context['week']);
        }

        if ($user->branch_id) {
            $this->applyClientBranchTreeScope($query, $this->resolveUserBranchTreeScope($user));

            return $query->pluck('id');
        }

        return collect();
    }

    /** هل يستطيع هذا المستخدم الوصول لهذا العميل تحديداً — الحارس المستخدم في تسجيل الزيارات. */
    public function canAccessClient(User $user, int $clientId): bool
    {
        if (hasPermission('clients_view_all_clients')) {
            $query = Client::query()->where('id', $clientId);
            $this->applyClientBranchTreeScope($query, $this->resolveUserBranchTreeScope($user));

            return $query->exists();
        }

        return $this->getAccessibleClientIds($user)->contains($clientId);
    }
}
