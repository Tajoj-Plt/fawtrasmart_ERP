<?php

namespace App\Models\Store;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل إعجابات تقييمات المنتجات
 * Product Review Helpful Model
 *
 * يدير إعجابات العملاء بتقييمات المنتجات
 * يسمح للعملاء بتقييم مدى فائدة التقييمات الأخرى
 */
class ProductReviewHelpful extends Model
{
    use HasFactory;

    protected $table = 'product_review_helpfuls';

    protected $fillable = [
        'review_id',
        'client_id',
    ];

    /**
     * العلاقة مع التقييم
     * الإعجاب ينتمي لتقييم واحد
     */
    public function review()
    {
        return $this->belongsTo(ProductReview::class, 'review_id');
    }

    /**
     * العلاقة مع العميل
     * الإعجاب ينتمي لعميل واحد
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * إضافة إعجاب لتقييم
     */
    public static function addHelpful($reviewId, $clientId): bool
    {
        $helpful = self::firstOrCreate([
            'review_id' => $reviewId,
            'client_id' => $clientId,
        ]);

        if ($helpful->wasRecentlyCreated) {
            $helpful->review->incrementHelpful();

            return true;
        }

        return false;
    }

    /**
     * إزالة الإعجاب من تقييم
     */
    public static function removeHelpful($reviewId, $clientId): bool
    {
        $helpful = self::where('review_id', $reviewId)
            ->where('client_id', $clientId)
            ->first();

        if ($helpful) {
            $helpful->review->decrementHelpful();
            $helpful->delete();

            return true;
        }

        return false;
    }

    /**
     * التحقق من وجود إعجاب من عميل محدد
     */
    public static function isHelpful($reviewId, $clientId): bool
    {
        return self::where('review_id', $reviewId)
            ->where('client_id', $clientId)
            ->exists();
    }

    /**
     * عدد الإعجابات لتقييم محدد
     */
    public static function getHelpfulCount($reviewId): int
    {
        return self::where('review_id', $reviewId)->count();
    }
}
