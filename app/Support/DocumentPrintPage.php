<?php

namespace App\Support;

use Illuminate\Support\Facades\View;

/**
 * يلفّ مطبوعة مستندٍ في **صفحةٍ كاملة** صالحةٍ للعرض والطباعة.
 *
 * **لماذا؟** `DocumentTemplateRenderer::render()` تُعيد **جزءاً** يبدأ بوسم
 * `<style>` — صالحاً للدمج داخل قالب، لا للإرجاع من متحكّم. وكانت أحد عشر
 * شاشة طباعة تعيده كما هو، فيعرضه المتصفّح في **وضع التوافق**
 * (`document.compatMode === 'BackCompat'`)، وفيه:
 *
 *   • الجداول لا ترث حجم الخط من غلافها — ونظام تصميم القوالب مبنيّ على
 *     `em` منسوبةً إلى الغلاف، فينهار تدرّج المقاسات في كل جدول.
 *   • جهة الصفحة `ltr` مهما كانت لغة الواجهة.
 *   • لا خلفية ولا توسيط: الورقة ملتصقة بحافة النافذة بهامش 8px.
 *
 * **ولماذا هنا لا في المُصيِّر؟** لأن ناتج المُصيِّر يذهب إلى مسارين: شاشة
 * طباعةٍ في المتصفّح، و**مولّد PDF** (TCPDF و Dompdf) في اثني عشر موضعاً.
 * ومولّد الـPDF لا يفهم `@media print`، فلو خرج من المُصيِّر صفحةً كاملة
 * لَطُبع شريط الأزرار داخل ملفّ الـPDF. فاللفّ عند الاستجابة وحدها.
 *
 * وقاعدة `@page` (مقاس الورق وهوامشه) تسافر داخل الجزء نفسه لأن المُصيِّر
 * وحده يعرف مقاس ورق القالب — فلا تحتاجها هذه الطبقة.
 */
class DocumentPrintPage
{
    /**
     * @param  string       $html      ناتج `DocumentTemplateRenderer::render()`
     * @param  string|null  $title     عنوان النافذة — اسم النظام إن أُهمل
     * @param  string|null  $backUrl   رابط الرجوع، أو `null` فيُعرض «إغلاق»
     * @param  bool         $autoPrint هل تُفتح نافذة الطباعة تلقائياً
     */
    public static function wrap(
        string $html,
        ?string $title = null,
        ?string $backUrl = null,
        bool $autoPrint = false
    ): string {
        return View::make('documents.print-page', [
            'html' => $html,
            'title' => $title ?: (string) config('app.name', 'Document'),
            'backUrl' => $backUrl,
            'autoPrint' => $autoPrint,
        ])->render();
    }
}
