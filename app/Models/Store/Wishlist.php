<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل قائمة الأمنيات (المفضلة)
 * Wishlist Model
 *
 * يدير المنتجات المفضلة للعملاء
 * يسمح للعملاء بحفظ المنتجات للشراء لاحقاً
 */
class Wishlist extends Model
{
    use HasFactory;

    protected $table = 'wishlists';

    protected $fillable = [
        'client_id',
        'product_id',
        'notes',
    ];

    /**
     * العلاقة مع العميل
     * المنتج المفضل ينتمي لعميل واحد
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع المنتج
     * المنتج المفضل يرتبط بمنتج واحد
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * الحصول على المنتجات المفضلة لعميل محدد
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * البحث عن منتج مفضل محدد لعميل
     */
    public function scopeForProduct($query, $clientId, $productId)
    {
        return $query->where('client_id', $clientId)
            ->where('product_id', $productId);
    }

    /**
     * إضافة منتج للمفضلة
     */
    public static function addToWishlist($clientId, $productId, $notes = null): self
    {
        return self::firstOrCreate(
            [
                'client_id' => $clientId,
                'product_id' => $productId,
            ],
            [
                'notes' => $notes,
            ]
        );
    }

    /**
     * إزالة منتج من المفضلة
     */
    public static function removeFromWishlist($clientId, $productId): bool
    {
        $wishlistItem = self::forProduct($clientId, $productId)->first();

        if ($wishlistItem) {
            return $wishlistItem->delete();
        }

        return false;
    }

    /**
     * التحقق من وجود المنتج في المفضلة
     */
    public static function isInWishlist($clientId, $productId): bool
    {
        return self::forProduct($clientId, $productId)->exists();
    }

    /**
     * الحصول على عدد المنتجات المفضلة للعميل
     */
    public static function getWishlistCount($clientId): int
    {
        return self::where('client_id', $clientId)->count();
    }

    /**
     * نقل المنتجات المفضلة إلى السلة
     */
    public function moveToCart(): bool
    {
        $cart = Cart::firstOrCreate([
            'client_id' => $this->client_id,
        ]);

        $cart->addItem($this->product_id);

        return $this->delete();
    }

    /**
     * نقل جميع المفضلة إلى السلة لعميل محدد
     */
    public static function moveAllToCart($clientId): int
    {
        $cart = Cart::firstOrCreate([
            'client_id' => $clientId,
        ]);

        $wishlistItems = self::forClient($clientId)->get();
        $movedCount = 0;

        foreach ($wishlistItems as $item) {
            $cart->addItem($item->product_id);
            $item->delete();
            $movedCount++;
        }

        return $movedCount;
    }

    /**
     * مسح جميع المفضلة لعميل محدد
     */
    public static function clearWishlist($clientId): int
    {
        return self::where('client_id', $clientId)->delete();
    }

    /**
     * الحصول على المنتجات المفضلة مع تفاصيل المنتج
     */
    public function scopeWithProductDetails($query)
    {
        return $query->with(['product' => function ($productQuery) {
            $productQuery->select('id', 'name', 'sale_price', 'images', 'status', 'available_online');
        }]);
    }

    /**
     * التحقق من توفر المنتج في المخزون
     */
    public function isProductAvailable(): bool
    {
        return $this->product &&
               $this->product->isActive() &&
               $this->product->available_online &&
               $this->product->totalQuantity() > 0;
    }
}
