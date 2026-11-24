<?php

namespace Modules\Purchases\Http\Controllers\Concerns;

use App\Support\DocumentPrintPage;
use App\Support\PrintSettings;
use Illuminate\Http\Response;

/**
 * «تنزيل PDF» لمستندٍ له قالبٌ مصمَّم في «قوالب المستندات».
 *
 * **لماذا صفحةُ طباعة لا ملفّ PDF؟** لا يملك النظام محرّك HTML→PDF حقيقياً:
 * Dompdf لا يشكّل العربية (تخرج الحروف مقلوبةً ومفكّكة)، وTCPDF لا يعرف
 * flexbox ولا grid ولا متغيّرات CSS التي بُنيت عليها القوالب، فيبعثر التصميم
 * على أربع صفحات. كانت النتيجة أن «المطبوعات» المضبوطة في الإعدادات لا تظهر
 * في ملف PDF بصورتها. المتصفّح وحده يصيّر القالب كما صُمّم، وحوارُ الطباعة
 * فيه يحفظ PDF مباشرة — وهو المسلك نفسه في وحدة المبيعات
 * (`InvoicesController::generatePdf()`).
 *
 * تُبقي كلُّ شاشةٍ blade المدمج القديم احتياطاً لا يُسلك إلا حين لا يكون
 * للنوع قالبٌ واحد في الإعدادات.
 *
 * يفترض أن المتحكّم المستعمل يعرّف `DOCUMENT_TYPE` و`renderConfiguredTemplate()`.
 */
trait ServesDesignedTemplatePage
{
    /**
     * صفحة الطباعة بالقالب المطلوب — وإلا بالقالب الافتراضي للنوع — أو
     * `null` إن لم يكن للنوع أيّ قالب فيتولّى النداءُ البديلَ المدمج.
     *
     * @param  int|string  $documentId
     * @param  int|string|null  $template  معرّف القالب، أو 'default'
     */
    protected function configuredTemplatePage($documentId, $template, ?string $code = null): ?Response
    {
        $html = $this->renderConfiguredTemplate($documentId, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($documentId, $fallback->id) : null;
        }

        if ($html === null) {
            return null;
        }

        $title = trim(__('purchase_cycle.documents.'.self::DOCUMENT_TYPE).' '.($code ?? ''));

        // الرابط يُفتح في لسانٍ جديد، فزرّ «إغلاق» يكفي ولا حاجة لرابط رجوع
        return response(DocumentPrintPage::wrap($html, $title, null, true));
    }
}
