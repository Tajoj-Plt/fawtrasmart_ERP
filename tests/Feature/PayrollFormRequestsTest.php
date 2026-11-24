<?php

namespace Tests\Feature;

use App\Models\Deduction;
use Illuminate\Support\Facades\Validator;
use Modules\HR\Http\Requests\AdvanceRequestRequest;
use Modules\HR\Http\Requests\ContractRequest;
use Modules\HR\Http\Requests\DeductionRequest;
use Modules\HR\Http\Requests\PayrollRunDisburseRequest;
use Modules\HR\Http\Requests\SalarySlipRequest;
use Tests\TestCase;

/**
 * خمسة FormRequests للرواتب (§15 TODO 2.4) بدل $request->validate الخام.
 */
class PayrollFormRequestsTest extends TestCase
{
    public function test_the_five_payroll_form_requests_exist(): void
    {
        $this->assertTrue(class_exists(SalarySlipRequest::class));
        $this->assertTrue(class_exists(PayrollRunDisburseRequest::class));
        $this->assertTrue(class_exists(ContractRequest::class));
        $this->assertTrue(class_exists(DeductionRequest::class));
        $this->assertTrue(class_exists(AdvanceRequestRequest::class));
    }

    public function test_controllers_type_hint_the_form_requests(): void
    {
        $pairs = [
            [module_path('HR', 'Http/Controllers/Salaries/SalarySlipController.php'), SalarySlipRequest::class],
            [module_path('HR', 'Http/Controllers/Salaries/PayrollRunController.php'), PayrollRunDisburseRequest::class],
            [module_path('HR', 'Http/Controllers/Salaries/ContractsController.php'), ContractRequest::class],
            [module_path('HR', 'Http/Controllers/Salaries/DeductionController.php'), DeductionRequest::class],
            [module_path('HR', 'Http/Controllers/Salaries/AdvanceRequestController.php'), AdvanceRequestRequest::class],
        ];

        foreach ($pairs as [$file, $class]) {
            $this->assertStringContainsString(
                class_basename($class),
                (string) file_get_contents($file),
                $file.' لا يستعمل '.class_basename($class)
            );
        }
    }

    public function test_salary_slip_store_rejects_an_empty_payload(): void
    {
        $request = new SalarySlipRequest;
        $request->setMethod('POST');
        $validator = Validator::make([], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('يرجى اختيار الموظف', $validator->errors()->first('employee_id'));
        $this->assertSame('الراتب الأساسي مطلوب', $validator->errors()->first('basic_amount'));
    }

    public function test_deduction_rejects_an_unknown_type(): void
    {
        $request = new DeductionRequest;
        $validator = Validator::make(
            ['deduction_type' => 'not-a-type'],
            ['deduction_type' => $request->rules()['deduction_type']],
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('نوع الخصمية غير صالح', $validator->errors()->first('deduction_type'));
        $this->assertContains(Deduction::TYPE_ABSENCE, array_keys(Deduction::types()));
    }

    public function test_advance_request_requires_amount_and_approver(): void
    {
        $request = new AdvanceRequestRequest;
        $validator = Validator::make([], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('يرجى إدخال مبلغ السلفة', $validator->errors()->first('amount'));
        $this->assertSame('يرجى اختيار الموظف المعتمد', $validator->errors()->first('approver_user_id'));
    }

    public function test_contract_and_disburse_carry_arabic_messages(): void
    {
        $contract = new ContractRequest;
        $this->assertSame('يرجى اختيار الموظف', $contract->messages()['employee_id.required']);

        $disburse = new PayrollRunDisburseRequest;
        $this->assertArrayHasKey('treasury_id', $disburse->rules());
        $this->assertSame('الخزينة المختارة غير موجودة', $disburse->messages()['treasury_id.exists']);
    }
}
