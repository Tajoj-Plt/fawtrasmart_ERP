<?php

namespace Modules\Sales\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * حسابُ اتفاقية التقسيط وجدولِ أقساطها — صنفٌ نقيّ بلا قاعدة بيانات.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  لماذا صنفٌ مستقلّ؟
 * ══════════════════════════════════════════════════════════════════════
 *
 * كان الحساب موزّعاً على ثلاثة مواضع متعارضة:
 *
 *   • `InstallmentsController::createInstallmentDetails()` — يقسّم
 *     `$invoice->due_value` ويعطي الباقي للقسط الأخير.
 *   • `InstallmentsController::update()` — يحسب العدد بـ
 *     `floor($grandTotal / $amount)` و`$grandTotal % $amount`، و`%` في PHP
 *     يحوّل معاملَيه إلى `int`: مع 1050.75 و100.25 تصير العملية
 *     `1050 % 100 = 50` بدل 48.75. والقسمة بلا حمايةٍ من الصفر →
 *     `DivisionByZeroError`. (البند 2.9 في تقرير التحسين.)
 *   • `create.blade.php` — نسخةٌ ثالثة بجافاسكربت تعرض جدولاً للمستخدم
 *     لا يُرسَل ولا يُحفظ، فما يراه قبل الحفظ غير ما يُحفظ.
 *
 * ثلاث حسابات لشيءٍ واحد تعني ثلاثة أرقام مختلفة. هنا حسابٌ واحد يناديه
 * المتحكّم، وتعرضه الشاشة بمناداته هو نفسه لا بإعادة كتابته.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  السياسة المالية — غيّرها من هنا وحدها
 * ══════════════════════════════════════════════════════════════════════
 *
 * هذه قراراتٌ ماليةٌ لا تقنية، مكتوبةٌ صريحةً كي تُراجَع لا كي تُكتشَف:
 *
 * **١. الربح مرابحةٌ ثابتة لا فائدةٌ متناقصة.**
 *     يُحتسب مرّةً واحدة ثمّ يُقسَّم على الأقساط، ولا يُعاد حسابه على
 *     الرصيد المتناقص. العمود `profit_method` يحمل `flat` كي يُضاف
 *     `declining` لاحقاً بلا هجرة — والمكان الوحيد الذي سيتغيّر هو
 *     `profitAmount()` أدناه.
 *
 * **١-ب. وعاء المرابحة هو الإجمالي كلُّه — والدفعة المقدَّمة من ضمن
 *     التمويل.**
 *
 *         التمويل = الإجمالي + الربح + الرسوم + الضريبة
 *         الربح   = الإجمالي × النسبة
 *         ما يُقسَّم على الأقساط = التمويل − الدفعة المقدَّمة
 *
 *     أي أنّ المقدَّمة **سدادٌ على تمويلٍ قائم** لا خصمٌ يُصغّر وعاءَه.
 *     مثالاً: إجمالي ١٠٠٠٠ ومقدَّمة ٢٠٠٠ ومرابحة ١٠٪ → تمويلٌ ١١٠٠٠،
 *     يُقسَّم منه ٩٠٠٠.
 *
 *     وكان الوعاء `الإجمالي − المقدَّمة`، فتُصغّر المقدَّمةُ الربحَ:
 *     في المثال نفسه ٨٠٠ بدل ١٠٠٠. غُيِّر بقرار المستخدم — وهو قرارٌ
 *     ماليّ لا تقنيّ، فمُثبَتٌ هنا لا مُستنتَجٌ من الشيفرة.
 *
 * **٢. الضريبة على الربح والرسوم الإدارية وحدهما.**
 *     لا على قيمة البضاعة: ضريبتها محتسبةٌ في الفاتورة أصلاً، وإعادةُ
 *     احتسابها هنا ازدواجٌ ضريبيّ. فالوعاء هنا هو مقابل الخدمة التمويلية
 *     وحده.
 *
 * **٣. غرامة التأخير تُحتسب هنا وتُحمَّل وتُقيَّد في مكانٍ آخر.**
 *     `lateFeeFor()` تُرجع المستحقّ على قسطٍ متأخّر بعد أيام السماح —
 *     نسبةً أو مبلغاً مقطوعاً، مرّةً واحدة أو عن كلّ يوم تأخير، بسقفٍ
 *     اختياري. **ولا يكتب هذا الصنف شيئاً**: التحميلُ على القسط والقيدُ
 *     المحاسبيّ في `App\Services\Installments\LateFeeCharger`.
 *
 *     وكان ذلك سابقاً قراراً صريحاً — «تحميلُ مالٍ على عميلٍ آلياً لا يقع
 *     بلا فعلٍ بشريّ» — والفعلُ البشريّ الآن هو **ضبطُ سياسة الغرامة في
 *     الاتفاقية نفسها**: اتفاقيةٌ بلا `late_fee_type` لا تُحمَّل شيئاً
 *     مهما تأخّرت أقساطُها، ومن ضبط السياسة فقد وافق على أثرها.
 *
 * **٣-ب. الغرامة تُحتسب على *متبقّي* القسط لا على مبلغه.**
 *     قسطٌ بألف سُدِّد منه تسعمئة تأخّرُه مئة لا ألف، وغرامتُه بنسبتها.
 *
 * **٤. الكسور تذهب إلى القسط الأخير.**
 *     100 على 3 = 33.33 + 33.33 + 33.34. البديل — توزيع الهللة على
 *     الأوائل — يجعل الأقساط غير متساوية بلا سبب ظاهر للعميل.
 *
 * ══════════════════════════════════════════════════════════════════════
 */
class InstallmentSchedule
{
    /** الربح مرابحةً ثابتة — النسبة تُطبَّق مرّةً على المبلغ الممول. */
    public const PROFIT_FLAT = 'flat';

    /**
     * دوريّات السداد وما تعنيه بالأيام/الأشهر — مصدرها الوحيد.
     *
     * و`align` هو مفتاحُ **بداية المدّة** التي يبدأ منها أوّلُ قسط:
     * انظر `alignedFirstDueDate()` أدناه.
     */
    public const RATES = [
        1 => ['key' => 'monthly', 'months' => 1, 'align' => 'month'],
        2 => ['key' => 'weekly', 'weeks' => 1, 'align' => 'week'],
        3 => ['key' => 'yearly', 'months' => 12, 'align' => 'year'],
        4 => ['key' => 'quarterly', 'months' => 3, 'align' => 'quarter'],
        5 => ['key' => 'biweekly', 'weeks' => 2, 'align' => 'week'],
    ];

    /** غرامةٌ تُحمَّل مرّةً واحدة بعد انقضاء السماح. */
    public const FEE_ONCE = 'once';

    /** غرامةٌ تُحمَّل عن كلّ يوم تأخير بعد السماح. */
    public const FEE_DAILY = 'daily';

    public const FEE_BASES = [self::FEE_ONCE, self::FEE_DAILY];

    /** أوّلُ أيام الأسبوع في هذا النظام — السبت، كما في `WeekHelper`. */
    public const WEEK_STARTS_ON = Carbon::SATURDAY;

    /** أقصى عدد أقساط تُقبَل — حارسٌ ضدّ حلقةٍ تبني مئة ألف صفّ. */
    public const MAX_INSTALLMENTS = 600;

    // ══════════════════ المدخلات ══════════════════

    private float $totalAmount;

    private float $downPayment;

    private float $profitRate;

    private float $adminFees;

    private float $taxRate;

    private int $count;

    private int $paymentRate;

    private Carbon $firstDueDate;

    /**
     * @param  float  $totalAmount  إجمالي المستحقّ على الفاتورة قبل التقسيط
     * @param  float  $downPayment  الدفعة المقدَّمة — تُخصَم قبل التمويل
     * @param  float  $profitRate  نسبة المرابحة المئوية (0 = بلا ربح)
     * @param  float  $adminFees  رسومٌ إدارية مقطوعة تُضاف إلى الممول
     * @param  float  $taxRate  نسبة الضريبة المئوية على الربح والرسوم
     * @param  int  $count  عدد الأقساط
     * @param  int  $paymentRate  مفتاح الدوريّة من `RATES`
     */
    public function __construct(
        float $totalAmount,
        float $downPayment,
        float $profitRate,
        float $adminFees,
        float $taxRate,
        int $count,
        int $paymentRate,
        Carbon|string $firstDueDate
    ) {
        // الفحص هنا لا في المتحكّم: صنفٌ يُنادى من شاشةٍ ومن أمرٍ ومن
        // اختبارٍ لا يصحّ أن يثق بأن كلّاً منها فحص قبله
        if ($count < 1) {
            throw new InvalidArgumentException('عدد الأقساط يجب أن يكون واحداً على الأقلّ.');
        }

        if ($count > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('عدد الأقساط يتجاوز الحدّ المسموح.');
        }

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('المبلغ الإجمالي يجب أن يكون أكبر من صفر.');
        }

        if ($downPayment < 0 || $downPayment >= $totalAmount) {
            throw new InvalidArgumentException('الدفعة المقدَّمة يجب أن تكون أقلّ من المبلغ الإجمالي.');
        }

        if (! isset(self::RATES[$paymentRate])) {
            throw new InvalidArgumentException('دوريّة سدادٍ غير معروفة.');
        }

        $this->totalAmount = round($totalAmount, 2);
        $this->downPayment = round(max(0, $downPayment), 2);
        $this->profitRate = max(0, $profitRate);
        $this->adminFees = round(max(0, $adminFees), 2);
        $this->taxRate = max(0, $taxRate);
        $this->count = $count;
        $this->paymentRate = $paymentRate;
        $this->firstDueDate = $firstDueDate instanceof Carbon
            ? $firstDueDate->copy()
            : Carbon::parse($firstDueDate);
    }

    // ══════════════════ المبالغ ══════════════════

    /** ما بقي من قيمة البضاعة بعد الدفعة المقدَّمة — نصيبُ الأصل في الجدول. */
    public function principal(): float
    {
        return round($this->totalAmount - $this->downPayment, 2);
    }

    /**
     * الربح — مرابحةً ثابتة على الإجمالي كلِّه.
     *
     * على `totalAmount` لا على `principal()`: الدفعة المقدَّمة من ضمن
     * التمويل فلا تُصغّر وعاءَه — انظر البند ١-ب في رأس الصنف.
     */
    public function profitAmount(): float
    {
        // موضع التفرّع لو أُضيف `declining` لاحقاً
        return round($this->totalAmount * ($this->profitRate / 100), 2);
    }

    /** الضريبة — على الربح والرسوم الإدارية وحدهما، لا على البضاعة. */
    public function taxAmount(): float
    {
        return round(($this->profitAmount() + $this->adminFees) * ($this->taxRate / 100), 2);
    }

    /**
     * التمويل — الإجمالي كلُّه بربحه ورسومه وضريبته، **والمقدَّمة داخله**.
     *
     * رقمُ العرض لا رقمُ الجدول: ما يُقسَّم على الأقساط هو
     * `scheduledAmount()` أدناه.
     */
    public function financedAmount(): float
    {
        return round(
            $this->totalAmount + $this->profitAmount() + $this->adminFees + $this->taxAmount(),
            2
        );
    }

    /**
     * ما يُقسَّم فعلاً على الأقساط: التمويل ناقصَ الدفعة المقدَّمة.
     *
     * وهو ما يُحفَظ في `installments.financed_amount`، وعليه تُبنى
     * `remaining_amount` و`progress` في `Installment` — مجموعُ الأقساط
     * يساويه بالضبط، فلو حُفظ التمويلُ الإجمالي مكانَه لظهر كلُّ اتفاقٍ
     * ناقصَ السداد بمقدار مقدَّمته إلى الأبد.
     */
    public function scheduledAmount(): float
    {
        return round($this->financedAmount() - $this->downPayment, 2);
    }

    /**
     * قيمة القسط الواحد — بلا الكسر المتبقّي.
     *
     * القسط الأخير يحمل الفرق، فمجموع الجدول يساوي المُقسَّط تماماً.
     */
    public function installmentAmount(): float
    {
        return floor(($this->scheduledAmount() / $this->count) * 100) / 100;
    }

    // ══════════════════ الجدول ══════════════════

    /**
     * جدول الأقساط.
     *
     * كلّ قسطٍ يحمل نصيبه من الأصل والربح والضريبة، لا مبلغاً مجملاً
     * وحده: التقرير المحاسبي يحتاج فصلَ مقابل البضاعة عن مقابل التمويل،
     * ولا سبيل إلى استخراجه بعد الحفظ إن لم يُخزَّن.
     *
     * @return array<int, array{number:int, due_date:string, amount:float,
     *                          principal_amount:float, profit_amount:float,
     *                          fees_amount:float, tax_amount:float}>
     */
    public function rows(): array
    {
        $financed = $this->scheduledAmount();
        $each = $this->installmentAmount();

        $rows = [];
        $allocated = 0.0;
        $due = $this->firstDueDate->copy();

        // نِسَب التوزيع: نصيب القسط من كلّ مكوّن بنسبة مبلغه من الممول
        $principalTotal = $this->principal();
        $profitTotal = $this->profitAmount();
        // الرسوم في عمودها لا داخل الضريبة: كانت تُجمَع إليها فيعرض كلُّ
        // قسطٍ ضريبةً أكبرَ ممّا عليه
        $feesTotal = $this->adminFees;
        $taxTotal = $this->taxAmount();

        $principalLeft = $principalTotal;
        $profitLeft = $profitTotal;
        $feesLeft = $feesTotal;
        $taxLeft = $taxTotal;

        for ($i = 1; $i <= $this->count; $i++) {
            $isLast = $i === $this->count;

            // الأخير يأخذ ما تبقّى بالضبط — فلا كسرٌ يضيع ولا يُخترع
            $amount = $isLast ? round($financed - $allocated, 2) : $each;

            $share = $financed > 0 ? $amount / $financed : 0;

            $principalPart = $isLast ? round($principalLeft, 2) : round($principalTotal * $share, 2);
            $profitPart = $isLast ? round($profitLeft, 2) : round($profitTotal * $share, 2);
            $feesPart = $isLast ? round($feesLeft, 2) : round($feesTotal * $share, 2);
            $taxPart = $isLast ? round($taxLeft, 2) : round($amount - $principalPart - $profitPart - $feesPart, 2);

            $rows[] = [
                'number' => $i,
                'due_date' => $due->format('Y-m-d'),
                'amount' => $amount,
                'principal_amount' => $principalPart,
                'profit_amount' => $profitPart,
                'fees_amount' => $feesPart,
                'tax_amount' => $taxPart,
            ];

            $allocated = round($allocated + $amount, 2);
            $principalLeft = round($principalLeft - $principalPart, 2);
            $profitLeft = round($profitLeft - $profitPart, 2);
            $feesLeft = round($feesLeft - $feesPart, 2);
            $taxLeft = round($taxLeft - $taxPart, 2);

            $due = $this->advance($due);
        }

        return $rows;
    }

    /** مجموع الجدول — يجب أن يساوي `financedAmount()` دائماً. */
    public function rowsTotal(): float
    {
        return round(array_sum(array_column($this->rows(), 'amount')), 2);
    }

    /**
     * ملخّصٌ للعرض والحفظ معاً — فالشاشة والمتحكّم يقرآن الرقم نفسه.
     *
     * @return array<string, float|int|string>
     */
    public function summary(): array
    {
        return [
            'total_amount' => $this->totalAmount,
            'down_payment' => $this->downPayment,
            'principal_amount' => $this->principal(),
            'profit_rate' => $this->profitRate,
            'profit_amount' => $this->profitAmount(),
            'admin_fees' => $this->adminFees,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount(),
            /* العمود يحمل المُقسَّط لا التمويل — انظر `scheduledAmount()`.
               والتمويل الإجمالي يُعرَض من `financed_amount + down_payment`. */
            'financed_amount' => $this->scheduledAmount(),
            'installment_amount' => $this->installmentAmount(),
            'installment_number' => $this->count,
            'payment_rate' => $this->paymentRate,
            'first_due_date' => $this->firstDueDate->format('Y-m-d'),
            'last_due_date' => $this->lastDueDate(),
        ];
    }

    public function lastDueDate(): string
    {
        $rows = $this->rows();

        return (string) end($rows)['due_date'];
    }

    /**
     * التاريخ التالي حسب الدوريّة.
     *
     * `addMonthsNoOverflow` مقصود: `addMonth()` على 31 يناير يعطي 3 مارس
     * (أو 2 في الكبيسة)، فينزلق الاستحقاق شهراً كلّما مرّ شهرٌ قصير.
     * `NoOverflow` تُبقيه في آخر يومٍ من الشهر التالي.
     */
    private function advance(Carbon $date): Carbon
    {
        $rate = self::RATES[$this->paymentRate];

        if (isset($rate['weeks'])) {
            return $date->copy()->addWeeks($rate['weeks']);
        }

        return $date->copy()->addMonthsNoOverflow($rate['months']);
    }

    // ══════════════════ الغرامة ══════════════════

    /**
     * أيام التأخّر المحتسَبة — بعد انقضاء مدّة السماح.
     *
     * صفرٌ يعني «لا غرامة»: قسطٌ حلّ أمسِ وسماحُه سبعةُ أيام ليس متأخّراً
     * بعد، وقسطٌ حلّ قبل عشرةٍ وسماحُه سبعة تأخّر **ثلاثة** لا عشرة —
     * فالسماحُ مدّةٌ لا تُحتسب لا مجرّد شرطٍ للبدء.
     *
     * @param  Carbon|string|null  $asOf  اليوم الذي يُقاس إليه — اليومُ افتراضاً
     */
    public static function lateDaysFor(
        Carbon|string|null $dueDate,
        int $graceDays = 0,
        Carbon|string|null $asOf = null
    ): int {
        if (! $dueDate) {
            return 0;
        }

        $due = ($dueDate instanceof Carbon ? $dueDate->copy() : Carbon::parse($dueDate))->startOfDay();
        $today = ($asOf instanceof Carbon ? $asOf->copy() : Carbon::parse($asOf ?: 'today'))->startOfDay();

        $graceEnds = $due->copy()->addDays(max(0, $graceDays));

        return $graceEnds->lt($today) ? (int) $graceEnds->diffInDays($today) : 0;
    }

    /**
     * غرامة التأخير المستحقّة على قسط.
     *
     * ══════════════════ القانون ══════════════════
     *
     *   أيام التأخّر = اليوم − (الاستحقاق + أيام السماح)
     *   نسبةً       : المتبقّي × النسبة ÷ 100
     *   مقطوعةً     : المبلغ المقطوع
     *   و`daily`    : يُضرَب الناتجُ في أيام التأخّر
     *   ثمّ السقف   : إن ضُبط `$max` لم تتجاوزه مهما طال التأخّر
     *
     * السقفُ ليس ترفاً: احتسابٌ يوميٌّ بلا سقفٍ يجعل غرامةَ قسطٍ مهجورٍ
     * سنةً تتجاوز أصلَه أضعافاً، فيصير الرقمُ بلا معنى ولا يُطالَب به.
     *
     * **ولا يكتب هذا الصنف شيئاً**: الحساب هنا، والتحميلُ والقيدُ في
     * `App\Services\Installments\LateFeeCharger` — فمن أراد أن يرى
     * الرقم بلا أن يُحمّله يناديها هنا وحدها.
     *
     * @param  float  $base  ما يُحتسب عليه — متبقّي القسط
     * @param  string  $type  percent | fixed
     * @param  float  $value  النسبة المئوية أو المبلغ المقطوع
     * @param  int  $graceDays  أيام السماح قبل بدء الاستحقاق
     * @param  string  $basis  once | daily
     * @param  float  $max  سقف الغرامة على القسط — 0 أو null: بلا سقف
     */
    public static function lateFeeFor(
        float $base,
        Carbon|string|null $dueDate,
        ?string $type,
        ?float $value,
        int $graceDays = 0,
        ?string $basis = self::FEE_ONCE,
        ?float $max = null,
        Carbon|string|null $asOf = null
    ): float {
        if (! $dueDate || ! $type || ! $value || $value <= 0 || $base <= 0) {
            return 0.0;
        }

        $days = self::lateDaysFor($dueDate, $graceDays, $asOf);

        if ($days < 1) {
            return 0.0;
        }

        $unit = $type === 'fixed'
            ? round($value, 2)
            : round($base * ($value / 100), 2);

        $fee = $basis === self::FEE_DAILY ? $unit * $days : $unit;

        if ($max !== null && $max > 0) {
            $fee = min($fee, $max);
        }

        return round($fee, 2);
    }

    // ══════════════════ بداية الجدول ══════════════════

    /**
     * تاريخ أوّل قسط — **من بداية المدّة التالية لا من اليوم**.
     *
     * ══════════════════ ما كان ══════════════════
     *
     * `create()` كان يقترح `now()->addMonth()` مهما كانت الدوريّة: فمن
     * اختار الأسبوعيّ يوم الثلاثاء صار استحقاقُه كلَّ ثلاثاء، ومن اختار
     * الشهريّ يوم ٢٣ صار استحقاقُه كلَّ ٢٣. فجدولُ التحصيل يتفرّق على
     * أيام الشهر كلِّها بعدد الاتفاقيات، ولا يقع أسبوعٌ ماليٌّ ولا شهرٌ
     * على حدٍّ معلوم.
     *
     * ══════════════════ ما صار ══════════════════
     *
     *   أسبوعيّ / كلّ أسبوعين  ← أوّلُ السبت القادم (والأسبوع يبدأ
     *                            بالسبت في هذا النظام — `WeekHelper`)
     *   شهريّ                  ← أوّلُ الشهر القادم
     *   ربع سنويّ              ← أوّلُ الربع القادم
     *   سنويّ                  ← أوّلُ السنة القادمة
     *
     * أي أنّ يومَ التوقيع **لا يُحتسب** من المدّة الأولى: العميلُ الذي
     * وقّع الأربعاء لا يُطالَب بقسط أسبوعٍ بقي منه يومان.
     *
     * والتاريخُ مُقترَحٌ لا مفروض: الحقلُ في الشاشة يبقى قابلاً للتعديل،
     * وهذه قيمتُه المبدئية وما يعود إليه عند تبديل الدوريّة.
     */
    public static function alignedFirstDueDate(int $paymentRate, Carbon|string|null $from = null): Carbon
    {
        $date = ($from instanceof Carbon ? $from->copy() : Carbon::parse($from ?: 'today'))->startOfDay();

        $align = self::RATES[$paymentRate]['align'] ?? 'month';

        return match ($align) {
            // `next()` تُرجع أقربَ سبتٍ **بعد** اليوم — ولو كان اليومُ سبتاً
            'week' => $date->copy()->next(self::WEEK_STARTS_ON),
            'quarter' => $date->copy()->firstOfQuarter()->addQuarter()->startOfDay(),
            'year' => $date->copy()->addYear()->startOfYear(),
            default => $date->copy()->addMonthNoOverflow()->startOfMonth(),
        };
    }

    /**
     * خريطة الدوريّة ← تاريخُها المُقترَح — للشاشة.
     *
     * تُبنى هنا لا في جافاسكربت: حسابُ «أوّل السبت القادم» و«أوّل الربع»
     * مرّتين بلغتين نسختان تتباعدان، وقد سبق أن كلّف ذلك هذا القسمَ
     * ثلاثَ حساباتٍ متعارضة للجدول نفسه.
     *
     * @return array<int, string>
     */
    public static function alignmentMap(Carbon|string|null $from = null): array
    {
        $map = [];

        foreach (array_keys(self::RATES) as $rate) {
            $map[$rate] = self::alignedFirstDueDate($rate, $from)->format('Y-m-d');
        }

        return $map;
    }

    /** مفتاح ترجمة الدوريّة — `RATES` هي المصدر، لا قائمةٌ في القالب. */
    public static function rateKey(?int $paymentRate): string
    {
        return self::RATES[$paymentRate]['key'] ?? 'monthly';
    }
}
