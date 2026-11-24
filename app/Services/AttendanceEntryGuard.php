<?php

namespace App\Services;

use App\Models\AttendanceDeterminant;
use App\Models\Employee;
use App\Models\HolyDayListCustomize;

/**
 * بوّابةُ تسجيل الحضور: هل يُسمح لهذا الموظف بالتسجيل من هذا الموقع/الشبكة؟
 *
 * منطقُ الفحص نفسه كان مكتوباً ومكتملاً منذ البداية في
 * AttendanceDeterminant::canEmployeeCheckIn() — نطاقٌ جغرافي، وقائمةُ عناوين
 * مسموحة، وصورةٌ إلزامية، ولكلٍّ منها وضعان: مانعٌ أو تحذيريّ فقط.
 *
 * لكن لا مسارَ تسجيلٍ واحدٍ كان يستدعيه. فكانت شاشةُ «محددات الحضور» تحفظ
 * إحداثياتٍ ونطاقاتٍ وعناوينَ IP لا أثر لها على الإطلاق: يُسجَّل الحضورُ من
 * أيّ مكانٍ وأيّ شبكة. هذه الطبقةُ هي الوصلةُ الناقصة.
 *
 * ولمَ لا تُستعمل مفاتيح «إعدادات الحضور V2» العامّة؟ لأن جدول
 * attendance_settings يحمل مفتاحَي تشغيلٍ فقط بلا إحداثياتٍ ولا نطاقٍ ولا
 * قائمةِ عناوين — فلا يملك ما يتحقّق به. الهندسةُ كلُّها في المحدِّدات.
 */
class AttendanceEntryGuard
{
    /**
     * @return array{allowed: bool, errors: array<int, string>, warnings: array<int, string>, determinant: ?AttendanceDeterminant}
     */
    public function check(Employee $employee, array $entryData): array
    {
        $determinant = $this->resolveDeterminant($employee);

        if (! $determinant) {
            // لا محدِّد يخصّ هذا الموظف = لا قيود.
            return ['allowed' => true, 'errors' => [], 'warnings' => [], 'determinant' => null];
        }

        $result = $determinant->canEmployeeCheckIn($employee->id, $entryData);

        return [
            'allowed' => $result['allowed'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'determinant' => $determinant,
        ];
    }

    /**
     * المحدِّدُ النشط الذي يقع هذا الموظف تحته.
     *
     * الإسنادُ يمرّ عبر HolyDayListCustomize: إمّا إسنادٌ مباشرٌ للموظف
     * (use_rules = 2) أو بقاعدةٍ على الفرع/القسم/المسمّى (use_rules = 1).
     * الإسنادُ المباشر أَولى، فهو الأخصّ.
     */
    public function resolveDeterminant(Employee $employee): ?AttendanceDeterminant
    {
        $customizations = HolyDayListCustomize::whereNotNull('attendance_determinant_id')
            ->with('employees:id')
            ->get();

        $direct = null;
        $byRule = null;

        foreach ($customizations as $customization) {
            if ((int) $customization->use_rules === 2) {
                if ($customization->employees->contains('id', $employee->id)) {
                    $direct = $customization->attendance_determinant_id;
                    break;
                }

                continue;
            }

            if ($byRule === null && $this->matchesRule($employee, $customization)) {
                $byRule = $customization->attendance_determinant_id;
            }
        }

        $determinantId = $direct ?? $byRule;

        if (! $determinantId) {
            return null;
        }

        // status: 0 = نشط (انظر AttendanceDeterminant::getStatusTextAttribute)
        return AttendanceDeterminant::where('id', $determinantId)->where('status', 0)->first();
    }

    private function matchesRule(Employee $employee, HolyDayListCustomize $customization): bool
    {
        $matched = false;

        foreach ([
            'branch_id' => $employee->branch_id,
            'department_id' => $employee->department_id,
            'job_title_id' => $employee->job_title_id ?? null,
        ] as $column => $employeeValue) {
            if (! $customization->{$column}) {
                continue;
            }

            if ((int) $customization->{$column} !== (int) $employeeValue) {
                return false;
            }

            $matched = true;
        }

        return $matched;
    }
}
