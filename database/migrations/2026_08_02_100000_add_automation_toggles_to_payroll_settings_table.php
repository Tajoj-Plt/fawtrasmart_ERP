<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // تمكين/تعطيل توليد payroll_runs تلقائياً من الكرون و lazy generation
            if (! Schema::hasColumn('payroll_settings', 'auto_generate_payroll_runs')) {
                $table->boolean('auto_generate_payroll_runs')->default(true)->after('auto_pay_approved_sales_periods');
            }
            // تمكين/تعطيل صرف الرواتب تلقائياً من الكرون
            if (! Schema::hasColumn('payroll_settings', 'auto_disburse_payroll_runs')) {
                $table->boolean('auto_disburse_payroll_runs')->default(true)->after('auto_generate_payroll_runs');
            }
        });

        // عند الترقية: الإعدادات الافتراضية ON (السلوك الحالي محفوظ)
        DB::table('payroll_settings')->update([
            'auto_generate_payroll_runs' => 1,
            'auto_disburse_payroll_runs' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_generate_payroll_runs', 'auto_disburse_payroll_runs']);
        });
    }
};
