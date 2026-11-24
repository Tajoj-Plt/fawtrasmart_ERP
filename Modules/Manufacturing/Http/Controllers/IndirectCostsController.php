<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndirectCostsRequest;
use App\Models\Account;
use App\Models\IndirectCost;
use App\Models\IndirectCostItem;
use App\Models\JournalEntry;
use App\Models\Log as ModelsLog;
use App\Models\ManufacturOrders;
use App\Models\Product;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Manufacturing\Services\IndirectCostAllocator;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class IndirectCostsController extends Controller
{
    use GeneratesDocumentNumbers,
        ResolvesAccountingAccounts,
        RespectsAccountingPeriods,
        SafeListing,
        ScopesToBranch,
        UsesDocumentCurrency;

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار — نمط
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
     *
     * وثلاثةُ مساراتٍ كانت محروسةً بـ`view` وحدها وهي تكتب أو تُصدِّر:
     * `getRestrictionsByDate` تقرأ قيود دفتر الأستاذ في مدًى تختاره،
     * و`export` تُخرج الجدول كلَّه. الأولى تبقى على `view` لأنها تخدم
     * شاشتَي الإنشاء والتعديل، والثانية تُنقل إلى صلاحية التصدير.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.indirect_costs.view')
            ->only(['index', 'getData', 'getStats', 'getRestrictionsByDate']);
        $this->middleware('permission.role:stock.indirect_costs.show')->only(['show']);
        $this->middleware('permission.role:stock.indirect_costs.create')->only(['create', 'store']);
        $this->middleware('permission.role:stock.indirect_costs.edit')->only(['edit', 'update']);
        $this->middleware('permission.role:stock.indirect_costs.delete')->only(['destroy']);
        $this->middleware('permission.role:stock.indirect_costs.export')->only(['export']);
    }

    public function index(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر
        // حساباتُ المصروفات وحدها — التوزيعُ لا يكون على عميلٍ ولا بنك
        $accounts = app(IndirectCostAllocator::class)->expenseAccounts();
        $products = Product::select('id', 'name')->orderBy('name')->get();
        /*
            أوامرُ التصنيع في الفلتر تُحصَر بالفرع النشط.

            قائمةٌ تعرض أوامرَ الفروع كلِّها تُسرّب أسماءها لمن لا يراها في
            شاشتها، ولا فائدة منها أصلاً: التصفيةُ بأمرٍ خارج النطاق تُرجع
            صفراً دائماً لأن التكاليف نفسَها مُصفّاة.
        */
        $manufacturing_orders = $this->scopeToActiveBranch(ManufacturOrders::query())
            ->select('id', 'name')->orderBy('name')->get();

        // العملات المفعَّلة والعملة المختارة — شاشة «العملات»
        $currencies = $this->activeCurrencies();
        $selectedCurrency = $this->filterCurrency($request);
        // فروعُ التصفية — النشط وما تحته
        $branches = $this->branchFilterOptions();
        // الفترة المحاسبية النشطة — تُشتقّ منها حدودُ التاريخ الافتراضية
        $period = $this->stockPeriod($request);

        return view('manufacturing::indirectCosts.index', compact(
            'accounts',
            'products',
            'manufacturing_orders',
            'currencies',
            'selectedCurrency',
            'branches',
            'period'
        ));
    }

    /**
     * جلب البيانات مع الفلترة عبر Ajax
     */
    public function getData(Request $request)
    {
        $query = IndirectCost::with(['account', 'indirectCostItems.manufacturingOrder', 'indirectCostItems.journalEntry']);

        /*
            نطاقُ الفرع أوّلاً.

            كانت القائمة تُبنى على الجدول كلِّه: تكاليفُ الفروع جميعاً في
            شاشةٍ واحدة، وتبديلُ الفرع في الشريط العلوي بلا أثرٍ فيها.
            والتكلفةُ غير المباشرة تُحمَّل على أوامرِ تصنيعٍ **مُفرَّعة** —
            فيظهر في مجموع الشاشة عبءُ مصنعٍ آخر ويُبنى عليه تسعيرُ منتَجٍ
            لا علاقة له به.
        */
        $query = $this->applyBranchFilter($query, $request);

        // العملة تُطبَّق حين تُطلب صراحةً — الحقلُ لا يُعرض إلّا مع أكثر من عملة
        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // فلترة حسب الحساب
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // فلترة حسب المنتج (من خلال أوامر التصنيع)
        if ($request->filled('product_id')) {
            $query->whereHas('indirectCostItems.manufacturingOrder', function ($q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }

        // فلترة حسب أمر التصنيع
        if ($request->filled('manufacturing_order_id')) {
            $query->whereHas('indirectCostItems', function ($q) use ($request) {
                $q->where('manufacturing_order_id', $request->manufacturing_order_id);
            });
        }

        // فلترة حسب تاريخ الإنشاء (from_date)
        if ($request->filled('date_from_start')) {
            $query->where('from_date', '>=', $request->date_from_start);
        }

        if ($request->filled('date_from_end')) {
            $query->where('from_date', '<=', $request->date_from_end);
        }

        // فلترة حسب تاريخ الإنتهاء (to_date)
        if ($request->filled('date_to_start')) {
            $query->where('to_date', '>=', $request->date_to_start);
        }

        if ($request->filled('date_to_end')) {
            $query->where('to_date', '<=', $request->date_to_end);
        }

        // فلترة حسب النوع
        if ($request->filled('based_on')) {
            $query->where('based_on', $request->based_on);
        }

        // فلترة حسب المجموع
        if ($request->filled('total_min')) {
            $query->where('total', '>=', $request->total_min);
        }

        if ($request->filled('total_max')) {
            $query->where('total', '<=', $request->total_max);
        }

        /*
            الترتيبُ من قائمةٍ بيضاء والاتجاهُ من قيمتين لا ثالث لهما،
            والصفحةُ بسقف — عبر [SafeListing].

            كان `orderBy($request->sort_field, $request->sort_direction)`
            يمرّر ما يصل من العنوان كما هو: `?sort_direction=x` يرمي
            InvalidArgumentException — خطأ 500 بحرفٍ واحد — و`?sort_field=xyz`
            يعطي Unknown column يُعرَض نصُّه فتُعدّ به أعمدةُ الجدول،
            و`?per_page=1000000` يحمّل الجدول كلَّه في الذاكرة.
        */
        $this->applySafeSort($query, $request, [
            'id', 'code', 'total', 'from_date', 'to_date', 'based_on', 'created_at',
        ]);

        // المجموعُ قبل الترقيم — بعده يرث `LIMIT/OFFSET` فيصفر من الصفحة الثانية
        $summary = $this->aggregateBefore((clone $query)->reorder(), 'total');

        $indirectCosts = $query->paginate($this->safePerPage($request, 10));

        // اسمُ الجهة لعمود «تابع لـ»، وعددُ القيود والأوامر كلٌّ على حدة
        $indirectCosts->getCollection()->each(function ($cost) {
            $cost->setAttribute('branch_name', \Modules\Manufacturing\Support\BranchTree::name($cost->branch_id));
            $cost->setAttribute('entries_count', $cost->indirectCostItems->whereNotNull('restriction_id')->count());
            $cost->setAttribute('orders_count', $cost->indirectCostItems->whereNotNull('manufacturing_order_id')->count());
        });

        // إرجاع البيانات كـ JSON
        return response()->json([
            'success' => true,
            'data' => $indirectCosts->items(),
            'pagination' => [
                'current_page' => $indirectCosts->currentPage(),
                'last_page' => $indirectCosts->lastPage(),
                'per_page' => $indirectCosts->perPage(),
                'total' => $indirectCosts->total(),
                'from' => $indirectCosts->firstItem(),
                'to' => $indirectCosts->lastItem(),
            ],
            'summary' => [
                'total_records' => $indirectCosts->total(),
                'total_amount' => $summary['sum'],
                'avg_amount' => $summary['avg'],
            ],
        ]);
    }

    /**
     * إحصائيات سريعة للداشبورد
     */
    public function getStats()
    {
        /*
            العدّاداتُ في نطاق الفرع النشط أيضاً.

            كانت `IndirectCost::sum('total')` على الجدول كلِّه بينما
            الجدولُ تحتها مُصفّى بالفرع — فيقرأ المستخدم بطاقةً تقول
            «الإجمالي ١٢٠ ألفاً» فوق جدولٍ مجموعُه ٣٠ ألفاً، ولا شيء
            يفسّر الفرق.

            و`$scope()` دالّةٌ لا استعلامٌ واحد: كلُّ سطرٍ يحتاج نسخته
            الخاصّة، وإعادةُ استعمال البانية نفسِها تُراكم الشروط
            (`whereMonth` الشهرِ الجاري ثمّ الماضي على النسخة نفسها
            ⇒ صفرٌ دائماً).
        */
        $scope = fn () => $this->scopeToActiveBranch(IndirectCost::query());

        $stats = [
            'total_costs' => $scope()->count(),
            'total_amount' => $scope()->sum('total'),
            'avg_amount' => $scope()->avg('total'),
            'this_month' => $scope()->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total'),
            'last_month' => $scope()->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('total'),
        ];

        return response()->json(['success' => true, 'stats' => $stats]);
    }

    /**
     * تصدير البيانات
     */
    public function export(Request $request)
    {
        // نفس منطق الفلترة من getData
        $query = IndirectCost::with(['account', 'indirectCostItems.manufacturingOrder', 'indirectCostItems.journalEntry']);

        /*
            نطاقُ الفرع على التصدير كذلك.

            التصديرُ بابٌ خلفيّ لنفس البيانات: شاشةٌ مُصفّاةٌ وتصديرٌ مفتوح
            يعني أن قيدَ الفرع يُتجاوز بزرٍّ واحد — وهو أوّلُ ما يُنسى لأن
            الشاشة تبدو صحيحة.
        */
        $query = $this->applyBranchFilter($query, $request);

        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // تطبيق نفس الفلاتر...
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        // ... باقي الفلاتر

        $indirectCosts = $query->get();

        // يمكن استخدام مكتبة مثل Laravel Excel للتصدير
        return response()->json([
            'success' => true,
            'message' => 'سيتم تصدير '.$indirectCosts->count().' سجل',
            'download_url' => route('manufacturing.indirectcosts.downloadExport', ['filters' => $request->all()]),
        ]);
    }

    public function create()
    {
        // حساباتُ المصروفات وحدها — كانت القائمة تعرض العملاء والبنوك والصناديق
        $accounts = app(IndirectCostAllocator::class)->expenseAccounts();

        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber(ManufacturingSources::SECTION_INDIRECT_COSTS);
        // العملة الافتراضية من شاشة «العملات» — قيمة ابتدائية للمستند الجديد
        $currencies = $this->activeCurrencies();
        $defaultCurrency = $this->defaultCurrency();

        return view('manufacturing::indirectCosts.create', compact(
            'accounts',
            'serial_number',
            'currencies',
            'defaultCurrency'
        ));
    }

    /**
     * قيودُ الحساب المختار في الفترة، وأوامرُ التصنيع فيها.
     *
     * الاسمُ القديم باقٍ لأن المسار مسجَّلٌ به، لكنّ ما يردّه تغيّر:
     *
     *   القيود   التي فيها حركةٌ على **الحساب المختار** وحدها، كلٌّ بصافي
     *            حركته عليه — كانت تُردّ قيودُ الفترة كلُّها من فواتير
     *            وسندات وغيرها. وما دخل توزيعاً آخر على الحساب نفسِه لا
     *            يُردّ: القيدُ يُستخدم مرّةً واحدة.
     *   الأوامر  التي تتقاطع فترتُها مع الفترة، غير الملغاة — كانت القائمةُ
     *            تعرض الأوامر كلَّها.
     */
    public function getRestrictionsByDate(Request $request)
    {
        $request->validate([
            'account_id' => 'nullable|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'except_id' => 'nullable|integer',
        ]);

        $allocator = app(IndirectCostAllocator::class);
        $branchIds = $this->activeBranchIds();

        $entries = collect();
        $lastEntryDate = null;

        if ($request->filled('account_id') && $allocator->isExpenseAccount($request->account_id)) {
            $entries = $allocator->entries(
                (int) $request->account_id,
                $request->from_date,
                $request->to_date,
                $branchIds,
                $request->filled('except_id') ? (int) $request->except_id : null
            );

            // فترةٌ خالية ⟵ يُدَلّ المستخدمُ على تاريخ آخر قيدٍ للحساب
            if ($entries->isEmpty()) {
                $lastEntryDate = $allocator->lastEntryDate((int) $request->account_id, $branchIds);
            }
        }

        return response()->json([
            'success' => true,
            'restrictions' => $entries,
            'last_entry_date' => $lastEntryDate,
            'orders' => $allocator->orders($request->from_date, $request->to_date, $branchIds),
        ]);
    }

    /**
     * التوزيعُ كما يحسبه الخادم — من الدفتر والأوامر لا من الشاشة.
     *
     * الشاشةُ تُرسل **أيَّ** القيود والأوامر اختيرت؛ والمبالغُ كلُّها تُشتقّ
     * هنا: مبلغُ القيد صافي حركته على الحساب، والإجماليُّ مجموعُها، ونصيبُ
     * كلّ أمرٍ بحسب نوع التوزيع. فلا مبلغَ يُكتب باليد، ولا قيدَ من خارج
     * الحساب أو الفترة، ولا قيدَ استُعمل في توزيعٍ آخر، ولا أمرَ ملغى أو
     * خارج الفترة.
     *
     * @return array{entries: \Illuminate\Support\Collection, orders: \Illuminate\Support\Collection, total: float, base: float, rate: float, shares: array<int, float>}
     *
     * @throws ValidationException
     */
    protected function buildAllocation(Request $request, ?int $exceptId = null): array
    {
        $allocator = app(IndirectCostAllocator::class);

        if (! $allocator->isExpenseAccount($request->account_id)) {
            throw ValidationException::withMessages([
                'account_id' => 'الحساب: اختر حساباً من حسابات المصروفات.',
            ]);
        }

        $branchIds = $this->activeBranchIds();

        $available = $allocator
            ->entries((int) $request->account_id, $request->from_date, $request->to_date, $branchIds, $exceptId)
            ->keyBy('id');

        $entries = collect();

        foreach (array_unique(array_map('intval', (array) $request->input('restriction_id', []))) as $entryId) {
            if (! $available->has($entryId)) {
                throw ValidationException::withMessages([
                    'restriction_id' => 'القيود: القيد رقم '.$entryId
                        .' ليس من قيود هذا الحساب في الفترة، أو سبق استعماله في توزيعٍ آخر.',
                ]);
            }

            $entries->push($available->get($entryId));
        }

        $periodOrders = $allocator->orders($request->from_date, $request->to_date, $branchIds)->keyBy('id');
        $orders = collect();

        foreach (array_unique(array_filter(array_map('intval', (array) $request->input('manufacturing_order_id', [])))) as $orderId) {
            if (! $periodOrders->has($orderId)) {
                throw ValidationException::withMessages([
                    'manufacturing_order_id' => 'أوامر التصنيع: الأمر رقم '.$orderId
                        .' خارج فترة التوزيع أو ملغى.',
                ]);
            }

            $orders->push($periodOrders->get($orderId));
        }

        $total = round((float) $entries->sum('amount'), 2);
        $distribution = $allocator->distribute($total, $orders, (int) $request->based_on);

        /*
            اختيارُ الأوامر اختياريّ.

            بلا أوامر مختارة يُحفظ المستندُ بقيوده وإجماليّه، ويبقى نصيبُ
            الوحدة صفراً في الرأس — فيُحسب وقتَ اختيار التكلفة في أمر
            التصنيع على أوامر الفترة القائمة حينها
            ([IndirectCostAllocator::effectiveRate]). الحفظُ لا يتوقّف على
            وجود أمر.
        */
        if ($orders->isNotEmpty() && $distribution['base'] <= 0) {
            throw ValidationException::withMessages([
                'manufacturing_order_id' => (int) $request->based_on === IndirectCostAllocator::BY_COST
                    ? 'أوامر التصنيع: تكلفة الأوامر المختارة صفر — لا أساس للتوزيع بحسب التكلفة. اختر التوزيع بحسب الكمية.'
                    : 'أوامر التصنيع: كمية الأوامر المختارة صفر — لا أساس للتوزيع.',
            ]);
        }

        return [
            'entries' => $entries,
            'orders' => $orders,
            'total' => $total,
            'base' => $distribution['base'],
            'rate' => $distribution['rate'],
            'shares' => $distribution['shares'],
        ];
    }

    /**
     * بنودُ التوزيع — سطرٌ لكل قيدٍ وسطرٌ لكل أمر.
     *
     * كان القيدُ والأمرُ يُحفظان **في سطرٍ واحد** بترتيب ظهورهما، والحلقةُ
     * تدور على القيود وحدها: فمن وزّع قيداً واحداً على ثلاثة أوامر حُفظ
     * له الأمرُ الأوّل وضاع الباقيان بلا تنبيه.
     */
    protected function syncAllocationItems(IndirectCost $indirectCost, array $allocation): void
    {
        $indirectCost->indirectCostItems()->delete();

        foreach ($allocation['entries'] as $entry) {
            IndirectCostItem::create([
                'indirect_costs_id' => $indirectCost->id,
                'restriction_id' => $entry['id'],
                'restriction_total' => $entry['amount'],
                'manufacturing_order_id' => null,
                'manufacturing_price' => 0,
            ]);
        }

        foreach ($allocation['orders'] as $order) {
            IndirectCostItem::create([
                'indirect_costs_id' => $indirectCost->id,
                'restriction_id' => null,
                'restriction_total' => 0,
                'manufacturing_order_id' => $order['id'],
                'manufacturing_price' => $allocation['shares'][$order['id']] ?? 0,
            ]);
        }
    }

    public function store(IndirectCostsRequest $request)
    {
        /*
            الفترةُ المحاسبية تُفحَص قبل الحفظ لا بعده.

            التكلفةُ غير المباشرة تُحمَّل على أوامر تصنيعٍ تُرحَّل قيودُها
            إلى الدفتر. فحفظُها في فترةٍ أقفلها المحاسب يعني عبئاً يدخل
            شهراً أُغلقت حساباتُه وصدرت قوائمُه.
        */
        if ($block = $this->accountingPeriodBlock($request->from_date)) {
            return back()->withInput()->with(['error' => $block]);
        }

        $allocation = $this->buildAllocation($request);

        DB::beginTransaction();
        try {
            $indirectCost = IndirectCost::create([
                'account_id' => $request->account_id,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'based_on' => $request->based_on,
                // الإجماليُّ ونصيبُ الوحدة من الخادم — لا من الطلب
                'total' => $allocation['total'],
                'allocation_base' => $allocation['base'],
                'unit_rate' => $allocation['rate'],
                // الرقم من إعدادات «الترقيم المتسلسل» تحت قفل — لا من الطلب
                'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_INDIRECT_COSTS),
                // العملة الافتراضية من شاشة «العملات»، ثمّ تُجمَّد مع المستند
            ] + $this->newDocumentCurrencyAttributes() + $this->newDocumentBranchAttributes());

            $this->syncAllocationItems($indirectCost, $allocation);

            OperationLog::record(
                'created',
                ManufacturingSources::LOG_INDIRECT_COST,
                $indirectCost->id,
                'تم إضافة تكاليف غير مباشرة **'.$indirectCost->code.'** بمبلغ '
                    .$indirectCost->formatMoney($indirectCost->total),
            );

            DB::commit();

            return redirect()->route('manufacturing.indirectcosts.show', $indirectCost->id)
                ->with(['success' => 'تم حفظ التكلفة غير المباشرة. اخترها في أمر التصنيع ليُحسب نصيبُه منها ويدخل تكلفتَه.']);
        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_INDIRECT_COST, 'store', $indirectCost ?? null);
            DB::rollBack();

            /*
                نصُّ الاستثناء لا يُعرَض للمستخدم — أسماءُ الأعمدة والجداول
                ومسارات الملفات تخرج في شريط الخطأ. والنصُّ الكامل محفوظٌ
                في «سجلّ الأخطاء» أعلاه لمن يملك شاشته.
            */
            return back()->withInput()->with(['error' => $this->safeError($e, 'حدث خطأ أثناء الحفظ.')]);
        }
    }

    public function edit($id)
    {
        $indirectCost = IndirectCost::with('indirectCostItems')->findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `edit/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($indirectCost->branch_id);

        $accounts = app(IndirectCostAllocator::class)->expenseAccounts();

        // حسابٌ قديم حُفظ قبل حصر القائمة بالمصروفات — يبقى ظاهراً كي لا يُفرَّغ الحقل
        if ($indirectCost->account_id && ! $accounts->contains('id', $indirectCost->account_id)) {
            $legacy = Account::select('id', 'name', 'code')->find($indirectCost->account_id);

            if ($legacy) {
                $accounts->prepend($legacy);
            }
        }

        // العملة تُعرض للقراءة — مجمَّدة مع المستند منذ حفظه
        $documentCurrency = $this->documentCurrency($indirectCost);

        return view('manufacturing::indirectCosts.edit', compact(
            'indirectCost',
            'accounts',
            'documentCurrency'
        ));
    }

    public function update(IndirectCostsRequest $request, $id)
    {
        $indirectCost = IndirectCost::findOrFail($id);
        $this->authorizeBranchScope($indirectCost->branch_id);

        /*
            الفترتان معاً: التي كان فيها المستند، والتي يُنقل إليها.

            فحصُ الجديدة وحدها يترك بابَ الإخراج مفتوحاً — يُنقل عبءٌ من
            فترةٍ مقفلة إلى فترةٍ مفتوحة فيتغيّر مجموعُ شهرٍ صدرت قوائمه،
            والفحصُ يمرّ لأن الوجهة مفتوحة.
        */
        foreach ([$indirectCost->from_date, $request->from_date] as $date) {
            if ($block = $this->accountingPeriodBlock($date)) {
                return back()->withInput()->with(['error' => $block]);
            }
        }

        $allocation = $this->buildAllocation($request, (int) $indirectCost->id);

        DB::beginTransaction();
        try {
            $before = $indirectCost->only(['account_id', 'from_date', 'to_date', 'based_on', 'total', 'unit_rate']);

            /*
                `withoutCurrency` و`withoutBranch`: عملةُ المستند وفرعُه
                لا يُعدَّلان بعد الحفظ. الأولى يحرسها
                [\App\Models\Concerns\HasDocumentCurrency] في الموديل
                أيضاً — حارسان لا واحد — والثاني لأن نقل المستند بين
                الفروع ينقل معه أثرَه المحاسبي، وهو لا يُنقل.
            */
            $indirectCost->update($this->withoutBranch($this->withoutCurrency([
                'account_id' => $request->account_id,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'based_on' => $request->based_on,
                'total' => $allocation['total'],
                'allocation_base' => $allocation['base'],
                'unit_rate' => $allocation['rate'],
                // الرقم كما صدر — ولا يُصدَر تالٍ إلّا لسجلٍّ قديمٍ بلا رقم
                'code' => $this->keepDocumentNumber($indirectCost->code, ManufacturingSources::SECTION_INDIRECT_COSTS),
            ])));

            $this->syncAllocationItems($indirectCost, $allocation);

            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_INDIRECT_COST,
                $indirectCost->id,
                'تم تحديث تكاليف غير مباشرة **'.$indirectCost->code.'**',
                [
                    'old' => $before,
                    'new' => $indirectCost->only(['account_id', 'from_date', 'to_date', 'based_on', 'total', 'unit_rate']),
                ],
            );

            DB::commit();

            return redirect()->route('manufacturing.indirectcosts.show', $indirectCost->id)
                ->with(['success' => 'تم التحديث بنجاح.']);
        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_INDIRECT_COST, 'update', $indirectCost);
            DB::rollBack();

            // كما في `store()` — نصُّ الاستثناء في سجلّ الأخطاء لا في الشاشة
            return back()->withInput()->with(['error' => $this->safeError($e, 'حدث خطأ أثناء التحديث.')]);
        }
    }

    public function show($id)
    {
        $indirectCost = IndirectCost::with(['indirectCostItems.journalEntry', 'indirectCostItems.manufacturingOrder'])
            ->findOrFail($id);
        $this->authorizeBranchScope($indirectCost->branch_id);

        $logs = ModelsLog::where('type', ManufacturingSources::LOG_INDIRECT_COST)
            ->where('type_id', $id)
            ->whereHas('indirect_cost') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        // ما حُمِّل فعلاً على أوامر التصنيع من هذه التكلفة
        $appliedTotal = app(IndirectCostAllocator::class)->appliedTotal((int) $indirectCost->id);

        return view('manufacturing::indirectCosts.show', compact('indirectCost', 'logs', 'appliedTotal'));
    }

    public function destroy($id)
    {
        $indirectCost = IndirectCost::findOrFail($id);
        $this->authorizeBranchScope($indirectCost->branch_id);

        // حذفُ عبءٍ من فترةٍ مقفلة يغيّر مجموعَها كما تغيّره إضافتُه
        if ($block = $this->accountingPeriodBlock($indirectCost->from_date)) {
            return redirect()->route('manufacturing.indirectcosts.index')->with(['error' => $block]);
        }

        // تكلفةٌ اختيرت في أمر تصنيعٍ لا تُحذف من تحته — بندُه يشير إليها
        $usedInOrders = DB::table('manufactur_orders_items')->where('indirect_cost_id', $indirectCost->id)->exists();

        if ($usedInOrders) {
            return redirect()->route('manufacturing.indirectcosts.index')
                ->with(['error' => 'لا يمكن حذف هذه التكلفة — مختارةٌ في أمر تصنيعٍ واحد على الأقل. احذف بندها من الأمر أولاً.']);
        }

        // ما يعرّف المحذوف يُقرأ قبل حذفه — بعده لا يبقى إلّا سطر السجلّ
        $code = $indirectCost->code;
        $amount = $indirectCost->formatMoney($indirectCost->total);

        /*
            الحذفُ في معاملةٍ واحدة.

            كان بندَين متتاليَين بلا `transaction` ولا `catch`: فشلُ حذف
            المستند بعد نجاح حذف بنوده يترك رأساً بلا بنود — مبلغاً
            إجمالياً لا تفصيلَ له ولا أمرَ تصنيعٍ يحمله.
        */
        DB::beginTransaction();
        try {
            $indirectCost->indirectCostItems()->delete();
            $indirectCost->delete();

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_INDIRECT_COST,
                $id,
                'تم حذف تكاليف غير مباشرة **'.$code.'** بمبلغ '.$amount,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_INDIRECT_COST, 'destroy', $id);

            return redirect()->route('manufacturing.indirectcosts.index')
                ->with(['error' => $this->safeError($e, 'تعذّر حذف التكاليف غير المباشرة.')]);
        }

        return redirect()->route('manufacturing.indirectcosts.index')
            ->with(['success' => 'تم حذف التكاليف بنجاح.']);
    }
}
