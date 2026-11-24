<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة حقول الخصومات قبل التسوية من جدول target_settings:
 *   - deduct_advances  (خصم السلف)
 *   - deduct_absences  (خصم الغياب)
 *   - deduct_other     (خصومات أخرى)
 *
 * السبب: هذه الميزات لا تُستخدم في النظام الحالي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_settings', function (Blueprint $table) {
            foreach (['deduct_advances', 'deduct_absences', 'deduct_other'] as $col) {
                if (Schema::hasColumn('target_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('target_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('target_settings', 'deduct_advances')) {
                $table->boolean('deduct_advances')->default(false)->after('settlement_time');
            }
            if (!Schema::hasColumn('target_settings', 'deduct_absences')) {
                $table->boolean('deduct_absences')->default(false)->after('deduct_advances');
            }
            if (!Schema::hasColumn('target_settings', 'deduct_other')) {
                $table->boolean('deduct_other')->default(false)->after('deduct_absences');
            }
        });
    }
};
