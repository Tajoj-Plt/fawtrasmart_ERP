<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * تحقّقُ `direct_manager_id` — إغلاقُ ثلاث حالاتٍ كانت تمرّ من
 * `exists:employees,id` وحدها: مرجعيةٌ ذاتية (موظفٌ مديرٌ لنفسه)، حلقةٌ
 * في التسلسل الإداري (مرؤوسٌ يُصبح مدير مديره)، ومديرٌ مؤرشَف
 * (`exists` يفحص الجدول الخام بلا وعيٍ بـ`SoftDeletes`).
 */
class EmployeeDirectManagerValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->unsignedBigInteger('direct_manager_id')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->foreign('direct_manager_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'موظف',
            'email' => 'e'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
        ]);
    }

    /** @return array<string, mixed> */
    private function validate(int $employeeId, ?int $directManagerId): array
    {
        $request = new EmployeeRequest;
        $request->merge(['id' => $employeeId, 'direct_manager_id' => $directManagerId]);

        $validator = Validator::make(
            $request->all(),
            ['direct_manager_id' => $request->rules()['direct_manager_id']],
            $request->messages()
        );

        return [
            'fails' => $validator->fails(),
            'errors' => $validator->errors()->get('direct_manager_id'),
        ];
    }

    public function test_an_employee_cannot_be_their_own_manager(): void
    {
        $employee = $this->employee();

        $result = $this->validate($employee->id, $employee->id);

        $this->assertTrue($result['fails'], 'مرّت المرجعية الذاتية من التحقّق');
    }

    public function test_a_subordinate_cannot_become_their_own_managers_manager(): void
    {
        $manager = $this->employee();
        $subordinate = $this->employee(['direct_manager_id' => $manager->id]);

        // لو نجح هذا لأصبح المرؤوسُ مديرَ مديره: حلقة.
        $result = $this->validate($manager->id, $subordinate->id);

        $this->assertTrue($result['fails'], 'مرّت حلقةٌ في التسلسل الإداري من التحقّق');
    }

    public function test_a_deep_descendant_cannot_become_a_managers_manager(): void
    {
        $ceo = $this->employee();
        $manager = $this->employee(['direct_manager_id' => $ceo->id]);
        $subordinate = $this->employee(['direct_manager_id' => $manager->id]);

        // subordinate يتبع manager الذي يتبع ceo — فاختيار subordinate
        // مديراً لـceo يصنع حلقةً غير مباشرة عبر مستويين.
        $result = $this->validate($ceo->id, $subordinate->id);

        $this->assertTrue($result['fails'], 'مرّت حلقةٌ غير مباشرة (مستويان) من التحقّق');
    }

    public function test_an_archived_employee_cannot_be_selected_as_manager(): void
    {
        $manager = $this->employee();
        $employee = $this->employee();
        $manager->delete();

        $result = $this->validate($employee->id, $manager->id);

        $this->assertTrue($result['fails'], 'مرّ مديرٌ مؤرشَف من التحقّق');
    }

    public function test_a_valid_distinct_active_manager_is_accepted(): void
    {
        $manager = $this->employee();
        $employee = $this->employee();

        $result = $this->validate($employee->id, $manager->id);

        $this->assertFalse($result['fails'], 'رُفض اختيارُ مديرٍ صحيحٍ ونشط: '.implode(' | ', $result['errors']));
    }

    public function test_a_null_manager_is_accepted(): void
    {
        $employee = $this->employee();

        $result = $this->validate($employee->id, null);

        $this->assertFalse($result['fails'], 'رُفض ترك المدير المباشر فارغاً');
    }
}
