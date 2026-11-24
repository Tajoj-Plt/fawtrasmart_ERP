<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Support\DocumentSections;
use Illuminate\Support\Facades\DB;

/**
 * أرقام مستندات المبيعات من إعدادات «الترقيم المتسلسل» لا من عدّ الصفوف.
 *
 * **ما كان**
 *
 *     $lastInvoice = Invoice::latest()->first();
 *     $nextId      = $lastInvoice ? $lastInvoice->id + 1 : 1;
 *     return str_pad($nextId, 6, '0', STR_PAD_LEFT);
 *
 * وفي `store()` صيغةٌ ثالثة بطولٍ خمسة لا ستّة. فالرقم المعروض في الشاشة
 * غير الرقم المحفوظ. وفيها العيوب المعروفة:
 *
 *   • *تجاهل الإعدادات*: قسم `invoice` معرَّف في «الترقيم المتسلسل»
 *     ببادئة `INV` وطولٍ وتصفيرٍ دوري وقالب — ولا شيء منها كان يُقرأ.
 *   • *التصادم*: مستخدمان يفتحان الشاشة معاً فيريان الرقم نفسه.
 *   • *إعادة الاستخدام*: `id + 1` بعد حذف صفٍّ يسلّم رقماً محجوزاً.
 *
 * **ما صار**
 *
 *   شاشة الإنشاء ← `previewDocumentNumber()` معاينةٌ بلا استهلاك.
 *   الحفظ        ← `resolveDocumentNumber()` تستهلك رقماً تحت قفل.
 *
 * `DocumentNumberService::next()` تعمل داخل معاملة مع `lockForUpdate`
 * وتتخطّى الأرقام المحجوزة فعلاً، فينحلّ العيبان الأخيران من أصلهما.
 *
 * أقسام المبيعات معرَّفة سلفاً في `DocumentSections`: `invoice`،
 * `return-invoice`، `quotation`، `credit-note`، `invoice-payment`.
 *
 * @see \Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers النسخة الأمّ
 */
trait GeneratesDocumentNumbers
{
    /**
     * الرقم التالي للعرض في نموذج الإنشاء — بلا استهلاك.
     *
     * لا يُستهلك هنا عمداً: المستخدم قد يفتح الشاشة ويتركها، فاستهلاك
     * الرقم عند العرض يُحدث ثغرات في التسلسل بلا مستند يقابلها.
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
     * الرقم المعتمد عند الحفظ — ما كتبه المستخدم، وإلا التالي من الإعدادات.
     *
     * الرقم المحجوز لا يُرفض بل يُستبدل بالتالي الشاغر: التضارب غالباً من
     * رقمٍ ولّدته الشاشة نفسها لمستخدمَين معاً، فلا يصحّ أن تضيع فاتورةٌ
     * كاملة بسببه.
     *
     * @param  string|null  $submitted  ما أرسله النموذج
     * @param  string  $section  مفتاح القسم في `DocumentSections`
     */
    protected function resolveDocumentNumber(?string $submitted, string $section): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted !== '' && ! $this->documentNumberTaken($submitted, $section)) {
            return $submitted;
        }

        return next_document_number($section);
    }

    /**
     * أرقمٌ محجوزٌ في جدول هذا القسم؟
     *
     * الأقسام التي لا يُعرف لها جدول أو عمود تُعامَل كأنّ الرقم شاغر —
     * وهو سلوك `DocumentNumberService` نفسه، فلا نخترع فحصاً لا يقابله.
     */
    protected function documentNumberTaken(string $number, string $section): bool
    {
        $table = DocumentSections::table($section);
        $column = DocumentSections::column($section);

        if (! $table || ! $column) {
            return false;
        }

        try {
            return DB::table($table)->where($column, $number)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
