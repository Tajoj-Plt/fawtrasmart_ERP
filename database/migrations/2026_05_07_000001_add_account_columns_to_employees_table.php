<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة عمودين محاسبيين لجدول الموظفين:
 *
 *   - salary_payable_account_id   → حساب الرواتب المستحقة (شجرة الخصوم)
 *                                    يُستخدم لقيد التزام الراتب الشهري للموظف.
 *
 *   - employee_advance_account_id → حساب سلف الموظف        (شجرة الأصول)
 *                                    يُستخدم لتسجيل السلف الممنوحة للموظف.
 *
 * يدعم migration كلا الحالتين:
 *   - تركيب جديد       → ينشئ الأعمدة بالأسماء الجديدة مباشرة.
 *   - ترقية من إصدار   → يقوم بإعادة تسمية الأعمدة القديمة (account_id / advance_account_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) إعادة تسمية الأعمدة القديمة إن وُجدت (ترقية من الإصدار السابق)
        if (Schema::hasColumn('employees', 'account_id')
            && !Schema::hasColumn('employees', 'salary_payable_account_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('account_id', 'salary_payable_account_id');
            });
        }

        if (Schema::hasColumn('employees', 'advance_account_id')
            && !Schema::hasColumn('employees', 'employee_advance_account_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('advance_account_id', 'employee_advance_account_id');
            });
        }

        // 2) إنشاء الأعمدة بالأسماء الجديدة في حال التركيب من الصفر
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'salary_payable_account_id')) {
                $table->unsignedBigInteger('salary_payable_account_id')
                    ->nullable()
                    ->after('credit_limit')
                    ->comment('حساب الرواتب المستحقة - الخصوم');
            }
            if (!Schema::hasColumn('employees', 'employee_advance_account_id')) {
                $table->unsignedBigInteger('employee_advance_account_id')
                    ->nullable()
                    ->after('salary_payable_account_id')
                    ->comment('حساب سلف الموظف - الأصول');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'employee_advance_account_id')) {
                $table->dropColumn('employee_advance_account_id');
            }
            if (Schema::hasColumn('employees', 'salary_payable_account_id')) {
                $table->dropColumn('salary_payable_account_id');
            }
        });
    }
};
