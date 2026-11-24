<?php

use App\Constants\planFeatures\AccountPlanFeatures;
use App\Constants\planFeatures\StockPlanFeatures;
use App\Http\Controllers\Api\MobileMediaController;
use App\Http\Controllers\Api\PublicApiController;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
// كان `App\Models\Sales\Invoice` — ولا مجلّد `Sales` تحت `app/Models`.
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\Controllers\ProductionMaterialsApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
    بنود قائمة المواد (BOM).

    ── ما كان ───────────────────────────────────────────────────────────

    إغلاقٌ (closure) من خمسين سطراً هنا، **بلا مصادقة إطلاقاً**، يُرجع لكل
    بندٍ `unit_price` المشتقّ من `purchase_price` للمادة الخام: أي **أسعار
    شراء المواد وتركيبةَ المنتج معاً** — أخصُّ ما تملكه منشأةٌ صناعية —
    لأيّ أحدٍ على الشبكة بلا جلسة ولا رمز. و`{id}` يقبل معرّف القائمة أو
    معرّف المنتج والعدُّ من ١، فالجرد الكامل لوصفات المنشأة حلقةُ `for`.

    وكان يُرجع `$e->getMessage()` نصّاً في الاستجابة — أسماءُ الأعمدة
    والجداول ومسارات الملفات لمن يستكشف من الخارج.

    ── ولماذا لم يُلاحَظ ────────────────────────────────────────────────

    لأن الشيفرة نفسها موجودة في **ثلاثة مواضع**: هنا إغلاقاً، وفي
    `Modules/Stock/routes/api.php` مساراً إلى المتحكّم، وفي
    `ProductionMaterialsApiController` نفسِه. وهذا الملفّ يُحمَّل أوّلاً
    (`bootstrap/app.php` ← `withRouting(api: …)`)، وأوّلُ مسارٍ مطابق هو
    الذي يعمل في Laravel — فأيُّ حارسٍ يوضَع على النسخة الأخرى لا يصله
    طلبٌ قطّ: قفلٌ مكتوبٌ لا يُنفَّذ.

    ── ما صار ───────────────────────────────────────────────────────────

    نسخةٌ واحدة، هي المتحكّم، بالحرّاس الثلاثة نفسِها التي تحرس شاشة قوائم
    المواد: مصادقةٌ، ثمّ `stock.bom.view`، ثمّ حدُّ الباقة. والاسم يبقى كما
    هو فلا ينكسر أيّ `route('api.production-materials.items')`.
*/
Route::middleware([
    'auth:sanctum',
    'permission.role:stock.bom.view',
    // `stock_bom` لا `stock_manufacturing`: ما يُرجعه هذا المسار بنودُ
    // **قائمة المواد** لا أمرُ تصنيع، فحارسُه ميزةُ الشاشة التي تعرضها
    'plan:'.StockPlanFeatures::BOM,
])->get('production-materials/{id}/items', [ProductionMaterialsApiController::class, 'getItems'])
    ->name('api.production-materials.items');

// الحصول على الأبناء المباشرين للحساب
/*
    دليل الحسابات ميزةُ باقةٍ — فالواجهة التي تقرؤه تُحرَس بها كما تُحرَس
    شاشتُه. وبلا هذا يُقرأ الدليل كاملاً من عنوانٍ واحد مهما كانت الباقة.

    ⚠ وهذه المسارات الثلاثة **بلا مصادقة أصلاً** (`api` وحدها، لا
    `auth:sanctum`) — أي أنها تكشف شجرة الحسابات لأي طالب. حدُّ الباقة
    يضيّقها ولا يُغني عن مصادقةٍ ناقصة.
*/
Route::middleware('plan:'.AccountPlanFeatures::CHART_OF_ACCOUNTS)->group(function () {

    Route::get('/accounts/{code}/direct-children', function ($code) {
        $account = ChartOfAccount::where('code', $code)->first();
        if (! $account) {
            return response()->json([]);
        }

        return response()->json($account->allDescendants);
    });

    // الحصول على بيانات الشجرة بتنسيق JSTree
    Route::get('/accounts/tree', function () {
        $accounts = ChartOfAccount::whereNull('parent_id')->get();

        function formatForJSTree($account)
        {
            $node = [
                'id' => $account->id,
                'text' => $account->code.' - '.$account->name,
                'state' => ['opened' => true],
                'children' => [],
            ];

            foreach ($account->childAccounts as $child) {
                $node['children'][] = formatForJSTree($child);
            }

            return $node;
        }

        $treeData = [];
        foreach ($accounts as $account) {
            $treeData[] = formatForJSTree($account);
        }

        return response()->json($treeData);
    });

    // الحصول على تفاصيل حساب معين
    Route::get('/accounts/{id}/details', function ($id) {
        $account = ChartOfAccount::find($id);
        if (! $account) {
            return response()->json(['error' => 'الحساب غير موجود'], 404);
        }

        return response()->json([
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'balance' => $account->balance ?? 0,
            'parent_id' => $account->parent_id,
        ]);
    });

});

// الحصول على تفاصيل الفاتورة
Route::get('/invoice-details/{code}', function ($code) {
    try {
        $account = ChartOfAccount::where('code', $code)->first();
        if (! $account) {
            return response()->json(['error' => 'الفاتورة غير موجودة']);
        }

        // جلب الفاتورة المرتبطة بهذا الحساب
        $invoice = Invoice::where('account_code', $code)->first();
        if (! $invoice) {
            return response()->json(['error' => 'لا توجد فاتورة مرتبطة بهذا الحساب']);
        }

        // جلب القيود المحاسبية للفاتورة
        $entries = JournalEntry::with(['details', 'employee'])
            ->where('invoice_id', $invoice->id)
            ->get()
            ->map(function ($entry) {
                return [
                    'reference_no' => $entry->reference_number,
                    'date' => $entry->date,
                    'debit' => $entry->details->sum('debit'),
                    'credit' => $entry->details->sum('credit'),
                    'balance_after' => 0, // سيتم حسابه لاحقاً
                    'created_by' => optional($entry->employee)->name ?? 'غير معروف',
                    'branch_name' => 'الفرع الرئيسي', // يمكن تغييره حسب هيكل البيانات لديك
                ];
            });

        // حساب الرصيد التراكمي
        $balance = 0;
        $entries = $entries->map(function ($entry) use (&$balance) {
            $balance += ($entry['debit'] - $entry['credit']);
            $entry['balance_after'] = $balance;

            return $entry;
        });

        return response()->json([
            'invoice' => $invoice,
            'entries' => $entries,
            'account' => $account,
        ]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// خطّا "فروع الموظف"/"عملاء الفرع" لخط السير انتقلا إلى
// Modules/Client/routes/web.php (itinerary.groups-for-employee /
// itinerary.clients-for-group) — كانا هنا تحت مجموعة `api`، فتحصيلهما من
// نفس متصفح نفس الجلسة كان يفشل أحياناً بـ"401 Unauthenticated." تبعاً
// لاسم النطاق المكتوب في شريط العنوان (`EnsureFrontendRequestsAreStateful`
// يقارن Referer بقائمة نطاقاتٍ موثوقة، ومزجُ مجموعتَي `api`+`web` على نفس
// المسار سبب تعارضاً في ترتيب بدء الجلسة). مجموعة `web` العادية — نفس
// التي تعمل بها كل شاشات خط السير الأخرى بلا استثناء — تتفادى المشكلة كلياً.

// Treasury Balance API Route
Route::get('/treasuries/{id}/balance', function ($id) {
    try {
        $treasury = Account::find($id);

        if (! $treasury) {
            return response()->json([
                'success' => false,
                'message' => 'الخزينة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'treasury_id' => $treasury->id,
            'treasury_name' => $treasury->name,
            'balance' => $treasury->balance ?? 0,
        ]);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ في جلب رصيد الخزينة',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('api.treasury.balance');

/*
|--------------------------------------------------------------------------
| الواجهة البرمجية المحمية بمفاتيح الربط
|--------------------------------------------------------------------------
|
| المسارات أعلاه تخدم واجهة النظام نفسها وتعمل بلا مصادقة. هذه المجموعة
| مستقلة عنها: كل نقطة فيها تتطلّب مفتاحاً صالحاً يحمل الصلاحية المذكورة،
| فيصير للمفتاح المُولَّد في شاشة «مفاتيح الربط» أثر حقيقي.
|
| الترويستان المطلوبتان:  X-Api-Key  و  X-Api-Secret
|
*/
Route::prefix('partner')->name('api.partner.')->group(function () {
    Route::middleware('api.key')
        ->get('/ping', [PublicApiController::class, 'ping'])->name('ping');

    Route::middleware('api.key:clients.read')
        ->get('/clients', [PublicApiController::class, 'clients'])->name('clients');

    Route::middleware('api.key:invoices.read')
        ->get('/invoices', [PublicApiController::class, 'invoices'])->name('invoices');

    Route::middleware('api.key:products.read')
        ->get('/products', [PublicApiController::class, 'products'])->name('products');

    Route::middleware('api.key:reports.read')
        ->get('/summary', [PublicApiController::class, 'summary'])->name('summary');
});

/*
    صور تطبيق الجوال — من خادم التطبيق (Node) إلى مجلّدات الويب تحت `public/`.
    مفتاحٌ سرّيٌّ مشترك في الترويسة `X-Media-Token` لا جلسة مستخدم؛ انظر
    `MobileMediaController`.
*/
Route::prefix('mobile-media')->name('mobile-media.')->middleware('throttle:240,1')->group(function () {
    Route::post('/', [MobileMediaController::class, 'store'])->name('store');
    Route::post('delete', [MobileMediaController::class, 'destroy'])->name('destroy');
});
