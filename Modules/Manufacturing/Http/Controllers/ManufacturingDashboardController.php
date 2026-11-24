<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Enums\ManufacturOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\IndirectCost;
use App\Models\ManufacturOrders;
use App\Models\ProductionMaterials;
use App\Models\WorkStations;
use App\Support\CurrencySettings;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

/**
 * لوحة وحدة التصنيع.
 *
 * كان التصنيع قسماً داخل المخزون بلا مدخلٍ خاصّ به: يُدخَل إليه من الشريط
 * الجانبي إلى «أوامر التصنيع» مباشرةً، فلا صفحةَ تجمع حاله. وحين صار وحدةً
 * قائمةً لزمته واجهةٌ أولى كسائر الوحدات — بطاقةٌ في الصفحة الرئيسية تقود
 * إلى هنا.
 */
class ManufacturingDashboardController extends Controller
{
    /*
        اللوحةُ نفسُها محروسةٌ في المسارات بأربع صلاحيات بديلة (أيّ واحدةٍ
        منها تكفي — انظر التعليق في `routes/web.php`)، فلا يُكرَّر الحارس
        هنا بصلاحيةٍ واحدة تُضيّق ما وُسِّع عمداً.
    */
    use ScopesToBranch, UsesDocumentCurrency;

    public function index(): Renderable
    {
        $orders = $this->orderStats();

        return view('manufacturing::dashboard', [
            'orders' => $orders,
            /*
                كلُّ عدّادٍ في نطاق الفرع النشط.

                كانت اللوحةُ كلُّها على الجداول العارية: عدّاداتُ الفروع
                جميعاً فوق شاشاتٍ لا تعرض إلّا فرعاً واحداً — يقرأ مديرُ
                مصنعٍ «٤٧ أمر تصنيع» ثمّ يفتح القائمة فيجد تسعة، ولا شيء
                يفسّر الفرق.
            */
            'boms' => (int) $this->scopeToActiveBranch(ProductionMaterials::query())->count(),
            'workstations' => $this->workstationStats(),
            'indirectCosts' => $this->indirectCostStats(),
            'recentOrders' => $this->recentOrders(),
            'topProducts' => $this->topProducts(),
            /*
                عملةُ العرض في اللوحة هي الافتراضية من شاشة «العملات».

                والمجاميعُ أدناه تُجمَع عبر العملات بلا تحويل — وهو ما
                كانت تفعله من قبلُ صامتةً. فيُصرَّح بالعملة في الواجهة
                بدل «ر.س» المكتوبة في القالب، ويُذكر التنبيه حين تختلف
                عملاتُ الصفوف (`mixedCurrency`) بدل أن يُجمَع ريالٌ إلى
                دولارٍ ويُعرض الناتجُ رقماً واحداً بلا إشارة.
            */
            'displayCurrency' => CurrencySettings::code(),
            'mixedCurrency' => $this->hasMixedCurrencies(),
        ]);
    }

    /**
     * أفي نطاق الفرع أكثرُ من عملةٍ واحدة؟
     *
     * المجاميعُ في اللوحة (`SUM(total_cost)`، `SUM(total)`) تجمع أعمدةً
     * قد تحمل عملاتٍ مختلفة. والتحويلُ بينها يحتاج أسعارَ صرفٍ بتاريخ كلّ
     * مستند — وهو ما لا تملكه اللوحة. فبدل جمعٍ صامتٍ يُخرج رقماً لا
     * معنى له، تُرفَع إشارةٌ إلى الواجهة.
     */
    protected function hasMixedCurrencies(): bool
    {
        try {
            $codes = collect()
                ->merge($this->scopeToActiveBranch(ManufacturOrders::query())->distinct()->pluck('currency'))
                ->merge($this->scopeToActiveBranch(WorkStations::query())->distinct()->pluck('currency'))
                ->merge($this->scopeToActiveBranch(IndirectCost::query())->distinct()->pluck('currency'))
                ->filter()
                ->unique();

            return $codes->count() > 1;
        } catch (\Throwable $e) {
            // عمودُ عملةٍ غير مُرحَّل بعد — لا يجوز أن تسقط اللوحة لأجله
            return false;
        }
    }

    /**
     * أوامر التصنيع حسب حالتها.
     *
     * الحالات تُخزَّن أرقاماً، وتُقرأ هنا بخريطةٍ واحدة بدل تكرار الشرط
     * في كل موضع.
     */
    protected function orderStats(): array
    {
        $counts = $this->scopeToActiveBranch(ManufacturOrders::query())
            ->selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $counts->sum();

        return [
            'total' => $total,
            // أساسُ النِّسَب: الملغي ليس أمراً «لم يكتمل» — هو خارج العدّ
            'live' => $total - (int) ($counts[ManufacturOrderStatus::Cancelled->value] ?? 0),
            /*
                المفاتيح نصّية كما يخزّنها العمود.

                كانت أرقاماً (0..3) و`$counts` مفاتيحه سلاسل، فكل هذه
                القيم صفرٌ دائماً — مؤشّرا «قيد التنفيذ» و«مكتملة»
                وأشرطة التقدّم كلها فارغة مهما كان عدد الأوامر.
            */
            'draft' => (int) ($counts[ManufacturOrderStatus::Pending->value] ?? 0),
            'in_progress' => (int) ($counts[ManufacturOrderStatus::InProgress->value] ?? 0)
                           + (int) ($counts[ManufacturOrderStatus::MaterialsReceived->value] ?? 0),
            'done' => (int) ($counts[ManufacturOrderStatus::Completed->value] ?? 0)
                    + (int) ($counts[ManufacturOrderStatus::Closed->value] ?? 0),
            'cancelled' => (int) ($counts[ManufacturOrderStatus::Cancelled->value] ?? 0),
            'by_status' => $counts,
        ];
    }

    /**
     * محطّات العمل.
     *
     * لا عمود `status` في `work_stations` — المحطّة موجودةٌ أو غير موجودة.
     * فيُعرَض بدل «النشطة» ما له معنًى فعلاً: مجموع تكلفتها.
     */
    protected function workstationStats(): array
    {
        return [
            'total' => (int) $this->scopeToActiveBranch(WorkStations::query())->count(),
            'cost' => (float) ($this->scopeToActiveBranch(WorkStations::query())->sum('total_cost') ?? 0),
        ];
    }

    protected function indirectCostStats(): array
    {
        // العمود اسمه `total` لا `total_amount`
        return [
            'count' => (int) $this->scopeToActiveBranch(IndirectCost::query())->count(),
            'amount' => (float) ($this->scopeToActiveBranch(IndirectCost::query())->sum('total') ?? 0),
        ];
    }

    protected function recentOrders()
    {
        return $this->scopeToActiveBranch(ManufacturOrders::query())
            ->with(['product:id,name'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    /**
     * أكثر الأصناف تصنيعاً.
     *
     * كان الاستعلام يقرأ `manufactur_orders_items.product_id` و`.quantity`
     * — وهما عمودان لا وجود لهما: بنود الأمر تحمل `raw_product_id` للمواد
     * الخام و`end_life_product_id` لنواتج نهاية العمر، لا صنفاً واحداً.
     * فكان الاستعلام يرمي دائماً، ويبتلعه `catch` فتُعرض قائمةٌ فارغة أبداً.
     *
     * والصنف المصنَّع يُحمَل على الأمر نفسه (`manufactur_orders.product_id`)
     * مع كمّيته — فمن هناك يُقرأ. والعدّ يُسمّى `orders_count` لا `lines`:
     * `lines` كلمةٌ محجوزة في MariaDB تُسقط الاستعلام.
     *
     * ── الملغي، وترتيبُ الأصناف ──────────────────────────────────────
     *
     * كان `SUM(o.quantity)` يجمع كميةَ الأمر الملغي مع غيره، والترتيبُ
     * وشريطُ «الحجم النسبي» على الكمية — فيُقارَن عشرةُ آلاف مليلتر من
     * عطرٍ سائب بثمانٍ وتسعين زجاجة، ويظهر الثاني «أقلَّ تصنيعاً» بمئة
     * مرّة. والكميةُ لا تُقارَن بين صنفين: كلٌّ بوحدته.
     *
     * الآن: الملغي خارج العدّ والكمية، والترتيبُ بعدد الأوامر، والكميةُ
     * تُعرض لكل صنفٍ على حِدته — مخطَّطةً، ومنتَجةً فعلاً من المكتمل.
     */
    protected function topProducts(int $limit = 5)
    {
        $done = "o.status IN ('".ManufacturOrderStatus::Completed->value."','".ManufacturOrderStatus::Closed->value."')";

        return $this->scopeRawToActiveBranch(
            DB::table('manufactur_orders as o')
                ->join('products as p', 'p.id', '=', 'o.product_id')
                ->whereNotNull('o.product_id')
                ->where('o.status', '!=', ManufacturOrderStatus::Cancelled->value),
            'o.branch_id'
        )
            ->groupBy('p.id', 'p.name')
            ->selectRaw(
                "p.id, p.name, COUNT(*) orders_count,
                 SUM(CASE WHEN {$done} THEN 1 ELSE 0 END) done_count,
                 COALESCE(SUM(o.quantity),0) qty,
                 COALESCE(SUM(CASE WHEN {$done} THEN COALESCE(o.actual_quantity, o.quantity) END),0) produced"
            )
            ->orderByDesc('orders_count')
            ->orderBy('p.name')
            ->limit($limit)
            ->get();
    }
}
