<?php

namespace Modules\Sales\Http\Controllers\Report;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * «مبيعات الأصناف» — التقريرُ المجمَّع.
 *
 * يقرأ سطورَ «مبيعات البنود» نفسَها ({@see SalesByItemController::buildLines()})
 * ويجمعها: حسب الصنف افتراضاً، أو التصنيف أو المستودع أو العميل أو الموظف
 * أو الفرع أو اليوم أو الشهر. التجميعُ حسب المجموعة وحسب الوحدة حُذف بطلب
 * صاحب النظام؛ ومسارُ تقرير المجموعات القديم يُحوِّل إلى هنا.
 */
class SalesByProductController extends SalesByItemController
{
    public function __construct()
    {
        $this->middleware('permission:sales.reports.by_product');
    }

    public function index(Request $request)
    {
        $groupBy = $request->input('group_by', 'item');
        $options = $this->groupByOptions();
        if (! isset($options[$groupBy])) {
            $groupBy = 'item';
        }

        $data = $this->reportContext($request);
        $built = $this->buildLines($request, $data);
        $totals = $this->totalsOf($built['lines'], $built);
        /* «ملخص» سطرٌ لكلّ مجموعة، و«تفاصيل» يُلحق بكلّ مجموعةٍ بنودَها */
        $isSummary = $request->input('view_type', 'summary') !== 'details';
        $reportData = $this->aggregate($built['lines'], $groupBy, ! $isSummary);

        if ($request->ajax() || $request->boolean('_ajax')) {
            return response()->json(['success' => true, 'data' => $reportData, 'totals' => $totals]);
        }

        return view('sales::sals_report.salesRport.Sales_By_Item', $data + [
            'reportData' => $reportData,
            'totals' => $totals,
            'groupBy' => $groupBy,
            'groupByOptions' => $options,
            'isSummary' => $isSummary,
            'pageTitle' => __('sales_reports.by_item.title_product'),
            'formAction' => route('salesReports.byProductNew'),
        ]);
    }

    protected function groupByOptions(): array
    {
        return [
            'item' => __('sales_reports.group_by.item'),
            'category' => __('sales_reports.group_by.category'),
            'storehouse' => __('sales_reports.group_by.storehouse'),
            'client' => __('sales_reports.group_by.client'),
            'employee' => __('sales_reports.group_by.employee'),
            'branch' => __('sales_reports.group_by.branch'),
            'day' => __('sales_reports.group_by.day'),
            'month' => __('sales_reports.group_by.month'),
        ];
    }

    protected function aggregate(Collection $lines, string $groupBy, bool $withLines = false): Collection
    {
        $keyOf = fn ($l) => match ($groupBy) {
            'day' => $l['date'],
            'month' => substr($l['date'], 0, 7),
            'employee' => $l['employee_id'] ?? 'unknown',
            'client' => $l['client_id'] ?? 'unknown',
            'branch' => $l['branch_id'] ?? 'unknown',
            'category' => $l['category_id'],
            'product_group' => $l['product_group_id'],
            'storehouse' => $l['store_house_id'] ?? 'unknown',
            'unit' => $l['unit_key'],
            default => $l['item_id'],
        };
        $nameOf = fn ($l) => match ($groupBy) {
            'day' => $l['date'],
            'month' => Carbon::parse($l['date'].'')->format('Y-m'),
            'employee' => $l['employee_name'],
            'client' => $l['client_name'],
            'branch' => $l['branch_name'],
            'category' => $l['category_name'],
            'product_group' => $l['product_group_name'],
            'storehouse' => $l['storehouse_name'],
            'unit' => $l['unit_name'],
            default => $l['product_name'],
        };

        $out = collect();
        foreach ($lines->groupBy($keyOf) as $key => $group) {
            $first = $group->first();
            $row = [
                'name' => $nameOf($first),
                'code' => $groupBy === 'item' ? $first['product_code'] : null,
                'category_name' => $groupBy === 'item' ? $first['category_name'] : null,
                'product_group_name' => in_array($groupBy, ['item', 'category'], true) ? $first['product_group_name'] : null,
                'sales_qty' => 0, 'returns_qty' => 0, 'sales_base_qty' => 0, 'returns_base_qty' => 0,
                'quantities_by_unit' => [],
                'amount' => 0, 'returns_amount' => 0, 'discount' => 0,
                'documents' => [],
                'lines' => $group->count(),
            ];
            foreach ($group as $l) {
                if ($l['kind'] === 'sale') {
                    $row['sales_qty'] += $l['quantity'];
                    $row['sales_base_qty'] += $l['base_quantity'];
                    $row['amount'] += $l['amount'];
                } else {
                    $row['returns_qty'] += abs($l['quantity']);
                    $row['returns_base_qty'] += abs($l['base_quantity']);
                    $row['returns_amount'] += abs($l['amount']);
                }
                if ($l['unit_name'] !== '-') {
                    $row['quantities_by_unit'][$l['unit_name']] = ($row['quantities_by_unit'][$l['unit_name']] ?? 0) + $l['quantity'];
                }
                $row['discount'] += $l['discount'];
                $row['documents'][$l['entry_id']] = true;
            }
            $row['net_qty'] = $row['sales_qty'] - $row['returns_qty'];
            $row['net_base_qty'] = $row['sales_base_qty'] - $row['returns_base_qty'];
            $row['net'] = $row['amount'] - $row['returns_amount'];
            $row['documents'] = count($row['documents']);

            if ($groupBy === 'client') {
                $row['status_name'] = $first['client_status_name'];
                $row['stage_name'] = $first['client_stage_name'];
            }

            if ($withLines) {
                $row['items'] = $group->sortByDesc(fn ($l) => $l['date'].'#'.str_pad((string) $l['entry_id'], 10, '0', STR_PAD_LEFT))->values()->all();
            }

            $out->put($key, $row);
        }

        return in_array($groupBy, ['day', 'month'], true)
            ? $out->sortKeysDesc()
            : $out->sortByDesc('net');
    }
}
