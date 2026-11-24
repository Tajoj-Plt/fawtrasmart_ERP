<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

/**
 * أرقام مستندات المتجر الإلكتروني من إعدادات «الترقيم المتسلسل» لا من عدّ
 * الصفوف ولا مما يرسله النموذج.
 *
 * نسخة مطابقة لـ `Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers`
 * (نفس التوقيع والسلوك) حتى لا يُنشأ اقترانٌ بين الموديولين، بينما تشترك
 * الأقسام المُستهلَكة (`warehouse-add`, `warehouse-dispose`, `transfer-request`,
 * `purchase-invoice`, `online-order`, ...) في نفس السجلّ المركزي
 * `App\Support\DocumentSections`.
 */
trait GeneratesDocumentNumbers
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
}
