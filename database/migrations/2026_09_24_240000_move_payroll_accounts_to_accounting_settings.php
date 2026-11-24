<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حساباتُ الرواتب إلى «إعدادات الحسابات المحاسبية» — مصدرٌ واحد.
 *
 * كانت خمسةُ حساباتٍ تُضبط في شاشات الرواتب وتُحفظ في أعمدة جداولها:
 *
 *   payroll_settings.eos_expense_account_id                  → eos_expense
 *   payroll_settings.eos_provision_account_id                → eos_provision
 *   social_insurance_settings.employee_payable_account_id    → gosi_employee_payable
 *   social_insurance_settings.employer_payable_account_id    → gosi_employer_payable
 *   social_insurance_settings.expense_account_id             → gosi_expense
 *   payroll_settings.salary_expense_account_id               → employees_salaries_expense (قائم)
 *
 * تُنسخ القيمُ إلى صفوف `accounting_settings`: يُنشأ الصفُّ الناقص، ويُملأ
 * القائمُ الفارغ — ولا يُمسّ صفٌّ فيه `account_id` (اختيارُ المحاسب يغلب).
 * وتُنشأ صفوفُ أنواع الخصميات (`payroll_deduction_{type}`) بلا ربط إن غابت.
 *
 * الأعمدةُ القديمة تبقى كما هي (تقاعدٌ لا حذف) — لا يقرؤها شيءٌ بعد الآن.
 */
return new class extends Migration
{
    private const GROUP = 'الرواتب والموظفين';

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> المفتاح => [الجدول، العمود، الاسم، الوصف] */
    private function sources(): array
    {
        return [
            'employees_salaries_expense' => ['payroll_settings', 'salary_expense_account_id', 'مصروف رواتب الموظفين', 'المصروف الفعلي المحمَّل عند احتساب الرواتب'],
            'eos_expense' => ['payroll_settings', 'eos_expense_account_id', 'مصروف مكافأة نهاية الخدمة', 'مدينٌ بمبلغ المكافأة عند اعتماد مسودة نهاية الخدمة'],
            'eos_provision' => ['payroll_settings', 'eos_provision_account_id', 'مخصص مكافأة نهاية الخدمة', 'التزامٌ دائنٌ عند اعتماد المكافأة، ومدينٌ عند تسويتها إلى ذمّة الموظف'],
            'gosi_employee_payable' => ['social_insurance_settings', 'employee_payable_account_id', 'تأمينات مستحقة — حصة الموظف', 'دائنٌ بحصة الموظف المستقطَعة من راتبه عند الصرف، ويُقفل عند السداد للتأمينات'],
            'gosi_employer_payable' => ['social_insurance_settings', 'employer_payable_account_id', 'تأمينات مستحقة — حصة المنشأة', 'دائنٌ بمساهمة المنشأة عند صرف الراتب، ويُقفل عند السداد للتأمينات'],
            'gosi_expense' => ['social_insurance_settings', 'expense_account_id', 'مصروف التأمينات الاجتماعية', 'مدينٌ بحصة المنشأة من التأمينات — تكلفةُ شركةٍ لا تمسّ صافي الموظف'],
        ];
    }

    /** أنواعُ الخصميات كما في `Deduction::types()` — منسوخةٌ هنا كي لا يتبدّل الترحيلُ بتبدّل النموذج. */
    private function deductionTypes(): array
    {
        return [
            'absence' => 'غياب',
            'late' => 'تأخير',
            'early_leave' => 'انصراف مبكر',
            'half_day' => 'نصف يوم',
            'violation' => 'مخالفة',
            'custody_damage' => 'تلف عهدة',
            'admin_penalty' => 'عقوبة إدارية',
            'other' => 'أخرى',
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        $now = now();

        foreach ($this->sources() as $key => [$table, $column, $name, $description]) {
            $accountId = $this->oldValue($table, $column);
            $account = $accountId ? DB::table('accounts')->where('id', $accountId)->first(['id', 'code']) : null;

            $row = DB::table('accounting_settings')->where('key', $key)->first();

            if (! $row) {
                DB::table('accounting_settings')->insert([
                    'key' => $key,
                    'name' => $name,
                    'description' => $description,
                    'group' => self::GROUP,
                    'account_id' => $account?->id,
                    'account_code' => $account && $account->code !== null ? (string) $account->code : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            // صفٌّ قائمٌ فارغ: يُملأ من القيمة القديمة. والمربوطُ لا يُمسّ.
            if ($row->account_id === null && $account) {
                DB::table('accounting_settings')->where('id', $row->id)->update([
                    'account_id' => $account->id,
                    'account_code' => $account->code !== null ? (string) $account->code : null,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($this->deductionTypes() as $type => $label) {
            $key = 'payroll_deduction_'.$type;
            if (DB::table('accounting_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('accounting_settings')->insert([
                'key' => $key,
                'name' => 'خصميات '.$label,
                'description' => 'الحساب الدائن في قيد اعتماد خصمية «'.$label.'» — إن تُرك فارغاً استُعمل حساب «خصميات الموظفين» العام',
                'group' => self::GROUP,
                'account_id' => null,
                'account_code' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (class_exists(\App\Support\AccountingSettings::class)) {
            \App\Support\AccountingSettings::forget();
        }
    }

    /** لا رجوعَ يمسّ البيانات: الأعمدةُ القديمة لم تُمسّ، والصفوفُ المُضافة قد رُبطت بعدها. */
    public function down(): void
    {
    }

    private function oldValue(string $table, string $column): ?int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }
        $value = $query->value($column);

        return $value ? (int) $value : null;
    }
};
