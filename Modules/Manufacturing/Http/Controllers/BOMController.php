<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionMaterialRequest;
use App\Models\Account;
use App\Models\Log as ModelsLog;
use App\Models\Product;
use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use App\Models\ProductionPath;
use App\Models\ProductionStage;
use App\Models\WarehousePermits;
use App\Models\WorkStations;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\BuildsBomItems;
use Modules\Manufacturing\Http\Controllers\Concerns\ResolvesExpenseAccounts;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Manufacturing\Support\BranchTree;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class BOMController extends Controller
{
    use BuildsBomItems,
        GeneratesDocumentNumbers,
        ResolvesAccountingAccounts,
        ResolvesExpenseAccounts,
        RespectsAccountingPeriods,
        SafeListing,
        ScopesToBranch,
        UsesDocumentCurrency;

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار — نمط
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
     *
     * و`get_cost_total` كانت **بلا حارسٍ من أيّ نوع**: لا في المسارات ولا
     * هنا. وهي تُرجع `total_cost` لأيّ محطّة عمل بمعرّفها — أجرُ الآلة
     * الساعي، وهو مكوّنُ تسعيرٍ لا يُعرَض لمن لا يملك شاشة المحطّات.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.bom.view')->only(['index', 'getData', 'get_cost_total']);
        $this->middleware('permission.role:stock.bom.show')->only(['show']);
        $this->middleware('permission.role:stock.bom.create')->only(['create', 'store']);
        $this->middleware('permission.role:stock.bom.edit')->only(['edit', 'update']);
        $this->middleware('permission.role:stock.bom.delete')->only(['destroy']);
    }

    public function index()
    {
        // جلب البيانات للفلاتر
        $products = Product::select('id', 'name')->get();
        // قوائمُ الفرع النشط وحدها في فلتر «عملية الإنتاج»
        $operations = $this->scopeToActiveBranch(ProductionMaterials::query())
            ->select('id', 'name')->get();

        return view('manufacturing::bom.index', [
            'products' => $products,
            'operations' => $operations,
            // فروعُ التصفية والعملات — شاشتا «الفروع» و«العملات»
            'branches' => $this->branchFilterOptions(),
            'currencies' => $this->activeCurrencies(),
            'defaultCurrency' => $this->defaultCurrency(),
        ]);
    }

    public function getData(Request $request)
    {
        /*
            وصفاتُ الفرع النشط وحدها.

            قائمةُ المواد هي تركيبةُ المنتج ونسبُ موادّه وتكلفتُه المعيارية —
            أخصُّ ما تملكه منشأةٌ صناعية. وكانت `ProductionMaterials::query()`
            عاريةً: وصفاتُ الفروع كلِّها في شاشةٍ واحدة، وتبديلُ الفرع بلا
            أثرٍ فيها.
        */
        $query = $this->applyBranchFilter(ProductionMaterials::query(), $request);

        // العملة تُطبَّق حين تُطلب صراحةً — الحقلُ لا يُعرض إلّا مع أكثر من
        // عملة، والافتراضُ الصامت كان يُخفي قوائمَ خُتمت بعملةٍ أخرى
        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // فلترة البحث بالاسم أو الكود
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // فلترة حسب المنتج
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب عملية الإنتاج
        if ($request->filled('operation_id')) {
            $query->where('operation_id', $request->operation_id);
        }

        // الترتيب من قائمةٍ بيضاء والصفحة بسقف — انظر [SafeListing]
        $this->applySafeSort($query, $request, [
            'id', 'name', 'code', 'quantity', 'last_total_cost', 'status', 'created_at',
        ]);

        $materials = $query->paginate($this->safePerPage($request, 10));

        // اسمُ الجهة لعمود «تابع لـ»
        $materials->getCollection()->each(function ($material) {
            $material->setAttribute('branch_name', BranchTree::name($material->branch_id));
        });

        // إرجاع البيانات كـ JSON
        return response()->json([
            'success' => true,
            'data' => $materials->items(),
            'pagination' => [
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
            ],
        ]);
    }

    public function create()
    {
        return view('manufacturing::bom.create', $this->bomFormData() + [
            // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
            'serial_number' => $this->previewDocumentNumber(ManufacturingSources::SECTION_BOM),
            // العملة الافتراضية من شاشة «العملات» — تُجمَّد مع الوصفة عند الحفظ
            'currencyCode' => $this->defaultCurrency(),
        ]);
    }

    public function store(ProductionMaterialRequest $request)
    {
        // الفترة المحاسبية: إنشاء قائمة المواد يُرحِّل قيد صرف المواد الخام
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        /*
            فحصُ حسابات المصروف **قبل** المعاملة وخارج `try`.

            كان داخلها، و`catch (\Exception)` يلتقط `ValidationException`
            معها: فيُعاد المستخدم إلى القائمة برسالةٍ عامّة «تعذّر الحفظ»
            وقد ضاع كلُّ ما أدخله، بدل أن يعود إلى النموذج والخطأُ على
            صفّه.
        */
        $this->assertBomExpenseAccounts($request);
        $this->assertHeaderAccount($request);

        DB::beginTransaction();

        try {
            /*
                «الوصفة الافتراضية» واحدةٌ **لكل فرع** لا لكل النظام.

                كان الشرط `where('default', 1)` على الجدول كلِّه: فتعيينُ
                وصفةٍ افتراضيةً في فرعٍ يُلغي افتراضيةَ فرعٍ آخر بصمت —
                يقف مصنعٌ بلا وصفةٍ افتراضية لأن مصنعاً في مدينةٍ أخرى
                عيّن وصفته. والقيدُ هنا هو نطاقُ العرض نفسُه.
            */
            if ($request->default == 1) {
                $this->scopeToActiveBranch(ProductionMaterials::where('default', 1))
                    ->update(['default' => 0]);
            }

            $productionMaterial = ProductionMaterials::create([
                'name' => $request->name,
                // الرقم من إعدادات الترقيم لا من الحقل — راجع التعليق في
                // ManufacturingOrdersController@store
                'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_BOM),
                'product_id' => $request->product_id,
                'account_id' => $request->account_id,
                'production_path_id' => $request->production_path_id,
                'quantity' => $request->quantity,
                // يُكتب بعد بناء البنود — من مجموعها لا من الحقل المخفي
                'last_total_cost' => 0,
                'status' => $request->status ?? 0,
                'default' => $request->default ?? 0,
                'created_by' => auth()->id(),
                // العملة والفرع يُختمان مع الوصفة ولا يُعدَّلان بعدها
            ] + $this->newDocumentCurrencyAttributes() + $this->newDocumentBranchAttributes());

            /*
                الإجماليُّ يحسبه الخادم من البنود — انظر [BuildsBomItems].

                كان `$request->last_total_cost`: حقلٌ مخفيّ تملؤه الشاشة،
                فأيُّ رقمٍ يُكتب فيه من أدوات المطوّر يصير «التكلفة
                المعيارية» للمنتج.
            */
            $productionMaterial->update([
                'last_total_cost' => $this->syncBomItems($productionMaterial, $request),
            ]);

            /*
                `created` لا `logged`.

                `logged` ليست عمليةً — هي كلمةٌ واحدة كانت تُكتب للإنشاء
                والتعديل والحذف معاً، فتعرض شاشةُ «سجلّ النشاط» ثلاثة أسطرٍ
                متطابقةِ الأيقونة واللون لا يميّزها إلّا نصُّ الوصف،
                والتصفيةُ بالعملية عديمةُ الجدوى. ومنها يشتقّ `OperationLog`
                مفتاحَ الحدث في «سجلّ الأحداث» — فيصير الجميع حدثاً واحداً.
            */
            OperationLog::record(
                'created',
                ManufacturingSources::LOG_BOM,
                $productionMaterial->id,
                'تم إنشاء قائمة مواد الإنتاج **'.$productionMaterial->name.'** برقم '.$productionMaterial->code,
            );

            /*
                لا إذن مخزني ولا قيد محاسبي عند حفظ قائمة المواد.

                كان هنا `createWarehousePermit()` ثم `createIssuanceJournalEntry()`
                — فمجرّد حفظ الوصفة يخصم كمياتها من `StoreHouse::first()` ويُرحّل
                قيد «مخزون تحت التشغيل مدين / حساب المستودع دائن».

                وقائمة المواد **تعريفٌ لا مستند**: وصفةٌ تُكتب مرّة وتُستعمل في
                كل أمر تصنيع. لا يصحّ أن يمسّ حفظُها مخزوناً ولا دفتراً — وإلا
                خصمت المنشأةُ موادَّها كلّما وصفت منتجاً.

                الصرف الحقيقي موضعه دورة أمر التصنيع: `receiveMaterials` في
                `ManufacturingOrderLifecycle`، وهي المسار الوحيد الذي يمسّ
                المخزون في هذا المديول.
            */

            DB::commit();

            return redirect()->route('BOM.index')->with(['success' => 'تم حفظ البيانات بنجاح.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_BOM, 'store');

            /*
                نصُّ الاستثناء في «سجلّ الأخطاء» لا في شريط الشاشة: أسماءُ
                الأعمدة والجداول ومسارات الملفات كانت تُعرض كما هي.
                و`safeError` تُبقيه ظاهراً في بيئة التطوير وحدها.

                والعودةُ إلى النموذج نفسِه بما أُدخل فيه — كانت إلى القائمة،
                فيضيع كلُّ ما كُتب مع أوّل خطأ.
            */
            return redirect()->back()->withInput()
                ->with(['error' => $this->safeError($e, 'تعذّر حفظ قائمة المواد.')]);
        }
    }

    public function edit($id)
    {
        // `findOrFail` كما في `show()` أدناه: `find` كانت تمرّر `null`
        // إلى القالب فيسقط على `$productionMaterial->name` بـ500 بدل 404
        $productionMaterial = ProductionMaterials::findOrFail($id);
        $this->authorizeBranchScope($productionMaterial->branch_id);

        return view('manufacturing::bom.edit', $this->bomFormData($productionMaterial) + [
            'productionMaterial' => $productionMaterial,
            // عملةُ الوصفة كما حُفظت — تُعرض للقراءة لا للتعديل
            'currencyCode' => $this->documentCurrency($productionMaterial),
        ]);
    }

    public function update(ProductionMaterialRequest $request, $id)
    {
        // الفترة المحاسبية: تعديل قائمة المواد يُعيد ترحيل قيد الصرف
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $productionMaterial = ProductionMaterials::findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `update/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($productionMaterial->branch_id);

        // قبل المعاملة وخارج `try` — انظر التعليق في `store()`
        $this->assertBomExpenseAccounts($request, $productionMaterial);
        $this->assertHeaderAccount($request, $productionMaterial);

        DB::beginTransaction();

        try {
            // «الافتراضية» واحدةٌ لكل فرع — انظر التعليق في `store()`
            if ($request->default == 1) {
                $this->scopeToActiveBranch(
                    ProductionMaterials::where('default', 1)->where('id', '!=', $id)
                )->update(['default' => 0]);
            }

            $before = $productionMaterial->only(['name', 'product_id', 'quantity', 'status', 'default', 'last_total_cost']);

            // العملة والفرع مجمَّدان بعد الحفظ — انظر [ScopesToBranch] و[UsesDocumentCurrency]
            $productionMaterial->update($this->withoutCurrency([
                'name' => $request->name,
                // التعديل لا يُعيد الترقيم — الرقم مرجعٌ خارجي
                'code' => $this->keepDocumentNumber($productionMaterial->code, ManufacturingSources::SECTION_BOM),
                'product_id' => $request->product_id,
                'account_id' => $request->account_id,
                'production_path_id' => $request->production_path_id,
                'quantity' => $request->quantity,
                'status' => $request->status ?? 0,
                'default' => $request->default ?? 0,
                'updated_by' => auth()->id(),
            ]) + ['branch_id' => $this->editedBranchId($productionMaterial->branch_id)]);

            // الإجماليُّ من البنود لا من الحقل المخفي — انظر `store()`
            $productionMaterial->update([
                'last_total_cost' => $this->syncBomItems($productionMaterial, $request),
            ]);

            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_BOM,
                $productionMaterial->id,
                'تم تحديث قائمة المواد **'.$productionMaterial->name.'** برقم '.$productionMaterial->code,
                [
                    'old' => $before,
                    'new' => $productionMaterial->only(['name', 'product_id', 'quantity', 'status', 'default', 'last_total_cost']),
                ],
            );

            /*
                لا مساس بالمخزون ولا بالدفتر عند تعديل قائمة المواد.

                كانت هنا كتلةٌ تحذف أذونات الوصفة وقيودها **حذفاً صلباً** ثم
                تُعيد الصرف من جديد. وفيها ثلاثة عيوب مجتمعة:

                  · الحذف بلا عكس — الكمية التي خُصمت عند الحفظ الأول لا تعود
                    إلى المخزون، فكلّ تعديل يخصم من جديد بلا حدّ.
                  · `where('type_id', $id)->where('type', …)` — عمودان لا
                    يكتبهما شيء في هذا المسار، فالسطران لا يحذفان شيئاً أصلاً.
                  · `permission_source_id = 17` بلا `reference_type` — والمصدر
                    نفسه تستعمله أذونُ صرف أوامر التصنيع، فوصفةٌ رقمها 5 كانت
                    تحذف إذن أمر تصنيع رقمه 5.

                والوصفة تعريفٌ لا مستند — تعديلها يغيّر ما سيُصرف مستقبلاً، ولا
                يعكس صرفاً وقع. راجع `store()` أعلاه.
            */

            DB::commit();

            return redirect()->route('BOM.index')->with(['success' => 'تم تحديث البيانات بنجاح.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_BOM, 'update', $id);

            // إلى النموذج بما أُدخل فيه، والنصُّ الخام في «سجلّ الأخطاء» — انظر `store()`
            return redirect()->back()->withInput()
                ->with(['error' => $this->safeError($e, 'تعذّر حفظ قائمة المواد.')]);
        }
    }

    public function show($id)
    {
        // استرجاع البيانات الحالية
        $productionMaterial = ProductionMaterials::findOrFail($id);
        $this->authorizeBranchScope($productionMaterial->branch_id);

        $productionMaterialItems = ProductionMaterialsItem::where('production_material_id', $id)
            ->with([
                'rawProduct', 'rawUnit.categoryUnit.unit', 'rawProductionStage',
                'expensesAccount', 'expensesProductionStage',
                'workStation', 'workshopProductionStage',
                'endLifeProduct', 'endLifeProductionStage',
            ])
            ->orderBy('id')
            ->get();

        // استرجاع البيانات الأخرى المطلوبة للنموذج
        $products = Product::all();
        $accounts = $this->manufacturingExpenseAccounts();
        $paths = $this->scopeToActiveBranch(ProductionPath::query())->get();
        $stages = ProductionStage::all();
        $workstations = $this->scopeToActiveBranch(WorkStations::query())->get();
        $logs = ModelsLog::where('type', ManufacturingSources::LOG_BOM)
            ->where('type_id', $id)
            ->whereHas('production_material') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('manufacturing::bom.show', compact(
            'productionMaterial',
            'productionMaterialItems',
            'products',
            'accounts',
            'paths',
            'stages',
            'logs',
            'workstations'
        ));
    }

    public function destroy($id)
    {
        $productionMaterial = ProductionMaterials::findOrFail($id);
        $this->authorizeBranchScope($productionMaterial->branch_id);

        // الفترة المحاسبية: الحذف يمسّ قيوداً مُرحَّلة، فيُفحص كما يُفحص الترحيل
        if ($block = $this->accountingPeriodBlock(now())) {
            return $this->bomDeleteResponse(false, $block);
        }

        $name = $productionMaterial->name;
        $code = $productionMaterial->code;

        /*
            القائمةُ المستعملة لا تُحذف.

            كان الحذفُ يمرّ وأوامرُ التصنيع وبنودُ خطط الإنتاج تشير إليها:
            يبقى الأمرُ بمعرّف قائمةٍ لا وجود لها — شاشتُه تعرض «قائمة
            المواد» فارغةً، وتعديلُه يُرفض لأن الحقل إلزامي، وتقريرُ
            التكلفة المعيارية يفقد ما يقارن به. ولا رجعةَ بعد الحذف.

            والبديلُ معروضٌ في الرسالة: التعطيل يُخرجها من قوائم الاختيار
            ويُبقي ما بُني عليها سليماً.
        */
        if ($usage = $this->bomUsage($productionMaterial->id)) {
            return $this->bomDeleteResponse(false, sprintf(
                'لا يمكن حذف قائمة المواد «%s» لأنها مستعملة في: %s. '
                .'عطّلها (اجعلها غير نشطة) بدل حذفها، أو احذف ما يعتمد عليها أولاً.',
                $name,
                implode('، ', $usage)
            ));
        }

        /*
            الحذفُ في معاملةٍ واحدة، ومسجَّل.

            كانت أربعَ عمليات حذفٍ متتالية بلا `transaction` ولا `catch`
            ولا سطرٍ في أيّ سجلّ: تُحذف القيودُ والأذونُ والبنود، وإن سقطت
            الرابعة بقي الرأسُ قائماً بلا بنودٍ ولا قيود — وصفةٌ فارغة
            تُختار في أوامر التصنيع فتُنتج أمراً بلا مواد. ولا شيء في
            النظام يقول من حذف ولا متى.
        */
        DB::beginTransaction();

        try {
            /*
                لا قيودَ ولا أذونَ تُحذف هنا.

                كان أوّلُ سطرٍ يحذف القيود بـ`where('type_id', …)->where('type', …)`
                — وجدول `journal_entries` لا عمودَ فيه بهذين الاسمين. فيسقط
                الاستعلام، ويلتقطه `catch`، ويُبلَّغ «تعذّر الحذف» دائماً:
                **لم تكن أيُّ قائمةٍ تُحذف إطلاقاً**، مستعملةً أو غير مستعملة.

                والقائمةُ تعريفٌ لا يُرحِّل قيداً ولا يُصدر إذناً (انظر
                `store()`)، فلا شيء يُحذف معها غيرُ بنودها. وإن وُجد إذنٌ قديم
                من المسار المحذوف فالقائمةُ «مستعملة» ويُرفض حذفُها أعلاه —
                حذفُ إذنٍ معتمد بلا عكسٍ يُبقي أثرَه في المخزون بلا مستند.
            */
            $productionMaterial->ProductionMaterialsItem()->delete();
            $productionMaterial->delete();

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_BOM,
                $id,
                'تم حذف قائمة المواد **'.$name.'**'.($code ? ' برقم '.$code : ''),
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_BOM, 'destroy', $id);

            return $this->bomDeleteResponse(false, 'تعذّر حذف قائمة المواد — قد تكون مستعملة في أوامر تصنيع قائمة.');
        }

        /*
            `success` لا `error`: كان الحذفُ الناجح يُبلَّغ في خانة الخطأ،
            فيظهر شريطٌ أحمر يقول «تم الحذف بنجاح» — فيعيد المستخدم الفعل
            ظنّاً أنه أخفق.
        */
        return $this->bomDeleteResponse(true, 'تم حذف البيانات بنجاح.');
    }

    /**
     * أين تُستعمل القائمة — أسطرٌ جاهزة للعرض، وفارغةٌ إن لم تُستعمل.
     *
     * العدُّ من الجدول مباشرةً لا من النموذج: أمرٌ محذوفٌ حذفاً ليّناً أو
     * خارجَ فرع المستخدم ما زال يشير إلى القائمة.
     *
     * @return array<int, string>
     */
    private function bomUsage(int $bomId): array
    {
        $usage = [];

        $orders = DB::table('manufactur_orders')->where('production_material_id', $bomId)->count();

        if ($orders > 0) {
            $usage[] = 'أوامر تصنيع (عددها '.$orders.')';
        }

        if (Schema::hasTable('production_plan_items')) {
            $plans = DB::table('production_plan_items')
                ->where('production_material_id', $bomId)
                ->distinct()
                ->count('production_plan_id');

            if ($plans > 0) {
                $usage[] = 'خطط إنتاج (عددها '.$plans.')';
            }
        }

        // إذنٌ من مسار «إنهاء الإنتاج» القديم داخل شاشة القوائم — أثرُه في المخزون قائم
        $permits = WarehousePermits::where('reference_id', $bomId)
            ->where('reference_type', ProductionMaterials::class)
            ->count();

        if ($permits > 0) {
            $usage[] = 'أذون مخزنية (عددها '.$permits.')';
        }

        return $usage;
    }

    /**
     * ردُّ الحذف — JSON لطلب الشاشة، وتحويلٌ لغيره.
     *
     * شاشتا القائمة والعرض تحذفان بـ`$.ajax` وتقرآن `responseJSON.message`
     * عند الإخفاق. وكان الردُّ تحويلاً في الحالين: فيتبعه المتصفّح بصمت،
     * ويصل `done()` — فيظهر الرفضُ للمستخدم **نجاحاً** والقائمةُ في مكانها.
     */
    private function bomDeleteResponse(bool $deleted, string $message)
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => $deleted, 'message' => $message], $deleted ? 200 : 422);
        }

        return redirect()->route('BOM.index')->with([$deleted ? 'success' : 'error' => $message]);
    }

    // ═════════════════════════════════════════════════════════════════
    // بياناتُ نموذج الإنشاء والتعديل — مصدرٌ واحد للشاشتين
    // ═════════════════════════════════════════════════════════════════

    /**
     * كلُّ ما يحتاجه نموذجُ القائمة، للإنشاء (`$bom = null`) وللتعديل.
     *
     * كانت `create()` و`edit()` تجمعان المراجع كلٌّ بطريقتها، فتباعدتا:
     * الإنشاء يعرض كلَّ المنتجات والتعديلُ يعرض المصنَّفةَ «خام» وحدها —
     * فقائمةٌ حُفظت بمنتجٍ غير مصنَّف تُفتح للتعديل بصفوفٍ فارغة لا تُحفظ.
     * والوحداتُ من `sub_units` (جدولٌ قديم بلا صفوف) فتخرج قائمتُها فارغةً
     * وهي إلزامية. الآن مصدرٌ واحد، والمحفوظُ في القائمة يبقى ظاهراً دائماً.
     */
    private function bomFormData(?ProductionMaterials $bom = null): array
    {
        $items = $bom
            ? ProductionMaterialsItem::where('production_material_id', $bom->id)->orderBy('id')->get()
            : collect();

        $savedProducts = $items->pluck('raw_product_id')
            ->merge($items->pluck('end_life_product_id'))
            ->filter()->unique()->values()->all();

        $components = $this->bomComponentProducts($savedProducts);
        $units = $components->mapWithKeys(fn (Product $product) => [$product->id => [
            // «سعر» البند = متوسطُ تكلفة الصنف في المخزون، لا سعرُ شرائه في بطاقته
            'purchase' => $this->bomAverageCost($product),
            'sale' => (float) $product->sale_price,
            'units' => $this->bomUnitsOf($product),
        ]])->all();

        $products = $this->bomHeaderProducts($bom?->product_id);
        $accounts = $this->bomFormAccounts($bom, $items);

        // المساراتُ والمحطّات من الفرع النشط وحده — والمحفوظُ يبقى ظاهراً
        $paths = $this->scopeToActiveBranch(ProductionPath::query())->select('id', 'name')->get();

        if ($bom?->production_path_id && ! $paths->contains('id', $bom->production_path_id)) {
            $paths = $paths->concat(
                ProductionPath::whereKey($bom->production_path_id)->select('id', 'name')->get()
            )->values();
        }

        $workstations = $this->scopeToActiveBranch(WorkStations::query())
            ->select('id', 'name', 'total_cost')->get();

        $missingStations = $items->pluck('workstation_id')->filter()->unique()
            ->diff($workstations->pluck('id'));

        if ($missingStations->isNotEmpty()) {
            $workstations = $workstations->concat(
                WorkStations::whereIn('id', $missingStations->all())->select('id', 'name', 'total_cost')->get()
            )->values();
        }

        $stages = ProductionStage::select('id', 'stage_name')->get();

        return [
            'productionMaterial' => $bom,
            'products' => $products,
            'accounts' => $accounts,
            // حسابُ رأس القائمة — من الأصول؛ وحساباتُ بنود المصروف تبقى أعلاه
            'headerAccounts' => $this->bomHeaderAccounts($bom?->account_id),
            'paths' => $paths,
            'form' => [
                'reference' => [
                    'products' => $components->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
                    'accounts' => $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(),
                    'stages' => $stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->stage_name])->values(),
                    'workstations' => $workstations->map(fn ($w) => [
                        'id' => $w->id,
                        'name' => $w->name,
                        'cost' => (float) $w->total_cost,
                    ])->values(),
                ],
                'units' => $units,
                'rows' => old('_token') !== null
                    ? $this->bomRowsFromOldInput()
                    : $this->bomRowsFromItems($items, $units),
            ],
        ];
    }

    /**
     * منتجاتُ بنود القائمة — المواد الخام والهوالك.
     *
     * التمييزُ من عمود `products.material_type` (خام · نصف مصنَّع · نهائي)
     * وهو إلزاميٌّ في شاشة المنتج لكل منتجٍ مخزني. فالبنودُ تعرض الخامَ
     * ونصفَ المصنَّع (نصفُ المصنَّع مادّةٌ لمنتجٍ آخر)، والمنتجُ النهائي
     * مكانُه حقل «المنتج» في رأس القائمة وحده.
     *
     * والمنتجُ المخزني **غير المصنَّف** (أُنشئ قبل أن يصير الحقل إلزامياً)
     * يبقى ظاهراً — لا يُعرف نوعُه فلا يُحجب. والخدماتُ والمنتجات الرقمية
     * لا مخزونَ لها فلا تُصرف مادّةً. وما حُفظ في القائمة يبقى ظاهراً
     * أيّاً كان نوعُه.
     */
    private function bomComponentProducts(array $keepIds = [])
    {
        return $this->bomProductsOfTypes(['raw', 'semi_finished'], $keepIds);
    }

    /** منتجاتُ رأس القائمة — النهائي ونصفُ المصنَّع، وغيرُ المصنَّف، والمحفوظ. */
    private function bomHeaderProducts($keepId = null)
    {
        return $this->bomProductsOfTypes(['finished', 'semi_finished'], array_filter([(int) $keepId]))
            ->each(function (Product $product) {
                $units = collect($this->bomUnitsOf($product));
                $base = $units->firstWhere('base', true) ?? $units->firstWhere('default', true);

                $product->setAttribute('bom_unit_name', $base['name'] ?? '');
            });
    }

    private function bomProductsOfTypes(array $materialTypes, array $keepIds)
    {
        return Product::query()
            ->where(function ($query) use ($materialTypes, $keepIds) {
                $query->whereIn('material_type', $materialTypes)
                    ->orWhere(function ($unclassified) {
                        $unclassified->whereNull('material_type')
                            ->where(function ($stocked) {
                                $stocked->whereNull('type')->orWhereNotIn('type', ['services', 'digital']);
                            });
                    });

                if ($keepIds !== []) {
                    $query->orWhereIn('id', $keepIds);
                }
            })
            ->with([
                'productUnits' => fn ($q) => $q->where('is_active', true)->with('categoryUnit.unit'),
                'category.categoryUnits' => fn ($q) => $q->where('is_active', true)->with('unit'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * وحداتُ المنتج — كما في نموذج أمر التصنيع.
     *
     * وحداتُ المنتج الخاصّة (`product_units`) إن وُجدت، وإلّا وحداتُ
     * تصنيفه (`category_unit`). ومعرّفُ الأولى رقمٌ، ومعرّفُ الثانية
     * `category_<id>` — انظر [BuildsBomItems::resolveBomUnit] لما يُحفظ
     * من كلٍّ منهما.
     *
     * وسعرُ وحدة التصنيف = سعر المنتج × معامل التحويل: سعرُ المنتج للوحدة
     * الأساسية، فكرتونةٌ بخمسين حبّة ثمنُها خمسون ضعفاً.
     */
    private function bomUnitsOf(Product $product): array
    {
        $units = [];

        if ($product->productUnits->isNotEmpty()) {
            foreach ($product->productUnits as $productUnit) {
                $categoryUnit = $productUnit->categoryUnit;

                if (! $categoryUnit || ! $categoryUnit->unit) {
                    continue;
                }

                $units[] = [
                    'id' => (string) $productUnit->id,
                    'name' => $categoryUnit->unit->name,
                    'factor' => (float) $categoryUnit->conversion_factor ?: 1.0,
                    'base' => (bool) $categoryUnit->is_base_unit,
                    'default' => (bool) $productUnit->is_default,
                    'purchase' => round($this->bomAverageCost($product) * (float) (optional($productUnit->categoryUnit)->conversion_factor ?: 1), 6),
                    'sale' => (float) $productUnit->sale_price,
                ];
            }

            return $units;
        }

        foreach (optional($product->category)->categoryUnits ?? [] as $categoryUnit) {
            if (! $categoryUnit->unit) {
                continue;
            }

            $factor = (float) $categoryUnit->conversion_factor ?: 1.0;

            $units[] = [
                'id' => 'category_'.$categoryUnit->id,
                'name' => $categoryUnit->unit->name,
                'factor' => $factor,
                'base' => (bool) $categoryUnit->is_base_unit,
                'default' => (bool) $categoryUnit->is_base_unit,
                'purchase' => round($this->bomAverageCost($product) * $factor, 6),
                'sale' => round((float) $product->sale_price * $factor, 2),
            ];
        }

        return $units;
    }

    /**
     * حساباتُ النموذج — حسابات مصروفات التصنيع، وما حُفظ في القائمة.
     *
     * قائمةٌ قديمة قد تحمل حساباً خارج الشجرة (حُفظت قبل الحصر): يبقى
     * ظاهراً في صفّه كي لا تُفتح القائمة بحقلٍ فارغ، ولا يُعرض في غيرها.
     */
    /**
     * حساباتُ رأس القائمة — حساباتُ الأصول.
     *
     * حقلُ «الحساب» في رأس قائمة المواد هو حسابُ مخزون المنتَج، وهو أصل.
     * كان يعرض حساباتِ مصروفات التصنيع نفسَها التي تخصّ بنودَ المصروف.
     * تُعرض الحساباتُ الفرعية وحدها، وتُستبعد ذممُ العملاء والمورّدين —
     * هي أصولٌ في الدليل لكنها ليست حساباتِ مخزون. والمحفوظُ على القائمة
     * يبقى ظاهراً عند التعديل.
     */
    private function bomHeaderAccounts($keepId = null)
    {
        $accounts = Account::query()
            ->where('account_type', 'assets')
            ->whereNull('client_id')
            ->whereNull('supplier_id')
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);

        if ($keepId && ! $accounts->contains('id', $keepId)) {
            $accounts = $accounts->concat(Account::whereKey($keepId)->get(['id', 'name', 'code']));
        }

        return $accounts->values();
    }

    /** حسابُ الرأس الواصل في الطلب — من الأصول، أو المحفوظُ على القائمة. */
    private function assertHeaderAccount(Request $request, ?ProductionMaterials $bom = null): void
    {
        $accountId = (int) $request->input('account_id');

        if ($accountId <= 0 || ($bom && (int) $bom->account_id === $accountId)) {
            return;
        }

        if (! $this->bomHeaderAccounts()->contains('id', $accountId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_id' => 'الحساب: اختر حساباً من حسابات الأصول (حساب مخزون المنتج).',
            ]);
        }
    }

    /**
     * متوسطُ تكلفة الصنف في المخزون — مرجَّحاً بالكميات.
     *
     * هو ما يُعرض سعراً للمادة الخام **وللمادة الهالكة** عند اختيارها:
     * التكلفةُ التي تُصرَف بها المادةُ فعلاً. وصنفٌ لا رصيدَ له يرجع إلى سعر
     * شرائه في بطاقته. القاعدةُ نفسُها في أمر التصنيع
     * ([ManufacturingOrdersController::averageCosts]).
     */
    private function bomAverageCost(Product $product): float
    {
        static $costs = null;

        if ($costs === null) {
            // «سعر التكلفة» في النظام — متوسطُ أذون الإضافة، كبطاقة الصنف
            $costs = app(\Modules\Manufacturing\Services\InventoryService::class)->averageCosts();

            // صنفٌ بلا أذون إضافةٍ مسعَّرة يُسعَّر بآخر تكلفةٍ عُرفت له في المخزون
            $stored = DB::table('product_details')
                ->groupBy('product_id')
                ->selectRaw('product_id, MAX(COALESCE(unit_price, 0)) AS cost')
                ->pluck('cost', 'product_id');

            foreach ($stored as $productId => $storedCost) {
                if (($costs[$productId] ?? 0) <= 0 && (float) $storedCost > 0) {
                    $costs[$productId] = (float) $storedCost;
                }
            }
        }

        $cost = (float) ($costs[$product->id] ?? 0);

        return round($cost > 0 ? $cost : (float) $product->purchase_price, 6);
    }

    private function bomFormAccounts(?ProductionMaterials $bom, $items)
    {
        $accounts = $this->manufacturingExpenseAccounts();

        $saved = $items->pluck('expenses_account_id')
            ->push($bom?->account_id)
            ->filter()->unique()
            ->diff($accounts->pluck('id'));

        if ($saved->isEmpty()) {
            return $accounts;
        }

        return $accounts
            ->concat(Account::whereIn('id', $saved->all())->orderBy('code')->get(['id', 'name', 'code']))
            ->values();
    }

    /**
     * الصفوفُ المحفوظة بصيغة الشاشة.
     *
     * الوحدةُ تُعاد بمعرّفها إن حُفظ، وإلّا بمعامل التحويل: وحدةُ التصنيف
     * لا معرّفَ لها في `raw_unit_id`، فتُطابَق بأوّل وحدةٍ للمنتج لها
     * المعاملُ نفسه.
     */
    private function bomRowsFromItems($items, array $units): array
    {
        $unitOf = function ($item) use ($units) {
            if ($item->raw_unit_id) {
                return (string) $item->raw_unit_id;
            }

            if ($item->raw_conversion_factor === null) {
                return '';
            }

            foreach ($units[$item->raw_product_id]['units'] ?? [] as $unit) {
                if (abs($unit['factor'] - (float) $item->raw_conversion_factor) < 0.000001) {
                    return $unit['id'];
                }
            }

            return '';
        };

        return [
            'raw' => $items->whereNotNull('raw_product_id')->map(fn ($item) => [
                'product' => $item->raw_product_id,
                'unit' => $unitOf($item),
                'factor' => (float) ($item->raw_conversion_factor ?: 1),
                'price' => (float) $item->raw_unit_price,
                'qty' => (float) $item->raw_quantity,
                'stage' => $item->raw_production_stage_id,
            ])->values(),

            'expenses' => $items->whereNotNull('expenses_account_id')->map(fn ($item) => [
                'account' => $item->expenses_account_id,
                'costType' => $item->expenses_cost_type ?: 1,
                'amount' => (float) $item->expenses_price,
                'description' => $item->expenses_description,
                'stage' => $item->expenses_production_stage_id,
            ])->values(),

            'manufacturing' => $items->whereNotNull('workstation_id')->map(fn ($item) => [
                'workstation' => $item->workstation_id,
                'costType' => $item->manu_cost_type ?: 1,
                'time' => (float) $item->operating_time,
                'cost' => (float) $item->manu_total_cost,
                'description' => $item->manu_description,
                'stage' => $item->manu_production_stage_id,
            ])->values(),

            'endlife' => $items->whereNotNull('end_life_product_id')->map(fn ($item) => [
                'product' => $item->end_life_product_id,
                'price' => (float) $item->end_life_unit_price,
                'qty' => (float) $item->end_life_quantity,
                'stage' => $item->end_life_production_stage_id,
            ])->values(),
        ];
    }

    /**
     * بعد خطأ في الحفظ تُعاد الصفوف كما أدخلها المستخدم.
     *
     * حقولُ الرأس كانت تعود بـ`old()`، أمّا الجداول الأربعة فتبنيها الشاشة
     * بالسكربت — فكان أيُّ خطأ تحقّقٍ يُفرغها كلَّها (في الإنشاء) أو يُعيدها
     * إلى المحفوظ ويُضيّع التعديل (في التعديل).
     */
    private function bomRowsFromOldInput(): array
    {
        $rows = fn (string $key, callable $map) => collect((array) old($key, []))->keys()->map($map)->values();

        return [
            'raw' => $rows('raw_product_id', fn ($i) => [
                'product' => old("raw_product_id.$i"),
                'unit' => (string) old("raw_unit_id.$i"),
                'factor' => (float) (old("raw_conversion_factor.$i") ?: 1),
                'price' => old("raw_unit_price.$i"),
                'qty' => old("raw_quantity.$i"),
                'stage' => old("raw_production_stage_id.$i"),
            ]),
            'expenses' => $rows('expenses_account_id', fn ($i) => [
                'account' => old("expenses_account_id.$i"),
                'costType' => old("expenses_cost_type.$i") ?: 1,
                'amount' => old("expenses_price.$i"),
                'description' => old("expenses_description.$i"),
                'stage' => old("expenses_production_stage_id.$i"),
            ]),
            'manufacturing' => $rows('workstation_id', fn ($i) => [
                'workstation' => old("workstation_id.$i"),
                'costType' => old("manu_cost_type.$i") ?: 1,
                'time' => old("operating_time.$i"),
                'cost' => old("manu_total_cost.$i"),
                'description' => old("manu_description.$i"),
                'stage' => old("manu_production_stage_id.$i"),
            ]),
            'endlife' => $rows('end_life_product_id', fn ($i) => [
                'product' => old("end_life_product_id.$i"),
                'price' => old("end_life_unit_price.$i"),
                'qty' => old("end_life_quantity.$i"),
                'stage' => old("end_life_production_stage_id.$i"),
            ]),
        ];
    }

    /**
     * حسابُ المصروف في كل صفٍّ من حسابات «مصروفات التصنيع».
     *
     * هو فحصُ [ResolvesExpenseAccounts::assertExpenseAccountsAllowed] مع
     * فارقين: الرسالةُ تذكر رقمَ الصفّ، وعند التعديل يُقبل الحسابُ
     * المحفوظُ في القائمة من قبل — وإلّا تعذّر حفظُ قائمةٍ قديمة لم
     * يُمسَّ فيها صفُّ المصروف أصلاً.
     *
     * @throws ValidationException
     */
    private function assertBomExpenseAccounts(Request $request, ?ProductionMaterials $bom = null): void
    {
        $submitted = (array) $request->input('expenses_account_id', []);

        if ($submitted === []) {
            return;
        }

        $allowed = array_map('intval', $this->allowedExpenseAccountIds());

        if ($bom) {
            $allowed = array_merge($allowed, $bom->ProductionMaterialsItem()
                ->whereNotNull('expenses_account_id')
                ->pluck('expenses_account_id')
                ->map(fn ($id) => (int) $id)
                ->all());
        }

        $errors = [];
        $position = 0;

        foreach ($submitted as $index => $accountId) {
            $position++;
            $accountId = (int) $accountId;

            if ($accountId > 0 && ! in_array($accountId, $allowed, true)) {
                $name = Account::whereKey($accountId)->value('name');

                $errors["expenses_account_id.$index"] = sprintf(
                    'المصروفات — الصف %d: الحساب «%s» ليس من حسابات «مصروفات التصنيع».',
                    $position,
                    $name ?? $accountId
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function get_cost_total($id)
    {
        // محطّاتُ الفرع النشط وحدها — الأجرُ الساعي مكوّنُ تسعير لا يُقرأ
        // بمعرّفٍ مكتوبٍ في العنوان
        $workstation = $this->scopeToActiveBranch(WorkStations::query())->whereKey($id)->first();

        if (! $workstation) {
            return response()->json(['error' => 'Workstation not found'], 404);
        }

        return response()->json([
            'total_cost' => $workstation->total_cost,
            // بعملة المحطّة هي، لا بالافتراضية الحالية
            'formatted' => $workstation->formatMoney($workstation->total_cost),
            'currency' => $workstation->documentCurrency(),
        ]);
    }

    /**
     * إنشاء قيد يومية (مرحلة الصرف / البدء) - مرتبط بإذن الصرف
     */
    /*
    ═══════════════════════════════════════════════════════════════════
      ثلاث دوالّ حُذفت من هنا — المرحلة ١
    ═══════════════════════════════════════════════════════════════════

      createIssuanceJournalEntry()   ~68 سطراً
      createWarehousePermit()        ~67 سطراً
      finish()                       ~248 سطراً

      كنّ يشكّلن **مسار تصنيع كاملاً موازياً** داخل شاشة قوائم المواد:
      صرف مواد، وإنتاج منتج تام، وترحيل قيود — على مستندٍ هو تعريفٌ لا
      عملية. وأخطر ما فيهنّ:

        · الصرف من `StoreHouse::first()` — أوّل مستودع في الجدول، لا
          مستودعاً يختاره أحد.
        · صفر فحص توفّر وصفر قفل ⇒ رصيد سالب بلا حارس.
        · `ProductDetails::updateOrCreate` مع `DB::raw("quantity - $qty")`
          ⇒ عند غياب الصفّ تُنفَّذ INSERT بتعبير يشير إلى العمود نفسه:
          «Unknown column 'quantity' in 'field list'» فتسقط المعاملة.
        · `finish()` تبني قيداً مدينُه من الواجهة (`last_total_cost`)
          ودائنُه من البنود، بلا فحص توازن، ثم تُرحّله بحالة مُرحَّل.
        · وتُعدّل `Account::balance` يدوياً بجانب
          `updateBalancesHierarchically()` — والمراقب يُعيد الاحتساب من
          القيود، فالتعديل اليدوي إمّا يُمحى أو يترك رصيداً شاذاً.
        · `stock_before` و`stock_after` تُكتبان صفراً في كل السطور.

      والمسار الحيّ الوحيد للتصنيع هو دورة أمر التصنيع:
      `Modules/Manufacturing/Http/Controllers/Concerns/ManufacturingOrderLifecycle.php`
      وفيها الصرف ذرّي، والقيد متوازن ومُتحقَّق منه، والعكس مسجَّل بإذن مقابل.

      المرجع: القسم B من `Manufacturing-Audit-And-Roadmap.md` — العيوب
      C‑1 و C‑2 و D‑1 و D‑2.
    */
}
