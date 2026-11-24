<?php

namespace Modules\Pos\Http\Controllers\Concerns;

/**
 * أرقام مستندات نقاط البيع من إعدادات «الترقيم المتسلسل» لا من عدّ الصفوف.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 *     $lastInvoice = Invoice::where('type','pos')->orderBy('id','desc')->first();
 *     $nextNumber  = $lastInvoice ? intval(substr($lastInvoice->code, -5)) + 1 : 1;
 *     while (Invoice::where('code', 'POS'.str_pad($nextNumber, 5, '0', STR_PAD_LEFT))->exists()) {
 *         $nextNumber++;
 *     }
 *
 * ثلاثةُ مواضع بهذه الصيغة: فاتورةُ البيع، وفاتورةُ المرتجع، وسندُ القبض
 * التلقائي. وفيها ثلاثةُ عيوب:
 *
 * • *التصادم*: لا قفلَ ولا معاملة. جهازا كاشيرٍ يبيعان في اللحظة نفسها
 *   يقرآن آخرَ فاتورةٍ نفسِها، فيحسبان الرقمَ نفسَه، ويجتازان حلقةَ
 *   `while` معاً قبل أن يحفظ أيٌّ منهما — وهذا هو الحال الطبيعي في نقاط
 *   البيع لا الحالُ النادر: أجهزةٌ متعدّدة تبيع في وقتٍ واحد هو تعريفُ
 *   الوحدة.
 *
 * • *الحلقةُ المفتوحة*: `while ... exists()` استعلامٌ لكل محاولة. سلسلةٌ
 *   فيها ثغراتٌ كثيرة تجعل الحفظَ يستعلم عشراتِ المرّات قبل أن يستقرّ.
 *
 * • *تجاهلُ الإعدادات*: البادئةُ `POS` والطولُ `5` مكتوبان في الشيفرة.
 *   فالمنشأة تضبط ترقيمها في شاشة «تسلسل المستندات» ولا يسري على نقاط
 *   البيع شيءٌ منه — ولا التصفيرُ الدوري ولا البادئةُ ولا الطول.
 *
 * ─────────────────────── ما صار ───────────────────────
 *
 *   شاشة الإنشاء ← `previewDocumentNumber()` معاينةٌ للعرض فقط، بلا استهلاك.
 *   الحفظ        ← `issueDocumentNumber()` تستهلك رقماً تحت قفل.
 *   التعديل      ← `keepDocumentNumber()` تُبقي رقم المستند كما صدر.
 *
 * ─────────────────────── ولا يُقبل رقمٌ من الطلب ───────────────────────
 *
 * الرقم حقلٌ تلقائي: مرجعٌ يحمله القيدُ المحاسبي وسجلُّ العمليات ورمزُ
 * الاستجابة المطبوع على الإيصال الحراري الذي يخرج من يد الكاشير. فلو سُمح
 * بتحريره انفصل المستندُ عن قيده، وأمكن انتحالُ رقم فاتورةٍ أخرى — وفي
 * نقاط البيع خاصّةً حيث الرقمُ هو ما يُبرزه العميل عند المرتجع.
 *
 * أقسام نقاط البيع في `DocumentSections`: `pos-invoice`، `pos-return`،
 * `pos-session`، `pos-cash-movement`.
 */
trait GeneratesDocumentNumbers
{
    /** فاتورة بيع من الشاشة. */
    protected const SECTION_INVOICE = 'pos-invoice';

    /** مرتجع نقطة بيع. */
    protected const SECTION_RETURN = 'pos-return';

    /** رقم الجلسة. */
    protected const SECTION_SESSION = 'pos-session';

    /** حركة درج نقدي — إيداع أو سحب أو ترحيل. */
    protected const SECTION_CASH_MOVEMENT = 'pos-cash-movement';

    /*
        ═══════════ إذنا المخزون ═══════════

        الإذنُ مستندٌ له رقمُه كسائر المستندات — يُقرأ في شاشة الأذون
        ويُبحث به وتُطبع نسخةٌ منه.

        وكان `number` يحمل **معرّف الفاتورة** (`$invoice->id`)، ثم صار
        يحمل رقمَها. وكلاهما خطأ: رقمُ الفاتورة ليس رقمَ الإذن، وفاتورةٌ
        واحدةٌ قد تُصدر أذوناً عدّة (منتجٌ مجمَّعٌ فوريّ يُصدر ثلاثة) —
        فتحمل كلُّها الرقمَ نفسَه.

        والقسمان معرَّفان في `DocumentSections` ومربوطان بـ
        `warehouse_permits.number`:

          • `warehouse-dispose` (WOUT) — صرفٌ: البيع.
          • `warehouse-add`     (WIN)  — إضافة: المرتجع، والمجمَّعُ الفوري.
    */

    /** إذن صرف مخزون — ما يخرج من المستودع. */
    protected const SECTION_PERMIT_OUT = 'warehouse-dispose';

    /** إذن إضافة مخزون — ما يدخل إليه. */
    protected const SECTION_PERMIT_IN = 'warehouse-add';

    /**
     * الرقم التالي للعرض في الشاشة — بلا استهلاك.
     *
     * لا يُستهلك هنا عمداً: الكاشير يفتح الشاشة ويتركها مفتوحةً طوال
     * الوردية، فاستهلاك الرقم عند العرض يُحدث ثغراتٍ في التسلسل بلا مستندٍ
     * يقابلها. وهو للعرض وحده — المعتمدُ ما تُصدره `issueDocumentNumber()`
     * عند الحفظ، وقد يختلف عن المعاينة إن سبق جهازٌ آخر إليه.
     */
    protected function previewDocumentNumber(string $section): string
    {
        try {
            return peek_document_number($section);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * الرقم المعتمد عند الحفظ — من الإعدادات وحدها.
     *
     * `DocumentNumberService::next()` تعمل داخل معاملة مع `lockForUpdate`
     * وتتخطّى الأرقام المحجوزة فعلاً، فلا تصادمَ بين جهازين ولا تكرار.
     *
     * @param  string  $section  مفتاح القسم في `DocumentSections`
     */
    protected function issueDocumentNumber(string $section): string
    {
        return next_document_number($section);
    }

    /**
     * رقم المستند عند التعديل — كما صدر، بلا مساس.
     *
     * @param  string|null  $current  الرقم المحفوظ في السجل
     * @param  string  $section  مفتاح القسم في `DocumentSections`
     */
    protected function keepDocumentNumber(?string $current, string $section): string
    {
        $current = trim((string) $current);

        return $current !== '' ? $current : $this->issueDocumentNumber($section);
    }

    /** رقم فاتورة بيعٍ جديدة. */
    protected function issueInvoiceNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_INVOICE);
    }

    /** رقم فاتورة مرتجعٍ جديدة. */
    protected function issueReturnNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_RETURN);
    }

    /** رقم جلسةٍ جديدة. */
    protected function issueSessionNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_SESSION);
    }

    /** رقم حركة درجٍ جديدة. */
    protected function issueCashMovementNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_CASH_MOVEMENT);
    }

    /** رقم إذن صرفٍ مخزني — بيعٌ يخرج من المستودع. */
    protected function issuePermitOutNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_PERMIT_OUT);
    }

    /** رقم إذن إضافةٍ مخزنية — مرتجعٌ يعود إليه. */
    protected function issuePermitInNumber(): string
    {
        return $this->issueDocumentNumber(self::SECTION_PERMIT_IN);
    }
}
