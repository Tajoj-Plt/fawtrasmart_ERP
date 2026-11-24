<?php

namespace Tests\Feature;

use App\Constants\planFeatures\HrPlanFeatures;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Observers\PlanLimitObserver;
use App\Services\Fawtra\UsageReporter;
use Tests\TestCase;

/**
 * حدُّ الحضور أُحيل إلى حدّ الموظفين.
 *
 * **ما كان**
 *
 * مفتاحُ `attendance_records` يَعُدّ جدولَ `attendance_records` — طبقةَ
 * «الجلسات» التي لم تعد تُكتب وحُذفت شاشتُها — فتجمّد العدّادُ عند رقمه:
 * يُرفَع إلى فوترة استهلاكٌ ثابتٌ مهما سجّل الموظفون.
 *
 * **ولماذا لم يُعَد ربطُه بسجلّ الحضور الحيّ**
 *
 * `attendance_days` يحمل صفّاً لكل موظفٍ كل يوم — نحو ٢٢ سجلاً لكل موظفٍ
 * شهرياً بعد استبعاد العطلات. وذلك ×٢٢ أقربَ مفاتيح الوحدة إليه
 * (`payslips` = قسيمةٌ لكل موظف)، فيُخترق به أيُّ حدٍّ مُعايَرٍ على العدّاد
 * القديم في أيام. والكلفةُ الحقيقية تتبع **عددَ الموظفين** لا عددَ
 * أيامهم، وأيامُ الحضور مشتقّةٌ منه حسابياً — فقياسان لكلفةٍ واحدة
 * أحدُهما يكفي.
 *
 * **وقبل ذلك كلِّه**: `PlanLimitObserver::creating()` ترمي عند بلوغ الحدّ،
 * وصفوفُ الحضور تُنشَأ لحظةَ مسح البطاقة على باب الفرع وفي كرون الليل.
 * فأيُّ إلزامٍ على جداول الحضور يعني أن نفادَ الحصّة يمنع الموظفَ من
 * تسجيل حضوره ويقتل الكرونَ في منتصفه.
 */
class AttendanceQuotaMeasuresWithoutBlockingTest extends TestCase
{
    /** لا مفتاحَ حضورٍ في كتالوج القياس. */
    public function test_the_attendance_quota_key_is_retired_from_the_catalog(): void
    {
        $this->assertArrayNotHasKey(
            'attendance_records',
            UsageReporter::catalog(),
            'حدُّ الحضور أُحيل إلى حدّ الموظفين — فلا يُقاس مرّتين'
        );
    }

    /** ولا جدولَ حضورٍ يُقاس تحت أيّ مفتاحٍ آخر. */
    public function test_no_attendance_table_is_metered_under_another_key(): void
    {
        $tables = array_column(UsageReporter::catalog(), 'table');

        foreach (['attendance_days', 'attendance_punches', 'attendance_records'] as $table) {
            $this->assertNotContains(
                $table,
                $tables,
                "الجدول {$table} يُقاس تحت مفتاحٍ ما — والحدُّ محالٌ إلى الموظفين"
            );
        }
    }

    /** وحدُّ الموظفين هو الحاكم، وهو مُلزَمٌ فعلاً. */
    public function test_the_employee_cap_governs_and_is_enforced(): void
    {
        $catalog = UsageReporter::catalog();

        $this->assertSame('employees', $catalog[HrPlanFeatures::LIMIT_EMPLOYEES]['table'] ?? null);
        $this->assertSame('cap', $catalog[HrPlanFeatures::LIMIT_EMPLOYEES]['kind'] ?? null);

        $map = (fn () => static::map())->call(new PlanLimitObserver);

        $this->assertSame(
            HrPlanFeatures::LIMIT_EMPLOYEES,
            $map[Employee::class] ?? null,
            'سقفُ الموظفين هو ما يُلزِم فعلاً'
        );
    }

    /**
     * ولا نموذجَ من مسار الحضور مربوطٌ بالحارس الرامي.
     *
     * وهو الفارقُ بين «تحذيرٍ» و«بابٍ مغلق»: `creating()` ترمي.
     */
    public function test_no_attendance_model_is_wired_to_the_throwing_guard(): void
    {
        $map = (fn () => static::map())->call(new PlanLimitObserver);

        foreach ([
            AttendanceDays::class,
            AttendancePunch::class,
            AttendanceRecord::class,
        ] as $model) {
            $this->assertArrayNotHasKey(
                $model,
                $map,
                $model.' مربوطٌ بحارس الحدّ — ونفادُ الحصّة سيمنع تسجيل الحضور على الباب'
            );
        }
    }

    /** والثابتُ المتقاعد لا يُعاد بلا قصد. */
    public function test_the_retired_constant_is_gone(): void
    {
        $this->assertFalse(
            defined(HrPlanFeatures::class.'::LIMIT_ATTENDANCE_RECORDS'),
            'أُعيد تعريفُ LIMIT_ATTENDANCE_RECORDS — راجع سبب تقاعده في HrPlanFeatures'
        );
    }

    /** ورفعُ الاستهلاك لا ينكسر بغياب المفتاح: ما لا يعرفه الكتالوج يُتخطّى. */
    public function test_an_unknown_key_in_the_fawtra_snapshot_is_skipped_not_fatal(): void
    {
        $source = (string) file_get_contents(app_path('Services/Fawtra/UsageReporter.php'));

        $this->assertStringContainsString('$meta = $key ? ($catalog[$key] ?? null) : null;', $source);
        $this->assertMatchesRegularExpression(
            '/if \(\$meta === null\) \{\s*\$summary\[.skipped.\]\+\+;/u',
            $source,
            'push() يجب أن تتخطّى المفتاح المجهول لا أن تُخفق'
        );
    }
}
