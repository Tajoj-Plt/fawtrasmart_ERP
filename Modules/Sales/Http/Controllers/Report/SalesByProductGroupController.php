<?php

namespace Modules\Sales\Http\Controllers\Report;

use Illuminate\Http\Request;

/**
 * «المبيعات حسب المجموعة / التصنيف / الوحدة» — لم يعد تقريراً مستقلاً.
 *
 * صار خيارَ «تجميع» في «مبيعات الأصناف» (`salesReports.byProductNew`)،
 * فالمسارُ باقٍ للروابط المحفوظة ويُحوِّل إليه بفلاتره كما هي، والتجميعُ
 * الافتراضيُّ «التصنيف» كما كان.
 */
class SalesByProductGroupController extends SalesByItemController
{
    public function __construct()
    {
        $this->middleware('permission:sales.reports.by_product_group');
    }

    public function index(Request $request)
    {
        return redirect()->route('salesReports.byProductNew', $request->query() + ['group_by' => 'category']);
    }

    public function ajax(Request $request)
    {
        return $this->index($request);
    }
}
