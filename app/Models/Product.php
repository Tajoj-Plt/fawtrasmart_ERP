<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Store\Brand;
use App\Models\Store\CartItem;
use App\Models\Store\ProductQuestion;
use App\Models\Store\ProductReview;
use App\Models\Store\RecentlyViewedProduct;
use App\Models\Store\Wishlist;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * قيمةُ سطرِ الإذن المخزني في حساب متوسّط التكلفة.
     *
     * السطرُ السليم يتّفق فيه `كمية × سعر` مع `total`، فالتعبيران واحد.
     * وحين يختلفان فالسطرُ معطوب، وتُؤخذ **القيمةُ الأصغر**: الشكلُ الذي
     * يفسد قيدَ التكلفة هو أن يبقى `unit_price` سعرَ العبوة والكميةُ
     * بالوحدة الأساسية — فيتضخّم `كمية × سعر` مئاتِ الأضعاف بينما يبقى
     * `total` صحيحاً. والشكلُ المعاكس (كميةٌ كُتبت ١ وإجمالي سطرٍ كامل)
     * يُطفَأ بالقاعدة نفسها: السطرُ يدخل بقيمةٍ ضئيلةٍ لا تحرّك المتوسّط،
     * بدل أن يقفز به. انظر `averageCost()`.
     */
    private const COST_LINE_VALUE_SUM = 'SUM(CASE WHEN warehouse_permits_products.total > 0'
        .' AND warehouse_permits_products.total < warehouse_permits_products.quantity * warehouse_permits_products.unit_price'
        .' THEN warehouse_permits_products.total'
        .' ELSE warehouse_permits_products.quantity * warehouse_permits_products.unit_price END) as total_value';

    protected $table = 'products';

    protected $fillable = ['id', 'name', 'sku', 'notify_before_days', 'expiry_date', 'description', 'category_id', 'brand_id', 'serial_number', 'supplier_id', 'low_stock_thershold', 'barcode', 'sales_cost_account', 'sales_account', 'available_online', 'available_pos', 'featured_product', 'track_inventory', 'inventory_type', 'low_stock_alert', 'Internal_notes', 'images', 'status', 'is_active', 'purchase_price', 'cost_basis_unit_price', 'sale_price', 'tax1', 'tax2', 'min_sale_price', 'discount', 'discount_type', 'profit_margin', 'type', 'weight', 'dimensions', 'is_returnable', 'return_days', 'created_by', 'created_at', 'updated_at'];

    protected $casts = [
        'status' => ProductStatus::class,
        'is_active' => 'boolean',
        'dimensions' => 'array',
        'cost_basis_unit_price' => 'decimal:4',
    ];

    // hasOne relationship for single product detail
    public function product_details()
    {
        return $this->hasOne(ProductDetails::class, 'product_id');
    }

    // hasMany relationship for multiple product details (different storehouses)
    public function productDetails()
    {
        return $this->hasMany(ProductDetails::class, 'product_id');
    }

    public function totalQuantity()
    {
        return $this->productDetails()->sum('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function price_list()
    {
        return $this->belongsToMany(PriceList::class, 'price_list_items');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function invoice_items()
    {
        return $this->hasMany(InvoiceItem::class, 'product_id');
    }

    public function totalSold()
    {
        return $this->invoice_items()->sum('quantity');
    }

    public function totalSoldLast28Days()
    {
        return $this->invoice_items()
            ->where('created_at', '>=', Carbon::now()->subDays(28))
            ->sum('quantity');
    }

    public function totalSoldLast7Days()
    {
        return $this->invoice_items()
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->sum('quantity');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehousePermitsProducts()
    {
        return $this->hasMany(WarehousePermitsProducts::class, 'product_id');
    }

    /**
     * سعر الوحدة المعروض والمستخدم كأساس تكلفة: التسوية اليدوية إن وُجدت، وإلا المتوسط المرجّح من الحركات، وإلا سعر الشراء.
     */
    public function resolvedUnitCost(): float
    {
        if ($this->cost_basis_unit_price !== null && (float) $this->cost_basis_unit_price > 0) {
            return round((float) $this->cost_basis_unit_price, 2);
        }
        $avg = $this->averageCost();
        if ($avg !== null && (float) $avg > 0) {
            return round((float) $avg, 2);
        }

        return round((float) ($this->purchase_price ?? 0), 2);
    }

    /**
     * حساب متوسط التكلفة المرجح للمنتج
     * يستبعد العمليات التي لا تؤثر على التكلفة الفعلية:
     * - التحويل بين المخازن
     * - فواتير المبيعات
     * - مرتجع المبيعات
     * - الإشعارات الدائنة
     * - الصرف والسحب من المخزن
     *
     * ═══════════ قيمةُ السطر تؤخذ من `total` لا من `unit_price` ═══════════
     *
     * كميةُ الإذن المخزني **بالوحدة الأساسية دائماً**، أمّا `unit_price` فقد
     * بقي في أذونٍ كثيرةٍ **سعرَ الوحدة المشتراة** (الكرتون أو العلبة) —
     * فصنفٌ اشتُري ٤ كراتين بـ ١٩١٫٣٠ للكرتون كُتب سطرُه:
     * `quantity = 4000` (حبة) و `unit_price = 191.30` (للكرتون)
     * و `total = 765.20` (صحيح). فالمتوسّطُ المحسوب من `كمية × سعر` يخرج
     * ١٨٨ للحبّة بدل ٠٫١٩ — ألفَ ضعف — ومنه خرج قيدُ تكلفة مبيعاتٍ بـ٩٤٬١٩٠
     * لفاتورةٍ قيمتُها أربعُمئة.
     *
     * و `total` هو نفسُه ما تبني عليه `WarehousePermitsProducts::inventoryLineFromInvoiceItem()`
     * سعرَ الوحدة في الأذون الجديدة (`total ÷ quantity`) — فالاعتمادُ عليه
     * هنا يوحّد المرجع ولا يخترع معياراً جديداً، ويُحصّن الحسابَ من كل سطرٍ
     * قديمٍ بقي سعرُه بوحدة العبوة.
     *
     * وما دام السطرُ سليماً فالتعبيران متساويان ولا يتغيّر شيء — القاعدةُ
     * لا تعمل إلا على سطرٍ متناقض، وتفصيلُها في `COST_LINE_VALUE_SUM`.
     *
     * @return float|null
     */
    public function averageCost()
    {
        // العمليات التي يجب استبعادها من حساب متوسط التكلفة
        $excludedSources = [
            \App\Constants\permissions\PermissionSources::MANUAL_TRANSFER,        // تحويل يدوي بين المخازن
            \App\Constants\permissions\PermissionSources::POS_SHIFT_OUTBOUND,     // تحويل صادر من نقطة البيع
            \App\Constants\permissions\PermissionSources::POS_SHIFT_INBOUND,      // تحويل وارد لنقطة البيع
            \App\Constants\permissions\PermissionSources::SALES_INVOICE,          // فاتورة مبيعات
            \App\Constants\permissions\PermissionSources::SALES_RETURN,           // مرتجع مبيعات
            \App\Constants\permissions\PermissionSources::SALES_CREDIT_NOTE,      // إشعار دائن مبيعات
            \App\Constants\permissions\PermissionSources::WAREHOUSE_DISBURSEMENT, // صرف من المخزن
            \App\Constants\permissions\PermissionSources::WAREHOUSE_WITHDRAWAL,   // سحب من المخزن
        ];

        // حساب المتوسط المرجح: Σ(قيمة السطر) / Σ(الكمية بالوحدة الأساسية)
        // فقط من عمليات الشراء والإضافة (الكميات الموجبة)
        $movements = $this->warehousePermitsProducts()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->whereNotIn('warehouse_permits.permission_source_id', $excludedSources)
            ->where('warehouse_permits_products.quantity', '>', 0)
            ->where('warehouse_permits_products.unit_price', '>', 0)
            ->selectRaw(self::COST_LINE_VALUE_SUM.', SUM(warehouse_permits_products.quantity) as total_quantity')
            ->first();

        if ($movements && $movements->total_quantity > 0) {
            return round($movements->total_value / $movements->total_quantity, 2);
        }

        // إذا لم توجد حركات شراء، إرجاع سعر الشراء الافتراضي
        return $this->purchase_price ?? 0;
    }

    /**
     * متوسط تكلفة الوحدة لهذا المنتج من حركات إذن مرتبطة بمستودع محدد (لتسوية أدق لكل مستودع).
     */
    public function averageCostForStorehouse(?int $storehouseId): ?float
    {
        if ($storehouseId === null) {
            return null;
        }

        $excludedSources = [
            \App\Constants\permissions\PermissionSources::MANUAL_TRANSFER,
            \App\Constants\permissions\PermissionSources::POS_SHIFT_OUTBOUND,
            \App\Constants\permissions\PermissionSources::POS_SHIFT_INBOUND,
            \App\Constants\permissions\PermissionSources::SALES_INVOICE,
            \App\Constants\permissions\PermissionSources::SALES_RETURN,
            \App\Constants\permissions\PermissionSources::SALES_CREDIT_NOTE,
            \App\Constants\permissions\PermissionSources::WAREHOUSE_DISBURSEMENT,
            \App\Constants\permissions\PermissionSources::WAREHOUSE_WITHDRAWAL,
        ];

        $movements = $this->warehousePermitsProducts()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->where('warehouse_permits.store_houses_id', $storehouseId)
            ->whereNotIn('warehouse_permits.permission_source_id', $excludedSources)
            ->where('warehouse_permits_products.quantity', '>', 0)
            ->where('warehouse_permits_products.unit_price', '>', 0)
            ->selectRaw(self::COST_LINE_VALUE_SUM.', SUM(warehouse_permits_products.quantity) as total_quantity')
            ->first();

        if ($movements && $movements->total_quantity > 0) {
            return round($movements->total_value / $movements->total_quantity, 2);
        }

        return null;
    }

    /**
     * حساب متوسط التكلفة المرجح الجديد عند إضافة كمية جديدة بسعر شراء
     * الصيغة: (الكمية القديمة × السعر القديم + الكمية الجديدة × السعر الجديد) / (الكمية القديمة + الكمية الجديدة)
     *
     * @param  float  $newQuantity  الكمية الجديدة المضافة
     * @param  float  $newUnitPrice  سعر الوحدة للكمية الجديدة
     * @return float متوسط التكلفة المرجح الجديد
     */
    public function calculateNewWeightedAverageCost($newQuantity, $newUnitPrice)
    {
        // الكمية الحالية في المخزون
        $currentQuantity = $this->totalQuantity() ?? 0;

        // السعر الحالي (متوسط التكلفة الحالي أو سعر الشراء)
        $currentPrice = $this->averageCost() ?? $this->purchase_price ?? 0;

        // إذا لم يكن هناك مخزون سابق، السعر الجديد هو المتوسط
        if ($currentQuantity <= 0) {
            return round($newUnitPrice, 2);
        }

        // حساب متوسط التكلفة المرجح الجديد
        $totalValue = ($currentQuantity * $currentPrice) + ($newQuantity * $newUnitPrice);
        $totalQuantity = $currentQuantity + $newQuantity;

        if ($totalQuantity <= 0) {
            return round($newUnitPrice, 2);
        }

        return round($totalValue / $totalQuantity, 2);
    }

    /**
     * تحديث سعر الشراء باستخدام متوسط التكلفة المرجح
     * يتم التحديث فقط إذا كان السعر الجديد مختلفاً عن السعر الحالي
     *
     * @param  float  $newQuantity  الكمية الجديدة المضافة
     * @param  float  $newUnitPrice  سعر الوحدة للكمية الجديدة
     * @return bool تم التحديث أم لا
     */
    public function updatePurchasePriceWithWeightedAverage($newQuantity, $newUnitPrice)
    {
        // إذا كان السعر الجديد يساوي السعر الحالي، لا نحتاج للتحديث
        $currentPrice = $this->purchase_price ?? 0;

        if (round($newUnitPrice, 2) == round($currentPrice, 2)) {
            return false; // لم يتم التحديث - السعر نفسه
        }

        // حساب متوسط التكلفة المرجح الجديد
        $newAverageCost = $this->calculateNewWeightedAverageCost($newQuantity, $newUnitPrice);

        // تحديث سعر الشراء
        $this->update([
            'purchase_price' => $newAverageCost,
            'updated_at' => now(),
        ]);

        return true;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            InvoiceItem::class,
            'product_id', // Foreign key on invoice_items table
            'id', // Local key on invoices table
            'id', // Local key on products table
            'invoice_id' // Foreign key on invoice_items table
        );
    }

    /**
     * Get the total quantity of this product in a specific storehouse
     */
    public function quantityInStorehouse($storehouseId)
    {
        return $this->productDetails()
            ->where('store_house_id', $storehouseId)
            ->sum('quantity');
    }

    /**
     * Get the total quantity of this product sold from a specific storehouse
     */
    public function soldFromStorehouse($storehouseId)
    {
        return $this->invoice_items()
            ->where('store_house_id', $storehouseId)
            ->sum('quantity');
    }

    /**
     * Get the remaining quantity of this product in a specific storehouse
     */
    public function remainingInStorehouse($storehouseId)
    {
        $total = $this->quantityInStorehouse($storehouseId);
        $sold = $this->soldFromStorehouse($storehouseId);

        return $total - $sold;
    }

    /**
     * Get all storehouses where this product is available with quantities
     */
    public function storehousesWithQuantities()
    {
        return $this->productDetails()
            ->join('store_houses', 'product_details.store_house_id', '=', 'store_houses.id')
            ->select(
                'store_houses.id as storehouse_id',
                'store_houses.name as storehouse_name',
                \DB::raw('SUM(product_details.quantity) as total_quantity')
            )
            ->groupBy('store_houses.id', 'store_houses.name')
            ->get();
    }

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ACTIVE->value);
    }

    /**
     * Scope to get only inactive products
     */
    public function scopeInactive($query)
    {
        return $query->where('status', ProductStatus::UNACTIVE->value);
    }

    // ==================== علاقات المتجر الإلكتروني ====================

    /**
     * العلاقة مع العلامة التجارية
     * المنتج ينتمي لعلامة تجارية واحدة
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * العلاقة مع تقييمات المنتج
     * المنتج لديه عدة تقييمات
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    /**
     * العلاقة مع التقييمات المعتمدة فقط
     */
    public function approvedReviews()
    {
        return $this->reviews()->where('status', 'approved');
    }

    /**
     * العلاقة مع أسئلة المنتج
     * المنتج لديه عدة أسئلة من العملاء
     */
    public function questions()
    {
        return $this->hasMany(ProductQuestion::class, 'product_id');
    }

    /**
     * العلاقة مع قوائم الأمنيات
     * المنتج يمكن أن يكون في قوائم أمنيات متعددة
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'product_id');
    }

    /**
     * العلاقة مع عناصر السلة
     * المنتج يمكن أن يكون في عدة سلال
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'product_id');
    }

    /**
     * العلاقة مع المنتجات المشاهدة مؤخراً
     */
    public function recentlyViewed()
    {
        return $this->hasMany(RecentlyViewedProduct::class, 'product_id');
    }

    // ==================== دوال مساعدة للمتجر الإلكتروني ====================

    /**
     * متوسط التقييم للمنتج
     */
    public function averageRating(): float
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }

    /**
     * عدد التقييمات المعتمدة
     */
    public function reviewsCount(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * التحقق من إمكانية عرض المنتج في المتجر الإلكتروني
     */
    public function isAvailableOnline(): bool
    {
        return $this->isActive() &&
               $this->available_online &&
               $this->totalQuantity() > 0;
    }

    /**
     * التحقق من كون المنتج مميز
     */
    public function isFeatured(): bool
    {
        return $this->featured_product;
    }

    /**
     * الحصول على نجوم التقييم (1-5)
     */
    public function getRatingStarsAttribute(): int
    {
        return (int) round($this->averageRating());
    }

    /**
     * المنتجات المتاحة للمتجر الإلكتروني فقط
     */
    public function scopeAvailableOnline($query)
    {
        return $query->where('status', ProductStatus::ACTIVE->value)
            ->where('available_online', true);
    }

    /**
     * المنتجات المميزة
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured_product', true);
    }

    // ==================== دوال الخصم والأسعار ====================

    /**
     * التحقق من نوع الخصم (نسبة مئوية)
     * discount_type: 1 = percentage, 2 = fixed
     */
    public function isPercentageDiscount(): bool
    {
        return $this->discount_type == 1 || $this->discount_type === 'percentage';
    }

    /**
     * التحقق من نوع الخصم (مبلغ ثابت)
     */
    public function isFixedDiscount(): bool
    {
        return $this->discount_type == 2 || $this->discount_type === 'fixed';
    }

    /**
     * حساب قيمة الخصم الفعلية بالريال
     */
    public function getDiscountAmount(): float
    {
        if (! $this->discount || $this->discount <= 0) {
            return 0;
        }

        if ($this->isPercentageDiscount()) {
            // خصم نسبة مئوية
            return round(($this->sale_price * $this->discount) / 100, 2);
        }

        // خصم مبلغ ثابت
        return min($this->discount, $this->sale_price);
    }

    /**
     * حساب السعر بعد الخصم
     */
    public function getFinalPrice(): float
    {
        return round($this->sale_price - $this->getDiscountAmount(), 2);
    }

    /**
     * التحقق من وجود خصم فعال
     */
    public function hasDiscount(): bool
    {
        return $this->discount && $this->discount > 0;
    }

    /**
     * الحصول على نص نوع الخصم
     */
    public function getDiscountTypeText(): string
    {
        return $this->isPercentageDiscount() ? '%' : 'ر.س';
    }

    /**
     * العلاقة مع وحدات المنتج
     */
    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    /**
     * الوحدات النشطة للمنتج
     */
    public function activeProductUnits()
    {
        return $this->productUnits()->where('is_active', true);
    }

    /**
     * الوحدة الافتراضية للمنتج
     */
    public function defaultUnit()
    {
        return $this->hasOne(ProductUnit::class, 'product_id')->where('is_default', true);
    }

    /**
     * الحصول على الوحدة الافتراضية أو الأولى
     */
    public function getDefaultProductUnit(): ?ProductUnit
    {
        return $this->defaultUnit ?? $this->activeProductUnits()->first();
    }

    /**
     * البحث عن وحدة بالباركود
     */
    public function findUnitByBarcode(string $barcode): ?ProductUnit
    {
        return $this->productUnits()->where('barcode', $barcode)->first();
    }

    /**
     * الحصول على سعر البيع الافتراضي
     */
    public function getDefaultSalePrice(): float
    {
        $defaultUnit = $this->getDefaultProductUnit();

        return $defaultUnit?->sale_price ?? $this->sale_price ?? 0;
    }

    /**
     * الحصول على سعر الشراء الافتراضي
     */
    public function getDefaultPurchasePrice(): float
    {
        $defaultUnit = $this->getDefaultProductUnit();

        return $defaultUnit?->purchase_price ?? $this->purchase_price ?? 0;
    }

    // ============================================
    // خيارات المنتج ومتغيّراته
    // ============================================

    public function options()
    {
        return $this->hasMany(\App\Models\Store\ProductOption::class, 'product_id')->orderBy('position');
    }

    public function variants()
    {
        return $this->hasMany(\App\Models\Store\ProductVariant::class, 'product_id');
    }

    public function activeVariants()
    {
        return $this->variants()->where('is_active', true);
    }

    /** هل للمنتج متغيّرات؟ — لا عمود مخزَّن، استنتاجٌ من وجود صفوفٍ فعلية. */
    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }
}
