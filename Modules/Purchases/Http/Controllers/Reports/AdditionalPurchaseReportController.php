<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * بقايا تقارير المشتريات القديمة — لم يبقَ منها إلا مسارٌ يُحوِّل.
 *
 * كان هنا تقريران: «أعمار الديون» و«المنتجات المشتراة». الأوّل انتقل
 * كاملاً (الشاشةُ ومغذّيها) إلى {@see SupplierReportController::debtAging}،
 * والثاني صار «المشتريات حسب المنتج» في {@see ProductReportController::index}
 * بنفس الفلاتر والنطاق. فحُذف ما لم يعد يصله مسار، وبقي مسارُ المنتجات
 * القديم يُحوِّل حتى لا تنكسر الروابطُ المحفوظة.
 */
class AdditionalPurchaseReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:purchases.reports.by_product', ['only' => ['productPurchases']]);
    }

    /**
     * تقريرُ «المنتجات المشتراة» القديم — يُحوِّل إلى نظيره الحديث حاملاً
     * معه معاملاتِ الاستعلام كما جاءت.
     */
    public function productPurchases(Request $request)
    {
        return redirect()->route('ReportsPurchases.byProduct', $request->query());
    }
}
