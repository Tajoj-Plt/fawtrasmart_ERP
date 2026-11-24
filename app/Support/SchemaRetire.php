<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * إحالةُ جدولٍ إلى التقاعد بدل حذفه.
 *
 * ── لماذا وُجدت هذه ──
 *
 * حين تُلغى ميزةٌ من النظام يكتب مَن ألغاها `Schema::dropIfExists('x')` في
 * ترحيلٍ اسمُه `drop_...`. وهو الصواب على حاسوب المطوّر: الجدول فارغٌ عنده
 * أصلاً. أمّا على قاعدة عميلٍ يعمل بها منذ سنة فقد يحمل الجدولُ آلافَ
 * الصفوف — وسطرٌ واحدٌ يمحوها بلا رجعة.
 *
 * وقرارُ صاحب النظام قاطعٌ هنا: **التحديث لا يحذف شيئاً — لا عموداً ولا
 * جدولاً ولا صفّاً.** فكان الأثر المزدوج قبل هذه الأداة:
 *
 *   • إمّا يمرّ الحذف فتضيع بيانات لا نسخةَ منها،
 *   • أو يقف `MigrationSafetyScanner` عنده فيتوقّف التحديث كلُّه — وما بعده
 *     من ترحيلاتٍ يبقى معلَّقاً، والنظامُ محبوسٌ على شاشة التحديث بلا مخرج.
 *
 * ── ماذا تفعل بدلاً من ذلك ──
 *
 * ثلاثُ حالاتٍ لا رابعة، بحسب ما في الجدول فعلاً:
 *
 *   • **غير موجود** → لا شيء. الترحيل بلا أثرٍ على هذه القاعدة.
 *   • **موجودٌ وفارغ** → يُسقَط. لا صفَّ يُفقَد، فالإسقاط تنظيفٌ لا حذف.
 *   • **موجودٌ وفيه صفوف** → يُعاد تسميته إلى `retired_<الاسم>`. البيانات
 *     باقيةٌ كما هي، والاسمُ الأصلي يتحرّر لمن يريده — وهو ما يحتاجه ترحيلٌ
 *     لاحق يُنشئ جدولاً بالاسم نفسه بشكلٍ جديد (`product_variants` مثلاً).
 *
 * وإعادةُ التسمية عمليةٌ يصنّفها `MigrationSafetyScanner` **مُعدِّلة** لا
 * متلفة، فلا توقف التحديث. وهي كذلك عمليةُ بيانات وصفيّة (metadata) في
 * MySQL: لا تُنسخ الصفوف ولا يتأخّر التحديث بحجم الجدول.
 *
 * ── ولماذا لا تُحذف الأعمدة أصلاً ──
 *
 * لا نظيرَ لهذه الدالّة للأعمدة عمداً: عمودٌ متروكٌ في جدولٍ لا يكلّف شيئاً
 * ولا يظهر لأحد، فالحلّ لعمودٍ لم يعد مستعمَلاً هو تركُه لا حذفُه. من ألغى
 * ميزةً فليَحذف قراءتَها من الكود، ويترك العمود حيث هو.
 *
 * @see \App\Services\Maintenance\MigrationSafetyScanner
 */
class SchemaRetire
{
    /** بادئةُ الجداول المتقاعدة — تُميّزها في أي عرضٍ لقائمة الجداول. */
    public const PREFIX = 'retired_';

    public const ABSENT = 'absent';

    public const DROPPED = 'dropped';

    public const RENAMED = 'renamed';

    public const KEPT = 'kept';

    /**
     * إحالةُ جدولٍ إلى التقاعد.
     *
     * @return string إحدى الحالات الأربع أعلاه — يُقرأ في مخرَج الترحيل
     */
    public static function table(string $table): string
    {
        try {
            if (! Schema::hasTable($table)) {
                return self::ABSENT;
            }
        } catch (Throwable $e) {
            return self::KEPT;
        }

        if (self::rowCount($table) === 0) {
            try {
                /* `DROP TABLE` خاماً لا `Schema::drop()`.

                   والسبب ليس تفضيلاً: أثناء التحديث تكون نحويّةُ
                   [\App\Support\Migration\SchemaReconciler] مركَّبةً، وهي
                   تعترض `compileDrop` فتنادي هذه الدالّة — فلو نادت هذه
                   الدالّةُ `Schema::drop()` لعادت إلى تلك النحويّة، فإلى
                   هنا، بلا نهاية. (دورةٌ أوقفت تجربةً كاملة قبل أن تُكتشف.)
                   والأمرُ الخام لا يمرّ بالنحويّة أصلاً. */
                DB::statement('DROP TABLE `'.$table.'`');

                return self::DROPPED;
            } catch (Throwable $e) {
                /* مفتاحٌ أجنبيٌّ من جدولٍ آخر يشير إليه (الخطأ 1217)، أو
                   صلاحيةٌ ناقصة. الإسقاطُ ليس هدفاً بذاته — يُكمَل بإعادة
                   التسمية، وهي تنجح حيث يفشل الإسقاط. */
            }
        }

        $target = self::PREFIX.$table;

        try {
            if (Schema::hasTable($target)) {
                /* تقاعدٌ سابق بالاسم نفسه: لا يُكتب فوقه ولا يُدمَج. تُترك
                   الطاولتان كما هما — بيانات المرّتين محفوظة، وهو المطلوب.
                   والاسمُ الأصلي يبقى مشغولاً، فمن أراده يفحصه بنفسه. */
                Log::warning('SchemaRetire: اسمُ التقاعد مشغول', [
                    'table' => $table,
                    'target' => $target,
                ]);

                return self::KEPT;
            }

            // خاماً كذلك، وللسبب نفسه المشروح عند `DROP TABLE` أعلاه
            DB::statement('RENAME TABLE `'.$table.'` TO `'.$target.'`');

            return self::RENAMED;
        } catch (Throwable $e) {
            Log::warning('SchemaRetire: تعذّرت إحالة الجدول إلى التقاعد', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return self::KEPT;
        }
    }

    /**
     * إحالةُ عدّة جداول، بالترتيب المُعطى.
     *
     * والترتيب يهمّ: جدولُ التفاصيل قبل رأسه، وإلّا منع المفتاحُ الأجنبي
     * إسقاطَ الفارغ منهما.
     *
     * @param  array<int, string>  $tables
     * @return array<string, string> اسم الجدول ← ما جرى له
     */
    public static function tables(array $tables): array
    {
        $result = [];

        foreach ($tables as $table) {
            $result[$table] = self::table($table);
        }

        return $result;
    }

    /** عددُ صفوف جدول، و`-1` إن تعذّر السؤال (فلا يُعدّ فارغاً بالخطأ). */
    private static function rowCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable $e) {
            return -1;
        }
    }
}
