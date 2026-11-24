<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل المنتجات المشاهدة مؤخراً
 * Recently Viewed Products Model
 *
 * يدير المنتجات التي شاهدها العملاء مؤخراً
 * يساعد في تحسين تجربة التسوق وتقديم اقتراحات
 */
class RecentlyViewedProduct extends Model
{
    use HasFactory;

    protected $table = 'recently_viewed_products';

    protected $fillable = [
        'client_id',
        'session_id',
        'product_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * العلاقة مع العميل
     * المنتج المشاهد ينتمي لعميل (أو زائر)
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع المنتج
     * المنتج المشاهد يرتبط بمنتج واحد
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * المنتجات المشاهدة لعميل محدد
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * المنتجات المشاهدة لجلسة محددة
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * المنتجات المشاهدة خلال فترة معينة
     */
    public function scopeWithinDays($query, $days = 30)
    {
        return $query->where('viewed_at', '>=', now()->subDays($days));
    }

    /**
     * ترتيب حسب آخر مشاهدة
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('viewed_at', 'desc');
    }

    /**
     * إضافة منتج للمشاهدة مؤخراً
     */
    public static function addView($productId, $clientId = null, $sessionId = null): self
    {
        // حذف المشاهدة السابقة للمنتج إن وجدت
        $existing = self::where('product_id', $productId);

        if ($clientId) {
            $existing->where('client_id', $clientId);
        } else {
            $existing->where('session_id', $sessionId);
        }

        $existing->delete();

        // إضافة المشاهدة الجديدة
        return self::create([
            'product_id' => $productId,
            'client_id' => $clientId,
            'session_id' => $sessionId,
            'viewed_at' => now(),
        ]);
    }

    /**
     * الحصول على المنتجات المشاهدة مؤخراً
     */
    public static function getRecentlyViewed($clientId = null, $sessionId = null, $limit = 10)
    {
        $query = self::query();

        if ($clientId) {
            $query->forClient($clientId);
        } elseif ($sessionId) {
            $query->forSession($sessionId);
        } else {
            return collect();
        }

        return $query->with(['product' => function ($productQuery) {
            $productQuery->select('id', 'name', 'sale_price', 'images', 'status', 'available_online')
                ->where('status', 'active')
                ->where('available_online', true);
        }])
            ->latest()
            ->limit($limit)
            ->get()
            ->filter(function ($item) {
                return $item->product !== null;
            });
    }

    /**
     * تنظيف المشاهدات القديمة
     */
    public static function cleanup($daysOld = 90): int
    {
        return self::where('viewed_at', '<', now()->subDays($daysOld))->delete();
    }

    /**
     * الحد الأقصى للمشاهدات المحفوظة لكل عميل
     */
    public static function limitViewsPerUser($clientId = null, $sessionId = null, $maxViews = 50): void
    {
        $query = self::query();

        if ($clientId) {
            $query->forClient($clientId);
        } elseif ($sessionId) {
            $query->forSession($sessionId);
        } else {
            return;
        }

        $totalViews = $query->count();

        if ($totalViews > $maxViews) {
            $viewsToDelete = $totalViews - $maxViews;
            $oldestViews = $query->orderBy('viewed_at', 'asc')
                ->limit($viewsToDelete)
                ->pluck('id');

            self::whereIn('id', $oldestViews)->delete();
        }
    }

    /**
     * دمج المشاهدات من الجلسة إلى حساب العميل عند تسجيل الدخول
     */
    public static function mergeSessionToClient($sessionId, $clientId): int
    {
        $sessionViews = self::forSession($sessionId)->get();
        $mergedCount = 0;

        foreach ($sessionViews as $view) {
            // التحقق من عدم وجود المنتج في مشاهدات العميل
            $existingView = self::forClient($clientId)
                ->where('product_id', $view->product_id)
                ->first();

            if (! $existingView) {
                $view->update([
                    'client_id' => $clientId,
                    'session_id' => null,
                ]);
                $mergedCount++;
            } else {
                // تحديث وقت المشاهدة إذا كان أحدث
                if ($view->viewed_at > $existingView->viewed_at) {
                    $existingView->update(['viewed_at' => $view->viewed_at]);
                }
                $view->delete();
            }
        }

        return $mergedCount;
    }

    /**
     * الحصول على المنتجات الأكثر مشاهدة
     */
    public static function getMostViewed($days = 30, $limit = 10)
    {
        return self::select('product_id')
            ->selectRaw('COUNT(*) as views_count')
            ->withinDays($days)
            ->groupBy('product_id')
            ->orderBy('views_count', 'desc')
            ->with('product')
            ->limit($limit)
            ->get();
    }
}
