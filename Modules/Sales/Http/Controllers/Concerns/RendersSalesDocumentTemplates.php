<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعة مستندات المبيعات بقوالب «قوالب المبيعات» المصمَّمة في الإعدادات.
 *
 * **ما كان**
 *
 * `print()` و`printTax()` و`generatePdf()` تعرض قوالب Blade مثبّتة في
 * الوحدة. وفي المشروع شاشتان تصمّمان القوالب — «قوالب المستندات» في
 * الإعدادات و«تصاميم مستندات المبيعات» في هذه الوحدة نفسها
 * (`SalesDocumentDesignsController`) — ونظامُ تصيير كامل
 * (`DocumentTemplateRenderer`) تستعمله وحدة المشتريات في عشرة مواضع.
 * فكان المستخدم يصمّم قالب فاتورة المبيعات ويحفظه ويعيّنه افتراضياً، ثم
 * يطبع الفاتورة فيخرج قالب النظام المدمج ولا أثر لما صمّمه.
 *
 * **ما صار**
 *
 *   `salesTemplates()`  ← قوالب النوع لبناء قائمة «المطبوعات».
 *   `renderSalesTemplate()` ← يصيّر قالباً بعينه، أو `null` فيتولّى
 *                             النداءُ القالبَ المدمج كما كان.
 *
 * فالقوالب المدمجة تبقى احتياطاً لا يُنزَع: منشأةٌ لم تصمّم قالباً بعد
 * يجب أن تطبع فاتورتها.
 *
 * أنواع المستندات هي نفسها التي تعرضها شاشة «تصاميم مستندات المبيعات»،
 * انظر `SalesDocumentDesignsController::TYPES`.
 */
trait RendersSalesDocumentTemplates
{
    /**
     * قوالب نوع مستند مبيعات — الفعّالة، وفرعُ المستخدم أو العامّة.
     *
     * @return Collection<int, DocumentTemplate>
     */
    protected function salesTemplates(string $documentType): Collection
    {
        return PrintSettings::templatesFor($documentType);
    }

    /**
     * طلبٌ صريحٌ لقالب النظام **المدمج** بدل المصمَّم — `.../print/builtin`
     * أو `?builtin=1`.
     *
     * الافتراضيّ أن يُطبع القالب المصمَّم متى وُجد، وهو الصواب في شاشات
     * المستندات. لكنّ **مساحة الزيارة** تطبع للعميل وهو حاضر، على طابعة
     * المندوب الحرارية، فتطلب المطبوعة المدمجة بعينها مهما صُمِّم من قوالب.
     */
    protected function wantsBuiltinPrint($template): bool
    {
        return $template === 'builtin' || request()->boolean('builtin');
    }

    /** القالب الافتراضي للنوع — أو أول فعّال، أو null */
    protected function defaultSalesTemplate(string $documentType): ?DocumentTemplate
    {
        return PrintSettings::defaultTemplateFor($documentType);
    }

    /**
     * يصيّر المستند بقالبٍ من «قوالب المستندات»: المطلوبَ باسمه إن مُرِّر،
     * وإلّا **القالبَ الافتراضيَّ لنوعه**.
     *
     * **ما كان** — بلا `$template` في العنوان تعود `null` فوراً، فيطبع
     * النداءُ قالبَه المدمج. ونتيجتُه أنّ ما يُطبع فعلياً هو ملفُّ بلايد
     * المثبَّت في الوحدة دائماً: المستخدم يصمّم قالباً، ويعيّنه افتراضياً
     * لنوعه من شاشة «قوالب المستندات»، ثمّ يضغط «طباعة» فيخرج غيرُه.
     * والافتراضيُّ اختيارٌ لا معنى له إن لم يكن هو ما يُطبع.
     *
     * **والمدمجُ يبقى احتياطاً لا يُنزَع**، لكنّه صار احتياطاً حقيقياً: لا
     * يُبلَغ إلّا إذا لم يكن للنوع قالبٌ فعّالٌ إطلاقاً، أو سقط تصييرُه.
     *
     * والناتج **جزء** لا صفحة: يذهب إمّا إلى `DocumentPrintPage::wrap()`
     * لشاشة الطباعة، أو إلى مولّد PDF كما هو — ومولّد الـPDF لا يفهم
     * `@media print`، فصفحةٌ كاملة تعني شريط أزرارٍ مطبوعاً داخل الملف.
     *
     * ونوعُ **البيانات** قد يخالف نوع **القالب**: سندُ قبض العميل يُطبع
     * بقوالب `receipt_voucher` وبياناتُه من `client_payment` — لأن صفَّه في
     * `payments_process` لا في `receipts`، والجدولان يتقاسمان آلافَ
     * المعرّفات. انظر `DocumentTemplateDataService::getDocumentData()`.
     *
     * @param  int|string|null  $template  معرّف القالب، أو لا شيء فالافتراضيّ
     * @param  string|null  $dataType  مصدرُ البيانات حين يخالف نوعَ القالب
     */
    protected function renderSalesTemplate(string $documentType, $documentId, $template, ?string $dataType = null): ?string
    {
        $model = is_numeric($template)
            ? DocumentTemplate::where('id', (int) $template)
                ->where('document_type', $documentType)
                ->first()
            : $this->defaultSalesTemplate($documentType);

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData($dataType ?? $documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            // تعذّر تصيير القالب لا يمنع الطباعة: يُكتب في سجلّ الأخطاء
            // ويعود المدمج
            OperationLog::failure($e, 'SalesInvoices', 'render_template', is_numeric($documentId) ? (int) $documentId : null);

            return null;
        }
    }
}
