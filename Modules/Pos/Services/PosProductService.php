<?php

namespace Modules\Pos\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PosGeneralSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class PosProductService
{
    /**
     * معرف المستودع الحالي
     */
    protected ?int $storeHouseId = null;

    /**
     * الحقول المطلوبة للمنتجات في POS (محسّنة للأداء)
     */
    protected const PRODUCT_FIELDS = [
        'id', 'name', 'sale_price', 'images', 'category_id', 
        'barcode', 'status', 'type', 'compile_type'
    ];

    /**
     * الحقول المطلوبة للتصنيفات
     */
    protected const CATEGORY_FIELDS = ['id', 'name', 'attachments'];

    /**
     * جلب المنتجات المتاحة في POS (بدون كاش - استعلام مباشر محسّن)
     */
    public function getAvailableProducts(?int $categoryId = null, ?string $search = null, int $limit = 50, int $page = 1)
    {
        return $this->fetchProducts($categoryId, $search, $limit, $page);
    }

    /**
     * جلب المنتجات من قاعدة البيانات بشكل محسّن
     */
    protected function fetchProducts(?int $categoryId, ?string $search, int $limit, int $page)
    {
        // الحصول على معرف المستودع
        $this->initStoreHouseId();
        
        $query = DB::table('products')
            ->leftJoin('product_details', function($join) {
                $join->on('products.id', '=', 'product_details.product_id')
                     ->where('product_details.store_house_id', '=', $this->storeHouseId);
            })
            ->select([
                'products.id', 'products.name', 'products.sale_price', 'products.images', 
                'products.category_id', 'products.barcode', 'products.type', 'products.compile_type',
                DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')
            ])
            ->where('products.available_pos', 1)
            ->where('products.status', 'active');

        // فلتر التصنيف - استخدام index
        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        // البحث المحسّن
        if ($search) {
            $searchTerm = trim($search);
            
            // البحث الدقيق بالباركود أولاً (أسرع)
            if (is_numeric($searchTerm) || strlen($searchTerm) >= 8) {
                $exactMatch = DB::table('products')
                    ->leftJoin('product_details', function($join) {
                        $join->on('products.id', '=', 'product_details.product_id')
                             ->where('product_details.store_house_id', '=', $this->storeHouseId);
                    })
                    ->select([
                        'products.id', 'products.name', 'products.sale_price', 'products.images', 
                        'products.category_id', 'products.barcode', 'products.type', 'products.compile_type',
                        DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')
                    ])
                    ->where('products.available_pos', 1)
                    ->where('products.status', 'active')
                    ->where('products.barcode', $searchTerm)
                    ->first();
                
                if ($exactMatch) {
                    return collect([$this->transformProductFromQuery($exactMatch)]);
                }
            }
            
            $query->where(function ($q) use ($searchTerm) {
                $q->where('products.name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('products.barcode', $searchTerm)
                  ->orWhere('products.id', $searchTerm);
            });
        }

        // الترتيب والتصفح المحسّن
        $offset = ($page - 1) * $limit;
        
        $products = $query
            ->orderBy('products.name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $products->map(fn($p) => $this->transformProductFromQuery($p));
    }

    /**
     * مستودعُ الشاشة — الذي تُقرأ منه الكمّياتُ ويُخصم منه البيع.
     *
     * ═══════════════ العطل ═══════════════
     *
     * كانت السلسلةُ حلقتين: جهازُ الجلسة النشطة، ثم **`StoreHouse::first()`**
     * — أوّلُ صفٍّ في الجدول، أيّاً كان.
     *
     * فالأثرُ ما رآه المستخدم: صنفٌ في مخزن الفرع منه ألف، ويظهر في الشاشة
     * «نفدت الكمّية» — لأنّ الكمّيةَ قُرئت من مستودعٍ آخرَ لا شأنَ له به.
     *
     * وأسوأُ منه أنّ الفشلَ صامت: لا خطأَ ولا تنبيه، إنما رقمٌ خاطئ.
     *
     * ═══════════════ المصدر ═══════════════
     *
     *     إعدادُ نقطة البيع للفرع  ←  الإعدادُ العام  ←  لا شيء
     *
     * وهو `pos_general_settings.store_house_id` عبر [PosCustody] — المصدرُ
     * الواحد الذي يقرؤه كذلك مسارُ البيع وحارسُ الدخول، فما يُعرض وما
     * يُخصم وما يُمنع من مشكاةٍ واحدة.
     *
     * **ولا سقوطَ إلى أوّل مستودع**: فرعٌ بلا مستودعٍ مضبوط يجب أن يُمنع
     * لا أن يُعطى مخزنَ غيره. و`null` هنا تعني «لا مستودع»، ويحرسها
     * `EnsurePosCustody` عند باب الشاشة.
     */
    protected function initStoreHouseId(): void
    {
        if ($this->storeHouseId !== null) {
            return;
        }

        $this->storeHouseId = self::resolveStoreHouseId();
    }

    /**
     * مستودعُ العهدة — الحلُّ كلُّه في [PosCustody].
     *
     * كانت هنا سلسلةٌ ثانيةٌ مستقلّة، وثالثةٌ في `SalesStartController`.
     * وثلاثُ سلاسلَ لسؤالٍ واحد تختلف في الجواب — وهو ما وقع فعلاً.
     */
    public static function resolveStoreHouseId(?int $userId = null): ?int
    {
        return \Modules\Pos\Services\PosCustody::storeHouseId($userId);
    }

    /**
     * جلب المنتجات بالباركود (بحث سريع - بدون كاش)
     * يبحث أولاً في باركودات الوحدات ثم في باركود المنتج الرئيسي
     */
    public function getProductByBarcode(string $barcode)
    {
        // تهيئة معرف المستودع
        $this->initStoreHouseId();
        
        // 1. البحث أولاً في باركودات الوحدات (product_units)
        $productUnit = DB::table('product_units')
            ->join('products', 'product_units.product_id', '=', 'products.id')
            ->leftJoin('product_details', function($join) {
                $join->on('products.id', '=', 'product_details.product_id')
                     ->where('product_details.store_house_id', '=', $this->storeHouseId);
            })
            ->leftJoin('category_unit', 'product_units.category_unit_id', '=', 'category_unit.id')
            ->leftJoin('units', 'category_unit.unit_id', '=', 'units.id')
            ->select([
                'products.id', 'products.name', 'products.sale_price as product_sale_price', 
                'products.images', 'products.category_id', 'products.barcode as product_barcode', 
                'products.type', 'products.compile_type',
                'product_units.id as product_unit_id',
                'product_units.barcode as unit_barcode',
                'product_units.sale_price as unit_sale_price',
                'product_units.purchase_price as unit_purchase_price',
                'units.name as unit_name',
                'category_unit.conversion_factor',
                DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')
            ])
            ->where('product_units.barcode', $barcode)
            ->where('product_units.is_active', 1)
            ->where('products.available_pos', 1)
            ->where('products.status', 'active')
            ->first();

        if ($productUnit) {
            return $this->transformProductWithUnit($productUnit);
        }
        
        // 2. إذا لم يوجد في الوحدات، ابحث في باركود المنتج الرئيسي
        $product = DB::table('products')
            ->leftJoin('product_details', function($join) {
                $join->on('products.id', '=', 'product_details.product_id')
                     ->where('product_details.store_house_id', '=', $this->storeHouseId);
            })
            ->select([
                'products.id', 'products.name', 'products.sale_price', 'products.images', 
                'products.category_id', 'products.barcode', 'products.type', 'products.compile_type',
                DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')
            ])
            ->where('products.barcode', $barcode)
            ->where('products.available_pos', 1)
            ->where('products.status', 'active')
            ->first();

        if (!$product) {
            return null;
        }

        return $this->transformProductFromQuery($product);
    }

    /**
     * تحويل منتج مع بيانات الوحدة
     */
    protected function transformProductWithUnit($product): array
    {
        // استخدام سعر الوحدة إذا كان موجوداً، وإلا سعر المنتج
        $salePrice = $product->unit_sale_price ?? $product->product_sale_price;
        
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sale_price' => (float) $salePrice,
            'category_id' => $product->category_id,
            'barcode' => $product->unit_barcode ?? $product->product_barcode,
            'type' => $product->type ?? 'products',

            /*
                رايةُ الرصيد صريحةً.

                الشاشةُ كانت تستنتجه بمقارنةِ النوع بـ`'service'` — بالمفرد،
                وقيمةُ العمود `'services'` بالجمع. فلا يتطابقان أبداً:
                الخدمةُ تُعرض برصيد صفرٍ و«نفدت الكمّية»، ويُمنع إضافتُها —
                فلا تُباع خدمةٌ من نقطة البيع إطلاقاً.

                و`ProductType::tracksStock()` هي الجواب في الخادم، فتُرسَل
                بدل أن تُخمَّن.
            */
            'tracks_stock' => \App\Enums\ProductType::from_value($product->type ?? 'products')->tracksStock(),
            'compile_type' => $product->compile_type,
            'images' => $this->getProductImage($product->images),
            'stock_quantity' => (float) ($product->stock_quantity ?? 0),
            // معلومات الوحدة
            'product_unit_id' => $product->product_unit_id,
            'unit_name' => $product->unit_name,
            'conversion_factor' => (float) ($product->conversion_factor ?? 1),
            'has_unit' => true,
        ];
    }

    /**
     * جلب التصنيفات المتاحة في POS (بدون كاش)
     */
    public function getCategories(?PosGeneralSetting $posSettings = null)
    {
        $query = Category::select(self::CATEGORY_FIELDS)
            ->orderBy('name');

        if ($posSettings) {
            switch ($posSettings->allowed_categories_type) {
                case 'only':
                    if (!empty($posSettings->allowed_categories_ids)) {
                        $query->whereIn('id', $posSettings->allowed_categories_ids);
                    }
                    break;
                case 'except':
                    if (!empty($posSettings->allowed_categories_ids)) {
                        $query->whereNotIn('id', $posSettings->allowed_categories_ids);
                    }
                    break;
            }
        }

        return $query->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'image' => $this->getCategoryImageUrl($category->attachments),
            ];
        });
    }

    /**
     * الحصول على رابط صورة الصنف
     */
    private function getCategoryImageUrl($attachments): string
    {
        if (empty($attachments)) {
            return asset('assets/uploads/no_image.jpg');
        }

        // إذا كانت الصور JSON string
        if (is_string($attachments)) {
            // محاولة فك الـ JSON
            $decoded = json_decode($attachments, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $attachments = $decoded[0];
            }
        } elseif (is_array($attachments) && count($attachments) > 0) {
            $attachments = $attachments[0];
        }

        // إذا كان الرابط كاملاً
        if (str_starts_with($attachments, 'http://') || str_starts_with($attachments, 'https://')) {
            return $attachments;
        }

        // إزالة الـ slashes الزائدة من البداية
        $attachments = ltrim($attachments, '/\\');

        // إذا كان المسار يبدأ بـ assets
        if (str_starts_with($attachments, 'assets/')) {
            return asset($attachments);
        }

        // افتراض أن الصورة في مجلد الأصناف
        return asset('assets/uploads/category/' . $attachments);
    }

    /**
     * جلب طرق الدفع المتاحة من الإعدادات
     */
    public function getPaymentMethods(?PosGeneralSetting $posSettings = null)
    {
        $query = PaymentMethod::select(['id', 'name', 'type', 'description'])
            ->where('status', 'active')
            ->orderBy('name');

        // إذا كانت هناك إعدادات وتحديد لطرق الدفع المتاحة
        if ($posSettings && !empty($posSettings->active_payment_method_ids)) {
            $query->whereIn('id', $posSettings->active_payment_method_ids);
        }

        return $query->get();
    }

    /**
     * جلب طريقة الدفع الافتراضية
     */
    public function getDefaultPaymentMethod(?PosGeneralSetting $posSettings = null)
    {
        if (!$posSettings || !$posSettings->default_payment_method_id) {
            // إرجاع أول طريقة دفع نشطة كافتراضية
            return PaymentMethod::where('status', 'active')->first();
        }

        return PaymentMethod::where('id', $posSettings->default_payment_method_id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * البحث السريع في المنتجات (للـ autocomplete - بدون كاش)
     */
    public function quickSearch(string $query, int $limit = 10)
    {
        if (strlen($query) < 2) {
            return collect([]);
        }

        $searchTerm = trim($query);

        // البحث الدقيق أولاً (باركود أو ID)
        $exactMatch = Product::select(self::PRODUCT_FIELDS)
            ->where('available_pos', 1)
            ->where('status', 'active')
            ->where(function (Builder $q) use ($searchTerm) {
                $q->where('barcode', $searchTerm)
                  ->orWhere('id', $searchTerm);
            })
            ->first();

        if ($exactMatch) {
            return collect([$this->transformProduct($exactMatch)]);
        }

        // البحث بالاسم مع ترتيب ذكي
        $products = Product::select(self::PRODUCT_FIELDS)
            ->where('available_pos', 1)
            ->where('status', 'active')
            ->where('name', 'LIKE', "%{$searchTerm}%")
            ->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", ["{$searchTerm}%"])
            ->limit($limit)
            ->get();

        return $this->transformProducts($products);
    }

    /**
     * جلب عدد المنتجات المتاحة (بدون كاش)
     */
    public function getProductsCount(?int $categoryId = null): int
    {
        $query = Product::where('available_pos', 1)
            ->where('status', 'active');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->count();
    }

    /**
     * تحويل مجموعة المنتجات
     */
    protected function transformProducts($products)
    {
        return $products->map(fn($product) => $this->transformProduct($product));
    }

    /**
     * تحويل منتج واحد (من Query Builder)
     */
    protected function transformProductFromQuery($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sale_price' => (float) $product->sale_price,
            'category_id' => $product->category_id,
            'barcode' => $product->barcode,
            'type' => $product->type ?? 'products',

            /*
                رايةُ الرصيد صريحةً.

                الشاشةُ كانت تستنتجه بمقارنةِ النوع بـ`'service'` — بالمفرد،
                وقيمةُ العمود `'services'` بالجمع. فلا يتطابقان أبداً:
                الخدمةُ تُعرض برصيد صفرٍ و«نفدت الكمّية»، ويُمنع إضافتُها —
                فلا تُباع خدمةٌ من نقطة البيع إطلاقاً.

                و`ProductType::tracksStock()` هي الجواب في الخادم، فتُرسَل
                بدل أن تُخمَّن.
            */
            'tracks_stock' => \App\Enums\ProductType::from_value($product->type ?? 'products')->tracksStock(),
            'compile_type' => $product->compile_type,
            'images' => $this->getProductImage($product->images),
            'stock_quantity' => (float) ($product->stock_quantity ?? 0),
        ];
    }

    /**
     * تحويل منتج واحد (من Eloquent)
     */
    protected function transformProduct($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sale_price' => (float) $product->sale_price,
            'category_id' => $product->category_id,
            'barcode' => $product->barcode,
            'type' => $product->type ?? 'products',

            /*
                رايةُ الرصيد صريحةً.

                الشاشةُ كانت تستنتجه بمقارنةِ النوع بـ`'service'` — بالمفرد،
                وقيمةُ العمود `'services'` بالجمع. فلا يتطابقان أبداً:
                الخدمةُ تُعرض برصيد صفرٍ و«نفدت الكمّية»، ويُمنع إضافتُها —
                فلا تُباع خدمةٌ من نقطة البيع إطلاقاً.

                و`ProductType::tracksStock()` هي الجواب في الخادم، فتُرسَل
                بدل أن تُخمَّن.
            */
            'tracks_stock' => \App\Enums\ProductType::from_value($product->type ?? 'products')->tracksStock(),
            'compile_type' => $product->compile_type,
            'images' => $this->getProductImage($product->images),
            'stock_quantity' => 0, // سيتم جلبها من مكان آخر
        ];
    }

    /**
     * الحصول على مسار صورة المنتج
     */
    protected function getProductImage($images): string
    {
        if (empty($images)) {
            return asset('assets/uploads/no_image.jpg');
        }

        // إذا كانت الصور JSON string
        if (is_string($images)) {
            // محاولة فك الـ JSON
            $decoded = json_decode($images, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $images = $decoded[0];
            }
        } elseif (is_array($images) && count($images) > 0) {
            $images = $images[0];
        }

        // إذا كان الرابط كاملاً
        if (str_starts_with($images, 'http://') || str_starts_with($images, 'https://')) {
            return $images;
        }

        // إزالة الـ slashes الزائدة من البداية
        $images = ltrim($images, '/\\');

        // إذا كان المسار يبدأ بـ assets
        if (str_starts_with($images, 'assets/')) {
            return asset($images);
        }

        // افتراض أن الصورة في مجلد المنتجات
        return asset('assets/uploads/products/' . $images);
    }

    /**
     * البحث في العملاء (بدون كاش)
     */
    public function searchClients(string $query, int $limit = 10)
    {
        if (strlen($query) < 2) {
            return collect([]);
        }

        $searchTerm = trim($query);

        return Client::select(['id', 'trade_name', 'phone', 'code'])
            ->with(['account:id,client_id,balance'])
            ->whereHas('account')
            ->where(function (Builder $q) use ($searchTerm) {
                $q->where('trade_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('code', $searchTerm);
            })
            ->limit($limit)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'trade_name' => $client->trade_name,
                    'phone' => $client->phone,
                    'code' => $client->code,
                    'balance' => $client->account->balance ?? 0,
                ];
            });
    }

    /**
     * جلب إعدادات نقطة البيع
     */
    /**
     * إعداداتُ نقطة البيع المطبَّقة — لفرع المستخدم الجاري.
     *
     * كان `first()`: أوّلُ صفٍّ في الجدول أيّاً كان فرعُه. ومنه تُقرأ
     * **شاشةُ الكاشير كلُّها** — العميلُ الافتراضي وطرقُ الدفع والتصنيفاتُ
     * المعروضة وقالبُ الفاتورة. فكاشيرُ الفرع الثاني يعمل بإعداد الأوّل:
     * يرى تصنيفاتٍ ليست له، وتُختار له طريقةُ دفعٍ ليست مفعَّلةً عنده.
     *
     * و`getSettings()` تحلّها بـ`current_branch_id()` وتسقط إلى العام حين
     * لا صفَّ للفرع.
     */
    public function getPosSettings(): ?PosGeneralSetting
    {
        return PosGeneralSetting::getSettings();
    }

    /**
     * العميلُ الافتراضي للشاشة — المضبوطُ في الإعدادات، وإلّا **العميل النقدي**.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * تُرجع `null` حين لا `default_customer_id` مضبوطاً. فتفتح الشاشةُ على
     * «اختر العميل» بلا عميل، وأكثرُ ما يُباع في نقطة البيع بيعٌ نقديٌّ لمن
     * لا ملفَّ له. فالكاشيرُ يختار العميلَ النقدي يدوياً في كل فاتورة، أو
     * ينساه فتُحفظ الفاتورة بلا `client_id`: لا كشفَ حسابٍ لها، ولا تقريرَ
     * مبيعاتٍ يعدّها، ولا نقاطَ ولاء.
     *
     * ═══════════════ والسقوطُ مرتَّب ═══════════════
     *
     *     إعدادُ نقاط البيع  ←  العميل النقدي  ←  لا شيء
     *
     * والمرتبةُ الأخيرة قائمةٌ ولم تُحذف: `CASH_CLIENT_ID` صفٌّ في `clients`
     * قد يُحذف أو يُترك بلا حسابٍ محاسبي في تركيبةٍ ما، و`whereHas('account')`
     * شرطٌ لازم — فاتورةٌ على عميلٍ بلا حسابٍ تكسر القيد.
     */
    public function getDefaultClient(?PosGeneralSetting $posSettings = null)
    {
        $id = $posSettings?->default_customer_id ?: Client::CASH_CLIENT_ID;

        return Client::with('account:id,client_id,balance')
            ->whereHas('account')
            ->whereKey($id)
            ->first(['id', 'trade_name', 'phone', 'code'])

            // إعدادٌ يشير إلى عميلٍ حُذف أو فُكّ حسابُه: يُرجَع للنقدي
            ?? ($id === Client::CASH_CLIENT_ID ? null : Client::with('account:id,client_id,balance')
                ->whereHas('account')
                ->whereKey(Client::CASH_CLIENT_ID)
                ->first(['id', 'trade_name', 'phone', 'code']));
    }
}
