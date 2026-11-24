<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مبيعاتُ الأصناف في نقطة البيع.
 *
 * التجميعُ بالصنف أو بالصنف والوحدة — والتمييزُ بينهما مهمّ: صنفٌ يُباع
 * بالحبّة وبالكرتون سطرٌ واحدٌ في الأوّل وسطران في الثاني، وجمعُ كمّياته
 * بلا تمييزٍ يخلط الوحدات.
 */
class ProductSalesReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_product_sales';
    }

    public function title(): string
    {
        return 'تقرير مبيعات الأصناف — نقاط البيع';
    }

    public function groupings(): array
    {
        return [
            'product' => 'حسب الصنف',
            'product_unit' => 'حسب الصنف والوحدة',
            'date' => 'حسب التاريخ',
            'session' => 'حسب الجلسة',
        ];
    }

    private function dimension(): array
    {
        return match ($this->groupBy()) {
            'product_unit' => [
                'select' => [
                    DB::raw("CONCAT(products.id, '-', COALESCE(invoice_items.product_unit_id, 0)) as dim_id"),
                    DB::raw("CONCAT(products.name, COALESCE(CONCAT(' (', invoice_items.unit_name, ')'), '')) as dim_label"),
                ],
                'group' => ['dim_id', 'dim_label'],
                'label' => 'الصنف والوحدة',
            ],
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
            default => [
                'select' => ['products.id as dim_id', 'products.name as dim_label'],
                'group' => ['products.id', 'products.name'],
                'label' => 'الصنف',
            ],
        };
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        return $this->mergeReturns(
            $this->aggregate('pos', $dimension, true),
            $this->aggregate('returned', $dimension, false),
            fn ($row) => (string) $row->dim_id
        );
    }

    private function aggregate(string $type, array $dimension, bool $isSales)
    {
        $query = $this->invoiceQuery($type)
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id');

        $query
            ->when($this->request->filled('category_id'), fn ($q) => $q->where('products.category_id', $this->request->category_id))
            ->when($this->request->filled('product_id'), fn ($q) => $q->where('products.id', $this->request->product_id));

        $metrics = $isSales
            ? [
                DB::raw('COUNT(DISTINCT invoices.id) as invoices_count'),
                DB::raw($this->baseQuantityExpression().' as total_quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as total_amount'),
                DB::raw('AVG(CAST(invoice_items.unit_price AS DECIMAL(18,4))) as avg_unit_price'),
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
            'متوسط السعر' => fn ($r) => number_format((float) ($r->avg_unit_price ?? 0), 2),
            'قيمة المبيعات' => fn ($r) => number_format((float) $r->total_amount, 2),
            'الكمية المرتجعة' => fn ($r) => number_format((float) $r->returns_quantity, 3),
            'قيمة المرتجعات' => fn ($r) => number_format((float) $r->returns_amount, 2),
            'صافي الكمية' => fn ($r) => number_format((float) $r->net_quantity, 3),
            'صافي المبيعات' => fn ($r) => number_format((float) $r->net_amount, 2),
        ];
    }
}
