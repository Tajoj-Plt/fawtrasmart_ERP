<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Store\Brand;

class BrandApiController extends Controller
{
    /**
     * كانت هذه الشاشة بلا أي حماية صلاحية — `auth:sanctum` وحده في
     * `Modules/Stock/routes/api.php` (`Route::apiResource`)، فأي مستخدمٍ
     * موثَّق (حتى بلا أي صلاحية) كان يستطيع إنشاء العلامات التجارية
     * وتعديلها وحذفها عبر الـAPI. نفس صلاحيات [BrandController] — شاشتا
     * ويب وAPI لعلامةٍ تجاريةٍ واحدة لا يُعقَل أن تفترقا في الحراسة.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.brands.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:stock.brands.create', ['only' => ['store']]);
        $this->middleware('permission.role:stock.brands.edit', ['only' => ['update']]);
        $this->middleware('permission.role:stock.brands.delete', ['only' => ['destroy']]);
    }

    /**
     * جلب جميع البراندات (API)
     */
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query();

        if ($request->filled('search')) {
            $brands->search($request->search);
        }

        if ($request->filled('status')) {
            $brands->where('status', $request->status);
        }

        if ($request->filled('featured') && $request->featured === 'yes') {
            $brands->featured();
        }

        $brands = $brands->ordered()->paginate($request->get('per_page', 15));

        return response()->json($brands);
    }

    /**
     * جلب براند واحد (API)
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'brand' => $brand,
            'products_count' => $brand->products()->count(),
            'total_stock_value' => $brand->products()
                ->join('product_details', 'products.id', '=', 'product_details.product_id')
                ->sum(\Illuminate\Support\Facades\DB::raw('product_details.quantity * product_details.unit_price')),
            'total_stock_quantity' => $brand->products()
                ->join('product_details', 'products.id', '=', 'product_details.product_id')
                ->sum('product_details.quantity'),
        ]);
    }

    /**
     * إنشاء براند جديد (API)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name',
            'description' => 'nullable|string|max:1000',
            'country_of_origin' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:500',
            'status' => 'required|in:active,inactive,suspended',
            'is_featured' => 'nullable|boolean',
        ]);

        $brand = Brand::create($validated);

        return response()->json([
            'message' => 'تم إنشاء البراند بنجاح',
            'brand' => $brand,
        ], 201);
    }

    /**
     * تحديث براند (API)
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name,' . $brand->id,
            'description' => 'nullable|string|max:1000',
            'country_of_origin' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:500',
            'status' => 'required|in:active,inactive,suspended',
            'is_featured' => 'nullable|boolean',
        ]);

        $brand->update($validated);

        return response()->json([
            'message' => 'تم تحديث البراند بنجاح',
            'brand' => $brand,
        ]);
    }

    /**
     * حذف براند (API)
     */
    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف البراند لأنه يحتوي على منتجات',
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'message' => 'تم حذف البراند بنجاح',
        ]);
    }
}
