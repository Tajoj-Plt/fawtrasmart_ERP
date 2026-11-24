<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Exports\SimpleDetailedMovementExport;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * الحركة التفصيلية المبسّطة — نسخة مختصرة وسريعة من
 * `DetailedMovementReportController`: بلا رصيدٍ افتتاحي، بلا رسمٍ بياني،
 * وبلا فلاتر التصنيف/العلامة/نوع الحركة — مستودعٌ وفترةٌ وبحثٌ نصي فقط.
 *
 * أُنشئ هذا المتحكّم لسدّ فجوة: الروابط الثلاثة (`index`/`getData`/
 * `exportExcel`) وبطاقتها في `report/index.blade.php` كانت موجودة منذ
 * البداية (`Modules/Stock/routes/web.php`)، لكنّ الكنترولر نفسه لم يُكتب
 * قط — فكل نقرة على «الحركة التفصيلية المبسّطة» كانت تُسقط الصفحة بخطأ 500
 * (`Target class [...SimpleDetailedMovementController] does not exist`).
 *
 * التصدير هنا فعليٌّ من الخادم (`Maatwebsite\Excel`) خلافاً لبقية تقارير
 * المخزون التي تصدّر من الجدول المعروض بجافاسكربت وحده
 * (`stock::components.report-tools`) — لأن مسار `exportExcel` مُعرَّفٌ
 * أصلاً بصلاحيةٍ مستقلة، فيصدّر كل ما يطابق الفلتر لا الصفحة المعروضة فقط.
 */
class SimpleDetailedMovementController extends ReportController
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware('permission.role:stock.reports.detailed_movement');
    }

    public function index(Request $request)
    {
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        [$startDate, $endDate] = $this->resolveDates($request);

        $movements = $this->fetchMovements($request, $startDate, $endDate);

        return view('stock::report.stock_report.simple_detailed_movement', [
            'warehouses' => $warehouses,
            'movements' => $movements,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function getData(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDates($request);

        return response()->json([
            'data' => $this->fetchMovements($request, $startDate, $endDate),
        ]);
    }

    public function exportExcel(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDates($request);

        $movements = $this->fetchMovements($request, $startDate, $endDate);

        return Excel::download(
            new SimpleDetailedMovementExport(collect($movements)),
            'simple_detailed_movement_'.now()->format('Y-m-d_H-i-s').'.xlsx'
        );
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function resolveDates(Request $request): array
    {
        $range = $this->applyDefaultReportDates($request);

        return [
            Carbon::parse($range['start'])->startOfDay(),
            Carbon::parse($range['end'])->endOfDay(),
        ];
    }

    /**
     * الاستعلام المبسّط: فلترة بالمستودع والبحث النصي فقط، وترتيبٌ زمنيٌّ
     * تنازلي — لا رصيد افتتاحي ولا رصيدٌ متتابع محسوب، القيمة هنا هي
     * الحركة نفسها لا أثرها على الرصيد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchMovements(Request $request, Carbon $startDate, Carbon $endDate): array
    {
        $query = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
            ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
            ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
            ->select(
                'warehouse_permits_products.quantity',
                'warehouse_permits.permission_date',
                'warehouse_permits.number as permit_number',
                'products.name as product_name',
                'products.serial_number as product_code',
                'store_houses.name as warehouse_name',
                'users.name as user_name',
            )
            ->whereNotNull('warehouse_permits_products.quantity')
            ->where('warehouse_permits_products.quantity', '!=', 0)
            ->whereBetween('warehouse_permits.permission_date', [$startDate, $endDate]);

        $this->scopeToCreator($query, 'warehouse_permits.created_by');

        if ($request->filled('warehouse')) {
            $query->where('warehouse_permits.store_houses_id', $request->warehouse);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.serial_number', 'like', "%{$search}%")
                    ->orWhere('warehouse_permits.number', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('warehouse_permits.permission_date')
            ->orderByDesc('warehouse_permits.id')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->permission_date,
                'permit_number' => $row->permit_number,
                'product_code' => $row->product_code,
                'product_name' => $row->product_name,
                'quantity' => abs($row->quantity),
                'direction' => $row->quantity > 0 ? 'in' : 'out',
                'warehouse_name' => $row->warehouse_name ?? '—',
                'user_name' => $row->user_name ?? '—',
            ])
            ->all();
    }
}
