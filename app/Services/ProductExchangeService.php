<?php

namespace App\Services;

use App\Models\ProductExchange;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ **صنفٌ ميّت ومعطوب — لا تُنادَ توابعُه قبل إعادة كتابتها.**
 *
 * لا مرجع واحد لهذا الصنف في المشروع كلّه: لا متحكّم يحقنه، ولا قالب
 * يستدعيه، ولا مزوّد يسجّله. وهذا خيرٌ له، لأنّ **كلّ** تابعٍ فيه يستعلم
 * عن أعمدةٍ لا وجود لها في `product_exchanges`:
 *
 *     الأعمدة المستعملة هنا: product_id، quantity_returned،
 *                            quantity_provided، customer_id، reason
 *
 *     الأعمدة الفعلية:      id، code، client_id، employee_id،
 *                            storehouse_id، return_storehouse_id،
 *                            created_by، exchange_date، notes، image،
 *                            total_value، status، outgoing_permit_id،
 *                            incoming_permit_id، created_at، updated_at
 *
 * فالصنف مكتوبٌ على تصميمٍ سابق كان يضع بندَ التبديل في صفّ العملية
 * نفسه؛ ثمّ انفصلت البنود إلى `product_exchange_items` وصار العميل
 * `client_id` — ولم يُحدَّث هذا الملفّ. أوّلُ نداءٍ لأيّ تابعٍ منه يرمي
 * `SQLSTATE[42S22]: Column not found`.
 *
 * وكذلك `ProductExchange::getReasons()` في الموديل: أسبابٌ ثابتة في الكود
 * (`damaged`, `missing_perfume`…) وقد صارت جدولاً — `exchange_reasons` —
 * تقرؤه الشاشات عبر `ExchangeReason::getForDropdown()`.
 *
 * الإحصاءات الحيّة مبنيّة في `ProductExchangeController::exchangeStats()`
 * و`dashboard()` على الأعمدة الصحيحة.
 *
 * تُرك الملفّ ولم يُحذف لأنّ الحذف قرارُ صاحب المشروع؛ وهذا التنبيه يمنع
 * أن يبنيَ أحدٌ عليه شاشةً تنكسر عند أوّل فتح.
 *
 * @deprecated لا تستعمله — أعد كتابته على المخطّط الحالي أو احذفه.
 */
class ProductExchangeService
{
    /**
     * حساب إجمالي الكميات المستبدلة لمنتج معين
     */
    public function getTotalExchangedQuantities($productId)
    {
        return ProductExchange::where('product_id', $productId)
            ->selectRaw('
                SUM(quantity_returned) as total_returned,
                SUM(quantity_provided) as total_provided,
                COUNT(*) as exchange_count
            ')
            ->first();
    }

    /**
     * الحصول على أسباب التبديل الأكثر شيوعاً
     */
    public function getCommonReasons($productId = null, $limit = 5)
    {
        $query = ProductExchange::query()
            ->selectRaw('reason, COUNT(*) as count, SUM(quantity_returned) as total_quantity')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit($limit);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->get()->mapWithKeys(function ($item) {
            return [
                $item->reason => [
                    'label' => ProductExchange::getReasons()[$item->reason] ?? $item->reason,
                    'count' => $item->count,
                    'total_quantity' => $item->total_quantity
                ]
            ];
        });
    }

    /**
     * الحصول على العملاء الأكثر تبديلاً
     */
    public function getTopCustomersByExchanges($limit = 10)
    {
        return ProductExchange::query()
            ->selectRaw('
                customer_id,
                COUNT(*) as exchange_count,
                SUM(quantity_returned) as total_quantity_returned
            ')
            ->groupBy('customer_id')
            ->orderByDesc('exchange_count')
            ->limit($limit)
            ->with('customer')
            ->get();
    }

    /**
     * الحصول على الكمية الصحيحة والمعيبة لمنتج في مدة زمنية
     */
    public function getExchangeStatisticsByPeriod($productId, $startDate, $endDate)
    {
        $exchanges = ProductExchange::where('product_id', $productId)
            ->whereBetween('exchange_date', [$startDate, $endDate])
            ->selectRaw('
                DATE(exchange_date) as date,
                COUNT(*) as exchange_count,
                SUM(quantity_returned) as quantity_returned,
                SUM(quantity_provided) as quantity_provided
            ')
            ->groupBy(DB::raw('DATE(exchange_date)'))
            ->get();

        return $exchanges;
    }

    /**
     * تقرير الأداء - أي موظف يقوم بأكثر عمليات تبديل
     */
    public function getEmployeePerformance($startDate = null, $endDate = null)
    {
        $query = ProductExchange::query()
            ->selectRaw('
                employee_id,
                COUNT(*) as exchange_count,
                SUM(quantity_returned) as total_returned,
                SUM(quantity_provided) as total_provided
            ')
            ->groupBy('employee_id')
            ->orderByDesc('exchange_count')
            ->with('employee');

        if ($startDate && $endDate) {
            $query->whereBetween('exchange_date', [$startDate, $endDate]);
        }

        return $query->get();
    }

    /**
     * الحصول على منتجات لها معدل تبديل عالي
     */
    public function getHighExchangeRateProducts($threshold = 5)
    {
        return ProductExchange::query()
            ->selectRaw('
                product_id,
                COUNT(*) as exchange_count,
                SUM(quantity_returned) as total_returned
            ')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('exchange_count')
            ->with('product')
            ->get();
    }

    /**
     * تقرير الجودة - أي سبب تبديل هو الأكثر تكراراً
     */
    public function getQualityReport($productId = null)
    {
        $query = ProductExchange::query()
            ->selectRaw('
                reason,
                COUNT(*) as count,
                SUM(quantity_returned) as quantity,
                ROUND(COUNT(*) * 100 / (SELECT COUNT(*) FROM product_exchanges), 2) as percentage
            ')
            ->groupBy('reason')
            ->orderByDesc('count');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->get()->map(function ($item) {
            $item->reason_label = ProductExchange::getReasons()[$item->reason] ?? $item->reason;
            return $item;
        });
    }

    /**
     * إحصائيات سريعة
     */
    public function getQuickStats()
    {
        return [
            'total_exchanges' => ProductExchange::count(),
            'total_returned_qty' => ProductExchange::sum('quantity_returned'),
            'total_provided_qty' => ProductExchange::sum('quantity_provided'),
            'affected_customers' => ProductExchange::distinct('customer_id')->count('customer_id'),
            'affected_products' => ProductExchange::distinct('product_id')->count('product_id'),
            'today_exchanges' => ProductExchange::whereDate('exchange_date', today())->count(),
            'this_month_exchanges' => ProductExchange::whereMonth('exchange_date', now()->month)
                ->whereYear('exchange_date', now()->year)
                ->count(),
        ];
    }
}
