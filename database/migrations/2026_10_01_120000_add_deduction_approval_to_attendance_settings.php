<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعتمادُ خصميات الحضور: تلقائي أو يدوي.
 *
 * كانت خصميةُ التأخير والغياب ونقص الدوام تُنشأ «معتمدة» وتُقيَّد فوراً —
 * لا مراجعة. صار للمنشأة أن تختار: `auto` كما كان، أو `manual` فتنزل في
 * شاشة الخصميات «بانتظار الاعتماد» ولا تُقيَّد حتى يعتمدها المدير.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_settings', 'deduction_approval')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->string('deduction_approval', 10)->default('auto')->after('deduction_calculation_type')
                    ->comment('auto = يُعتمد فوراً | manual = معلّق حتى اعتماد المدير');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_settings', 'deduction_approval')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->dropColumn('deduction_approval');
            });
        }
    }
};
