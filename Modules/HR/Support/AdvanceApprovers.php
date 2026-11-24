<?php

namespace Modules\HR\Support;

use App\Models\Department;
use App\Models\User;
use App\Support\PayrollSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «الموظف المعتمد» في طلب السلفة — من يجوز أن يُختار.
 *
 * كانت القائمةُ `User::all()` مصفّاةً بـ`hasPermission()` — والدالّةُ تسأل عن
 * **المستخدم الحالي** لا عن كلّ مستخدمٍ في الحلقة. فمن كان يملك صلاحيةَ
 * الاعتماد رأى مستخدمي النظام كلَّهم معتمِدين، ومن لا يملكها رأى المدراءَ
 * بأسماء أدوارهم فحسب. فالشرطان الآن على المرشَّح نفسه:
 *
 *   1. **الصلاحية**: دورُه الأساسيّ (`role_id`) يملك `loans.approve`
 *      — المصدرُ نفسُه الذي يقرؤه حارسُ مسار الاعتماد، فلا يُختار من يُردّ.
 *   2. **القسم**: إن ضُبطت في «إعدادات السلف» أقسامُ الاعتماد، فلا يُعرض إلّا
 *      موظفو تلك الأقسام (قسمُه الأساسي أو عضويّتُه في `department_employee`).
 *      وبلا ضبطٍ يكفي الشرطُ الأوّل — ولا يُعرض من لا يملكه أبداً.
 *
 * ولا يختار الموظفُ نفسَه: لا يعتمد أحدٌ طلبَه.
 */
class AdvanceApprovers
{
    public const PERMISSION = 'loans.approve';

    public const SETTING = 'advance_approver_department_ids';

    /** @return array<int> الأقسامُ المضبوطة — فارغةٌ = لا تقييدَ بالقسم */
    public static function departmentIds(): array
    {
        $raw = PayrollSettings::value(self::SETTING);

        if (! $raw) {
            return [];
        }

        $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }

    /** @return Collection<int, Department> */
    public static function departments(): Collection
    {
        $ids = self::departmentIds();

        return $ids === [] ? collect() : Department::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** @return array<int> الأدوارُ التي تملك صلاحيةَ الاعتماد */
    private static function roleIds(): array
    {
        return DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', self::PERMISSION)
            ->pluck('rp.role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * المعتمِدون المتاحون لطلبٍ يقدّمه هذا المستخدم.
     *
     * @return Collection<int, User>
     */
    public static function for(?User $requester = null): Collection
    {
        $roleIds = self::roleIds();

        if ($roleIds === []) {
            return collect();
        }

        $query = User::query()
            ->with('employee:id,first_name,middle_name,nickname,department_id')
            ->whereIn('role_id', $roleIds)
            ->orderBy('name');

        if ($requester) {
            $query->whereKeyNot($requester->getKey());
        }

        $departmentIds = self::departmentIds();

        if ($departmentIds !== []) {
            $employeeIds = DB::table('employees')->whereIn('department_id', $departmentIds)->pluck('id')
                ->merge(DB::table('department_employee')->whereIn('department_id', $departmentIds)->pluck('employee_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $query->whereIn('employee_id', $employeeIds ?: [0]);
        }

        return $query->get();
    }

    /** هل يجوز اختيارُ هذا المستخدم معتمِداً لطلب هذا المقدِّم؟ */
    public static function allows(int $userId, ?User $requester = null): bool
    {
        return self::for($requester)->contains(fn (User $u) => (int) $u->id === $userId);
    }
}
