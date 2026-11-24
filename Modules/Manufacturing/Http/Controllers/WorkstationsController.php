<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkStationRequest;
use App\Models\Log as ModelsLog;
use App\Models\WorkStations;
use App\Models\WorkStationsCost;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class WorkstationsController extends Controller
{
    use GeneratesDocumentNumbers,
        ResolvesAccountingAccounts,
        SafeListing,
        ScopesToBranch,
        UsesDocumentCurrency;

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار — نمط
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.workstations.view')->only(['index', 'getData']);
        $this->middleware('permission.role:stock.workstations.show')->only(['show']);
        $this->middleware('permission.role:stock.workstations.create')->only(['create', 'store']);
        $this->middleware('permission.role:stock.workstations.edit')->only(['edit', 'update']);
        $this->middleware('permission.role:stock.workstations.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        /*
            الجدولُ يُرسَل مع الصفحة — على قياس فواتير المبيعات — ثمّ
            يُستبدل وحده بـAjax عند البحث والتنقّل.
        */
        $workstations = $this->workstationsQuery($request)->paginate($this->safePerPage($request, 10));

        return view('manufacturing::workstations.index', [
            'workstations' => $workstations,
            // العملات المفعَّلة — شاشة «العملات»
            'currencies' => $this->activeCurrencies(),
            'selectedCurrency' => $request->has('currency') ? $request->input('currency') : null,
        ]);
    }

    public function getData(Request $request)
    {
        $workstations = $this->workstationsQuery($request)->paginate($this->safePerPage($request, 10));

        return response()->json([
            'success' => true,
            'html' => view('manufacturing::workstations.table', compact('workstations'))->render(),
            'total' => $workstations->total(),
        ]);
    }

    /**
     * استعلامُ القائمة — نطاقُ الجهة أوّلاً ثمّ البحث.
     *
     * المحطّةُ آلةٌ قائمةٌ في مصنعٍ بعينه، وتكلفتُها الساعية تدخل تكلفةَ
     * كل أمرِ تصنيعٍ يمرّ بها. فمن وقف على جهةٍ رأى محطّاتِها وما تحتها،
     * ومن وقف على الفرع الرئيسيّ رأى المنشأةَ كلَّها.
     */
    private function workstationsQuery(Request $request)
    {
        $query = $this->applyBranchFilter(WorkStations::with('createdByUser:id,name'), $request);

        /*
            العملة تُطبَّق حين تُطلب صراحةً فقط.

            `filterCurrency()` تردّ العملةَ الافتراضية حين يغيب المفتاح عن
            الطلب — والحقلُ لا يُعرض أصلاً إلّا مع أكثر من عملة. فكانت
            محطّةٌ خُتمت بعملةٍ غير الافتراضية الحالية تختفي من القائمة بلا
            فلترٍ ظاهر يفسّر غيابَها.
        */
        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // البحث
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // تكلفة الساعة من — إلى
        if ($request->filled('cost_from')) {
            $query->where('total_cost', '>=', (float) $request->cost_from);
        }

        if ($request->filled('cost_to')) {
            $query->where('total_cost', '<=', (float) $request->cost_to);
        }

        // تاريخ الإضافة
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // الترتيب من قائمةٍ بيضاء — انظر [SafeListing]
        $this->applySafeSort($query, $request, [
            'id', 'name', 'code', 'total_cost', 'cost_wages', 'created_at',
        ]);

        return $query;
    }

    /**
     * تكلفةُ المحطّة من مكوّناتها — لا من الحقل المخفي في الطلب.
     *
     * `total_cost` كان يُقبل كما يصل: حقلٌ مخفيّ يحسبه السكربت، وتعديلُه
     * من أدوات المطوّر يكتب تكلفةَ ساعةٍ مخترَعة تدخل كلَّ أمر تصنيعٍ يمرّ
     * بالمحطّة.
     *
     *   تكلفة الساعة = مجموع المصروفات + الأجور + إهلاك الساعة
     *   إهلاك الساعة = قيمة الأصل ÷ عمر الأصل بالساعات (صفر إن خلا العمر)
     *
     * كانت قيمةُ الأصل كلُّها تُجمع هنا، فتصير ساعةُ محطّةٍ فيها آلةٌ بمئة
     * ألف بمئة ألف. والصيغةُ نفسُها في [WorkStations::hourlyCostFor] وعليها
     * تُبنى الشاشة.
     */
    private function computedTotalCost(Request $request): float
    {
        $expenses = 0.0;

        foreach ((array) $request->input('cost_expenses', []) as $cost) {
            $expenses += (float) $cost;
        }

        return WorkStations::hourlyCostFor(
            $expenses,
            $request->input('cost_wages', 0),
            $request->input('cost_origin', 0),
            // «الإهلاك التلقائي» معطَّلاً ⇒ لا إهلاكَ في تكلفة الساعة
            $request->boolean('automatic_depreciation') ? $request->input('asset_life_hours', 0) : 0
        );
    }

    /**
     * حقولُ التكلفة كما تُحفظ — مشتركةٌ بين الإنشاء والتعديل.
     *
     * «الإهلاك التلقائي» مفتاحٌ يعمل: مفعَّلاً يدخل إهلاكُ الساعة (قيمة الأصل
     * ÷ عمره بالساعات) تكلفةَ ساعة المحطّة ويُقيَّد على حساب الأصل عند إتمام
     * كل أمر تصنيعٍ يمرّ بها؛ معطَّلاً تُحفظ بياناتُ الأصل للعلم ولا تدخل
     * التكلفة.
     */
    private function costAttributes(Request $request): array
    {
        $costOrigin = (float) $request->input('cost_origin', 0);

        return [
            // الوحدةُ ثابتة: كلُّ الحسابات تضرب في ساعات التشغيل
            'unit' => WorkStations::UNIT,
            'total_cost' => $this->computedTotalCost($request),
            'cost_wages' => $request->input('cost_wages'),
            'account_wages' => $request->input('account_wages'),
            'cost_origin' => $request->input('cost_origin'),
            'origin' => $request->input('origin'),
            // العمرُ بلا قيمة أصلٍ لا معنى له — لا يُحفظ
            'asset_life_hours' => $costOrigin > 0 ? $request->input('asset_life_hours') : null,
            'automatic_depreciation' => $request->boolean('automatic_depreciation') ? 1 : 0,
        ];
    }

    /**
     * صفوفُ المصروفات — الصفُّ الخالي أسقطه [WorkStationRequest] قبل الوصول.
     */
    private function saveExpenseRows(WorkStations $workstation, Request $request): void
    {
        $accounts = (array) $request->input('account_expenses', []);

        foreach ((array) $request->input('cost_expenses', []) as $index => $cost) {
            WorkStationsCost::create([
                'work_station_id' => $workstation->id,
                'cost_expenses' => $cost,
                'account_expenses' => $accounts[$index] ?? null,
            ]);
        }
    }

    /**
     * قوائمُ الحسابات للشاشتين — المصروفاتُ الطرفية للأجور والمصروفات،
     * والأصولُ الثابتة للأصل. انظر [WorkStations::expenseAccountOptions].
     *
     * عند التعديل يبقى الحسابُ المحفوظ ظاهراً ولو خرج عن الفلتر.
     */
    private function accountLists(?WorkStations $workstation = null): array
    {
        return [
            'expenseAccounts' => WorkStations::expenseAccountOptions(
                $workstation ? $workstation->savedExpenseAccountIds() : []
            ),
            'assetAccounts' => WorkStations::assetAccountOptions(
                $workstation ? [$workstation->origin] : []
            ),
        ];
    }

    public function destroy($id)
    {
        try {
            $workstation = WorkStations::findOrFail($id);
            $this->authorizeBranchScope($workstation->branch_id);

            /*
                محطّةٌ دخلت أمرَ تصنيعٍ أو قائمةَ مواد لا تُحذف: بنودُ الأمر
                والقائمة تشير إليها بـ`workstation_id`، وحذفُها يترك عملياتِ
                تشغيلٍ بلا محطّة — تكلفةٌ محسوبة لا يُعرف مصدرُها.
            */
            $ordersCount = DB::table('manufactur_orders_items')
                ->where('workstation_id', $workstation->id)
                ->distinct()
                ->count('manufactur_order_id');
            $bomsCount = DB::table('production_materials_items')
                ->where('workstation_id', $workstation->id)
                ->distinct()
                ->count('production_material_id');

            if ($ordersCount > 0 || $bomsCount > 0) {
                $uses = array_filter([
                    $ordersCount > 0 ? $ordersCount.' من أوامر التصنيع' : null,
                    $bomsCount > 0 ? $bomsCount.' من قوائم المواد' : null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف محطة العمل «'.$workstation->name.'» لأنها مستخدمة في '
                        .implode(' و', $uses).'. أزِلها من تلك المستندات أولاً ثم احذفها.',
                ], 422);
            }

            $name = $workstation->name;
            $code = $workstation->code;

            DB::transaction(function () use ($workstation) {
                $workstation->stationsCosts()->delete();
                $workstation->delete();
            });

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_WORKSTATION,
                $id,
                'تم حذف محطة العمل **'.$name.'**'.($code ? ' برقم '.$code : ''),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف محطة العمل بنجاح',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_WORKSTATION, 'destroy', $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف محطة العمل',
            ], 500);
        }
    }

    public function create()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber(ManufacturingSources::SECTION_WORKSTATIONS);
        // العملة الافتراضية من شاشة «العملات» — تُجمَّد مع المحطّة عند الحفظ
        $currencies = $this->activeCurrencies();
        $defaultCurrency = $this->defaultCurrency();

        return view('manufacturing::workstations.create', compact(
            'serial_number', 'currencies', 'defaultCurrency'
        ) + $this->accountLists());
    }

    public function store(WorkStationRequest $request)
    {
        try {
            DB::beginTransaction();

            $workStation = WorkStations::create([
                'name' => $request->input('name'),
                /*
                    الرقم من الإعدادات لا من الشاشة.

                    كان `$request->input('code')` — والحقل قابل للتحرير في
                    النموذج. فبادئةُ «الترقيم المتسلسل» وطولُها وعدّادُها
                    تحكم المعاينةَ وحدها، والتخزينُ يقبل أيّ نصّ: يُكتب
                    `WS-1` مرّتين، أو يُنتحل رقمُ محطّةٍ أخرى، أو يُقفز
                    بالتسلسل إلى حيث لا يعود منه. و`issueDocumentNumber`
                    تعمل داخل معاملةٍ بقفل وتتخطّى المحجوز فعلاً.
                    والحقلُ في الشاشة اليوم معطَّلٌ للعرض لا يُرسَل.
                */
                'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_WORKSTATIONS),
                'description' => $request->input('description'),
                'created_by' => auth()->user()->id,
                // العملة والفرع يُختمان مع المحطّة ولا يُعدَّلان بعدها
            ] + $this->costAttributes($request)
              + $this->newDocumentCurrencyAttributes()
              + $this->newDocumentBranchAttributes());

            // إضافة المصروفات
            $this->saveExpenseRows($workStation, $request);

            // تسجيل لوج إضافة محطة العمل
            OperationLog::record(
                'created',
                ManufacturingSources::LOG_WORKSTATION,
                $workStation->id,
                'تم إضافة محطة عمل **'.$workStation->name.'** برقم '.$workStation->code
                    .' بتكلفة '.$workStation->formatMoney($workStation->total_cost),
            );

            DB::commit();

            $message = 'تم إضافة محطة العمل بنجاح.';

            return redirect()
                ->route('manufacturing.workstations.index')
                ->with(['success' => $message]);

        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_WORKSTATION, 'store');
            DB::rollBack();

            // لوج الخطأ للمطورين
            Log::error('خطأ في إضافة محطة العمل: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => $this->safeError($e, 'حدث خطأ أثناء إضافة محطة العمل.')]);
        }
    }

    public function edit($id)
    {
        // `findOrFail` لا `find`: المحطّة المحذوفة أو المعرّف الخاطئ كان
        // يمرّ `null` إلى القالب، فيسقط على `$workstation->name` بـ500
        // وصفحةِ خطأٍ عارية بدل 404 المفهومة
        $workstation = WorkStations::findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `edit/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($workstation->branch_id);

        // عملةُ المحطّة كما حُفظت — تُعرض للقراءة لا للتعديل
        $documentCurrency = $this->documentCurrency($workstation);

        return view('manufacturing::workstations.edit', compact('workstation', 'documentCurrency')
            + $this->accountLists($workstation));
    }

    public function update(WorkStationRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            // `findOrFail` لا `find`: معرّفٌ خاطئ يردّ 404 المفهومة لا خطأ 500
            $workstation = WorkStations::findOrFail($id);
            $this->authorizeBranchScope($workstation->branch_id);

            // القيم القديمة — لسجلّ النشاط
            $logged = ['name', 'total_cost', 'cost_wages', 'cost_origin', 'asset_life_hours'];
            $before = $workstation->only($logged);

            $workstation->update($this->withoutCurrency([
                'name' => $request->input('name'),
                // الرقم كما صدر — ولا يُصدَر تالٍ إلّا لسجلٍّ قديمٍ بلا رقم
                'code' => $this->keepDocumentNumber($workstation->code, ManufacturingSources::SECTION_WORKSTATIONS),
                'description' => $request->input('description'),
                // «تابع لـ» — المحطّةُ بيانٌ مرجعيّ بلا قيود، فتُنقل بين الجهات
                'branch_id' => $this->editedBranchId($workstation->branch_id),
                'updated_by' => auth()->user()->id,
            ] + $this->costAttributes($request)));

            // حذف وإعادة إنشاء المصروفات
            $workstation->stationsCosts()->delete();
            $this->saveExpenseRows($workstation, $request);

            DB::commit();

            // إنشاء لوج التعديل
            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_WORKSTATION,
                $workstation->id,
                'تم تعديل محطة العمل **'.$workstation->name.'**',
                [
                    'old' => $before,
                    'new' => $workstation->only($logged),
                ],
            );

            // رسالة النجاح
            $successMessage = 'تم تعديل محطة العمل بنجاح.';

            return redirect()
                ->route('manufacturing.workstations.index')
                ->with(['success' => $successMessage]);

        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_WORKSTATION, 'update', $id);
            DB::rollBack();

            // لوج الخطأ للمطورين
            Log::error('خطأ في تعديل محطة العمل: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'workstation_id' => $id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => $this->safeError($e, 'حدث خطأ أثناء تعديل محطة العمل.')]);
        }
    }

    public function show($id)
    {
        // كما في `edit()` — انظر التعليق هناك
        $workstation = WorkStations::findOrFail($id);
        $this->authorizeBranchScope($workstation->branch_id);

        $logs = ModelsLog::where('type', ManufacturingSources::LOG_WORKSTATION)
            ->where('type_id', $id)
            ->whereHas('work_station') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('manufacturing::workstations.show', compact('workstation', 'logs'));
    }
}
