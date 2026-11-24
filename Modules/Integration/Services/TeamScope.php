<?php

namespace Modules\Integration\Services;

use App\Models\Employee;
use App\Models\User;
use App\Support\DataScope;

/**
 * «من أرى» في شاشات الأشخاص (تتبّع الموقع، الزيارات المنسيّة) — المستخدمون
 * لا الموظفون، لأن جداول الزيارات والتتبّع تحمل `users.id`.
 *
 * القاعدة نفسها المعتمدة في المالية والمخزون: سلَّم النطاق
 * ({@see DataScope::documentUserIds()}) قد يوسّع ولا يضيّق عن الفريق —
 * فالمشرف يرى نفسه ومن تحته في التسلسل الإداري مهما كان نطاق دوره، ومن لا
 * قيد عليه (`data_scope_all` أو غير موظف) يرى الجميع (`null`).
 */
class TeamScope
{
    /** @return array<int, int>|null  null = بلا قيد */
    public static function userIds(User $user): ?array
    {
        $ladder = DataScope::documentUserIds();
        if ($ladder === null) {
            return null;
        }

        $team = [];
        if ($user->employee_id && ($employee = Employee::find($user->employee_id))) {
            $descendants = $employee->getDescendantIds();
            $team = $descendants
                ? User::whereIn('employee_id', $descendants)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];
        }

        return array_values(array_unique(array_map('intval', array_merge($ladder, [$user->id], $team))));
    }

    public static function covers(User $user, int $userId, ?array $scope = null): bool
    {
        if ($userId === (int) $user->id) {
            return true;
        }

        $ids = func_num_args() >= 3 ? $scope : self::userIds($user);

        return $ids === null || in_array($userId, $ids, true);
    }
}
