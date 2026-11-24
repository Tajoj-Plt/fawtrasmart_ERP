<?php

namespace Modules\Sales\Http\Controllers\Installments;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\TaxSitting;
use App\Services\Installments\InstallmentCollections;
use App\Services\Installments\InstallmentDownPayment;
use App\Services\Installments\InstallmentFinancing;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Sales\Http\Controllers\Concerns\RendersSalesDocumentTemplates;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Sales\Http\Requests\InstallmentRequest;
use Modules\Sales\Support\InstallmentSchedule;
use Throwable;

/**
 * اتفاقيات التقسيط وأقساطها.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان معطوباً — بترتيب الأثر
 * ══════════════════════════════════════════════════════════════════════
 *
 * **١. شاشة القائمة تسقط بـ500 بمجرّد وجود اتفاقيةٍ واحدة.**
 * `index()` ينادي `calculateInstallmentStatus()` لكلّ صفّ، وفيها:
 *
 *     $installment->invoice->installments()->sum('amount')
 *
 * و`Installment::invoice()` كانت `hasMany` لا `belongsTo`، فتُرجع
 * `Collection`. و`Collection::installments()` دالّةٌ غير موجودة ⇒
 * `BadMethodCallException`.
 *
 * **٢. إنشاء اتفاقية يسقط عند أوّل قسط.** `createInstallmentDetails()`
 * يكتب `'status' => 'unactive'` نصّاً في عمود `tinyInteger` ⇒
 * `SQLSTATE[22007] Incorrect integer value`.
 *
 * **٣. نصف المدخلات تُسقَط بصمت.** `client_id` و`start_date` و`note`
 * تُمرَّر إلى `create()` ولا أعمدةَ لها ولا `$fillable`، و`payment_rate`
 * خارج `$fillable` فيبقى على الافتراضي `1` — أي أن **كلّ اتفاقيةٍ شهريةٌ
 * مهما اختار المستخدم**. والسطر `$installment->refresh(); // ضروري حتى
 * نضمن أن payment_rate مش null` أثرُ من اصطدم بهذا وظنّ أنه عالجه: هو
 * يقرأ من القاعدة ما لم يُكتَب فيها قطّ.
 *
 * **٤. `start_date` يُقرأ فارغاً.** `Carbon::parse(null)` تعطي «الآن»،
 * فجدول الاستحقاقات كلُّه يبدأ من لحظة الحفظ لا من التاريخ المختار.
 * و`installments.due_date` يبقى NULL أبداً، فترتيبُ القائمة على فراغ
 * وفلترُ الفترة لا يطابق شيئاً.
 *
 * **٥. `orWhereHas` بلا تجميع يُبطل كلّ الفلاتر السابقة** — البند 2.8 في
 * تقرير التحسين: اختيارُ «غير مدفوع» يُظهر كلّ أقساط النظام.
 *
 * **٦. `%` على قيمٍ عشرية** — البند 2.9: `$grandTotal % $installmentAmount`
 * يحوّل معاملَيه إلى `int`، ومع 1050.75 و100.25 تصير `1050 % 100 = 50`
 * بدل 48.75. والقسمة بلا حمايةٍ من الصفر ⇒ `DivisionByZeroError`.
 *
 * **٧. حالة القسط من المصدر الخطأ** — البند 2.10: تجمع مبالغ
 * *الاتفاقيات* وتعدّها *سداداً*، فيظهر كلّ قسط «مكتمل» فور الإنشاء.
 *
 * **٨. `update()` لا يعيد بناء الجدول.** يغيّر `installment_number` على
 * الاتفاقية وتبقى صفوف `installments_details` كما كانت — فالاتفاقية تقول
 * اثني عشر قسطاً والجدول فيه ثمانية.
 *
 * **٩. `destroy()` يحذف الاتفاقية ويترك أقساطها** يتيمةً في الجدول، ولا
 * يعيد حالة الفاتورة من `installment`.
 *
 * **١٠. `show_amount()` يعرض `sales::Installments…` بحرفٍ كبير** — يعمل
 * على Windows ويعطي 404 على أيّ خادم Linux.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  السداد — مسارٌ واحد للمال
 * ══════════════════════════════════════════════════════════════════════
 *
 * هذا المتحكّم **لا يحفظ مالاً ولا يكتب قيداً**. الدفعة تُسجَّل سندَ قبضٍ
 * عبر `SalesPaymentController` (`paymentsClient.*`) على الفاتورة، وهو
 * مسار المال الوحيد في النظام؛ ثمّ يُوزَّع المسدَّد على الأقساط هنا
 * بـ`applyPayments()`.
 *
 * البديل — شاشة دفعٍ داخل الأقساط تكتب سجلّها بنفسها — يُنشئ مساراً
 * ثانياً للمال يجب أن يبقى موافقاً للأول إلى الأبد، وأولُ اختلافٍ بينهما
 * رصيدُ عميلٍ لا يُعرف أيُّهما صوابه.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  الوصلات بإعدادات النظام
 * ══════════════════════════════════════════════════════════════════════
 *
 *   الترقيم المتسلسل   القسم `installment` ببادئة `INS`
 *   العملات            `UsesDocumentCurrency` — افتراضٌ ثمّ تجميد
 *   الفترات المحاسبية  `InstallmentRequest::withValidator()`
 *   طرق الدفع          `payment_methods` الفعّالة
 *   قوالب المستندات    النوع `installment_agreement`
 *   السجلّات الثلاثة   `OperationLog`
 *   الفروع             `branch_id` و`scopeForBranch`
 *   الصلاحيات          `installments_*` مع إبقاء دور `manager`
 */
class InstallmentsController extends Controller
{
    use GeneratesDocumentNumbers;
    use RendersSalesDocumentTemplates;
    use RespectsAccountingPeriods;
    use UsesDocumentCurrency;

    private const LOG_MODULE = 'SalesInstallments';

    private const DOCUMENT_TYPE = 'installment_agreement';

    private const PAGE_SIZES = [10, 25, 50, 100];

    private const TRACKED = [
        'code', 'client_id', 'invoice_id', 'total_amount', 'down_payment',
        'profit_rate', 'admin_fees', 'tax_id', 'tax_rate', 'financed_amount',
        'installment_number', 'payment_rate', 'first_due_date', 'currency',
        'payment_method', 'status',
        'late_fee_type', 'late_fee_value', 'late_fee_grace_days',
        'late_fee_basis', 'late_fee_max',
    ];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.installments.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.installments.view')
            ->only(['index', 'show', 'schedule', 'print', 'pdf']);

        $this->middleware('permission:sales.installments.create')
            ->only(['create', 'store']);

        $this->middleware('permission:sales.installments.agreement')
            ->only(['edit', 'update', 'destroy']);

        $this->middleware('permission:sales.installments.edit_amount')
            ->only(['sync', 'cancelDetail']);
    }

    // ══════════════════════════════════════════════════════════════
    //  القائمة
    // ══════════════════════════════════════════════════════════════

    /**
     * فرعُ الشريط العلويّ — والرئيسيُّ مستثنىً.
     *
     * الفرعُ الرئيسيّ مركزُ المنشأة لا فرعٌ من فروعها، فمن يقف عليه يرى
     * الاتفاقيات كلَّها — كما في شاشة الفواتير والمرتجعات والإشعارات
     * الدائنة. وكان `forBranch()` يُطبَّق بلا استثناء في أربعة مواضع من
     * هذه الشاشة، فتُفتَح فارغةً على من يقف على الرئيسيّ.
     *
     * والدالّةُ واحدة لأن الجدولَ وبطاقاتِه يجب أن يقرآ الفرعَ نفسه:
     * رقمٌ فوق جدولٍ يخالفه لا يشرح شيئاً.
     */
    protected function applyHeaderBranch($query)
    {
        if (! $this->isMainBranch(current_branch_id())) {
            $query->forBranch(current_branch_id());
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = Installment::query()
            ->with(['client:id,trade_name,code', 'invoice:id,code,grand_total', 'details'])
            ->visibleTo();

        $this->applyHeaderBranch($query);

        $this->applyFilters($query, $request);

        $agreements = $query->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return view('sales::installments.index', [
            'agreements' => $agreements,
            'pageSizes' => self::PAGE_SIZES,
            'stats' => $this->stats(),
            'clients' => $this->clientOptions(),
            'statuses' => Installment::STATUSES,
        ]);
    }

    /**
     * الفلاتر.
     *
     * البند 2.8: `orWhereHas` كان خارج `where(function …)` فيُنتج
     * `… OR EXISTS(…)` على المستوى الأعلى، فيُلغي كلّ ما سبقه من شروط.
     * كلُّ فرعٍ هنا مُغلَّف.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';

            $query->where(function ($scope) use ($term) {
                $scope->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('trade_name', 'like', $term))
                    ->orWhereHas('invoice', fn ($i) => $i->where('code', 'like', $term));
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // النطاق على تاريخ أوّل قسط — العمود الذي يُملأ فعلاً، لا
        // `due_date` الذي بقي NULL في كلّ صفّ أنشأته الشاشة القديمة
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($from) {
            $query->whereDate('first_due_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('first_due_date', '<=', $to);
        }

        // «متعثّرة» = فيها قسطٌ فات استحقاقه ولم يكتمل. مُغلَّفةٌ في
        // `where(function …)` فلا تبتلع الفلاتر أعلاها
        if ($request->input('collection') === 'overdue') {
            $query->where(function ($scope) {
                $scope->whereHas('details', function ($d) {
                    $d->whereIn('status', [InstallmentDetail::STATUS_PENDING, InstallmentDetail::STATUS_PARTIAL])
                        ->whereDate('due_date', '<', now()->toDateString());
                });
            });
        }

        if ($request->input('collection') === 'due_soon') {
            $query->where(function ($scope) {
                $scope->whereHas('details', function ($d) {
                    $d->whereIn('status', [InstallmentDetail::STATUS_PENDING, InstallmentDetail::STATUS_PARTIAL])
                        ->whereDate('due_date', '>=', now()->toDateString())
                        ->whereDate('due_date', '<=', now()->addDays(30)->toDateString());
                });
            });
        }
    }

    /**
     * إحصاء الشاشة — على ما يراه المستخدم، خارج الفلترة.
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $empty = [
            'total' => 0, 'active' => 0, 'overdue' => 0,
            'financed' => CurrencySettings::format(0),
            'collected' => CurrencySettings::format(0),
            'outstanding' => CurrencySettings::format(0),
        ];

        try {
            $base = fn () => $this->applyHeaderBranch(Installment::query()->visibleTo());

            $currency = $this->defaultCurrency();

            $total = (clone $base())->count();
            $active = (clone $base())->where('status', Installment::STATUS_ACTIVE)->count();

            $overdue = (clone $base())
                ->whereHas('details', function ($d) {
                    $d->whereIn('status', [InstallmentDetail::STATUS_PENDING, InstallmentDetail::STATUS_PARTIAL])
                        ->whereDate('due_date', '<', now()->toDateString());
                })
                ->count();

            // بعملة النظام وحدها: جمعُ عملتين في رقمٍ واحد رقمٌ خاطئ
            $financed = (clone $base())->where('currency', $currency)->sum('financed_amount');

            $collected = (float) InstallmentDetail::query()
                ->whereIn('installments_id', (clone $base())->where('currency', $currency)->select('id'))
                ->sum('paid_amount');

            return [
                'total' => $total,
                'active' => $active,
                'overdue' => $overdue,
                'financed' => CurrencySettings::format($financed),
                'collected' => CurrencySettings::format($collected),
                'outstanding' => CurrencySettings::format(max(0, $financed - $collected)),
            ];
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'stats');

            return $empty;
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  الإنشاء
    // ══════════════════════════════════════════════════════════════

    /**
     * شاشة الإنشاء — تُفتَح على فاتورة.
     *
     * كان `create()` يقرأ `$request->query('id')` ويكتب `Log::info` بكلّ
     * فتحة، ويجلب `Client::all()`. والفاتورة لم تكن مفحوصةً: فاتورةٌ
     * مسدَّدةٌ أو ملغاة تُقسَّط كما تُقسَّط غيرها.
     */
    public function create(Request $request)
    {
        $request->validate(
            ['id' => ['required', 'integer', 'exists:invoices,id']],
            [],
            ['id' => __('sales_installments.fields.invoice')]
        );

        $invoice = Invoice::with('client')->findOrFail($request->query('id'));

        if ($existing = Installment::where('invoice_id', $invoice->id)
            ->where('status', '!=', Installment::STATUS_CANCELLED)
            ->first()) {
            // فاتورةٌ لها اتفاقيةٌ قائمة: اتفاقيتان على فاتورةٍ واحدة
            // تعنيان جدولَي سدادٍ متوازيين لدَينٍ واحد
            return redirect()->route('installments.show', $existing->id)
                ->with('error', __('sales_installments.messages.already_has_agreement'));
        }

        /* فاتورةٌ لا دَينَ فيها لا تُقسَّط — انظر `Invoice::installmentBlockReason()`.
           والفحصُ هنا **وفي `InstallmentRequest`**: الشاشةُ وحدها لا تكفي
           حارساً، فالرابط قد يُفتح من صفحةٍ محفوظة أو يُرسَل الطلبُ مباشرةً. */
        if ($blocked = $invoice->installmentBlockReason()) {
            return redirect()
                ->route('invoices.show', $invoice->id)
                ->with('error', $blocked);
        }

        $agreement = new Installment([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'total_amount' => $invoice->due_value ?: $invoice->grand_total,
            'down_payment' => 0,
            'profit_rate' => 0,
            'admin_fees' => 0,

            /* الضريبة من «إعدادات الضريبة» لا من حقلٍ يُكتب — البند ٢.
               تُقترَح المضبوطةُ في النظام وتبقى قابلةً للتغيير أو الإلغاء. */
            'tax_id' => $this->defaultTaxId(),

            'installment_number' => 1,
            'payment_rate' => 1,

            /* من **بداية المدّة التالية** لا من اليوم: أسبوعيٌّ يبدأ أوّلَ
               السبت القادم، وشهريٌّ أوّلَ الشهر القادم. وكان `addMonth()`
               مهما كانت الدوريّة — انظر `InstallmentSchedule::alignedFirstDueDate`. */
            'first_due_date' => InstallmentSchedule::alignedFirstDueDate(1)->format('Y-m-d'),

            'currency' => $invoice->currency ?: $this->defaultCurrency(),
            'status' => Installment::STATUS_ACTIVE,
        ]);

        return view('sales::installments.create', $this->formData($agreement, $invoice) + [
            'code' => $this->previewDocumentNumber(Installment::NUMBER_SECTION),
        ]);
    }

    public function store(InstallmentRequest $request)
    {
        $schedule = $request->schedule();

        try {
            $agreement = DB::transaction(function () use ($request, $schedule) {
                // `treasury_id` وجهةُ سند المقدَّمة لا عمودٌ في الاتفاقية
                $data = $request->safe()->except(['code', 'treasury_id']);

                // المبالغ من الحاسبة لا من النموذج: ما يراه المستخدم في
                // المعاينة هو ما يُحفَظ، لأنّ المصدر واحد
                $data = array_merge($data, $schedule->summary());

                $data['code'] = $this->resolveDocumentNumber(
                    $request->input('code'),
                    Installment::NUMBER_SECTION
                );

                $data['created_by'] = auth()->id();
                $data['updated_by'] = auth()->id();
                $data['branch_id'] = current_branch_id();
                $data['currency'] = $data['currency'] ?? $this->defaultCurrency();
                $data['status'] = Installment::STATUS_ACTIVE;
                $data['profit_method'] = InstallmentSchedule::PROFIT_FLAT;

                // `due_date` القديم يبقى موافقاً لأوّل قسط: تقاريرُ وشاشاتٌ
                // خارج هذا القسم قد تقرأه
                $data['due_date'] = $data['first_due_date'];
                $data['amount'] = $data['installment_amount'];

                $agreement = Installment::create($data);

                $this->writeSchedule($agreement, $schedule);
                $this->markInvoiceInstalment($agreement);

                /* الدفعة المقدَّمة **دفعةٌ أولى حقيقية**: سندُ قبضٍ على
                   الفاتورة وقيدٌ في دفتر الأستاذ — لا رقمٌ في حقلٍ يخصم
                   من الممول ولا أثرَ له في المال.

                   داخل المعاملة: اتفاقيةٌ حُفظت ومقدَّمةٌ لم تُقبَض تُظهر
                   جدولاً ناقصاً بلا ما يفسّره، فإمّا الاثنان أو لا شيء. */
                InstallmentDownPayment::post($agreement, $request->input('treasury_id'));

                /* الربحُ والرسومُ والضريبةُ على العميل في دفتر الأستاذ —
                   كانت في جدول الأقساط وحده، فيقف التحصيلُ عند إجمالي
                   الفاتورة ويبقى التمويلُ بلا طريقٍ إلى الخزينة ولا إلى
                   الإيراد. انظر `InstallmentFinancing`. */
                //
                // قيدُه **مستقلٌّ عن قيد الدفعة المقدَّمة**: القبضُ النقديّ
                // وتحميلُ التمويل على العميل حدثان مختلفان، ودمجُهما يُظهر
                // حسابَ العميل مرّتين (دائناً بالمقدَّمة ومديناً بالتمويل)
                // فلا يُقرأ القيد. المراجعة المحاسبية على INS-000005.
                InstallmentFinancing::sync($agreement->refresh());

                return $agreement;
            });
        } catch (\RuntimeException $e) {
            // حسابٌ غير مربوط أو عميلٌ بلا حساب: رسالةٌ مفهومة لا صفحة خطأ
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->withInput()->with('error', __('sales_installments.messages.save_failed'));
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $agreement,
            __('sales_installments.log.created', [
                'code' => $agreement->code,
                'count' => $agreement->installment_number,
            ]),
            ['new' => $agreement->only(self::TRACKED)]
        );

        return redirect()->route('installments.show', $agreement->id)
            ->with('success', __('sales_installments.messages.created'));
    }

    /**
     * كتابة جدول الأقساط.
     *
     * يُمحى القديم ويُبنى من جديد — لكن **الأقساط المسدَّدة لا تُمَسّ**:
     * إعادةُ بناءٍ تمحو سداداً مسجَّلاً تُفقد مالاً من السجلّ.
     */
    private function writeSchedule(Installment $agreement, InstallmentSchedule $schedule): void
    {
        $paid = $agreement->details()
            ->where('paid_amount', '>', 0)
            ->exists();

        if ($paid) {
            throw new \RuntimeException(__('sales_installments.messages.has_payments'));
        }

        $agreement->details()->delete();

        foreach ($schedule->rows() as $row) {
            InstallmentDetail::create([
                'installments_id' => $agreement->id,
                'installment_number' => $row['number'],
                'payment_rate' => $agreement->payment_rate,
                'amount' => $row['amount'],
                'principal_amount' => $row['principal_amount'],
                'profit_amount' => $row['profit_amount'],
                'fees_amount' => $row['fees_amount'],
                'tax_amount' => $row['tax_amount'],
                'due_date' => $row['due_date'],
                'paid_amount' => 0,
                'status' => InstallmentDetail::STATUS_PENDING,
            ]);
        }
    }

    /** وسمُ الفاتورة بأنّها مقسَّطة — بلا لمس مبالغها. */
    private function markInvoiceInstalment(Installment $agreement): void
    {
        try {
            // `remaining_amount` لا تُلمَس هنا: هي حصيلة سندات القبض،
            // و`createInstallmentDetails()` القديمة كانت تكتب فيها بقيّةَ
            // حلقةِ التقسيط — رقمٌ لا علاقة له بما سُدِّد فعلاً
            $agreement->invoice?->update(['status' => 'installment']);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'mark_invoice', $agreement);
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  التعديل
    // ══════════════════════════════════════════════════════════════

    public function edit($id)
    {
        $agreement = $this->findVisible($id);

        return view('sales::installments.edit', $this->formData($agreement, $agreement->invoice) + [
            'code' => $agreement->code,
            'hasPayments' => $agreement->details->where('paid_amount', '>', 0)->isNotEmpty(),
        ]);
    }

    public function update(InstallmentRequest $request, $id)
    {
        $agreement = $this->findVisible($id);
        $schedule = $request->schedule();

        /* الدفعة المقدَّمة قُبضت وقُيِّدت: تغييرُ رقمها في الاتفاقية لا
           يُعيد مالاً ولا يعكس قيداً — يُبقي المستندَ يقول غيرَ ما يقوله
           دفترُ الأستاذ. ومن أراد تغييرها فطريقُه إلغاءُ السند من شاشة
           سندات القبض ثمّ التعديل. */
        if ($posted = InstallmentDownPayment::existingFor($agreement)) {
            $requested = round((float) $request->input('down_payment', 0), 2);

            if (abs($requested - (float) $posted->amount) > 0.009) {
                return back()->withInput()->with('error', __('sales_installments.messages.down_payment_posted', [
                    'amount' => CurrencySettings::format((float) $posted->amount),
                ]));
            }
        }

        try {
            $changes = DB::transaction(function () use ($request, $agreement, $schedule) {
                $data = array_merge($request->safe()->except(['code', 'treasury_id']), $schedule->summary());

                $data['code'] = $this->resolveDocumentNumber(
                    $request->input('code') ?: $agreement->code,
                    Installment::NUMBER_SECTION
                );

                $data['updated_by'] = auth()->id();
                $data['due_date'] = $data['first_due_date'];
                $data['amount'] = $data['installment_amount'];

                $agreement->fill($data);

                $diff = OperationLog::diff($agreement, self::TRACKED);

                $agreement->save();

                // الجدول يُعاد بناؤه مع كلّ تعديل — البند 8: كان العدد
                // يتغيّر على الاتفاقية وتبقى صفوف الأقساط كما كانت
                $this->writeSchedule($agreement, $schedule);

                // قيدُ التمويل يتبع الأرقام الجديدة — بالفرق
                InstallmentFinancing::sync($agreement->refresh());

                return $diff;
            });
        } catch (\RuntimeException $e) {
            // اتفاقيةٌ بدأ سدادها: رسالةٌ مفهومة لا صفحة خطأ
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $agreement);

            return back()->withInput()->with('error', __('sales_installments.messages.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $agreement,
            __('sales_installments.log.updated', ['code' => $agreement->code]),
            $changes
        );

        return redirect()->route('installments.show', $agreement->id)
            ->with('success', __('sales_installments.messages.updated'));
    }

    public function destroy($id)
    {
        $agreement = $this->findVisible($id);

        if ($agreement->details->where('paid_amount', '>', 0)->isNotEmpty()) {
            // حذفُ اتفاقيةٍ سُدِّد منها يمحو أثر مالٍ قُبِض
            return back()->with('error', __('sales_installments.messages.has_payments'));
        }

        // والمقدَّمةُ مالٌ قُبِض كذلك — سندُها وقيدُها قائمان
        if (InstallmentDownPayment::existingFor($agreement)) {
            return back()->with('error', __('sales_installments.messages.has_down_payment'));
        }

        // وغرامةٌ رُحِّلت ذمّةٌ على العميل لا تُمحى بحذف مستند
        if ($agreement->details->where('late_fee_amount', '>', 0)->isNotEmpty()) {
            return back()->with('error', __('sales_installments.messages.has_late_fees'));
        }

        $snapshot = $agreement->only(self::TRACKED);
        $code = $agreement->code;

        try {
            DB::transaction(function () use ($agreement) {
                // قيدُ التمويل يُعكس كلُّه قبل أن يزول مستندُه
                InstallmentFinancing::sync($agreement, ['profit' => 0.0, 'fees' => 0.0, 'tax' => 0.0]);

                // الأقساط أولاً: حذفُ الاتفاقية وحدها كان يترك صفوفها
                // يتيمةً في `installments_details` بلا ما يشير إليها
                $agreement->details()->delete();

                // الفاتورة تعود من حالة «مقسَّطة»، وإلا بقيت خارج قوائم
                // المتابعة بلا اتفاقيةٍ تفسّر ذلك
                $agreement->invoice?->update(['status' => 'unpaid']);

                $agreement->delete();
            });
        } catch (\RuntimeException $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $agreement);

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $agreement);

            return back()->with('error', __('sales_installments.messages.delete_failed'));
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            __('sales_installments.log.deleted', ['code' => $code]),
            ['old' => $snapshot]
        );

        return redirect()->route('installments.index')
            ->with('success', __('sales_installments.messages.deleted'));
    }

    // ══════════════════════════════════════════════════════════════
    //  العرض والمتابعة
    // ══════════════════════════════════════════════════════════════

    public function show($id)
    {
        $agreement = $this->findVisible($id);

        // التحصيل يُقرأ من سندات القبض قبل العرض، فالشاشة تقول الحقيقة
        // ولو سُجِّلت الدفعة من شاشة السندات لا من هنا
        $this->applyPayments($agreement);

        return view('sales::installments.show', [
            'agreement' => $agreement->fresh(['details', 'client', 'invoice', 'paymentMethod', 'creator']),
            'templates' => $this->salesTemplates(self::DOCUMENT_TYPE),
            'timeline' => $this->timeline($agreement),
        ]);
    }

    /**
     * جدول الأقساط عبر الاتفاقيات كلّها — شاشة المتابعة والتحصيل.
     *
     * كانت `agreement()` تُرجع `InstallmentDetail::all()`: الجدول كلُّه
     * إلى الذاكرة، بلا ترقيم ولا فلترةٍ ولا حالةٍ فارغة، وقالبُها لا
     * يُعيد قيم الفلاتر فتُمحى مع كل بحث.
     */
    public function schedule(Request $request)
    {
        $query = InstallmentDetail::query()
            ->with(['installment.client:id,trade_name', 'installment.invoice:id,code'])
            ->whereHas('installment', fn ($a) => $this->applyHeaderBranch($a->visibleTo()));

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';

            $query->whereHas('installment', function ($a) use ($term) {
                $a->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('trade_name', 'like', $term));
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');

            $status === 'overdue'
                ? $query->overdue()
                : $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('due_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('due_date', '<=', $request->input('to_date'));
        }

        $rows = $query->orderBy('due_date')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        /* التحصيل يُقرأ من سندات القبض قبل العرض — كما تفعل `show()`.
         *
         * كانت هذه الشاشة وحدها لا تزامن: يُسجَّل السداد من زرّ «تحصيل»
         * فيها، ثمّ يعود المستخدم إليها فيجد القسط «غير مسدَّد» لأنّ
         * `paid_amount` لم يُحدَّث إلّا بفتح الاتفاقية أو بزرّ المزامنة.
         *
         * النطاق صفحةٌ واحدة لا الجدول كلّه، و`applyPayments()` لا تكتب
         * شيئاً إن لم يتغيّر المبلغ. */
        $rows->pluck('installment')
            ->filter()
            ->unique('id')
            ->each(fn (Installment $agreement) => $this->applyPayments($agreement));

        $rows->getCollection()->transform(fn (InstallmentDetail $row) => $row->refresh());

        return view('sales::installments.schedule', [
            'rows' => $rows,
            'pageSizes' => self::PAGE_SIZES,
            'statuses' => array_merge(InstallmentDetail::STATUSES, ['overdue']),
        ]);
    }

    /** توزيعُ السندات على الأقساط وتحقّقُ الربح — انظر `InstallmentCollections`. */
    private function applyPayments(Installment $agreement): void
    {
        InstallmentCollections::apply($agreement);
    }

    /** إعادة المزامنة يدوياً — زرٌّ في الشاشة لمن سجّل دفعةً للتوّ. */
    public function sync($id)
    {
        $agreement = $this->findVisible($id);

        $this->applyPayments($agreement);

        return back()->with('success', __('sales_installments.messages.synced'));
    }

    /** إلغاء قسطٍ بعينه — لا حذفه: الجدول أثرٌ لا يُمحى. */
    public function cancelDetail(Request $request, $id)
    {
        $detail = InstallmentDetail::with('installment')->findOrFail($id);

        abort_unless(
            $detail->installment && Installment::whereKey($detail->installments_id)->visibleTo()->exists(),
            403
        );

        $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        if ((float) $detail->paid_amount > 0) {
            return back()->with('error', __('sales_installments.messages.detail_has_payment'));
        }

        try {
            DB::transaction(function () use ($detail, $request) {
                $detail->update([
                    'status' => InstallmentDetail::STATUS_CANCELLED,
                    'note' => $request->input('note'),
                ]);

                // القسطُ خرج من المطالبة: يُعكس نصيبُه من التمويل
                InstallmentFinancing::sync($detail->installment->refresh());
            });
        } catch (\RuntimeException $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel_detail', $detail);

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel_detail', $detail);

            return back()->with('error', __('sales_installments.messages.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $detail->installment,
            __('sales_installments.log.detail_cancelled', [
                'number' => $detail->installment_number,
                'code' => $detail->installment->code,
            ])
        );

        return back()->with('success', __('sales_installments.messages.detail_cancelled'));
    }

    // ══════════════════════════════════════════════════════════════
    //  الطباعة
    // ══════════════════════════════════════════════════════════════

    public function print(Request $request, $id)
    {
        return $this->render($request, $id, 'sales::installments.print');
    }

    public function pdf(Request $request, $id)
    {
        return $this->render($request, $id, 'sales::installments.pdf');
    }

    private function render(Request $request, $id, string $fallbackView)
    {
        $agreement = $this->findVisible($id);

        if ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $agreement->id, $request->input('template'))) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view($fallbackView, ['agreement' => $agreement]);
    }

    // ══════════════════════════════════════════════════════════════
    //  مشترَكات
    // ══════════════════════════════════════════════════════════════

    /** @return array<string, mixed> */
    private function formData(Installment $agreement, ?Invoice $invoice): array
    {
        $taxes = $this->taxOptions();

        $taxRates = $taxes->mapWithKeys(fn ($tax) => [
            $tax->id => $this->asPercent($tax->tax),
        ])->all();

        /* اتفاقيةٌ أُنشئت قبل أن تصير الضريبة تُختار: تحمل `tax_rate`
           محفوظةً و`tax_id` فارغاً. فلو فُتحت للتعديل ظهرت القائمة على
           «بلا ضريبة»، ثمّ محا الحفظُ ضريبتَها بلا أن يقصد أحد.

           تُلتقَط هنا بمطابقة النسبة المحفوظة على المعرَّف في «إعدادات
           الضريبة» — وهي مطابقةٌ للعرض لا تُحفَظ بنفسها: الحفظُ يقع حين
           يضغط المستخدم حفظ، فيثبت الاختيارُ الذي رآه. وإن لم توجد
           ضريبةٌ بتلك النسبة ظهر التحذير في القالب. */
        $storedRate = $this->asPercent($agreement->tax_rate);

        $matchedTaxId = ! $agreement->tax_id && $storedRate > 0
            ? array_search($storedRate, $taxRates, true) ?: null
            : null;

        if ($matchedTaxId) {
            $agreement->tax_id = $matchedTaxId;
        }

        return [
            'agreement' => $agreement,
            'invoice' => $invoice,
            'clients' => $this->clientOptions(),
            'currencies' => $this->activeCurrencies(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'rates' => InstallmentSchedule::RATES,

            // «إعدادات الضريبة» — الشاشة تختار منها ولا تكتب نسبةً
            'taxes' => $taxes,

            /* المعرّف ← النسبةُ المئوية. تُبنى بالقانون نفسِه الذي يستعمله
               `InstallmentRequest::taxRate()` — الكسرُ (0.15) يصير مئويةً —
               فلا تختلف المعاينةُ عمّا يُحفَظ. */
            'taxRates' => $taxRates,

            /* نسبةٌ محفوظة لا تقابلها ضريبةٌ في الإعدادات: يُقال ذلك في
               الشاشة بدل أن تُمحى بصمت عند أوّل حفظ */
            'orphanTaxRate' => $storedRate > 0 && ! $agreement->tax_id ? $storedRate : null,

            /* خزائنُ الدفعة المقدَّمة — من شجرة الحسابات لا من جدول
               `treasuries`: عمودُ `payments_process.treasury_id` يشير إلى
               `accounts`، وهو ما تفعله شاشةُ سند القبض كذلك. */
            'treasuries' => $this->treasuryOptions(),

            /* الدوريّة ← تاريخُها المُقترَح. تُبنى في الخادم لا في
               جافاسكربت: حسابُ «أوّل السبت القادم» مرّتين بلغتين نسختان
               تتباعدان — وقد كلّف ذلك هذا القسمَ ثلاثَ حساباتٍ متعارضة
               للجدول نفسه من قبلُ. */
            'alignment' => InstallmentSchedule::alignmentMap(),

            'feeBases' => InstallmentSchedule::FEE_BASES,
        ];
    }

    /**
     * الضرائب المعرَّفة في «إعدادات الضريبة».
     *
     * @return \Illuminate\Support\Collection
     */
    private function taxOptions()
    {
        try {
            return TaxSitting::query()->orderBy('id')->get(['id', 'name', 'tax', 'type']);
        } catch (Throwable $e) {
            return collect();
        }
    }

    /**
     * الضريبة المُقترَحة لاتفاقيةٍ جديدة — أوّلُ ما ضُبط في الإعدادات.
     *
     * «تُحدَّد الضريبة من الإعدادات» تعني ألّا يكتب المستخدمُ نسبةً؛ ولو
     * تُركت الخانةُ فارغةً في كلّ اتفاقيةٍ لعاد الأمرُ إلى أن يتذكّر كلُّ
     * موظّفٍ اختيارَها. فتُقترَح المضبوطة، ويبقى «بلا ضريبة» خياراً صريحاً
     * لمن أراده.
     */
    /**
     * خزائن النقد وحسابات البنوك — من «الحسابات المحاسبية».
     *
     * @return \Illuminate\Support\Collection
     */
    private function treasuryOptions()
    {
        try {
            $parents = array_filter([
                AccountingSettings::id('cash_treasuries_parent'),
                AccountingSettings::id('bank_accounts_parent'),
            ]);

            if ($parents === []) {
                return collect();
            }

            return Account::whereIn('parent_id', $parents)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
        } catch (Throwable $e) {
            return collect();
        }
    }

    /**
     * النسبة مئويةً — يقبل الصيغتين كما يفعل `TaxCalculator::fraction()`.
     *
     * جدولُ الضرائب يحتمل `0.15` و`15` معاً، وهذا الصنف يعمل بالمئوية.
     */
    private function asPercent($rate): float
    {
        $rate = (float) $rate;

        return $rate > 0 && $rate < 1 ? round($rate * 100, 4) : round($rate, 4);
    }

    private function defaultTaxId(): ?int
    {
        try {
            return TaxSitting::query()->orderBy('id')->value('id');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function findVisible($id): Installment
    {
        return $this->applyHeaderBranch(
            Installment::query()
                ->with(['details', 'client', 'invoice', 'paymentMethod', 'creator'])
                ->visibleTo()
        )
            ->whereKey($id)
            ->firstOrFail();
    }

    private function timeline(Installment $agreement)
    {
        try {
            return \App\Models\Log::query()
                ->where('type', self::LOG_MODULE)
                ->where('type_id', $agreement->id)
                ->with('user:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->groupBy(fn ($row) => optional($row->created_at)->format('Y-m-d'));
        } catch (Throwable $e) {
            return collect();
        }
    }

    private function clientOptions()
    {
        try {
            return Client::query()->orderBy('trade_name')->get(['id', 'trade_name', 'code']);
        } catch (Throwable $e) {
            return collect();
        }
    }

    private function paymentMethodOptions()
    {
        try {
            return PaymentMethod::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']);
        } catch (Throwable $e) {
            return collect();
        }
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }
}
