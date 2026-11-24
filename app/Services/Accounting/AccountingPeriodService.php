<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\AccountingPeriodSetting;
use App\Models\FiscalYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * الفترات المحاسبية — التوليد والإقفال والحكم على تاريخ.
 *
 * الشاشة تستدعي التوليد والإقفال، وبقية النظام يستدعي `check()` وحدها:
 * تأخذ تاريخاً وتُرجع أمسموح القيد فيه أم لا ولماذا. أُفرِدت في خدمة كي
 * يبقى ربطها ببقية النظام لاحقاً سطراً واحداً في كل موضع إدخال، لا نسخةً
 * من المنطق في كل موضع.
 *
 * ما دام `enforce_lock` مطفأً تُرجع `check()` السماح دائماً — فالفترات
 * تبقى سجلّاً تنظيمياً حتى تقرّر المنشأة فرضها.
 */
class AccountingPeriodService
{
    protected const SETTINGS_CACHE_KEY = 'accounting_period_settings';

    protected const SETTINGS_CACHE_TTL = 86400;

    /** أسباب المنع — نصّها في ملفّ اللغة تحت `settings.accounting_periods.block` */
    public const REASON_YEAR_CLOSED = 'year_closed';

    public const REASON_PERIOD_CLOSED = 'period_closed';

    public const REASON_PERIOD_LOCKED = 'period_locked';

    public const REASON_BEFORE_LOCK_DATE = 'before_lock_date';

    public const REASON_UNDEFINED = 'undefined';

    public const REASON_FUTURE = 'future';

    // ═══════════════════════ الضوابط ═══════════════════════

    public function settings(): AccountingPeriodSetting
    {
        try {
            $row = Cache::remember(
                self::SETTINGS_CACHE_KEY,
                self::SETTINGS_CACHE_TTL,
                fn () => AccountingPeriodSetting::first()
            );
        } catch (\Throwable) {
            // قاعدة غير مهيّأة (أثناء الترحيلات مثلاً) — الافتراضات تكفي
            $row = null;
        }

        return $row ?: new AccountingPeriodSetting(AccountingPeriodSetting::DEFAULTS);
    }

    /**
     * إبطال قائمة الفترات المعروضة في فلاتر التقارير.
     *
     * القائمة محفوظةٌ لساعة في `AccountServiceProvider`؛ فترةٌ تُولَّد أو
     * تُقفَل ولا تُبطِل الحفظ تظهر — أو تختفي — بعد ساعةٍ من وقوعها، وهو
     * فارقٌ يُقرأ عطلاً لا تأخيراً.
     */
    public function forgetReportLists(): void
    {
        \App\Support\AccountingPeriodOptions::forget();
    }

    public function forgetSettings(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    // ═══════════════════════ الحكم على تاريخ ═══════════════════════

    /**
     * أمسموح القيد بهذا التاريخ؟
     *
     * @return array{allowed:bool, reason:?string, period:?AccountingPeriod, year:?FiscalYear}
     */
    public function check(Carbon|string|null $date): array
    {
        $date = $this->normalize($date);
        $settings = $this->settings();

        $period = AccountingPeriod::forDate($date);
        $year = $period?->fiscalYear ?: FiscalYear::forDate($date);

        $verdict = fn (?string $reason) => [
            'allowed' => $reason === null,
            'reason' => $reason,
            'period' => $period,
            'year' => $year,
        ];

        // القفل غير مفروض: الفترات تُعرض ولا تمنع
        if (! $settings->enforce_lock) {
            return $verdict(null);
        }

        if ($settings->lock_before_date && $date->lt($settings->lock_before_date)) {
            return $verdict(self::REASON_BEFORE_LOCK_DATE);
        }

        if ($settings->block_future_dates && $date->gt(today())) {
            return $verdict(self::REASON_FUTURE);
        }

        if (! $period) {
            return $verdict($settings->block_undefined_dates ? self::REASON_UNDEFINED : null);
        }

        if ($period->status === AccountingPeriod::STATUS_LOCKED) {
            return $verdict(self::REASON_PERIOD_LOCKED);
        }

        if ($period->status === AccountingPeriod::STATUS_CLOSED) {
            return $verdict(self::REASON_PERIOD_CLOSED);
        }

        // السنة المقفلة تقفل فتراتها ولو بقيت حالتها مفتوحة — الإقفال
        // السنوي إجراء واحد يجب ألا يحتاج المرور على اثنتي عشرة فترة
        if ($year && ! $year->isOpen()) {
            return $verdict(self::REASON_YEAR_CLOSED);
        }

        return $verdict(null);
    }

    /**
     * السنةُ المالية المقفلة قفلٌ **نهائيّ** — رسالةُ المنع أو `null`.
     *
     * صلاحيةُ «تجاوز قفل الفترة» تفتح الشهرَ المقفل داخل سنةٍ مفتوحة:
     * محاسبٌ أوّل يصحّح قبل إقفال السنة. أمّا السنةُ المقفلة فقد صُفّرت
     * إيراداتُها ومصروفاتُها بقيود الإقفال (`YearEndClosingService`)
     * ورُحِّل صافيها إلى الأرباح المرحّلة؛ وكلُّ تعديلٍ بعدها يترك في
     * السنة رصيداً لا يبلغ الأرباح المرحّلة. فلا تتجاوزها صلاحية —
     * تُعاد فتحُها ثم تُقفل، أو يُسجَّل قيدُ تسويةٍ في سنةٍ مفتوحة.
     */
    public function closedYearReason(Carbon|string|null $date): ?string
    {
        /*
            ولا يُطفئه «فرضُ القفل» (`enforce_lock`) في الإعدادات: ذاك ضابطُ
            الفترات، وإطفاؤه بابٌ خلفيّ لتعديل سنةٍ رُحِّلت قيودُ إقفالها —
            وقيودُها تفسد بالتعديل أيّاً كان الإعداد.
        */
        $date = $this->normalize($date);
        $year = AccountingPeriod::forDate($date)?->fiscalYear ?: FiscalYear::forDate($date);

        return $this->closedYearMessage($year, $date);
    }

    /** الرسالةُ نفسُها لسنةٍ معلومة — لمستندٍ تُعرف فترتُه لا تاريخُه. */
    public function closedYearMessage(?FiscalYear $year, Carbon|string|null $date = null): ?string
    {
        if (! $year || $year->isOpen()) {
            return null;
        }

        return __('settings.accounting_periods.block.year_closed_final', [
            'year' => $year->name,
            'date' => $date ? $this->normalize($date)->format('Y-m-d') : '—',
        ]);
    }

    public function isOpen(Carbon|string|null $date): bool
    {
        return $this->check($date)['allowed'];
    }

    /** رسالة المنع جاهزة للعرض — أو `null` إن كان التاريخ مسموحاً. */
    public function blockReason(Carbon|string|null $date): ?string
    {
        $result = $this->check($date);

        if ($result['allowed']) {
            return null;
        }

        return __('settings.accounting_periods.block.'.$result['reason'], [
            'date' => $this->normalize($date)->format('Y-m-d'),
            'period' => $result['period']?->name ?? '—',
            'year' => $result['year']?->name ?? '—',
            'lock_date' => $this->settings()->lock_before_date?->format('Y-m-d') ?? '—',
        ]);
    }

    // ═══════════════════════ التوليد ═══════════════════════

    /**
     * توليد فترات سنة مالية.
     *
     * التوليد لا يمسّ الفترات المقفلة أبداً: من ولّد شهرياً ثم بدا له ربعياً
     * بعد إقفال الربع الأول لا يجوز أن يمحو إقفالاً حصل. لذلك يُردّ الخطأ
     * بدل التوليد الناقص الصامت.
     *
     * @return int عدد الفترات المولَّدة
     *
     * @throws \RuntimeException إن كانت في السنة فترة مقفلة
     */
    public function generate(FiscalYear $year, string $type): int
    {
        $this->forgetReportLists();

        if (! array_key_exists($type, AccountingPeriod::TYPES)) {
            throw new \InvalidArgumentException("نوع فترات غير معروف: {$type}");
        }

        $locked = $year->periods()
            ->whereIn('status', [AccountingPeriod::STATUS_CLOSED, AccountingPeriod::STATUS_LOCKED])
            ->count();

        if ($locked > 0) {
            throw new \RuntimeException(
                __('settings.accounting_periods.errors.generate_locked', ['count' => $locked])
            );
        }

        $months = AccountingPeriod::TYPES[$type];
        $rows = [];
        $sequence = 1;
        $cursor = $year->start_date->copy();
        $end = $year->end_date->copy();

        while ($cursor->lte($end)) {
            // آخر فترة تُقصَّ عند نهاية السنة: سنة من أحد عشر شهراً لا
            // تُولَّد لها فترة تتجاوز نهايتها
            $periodEnd = $cursor->copy()->addMonthsNoOverflow($months)->subDay();

            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }

            $rows[] = [
                'fiscal_year_id' => $year->id,
                'name' => $this->periodName($type, $cursor, $sequence),
                'sequence' => $sequence,
                'start_date' => $cursor->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => AccountingPeriod::STATUS_OPEN,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $sequence++;
            $cursor = $periodEnd->copy()->addDay();
        }

        DB::transaction(function () use ($year, $rows) {
            $year->periods()->delete();
            AccountingPeriod::insert($rows);
        });

        return count($rows);
    }

    /** اسم الفترة حسب نوعها — «يناير 2026»، «الربع الأول 2026». */
    protected function periodName(string $type, Carbon $start, int $sequence): string
    {
        $year = $start->format('Y');

        return match ($type) {
            'monthly' => __('settings.accounting_periods.months.'.$start->month).' '.$year,
            'quarterly' => $this->ordinal('quarters', 'quarter', $sequence).' '.$year,
            'semiannual' => $this->ordinal('halves', 'half', $sequence).' '.$year,
            default => __('settings.accounting_periods.full_year', ['year' => $year]),
        };
    }

    /**
     * الاسم المرتّب («الربع الأول») مع رجوع إلى الرقمي («الربع 5»).
     *
     * الرجوع ليس تزيّداً: السنة المالية لا يحدّها الكود باثني عشر شهراً،
     * وسنة أطول تولّد ربعاً خامساً لا اسم مرتّب له.
     */
    protected function ordinal(string $list, string $fallback, int $sequence): string
    {
        $line = 'settings.accounting_periods.'.$list.'.'.$sequence;
        $text = __($line);

        return $text === $line
            ? __('settings.accounting_periods.'.$fallback, ['n' => $sequence])
            : $text;
    }

    // ═══════════════════════ الإقفال والفتح ═══════════════════════

    public function closePeriod(AccountingPeriod $period, bool $permanent = false): void
    {
        $this->forgetReportLists();

        $period->update([
            'status' => $permanent ? AccountingPeriod::STATUS_LOCKED : AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);
    }

    public function reopenPeriod(AccountingPeriod $period): void
    {
        $this->forgetReportLists();

        $period->update([
            'status' => AccountingPeriod::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * إقفال سنة — يقفل فتراتها المفتوحة معها.
     *
     * الفترات المقفلة نهائياً تبقى على حالها: الإقفال السنوي لا يخفّف قفلاً
     * أشدّ منه.
     */
    public function closeYear(FiscalYear $year): void
    {
        $this->forgetReportLists();

        DB::transaction(function () use ($year) {
            $year->periods()
                ->where('status', AccountingPeriod::STATUS_OPEN)
                ->update([
                    'status' => AccountingPeriod::STATUS_CLOSED,
                    'closed_at' => now(),
                    'closed_by' => auth()->id(),
                ]);

            $year->update([
                'status' => FiscalYear::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * إعادة فتح سنة — تفتح فتراتها المقفلة إدارياً دون المقفلة نهائياً.
     */
    public function reopenYear(FiscalYear $year): void
    {
        $this->forgetReportLists();

        DB::transaction(function () use ($year) {
            $year->periods()
                ->where('status', AccountingPeriod::STATUS_CLOSED)
                ->update([
                    'status' => AccountingPeriod::STATUS_OPEN,
                    'closed_at' => null,
                    'closed_by' => null,
                ]);

            $year->update([
                'status' => FiscalYear::STATUS_OPEN,
                'closed_at' => null,
                'closed_by' => null,
            ]);
        });
    }

    // ═══════════════════════ فحوص التداخل ═══════════════════════

    /**
     * أتتداخل هذه المدّة مع سنة مالية أخرى؟
     *
     * التداخل ممنوع لأن `forDate()` تُرجع الأولى فقط: سنتان تغطّيان تاريخاً
     * واحداً تعنيان أن حكم النظام على ذلك التاريخ يتبع ترتيب الصفوف.
     */
    public function yearOverlaps(Carbon $start, Carbon $end, ?int $exceptId = null): bool
    {
        return FiscalYear::when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    public function periodOverlaps(int $yearId, Carbon $start, Carbon $end, ?int $exceptId = null): bool
    {
        return AccountingPeriod::where('fiscal_year_id', $yearId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    protected function normalize(Carbon|string|null $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->startOfDay();
        }

        return $date ? Carbon::parse($date)->startOfDay() : today();
    }
}
