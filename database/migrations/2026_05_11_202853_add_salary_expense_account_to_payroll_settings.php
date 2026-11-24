<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * إضافة حقل حساب مصروف الرواتب لإعدادات الرواتب،
 * وحقل قيد الاستحقاق المنفصل لـ payroll_runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // أضف default_payment_day إن لم يكن موجوداً
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_settings', 'default_payment_day')) {
                $table->unsignedTinyInteger('default_payment_day')->default(1)
                    ->after('auto_pay_approved_sales_periods')
                    ->comment('اليوم الافتراضي للصرف من الشهر');
            }
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_settings', 'salary_expense_account_id')) {
                $after = Schema::hasColumn('payroll_settings', 'default_payment_day')
                    ? 'default_payment_day' : 'id';
                $table->unsignedBigInteger('salary_expense_account_id')->nullable()
                    ->after($after)
                    ->comment('حساب مصروف الرواتب والأجور (يُدبَّت عند الاستحقاق)');
            }
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_runs', 'accrual_journal_entry_id')) {
                $after = Schema::hasColumn('payroll_runs', 'journal_entry_id')
                    ? 'journal_entry_id' : 'id';
                $table->unsignedBigInteger('accrual_journal_entry_id')->nullable()
                    ->after($after)
                    ->comment('قيد الاستحقاق (مصروف الرواتب ← مستحقات الموظف)');
            }
        });

        // ضبط القيمة الافتراضية = "رواتب الموظفين" (id=3079) إن وُجدت
        $defaultExpense = DB::table('accounts')->where('id', 3079)->value('id')
            ?? DB::table('accounts')->where('parent_id', 3041)->value('id')
            ?? 3041;

        DB::table('payroll_settings')
            ->whereNull('salary_expense_account_id')
            ->update(['salary_expense_account_id' => $defaultExpense]);

        // لو لم يكن هناك سجل أصلاً
        if (DB::table('payroll_settings')->count() === 0) {
            DB::table('payroll_settings')->insertOrIgnore([
                'salary_expense_account_id' => $defaultExpense,
                'default_payment_day'       => 1,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_settings', 'salary_expense_account_id')) {
                $table->dropColumn('salary_expense_account_id');
            }
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_runs', 'accrual_journal_entry_id')) {
                $table->dropColumn('accrual_journal_entry_id');
            }
        });
    }
};
