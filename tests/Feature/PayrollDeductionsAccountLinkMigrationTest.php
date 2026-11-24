<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ترحيل ربط «خصميات الموظفين» — يمنع فشل صرف من عليه خصم.
 */
class PayrollDeductionsAccountLinkMigrationTest extends TestCase
{
    public function test_migration_creates_5310_and_links_payroll_deductions(): void
    {
        $source = (string) file_get_contents(base_path(
            'database/migrations/2026_09_22_220000_add_payroll_deductions_account_and_link.php'
        ));

        $this->assertStringContainsString("'5310'", $source);
        $this->assertStringContainsString('payroll_deductions', $source);
        $this->assertStringContainsString("where('code', self::PARENT_CODE)", $source);
        $this->assertStringContainsString('insertGetId', $source);
        // لا يمسّ ربطاً قائماً
        $this->assertStringContainsString('if ($setting->account_id)', $source);
    }

    public function test_account_seeder_includes_5310(): void
    {
        $source = (string) file_get_contents(base_path('database/seeders/AccountSeeder.php'));

        $this->assertStringContainsString("'5310'", $source);
        $this->assertStringContainsString('خصميات الموظفين', $source);
    }
}
