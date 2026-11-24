<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_target_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_target_settlements', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->after('sales_target_id')->comment('الموظف المستحق للعمولة');
            }
            if (! Schema::hasColumn('sales_target_settlements', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->after('employee_id')->default(0)->comment('قيمة العمولة المستحقة');
            }
            if (! Schema::hasColumn('sales_target_settlements', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('settled_by')->comment('هل تمت التسوية في قسيمة راتب');
            }
            if (! Schema::hasColumn('sales_target_settlements', 'salary_slip_id')) {
                $table->unsignedBigInteger('salary_slip_id')->nullable()->after('is_settled')->comment('رقم قسيمة الراتب');
            }
            if (! Schema::hasColumn('sales_target_settlements', 'settled_date')) {
                $table->date('settled_date')->nullable()->after('salary_slip_id')->comment('تاريخ التسوية في القسيمة');
            }
            if (! Schema::hasColumn('sales_target_settlements', 'settled_amount')) {
                $table->decimal('settled_amount', 10, 2)->nullable()->after('settled_date')->comment('المبلغ المسوى في القسيمة');
            }
            
            if (! SchemaGuard::hasForeignKey('sales_target_settlements', 'sales_target_settlements_employee_id_foreign')) {
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('sales_target_settlements', 'sales_target_settlements_salary_slip_id_foreign')) {
                $table->foreign('salary_slip_id')->references('id')->on('salary_slips')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_target_settlements', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['salary_slip_id']);
            $table->dropColumn(['employee_id', 'commission_amount', 'is_settled', 'salary_slip_id', 'settled_date', 'settled_amount']);
        });
    }
};
