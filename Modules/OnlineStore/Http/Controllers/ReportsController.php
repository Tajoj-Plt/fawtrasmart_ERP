<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\OrderStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Store\OnlineOrder;
use App\Models\Store\OnlineOrderItem;
use App\Models\Store\ProductQuestion;
use App\Models\Store\ProductReview;
use App\Models\Store\ShippingMethod;
use App\Models\Store\StoreCoupon;
use App\Models\Store\StoreCouponUsage;
use App\Support\AccountingPeriodFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\PostsStoreOrderAccounting;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreOrders;

/**
 * كنترولر التقارير الشاملة للمتجر الإلكتروني
 * Online Store Reports Controller
 */
class ReportsController extends Controller
{
    use PostsStoreOrderAccounting, ResolvesStoreDefaults, SanitizesFilters, ScopesStoreOrders;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.reports.view', ['only' => ['index']]);
        $this->middleware('permission.role:online_store.reports.sales', ['only' => ['sales']]);
        $this->middleware('permission.role:online_store.reports.orders', ['only' => ['orders']]);
        $this->middleware('permission.role:online_store.reports.products', ['only' => ['products']]);
        $this->middleware('permission.role:online_store.reports.clients', ['only' => ['clients']]);
        $this->middleware('permission.role:online_store.reports.coupons', ['only' => ['coupons']]);
        $this->middleware('permission.role:online_store.reports.shipping', ['only' => ['shipping']]);
        $this->middleware('permission.role:online_store.reports.reviews', ['only' => ['reviews']]);

        // فلاتر الرابط نصوصٌ لا مصفوفات — وإلا سقطت القوالب التي تطبعها
        $this->middleware(function ($request, $next) {
            $this->dropNonScalarFilters($request, ['start_date', 'end_date', 'period_id', 'period', 'status', 'search', 'page']);

            return $next($request);
        });
    }

    /**
     * الحالات التي تعتبر طلبات ناجحة/مكتملة للتقارير المالية
     */
    private function getSuccessfulStatuses()
    {
        return [
            OrderStatus::DELIVERED->value,
            OrderStatus::SHIPPED->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
        ];
    }

    /**
     * صافي مبيعات الطلب كما في الدفاتر: الملغي صفر (عُكس قيدُه كاملاً)،
     * وغيرُه إجماليُّه ناقصاً ما رُدّ منه. كل «إيراد» في التقارير منه.
     *
     * كانت التقارير تجمع `total_amount` لكل الطلبات — الملغي والمسترد
     * بكامل قيمتهما — أو للطلبات «المشحونة فما بعد» وحدها، وكلاهما لا يوافق
     * القيود: الإيراد يُقيَّد لحظةَ إنشاء الطلب ويُعكَس عند إلغائه أو ردّه.
     */
    private const NET = "(CASE WHEN status = 'cancelled' THEN 0 ELSE total_amount - COALESCE(refund_amount, 0) END)";

    /** عمودٌ من مبالغ الطلب، صفراً للملغي. */
    private static function live(string $column): string
    {
        return "(CASE WHEN status = 'cancelled' THEN 0 ELSE COALESCE({$column}, 0) END)";
    }

    /**
     * نافذةُ التقرير: مدًى مكتوبٌ باليد يغلب الفترةَ المحاسبية المختارة؛
     * وإلا حدودُ الفترة/السنة المختارة (الافتراضي: السنة المالية الجارية)؛
     * وإلا من أول الشهر إلى اليوم. تاريخٌ مشوَّه يُهمَل ولا يُسقط الصفحة
     * (كان `Carbon::parse()` على نصٍّ عشوائي يرمي 500).
     *
     * @return array{0: Carbon, 1: Carbon, 2: array}
     */
    private function reportWindow(Request $request): array
    {
        $period = AccountingPeriodFilter::resolve($request);

        $parse = function ($value): ?Carbon {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        };

        $from = $parse($request->query('start_date'));
        $to = $parse($request->query('end_date'));

        if (! $from && ! $to) {
            $from = $parse($period['from'] ?? null);
            $to = $parse($period['to'] ?? null);

            // «كل الفترات» (أو لا تقويم أصلاً): التاريخ كلُّه، لا الشهر الجاري
            if (! $from && (! empty($period['all']) || ! empty($period['no_calendar']))) {
                $from = Carbon::create(2000, 1, 1);
            }
        }

        $from = ($from ?? Carbon::now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? Carbon::now())->copy()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // الجزء المشترك في كل صفحات التقارير يقرأها من هنا
        view()->share('accountingPeriod', $period);

        return [$from, $to, $period];
    }

    /**
     * مطابقةُ صافي مبيعات مجموعةِ طلباتٍ مع قيودها (فواتيرها ومرتجعاتها).
     *
     * من القيود: الإيراد الصافي (بعد مردوداته) + الضريبة + مستحق شركات الشحن
     * − الخصم المسموح به = حركةُ ذمّة العميل = صافي التقرير. فرقٌ غير الصفر
     * يعني طلباً بلا قيد أو قيداً عُدِّل يدوياً — يُعرض ليُراجَع.
     *
     * @param  Builder  $orders  استعلام الطلبات بنفس فلتر التقرير
     * @return array<string, float|int>
     */
    private function journalReconciliation($orders): array
    {
        $reportNet = round((float) (clone $orders)->sum(DB::raw(self::NET)), 2);
        $invoiceIds = (clone $orders)->whereNotNull('invoice_id')->pluck('invoice_id');

        $returnIds = $invoiceIds->isEmpty() ? collect() : Invoice::where('type', 'returned')
            ->whereIn('reference_number', $invoiceIds->map(fn ($id) => (string) $id)->all())
            ->pluck('id');

        $vatId = $this->storeAccount('vat_payable_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE)?->id;
        // ضريبة الشحن حسابٌ مستقلّ في إعدادات المتجر (افتراضيُّه حساب الضريبة نفسه) — تدخل ضمن «الضريبة» لا «مستحق الشحن»
        $shippingVatId = $this->storeAccount('shipping_vat_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE)?->id;
        $vatIds = array_values(array_unique(array_filter([$vatId, $shippingVatId])));

        $lines = $invoiceIds->isEmpty() ? collect() : DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->whereIn('e.invoice_id', $invoiceIds->merge($returnIds)->all())
            ->whereIn('e.reference_type', [ReferenceType::SALES_INVOICE->value, ReferenceType::SALES_RETURN->value])
            ->where('e.reference_number', 'not like', '%-COST')
            ->selectRaw('a.account_type, d.account_id, SUM(d.debit) as debit, SUM(d.credit) as credit')
            ->groupBy('a.account_type', 'd.account_id')
            ->get();

        $sum = fn ($rows, bool $creditSide) => round((float) $rows->sum(
            fn ($l) => $creditSide ? $l->credit - $l->debit : $l->debit - $l->credit
        ), 2);

        $vat = $sum($lines->whereIn('account_id', $vatIds), true);
        $shipping = $sum($lines->where('account_type', 'liabilities')->whereNotIn('account_id', $vatIds), true);
        // الإيراد/الخصم لا يضمّان حساب ضريبةٍ مهما كان نوعُه
        $revenue = $sum($lines->where('account_type', 'revenues')->whereNotIn('account_id', $vatIds), true);
        $discount = $sum($lines->where('account_type', 'expenses')->whereNotIn('account_id', $vatIds), false);
        $journalNet = round($revenue + $vat + $shipping - $discount, 2);

        return [
            'report_net' => $reportNet,
            'revenue' => $revenue,
            'vat' => $vat,
            'shipping_payable' => $shipping,
            'discount' => $discount,
            'journal_net' => $journalNet,
            'difference' => round($reportNet - $journalNet, 2),
            'gross' => round((float) (clone $orders)->sum('total_amount'), 2),
            'cancelled' => round((float) (clone $orders)->where('status', OrderStatus::CANCELLED->value)->sum('total_amount'), 2),
            'cancelled_count' => (clone $orders)->where('status', OrderStatus::CANCELLED->value)->count(),
            'refunds' => round((float) (clone $orders)->where('status', '!=', OrderStatus::CANCELLED->value)->sum('refund_amount'), 2),
            'uncollected' => round((float) (clone $orders)
                ->where('payment_status', '!=', 'paid')
                ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::REFUNDED->value])
                ->sum(DB::raw('total_amount - COALESCE(refund_amount, 0)')), 2),
        ];
    }

    /**
     * الصفحة الرئيسية للتقارير - نظرة عامة
     */
    public function index(Request $request)
    {
        $period = (string) min(3650, max(1, (int) $request->query('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // إحصائيات عامة
        $totalOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $completedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->count();
        $totalRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));
        $totalCustomers = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');
        $totalProductsSold = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })->sum('quantity');

        // إجمالي مبالغ الطلبات (جميع الطلبات)
        $totalOrderAmounts = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // إجمالي الإيرادات بدون المسترده والملغيه والمرتجعه
        $totalRevenueWithoutCancelledReturned = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));

        // إحصائيات الطلبات المرتجعة والمستردة
        $returnedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::RETURNED->value)
            ->count();
        $refundedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::REFUNDED->value)
            ->count();
        $totalReturnedRefunded = $returnedOrders + $refundedOrders;

        // إجمالي المبالغ المستردة
        $totalRefundedAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('refund_amount');

        // إحصائيات الطلبات حسب الحالة (المتوقع طلبها)
        $pendingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PENDING->value)
            ->count();
        $confirmedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CONFIRMED->value)
            ->count();
        $processingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PROCESSING->value)
            ->count();
        $readyForShippingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::READY_FOR_SHIPPING->value)
            ->count();
        $shippedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::SHIPPED->value)
            ->count();
        $outForDeliveryOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::OUT_FOR_DELIVERY->value)
            ->count();
        $deliveredOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::DELIVERED->value)
            ->count();
        $cancelledOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CANCELLED->value)
            ->count();

        // إجمالي الإيرادات بدون الملغيه والمرتجعه
        $totalRevenueWithoutCancelledReturned = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));

        // إجمالي الإيرادات لكل حالة
        $pendingRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PENDING->value)
            ->sum('total_amount');
        $confirmedRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CONFIRMED->value)
            ->sum('total_amount');
        $processingRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PROCESSING->value)
            ->sum('total_amount');
        $readyForShippingRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::READY_FOR_SHIPPING->value)
            ->sum('total_amount');
        $shippedRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::SHIPPED->value)
            ->sum('total_amount');
        $outForDeliveryRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::OUT_FOR_DELIVERY->value)
            ->sum('total_amount');
        $deliveredRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::DELIVERED->value)
            ->sum('total_amount');
        $cancelledRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CANCELLED->value)
            ->sum('total_amount');
        $returnedRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::RETURNED->value)
            ->sum('total_amount');
        $refundedRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::REFUNDED->value)
            ->sum('total_amount');

        // المقارنة مع الفترة السابقة
        $previousStartDate = Carbon::now()->subDays($period * 2)->startOfDay();
        $previousEndDate = $startDate->copy()->subSecond();

        $previousOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();
        $previousRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->sum(DB::raw(self::NET));

        // حساب النسب
        $ordersGrowth = $previousOrders > 0
            ? round((($totalOrders - $previousOrders) / $previousOrders) * 100, 1)
            : ($totalOrders > 0 ? 100 : 0);
        $revenueGrowth = $previousRevenue > 0
            ? round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : ($totalRevenue > 0 ? 100 : 0);

        // بيانات الرسم البياني - المبيعات اليومية
        $dailySales = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw('DATE(created_at) as date, SUM('.self::NET.') as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // توزيع حالات الطلبات
        $ordersByStatusRaw = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $ordersByStatus = [];
        foreach ($ordersByStatusRaw as $item) {
            $statusValue = $item->status instanceof OrderStatus ? $item->status->value : $item->status;
            $ordersByStatus[$statusValue] = $item->count;
        }

        // أفضل 5 منتجات
        $topProducts = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })
            ->with('product:id,name,images')
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total) as total_revenue')
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get();

        // أفضل 5 عملاء
        $topClients = OnlineOrder::forBranch($branchId)->with('client:id,first_name,last_name,email')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw('client_id, COUNT(*) as orders_count, SUM('.self::NET.') as total_spent')
            ->groupBy('client_id')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get();

        return view('onlinestore::reports.index', compact(
            'period',
            'totalOrders',
            'completedOrders',
            'totalRevenue',
            'totalCustomers',
            'totalProductsSold',
            'totalOrderAmounts',
            'totalRevenueWithoutCancelledReturned',
            'returnedOrders',
            'refundedOrders',
            'totalReturnedRefunded',
            'totalRefundedAmount',
            'pendingOrders',
            'confirmedOrders',
            'processingOrders',
            'readyForShippingOrders',
            'shippedOrders',
            'outForDeliveryOrders',
            'deliveredOrders',
            'cancelledOrders',
            'totalRevenueWithoutCancelledReturned',
            'pendingRevenue',
            'confirmedRevenue',
            'processingRevenue',
            'readyForShippingRevenue',
            'shippedRevenue',
            'outForDeliveryRevenue',
            'deliveredRevenue',
            'cancelledRevenue',
            'returnedRevenue',
            'refundedRevenue',
            'ordersGrowth',
            'revenueGrowth',
            'dailySales',
            'ordersByStatus',
            'topProducts',
            'topClients',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير المبيعات المالي التفصيلي
     *
     * «الضريبة» في هذا التقرير = ضريبة المنتجات + ضريبة الشحن (كما في ضريبة الفاتورة
     * `tax_total` وفي مطابقة القيود)، فتتطابق أرقامه مع الإقرار الضريبي.
     */
    public function sales(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // المبيعات اليومية (كل الطلبات)
        $dailySales = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM('.self::NET.') as revenue, SUM('.self::live('subtotal').') as subtotal, SUM('.self::live('tax_amount + COALESCE(shipping_tax_amount, 0)').') as tax, SUM('.self::live('shipping_cost').') as shipping, SUM('.self::live('discount_amount').') as discount, SUM('.self::live('refund_amount').') as refunds')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // إجمالي المبيعات (كل الطلبات)
        $totalSales = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as orders, SUM('.self::NET.') as revenue, SUM('.self::live('subtotal').') as subtotal, SUM('.self::live('tax_amount + COALESCE(shipping_tax_amount, 0)').') as tax, SUM('.self::live('shipping_cost').') as shipping, SUM('.self::live('discount_amount').') as discount, SUM('.self::live('refund_amount').') as refunds, AVG('.self::NET.') as avg_order')
            ->first();

        // توضيح كيفية حساب المبيعات الصافية
        // المبيعات الصافية = المجموع الإجمالي - الخصومات + الضرائب + الشحن
        // في الواقع، في هذا النظام، subtotal هو المبلغ قبل الضريبة والشحن والخصومات

        // إجمالي المبيعات بدون المرتجع والمسترد والملغي
        $totalSalesWithoutReturned = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::RETURNED->value, OrderStatus::REFUNDED->value])
            ->selectRaw('COUNT(*) as orders, SUM('.self::NET.') as revenue, SUM('.self::live('subtotal').') as subtotal, SUM('.self::live('tax_amount + COALESCE(shipping_tax_amount, 0)').') as tax, SUM('.self::live('shipping_cost').') as shipping, SUM('.self::live('discount_amount').') as discount, SUM('.self::live('refund_amount').') as refunds, AVG('.self::NET.') as avg_order')
            ->first();

        // توزيع المبيعات حسب الحالات
        $salesByStatus = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('status')
            ->get();

        // توزيع المبيعات حسب طريقة الدفع (كل الطلبات)
        $salesByPaymentMethod = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('payment_method')
            ->get();

        // توزيع المبيعات حسب طريقة الشحن (كل الطلبات)
        $salesByShippingMethod = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('shipping_method_id, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('shipping_method_id')
            ->with('shippingMethod:id,name')
            ->get();

        // متوسطات إضافية (كل الطلبات)
        $additionalStats = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('AVG(shipping_cost) as avg_shipping, AVG(discount_amount) as avg_discount, AVG(tax_amount + COALESCE(shipping_tax_amount, 0)) as avg_tax')
            ->first();

        // توزيع المبيعات حسب المدن (كل الطلبات)
        $salesByCity = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('shipping_city')
            ->where('shipping_city', '!=', '')
            ->selectRaw('shipping_city as city, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('shipping_city')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        // تفاصيل المبالغ حسب الحالات
        $amountsByStatus = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as orders, SUM(total_amount) as total_amount, AVG(total_amount) as avg_amount')
            ->groupBy('status')
            ->get();

        // تفاصيل الأعداد حسب الحالات
        $countsByStatus = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as orders')
            ->groupBy('status')
            ->get();

        // إجمالي كل الطلبات
        $totalAllOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // إجمالي الإيرادات لكل الطلبات
        $totalAllRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // إذا لم توجد بيانات، أنشئ كائن فارغ
        if (! $totalSales || $totalSales->orders == 0) {
            $totalSales = (object) [
                'orders' => 0,
                'revenue' => 0,
                'subtotal' => 0,
                'tax' => 0,
                'shipping' => 0,
                'discount' => 0,
                'avg_order' => 0,
            ];
        }

        // المبيعات حسب الشهر (كل الطلبات)
        $monthlySales = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // أفضل أيام الأسبوع (كل الطلبات)
        $bestDays = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DAYOFWEEK(created_at) as day_num, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('day_num')
            ->orderBy('day_num')
            ->get()
            ->map(function ($item) {
                $days = ['', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                $item->day_name = $days[$item->day_num] ?? '';

                return $item;
            });

        // أفضل ساعات المبيعات (كل الطلبات)
        $bestHours = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // المبيعات حسب طريقة الدفع
        $salesByPaymentRaw = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw('payment_method, COUNT(*) as orders, SUM('.self::NET.') as revenue')
            ->groupBy('payment_method')
            ->orderBy('revenue', 'desc')
            ->get();

        // تحويل enum إلى قيم نصية
        $salesByPayment = $salesByPaymentRaw->map(function ($item) {
            $item->payment_method = $item->payment_method instanceof \BackedEnum
                ? $item->payment_method->value
                : $item->payment_method;

            return $item;
        });

        // المطابقة مع القيود لنفس مجموعة الطلبات (نفس الفرع والنافذة)
        $reconciliation = $this->journalReconciliation(
            OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
        );

        return view('onlinestore::reports.sales', compact(
            'reconciliation',
            'dailySales',
            'totalSales',
            'totalSalesWithoutReturned',
            'salesByStatus',
            'salesByPaymentMethod',
            'salesByShippingMethod',
            'salesByCity',
            'additionalStats',
            'amountsByStatus',
            'countsByStatus',
            'totalAllOrders',
            'totalAllRevenue',
            'monthlySales',
            'bestDays',
            'bestHours',
            'salesByPayment',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير الطلبات التفصيلي
     */
    public function orders(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);
        $status = OrderStatus::tryFrom((string) $request->query('status'))?->value ?? 'all';

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // إحصائيات الطلبات حسب الحالة مع المبالغ
        $ordersByStatusRaw = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total_amount, AVG(total_amount) as avg_amount')
            ->groupBy('status')
            ->get();

        // تحويل الحالات لعرضها بشكل صحيح
        $ordersByStatus = $ordersByStatusRaw->map(function ($item) {
            $statusValue = $item->status instanceof OrderStatus ? $item->status->value : $item->status;
            $item->status_value = $statusValue;
            $item->status_label = $item->status instanceof OrderStatus ? $item->status->label() : $statusValue;

            return $item;
        });

        // إحصائيات الطلبات - الأعداد
        $totalOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $completedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->count();
        $cancelledOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CANCELLED->value)
            ->count();
        $pendingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PENDING->value)
            ->count();
        $confirmedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CONFIRMED->value)
            ->count();
        $processingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PROCESSING->value)
            ->count();
        $shippedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::SHIPPED->value)
            ->count();
        $deliveredOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::DELIVERED->value)
            ->count();
        $returnedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::RETURNED->value)
            ->count();
        $refundedOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::REFUNDED->value)
            ->count();

        // إحصائيات الطلبات - المبالغ
        $totalOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');
        $completedOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));
        $cancelledOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CANCELLED->value)
            ->sum('total_amount');
        $pendingOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PENDING->value)
            ->sum('total_amount');
        $confirmedOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::CONFIRMED->value)
            ->sum('total_amount');
        $processingOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::PROCESSING->value)
            ->sum('total_amount');
        $shippedOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::SHIPPED->value)
            ->sum('total_amount');
        $deliveredOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::DELIVERED->value)
            ->sum('total_amount');
        $returnedOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::RETURNED->value)
            ->sum('total_amount');
        $refundedOrdersAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatus::REFUNDED->value)
            ->sum('total_amount');

        // إجمالي الطلبات بدون المرتجع والملغي
        $totalOrdersWithoutCancelledReturned = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::RETURNED->value, OrderStatus::REFUNDED->value])
            ->count();
        $totalAmountWithoutCancelledReturned = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));

        // إجمالي الضرائب والشحن والخصومات
        $totalTaxAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum(DB::raw('tax_amount + COALESCE(shipping_tax_amount, 0)'));
        $totalShippingAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('shipping_cost');
        $totalDiscountAmount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('discount_amount');

        $conversionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 1) : 0;

        // متوسط قيمة الطلب
        $avgOrderValue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->avg(DB::raw(self::NET)) ?? 0;

        // الطلبات حسب طريقة الدفع
        $ordersByPaymentRaw = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as count, SUM('.self::NET.') as total')
            ->groupBy('payment_method')
            ->get();

        $ordersByPayment = $ordersByPaymentRaw->map(function ($item) {
            $item->payment_method_value = $item->payment_method instanceof \BackedEnum
                ? $item->payment_method->value
                : $item->payment_method;

            return $item;
        });

        // الطلبات اليومية
        $dailyOrdersRaw = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        $dailyOrders = $dailyOrdersRaw->map(function ($item) {
            $item->status_value = $item->status instanceof OrderStatus ? $item->status->value : $item->status;

            return $item;
        });

        // قائمة الطلبات
        $ordersQuery = OnlineOrder::forBranch($branchId)->with(['client:id,first_name,last_name,email,phone', 'items.product:id,name'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($status != 'all') {
            $ordersQuery->where('status', $status);
        }
        // تحويل البيانات للرسوم البيانية
        $ordersByStatusChart = $ordersByStatus->map(function ($item) {
            $statusValue = $item->status instanceof OrderStatus ? $item->status->value : ($item->status_value ?? $item->status);
            $statusLabel = $item->status instanceof OrderStatus ? $item->status->label() : ($item->status_label ?? $statusValue);

            return [
                'status_value' => $statusValue,
                'status_label' => $statusLabel,
                'count' => (int) $item->count,
                'total_amount' => (float) ($item->total_amount ?? 0),
            ];
        })->values()->toArray();

        $ordersByPaymentChart = $ordersByPayment->map(function ($item) {
            return [
                'payment_method' => $item->payment_method instanceof \BackedEnum
                    ? $item->payment_method->value
                    : ($item->payment_method_value ?? $item->payment_method ?? 'غير محدد'),
                'count' => (int) $item->count,
            ];
        })->values()->toArray();

        $orders = $ordersQuery->orderBy('created_at', 'desc')->paginate(25);

        return view('onlinestore::reports.orders', compact(
            'ordersByStatus',
            'totalOrders',
            'ordersByStatusChart',
            'ordersByPaymentChart',
            'completedOrders',
            'cancelledOrders',
            'pendingOrders',
            'confirmedOrders',
            'processingOrders',
            'shippedOrders',
            'deliveredOrders',
            'returnedOrders',
            'refundedOrders',
            'totalOrdersAmount',
            'completedOrdersAmount',
            'cancelledOrdersAmount',
            'pendingOrdersAmount',
            'confirmedOrdersAmount',
            'processingOrdersAmount',
            'shippedOrdersAmount',
            'deliveredOrdersAmount',
            'returnedOrdersAmount',
            'refundedOrdersAmount',
            'totalOrdersWithoutCancelledReturned',
            'totalAmountWithoutCancelledReturned',
            'totalTaxAmount',
            'totalShippingAmount',
            'totalDiscountAmount',
            'conversionRate',
            'cancellationRate',
            'avgOrderValue',
            'ordersByPayment',
            'dailyOrders',
            'orders',
            'startDate',
            'endDate',
            'status'
        ));
    }

    /**
     * تقرير المنتجات
     */
    public function products(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // أكثر المنتجات مبيعاً
        $topProducts = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })
            ->with('product:id,name,images,sale_price,category_id')
            ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(total) as total_revenue, COUNT(DISTINCT order_id) as orders_count, AVG(unit_price) as avg_price')
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->limit(20)
            ->get();

        // المنتجات الأقل مبيعاً
        $lowSellingProducts = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })
            ->with('product:id,name,images,sale_price')
            ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(total) as total_revenue')
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'asc')
            ->limit(10)
            ->get();

        // المبيعات حسب الفئة
        $salesByCategory = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })
            ->join('products', 'online_order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('categories.id, categories.name, SUM(online_order_items.quantity) as total_quantity, SUM(online_order_items.total) as total_revenue, COUNT(DISTINCT online_order_items.order_id) as orders_count')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // المبيعات حسب العلامة التجارية
        $salesByBrand = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })
            ->join('products', 'online_order_items.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->selectRaw('brands.id, COALESCE(brands.name, "بدون علامة") as name, SUM(online_order_items.quantity) as total_quantity, SUM(online_order_items.total) as total_revenue')
            ->groupBy('brands.id', 'brands.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // إحصائيات عامة
        $totalProductsSold = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })->sum('quantity');

        $uniqueProductsSold = OnlineOrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $successStatuses, $branchId) {
            $q->forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', $successStatuses);
        })->distinct('product_id')->count('product_id');

        $avgUnitsPerOrder = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->withCount('items')
            ->get()
            ->avg('items_count') ?? 0;
        // تحويل البيانات للرسوم البيانية
        $salesByCategoryChart = $salesByCategory->map(function ($item) {
            return [
                'name' => $item->name ?? 'غير محدد',
                'total_revenue' => (float) ($item->total_revenue ?? 0),
                'total_quantity' => (int) ($item->total_quantity ?? 0),
            ];
        })->values()->toArray();

        $salesByBrandChart = $salesByBrand->map(function ($item) {
            return [
                'name' => $item->name ?? 'غير محدد',
                'total_revenue' => (float) ($item->total_revenue ?? 0),
                'total_quantity' => (int) ($item->total_quantity ?? 0),
            ];
        })->values()->toArray();

        return view('onlinestore::reports.products', compact(
            'topProducts',
            'lowSellingProducts',
            'salesByCategory',
            'salesByBrand',
            'totalProductsSold',
            'uniqueProductsSold',
            'salesByCategoryChart',  // أضف هذا
            'salesByBrandChart',     // أضف هذا
            'avgUnitsPerOrder',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير العملاء
     */
    public function clients(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // العملاء الجدد (الذين لديهم طلبات في الفترة)
        $newClients = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        // إجمالي العملاء النشطين (لديهم طلبات)
        $activeClients = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        // أفضل العملاء (حسب المبيعات)
        $topClients = OnlineOrder::forBranch($branchId)->with('client:id,first_name,last_name,email,phone')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw('client_id, COUNT(*) as orders_count, SUM('.self::NET.') as total_spent, AVG('.self::NET.') as avg_order')
            ->groupBy('client_id')
            ->orderBy('total_spent', 'desc')
            ->limit(20)
            ->get();

        // العملاء الجدد vs المتكررين
        // نفس تصفية الفرع أعلاه — الاستعلام الفرعي كان SQL خام على جدول
        // online_orders كاملاً بلا قيد الفرع، فيحسب عميلاً "جديداً" حتى لو
        // طلب سابقاً من فرعٍ آخر ضمن نطاقٍ خارج ما يراه المستخدم الحالي
        $firstTimeCustomers = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('client_id', OnlineOrder::forBranch($branchId)->where('created_at', '<', $startDate)->pluck('client_id'))
            ->distinct('client_id')
            ->count('client_id');

        $returningCustomers = $activeClients - $firstTimeCustomers;

        // معدل الاحتفاظ بالعملاء
        $retentionRate = $activeClients > 0 ? round(($returningCustomers / $activeClients) * 100, 1) : 0;

        // متوسط الإنفاق لكل عميل
        $totalRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET));
        $avgSpendingPerClient = $activeClients > 0 ? round($totalRevenue / $activeClients, 2) : 0;

        // العملاء حسب المدينة
        $clientsByCity = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->whereNotNull('shipping_city')
            ->where('shipping_city', '!=', '')
            ->selectRaw('shipping_city as city, COUNT(DISTINCT client_id) as clients_count, COUNT(*) as orders_count, SUM('.self::NET.') as total_revenue')
            ->groupBy('shipping_city')
            ->orderBy('total_revenue', 'desc')
            ->limit(15)
            ->get();

        // العملاء الجدد حسب اليوم
        $newClientsByDay = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT client_id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        // تحويل البيانات للرسوم البيانية
        $newClientsByDayChart = $newClientsByDay->map(function ($item) {
            return [
                'date' => $item->date,
                'count' => (int) $item->count,
            ];
        })->values()->toArray();

        return view('onlinestore::reports.clients', compact(
            'newClients',
            'activeClients',
            'newClientsByDayChart',
            'topClients',
            'firstTimeCustomers',
            'returningCustomers',
            'retentionRate',
            'avgSpendingPerClient',
            'clientsByCity',
            'newClientsByDay',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير الكوبونات والخصومات
     */
    public function coupons(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // إحصائيات الكوبونات
        $totalCoupons = StoreCoupon::forBranch($branchId)->count();
        $activeCoupons = StoreCoupon::forBranch($branchId)->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->count();

        // استخدامات الكوبونات
        $couponUsages = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->with('coupon:id,code,name,type,value')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('coupon_id, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('coupon_id')
            ->orderBy('usage_count', 'desc')
            ->get();

        // إجمالي الخصومات
        $totalDiscount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->sum('discount_amount');

        // عدد الطلبات مع خصومات
        $ordersWithDiscount = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->where('discount_amount', '>', 0)
            ->count();

        // متوسط الخصم
        $avgDiscount = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('discount_amount') ?? 0;

        // أكثر الكوبونات استخداماً
        $topCoupons = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->with('coupon:id,code,name,type,value')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('coupon_id, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('coupon_id')
            ->orderBy('total_discount', 'desc')
            ->limit(10)
            ->get();

        // الاستخدام اليومي للكوبونات
        $dailyCouponUsage = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        // تحويل البيانات للرسوم البيانية
        $dailyCouponUsageChart = $dailyCouponUsage->map(function ($item) {
            return [
                'date' => $item->date,
                'usage_count' => (int) $item->usage_count,
                'total_discount' => (float) ($item->total_discount ?? 0),
            ];
        })->values()->toArray();

        $topCouponsChart = $topCoupons->map(function ($item) {
            return [
                'code' => $item->coupon->code ?? 'محذوف',
                'total_discount' => (float) ($item->total_discount ?? 0),
            ];
        })->values()->toArray();

        return view('onlinestore::reports.coupons', compact(
            'totalCoupons',
            'activeCoupons',
            'couponUsages',
            'totalDiscount',
            'ordersWithDiscount',
            'avgDiscount',
            'topCoupons',
            'dailyCouponUsage',
            'dailyCouponUsageChart',  // أضف هذا
            'topCouponsChart',        // أضف هذا
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير الشحن والتوصيل
     */
    public function shipping(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        $successStatuses = $this->getSuccessfulStatuses();
        // الفرع النشط للمستخدم — نفس تصفية OnlineOrderController::index() كي لا
        // تختلف أرقام هذا التقرير عن قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // الطلبات حسب طريقة الشحن
        $ordersByShippingMethod = OnlineOrder::forBranch($branchId)->with('shippingMethod:id,name')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('shipping_method_id, COUNT(*) as orders_count, SUM(shipping_cost) as total_shipping, AVG(shipping_cost) as avg_shipping')
            ->groupBy('shipping_method_id')
            ->orderBy('orders_count', 'desc')
            ->get();

        // إجمالي تكاليف الشحن
        $totalShippingCost = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->sum('shipping_cost');

        // إيرادات الشحن (للطلبات المكتملة)
        $shippingRevenue = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', $successStatuses)
            ->sum('shipping_cost');

        // متوسط تكلفة الشحن
        $avgShippingCost = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('shipping_cost', '>', 0)
            ->avg('shipping_cost') ?? 0;

        // الطلبات بشحن مجاني
        $freeShippingOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->where('shipping_cost', 0)
            ->count();

        $totalOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $freeShippingRate = $totalOrders > 0 ? round(($freeShippingOrders / $totalOrders) * 100, 1) : 0;

        // الطلبات حسب المدينة
        $ordersByCity = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('shipping_city')
            ->where('shipping_city', '!=', '')
            ->selectRaw('shipping_city as city, COUNT(*) as orders_count, SUM(shipping_cost) as total_shipping')
            ->groupBy('shipping_city')
            ->orderBy('orders_count', 'desc')
            ->limit(15)
            ->get();

        // الشحن اليومي
        $dailyShipping = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders_count, SUM(shipping_cost) as total_shipping')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // طرق الشحن النشطة
        $activeShippingMethods = ShippingMethod::forBranch($branchId)->where('is_active', true)->get();

        // تحويل البيانات للرسوم البيانية
        $dailyShippingChart = $dailyShipping->map(function ($item) {
            return [
                'date' => $item->date,
                'orders_count' => (int) $item->orders_count,
                'total_shipping' => (float) ($item->total_shipping ?? 0),
            ];
        })->values()->toArray();

        $shippingMethodsChart = $ordersByShippingMethod->map(function ($item) {
            return [
                'name' => $item->shippingMethod->name ?? 'غير محدد',
                'orders_count' => (int) $item->orders_count,
            ];
        })->values()->toArray();

        return view('onlinestore::reports.shipping', compact(
            'ordersByShippingMethod',
            'totalShippingCost',
            'shippingRevenue',
            'avgShippingCost',
            'freeShippingOrders',
            'freeShippingRate',
            'ordersByCity',
            'dailyShipping',
            'dailyShippingChart',      // أضف هذا
            'shippingMethodsChart',    // أضف هذا
            'activeShippingMethods',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير التقييمات والأسئلة
     */
    public function reviews(Request $request)
    {
        [$startDate, $endDate] = $this->reportWindow($request);

        // إحصائيات التقييمات
        $totalReviews = ProductReview::whereBetween('created_at', [$startDate, $endDate])->count();
        $approvedReviews = ProductReview::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $pendingReviews = ProductReview::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // متوسط التقييم
        $avgRating = ProductReview::whereBetween('created_at', [$startDate, $endDate])
            ->avg('rating') ?? 0;

        // التقييمات حسب النجوم
        $reviewsByRating = ProductReview::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->pluck('count', 'rating')
            ->toArray();

        // إحصائيات الأسئلة
        $totalQuestions = ProductQuestion::whereBetween('created_at', [$startDate, $endDate])->count();
        $answeredQuestions = ProductQuestion::where('status', 'answered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $pendingQuestions = ProductQuestion::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $answerRate = $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100, 1) : 0;

        // المنتجات الأكثر تقييماً
        $mostReviewedProducts = ProductReview::with('product:id,name,images')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('product_id, COUNT(*) as reviews_count, AVG(rating) as avg_rating')
            ->groupBy('product_id')
            ->orderBy('reviews_count', 'desc')
            ->limit(10)
            ->get();

        // التقييمات اليومية
        $dailyReviews = ProductReview::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, AVG(rating) as avg_rating')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // أحدث التقييمات
        $latestReviews = ProductReview::with(['product:id,name', 'client:id,first_name,last_name'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // تحويل البيانات للرسوم البيانية
        $dailyReviewsChart = $dailyReviews->map(function ($item) {
            return [
                'date' => $item->date,
                'count' => (int) $item->count,
                'avg_rating' => (float) ($item->avg_rating ?? 0),
            ];
        })->values()->toArray();

        // تحويل التقييمات حسب النجوم لتكون array of objects
        $reviewsByRatingChart = [];
        foreach ($reviewsByRating as $rating => $count) {
            $reviewsByRatingChart[] = [
                'rating' => (int) $rating,
                'count' => (int) $count,
            ];
        }

        return view('onlinestore::reports.reviews', compact(
            'totalReviews',
            'approvedReviews',
            'pendingReviews',
            'avgRating',
            'reviewsByRating',
            'reviewsByRatingChart',    // أضف هذا
            'totalQuestions',
            'answeredQuestions',
            'pendingQuestions',
            'answerRate',
            'mostReviewedProducts',
            'dailyReviews',
            'dailyReviewsChart',       // أضف هذا
            'latestReviews',
            'startDate',
            'endDate'
        ));
    }
}
