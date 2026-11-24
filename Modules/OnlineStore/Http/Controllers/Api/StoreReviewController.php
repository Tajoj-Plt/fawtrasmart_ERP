<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Store\OnlineStoreSetting;
use App\Models\Store\ProductReview;
use App\Models\Store\ProductReviewHelpful;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم تقييمات المنتجات للمتجر الإلكتروني
 * Store Product Reviews Controller
 *
 * يدير إنشاء وعرض تقييمات المنتجات
 */
class StoreReviewController extends Controller
{
    use FormatsImages;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * عرض تقييمات منتج
     * List product reviews
     */
    public function index(Request $request, int $productId): JsonResponse
    {
        $product = Product::find($productId);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        $query = ProductReview::where('product_id', $productId)
            ->approved()
            ->with('client:id,first_name,last_name');

        // تصفية حسب التقييم
        if ($request->filled('rating')) {
            $query->byRating($request->rating);
        }

        // المشتريات الموثقة فقط
        if ($request->boolean('verified_only')) {
            $query->verifiedPurchase();
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');

        switch ($sortBy) {
            case 'helpful':
                $query->orderByHelpful();
                break;
            case 'rating_high':
                $query->orderByRating('desc');
                break;
            case 'rating_low':
                $query->orderByRating('asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = min($request->get('per_page', 10), 50);
        $reviews = $query->paginate($perPage);

        // حساب الإحصائيات
        $stats = $this->getProductReviewStats($productId);

        $client = $this->client();
        $clientId = $client?->id;

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'reviews' => $reviews->map(fn ($review) => $this->formatReview($review, $clientId)),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * إحصائيات تقييمات منتج (المتوسط وتوزيع النجوم) — المسار كان مسجَّلاً بلا دالة فيسقط بـ500.
     * Product review stats
     */
    public function stats(Request $request, int $productId): JsonResponse
    {
        if (! Product::whereKey($productId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'stats' => $this->getProductReviewStats($productId),
        ]);
    }

    /**
     * إنشاء تقييم جديد
     * Create new review
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'bail|required|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'pros' => 'nullable|string|max:1000',
            'cons' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'string',
        ], [
            'product_id.required' => 'المنتج مطلوب',
            'rating.required' => 'التقييم مطلوب',
            'rating.min' => 'التقييم يجب أن يكون 1 على الأقل',
            'rating.max' => 'التقييم يجب أن يكون 5 كحد أقصى',
            'comment.required' => 'التعليق مطلوب',
            'comment.min' => 'التعليق يجب أن يكون 10 أحرف على الأقل',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول لإضافة تقييم',
            ], 401);
        }

        $productId = $request->product_id;

        // التحقق من عدم وجود تقييم سابق
        $existingReview = ProductReview::where('product_id', $productId)
            ->where('client_id', $client->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'لقد قمت بتقييم هذا المنتج مسبقاً',
            ], 400);
        }

        // التحقق من الشراء الموثق
        $isVerifiedPurchase = $this->isVerifiedPurchase($client->id, $productId);
        $invoiceId = $this->getLastPurchaseInvoice($client->id, $productId);

        // تحويل pros و cons من strings إلى arrays
        $pros = $request->pros ? array_map('trim', explode(',', $request->pros)) : null;
        $cons = $request->cons ? array_map('trim', explode(',', $request->cons)) : null;

        // التحقق من الموافقة التلقائية للتقييمات
        $autoApprove = OnlineStoreSetting::get('auto_approve_reviews', false);
        $status = $autoApprove ? 'approved' : 'pending';
        $message = $autoApprove ? 'تم نشر التقييم بنجاح' : 'تم إرسال التقييم بنجاح وسيتم مراجعته';

        $review = ProductReview::create([
            'product_id' => $productId,
            'client_id' => $client->id,
            'invoice_id' => $invoiceId,
            'rating' => $request->rating,
            'title' => $request->title,
            'comment' => $request->comment,
            'pros' => $pros,
            'cons' => $cons,
            'images' => $request->images,
            'is_verified_purchase' => $isVerifiedPurchase,
            'status' => $status,
            'helpful_count' => 0,
        ]);

        OperationLog::record('created', 'product_review', $review, 'أضاف العميل تقييماً للمنتج **'.$review->product?->name.'** بتقييم '.$review->rating.'/5');

        return response()->json([
            'success' => true,
            'message' => $message,
            'review' => $this->formatReview($review->fresh('client'), $client->id),
        ], 201);
    }

    /**
     * تحديث تقييم
     * Update review
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $review = ProductReview::where('client_id', $client->id)->find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'sometimes|string|min:10|max:2000',
            'pros' => 'nullable|string|max:1000',
            'cons' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // تحضير البيانات مع تحويل pros و cons
        $updateData = [];
        if ($request->has('rating')) {
            $updateData['rating'] = $request->rating;
        }
        if ($request->has('title')) {
            $updateData['title'] = $request->title;
        }
        if ($request->has('comment')) {
            $updateData['comment'] = $request->comment;
        }
        if ($request->has('images')) {
            $updateData['images'] = $request->images;
        }

        // تحويل pros و cons من strings إلى arrays
        if ($request->has('pros')) {
            $updateData['pros'] = $request->pros ? array_map('trim', explode(',', $request->pros)) : null;
        }
        if ($request->has('cons')) {
            $updateData['cons'] = $request->cons ? array_map('trim', explode(',', $request->cons)) : null;
        }

        $review->update($updateData);

        // التحقق من الموافقة التلقائية للتقييمات
        $autoApprove = OnlineStoreSetting::get('auto_approve_reviews', false);
        $status = $autoApprove ? 'approved' : 'pending';
        $message = $autoApprove ? 'تم تحديث التقييم بنجاح' : 'تم تحديث التقييم وسيتم مراجعته';

        // إعادة للمراجعة بعد التحديث
        $review->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'review' => $this->formatReview($review->fresh('client'), $client->id),
        ]);
    }

    /**
     * حذف تقييم
     * Delete review
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $review = ProductReview::where('client_id', $client->id)->find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التقييم',
        ]);
    }

    /**
     * تقييماتي
     * My reviews
     */
    public function myReviews(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $reviews = ProductReview::where('client_id', $client->id)
            ->with('product:id,name,images')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'reviews' => $reviews->map(fn ($review) => [
                'id' => $review->id,
                'product' => [
                    'id' => $review->product?->id,
                    'name' => $review->product?->name,
                    'image' => $this->getFirstImageUrl($review->product?->images),
                ],
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'status' => $review->status->value,
                'status_label' => $this->getStatusLabel($review->status),
                'is_verified_purchase' => $review->is_verified_purchase,
                'helpful_count' => $review->helpful_count,
                'created_at' => $review->created_at->toDateTimeString(),
            ]),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * إضافة إعجاب لتقييم
     * Mark review as helpful
     */
    public function helpful(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $review = ProductReview::approved()->find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود',
            ], 404);
        }

        // لا يمكن الإعجاب بتقييمك الخاص
        if ($review->client_id === $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك الإعجاب بتقييمك',
            ], 400);
        }

        $added = ProductReviewHelpful::addHelpful($id, $client->id);

        return response()->json([
            'success' => true,
            'message' => $added ? 'تم إضافة الإعجاب' : 'الإعجاب موجود مسبقاً',
            'helpful_count' => $review->fresh()->helpful_count,
        ]);
    }

    /**
     * إزالة الإعجاب من تقييم
     * Remove helpful from review
     */
    public function unhelpful(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $review = ProductReview::find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود',
            ], 404);
        }

        $removed = ProductReviewHelpful::removeHelpful($id, $client->id);

        return response()->json([
            'success' => true,
            'message' => $removed ? 'تم إزالة الإعجاب' : 'لم يتم العثور على الإعجاب',
            'helpful_count' => $review->fresh()->helpful_count,
        ]);
    }

    /**
     * التحقق من إمكانية التقييم
     * Check if can review
     */
    public function canReview(Request $request, int $productId): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'can_review' => false,
                'message' => 'يجب تسجيل الدخول لإضافة تقييم',
            ], 401);
        }

        // التحقق من وجود تقييم سابق
        $hasReview = ProductReview::where('product_id', $productId)
            ->where('client_id', $client->id)
            ->exists();

        if ($hasReview) {
            return response()->json([
                'success' => true,
                'can_review' => false,
                'reason' => 'already_reviewed',
                'message' => 'لقد قمت بتقييم هذا المنتج مسبقاً',
            ]);
        }

        // التحقق من الشراء
        $hasPurchased = $this->isVerifiedPurchase($client->id, $productId);

        return response()->json([
            'success' => true,
            'can_review' => true,
            'is_verified_purchase' => $hasPurchased,
            'message' => $hasPurchased
                ? 'يمكنك تقييم هذا المنتج (مشتري موثق)'
                : 'يمكنك تقييم هذا المنتج',
        ]);
    }

    /**
     * الحصول على إحصائيات التقييمات
     */
    private function getProductReviewStats(int $productId): array
    {
        $reviews = ProductReview::where('product_id', $productId)->approved();

        $total = $reviews->count();
        $average = $reviews->avg('rating') ?? 0;

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = ProductReview::where('product_id', $productId)
                ->approved()
                ->where('rating', $i)
                ->count();
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'total' => $total,
            'average' => round($average, 1),
            'distribution' => $distribution,
        ];
    }

    /**
     * التحقق من الشراء الموثق
     */
    private function isVerifiedPurchase(int $clientId, int $productId): bool
    {
        return Invoice::where('client_id', $clientId)
            ->where('type', 'sales')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();
    }

    /**
     * الحصول على آخر فاتورة شراء
     */
    private function getLastPurchaseInvoice(int $clientId, int $productId): ?int
    {
        $invoice = Invoice::where('client_id', $clientId)
            ->where('type', 'sales')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->orderBy('created_at', 'desc')
            ->first();

        return $invoice?->id;
    }

    /**
     * تنسيق التقييم
     */
    private function formatReview(ProductReview $review, ?int $currentClientId = null): array
    {
        $isHelpful = false;
        if ($currentClientId) {
            $isHelpful = ProductReviewHelpful::isHelpful($review->id, $currentClientId);
        }

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'pros' => $review->pros,
            'cons' => $review->cons,
            'images' => $this->formatImagesUrls($review->images),
            'client_name' => $review->client?->first_name ?? 'عميل',
            'is_verified_purchase' => $review->is_verified_purchase,
            'is_featured' => $review->is_featured,
            'helpful_count' => $review->helpful_count,
            'is_helpful_by_me' => $isHelpful,
            'has_reply' => ! empty($review->admin_reply),
            'admin_reply' => $review->admin_reply,
            'reply_date' => $review->replied_at?->toDateTimeString(),
            'created_at' => $review->created_at->toDateTimeString(),
        ];
    }

    /**
     * الحصول على اسم الحالة
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد المراجعة',
            'approved' => 'منشور',
            'rejected' => 'مرفوض',
            default => $status,
        };
    }
}
