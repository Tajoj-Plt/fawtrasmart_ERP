<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل تقييمات المنتجات
 * Product Review Model
 *
 * يدير تقييمات العملاء للمنتجات المختلفة
 * يحتوي على التقييم والتعليقات والصور والردود
 */
class ProductReview extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id',
        'client_id',
        'invoice_id',
        'rating',
        'title',
        'comment',
        'pros',
        'cons',
        'images',
        'status',
        'admin_reply',
        'replied_at',
        'replied_by',
        'helpful_count',
        'is_verified_purchase',
        'is_featured',
    ];

    protected $casts = [
        'rating' => 'integer',
        'pros' => 'array',
        'cons' => 'array',
        'images' => 'array',
        'replied_at' => 'datetime',
        'helpful_count' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * العلاقة مع المنتج
     * التقييم ينتمي لمنتج واحد
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * العلاقة مع العميل
     * التقييم ينتمي لعميل واحد
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع الفاتورة
     * التقييم مرتبط بفاتورة الشراء
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * العلاقة مع المستخدم الذي رد على التقييم
     */
    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * العلاقة مع إعجابات التقييم
     */
    public function helpfulVotes()
    {
        return $this->hasMany(ProductReviewHelpful::class, 'review_id');
    }

    /**
     * التقييمات المعتمدة فقط
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * التقييمات المعلقة
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * التقييمات المرفوضة
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * التقييمات المميزة
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * التقييمات المؤكدة الشراء
     */
    public function scopeVerifiedPurchase($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    /**
     * ترتيب التقييمات حسب التقييم (من الأعلى للأقل)
     */
    public function scopeOrderByRating($query, $direction = 'desc')
    {
        return $query->orderBy('rating', $direction);
    }

    /**
     * ترتيب التقييمات حسب الإعجابات
     */
    public function scopeOrderByHelpful($query, $direction = 'desc')
    {
        return $query->orderBy('helpful_count', $direction);
    }

    /**
     * فلترة التقييمات حسب نجمة محددة
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * اعتماد التقييم
     */
    public function approve(): bool
    {
        return $this->update(['status' => 'approved']);
    }

    /**
     * رفض التقييم
     */
    public function reject(): bool
    {
        return $this->update(['status' => 'rejected']);
    }

    /**
     * إضافة رد من الإدارة
     */
    public function addAdminReply(string $reply, $adminId): bool
    {
        return $this->update([
            'admin_reply' => $reply,
            'replied_at' => now(),
            'replied_by' => $adminId,
        ]);
    }

    /**
     * زيادة عدد الإعجابات
     */
    public function incrementHelpful(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * تقليل عدد الإعجابات
     */
    public function decrementHelpful(): void
    {
        $this->decrement('helpful_count');
    }

    /**
     * التحقق من موافقة التقييم
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * التحقق من انتظار التقييم
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * التحقق من رفض التقييم
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * التحقق من وجود رد من الإدارة
     */
    public function hasAdminReply(): bool
    {
        return ! empty($this->admin_reply);
    }

    /**
     * التحقق من كون التقييم مميز
     */
    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    /**
     * التحقق من تأكيد الشراء
     */
    public function isVerifiedPurchase(): bool
    {
        return $this->is_verified_purchase;
    }

    /**
     * الحصول على نص التقييم بالنجوم
     */
    public function getRatingTextAttribute(): string
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    /**
     * الحصول على ملخص التقييم
     */
    public function getSummaryAttribute(): string
    {
        $summary = $this->rating.' نجوم';

        if ($this->title) {
            $summary .= ' - '.$this->title;
        }

        if ($this->is_verified_purchase) {
            $summary .= ' (مشتري مؤكد)';
        }

        return $summary;
    }

    /**
     * التحقق من إمكانية تعديل التقييم
     */
    public function canBeEdited(): bool
    {
        return $this->isPending() || $this->created_at->addDays(7)->isFuture();
    }
}
