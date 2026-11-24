<?php

namespace Modules\Purchases\Http\Controllers\Reports;
use App\Http\Controllers\Controller;

/**
 * PurchasesReportController - المتحكم الرئيسي للتقارير
 *
 * تم تقسيم التقارير إلى عدة Controllers منفصلة:
 *
 * 1. EmployeePurchasesReportController - تقارير المشتريات حسب الموظفين
 * 2. SupplierReportController - تقارير وإدارة الموردين
 * 3. ProductReportController - تقارير المنتجات
 * 4. PaymentReportController - تقارير المدفوعات
 * 5. AdditionalPurchaseReportController - تقارير إضافية
 */
class PurchasesReportController extends Controller
{
    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً رغم أن مجموعة
     * `reports` في كتالوج `PurchasesPermissions` تشمل تقارير المشتريات
     * السبعة كلّها صراحةً.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.reports.view');
    }

    public function index()
    {
        return view('purchases::purchases.report.index');
    }
}
