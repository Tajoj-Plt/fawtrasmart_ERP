<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مبيعاتُ التصنيفات في نقطة البيع.
 *
 * التجميعُ ثلاثيّ: بالتصنيف، أو بالتاريخ، أو بالجلسة — والثلاثةُ كانت
 * مكتوبةً في `switch` من مئتَي سطر، كلُّ فرعٍ منها يُعيد بناءَ استعلامَي
 * المبيعات والمرتجعات كاملَين.
 */
class CategorySalesReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_category_sales';
    }

    public function title(): string
    {
        return 'تقرير مبيعات التصنيفات — نقاط البيع';
    }

    public function groupings(): array
    {
        return [
            'category' => 'حسب التصنيف',
            'date' => 'حسب التاريخ',
            'session' => 'حسب الجلسة',
            'branch' => 'حسب الفرع',
        ];
    }

    /**
     * أعمدةُ التجميع لكلّ وجه — التعريفُ بيانٌ لا شيفرة.
     *
     * @return array{select: array, group: array, key: callable, label: string}
     */
    private function dimension(): array
    {
        return match ($this->groupBy()) {
            'date' => [
                'select' => [DB::raw('DATE(invoices.invoice_date) as dim_id')],
                'group' => ['dim_id'],
                'label' => 'التاريخ',
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
            default => [
                'select' => ['categories.id as dim_id', 'categories.name as dim_label'],
                'group' => ['categories.id', 'categories.name'],
                'label' => 'التصنيف',
            ],
        };
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        $sales = $this->aggregate('pos', $dimension, true);
        $returns = $this->aggregate('returned', $dimension, false);

        return $this->mergeReturns($sales, $returns, fn ($row) => (string) $row->dim_id);
    }

    private function aggregate(string $type, array $dimension, bool $isSales)
    {
        $query = $this->invoiceQuery($type)
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('branches', 'invoices.pos_branch_id', '=', 'branches.id');

        // فلترُ التصنيف يخصّ هذا التقرير وحده
        $query->when($this->request->filled('category_id'), fn ($q) => $q->where('categories.id', $this->request->category_id));

        $metrics = $isSales
            ? [
                DB::raw('COUNT(DISTINCT invoices.id) as invoices_count'),
                DB::raw($this->baseQuantityExpression().' as total_quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as total_amount'),
                DB::raw('SUM(CAST(invoice_items.tax_1 AS DECIMAL(18,2))) as tax_amount'),
                DB::raw('MIN(invoices.invoice_date) as first_sale'),
                DB::raw('MAX(invoices.invoice_date) as last_sale'),
            ]
            : [
                DB::raw('COUNT(DISTINCT invoices.id) as returns_count'),
                DB::raw($this->baseQuantityExpression().' as returns_quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as returns_amount'),
            ];

        return collect(
            $query->select(array_merge($dimension['select'], $metrics))
                ->groupBy(...$dimension['group'])
                ->get()
        );
    }

    public function exportColumns(): array
    {
        return [
            $this->dimension()['label'] => 'dim_label',
            'عدد الفواتير' => 'invoices_count',
            'الكمية المباعة' => fn ($r) => number_format((float) $r->total_quantity, 3),
            'قيمة المبيعات' => fn ($r) => number_format((float) $r->total_amount, 2),
            'الضريبة' => fn ($r) => number_format((float) ($r->tax_amount ?? 0), 2),
            'عدد المرتجعات' => 'returns_count',
            'الكمية المرتجعة' => fn ($r) => number_format((float) $r->returns_quantity, 3),
            'قيمة المرتجعات' => fn ($r) => number_format((float) $r->returns_amount, 2),
            'صافي الكمية' => fn ($r) => number_format((float) $r->net_quantity, 3),
            'صافي المبيعات' => fn ($r) => number_format((float) $r->net_amount, 2),
        ];
    }
}
