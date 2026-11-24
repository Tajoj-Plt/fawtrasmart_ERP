<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\DocumentSections;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * رقمُ الموظف — من «الترقيم المتسلسل» لا من `id`.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * لم يكن للموظف رقمٌ أصلاً: الشاشاتُ والتقاريرُ تعرض `id` الخام، وهو
 * مفتاحُ قاعدةٍ لا رقمُ موظف — يتغيّر بين بيئةٍ وأخرى، ولا بادئةَ له ولا
 * حشوَ ولا تصفيرَ دوري. وبنيةُ الترقيم قائمةٌ في النظام منذ زمن
 * (`DocumentSections` + `DocumentNumberService` + شاشة الإعدادات)،
 * والعقودُ تستعملها بالفعل. الموظفون وحدهم كانوا خارجها.
 *
 * ويُقاس معه أنّ نقلَ «العقود» إلى مجموعة الموارد البشرية **لم يمسّ
 * مفتاحَ قسمها**: المجموعةُ تصنيفُ عرضٍ في الشاشة، ولو انكسر المفتاح
 * لتوقّف ترقيمُ العقود العامل.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase` — كما في نظائره وللسبب نفسه.
 */
class EmployeeCodeNumberingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->string('employee_code', 64)->nullable()->unique();
            $t->integer('created_by')->nullable();
            // الموديل يستعمل `SoftDeletes` — فالعمود شرطُ كل استعلام عليه
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('serial_settings', function (Blueprint $t) {
            $t->id();
            $t->string('section');
            $t->unsignedBigInteger('current_number')->default(0);
            $t->unsignedInteger('number_of_digits')->default(6);
            $t->string('prefix')->nullable();
            $t->string('suffix')->nullable();
            $t->string('separator')->nullable();
            $t->string('mode')->nullable();
            $t->boolean('reset_yearly')->default(false);
            $t->string('reset_period')->nullable();
            $t->timestamp('last_reset_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    // ═══════════════ سجلّ الأقسام ═══════════════

    public function test_the_registry_exposes_an_hr_group(): void
    {
        $this->assertArrayHasKey('hr', DocumentSections::GROUPS);

        $grouped = DocumentSections::grouped();

        $this->assertArrayHasKey('hr', $grouped);
        $this->assertArrayHasKey('employee', $grouped['hr']['sections']);
    }

    public function test_the_employee_section_points_at_the_new_column(): void
    {
        $this->assertSame('employees', DocumentSections::table('employee'));
        $this->assertSame('employee_code', DocumentSections::column('employee'));
        $this->assertSame('EMP', DocumentSections::suggestedPrefix('employee'));
        $this->assertContains('employee', DocumentSections::keys());
    }

    /**
     * نقلُ «العقود» إلى مجموعة الموارد البشرية عرضٌ لا أكثر: المفتاحُ
     * والجدولُ والعمود كما كانت، فترقيمُ العقود العامل لا يتأثّر.
     */
    public function test_moving_contracts_into_hr_did_not_break_its_section(): void
    {
        $this->assertContains('contracts', DocumentSections::keys());
        $this->assertSame('contracts', DocumentSections::table('contracts'));
        $this->assertSame('code', DocumentSections::column('contracts'));
        $this->assertSame('hr', DocumentSections::SECTIONS['contracts']['group']);
    }

    // ═══════════════ توليد الرقم ═══════════════

    public function test_peek_does_not_consume_and_next_does(): void
    {
        $first = peek_document_number('employee');

        $this->assertSame($first, peek_document_number('employee'), 'المعاينة لا تستهلك');

        $consumed = next_document_number('employee');

        $this->assertSame($first, $consumed, 'أوّل استهلاكٍ يسلّم ما عُرض');
        $this->assertNotSame($consumed, next_document_number('employee'), 'الاستهلاك يتقدّم');
    }

    public function test_generated_codes_carry_the_prefix_and_are_unique(): void
    {
        $codes = [
            next_document_number('employee'),
            next_document_number('employee'),
            next_document_number('employee'),
        ];

        foreach ($codes as $code) {
            $this->assertStringContainsString('EMP', $code);
        }

        $this->assertCount(3, array_unique($codes), 'الأرقام لا تتكرّر');
    }

    /** ورقمٌ محجوزٌ فعلاً في الجدول يُتخطّى بدل أن يُسلَّم فيُرفض عند الحفظ. */
    public function test_a_code_already_taken_is_skipped(): void
    {
        $taken = peek_document_number('employee');

        Employee::create([
            'first_name' => 'محجوز',
            'email' => 'taken@example.test',
            'employee_code' => $taken,
        ]);

        $this->assertNotSame($taken, next_document_number('employee'));
    }

    // ═══════════════ الموديل والشاشة ═══════════════

    public function test_the_code_is_mass_assignable(): void
    {
        $employee = Employee::create([
            'first_name' => 'سالم',
            'email' => 'salem@example.test',
            'employee_code' => 'EMP-000042',
        ]);

        $this->assertSame('EMP-000042', $employee->fresh()->employee_code);
    }

    /** والموظفون السابقون للترقيم يبقون بلا رقم — العمود `nullable`. */
    public function test_an_employee_without_a_code_is_allowed(): void
    {
        $employee = Employee::create([
            'first_name' => 'قديم',
            'email' => 'old@example.test',
        ]);

        $this->assertNull($employee->fresh()->employee_code);
    }

    /**
     * حقلُ الرقم في نموذج الإضافة **لا يُرسَل**: لا `name` له.
     *
     * ولو أُعطي اسماً لَمَلَك المستخدمُ فرضَ أيّ رقم على التسلسل —
     * فالمتحكّم يولّده بنفسه ولا يقرأ الطلب.
     */
    public function test_the_create_form_never_submits_the_code(): void
    {
        $blade = base_path('Modules/HR/resources/views/employee/create.blade.php');

        $this->assertFileExists($blade);

        $html = (string) file_get_contents($blade);

        $this->assertSame(
            1,
            preg_match('/<input[^>]*id="employee_code"[^>]*>/', $html, $input),
            'حقل رقم الموظف غير موجود في نموذج الإضافة'
        );

        $this->assertStringNotContainsString('name=', $input[0], 'الحقل يُرسَل — يجب ألّا يكون له name');
        $this->assertStringContainsString('readonly', $input[0]);
    }

    /** والمتحكّم لا يقرأ الرقم من الطلب بحال. */
    public function test_the_controller_generates_the_code_instead_of_reading_it(): void
    {
        $source = (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/EmployeeController.php')
        );

        $this->assertStringContainsString("next_document_number('employee')", $source);
        $this->assertStringNotContainsString('request->employee_code', $source);
        $this->assertStringNotContainsString("input('employee_code')", $source);
    }
}
