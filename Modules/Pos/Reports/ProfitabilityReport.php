<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ربحيةُ نقاط البيع — بالصنف أو التصنيف أو الجلسة أو الفرع.
 *
 * ═════════════════════ صلاحيةٌ مستقلّة ═════════════════════
 *
 * `pos.reports.profit` لا `pos_view_reports`. التكلفةُ والهامشُ ليسا كعدد
 * الفواتير: من يقرأ مبيعاتِ ورديته لا يلزم أن يرى ما ربحته المنشأةُ من كل
 * صنف. وكان التقريرُ — كبقيّة التقارير — **بلا أيّ حراسة إطلاقاً**.
 *
 * ═════════════════════ ومصدرُ التكلفة ═════════════════════
 *
 * كانت التكلفةُ تُحسب من `products.purchase_price` **الحالي**. فتغييرُ سعر
 * الشراء اليوم **يُعيد كتابة ربحية الشهر الماضي** — تقريرٌ لا يُنتج الرقمَ
 * نفسَه مرّتين.
 *
 * والآن تُقرأ من `invoice_items.unit_cost_at_sale` المجمَّد لحظةَ البيع،
 * ويُسقَط إلى سعر الشراء الحالي للفواتير القديمة التي سبقت التجميد — مع
 * عمودٍ يُبيّن أيُّ الصفوف مبنيٌّ على تكلفةٍ تقديرية.
 */
class ProfitabilityReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_profitability';
    }

    public function title(): string
    {
        return 'تقرير الربحية — نقاط البيع';
    }

    public function permission(): string
    {
        return 'pos.reports.profit';
    }

    public function groupings(): array
    {
        return [
            'product' => 'حسب الصنف',
            'category' => 'حسب التصنيف',
            'session' => 'حسب الجلسة',
            'branch' => 'حسب الفرع',
            'date' => 'حسب التاريخ',
        ];
    }

    private function dimension(): array
    {
        return match ($this->groupBy()) {
            'category' => [
                'select' => ['categories.id as dim_id', 'categories.name as dim_label'],
                'group' => ['categories.id', 'categories.name'],
                'label' => 'التصنيف',
            ],
            'session' => [
                'select' => ['pos_sessions.id as dim_id', 'pos_sessions.session_number as dim_label'],
                'group' => ['pos_sessions.id', 'pos_sessions.session_number'],
                'label' => 'الجلسة',
            ],
            'branch' => [
                'select' => ['branches.id as dim_id', 'branches.name as dim_label'],
                'group' => ['branches.id', 'branches.name'],
                'label' => 'الفرع',
            ],
            'date' => [
                'select' => [DB::raw('DATE(invoices.invoice_date) as dim_id')],
                'group' => ['dim_id'],
                'label' => 'التاريخ',
            ],
            default => [
                'select' => ['products.id as dim_id', 'products.name as dim_label'],
                'group' => ['products.id', 'products.name'],
                'label' => 'الصنف',
            ],
        };
    }

    /**
     * صيغةُ التكلفة — المجمَّدةُ أوّلاً، ثم الحالية.
     *
     * `COALESCE` لا `IFNULL`: قياسيّةٌ عبر المحرّكات، والمشروع يختبر على
     * sqlite ويعمل على MySQL.
     */
    private function costExpression(): string
    {
        return 'SUM('
            .'CAST(invoice_items.quantity AS DECIMAL(18,4)) '
            .'* COALESCE(invoice_items.conversion_factor, 1) '
            .'* COALESCE(invoice_items.unit_cost_at_sale, products.purchase_price, 0)'
            .')';
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        $sales = $this->aggregate('pos', $dimension, true);
        $returns = $this->aggregate('returned', $dimension, false);

        $merged = $this->mergeReturns($sales, $returns, fn ($row) => (string) $row->dim_id);

        return $merged->map(function ($row) use ($returns) {
            $match = $returns->firstWhere('dim_id', $row->dim_id);

            $returnedCost = (float) ($match->returns_cost ?? 0);

            $row->net_revenue = round((float) $row->net_amount, 2);
            $row->net_cost = round((float) ($row->total_cost ?? 0) - $returnedCost, 2);
            $row->returns_cost = round($returnedCost, 2);
            $row->profit = round($row->net_revenue - $row->net_cost, 2);

            // هامشٌ على إيرادٍ صفر لا يُحسب — القسمة على صفر تُنتج قيمةً بلا معنى
            $row->margin_percent = $row->net_revenue > 0
                ? round(($row->profit / $row->net_revenue) * 100, 2)
                : 0.0;

            // صفوفٌ بلا تكلفةٍ مجمَّدة تُعلَّم كي تُقرأ على أنها تقديرية
            $row->cost_is_estimated = (bool) ($row->estimated_lines ?? 0);

            return $row;
        });
    }

    private function aggregate(string $type, array $dimension, bool $isSales)
    {
        $query = $this->invoiceQuery($type)
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('branches', 'invoices.pos_branch_id', '=', 'branches.id');

        $query
            ->when($this->request->filled('category_id'), fn ($q) => $q->where('products.category_id', $this->request->category_id))
            ->when($this->request->filled('product_id'), fn ($q) => $q->where('products.id', $this->request->product_id));

        $metrics = $isSales
            ? [
                DB::raw('COUNT(DISTINCT invoices.id) as invoices_count'),
                DB::raw($this->baseQuantityExpression().' as total_quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as total_amount'),
                DB::raw($this->costExpression().' as total_cost'),
                DB::raw('SUM(CASE WHEN invoice_items.unit_cost_at_sale IS NULL THEN 1 ELSE 0 END) as estimated_lines'),
            ]
            : [
                DB::raw('COUNT(DISTINCT invoices.id) as returns_count'),
                DB::raw($this->baseQuantityExpression().' as returns_quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as returns_amount'),
                DB::raw($this->costExpression().' as returns_cost'),
            ];

        return collect(
            $query->select(array_merge($dimension['select'], $metrics))
                ->groupBy(...$dimension['group'])
                ->get()
        );
    }

    protected function totals($rows): array
    {
        $totals = parent::totals($rows);

        $totals['net_cost'] = round((float) $rows->sum('net_cost'), 2);
        $totals['profit'] = round((float) $rows->sum('profit'), 2);
        $totals['margin_percent'] = $totals['net_amount'] > 0
            ? round(($totals['profit'] / $totals['net_amount']) * 100, 2)
            : 0.0;
        $totals['estimated_rows'] = $rows->where('cost_is_estimated', true)->count();

        return $totals;
    }

    public function exportColumns(): array
    {
        return [
            $this->dimension()['label'] => 'dim_label',
            'عدد الفواتير' => 'invoices_count',
            'صافي الكمية' => fn ($r) => number_format((float) $r->net_quantity, 3),
            'صافي الإيراد' => fn ($r) => number_format((float) $r->net_revenue, 2),
            'صافي التكلفة' => fn ($r) => number_format((float) $r->net_cost, 2),
            'الربح' => fn ($r) => number_format((float) $r->profit, 2),
            'الهامش %' => fn ($r) => number_format((float) $r->margin_percent, 2),
            'التكلفة تقديرية؟' => fn ($r) => $r->cost_is_estimated ? 'نعم' : 'لا',
        ];
    }
}
