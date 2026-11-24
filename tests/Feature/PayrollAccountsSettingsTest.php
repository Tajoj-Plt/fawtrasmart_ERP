<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Salaries\PayrollAccountsController;
use Tests\TestCase;

/**
 * شاشة حسابات الرواتب (§15 TODO 2.2): المصروف والأبوان.
 *
 * المصروف يُكتب في `payroll_settings` لأن الصرف يقرؤه من هناك.
 * الأبوان يُكتبان في `accounting_settings` لأن إنشاء حساب الموظف يقرأها.
 */
class PayrollAccountsSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();
        AccountingSettings::forget();

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('send_salary_slips')->default(false);
            $t->string('default_template', 8)->default('ar');
            $t->unsignedBigInteger('salary_expense_account_id')->nullable();
            $t->timestamps();
        });

        Schema::create('accounting_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->string('group', 100)->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->string('account_code', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        PayrollSettings::forget();
        AccountingSettings::forget();
        parent::tearDown();
    }

    public function test_settings_route_uses_payroll_settings_permission_and_salary_items_plan(): void
    {
        $this->assertTrue(Route::has('SalarySittings.accounts'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'SalarySittings.accounts');

        $this->assertContains('permission.role:salaries_payroll_settings_manage', $route->gatherMiddleware());
        $this->assertContains('plan:hr_salary_items', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_settings_manage',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_update_writes_expense_and_both_parents(): void
    {
        $expense = Account::create(['name' => 'رواتب', 'code' => '5301']);
        $payables = Account::create(['name' => 'مستحقات', 'code' => '215']);
        $advances = Account::create(['name' => 'سلف', 'code' => '1243']);

        $this->startSession();
        $controller = app(PayrollAccountsController::class);
        $request = Request::create('/hr/SalarySittings/accounts', 'POST', [
            'salary_expense_account_id' => $expense->id,
            'employee_payables_parent_id' => $payables->id,
            'employee_advances_parent_id' => $advances->id,
        ]);
        $request->setLaravelSession(app('session.store'));

        $controller->update($request);

        $this->assertEquals($expense->id, (int) PayrollSettings::value('salary_expense_account_id'));
        $this->assertEquals($expense->id, AccountingSettings::id('employees_salaries_expense'));
        $this->assertEquals($payables->id, AccountingSettings::id('employee_payables_parent'));
        $this->assertEquals($advances->id, AccountingSettings::id('employee_advances_parent'));

        $this->assertSame('5301', AccountingSetting::query()->where('key', 'employees_salaries_expense')->value('account_code'));
    }
}
