<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * جدول employee_branch يُكتب إليه ويُقرأ منه بعمود is_main
     * (فرع أساسي = 1 مقابل فرع إضافي/سلف = 0) في EmployeeController،
     * لكن migration الإنشاء الأصلي لم يُضِف هذا العمود قط.
     */
    public function up(): void
    {
        Schema::table('employee_branch', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_branch', 'is_main')) {
                $table->boolean('is_main')->default(0)->after('branch_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_branch', function (Blueprint $table) {
            if (Schema::hasColumn('employee_branch', 'is_main')) {
                $table->dropColumn('is_main');
            }
        });
    }
};
