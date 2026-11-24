<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\ProductBarcodeSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;

class BarcodeSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:stock.barcodes.settings');
    }

    /**
     * عرض صفحة إعدادات الباركود
     */
    public function index()
    {
        $settings = ProductBarcodeSettings::getCurrent();

        return view('stock::products_settings.barcode_settings', compact('settings'));
    }

    /**
     * حفظ إعدادات الباركود
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'barcode_type' => 'required|in:CODE128,EAN13',
                'include_weight' => 'boolean',
                'barcode_format' => 'nullable|string',
                'weight_unit_division' => 'nullable|numeric',
                'currency_division' => 'nullable|numeric',
                'barcode_width' => 'required|integer|min:1|max:10',
                'barcode_height' => 'required|integer|min:20|max:200',
                'display_value' => 'boolean',
                'font_size' => 'required|integer|min:8|max:24',
                'margin' => 'required|integer|min:0|max:50',
            ]);

            // الحصول على الإعدادات الحالية أو إنشاء جديدة
            $settings = ProductBarcodeSettings::first() ?? new ProductBarcodeSettings;

            // تحديث البيانات
            $settings->update($validated);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حفظ الإعدادات بنجاح',
                    'settings' => $settings,
                ]);
            }

            return redirect()
                ->route('barcode_settings.index')
                ->with('success', 'تم حفظ إعدادات الباركود بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'BarcodeSettings', 'store');

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * الحصول على الإعدادات بصيغة JSON (للاستخدام في JavaScript)
     */
    public function getSettings()
    {
        $settings = ProductBarcodeSettings::getCurrent();

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }
}
