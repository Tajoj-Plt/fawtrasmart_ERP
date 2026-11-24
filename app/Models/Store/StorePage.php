<?php

namespace App\Models\Store;

use App\Enums\StorePageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل صفحات المتجر الثابتة
 * Store Page Model
 *
 * يدير الصفحات الثابتة في المتجر مثل سياسات الخصوصية والشروط
 * يحتوي على المحتوى والعنوان وإعدادات SEO
 */
class StorePage extends Model
{
    use HasFactory;

    protected $table = 'store_pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'content_en',
        'meta_title',
        'meta_description',
        'type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type' => StorePageType::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * الصفحات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * فلترة حسب نوع الصفحة
     */
    public function scopeByType($query, StorePageType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * ترتيب الصفحات
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc');
    }

    /**
     * البحث في الصفحات
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($subQuery) use ($searchTerm) {
            $subQuery->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                ->orWhere('content_en', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * البحث بالـ slug
     */
    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * الحصول على العنوان حسب اللغة
     */
    public function getTitleForLocale($locale = 'ar'): string
    {

        return $this->title;
    }

    /**
     * الحصول على المحتوى حسب اللغة
     */
    public function getContentForLocale($locale = 'ar'): string
    {
        if ($locale === 'en' && $this->content_en) {
            return $this->content_en;
        }

        return $this->content ?? '';
    }

    /**
     * إنشاء slug تلقائياً من العنوان
     */
    public function generateSlug(): string
    {
        $title = $this->title;
        $slug = str($title)->slug();

        // التأكد من عدم تكرار الـ slug
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * حفظ الصفحة مع إنشاء slug تلقائي
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($page) {
            if (empty($page->slug)) {
                $page->slug = $page->generateSlug();
            }
        });
    }

    /**
     * الحصول على صفحات نوع محدد
     */
    public static function getByType(StorePageType $type)
    {
        return self::byType($type)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * الحصول على صفحات FAQ
     */
    public static function getFAQPages()
    {
        return self::getByType(StorePageType::FAQ);
    }

    /**
     * الحصول على صفحات السياسات
     */
    public static function getPolicyPages()
    {
        return self::getByType(StorePageType::POLICY);
    }

    /**
     * الحصول على الصفحات العادية
     */
    public static function getRegularPages()
    {
        return self::getByType(StorePageType::PAGE);
    }

    /**
     * البحث عن صفحة بالـ slug
     */
    public static function findBySlug($slug)
    {
        return self::bySlug($slug)->active()->first();
    }

    /**
     * الحصول على رابط الصفحة
     */
    public function getLinkAttribute(): string
    {
        return route('store.page.show', $this->slug);
    }

    /**
     * الحصول على ملخص المحتوى
     */
    public function getExcerptAttribute(): string
    {
        $content = strip_tags($this->content ?? '');

        return str($content)->limit(150);
    }

    /**
     * تفعيل الصفحة
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * إلغاء تفعيل الصفحة
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * نسخ الصفحة
     */
    public function duplicate(): self
    {
        $attributes = $this->getAttributes();
        unset($attributes['id']);
        unset($attributes['created_at']);
        unset($attributes['updated_at']);
        unset($attributes['slug']); // سيتم إنشاؤه تلقائياً

        $attributes['title'] = $attributes['title'].' - نسخة';

        $attributes['is_active'] = false;

        return self::create($attributes);
    }

    /**
     * الحصول على عدد الكلمات في المحتوى
     */
    public function getWordCountAttribute(): int
    {
        $content = strip_tags($this->content ?? '');

        return str_word_count($content);
    }

    /**
     * الحصول على وقت القراءة المقدر (بالدقائق)
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = $this->word_count;
        $averageWordsPerMinute = 200; // متوسط سرعة القراءة

        return max(1, ceil($wordCount / $averageWordsPerMinute));
    }
}
