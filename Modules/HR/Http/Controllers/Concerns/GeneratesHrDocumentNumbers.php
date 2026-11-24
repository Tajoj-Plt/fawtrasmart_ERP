<?php

namespace Modules\HR\Http\Controllers\Concerns;

/**
 * أرقام مستندات الموارد البشرية من إعدادات «الترقيم المتسلسل»
 * لا مما يرسله النموذج.
 *
 * شاشة الإنشاء ← `previewDocumentNumber()` معاينة للعرض فقط، بلا استهلاك.
 * الحفظ        ← `issueDocumentNumber()` تستهلك رقماً تحت قفل.
 * التعديل      ← `keepDocumentNumber()` تُبقي رقم المستند كما صدر.
 *
 * ولا يُقبل رقمٌ من الطلب. الحقل للقراءة فقط — بلا `name` — فلا يُرسَل.
 *
 * أقسام الرواتب معرَّفة في `DocumentSections`: `salary-slip`،
 * `payroll-run`، `advance`، `deduction`. الموظفون والعقود لهم
 * مسارات أقدم تستدعي `peek`/`next` مباشرة.
 */
trait GeneratesHrDocumentNumbers
{
    /**
     * الرقم التالي للعرض في نموذج الإنشاء — بلا استهلاك.
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
     * وتتخطّى الأرقام المحجوزة فعلاً، فلا تصادمَ ولا تكرار.
     */
    protected function issueDocumentNumber(string $section): string
    {
        return next_document_number($section);
    }

    /**
     * رقم المستند عند التعديل — كما صدر، بلا مساس.
     *
     * ولا يُصدَر تالٍ إلّا لسجلٍّ قديمٍ وصل بلا رقم.
     */
    protected function keepDocumentNumber(?string $current, string $section): string
    {
        $current = trim((string) $current);

        return $current !== '' ? $current : $this->issueDocumentNumber($section);
    }
}
