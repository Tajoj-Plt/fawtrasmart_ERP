<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * موظفو الموعد، وسلسلةُ الموعد المتكرّر.
 *
 * ١. **`appointment_employees`** — الموعدُ لمن؟ كان عموداً واحداً
 *    (`appointments.employee_id`) اختيارياً خلف مربّع «تعيين موظف»، فيُحفظ
 *    موعدٌ لا يحضره أحد. صار موعداً واحداً لموظفٍ أو أكثر: يظهر في مواعيد
 *    كلٍّ منهم ويُذكَّر به كلٌّ منهم ويُفحص تداخلُه عند كلٍّ منهم. وبقي
 *    `employee_id` يحمل **الأوّل** لأنّ ما يقرؤه (المواعيد من الحجز،
 *    شاشات العملاء، النطاق `_assigned` القديم) كثير.
 *    ويُملأ من `employee_id` القائم، فلا يفقد موعدٌ قديمٌ موظّفَه.
 *
 * ٢. **`appointments.recurrence_parent_id`** — الموعدُ المتكرّر كان يُحفظ
 *    `is_recurring=1` ولا يولّد شيئاً. تولّد مهمّةُ `appointments:generate-recurring`
 *    المواعيدَ التالية، وكلُّ مولَّدٍ يشير إلى أصل سلسلته. والفريدُ على
 *    (الأصل، التاريخ) يمنع تشغيلين متزامنين من توليد اليوم نفسِه مرّتين.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        if (! Schema::hasTable('appointment_employees')) {
            Schema::create('appointment_employees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_id');
                $table->unsignedBigInteger('employee_id')->index();
                $table->timestamps();

                $table->unique(['appointment_id', 'employee_id']);
            });

            $now = now();

            DB::table('appointments')
                ->whereNotNull('employee_id')
                ->orderBy('id')
                ->select(['id', 'employee_id'])
                ->chunk(500, function ($rows) use ($now) {
                    DB::table('appointment_employees')->insertOrIgnore($rows->map(fn ($row) => [
                        'appointment_id' => $row->id,
                        'employee_id' => $row->employee_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        }

        if (! Schema::hasColumn('appointments', 'recurrence_parent_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('recurrence_parent_id')->nullable()->after('recurrence_date');
                $table->unique(['recurrence_parent_id', 'appointment_date'], 'appointments_recurrence_occurrence_unique');
            });
        }
    }

    /** لا حذفَ في التراجع — الأعمدةُ والجداولُ تُتقاعَد ولا تُسقَط. */
    public function down(): void {}
};
