<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * مهلةُ السداد محلولةً مرّةً واحدة — تاريخُ الاستحقاق وحالتُه.
 *
 * ══════════ الثغرةُ التي يسدّها ══════════
 *
 * حقل «شروط الدفع» في شاشة إنشاء فاتورة المبيعات كان يُعرض ويُملأ
 * ويُرسَل باسم `terms`، و`Invoice::create()` يكتبه باسم `terms` — ولا
 * عمودَ بهذا الاسم في `invoices`، فيُسقطه `$fillable` بصمت. فالمستخدم
 * يكتب «٣٠ يوماً» عند كل فاتورةٍ آجلة، ولا يُحفظ رقمٌ واحد منها، ولا
 * تعرف المنظومةُ متى تستحقّ فاتورةٌ واحدة. والعمودُ الصحيح `payment_terms`
 * موجودٌ في الجدول منذ إنشائه وبقي فارغاً أبداً.
 *
 * وعرضُ السعر وأمرُ البيع لم يكن فيهما عمودٌ للمهلة أصلاً — والشرحُ في
 * رأس نموذجهما يذكر `terms` صراحةً بوصفه «حقلاً يُعرَض ويُرسَل ولا يقرأه
 * المتحكّم»، فحُذف بدل أن يُوصَل.
 *
 * ══════════ لماذا صنفٌ مشترك ══════════
 *
 * ثلاثةُ مستنداتٍ تسأل السؤال نفسَه (الفاتورة وعرضُ السعر وأمرُ البيع)،
 * وتسألُه في خمسة مواضع: تلميحُ النموذج، وشارةُ القائمة، وصفحةُ المستند،
 * والمطبوعة، والمهمّةُ المجدولة. ولو حُسب التاريخُ في كلٍّ منها لاختلفت
 * المواضعُ بعد تعديلين — وهو ما وقع فعلاً في فلتر الفرع قبل توحيده في
 * {@see AffiliationTree}.
 *
 * ══════════ الأساسُ الذي تُحسب منه المهلة ══════════
 *
 * تاريخُ **إصدار** المستند لا تاريخُ إنشاء الصفّ: فاتورةٌ تُسجَّل اليوم عن
 * بيعٍ وقع قبل أسبوع مهلتُها من يوم البيع. ولذلك يُمرَّر التاريخُ صراحةً
 * من كل مُستدعٍ (`issue_date` في الفاتورة، `quote_date` في عرض السعر
 * وأمر البيع) ولا يُستنتج هنا.
 *
 * ══════════ ومتى تكون الحالةُ حالةَ إنذار ══════════
 *
 * الإنذارُ يتبع **المستحقَّ** لا التاريخَ وحدَه: فاتورةٌ مضى استحقاقُها
 * وسُدِّدت ليست متأخّرة. فمن مرّر `$outstanding = 0` أو `null` يحصل على
 * تاريخٍ ووصفٍ بلا لونِ إنذار، ومن مرّر مبلغاً قائماً يحصل على الشارة.
 * وعرضُ السعر لا مستحقَّ له — لم يُقبَل بعد — فتاريخُه إخبارٌ لا إنذار.
 */
class PaymentTerms
{
    /** كم يوماً قبل الاستحقاق يُعدّ المستند «مستحقّاً قريباً». */
    public const SOON_WINDOW = 7;

    public const OVERDUE = 'overdue';

    public const DUE_TODAY = 'due_today';

    public const DUE_SOON = 'due_soon';

    public const SCHEDULED = 'scheduled';

    private function __construct(
        public readonly int $days,
        public readonly Carbon $baseDate,
        public readonly Carbon $dueDate,
        public readonly int $daysRemaining,
        public readonly string $state,
        public readonly bool $isAlert,
    ) {}

    /**
     * يُبنى من مهلةٍ وتاريخِ أساس — أو `null` حين لا مهلة.
     *
     * `null` لا كائنٌ بحالة «بلا مهلة»: المهلةُ الفارغة تعني «نقداً» أو
     * «غير محدَّد»، ولا شيءَ يُعرض عنها. وكائنٌ فارغٌ يُغري كلَّ مُستدعٍ
     * بفحصٍ إضافيّ يُنسى في موضعٍ من المواضع الخمسة.
     *
     * @param  int|string|null  $days  ما هو مخزَّنٌ في `payment_terms` (عمودٌ نصّي)
     * @param  mixed  $baseDate  تاريخ إصدار المستند
     * @param  float|null  $outstanding  المستحقُّ القائم — `null` أو صفر يعني إخباراً بلا إنذار
     */
    public static function make($days, $baseDate, ?float $outstanding = null): ?self
    {
        $days = static::normalizeDays($days);

        if ($days === null || ! $baseDate) {
            return null;
        }

        try {
            $base = Carbon::parse($baseDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $due = $base->copy()->addDays($days);
        $today = Carbon::today();

        /* `diffInDays` بلا `false` تعود موجبةً دائماً — فيصير المتأخّرُ
           عشرةَ أيامٍ «يستحقّ بعد عشرة». الإشارةُ هي كلُّ المعنى هنا. */
        $remaining = (int) $today->diffInDays($due, false);

        $hasBalance = $outstanding !== null && $outstanding >= 0.005;

        $state = match (true) {
            $remaining < 0 => static::OVERDUE,
            $remaining === 0 => static::DUE_TODAY,
            $remaining <= static::SOON_WINDOW => static::DUE_SOON,
            default => static::SCHEDULED,
        };

        return new self(
            days: $days,
            baseDate: $base,
            dueDate: $due,
            daysRemaining: $remaining,
            state: $state,
            // لا إنذارَ بلا مستحقٍّ قائم، ولا على مستندٍ لم يحن أجلُه بعد
            isAlert: $hasBalance && in_array($state, [static::OVERDUE, static::DUE_TODAY, static::DUE_SOON], true),
        );
    }

    /**
     * مهلةُ مستندٍ مباشرةً — للفاتورة وعرض السعر وأمر البيع.
     *
     * الفاتورةُ تُحسب من `issue_date` وتردّ مستحقَّها بعد المرتجع، وعرضُ
     * السعر وأمرُ البيع من `quote_date` بلا مستحقّ.
     *
     * @param  \App\Models\Invoice|\App\Models\Quote|null  $document
     */
    public static function of($document): ?self
    {
        if (! $document) {
            return null;
        }

        if ($document instanceof \App\Models\Invoice) {
            /* المستحقُّ بعد المرتجع لا قبله — نفسُ حساب `netDue` في جدول
               الفهرس: فاتورةٌ أُرجعت كلُّها لا تتأخّر. والملغاةُ خارج
               الحساب أصلاً. */
            $outstanding = $document->isVoided()
                ? 0.0
                : (float) ($document->due_value ?? 0) - (float) ($document->returned_payment ?? 0);

            return static::make(
                $document->payment_terms,
                $document->issue_date ?: $document->invoice_date,
                $outstanding
            );
        }

        return static::make($document->payment_terms, $document->quote_date);
    }

    /**
     * الوصفُ المعروض — «متأخّرة ١٢ يوماً»، «تستحقّ اليوم»، …
     *
     * مفتاحان صريحان لا `trans_choice`: قاعدةُ الجمع العربية في Laravel
     * ستُّ صيغ، فتردّ لـ«٢٢٤ يوماً» فهرساً رابعاً لا وجودَ له في سلسلةٍ
     * من صيغتين وتسقط إلى الأولى — فتقول «متأخّرة يوماً واحداً» عن
     * فاتورةٍ مضى على أجلها ثمانية أشهر، وهو إنذارٌ مقلوبٌ لا ناقص.
     */
    public function label(): string
    {
        $overdueDays = abs($this->daysRemaining);

        return match ($this->state) {
            static::OVERDUE => $overdueDays === 1
                ? __('payment_terms.state.overdue_one')
                : __('payment_terms.state.overdue', ['days' => $overdueDays]),
            static::DUE_TODAY => __('payment_terms.state.due_today'),
            static::DUE_SOON => $this->daysRemaining === 1
                ? __('payment_terms.state.due_tomorrow')
                : __('payment_terms.state.due_soon', ['days' => $this->daysRemaining]),
            default => __('payment_terms.state.scheduled', ['date' => $this->formattedDueDate()]),
        };
    }

    /** لونُ الشارة — أحمرُ للمتأخّر، وبرتقاليٌّ لما قرُب، ورماديٌّ لما بَعُد. */
    public function tone(): string
    {
        return match ($this->state) {
            static::OVERDUE => 'danger',
            static::DUE_TODAY => 'warning',
            static::DUE_SOON => 'warning',
            default => 'secondary',
        };
    }

    public function icon(): string
    {
        return $this->state === static::OVERDUE ? 'icon-alert-triangle' : 'icon-clock';
    }

    /** تاريخُ الاستحقاق بصيغة اللغة النشطة — لا بصيغةٍ مثبَّتة في القالب. */
    public function formattedDueDate(): string
    {
        return LanguageSettings::formatDate($this->dueDate);
    }

    /** سطرٌ كاملٌ للتلميح: «مهلة ٣٠ يوماً — الاستحقاق ١٢/١٠/٢٠٢٦». */
    public function summary(): string
    {
        return __('payment_terms.summary', [
            'days' => $this->days,
            'date' => $this->formattedDueDate(),
        ]);
    }

    /**
     * المهلةُ رقماً صحيحاً موجباً — أو `null`.
     *
     * العمودُ نصّيٌّ في `invoices` (`string(100)`) وتحمل صفوفُه القديمة
     * فراغاً وأصفاراً ونصوصاً حرّة. والصفرُ ليس مهلةً: «فوريّ» لا استحقاقَ
     * مؤجَّلٌ له، فلا شارةَ ولا تنبيه.
     */
    private static function normalizeDays($days): ?int
    {
        if ($days === null || $days === '' || ! is_numeric($days)) {
            return null;
        }

        $days = (int) $days;

        return $days > 0 ? $days : null;
    }
}
