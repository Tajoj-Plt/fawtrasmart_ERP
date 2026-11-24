<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الفترةُ المحجوزة فعلاً على تقويم الموظّف — مخزَّنةً لا محسوبة.
 *
 * ═══════════════ العطل الذي كشفه الاختبار ═══════════════
 *
 * وقتا التحضير والتنظيف كانا يُضافان إلى طرفَي **الفترة المفحوصة** وحدها،
 * ولا يُضافان إلى الحجوزات المحفوظة التي تُقارَن بها. والنتيجة تعارضٌ
 * يمرّ:
 *
 *     محفوظ : 10:00 – 10:30، وبعده عشرُ دقائق تنظيف ⇒ الموظّف مشغولٌ حتى 10:40
 *     جديد  : 10:35 – 11:05
 *
 * فالمقارنةُ تجري بين (10:35–11:15) و(10:00–**10:30**) فلا تتداخلان،
 * ويمرّ حجزٌ يبدأ والموظّفُ لم يفرغ بعدُ من تنظيف سابقه.
 *
 * ═══════════════ ولمَ عمودان لا حسابٌ في الاستعلام؟ ═══════════════
 *
 * لأن حسابَه في الاستعلام يعني ضمَّ `reservation_services` في كلّ فحصِ
 * تعارض ثمّ حسابَ إزاحةِ وقتٍ في SQL — وحسابُ الوقت يختلف بين MySQL
 * و SQLite (قاعدةُ الاختبارات)، فيفترق سلوكُ الحارس بين البيئتين.
 *
 * ثمّ إن بُفَرات الخدمة تتغيّر: تعديلُ «وقت التنظيف» في خدمةٍ كان
 * سيُعيد تفسير حجوزٍ ماضية بأثرٍ رجعي، فينقلب حجزٌ صحيحٌ إلى متعارض.
 * والعمودُ المخزَّن يُجمّد ما كان وقتَ الحجز — كما تُجمَّد العملةُ
 * والفترةُ المحاسبية وسعرُ البند.
 *
 * والمقارنةُ تصير بين عمودين مفهرسين، بلا ضمٍّ ولا حسابٍ في كلّ صفّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'blocked_start')) {
                $table->time('blocked_start')->nullable()->after('end_time')
                    ->comment('بداية الفترة المحجوزة على تقويم الموظف — البداية ناقصَ وقت التحضير');
            }
            if (! Schema::hasColumn('reservations', 'blocked_end')) {
                $table->time('blocked_end')->nullable()->after('blocked_start')
                    ->comment('نهايتُها — النهاية زائدَ وقت التنظيف');
            }
        });

        /*
            الصفوفُ السابقة (إن وُجدت) تُملأ بالوقتين كما هما: لا بُفَر
            معروفةً لها بأثرٍ رجعي، وتركُها فارغةً يجعل `COALESCE` في
            الحارس يعمل — لكنّ الملءَ أوضح ويُغني عن الاعتماد عليه.
        */
        DB::table('reservations')
            ->whereNull('blocked_start')
            ->update([
                'blocked_start' => DB::raw('start_time'),
                'blocked_end' => DB::raw('end_time'),
            ]);

        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['employee_id', 'reservation_date', 'blocked_start'], 'reservations_employee_blocked_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_employee_blocked_index');
            $table->dropColumn(['blocked_start', 'blocked_end']);
        });
    }
};
