<?php

namespace App\Services\Targets;

use App\Models\TargetSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * موعدُ التسوية المجدولة وفترتُها — مصدرٌ واحد يقرأ منه الطرفان.
 *
 * **لماذا وُجد هذا الصنف؟**
 *
 * كان لموعد التسوية ثلاثة تعريفات متخالفة:
 *
 *   1. `Console\Kernel::scheduleTargetSettlement()` يبني تعبير cron من
 *      `settlement_day`، فيقرأ الأسبوعيّ `(day-1) % 7`.
 *   2. `ProcessTargetSettlements::shouldRunNow()` يقرأ الأسبوعيّ `day % 7`
 *      — أي بيومٍ كامل فرقاً — وهي **لم تكن مستدعاةً أصلاً**.
 *   3. `ProcessTargetSettlements::resolvePeriod()` يحسب الفترة بـ`subX()`
 *      دائماً، بلا نظرٍ إلى اليوم الذي أُطلق فيه الأمر.
 *
 * ومن (3) وقع خللٌ ظاهر: الربعيّ بخيار «آخر يوم» يُجدوَل في ٣١ مارس ثمّ
 * يُسوّي `subQuarter` — أي أكتوبر إلى ديسمبر، فيتأخّر **ربعاً كاملاً**؛
 * ونظيره السنويّ يُسوّي السنة قبل الماضية. وفي الشهريّ باليوم الرقميّ:
 * `cron("… 31 * *")` لا يقع أبداً في شهرٍ من ثلاثين يوماً، فالتسوية
 * المضبوطة على اليوم ٣١ لا تُنفَّذ في أكثر شهور السنة.
 *
 * فصار الموعدُ يوماً واحداً هنا، والجدولةُ في `Kernel` يوميّةً عند الوقت
 * المضبوط تسأل `isDueOn()` — فلا تعبير cron يُبنى ولا اصطلاح يتفرّع.
 *
 * **الاصطلاح**
 *
 *   • `settlement_day = 0` تعني «آخر يوم في الدورة»، وحينها تُسوَّى
 *     **الدورةُ المنتهية في ذلك اليوم نفسه** — فمن اختار «آخر يوم من
 *     الشهر» يريد إقفال ذلك الشهر لا الذي قبله. ولذلك يُستحسن ضبط وقتٍ
 *     متأخّر من اليوم، فما يقع بعد ساعة التنفيذ يخرج من الفترة.
 *   • `settlement_day > 0` تعني «اليوم كذا من الدورة»، وحينها تُسوَّى
 *     **الدورةُ المنقضية قبلها** (نمط «يُقفل الشهر في يومه الخامس») — أو،
 *     حين `TargetSetting::settlementScope()` = «الجارية»، **الدورةُ الجارية
 *     من أوّلها إلى يوم التنفيذ نفسه** (نمط «عمولة الشهر تُصرف يوم ٢٦ مع
 *     الراتب»). ما يقع بعد ساعة التنفيذ في ذلك اليوم يخرج من الفترة.
 *   • الأسبوعيّ: `1 = الأحد … 7 = السبت`، ويُسوّي الأسبوع المنقضي.
 *
 * @see \App\Console\Kernel::scheduleTargetSettlement()
 * @see \App\Console\Commands\ProcessTargetSettlements
 */
class TargetSettlementSchedule
{
    /** ما يُقرأ حين لا وقتَ محفوظاً. */
    public const DEFAULT_TIME = '02:00';

    /** أبعدُ ما يُبحث عنه موعدٌ قادم — السنويّ قد يبعد اثني عشر شهراً. */
    private const LOOKAHEAD_DAYS = 400;

    /**
     * أحان موعدُ التسوية الآن؟ — اليومُ يومُها، والساعةُ بلغت وقتَها، ولم
     * تُنفَّذ اليوم بعد.
     *
     * هذا ما يسأله المجدول **كلَّ دقيقة** بدل `dailyAt()`: كانت الجدولة
     * تطابق دقيقةً واحدة في اليوم، فإن لم يقع `schedule:run` فيها بالضبط
     * — مجدولٌ يُطلَق كل خمس دقائق، أو خادمٌ أُعيد تشغيله عند منتصف الليل،
     * أو دقيقةٌ ضاعت في تبديل التوقيت الصيفي — ضاعت التسويةُ دورةً كاملة
     * بلا أثر. «بلغت الساعةُ الوقتَ ولم تُنفَّذ اليوم» تصمد أمام ذلك كلّه.
     */
    public static function isDueNow(TargetSetting $setting, ?Carbon $moment = null): bool
    {
        $moment = $moment ? $moment->copy() : Carbon::now();

        return self::isDueOn($setting, $moment)
            && self::isTimeReached($setting, $moment)
            && ! self::ranOn($setting, $moment);
    }

    /**
     * أنُفِّذت تسويةُ هذا النوع في هذا اليوم؟
     *
     * العلامة في الذاكرة المؤقتة لا في القاعدة: هي حارسُ تكرارٍ لا سجلّ.
     * فإن مُسحت (`cache:clear` وقت النشر مثلاً) أُطلق الأمرُ مرّةً أخرى
     * فوجد كلَّ موظفٍ مسوّىً — حارسُ التداخل في الخدمة يمنع الصرفَ مرّتين —
     * وانتهى بلا أثر. أسوأُ الاحتمالات تشغيلٌ فارغ، لا عمولةٌ مكرّرة.
     */
    public static function ranOn(TargetSetting $setting, ?Carbon $date = null): bool
    {
        return (bool) Cache::get(self::ranKey($setting, $date));
    }

    /** تسجيلُ أنّ تسوية هذا النوع نُفِّذت اليوم. */
    public static function markRan(TargetSetting $setting, ?Carbon $date = null): void
    {
        Cache::put(self::ranKey($setting, $date), true, now()->addHours(36));
    }

    /**
     * إسقاطُ علامة «نُفِّذت اليوم» — تُستدعى عند حفظ إعدادات الأهداف.
     *
     * من غيّر الموعدَ إلى وقتٍ لاحقٍ من اليوم نفسه يريد تنفيذاً عنده؛ ولولا
     * هذا لبقيت علامةُ تشغيلٍ صباحيّ («لا مستحقّ» على الإعداد القديم) تمنع
     * تشغيلَ المساء صامتةً.
     */
    public static function forgetRan(TargetSetting $setting, ?Carbon $date = null): void
    {
        Cache::forget(self::ranKey($setting, $date));
    }

    private static function ranKey(TargetSetting $setting, ?Carbon $date = null): string
    {
        $date = $date ? $date->copy() : Carbon::now();

        return 'targets:settlement-ran:'.$setting->target_type.':'.$date->toDateString();
    }

    /**
     * الموعدُ القادم للتسوية المجدولة — تاريخاً ووقتاً — أو `null` حين لا
     * جدولةَ مضبوطة.
     *
     * يُعرض في شاشة الإعدادات مع الفترة التي ستُسوَّى فيه (`periodFor`)
     * حتى يرى من ضبط «اليوم ٢٦» أنّ أوّلَ تشغيلٍ يُسوّي الشهرَ المنقضي لا
     * الجاري — كان الإعدادُ يُحفظ ثم لا يُرى شيءٌ حتى يحلّ الموعد ويمرّ
     * صامتاً لأنّ الفترةَ المسوّاة سبقت إنشاء الهدف.
     */
    public static function nextRun(TargetSetting $setting, ?Carbon $from = null): ?Carbon
    {
        if (! $setting->settlement_frequency) {
            return null;
        }

        $from = $from ? $from->copy() : Carbon::now();
        [$hour, $minute] = array_map('intval', explode(':', self::time($setting)));

        for ($i = 0; $i <= self::LOOKAHEAD_DAYS; $i++) {
            $day = $from->copy()->addDays($i);

            if (! self::isDueOn($setting, $day)) {
                continue;
            }

            $at = $day->copy()->setTime($hour, $minute, 0);

            // اليومُ نفسه يُحتسب ما دام الوقتُ لم يبلغ بعد ولم تُنفَّذ
            if ($i === 0 && ($at->lte($from) || self::ranOn($setting, $day))) {
                continue;
            }

            return $at;
        }

        return null;
    }

    /**
     * أيقع موعدُ التسوية في هذا اليوم؟
     *
     * الفحصُ باليوم لا بالدقيقة عن قصد: الجدولة تُطلق الأمر عند الوقت
     * المضبوط، لكنّ الأمر نفسه قد يُطلَق من «المهام المجدولة» كلَّ ساعة —
     * ومطابقةُ الدقيقة تُسقط تلك الحالة كلَّها بصمت. ومنعُ التكرار مسؤوليةُ
     * `hasSettlementForPeriod()` لا مسؤوليةُ الدقيقة.
     */
    public static function isDueOn(TargetSetting $setting, ?Carbon $date = null): bool
    {
        $date = $date ? $date->copy() : Carbon::now();
        $day = (int) ($setting->settlement_day ?? 1);
        $lastDay = $day === 0;

        return match ($setting->settlement_frequency) {
            'weekly' => $date->dayOfWeek === (max(1, $day) - 1) % 7,

            'monthly' => $lastDay
                ? $date->day === $date->daysInMonth
                : $date->day === min($day, $date->daysInMonth),

            'quarterly' => $lastDay
                ? in_array($date->month, [3, 6, 9, 12], true) && $date->day === $date->daysInMonth
                : in_array($date->month, [1, 4, 7, 10], true) && $date->day === min($day, $date->daysInMonth),

            'yearly' => $lastDay
                ? $date->month === 12 && $date->day === 31
                : $date->month === 1 && $date->day === min($day, $date->daysInMonth),

            default => false,
        };
    }

    /**
     * أبلغت الساعةُ وقتَ التسوية في هذا اليوم؟
     *
     * «بلغت أو تجاوزت» لا «تساوي»: الجدولة قد تتأخّر دقيقةً، والتشغيل
     * الساعيّ من جدول `cron_jobs` لا يقع على الدقيقة أصلاً.
     */
    public static function isTimeReached(TargetSetting $setting, ?Carbon $moment = null): bool
    {
        $moment = $moment ?: Carbon::now();

        return $moment->format('H:i') >= self::time($setting);
    }

    /** وقتُ التسوية بصيغة `H:i`. */
    public static function time(TargetSetting $setting): string
    {
        return $setting->settlement_time
            ? Carbon::parse($setting->settlement_time)->format('H:i')
            : self::DEFAULT_TIME;
    }

    /**
     * الفترةُ التي تُسوَّى عند التشغيل في هذا اليوم.
     *
     * @return array{0: string, 1: string} [من, إلى] بصيغة `Y-m-d`
     */
    public static function periodFor(TargetSetting $setting, ?Carbon $date = null): array
    {
        $date = $date ? $date->copy() : Carbon::now();
        $lastDay = (int) ($setting->settlement_day ?? 1) === 0;
        // «الجارية حتى يوم التنفيذ»: من أوّل الدورة إلى اليوم نفسه
        $toToday = ! $lastDay && $setting->settlementScope() === TargetSetting::SCOPE_CURRENT;

        [$from, $to] = match ($setting->settlement_frequency) {
            /* الأسبوعيّ: الأيّام السبعة المنتهية أمس — لا «الأسبوع
               التقويميّ السابق». `startOfWeek()` تبدأ من الاثنين افتراضاً،
               فتسويةُ الأحد كانت تُغطّي الأسبوع الذي انتهى الأحدَ **قبل**
               الماضي: تأخُّرُ سبعة أيّام، وفجوةٌ لا يُسوّى ما فيها أبداً.
               والسبعةُ المنتهية أمس تتلاصق مهما كان يوم التسوية. */
            'weekly' => [
                $date->copy()->subDays(7)->startOfDay(),
                $date->copy()->subDay()->endOfDay(),
            ],

            'quarterly' => match (true) {
                $lastDay => [$date->copy()->startOfQuarter(), $date->copy()->endOfQuarter()],
                $toToday => [$date->copy()->startOfQuarter(), $date->copy()->endOfDay()],
                default => [$date->copy()->subQuarter()->startOfQuarter(), $date->copy()->subQuarter()->endOfQuarter()],
            },

            'yearly' => match (true) {
                $lastDay => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
                $toToday => [$date->copy()->startOfYear(), $date->copy()->endOfDay()],
                default => [$date->copy()->subYear()->startOfYear(), $date->copy()->subYear()->endOfYear()],
            },

            // الشهريّ هو الافتراضيّ حين لا دورةَ مضبوطة
            default => match (true) {
                $lastDay => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
                $toToday => [$date->copy()->startOfMonth(), $date->copy()->endOfDay()],
                default => [$date->copy()->subMonth()->startOfMonth(), $date->copy()->subMonth()->endOfMonth()],
            },
        };

        return [$from->toDateString(), $to->toDateString()];
    }
}
