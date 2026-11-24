<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;

/**
 * يربط أعلام موافقات الإجازة في «الإعدادات الأساسية» بمسار الاعتماد.
 *
 * كانت تُحفظ دون فحص: أيُّ مستخدمٍ بصلاحية المسار يعتمد أيَّ طلب.
 * إن فُعِّل أيُّ قناة، يجب أن يطابق المُعتمِد واحدةً منها على الأقل.
 * بلا قنوات مفعَّلة يبقى سلوك الصلاحية كما كان.
 */
class LeaveApprovalAuthorizer
{
    public function canDecide(?User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user) {
            return false;
        }

        /*
            لا يبتّ أحدٌ في إجازته. كان من يملك صلاحية الاعتماد يطلب إجازةً
            لنفسه ويعتمدها — والقنواتُ مُطفأةٌ افتراضياً فيمرّ كلُّ شيء.
        */
        if ($user->employee_id && (int) $user->employee_id === (int) $leaveRequest->employee_id) {
            return false;
        }

        $settings = AttendanceSetting::current();
        $channelsEnabled = $settings->direct_manager_approval
            || $settings->department_manager_approval
            || $settings->employees_approval;

        if (! $channelsEnabled) {
            return true;
        }

        $actorId = $user->employee_id ? (int) $user->employee_id : null;

        // حساب إداري بلا ملف موظف — يتجاوز قنوات الموظفين
        if (! $actorId) {
            return true;
        }

        $employee = $leaveRequest->employee ?? Employee::find($leaveRequest->employee_id);

        if (! $employee) {
            return false;
        }

        if ($settings->direct_manager_approval
            && (int) $employee->direct_manager_id === $actorId) {
            return true;
        }

        if ($settings->department_manager_approval && $employee->department_id) {
            $managesInDepartment = Employee::query()
                ->where('direct_manager_id', $actorId)
                ->where('department_id', $employee->department_id)
                ->exists();

            if ($managesInDepartment || (int) $employee->direct_manager_id === $actorId) {
                return true;
            }
        }

        if ($settings->employees_approval
            && $settings->employees()->where('employees.id', $actorId)->exists()) {
            return true;
        }

        return false;
    }
}
