<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\QuestionStatus;
use App\Enums\RecipientType;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\Store\ProductQuestion;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * إدارة «أسئلة المنتجات».
 *
 * ما هي: عميل المتجر يطرح سؤالاً من صفحة المنتج في واجهة المتجر
 * (`POST /api/store/questions`)، فيصل هنا «قيد الانتظار»؛ الموظف يجيب،
 * فتظهر الإجابة تحت المنتج لكل الزوّار ما دام السؤال «منشوراً»
 * (`GET /api/store/questions/product/{id}` يعيد المُجاب المنشور فقط).
 *
 * وللموظف أن يسجّل سؤالاً بنفسه — وصله بالهاتف أو الواتساب أو في الفرع —
 * باسم عميل متجر أو باسم زائرٍ بلا حساب (`asker_name`)، ويجيب عليه في
 * الحفظ نفسه؛ بهذا تُبنى «الأسئلة الشائعة» للمنتج قبل أن يسأل أحد.
 */
class ProductQuestionController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.questions.view', ['only' => ['index', 'show', 'productQuestions']]);
        $this->middleware('permission.role:online_store.questions.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.questions.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.questions.answer', ['only' => ['answer', 'updateAnswer', 'bulkAnswer', 'quickAnswer']]);
        $this->middleware('permission.role:online_store.questions.moderate', ['only' => ['reject', 'reopen', 'bulkReject']]);
        $this->middleware('permission.role:online_store.questions.toggle_public', ['only' => ['togglePublic']]);
        $this->middleware('permission.role:online_store.questions.delete', ['only' => ['destroy', 'bulkDelete']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'product_id' => 'المنتج',
        'client_id' => 'عميل المتجر',
        'asker_name' => 'اسم السائل',
        'question' => 'نص السؤال',
        'answer' => 'الإجابة',
        'is_public' => 'النشر',
        'ids' => 'الأسئلة المحددة',
        'ids.*' => 'السؤال المحدد',
    ];

    /**
     * عرض قائمة الأسئلة
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = ProductQuestion::with(['product', 'client', 'answeredBy']);

        // البحث — لا عمود `name` في `clients`: الاسم التجاري أو الاسمان الشخصيان
        if ($request->filled('search') && is_string($request->search)) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('question', 'LIKE', "%{$search}%")
                    ->orWhere('answer', 'LIKE', "%{$search}%")
                    ->orWhere('asker_name', 'LIKE', "%{$search}%")
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

        // فلترة حسب الحالة — قيمة خارج القائمة تُهمَل ولا تُسقط الصفحة
        if (is_string($request->status) && QuestionStatus::tryFrom($request->status)) {
            $query->where('status', $request->status);
        }

        // فلترة حسب المنتج
        if ($request->filled('product_id') && is_numeric($request->product_id)) {
            $query->where('product_id', (int) $request->product_id);
        }

        // فلترة حسب النشر
        if (in_array($request->is_public, ['yes', 'no'], true)) {
            $query->where('is_public', $request->is_public === 'yes');
        }

        // فلترة حسب وجود إجابة
        if ($request->answered === 'yes') {
            $query->whereNotNull('answer')->where('answer', '!=', '');
        } elseif ($request->answered === 'no') {
            $query->where(fn ($q) => $q->whereNull('answer')->orWhere('answer', ''));
        }

        $questions = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => ProductQuestion::count(),
            'pending' => ProductQuestion::pending()->count(),
            'answered' => ProductQuestion::answered()->count(),
            'rejected' => ProductQuestion::rejected()->count(),
            'public' => ProductQuestion::public()->count(),
        ];

        // المنتجات للفلترة
        $products = Product::select('id', 'name')->orderBy('name')->get();

        return view('onlinestore::questions.index', compact('questions', 'stats', 'products'));
    }

    /**
     * نموذج إضافة سؤال نيابةً عن عميل أو زائر
     */
    public function create(Request $request)
    {
        $productQuestion = new ProductQuestion(['is_public' => true]);
        if ($request->filled('product_id') && is_numeric($request->product_id)) {
            $productQuestion->product_id = (int) $request->product_id;
        }

        return view('onlinestore::questions.create', $this->formData($productQuestion));
    }

    /**
     * حفظ سؤال جديد (مع إجابته إن كُتبت)
     */
    public function store(Request $request)
    {
        $validated = $this->validateQuestion($request);

        $question = new ProductQuestion;
        $this->fillQuestion($question, $validated);
        $question->save();

        if ($question->hasAnswer()) {
            $this->emailQuestionAnswer($question, $question->answer);
        }

        return redirect()->route('product_questions.show', $question)->with('success', 'تمت إضافة السؤال بنجاح');
    }

    /**
     * نموذج تعديل سؤال
     */
    public function edit(ProductQuestion $productQuestion)
    {
        return view('onlinestore::questions.edit', $this->formData($productQuestion));
    }

    /**
     * حفظ تعديل السؤال
     */
    public function update(Request $request, ProductQuestion $productQuestion)
    {
        $validated = $this->validateQuestion($request);

        $previousAnswer = (string) $productQuestion->answer;
        $this->fillQuestion($productQuestion, $validated);
        $productQuestion->save();

        // بريد الإجابة مرةً عند أول إجابة أو عند تغيّر نصّها — لا عند كل حفظ
        if ($productQuestion->hasAnswer() && $productQuestion->answer !== $previousAnswer) {
            $this->emailQuestionAnswer($productQuestion, $productQuestion->answer);
        }

        return redirect()->route('product_questions.show', $productQuestion)->with('success', 'تم تعديل السؤال بنجاح');
    }

    /** بيانات نموذجَي الإضافة والتعديل. */
    private function formData(ProductQuestion $productQuestion): array
    {
        $products = Product::select('id', 'name')->orderBy('name')->get();

        // عملاء المتجر فقط — ومعهم عميل السؤال الحالي وإن أزيلت عنه العلامة
        $clients = Client::query()
            ->where(function ($q) use ($productQuestion) {
                $q->where('is_store_client', true);
                if ($productQuestion->client_id) {
                    $q->orWhere('id', $productQuestion->client_id);
                }
            })
            ->orderBy('trade_name')->orderBy('first_name')
            ->get(['id', 'trade_name', 'first_name', 'last_name', 'email', 'phone']);

        return compact('productQuestion', 'products', 'clients');
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'asker_name' => ['nullable', 'string', 'max:150'],
            'question' => ['required', 'string', 'min:5', 'max:1000'],
            'answer' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
        ], [], self::ATTRIBUTES);
    }

    /**
     * يملأ السؤال من المُدخلات. الحالة تتبع الإجابة: كُتبت ⇒ «تمت الإجابة»
     * باسم الموظف الحالي؛ مُسحت ⇒ يعود «قيد الانتظار». السؤال المرفوض يبقى
     * مرفوضاً ما لم تُكتب له إجابة.
     */
    private function fillQuestion(ProductQuestion $question, array $validated): void
    {
        $answer = trim((string) ($validated['answer'] ?? ''));
        $clientId = $validated['client_id'] ?? null;

        // `asker_name` خارج `$fillable` في المودل — forceFill
        $question->forceFill([
            'product_id' => (int) $validated['product_id'],
            'client_id' => $clientId ? (int) $clientId : null,
            // الاسم الحرّ للزائر فقط؛ مع عميلٍ مختار الاسم اسمُ العميل
            'asker_name' => $clientId ? null : (trim((string) ($validated['asker_name'] ?? '')) ?: null),
            'question' => trim($validated['question']),
            'is_public' => (bool) ($validated['is_public'] ?? false),
        ]);

        if ($answer !== '') {
            if ($answer !== (string) $question->answer || ! $question->answered_at) {
                $question->answered_by = auth()->id();
                $question->answered_at = now();
            }
            $question->answer = $answer;
            $question->status = QuestionStatus::ANSWERED;
        } else {
            $question->answer = null;
            $question->answered_by = null;
            $question->answered_at = null;
            if (! $question->exists || $question->status !== QuestionStatus::REJECTED) {
                $question->status = QuestionStatus::PENDING;
            }
        }
    }

    /**
     * عرض تفاصيل سؤال
     */
    public function show(ProductQuestion $productQuestion)
    {
        $productQuestion->load(['product', 'client', 'answeredBy']);

        // الأسئلة الأخرى لنفس المنتج
        $relatedQuestions = ProductQuestion::where('product_id', $productQuestion->product_id)
            ->where('id', '!=', $productQuestion->id)
            ->answered()
            ->public()
            ->latest()
            ->limit(5)
            ->get();

        return view('onlinestore::questions.show', compact('productQuestion', 'relatedQuestions'));
    }

    /**
     * إضافة إجابة للسؤال
     */
    public function answer(Request $request, ProductQuestion $productQuestion)
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:2000',
            'is_public' => 'nullable|boolean',
        ], [], self::ATTRIBUTES);

        $productQuestion->addAnswer($validated['answer'], auth()->id());

        // مربّع «نشر للعامة» في نموذج الإجابة: غيابه = غير منشور. طلبات
        // AJAX التي لا ترسل الحقل أصلاً لا تغيّر حالة النشر.
        if ($request->has('is_public') || ! $request->ajax()) {
            $productQuestion->update(['is_public' => (bool) ($validated['is_public'] ?? false)]);
        }

        $this->emailQuestionAnswer($productQuestion, $validated['answer']);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الإجابة بنجاح',
            ]);
        }

        return redirect()->back()->with('success', 'تم إضافة الإجابة بنجاح');
    }

    /**
     * بريد للعميل عند الإجابة على سؤاله — يفشل بصمت إن لم يوجد بريد أو قالب.
     * رمز القالب `question.answered` (database/seeders/EmailTemplateSeeder).
     */
    private function emailQuestionAnswer(ProductQuestion $question, string $answer): void
    {
        try {
            $question->loadMissing('client', 'product');
            $client = $question->client;

            if (! $client || empty($client->email)) {
                return;
            }

            $store = OnlineStoreSettingsService::getStoreInfo();
            $product = $question->product;

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'question.answered',
                variables: [
                    'customer_name' => $client->display_name ?: 'عميلنا العزيز',
                    'product_name' => $product->name ?? 'منتج',
                    'question' => $question->question,
                    'answer' => $answer,
                    'product_url' => $product ? url('/products/'.($product->sku_online ?? $product->id)) : url('/'),
                    'store_name' => $store['name'] ?? config('app.name'),
                    'store_url' => url('/'),
                    'store_phone' => $store['phone'] ?? '',
                    'support_email' => $store['email'] ?? config('mail.from.address'),
                ],
                recipientType: RecipientType::Customer,
                recipientId: $question->client_id,
            );
        } catch (\Throwable $e) {
            // تعطّل البريد لا يُسقط حفظ الإجابة
            report($e);
        }
    }

    /**
     * تعديل الإجابة
     */
    public function updateAnswer(Request $request, ProductQuestion $productQuestion)
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:2000',
        ], [], self::ATTRIBUTES);

        $changed = trim($validated['answer']) !== trim((string) $productQuestion->answer);

        $productQuestion->addAnswer($validated['answer'], auth()->id());

        if ($changed) {
            $this->emailQuestionAnswer($productQuestion, $validated['answer']);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الإجابة بنجاح',
            ]);
        }

        return redirect()->back()->with('success', 'تم تحديث الإجابة بنجاح');
    }

    /**
     * رفض سؤال
     */
    public function reject(ProductQuestion $productQuestion)
    {
        $productQuestion->reject();

        return response()->json([
            'success' => true,
            'message' => 'تم رفض السؤال',
            'status' => 'rejected',
        ]);
    }

    /**
     * إعادة فتح سؤال
     */
    public function reopen(ProductQuestion $productQuestion)
    {
        $productQuestion->reopen();

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة فتح السؤال للمراجعة',
            'status' => 'pending',
        ]);
    }

    /**
     * تبديل حالة العامة
     */
    public function togglePublic(ProductQuestion $productQuestion)
    {
        $productQuestion->update(['is_public' => ! $productQuestion->is_public]);

        return response()->json([
            'success' => true,
            'message' => $productQuestion->is_public ? 'تم نشر السؤال للعامة' : 'تم إخفاء السؤال',
            'is_public' => $productQuestion->is_public,
        ]);
    }

    /**
     * حذف سؤال
     */
    public function destroy(ProductQuestion $productQuestion)
    {
        $productId = $productQuestion->product_id;
        $productQuestion->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف السؤال بنجاح',
            ]);
        }

        // الحذف من شاشة «أسئلة المنتج» يعيد إليها لا إلى القائمة العامة
        if (request('back') === 'product' && $productId && Product::whereKey($productId)->exists()) {
            return redirect()->route('product_questions.product', $productId)->with('success', 'تم حذف السؤال بنجاح');
        }

        return redirect()->route('product_questions.index')->with('success', 'تم حذف السؤال بنجاح');
    }

    /**
     * الإجابة على عدة أسئلة
     */
    public function bulkAnswer(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_questions,id',
            'answer' => 'required|string|max:2000',
        ], [], self::ATTRIBUTES);

        $questions = ProductQuestion::with(['client', 'product'])->whereIn('id', $validated['ids'])->get();

        foreach ($questions as $question) {
            $question->addAnswer($validated['answer'], auth()->id());

            $this->emailQuestionAnswer($question, $validated['answer']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الإجابة على الأسئلة المحددة',
        ]);
    }

    /**
     * رفض عدة أسئلة
     */
    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_questions,id',
        ], [], self::ATTRIBUTES);

        ProductQuestion::whereIn('id', $validated['ids'])->update(['status' => QuestionStatus::REJECTED]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الأسئلة المحددة',
        ]);
    }

    /**
     * حذف عدة أسئلة
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:product_questions,id',
        ], [], self::ATTRIBUTES);

        ProductQuestion::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الأسئلة المحددة',
        ]);
    }

    /**
     * أسئلة منتج محدد
     */
    public function productQuestions(Product $product)
    {
        $questions = $product->questions()
            ->with(['client', 'answeredBy'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);

        $stats = [
            'total' => $product->questions()->count(),
            'answered' => $product->questions()->answered()->count(),
            'pending' => $product->questions()->pending()->count(),
        ];

        return view('onlinestore::questions.product-questions', compact('product', 'questions', 'stats'));
    }

    /**
     * إجابة سريعة من القائمة
     */
    public function quickAnswer(Request $request, ProductQuestion $productQuestion)
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:2000',
        ], [], self::ATTRIBUTES);

        $changed = trim($validated['answer']) !== trim((string) $productQuestion->answer);

        $productQuestion->addAnswer($validated['answer'], auth()->id());
        $productQuestion->update(['is_public' => true]);

        if ($changed) {
            $this->emailQuestionAnswer($productQuestion, $validated['answer']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الإجابة ونشر السؤال',
            'answer' => $validated['answer'],
            'answered_at' => $productQuestion->answered_at->format('Y-m-d H:i'),
        ]);
    }
}
