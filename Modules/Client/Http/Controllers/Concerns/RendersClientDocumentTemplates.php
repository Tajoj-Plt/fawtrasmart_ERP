<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعة مستندات العميل بقوالب «قوالب المستندات» المصمَّمة في الإعدادات.
 *
 * **ما كان**
 *
 * كشف حساب العميل وتقاريره تُطبع بقوالب Blade مثبَّتة في الوحدة، ونوعُ
 * المستند `client_statement` معرَّفٌ في `DocumentTemplateDataService`
 * ومعروضٌ في شاشة «قوالب المستندات» تُصمَّم قوالبُه وتُحفَظ — ولا سطر في
 * وحدة العملاء يقرؤها. فالمستخدم يصمّم كشف الحساب ويعيّنه افتراضياً، ثم
 * يطبع فيخرج القالبُ المدمج ولا أثر لما صمّمه.
 *
 * **ما صار**
 *
 *   `clientTemplates()`      ← قوالب النوع لبناء قائمة «المطبوعات».
 *   `renderClientTemplate()` ← يصيّر قالباً بعينه، أو `null` فيتولّى
 *                              النداءُ القالبَ المدمج كما كان.
 *
 * فالقوالب المدمجة تبقى احتياطاً لا يُنزَع: منشأةٌ لم تصمّم قالباً بعد يجب
 * أن تطبع كشف حساب عميلها.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\RendersSalesDocumentTemplates النسخة الأمّ
 * @see \App\Services\DocumentTemplateDataService أنواع المستندات المعرَّفة
 */
trait RendersClientDocumentTemplates
{
    /**
     * قوالب نوع مستند عميل — الفعّالة، وفرعُ المستخدم أو العامّة.
     *
     * @return Collection<int, DocumentTemplate>
     */
    protected function clientTemplates(string $documentType): Collection
    {
        return PrintSettings::templatesFor($documentType);
    }

    /** القالب الافتراضي للنوع — أو أول فعّال، أو `null` */
    protected function defaultClientTemplate(string $documentType): ?DocumentTemplate
    {
        return PrintSettings::defaultTemplateFor($documentType);
    }

    /**
     * يصيّر قالباً مصمَّماً في الإعدادات — أو `null` إن لم يكن `$template`
     * معرّفَ قالبٍ صالحٍ من هذا النوع.
     *
     * `null` هنا ليست خطأً بل تفويضاً: النداء يعرض قالبه المدمج.
     *
     * والناتج **جزء** لا صفحة: يذهب إمّا إلى `DocumentPrintPage::wrap()`
     * لشاشة الطباعة، أو إلى مولّد PDF كما هو — ومولّد الـPDF لا يفهم
     * `@media print`، فصفحةٌ كاملة تعني شريط أزرارٍ مطبوعاً داخل الملف.
     *
     * @param  int|string|null  $template  معرّف القالب، أو مفتاح قالب مدمج
     */
    protected function renderClientTemplate(string $documentType, $documentId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', $documentType)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData($documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            // تعذّر تصيير القالب لا يمنع الطباعة: يُكتب في سجلّ الأخطاء
            // ويعود المدمج
            OperationLog::failure($e, 'Clients', 'render_template', is_numeric($documentId) ? (int) $documentId : null);

            return null;
        }
    }
}
