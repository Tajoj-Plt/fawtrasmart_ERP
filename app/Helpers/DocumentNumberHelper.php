<?php

use App\Services\DocumentNumberService;
use App\Support\DocumentSections;

/**
 * دوال مختصرة للترقيم المتسلسل — تُستدعى من أي مكان في النظام
 * (كنترولر، موديل، Observer، Job، أو قالب Blade) بلا حقن يدوي للخدمة.
 */

if (! function_exists('document_numbers')) {
    /** نسخة الخدمة من الحاوية — واحدة لكل طلب */
    function document_numbers(): DocumentNumberService
    {
        return app(DocumentNumberService::class);
    }
}

if (! function_exists('next_document_number')) {
    /**
     * الرقم التالي مع استهلاكه — ينادى مرة واحدة عند حفظ المستند فعلياً.
     *
     * مثال:
     *     $invoice->invoice_number = next_document_number('invoice');
     *
     * @param  string  $section  مفتاح القسم، انظر App\Support\DocumentSections
     */
    function next_document_number(string $section): string
    {
        return document_numbers()->next($section);
    }
}

if (! function_exists('peek_document_number')) {
    /**
     * معاينة الرقم التالي بلا استهلاك — للعرض في نموذج الإنشاء قبل الحفظ.
     *
     * مثال:
     *     <input value="{{ peek_document_number('invoice') }}" readonly>
     */
    function peek_document_number(string $section): string
    {
        return document_numbers()->peek($section);
    }
}

if (! function_exists('current_document_number')) {
    /** آخر رقم استُهلك فعلاً في هذا القسم */
    function current_document_number(string $section): string
    {
        return document_numbers()->current($section);
    }
}

if (! function_exists('document_section_label')) {
    /** التسمية العربية للقسم */
    function document_section_label(?string $section): string
    {
        return DocumentSections::label($section);
    }
}
