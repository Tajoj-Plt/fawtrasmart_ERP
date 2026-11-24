<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use Tests\TestCase;

/**
 * توسيعُ enum status ليشمل leave وholiday.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `AttendanceProcessorService::markAsHoliday/markAsWeeklyOff/markAsLeave`
 * تكتب `AttendanceDays::STATUS_HOLIDAY`/`STATUS_LEAVE` منذ زمن، وقوالبُ
 * دفتر الحضور تقرأ `status == 'leave'` — لكنّ enum العمود في MySQL بقي
 * أربعةً فقط. فكلُّ يومِ عطلةٍ أو إجازةٍ يعالجه الكرون كان يفشل بخطأ SQL
 * صريح (`STRICT_TRANS_TABLES` مفعَّلة على قاعدة التطوير).
 *
 * **لماذا لا يُختبر هنا سلوكياً عبر استدعاء المعالج؟** لأن جداول
 * الاختبار على sqlite — التي يفرضها `phpunit.xml` — تُبنى يدوياً بعمود
 * `status` نصيّاً حراً (كما في [BarcodeAttendanceCheckInGuardTest])، فلا
 * يُعيد إنتاج قيدَ الـenum في MySQL أصلاً؛ اختبارٌ من هذا النوع كان سينجح
 * حتى قبل هذا الإصلاح. **التحقّق الحقيقي جرى مباشرةً على قاعدة التطوير
 * الحيّة**: تطبيق الهجرة، إدراج صفٍّ بـ`status = 'holiday'` و`'leave'`
 * ونجاحه، ثم التراجع (`down()`) والتأكد أنه يُرجع صفوف leave/holiday
 * الموجودة فعلياً إلى `absent` بدل رفض التضييق.
 *
 * وما يبقى قابلاً للاختبار آلياً هنا: أن الثوابت التي يعتمد عليها
 * المعالج والقوالب لا تزال بنفس القيم النصية التي وسّعتها الهجرة —
 * فانحرافٌ في أيٍّ منهما يُعيد المشكلة بصمت.
 */
class AttendanceDaysStatusEnumTest extends TestCase
{
    public function test_the_model_constants_match_what_the_migration_widened_the_enum_to(): void
    {
        $this->assertSame('leave', AttendanceDays::STATUS_LEAVE);
        $this->assertSame('holiday', AttendanceDays::STATUS_HOLIDAY);
    }

    public function test_the_migration_widens_the_enum_to_include_leave_and_holiday(): void
    {
        $path = base_path('database/migrations/2026_09_01_090100_add_leave_holiday_to_attendance_days_status_enum.php');

        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            "/ENUM\('present','absent','late','half_day','leave','holiday'\)/",
            $source,
            'الهجرة لا تحوي القيم الست في up()'
        );
    }

    /** والتراجع لا يُسقط بيانات صامتاً: يُرجع leave/holiday إلى absent قبل تضييق الـenum. */
    public function test_the_migration_backfills_before_narrowing_on_rollback(): void
    {
        $path = base_path('database/migrations/2026_09_01_090100_add_leave_holiday_to_attendance_days_status_enum.php');

        $source = (string) file_get_contents($path);

        $this->assertStringContainsString("whereIn('status', ['leave', 'holiday'])", $source);
        $this->assertStringContainsString("'status' => 'absent'", $source);
    }
}
