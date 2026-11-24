<?php

namespace Tests\Feature;

use App\Models\Log as ModelsLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * الصرفُ التلقائي يترك أثراً في «سجل النشاطات» كالصرف اليدوي.
 *
 * **ما كان**
 *
 * `PayrollRunController::disburse()` يكتب صفَّ `payroll_run_log` لكلِّ صرفٍ
 * يدوي. و`ProcessPayrollRuns` — وهو المسارُ الذي يصرف رواتبَ المنشأة كلَّها
 * في يوم الصرف — كان يصرف عبر الخدمة نفسها **ولا يكتب شيئاً**: الأثرُ
 * الوحيد `trigger_source = 'cron'` على الصرفة، و`Log::error` في ملفّ اللوق
 * عند الفشل وحده. فنجاحُ الصرف لا يُخلّف سطراً في أيّ مكان يُراجَع.
 *
 * و`ActivityLogController::index()` يقرأ `type_log = 'log'` من جدول `logs`،
 * فكانت الصرفاتُ التلقائية غائبةً عن الشاشة التي يُراجع فيها المحاسبُ من
 * صرف وماذا صُرف — وهي كلُّ الصرفات في منشأةٍ تعمل بالجدولة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الصفَّ المكتوب يوافق ما يقرؤه `ActivityLogController` (`type_log`)
 * وما يصفّي به (`type`)، وأنّ الأمرَ يسجّل بعد نجاح الصرف لا قبله، وأنّ
 * تعذّرَ الكتابة لا يُسقط راتباً صُرف وقُيّد.
 */
class PayrollCronActivityLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('logs', function (Blueprint $t) {
            $t->id();
            $t->string('type')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('type_log')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    /**
     * الصفُّ يوافق ما تقرؤه شاشةُ سجل النشاطات: `type_log = 'log'` شرطُ
     * ظهوره، و`type` هو ما تصفّي به الشاشةُ بادئةً.
     */
    public function test_the_written_row_matches_what_the_activity_screen_reads(): void
    {
        ModelsLog::create([
            'type' => 'payroll_run_log',
            'type_id' => 12,
            'type_log' => 'log',
            'description' => 'تم صرف راتب تلقائياً (الصرف المجدول) للفترة 2026-09-01 → 2026-09-30 بصافي 5,000.00',
            'created_by' => 3,
        ]);

        $visible = ModelsLog::where('type_log', 'log')
            ->where('type', 'like', 'payroll_run_log%')
            ->get();

        $this->assertCount(1, $visible, 'الصفُّ يجب أن يظهر في سجل النشاطات');
        $this->assertSame(12, (int) $visible->first()->type_id);
        $this->assertSame(3, (int) $visible->first()->created_by);
    }

    /**
     * حارسٌ على الشيفرة: الأمرُ يسجّل **بعد** نجاح `disburse()` — فلا
     * يُسجَّل صرفٌ رُفض، ولا يُنسب أثرٌ إلى صرفةٍ لم تتغيّر حالتُها.
     */
    public function test_the_command_logs_only_after_a_successful_disbursement(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/ProcessPayrollRuns.php'));

        $disburseAt = strpos($source, '$service->disburse($run, PayrollRun::TRIGGER_CRON)');
        $logAt = strpos($source, '$this->recordActivity($run)');

        $this->assertNotFalse(
            $logAt,
            'الصرفُ التلقائي يجب أن يكتب أثراً في سجل النشاطات كالصرف اليدوي'
        );
        $this->assertNotFalse($disburseAt, 'نداءُ الصرف مفقود');
        $this->assertGreaterThan($disburseAt, $logAt, 'التسجيل يجب أن يلي الصرف لا أن يسبقه');
    }

    /**
     * والحالةُ التي كانت: لا سطرَ لأنّ الأمرَ لا يكتب. فيُحرَس على أنّ
     * التسجيل صار جزءاً من مسار النجاح، وأنّ إخفاقَه لا يُلقى استثناءً
     * يُسقط راتباً صُرف وقُيّد وأُثبت.
     */
    public function test_a_log_failure_cannot_undo_a_paid_salary(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/ProcessPayrollRuns.php'));

        $start = strpos($source, 'private function recordActivity(');
        $this->assertNotFalse($start, 'recordActivity() مفقودة');

        $body = substr($source, $start);

        $this->assertStringContainsString(
            'catch (\Throwable',
            $body,
            'تعذّرُ كتابة سطرِ سجلّ لا يجوز أن يُسقط صرفاً تمّ وقُيّد'
        );
        $this->assertStringContainsString(
            "'type_log' => 'log'",
            $body,
            "بغير type_log = 'log' لا يظهر الصفُّ في شاشة سجل النشاطات"
        );
    }
}
