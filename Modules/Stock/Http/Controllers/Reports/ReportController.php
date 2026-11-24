<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class ReportController extends Controller
{
    use ScopesToEmployeeHierarchy, RespectsAccountingPeriods;

    /**
     * حماية على مستوى المتحكّم بجانب الراوت — كل تقارير المخزون تشترك في
     * صلاحية دخولٍ واحدة (`stock.reports.view`) كما في
     * `Modules/Stock/routes/web.php`؛ متحكّمٌ فرعي يحتاج صلاحيةً أخصّ
     * يضيفها في مُنشئه الخاص (`parent::__construct()` أولاً).
     *
     * نطاق البيانات في كل تقرير: معاملات (منشئها/مستودعها) نطاقي —
     * أنا نفسي، أو أنا ومن أديرهم إدارياً إن كنت أديرهم — عبر
     * `ScopesToEmployeeHierarchy` (`default_warehouses`/`direct_manager_id`)،
     * لا نطاقات `data_scope_*` الهرمية القديمة ولا أي فحص اسم دورٍ يدوي.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.reports.view');
    }

    /**
     * يملأ تاريخَي التقرير بالفترة المحاسبية الجارية إن لم يحدّدهما المستخدم.
     *
     * كانت كل شاشة تكتب `startOfMonth()` بيدها، فلا علاقة لما تعرضه
     * بالفترة التي تعمل المنشأة عليها فعلاً. تُستدعى هذه في أول `index()`
     * فتصير الشاشة تابعةً لإعداد الفترات، ويبقى اختيار المستخدم مقدَّماً
     * على الافتراضي دائماً.
     *
     * @return array{start: string, end: string, period: ?\App\Models\AccountingPeriod, is_default: bool}
     */
    protected function applyDefaultReportDates(
        Request $request,
        string $startKey = 'start_date',
        string $endKey = 'end_date'
    ): array {
        $range = $this->reportDateRange($request, $startKey, $endKey);

        $request->merge([
            $startKey => $range['start'],
            $endKey => $range['end'],
        ]);

        return $range;
    }

    /**
     * Get warehouse names for a product
     */
    protected function getWarehouseNames($product)
    {
        if ($product->product_details && $product->product_details->storeHouse) {
            return $product->product_details->storeHouse->name;
        }

        return 'غير محدد';
    }

    /**
     * Get group by label
     */
    protected function getGroupByLabel($groupBy)
    {
        switch ($groupBy) {
            case 'category':
                return 'التصنيف';
            case 'warehouse':
                return 'المستودع';
            case 'movement_type':
                return 'نوع الحركة';
            default:
                return null;
        }
    }

    /**
     * Get current stock from product_details table
     */
    protected function getCurrentStockFromProductDetails($productId, $storeHouseId)
    {
        try {
            $productDetail = ProductDetails::where('product_id', $productId)
                ->where('store_house_id', $storeHouseId)
                ->first();

            return $productDetail ? $productDetail->quantity : 0;
        } catch (\Exception $e) {
            Log::error("خطأ في جلب المخزون من product_details: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate stock after movement
     */
    protected function calculateStockAfterMovement($movement, $currentStock)
    {
        // If the movement is old, we need to calculate the stock at that time
        // Here you can add more complex logic according to the type of movement

        $movementQuantity = $movement->quantity ?? 0;

        // According to the type of movement, we may need to adjust the calculation
        switch ($movement->type) {
            case '2': // Sales invoice (exit)
            case '6': // Returned invoice
                $stockAfter = $currentStock - abs($movementQuantity);
                break;
            case '3': // Purchase invoice (entry)
            case '7': // Purchase returns
                $stockAfter = $currentStock + abs($movementQuantity);
                break;
            case '1': // Manual adjustment
                $stockAfter = $movementQuantity; // The new value directly
                break;
            case '5': // Stock transfer
                // Here we need to check the direction of transfer
                $stockAfter = $currentStock + $movementQuantity;
                break;
            default:
                $stockAfter = $currentStock + $movementQuantity;
                break;
        }

        return max(0, $stockAfter); // Stock cannot be negative
    }

    /**
     * Calculate total stock value from product_details
     */
    protected function calculateTotalStockValue($productIds, $warehouseId = null)
    {
        try {
            $query = ProductDetails::join('products', 'product_details.product_id', '=', 'products.id')
                ->selectRaw('SUM(product_details.quantity * products.purchase_price) as total_value');

            if (!empty($productIds)) {
                $query->whereIn('product_details.product_id', $productIds);
            }

            if ($warehouseId) {
                $query->where('product_details.store_house_id', $warehouseId);
            }

            $result = $query->first();
            return $result ? $result->total_value : 0;

        } catch (\Exception $e) {
            Log::error("خطأ في حساب قيمة المخزون: " . $e->getMessage());
            return 0;
        }
    }
}