<?php

namespace App\Models\Store;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Concerns\DefaultsToMainBranch;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل بانرات المتجر
 * Store Banner Model
 *
 * يدير البانرات والإعلانات في المتجر الإلكتروني
 * يحتوي على معلومات الصورة والرابط والموقع والفترة الزمنية
 */
class StoreBanner extends Model
{
    use BelongsToBranch, DefaultsToMainBranch, HasFactory;

    protected $table = 'store_banners';

    protected $fillable = [
        'branch_id',
        'title',
        'description',
        'image',
        'mobile_image',
        'link',
        'link_type',
        'link_id',
        'position',
        'sort_order',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'link_type' => BannerLinkType::class,
        'position' => BannerPosition::class,
        'sort_order' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * البانرات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * البانرات السارية (ضمن الفترة الزمنية)
     */
    public function scopeValid($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            });
    }

    /**
     * فلترة حسب الموقع
     */
    public function scopeByPosition($query, BannerPosition $position)
    {
        return $query->where('position', $position);
    }

    /**
     * ترتيب البانرات
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * البحث في البانرات
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($subQuery) use ($searchTerm) {
            $subQuery->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * التحقق من صلاحية البانر
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * التحقق من انتهاء صلاحية البانر
     */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * التحقق من بداية صلاحية البانر
     */
    public function isStarted(): bool
    {
        return ! $this->start_date || $this->start_date->isPast();
    }

    /**
     * الحصول على الرابط المناسب حسب النوع
     */
    public function getFullLinkAttribute(): ?string
    {
        switch ($this->link_type) {
            case BannerLinkType::URL:
                return $this->link;

            case BannerLinkType::PRODUCT:
                return $this->link_id ? route('product.show', $this->link_id) : null;

            case BannerLinkType::CATEGORY:
                return $this->link_id ? route('category.show', $this->link_id) : null;

            case BannerLinkType::BRAND:
                return $this->link_id ? route('brand.show', $this->link_id) : null;

            case BannerLinkType::PAGE:
                return $this->link_id ? route('page.show', $this->link_id) : null;

            default:
                return $this->link;
        }
    }

    /**
     * الحصول على الصورة المناسبة حسب الجهاز
     */
    public function getImageForDevice($isMobile = false): string
    {
        if ($isMobile && $this->mobile_image) {
            return $this->mobile_image;
        }

        return $this->image;
    }

    /**
     * الحصول على بانرات موقع محدد
     */
    public static function getForPosition(BannerPosition $position, $limit = null)
    {
        $query = self::byPosition($position)
            ->valid()
            ->ordered();

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * الحصول على بانرات الصفحة الرئيسية
     */
    public static function getHomeSliders($limit = 5)
    {
        return self::getForPosition(BannerPosition::HOME_SLIDER, $limit);
    }

    /**
     * الحصول على بانرات الشريط الجانبي
     */
    public static function getSidebarBanners($limit = 3)
    {
        return self::getForPosition(BannerPosition::SIDEBAR, $limit);
    }

    /**
     * الحصول على العنوان المناسب حسب اللغة
     */
    public function getTitleForLocale($locale = 'ar'): string
    {

        return $this->title;
    }

    /**
     * الحصول على حالة البانر
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'غير نشط';
        }

        if ($this->isExpired()) {
            return 'منتهي الصلاحية';
        }

        if (! $this->isStarted()) {
            return 'لم يبدأ بعد';
        }

        return 'نشط';
    }

    /**
     * الحصول على الأيام المتبقية
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }

    /**
     * تفعيل البانر
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * إلغاء تفعيل البانر
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * نسخ البانر
     */
    public function duplicate(): self
    {
        $attributes = $this->getAttributes();
        unset($attributes['id']);
        unset($attributes['created_at']);
        unset($attributes['updated_at']);

        $attributes['title'] = $attributes['title'].' - نسخة';
        $attributes['is_active'] = false;
        $attributes['sort_order'] = $attributes['sort_order'] + 1;

        return self::create($attributes);
    }
}
