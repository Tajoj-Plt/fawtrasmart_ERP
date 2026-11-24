<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Enums\LinkingType;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Client;
use App\Models\CompiledProducts;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\notifications;
use App\Models\PaymentMethod;
use App\Models\PosGeneralSetting;
use App\Models\PosSession;
use App\Models\PosSessionDetail;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use App\Models\Receipt;
use App\Models\StoreHouse;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\User;
use App\Support\DocumentPrintPage;
use App\Support\OrganizationInfo;
use App\Support\TaxCalculator;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Pos\Jobs\SendPosTelegramAlert;
use Modules\Pos\Models\PosAuditLog;
use Modules\Pos\Models\PosHeldOrder;
use Modules\Pos\Services\Accounting\PosJournalPoster;
use Modules\Pos\Services\Inventory\PosPermitIssuer;
use Modules\Pos\Services\Payment\PosPaymentRecorder;
use Modules\Pos\Services\PosCustody;
use Modules\Pos\Services\PosProductService;
use Modules\Pos\Services\Sale\VoidSaleService;
use Modules\Pos\Services\Session\PosSessionService;

class SalesStartController extends Controller
{
    use Concerns\CalculatesPosTax;
    use Concerns\GeneratesDocumentNumbers;
    use Concerns\GuardsStockAvailability;
    use Concerns\RendersPosDocumentTemplates;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\ResolvesPosSettings;
    use Concerns\ResolvesProductCost;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ScopesToPosAccess;
    use Concerns\UsesDocumentCurrency;

    protected PosProductService $productService;

    /**
     * بوابةُ الدخول: أيُّ صلاحيةٍ واحدة من صلاحيات نقاط البيع تكفي — ليست
     * صلاحيةً بعينها. نفس مبدأ داشبورد المخزون (`Modules\Stock\...\StockController`).
     *
     * ═════════ ما حُذف من هنا ═════════
     *
     *     protected array $fastEndpoints = [
     *         'getProductsByCategory', 'getProductByBarcode',
     *         'quickSearchProducts', 'searchClients'
     *     ];
     *     // ...
     *     if (in_array($currentAction, $this->fastEndpoints)) {
     *         return $next($request);          // ← تخطٍّ كامل للصلاحيات
     *     }
     *
     * أربعُ نقاط نهاية مستثناةٍ من الفحص «للسرعة». وهي تقرأ كتالوجَ
     * المنتجات وأسعارَه، و`searchClients` تُرجع **أرصدةَ العملاء**. فأيُّ
     * موظفٍ مسجَّلِ الدخول — ولو بلا صلاحيةِ نقاط بيعٍ واحدة — كان يقرأها.
     *
     * والمبرَّرُ لم يكن قائماً: `canAny()` استعلامٌ واحد على صلاحيات الدور،
     * مخزَّنٌ لمدّة الطلب. والسرعةُ الحقيقية في الاستعلامات لا في تخطّي
     * الحراسة.
     */
    public function __construct(PosProductService $productService)
    {
        $this->productService = $productService;

        /*
            ═══════════════ بوّابةُ الوحدة ═══════════════

            الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع، لا لمن يملك صلاحيةً
            بعينها.

            كان الفحصُ بصلاحيةٍ واحدة، وهو يقلب المقصود: كاشيرٌ يملك البيعَ
            وحده يُردّ من باب الشاشة التي هي عمله، ومشرفٌ يملك التقارير
            وحدها كذلك. والحراسةُ الدقيقة موضعُها الدالّة لا الباب.

            والبادئاتُ ثلاثٌ لا واحدة (`pos.` و`pos_` و`points_sale_`):
            إغفالُ إحداها يردّ كلَّ من صلاحياتُه بالنمط القديم.
        */
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });

        /*
            ═══════════════ حراسةُ كل دالّة ═══════════════

            مطابقةٌ حرفياً لما يفرضه `Modules/Pos/routes/web.php`. والتكرارُ
            مقصود: الحارسُ على المسار وحده يسقط متى نُودي المتحكّمُ من مسارٍ
            جديد يُنسى تزويدُه، أو من وحدةٍ أخرى تستدعي الدالّة مباشرةً.
        */
        $this->middleware('permission.role:pos.sell', ['only' => [
            'index',
            'store',
            'search',
            'searchClients',
            'quickSearchProducts',
            'getProductByBarcode',
            'getProductsByCategory',
            'getInvoiceDetails',
            'getSessionStats',
            'getDailyStats',
            'checkSession',
        ]]);
        $this->middleware('permission.role:pos.orders.hold', ['only' => [
            'holdOrder',
            'getHeldInvoices',
            'resumeHeldInvoice',
            'deleteHeldInvoice',
        ]]);
        $this->middleware('permission.role:pos.returns.create', ['only' => [
            'processReturn',
            'getAvailableInvoicesForReturn',
            'getInvoiceDetailsForReturn',
        ]]);
        $this->middleware('permission.role:pos.orders.reprint', ['only' => ['print', 'printReturnInvoice']]);
        $this->middleware('permission.role:pos.orders.void', ['only' => ['voidInvoice']]);
    }

    /**
     * عرض الصفحة الرئيسية لنقطة البيع
     */
    public function index()
    {
        // التحقق من وجود جلسة نشطة للموظف
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        if (! $activeSession) {
            return redirect()->route('pos.sessions.create')
                ->with('warning', 'يجب بدء جلسة عمل قبل استخدام نقطة البيع');
        }

        /*
            ═══════════ الورديةُ مدًى زمنيّ لا بوّابةُ دخول ═══════════

            كان فحصُ نافذة الوردية عند **الفتح** وحده. فمن فتح جلستَه في
            موعدها ثم بقي عليها إلى الفجر يبيع بلا مانع — والقاعدةُ التي
            تمنع فتحَ الجلسة الساعةَ الثالثة فجراً تمنع البيعَ فيها من باب
            أولى.

            والمنعُ هنا بالتحويل لا بالرفض الصامت: الشاشةُ تُغلق ويُقال
            السبب، فيُقفل الكاشيرُ عهدتَه بدل أن يظنّ العطلَ في النظام.
        */
        if ($block = app(PosSessionService::class)->sessionWindowBlock($activeSession)) {
            return redirect()->route('pos.sessions.show', $activeSession->id)
                ->with('error', $block.' — أقفِل الجلسة أو راجع مسؤولك.');
        }

        try {
            // جلب إعدادات نقاط البيع (بدون كاش)
            $posSettings = $this->productService->getPosSettings();

            // جلب التصنيفات باستخدام الـ Service
            $categories = $this->productService->getCategories($posSettings);

            // جلب المنتجات باستخدام الـ Service (أول 50 منتج فقط للتحميل السريع)
            $products = $this->productService->getAvailableProducts(null, null, 50);

            // جلب العميل الافتراضي
            $defaultClient = $this->productService->getDefaultClient($posSettings);
            $defaultCustomerId = $posSettings->default_customer_id ?? null;

            // جلب طرق الدفع المتاحة (بدون كاش)
            $paymentMethods = $this->productService->getPaymentMethods($posSettings);

            // إذا لم تكن هناك طرق دفع محددة، جلب جميع الطرق النشطة
            if ($paymentMethods->isEmpty()) {
                $paymentMethods = PaymentMethod::where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'name', 'type']);
            }

            // جلب طريقة الدفع الافتراضية
            $defaultPaymentMethod = $this->productService->getDefaultPaymentMethod($posSettings);
            $defaultPaymentMethodId = $defaultPaymentMethod->id ?? null;

            // جلب قائمة الضرائب من جدول الضرائب
            $taxSettings = TaxSitting::orderBy('tax')->get(['id', 'name', 'tax', 'type']);
            $defaultTaxId = $taxSettings->where('tax', '>', 0)->first()?->id ?? null;

            /*
                رمزُ العملة إلى الشاشة.

                كانت «ر.س» مكتوبةً حرفياً في ستٍّ وخمسين موضعاً من شيفرة
                الشاشة — في السلّة والمجاميع ونافذة الدفع والمرتجع وقائمة
                الفواتير. فمنشأةٌ تبيع بغير الريال ترى أرقامَها موسومةً
                بعملةٍ ليست عملتها، والفاتورةُ المحفوظة بعملتها الصحيحة
                تُعرض بغيرها.

                و`currencySymbol()` موجودةٌ في `UsesDocumentCurrency` ولم
                تكن تُنادى من هذه الشاشة إطلاقاً.
            */
            $currencySymbol = $this->currencySymbol();

            /*
                أيجوز صرفُ قيمة المرتجع من الدرج؟ الشرطان معاً: إذنُ الفرع
                وصلاحيةُ المستخدم — نفسُهما اللذان يفحصهما
                `createReturnAccountingEntries()`. يُقرآن هنا مرّةً ليُعرض
                زرُّ «مرتجع مع الدفع» أو يُخفى.
            */
            $canCashRefund = $this->allowsCashRefund($activeSession?->branch_id)
                && hasPermission('pos.returns.cash_refund');

            return view('pos::sales_start.index', compact(
                'products',
                'categories',
                'paymentMethods',
                'activeSession',
                'defaultCustomerId',
                'defaultClient',
                'defaultPaymentMethodId',
                'posSettings',
                'taxSettings',
                'defaultTaxId',
                'currencySymbol',
                'canCashRefund'
            ));

        } catch (\Exception $e) {
            Log::error('خطأ في تحميل صفحة نقطة البيع: '.$e->getMessage());

            return view('pos::sales_start.index', [
                'products' => collect([]),
                'categories' => collect([]),
                'paymentMethods' => collect([]),
                'activeSession' => $activeSession,
                'defaultClient' => null,
                'defaultCustomerId' => null,
                'defaultPaymentMethodId' => null,
                'posSettings' => null,
                'taxSettings' => collect([]),
                'defaultTaxId' => null,
                'currencySymbol' => 'ر.س',
                'canCashRefund' => false,
            ])->with('error', 'حدث خطأ أثناء تحميل البيانات. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * البحث عن العملاء (AJAX)
     */
    public function searchClients(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $limit = $request->get('limit', 10);

            // البحث عن العملاء المرتبطين بحساب فقط
            $clients = Client::with('account:id,client_id,balance')
                ->whereHas('account') // فقط العملاء المرتبطين بحساب
                ->where(function ($q) use ($query) {
                    $q->where('trade_name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->orderBy('trade_name')
                ->limit($limit)
                ->get(['id', 'trade_name', 'phone', 'code']);

            return response()->json([
                'success' => true,
                'clients' => $clients->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'trade_name' => $client->trade_name,
                        'phone' => $client->phone,
                        'code' => $client->code,
                        'balance' => $client->account->balance ?? 0,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن العملاء: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
            ], 500);
        }
    }

    public function print($id)
    {
        $invoice = Invoice::find($id);

        if (! $invoice) {
            return redirect()->back()->with('error', 'الفاتورة غير موجودة');
        }

        /*
            `Client::all()` و`Employee::all()` كانتا تُحمَّلان كاملتين لكل
            طباعةِ إيصال — جدولان بأكملهما لإيصالٍ يحمل عميلاً واحداً.
            والقالبُ لا يستعملهما أصلاً إلا للعميل صاحب الفاتورة.
        */
        $clients = collect();
        $employees = collect();

        // إذا كانت فاتورة مرتجع، توجيه لصفحة طباعة المرتجع
        if ($invoice->type === 'returned') {
            return $this->printReturnInvoice($id);
        }

        // إعادةُ الطباعة فعلٌ يُراجَع — انظر `auditReprint()`
        $this->auditReprint($invoice);

        /*
            ═══════════ القالبُ المصمَّم أوّلاً ═══════════

            في «الإعدادات ← قوالب المستندات» مئتا قالبٍ مصمَّم، لكلِّ نوعٍ
            افتراضيُّه. وكانت هذه الشاشةُ لا تقرأ منها حرفاً: يصمّم
            المستخدمُ إيصالَه ويعيّنه افتراضياً ثم يطبع فيخرج قالبُ النظام.

            وفاتورةُ نقطة البيع **مبسَّطةٌ** في اصطلاح الهيئة — فنوعُها
            `simplified_invoice` لا `sales_invoice`.

            و`?template=` يسمح باختيار قالبٍ بعينه، و`null` يأخذ الافتراضي.
            وتعذُّرُ التصيير يعود بالقالب المدمج بلا أن يمنع الطباعة.
        */
        $rendered = $this->renderPosTemplate(
            self::TEMPLATE_INVOICE,
            $invoice->id,
            request('template')
        );

        if ($rendered !== null) {
            return response(DocumentPrintPage::wrap(
                $rendered,
                'فاتورة '.$invoice->code,
                route('POS.sales_start.index'),
                request()->boolean('auto_print')
            ));
        }

        // $qrCodeSvg = QrCode::encoding('UTF-8')->size(150)->generate($invoice->qrcode);
        $renderer = new ImageRenderer(
            new RendererStyle(150), // تحديد الحجم
            new SvgImageBackEnd, // تحديد نوع الصورة (SVG)
        );

        $writer = new Writer($renderer);

        // التحقق من وجود qrcode قبل إنشاء الـ QR
        // المخزَّن لا يُقرأ: رمز ما قبلته الهيئة يتقدّم عليه — انظر `ZatcaQr`
        $qrCodeSvg = \App\Services\Zatca\ZatcaQr::svg(\App\Services\Zatca\ZatcaQr::forInvoice($invoice));

        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'invoice')->get();
        $account_setting = null;

        if (auth()->check()) {
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        }
        $client = null;
        if (auth()->check()) {
            $client = Client::where('user_id', auth()->user()->id)->first();
        }
        // رقمُ الفاتورة كما صدر عند حفظها — لا رقمٌ يُولَّد عند الطباعة
        $invoice_number = $invoice->code;

        // إنشاء رقم الباركود من رقم الفاتورة
        $barcodeNumber = str_pad($invoice->id, 13, '0', STR_PAD_LEFT); // تنسيق الرقم إلى 13 خانة

        // إنشاء رابط الباركود باستخدام خدمة Barcode Generator
        $barcodeImage = 'https://barcodeapi.org/api/128/'.$barcodeNumber;
        $nextCode = Receipt::max('code') ?? 0;

        // نحاول تكرار البحث حتى نحصل على كود غير مكرر
        while (Receipt::where('code', $nextCode)->exists()) {
            $nextCode++;
        }

        // تغيير اسم المتغير من qrCodeImage إلى barcodeImage
        return view('pos::sales_start.print', compact('invoice_number', 'account_setting', 'nextCode', 'client', 'clients', 'employees', 'invoice', 'barcodeImage', 'TaxsInvoice', 'qrCodeSvg'));
    }

    /**
     * ⚠️ حُذفت `generateInvoiceNumber()`.
     *
     * كانت تبني رقماً من `Invoice::latest()->id + 1` — أي **معرّفَ الصفّ
     * التالي** في الجدول كلِّه، لا رقمَ فاتورةٍ من التسلسل. وتُمرَّر إلى
     * شاشة الطباعة باسم `$invoice_number`، فتعرض رقماً لا يخصّ هذه
     * الفاتورة ولا أيَّ فاتورة.
     *
     * والرقمُ المعتمد يصدر مرّةً واحدة عند الحفظ من
     * `issueInvoiceNumber()` ويُخزَّن في `invoices.code`.
     */
    /**
     * بحث متقدم للمنتجات والعملاء والفواتير
     */
    public function search(Request $request)
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        /*
            ═══════════ شرطُ الجلسة صار إعداداً ═══════════

            كان مكتوباً في الشيفرة بلا شرط: لا بيعَ بلا جلسةٍ نشطة، دائماً.
            و`requiresOpenShift()` كانت **معرَّفةً ولا تُنادى** — مفتاحٌ
            موثَّقٌ في الكتالوج لا أثر له، يُطفئه المستخدم فلا يتغيّر شيء.

            وهو قرارٌ للمنشأة لا حكمٌ عامّ: منشأةٌ بجهازٍ واحدٍ ومالكٍ واحد قد
            لا تريد عهدةً تُفتح وتُقفل كلَّ يوم.

            والافتراضُ يبقى «مطلوبة»: العهدةُ ضمانةُ الصندوق، فمن يريد
            إسقاطها يُسقطها عامداً.
        */
        if (! $activeSession && $this->requiresOpenShift(current_branch_id())) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد جلسة نشطة. يجب بدء جلسة عمل أولاً.',
                'redirect' => route('pos.sessions.create'),
            ], 403);
        }

        // ونافذةُ الوردية تُفحص عند كل بيع لا عند الفتح وحده
        if ($block = app(PosSessionService::class)->sessionWindowBlock($activeSession)) {
            return response()->json([
                'success' => false,
                'message' => $block.' — لا يمكن إتمام البيع خارج وقت الوردية.',
                'redirect' => route('pos.sessions.show', $activeSession->id),
            ], 403);
        }

        try {
            $query = trim($request->input('query', ''));
            $type = $request->input('type', 'all'); // all, products, clients, invoices
            $category = $request->input('category');
            $limit = min((int) $request->input('limit', 20), 50);

            $results = [];

            if (empty($query) && $type !== 'invoices') {
                return response()->json([
                    'success' => true,
                    'products' => [],
                    'clients' => [],
                    'invoices' => [],
                    'message' => 'يرجى إدخال كلمة للبحث',
                ]);
            }

            // البحث في المنتجات
            if ($type === 'all' || $type === 'products') {
                /*
                    `status` في `products` عمودٌ **نصّي** قيمتُه `'active'`
                    — وكان الفحصُ `where('status', 0)`، فلا يوافق صفّاً
                    واحداً أبداً وتعود نتيجةُ البحث فارغةً دائماً. وبقيّةُ
                    الوحدة تفحص `'active'` صحيحاً (`PosProductService`)،
                    فالبحثُ المتقدّم وحده كان مكسوراً.
                */
                $productsQuery = Product::with(['category:id,name'])
                    ->where('status', 'active')
                    ->where('available_pos', 1)
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'LIKE', "%{$query}%")
                            ->orWhere('code', 'LIKE', "%{$query}%")
                            ->orWhere('description', 'LIKE', "%{$query}%");
                    });

                if ($category) {
                    $productsQuery->where('category_id', $category);
                }

                $products = $productsQuery->limit($limit)
                    ->get(['id', 'name', 'sale_price', 'images', 'category_id', 'code'])
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sale_price' => (float) $product->sale_price,
                            'code' => $product->code,
                            'category_id' => $product->category_id,
                            'category_name' => optional($product->category)->name,
                            'images' => $this->getProductImageUrl($product->images),
                            'type' => 'product',
                        ];
                    });

                $results['products'] = $products;
            }

            // البحث في العملاء
            if ($type === 'all' || $type === 'clients') {
                // نفس العلّة في العملاء — ويُقتصر على من له حسابٌ محاسبي
                $clients = Client::whereHas('account')
                    ->where(function ($q) use ($query) {
                        $q->where('trade_name', 'LIKE', "%{$query}%")
                            ->orWhere('phone', 'LIKE', "%{$query}%")
                            ->orWhere('email', 'LIKE', "%{$query}%")
                            ->orWhere('code', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'trade_name', 'phone', 'email'])
                    ->map(function ($client) {
                        return [
                            'id' => $client->id,
                            'trade_name' => $client->trade_name,
                            'phone' => $client->phone,
                            'email' => $client->email,
                            'type' => 'client',
                        ];
                    });

                $results['clients'] = $clients;
            }

            // البحث في فواتير الجلسة الحالية
            if ($type === 'all' || $type === 'invoices') {
                $invoicesQuery = Invoice::with(['client:id,trade_name', 'items'])
                    ->where('type', 'pos')
                    ->where('session_id', $activeSession->id); // فقط فواتير الجلسة الحالية

                if (! empty($query)) {
                    $invoicesQuery->where(function ($q) use ($query) {
                        $q->where('code', 'LIKE', "%{$query}%")
                            ->orWhereHas('client', function ($clientQuery) use ($query) {
                                $clientQuery->where('trade_name', 'LIKE', "%{$query}%");
                            });
                    });
                }

                $invoicesList = $invoicesQuery->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'code', 'client_id', 'invoice_date', 'grand_total', 'payment_status', 'created_at']);

                /*
                    حالةُ المرتجع من `returnStateOf()` — لا محسوبةً هنا.

                    كانت تُحسب في هذا الموضع وفي `getInvoicesForReturn()`
                    بنسختين متطابقتين، **ولا تحمل معرّفَ فاتورة المرتجع**.
                    فزرُّ الطباعة في صفّ فاتورةٍ مرتجعةٍ بالكامل لا يعرف أين
                    ورقةُ المرتجع، فيطبع فاتورةَ بيعٍ لم يعد لها وجودٌ
                    فعليّ — بضاعتُها عادت ومالُها رُدّ.
                */
                $invoices = $invoicesList->map(function ($invoice) {
                    $state = $this->returnStateOf($invoice);

                    return [
                        'id' => $invoice->id,
                        'code' => $invoice->code,
                        'client_id' => $invoice->client_id,
                        'client_name' => optional($invoice->client)->trade_name,
                        'invoice_date' => $invoice->invoice_date,
                        'grand_total' => (float) $invoice->grand_total,
                        'payment_status' => $invoice->payment_status,
                        'created_at' => $invoice->created_at->toDateTimeString(),
                        'type' => 'invoice',
                    ] + $state;
                });

                $results['invoices'] = $invoices;
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'session_id' => $activeSession->id,
                'results_count' => [
                    'products' => isset($results['products']) ? $results['products']->count() : 0,
                    'clients' => isset($results['clients']) ? $results['clients']->count() : 0,
                    'invoices' => isset($results['invoices']) ? $results['invoices']->count() : 0,
                ],
                ...$results,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.',
            ], 500);
        }
    }

    /**
     * تخزين الفاتورة مع ربطها بالجلسة.
     *
     * ═════════ ما أُضيف من حراسة ═════════
     *
     * • **الفترةُ المحاسبية**: كان البيعُ يُرحَّل بلا أيّ فحص — فاتورةٌ
     *   بتاريخ اليوم تُقيَّد في فترةٍ أقفلها المحاسب أمس، فينكسر الإقفال
     *   بعد اعتماده.
     *
     * • **صلاحيةُ الخصم**: `points_sale_add_discount` معرَّفةٌ في الكتالوج
     *   ولم تكن تُفحص في مسار البيع إطلاقاً — كانت جزءاً من قائمة `canAny`
     *   عند الدخول فقط، أي أنّ من يملك أيَّ صلاحية جلسةٍ كان يمنح خصماً.
     */
    public function store(Request $request)
    {
        // التحقق من وجود جلسة نشطة
        $activeSession = PosSession::active()->forUser(auth()->id())->first();
        if (! $activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد جلسة نشطة. يجب بدء جلسة عمل أولاً.',
                'redirect' => route('pos.sessions.create'),
            ], 403);
        }

        // فترةٌ مقفلة تردّ البيع قبل أن يُخصم مخزونٌ أو يُصدر رقمُ مستند
        if ($block = $this->accountingPeriodBlock(now())) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'client_name' => 'nullable|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer|exists:products,id',
            'products.*.product_unit_id' => 'nullable|integer|exists:product_units,id', // معرف الوحدة
            'products.*.name' => 'required|string|max:255',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.total' => 'required|numeric|min:0',
            'discount_type' => 'nullable|string|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|integer|exists:tax_sittings,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'total' => 'required|numeric|min:0',
            'net_total' => 'required|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.method_id' => 'required_with:payments|integer|exists:payment_methods,id',
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            // التحقق من توفر المنتجات والمخزون
            // استخدام unique() للسماح بإضافة نفس المنتج بوحدات مختلفة
            $productIds = collect($validated['products'])->pluck('id');
            $uniqueProductIds = $productIds->unique()->values();

            $availableProducts = Product::whereIn('id', $uniqueProductIds)
                ->where('available_pos', 1) // التحقق من أن المنتج متاح في نقاط البيع
                ->get();

            if ($uniqueProductIds->count() !== $availableProducts->count()) {
                // تحديد المنتجات غير المتوفرة
                $availableProductIds = $availableProducts->pluck('id')->toArray();
                $missingProductIds = $uniqueProductIds->diff($availableProductIds)->values();

                // جلب أسماء المنتجات غير المتوفرة
                $missingProducts = Product::whereIn('id', $missingProductIds)->pluck('name')->toArray();

                if (! empty($missingProducts)) {
                    $productNames = implode('، ', $missingProducts);
                    throw new \Exception("المنتجات التالية غير متوفرة أو تم إلغاؤها: {$productNames}");
                } else {
                    // المنتجات غير موجودة في قاعدة البيانات أصلاً
                    throw new \Exception('بعض المنتجات غير موجودة في النظام. أرقام المنتجات: '.$missingProductIds->implode('، '));
                }
            }

            /*
                ═════════ حراسةُ السعر ═════════

                `points_sale_edit_product_prices` كانت معرَّفةً في الكتالوج
                **ولا تُفحص في سطرٍ واحد**. والسعرُ يصل من الواجهة كما هو
                ويُعتمد بلا مقارنةٍ بسعر الصنف المسجَّل — فأيُّ كاشير يبيع
                بأيّ سعرٍ يكتبه، بلا أثرٍ يُراجَع.

                والمقارنةُ بسعر البيع المسجَّل: ما وافقه يمرّ، وما خالفه
                يحتاج صلاحيةً ويُكتب في سجلّ التدقيق. والفرقُ لأعلى يُكتب
                كذلك — رفعُ السعر على العميل ليس أهونَ من خفضه على المنشأة.
            */
            $this->guardPriceOverrides($validated['products'], $availableProducts, $activeSession);

            // الحصول على المستودع المناسب للموظف
            $storeHouse = $this->requirePosWarehouse();
            if (! $storeHouse) {
                throw new \Exception('لا يوجد أي مستودع في النظام. الرجاء إضافة مستودع واحد على الأقل.');
            }

            /*
                ═══════════ لا يخرج من المخزن ما ليس فيه ═══════════

                فحصٌ واحدٌ للفاتورة كلِّها على صفوفٍ مقفلة، بدل حلقةٍ تقارن
                كلَّ سطرٍ بالرصيد وحدَه. انظر [GuardsStockAvailability]
                لتفصيل الثقوب الأربعة التي كانت فيها — أهمُّها أنّ الصنفَ
                الواحد في سطرين كان يمرّ مرّتين، وأنّ الرفضَ كان يعود
                بـ`return` داخل المعاملة فيتركها مفتوحةً بأقفالها.

                ويُرمى الاستثناءُ ولا يُردّ ردّاً هنا: `catch` في آخر
                الدالّة يتراجع بالمعاملة ثم يردّ الرسالةَ نفسَها.
            */
            $this->assertStockAvailable($validated['products'], $storeHouse->id, $availableProducts);

            /*
                ═══════════ الضريبةُ من سجلّها لا من الطلب ═══════════

                **ما كان**: `tax_rate` يُؤخذ كما أرسلته الشاشة، وإن لم
                يُرسَل يُقرأ `$taxSetting->tax` من الجدول.

                وفي الحالين عطل: العمودُ مخزَّنٌ **كسراً** (٠٫١٥ تعني ١٥٪)،
                ثم تقسمه الشيفرةُ على مئةٍ فيصير ٠٫٠٠١٥. فستّمئةٍ وثلاثون
                ضريبتُها ٠٫٩٥ بدل ٩٤٫٥٠ — وهو ما رآه المستخدم.

                و`type` لم يكن يُقرأ: ضريبةٌ ضُبطت «شاملة» تُحسب مضافةً،
                فتُضاف فوق سعرٍ يحملها ويخرج الإقرارُ بأكثرَ ممّا حُصِّل.

                **وما صار**: سجلُّ الضريبة هو المرجع — نسبتُه وطريقتُه —
                عبر `TaxCalculator` الذي تستعمله السنداتُ والتقارير.
                و`tax_rate` القادمُ من الشاشة **يُهمَل**: ما يُقيَّد لا
                يُملى من المتصفّح.
            */
            $taxId = $validated['tax_id'] ?? null;
            $taxSetting = $this->resolveTax($taxId);
            $taxId = $taxSetting?->id ?? $taxId;

            // مئويةً للحفظ والعرض — والحسابُ يتولّاه `TaxCalculator`
            $taxRate = round(TaxCalculator::fraction($this->taxRateOf($taxSetting)) * 100, 4);

            // ===============================================
            // حساب المجاميع من السيرفر (لا نعتمد على القيم المرسلة من الواجهة)
            // ===============================================

            /*
                الوعاءُ والضريبةُ من `taxTotals` — جمعاً على مستوى السطر.

                وهي تحسم «مضافة» من «شاملة»: في الأولى الوعاءُ هو السعرُ
                المُدخَل والضريبةُ فوقه، وفي الثانية السعرُ يحملها فتُستخرَج
                منه ويعود الوعاءُ أقلّ.

                فـ`$subtotal` هنا **الوعاءُ بعد استخراج الشاملة** لا مجموعَ
                الأسعار كما كان — وهو ما يُحفظ في `subtotal`، ويصحّ به
                `subtotal + tax_total = grand_total` في الحالَين.
            */
            $totals = $this->taxTotals($validated['products'], $taxSetting);

            $subtotal = $totals['base'];
            $taxTotal = $totals['tax'];

            // حساب الخصم
            $discountAmount = $this->calculateDiscount(
                $subtotal + $taxTotal, // الخصم من الإجمالي شامل الضريبة
                $validated['discount_type'] ?? 'amount',
                $validated['discount_value'] ?? 0
            );

            /*
                حراسةُ الخصم.

                `points_sale_add_discount` كانت في قائمة `canAny` عند الدخول
                فقط — أي أنّ من يملك أيَّ صلاحية جلسةٍ كان يمنح خصماً بلا حدّ.
                والحدُّ نفسُه (`max_discount_percent`) لم يكن له عمودٌ أصلاً.
            */
            if ($discountAmount > 0) {
                if (! hasPermission('points_sale_add_discount')) {
                    throw new \RuntimeException('غير مصرح لك بمنح خصم على الفاتورة.');
                }

                $maxPercent = (float) ($this->posSettings()?->max_discount_percent ?? 0);
                $base = $subtotal + $taxTotal;

                if ($maxPercent > 0 && $base > 0) {
                    $appliedPercent = ($discountAmount / $base) * 100;

                    if ($appliedPercent > $maxPercent + 0.01) {
                        // التجاوزُ يحتاج إذناً — ويُكتب في سجل التدقيق حين يُؤذَن
                        if (! hasPermission('pos.discount.override')) {
                            throw new \RuntimeException(sprintf(
                                'الخصم المطلوب (%.2f%%) يتجاوز الحدّ المسموح (%.2f%%). يلزم اعتماد مشرف.',
                                $appliedPercent,
                                $maxPercent
                            ));
                        }

                        PosAuditLog::record(PosAuditLog::ACTION_DISCOUNT_OVERRIDE, [
                            'session_id' => $activeSession?->id,
                            'device_id' => $activeSession?->device_id,
                            'branch_id' => $activeSession?->branch_id,
                            'approved_by' => auth()->id(),
                            'amount' => $discountAmount,
                            'currency' => $this->defaultCurrency(),
                            'before' => ['max_percent' => $maxPercent],
                            'after' => ['applied_percent' => round($appliedPercent, 2)],
                        ]);
                    }
                }
            }

            // الإجمالي النهائي = المجموع الصافي + الضريبة - الخصم (محسوب من السيرفر)
            $grandTotal = max(0, $subtotal + $taxTotal - $discountAmount);

            // تسجيل الفرق إذا وجد (للمراقبة فقط، لكن نعتمد القيمة المحسوبة من السيرفر)
            if (abs($grandTotal - ($validated['net_total'] ?? 0)) > 0.01) {
                Log::info('فرق بين المبلغ المحسوب والمرسل (تم اعتماد المحسوب من السيرفر)', [
                    'calculated_subtotal' => $subtotal,
                    'calculated_grandTotal' => $grandTotal,
                    'received_total' => $validated['total'] ?? 'N/A',
                    'received_net_total' => $validated['net_total'] ?? 'N/A',
                    'taxRate' => $taxRate,
                    'taxTotal' => $taxTotal,
                    'discountAmount' => $discountAmount,
                ]);
            }

            // معالجة المدفوعات
            $totalPaid = 0;
            $cashAmount = 0;
            $cardAmount = 0;

            /*
                إعدادُ **فرع الجلسة** لا أوّلُ صفٍّ في الجدول.

                كان `PosGeneralSetting::first()`، فالبيعُ في الفرع الثاني
                يُحاسَب ببنكِ الفرع الأوّل وبتسويته — وتُقيَّد الدفعةُ
                الإلكترونية في حسابٍ ليس حسابَه.
            */
            $posSettings = $this->posSettings($activeSession->branch_id ?? null);

            if (! empty($validated['payments'])) {
                // التحقق من طرق الدفع الأونلاين والبنك الافتراضي
                foreach ($validated['payments'] as $payment) {
                    $paymentMethod = PaymentMethod::find($payment['method_id']);

                    if ($paymentMethod && $paymentMethod->is_online) {
                        // طريقة دفع أونلاين - يجب التحقق من وجود بنك افتراضي
                        // نفسُ مصدر [PosPaymentRecorder]: المفتاحُ المحاسبي
                        // أوّلاً، والعمودُ القديم احتياطاً
                        $defaultBank = $this->accountingAccountId('pos_default_bank')
                            ?: $posSettings?->default_bank_account_id;

                        if (! $defaultBank) {
                            throw new \Exception("لا يمكن استخدام طريقة الدفع '{$paymentMethod->name}' لأنها طريقة دفع إلكترونية ولم يُحدَّد «البنك الافتراضي» في الحسابات المحاسبية.");
                        }
                    }

                    $totalPaid += $payment['amount'];
                    if ($paymentMethod && $paymentMethod->is_online) {
                        $cardAmount += $payment['amount']; // المدفوعات الأونلاين تُحسب كـ card
                    } else {
                        $cashAmount += $payment['amount']; // المدفوعات غير الأونلاين تُحسب كـ cash
                    }
                }

                // التحقق من أن إجمالي المدفوعات يساوي إجمالي الفاتورة (مع تسامح بسيط للتقريب)
                if ($totalPaid < $grandTotal - 0.01) {
                    $remaining = $grandTotal - $totalPaid;
                    /*
                        الرمزُ من شاشة العملات، والأرقامُ مُنسَّقة.

                        كانت الرسالة تكتب «ر.س» صراحةً وتطبع الفرق خاماً:
                        «المتبقي: 0.60000000000002 ر.س» — رقمُ فاصلةٍ عائمة
                        بكامل ذيله يُعرض على الكاشير أمام العميل.
                    */
                    $symbol = $this->currencySymbol();

                    throw new \Exception(sprintf(
                        'إجمالي المدفوعات (%s %s) أقل من إجمالي الفاتورة (%s %s). المتبقي: %s %s',
                        number_format($totalPaid, 2), $symbol,
                        number_format($grandTotal, 2), $symbol,
                        number_format($remaining, 2), $symbol
                    ));
                }
            } else {
                // في نقطة البيع، إذا لم يتم تحديد طريقة دفع، نعتبر الفاتورة مدفوعة نقداً بالكامل
                $totalPaid = $grandTotal;
                $cashAmount = $grandTotal;
            }

            // تحديد حالة الدفع بناءً على المبلغ المدفوع
            $paymentStatus = 3; // غير مدفوع (افتراضي)
            $isPaid = false;
            $dueValue = $grandTotal;

            // التحقق من حالة الدفع الفعلية
            if ($totalPaid >= $grandTotal - 0.01) {
                // مدفوع بالكامل
                $paymentStatus = 1;
                $isPaid = true;
                $dueValue = 0;
                $totalPaid = $grandTotal; // التأكد من أن المبلغ المدفوع = الإجمالي
            } elseif ($totalPaid > 0) {
                // دفع جزئي
                $paymentStatus = 2;
                $isPaid = false;
                $dueValue = $grandTotal - $totalPaid;
            }

            // إنشاء الفاتورة مع ربطها بالجلسة
            $invoice = $this->createInvoice($validated, $subtotal, $discountAmount, $grandTotal, $paymentStatus, $isPaid, $dueValue, $totalPaid, $activeSession?->id, $taxRate, $activeSession, $taxSetting);

            // إضافة عناصر الفاتورة مع تحديث المخزون
            $this->createInvoiceItemsWithInventoryManagement($invoice->id, $validated['products'], $storeHouse, $invoice, $taxRate, $taxSetting);

            // إنشاء سجلات الضرائب للفاتورة
            $this->createTaxInvoiceRecords($invoice, $validated['products'], $taxRate, $taxId, $taxSetting);

            /*
                الضريبةُ حُسبت مرّةً في `$totals` أعلاه — ولا تُعاد.

                كانت تُحسب هنا ثالثةً بصيغةٍ مكتوبةٍ بيدها، فيختلف الرقمُ
                عن المحفوظ في الفاتورة بفرقِ تقريب، ويُبنى القيدُ على غير
                ما يراه العميلُ في الإيصال.
            */

            /*
                حفظُ المدفوعات **قبل** القيد.

                القيدُ يوجّه كلَّ دفعةٍ إلى حسابها، فيحتاج الدفعاتِ محفوظةً
                أوّلاً. وكانت `processPayments()` تُستدعى **بعد** القيد
                وتكتب في اللوغ فقط بلا حفظِ صفٍّ واحد — فالقيدُ كان يُبنى
                من مصفوفة الطلب مباشرةً بقاعدةٍ ثنائية.
            */
            $savedPayments = collect();

            if (! empty($validated['payments'])) {
                $savedPayments = app(PosPaymentRecorder::class)
                    ->record($invoice, $validated['payments'], $activeSession);
            }

            // القيدُ المحاسبي — يرمي عند الفشل فتتراجع المعاملة كاملةً
            $this->createJournalEntries(
                $invoice, $grandTotal, $discountAmount, $taxTotal, $totalPaid,
                $validated['payments'] ?? [], $posSettings, $activeSession, $savedPayments
            );

            if ($savedPayments->isNotEmpty()) {
                // تطبيق إعداد: تفعيل التسوية التلقائية وتسوية المبيعات
                if ($posSettings && $posSettings->enable_auto_settlement && $posSettings->enable_sales_settlement) {
                    $this->autoSettleInvoice($invoice, $totalPaid, $validated['payments']);
                }

                /*
                    تقسيمُ النقد والبطاقة من الدفعات المحفوظة لا من افتراضِ
                    `method_id == 1`. كان الرقمُ ١ يُفترض نقداً في ثلاثة
                    مواضع — افتراضٌ يصير خطأً بأول إعادة ترتيبٍ لجدول طرق الدفع.
                */
                $split = app(PosPaymentRecorder::class)->splitCashAndCard($savedPayments);

                /*
                    لا جلسةَ ⇒ لا حركةَ جلسة.

                    الحركةُ سطرٌ في عهدةٍ لا وجودَ لها هنا — وقد سُمح بالبيع
                    بلا جلسةٍ عمداً (`requiresOpenShift` مطفأ). والفاتورةُ
                    وقيدُها ومخزونُها تمّت كلُّها؛ الناقصُ ربطُها بعهدةٍ لا
                    أحدَ يحملها.
                */
                if ($activeSession) {
                    $this->addTransactionToSession($activeSession->id, [
                        'type' => 'sale',
                        'reference' => $invoice->code,
                        'amount' => $grandTotal,
                        'payment_method' => $savedPayments->count() > 1
                            ? 'mixed'
                            : ($split['cash'] > 0 ? 'cash' : 'card'),
                        'cash_amount' => $split['cash'],
                        'card_amount' => $split['card'],
                        'description' => "بيع - فاتورة رقم {$invoice->code}",
                        'metadata' => json_encode([
                            'invoice_id' => $invoice->id,
                            'products_count' => count($validated['products']),
                            'client_id' => $validated['client_id'] ?? null,
                        ]),
                    ]);
                }
            }

            // تحديث إحصائيات الجلسة — بلا جلسةٍ لا إحصاء
            if ($activeSession) {
                $this->updateSessionStatistics($activeSession);
            }

            // إرسال إشعارات
            $this->sendInvoiceNotifications($invoice, $validated['products']);

            DB::commit();

            return response()->json([
                'success' => true,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->code,
                'session_id' => $activeSession?->id,
                'payment_status' => $paymentStatus,
                'total_paid' => $totalPaid,
                'due_amount' => $dueValue,
                'change_amount' => max(0, $totalPaid - $grandTotal),

                /*
                    الأرصدةُ بعد الخصم — لتُحدِّث الشاشةُ ما تعرضه.

                    بدونها تبقى `appData.products` على ما حُمّلت به عند فتح
                    الشاشة: الكاشيرُ يبيع ويرى الرقمَ لا يتغيّر، وفحصُ
                    التوفّر قبل الإضافة يقرأ رقماً قديماً.
                */
                'stock' => $this->currentStockOf(
                    collect($validated['products'])->pluck('id')->all(),
                    $storeHouse->id
                ),

                'message' => 'تم إنشاء الفاتورة بنجاح وربطها بالجلسة',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في حفظ الفاتورة: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'session_id' => $activeSession?->id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * مستودعُ نقطة البيع — يُقرأ منه المعروضُ ويُخصم منه المباع.
     *
     * ═══════════════ ما كانت ═══════════════
     *
     * اسمُها «مستودع الموظف»، وكانت تُحلّ من **مخزون المندوب**: موظّفُ
     * الجهاز ثم مستودعُه في `default_warehouses`.
     *
     * وهو منطقُ مندوبٍ يحمل بضاعتَه، لا منطقُ كاشيرٍ يبيع من مخزن الفرع:
     * كاشيرٌ بلا ربطٍ في شاشة الموظفين لا يبيع، وكاشيران بمستودعين يقسمان
     * مخزونَ الفرع بينهما، وحسابُ المخزون في القيد يتغيّر بتغيّر من وقف
     * على الجهاز.
     *
     * ═══════════════ وما صارت ═══════════════
     *
     * تسأل [PosCustody] — التي صارت تقرأ **إعدادَ نقطة البيع**
     * (`pos_general_settings.store_house_id`) بالتوريث: صفُّ الفرع ←
     * الصفُّ العام. وهي المصدرُ الواحد الذي يسأله كذلك مُحمِّلُ الشاشة
     * وحارسُ الدخول، فما يُعرض وما يُخصم وما يُمنع من مشكاةٍ واحدة.
     *
     * و`null` تعني «لا مستودعَ مضبوط»: يمنعها `EnsurePosCustody` عند
     * الباب، فلا تصل إلى هنا إلّا في نداءٍ لا يمرّ بالمسار.
     */
    private function getPosWarehouse()
    {
        $id = PosCustody::storeHouseId();

        return $id ? StoreHouse::find($id) : null;
    }

    /**
     * المستودعُ أو خطأٌ مفهوم — لمسارات البيع والمرتجع.
     *
     * `EnsurePosCustody` يمنع الوصولَ بلا مستودع، لكنّ الاعتمادَ على حارسٍ
     * وحده هشّ: نداءٌ من مهمّةٍ أو من وحدةٍ أخرى لا يمرّ بالمسار. وبلا هذا
     * يسقط `$storeHouse->id` على `null` بخطأٍ لا يقول شيئاً — بعد أن يكون
     * القيدُ قد بدأ.
     */
    private function requirePosWarehouse(): StoreHouse
    {
        $storeHouse = $this->getPosWarehouse();

        if (! $storeHouse) {
            throw new \Exception(
                'لم يُحدَّد مستودعُ نقطة البيع. والبيعُ يُخصم من مستودعٍ بعينه '
                .'وحسابُه هو الطرفُ الدائن في قيد التكلفة، فلا يصحّ تخمينُه. '
                .'يُختار من: إعدادات نقاط البيع ← تبويب «البيع» ← مستودع نقطة البيع.'
            );
        }

        return $storeHouse;
    }

    /**
     * إنشاء عناصر الفاتورة مع إدارة المخزون
     */
    private function createInvoiceItemsWithInventoryManagement($invoiceId, $products, $storeHouse, $invoice, $taxRate = 0, $taxSetting = null)
    {
        $items = [];
        // الطريقةُ من سجلّ الضريبة — «مضافة» أو «شاملة»
        $taxSetting = $taxSetting ?: $this->resolveTax(null);

        foreach ($products as $productData) {
            $product = Product::find($productData['id']);
            if (! $product) {
                throw new \Exception("المنتج غير موجود: {$productData['id']}");
            }

            /*
                تفصيلُ السطر من `taxLine` — لا صيغةٌ مكتوبةٌ بيدها.

                كان السعرُ المُدخل يُفترض **دائماً** وعاءً والضريبةُ فوقه.
                وهو صحيحٌ في «المضافة» وحدها. أمّا «الشاملة» فالسعرُ يحملها،
                فوعاؤه أقلُّ منه وتُستخرَج الضريبةُ لا تُضاف.

                و`taxLine` تحسب الاثنين — للوحدة وللسطر — بطريقة السجلّ.
            */
            $line = $this->taxLine(
                (float) $productData['unit_price'],
                (float) $productData['quantity'],
                $taxSetting
            );

            $priceExcludingTax = $line['unit_base'];
            $taxAmountPerUnit = $line['unit_tax'];
            $priceIncludingTax = $line['unit_total'];

            $totalExcludingTax = $line['base'];
            $totalTaxAmount = $line['tax'];
            $totalIncludingTax = $line['total'];

            // جلب اسم الوحدة إذا كانت موجودة
            $unitName = '';
            $quantityInBaseUnit = $productData['quantity']; // الافتراضي: الكمية المباشرة
            $conversionFactor = 1; // الافتراضي: معامل التحويل = 1

            // التحقق من وجود وحدة للمنتج
            if (! empty($productData['product_unit_id'])) {
                $productUnit = ProductUnit::with('categoryUnit.unit')->find($productData['product_unit_id']);
                if ($productUnit && $productUnit->categoryUnit) {
                    // الحصول على اسم الوحدة
                    if ($productUnit->categoryUnit->unit) {
                        $unitName = $productUnit->categoryUnit->unit->name;
                    }

                    // الحصول على معامل التحويل والتأكد من صحته
                    $conversionFactor = (float) ($productUnit->categoryUnit->conversion_factor ?? 1);
                    if ($conversionFactor <= 0) {
                        $conversionFactor = 1; // قيمة افتراضية آمنة
                    }

                    // الكمية بالوحدة الأساسية = الكمية × معامل التحويل
                    $quantityInBaseUnit = $productData['quantity'] * $conversionFactor;
                }
            }
            // إذا لم يكن هناك product_unit_id، تبقى quantityInBaseUnit = الكمية المباشرة

            // إضافة اسم الوحدة بجانب اسم المنتج إذا وجدت
            $itemName = $product->name;
            $itemDescription = $product->name;
            if (! empty($unitName)) {
                $itemName = $product->name.' ('.$unitName.')';
                $itemDescription = $product->name.' - '.$unitName;
            }

            $item = [
                'invoice_id' => $invoiceId,
                'product_id' => $product->id,
                'product_unit_id' => $productData['product_unit_id'] ?? null, // معرف الوحدة المستخدمة
                'store_house_id' => $storeHouse->id,
                'item' => $itemName, // اسم المنتج مع الوحدة
                'description' => $itemDescription, // الوصف مع الوحدة
                'unit_price' => $priceExcludingTax, // السعر صافي بدون ضريبة
                'quantity' => $productData['quantity'],
                'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                'conversion_factor' => $conversionFactor, // معامل التحويل
                'unit_name' => $unitName, // اسم الوحدة
                'discount' => 0,
                'tax_1' => $totalTaxAmount, // إجمالي الضريبة للعنصر (فوق السعر)
                'tax_2' => 0,
                'total' => $totalIncludingTax, // الإجمالي = الصافي + الضريبة
                'type' => 'product',
                /*
                    التكلفةُ تُجمَّد هنا — بالوحدة الأساسية.

                    بدونها كانت تقاريرُ الربحية تقرأ `purchase_price` الحالي،
                    فتغييرُ سعر الشراء اليوم يُعيد كتابة ربح الشهر الماضي.
                */
                'unit_cost_at_sale' => $this->resolveUnitCost($product, $storeHouse->id),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $itemInvoice = InvoiceItem::create($item);

            // إدارة المخزون حسب نوع المنتج (بالوحدة الأساسية)
            $this->manageProductInventory($product, $quantityInBaseUnit, $storeHouse, $invoice, $itemInvoice);

            $items[] = $item;
        }

        return $items;
    }

    /**
     * تكلفةُ الوحدة الأساسية لصنف — **متوسّطُ التكلفة المرجَّح**.
     *
     * هو نفسُه المستعمَل في قيد تكلفة المبيعات وفي عكسه للمرتجع، والمصدرُ
     * واحد: [ResolvesProductCost]. فالتقريرُ لا يخالف الدفتر، والدفترُ لا
     * يخالف بطاقةَ الصنف في وحدة المخزون.
     *
     * وكان الترتيبُ القديم — سعرُ آخر صفٍّ في المستودع، ثم سعرُ الشراء، ثم
     * متوسّطٌ غيرُ مرجَّح يشمل حركاتِ البيع — مكتوباً ثلاثَ مرّات بثلاث
     * نسخ. انظر تفصيلَ أعطاله في [ResolvesProductCost].
     */
    private function resolveUnitCost($product, $storeHouseId): ?float
    {
        return $this->averageUnitCost($product);
    }

    /**
     * إدارة مخزون المنتج
     *
     * @param  Product  $product  المنتج
     * @param  float  $quantity  الكمية بالوحدة الأساسية
     * @param  StoreHouse  $storeHouse  المستودع
     * @param  Invoice  $invoice  الفاتورة
     * @param  InvoiceItem  $itemInvoice  عنصر الفاتورة
     */
    private function manageProductInventory($product, $quantity, $storeHouse, $invoice, $itemInvoice)
    {
        /*
            ═══════════ ما لا رصيدَ له يخرج أوّلاً ═══════════

            الخدمةُ والرقميُّ لا يدخلان المستودع أصلاً — فلا سطرَ لهما في
            `product_details`، ولا خصمَ، ولا إذنَ مخزني.

            **والفحصُ قبل `firstOrCreate` لا بعدها**: كانت تُنشئ للخدمة
            سطرَ مخزونٍ برصيد صفر ثم يُفحص النوع. و`StockEligibility` —
            الحارسُ العامّ على الموديل — يرفض ذلك السطرَ ويرمي:

                «لا يمكن إدخال «ساعة عمل فنّي» في المخزون: نوعه منتج خدمي»

            فتفشل الفاتورةُ كلُّها، ولا تُباع خدمةٌ من نقطة البيع إطلاقاً.

            ولولا ذلك الحارس لظهرت «ساعةُ عمل» في أرصدة المستودعات وورقة
            الجرد وقيمة المخزون — أرقاماً لا معنى لها.
        */
        if (! ProductType::from_value($product->type)->tracksStock()) {
            return;
        }

        // الحصول على أو إنشاء سجل تفاصيل المنتج في المستودع
        $productDetails = ProductDetails::firstOrCreate(
            [
                'store_house_id' => $storeHouse->id,
                'product_id' => $product->id,
            ],
            ['quantity' => 0]
        );

        if ($product->type == 'products') {
            $this->handleRegularProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice);
        } elseif ($product->type == 'compiled' && $product->compile_type == 'Instant') {
            $this->handleInstantCompiledProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice);
        } elseif ($product->type == 'compiled' && $product->compile_type !== 'Instant') {
            $this->handleCompiledProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice);
        }
    }

    /**
     * معالجة المنتج العادي
     */
    private function handleRegularProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice)
    {
        /*
            الرصيدُ قبل وبعد — **في هذا المستودع** لا في المنشأة كلِّها.

            كان يُقرأ `SUM(quantity)` على `product_details` بلا تقييدٍ
            بالمستودع، فيُكتب في الإذن رصيدٌ إجماليٌّ لا علاقةَ له بالمخزن
            الذي خرجت منه البضاعة: إذنُ صرفٍ من مخزنٍ فيه خمسٌ يقول إنّ
            الرصيد كان ألفاً. وورقةُ الجرد تُقارَن بالإذن فلا تتّفق.
        */
        $stockBefore = (float) $productDetails->quantity;
        $stockAfter = $stockBefore - $quantity;

        // الحارسُ الأخير على حافّة الخصم — انظر [GuardsStockAvailability]
        $this->assertCanDecrement($product, $productDetails, $quantity);

        // تحديث المخزون
        $productDetails->decrement('quantity', $quantity);

        // إذنُ صرفٍ معتمَدٌ مربوطٌ بالفاتورة — انظر [PosPermitIssuer]
        PosPermitIssuer::issueLine(
            PosPermitIssuer::SOURCE_SALE,
            $storeHouse->id,
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $itemInvoice->unit_price,
                'total' => $itemInvoice->total,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ],
            $this->permitContext($invoice),
            $invoice->created_at
        );

        // تحقق من تنبيهات انخفاض الكمية
        $this->checkLowStockAlert($product, $productDetails);
        $this->checkExpiryAlert($product);
    }

    /**
     * معالجة المنتج المجمع الفوري
     */
    private function handleInstantCompiledProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice)
    {
        // رصيدُ هذا المستودع لا مجموعُ المستودعات — انظر [handleRegularProduct]
        $stockBefore = (float) $productDetails->quantity;

        // الحركة الأولى: إضافة الكمية إلى المخزن
        // تحديث المخزون: إضافة الكمية
        $productDetails->increment('quantity', $quantity);

        PosPermitIssuer::issueLine(
            PosPermitIssuer::SOURCE_COMPILE_ADD,
            $storeHouse->id,
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $itemInvoice->unit_price,
                'total' => $itemInvoice->total,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $quantity,
            ],
            $this->permitContext($invoice),
            $invoice->created_at
        );

        // الحركة الثانية: خصم الكمية من المخزن
        // تحديث المخزون: خصم الكمية
        $productDetails->decrement('quantity', $quantity);

        PosPermitIssuer::issueLine(
            PosPermitIssuer::SOURCE_SALE,
            $storeHouse->id,
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $itemInvoice->unit_price,
                'total' => $itemInvoice->total,
                'stock_before' => $stockBefore + $quantity,
                'stock_after' => $stockBefore,
            ],
            $this->permitContext($invoice),
            $invoice->created_at
        );

        // معالجة المنتجات التابعة للمنتج المجمع
        $compiledProducts = CompiledProducts::where('compile_id', $product->id)->get();
        foreach ($compiledProducts as $compiledProduct) {
            $this->handleCompiledSubProduct($compiledProduct, $quantity, $storeHouse, $invoice, $itemInvoice);
        }
    }

    /**
     * معالجة المنتج المجمع العادي
     */
    private function handleCompiledProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice)
    {
        // التحقق من توفر الكميات المطلوبة من المنتجات الفرعية
        $compiledProducts = CompiledProducts::where('compile_id', $product->id)->get();

        foreach ($compiledProducts as $compiledProduct) {
            $requiredQuantity = $compiledProduct->qyt * $quantity;
            $subProductDetails = ProductDetails::where('store_house_id', $storeHouse->id)
                ->where('product_id', $compiledProduct->product_id)
                ->first();

            if (! $subProductDetails || $subProductDetails->quantity < $requiredQuantity) {
                $subProduct = Product::find($compiledProduct->product_id);
                throw new \Exception("الكمية المطلوبة ({$requiredQuantity}) غير متاحة في المخزون للمنتج الفرعي '{$subProduct->name}'");
            }
        }

        // خصم المنتج المجمع من المخزون
        $this->handleRegularProduct($product, $quantity, $productDetails, $storeHouse, $invoice, $itemInvoice);
    }

    /**
     * معالجة المنتج الفرعي في المنتج المجمع
     */
    private function handleCompiledSubProduct($compiledProduct, $parentQuantity, $storeHouse, $invoice, $itemInvoice)
    {
        $requiredQuantity = $compiledProduct->qyt * $parentQuantity;
        $subProduct = Product::find($compiledProduct->product_id);

        if (! $subProduct) {
            return;
        }

        $subProductDetails = ProductDetails::firstOrCreate(
            [
                'store_house_id' => $storeHouse->id,
                'product_id' => $subProduct->id,
            ],
            ['quantity' => 0]
        );

        // رصيدُ هذا المستودع لا مجموعُ المستودعات — انظر [handleRegularProduct]
        $stockBefore = (float) $subProductDetails->quantity;
        $stockAfter = $stockBefore - $requiredQuantity;

        // الحارسُ الأخير — قبل الإذن لا بعده: لا يُوثَّق ما لن يقع
        $this->assertCanDecrement($subProduct, $subProductDetails, $requiredQuantity);

        PosPermitIssuer::issueLine(
            PosPermitIssuer::SOURCE_SALE,
            $storeHouse->id,
            [
                'product_id' => $subProduct->id,
                'quantity' => $requiredQuantity,
                'unit_price' => $itemInvoice->unit_price,
                'total' => $itemInvoice->total,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ],
            $this->permitContext($invoice),
            $invoice->created_at
        );

        // تحديث المخزون للمنتج الفرعي
        $subProductDetails->decrement('quantity', $requiredQuantity);

        // تحقق من التنبيهات
        $this->checkLowStockAlert($subProduct, $subProductDetails);
    }

    /**
     * تحقق من تنبيه انخفاض الكمية
     */
    private function checkLowStockAlert($product, $productDetails)
    {
        if (isset($product->low_stock_alert) && $productDetails->quantity < $product->low_stock_alert) {
            // إنشاء إشعار في النظام
            notifications::create([
                'type' => 'Products',
                'title' => 'تنبيه الكمية',
                'description' => "كمية المنتج '{$product->name}' قاربت على الانتهاء. الكمية المتبقية: {$productDetails->quantity}",
            ]);

            // إرسال إشعار تليجرام
            $this->sendTelegramAlert([
                'type' => 'low_stock',
                'product_name' => $product->name,
                'remaining_quantity' => $productDetails->quantity,
                'alert_level' => $product->low_stock_alert,
            ]);
        }
    }

    /**
     * تحقق من تنبيه انتهاء الصلاحية
     */
    private function checkExpiryAlert($product)
    {
        if (
            $product->track_inventory == 2 &&
            ! empty($product->expiry_date) &&
            ! empty($product->notify_before_days)
        ) {

            $expiryDate = Carbon::parse($product->expiry_date);
            $daysBeforeExpiry = (int) $product->notify_before_days;

            if ($expiryDate->greaterThan(now())) {
                $remainingDays = floor($expiryDate->diffInDays(now()));

                if ($remainingDays <= $daysBeforeExpiry) {
                    notifications::create([
                        'type' => 'Products',
                        'title' => 'تاريخ الانتهاء',
                        'description' => "المنتج '{$product->name}' قارب على الانتهاء في خلال {$remainingDays} يوم.",
                    ]);

                    $this->sendTelegramAlert([
                        'type' => 'expiry',
                        'product_name' => $product->name,
                        'expiry_date' => $expiryDate->format('Y-m-d'),
                        'remaining_days' => $remainingDays,
                    ]);
                }
            }
        }
    }

    /**
     * تنبيهُ تيليجرام — يُدفَع إلى الطابور، ولا يُرسَل من هنا.
     *
     * كان الإرسالُ استدعاءَ شبكةٍ متزامناً بمهلة ستّين ثانية **داخل معاملة
     * البيع المفتوحة**، بتوكن مكتوبٍ صراحةً في الشيفرة. فتباطؤُ تيليجرام
     * كان يُبقي صفوفَ الفاتورة والمخزون مقفولةً والكاشيرُ واقفٌ ينتظر.
     *
     * انظر [SendPosTelegramAlert] لتفصيل ذلك وللتوكن الذي صار من البيئة.
     */
    private function sendTelegramAlert($data)
    {
        try {
            $message = null;

            if ($data['type'] === 'low_stock') {
                $message = "🚨 *تنبيه جديد!* 🚨\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "📌 *العنوان:* 🔔 `تنبيه الكمية`\n";
                $message .= "📦 *المنتج:* `{$data['product_name']}`\n";
                $message .= "⚠️ *الكمية المتبقية:* `{$data['remaining_quantity']}`\n";
                $message .= '📅 *التاريخ:* `'.now()->format('Y-m-d H:i')."`\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            } elseif ($data['type'] === 'expiry') {
                $message = "⚠️ *تنبيه انتهاء صلاحية المنتج* ⚠️\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "📌 *اسم المنتج:* `{$data['product_name']}`\n";
                $message .= "📅 *تاريخ الانتهاء:* `{$data['expiry_date']}`\n";
                $message .= "⏳ *المدة المتبقية:* `{$data['remaining_days']} يوم`\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            }

            if ($message) {
                $this->queueTelegramAlert($message);
            }
        } catch (\Exception $e) {
            Log::warning('فشل في تجهيز تنبيه تليجرام: '.$e->getMessage());
        }
    }

    /**
     * دفعُ التنبيه إلى الطابور **بعد** نجاح المعاملة.
     *
     * `afterCommit()` مقصودة: تنبيهٌ عن فاتورةٍ تراجعت معاملتُها يُخبر عن
     * بيعٍ لم يقع. وكان الإرسالُ يتمّ داخل المعاملة، فيصل التنبيهُ ثم
     * تفشل الفاتورة.
     */
    private function queueTelegramAlert(string $message): void
    {
        try {
            SendPosTelegramAlert::dispatch($message)->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('تعذّر جدولة تنبيه تليجرام: '.$e->getMessage());
        }
    }

    /**
     * إرسال إشعارات الفاتورة
     */
    private function sendInvoiceNotifications($invoice, $products)
    {
        try {
            $user = User::find($invoice->created_by);
            $client = Client::find($invoice->client_id);

            $symbol = $this->currencySymbol($invoice);

            // إنشاء إشعار في النظام
            notifications::create([
                'type' => 'invoice',
                'title' => ($user->name ?? 'مستخدم').' أضاف فاتورة POS',
                'description' => 'فاتورة POS للعميل '.($client->trade_name ?? 'عميل نقدي').' بقيمة '.number_format($invoice->grand_total, 2).' '.$symbol,
            ]);

            /*
                أسماءُ المنتجات من بنود الفاتورة المحفوظة لا من `Product::find()`
                داخل حلقة — كان استعلامٌ لكل صنف في كل فاتورة، والأسماءُ
                محفوظةٌ في البنود أصلاً مع وحداتها.
            */
            $productsList = '';
            foreach ($invoice->items as $item) {
                $productsList .= "▫️ *{$item->item}* — الكمية: {$item->quantity}, السعر: {$item->unit_price}\n";
            }

            $message = "📜 *فاتورة POS جديدة* 📜\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🆔 *رقم الفاتورة:* `{$invoice->code}`\n";
            $message .= '🏢 *العميل:* '.($client->trade_name ?? 'عميل نقدي')."\n";
            $message .= '✍🏻 *أنشئت بواسطة:* '.($user->name ?? 'مستخدم')."\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= '💰 *المجموع:* `'.number_format($invoice->grand_total, 2)."` {$symbol}\n";
            $message .= "📌 *نوع الفاتورة:* `نقطة بيع (POS)`\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📦 *المنتجات:* \n".$productsList;
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= '📅 *التاريخ:* `'.now()->format('Y-m-d H:i')."`\n";

            $this->queueTelegramAlert($message);
        } catch (\Exception $e) {
            Log::warning('فشل في إرسال إشعارات الفاتورة: '.$e->getMessage());
        }
    }

    /**
     * إنشاء الفاتورة مع ربطها بالجلسة.
     *
     * ═════════ ما تغيّر ═════════
     *
     * **الرقم**: كان يُشتقّ من آخر فاتورةٍ بـ`substr(-5)` ثم يُبحث عن أول
     * رقمٍ شاغرٍ في حلقة `while` — بلا قفلٍ ولا معاملة. جهازا كاشيرٍ يبيعان
     * في اللحظة نفسها يقرآن الرقمَ نفسَه ويجتازان الحلقةَ معاً. وصار من
     * `next_document_number('pos-invoice')` تحت قفل.
     *
     * **الإعدادات**: كانت `PosGeneralSetting::find(1)` — تفترض أن معرّف
     * الصفّ ١ حرفياً.
     *
     * **السياق**: `'currency' => 'SAR'` مكتوبةً في الشيفرة، ولا `branch_id`
     * ولا `accounting_period_id` إطلاقاً.
     */
    private function createInvoice($data, $subtotal, $discountAmount, $grandTotal, $paymentStatus, $isPaid, $dueValue, $totalPaid, $sessionId, $taxRate = 0, ?PosSession $session = null, $taxSetting = null)
    {
        $code = $this->issueInvoiceNumber();

        /*
            العميلُ الافتراضي عند الحفظ = العميلُ الافتراضي في الشاشة.

            كان `default_customer_id` وحده، فيسقط إلى `null` حين لا إعداد.
            والشاشةُ تعرض «العميل النقدي» افتراضياً — فلو أرسل الطلبُ بلا
            `client_id` لحُفظت الفاتورةُ بلا عميل رغم ما رآه الكاشير.

            فاتورةٌ بلا `client_id` لا كشفَ حسابٍ لها ولا تقريرَ مبيعاتٍ
            يعدّها ولا نقاطَ ولاء — وهي أكثرُ ما يُباع في نقطة البيع.
        */
        $defaultClient = $this->posSettings()?->default_customer_id ?: Client::CASH_CLIENT_ID;

        /*
            الضريبةُ من `taxTotals` — بطريقة السجلّ لا بصيغةٍ مفترضة.

            الصيغةُ المحذوفة تفترض «مضافة» دائماً وتقسم على مئةٍ قيمةً
            مخزَّنةً كسراً. فتنتج ضريبةً تساوي جزءاً من مئةٍ من الصحيح.
        */
        $taxSetting = $taxSetting ?: $this->resolveTax($data['tax_id'] ?? null);

        $taxTotal = $this->taxTotals($data['products'], $taxSetting)['tax'];
        // جلب employee_id من المستخدم الحالي
        $employeeId = auth()->user()->employee_id ?? null;

        // تحديد طريقة الدفع - تدعم طريقة واحدة (رقم) أو متعددة (JSON)
        $paymentMethodValue = 1; // نقد افتراضياً
        if (! empty($data['payments'])) {
            if (count($data['payments']) === 1) {
                // طريقة دفع واحدة - تخزين كرقم
                $paymentMethodValue = (int) $data['payments'][0]['method_id'];
            } else {
                // طرق دفع متعددة - تخزين كـ JSON
                $paymentMethodsArray = [];
                foreach ($data['payments'] as $payment) {
                    $paymentMethodsArray[] = [
                        'method_id' => (int) $payment['method_id'],
                        'amount' => (float) $payment['amount'],
                    ];
                }
                $paymentMethodValue = json_encode($paymentMethodsArray);
            }
        }

        $invoice = Invoice::create([
            'client_id' => $data['client_id'] ?? $defaultClient,
            'employee_id' => $employeeId, // الموظف المسؤول
            'code' => $code,
            'type' => 'pos',
            'invoice_date' => now(),
            'issue_date' => now(),
            'notes' => 'فاتورة نقطة بيع - الجلسة رقم: '.$sessionId,
            'payment_status' => $paymentStatus, // 1: مدفوع, 2: جزئي, 3: غير مدفوع
            'is_paid' => $isPaid,
            'created_by' => auth()->id(),
            'discount_amount' => $discountAmount,
            'discount_type' => isset($data['discount_type']) && $data['discount_type'] === 'percentage' ? 2 : 1,
            'advance_payment' => $totalPaid, // المبلغ المدفوع مقدماً
            'payment_method' => $paymentMethodValue, // طريقة الدفع (رقم إذا واحدة، JSON إذا متعددة)
            'shipping_cost' => 0,
            'shipping_tax' => 0,
            'tax_type' => 1,
            'subtotal' => $subtotal, // المجموع قبل الضريبة والخصم
            'total' => $subtotal, // المجموع الفرعي
            'tax_total' => $taxTotal, // إجمالي الضريبة
            'grand_total' => $grandTotal, // الإجمالي النهائي شامل الضريبة
            'due_value' => $dueValue, // المبلغ المستحق (من المعاملات الممررة)
            'remaining_amount' => $dueValue, // المبلغ المتبقي
            'session_id' => $sessionId, // ربط الفاتورة بجلسة نقطة البيع
            /*
                العملةُ والفرعُ والفترةُ تُختَم في [Invoice] عبر سماتها،
                والفرعُ يُشتقّ هناك من الجلسة.

                والجهازُ وحده يُمرَّر: لا سبيل للمودل أن يستنتجه — الجلسةُ
                تحمله، والفاتورةُ لا تحمل إلّا رقمَ الجلسة.
            */
            'pos_device_id' => $session?->device_id,
        ]);

        // إنشاء QR Code للفاتورة
        $invoice->qrcode = $this->buildZatcaQr($invoice);
        $invoice->save();

        // ملاحظة: القيود المحاسبية تُنشأ في دالة store() لتجنب التكرار

        return $invoice;
    }

    /**
     * القيدُ المحاسبي للفاتورة.
     *
     * ═════════════════════ ما كان ═════════════════════
     *
     *     try { ... }
     *     catch (\Exception $e) {
     *         Log::error('خطأ في إنشاء القيود المحاسبية: ' . $e->getMessage());
     *     }
     *
     * **الاستثناءُ يُبتلع، والتنفيذُ يمضي إلى `DB::commit()`.** فإذا فشل
     * القيدُ لأي سبب — حسابٌ مفقود، عمودٌ ناقص، قيمةُ `null` — تُحفظ
     * الفاتورةُ ويُخصم المخزون **ولا يُكتب قيد**. الميزانيةُ لا تتوازن، ولا
     * شيءَ في الشاشة يقول ذلك. ويومُ تشغيلٍ كاملٌ قد يمرّ هكذا.
     *
     * وكان في الدالّة عيبان آخران:
     *
     *   • `Account::find(52)` — رقمٌ عارٍ لحساب «الخصم المسموح به». ومنشأةٌ
     *     تُنشئ شجرةَ حساباتها من الصفر لا يحمل حسابُها ذلك الرقم — أو
     *     يحمله حسابٌ آخر تماماً.
     *
     *   • `$account->increment('balance', ...)` منفصلةً عن كتابة السطر —
     *     مصدرانِ للحقيقة يتباعدان كلّما فشل أحدهما.
     *
     * ═════════════════════ ما صار ═════════════════════
     *
     * [PosJournalPoster] يبني القيد ويرفضه إن لم يتوازن، ويحدّث الأرصدة في
     * المسار نفسِه. ولا `catch` هنا: ما يفشل يرمي، فتتراجع المعاملةُ كاملةً
     * — لا فاتورة ولا خصمَ مخزون ولا قيد.
     *
     * @throws \RuntimeException
     */
    private function createJournalEntries($invoice, $grandTotal, $discountAmount, $taxTotal, $totalPaid, $payments = [], $posSettings = null, ?PosSession $session = null, $savedPayments = null)
    {
        $clientAccount = Account::where('client_id', $invoice->client_id)->first();

        if (! $clientAccount) {
            /*
                حسابُ العميل مفقود.

                كان يُسجَّل تحذيراً و`return` — فتُحفظ الفاتورةُ بلا قيد.
                وهو أكثرُ الأسباب وقوعاً: عميلٌ أُنشئ من شاشةٍ لا تربط له
                حساباً. والرميُ هنا يجعل الخطأ يظهر عند أول فاتورة بدل أن
                يتراكم صامتاً.
            */
            throw new \RuntimeException(
                'لا يوجد حساب محاسبي مرتبط بالعميل — لا يمكن ترحيل الفاتورة. أنشئ حساباً للعميل أولاً.'
            );
        }

        $amountBeforeTax = $grandTotal - $taxTotal;

        $poster = PosJournalPoster::make([
            'reference_number' => $invoice->code,
            'date' => $invoice->invoice_date ?? now(),
            'description' => 'فاتورة نقطة بيع رقم '.$invoice->code,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'branch_id' => $session?->branch_id ?? $invoice->pos_branch_id,
            'accounting_period_id' => $invoice->accounting_period_id,
            'reference_type' => ReferenceType::SALES_INVOICE,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // العميل (مدين) — إجمالي الفاتورة
        $poster->debit($clientAccount, $grandTotal, 'فاتورة نقطة بيع رقم '.$invoice->code);

        // المبيعات (دائن) — قبل الضريبة، مضافاً إليها الخصم الذي يُقيَّد مديناً
        $poster->credit($this->salesAccount(), $amountBeforeTax + $discountAmount, 'إيرادات مبيعات نقطة بيع');

        // الخصم المسموح به (مدين)
        if ($discountAmount > 0) {
            $discountAccount = $this->allowedDiscountAccount();

            if (! $discountAccount) {
                throw new \RuntimeException(
                    'لا يمكن ترحيل خصم الفاتورة: حساب «الخصم المسموح به» غير مربوط في شاشة الحسابات المحاسبية.'
                );
            }

            $poster->debit($discountAccount, $discountAmount, 'خصم على فاتورة '.$invoice->code);
        }

        // ضريبة القيمة المضافة (دائن)
        if ($taxTotal > 0) {
            $vatAccount = $this->vatPayableAccount();

            if (! $vatAccount) {
                throw new \RuntimeException(
                    'لا يمكن ترحيل ضريبة الفاتورة: حساب «ضريبة القيمة المضافة المستحقّة» غير مربوط.'
                );
            }

            $poster->credit($vatAccount, $taxTotal, 'ضريبة القيمة المضافة');
        }

        $poster->post();

        // قيدُ الدفع منفصلٌ عن قيد الفاتورة — لأنه قد لا يقع أصلاً (بيعٌ آجل)
        if ($totalPaid > 0) {
            $this->createPaymentJournalEntry($invoice, $clientAccount, $totalPaid, $payments, $posSettings, $session, $savedPayments);
        }

        // قيدُ تكلفة المبيعات
        $this->createCostOfSalesEntry($invoice, $session);

        Log::info('تم إنشاء القيود المحاسبية للفاتورة: '.$invoice->code);
    }

    /**
     * إنشاء سجلات الضرائب للفاتورة
     */
    private function createTaxInvoiceRecords($invoice, $products, $taxRate = 0, $taxId = null, $taxSetting = null)
    {
        try {
            /*
                ═══════════ سجلُّ الضريبة يخرج منه الإقرار ═══════════

                فكلُّ خطأٍ هنا يصير خطأً أمام الهيئة.

                **وثلاثةٌ كانت فيه:**

                ١. `($taxRate / 100)` على قيمةٍ مخزَّنةٍ كسراً — فالمسجَّلُ
                   جزءٌ من مئةٍ من المستحقّ.

                ٢. طريقةُ الضريبة مُهمَلة: «الشاملة» تُسجَّل كأنها مضافة.

                ٣. و`TaxSitting::where('tax', $taxRate)` تبحث عن السجلّ
                   **بالنسبة** حين لا يُرسَل المعرّف. وأربعُ ضرائبَ في
                   الجدول نسبتُها ٠٫١٥، فيُلتقط أوّلُها أيّاً كان — وقد
                   يكون «مضافة» بينما المختارةُ «شاملة».

                والسجلُّ الآن هو نفسُه الذي حُسبت به الفاتورة، يُمرَّر من
                `store()` — فلا يُبحث عنه ثانيةً ولا يُخطَأ.
            */
            $taxSetting = $taxSetting ?: $this->resolveTax($taxId);
            $taxName = $taxSetting?->name ?: 'ضريبة القيمة المضافة';

            $taxInvoicesToInsert = [];

            foreach ($products as $productData) {
                $product = Product::find($productData['id']);
                if (! $product) {
                    continue;
                }

                // الوعاءُ والضريبةُ بطريقة السجلّ — مضافةً كانت أو شاملة
                $line = $this->taxLine(
                    (float) $productData['unit_price'],
                    (float) $productData['quantity'],
                    $taxSetting
                );

                $taxAmount = $line['tax'];

                // إضافة سجل ضريبة القيمة المضافة
                $taxInvoicesToInsert[] = [
                    'name' => $taxName,
                    'invoice_id' => $invoice->id,
                    'type' => 'percentage',
                    'rate' => $taxRate,
                    'value' => $taxAmount,
                    'type_invoice' => 'invoice',
                    'product_id' => $product->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($taxInvoicesToInsert)) {
                TaxInvoice::insert($taxInvoicesToInsert);
                Log::info('تم إنشاء سجلات الضرائب للفاتورة: '.$invoice->code.' بنسبة: '.$taxRate.'%');
            }
        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء سجلات الضرائب: '.$e->getMessage());
        }
    }

    /**
     * إنشاء قيود تكلفة المبيعات (COGS)
     *
     * المنطق:
     * 1. إذا كان المنتج لديه وحدة (product_unit_id):
     *    - يتم حساب الكمية بالوحدة الأساسية = الكمية × معامل التحويل (conversion_factor)
     *    - تكلفة السطر = الكمية بالوحدة الأساسية × سعر التكلفة للوحدة الأساسية
     * 2. إذا كان المنتج لا يحتوي على وحدة:
     *    - تكلفة السطر = الكمية المباشرة × سعر التكلفة
     */
    private function createCostOfSalesEntry($invoice, ?PosSession $session = null)
    {
        try {
            // التحقق من عدم إنشاء قيد تكلفة مبيعات مسبقاً لنفس الفاتورة
            $existingCostEntry = JournalEntry::where('invoice_id', $invoice->id)
                ->where('description', 'like', '%تكلفة مبيعات%')
                ->first();

            if ($existingCostEntry) {
                Log::info('تم تخطي إنشاء قيد تكلفة المبيعات لأنه موجود مسبقاً', ['invoice_id' => $invoice->id]);

                return;
            }

            $costTotal = 0;
            $lineCosts = [];
            $inventoryAccountId = null;

            // جلب بنود الفاتورة مع العلاقات المطلوبة
            $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)
                ->with(['productUnit.categoryUnit'])
                ->get();

            /*
                مستودعُ البند يحسم حسابَ المخزون.

                كان يُقرأ من `store_houses.account_id` وحده — وهو فارغٌ حين
                يكون الربطُ في الاتجاه الآخر (`accounts.storehouse_id`).
                فيسقط إلى العام ويُدائَن الأبُ بدل حساب المستودع.
            */
            $itemStoreHouseId = null;

            foreach ($invoiceItems as $item) {
                $product = Product::find($item->product_id);

                /*
                    الخدمةُ والرقميُّ خارج قيد التكلفة.

                    كانت الحلقةُ تمرّ على كل بندٍ وتحسب تكلفةً من
                    `purchase_price`. فخدمةٌ لها سعرُ شراءٍ مُدخَل — وهو
                    شائعٌ: تكلفةُ الفنّي مثلاً — يُكتب لها قيدٌ يُدائن
                    **حساب المخزون**، ومخزونٌ لم ينقص لأنها لا رصيدَ لها.

                    فينقص المخزونُ الدفتريُّ بلا مقابلٍ في الجرد، والفرقُ
                    لا يُفسَّر إلّا بمراجعة كل قيدٍ على حدة.

                    وتكلفةُ الخدمة — إن أُريد إثباتُها — مصروفٌ لا تخفيضُ
                    مخزون، وموضعُها قيدٌ آخر.
                */
                if (! ProductType::from_value($product?->type)->tracksStock()) {
                    continue;
                }

                $storeHouse = StoreHouse::find($item->store_house_id);

                $itemStoreHouseId ??= $item->store_house_id;

                if ($storeHouse && $storeHouse->account_id) {
                    $inventoryAccountId = $storeHouse->account_id;
                }

                // ===== حساب الكمية بالوحدة الأساسية =====
                $quantityInBaseUnit = $item->quantity; // القيمة الافتراضية هي الكمية المباشرة
                $conversionFactor = 1; // معامل التحويل الافتراضي
                $hasUnit = false;
                $unitName = '';

                // التحقق من وجود وحدة للمنتج في بند الفاتورة
                if ($item->product_unit_id) {
                    // جلب وحدة المنتج مع وحدة الصنف
                    $productUnit = $item->productUnit;

                    // إذا لم يتم تحميلها بالعلاقة، نجلبها يدوياً
                    if (! $productUnit) {
                        $productUnit = ProductUnit::with('categoryUnit')->find($item->product_unit_id);
                    }

                    if ($productUnit && $productUnit->categoryUnit) {
                        $hasUnit = true;
                        $unitName = $productUnit->categoryUnit->name ?? '';

                        // الحصول على معامل التحويل مع التحقق من صحته (مطابق لـ ReturnInvoiceController)
                        $conversionFactor = (float) ($productUnit->categoryUnit->conversion_factor ?? 1);

                        // التأكد من أن معامل التحويل صالح (أكبر من صفر)
                        if ($conversionFactor <= 0) {
                            Log::warning('معامل التحويل غير صالح في تكلفة المبيعات، استخدام 1', [
                                'invoice_id' => $invoice->id,
                                'product_id' => $item->product_id,
                                'product_unit_id' => $item->product_unit_id,
                                'conversion_factor' => $conversionFactor,
                            ]);
                            $conversionFactor = 1;
                        }

                        // الكمية بالوحدة الأساسية = الكمية × معامل التحويل
                        $quantityInBaseUnit = $item->quantity * $conversionFactor;
                    }
                }

                /*
                    ═══════════ التكلفةُ متوسّطٌ مرجَّح، لا سعرُ شراء ═══════════

                    كان الترتيب: `product_details.unit_price` لآخر صفٍّ ←
                    `products.purchase_price` ← `avg('unit_price')` على كل
                    أذون الصنف. وثلاثتُها تعطي رقماً ليس تكلفةَ المخزون:
                    الأولُ سعرُ آخر إدخال، والثاني سعرُ آخر صفقة شراء،
                    والثالثُ متوسّطٌ غيرُ مرجَّح **يجمع أسعارَ البيع** لأنّ
                    أذونَ المبيعات تكتب سعرَ البيع في `unit_price`.

                    وصارت `Product::averageCost()` — المتوسّطُ المرجَّح
                    نفسُه المعروضُ على بطاقة الصنف في وحدة المخزون. انظر
                    [ResolvesProductCost].

                    و`unit_cost_at_sale` المجمَّدُ في البند يتقدّم عليه:
                    قيدُ الفاتورة يُكتب بما جُمّد لحظةَ بيعها.
                */
                $purchaseUnitCost = $this->itemUnitCost($item, $product);

                if ($purchaseUnitCost <= 0) {
                    Log::warning('لا متوسّطَ تكلفةٍ للصنف — قُيّدت بصفر', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $item->product_id,
                    ]);
                }

                // ===== حساب تكلفة السطر =====
                // تكلفة السطر = الكمية بالوحدة الأساسية × متوسّط تكلفة الوحدة الأساسية
                $lineCost = $quantityInBaseUnit * $purchaseUnitCost;
                $costTotal += $lineCost;

                $lineCosts[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $product ? $product->name : 'غير معروف',
                    'has_unit' => $hasUnit,
                    'unit_name' => $unitName,
                    'original_quantity' => $item->quantity, // الكمية الأصلية
                    'conversion_factor' => $conversionFactor, // معامل التحويل
                    'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                    'average_unit_cost' => $purchaseUnitCost, // متوسّط تكلفة الوحدة
                    'line_cost' => $lineCost, // تكلفة السطر
                ];

                Log::debug('حساب تكلفة المبيعات لبند الفاتورة', [
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'product_name' => $product ? $product->name : 'غير معروف',
                    'has_unit' => $hasUnit,
                    'unit_name' => $unitName,
                    'original_quantity' => $item->quantity,
                    'conversion_factor' => $conversionFactor,
                    'quantity_in_base_unit' => $quantityInBaseUnit,
                    'average_unit_cost' => $purchaseUnitCost,
                    'line_cost' => $lineCost,
                ]);
            }

            /*
                حسابُ المخزون: مستودعُ البند ← المستودعُ الرئيسي ← حسابُ
                المخزون المضبوط في الإعدادات.

                كان الحلُّ يقف عند الثانية، فمنشأةٌ بلا مستودعٍ رئيسيٍّ
                مُعلَّم لا يُكتب لها قيدُ تكلفةٍ إطلاقاً — ويُسجَّل تحذيرٌ
                وحده.
            */
            $inventoryAccountId ??= $this->inventoryAccountIdFor($itemStoreHouseId);

            if (! $inventoryAccountId) {
                throw new \RuntimeException(
                    'لا يمكن ترحيل تكلفة المبيعات: لا يوجد حساب مخزون مربوط بالمستودع ولا في الحسابات المحاسبية.'
                );
            }

            if ($costTotal <= 0) {
                /*
                    تكلفةٌ صفرية ليست خطأً دائماً — خدمةٌ تُباع بلا تكلفة
                    مخزون. لكنّها تُكتب لتُراجَع: صنفٌ ماديّ بتكلفة صفر
                    يعني أن سعر شرائه لم يُدخَل.
                */
                Log::info('لا قيدَ تكلفةِ مبيعاتٍ — التكلفة صفر', [
                    'invoice_id' => $invoice->id,
                    'lines' => $lineCosts,
                ]);

                return;
            }

            PosJournalPoster::make([
                'reference_number' => 'COGS-'.$invoice->code,
                'date' => $invoice->invoice_date ?? now(),
                'description' => 'تكلفة مبيعات فاتورة POS رقم '.$invoice->code,
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'branch_id' => $session?->branch_id ?? $invoice->pos_branch_id,
                'accounting_period_id' => $invoice->accounting_period_id,
                'reference_type' => ReferenceType::SALES_INVOICE,
                'linking_type' => LinkingType::SINGLE,
            ])
                ->debit($this->costOfSalesAccount(), $costTotal, 'إثبات تكلفة مبيعات فاتورة POS رقم '.$invoice->code)
                ->credit($inventoryAccountId, $costTotal, 'تخفيض المخزون لفاتورة POS رقم '.$invoice->code)
                ->post();

            Log::info('تم إنشاء قيود تكلفة المبيعات', [
                'invoice_id' => $invoice->id,
                'cost_total' => $costTotal,
                'lines' => $lineCosts,
            ]);
        } catch (\Throwable $e) {
            /*
                لا ابتلاعَ هنا.

                كان `catch` يسجّل الخطأ ويمضي، فتُحفظ فاتورةٌ بلا قيدِ تكلفة:
                المخزونُ ينقص في `product_details` ولا ينقص في دفتر الأستاذ.
                والفرقُ لا يظهر إلّا عند جرد المخزون.
            */
            Log::error('خطأ في إنشاء قيد تكلفة المبيعات: '.$e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);

            throw $e;
        }
    }

    /**
     * قيدُ الدفع — بتوجيهِ كلِّ دفعةٍ إلى حسابها.
     *
     * ═════════ ما كان ═════════
     *
     *     if ($paymentMethod->is_online) { $onlineAmount += $amount; }
     *     else                           { $offlineAmount += $amount; }
     *
     * قاعدةٌ ثنائية: كلُّ إلكترونيٍّ إلى **بنكٍ افتراضيٍّ واحد**، وكلُّ ما
     * سواه إلى الخزينة. فمنشأةٌ تقبل «مدى» و«فيزا» و«آبل باي» عبر ثلاثة
     * مزوّدين بثلاثة حسابات تُرحَّل حصيلتُها كلُّها إلى حسابٍ واحد، ثم
     * تُفكَّك يدوياً عند مطابقة كشوف البنوك.
     *
     * وصار التوجيهُ من `payment_methods.account_id` حين يُربط، والقاعدةُ
     * القديمة تبقى احتياطاً — فلا ينكسر شيءٌ قبل أن يربط المحاسب.
     *
     * @param  Collection|null  $savedPayments  الدفعاتُ المحفوظة
     *
     * @throws \RuntimeException
     */
    private function createPaymentJournalEntry($invoice, $clientAccount, $paidAmount, $payments = [], $posSettings = null, ?PosSession $session = null, $savedPayments = null)
    {
        $employeeId = auth()->user()->employee_id ?? null;

        $poster = PosJournalPoster::make([
            'reference_number' => 'PAY-'.$invoice->code,
            'date' => $invoice->invoice_date ?? now(),
            'description' => 'دفعة على فاتورة نقطة بيع رقم '.$invoice->code,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'branch_id' => $session?->branch_id ?? $invoice->pos_branch_id,
            'accounting_period_id' => $invoice->accounting_period_id,
            'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
            'linking_type' => LinkingType::SINGLE,
        ]);

        /*
            سطرٌ مدينٌ لكل حسابِ وجهة.

            الدفعاتُ المحفوظة تُجمَّع بحسابها في [PosPaymentRecorder]، فيُنشأ
            سطرٌ واحد لكل حساب حتى لو تعدّدت الدفعات إليه.
        */
        if ($savedPayments && $savedPayments->isNotEmpty()) {
            $recorder = app(PosPaymentRecorder::class);

            foreach ($recorder->groupByDestinationAccount($savedPayments, $employeeId) as $accountId => $amount) {
                $poster->debit($accountId, $amount, 'استلام دفعة — فاتورة '.$invoice->code);
            }
        } else {
            // لا دفعاتٍ محفوظة: بيعٌ نقديٌّ ضمنيّ يذهب كلُّه إلى خزينة الكاشير
            $poster->debit(
                $this->treasuryAccountFor($employeeId),
                $paidAmount,
                'استلام دفعة نقدية (خزينة)'
            );
        }

        // العميل (دائن) — إجمالي المدفوع
        $poster->credit($clientAccount, $paidAmount, 'دفعة على فاتورة '.$invoice->code);

        $entry = $poster->post();

        // ربطُ الدفعات بقيدها — لتتبّع الأثر من الدفعة إلى الدفتر
        if ($entry && $savedPayments) {
            foreach ($savedPayments as $payment) {
                $payment->update(['journal_entry_id' => $entry->id]);
            }
        }

        Log::info('تم إنشاء قيد الدفع للفاتورة: '.$invoice->code, ['amount' => $paidAmount]);
    }

    /**
     * التسوية التلقائية للفاتورة
     * يتم استدعاؤها عند تفعيل إعدادات التسوية التلقائية وتسوية المبيعات
     */
    private function autoSettleInvoice($invoice, $totalPaid, $payments)
    {
        try {
            // التحقق من أن الفاتورة مدفوعة بالكامل
            if ($totalPaid >= $invoice->grand_total) {
                // تحديث حالة الفاتورة إلى مسددة
                $invoice->update([
                    'payment_status' => 1, // مدفوعة
                    'is_paid' => true,
                    'paid_amount' => $totalPaid,
                    'due_value' => 0,
                ]);

                // إنشاء سند قبض تلقائي
                $this->createAutoReceipt($invoice, $totalPaid, $payments);

                Log::info('تم تسوية الفاتورة تلقائياً: '.$invoice->code);
            }
        } catch (\Exception $e) {
            Log::error('خطأ في التسوية التلقائية: '.$e->getMessage());
        }
    }

    /**
     * إنشاء سند قبض تلقائي
     */
    private function createAutoReceipt($invoice, $amount, $payments)
    {
        try {
            // الحصول على أعلى كود سند قبض وتحويله لرقم
            $maxCode = Receipt::max('code');
            $nextCode = $maxCode ? (intval($maxCode) + 1) : 1;

            // تحديد طريقة الدفع الرئيسية
            $primaryPaymentMethodId = 1; // نقد افتراضياً
            if (! empty($payments) && isset($payments[0]['method_id'])) {
                $primaryPaymentMethodId = $payments[0]['method_id'];
            }

            Receipt::create([
                'code' => (string) $nextCode,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'amount' => $amount,
                'payment_method_id' => $primaryPaymentMethodId,
                'date' => now(),
                'notes' => 'سند قبض تلقائي - فاتورة POS رقم '.$invoice->code,
                'status' => 1,
                'created_by' => auth()->id(),
            ]);

            Log::info('تم إنشاء سند قبض تلقائي للفاتورة: '.$invoice->code);
        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء سند القبض التلقائي: '.$e->getMessage());
        }
    }

    /**
     * إضافة معاملة لتفاصيل الجلسة
     */
    private function addTransactionToSession($sessionId, $data)
    {
        PosSessionDetail::create([
            'session_id' => $sessionId,
            'transaction_type' => $data['type'],
            'reference_number' => $data['reference'] ?? null,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'cash_amount' => $data['cash_amount'] ?? 0,
            'card_amount' => $data['card_amount'] ?? 0,
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'transaction_time' => now(),
        ]);
    }

    /**
     * تحديث إحصائيات الجلسة
     */
    private function updateSessionStatistics(PosSession $session)
    {
        $stats = $session->details()
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(CASE WHEN transaction_type = "sale" THEN amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN transaction_type = "return" THEN amount ELSE 0 END) as total_returns,
                SUM(cash_amount) as total_cash,
                SUM(card_amount) as total_card
            ')
            ->first();

        $session->update([
            'total_transactions' => $stats->transaction_count ?? 0,
            'total_sales' => $stats->total_sales ?? 0,
            'total_returns' => $stats->total_returns ?? 0,
            'total_cash' => $stats->total_cash ?? 0,
            'total_card' => $stats->total_card ?? 0,
        ]);

        return $session->fresh();
    }

    /**
     * جلب إحصائيات الجلسة الحالية
     */
    public function getSessionStats()
    {
        try {
            $activeSession = PosSession::active()->forUser(auth()->id())->first();

            if (! $activeSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد جلسة نشطة',
                ], 404);
            }

            // تحديث الإحصائيات
            $this->updateSessionStatistics($activeSession);

            // جلب إحصائيات إضافية
            $invoicesCount = Invoice::where('session_id', $activeSession->id)->count();
            $lastInvoice = Invoice::where('session_id', $activeSession->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $stats = [
                'session_id' => $activeSession->id,
                'session_number' => $activeSession->session_number,
                'started_at' => $activeSession->started_at->format('Y-m-d H:i'),
                'opening_balance' => (float) $activeSession->opening_balance,
                'total_sales' => (float) $activeSession->total_sales,
                'total_cash' => (float) $activeSession->total_cash,
                'total_card' => (float) $activeSession->total_card,
                'total_transactions' => $activeSession->total_transactions,
                'invoices_count' => $invoicesCount,
                'expected_balance' => (float) ($activeSession->opening_balance + $activeSession->total_cash),
                'last_invoice_time' => $lastInvoice ? $lastInvoice->created_at->diffForHumans() : null,
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب إحصائيات الجلسة: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
            ], 500);
        }
    }

    /**
     * التحقق من حالة الجلسة
     */
    public function checkSession()
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        return response()->json([
            'has_active_session' => (bool) $activeSession,
            'session' => $activeSession ? [
                'id' => $activeSession->id,
                'session_number' => $activeSession->session_number,
                'started_at' => $activeSession->started_at->format('Y-m-d H:i'),
                'device_name' => $activeSession->device->name ?? 'غير محدد',
            ] : null,
        ]);
    }

    // باقي الدوال من الكلاس الأصلي...
    private function calculateDiscount($subtotal, $discountType, $discountValue)
    {
        if ($discountValue <= 0) {
            return 0;
        }

        if ($discountType === 'percentage') {
            if ($discountValue > 100) {
                throw new \Exception('نسبة الخصم لا يمكن أن تتجاوز 100%');
            }

            return $subtotal * ($discountValue / 100);
        }

        if ($discountValue > $subtotal) {
            throw new \Exception('قيمة الخصم لا يمكن أن تتجاوز المجموع الفرعي');
        }

        return $discountValue;
    }

    /**
     * رمزُ استجابة الفاتورة (ZATCA — المرحلة الأولى).
     *
     * ═════════ ثلاثُ نسخٍ صارت واحدة ═════════
     *
     * كانت في هذا المتحكّم **ثلاثُ دوالّ** تُنتج الرمز، بثلاثة مصادر
     * مختلفة لبيانات البائع:
     *
     *   generateQRCode()        ← config('app.company_name') و config('app.vat_number')
     *                             مع افتراضي `'300000000000003'`
     *   generateReturnQRCode()  ← 'مؤسسة اعمال خاصة للتجارة' / '310213567700003'  ← مكتوبةً صراحةً
     *   generateInvoiceQRCode() ← نفس القيمتين مكتوبتين صراحةً مرّةً أخرى
     *
     * فالرقمُ الضريبي المطبوع على الإيصال كان يخصّ منشأةً بعينها — **على
     * فواتير كلِّ منشأةٍ تُشغّل هذه الشيفرة**. وهو رقمٌ يقرؤه جهازُ الهيئة
     * من الإيصال.
     *
     * والمصدرُ الآن `App\Support\OrganizationInfo` — نفسُه الذي تقرأ منه
     * فواتيرُ المبيعات والمشتريات.
     *
     * @param  bool  $isReturn  المرتجعُ يحمل مبالغَ سالبة في الرمز
     */
    private function buildZatcaQr($invoice, bool $isReturn = false): string
    {
        /* المبالغ موجبةٌ في المرتجع أيضاً: المستند إشعارٌ دائن بنوعه، والهيئة
           لا تقبل مبلغاً سالباً في الرمز. والوقت بلا كسور ثانية — كان
           `toISOString()` يكتب `.000000Z`. */
        return \App\Services\Zatca\ZatcaQr::phaseOne(
            $invoice->created_at,
            (float) $invoice->grand_total,
            (float) ($invoice->tax_total ?? 0),
            OrganizationInfo::name(),
            OrganizationInfo::taxNumber()
        );
    }

    private function getTlv($tag, $value)
    {
        $value = (string) $value;

        return pack('C', $tag).pack('C', strlen($value)).$value;
    }

    /**
     * حالةُ المرتجع لفاتورةٍ واحدة — موضعٌ واحدٌ للقاعدة.
     *
     * كانت تُحسب داخل حلقتين في `searchInvoices()` و`getInvoicesForReturn()`
     * بنسختين متطابقتين، ولا تُحسب في `getInvoiceDetails()` إطلاقاً — فلا
     * تعرف الشاشةُ أنّ الفاتورة أُرجعت بالكامل.
     *
     * @return array{has_return: bool, return_status: ?string, can_return: bool,
     *               total_original_qty: float, total_returned_qty: float}
     */
    /**
     * أصُرِف نقدٌ في آخر مرتجع؟ — تُقرأ بعد `createReturnAccountingEntries()`.
     *
     * حقلٌ لا معامِلٌ مُعاد: الدالّةُ تكتب ثلاثة قيود، والقرارُ يُتّخذ في
     * وسطها بعد فحص الإذن والصلاحية — فيُحمل إلى المستدعي ليُخبر الشاشةَ
     * بما وقع فعلاً، وإلى صفّ الجلسة ليكون أثرُ الدرج صادقاً.
     */
    private bool $refundedCash = false;

    private function returnStateOf(Invoice $invoice): array
    {
        // فاتورةُ المرتجع نفسُها لا تُرجَع
        if ($invoice->type === 'returned') {
            return [
                'has_return' => false,
                'return_status' => null,
                'can_return' => false,
                'total_original_qty' => 0.0,
                'total_returned_qty' => 0.0,
                'return_invoice_id' => null,
                'return_invoice_code' => null,
            ];
        }

        $originalQty = (float) InvoiceItem::where('invoice_id', $invoice->id)->sum('quantity');

        $returnedQty = (float) InvoiceItem::whereIn(
            'invoice_id',
            Invoice::where('type', 'returned')->where('reference_number', $invoice->id)->select('id')
        )->sum('quantity');

        $hasReturn = $returnedQty > 0;

        /*
            ═══════════ ومرتجعُها الأحدث ═══════════

            كانت الحالةُ تقول «أُرجعت» ولا تقول **أين ورقةُ المرتجع**. فزرُّ
            الطباعة في صفّ فاتورةٍ مرتجعةٍ بالكامل يُخرج فاتورةَ بيعٍ لم يعد
            لها وجودٌ فعليّ — بضاعتُها عادت ومالُها رُدّ.

            والمعرّفُ هنا ليُطبَع الصحيح.
        */
        $latestReturn = Invoice::where('type', 'returned')
            ->where('reference_number', $invoice->id)
            ->latest('id')
            ->first(['id', 'code']);

        return [
            'has_return' => $hasReturn,
            'return_status' => $hasReturn ? ($returnedQty >= $originalQty ? 'full' : 'partial') : null,
            'can_return' => $returnedQty < $originalQty,
            'total_original_qty' => $originalQty,
            'total_returned_qty' => $returnedQty,
            'return_invoice_id' => $latestReturn?->id,
            'return_invoice_code' => $latestReturn?->code,
        ];
    }

    // باقي الدوال الموجودة في الكلاس الأصلي...
    public function getInvoiceDetails($id)
    {
        try {
            /*
                ═══════════ فاتورةُ المرتجع تُفتح تفاصيلُها أيضاً ═══════════

                كان الشرطُ `where('type', 'pos')` — فالنقرُ على «عرض» في صفّ
                فاتورة مرتجع يُجيب «الفاتورة غير موجودة». وهي موجودةٌ ومعروضةٌ
                في الصفّ نفسِه الذي نُقر عليه.
            */
            $invoice = Invoice::with(['client:id,trade_name', 'items.product:id,name'])
                ->where('id', $id)
                ->whereIn('type', ['pos', 'returned'])
                ->first();

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'الفاتورة غير موجودة',
                ], 404);
            }

            $returnState = $this->returnStateOf($invoice);

            // حساب نسبة الضريبة من الفاتورة
            $subtotal = floatval($invoice->subtotal);
            $taxTotal = floatval($invoice->tax_total);
            $taxRate = ($subtotal > 0) ? ($taxTotal / $subtotal) : 0.15;

            $invoiceData = [
                'id' => $invoice->id,
                'code' => $invoice->code,
                'client_id' => $invoice->client_id,
                'client_name' => optional($invoice->client)->trade_name,
                'invoice_date' => $invoice->invoice_date,
                'grand_total' => (float) $invoice->grand_total,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'tax_rate' => round($taxRate * 100, 2), // نسبة الضريبة كنسبة مئوية
                'discount_amount' => (float) $invoice->discount_amount,
                'payment_status' => $invoice->payment_status,
                'notes' => $invoice->notes,
                'session_id' => $invoice->session_id,

                /*
                    ما تحتاجه الشاشةُ لتقرّر: أتُعرض زرُّ المرتجع أم يُعطَّل؟

                    كانت لا تُرسَل، فيُعرض الزرُّ دائماً — حتى على فاتورة
                    مرتجع، وحتى على فاتورةٍ أُرجعت بالكامل. فيضغطه المستخدم
                    ويُردّ.
                */
                'type' => $invoice->type,
                'is_return' => $invoice->type === 'returned',
                'reference_number' => $invoice->reference_number,
                'has_return' => $returnState['has_return'],
                'return_status' => $returnState['return_status'],
                'can_return' => $returnState['can_return'],
                'total_original_qty' => $returnState['total_original_qty'],
                'total_returned_qty' => $returnState['total_returned_qty'],
                'return_invoice_id' => $returnState['return_invoice_id'],
                'return_invoice_code' => $returnState['return_invoice_code'],

                'items' => $invoice->items->map(function ($item) use ($taxRate) {
                    // بناء اسم العرض مع الوحدة إن وجدت
                    $displayName = $item->item; // الاسم المحفوظ يحتوي على الوحدة
                    if (empty($displayName)) {
                        $displayName = optional($item->product)->name;
                    }
                    // إذا كان هناك product_unit_id ولم يكن الاسم يحتوي على الوحدة، أضفها
                    if ($item->product_unit_id && $displayName && ! str_contains($displayName, '(')) {
                        $productUnit = ProductUnit::with('categoryUnit.unit')->find($item->product_unit_id);
                        if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                            $displayName = $displayName.' ('.$productUnit->categoryUnit->unit->name.')';
                        }
                    }

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_unit_id' => $item->product_unit_id, // معرف الوحدة
                        'product_name' => $displayName,
                        'item' => $displayName,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total' => (float) $item->total,
                        'discount' => (float) $item->discount,
                        'tax_rate' => round($taxRate * 100, 2), // نسبة الضريبة لكل عنصر
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب تفاصيل الفاتورة: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الفاتورة.',
            ], 500);
        }
    }

    public function getProductsByCategory(Request $request)
    {
        try {
            $categoryId = $request->input('category_id') ? (int) $request->input('category_id') : null;
            $search = $request->input('search');
            $limit = min((int) $request->input('limit', 50), 100);
            $page = max((int) $request->input('page', 1), 1);

            // استعلام مباشر وسريع بدون Service للحصول على أقصى سرعة
            $products = $this->fetchProductsFast($categoryId, $search, $limit, $page);

            return response()->json([
                'success' => true,
                'products' => $products,
                'has_more' => count($products) >= $limit,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب منتجات التصنيف: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المنتجات.',
            ], 500);
        }
    }

    /**
     * جلب المنتجات بأقصى سرعة ممكنة (استعلام مباشر)
     */
    private function fetchProductsFast(?int $categoryId, ?string $search, int $limit, int $page): array
    {
        $offset = ($page - 1) * $limit;

        // الحصول على مستودع الموظف
        $storeHouse = $this->getPosWarehouse();
        $storeHouseId = $storeHouse ? $storeHouse->id : null;

        // بناء الاستعلام بشكل مباشر للحصول على أقصى سرعة مع الكمية
        $query = DB::table('products')
            ->leftJoin('product_details', function ($join) use ($storeHouseId) {
                $join->on('products.id', '=', 'product_details.product_id')
                    ->where('product_details.store_house_id', '=', $storeHouseId);
            })
            ->select(['products.id', 'products.name', 'products.sale_price', 'products.images', 'products.category_id', 'products.barcode', 'products.type', 'products.compile_type', DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')])
            ->where('products.available_pos', 1)
            ->where('products.status', 'active');

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        if ($search) {
            $searchTerm = trim($search);

            // البحث الدقيق بالباركود أولاً
            if (strlen($searchTerm) >= 3) {
                $exactMatch = DB::table('products')
                    ->leftJoin('product_details', function ($join) use ($storeHouseId) {
                        $join->on('products.id', '=', 'product_details.product_id')
                            ->where('product_details.store_house_id', '=', $storeHouseId);
                    })
                    ->select(['products.id', 'products.name', 'products.sale_price', 'products.images', 'products.category_id', 'products.barcode', 'products.type', 'products.compile_type', DB::raw('COALESCE(product_details.quantity, 0) as stock_quantity')])
                    ->where('products.available_pos', 1)
                    ->where('products.status', 'active')
                    ->where('products.barcode', $searchTerm)
                    ->first();

                if ($exactMatch) {
                    return [$this->formatProductFast($exactMatch)];
                }
            }

            $query->where(function ($q) use ($searchTerm) {
                $q->where('products.name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('products.barcode', $searchTerm)
                    ->orWhere('products.id', $searchTerm);
            });
        }

        $products = $query
            ->orderBy('products.name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $products->map(fn ($p) => $this->formatProductFast($p))->toArray();
    }

    /**
     * تنسيق المنتج بسرعة
     */
    private function formatProductFast($product): array
    {
        $imageUrl = $this->getProductImageUrl($product->images);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sale_price' => (float) $product->sale_price,
            'category_id' => $product->category_id,
            'barcode' => $product->barcode,
            'type' => $product->type ?? 'products',

            // رايةُ الرصيد — الشاشةُ لا تستنتجها من اسم النوع
            'tracks_stock' => ProductType::from_value($product->type ?? 'products')->tracksStock(),

            'compile_type' => $product->compile_type,
            'images' => $imageUrl,
            'stock_quantity' => (float) ($product->stock_quantity ?? 0),
        ];
    }

    /**
     * الحصول على رابط صورة المنتج
     */
    private function getProductImageUrl($images): string
    {
        if (empty($images)) {
            return asset('assets/uploads/no_image.jpg');
        }

        // إذا كانت الصور JSON string
        if (is_string($images)) {
            // محاولة فك الـ JSON
            $decoded = json_decode($images, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $images = $decoded[0];
            } else {
                // ليست JSON، استخدمها كما هي
                $images = $images;
            }
        } elseif (is_array($images) && count($images) > 0) {
            $images = $images[0];
        }

        // إذا كان الرابط كاملاً
        if (str_starts_with($images, 'http://') || str_starts_with($images, 'https://')) {
            return $images;
        }

        // إزالة الـ slashes الزائدة من البداية
        $images = ltrim($images, '/\\');

        // إذا كان المسار يبدأ بـ assets
        if (str_starts_with($images, 'assets/')) {
            return asset($images);
        }

        // افتراض أن الصورة في مجلد المنتجات
        return asset('assets/uploads/products/'.$images);
    }

    /**
     * البحث السريع بالباركود
     */
    public function getProductByBarcode(Request $request)
    {
        // التحقق من الجلسة النشطة
        $activeSession = PosSession::active()->forUser(auth()->id())->first();
        if (! $activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد جلسة نشطة',
                'redirect' => route('pos.sessions.create'),
            ], 403);
        }

        try {
            $barcode = $request->input('barcode');

            if (empty($barcode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الباركود مطلوب',
                ], 400);
            }

            $product = $this->productService->getProductByBarcode($barcode);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج غير موجود',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'product' => $product,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث بالباركود: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
            ], 500);
        }
    }

    /**
     * البحث السريع للمنتجات (autocomplete)
     */
    public function quickSearchProducts(Request $request)
    {
        try {
            $query = $request->input('query', '');
            $limit = min((int) $request->input('limit', 10), 20);

            $products = $this->productService->quickSearch($query, $limit);

            return response()->json([
                'success' => true,
                'products' => $products,
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث السريع: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
            ], 500);
        }
    }

    /**
     * السلال المعلَّقة للجلسة الحالية.
     *
     * ═════════════════════ ما كان ═════════════════════
     *
     * ثلاثُ نقاط نهاية كاملة تستعلم عن `invoices.status = 'held'` —
     * **ولا سطرَ واحدٌ في المستودع كلِّه يُنشئ فاتورةً بهذه الحالة**، ولا
     * نقطةَ نهايةٍ للتعليق أصلاً. فالميزةُ ثلاثُ نوافذَ على العدم: يضغطها
     * الكاشير فتُرجع قائمةً فارغةً دائماً.
     *
     * ═════════════════════ ما صار ═════════════════════
     *
     * السلّةُ تُحفظ في `pos_held_orders` لا في `invoices`. والفرقُ جوهريّ:
     * لم يُخصم لها مخزون، ولا قيدَ لها، ولا رقمَ مستندٍ استُهلك. ووضعُها في
     * `invoices` — كما كانت الشيفرة تفترض — يُلوّث كلَّ استعلامٍ على
     * الفواتير، وكلُّ تقريرٍ ينسى استثناءها يحتسبها مبيعاتٍ حقيقية.
     */
    public function getHeldInvoices(Request $request)
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        if (! $activeSession) {
            return $this->noSessionResponse();
        }

        try {
            $orders = PosHeldOrder::with('client:id,trade_name')
                ->forSession($activeSession->id)
                ->forCashier(auth()->id())
                ->live()
                ->latest()
                ->limit((int) config('pos.held_orders.max_per_cashier', 20))
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'label' => $order->label ?: 'سلّة رقم '.$order->id,
                    'client_name' => $order->client->trade_name ?? null,
                    'items_count' => $order->items_count,
                    'estimated_total' => (float) $order->estimated_total,
                    'currency' => $order->currency,
                    'held_at' => $order->created_at?->format('H:i'),
                    'expires_at' => $order->expires_at?->format('Y-m-d H:i'),
                ]);

            return response()->json(['success' => true, 'orders' => $orders, 'invoices' => $orders]);
        } catch (\Throwable $e) {
            Log::error('خطأ في جلب السلال المعلَّقة: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب السلال المعلَّقة'], 500);
        }
    }

    /**
     * تعليقُ السلّة الحالية — نقطةُ النهاية التي لم تكن موجودة.
     *
     * تُحفظ السطورُ كما هي في الشاشة (`payload`)، فتُستأنف كما تُركت:
     * الكمّياتُ والوحداتُ والأسعارُ والعميلُ والخصم.
     */
    public function holdOrder(Request $request)
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        if (! $activeSession) {
            return $this->noSessionResponse();
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:120',
            'client_id' => 'nullable|integer|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
            'discount_type' => 'nullable|string|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|integer|exists:tax_sittings,id',
        ]);

        /*
            حدُّ السلال المفتوحة للكاشير الواحد.

            بلا حدٍّ تتراكم السلالُ المنسيّة حتى تصير القائمةُ غيرَ قابلة
            للقراءة، فيبحث الكاشير عن سلّته بدل أن يجدها.
        */
        $max = (int) config('pos.held_orders.max_per_cashier', 20);

        $open = PosHeldOrder::forSession($activeSession->id)
            ->forCashier(auth()->id())
            ->live()
            ->count();

        if ($open >= $max) {
            return response()->json([
                'success' => false,
                'message' => 'بلغتَ الحدّ الأقصى للسلال المعلَّقة ('.$max.'). استأنف سلّةً أو احذفها أولاً.',
            ], 422);
        }

        try {
            $estimated = collect($validated['items'])->sum(
                fn ($item) => (float) $item['unit_price'] * (float) $item['quantity']
            );

            $ttl = config('pos.held_orders.ttl_hours');

            $order = PosHeldOrder::create([
                'session_id' => $activeSession->id,
                'device_id' => $activeSession->device_id,
                'client_id' => $validated['client_id'] ?? null,
                'label' => $validated['label'] ?? null,
                'payload' => [
                    'items' => $validated['items'],
                    'discount_type' => $validated['discount_type'] ?? null,
                    'discount_value' => $validated['discount_value'] ?? 0,
                    'tax_id' => $validated['tax_id'] ?? null,
                ],
                'estimated_total' => round($estimated, 2),
                'items_count' => count($validated['items']),
                // الصلاحيةُ تنتهي بانتهاء الجلسة ما لم تُضبَط مدّةٌ أطول
                'expires_at' => $ttl ? now()->addHours((int) $ttl) : null,
                // الفرعُ والعملةُ والكاشير تُختَم في [PosHeldOrder]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تعليق السلّة',
                'order_id' => $order->id,
                'label' => $order->label ?: 'سلّة رقم '.$order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في تعليق السلّة: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء تعليق السلّة'], 500);
        }
    }

    /**
     * استئنافُ سلّةٍ معلَّقة — تُعاد سطورُها ثم تُحذف.
     *
     * الحذفُ بعد الاستئناف مقصود: سلّةٌ استُؤنفت وبقيت معلَّقة تُباع مرّتين
     * إن فتحها كاشيرٌ آخر.
     */
    public function resumeHeldInvoice(Request $request)
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        if (! $activeSession) {
            return $this->noSessionResponse();
        }

        $orderId = $request->input('order_id')
            ?? $request->input('invoice_id')
            ?? $request->route('id');

        $order = PosHeldOrder::with('client:id,trade_name,phone,code')
            ->forSession($activeSession->id)
            ->forCashier(auth()->id())
            ->find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'السلّة غير موجودة أو لا يمكن الوصول إليها',
            ], 404);
        }

        if ($order->isExpired()) {
            $order->delete();

            return response()->json(['success' => false, 'message' => 'انتهت صلاحية هذه السلّة.'], 410);
        }

        $payload = $order->payload;
        $client = $order->client;

        $order->delete();

        return response()->json([
            'success' => true,
            'order' => [
                'items' => $payload['items'] ?? [],
                'discount_type' => $payload['discount_type'] ?? null,
                'discount_value' => $payload['discount_value'] ?? 0,
                'tax_id' => $payload['tax_id'] ?? null,
                'client' => $client ? [
                    'id' => $client->id,
                    'trade_name' => $client->trade_name,
                    'phone' => $client->phone,
                    'code' => $client->code,
                ] : null,
            ],
        ]);
    }

    public function deleteHeldInvoice($id)
    {
        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        if (! $activeSession) {
            return $this->noSessionResponse();
        }

        $order = PosHeldOrder::forSession($activeSession->id)
            ->forCashier(auth()->id())
            ->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'السلّة غير موجودة أو لا يمكن حذفها',
            ], 404);
        }

        $order->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف السلّة المعلَّقة']);
    }

    /**
     * إلغاءُ فاتورة بيع.
     *
     * الصلاحيةُ `pos.orders.void` كانت معرَّفةً في الكتالوج **بلا دالّةٍ
     * ولا مسار** — فالوسيلةُ الوحيدة لتصحيح فاتورةٍ خاطئة كانت المرتجع،
     * وهو يُظهر بيعاً ثم مرتجعاً لعمليةٍ لم تقع أصلاً.
     *
     * انظر [VoidSaleService] للفرق بين الإلغاء والمرتجع.
     */
    public function voidInvoice(Request $request, $id)
    {
        if (! hasPermission('pos.orders.void')) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإلغاء الفواتير.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ], [
            'reason.required' => 'سبب الإلغاء مطلوب',
            'reason.min' => 'اكتب سبباً مفهوماً للإلغاء',
        ]);

        $invoice = Invoice::find($id);

        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'الفاتورة غير موجودة'], 404);
        }

        $activeSession = PosSession::active()->forUser(auth()->id())->first();

        try {
            $voided = app(VoidSaleService::class)->void($invoice, $validated['reason'], $activeSession);

            if ($activeSession) {
                app(PosSessionService::class)
                    ->refreshStatistics($activeSession);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الفاتورة '.$voided->code.' وعكس أثرها.',
                'invoice_code' => $voided->code,
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في إلغاء الفاتورة: '.$e->getMessage(), ['invoice_id' => $id]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * تسجيلُ إعادة الطباعة في سجلّ التدقيق.
     *
     * `ACTION_REPRINT` كانت معرَّفةً ولا تُكتب. وإعادةُ طباعة الإيصال فعلٌ
     * يُراجَع: إيصالٌ ثانٍ لعمليةٍ واحدة وسيلةٌ معروفة للتلاعب — يُسلَّم
     * الأولُ للعميل ويُستعمل الثاني في مطالبةٍ أو مرتجع.
     */
    private function auditReprint(Invoice $invoice): void
    {
        PosAuditLog::record(PosAuditLog::ACTION_REPRINT, [
            'session_id' => $invoice->session_id,
            'invoice_id' => $invoice->id,
            'device_id' => $invoice->pos_device_id,
            'amount' => $invoice->grand_total,
        ]);
    }

    /**
     * فحصُ الأسعار المُعدَّلة يدوياً.
     *
     * السعرُ المرجعي هو سعرُ **الوحدة المختارة** إن وُجدت، وإلا سعرُ الصنف.
     * فبيعُ كرتونٍ بسعر الكرتون ليس تعديلاً وإن خالف سعرَ الحبّة.
     *
     * @param  array  $lines  بنودُ الطلب
     * @param  Collection  $products  الأصنافُ المحمَّلة
     *
     * @throws \RuntimeException
     */
    private function guardPriceOverrides(array $lines, $products, ?PosSession $session): void
    {
        $mayEdit = hasPermission('points_sale_edit_product_prices');

        foreach ($lines as $line) {
            $product = $products->firstWhere('id', $line['id']);

            if (! $product) {
                continue;
            }

            $reference = $this->referenceUnitPrice($product, $line['product_unit_id'] ?? null);

            if ($reference === null) {
                continue;
            }

            $sent = round((float) $line['unit_price'], 2);
            $reference = round($reference, 2);

            // فرقُ قرشٍ تقريبيّ لا يُعدّ تعديلاً
            if (abs($sent - $reference) <= 0.01) {
                continue;
            }

            if (! $mayEdit) {
                throw new \RuntimeException(sprintf(
                    'غير مصرح لك بتعديل سعر «%s». السعر المسجَّل %s والمُرسَل %s.',
                    $product->name,
                    number_format($reference, 2),
                    number_format($sent, 2)
                ));
            }

            PosAuditLog::record(PosAuditLog::ACTION_PRICE_OVERRIDE, [
                'session_id' => $session?->id,
                'device_id' => $session?->device_id,
                'approved_by' => auth()->id(),
                'amount' => $sent - $reference,
                'reason' => 'تعديل سعر أثناء البيع',
                'before' => ['product_id' => $product->id, 'price' => $reference],
                'after' => ['price' => $sent, 'quantity' => $line['quantity']],
            ]);
        }
    }

    /** سعرُ البيع المرجعي: سعرُ الوحدة إن وُجدت، وإلا سعرُ الصنف. */
    private function referenceUnitPrice($product, $productUnitId): ?float
    {
        if ($productUnitId) {
            $unitPrice = ProductUnit::where('id', $productUnitId)->value('sale_price');

            if ($unitPrice > 0) {
                return (float) $unitPrice;
            }
        }

        return $product->sale_price > 0 ? (float) $product->sale_price : null;
    }

    /** استجابةُ «لا جلسة نشطة» — كانت مكرّرةً حرفياً في اثني عشر موضعاً. */
    private function noSessionResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'لا توجد جلسة نشطة. يجب بدء جلسة عمل أولاً.',
            'redirect' => route('pos.sessions.create'),
        ], 403);
    }

    public function getDailyStats()
    {
        // التحقق من الجلسة النشطة
        $activeSession = PosSession::active()->forUser(auth()->id())->first();
        if (! $activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد جلسة نشطة',
                'redirect' => route('pos.sessions.create'),
            ], 403);
        }

        try {
            $today = Carbon::today();

            $stats = [
                /*
                    إحصائياتُ اليوم — لهذا الكاشير ومن يديرهم، لا للنظام كلِّه.

                    كانت `Invoice::where('type','pos')->whereDate(...)` بلا
                    أيّ قيد: مجموعُ مبيعات المنشأة كلِّها، كلِّ الفروع، معروضاً
                    على شاشة كل كاشير باسم «مبيعات اليوم».
                */
                'daily_total_sales' => (float) $this->scopeToVisibleSessionsOf(
                    Invoice::where('type', 'pos')->whereDate('created_at', $today)
                )->sum('grand_total'),

                'daily_total_invoices' => (int) $this->scopeToVisibleSessionsOf(
                    Invoice::where('type', 'pos')->whereDate('created_at', $today)
                )->count(),

                // إحصائيات الجلسة الحالية
                'session_total_sales' => $activeSession->total_sales,
                'session_total_transactions' => $activeSession->total_transactions,
                'session_cash_amount' => $activeSession->total_cash,
                'session_card_amount' => $activeSession->total_card,
                // الرصيدُ المتوقَّع يشمل حركاتِ الدرج — انظر PosSession
                'session_expected_balance' => $activeSession->computeExpectedCash(),

                // من `pos_held_orders` لا من `invoices.status='held'` التي لا تُكتب أبداً
                'held_invoices' => PosHeldOrder::forSession($activeSession->id)->live()->count(),

                'top_products' => InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->where('invoices.type', 'pos')
                    ->where('invoices.session_id', $activeSession->id)
                    ->select('invoice_items.item', DB::raw('SUM(invoice_items.quantity) as total_quantity'))
                    ->groupBy('invoice_items.item')
                    ->orderBy('total_quantity', 'desc')
                    ->limit(5)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'session_info' => [
                    'session_number' => $activeSession->session_number,
                    'started_at' => $activeSession->started_at->format('Y-m-d H:i'),
                    'duration' => $activeSession->started_at->diffForHumans(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب إحصائيات اليوم: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
            ], 500);
        }
    }

    /**
     * جلب الفواتير المتاحة للاسترداد من الجلسة الحالية
     */
    public function getAvailableInvoicesForReturn()
    {
        try {
            $activeSession = PosSession::active()->forUser(auth()->id())->first();
            if (! $activeSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد جلسة نشطة',
                ], 403);
            }

            $invoices = Invoice::with(['client:id,trade_name', 'items'])
                ->where('type', 'pos')
                ->where('session_id', $activeSession->id)
                ->where('payment_status', 1) // فقط الفواتير المدفوعة
                ->orderBy('created_at', 'desc')
                ->get(['id', 'code', 'client_id', 'invoice_date', 'grand_total']);

            // جلب جميع المرتجعات للفواتير - نفس الطريقة المستخدمة في table.blade.php
            $invoiceIds = $invoices->pluck('id')->toArray();

            // البحث عن المرتجعات باستخدام نفس طريقة table.blade.php
            $returnedInvoicesMap = [];
            foreach ($invoiceIds as $invoiceId) {
                $returnedInvoice = Invoice::where('type', 'returned')
                    ->where('reference_number', $invoiceId)
                    ->first();
                if ($returnedInvoice) {
                    $returnedInvoicesMap[$invoiceId] = $returnedInvoice;
                }
            }

            // حساب الكميات المرتجعة لكل فاتورة
            $returnedQuantitiesByInvoice = [];
            foreach ($invoiceIds as $invoiceId) {
                $returnedQuantitiesByInvoice[$invoiceId] = InvoiceItem::whereHas('invoice', function ($query) use ($invoiceId) {
                    $query->where('reference_number', $invoiceId)
                        ->where('type', 'returned');
                })->sum('quantity');
            }

            return response()->json([
                'success' => true,
                'invoices' => $invoices->map(function ($invoice) use ($returnedInvoicesMap, $returnedQuantitiesByInvoice) {
                    // حساب الكمية الأصلية
                    $totalOriginalQty = $invoice->items->sum('quantity');
                    // الكمية المرتجعة
                    $totalReturnedQty = $returnedQuantitiesByInvoice[$invoice->id] ?? 0;

                    // تحديد حالة الاسترداد
                    $hasReturn = isset($returnedInvoicesMap[$invoice->id]);
                    $returnStatus = 'none'; // لا يوجد مرتجع
                    $canReturn = true;

                    if ($hasReturn) {
                        if ($totalReturnedQty >= $totalOriginalQty) {
                            $returnStatus = 'full'; // مرتجع بالكامل
                            $canReturn = false;
                        } else {
                            $returnStatus = 'partial'; // مرتجع جزئي
                        }
                    }

                    return [
                        'id' => $invoice->id,
                        'code' => $invoice->code,
                        'client_name' => optional($invoice->client)->trade_name,
                        'invoice_date' => $invoice->invoice_date,
                        'grand_total' => $invoice->grand_total,
                        'has_return' => $hasReturn,
                        'return_status' => $returnStatus,
                        'can_return' => $canReturn,
                        'total_original_qty' => $totalOriginalQty,
                        'total_returned_qty' => $totalReturnedQty,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب الفواتير للاسترداد: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الفواتير',
            ], 500);
        }
    }

    /**
     * جلب تفاصيل الفاتورة للاسترداد
     */
    public function getInvoiceDetailsForReturn($id)
    {
        try {
            $invoice = Invoice::with(['items', 'client'])
                ->where('id', $id)
                ->where('type', 'pos')
                ->first();

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'الفاتورة غير موجودة',
                ], 404);
            }

            // حساب الكميات المرتجعة سابقاً مع مراعاة الوحدة
            // نستخدم مفتاح مركب: product_id_unit_id
            $returnedQuantities = [];
            $returnedItems = InvoiceItem::whereHas('invoice', function ($query) use ($id) {
                $query->where('reference_number', $id)
                    ->where('type', 'returned');
            })->get();

            foreach ($returnedItems as $returnedItem) {
                $key = $returnedItem->product_id.'_'.($returnedItem->product_unit_id ?? 'nounit');
                if (! isset($returnedQuantities[$key])) {
                    $returnedQuantities[$key] = 0;
                }
                $returnedQuantities[$key] += floatval($returnedItem->quantity);
            }

            // حساب نسبة الضريبة من الفاتورة الأصلية
            $invoiceSubtotal = floatval($invoice->subtotal);
            $invoiceTaxTotal = floatval($invoice->tax_total);
            $taxRate = ($invoiceSubtotal > 0) ? ($invoiceTaxTotal / $invoiceSubtotal) : 0.15; // نسبة الضريبة

            $items = $invoice->items->map(function ($item) use ($returnedQuantities, $taxRate) {
                // حساب السعر شامل الضريبة للعرض باستخدام نسبة الضريبة من الفاتورة
                $priceExcludingTax = floatval($item->unit_price);
                $taxAmount = $priceExcludingTax * $taxRate;
                $priceIncludingTax = $priceExcludingTax + $taxAmount;

                // جلب اسم الوحدة
                $unitName = '';
                if ($item->product_unit_id) {
                    $productUnit = ProductUnit::with('categoryUnit.unit')->find($item->product_unit_id);
                    if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                        $unitName = $productUnit->categoryUnit->unit->name;
                    }
                }

                // بناء اسم العرض مع الوحدة إن وجدت
                $displayName = $item->item; // الاسم المحفوظ يحتوي على الوحدة
                if (empty($displayName)) {
                    $displayName = optional($item->product)->name;
                }
                // إذا كان هناك product_unit_id ولم يكن الاسم يحتوي على الوحدة، أضفها
                if ($item->product_unit_id && $displayName && ! str_contains($displayName, '(') && ! empty($unitName)) {
                    $displayName = $displayName.' ('.$unitName.')';
                }

                // حساب الكمية المرتجعة لهذا المنتج+الوحدة
                $returnKey = $item->product_id.'_'.($item->product_unit_id ?? 'nounit');
                $returnedQty = $returnedQuantities[$returnKey] ?? 0;

                return [
                    'id' => $item->id,
                    'invoice_id' => $item->invoice_id,
                    'product_id' => $item->product_id,
                    'product_unit_id' => $item->product_unit_id, // معرف الوحدة للمرتجع
                    'unit_name' => $unitName, // اسم الوحدة للعرض
                    'item' => $displayName,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price, // السعر الصافي بدون ضريبة (للحساب الداخلي)
                    'unit_price_with_tax' => round($priceIncludingTax, 2), // السعر شامل الضريبة (للعرض)
                    'tax_rate' => round($taxRate * 100, 2), // نسبة الضريبة كنسبة مئوية
                    'total' => $item->total, // الإجمالي شامل الضريبة
                    'returned_quantity' => $returnedQty, // الكمية المرتجعة لهذا المنتج+الوحدة
                ];
            });

            return response()->json([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'subtotal' => $invoiceSubtotal,
                    'tax_total' => $invoiceTaxTotal,
                    'tax_rate' => round($taxRate * 100, 2), // نسبة الضريبة كنسبة مئوية
                    'items' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب تفاصيل الفاتورة للاسترداد: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الفاتورة',
            ], 500);
        }
    }

    /**
     * معالجة الاسترداد
     */
    public function processReturn(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id', // معرف الوحدة
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        if (! hasPermission('pos.returns.create')) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء مرتجعات.',
            ], 403);
        }

        // فترةٌ مقفلة تردّ المرتجع قبل أن تعود بضاعةٌ إلى المخزون
        if ($block = $this->accountingPeriodBlock(now())) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        DB::beginTransaction();

        try {
            $originalInvoice = Invoice::find($validated['invoice_id']);
            if (! $originalInvoice) {
                throw new \Exception('الفاتورة الأصلية غير موجودة');
            }

            /*
                ═══════════ لا يُرجَع إلّا ما بِيع ═══════════

                **ما كان**: `processReturn` تقبل أيَّ فاتورة — بما فيها
                **فاتورةُ مرتجع**. فيُنشأ مرتجعٌ لمرتجع: بضاعةٌ تُضاف إلى
                المخزون مرّةً ثانية وقد أُضيفت، وقيدٌ يعكس عكساً.

                وقائمةُ «المتاح للإرجاع» تُصفّي `type = 'pos'` — لكنّ زرَّ
                المرتجع في قائمة الفواتير يظهر على كل صفّ، وصفوفُ المرتجعات
                فيها. فالحراسةُ في العرض وحده تُلتفّ بنقرة.

                والفواتيرُ غيرُ فواتير نقطة البيع تُردّ كذلك: مرتجعُ فاتورة
                مبيعاتٍ عاديّة موضعُه شاشةُ المبيعات، وحسابُها وقيدُها
                يختلفان.
            */
            if ($originalInvoice->type === 'returned') {
                throw new \Exception(
                    'لا يمكن إنشاء مرتجعٍ لفاتورة مرتجع. '
                    .'فاتورةُ المرتجع '.$originalInvoice->code.' هي نفسُها إرجاعٌ لفاتورةٍ أخرى.'
                );
            }

            if ($originalInvoice->type !== 'pos') {
                throw new \Exception(
                    'هذه ليست فاتورةَ نقطة بيع، فلا تُرجَع من هذه الشاشة. '
                    .'مرتجعُ فواتير المبيعات يُنشأ من شاشة المبيعات.'
                );
            }

            /*
                ═══════════ ولا فاتورةٌ أُرجعت بالكامل ═══════════

                كان الحارسُ الوحيد على مستوى **البند**: «لا يمكن إرجاع كمية
                أكبر من المتاحة». وهو يمنع تجاوزَ الكمّية ولا يمنع فتحَ
                نافذة مرتجعٍ لفاتورةٍ لم يبقَ فيها شيء — فيرى المستخدم
                جدولاً كلُّ سقوفه صفر، ويحاول، ويُردّ ببندٍ واحد.

                والقولُ في أوّله أوضح: الفاتورةُ مستوفاة.
            */
            $state = $this->returnStateOf($originalInvoice);

            if (! $state['can_return']) {
                throw new \Exception(sprintf(
                    'الفاتورة %s أُرجعت بالكامل (%s من %s) — لم يبقَ فيها ما يُرجَع.',
                    $originalInvoice->code,
                    rtrim(rtrim(number_format($state['total_returned_qty'], 2), '0'), '.'),
                    rtrim(rtrim(number_format($state['total_original_qty'], 2), '0'), '.')
                ));
            }

            // ======================================
            // فحوصات المرتجع - التحقق من عدم تجاوز القيم الأصلية
            // ======================================

            // 1. التحقق من أن الفاتورة ليست مرتجعة بالكامل مسبقاً
            $previousReturns = Invoice::where('reference_number', $originalInvoice->id)
                ->where('type', 'returned')
                ->sum('grand_total');

            if ($previousReturns >= $originalInvoice->grand_total) {
                throw new \Exception('هذه الفاتورة تم إرجاعها بالكامل مسبقاً');
            }

            // 2. التحقق من الكمية المتاحة للإرجاع لكل منتج مع مراعاة الوحدات
            // نجمع العناصر الأصلية بمفتاح مركب (product_id + product_unit_id)
            $originalItems = InvoiceItem::where('invoice_id', $originalInvoice->id)->get();
            $originalItemsMap = [];
            foreach ($originalItems as $origItem) {
                // المفتاح المركب: product_id_unit_id أو product_id_nounit
                $key = $origItem->product_id.'_'.($origItem->product_unit_id ?? 'nounit');
                $originalItemsMap[$key] = $origItem;
            }

            foreach ($validated['items'] as $item) {
                $productId = $item['product_id'];
                $productUnitId = $item['product_unit_id'] ?? null;
                $requestedQty = floatval($item['quantity']);

                // إنشاء المفتاح المركب للبحث
                $itemKey = $productId.'_'.($productUnitId ?? 'nounit');

                // التحقق من وجود المنتج في الفاتورة الأصلية
                $originalItem = $originalItemsMap[$itemKey] ?? null;

                // إذا لم يوجد بالمفتاح المركب، جرب البحث بـ product_id فقط (للتوافق مع الفواتير القديمة)
                if (! $originalItem) {
                    foreach ($originalItemsMap as $key => $origItem) {
                        if (strpos($key, $productId.'_') === 0) {
                            $originalItem = $origItem;
                            $productUnitId = $origItem->product_unit_id; // استخدم الوحدة من الفاتورة الأصلية
                            $itemKey = $key;
                            break;
                        }
                    }
                }

                if (! $originalItem) {
                    $product = Product::find($productId);
                    throw new \Exception('المنتج غير موجود في الفاتورة الأصلية: '.($product->name ?? $productId));
                }

                $originalQty = floatval($originalItem->quantity);

                // حساب الكمية المرتجعة مسبقاً لهذا المنتج ونفس الوحدة
                $previousReturnQuery = InvoiceItem::whereHas('invoice', function ($query) use ($originalInvoice) {
                    $query->where('reference_number', $originalInvoice->id)
                        ->where('type', 'returned');
                })->where('product_id', $productId);

                // مراعاة الوحدة في حساب المرتجعات السابقة
                if ($productUnitId) {
                    $previousReturnQuery->where('product_unit_id', $productUnitId);
                } else {
                    $previousReturnQuery->whereNull('product_unit_id');
                }

                $previousReturnedQty = $previousReturnQuery->sum('quantity');

                $availableQty = $originalQty - floatval($previousReturnedQty);

                if ($requestedQty > $availableQty) {
                    $product = Product::find($productId);
                    // الحصول على اسم الوحدة للعرض في رسالة الخطأ
                    $unitInfo = '';
                    if ($productUnitId) {
                        $productUnit = ProductUnit::with('categoryUnit.unit')->find($productUnitId);
                        if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                            $unitInfo = ' ('.$productUnit->categoryUnit->unit->name.')';
                        }
                    }
                    throw new \Exception(sprintf(
                        'لا يمكن إرجاع كمية أكبر من المتاحة للمنتج "%s%s". الكمية المتاحة: %s، الكمية المطلوبة: %s',
                        $product->name ?? $productId,
                        $unitInfo,
                        number_format($availableQty, 2),
                        number_format($requestedQty, 2)
                    ));
                }
            }

            // 3. حساب إجمالي المرتجع المطلوب والتحقق من عدم تجاوز إجمالي الفاتورة
            $requestedReturnTotal = 0;
            $taxRate = ($originalInvoice->subtotal > 0)
                ? ($originalInvoice->tax_total / $originalInvoice->subtotal)
                : 0.15;

            foreach ($validated['items'] as $item) {
                $unitPrice = floatval($item['unit_price']);
                $quantity = floatval($item['quantity']);
                $itemTotal = $quantity * $unitPrice * (1 + $taxRate);
                $requestedReturnTotal += $itemTotal;
            }

            $remainingAmount = $originalInvoice->grand_total - $previousReturns;

            if ($requestedReturnTotal > $remainingAmount + 0.01) { // تسامح بسيط للتقريب
                throw new \Exception(sprintf(
                    'إجمالي المرتجع (%.2f) يتجاوز المبلغ المتبقي للإرجاع (%.2f)',
                    $requestedReturnTotal,
                    $remainingAmount
                ));
            }

            // ======================================
            // نهاية فحوصات المرتجع
            // ======================================

            // التحقق من وجود جلسة نشطة
            $activeSession = PosSession::active()->forUser(auth()->id())->first();
            if (! $activeSession) {
                throw new \Exception('لا توجد جلسة نشطة');
            }

            // رقمُ المرتجع من إعدادات الترقيم تحت قفل — لا حلقةَ `while` بلا قفل
            $code = $this->issueReturnNumber();

            // تجهيز المتغيرات الرئيسية لحساب الفاتورة
            $total_amount = 0;
            $total_discount = 0;
            $items_data = [];

            $user = Auth::user();

            /*
                المستودعُ من إعدادات نقطة البيع — بنفس قاعدة البيع.

                كان المرتجعُ يشتقّه من `DefaultWarehouses` (مخزونِ المندوب)
                بينما البيعُ يشتقّه من مستودع الجهاز عبر الجلسة. منطقان
                مختلفان لسؤالٍ واحد، فيُخصم البيعُ من مستودعٍ ويُردّ
                المرتجعُ إلى آخر. وصارا يسألان [PosCustody] معاً.

                (وبنودُ المرتجع تستعمل `store_house_id` المحفوظ في البند
                الأصلي على كل حال — وهو الأصحّ — فهذا احتياطٌ لما لا بند له.)
            */
            $storeHouse = $this->requirePosWarehouse();

            if (! $storeHouse) {
                throw new \RuntimeException('لا يوجد أي مستودع في النظام.');
            }

            // خزينةُ الكاشير — بالمفتاح المضبوط لا بالبحث عن اسمٍ عربي
            $MainTreasury = $this->treasuryAccountFor($user?->employee_id);

            // حساب نسبة الضريبة من الفاتورة الأصلية
            $originalSubtotal = floatval($originalInvoice->subtotal);
            $originalTaxTotal = floatval($originalInvoice->tax_total);
            $taxRate = ($originalSubtotal > 0) ? ($originalTaxTotal / $originalSubtotal) : 0.15;

            // معالجة البنود
            foreach ($validated['items'] as $item) {
                // جلب المنتج
                $product = Product::find($item['product_id']);
                if (! $product) {
                    throw new \Exception('المنتج غير موجود: '.$item['product_id']);
                }

                // جلب product_unit_id من الطلب (قد يكون null للمنتجات بدون وحدة)
                $requestProductUnitId = $item['product_unit_id'] ?? null;

                // التحقق من البند في الفاتورة الأصلية مع مراعاة الوحدة
                // يجب البحث بنفس product_unit_id لأن نفس المنتج قد يكون ببيع بوحدات مختلفة
                $originalItemQuery = InvoiceItem::where('invoice_id', $originalInvoice->id)
                    ->where('product_id', $item['product_id']);

                if ($requestProductUnitId) {
                    // البحث عن العنصر بنفس الوحدة
                    $original_item = $originalItemQuery->where('product_unit_id', $requestProductUnitId)->first();
                } else {
                    // البحث عن العنصر بدون وحدة (product_unit_id = null)
                    $original_item = $originalItemQuery->whereNull('product_unit_id')->first();

                    // إذا لم يوجد، جرب البحث بأي عنصر لهذا المنتج (للتوافق مع الفواتير القديمة)
                    if (! $original_item) {
                        $original_item = InvoiceItem::where('invoice_id', $originalInvoice->id)
                            ->where('product_id', $item['product_id'])->first();
                    }
                }

                if (! $original_item) {
                    throw new \Exception('المنتج غير موجود في الفاتورة الأصلية: '.$product->name);
                }

                // استخدام product_unit_id من العنصر الأصلي إذا لم يُرسل في الطلب
                $productUnitId = $requestProductUnitId ?? $original_item->product_unit_id;

                // حساب الكمية المرتجعة سابقاً لنفس المنتج ونفس الوحدة
                $previousReturnQuery = InvoiceItem::whereHas('invoice', function ($query) use ($originalInvoice) {
                    $query->where('reference_number', $originalInvoice->id)
                        ->where('type', 'returned');
                })->where('product_id', $item['product_id']);

                // مراعاة الوحدة في حساب المرتجعات السابقة
                if ($productUnitId) {
                    $previousReturnQuery->where('product_unit_id', $productUnitId);
                } else {
                    $previousReturnQuery->whereNull('product_unit_id');
                }

                $previous_return_qty = $previousReturnQuery->sum('quantity');

                // التحقق من عدم تجاوز الكمية الأصلية
                $total_return_qty = floatval($previous_return_qty) + floatval($item['quantity']);

                // الحصول على معلومات الوحدة للعرض في رسالة الخطأ
                $unitInfo = '';
                if ($productUnitId) {
                    $productUnit = ProductUnit::with('categoryUnit.unit')->find($productUnitId);
                    if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                        $unitInfo = ' ('.$productUnit->categoryUnit->unit->name.')';
                    }
                }

                if ($total_return_qty > $original_item->quantity) {
                    $availableQty = $original_item->quantity - floatval($previous_return_qty);
                    throw new \Exception(sprintf(
                        'لا يمكن إرجاع كمية أكبر من الأصلية للمنتج: %s%s. الكمية المتاحة للإرجاع: %s، الكمية المطلوبة: %s',
                        $product->name,
                        $unitInfo,
                        number_format($availableQty, 2),
                        number_format($item['quantity'], 2)
                    ));
                }

                // حساب تفاصيل الكمية والأسعار مع الضريبة
                // السعر المستلم من قاعدة البيانات هو السعر بدون ضريبة (Tax Exclusive)
                // نستخدم نسبة الضريبة المحسوبة من الفاتورة الأصلية
                $quantity = floatval($item['quantity']);
                $unit_price_excluding_tax = floatval($item['unit_price']); // السعر المخزن بدون ضريبة
                $tax_per_unit = $unit_price_excluding_tax * $taxRate; // مقدار الضريبة للوحدة
                $unit_price_including_tax = $unit_price_excluding_tax + $tax_per_unit; // السعر شامل الضريبة
                $item_total = $quantity * $unit_price_including_tax; // الإجمالي شامل الضريبة
                $item_tax_total = $quantity * $tax_per_unit; // إجمالي الضريبة

                // تحديث الإجماليات
                $total_amount += $item_total;

                // حساب الكمية بالوحدة الأساسية لتحديث المخزون
                // ملاحظة مهمة: نستخدم القيم المحفوظة في الفاتورة الأصلية بدلاً من إعادة حسابها
                // لأن البيانات (معامل التحويل، الوحدات) قد تتغير لاحقاً

                // الحصول على معامل التحويل واسم الوحدة من الفاتورة الأصلية
                $conversionFactor = floatval($original_item->conversion_factor ?? 1);
                if ($conversionFactor <= 0) {
                    $conversionFactor = 1;
                }
                $unitName = $original_item->unit_name ?? '';

                // الكمية بالوحدة الأساسية = الكمية المرتجعة × معامل التحويل المحفوظ
                $quantityInBaseUnit = $quantity * $conversionFactor;

                // إضافة اسم الوحدة بجانب اسم المنتج إذا وجدت
                $itemName = $product->name;
                $itemDescription = $product->name;
                if (! empty($unitName)) {
                    $itemName = $product->name.' ('.$unitName.')';
                    $itemDescription = $product->name.' - '.$unitName;
                }

                // تجهيز بيانات البند
                // ملاحظة مهمة: نستخدم store_house_id من البند الأصلي لضمان حساب التكلفة الصحيحة
                $originalStoreHouseId = $original_item->store_house_id ?? $storeHouse->id;

                $items_data[] = [
                    'invoice_id' => null, // سيتم تعيينه لاحقاً
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $productUnitId, // معرف الوحدة المستخدمة
                    'store_house_id' => $originalStoreHouseId, // استخدام مستودع البند الأصلي
                    'item' => $itemName, // اسم المنتج مع الوحدة
                    'description' => $itemDescription, // الوصف مع الوحدة
                    'quantity' => $quantity, // الكمية بالوحدة المختارة (للفاتورة)
                    'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية (للمخزون)
                    'conversion_factor' => $conversionFactor, // معامل التحويل (من الفاتورة الأصلية)
                    'unit_name' => $unitName, // اسم الوحدة (من الفاتورة الأصلية)
                    'unit_price' => $unit_price_excluding_tax, // السعر بدون ضريبة
                    'discount' => 0,
                    'discount_type' => 1,
                    'tax_1' => $item_tax_total, // إجمالي الضريبة للعنصر
                    'tax_2' => 0,
                    'total' => $item_total, // الإجمالي شامل الضريبة

                    /*
                        التكلفةُ منقولةٌ عن البند الأصلي — لا محسوبةً اليوم.

                        فقيدُ المرتجع يعكس ما قُيّد على الفاتورة بالضبط،
                        ولا يُنتج الردُّ وحدَه ربحاً من فرق المتوسّط.
                        و`null` لفواتيرَ سبقت تجميدَ التكلفة، ويتولّاها
                        [ResolvesProductCost] بالمتوسّط.
                    */
                    'unit_cost_at_sale' => $original_item->unit_cost_at_sale ?? null,
                ];
            }

            // حساب إجمالي الضريبة
            $tax_total = 0;
            foreach ($items_data as $item_data) {
                $tax_total += $item_data['tax_1'];
            }

            // حساب المبلغ بدون ضريبة
            $amount_excluding_tax = $total_amount - $tax_total;

            /*
                حدُّ المرتجع — ضابطُ الفرع الذي كان معطَّلاً.

                `max_refund_amount` معرَّفٌ في كتالوج إعدادات الفروع بوصفٍ
                صريح: «المرتجعات فوق هذا المبلغ تتطلب اعتماد مسؤول. صفر =
                كلها تتطلب اعتماداً». **ولم يكن يقرؤه سطرٌ واحد في وحدة
                نقاط البيع** — فالمسؤول يضبطه ولا يحدث شيء.
            */
            if ($this->refundNeedsApproval($total_amount, $activeSession->branch_id)) {
                if (! hasPermission('pos.returns.approve')) {
                    $limit = $this->maxRefundWithoutApproval($activeSession->branch_id);

                    throw new \RuntimeException($limit > 0
                        ? sprintf(
                            'قيمة المرتجع (%s) تتجاوز الحدّ المسموح بلا اعتماد (%s). يلزم اعتماد مسؤول.',
                            number_format($total_amount, 2),
                            number_format($limit, 2)
                        )
                        : 'كلُّ المرتجعات في هذا الفرع تتطلّب اعتماد مسؤول.');
                }

                PosAuditLog::record(PosAuditLog::ACTION_REFUND_OVER_LIMIT, [
                    'session_id' => $activeSession->id,
                    'device_id' => $activeSession->device_id,
                    'branch_id' => $activeSession->branch_id,
                    'invoice_id' => $originalInvoice->id,
                    'approved_by' => auth()->id(),
                    'amount' => $total_amount,
                    'currency' => $this->documentCurrency($originalInvoice),
                    'before' => ['limit' => $this->maxRefundWithoutApproval($activeSession->branch_id)],
                ]);
            }

            // إنشاء فاتورة الإرجاع
            $returnInvoice = Invoice::create([
                'client_id' => $originalInvoice->client_id,
                'employee_id' => $user->employee_id,
                'due_value' => $total_amount,
                'reference_number' => $originalInvoice->id,
                'code' => $code,
                'type' => 'returned',
                'invoice_date' => now(),
                'issue_date' => now(),
                'terms' => 0,
                'notes' => 'فاتورة إرجاع من نقطة البيع - الفاتورة الأصلية: '.$originalInvoice->code,
                'payment_status' => 4,
                'is_paid' => false,
                'created_by' => Auth::id(),
                'account_id' => null,
                'discount_amount' => 0,
                'discount_type' => 1,
                'advance_payment' => 0,
                'payment_type' => 1,
                'shipping_cost' => 0,
                'shipping_tax' => 0,
                'tax_type' => 1,
                'payment_method' => null,
                'received_date' => now(),
                'subtotal' => $amount_excluding_tax,
                'total_discount' => 0,
                'tax_total' => $tax_total,
                'grand_total' => $total_amount,
                'advance_payment' => 0, // المبلغ المدفوع
                'session_id' => $activeSession->id, // ربط بالجلسة
                // العملةُ والفرعُ والفترةُ تُختَم في [Invoice] — انظر سماتها
                'pos_device_id' => $activeSession->device_id,
            ]);

            // تحديث قيمة المرتجع في الفاتورة الأصلية
            $originalInvoice->returned_payment += $returnInvoice->grand_total;
            $originalInvoice->save();

            // إنشاء عناصر الفاتورة وتحديث المخزون
            foreach ($items_data as $item) {
                $item['invoice_id'] = $returnInvoice->id;

                // استخراج الكمية بالوحدة الأساسية لتحديث المخزون
                $quantityInBaseUnit = $item['quantity_in_base_unit'] ?? $item['quantity'];

                // الحقول الآن موجودة في الجدول: quantity_in_base_unit, conversion_factor, unit_name
                $item_invoice = InvoiceItem::create($item);

                // تحديث المخزون
                $productDetails = ProductDetails::where('store_house_id', $item['store_house_id'])
                    ->where('product_id', $item['product_id'])->first();

                if (! $productDetails) {
                    $productDetails = ProductDetails::create([
                        'store_house_id' => $item['store_house_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => 0,
                    ]);
                }

                $product = Product::find($item['product_id']);

                /*
                    ═════════ ما كان ═════════

                        if ($product->type == 'products') { ... }
                        // ← لا فرعَ لـ compiled

                    والبيعُ يخصم مكوّناتِ المنتج المجمّع
                    (`handleInstantCompiledProduct` و`handleCompiledSubProduct`)،
                    **والمرتجعُ لا يعيدها**. فمخزونُ المكوّنات يتناقص تناقصاً
                    دائماً مع كل دورة بيعٍ وإرجاع — وهو نقصٌ لا يفسّره شيء
                    في حركة المخزون لأنّ الصنفَ المجمّع نفسَه يبدو متوازناً.
                */
                $this->restoreReturnedStock(
                    $product,
                    $quantityInBaseUnit,
                    $productDetails,
                    $item,
                    $returnInvoice
                );
            }

            // القيود المحاسبية — تُكتب دائماً، لا للمدفوعة بالكامل وحدها
            $this->createReturnAccountingEntries(
                $returnInvoice, $originalInvoice, $MainTreasury, $activeSession,
                $request->boolean('refund_cash')
            );

            // إضافة معاملة لتفاصيل الجلسة
            $this->addReturnTransactionToSession($activeSession->id, $returnInvoice, $this->refundedCash);

            // حساب حالة المرتجع للفاتورة الأصلية
            $totalReturnedAmount = $previousReturns + $returnInvoice->grand_total;
            $returnStatus = ($totalReturnedAmount >= $originalInvoice->grand_total) ? 'full' : 'partial';
            $canReturnMore = ($totalReturnedAmount < $originalInvoice->grand_total);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء فاتورة الاسترداد بنجاح',
                'return_invoice_id' => $returnInvoice->id,
                'return_invoice_code' => $returnInvoice->code,
                'return_total' => $returnInvoice->grand_total,
                'original_invoice_id' => $originalInvoice->id,
                'original_invoice_code' => $originalInvoice->code,
                'total_returned_amount' => $totalReturnedAmount,
                'remaining_amount' => max(0, $originalInvoice->grand_total - $totalReturnedAmount),
                'return_status' => $returnStatus,
                'can_return_more' => $canReturnMore,

                // ما وقع فعلاً — لتقوله الشاشةُ للكاشير بدل أن يخمّنه
                'refunded_cash' => $this->refundedCash,
                'refunded_amount' => $this->refundedCash ? (float) $returnInvoice->grand_total : 0.0,

                // الأرصدةُ بعد إعادة البضاعة — بالاتجاه المعاكس للبيع
                'stock' => $this->currentStockOf(
                    collect($items_data)->pluck('product_id')->all(),
                    collect($items_data)->pluck('store_house_id')->first() ?? $storeHouse->id
                ),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في معالجة الاسترداد: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * إعادةُ البضاعة المرتجعة إلى المخزون — بأنواع المنتجات الثلاثة.
     *
     * البيعُ يميّز ثلاثةَ أنواع (عاديّ، ومجمّعٌ فوري، ومجمّعٌ عادي) ويخصم
     * لكلٍّ منها ما يخصّه. والمرتجعُ كان يعرف نوعاً واحداً.
     *
     * @param  float  $quantity  الكمية بالوحدة الأساسية
     */
    private function restoreReturnedStock($product, float $quantity, $productDetails, array $item, $returnInvoice): void
    {
        if (! $product) {
            return;
        }

        // ما لم يُخصم عند البيع لا يُعاد عند المرتجع
        if (! ProductType::from_value($product->type)->tracksStock()) {
            return;
        }

        $storeHouseId = $item['store_house_id'];

        $isInstantCompiled = $product->type === 'compiled' && $product->compile_type === 'Instant';

        /*
            المجمّعُ الفوري لا مخزونَ له: يُركَّب لحظةَ البيع من مكوّناته
            (يُضاف ثم يُخصم في حركتين متعاكستين). فمرتجعُه يُعيد **المكوّنات**
            ولا يُعيد الصنفَ المركَّب.
        */
        if (! $isInstantCompiled) {
            $this->restoreProductQuantity($product->id, $quantity, $productDetails, $storeHouseId, $item, $returnInvoice);
        }

        // المجمَّعُ بنوعيه يُعيد مكوّناته
        if ($product->type === 'compiled') {
            $components = CompiledProducts::where('compile_id', $product->id)->get();

            foreach ($components as $component) {
                $componentQty = (float) $component->qyt * $quantity;

                if ($componentQty <= 0) {
                    continue;
                }

                $componentDetails = ProductDetails::firstOrCreate(
                    ['store_house_id' => $storeHouseId, 'product_id' => $component->product_id],
                    ['quantity' => 0]
                );

                $this->restoreProductQuantity(
                    $component->product_id,
                    $componentQty,
                    $componentDetails,
                    $storeHouseId,
                    $item + ['unit_price' => 0, 'total' => 0],
                    $returnInvoice
                );
            }
        }
    }

    /**
     * أرصدةُ أصنافٍ في مستودعٍ بعينه — بعد الحركة مباشرةً.
     *
     * تُقرأ بعد `DB::commit()` منطقياً (السطورُ محدَّثةٌ في المعاملة نفسِها)
     * فتعكس ما استقرّ فعلاً، لا ما كان قبل البيع.
     *
     * والمجمَّعُ يُعيد مكوّناته أيضاً: فتُضاف معرّفاتُها ليُحدَّث المعروضُ
     * منها كذلك — وهي التي تنقص فعلياً حين يُباع صنفٌ مركَّب.
     *
     * @param  array<int, int|string>  $productIds
     * @return array<int, float> معرّفُ الصنف ⇐ رصيدُه
     */
    private function currentStockOf(array $productIds, ?int $storeHouseId): array
    {
        if (! $storeHouseId || ! $productIds) {
            return [];
        }

        $ids = collect($productIds)->map(fn ($id) => (int) $id)->unique();

        // مكوّناتُ ما كان مجمَّعاً — تنقص وتعود مع الصنف المركَّب
        $components = CompiledProducts::whereIn('compile_id', $ids)->pluck('product_id');

        return ProductDetails::where('store_house_id', $storeHouseId)
            ->whereIn('product_id', $ids->merge($components)->unique())
            ->pluck('quantity', 'product_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }

    /**
     * سياقُ الإذن — ما يربطه بمستنده.
     *
     * كان الإذنُ يُنشأ بـ`number = $invoice->id` وحدَه: **معرّفُ** الفاتورة
     * في حقلٍ يُقرأ رقماً، و`reference_type` على افتراضه `'normal'`
     * و`reference_id` فارغاً. فلا يُعرف من الإذن أيَّ فاتورةٍ يخصّ إلّا
     * بتخمين، ولا يُوصَل إليه من الفاتورة إطلاقاً.
     */
    private function permitContext($invoice, $referenceType = null): array
    {
        return [
            'reference_type' => ($referenceType ?? ReferenceType::SALES_INVOICE)->value,
            'reference_id' => $invoice->id,

            /*
                ولا `number` هنا.

                وضعتُه أوّلاً رقمَ الفاتورة — وهو خطأ: فاتورةٌ واحدةٌ قد
                تُصدر أذوناً عدّة (منتجٌ مجمَّعٌ فوريّ يُصدر ثلاثة) فتحمل
                كلُّها الرقمَ نفسَه.

                فيتولّاه [PosPermitIssuer] من إعدادات الترقيم بحسب اتّجاه
                الحركة. والربطُ بالفاتورة قائمٌ في `reference_id` أعلاه.
            */
            'currency' => $this->documentCurrency($invoice),
            'accounting_period_id' => $invoice->accounting_period_id ?? null,
            'client_id' => $invoice->client_id ?? null,
        ];
    }

    /** زيادةُ رصيد صنفٍ في مستودع، وتسجيلُ إذنِ حركته. */
    private function restoreProductQuantity($productId, float $quantity, $productDetails, $storeHouseId, array $item, $returnInvoice): void
    {
        // رصيدُ هذا المستودع لا مجموعُ المستودعات — انظر [handleRegularProduct]
        $stockBefore = (float) $productDetails->quantity;

        $productDetails->increment('quantity', $quantity);

        /*
            إذنُ إضافةٍ معتمَدٌ للمرتجع — بالاتجاه المعاكس للبيع.

            البضاعةُ عادت إلى الرفّ فعلاً قبل هذا السطر. فالإذنُ يوثّقه
            معتمَداً، ولا ينتظر موافقةً على أمرٍ وقع.
        */
        PosPermitIssuer::issueLine(
            PosPermitIssuer::SOURCE_RETURN,
            $storeHouseId,
            [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $item['unit_price'] ?? 0,
                'total' => $item['total'] ?? 0,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $quantity,
            ],
            $this->permitContext($returnInvoice, ReferenceType::SALES_RETURN)
        );
    }

    /**
     * قيودُ المرتجع.
     *
     * ═════════════════════ أخطرُ ما كان ═════════════════════
     *
     *     if ($originalInvoice->payment_status == 1) {
     *         // ... كلُّ القيود هنا ...
     *     }
     *     // ← لا else
     *
     * **مرتجعُ فاتورةٍ غيرِ مدفوعةٍ بالكامل لا يُنتج قيداً واحداً.** والمسارُ
     * قبله يكون قد أنشأ فاتورةَ المرتجع و**أعاد البضاعة إلى المخزون**
     * وسجّل الحدث في الجلسة. فالمخزونُ يزيد في الواقع وفي `product_details`
     * ولا يزيد في الدفاتر، ورصيدُ العميل لا يُعدَّل — والفرقُ لا يظهر إلّا
     * عند الجرد.
     *
     * ═════════════════════ ما صار ═════════════════════
     *
     * قيدُ المرتجع يُكتب **دائماً** — مردودُ المبيعات مدينٌ والضريبةُ مدينة
     * والعميلُ دائن. وما يختلف هو **التسوية**:
     *
     *   • فاتورةٌ مدفوعة ⇒ يُردّ المبلغ نقداً: قيدٌ ثانٍ يُنقص الدرج.
     *   • فاتورةٌ آجلة  ⇒ لا نقدَ يُردّ، ويبقى المرتجعُ خصماً على ذمّة
     *     العميل — وهو الصحيح محاسبياً.
     *
     * وردُّ النقد نفسُه صار محكوماً بإعداد الفرع `allow_cash_refund`: كان
     * مفروضاً دائماً بلا خيار، والمفتاحُ معرَّفٌ في كتالوج إعدادات الفروع
     * ولا يقرؤه شيء.
     *
     * @throws \RuntimeException
     */
    private function createReturnAccountingEntries(
        $returnInvoice,
        $originalInvoice,
        $MainTreasury,
        ?PosSession $session = null,
        bool $refundCash = false
    ) {
        $subtotal = (float) $returnInvoice->subtotal;
        $taxTotal = (float) $returnInvoice->tax_total;
        $grandTotal = (float) $returnInvoice->grand_total;

        $clientAccount = Account::where('client_id', $returnInvoice->client_id)->first();

        if (! $clientAccount) {
            throw new \RuntimeException(
                'لا يوجد حساب محاسبي مرتبط بالعميل — لا يمكن ترحيل المرتجع.'
            );
        }

        $context = [
            'currency' => $this->documentCurrency($returnInvoice),
            'client_id' => $returnInvoice->client_id,
            'invoice_id' => $returnInvoice->id,
            'branch_id' => $session?->branch_id ?? $returnInvoice->pos_branch_id,
            'accounting_period_id' => $returnInvoice->accounting_period_id,
            'reference_type' => ReferenceType::SALES_RETURN,
            'linking_type' => LinkingType::SINGLE,
        ];

        /* ═══════ القيد الأول: إثبات المرتجع — يُكتب دائماً ═══════ */

        $poster = PosJournalPoster::make($context + [
            'reference_number' => $returnInvoice->code,
            'date' => $returnInvoice->invoice_date ?? now(),
            'description' => 'مرتجع مبيعات نقطة بيع رقم '.$returnInvoice->code,
        ]);

        $poster->debit($this->salesReturnsAccount(), $subtotal, 'مردود مبيعات نقطة بيع');

        if ($taxTotal > 0) {
            $vatAccount = $this->vatPayableAccount();

            if (! $vatAccount) {
                throw new \RuntimeException(
                    'لا يمكن ترحيل ضريبة المرتجع: حساب «ضريبة القيمة المضافة المستحقّة» غير مربوط.'
                );
            }

            $poster->debit($vatAccount, $taxTotal, 'عكس الضريبة المحصَّلة على المرتجع');
        }

        $poster->credit($clientAccount, $grandTotal, 'مرتجع مبيعات — فاتورة '.$returnInvoice->code);

        $poster->post();

        /* ═══════ القيد الثاني: ردُّ النقد — بقرارٍ صريح ═══════ */

        $wasPaid = (int) $originalInvoice->payment_status === 1;
        /*
            الردُّ النقدي يحتاج ثلاثة: إذنَ الفرع، وصلاحيةَ المستخدم،
            **وطلبَه**.

            `pos.returns.cash_refund` كانت معرَّفةً في الكتالوج ولا تُفحص —
            فإخراجُ المال من الدرج كان تابعاً لإعداد الفرع وحده.

            والثالثُ هو الجديد: كان الصرفُ يقع **تلقائياً** متى سُمح به،
            بلا أن يعرف الكاشيرُ أنّ مالاً خرج من درجه. والعميلُ قد يريد
            رصيداً لفاتورةٍ تالية لا نقداً في يده — وهو قرارُ لحظةِ
            المرتجع، لا إعدادٌ يُضبط مرّةً في السنة.

            فصار زرّان: «مرتجع مع الدفع» و«مرتجع بلا دفع».
        */
        $cashRefundAllowed = $this->allowsCashRefund($session?->branch_id)
            && hasPermission('pos.returns.cash_refund');

        $this->refundedCash = $wasPaid && $cashRefundAllowed && $refundCash;

        if ($this->refundedCash) {
            $treasury = $MainTreasury ?: $this->treasuryAccountFor(auth()->user()->employee_id ?? null);

            PosJournalPoster::make($context + [
                'reference_number' => $returnInvoice->code.'-PAY',
                'date' => $returnInvoice->invoice_date ?? now(),
                'description' => 'ردّ قيمة المرتجع نقداً — فاتورة '.$returnInvoice->code,
            ])
                ->debit($clientAccount, $grandTotal, 'تسوية ذمّة العميل بردّ المرتجع')
                ->credit($treasury, $grandTotal, 'صرف قيمة المرتجع من الدرج')
                ->post();
        } elseif ($wasPaid && $refundCash) {
            /*
                طلبَ الصرفَ ولا يجوز — يُقال السببُ بعينه لا «تعذّر».
            */
            throw new \RuntimeException(
                ! $this->allowsCashRefund($session?->branch_id)
                    ? 'الردُّ النقدي معطَّلٌ في إعدادات هذا الفرع — يُنشأ المرتجع رصيداً للعميل.'
                    : 'لا تملك صلاحية «ردّ قيمة المرتجع نقداً» — يُنشأ المرتجع رصيداً للعميل.'
            );
        } elseif ($wasPaid) {
            /*
                الفاتورةُ مدفوعة والفرعُ يمنع الردّ النقدي: يبقى المبلغُ
                رصيداً دائناً للعميل يُسوّى لاحقاً بإشعارٍ دائن أو بفاتورةٍ
                تالية. ويُكتب ذلك ليُقرأ في التقرير.
            */
            Log::info('مرتجعٌ بلا ردّ نقدي — إعدادُ الفرع يمنعه', [
                'return_invoice_id' => $returnInvoice->id,
                'branch_id' => $session?->branch_id,
                'amount' => $grandTotal,
            ]);
        }

        /* ═══════ القيد الثالث: عكسُ تكلفة المبيعات ═══════ */

        $this->createReturnCostOfSalesEntry($returnInvoice, $this->costOfSalesAccount(), $session);
    }

    /**
     * عكسُ تكلفة المبيعات للمرتجع — المخزونُ مدينٌ والتكلفةُ دائنة.
     *
     * يرمي عند الفشل بدل أن يبتلع: البضاعةُ عادت إلى `product_details`
     * فعلاً، فتركُها بلا قيدٍ يجعل المخزونَ الدفتري أقلَّ من الفعلي بمقدار
     * تكلفتها — وهو فرقٌ لا يُكتشف إلّا في الجرد.
     */
    private function createReturnCostOfSalesEntry($returnInvoice, $costAccount, ?PosSession $session = null)
    {
        $costData = $this->calculateReturnCostOfGoods($returnInvoice);

        $costOfGoods = $costData['total_cost'];
        /*
            حسابُ المخزون من مستودع البند المرتجَع — لا من العام.

            البضاعةُ تعود إلى المستودع الذي خرجت منه، فقيدُها يَمَسّ حسابَه
            هو. و`null` هنا كانت تُرحّل الإرجاعَ إلى الأبّ فيختلّ رصيدُ
            المستودعَين معاً: الذي عادت إليه والذي قُيّد عليه.
        */
        $returnStoreHouseId = $costData['store_house_id']
            ?? $returnInvoice->items()->value('store_house_id');

        $inventoryAccountId = $costData['inventory_account_id']
            ?? $this->inventoryAccountIdFor($returnStoreHouseId);

        if ($costOfGoods <= 0) {
            Log::info('لا قيدَ تكلفةٍ للمرتجع — التكلفة صفر', [
                'return_invoice_id' => $returnInvoice->id,
            ]);

            return;
        }

        if (! $inventoryAccountId) {
            throw new \RuntimeException(
                'لا يمكن ترحيل تكلفة المرتجع: لا يوجد حساب مخزون مربوط بالمستودع ولا في الحسابات المحاسبية.'
            );
        }

        PosJournalPoster::make([
            'reference_number' => $returnInvoice->code.'-COGS',
            'date' => $returnInvoice->invoice_date ?? now(),
            'description' => 'عكس تكلفة المبيعات لمرتجع POS رقم '.$returnInvoice->code,
            'currency' => $this->documentCurrency($returnInvoice),
            'client_id' => $returnInvoice->client_id,
            'invoice_id' => $returnInvoice->id,
            'branch_id' => $session?->branch_id ?? $returnInvoice->pos_branch_id,
            'accounting_period_id' => $returnInvoice->accounting_period_id,
            'reference_type' => ReferenceType::SALES_RETURN,
            'linking_type' => LinkingType::SINGLE,
        ])
            ->debit($inventoryAccountId, $costOfGoods, 'إرجاع البضاعة إلى المخزون')
            ->credit($costAccount, $costOfGoods, 'إلغاء تكلفة المبيعات للمرتجع')
            ->post();

        Log::info('تم إنشاء قيد تكلفة المبيعات للمرتجع', [
            'return_invoice_id' => $returnInvoice->id,
            'cost_of_goods' => $costOfGoods,
        ]);
    }

    /**
     * تكلفةُ البضاعة المرتجعة، وحسابُ المخزون الذي تعود إليه.
     *
     * المنطق:
     * 1. تكلفةُ الوحدة = `unit_cost_at_sale` المنقولةُ عن البند الأصلي —
     *    فيُعكَس ما قُيّد؛ وإلّا متوسّطُ التكلفة، انظر [ResolvesProductCost].
     * 2. إذا كان للبند وحدة (`product_unit_id`):
     *    - الكمية بالوحدة الأساسية = الكمية × معامل التحويل
     *    - تكلفة السطر = الكمية بالوحدة الأساسية × تكلفة الوحدة الأساسية
     * 3. وإلّا: تكلفة السطر = الكمية المباشرة × تكلفة الوحدة.
     * 4. وحسابُ المخزون من مستودع البند (`accounts.storehouse_id`).
     */
    private function calculateReturnCostOfGoods($returnInvoice)
    {
        $totalCost = 0;
        $inventoryAccountId = null;
        $lineCosts = []; // لتتبع تفاصيل كل بند

        // جلب البنود من قاعدة البيانات (مطابق لـ ReturnInvoiceController)
        $items = InvoiceItem::where('invoice_id', $returnInvoice->id)->get();

        foreach ($items as $item) {
            $productId = $item->product_id;
            $storeHouseId = $item->store_house_id;
            $quantity = $item->quantity;

            $product = Product::find($productId);

            if ($product) {
                // البحث عن حساب المخزون من جدول الحسابات باستخدام storehouse_id (مطابق لـ ReturnInvoiceController)
                if ($storeHouseId && $inventoryAccountId === null) {
                    $itemInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                    if ($itemInventoryAccount) {
                        $inventoryAccountId = $itemInventoryAccount->id;
                    }
                }

                // ===== حساب الكمية بالوحدة الأساسية (مطابق لـ ReturnInvoiceController) =====
                $quantityInBaseUnit = $quantity;
                $conversionFactor = 1;
                $hasUnit = false;
                $unitName = '';

                if ($item->product_unit_id) {
                    $productUnit = ProductUnit::with('categoryUnit')->find($item->product_unit_id);
                    if ($productUnit && $productUnit->categoryUnit) {
                        $hasUnit = true;
                        $unitName = $productUnit->categoryUnit->name ?? '';

                        // الحصول على معامل التحويل مع التحقق من صحته
                        $conversionFactor = (float) ($productUnit->categoryUnit->conversion_factor ?? 1);
                        if ($conversionFactor <= 0) {
                            Log::warning('معامل التحويل غير صالح في تكلفة المرتجع، استخدام 1', [
                                'product_id' => $productId,
                                'product_unit_id' => $item->product_unit_id,
                                'conversion_factor' => $conversionFactor,
                            ]);
                            $conversionFactor = 1;
                        }
                        // الكمية بالوحدة الأساسية = الكمية × معامل التحويل
                        $quantityInBaseUnit = $quantity * $conversionFactor;
                    }
                }

                /*
                    ═══════════ تكلفةُ المرتجع = تكلفةُ بيعه ═══════════

                    البندُ يحمل `unit_cost_at_sale` منقولاً عن بند الفاتورة
                    الأصلية، فيُعكَس القيدُ **بما قُيّد** لا بمتوسّط اليوم.

                    ولولا ذلك لأنتج مجرّدُ الردّ ربحاً أو خسارة: بضاعةٌ
                    بِيعت وتكلفتُها ثمانية تعود بمتوسّطٍ صار عشرة، فيدخل
                    المخزونُ بعشرة وقد خرج بثمانية — وفرقُ اثنين لا مصدرَ
                    له إلا اختلافُ المرجع.

                    والمتوسّطُ احتياطٌ لفواتيرَ قديمةٍ سبقت تجميدَ التكلفة.
                */
                $purchaseUnitCost = $this->itemUnitCost($item, $product);

                if ($purchaseUnitCost <= 0) {
                    Log::warning('لا تكلفةَ للصنف المرتجَع — قُيّدت بصفر', ['product_id' => $productId]);
                }

                // ===== حساب تكلفة السطر =====
                $lineCost = $quantityInBaseUnit * $purchaseUnitCost;
                $totalCost += $lineCost;

                // سجل تفاصيل حساب التكلفة لكل بند (مطابق لـ ReturnInvoiceController)
                Log::info('حساب تكلفة المبيعات للمرتجع POS - بند', [
                    'product_id' => $productId,
                    'product_unit_id' => $item->product_unit_id,
                    'quantity_original' => $quantity,
                    'conversion_factor' => $conversionFactor,
                    'quantity_in_base_unit' => $quantityInBaseUnit,
                    'purchase_unit_cost' => $purchaseUnitCost,
                    'line_cost' => $lineCost,
                ]);

                $lineCosts[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name ?? 'غير معروف',
                    'has_unit' => $hasUnit,
                    'unit_name' => $unitName,
                    'original_quantity' => $quantity,
                    'conversion_factor' => $conversionFactor,
                    'quantity_in_base_unit' => $quantityInBaseUnit,
                    'purchase_unit_cost' => $purchaseUnitCost,
                    'line_cost' => $lineCost,
                ];
            }
        }

        // إذا لم يتم العثور على حساب مخزون من البنود، استخدم المستودع الرئيسي (مطابق لـ ReturnInvoiceController)
        if ($inventoryAccountId === null) {
            $mainStoreHouse = StoreHouse::where('major', 1)->first();
            if ($mainStoreHouse) {
                $inventoryAccountModel = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                if ($inventoryAccountModel) {
                    $inventoryAccountId = $inventoryAccountModel->id;
                    Log::info('تم استخدام حساب المخزون من جدول الحسابات للمرتجع POS', [
                        'storehouse_id' => $mainStoreHouse->id,
                        'storehouse_name' => $mainStoreHouse->name,
                        'account_id' => $inventoryAccountId,
                        'account_name' => $inventoryAccountModel->name,
                    ]);
                }
            }
        }

        Log::debug('حساب تكلفة البضاعة المرتجعة', [
            'return_invoice_id' => $returnInvoice->id,
            'total_cost' => $totalCost,
            'inventory_account_id' => $inventoryAccountId,
            'lines' => $lineCosts,
        ]);

        return [
            'total_cost' => $totalCost,
            'inventory_account_id' => $inventoryAccountId,
        ];
    }

    /**
     * إضافة معاملة الإرجاع لتفاصيل الجلسة
     */
    private function addReturnTransactionToSession($sessionId, $returnInvoice, bool $refundedCash = false)
    {
        /*
            ═══════════ النقدُ بقدر ما صُرف ═══════════

            كان `cash_amount` يُكتب بقيمة المرتجع **دائماً**، وكذلك
            `payment_method = 'cash'` — حتى حين لا يخرج ريالٌ من الدرج
            (الفاتورةُ آجلة، أو الفرعُ يمنع الردّ النقدي، أو العميل أراد
            رصيداً).

            والنقدُ المتوقَّع يُحسب من هذه الصفوف. فالدرجُ يُنقص دفترياً بما
            لم يُصرف، ويظهر عند العدّ **فائضٌ** بقيمة المرتجع — يُراجَع
            ويُحقَّق فيه ولا سبب له.
        */
        PosSessionDetail::create([
            'session_id' => $sessionId,
            'transaction_type' => 'return',
            'reference_number' => $returnInvoice->code,
            'amount' => $returnInvoice->grand_total,
            'payment_method' => $refundedCash ? 'cash' : 'credit',
            'cash_amount' => $refundedCash ? $returnInvoice->grand_total : 0,
            'card_amount' => 0,
            'description' => "استرداد - فاتورة رقم {$returnInvoice->code}",
            'metadata' => json_encode([
                'return_invoice_id' => $returnInvoice->id,
                'original_invoice_id' => $returnInvoice->reference_number,
                'items_count' => $returnInvoice->items->count(),
            ]),
            'transaction_time' => now(),
        ]);
    }

    public function printReturnInvoice($id)
    {
        try {
            $invoice = Invoice::with(['client', 'items.product'])
                ->where('id', $id)
                ->where('type', 'returned')
                ->first();

            /*
                والمرتجعُ كذلك يقرأ قالبَه المصمَّم — نوعُه `sales_return`،
                وهو النوعُ الذي يقرأ `type = 'returned'` في
                `DocumentTemplateDataService`.
            */
            if ($invoice) {
                $rendered = $this->renderPosTemplate(
                    self::TEMPLATE_RETURN,
                    $invoice->id,
                    request('template')
                );

                if ($rendered !== null) {
                    return response(DocumentPrintPage::wrap(
                        $rendered,
                        'مرتجع '.$invoice->code,
                        route('POS.sales_start.index'),
                        request()->boolean('auto_print')
                    ));
                }
            }

            if (! $invoice) {
                return redirect()->back()->with('error', 'فاتورة الإرجاع غير موجودة');
            }

            // إنشاء QR Code للفاتورة
            $renderer = new ImageRenderer(
                new RendererStyle(150),
                new SvgImageBackEnd,
            );

            $writer = new Writer($renderer);
            $qrCodeSvg = \App\Services\Zatca\ZatcaQr::svg(\App\Services\Zatca\ZatcaQr::forInvoice($invoice));

            // جلب الفاتورة الأصلية
            $originalInvoice = Invoice::find($invoice->reference_number);

            return view('pos::sales_start.return_print', compact('invoice', 'qrCodeSvg', 'originalInvoice'));
        } catch (\Exception $e) {
            Log::error('خطأ في طباعة فاتورة الإرجاع: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء طباعة فاتورة الإرجاع');
        }
    }
}
