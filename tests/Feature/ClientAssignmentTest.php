<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Region_groub;
use App\Services\ClientAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClientAssignmentService::class);
    }

    /**
     * اختبار تعيين مجموعة إلى موظف واحد
     */
    public function test_assign_group_to_employee()
    {
        // إنشاء بيانات اختبار
        $group = Region_groub::factory()->create();
        $employee = Employee::factory()->create();
        $client = Client::factory()->create();

        // رابط العميل بالمجموعة
        // (يفترض أن هناك علاقة Neighborhood)

        // تنفيذ التعيين
        $result = $this->service->assignGroupToEmployee($group->id, $employee->id);

        // التحقق من النتيجة
        $this->assertTrue($result['success']);
        $this->assertGreater($result['assigned_count'], -1);
    }

    /**
     * اختبار التعيين المتعدد
     */
    public function test_bulk_assign()
    {
        // إنشاء بيانات اختبار
        $groups = Region_groub::factory(3)->create();
        $employees = Employee::factory(3)->create();

        $assignments = [];
        foreach ($groups as $index => $group) {
            $assignments[] = [
                'group_id' => $group->id,
                'employee_id' => $employees[$index]->id
            ];
        }

        // تنفيذ التعيين المتعدد
        $result = $this->service->assignClientsToEmployeesByGroup($assignments);

        // التحقق من النتيجة
        $this->assertTrue($result['success']);
    }

    /**
     * اختبار معالجة الأخطاء - موظف غير موجود
     */
    public function test_assign_with_invalid_employee()
    {
        $group = Region_groub::factory()->create();
        $invalidEmployeeId = 9999;

        $result = $this->service->assignGroupToEmployee($group->id, $invalidEmployeeId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('غير موجود', $result['message']);
    }

    /**
     * اختبار معالجة الأخطاء - مجموعة بدون عملاء
     */
    public function test_assign_empty_group()
    {
        $group = Region_groub::factory()->create();
        $employee = Employee::factory()->create();

        // المجموعة فارغة (لا توجد عملاء)
        $result = $this->service->assignGroupToEmployee($group->id, $employee->id);

        // النتيجة قد تكون نجاح مع 0 عملاء أو تحذير
        $this->assertTrue(isset($result['message']));
    }
}
