<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة holidays لدعم نوعَين من العطل:
 *
 *   1) عطلة بتاريخ محدد (مرة واحدة) — recurrence_type=once
 *      مثال: 2026-02-22 (يوم وطني)
 *      يُستخدم holiday_date
 *
 *   2) عطلة متكررة — recurrence_type=weekly
 *      مثال: كل جمعة
 *      يُستخدم recurrence_day_of_week (0=أحد، 1=اثنين، ... 5=جمعة، 6=سبت)
 *
 *   3) عطلة شهرية متكررة — recurrence_type=monthly
 *      مثال: أول كل شهر / الـ 15 من كل شهر
 *      يُستخدم recurrence_day_of_month (1-31)
 *
 *   4) عطلة سنوية متكررة — recurrence_type=yearly
 *      مثال: 1 يناير (رأس السنة) من كل سنة
 *      يُستخدم recurrence_month + recurrence_day_of_month
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('holidays', 'recurrence_type')) {
                $table->enum('recurrence_type', ['once', 'weekly', 'monthly', 'yearly'])
                    ->default('once')
                    ->after('holiday_date')
                    ->comment('نوع التكرار: مرة واحدة / أسبوعي / شهري / سنوي');
            }

            if (!Schema::hasColumn('holidays', 'recurrence_day_of_week')) {
                $table->unsignedTinyInteger('recurrence_day_of_week')->nullable()
                    ->after('recurrence_type')
                    ->comment('0=الأحد، 1=الاثنين، ... 5=الجمعة، 6=السبت (للأسبوعي)');
            }

            if (!Schema::hasColumn('holidays', 'recurrence_day_of_month')) {
                $table->unsignedTinyInteger('recurrence_day_of_month')->nullable()
                    ->after('recurrence_day_of_week')
                    ->comment('1-31 (للشهري والسنوي)');
            }

            if (!Schema::hasColumn('holidays', 'recurrence_month')) {
                $table->unsignedTinyInteger('recurrence_month')->nullable()
                    ->after('recurrence_day_of_month')
                    ->comment('1-12 (للسنوي فقط)');
            }

            if (!Schema::hasColumn('holidays', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('recurrence_month');
            }

            if (!Schema::hasColumn('holidays', 'is_paid')) {
                $table->boolean('is_paid')->default(true)->after('is_active')
                    ->comment('عطلة مدفوعة الأجر أم غير مدفوعة');
            }

            // جعل holiday_date اختياري (للعطل المتكررة لا نحتاجه)
            // لا نستطيع جعله NULL بـ Blueprint بسهولة، لكن الكود سيتعامل معه
        });

        // فهرس مهم للأداء (البحث عن العطل في تاريخ معين)
        Schema::table('holidays', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('holidays'))->pluck('name')->toArray();
            if (!in_array('idx_holidays_active_type', $indexes)) {
                if (! SchemaGuard::hasIndex('holidays', 'idx_holidays_active_type')) {
                    $table->index(['is_active', 'recurrence_type'], 'idx_holidays_active_type');
                }
            }
            if (!in_array('idx_holidays_dow', $indexes)) {
                if (! SchemaGuard::hasIndex('holidays', 'idx_holidays_dow')) {
                    $table->index('recurrence_day_of_week', 'idx_holidays_dow');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            foreach ([
                'recurrence_type', 'recurrence_day_of_week',
                'recurrence_day_of_month', 'recurrence_month',
                'is_active', 'is_paid',
            ] as $col) {
                if (Schema::hasColumn('holidays', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
