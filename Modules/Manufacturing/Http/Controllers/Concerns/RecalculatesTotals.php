<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

/**
 * إعادة حساب المبالغ في الخادم، والتحقق من توازن القيد قبل الترحيل.
 *
 * ── لماذا هذا الملف ──────────────────────────────────────────────────
 *
 * كل رقم مالي في الموديول يُحسب في JavaScript ويُقبل من الطلب كما هو:
 *
 *   last_total_cost   BOMController:133, 251 · MOC:305, 597 · OrdersController:78, 207
 *   raw_total …       BOMController:148-174
 *   total_cost        WorkstationsController:205
 *   total             IndirectCostsController:219, 288
 *
 * وBOMController@finish يبني قيد يومية مجموع مدينه من مصدر ومجموع
 * دائنه من مصدر آخر، بلا ما يضمن تساويهما — ثم يُرحّله بحالة
 * `status = 1` وتتغير أرصدة الحسابات بالفارق.
 *
 * أي أن تعديل حقل مخفي في أدوات المطوّر يكفي لكتابة قيد يومية
 * مُرحَّل بالمبلغ الذي يريده المستخدم.
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 * أضف الاسم إلى سطر `use` القائم داخل الصنف — لا تستبدله:
 *
 *     class BOMController extends Controller
 *     {
 *         use GeneratesDocumentNumbers, RecalculatesTotals, ResolvesAccountingAccounts, …;
 *
 * ── ملاحظة ───────────────────────────────────────────────────────────
 *
 * أبقِ حساب JavaScript كما هو — هو للعرض الفوري للمستخدم.
 * الفرق أن الخادم لم يعد يثق به.
 */
trait RecalculatesTotals
{
    /** الدقة المعتمدة للمبالغ. عدّلها إن كانت عملتك بثلاث خانات. */
    protected int $moneyScale = 2;

    /** دقة أسعار الوحدة — أعلى، كي لا يضيع الكسر في المتوسط المرجح. */
    protected int $unitPriceScale = 6;

    /**
     * إجمالي سطر = كمية × سعر. لا يُقبل إجمالي العميل.
     */
    protected function lineTotal($quantity, $unitPrice): float
    {
        return $this->money(((float) $quantity) * ((float) $unitPrice));
    }

    /** تقريب مبلغ إلى دقة العملة. */
    protected function money($value): float
    {
        return round((float) $value, $this->moneyScale);
    }

    /** تقريب سعر وحدة. */
    protected function unitPrice($value): float
    {
        return round((float) $value, $this->unitPriceScale);
    }

    /**
     * قسمة آمنة — تُرجع 0 بدل أن ترمي DivisionByZeroError.
     *
     * الموديول يقسم على `$order->quantity` في موضعين بلا حارس
     * (MOC:1689 و2722)، بينما الحارس موجود في دالة مجاورة
     * (calculateUnitPrice:1657). وDivisionByZeroError هو Error لا
     * Exception، فلا يلتقطه `catch (\Exception $e)` ⇒ لا rollBack
     * ⇒ معاملة معلّقة.
     */
    protected function safeDivide($numerator, $denominator, float $fallback = 0.0): float
    {
        $d = (float) $denominator;

        return abs($d) < 1e-9 ? $fallback : ((float) $numerator) / $d;
    }

    /**
     * مقارنة مبلغين بدقة العملة.
     *
     * `$a == 0` على float قد يفشل على بقايا مثل 1.4e-14، فيمرّ فرعٌ
     * احتياطي كان يجب أن يُنفَّذ — أو يُكتب قيد بمبلغ 0.00 بعد التقريب.
     */
    protected function moneyEquals($a, $b): bool
    {
        return abs($this->money($a) - $this->money($b)) < (0.5 / (10 ** $this->moneyScale));
    }

    protected function isZeroMoney($value): bool
    {
        return $this->moneyEquals($value, 0);
    }

    /**
     * يتحقق أن القيد متوازن قبل الترحيل.
     *
     * استدعِها قبل DB::commit() في كل موضع يُنشئ قيداً.
     *
     * @throws \RuntimeException إذا اختلّ التوازن
     */
    protected function assertBalanced(float $debit, float $credit, string $context = ''): void
    {
        $d = $this->money($debit);
        $c = $this->money($credit);

        if (! $this->moneyEquals($d, $c)) {
            throw new \RuntimeException(sprintf(
                'قيد غير متوازن%s: مدين %s ≠ دائن %s (الفرق %s). أُلغيت العملية.',
                $context ? ' — '.$context : '',
                number_format($d, $this->moneyScale),
                number_format($c, $this->moneyScale),
                number_format($d - $c, $this->moneyScale)
            ));
        }
    }

    /**
     * نفس ما سبق لكن على مصفوفة سطور قيد.
     *
     * كل سطر: ['account_id' => int, 'debit' => float, 'credit' => float, ...]
     *
     * @throws \RuntimeException
     */
    protected function assertLinesBalanced(array $lines, string $context = ''): void
    {
        $debit = array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines));
        $credit = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines));

        $this->assertBalanced($debit, $credit, $context);
    }

    /**
     * يتحقق أن إجمالي المستند يطابق مجموع سطوره.
     *
     * يخدم الثابت S3: `grand_total` للإذن = مجموع بنوده.
     * كان createWarehousePermitForOrder يكتب إجمالاً «يشمل الهالك»
     * ثم يضيف بند المنتج الرئيسي وحده — ودالة إضافة بنود الهالك
     * لا تُستدعى إطلاقاً.
     *
     * @throws \RuntimeException
     */
    protected function assertHeaderMatchesLines(float $header, float $linesSum, string $context = ''): void
    {
        if (! $this->moneyEquals($header, $linesSum)) {
            throw new \RuntimeException(sprintf(
                'إجمالي المستند لا يطابق مجموع بنوده%s: الرأس %s ≠ البنود %s.',
                $context ? ' — '.$context : '',
                number_format($this->money($header), $this->moneyScale),
                number_format($this->money($linesSum), $this->moneyScale)
            ));
        }
    }
}
