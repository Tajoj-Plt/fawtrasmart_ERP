<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط قسيمة الراتب بمسير الرواتب (Payroll Process).
 *
 *   - salary_slips.payroll_id  → FK اختياري إلى payrolls
 *   - payrolls.status          → 'active' | 'inactive' | 'draft'  (افتراضي 'active')
 *
 * الغرض: لا يمكن إنشاء أو صرف قسيمة إلا تحت مسير رواتب فعّال يشمل الموظف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_slips', 'payroll_id')) {
                // إن كان contract_id موجوداً نضع الحقل بعده، وإلا بعد employee_id
                $afterColumn = Schema::hasColumn('salary_slips', 'contract_id') ? 'contract_id' : 'employee_id';
                $table->unsignedBigInteger('payroll_id')->nullable()->after($afterColumn)
                    ->comment('مسير الرواتب الذي ينتمي إليه');
            }
        });

        Schema::table('salary_slips', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('salary_slips'))->pluck('name')->toArray();
            if (!in_array('idx_slip_payroll', $indexes)) {
                if (! SchemaGuard::hasIndex('salary_slips', 'idx_slip_payroll')) {
                    $table->index('payroll_id', 'idx_slip_payroll');
                }
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'status')) {
                $table->string('status', 20)->default('active')->after('attendance_check')
                    ->comment('active | inactive | draft');
            }
            if (!Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('salary_slips'))->pluck('name')->toArray();
            if (in_array('idx_slip_payroll', $indexes)) {
                $table->dropIndex('idx_slip_payroll');
            }
            if (Schema::hasColumn('salary_slips', 'payroll_id')) {
                $table->dropColumn('payroll_id');
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            foreach (['approved_by', 'approved_at', 'status'] as $col) {
                if (Schema::hasColumn('payrolls', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
