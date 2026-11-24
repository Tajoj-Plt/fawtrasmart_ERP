<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعةُ مستندات نقاط البيع بالقوالب المصمَّمة في الإعدادات.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * `print()` و`printReturnInvoice()` تعرضان قالبَي Blade مثبّتَين في
 * الوحدة (`sales_start.print` و`sales_start.return_print`).
 *
 * وفي المشروع شاشةٌ كاملة لتصميم القوالب
 * (`Sitting\DocumentTemplateController`)، ونظامُ تصيير
 * (`DocumentTemplateRenderer`)، **ومئتا قالبٍ مصمَّمٍ في القاعدة** موزَّعةٍ
 * على عشرين نوعاً — لكلِّ نوعٍ افتراضيُّه. وتستعمله المبيعاتُ والمشترياتُ
 * والعملاء عبر سماتٍ مماثلة لهذه.
 *
 * ونقاطُ البيع وحدها لا تقرأ منه حرفاً. فيصمّم المستخدمُ قالبَ الإيصال
 * ويحفظه ويعيّنه افتراضياً، ثم يطبع فاتورةَ الكاشير فيخرج قالبُ النظام
 * المدمج ولا أثرَ لما صمّم.
 *
 * ═════════════════════ ونوعُ المستند ═════════════════════
 *
 * فاتورةُ نقطة البيع **فاتورةٌ ضريبية مبسَّطة** (B2C) في اصطلاح الهيئة، لا
 * فاتورةَ مبيعاتٍ عادية. ولذلك `simplified_invoice` لا `sales_invoice` —
 * وهو نوعٌ قائمٌ في `DocumentTemplateDataService` ويُصيَّر من جدول
 * `invoices` نفسِه الذي تكتب فيه الشاشة.
 *
 * والمرتجعُ `sales_return`، وهو النوعُ الذي يقرأ `type = 'returned'`.
 *
 * ═════════════════════ والقالبُ المدمج يبقى ═════════════════════
 *
 * `null` من `renderPosTemplate()` ليست خطأً بل **تفويضاً**: النداءُ يعرض
 * قالبَه المدمج كما كان. فمنشأةٌ لم تصمّم قالباً بعد، أو قالبٌ تعذّر
 * تصييرُه، لا يجوز أن يمنعا طباعةَ إيصال.
 */
trait RendersPosDocumentTemplates
{
    /** نوعُ قالبِ فاتورة نقطة البيع — فاتورةٌ ضريبية مبسَّطة. */
    public const TEMPLATE_INVOICE = 'simplified_invoice';

    /** ونوعُ قالبِ المرتجع. */
    public const TEMPLATE_RETURN = 'sales_return';

    /**
     * قوالبُ نوعٍ — الفعّالة، وفرعُ المستخدم أو العامّة.
     *
     * @return Collection<int, DocumentTemplate>
     */
    protected function posTemplates(string $documentType): Collection
    {
        return PrintSettings::templatesFor($documentType);
    }

    /**
     * القالبُ المختار لنوعٍ — بثلاث مراتب.
     *
     * ═══════════ لماذا مرتبةٌ خاصّةٌ بنقاط البيع ═══════════
     *
     * `PrintSettings::defaultTemplateFor()` تُجيب عن «أيُّ قالبٍ افتراضيٌّ
     * لهذا النوع في المنشأة؟» — وهو جوابٌ عامّ تشترك فيه المبيعاتُ ونقاطُ
     * البيع.
     *
     * وهما لا يطبعان على ورقٍ واحد: المبيعاتُ على A4 والكاشيرُ على شريطٍ
     * حراريٍّ بعرض 80mm. فقالبٌ واحدٌ لا يخدم الاثنين، ولذلك صار لنقاط
     * البيع اختيارُها في إعداداتها.
     *
     * ═══════════ المراتب ═══════════
     *
     *   `<رقم>`    ⇒ هذا القالبُ بعينه — ما اختاره المستخدم في الإعدادات.
     *   `default`  ⇒ الافتراضيُّ المعيَّن في شاشة «قوالب المستندات».
     *   `builtin`  ⇒ قالبُ النظام المدمج (`null` من هنا).
     */
    protected function defaultPosTemplate(string $documentType): ?DocumentTemplate
    {
        $choice = $this->posTemplateChoice($documentType);

        if ($choice === 'builtin') {
            return null;
        }

        if (is_numeric($choice)) {
            $picked = DocumentTemplate::where('id', (int) $choice)
                ->where('document_type', $documentType)
                ->first();

            /*
                القالبُ المختار قد يُحذف أو يُنقل إلى نوعٍ آخر — فيُرجَع إلى
                الافتراضي بدل أن تتعطّل الطباعة على اختيارٍ لم يعد قائماً.
            */
            if ($picked) {
                return $picked;
            }
        }

        return PrintSettings::defaultTemplateFor($documentType);
    }

    /** ما اختاره المستخدم في إعدادات نقاط البيع لهذا النوع. */
    protected function posTemplateChoice(string $documentType): ?string
    {
        $column = $documentType === self::TEMPLATE_RETURN ? 'return_template' : 'invoice_template';

        $value = $this->posSettings()?->{$column};

        return filled($value) ? (string) $value : 'default';
    }

    /**
     * يصيّر القالبَ المطلوب — أو الافتراضيَّ حين لا يُطلب بعينه.
     *
     * والناتجُ **جزءٌ** لا صفحة: يُغلَّف في شاشة الطباعة أو يُمرَّر لمولّد
     * الـPDF كما هو.
     *
     * @param  int|string|null  $template  معرّفُ قالبٍ بعينه، أو `null` للافتراضي
     */
    protected function renderPosTemplate(string $documentType, $documentId, $template = null): ?string
    {
        $model = is_numeric($template)
            ? DocumentTemplate::where('id', (int) $template)
                ->where('document_type', $documentType)
                ->first()
            : $this->defaultPosTemplate($documentType);

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData($documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            /*
                تعذُّرُ التصيير لا يمنع الطباعة.

                الكاشيرُ أمامه عميلٌ ينتظر إيصاله — فيُكتب الخطأُ في السجلّ
                ويعود القالبُ المدمج. وقالبٌ معطوبٌ يُكتشف من السجلّ لا من
                طابورِ الصندوق.
            */
            OperationLog::failure(
                $e,
                'Pos.Print',
                'render_template',
                is_numeric($documentId) ? (int) $documentId : null
            );

            return null;
        }
    }
}
