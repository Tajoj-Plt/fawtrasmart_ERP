<?php

namespace Tests\Feature;

use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * غلاف إعدادات الرواتب (§15 TODO 2.1): كاش بدل DB::table الخام.
 */
class PayrollSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('send_salary_slips')->default(false);
            $t->string('default_template', 8)->default('ar');
            $t->unsignedBigInteger('salary_expense_account_id')->nullable();
            $t->boolean('auto_generate_payroll_runs')->default(true);
            $t->timestamps();
        });

        PayrollSettings::forget();
    }

    protected function tearDown(): void
    {
        PayrollSettings::forget();
        parent::tearDown();
    }

    public function test_value_reads_the_row(): void
    {
        DB::table('payroll_settings')->insert([
            'salary_expense_account_id' => 3079,
            'default_template' => 'en',
        ]);
        PayrollSettings::forget();

        $this->assertSame('en', PayrollSettings::value('default_template'));
        $this->assertEquals(3079, PayrollSettings::value('salary_expense_account_id'));
        $this->assertSame('x', PayrollSettings::value('missing_column', 'x'));
    }

    public function test_put_invalidates_the_cache(): void
    {
        PayrollSettings::put(['default_template' => 'ar']);
        $this->assertSame('ar', PayrollSettings::value('default_template'));

        PayrollSettings::put(['default_template' => 'en']);
        $this->assertSame('en', PayrollSettings::value('default_template'));
    }

    public function test_missing_table_returns_defaults(): void
    {
        Schema::drop('payroll_settings');
        PayrollSettings::forget();

        $this->assertNull(PayrollSettings::row());
        $this->assertTrue(PayrollSettings::value('auto_generate_payroll_runs', true));
        $this->assertSame([], PayrollSettings::toArray());
    }

    public function test_production_code_no_longer_queries_the_table_raw(): void
    {
        $roots = [
            app_path('Services'),
            app_path('Console/Commands'),
            app_path('Models'),
            module_path('HR', 'Http'),
        ];

        foreach ($roots as $root) {
            foreach ($this->phpFiles($root) as $file) {
                $source = file_get_contents($file);
                $this->assertStringNotContainsString(
                    "DB::table('payroll_settings')",
                    (string) $source,
                    $file.' ما زال يقرأ payroll_settings خاماً'
                );
            }
        }

        $this->assertStringContainsString(
            "DB::table('payroll_settings')",
            (string) file_get_contents(app_path('Support/PayrollSettings.php'))
        );
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
