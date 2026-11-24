<?php

namespace Modules\Sales\Http\Controllers;

use App\Models\Quote;
use App\Models\SalesInvoiceSetting;

/**
 * ══════════════════════════════════════════════════════════════════════
 * أوامر البيع
 * ══════════════════════════════════════════════════════════════════════
 *
 * المستند الذي بين عرض السعر والفاتورة: العميل قَبِل، فصار الاتفاق
 * التزاماً — ولم تُصدَر الفاتورة بعد. نظيرُه في المشتريات «أمر الشراء»
 * الذي يُحوَّل إلى فاتورة شراء.
 *
 * ── ما كان ──
 *
 * مفتاحان في «إعدادات الفواتير» — «تفعيل أوامر البيع» و«إعطاء أوامر
 * البيع حالات يدوية» — يُحفظان في القاعدة ولا يقرؤهما شيء. لا نموذجَ
 * `SalesOrder` في المشروع، ولا متحكّم، ولا مسار، ولا شاشة. وشرحُ
 * المفتاحين كان يقول ذلك صراحةً: «شاشةٌ لم تُبنَ بعد».
 *
 * ── لماذا وراثةٌ لا صنفٌ جديد ──
 *
 * أمر البيع وعرض السعر مستندٌ واحدٌ في بنيته: عميلٌ وبنودٌ وأسعارٌ وخصمٌ
 * وضريبةٌ وشحنٌ وإجمالٌ وحالةٌ ثمّ تحويلٌ إلى فاتورة. والتحويل وحده —
 * `QuoteController::convertToInvoice()` — سبعمئة سطرٍ فيها إنشاءُ
 * الفاتورة وبنودِها، وفحصُ حدّ الائتمان، وخصمُ المخزون بإذن صرف، وقيدُ
 * اليومية بطرفَي العميل والمبيعات والضريبة والخصم، وقيدُ تكلفة
 * المبيعات. نسخُ ذلك يعني نسختين تفترقان عند أوّل تصحيح، وطريقتين
 * لإصدار فاتورةٍ في نظامٍ واحد.
 *
 * فالوارث هنا لا يحمل منطقاً: يبدّل نوعَ الصفّ في `quotes`، وقسمَ
 * الترقيم، وبادئةَ المسارات، وبادئةَ الترجمة، والصلاحيات. والباقي —
 * الفهرسُ والإنشاءُ والتعديلُ والطباعةُ والتحويل — موروثٌ كما هو.
 *
 * @see \Modules\Sales\Http\Controllers\QuoteController الأصل
 * @see \App\Models\Quote::TYPE_SALES_ORDER
 */
class SalesOrderController extends QuoteController
{
    /** نوع الصفّ في `quotes` */
    public const DOCUMENT_KIND = Quote::TYPE_SALES_ORDER;

    /** قسمُ الترقيم المستقلّ — بادئة `SOR` في «الترقيم المتسلسل» */
    public const NUMBER_SECTION = 'sales-order';

    /** نوع المستند في «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'sales_order';

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'sales_order';

    public function __construct()
    {
        $this->middleware('permission:sales.sales_orders.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.sales_orders.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.sales_orders.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.sales_orders.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.sales_orders.print', ['only' => ['print', 'downloadPdf']]);
        $this->middleware('permission:sales.sales_orders.send_link', ['only' => ['sendQuoteLink']]);
        $this->middleware('permission:sales.sales_orders.convert', ['only' => ['convertToInvoice']]);
    }

    protected function routePrefix(): string
    {
        return 'sales_orders';
    }

    protected function langPrefix(): ?string
    {
        return 'sales_order';
    }

    /**
     * «إعطاء أوامر البيع حالات يدوية» — مفتاحُه غير مفتاح عروض الأسعار.
     */
    protected function manualStatusEnabled(): bool
    {
        return (bool) SalesInvoiceSetting::current()->manual_sales_order_status;
    }
}
