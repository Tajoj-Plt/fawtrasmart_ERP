<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\RecipientType;
use App\Enums\ReviewStatus;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\Store\ProductReview;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * Controller لإدارة تقييمات المنتجات
 */
class ProductReviewController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.reviews.view', ['only' => ['index', 'show', 'productReviews']]);
        $this->middleware('permission.role:online_store.reviews.moderate', ['only' => ['approve', 'reject', 'bulkApprove', 'bulkReject']]);
        $this->middleware('permission.role:online_store.reviews.reply', ['only' => ['reply', 'removeReply']]);
        $this->middleware('permission.role:online_store.reviews.toggle_featured', ['only' => ['toggleFeatured']]);
        $this->middleware('permission.role:online_store.reviews.delete', ['only' => ['destroy', 'bulkDelete']]);
    }

    /**
     * عرض قائمة التقييمات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = ProductReview::with(['product', 'client']);

        // البحث
        // لا عمود `name` في `clients` — البحث بـ`name` كان يُسقط الصفحة بخطأ 500
        // عند أي بحث. الاسم التجاري أو الاسمان الشخصيان.
        if ($request->filled('search') && is_string($request->search)) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('comment', 'LIKE', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('trade_name', 'LIKE', "%{$search}%")
                            ->orWhere('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        // فلترة حسب الحالة — قيمة خارج القائمة تُهمَل
        if (is_string($request->status) && ReviewStatus::tryFrom($request->status)) {
            $query->where('status', $request->status);
        }

        // فلترة حسب التقييم
        if (is_scalar($request->rating) && in_array((int) $request->rating, [1, 2, 3, 4, 5], true)) {
            $query->where('rating', (int) $request->rating);
        }

        // فلترة حسب المنتج
        if ($request->filled('product_id') && is_numeric($request->product_id)) {
            $query->where('product_id', (int) $request->product_id);
        }

        // فلترة حسب المشتري المؤكد
        if (in_array($request->verified, ['yes', 'no'], true)) {
            $query->where('is_verified_purchase', $request->verified === 'yes');
        }

        // فلترة حسب التقييمات المميزة
        if (in_array($request->featured, ['yes', 'no'], true)) {
            $query->where('is_featured', $request->featured === 'yes');
        }

        $reviews = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => ProductReview::count(),
            'pending' => ProductReview::pending()->count(),
            'approved' => ProductReview::approved()->count(),
            'rejected' => ProductReview::rejected()->count(),
            'average_rating' => round(ProductReview::approved()->avg('rating') ?? 0, 1),
            'verified_purchases' => ProductReview::verifiedPurchase()->count(),
        ];

        // توزيع التقييمات
        $ratingDistribution = ProductReview::approved()
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->pluck('count', 'rating')
            ->toArray();

        // المنتجات للفلترة
        $products = Product::select('id', 'name')->orderBy('name')->get();

        return view('onlinestore::reviews.index', compact('reviews', 'stats', 'ratingDistribution', 'products'));
    }

    /**
     * عرض تفاصيل تقييم
     */
    public function show(ProductReview $productReview)
    {
        $productReview->load(['product', 'client', 'invoice', 'repliedBy']);

        return view('onlinestore::reviews.show', compact('productReview'));
    }

    /**
     * الموافقة على تقييم
     */
    public function approve(ProductReview $productReview)
    {
        $productReview->approve();

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد التقييم بنجاح',
            'status' => 'approved',
        ]);
    }

    /**
     * رفض تقييم
     */
    public function reject(ProductReview $productReview)
    {
        $productReview->reject();

        return response()->json([
            'success' => true,
            'message' => 'تم رفض التقييم',
            'status' => 'rejected',
        ]);
    }

    /**
     * إضافة رد من الإدارة
     */
    public function reply(Request $request, ProductReview $productReview)
    {
        $validated = $request->validate([
            'admin_reply' => 'required|string|max:2000',
        ], [], ['admin_reply' => 'نص الرد']);

        $productReview->addAdminReply($validated['admin_reply'], auth()->id());

        // إخطار العميل بالبريد عبر قالب review.replied — نفس نمط بقية الأحداث
        $this->emailReviewReply($productReview, $validated['admin_reply']);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الرد بنجاح',
            ]);
        }

        return redirect()->back()->with('success', 'تم إضافة الرد بنجاح');
    }

    /**
     * بريد للعميل عند ردّ الإدارة على تقييمه — يفشل بصمت إن لم يوجد بريد
     * أو قالب مفعَّل (سلوك EmailTemplate::sendTemplatedEmail نفسه).
     */
    private function emailReviewReply(ProductReview $review, string $reply): void
    {
        try {
            $this->sendReviewReplyEmail($review, $reply);
        } catch (\Throwable $e) {
            // تعطّل البريد لا يُسقط حفظ الرد
            report($e);
        }
    }

    private function sendReviewReplyEmail(ProductReview $review, string $reply): void
    {
        $review->loadMissing('client', 'product');
        $client = $review->client;

        if (! $client || empty($client->email)) {
            return;
        }

        $store = OnlineStoreSettingsService::getStoreInfo();
        $product = $review->product;

        EmailTemplate::sendTemplatedEmail(
            to: $client->email,
            templateCode: 'review.replied',
            variables: [
                'customer_name' => $client->display_name ?: 'عميلنا العزيز',
                'product_name' => $product->name ?? 'منتج',
                'review_title' => $review->title ?? '',
                'admin_reply' => $reply,
                'product_url' => $product ? url('/products/'.($product->sku_online ?? $product->id)) : url('/'),
                'store_name' => $store['name'] ?? config('app.name'),
                'store_url' => url('/'),
                'store_phone' => $store['phone'] ?? '',
                'support_email' => $store['email'] ?? config('mail.from.address'),
            ],
            recipientType: RecipientType::Customer,
            recipientId: $review->client_id,
        );
    }

    /**
     * حذف الرد
     */
    public function removeReply(ProductReview $productReview)
    {
        $productReview->update([
            'admin_reply' => null,
            'replied_at' => null,
            'replied_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الرد بنجاح',
        ]);
    }

    /**
     * تبديل حالة التمييز
     */
    public function toggleFeatured(ProductReview $productReview)
    {
        $productReview->update(['is_featured' => ! $productReview->is_featured]);

        return response()->json([
            'success' => true,
            'message' => $productReview->is_featured ? 'تم تمييز التقييم' : 'تم إلغاء التمييز',
            'is_featured' => $productReview->is_featured,
        ]);
    }

    /**
     * حذف تقييم
     */
    public function destroy(ProductReview $productReview)
    {
        $productReview->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف التقييم بنجاح',
            ]);
        }

        return redirect()->route('product_reviews.index')->with('success', 'تم حذف التقييم بنجاح');
    }

    /**
     * الموافقة على عدة تقييمات
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_reviews,id',
        ], [], ['ids' => 'التقييمات المحددة', 'ids.*' => 'التقييم المحدد']);

        ProductReview::whereIn('id', $validated['ids'])->update(['status' => ReviewStatus::APPROVED]);

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد التقييمات المحددة',
        ]);
    }

    /**
     * رفض عدة تقييمات
     */
    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_reviews,id',
        ], [], ['ids' => 'التقييمات المحددة', 'ids.*' => 'التقييم المحدد']);

        ProductReview::whereIn('id', $validated['ids'])->update(['status' => ReviewStatus::REJECTED]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض التقييمات المحددة',
        ]);
    }

    /**
     * حذف عدة تقييمات
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_reviews,id',
        ], [], ['ids' => 'التقييمات المحددة', 'ids.*' => 'التقييم المحدد']);

        ProductReview::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التقييمات المحددة',
        ]);
    }

    /**
     * تقييمات منتج محدد
     */
    public function productReviews(Product $product)
    {
        $reviews = $product->reviews()
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => $product->reviews()->count(),
            'approved' => $product->approvedReviews()->count(),
            'average' => round($product->averageRating(), 1),
        ];

        return view('onlinestore::reviews.product-reviews', compact('product', 'reviews', 'stats'));
    }
}
