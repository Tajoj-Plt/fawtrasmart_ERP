<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * السندُ المكرَّر — سند القبض (`Receipt`) وسند الصرف (`Expense`) معاً.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * شاشتا السندين تعرضان مفتاح «سند مكرَّر» وتقولان تحته «يُعاد إنشاؤه
 * تلقائياً على فترات»، وتحفظان `is_recurring` و`recurring_frequency`
 * و`end_date`. ثم لا يقرأ ذلك أحد: لا أمرَ ولا مهمّةَ مجدولة. فالسند
 * الأوّل وحده في الدفتر، والمستخدم ينتظر إيجاراً شهرياً لا يُقيَّد.
 *
 * ═════════════════════ ما صار ═════════════════════
 *
 * `App\Services\Finance\RecurringVoucherGenerator` يُصدر النسخ المستحقّة،
 * وهذه السِّمة لغةُ السلسلة المشتركة بين النوعين:
 *
 *   • الأصل  — `is_recurring = 1` و`recurring_parent_id` فارغ.
 *   • النسخة — `recurring_parent_id` = الأصل، و`recurring_sequence` = رقمها
 *              في السلسلة (1 = أوّل تكرارٍ بعد الأصل). والنسخة نفسها **غير
 *              مكرَّرة** حتى لا تلد سلسلةً من سلسلة.
 *
 * ═════════════════════ موعد النسخة n ═════════════════════
 *
 * يُحسب **من تاريخ الأصل** لا من آخر نسخة: `date + n × الدورة`. فسندٌ
 * شهريّ مؤرَّخ 31 يناير يخرج 28 فبراير ثم 31 مارس — لو خطا من آخر نسخةٍ
 * لانزلق إلى 28 من كلّ شهرٍ بعد فبراير إلى الأبد.
 */
trait RecursAsVoucher
{
    /** دورات التكرار كما يرسلها النموذج — وتسمياتها للعرض. */
    public static function recurringFrequencies(): array
    {
        return [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'bi-weekly' => 'كل أسبوعين',
            'monthly' => 'شهري',
            'yearly' => 'سنوي',
        ];
    }

    /** الأصلُ الذي وُلِّدت منه هذه النسخة. */
    public function recurringParent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'recurring_parent_id');
    }

    /** النسخُ المولَّدة من هذا الأصل. */
    public function recurringCopies(): HasMany
    {
        return $this->hasMany(static::class, 'recurring_parent_id');
    }

    /** هل هذا السند أصلُ سلسلةٍ صالحةُ الدورة؟ */
    public function isRecurringSource(): bool
    {
        return (bool) $this->is_recurring
            && empty($this->recurring_parent_id)
            && array_key_exists((string) $this->recurring_frequency, static::recurringFrequencies());
    }

    public function recurringFrequencyLabel(): ?string
    {
        return static::recurringFrequencies()[(string) $this->recurring_frequency] ?? null;
    }

    /**
     * تاريخُ النسخة رقم `$sequence` — أو `null` إن تجاوز تاريخَ النهاية أو
     * كانت الدورةُ غيرَ معروفة.
     */
    public function recurringOccurrenceDate(int $sequence): ?Carbon
    {
        if ($sequence < 1 || empty($this->date)) {
            return null;
        }

        $start = Carbon::parse($this->date)->startOfDay();

        $date = match ((string) $this->recurring_frequency) {
            'daily' => $start->copy()->addDays($sequence),
            'weekly' => $start->copy()->addWeeks($sequence),
            'bi-weekly' => $start->copy()->addWeeks(2 * $sequence),
            // بلا فيضان: 31 يناير + شهر = 28 فبراير لا 3 مارس
            'monthly' => $start->copy()->addMonthsNoOverflow($sequence),
            'yearly' => $start->copy()->addYearsNoOverflow($sequence),
            default => null,
        };

        if ($date === null) {
            return null;
        }

        // تاريخ النهاية شاملٌ: نسخةٌ تقع فيه تُصدَر
        if (! empty($this->end_date) && $date->gt(Carbon::parse($this->end_date)->endOfDay())) {
            return null;
        }

        return $date;
    }

    /**
     * أوّلُ نسخةٍ لم تُصدَر بعد: `[رقمها, تاريخها]` — أو `null` إن انتهت
     * السلسلة. تُقرأ في شاشة السند لتقول «النسخة التالية في …».
     *
     * @return array{0:int, 1:Carbon}|null
     */
    public function nextRecurringOccurrence(): ?array
    {
        if (! $this->isRecurringSource()) {
            return null;
        }

        $issued = $this->recurringCopies()->pluck('recurring_sequence')->filter()->flip();

        // الحدُّ حارسٌ من حلقةٍ طويلة على سلسلةٍ يوميةٍ قديمة بلا نهاية
        for ($sequence = 1; $sequence <= 5000; $sequence++) {
            if (isset($issued[$sequence])) {
                continue;
            }

            $date = $this->recurringOccurrenceDate($sequence);

            return $date ? [$sequence, $date] : null;
        }

        return null;
    }
}
