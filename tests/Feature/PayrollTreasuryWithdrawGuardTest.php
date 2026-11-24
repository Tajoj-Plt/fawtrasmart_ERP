<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TreasuryAccessGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * صرفُ الراتب يسأل شرطَ السحب من الخزينة كما يسأله سندُ الصرف.
 *
 * **ما كان**
 *
 * `guardTreasuryWithdraw()` مُنادىً في كلِّ مخرجٍ نقديٍّ في النظام —
 * `ExpensesController::store()` و`update()`، وتحويلاتُ `TreasuryController`،
 * وصرفُ الشيك في `PayableChequesController`. وكان صرفُ الراتب المخرجَ
 * الوحيد الذي لا يسأله: لا في `PayrollAccountant`، ولا في
 * `PayrollRunDisburseRequest` الذي يتحقّق من `exists:accounts,id` وحده.
 *
 * فخزينةٌ ضُبطت في شاشتها على «موظّف محدد» يصرف منها رواتبَ كلُّ من يملك
 * `salaries_payroll_payment` — والشاشة تَعِد بضبطٍ لا يسري.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الحارسَ المشترك يمنع من لا يملك السحب ويسمح لمن يملكه، وأنّه لا
 * يوسّع منعَه إلى ما لا شرطَ له (خزينةٌ بلا صفٍّ في `treasuries`، أو شرطٌ
 * «للكلّ»، أو مسارُ الكرون بلا جلسة). ثمّ يقيس أنّ مسارَ الرواتب يسأله
 * فعلاً وقبل فحصِ الرصيد.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class PayrollTreasuryWithdrawGuardTest extends TestCase
{
    /** أنواع الشرط كما تحفظها شاشة إنشاء الخزينة. */
    private const SCOPE_EVERYONE = 0;

    private const SCOPE_EMPLOYEE = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('treasuries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->string('name');
            $t->tinyInteger('type')->default(0);
            $t->tinyInteger('deposit_permissions')->default(0);
            $t->tinyInteger('withdraw_permissions')->default(0);
            $t->integer('value_of_deposit_permissions')->nullable();
            $t->integer('value_of_withdraw_permissions')->nullable();
            $t->timestamps();
        });
    }

    /**
     * خزينةٌ مربوطةٌ بالحساب 3، وشرطُ سحبها ما يُمرَّر.
     *
     * @param  int  $scope  نوعُ الشرط
     * @param  int|null  $value  قيمتُه
     */
    private function seedTreasury(int $scope, ?int $value): void
    {
        DB::table('treasuries')->insert([
            'account_id' => 3,
            'name' => 'خزينة الفرع',
            'type' => 0,
            'withdraw_permissions' => $scope,
            'value_of_withdraw_permissions' => $value,
        ]);
    }

    /** مستخدمٌ غيرُ محفوظ — الشرطُ يقرأ `employee_id` منه بلا استعلام. */
    private function actAsEmployee(int $employeeId): void
    {
        $user = new User;
        $user->employee_id = $employeeId;

        $this->actingAs($user);
    }

    /** من لا يملك السحب يُمنع، والرسالةُ تسمّي الخزينة. */
    public function test_a_user_outside_the_withdraw_scope_is_blocked(): void
    {
        $this->seedTreasury(self::SCOPE_EMPLOYEE, 5);
        $this->actAsEmployee(9);

        $reason = TreasuryAccessGuard::withdrawBlockReason(3);

        $this->assertNotNull($reason, 'خزينةٌ مقصورةٌ على موظّفٍ آخر يجب أن تُمنع');
        $this->assertStringContainsString('خزينة الفرع', $reason, 'الرسالةُ يجب أن تسمّي الخزينة');
        $this->assertStringContainsString('السحب من', $reason);
    }

    /** ومن يملكه يمرّ. */
    public function test_the_permitted_employee_passes(): void
    {
        $this->seedTreasury(self::SCOPE_EMPLOYEE, 5);
        $this->actAsEmployee(5);

        $this->assertNull(TreasuryAccessGuard::withdrawBlockReason(3));
    }

    /** شرطُ «الكلّ» لا يمنع أحداً. */
    public function test_an_unrestricted_treasury_blocks_nobody(): void
    {
        $this->seedTreasury(self::SCOPE_EVERYONE, null);
        $this->actAsEmployee(9);

        $this->assertNull(TreasuryAccessGuard::withdrawBlockReason(3));
    }

    /**
     * خزينةٌ لا يقابلها صفٌّ في `treasuries` مسموحةٌ للجميع: المنعُ يحتاج
     * شرطاً منصوصاً، وخزائنُ ما قبل الميزة لا يجوز أن تُقفل فجأةً.
     */
    public function test_an_account_with_no_treasury_row_is_not_blocked(): void
    {
        $this->seedTreasury(self::SCOPE_EMPLOYEE, 5);
        $this->actAsEmployee(9);

        $this->assertNull(TreasuryAccessGuard::withdrawBlockReason(4));
    }

    /**
     * ومسارُ الكرون يصرف بلا جلسة — والشرطُ يقيّد المستخدمين لا النظام،
     * فلا يجوز أن يوقف الصرفَ التلقائي.
     */
    public function test_an_unauthenticated_run_is_not_blocked(): void
    {
        $this->seedTreasury(self::SCOPE_EMPLOYEE, 5);

        $this->assertNull(TreasuryAccessGuard::withdrawBlockReason(3));
    }

    /**
     * حارسٌ على الشيفرة: مسارُ الرواتب يسأل الشرطَ، وقبل فحصِ الرصيد —
     * فمن لا يملك السحب لا يفيده أن يُقال له إنّ الرصيد لا يكفي.
     */
    public function test_the_payroll_posting_path_consults_the_guard_before_the_balance(): void
    {
        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));

        $guardAt = strpos($source, 'TreasuryAccessGuard::withdrawBlockReason');
        // علامةُ فحص الكفاية — صارت تقرأ رصيداً محتسباً من الدفتر لا العمود
        // المخزَّن، انظر [PayrollTreasuryBalanceGuardTest]
        $balanceAt = strpos($source, '< $calc->netSalary');

        $this->assertNotFalse(
            $guardAt,
            'PayrollAccountant يجب أن يسأل شرطَ السحب — وإلّا صرفُ الراتب مخرجٌ نقديٌّ بلا حارس'
        );
        $this->assertNotFalse($balanceAt, 'فحصُ رصيد الخزينة مفقود');
        $this->assertLessThan($balanceAt, $guardAt, 'شرطُ السحب يُفحص قبل الرصيد');
    }
}
