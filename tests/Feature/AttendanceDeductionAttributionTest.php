<?php

namespace Tests\Feature;

use App\Models\Deduction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * خصمُ الحضور يُنسَب إلى من أنشأه، لا إلى المستخدم رقم ١.
 *
 * **ما كان**
 *
 * `AttendanceProcessorService` يكتب `created_by_user => 1` و
 * `approved_by_user => 1` و`user_id => 1` نصّاً حين يولّد خصميةَ غيابٍ أو
 * تأخير. والمستخدمُ رقم واحد شخصٌ حقيقيٌّ في كل قاعدة: فيحمل خصمٌ من أجرِ
 * موظفٍ اسمَه منشئاً ومعتمِداً، في شاشة الخصميات وفي تاريخها، لشيءٍ لم
 * يُنشئه ولم يعتمده. وأثرُ تدقيقٍ يشير إلى الشخص الخطأ يضلّل من يقرؤه،
 * والفراغُ يقول الحقيقة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الرقمَ المكتوبَ نصّاً زال، وأنّ النسبةَ صارت إلى صاحب الجلسة إن وُجد
 * وإلى `null` في مسار الكرون، وأنّ الفراغَ يُقرأ «النظام» لا شرطةً تُوهم
 * ببيانٍ ناقص.
 */
class AttendanceDeductionAttributionTest extends TestCase
{
    /** الرقمُ المكتوبُ نصّاً زال، والنسبةُ صارت إلى صاحب الجلسة. */
    public function test_the_deduction_is_not_attributed_to_user_one(): void
    {
        $source = (string) file_get_contents(app_path('Services/AttendanceProcessorService.php'));

        $this->assertStringNotContainsString("'created_by_user' => 1", $source);
        $this->assertStringNotContainsString("'approved_by_user' => 1", $source);
        $this->assertStringNotContainsString("'user_id' => 1,", $source);
        $this->assertStringContainsString("'created_by_user' => \$actor", $source);
        $this->assertStringContainsString('$actor = Auth::id();', $source);
    }

    /** والفراغُ يُقرأ «النظام». */
    public function test_a_system_created_deduction_reads_as_the_system(): void
    {
        Schema::create('deductions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status', 30)->default(Deduction::STATUS_APPROVED);
            $t->unsignedBigInteger('created_by_user')->nullable();
            $t->unsignedBigInteger('approved_by_user')->nullable();
            $t->timestamps();
        });

        DB::table('deductions')->insert([
            'employee_id' => 5,
            'amount' => 300,
            'status' => Deduction::STATUS_APPROVED,
            'created_by_user' => null,
            'approved_by_user' => null,
        ]);

        $deduction = Deduction::first();

        $this->assertSame('النظام', $deduction->creator_label);
        $this->assertSame('النظام', $deduction->approver_label);
    }

    /** والشاشتان تعرضان الاسمَ المقروء لا الحقلَ الخام. */
    public function test_the_screens_show_the_readable_label(): void
    {
        $index = (string) file_get_contents(
            base_path('Modules/HR/resources/views/salaries/deductions/index.blade.php')
        );
        $show = (string) file_get_contents(
            base_path('Modules/HR/resources/views/salaries/deductions/show.blade.php')
        );

        $this->assertStringContainsString('creator_label', $index);
        $this->assertStringContainsString('creator_label', $show);
        $this->assertStringContainsString('approver_label', $show);
    }
}
