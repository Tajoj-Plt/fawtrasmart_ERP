<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class TestManufacturingCalculatorController extends Controller
{
    use UsesDocumentCurrency;

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار.
     *
     * والحاسبة تعرض تكلفةً وتكلفةَ وحدة، فالحساب التفصيلي يشترط صلاحيةَ
     * تفصيل التكلفة كما في المسار — لا `view` وحدها.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.manufacturing_orders.view')->only(['index']);
        $this->middleware('permission.role:stock.manufacturing_orders.cost_breakdown')->only(['calculate']);
    }

    /**
     * عرض صفحة حاسبة التصنيع
     */
    public function index()
    {
        return view('manufacturing::test-calculator', [
            // العملة من شاشة «العملات» — كانت «ر.س» مكتوبةً في القالب
            'displayCurrency' => $this->defaultCurrency(),
        ]);
    }

    /**
     * حساب احتياجات المواد الخام للتصنيع
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'product_name' => 'required|string',
            'production_quantity' => 'required|numeric|min:1',
            'unit_size' => 'required|numeric|min:0',
            'unit_size_type' => 'required|string',
            'materials' => 'required|array|min:1',
            'materials.*.name' => 'required|string',
            'materials.*.quantity_per_unit' => 'required|numeric|min:0',
            'materials.*.unit' => 'required|string',
            'materials.*.price_per_unit' => 'nullable|numeric|min:0',
        ]);

        $productName = $request->product_name;
        $productionQuantity = $request->production_quantity;
        $unitSize = $request->unit_size;
        $unitSizeType = $request->unit_size_type;
        $materials = $request->materials;

        // حساب إجمالي المواد المطلوبة
        $calculatedMaterials = [];
        $totalCost = 0;

        foreach ($materials as $material) {
            $materialName = $material['name'];
            $quantityPerUnit = $material['quantity_per_unit'];
            $unit = $material['unit'];
            $pricePerUnit = $material['price_per_unit'] ?? 0;

            // حساب الإجمالي
            $totalQuantity = $productionQuantity * $quantityPerUnit;

            // حساب التكلفة
            $materialCost = $totalQuantity * $pricePerUnit;
            $totalCost += $materialCost;

            // تحويل من مل إلى لتر إذا كانت الوحدة مل
            $totalInLiters = null;
            if (strtolower($unit) === 'مل' || strtolower($unit) === 'ml') {
                $totalInLiters = $totalQuantity / 1000;
            }

            $calculatedMaterials[] = [
                'name' => $materialName,
                'quantity_per_unit' => $quantityPerUnit,
                'unit' => $unit,
                'total_quantity' => $totalQuantity,
                'total_in_liters' => $totalInLiters,
                'price_per_unit' => $pricePerUnit,
                'material_cost' => $materialCost,
            ];
        }

        // حساب إجمالي حجم المنتج النهائي
        $totalProductVolume = $productionQuantity * $unitSize;

        // تحويل إلى لتر إذا كانت الوحدة مل
        $totalProductVolumeInLiters = null;
        if (strtolower($unitSizeType) === 'مل' || strtolower($unitSizeType) === 'ml') {
            $totalProductVolumeInLiters = $totalProductVolume / 1000;
        } elseif (strtolower($unitSizeType) === 'لتر' || strtolower($unitSizeType) === 'liter') {
            $totalProductVolumeInLiters = $totalProductVolume;
        }

        // تحويل إلى كجم إذا كانت الوحدة جرام
        $totalProductVolumeInKg = null;
        if (strtolower($unitSizeType) === 'جرام' || strtolower($unitSizeType) === 'g') {
            $totalProductVolumeInKg = $totalProductVolume / 1000;
        } elseif (strtolower($unitSizeType) === 'كجم' || strtolower($unitSizeType) === 'kg') {
            $totalProductVolumeInKg = $totalProductVolume;
        }

        // حساب تكلفة الوحدة الواحدة
        $costPerUnit = $productionQuantity > 0 ? $totalCost / $productionQuantity : 0;

        return view('manufacturing::test-calculator', [
            'calculated' => true,
            'product_name' => $productName,
            'production_quantity' => $productionQuantity,
            'unit_size' => $unitSize,
            'unit_size_type' => $unitSizeType,
            'total_product_volume' => $totalProductVolume,
            'total_product_volume_in_liters' => $totalProductVolumeInLiters,
            'total_product_volume_in_kg' => $totalProductVolumeInKg,
            'materials' => $materials,
            'calculated_materials' => $calculatedMaterials,
            'total_cost' => $totalCost,
            'cost_per_unit' => $costPerUnit,
            // العملة من شاشة «العملات» لا من نصٍّ في القالب
            'displayCurrency' => $this->defaultCurrency(),
        ]);
    }
}
