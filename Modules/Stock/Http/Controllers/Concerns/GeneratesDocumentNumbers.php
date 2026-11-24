<?php

namespace Modules\Stock\Http\Controllers\Concerns;

/**
 * أرقام مستندات المخزون من إعدادات «الترقيم المتسلسل» لا من عدّ الصفوف،
 * ولا مما يرسله النموذج.
 *
 * **ما كان**
 *
 *     $record_count  = DB::table('warehouse_permits')->count();
 *     $serial_number = str_pad($record_count + 1, 6, '0', STR_PAD_LEFT);
 *
 * سبعة عشر موضعاً بهذه الصيغة. وفيها ثلاثة عيوب: *إعادة الاستخدام* (حذفُ
 * مستند يُنقص العدّ فيتكرّر الرقم)، و*التصادم* (مستخدمان يريان الرقم نفسه)،
 * و*تجاهل الإعدادات* (البادئة والطول والتصفير الدوري لا يُقرأ منها شيء).
 *
 * **ما صار**
 *
 *   شاشة الإنشاء ← `previewDocumentNumber()` معاينةٌ للعرض فقط، بلا استهلاك.
 *   الحفظ        ← `issueDocumentNumber()` تستهلك رقماً تحت قفل.
 *   التعديل      ← `keepDocumentNumber()` تُبقي رقم المستند كما صدر.
 *
 * **ولا يُقبل رقمٌ من الطلب.** الرقم حقلٌ تلقائي: مرجعٌ يحمله القيد
 * المحاسبي وسجلّ العمليات والتقارير وملفات PDF المطبوعة. فلو سُمح بتحريره
 * انفصل المستند عن قيده وعن أثره في السجلّ، وأمكن تجاوز التسلسل أو تكراره
 * أو انتحال رقم مستندٍ آخر. لذلك تتجاهل الدوالُّ هنا ما يصل من النموذج
 * تجاهلاً تامّاً، والشاشات تعرض الحقل للقراءة فقط — حارسان لا واحد.
 *
 * أقسام المخزون معرَّفة سلفاً في `DocumentSections`: `warehouse-add`،
 * `warehouse-dispose`، `transfer-request`، `inventory-report`، `products`،
 * `manufacturing-orders`، `production-routes`، `production-material-lists`،
 * `workstations`.
 */
trait GeneratesDocumentNumbers
{
    /**
     * الرقم التالي للعرض في نموذج الإنشاء — بلا استهلاك.
     *
     * لا يُستهلك هنا عمداً: المستخدم قد يفتح الشاشة ويتركها، فاستهلاك
     * الرقم عند العرض يُحدث ثغرات في التسلسل بلا مستند يقابلها. وهو
     * للعرض وحده — الرقم المعتمد ما تُصدره `issueDocumentNumber()` عند
     * الحفظ، وقد يختلف عن المعاينة إن سبق غيرُك إليه.
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
     * تعديلُ المستند لا يعيد ترقيمه: الرقم مرجعٌ خارجي يحمله القيد وسجلّ
     * العمليات والمطبوعات. ولا يُصدَر تالٍ إلّا لسجلٍّ قديمٍ وصل بلا رقم.
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
