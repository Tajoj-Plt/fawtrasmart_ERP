<?php

namespace App\Models\Store;

use App\Enums\QuestionStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل أسئلة المنتجات
 * Product Question Model
 *
 * يدير الأسئلة التي يطرحها العملاء حول المنتجات
 * يحتوي على السؤال والإجابة وحالة النشر
 */
class ProductQuestion extends Model
{
    use HasFactory;

    protected $table = 'product_questions';

    protected $fillable = [
        'product_id',
        'client_id',
        'asker_name',
        'question',
        'answer',
        'answered_by',
        'answered_at',
        'status',
        'is_public',
    ];

    protected $casts = [
        'status' => QuestionStatus::class,
        'answered_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    /**
     * العلاقة مع المنتج
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * العلاقة مع العميل
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع المستخدم الذي أجاب
     */
    public function answeredBy()
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * الأسئلة المعلقة
     */
    public function scopePending($query)
    {
        return $query->where('status', QuestionStatus::PENDING);
    }

    /**
     * الأسئلة المجابة
     */
    public function scopeAnswered($query)
    {
        return $query->where('status', QuestionStatus::ANSWERED);
    }

    /**
     * الأسئلة المرفوضة
     */
    public function scopeRejected($query)
    {
        return $query->where('status', QuestionStatus::REJECTED);
    }

    /**
     * الأسئلة العامة المنشورة
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * أسئلة منتج محدد
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * أسئلة عميل محدد
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * إضافة إجابة للسؤال
     */
    public function addAnswer(string $answer, $userId): bool
    {
        return $this->update([
            'answer' => $answer,
            'answered_by' => $userId,
            'answered_at' => now(),
            'status' => QuestionStatus::ANSWERED,
        ]);
    }

    /**
     * رفض السؤال
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => QuestionStatus::REJECTED,
        ]);
    }

    /**
     * إعادة فتح السؤال للمراجعة
     */
    public function reopen(): bool
    {
        return $this->update([
            'status' => QuestionStatus::PENDING,
            'answer' => null,
            'answered_by' => null,
            'answered_at' => null,
        ]);
    }

    /**
     * التحقق من حالة السؤال
     */
    public function isPending(): bool
    {
        return $this->status === QuestionStatus::PENDING;
    }

    public function isAnswered(): bool
    {
        return $this->status === QuestionStatus::ANSWERED;
    }

    public function isRejected(): bool
    {
        return $this->status === QuestionStatus::REJECTED;
    }

    /**
     * التحقق من كون السؤال عام
     */
    public function isPublic(): bool
    {
        return $this->is_public;
    }

    /**
     * التحقق من وجود إجابة
     */
    public function hasAnswer(): bool
    {
        return ! empty($this->answer);
    }

    /**
     * الحصول على الأسئلة الأكثر شيوعاً لمنتج
     */
    public static function getFrequentQuestionsForProduct($productId, $limit = 5)
    {
        return self::forProduct($productId)
            ->answered()
            ->public()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * البحث في الأسئلة والأجوبة
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($subQuery) use ($searchTerm) {
            $subQuery->where('question', 'LIKE', "%{$searchTerm}%")
                ->orWhere('answer', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * الحصول على ملخص السؤال
     */
    public function getSummaryAttribute(): string
    {
        $summary = substr($this->question, 0, 100);
        if (strlen($this->question) > 100) {
            $summary .= '...';
        }

        return $summary;
    }

    /**
     * الحصول على وقت الإجابة منسق
     */
    public function getAnsweredTimeAttribute(): ?string
    {
        return $this->answered_at ? $this->answered_at->diffForHumans() : null;
    }

    /**
     * التحقق من إمكانية تعديل السؤال
     */
    public function canBeEdited(): bool
    {
        return $this->isPending() && $this->created_at->addHours(24)->isFuture();
    }
}
