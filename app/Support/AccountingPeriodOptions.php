<?php

namespace App\Support;

use App\Models\AccountingPeriod;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * قائمتا «الفترات المحاسبية» و«مراكز التكلفة» لفلاتر الشاشات.
 *
 * **لماذا صنفٌ لا مُركِّبُ عرض؟**
 *
 * الفلتر يُستعمل في وحدتين — الحسابات والمخزون — وفي أكثر من ثلاثين شاشة.
 * ومُركِّبُ العرض يُربَط بمساحة أسماء وحدةٍ بعينها (`account::report.*`)،
 * فيبقى نصف الشاشات بلا قائمة. والصنف يُنادى من القالب مباشرةً فيعمل أينما
 * وُضع القالب.
 *
 * القائمتان صغيرتان ونادرتا التغيّر، فتُحفظان ساعةً. وكل ما يغيّرهما —
 * توليدُ فترات، إقفالها، إضافةُ مركز تكلفة — يُنادي `forget()`.
 */
class AccountingPeriodOptions
{
    public const PERIODS_KEY = 'accounting.filter.periods';

    public const CENTERS_KEY = 'accounting.filter.cost_centers';

    public const YEARS_KEY = 'accounting.filter.years';

    /**
     * الفترات المتاحة للاختيار، من الأحدث.
     *
     * @return Collection<int, AccountingPeriod>
     */
    public static function periods(int $limit = 24): Collection
    {
        return Cache::remember(self::PERIODS_KEY, 3600, function () use ($limit) {
            try {
                return AccountingPeriod::with('fiscalYear')
                    ->orderByDesc('start_date')
                    ->limit($limit)
                    ->get();
            } catch (Throwable $e) {
                // جدول الفترات غير مهيّأ بعد: تُعرض الشاشة بلا قائمة بدل
                // أن تنهار عند أول فتح
                return collect();
            }
        });
    }

    /**
     * السنواتُ المالية وفتراتُها — لقائمة الفلتر.
     *
     * القائمةُ كانت فتراتٍ مسطَّحة، والمستخدمُ يعمل على سنته: يقارن الشهرَ
     * بالشهر ويراجع ما مضى منها. فتُعرض السنةُ خياراً أوّلاً، وتحتها فتراتُها
     * لمن أراد شهراً بعينه.
     *
     * @return Collection<int, FiscalYear>
     */
    public static function years(int $limit = 8): Collection
    {
        return Cache::remember(self::YEARS_KEY, 3600, function () use ($limit) {
            try {
                return FiscalYear::with(['periods' => fn ($q) => $q->orderBy('start_date')])
                    ->orderByDesc('start_date')
                    ->limit($limit)
                    ->get();
            } catch (Throwable $e) {
                // جدول السنوات غير مهيّأ بعد: تُعرض الشاشة بلا قائمة
                return collect();
            }
        });
    }

    /**
     * مراكز التكلفة مرتَّبةً بالكود.
     *
     * @return Collection<int, CostCenter>
     */
    public static function costCenters(): Collection
    {
        return Cache::remember(self::CENTERS_KEY, 3600, function () {
            try {
                return CostCenter::orderBy('code')->get();
            } catch (Throwable $e) {
                return collect();
            }
        });
    }

    /** يُنادى بعد كل تغيّرٍ في الفترات أو المراكز */
    public static function forget(): void
    {
        Cache::forget(self::PERIODS_KEY);
        Cache::forget(self::CENTERS_KEY);
        Cache::forget(self::YEARS_KEY);
    }
}
