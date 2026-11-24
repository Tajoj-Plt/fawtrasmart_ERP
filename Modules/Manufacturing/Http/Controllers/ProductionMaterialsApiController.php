<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;

class ProductionMaterialsApiController extends Controller
{
    use SafeListing, ScopesToBranch;

    /**
     * صلاحيةُ الشاشة نفسِها على الواجهة نفسِها.
     *
     * الحارس مكرَّرٌ هنا وفي `Modules/Stock/routes/api.php` عمداً: المسارُ
     * كان مفتوحاً بلا مصادقةٍ إطلاقاً، وحارسٌ في ملفِّ مساراتٍ واحد يسقط
     * بنقل سطرٍ أو بتسجيل المتحكّم من موضعٍ ثانٍ. والبيانات التي يُرجعها
     * — تركيبةُ المنتج وأسعارُ شراء مواده — لا تحتمل حارساً واحداً.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.bom.view');
    }

    /**
     * جلب عناصر قائمة المواد (BOM)
     *
     * @param  int  $id  معرف قائمة المواد أو معرف المنتج
     * @return JsonResponse
     */
    public function getItems($id)
    {
        try {
            /*
                البحثُ داخل نطاق الفرع النشط لا في الجدول كلّه.

                `{id}` يقبل معرّف القائمة أو معرّف المنتج، والعدُّ من ١ —
                فبلا هذا القيد تُقرأ وصفاتُ الفروع كلِّها من فرعٍ واحد
                بحلقة `for`، وهي أخصُّ ما تملكه منشأةٌ صناعية.
            */
            $productionMaterial = $this->scopeToActiveBranch(ProductionMaterials::query())
                ->whereKey($id)
                ->first();

            // إذا لم نجدها، نبحث بـ product_id
            if (! $productionMaterial) {
                $productionMaterial = $this->scopeToActiveBranch(ProductionMaterials::query())
                    ->where('product_id', $id)
                    ->where('status', 1) // نشط
                    ->first();
            }

            // إذا لم نجد أي قائمة مواد
            if (! $productionMaterial) {
                return response()->json([
                    'success' => false,
                    'message' => 'قائمة المواد غير موجودة',
                ], 404);
            }

            // جلب عناصر قائمة المواد مع معلومات المنتج (المواد الخام)
            $items = ProductionMaterialsItem::where('production_material_id', $productionMaterial->id)
                ->whereNotNull('raw_product_id') // فقط المواد الخام
                ->where('raw_product_id', '>', 0)
                ->with(['rawProduct' => function ($query) {
                    $query->select('id', 'name', 'sale_price', 'purchase_price', 'sub_unit_id');
                }, 'rawProduct.subUnit'])
                ->get()
                ->map(function ($item) {
                    $unitPrice = $item->raw_unit_price ?? $item->rawProduct->purchase_price ?? $item->rawProduct->sale_price ?? 0;
                    $unitId = $item->rawProduct->sub_unit_id ?? null;
                    $unitName = $item->rawProduct->subUnit->larger_unit_name ?? 'وحدة';

                    return [
                        'id' => $item->id,
                        'material_name' => $item->rawProduct->name ?? 'غير محدد',
                        'product_id' => $item->raw_product_id,
                        'quantity_per_unit' => $item->raw_quantity ?? 0,
                        'quantity' => $item->raw_quantity ?? 0,
                        'unit_id' => $unitId,
                        'unit' => $unitName,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $item->raw_total ?? 0,
                        'production_stage_id' => $item->raw_production_stage_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $productionMaterial->id,
                    'name' => $productionMaterial->name,
                    'product_id' => $productionMaterial->product_id,
                    'items' => $items,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, ManufacturingSources::LOG_BOM, 'getItems');

            /*
                نصُّ الاستثناء لا يُرسَل إلى العميل.

                كان `'error' => $e->getMessage()` يُخرج أسماءَ الأعمدة
                والجداول ومسارات الملفات في استجابةٍ عامّة — وهي أوّل ما
                يُبنى عليه استكشافُ قاعدة البيانات من الخارج. والنصُّ
                الكامل محفوظٌ في «سجلّ الأخطاء» أعلاه لمن يملك شاشته.
            */
            return response()->json([
                'success' => false,
                'message' => $this->safeError($e, 'حدث خطأ أثناء جلب البيانات'),
            ], 500);
        }
    }
}
