<?php

namespace Modules\HR\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * تعيينُ موظفين لسجلٍّ مشترك (دفتر حضور، وردية مخصّصة…) تحت نطاق الرؤية.
 *
 * يُستعمل مع {@see ResolvesEmployeesByRules} و{@see ScopesToAccessibleEmployees}:
 *
 *   • الاختيارُ الصريح لموظفٍ خارج النطاق يُرفض (403).
 *   • مطابقو القواعد (فرع/قسم/مسمى) يُقصرون على النطاق.
 *   • من كان مرتبطاً بالسجلّ من خارج نطاق المستخدم **يبقى** مرتبطاً — لا يُسقطه
 *     تعديلُ من لا يراه أصلاً.
 */
trait ScopesEmployeeAssignments
{
    /**
     * @param  array<int, int|string>  $existingIds  المرتبطون حالياً بالسجلّ
     * @return array<int, int>
     */
    protected function scopedAssignment(Request $request, array $existingIds = []): array
    {
        $ids = $this->resolveEmployeeIdsFromRequest($request);
        $allowed = $this->accessibleEmployeeIds();

        if ($allowed === null) {
            return $ids;
        }

        if ($request->input('use_rules') !== 'rules') {
            foreach ($ids as $id) {
                $this->authorizeEmployeeScope($id);
            }
        } else {
            $ids = array_values(array_intersect($ids, $allowed));
        }

        $hidden = array_diff(array_map('intval', $existingIds), $allowed);

        return array_values(array_unique(array_merge($ids, $hidden)));
    }

    /**
     * ما يُعرض في شاشة التعديل «مستبعَداً»: من النطاق وحده.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function onlyListable(array $ids): array
    {
        $allowed = $this->listEmployeeIds();

        return $allowed === null ? $ids : array_values(array_intersect($ids, $allowed));
    }

    /** علاقةُ موظفين مقصورةٌ على النطاق — للتحميل المسبق والعدّ. */
    protected function scopedEmployeesRelation(): \Closure
    {
        return fn ($q) => $this->scopeAnyQueryToEmployees($q, 'employees.id');
    }
}
