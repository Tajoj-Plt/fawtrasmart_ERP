<?php

namespace Tests\Feature;

use App\Models\Deduction;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\SalarySlip;
use App\Support\DocumentSections;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ترقيم قسائم الرواتب والصرف والسلف والخصميات من «الترقيم المتسلسل».
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase` — كما في EmployeeCodeNumberingTest.
 */
class PayrollDocumentNumberingTest extends TestCase
{
    /** @var list<string> */
    private array $tables = [
        'salary_slips',
        'payroll_runs',
        'salary_advances',
        'deductions',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->tables as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('code', 64)->nullable()->unique();
                $t->timestamps();
            });
        }

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

    public function test_the_four_payroll_sections_live_in_the_hr_group(): void
    {
        $grouped = DocumentSections::grouped();

        foreach (['salary-slip', 'payroll-run', 'advance', 'deduction'] as $key) {
            $this->assertContains($key, DocumentSections::keys());
            $this->assertArrayHasKey($key, $grouped['hr']['sections']);
            $this->assertSame('hr', DocumentSections::SECTIONS[$key]['group']);
            $this->assertSame('code', DocumentSections::column($key));
        }

        $this->assertSame('salary_slips', DocumentSections::table('salary-slip'));
        $this->assertSame('payroll_runs', DocumentSections::table('payroll-run'));
        $this->assertSame('salary_advances', DocumentSections::table('advance'));
        $this->assertSame('deductions', DocumentSections::table('deduction'));

        $this->assertSame('SLP', DocumentSections::suggestedPrefix('salary-slip'));
        $this->assertSame('PRN', DocumentSections::suggestedPrefix('payroll-run'));
        $this->assertSame('ADV', DocumentSections::suggestedPrefix('advance'));
        $this->assertSame('DED', DocumentSections::suggestedPrefix('deduction'));
    }

    // ═══════════════ توليد الرقم ═══════════════

    public function test_peek_does_not_consume_and_next_does_for_each_section(): void
    {
        foreach (['salary-slip', 'payroll-run', 'advance', 'deduction'] as $section) {
            $first = peek_document_number($section);

            $this->assertSame($first, peek_document_number($section), "المعاينة لا تستهلك ({$section})");

            $consumed = next_document_number($section);

            $this->assertSame($first, $consumed, "أوّل استهلاكٍ يسلّم ما عُرض ({$section})");
            $this->assertNotSame($consumed, next_document_number($section), "الاستهلاك يتقدّم ({$section})");
        }
    }

    public function test_generated_codes_carry_the_prefix_and_are_unique(): void
    {
        $prefixes = [
            'salary-slip' => 'SLP',
            'payroll-run' => 'PRN',
            'advance' => 'ADV',
            'deduction' => 'DED',
        ];

        foreach ($prefixes as $section => $prefix) {
            $codes = [
                next_document_number($section),
                next_document_number($section),
                next_document_number($section),
            ];

            foreach ($codes as $code) {
                $this->assertStringContainsString($prefix, $code);
            }

            $this->assertCount(3, array_unique($codes), "الأرقام لا تتكرّر ({$section})");
        }
    }

    /** ورقمٌ محجوزٌ فعلاً في الجدول يُتخطّى بدل أن يُسلَّم فيُرفض عند الحفظ. */
    public function test_a_code_already_taken_is_skipped(): void
    {
        $map = [
            'salary-slip' => 'salary_slips',
            'payroll-run' => 'payroll_runs',
            'advance' => 'salary_advances',
            'deduction' => 'deductions',
        ];

        foreach ($map as $section => $table) {
            $taken = peek_document_number($section);

            DB::table($table)->insert([
                'code' => $taken,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertNotSame($taken, next_document_number($section), "لم يُتخطَّ المحجوز ({$section})");
        }
    }

    // ═══════════════ الموديل ═══════════════

    public function test_the_code_is_mass_assignable_on_the_four_models(): void
    {
        $this->assertSame('SLP-1', (new SalarySlip(['code' => 'SLP-1']))->code);
        $this->assertSame('PRN-1', (new PayrollRun(['code' => 'PRN-1']))->code);
        $this->assertSame('ADV-1', (new SalaryAdvance(['code' => 'ADV-1']))->code);
        $this->assertSame('DED-1', (new Deduction(['code' => 'DED-1']))->code);
    }

    public function test_existing_rows_may_remain_without_a_code(): void
    {
        $id = DB::table('salary_slips')->insertGetId([
            'code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(DB::table('salary_slips')->where('id', $id)->value('code'));
    }

    // ═══════════════ المتحكّمات والشاشات ═══════════════

    public function test_controllers_issue_numbers_and_never_read_them_from_the_request(): void
    {
        $cases = [
            'Modules/HR/Http/Controllers/Salaries/SalarySlipController.php' => "issueDocumentNumber('salary-slip')",
            'Modules/HR/Http/Controllers/Salaries/DeductionController.php' => "issueDocumentNumber('deduction')",
            'Modules/HR/Http/Controllers/Salaries/AncestorController.php' => "issueDocumentNumber('advance')",
            'app/Models/PayrollRun.php' => "next_document_number('payroll-run')",
            'app/Services/PayrollDisbursementService.php' => 'PayrollRun::issueDocumentCode()',
        ];

        foreach ($cases as $relative => $needle) {
            $source = (string) file_get_contents(base_path($relative));

            $this->assertStringContainsString($needle, $source, "{$relative} لا يصدر الرقم");
            $this->assertStringNotContainsString('request->code', $source, "{$relative} يقرأ الرقم من الطلب");
            $this->assertStringNotContainsString("input('code')", $source, "{$relative} يقرأ الرقم من الطلب");
        }
    }

    public function test_create_forms_never_submit_the_code(): void
    {
        $forms = [
            'Modules/HR/resources/views/salaries/salary_slip/create.blade.php' => 'salary_slip_code',
            'Modules/HR/resources/views/salaries/deductions/create.blade.php' => 'deduction_code',
            'Modules/HR/resources/views/salaries/ancestor/create.blade.php' => 'advance_code',
        ];

        foreach ($forms as $relative => $id) {
            $blade = base_path($relative);

            $this->assertFileExists($blade);

            $html = (string) file_get_contents($blade);

            $this->assertSame(
                1,
                preg_match('/<input[^>]*id="'.preg_quote($id, '/').'"[^>]*>/', $html, $input),
                "حقل {$id} غير موجود في {$relative}"
            );

            $this->assertStringNotContainsString('name=', $input[0], "الحقل يُرسَل في {$relative}");
            $this->assertStringContainsString('readonly', $input[0]);
        }
    }
}
