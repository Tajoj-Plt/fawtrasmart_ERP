<?php

namespace App\Models\Store;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل العلامات التجارية (البراندات)
 * Brand Model
 *
 * يدير معلومات العلامات التجارية المختلفة في النظام
 * يحتوي على تفاصيل البراند مثل الاسم والشعار والوصف
 */
class Brand extends Model
{
    use HasFactory;

    protected $table = 'brands';

    protected $fillable = [
        'name',

        'slug',
        'description',
        'logo',
        'website',
        'is_active',
        'is_featured',
        'sort_order',
        'meta_title',
        'meta_description',

        /*
         * أعمدة الجيل الأول من الجدول — لا تزال موجودة ويكتبها الـAPI
         * والبذرة. تُركت قابلةً للتعبئة كي لا يسقط ما يعتمد عليها بصمت.
         */
        'country_of_origin',
        'contact_email',
        'contact_phone',
        'status',
        'display_order',
        'logo_path',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * العلاقة مع المنتجات
     * العلامة التجارية لديها العديد من المنتجات
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /**
     * عدد المنتجات النشطة للبراند
     */
    public function activeProductsCount()
    {
        return $this->products()->active()->count();
    }

    /**
     * البراندات النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * البراندات المميزة
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * ترتيب البراندات
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * بحث نصّي في الاسم والوصف — يستعمله الـAPI
     */
    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }

    /**
     * التحقق من نشاط البراند
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * التحقق من البراند المميز
     */
    public function isFeatured(): bool
    {
        return $this->is_featured;
    }
}
