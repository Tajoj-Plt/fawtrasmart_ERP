<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Enums\EmployeeDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\EmployeeDocumentController;
use Tests\TestCase;

/**
 * وثائقُ الموظف الرسمية.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * لم يكن للموظف وثائق: `employee_attachments` دلوُ ملفّاتٍ بلا نوعٍ ولا
 * رقمٍ ولا تاريخِ انتهاء. فتُرفع صورةُ الإقامة بين الملفّات ولا يعرف
 * النظام أنها إقامة ولا متى تنتهي — ومن ثمّ بقيت مهمّةُ
 * `hr:document-expiry-alerts` معطَّلةً موسومةً «(غير مطبَّقة بعد)» لأنّ لا
 * بيانات لها.
 *
 * ويُقاس هنا ما يبني عليه التنبيهُ لاحقاً: حسابُ الأيام الباقية، ونطاقُ
 * «ينتهي خلال»، وأنّ ما لا ينتهي لا يدخل فيه.
 */
class EmployeeDocumentTest extends TestCase
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
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('type', 40)->default('other');
            $t->string('title')->nullable();
            $t->string('number', 100)->nullable();
            $t->date('issue_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('issuing_authority')->nullable();
            $t->string('file_path')->nullable();
            $t->string('original_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('owner');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamps();
        });

        // مستخدمٌ ليس موظفاً ⇒ بلا قيدٍ من نطاق الرؤية (انظر المرجع)
        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    private function controller(): EmployeeDocumentController
    {
        return new class extends EmployeeDocumentController
        {
            public function __construct() {}
        };
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
        ]);
    }

    private function document(Employee $employee, array $attributes = []): EmployeeDocument
    {
        return EmployeeDocument::create($attributes + [
            'employee_id' => $employee->id,
            'type' => EmployeeDocumentType::RESIDENCY->value,
        ]);
    }

    // ═══════════════ النوع ═══════════════

    public function test_certificates_do_not_expire_but_residencies_do(): void
    {
        $this->assertFalse(EmployeeDocumentType::CERTIFICATE->expires());
        $this->assertTrue(EmployeeDocumentType::RESIDENCY->expires());
        $this->assertNotContains(
            EmployeeDocumentType::CERTIFICATE->value,
            EmployeeDocumentType::expiringValues()
        );
    }

    // ═══════════════ حسابُ الانتهاء ═══════════════

    public function test_days_until_expiry_counts_whole_days(): void
    {
        $employee = $this->employee();

        $this->assertSame(
            10,
            $this->document($employee, ['expiry_date' => now()->addDays(10)])->daysUntilExpiry()
        );

        // اليوم نفسه صفرٌ لا سالب: الوثيقةُ سارية حتى نهايته
        $this->assertSame(
            0,
            $this->document($employee, ['expiry_date' => now()])->daysUntilExpiry()
        );

        $this->assertSame(
            -3,
            $this->document($employee, ['expiry_date' => now()->subDays(3)])->daysUntilExpiry()
        );
    }

    /** وما لا تاريخَ انتهاءٍ له لا يُحسب ولا يُعَدّ منتهياً. */
    public function test_a_document_without_an_expiry_is_never_expired(): void
    {
        $document = $this->document($this->employee(), [
            'type' => EmployeeDocumentType::CERTIFICATE->value,
            'expiry_date' => null,
        ]);

        $this->assertNull($document->daysUntilExpiry());
        $this->assertFalse($document->isExpired());
        $this->assertFalse($document->expiresWithin(365));
    }

    public function test_expired_documents_are_recognised(): void
    {
        $employee = $this->employee();

        $this->assertTrue($this->document($employee, ['expiry_date' => now()->subDay()])->isExpired());
        $this->assertFalse($this->document($employee, ['expiry_date' => now()->addDay()])->isExpired());
    }

    // ═══════════════ النطاقات — أساسُ التنبيه ═══════════════

    public function test_the_expiring_scope_ignores_documents_without_an_expiry(): void
    {
        $employee = $this->employee();

        $this->document($employee, ['expiry_date' => now()->addDays(5)]);   // داخل المدّة
        $this->document($employee, ['expiry_date' => now()->addDays(90)]);  // خارجها
        $this->document($employee, ['expiry_date' => null]);                // لا تنتهي

        $this->assertSame(1, EmployeeDocument::expiringWithin(30)->count());
    }

    public function test_the_expired_scope_selects_only_past_dates(): void
    {
        $employee = $this->employee();

        $this->document($employee, ['expiry_date' => now()->subDay()]);
        $this->document($employee, ['expiry_date' => now()->addDay()]);
        $this->document($employee, ['expiry_date' => null]);

        $this->assertSame(1, EmployeeDocument::expired()->count());
    }

    // ═══════════════ الحفظ والتحقّق ═══════════════

    public function test_a_document_is_stored_with_its_details(): void
    {
        $employee = $this->employee();

        $this->controller()->store(Request::create('/', 'POST', [
            'type' => EmployeeDocumentType::RESIDENCY->value,
            'number' => '2401234567',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'issuing_authority' => 'الجوازات',
        ]), $employee->id);

        $document = EmployeeDocument::firstOrFail();

        $this->assertSame(EmployeeDocumentType::RESIDENCY, $document->type);
        $this->assertSame('2401234567', $document->number);
        $this->assertSame('الجوازات', $document->issuing_authority);
        $this->assertSame('الإقامة', $document->displayTitle());
    }

    /** والتسميةُ الحرّة تعلو على اسم النوع حين تُكتب. */
    public function test_an_explicit_title_wins_over_the_type_label(): void
    {
        $document = $this->document($this->employee(), ['title' => 'إقامة سابقة']);

        $this->assertSame('إقامة سابقة', $document->displayTitle());
    }

    /** ووثيقةٌ تنتهي قبل أن تُصدر خطأُ إدخالٍ لا حالةٌ ممكنة. */
    public function test_an_expiry_before_the_issue_date_is_rejected(): void
    {
        $employee = $this->employee();

        $this->expectException(ValidationException::class);

        $this->controller()->store(Request::create('/', 'POST', [
            'type' => EmployeeDocumentType::PASSPORT->value,
            'issue_date' => '2027-01-01',
            'expiry_date' => '2026-01-01',
        ]), $employee->id);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $employee = $this->employee();

        $this->expectException(ValidationException::class);

        $this->controller()->store(
            Request::create('/', 'POST', ['type' => 'not_a_real_type']),
            $employee->id
        );
    }

    /**
     * ووثيقةٌ لا تخصّ هذا الموظف لا تُبلَغ بتبديل المعرّف في العنوان.
     */
    public function test_a_document_of_another_employee_is_not_reachable(): void
    {
        $owner = $this->employee();
        $other = $this->employee();
        $document = $this->document($owner);

        $this->expectException(ModelNotFoundException::class);

        $this->controller()->destroy($other->id, $document->id);
    }

    // ═══════════════ الحراسة ═══════════════

    public function test_both_permissions_are_in_the_catalog(): void
    {
        $names = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $this->assertContains('employees_documents_view', $names);
        $this->assertContains('employees_documents_manage', $names);
    }

    /** والقراءةُ مفصولةٌ عن الكتابة على المسارات نفسِها. */
    public function test_reading_and_writing_are_guarded_separately(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->keyBy(fn ($r) => (string) $r->getName());

        foreach (['employee.documents.index', 'employee.documents.download'] as $name) {
            $this->assertContains(
                'permission.role:employees_documents_view',
                $routes->get($name)?->gatherMiddleware() ?? [],
                "{$name} غير محروس بصلاحية العرض"
            );
        }

        foreach (['employee.documents.store', 'employee.documents.update', 'employee.documents.destroy'] as $name) {
            $this->assertContains(
                'permission.role:employees_documents_manage',
                $routes->get($name)?->gatherMiddleware() ?? [],
                "{$name} غير محروس بصلاحية الإدارة"
            );
        }
    }
}
