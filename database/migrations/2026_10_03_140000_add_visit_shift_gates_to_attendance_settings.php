<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «زيارة العميل داخل الدوام» تصير ثلاثةَ مفاتيح بدل قاعدةٍ مثبَّتة.
 *
 * `FieldWorkGuard::visitStartBlock()` كانت تمنع الموظفَ الميدانيَّ من بدء
 * زيارةِ عميلٍ في ثلاث حالات، بلا مفتاحٍ يُطفئ أيّاً منها:
 *
 *   ١) لم يسجّل حضورَه بعد    ← «سجّل حضورك أولاً»
 *   ٢) سجّل انصرافَه          ← «لا تبدأ زيارة بعد انتهاء دوامك»
 *   ٣) هو في استراحة          ← «أنهِ الاستراحة ثم ابدأ الزيارة»
 *
 * والعلّةُ المكتوبة في الحارس صحيحة: وقتٌ عند العملاء خارج الدوام المسجَّل
 * يُفسد الإقفالَ اليومي، فلا يُعرف أيُّ الرقمين الصحيح. لكنّها **سياسةُ
 * منشأةٍ لا قانونَ طبيعة**: منشأةٌ يبدأ مندوبُها طريقَه من بيته إلى أوّل
 * عميلٍ ثم يسجّل حضورَه من عنده، وأخرى تسمح بزيارةٍ عابرةٍ بعد الانصراف.
 * فالقرارُ قرارُها.
 *
 * والافتراضُ `1` في الثلاثة — هو السلوكُ القائم، فلا يتغيّر شيءٌ حتى يغيّره
 * المستخدم من «الدوام والحضور».
 *
 * والقيدُ المعاكس — زيارةٌ مفتوحةٌ تمنع الاستراحةَ والانصراف
 * (`FieldWorkGuard::openVisit()`) — يبقى كما هو: لا معنى لانصرافٍ وزيارةٌ
 * عند عميلٍ ما زالت تعدّ وقتَها.
 */
return new class extends Migration
{
    /** المفتاح => افتراضُه */
    private const COLUMNS = [
        'visit_requires_check_in' => true,
        'visit_blocked_after_check_out' => true,
        'visit_blocked_during_break' => true,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            $after = 'checkin_close_after_minutes';

            foreach (self::COLUMNS as $column => $default) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    continue;
                }

                $table->boolean($column)->default($default)->after($after);
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_settings')) {
            return;
        }

        $existing = array_values(array_filter(
            array_keys(self::COLUMNS),
            fn (string $column) => Schema::hasColumn('attendance_settings', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
