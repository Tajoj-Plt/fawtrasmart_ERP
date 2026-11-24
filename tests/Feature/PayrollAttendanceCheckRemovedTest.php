<?php

namespace Tests\Feature;

use App\Models\Payroll;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * إزالةُ `payrolls.attendance_check` — خانة واجهة كانت تُحفظ وتُعرض ولا
 * تُقرأ عند أي ترحيل أو توليد صرف. قرارُ عملٍ صريح: الحذف بدل الإبقاء
 * على خانةٍ تُوهم مستخدم HR بأنها تشترط تحقق حضور فعلي قبل الصرف.
 *
 * الهجرةُ نفسُها (لا محاكاةٌ لمنطقها) تُحمَّل وتُشغَّل هنا — استعلامُ
 * Schema عادي يعمل على sqlite دون حاجةٍ لصياغة MySQL خاصة.
 */
class PayrollAttendanceCheckRemovedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('attendance_check')->default(false);
            $t->timestamps();
        });
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_09_01_090300_drop_attendance_check_from_payrolls_table.php');
    }

    public function test_the_migration_drops_the_column(): void
    {
        $this->assertTrue(Schema::hasColumn('payrolls', 'attendance_check'));

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('payrolls', 'attendance_check'));
    }

    public function test_rolling_back_restores_the_column(): void
    {
        $this->migration()->up();
        $this->assertFalse(Schema::hasColumn('payrolls', 'attendance_check'));

        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('payrolls', 'attendance_check'));
    }

    public function test_the_model_no_longer_lists_it_as_fillable(): void
    {
        $this->assertNotContains('attendance_check', (new Payroll())->getFillable());
    }

    /** ولم يبقَ أثرٌ منها في النماذج والشاشات التي كانت تعرضها. */
    public function test_no_view_or_controller_references_it_anymore(): void
    {
        $paths = [
            base_path('Modules/HR/resources/views/salaries/payroll_ process/create.blade.php'),
            base_path('Modules/HR/resources/views/salaries/payroll_ process/edit.blade.php'),
            base_path('Modules/HR/resources/views/salaries/payroll_ process/show.blade.php'),
            base_path('Modules/HR/Http/Controllers/Salaries/PayrollProcessController.php'),
            base_path('app/Models/Payroll.php'),
        ];

        foreach ($paths as $path) {
            $this->assertStringNotContainsString(
                'attendance_check',
                (string) file_get_contents($path),
                basename($path).' ما زال يذكر attendance_check'
            );
        }
    }
}
