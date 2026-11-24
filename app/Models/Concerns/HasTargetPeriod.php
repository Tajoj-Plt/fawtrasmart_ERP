<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * مدّةُ الهدف وطريقةُ صرف عمولته — لأهداف المبيعات والتحصيلات معاً.
 *
 * **القاعدة**
 *
 * هدفٌ له مدّة لا تُصرف له عمولةٌ عن يومٍ خارجها — لا بالتسوية المجدولة ولا
 * باليدوية. وطريقةُ الصرف واحدةٌ من اثنتين:
 *
 *   • `period_end` — مرّةً واحدة عند نهاية المدّة، عن المدّة كلِّها. قيمةُ
 *     الهدف تُقاس على المدّة كلِّها: هدفُ مئةِ ألفٍ لستّة أشهر يُقارَن بما
 *     حُقّق في الستّة، لا بما حُقّق في شهر.
 *   • `cycle` — دوريّاً بجدول «إعدادات الأهداف»، وكلُّ دورةٍ تُقصّ على
 *     المدّة: دورةٌ تسبق البداية أو تلي النهاية لا يُصرف عنها شيء.
 *
 * وهدفٌ بلا مدّة يبقى على ما كان قبل هذا الصنف: دوريٌّ بلا قصّ.
 *
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $settlement_mode
 * @property \Illuminate\Support\Carbon|null $period_settled_at
 */
trait HasTargetPeriod
{
    public const SETTLE_PERIOD_END = 'period_end';

    public const SETTLE_CYCLE = 'cycle';

    /** @var array<string, bool> */
    private static array $periodColumnsMemo = [];

    public function initializeHasTargetPeriod(): void
    {
        $this->mergeFillable(['start_date', 'end_date', 'settlement_mode', 'period_settled_at']);
        $this->mergeCasts([
            'start_date' => 'date',
            'end_date' => 'date',
            'period_settled_at' => 'datetime',
        ]);
    }

    /**
     * أأُضيفت أعمدةُ المدّة إلى الجدول؟
     *
     * الشيفرةُ تصل الخادمَ قبل أن يُشغَّل الترحيل من «تحديث النظام»، وحفظُ
     * هدفٍ بعمودٍ لا وجود له يُسقط الشاشة كلَّها. فيُسأل الجدولُ مرّةً في الطلب.
     */
    public static function periodColumnsReady(): bool
    {
        $table = (new static)->getTable();

        return self::$periodColumnsMemo[$table] ??= Schema::hasColumn($table, 'settlement_mode');
    }

    /** أللهدف مدّةٌ مضبوطة؟ — الطرفان معاً */
    public function hasPeriod(): bool
    {
        return $this->start_date !== null && $this->end_date !== null;
    }

    /** أتُصرف عمولتُه مرّةً واحدة عند نهاية المدّة؟ */
    public function settlesAtPeriodEnd(): bool
    {
        return $this->hasPeriod() && $this->settlement_mode !== self::SETTLE_CYCLE;
    }

    /** أانتهت مدّتُه حتى هذا اليوم؟ */
    public function periodEnded(?Carbon $on = null): bool
    {
        return $this->hasPeriod() && $this->end_date->toDateString() <= ($on ?: Carbon::now())->toDateString();
    }

    /**
     * قصُّ فترةٍ على مدّة الهدف.
     *
     * @return array{0: string, 1: string}|null `null` حين لا تقاطع
     */
    public function clampToPeriod(string $from, string $to): ?array
    {
        if (! $this->hasPeriod()) {
            return [$from, $to];
        }

        $from = max($from, $this->start_date->toDateString());
        $to = min($to, $this->end_date->toDateString());

        return $from <= $to ? [$from, $to] : null;
    }

    /** أتقع الفترةُ كلُّها داخل مدّة الهدف؟ */
    public function periodCovers(string $from, string $to): bool
    {
        if (! $this->hasPeriod()) {
            return true;
        }

        return $from >= $this->start_date->toDateString()
            && $to <= $this->end_date->toDateString();
    }

    /** أهدافٌ تُسوّى عند نهاية مدّتها، وانتهت، ولم تُعالَج بعد */
    public function scopeAwaitingPeriodSettlement($query, ?Carbon $on = null)
    {
        return $query
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where(fn ($q) => $q->whereNull('settlement_mode')->orWhere('settlement_mode', self::SETTLE_PERIOD_END))
            ->whereNull('period_settled_at')
            ->whereDate('end_date', '<=', ($on ?: Carbon::now())->toDateString());
    }
}
