<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Exports\EmployeesExport;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * حالةُ الموظف في ملفّ التصدير.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان السطر: `$employee->status == 0 ? 'مفعل' : 'غير مفعل'` — **مقلوباً**.
 * فالنشط قيمتُه `1` لا `0`، وعليه كان التصديرُ يصف الموظفين النشطين
 * كلَّهم «غير مفعل».
 *
 * ولم تُكتب `0` اعتباطاً: كان `EmployeeController::updateStatus()` يحفظ
 * `! $status` — نفياً منطقياً على عمودٍ مجالُه `1`/`2` — فتخرج `0` من كل
 * تعطيل. فبُني الوصفُ على أثرِ عطلٍ لا على المجال المعلن.
 */
class EmployeesExportStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            // الموديل يستعمل `SoftDeletes` — فالعمود شرطُ كل استعلام عليه
            $t->softDeletes();
            $t->timestamps();
        });

        // EmployeesExport::selectedDefinitions() يقرأ Shift::pluck('name','id')
        // لترجمة معرّف الوردية إلى اسمها، فالجدولُ شرطٌ لبناء التعريفات حتى
        // حين لا يُصدَّر عمودُ الورديات.
        Schema::create('shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    private function make(int $status): Employee
    {
        return Employee::create([
            'first_name' => 'موظف',
            'email' => 'e'.uniqid().'@example.test',
            'status' => $status,
        ]);
    }

    /** @return array<int, mixed> صفُّ التصدير الوحيد */
    private function exportedRow(): array
    {
        $rows = (new EmployeesExport(['status']))->array();

        $this->assertCount(1, $rows, 'يُفترض صفٌّ واحد');

        return array_values((array) $rows[0]);
    }

    public function test_an_active_employee_is_not_labelled_inactive(): void
    {
        $this->make(EmploymentStatus::ACTIVE->value);

        $row = $this->exportedRow();

        $this->assertNotContains('غير مفعل', $row, 'النشط يُوصَف «غير مفعل» — عاد القلب');
        $this->assertContains('نشط', $row);
    }

    public function test_an_inactive_employee_is_labelled_inactive(): void
    {
        $this->make(EmploymentStatus::INACTIVE->value);

        $this->assertContains('غير نشط', $this->exportedRow());
    }

    /**
     * والحالاتُ الثلاثُ الجديدة تُفرَّق في الملفّ بدل أن تُجمَع في
     * «غير مفعل» واحدة.
     */
    public function test_the_new_states_are_told_apart(): void
    {
        foreach ([
            EmploymentStatus::SUSPENDED,
            EmploymentStatus::RESIGNED,
            EmploymentStatus::TERMINATED,
            EmploymentStatus::PROBATION,
        ] as $case) {
            Employee::query()->delete();
            $this->make($case->value);

            $this->assertContains($case->label(), $this->exportedRow());
        }
    }

    /** والقيمةُ `0` التي خلّفها العطلُ القديم تُقرأ «غير نشط» لا «مفعل». */
    public function test_the_legacy_zero_value_is_not_reported_as_active(): void
    {
        $this->make(EmploymentStatus::ACTIVE->value)->forceFill(['status' => 0])->saveQuietly();

        $row = $this->exportedRow();

        $this->assertContains('غير نشط', $row);
        $this->assertNotContains('مفعل', $row);
    }
}
