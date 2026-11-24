<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعة طلبات المتجر الإلكتروني بالقوالب المصمَّمة في «قوالب المستندات».
 *
 * نسخة مطابقة لـ `Modules\Sales\Http\Controllers\Concerns\RendersSalesDocumentTemplates`
 * لنوع المستند `online_order` — كانت `OnlineOrderController::print()`/
 * `printInvoice()` تعرضان Blade مثبَّتاً بلا أي تكامل مع نظام القوالب،
 * فتصميمٌ يُنشئه المستخدم في الإعدادات ويعيّنه افتراضياً لا يظهر أبداً.
 *
 * القالب المدمج يبقى احتياطاً لا يُنزَع: طلبٌ بلا قالب مخصَّص يجب أن يُطبع.
 */
trait RendersOnlineStoreDocumentTemplates
{
    /**
     * قوالب نوع مستند — الفعّالة، وفرعُ المستخدم أو العامّة.
     *
     * @return Collection<int, DocumentTemplate>
     */
    protected function onlineStoreTemplates(string $documentType): Collection
    {
        return PrintSettings::templatesFor($documentType);
    }

    /** القالب الافتراضي للنوع — أو أول فعّال، أو null */
    protected function defaultOnlineStoreTemplate(string $documentType): ?DocumentTemplate
    {
        return PrintSettings::defaultTemplateFor($documentType);
    }

    /**
     * يصيّر قالباً مصمَّماً في الإعدادات — أو `null` إن لم يكن `$template`
     * معرّفَ قالبٍ صالحٍ من هذا النوع، فيتولّى النداءُ القالبَ المدمج.
     *
     * @param  int|string|null  $template  معرّف القالب، أو لا شيء
     */
    protected function renderOnlineStoreTemplate(string $documentType, $documentId, $template): ?string
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
            // تعذّر تصيير القالب لا يمنع الطباعة: يُكتب في سجلّ الأخطاء ويعود المدمج
            OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'render_template', is_numeric($documentId) ? (int) $documentId : null);

            return null;
        }
    }
}
