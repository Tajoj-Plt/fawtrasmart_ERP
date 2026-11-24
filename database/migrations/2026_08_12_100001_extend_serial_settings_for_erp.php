<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع إعدادات الترقيم المتسلسل لمستوى ERP.
 *
 * يضيف: اللاحقة، دورة إعادة التصفير (سنوي/شهري/يومي)، تتبّع آخر تصفير،
 * حالة التفعيل، وفاصل بين البادئة والرقم.
 *
 * `reset_yearly` القديم يبقى في مكانه ويُنقل محتواه إلى `reset_period`
 * حتى لا ينكسر أي كود قائم يقرأه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('serial_settings')) {
            return;
        }

        Schema::table('serial_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('serial_settings', 'suffix')) {
                $table->string('suffix', 20)->nullable()->after('prefix')
                    ->comment('لاحقة تُضاف بعد الرقم');
            }
            if (! Schema::hasColumn('serial_settings', 'separator')) {
                $table->string('separator', 5)->nullable()->default('')->after('suffix')
                    ->comment('فاصل بين البادئة والرقم مثل - أو /');
            }
            if (! Schema::hasColumn('serial_settings', 'reset_period')) {
                $table->string('reset_period', 10)->default('never')->after('reset_yearly')
                    ->comment('never|yearly|monthly|daily');
            }
            if (! Schema::hasColumn('serial_settings', 'last_reset_at')) {
                $table->timestamp('last_reset_at')->nullable()->after('reset_period')
                    ->comment('تاريخ آخر تصفير فعلي — مرجع دورة التصفير');
            }
            if (! Schema::hasColumn('serial_settings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_reset_at')
                    ->comment('تعطيل الترقيم التلقائي لهذا القسم');
            }
        });

        // نقل القيمة القديمة إلى الحقل الجديد مرة واحدة
        if (Schema::hasColumn('serial_settings', 'reset_yearly')) {
            DB::table('serial_settings')
                ->where('reset_yearly', true)
                ->where('reset_period', 'never')
                ->update(['reset_period' => 'yearly']);
        }

        // مرجع دورة التصفير: بدون تاريخ لن تعمل المقارنة في أول توليد
        DB::table('serial_settings')
            ->whereNull('last_reset_at')
            ->update(['last_reset_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('serial_settings')) {
            return;
        }

        Schema::table('serial_settings', function (Blueprint $table) {
            foreach (['suffix', 'separator', 'reset_period', 'last_reset_at', 'is_active'] as $column) {
                if (Schema::hasColumn('serial_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
