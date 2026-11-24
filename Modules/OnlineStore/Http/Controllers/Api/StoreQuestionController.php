<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\QuestionStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\ProductQuestion;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم أسئلة المنتجات للمتجر الإلكتروني
 * Store Product Questions Controller
 *
 * يدير إنشاء وعرض أسئلة المنتجات
 */
class StoreQuestionController extends Controller
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
     * عرض أسئلة منتج
     * List product questions
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

        $query = ProductQuestion::forProduct($productId)
            ->answered()
            ->public()
            // المُجاب فعلاً فقط — سؤالٌ حالته «answered» بلا نصّ إجابة لا يُعرض
            ->whereNotNull('answer')
            ->where('answer', '!=', '')
            ->with('client:id,trade_name,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // قيمة غير رقمية أو صفر/سالبة كانت تُسقط الطلب بخطأ 500
        $perPage = max(1, min((int) $request->get('per_page', 10) ?: 10, 50));
        $questions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'questions' => $questions->map(fn ($question) => $this->formatQuestion($question)),
            'pagination' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    /**
     * إنشاء سؤال جديد
     * Create new question
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'bail|required|integer|exists:products,id',
            'question' => 'required|string|min:10|max:1000',
        ], [
            'product_id.required' => 'المنتج مطلوب',
            'product_id.integer' => 'المنتج غير صحيح',
            'product_id.exists' => 'المنتج غير موجود',
            'question.required' => 'السؤال مطلوب',
            'question.string' => 'السؤال يجب أن يكون نصاً',
            'question.min' => 'السؤال يجب أن يكون 10 أحرف على الأقل',
            'question.max' => 'السؤال يجب ألا يتجاوز 1000 حرف',
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
                'message' => 'يجب تسجيل الدخول لإضافة سؤال',
            ], 401);
        }

        $productId = $request->product_id;

        // التحقق من عدم وجود سؤال مكرر
        $recentQuestion = ProductQuestion::forProduct($productId)
            ->forClient($client->id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($recentQuestion) {
            return response()->json([
                'success' => false,
                'message' => 'يمكنك إرسال سؤال واحد فقط كل 24 ساعة',
            ], 400);
        }

        $question = ProductQuestion::create([
            'product_id' => $productId,
            'client_id' => $client->id,
            'question' => $request->question,
            'status' => QuestionStatus::PENDING,
            'is_public' => true,
        ]);

        OperationLog::record('created', 'product_question', $question, 'طرح العميل سؤالاً عن المنتج **'.$question->product?->name.'**');

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال السؤال بنجاح وسيتم الرد عليه قريباً',
            'question' => $this->formatQuestion($question->fresh('client')),
        ], 201);
    }

    /**
     * حذف سؤال
     * Delete question
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

        $question = ProductQuestion::where('client_id', $client->id)->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'السؤال غير موجود',
            ], 404);
        }

        // لا يمكن حذف سؤال تم الرد عليه
        if ($question->isAnswered()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف سؤال تم الرد عليه',
            ], 400);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال',
        ]);
    }

    /**
     * أسئلتي
     * My questions
     */
    public function myQuestions(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $questions = ProductQuestion::forClient($client->id)
            ->with('product:id,name,images')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id,
                'product' => [
                    'id' => $question->product?->id,
                    'name' => $question->product?->name,
                    'image' => $this->getFirstImageUrl($question->product?->images),
                ],
                'question' => $question->question,
                'answer' => $question->answer,
                'status' => $question->status->value,
                'status_label' => $this->getStatusLabel($question->status),
                'answered_at' => $question->answered_at?->toDateTimeString(),
                'created_at' => $question->created_at->toDateTimeString(),
            ]),
            'pagination' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    /**
     * تنسيق السؤال
     */
    private function formatQuestion(ProductQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question' => $question->question,
            'answer' => $question->answer,
            // عميل المتجر باسمه الأول؛ سؤالٌ سجّله الموظف لزائر باسمه الحرّ
            'client_name' => $this->askerName($question),
            'status' => $question->status->value,
            'answered_at' => $question->answered_at?->toDateTimeString(),
            'created_at' => $question->created_at->toDateTimeString(),
        ];
    }

    /**
     * اسم السائل كما يظهر في المتجر — الاسم الأول فقط حفاظاً على الخصوصية.
     */
    private function askerName(ProductQuestion $question): string
    {
        $client = $question->client;

        if ($client) {
            return trim((string) ($client->first_name ?: $client->trade_name)) ?: 'عميل';
        }

        return trim((string) $question->asker_name) ?: 'زائر';
    }

    /**
     * الحصول على اسم الحالة
     */
    private function getStatusLabel(QuestionStatus $status): string
    {
        return match ($status) {
            QuestionStatus::PENDING => 'قيد الانتظار',
            QuestionStatus::ANSWERED => 'تم الرد',
            QuestionStatus::REJECTED => 'مرفوض',
            default => $status->value,
        };
    }
}
