<?php

namespace Tests\Feature;

use App\Models\CronJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\SettingsController;
use Tests\TestCase;

/**
 * لوحةُ إعدادات الحضور.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن حالةَ «الإغلاق التلقائي للأيام غير المكتملة» تصل إلى الشاشة.
 * مهمّةُ `attendance:process` هي التي تُغلق يومَ من لم يُسجِّل وتُنشئ
 * خصمياتِ الغياب والتأخير — وحين تتوقّف لا يظهر ذلك في شاشةٍ واحدةٍ من
 * شاشات الحضور: الأيامُ تبقى مفتوحةً، والرواتبُ تُصرف على بياناتٍ لم
 * تُعالَج، ولا شيءَ يقول إن شيئاً توقّف.
 *
 * وأن أسماءَ البلاطات تُطابق أسماءَ القائمة الجانبية — فلا يُسمَّى
 * المُسمَّى الواحدُ باسمَين.
 */
class AttendanceSettingsHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cron_jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('key')->nullable();
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->string('type')->nullable();
            $t->string('handler')->nullable();
            $t->text('parameters')->nullable();
            $t->string('status')->default('active');
            $t->boolean('is_system')->default(false);
            $t->boolean('is_protected')->default(false);
            $t->boolean('prevent_overlap')->default(true);
            $t->integer('timeout')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('last_finished_at')->nullable();
            $t->string('last_status')->nullable();
            $t->integer('last_duration_ms')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
    }

    private function controller(): SettingsController
    {
        return new class extends SettingsController
        {
            public function __construct() {}
        };
    }

    /** المهمّةُ المجدولةُ تصل إلى الشاشة حين تكون موجودة. */
    public function test_the_auto_close_job_reaches_the_screen(): void
    {
        $job = CronJob::create([
            'key' => 'attendance-process',
            'name' => 'معالجة الحضور اليومي',
            'handler' => 'attendance:process',
            'status' => CronJob::STATUS_ACTIVE,
            'last_run_at' => now()->subHours(2),
            'last_status' => 'success',
        ]);

        $data = $this->controller()->index()->getData();

        $this->assertArrayHasKey('autoCloseJob', $data);
        $this->assertSame($job->id, $data['autoCloseJob']->id);
        $this->assertTrue($data['autoCloseJob']->isActive());
    }

    /** وغيابُها يصل كذلك — فتُنبَّه الشاشةُ إلى أن لا إغلاقَ آليّاً أصلاً. */
    public function test_a_missing_job_is_reported_as_null_not_hidden(): void
    {
        $this->assertNull($this->controller()->index()->getData()['autoCloseJob']);
    }

    /** ومهمّةٌ أخرى ليست هي: المطابقةُ بـ`handler` لا بأيّ اسم. */
    public function test_another_job_is_not_mistaken_for_it(): void
    {
        CronJob::create([
            'key' => 'payroll',
            'name' => 'معالجة الرواتب',
            'handler' => 'payroll:process',
            'status' => CronJob::STATUS_ACTIVE,
        ]);

        $this->assertNull($this->controller()->index()->getData()['autoCloseJob']);
    }

    /** وأسماءُ البلاطات هي أسماءُ القائمة نفسُها. */
    public function test_the_tiles_use_the_same_names_as_the_sidebar(): void
    {
        $hub = (string) file_get_contents(
            base_path('Modules/HR/resources/views/attendance/settings/index.blade.php')
        );

        foreach ([
            'سجلات الحضور',
            'أيام الحضور',
            'طلبات تصحيح الحضور',
            'اعتماد طلبات التصحيح',
            'دفاتر الحضور',
            'أذونات إجازة',
            'طلبات الإجازة',
            'سجل جلسات الحضور',
            'إدارة بطاقات الحضور',
            'واجهة الكشك (جهاز الفرع)',
        ] as $name) {
            $this->assertStringContainsString($name, $hub, "بلاطة «{$name}» ناقصةٌ أو مسمّاةٌ بغير اسم القائمة");
        }
    }

    /** ولا تبقى الأسماءُ القديمةُ جنباً إلى جنبٍ مع الجديدة. */
    public function test_the_superseded_tile_names_are_gone(): void
    {
        $hub = (string) file_get_contents(
            base_path('Modules/HR/resources/views/attendance/settings/index.blade.php')
        );

        foreach (['سجل الأختام', 'سجل اليوم الموحد'] as $old) {
            $this->assertStringNotContainsString($old, $hub, "الاسمُ القديم «{$old}» ما زال في اللوحة");
        }
    }
}
