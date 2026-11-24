<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `holy_day_list_customizes` جدولٌ ذو غرضين: تخصيصُ قوائم العطلات
 * (holiday_list_id) وتخصيصُ محدِّدات الحضور (attendance_determinant_id).
 *
 * لكنّ holiday_list_id بقي NOT NULL بلا قيمةٍ افتراضية، فصفُّ المحدِّد — ولا
 * قائمةَ عطلاتٍ له — لا يمكن إدراجُه أصلاً. والمتحكّم يتحايل بتمرير الرقم 1
 * ثابتاً، أي يشير إلى قائمة عطلاتٍ قد لا توجد.
 *
 * جعلُه nullable هو ما يصف الواقع: أحدُ العمودين يُملأ حسب غرض الصف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('holy_day_list_customizes')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite يبني العمود من هجرة الإنشاء، ولا ALTER ... MODIFY فيه
            return;
        }

        DB::statement('ALTER TABLE `holy_day_list_customizes` MODIFY `holiday_list_id` BIGINT UNSIGNED NULL');

        // صفوفُ المحدِّدات التي حُشِيت بالرقم 1 دون أن تعني قائمةَ عطلات
        DB::table('holy_day_list_customizes')
            ->whereNotNull('attendance_determinant_id')
            ->where('holiday_list_id', 1)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('holiday_lists')->whereColumn('holiday_lists.id', 'holy_day_list_customizes.holiday_list_id'))
            ->update(['holiday_list_id' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('holy_day_list_customizes') || DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('holy_day_list_customizes')->whereNull('holiday_list_id')->update(['holiday_list_id' => 1]);
        DB::statement('ALTER TABLE `holy_day_list_customizes` MODIFY `holiday_list_id` BIGINT UNSIGNED NOT NULL');
    }
};
