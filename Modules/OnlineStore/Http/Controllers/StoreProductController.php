<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\OnlineStoreOptionPresets;
use App\Constants\permissions\PermissionSources;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Log as ModelsLog;
use App\Models\PermissionSource;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\Store\Brand;
use App\Models\Store\ProductOption;
use App\Models\Store\ProductOptionValue;
use App\Models\Store\ProductVariant;
use App\Models\StoreHouse;
use App\Models\TaxSitting;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreWarehouses;
use Modules\OnlineStore\Support\StoreAssignmentScope;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * كنترولر إدارة منتجات المتجر الإلكتروني
 * Store Product Management Controller
 */
class StoreProductController extends Controller
{
    use SanitizesFilters;

    // مخازن المتجر المرئيّة (المسنَدة للمستخدم ولتابعيه ∩ فرع الشريط) — تحتاج `scopeUser()` و
    // `storehouseViewableIds()` من سمة المخزون؛ غيابها كان يُسقط شاشة تسوية المخزون وأرصدتها بخطأ 500
    use ScopesStoreWarehouses;
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.products.view', ['only' => ['index']]);
        $this->middleware('permission.role:online_store.products.show', ['only' => ['show', 'getStockMovements', 'getTimeline', 'getActivityLogs', 'printBarcode']]);
        $this->middleware('permission.role:online_store.products.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.products.edit', ['only' => ['edit', 'update', 'removeImage']]);
        $this->middleware('permission.role:online_store.products.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.products.toggle_status', ['only' => ['toggleStatus', 'toggleOnline', 'toggleFeatured']]);
        $this->middleware('permission.role:online_store.products.export', ['only' => ['export']]);
        $this->middleware('permission.role:online_store.products.manual_stock_adjust', ['only' => ['manualStockAdjust', 'addManualStockAdjust', 'productStockInStore']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'name' => 'اسم المنتج',
        'description' => 'الوصف',
        'category_id' => 'الصنف',
        'brand_id' => 'العلامة التجارية',
        'barcode' => 'الباركود',
        'purchase_price' => 'سعر الشراء',
        'sale_price' => 'سعر البيع',
        'min_sale_price' => 'أقل سعر بيع',
        'discount' => 'الخصم',
        'discount_type' => 'نوع الخصم',
        'tax1' => 'الضريبة الأولى',
        'tax2' => 'الضريبة الثانية',
        'weight' => 'الوزن',
        'dimensions' => 'الأبعاد',
        'is_returnable' => 'قابل للإرجاع',
        'return_days' => 'مدة الإرجاع',
        'available_online' => 'يُعرض في المتجر',
        'available_pos' => 'متاح في نقاط البيع',
        'featured_product' => 'منتج مميز',
        'track_inventory' => 'تتبع المخزون',
        'low_stock_alert' => 'تنبيه انخفاض المخزون',
        'low_stock_thershold' => 'حد التنبيه',
        'status' => 'الحالة',
        'Internal_notes' => 'ملاحظات داخلية',
        'images' => 'صور المنتج',
        'images.*' => 'صورة المنتج',
        'variants' => 'المتغيّرات',
        'variants.*.sku' => 'رمز المتغيّر (SKU)',
        'variants.*.barcode' => 'باركود المتغيّر',
        'variants.*.price_modifier' => 'فرق السعر',
        'variants.*.override_price' => 'السعر البديل',
        'variants.*.compare_at_price' => 'السعر قبل الخصم',
        'variants.*.cost_price' => 'تكلفة المتغيّر',
        'variants.*.stock_quantity' => 'كمية المتغيّر',
        'variants.*.image' => 'صورة المتغيّر',
        'options' => 'الخيارات',
        'options.*.name' => 'اسم الخيار',
        'options.*.values.*.value' => 'قيمة الخيار',
        'quantity' => 'الكمية',
        'type' => 'نوع العملية',
        'unit_price' => 'سعر الوحدة',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'store_house_id' => 'المستودع',
        'attachments' => 'المرفق',
    ];

    /** قواعد حفظ المنتج — واحدةٌ للإنشاء والتعديل. */
    private function productRules(?Product $product = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'purchase_price' => 'required|numeric|min:0|max:99999999',
            'sale_price' => 'required|numeric|min:0|max:99999999',
            'min_sale_price' => 'nullable|numeric|min:0|lte:sale_price',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:1,2,percentage,fixed',
            /*
                `products.tax1/tax2` معرّفُ ضريبةٍ من شاشة «الضرائب» (tax_sittings)
                في كل النظام — لا نسبةٌ مكتوبة باليد. نموذج المتجر كان يكتب
                النسبة نفسها (15) في العمود، فيقرؤها المخزون والمبيعات معرّفاً
                لضريبةٍ لا وجود لها.
            */
            'tax1' => 'nullable|integer|exists:tax_sittings,id',
            'tax2' => 'nullable|integer|exists:tax_sittings,id|different:tax1',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:255',
            'is_returnable' => 'nullable|boolean',
            'return_days' => 'nullable|integer|min:0|max:3650',
            'available_online' => 'nullable|boolean',
            'available_pos' => 'nullable|boolean',
            'featured_product' => 'nullable|boolean',
            'track_inventory' => 'nullable|boolean',
            'low_stock_alert' => 'nullable|boolean',
            'low_stock_thershold' => 'nullable|integer|min:0',
            // حالةٌ خارج القائمة كانت تُسقط الحفظ بخطأ 500
            'status' => ['required', Rule::in(array_column(ProductStatus::cases(), 'value'))],
            'Internal_notes' => 'nullable|string',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,jpg,png,gif,webp|max:2048',
            // المتغيّرات: أرقامٌ فعلاً — نصٌّ في حقل سعر كان يصل قاعدة البيانات خاماً
            'variants' => 'nullable|array',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.price_modifier' => 'nullable|numeric|min:-99999999|max:99999999',
            'variants.*.override_price' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.compare_at_price' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.cost_price' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.stock_quantity' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'options' => 'nullable|array',
            'options.*.name' => 'nullable|string|max:100',
            'options.*.values.*.value' => 'nullable|string|max:100',
        ];
    }

    private const PRODUCT_MESSAGES = [
        'dimensions.string' => 'الأبعاد يجب أن تكون بصيغة نصية',
        'dimensions.max' => 'الأبعاد يجب ألا تتجاوز 255 حرف',
        'min_sale_price.lte' => 'أقل سعر بيع يجب ألا يزيد على سعر البيع.',
        'tax2.different' => 'الضريبة الثانية يجب أن تختلف عن الأولى.',
        'tax1.exists' => 'الضريبة الأولى غير معرَّفة في شاشة الضرائب.',
        'tax2.exists' => 'الضريبة الثانية غير معرَّفة في شاشة الضرائب.',
    ];

    /** الخصم لا يتجاوز السعر: نسبةٌ ≤ 100، ومبلغٌ ≤ سعر البيع. */
    private function assertDiscountWithinPrice(array $validated): void
    {
        $discount = (float) ($validated['discount'] ?? 0);
        if ($discount <= 0) {
            return;
        }

        $isPercentage = ((int) ($validated['discount_type'] ?? 1)) === 1;

        if ($isPercentage && $discount > 100) {
            throw ValidationException::withMessages(['discount' => 'نسبة الخصم لا تتجاوز 100%.']);
        }

        if (! $isPercentage && $discount > (float) $validated['sale_price']) {
            throw ValidationException::withMessages(['discount' => 'مبلغ الخصم لا يصح أن يزيد على سعر البيع.']);
        }
    }

    /** الضرائب المعرَّفة في «الإعدادات ← الضرائب» — لقائمتَي الضريبة في النموذج. */
    private function taxOptions()
    {
        return TaxSitting::orderBy('id')->get();
    }

    /**
     * عرض قائمة جميع المنتجات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        // شاشة المتجر تدير المنتجات المخزنية فقط — لا خدمية ولا تجميعية ولا رقمية
        $query = Product::query()->where('type', ProductType::PRODUCTS->value);

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الحالة
        if (is_string($request->status) && ProductStatus::tryFrom($request->status)) {
            $query->where('status', $request->status);
        }

        // فلترة حسب التصنيف
        if ($request->filled('category_id') && is_numeric($request->category_id)) {
            $query->where('category_id', (int) $request->category_id);
        }

        // فلترة حسب العلامة التجارية
        if ($request->filled('brand_id') && is_numeric($request->brand_id)) {
            $query->where('brand_id', (int) $request->brand_id);
        }

        // فلترة المنتجات المتاحة أونلاين فقط
        if ($request->filled('available_online')) {
            $query->where('available_online', $request->available_online == '1');
        }

        // فلترة المنتجات المميزة
        if ($request->filled('featured')) {
            $query->where('featured_product', $request->featured == '1');
        }

        $products = $query->with(['category', 'brand'])
            ->withCount('variants')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // إحصائيات — بنفس نطاق المنتجات المخزنية أعلاه
        $stockProducts = Product::where('type', ProductType::PRODUCTS->value);
        $stats = [
            'total' => (clone $stockProducts)->count(),
            'active' => (clone $stockProducts)->where('status', ProductStatus::ACTIVE->value)->count(),
            'available_online' => (clone $stockProducts)->where('available_online', true)->count(),
            'featured' => (clone $stockProducts)->where('featured_product', true)->count(),
            'low_stock' => (clone $stockProducts)->where('low_stock_alert', true)->count(),
        ];

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->where('is_active', true)->get();

        return view('onlinestore::products.index', compact('products', 'stats', 'categories', 'brands'));
    }

    /**
     * عرض صفحة إنشاء منتج جديد
     */
    public function create()
    {
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->where('is_active', true)->get();
        $productStatuses = ProductStatus::cases();
        $optionTemplates = collect(OnlineStoreOptionPresets::PRESETS);
        $taxOptions = $this->taxOptions();

        return view('onlinestore::products.create', compact('categories', 'brands', 'productStatuses', 'optionTemplates', 'taxOptions'));
    }

    /**
     * حفظ منتج جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->productRules(), self::PRODUCT_MESSAGES, self::ATTRIBUTES);
        unset($validated['variants'], $validated['options']);

        // تحويل discount_type إلى رقم (1 = percentage, 2 = fixed)
        if (isset($validated['discount_type'])) {
            $validated['discount_type'] = $this->convertDiscountType($validated['discount_type']);
        }

        // تنسيق الأبعاد تلقائياً
        if (isset($validated['dimensions']) && ! empty($validated['dimensions'])) {
            $formattedDimensions = $this->formatDimensions($validated['dimensions']);

            if ($formattedDimensions === null) {
                return back()->withErrors([
                    'dimensions' => 'صيغة الأبعاد غير صحيحة. الرجاء استخدام صيغة: {"length":"10","width":"10","height":"10"} أو 10x10x10',
                ])->withInput();
            }

            $validated['dimensions'] = $formattedDimensions;
        } else {
            $validated['dimensions'] = null;
        }

        // تعيين القيم الافتراضية
        $validated['available_online'] = $request->boolean('available_online', false);
        $validated['available_pos'] = $request->boolean('available_pos');
        $validated['featured_product'] = $request->boolean('featured_product', false);
        $validated['track_inventory'] = $request->boolean('track_inventory');
        $validated['low_stock_alert'] = $request->boolean('low_stock_alert', false);
        $validated['is_returnable'] = $request->boolean('is_returnable', true);
        $validated['created_by'] = auth()->id();

        $this->assertDiscountWithinPrice($validated);

        // شاشة المتجر لا تُنشئ إلا منتجاً مخزنياً — لا خدمياً ولا تجميعياً
        // ولا رقمياً، ولا يُقرأ هذا من الطلب مهما أُرسل فيه
        $validated['type'] = ProductType::PRODUCTS->value;

        // التعامل مع رفع الصور
        if ($request->hasFile('images')) {
            $imagesArray = [];
            foreach ($request->file('images') as $image) {
                $imagesArray[] = $this->uploadImage('assets/uploads/products', $image);
            }
            $validated['images'] = json_encode($imagesArray);
        }

        // المنتج وخياراته ومتغيّراته معاً أو لا شيء — ورقمه من «الترقيم المتسلسل»
        // (قسم المنتجات) كما في شاشة المخزون، لا فارغاً
        $product = DB::transaction(function () use ($validated, $request) {
            $validated['serial_number'] = next_document_number('products');

            $product = Product::create($validated);

            if ($request->filled('options')) {
                $this->saveProductOptions($product, $request);
            }

            if ($request->filled('variants')) {
                $this->saveProductVariants($product, $request);
            }

            return $product;
        });

        OperationLog::record('created', 'product', $product, 'تم إنشاء منتج **'.$product->name.'**');

        return redirect()->route('store_products.index')
            ->with('success', 'تم إضافة المنتج بنجاح');
    }

    /**
     * حفظ/تحديث خيارات المنتج (وقيمها) من مصفوفة `options` في الطلب.
     *
     * كل عنصرٍ: `{id?, name, label_ar?, values: [{id?, value, label_ar?, color_code?}]}`.
     * وجودُ `id` يعني تحديث خيارٍ/قيمةٍ قائمة، وغيابه يعني إنشاء جديد.
     * الحذف الصريح عبر `delete_options[]`/`delete_option_values[]` — انظر syncProductOptions().
     *
     * @return array<string, int> خريطة "index الخيار-index القيمة" => معرّف ProductOptionValue
     */
    private function saveProductOptions(Product $product, Request $request): array
    {
        $optionsInput = $request->input('options', []);
        $valueIdMap = [];

        // حذفٌ صريح أولاً — الخيار المحذوف يسحب قيمه (cascade)، وأي متغيّرٍ
        // كان مرتبطاً بها يفقد تلك الحلقة من الربط وحدها لا كل سجلّه
        if ($request->filled('delete_options')) {
            ProductOption::whereIn('id', $request->input('delete_options'))
                ->where('product_id', $product->id)
                ->delete();
        }

        if ($request->filled('delete_option_values')) {
            ProductOptionValue::whereIn('id', $request->input('delete_option_values'))
                ->whereHas('option', fn ($q) => $q->where('product_id', $product->id))
                ->delete();
        }

        foreach ($optionsInput as $optionIndex => $optionData) {
            if (blank($optionData['name'] ?? null)) {
                continue;
            }

            $optionId = $optionData['id'] ?? null;

            if ($optionId) {
                $option = ProductOption::where('product_id', $product->id)->find($optionId);
            }

            if (empty($option)) {
                $option = new ProductOption(['product_id' => $product->id]);
            }

            $option->name = $optionData['name'];
            $option->label_ar = $optionData['label_ar'] ?? $optionData['name'];
            $option->label_en = $optionData['label_en'] ?? null;
            $option->position = $optionIndex;
            $option->save();

            foreach ($optionData['values'] ?? [] as $valueIndex => $valueData) {
                if (blank($valueData['value'] ?? null)) {
                    continue;
                }

                $valueId = $valueData['id'] ?? null;
                $value = $valueId ? $option->values()->find($valueId) : null;

                if (empty($value)) {
                    $value = new ProductOptionValue(['option_id' => $option->id]);
                }

                $value->value = $valueData['value'];
                $value->label_ar = $valueData['label_ar'] ?? $valueData['value'];
                $value->label_en = $valueData['label_en'] ?? null;
                // نمط hex فقط — الحقل مُدخَلٌ عبر <input type="color"> في الواجهة، وأي
                // نصٍّ آخر يصل مباشرة عبر الطلب يُتجاهَل بدل أن يُطبع كما هو في style="..."
                $value->color_code = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($valueData['color_code'] ?? ''))
                    ? $valueData['color_code']
                    : null;
                $value->position = $valueIndex;
                $value->save();

                $valueIdMap["{$optionIndex}-{$valueIndex}"] = $value->id;
            }
        }

        return $valueIdMap;
    }

    /**
     * حفظ/تحديث متغيّرات المنتج من مصفوفة `variants` في الطلب.
     *
     * كل عنصرٍ: `{id?, option_values: {optionIndex: valueIndex}, sku, price_modifier,
     * override_price?, compare_at_price?, cost_price?, stock_quantity, is_active}`،
     * بجانب صورةٍ اختيارية عبر `variants[INDEX][image]`. `option_values` تُترجَم
     * إلى معرّفات حقيقية عبر خريطة saveProductOptions()، ومنها تُبنى لقطة
     * variant_options القابلة للعرض المباشر.
     */
    private function saveProductVariants(Product $product, Request $request): void
    {
        $variantsInput = $request->input('variants', []);

        if ($request->filled('delete_variants')) {
            ProductVariant::whereIn('id', $request->input('delete_variants'))
                ->where('product_id', $product->id)
                ->delete();
        }

        // خريطة index الخيار => تسميته، وindex القيمة => تسميتها — لبناء
        // لقطة variant_options من نفس القيم المحفوظة للتوّ في saveProductOptions()
        $optionLabels = [];
        $valueLabels = [];
        $optionValueIds = [];

        foreach ($product->options()->with('values')->get() as $option) {
            // ترتيب الحفظ أعلاه يضمن position = index الإرسال نفسه
            $optionLabels[$option->position] = $option->displayLabel();

            foreach ($option->values as $value) {
                $valueLabels["{$option->position}-{$value->position}"] = $value->displayLabel();
                $optionValueIds["{$option->position}-{$value->position}"] = $value->id;
            }
        }

        foreach ($variantsInput as $index => $variantData) {
            /*
                array_filter() الافتراضي يُسقط القيمة النصية "0" بوصفها فارغة —
                وهي فهرس القيمة الأولى المشروعة لأي خيار (اللون الأول، القياس
                الأول...). متغيّرٌ اخترتَ له أول قيمة في خيارٍ واحد فقط (شائع في
                منتج بخيار لون وحيد) كان يفقد اختياره بالكامل هنا، فيسقط لاحقاً
                من الحفظ عبر شرط blank(sku) أدناه — وهذا كان السبب الفعلي
                لعدم حفظ إلا متغيّر واحد من كل مجموعة.
            */
            $selectedValues = array_filter(
                $variantData['option_values'] ?? [],
                fn ($value) => $value !== null && $value !== ''
            );

            if (empty($selectedValues) && blank($variantData['sku'] ?? null)) {
                continue;
            }

            $variantId = $variantData['id'] ?? null;
            $variant = $variantId ? ProductVariant::where('product_id', $product->id)->find($variantId) : null;

            if (empty($variant)) {
                $variant = new ProductVariant(['product_id' => $product->id]);
            }

            $variantOptionsSnapshot = [];
            $resolvedValueIds = [];

            foreach ($selectedValues as $optionIndex => $valueIndex) {
                $key = "{$optionIndex}-{$valueIndex}";

                if (! isset($optionValueIds[$key])) {
                    continue;
                }

                $variantOptionsSnapshot[$optionLabels[$optionIndex] ?? "خيار {$optionIndex}"] = $valueLabels[$key];
                $resolvedValueIds[] = $optionValueIds[$key];
            }

            // لا عمود `sku` في `products` — كان الرمز الافتراضي يخرج «-1»، «-2»
            // لكل منتج. الأساس رقم المنتج المتسلسل (أو معرّفه).
            $skuBase = $product->serial_number ?: ('P'.$product->id);
            $variant->sku = filled($variantData['sku'] ?? null)
                ? $variantData['sku']
                : ($variant->sku ?: $skuBase.'-'.($index + 1));
            $variant->barcode = $variantData['barcode'] ?? null;
            $variant->variant_options = $variantOptionsSnapshot;
            $variant->price_modifier = filled($variantData['price_modifier'] ?? null) ? $variantData['price_modifier'] : 0;
            $variant->override_price = filled($variantData['override_price'] ?? null) ? $variantData['override_price'] : null;
            $variant->compare_at_price = filled($variantData['compare_at_price'] ?? null) ? $variantData['compare_at_price'] : null;
            $variant->cost_price = filled($variantData['cost_price'] ?? null) ? $variantData['cost_price'] : null;
            // كمية المتغيّر حقلٌ وصفيّ فقط: مخزون المتجر هو رصيد المنتج الأساسي
            // في مستودع المتجر (انظر Api\StoreProductController::formatProductVariants)
            $variant->stock_quantity = filled($variantData['stock_quantity'] ?? null) ? $variantData['stock_quantity'] : 0;
            $variant->is_active = ! empty($variantData['is_active']);

            if ($request->hasFile("variants.{$index}.image")) {
                $variant->image_url = $this->uploadImage('assets/uploads/variants', $request->file("variants.{$index}.image"));
            }

            $variant->save();

            if (! empty($resolvedValueIds)) {
                $variant->optionValues()->sync($resolvedValueIds);
            }
        }
    }

    /**
     * رفع الصورة وحفظها
     */
    private function uploadImage($folder, $image)
    {
        $path = public_path($folder);
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // الامتداد من نوع الملف الفعلي لا من اسمه — ملفٌ باسم `x.php` لا يُحفظ `.php`.
        // واسمٌ فريد: `time().rand(1,99)` كان يتصادم عند رفع عدّة صور في الثانية نفسها
        // فتكتب صورةٌ فوق أخرى.
        $fileExtension = $image->extension() ?: 'png';
        $fileName = time().'_'.uniqid().'.'.$fileExtension;
        $image->move($path, $fileName);

        return $folder.'/'.$fileName;
    }

    /**
     * تحويل نوع الخصم من نص إلى رقم
     * Convert discount type from string to integer
     * 1 = percentage (نسبة مئوية)
     * 2 = fixed (مبلغ ثابت)
     */
    private function convertDiscountType($value)
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return match ($value) {
            'percentage' => 1,
            'fixed' => 2,
            default => 1, // الافتراضي نسبة مئوية
        };
    }

    /**
     * تنسيق الأبعاد إلى صيغة JSON
     * Format dimensions to JSON format (since DB column is JSON type)
     *
     * القيمة المقبولة:
     * - JSON: {"length":"10","width":"10","height":"9.98"}
     * - النص المباشر: "10x10x9.98" أو "10 x 10 x 9.98"
     *
     * @param  string|null  $dimensions
     * @return array|null Returns array for JSON column
     */
    private function formatDimensions($dimensions)
    {
        if (empty($dimensions)) {
            return null;
        }

        $dimensions = trim($dimensions);

        // محاولة فك تشفير JSON
        if (substr($dimensions, 0, 1) === '{') {
            $decoded = json_decode($dimensions, true);

            if (is_array($decoded) && isset($decoded['length'], $decoded['width'], $decoded['height'])) {
                $length = $this->cleanNumericValue($decoded['length']);
                $width = $this->cleanNumericValue($decoded['width']);
                $height = $this->cleanNumericValue($decoded['height']);

                if ($length && $width && $height) {
                    return [
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
        }

        // تنظيف النص المباشر (إزالة المسافات الزائدة)
        $dimensions = preg_replace('/\s*x\s*/i', 'x', $dimensions);

        // التحقق من الصيغة الصحيحة: رقم x رقم x رقم
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)x([0-9]+(?:\.[0-9]+)?)x([0-9]+(?:\.[0-9]+)?)$/i', $dimensions, $matches)) {
            return [
                'length' => $matches[1],
                'width' => $matches[2],
                'height' => $matches[3],
            ];
        }

        // إذا لم يتطابق مع أي صيغة، إرجاع null
        return null;
    }

    /**
     * تنظيف القيمة الرقمية من المسافات والأحرف غير الرقمية
     *
     * @param  mixed  $value
     * @return string|null
     */
    private function cleanNumericValue($value)
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);
        // إزالة كل شيء ما عدا الأرقام والنقطة العشرية
        $value = preg_replace('/[^0-9.]/', '', $value);

        return ! empty($value) && is_numeric($value) ? $value : null;
    }

    /**
     * حذف الصورة القديمة
     */
    private function deleteOldImage($imagePath)
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }

    /**
     * عرض تفاصيل منتج
     */
    /**
     * يمنع الوصول إلى منتجٍ غير مخزني عبر شاشة المتجر — حتى لو عُرف معرّفه
     * مباشرةً ولم يمرّ بقائمة index() المُصفّاة أصلاً.
     */
    /**
     * حركاتُ المخزون (مربوطةً بـ`warehouse_permits`) في مستودعاتٍ يراها المستخدم وحدها —
     * لمن لا يرى كل مخازن المتجر. أمّا من يراها كلَّها فبلا قيد.
     */
    private function scopeMovementsToVisibleWarehouses($query): void
    {
        if (StoreAssignmentScope::seesAll()) {
            return;
        }

        $ids = $this->storeWarehouseScopeIds() ?: [0];

        $query->where(function ($q) use ($ids) {
            $q->whereIn('warehouse_permits.store_houses_id', $ids)
                ->orWhereIn('warehouse_permits.from_store_houses_id', $ids)
                ->orWhereIn('warehouse_permits.to_store_houses_id', $ids);
        });
    }

    /**
     * منسدلة مستودعات فلتر الحركات: مستودعات المتجر المرئيّة لمن لا يرى الكل — كانت كلَّ
     * مستودعات النظام لأيّ مستخدم فتُفشي أسماءَ مستودعاتٍ لا يراها. ومن يرى الكل يبقى على ما كان.
     */
    private function movementWarehouseOptions()
    {
        $query = StoreAssignmentScope::seesAll() ? StoreHouse::query() : $this->storeWarehousesQuery(false);

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeStockProduct(Product $product): void
    {
        if ($product->type !== ProductType::PRODUCTS->value) {
            abort(404);
        }
    }

    public function show(Product $product)
    {
        $this->authorizeStockProduct($product);

        $product->load(['category', 'brand', 'reviews', 'questions', 'options.values', 'variants.optionValues.option']);

        // إحصائيات المنتج الأساسية
        $total_quantity = DB::table('product_details')->where('product_id', $product->id)->sum('quantity');
        $total_sold = $product->totalSold();
        $sold_last_28_days = $product->totalSoldLast28Days();
        $sold_last_7_days = $product->totalSoldLast7Days();
        $average_cost = $product->averageCost();

        // كميات المخازن
        $storeQuantities = ProductDetails::where('product_id', $product->id)
            ->selectRaw('store_house_id, SUM(quantity) as total_quantity')
            ->groupBy('store_house_id')
            ->with('storeHouse')
            // من لا يرى كل المخازن لا يرى كميات غير مستودعاته (المسنَدة له ولتابعيه)
            ->when(! StoreAssignmentScope::seesAll(), fn ($q) => $q->whereIn('store_house_id', $this->storeWarehouseScopeIds() ?: [0]))
            ->get();

        // حركات المخزون
        $stock_movements = WarehousePermitsProducts::where('warehouse_permits_products.product_id', $product->id)
            ->leftJoin('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
            ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
            ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
            ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
            ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
            ->select(
                'warehouse_permits_products.*',
                'warehouse_permits.id as permit_id',
                'warehouse_permits.number as permit_number',
                'warehouse_permits.permission_date',
                'warehouse_permits.permission_type',
                'warehouse_permits.store_houses_id',
                'warehouse_permits.from_store_houses_id',
                'warehouse_permits.to_store_houses_id',
                'warehouse_permits.permission_source_id',
                'warehouse_permits.created_by',
                'store_houses.name as store_name',
                'from_stores.name as from_store_name',
                'to_stores.name as to_store_name',
                'permission_sources.name as source_name',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('warehouse_permits.permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE)
            ->tap(fn ($q) => $this->scopeMovementsToVisibleWarehouses($q))
            ->orderBy('warehouse_permits_products.created_at', 'desc')
            ->paginate(100);

        // سجل النشاطات
        $logs = ModelsLog::where('type', 'product')
            ->where('type_id', $product->id)
            ->with(['user', 'Product'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // مصادر الأذونات
        $permission_sources = PermissionSource::all();

        // منسدلة مستودعات فلتر الحركات — مستودعات المتجر المرئيّة (كانت كلَّ مستودعات النظام)
        $warehouses = $this->movementWarehouseOptions();

        // إحصائيات المنتج للعرض
        $stats = [
            'total_sold' => $total_sold,
            'total_quantity' => $total_quantity,
            'reviews_count' => $product->reviewsCount(),
            'average_rating' => $product->averageRating(),
            'sold_last_28_days' => $sold_last_28_days,
            'sold_last_7_days' => $sold_last_7_days,
            'average_cost' => $average_cost,
        ];

        // متغيرات فارغة للـ view (تُستخدم عند الفلترة)
        $source_id = null;
        $warehouse_id = null;
        $user_id = null;

        return view('onlinestore::products.show', compact(
            'product',
            'stats',
            'storeQuantities',
            'stock_movements',
            'logs',
            'permission_sources',
            'average_cost',
            'source_id',
            'warehouse_id',
            'user_id',
            'warehouses'
        ));
    }

    /**
     * جلب حركة المخزون مع الفلاتر
     */
    public function getStockMovements(Request $request, $id)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = 100;

            $product = Product::find($id);
            if (! $product) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'المنتج غير موجود',
                    ], 404);
                }

                return back()->with('error', 'المنتج غير موجود');
            }

            // استلام الفلاتر
            $source_id = $request->get('source_id');
            $date_from = $request->get('date_from');
            $date_to = $request->get('date_to');
            $warehouse_id = $request->get('warehouse_id');
            $user_id = $request->get('user_id');

            // بناء الاستعلام الأساسي
            $query = WarehousePermitsProducts::where('warehouse_permits_products.product_id', $id)
                ->leftJoin('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
                ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
                ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
                ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
                ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
                ->select(
                    'warehouse_permits_products.*',
                    'warehouse_permits.id as permit_id',
                    'warehouse_permits.number as permit_number',
                    'warehouse_permits.permission_date',
                    'warehouse_permits.permission_type',
                    'warehouse_permits.store_houses_id',
                    'warehouse_permits.from_store_houses_id',
                    'warehouse_permits.to_store_houses_id',
                    'warehouse_permits.permission_source_id',
                    'warehouse_permits.created_by',
                    'store_houses.name as store_name',
                    'from_stores.name as from_store_name',
                    'to_stores.name as to_store_name',
                    'permission_sources.name as source_name',
                    'users.name as user_name',
                    'users.email as user_email'
                );

            // تطبيق الفلاتر
            if ($source_id) {
                $query->where('warehouse_permits.permission_source_id', $source_id);
            }

            if ($date_from) {
                $query->whereDate('warehouse_permits.permission_date', '>=', $date_from);
            }

            if ($date_to) {
                $query->whereDate('warehouse_permits.permission_date', '<=', $date_to);
            }

            if ($warehouse_id) {
                $query->where(function ($q) use ($warehouse_id) {
                    $q->where(function ($q2) use ($warehouse_id) {
                        $q2->whereNotIn('warehouse_permits.permission_type', [3, 13])
                            ->where('warehouse_permits.store_houses_id', $warehouse_id);
                    });
                    $q->orWhere(function ($q2) use ($warehouse_id) {
                        $q2->whereIn('warehouse_permits.permission_type', [3, 13])
                            ->where(function ($q3) use ($warehouse_id) {
                                $q3->where(function ($q4) use ($warehouse_id) {
                                    $q4->where('warehouse_permits.from_store_houses_id', $warehouse_id)
                                        ->whereRaw('warehouse_permits_products.stock_after < warehouse_permits_products.stock_before');
                                })
                                    ->orWhere(function ($q4) use ($warehouse_id) {
                                        $q4->where('warehouse_permits.to_store_houses_id', $warehouse_id)
                                            ->whereRaw('warehouse_permits_products.stock_after > warehouse_permits_products.stock_before');
                                    });
                            });
                    });
                });
            }

            if ($user_id) {
                $query->where('warehouse_permits.created_by', $user_id);
            }

            // تصفية المشتريات
            $query->where('warehouse_permits.permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE);

            // حركاتُ مستودعاتٍ يراها المستخدم وحدها — مهما أرسل في `warehouse_id`
            $this->scopeMovementsToVisibleWarehouses($query);

            $stock_movements = $query
                ->orderBy('warehouse_permits_products.created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $permission_sources = PermissionSource::all();
            $warehouses = $this->movementWarehouseOptions();

            if ($request->ajax()) {
                try {
                    $html = view('onlinestore::products.partials.stock_movements',
                        compact('stock_movements', 'product', 'permission_sources', 'source_id', 'date_from', 'date_to', 'warehouse_id', 'user_id', 'warehouses'))->render();

                    return response()->json([
                        'success' => true,
                        'html' => $html,
                    ]);
                } catch (\Exception $e) {
                    OperationLog::failure($e, 'OnlineStore.StoreProduct', 'getStockMovements', $product);

                    \Log::error('Stock movements view error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                    return response()->json([
                        'success' => false,
                        'error' => 'خطأ في تحميل البيانات: '.$e->getMessage(),
                    ], 500);
                }
            }

            return view('onlinestore::products.partials.stock_movements',
                compact('stock_movements', 'product', 'permission_sources', 'source_id', 'date_from', 'date_to', 'warehouse_id', 'user_id', 'warehouses'));

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreProduct', 'getStockMovements', $id);

            \Log::error('Get stock movements error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * جلب الجدول الزمني
     */
    public function getTimeline(Request $request, $id)
    {
        $page = $request->get('page', 1);
        $perPage = 50;

        $query = WarehousePermitsProducts::where('product_id', $id)
            ->with([
                'warehousePermits' => function ($query) {
                    $query->with(['storeHouse', 'user', 'permissionSource']);
                },
            ]);

        $query = $query->where(function ($q) {
            $q->whereHas('warehousePermits', function ($permit) {
                $permit->where('permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE);

                if (! StoreAssignmentScope::seesAll()) {
                    $this->scopePermitsToStoreWarehouses($permit, $this->storeWarehouseScopeIds());
                }
            });
        });

        $stock_movements = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $product = Product::findOrFail($id);
        $average_cost = $product->averageCost();

        $html = view('onlinestore::products.partials.timeline', compact('stock_movements', 'product', 'average_cost'))->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => [
                'current_page' => $stock_movements->currentPage(),
                'last_page' => $stock_movements->lastPage(),
                'total' => $stock_movements->total(),
            ],
        ]);
    }

    /**
     * جلب سجل النشاطات
     */
    public function getActivityLogs(Request $request, $id)
    {
        $page = $request->get('page', 1);
        $perPage = 50;

        $logs = ModelsLog::where('type', 'product')
            ->where('type_id', $id)
            ->with(['user', 'Product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $html = view('onlinestore::products.partials.activity_logs', compact('logs'))->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * عرض صفحة تعديل منتج
     */
    public function edit(Product $product)
    {
        $this->authorizeStockProduct($product);

        $product->load(['options.values', 'variants.optionValues.option']);

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->where('is_active', true)->get();
        $productStatuses = ProductStatus::cases();
        $optionTemplates = collect(OnlineStoreOptionPresets::PRESETS);
        $taxOptions = $this->taxOptions();

        return view('onlinestore::products.edit', compact('product', 'categories', 'brands', 'productStatuses', 'optionTemplates', 'taxOptions'));
    }

    /**
     * تحديث منتج
     */
    public function update(Request $request, Product $product)
    {
        $this->authorizeStockProduct($product);

        $validated = $request->validate($this->productRules($product), self::PRODUCT_MESSAGES, self::ATTRIBUTES);
        unset($validated['variants'], $validated['options']);

        // تحويل discount_type إلى رقم (1 = percentage, 2 = fixed)
        if (isset($validated['discount_type'])) {
            $validated['discount_type'] = $this->convertDiscountType($validated['discount_type']);
        }

        // تنسيق الأبعاد تلقائياً
        if (isset($validated['dimensions']) && ! empty($validated['dimensions'])) {
            $formattedDimensions = $this->formatDimensions($validated['dimensions']);

            if ($formattedDimensions === null) {
                return back()->withErrors([
                    'dimensions' => 'صيغة الأبعاد غير صحيحة. الرجاء استخدام صيغة: {"length":"10","width":"10","height":"10"} أو 10x10x10',
                ])->withInput();
            }

            $validated['dimensions'] = $formattedDimensions;
        } else {
            $validated['dimensions'] = null;
        }

        // تعيين القيم البولينية
        $validated['available_online'] = $request->boolean('available_online', false);
        $validated['available_pos'] = $request->boolean('available_pos');
        $validated['featured_product'] = $request->boolean('featured_product', false);
        $validated['track_inventory'] = $request->boolean('track_inventory');
        $validated['low_stock_alert'] = $request->boolean('low_stock_alert', false);
        $validated['is_returnable'] = $request->boolean('is_returnable', true);

        $this->assertDiscountWithinPrice($validated);

        // الصور الجديدة تُضاف إلى الموجودة — كان رفعُ صورةٍ واحدة يمسح كل صور
        // المنتج. حذف صورةٍ بعينها من زرّها في النموذج (removeImage).
        unset($validated['images']);
        if ($request->hasFile('images')) {
            $existing = json_decode((string) $product->images, true);
            $imagesArray = is_array($existing) ? array_values($existing) : [];

            if (count($imagesArray) + count($request->file('images')) > 10) {
                return back()->withInput()->withErrors(['images' => 'الحد الأقصى 10 صور للمنتج. احذف صورة قبل إضافة أخرى.']);
            }

            foreach ($request->file('images') as $image) {
                $imagesArray[] = $this->uploadImage('assets/uploads/products', $image);
            }
            $validated['images'] = json_encode($imagesArray);
        }

        DB::transaction(function () use ($product, $validated, $request) {
            $product->update($validated);

            if ($request->filled('options') || $request->filled('delete_options') || $request->filled('delete_option_values')) {
                $this->saveProductOptions($product, $request);
            }

            if ($request->filled('variants') || $request->filled('delete_variants')) {
                $this->saveProductVariants($product, $request);
            }
        });

        OperationLog::record('updated', 'product', $product, 'تم تعديل بيانات المنتج **'.$product->name.'**');

        return redirect()->route('store_products.index')
            ->with('success', 'تم تحديث المنتج بنجاح');
    }

    /**
     * حذف منتج
     */
    public function destroy(Product $product)
    {
        $this->authorizeStockProduct($product);

        // منتجٌ عليه رصيد أو طلبات أو فواتير أو أذون لا يُحذف — كان يُحذف بلا أي
        // فحص فتبقى الطلبات والقيود تشير إلى صنفٍ غير موجود
        if ($reason = $this->productInUseReason($product)) {
            return redirect()->back()->with('error', 'لا يمكن حذف المنتج «'.$product->name.'»: '.$reason.' عطّله أو أوقف عرضه في المتجر بدل حذفه.');
        }

        // حذف الصور
        if ($product->images) {
            $images = json_decode($product->images, true);
            if (is_array($images)) {
                foreach ($images as $image) {
                    $this->deleteOldImage($image);
                }
            }
        }

        OperationLog::record('deleted', 'product', $product, 'تم حذف المنتج **'.$product->name.'**');

        $product->delete();

        return redirect()->route('store_products.index')
            ->with('success', 'تم حذف المنتج بنجاح');
    }

    /** سبب منع حذف المنتج، أو null إن كان بلا أي حركة. */
    private function productInUseReason(Product $product): ?string
    {
        $stock = (float) DB::table('product_details')->where('product_id', $product->id)->sum('quantity');
        if (abs($stock) > 0.00001) {
            return 'له رصيد في المخزون ('.rtrim(rtrim(number_format($stock, 2), '0'), '.').').';
        }

        $checks = [
            'online_order_items' => 'مرتبط بطلبات في المتجر.',
            'invoice_items' => 'مرتبط بفواتير.',
            'warehouse_permits_products' => 'مرتبط بأذون مخزنية.',
        ];

        foreach ($checks as $table => $reason) {
            if (Schema::hasTable($table)
                && DB::table($table)->where('product_id', $product->id)->exists()) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * تبديل حالة المنتج (نشط/غير نشط)
     */
    public function toggleStatus(Product $product)
    {
        $this->authorizeStockProduct($product);

        // الحالة المعطّلة في التعداد اسمها UNACTIVE — `INACTIVE` غير معرَّف فكان
        // زرّ تبديل الحالة يسقط بخطأ 500
        $newStatus = $product->status === ProductStatus::ACTIVE
            ? ProductStatus::UNACTIVE
            : ProductStatus::ACTIVE;

        $product->update(['status' => $newStatus]);

        OperationLog::record('updated', 'product', $product, 'تم تغيير حالة المنتج **'.$product->name.'** إلى '.$newStatus->value);

        return response()->json([
            'success' => true,
            'status' => $newStatus->value,
            'message' => 'تم تغيير حالة المنتج بنجاح',
        ]);
    }

    /**
     * تبديل حالة الإتاحة أونلاين
     */
    public function toggleOnline(Product $product)
    {
        $this->authorizeStockProduct($product);

        $product->update(['available_online' => ! $product->available_online]);

        OperationLog::record('updated', 'product', $product, 'تم '.($product->available_online ? 'إتاحة' : 'إخفاء').' المنتج **'.$product->name.'** في المتجر الإلكتروني');

        return response()->json([
            'success' => true,
            'available_online' => $product->available_online,
            'message' => $product->available_online ? 'تم إتاحة المنتج أونلاين' : 'تم إلغاء إتاحة المنتج أونلاين',
        ]);
    }

    /**
     * تبديل حالة المنتج المميز
     */
    public function toggleFeatured(Product $product)
    {
        $this->authorizeStockProduct($product);

        $product->update(['featured_product' => ! $product->featured_product]);

        OperationLog::record('updated', 'product', $product, 'تم '.($product->featured_product ? 'تمييز' : 'إلغاء تمييز').' المنتج **'.$product->name.'**');

        return response()->json([
            'success' => true,
            'featured' => $product->featured_product,
            'message' => $product->featured_product ? 'تم تمييز المنتج' : 'تم إلغاء تمييز المنتج',
        ]);
    }

    /**
     * تصدير المنتجات
     */
    public function export(Request $request)
    {
        // يمكن إضافة منطق التصدير هنا لاحقاً
        return back()->with('info', 'ميزة التصدير قيد التطوير');
    }

    /**
     * حذف صورة معينة من المنتج
     */
    public function removeImage(Request $request, Product $product)
    {
        $this->authorizeStockProduct($product);

        $imageIndex = $request->input('image_index');

        if ($product->images && is_scalar($imageIndex) && ctype_digit((string) $imageIndex)) {
            $images = json_decode($product->images, true);
            $imageIndex = (int) $imageIndex;

            if (is_array($images) && isset($images[$imageIndex])) {
                $this->deleteOldImage($images[$imageIndex]);
                unset($images[$imageIndex]);
                $images = array_values($images); // إعادة ترقيم المصفوفة

                $product->update([
                    'images' => count($images) > 0 ? json_encode($images) : null,
                ]);

                OperationLog::record('updated', 'product', $product, 'تم حذف صورة من المنتج **'.$product->name.'**');

                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف الصورة بنجاح',
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'الصورة غير موجودة',
        ], 404);
    }

    /**
     * عرض صفحة إضافة عملية يدوية على المخزون
     */
    public function manualStockAdjust($id)
    {
        $product = Product::findOrFail($id);
        $this->authorizeStockProduct($product);
        // مستودعات المتجر التي يغطّيها نطاق المستخدم والفرع النشط — لا كل الفروع
        $storehouses = $this->storeWarehousesQuery()->select(['name', 'id'])->get();

        // الإجمالي المعروض في الشاشة = رصيد المنتج في مستودعات المتجر وحدها
        // (`is_store_storehouse`)، لا كل مستودعات النظام — هذه الشاشة تدير
        // مخزون المتجر فقط، فإظهار كمياتٍ في مستودعات فروعٍ أخرى مُضلِّل.
        $total_quantity = (float) DB::table('product_details')
            ->where('product_id', $product->id)
            ->whereIn('store_house_id', $storehouses->pluck('id'))
            ->sum('quantity');

        return view('onlinestore::products.manual_stock_adjust', compact('product', 'storehouses', 'total_quantity'));
    }

    /**
     * رصيد صنفٍ في مستودعٍ بعينه — نداء AJAX تستعمله شاشة «إضافة عملية على
     * المخزون» لعرض «الرصيد الحالي» فور اختيار المستودع.
     *
     * نفس مصدر الحقيقة الذي تقرأه شاشة الأذون المخزنية
     * (StorePermitsController::getProductStock): جدول `product_details`
     * مرشَّحاً بـ `store_house_id` + `product_id`. نستعمل `SUM(quantity)`
     * ليطابق تماماً ما يحسبه `addManualStockAdjust()` عند التحقّق
     * (`$oldQuantity`) — مخزون المنتج الأساسي، بلا مخزون المتغيرات.
     */
    public function productStockInStore(Product $product, $storeId)
    {
        // المستودع يجب أن يكون مستودع متجر — لا نكشف أرصدة مستودعات الفروع
        if (! $this->storeWarehouseInScope($storeId)) {
            return response()->json(['stock' => 0, 'message' => 'مستودع غير تابع للمتجر'], 422);
        }

        $stock = (float) ProductDetails::where('product_id', $product->id)
            ->where('store_house_id', $storeId)
            ->sum('quantity');

        return response()->json(['stock' => $stock]);
    }

    /**
     * تنفيذ إضافة عملية يدوية على المخزون.
     *
     * كانت هذه الدالة تُحرِّك `ProductDetails` مباشرة، ثم تُنشئ سجلّ
     * `WarehousePermits` بعدها كمجرّد أثرٍ توثيقي — بلا `created_by` (عمود
     * NOT NULL، فيفشل الحفظ بخطأ SQL خام)، وبحقلي `user_id`/`notes` غير
     * الموجودين أصلاً في `$fillable` (يُسقطهما Eloquent بصمت). ولأنها لا
     * تمرّ بضابط الاعتماد نفسه (StorePermitsController::approve)، لم تكن
     * تُنشئ أيّ قيدٍ محاسبي للعملية إطلاقاً.
     *
     * الإصلاح: يُنشأ إذنٌ مخزني «معلَّق» بالطريقة الصحيحة (رقمه من إعدادات
     * الترقيم، لا نصّاً عشوائياً) ثم يُعتمَد فوراً عبر
     * StorePermitsController::approve() — نفس ما تفعله شاشة «رصيد افتتاحي»
     * لنفسها في StorePermitsController::store(): هذه عمليةٌ سريعة من صفحة
     * المنتج لا تنتظر موافقةً منفصلة، لكنها تمرّ بنفس المسار الذي يحرّك
     * المخزون وينشئ القيد المحاسبي بلا ازدواج منطقٍ في مكانين.
     */
    public function addManualStockAdjust(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:99999999',
            'type' => 'required|in:1,2', // 1 = إضافة ، 2 = سحب
            'unit_price' => 'required|numeric|min:0|max:99999999',
            'date' => 'required|date',
            'time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'store_house_id' => 'required|integer|exists:store_houses,id',
            'attachments' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf,doc,docx|max:10240',
        ], ['time.regex' => 'صيغة الوقت غير صحيحة.'], self::ATTRIBUTES);

        // مستوى المعاملة عند الدخول: `rollBack()` في catch لا يتجاوزه — الاعتماد
        // يجري بعد `commit()`، وخطأٌ فيه كان يُلغي معاملةَ مَن نادى لا معاملتَنا
        $txLevel = DB::transactionLevel();

        // الفترة المحاسبية — العملية تُعتمَد فوراً أدناه فيُرحَّل قيدها بهذا التاريخ
        if ($blocked = AccountingPeriodGuard::blockReason($request->date)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);
            $this->authorizeStockProduct($product);

            // فحص حالة المنتج
            if ($product->status->value !== 'active') {
                DB::rollBack();

                return redirect()->back()->with('error', 'لا يمكن إجراء عمليات على منتج غير نشط');
            }

            $storeHouse = $this->storeWarehouseInScope($request->store_house_id) ? StoreHouse::onlineStore()->find($request->store_house_id) : null;
            if (! $storeHouse) {
                DB::rollBack();

                return redirect()->back()->with('error', 'المستودع المحدد لا يتبع المتجر.')->withInput();
            }

            $oldQuantity = (float) ProductDetails::where('product_id', $id)
                ->where('store_house_id', $request->store_house_id)
                ->sum('quantity');

            // إذن صرف: تأكّدٌ مبكّر من توفّر الكمية — الخصم الفعلي يحدث عند
            // الاعتماد أدناه لا هنا، بنفس ضابط StorePermitsController::store()
            if ($request->type == 2 && $oldQuantity < $request->quantity) {
                DB::rollBack();

                return redirect()->back()
                    ->with('error', 'الكمية غير متوفرة في المخزن المحدد: المتاح '.$oldQuantity.' والمطلوب '.$request->quantity.'.')
                    ->withInput();
            }

            $attachments = null;
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                $attachments = time().rand(1, 99).'.'.($file->extension() ?: 'bin');
                $file->move('assetss/uploads/warehouse', $attachments);
            }

            $quantity = (float) $request->quantity;
            $unitPrice = (float) $request->unit_price;
            $direction = $request->type == 2 ? -1 : 1;

            // رقم الإذن من إعدادات «الترقيم المتسلسل» وحدها — نفس ضابط
            // StorePermitsController::store()، لا نصّاً عشوائياً يتخطّى التسلسل
            $numberSection = $request->type == 2 ? 'warehouse-dispose' : 'warehouse-add';

            $warehousePermit = WarehousePermits::create([
                'number' => next_document_number($numberSection),
                'permission_date' => $request->date.' '.$request->time,
                'permission_type' => $request->type,
                'permission_source_id' => $request->type == 2 ? PermissionSources::WAREHOUSE_DISBURSEMENT : PermissionSources::WAREHOUSE_ADD,
                'store_houses_id' => $request->store_house_id,
                'grand_total' => $quantity * $unitPrice,
                'details' => $request->comments ?? null,
                'attachments' => $attachments,
                'created_by' => auth()->id(),
            ]);

            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $warehousePermit->id,
                'product_id' => $id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $quantity * $unitPrice,
                'stock_before' => $oldQuantity,
                'stock_after' => $oldQuantity + ($direction * $quantity),
            ]);

            DB::commit();

            // تُعتمد فوراً — نفس مسار approve() الذي يحرّك المخزون فعلياً
            // وينشئ القيد المحاسبي (راجع تعليق الدالة أعلاه)
            $approval = app(StorePermitsController::class)->approve($warehousePermit->id);
            $approvalError = method_exists($approval, 'getSession') && $approval->getSession()
                ? $approval->getSession()->get('error')
                : null;

            if ($approvalError) {
                return redirect()
                    ->route('store_products.show', $id)
                    ->with('error', 'أُنشئت العملية لكن تعذّر اعتمادها: '.$approvalError);
            }

            /*
             * تسجيل في سجل النشاطات — كان يكتب على عمودي `product_id`/`old_value`/
             * `new_value` غير الموجودين في جدول `logs` (الأعمدة الفعلية `type_id`/
             * `old_values`/`new_values`)، فلا يظهر السجل أبداً في تبويب نشاطات
             * المنتج التي تُصفَّى بـ `type_id = $product->id`.
             */
            OperationLog::record(
                $request->type == 1 ? 'stock_added' : 'stock_disbursed',
                'product',
                $product,
                ($request->type == 1 ? 'تم إضافة ' : 'تم سحب ').$quantity.' وحدة - السعر: '.CurrencySettings::format($unitPrice),
                ['old' => ['quantity' => $oldQuantity], 'new' => ['quantity' => $oldQuantity + ($direction * $quantity)]]
            );

            $message = $request->type == 1
                ? 'تم إضافة الكمية إلى المخزون بنجاح'
                : 'تم سحب الكمية من المخزون بنجاح';

            return redirect()
                ->route('store_products.show', $id)
                ->with('success', $message);

        } catch (HttpException $e) {
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            throw $e;
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده: التسجيل يكتب عبر نفس اتصال
            // قاعدة البيانات، فإن كُتب داخل معاملةٍ لم تُنهَ بعد يُلغى سجلّه
            // هو نفسه حين تُلغى المعاملة بأثرٍ رجعي (تحقّقتُ من هذا مباشرةً)
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreProduct', 'addManualStockAdjust');

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تنفيذ العملية. راجع سجل الأخطاء أو حاول مرة أخرى.')
                ->withInput();
        }
    }

    /**
     * طباعة الباركود
     */
    public function printBarcode($id)
    {
        $product = Product::findOrFail($id);
        $this->authorizeStockProduct($product);
        $product->load('variants.optionValues.option');

        return view('onlinestore::products.print_barcode', compact('product'));
    }
}
