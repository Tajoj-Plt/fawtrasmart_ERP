<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Constants\planFeatures\StockPlanFeatures;
use App\Enums\ProductionPlanStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionPlanRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\Product;
use App\Models\ProductionMaterials;
use App\Models\ProductionPath;
use App\Models\ProductionPlan;
use App\Models\StoreHouse;
use App\Services\Fawtra\UsagePush;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\GeneratesOrdersFromPlan;
use Modules\Manufacturing\Http\Controllers\Concerns\RecalculatesTotals;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * خطط الإنتاج — الطبقةُ فوق أوامر التصنيع.
 *
 * الخطّةُ نيّةٌ لا حركة: لا مخزونَ تمسّه ولا قيداً تُرحّله. أثرُها كلُّه
 * عبر الأوامر المولَّدة منها — انظر [GeneratesOrdersFromPlan].
 *
 * والحالةُ لا تُكتب من الشاشة: تتحرّك بآلة [\App\Enums\ProductionPlanStatus]
 * عبر `approve` و`revertToDraft` و`cancel`، وتُشتقّ بعد الاعتماد من البنود.
 */
class ProductionPlansController extends Controller
{
    use GeneratesDocumentNumbers,
        GeneratesOrdersFromPlan,
        RecalculatesTotals,
        RespectsAccountingPeriods,
        SafeListing,
        ScopesToBranch,
        UsesDocumentCurrency;

    /** أعمدةُ الترتيب المسموحة — انظر [SafeListing::applySafeSort]. */
    private const SORTABLE = ['created_at', 'code', 'name', 'from_date', 'to_date', 'status'];

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار — نمط
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
     *
     * والاعتمادُ وفكُّه والإلغاءُ بصلاحية `approve` واحدة: ثلاثتُها قرارٌ
     * على الخطّة كلِّها لا تحريرٌ لبنودها، ومن يملك أن يُقرّ يملك أن يتراجع.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.production_plans.view')->only(['index', 'getData']);
        $this->middleware('permission.role:stock.production_plans.show')->only(['show']);
        $this->middleware('permission.role:stock.production_plans.create')->only(['create', 'store']);
        $this->middleware('permission.role:stock.production_plans.edit')->only(['edit', 'update']);
        $this->middleware('permission.role:stock.production_plans.delete')->only(['destroy']);
        $this->middleware('permission.role:stock.production_plans.approve')
            ->only(['approve', 'revertToDraft', 'cancel']);
        $this->middleware('permission.role:stock.production_plans.generate_orders')->only(['generateOrders']);
    }

    public function index()
    {
        return view('manufacturing::production-plans.index', [
            'statuses' => ProductionPlanStatus::options(),
            'employees' => Employee::select('id', 'first_name', 'middle_name')->get(),
            // فروعُ التصفية — الفرعُ النشط وما تحته، انظر [ScopesToBranch]
            'branches' => $this->branchFilterOptions(),
        ]);
    }

    /** نقطةُ البيانات لجدول القائمة — نفسُ عقد `workstations.getData`. */
    public function getData(Request $request)
    {
        try {
            /*
                خططُ الفرع النشط وحدها.

                الخطّةُ نيّةُ إنتاجٍ في مصنعٍ بعينه، وأوامرُها تُختم بفرعها.
                فعرضُ خطط الفروع كلِّها يجعل مشرفَ خطٍّ يعتمد أو يولّد خطّةَ
                مصنعٍ لا يديره.
            */
            $query = $this->applyBranchFilter(ProductionPlan::query(), $request)
                ->with(['employee', 'items.manufacturOrder']);

            if ($search = trim((string) $request->get('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%")
                        ->orWhere('notes', 'LIKE', "%{$search}%");
                });
            }

            // الحالةُ عبر الـEnum — قيمةٌ مختلَقة تُهمَل لا تُستعلَم بها
            if ($status = ProductionPlanStatus::parse($request->get('status'))) {
                $query->where('status', $status->value);
            }

            if ($employee = $request->get('employee_id')) {
                $query->where('employee_id', $employee);
            }

            /*
                تداخلُ الفترة لا احتواؤها: خطّةٌ من ١ إلى ٣٠ مارس تخصّ من
                يبحث عن أسبوعٍ داخلها، والشرطُ الساذج
                (`from_date >= x AND to_date <= y`) كان سيُخفيها.
            */
            if ($from = $request->get('from_date')) {
                $query->whereDate('to_date', '>=', $from);
            }

            if ($to = $request->get('to_date')) {
                $query->whereDate('from_date', '<=', $to);
            }

            $this->applySafeSort($query, $request, self::SORTABLE);

            $plans = $query->paginate($this->safePerPage($request, 10));

            return response()->json([
                'success' => true,
                'data' => $plans->getCollection()->map(function (ProductionPlan $plan) {
                    /*
                        الحالةُ تُشتقّ قبل عرضها: الأوامرُ تكتمل وتُلغى
                        وتُحذف من شاشتها، والقائمةُ لا تعرض حالةً سبقها
                        الواقع. وهي في الغالب قراءةٌ بلا كتابة.
                    */
                    $plan->syncStatusFromOrders();
                    $ratio = $plan->generatedRatio();

                    return [
                        'id' => $plan->id,
                        'code' => $plan->code,
                        'name' => $plan->name,
                        'from_date' => optional($plan->from_date)->format('Y-m-d'),
                        'to_date' => optional($plan->to_date)->format('Y-m-d'),
                        'employee' => $plan->employee
                            ? trim($plan->employee->first_name.' '.($plan->employee->middle_name ?? ''))
                            : null,
                        'branch_name' => \Modules\Manufacturing\Support\BranchTree::name($plan->branch_id),
                        'status' => $plan->status->value,
                        'status_label' => $plan->status->label(),
                        'status_tone' => $plan->status->tone(),
                        'items_count' => $ratio['total'],
                        'generated_count' => $ratio['generated'],
                        'finished_count' => $ratio['finished'],
                        'planned_quantity' => $plan->plannedQuantity(),
                        'deletable' => $plan->status->isDeletable() && ! $plan->hasGeneratedOrders(),
                        'editable' => $plan->status->isEditable(),
                    ];
                })->all(),
                'pagination' => [
                    'current_page' => $plans->currentPage(),
                    'last_page' => $plans->lastPage(),
                    'per_page' => $plans->perPage(),
                    'total' => $plans->total(),
                    'from' => $plans->firstItem(),
                    'to' => $plans->lastItem(),
                ],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_PLAN, 'getData');

            return $this->safeErrorJson($e, 'تعذّر تحميل خطط الإنتاج.');
        }
    }

    public function create()
    {
        return view('manufacturing::production-plans.create', $this->formData() + [
            // معاينةٌ بلا استهلاك — يُصدَر الرقمُ عند الحفظ
            'serial_number' => $this->previewDocumentNumber(ManufacturingSources::SECTION_PLANS),
        ]);
    }

    public function store(ProductionPlanRequest $request)
    {
        // المراجعُ الواصلة في الطلب تُفحَص بالفرع كما تُصفّى القوائمُ المعروضة
        $this->assertPlanReferencesWithinBranch($request);
        $this->assertBomsBelongToProducts($request);

        DB::beginTransaction();

        try {
            $plan = ProductionPlan::create([
                'name' => $request->name,
                // من إعدادات الترقيم لا من الشاشة — انظر ProductionPlanRequest
                'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_PLANS),
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'employee_id' => $request->employee_id,
                'client_id' => $request->client_id ?: null,
                'account_id' => $request->account_id ?: null,
                'storehouse_id' => $request->storehouse_id ?: null,
                'notes' => $request->notes,
                // تُولد مسوّدةً دائماً — الاعتمادُ خطوةٌ مستقلّة بصلاحيتها
                'status' => ProductionPlanStatus::Draft,
                'created_by' => auth()->id(),
            ] + $this->newDocumentBranchAttributes());

            $this->syncItems($plan, $request);

            OperationLog::record(
                'created',
                ManufacturingSources::LOG_PLAN,
                $plan->id,
                'تم إضافة خطة إنتاج **'.$plan->name.'** برقم '.$plan->code,
            );

            DB::commit();

            return redirect()
                ->route('manufacturing.production-plans.show', $plan->id)
                ->with('success', 'تم حفظ خطة الإنتاج بنجاح.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_PLAN, 'store');

            return redirect()->back()->withInput()
                ->with('error', $this->safeError($e, 'حدث خطأ أثناء حفظ خطة الإنتاج.'));
        }
    }

    public function edit($id)
    {
        $plan = ProductionPlan::with(['items.product', 'items.productionMaterial', 'items.productionPath'])
            ->findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `edit/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($plan->branch_id);

        if (! $plan->status->isEditable()) {
            return redirect()
                ->route('manufacturing.production-plans.show', $plan->id)
                ->with('error', sprintf(
                    'لا يمكن تعديل خطّة «%s» — وُلِّد عن بنودها أوامرُ تصنيع.',
                    $plan->status->label()
                ));
        }

        return view('manufacturing::production-plans.edit', $this->formData() + ['plan' => $plan]);
    }

    public function update(ProductionPlanRequest $request, $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        if (! $plan->status->isEditable()) {
            return redirect()
                ->route('manufacturing.production-plans.show', $plan->id)
                ->with('error', sprintf('لا يمكن تعديل خطّة «%s».', $plan->status->label()));
        }

        $this->assertPlanReferencesWithinBranch($request);
        $this->assertBomsBelongToProducts($request);

        DB::beginTransaction();

        try {
            // الفرعُ لا يُعدَّل — `withoutBranch` تنزعه وإن وصل
            $plan->update($this->withoutBranch([
                'name' => $request->name,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'employee_id' => $request->employee_id,
                'client_id' => $request->client_id ?: null,
                'account_id' => $request->account_id ?: null,
                'storehouse_id' => $request->storehouse_id ?: null,
                'notes' => $request->notes,
                'updated_by' => auth()->id(),
            ]));

            $this->syncItems($plan, $request);

            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_PLAN,
                $plan->id,
                'تم تعديل خطة الإنتاج **'.$plan->name.'**',
            );

            DB::commit();

            return redirect()
                ->route('manufacturing.production-plans.show', $plan->id)
                ->with('success', 'تم تعديل خطة الإنتاج بنجاح.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_PLAN, 'update', (int) $id);

            return redirect()->back()->withInput()
                ->with('error', $this->safeError($e, 'حدث خطأ أثناء تعديل خطة الإنتاج.'));
        }
    }

    public function show($id)
    {
        $plan = ProductionPlan::with([
            'employee', 'client', 'account', 'storeHouse',
            'items.product', 'items.productionMaterial', 'items.productionPath',
        ])->findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        /*
            الحالةُ تُضبط عند الفتح لا عند التوليد وحده: الأوامرُ تكتمل
            وتُلغى وتُحذف من شاشتها، والخطّةُ «مكتملة» حين تكتمل أوامرُها
            القائمة كلُّها لا قبل ذلك — انظر
            `ProductionPlan::syncStatusFromOrders`.
        */
        $this->syncPlanStatusFromItems($plan);
        $plan->refresh()->load([
            'employee', 'client', 'account', 'storeHouse',
            'items.product', 'items.productionMaterial', 'items.productionPath', 'items.manufacturOrder',
        ]);

        $logs = ModelsLog::where('type', ManufacturingSources::LOG_PLAN)
            ->where('type_id', $plan->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        return view('manufacturing::production-plans.show', compact('plan', 'logs'));
    }

    public function destroy($id)
    {
        try {
            $plan = ProductionPlan::findOrFail($id);
            $this->authorizeBranchScope($plan->branch_id);

            /*
                حارسان لا واحد: الحالةُ تقول متى **يُتوقَّع** ألّا تكون
                هناك أوامر، والفحصُ يقول إن كانت هناك فعلاً — وهو الحاسم.
                حذفُ خطّةٍ لها أوامر يقطع الأوامرَ عن سببها.
            */
            if (! $plan->status->isDeletable()) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf('لا يمكن حذف خطّة «%s».', $plan->status->label()),
                ], 422);
            }

            if ($plan->hasGeneratedOrders()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الخطّة — وُلِّد عن بنودها أوامرُ تصنيع. ألغِ الخطّة بدل حذفها.',
                ], 422);
            }

            $name = $plan->name;
            $code = $plan->code;

            // البنودُ تسقط بـ`cascadeOnDelete` من الهجرة
            $plan->delete();

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_PLAN,
                (int) $id,
                'تم حذف خطة الإنتاج **'.$name.'** برقم '.$code,
            );

            return response()->json(['success' => true, 'message' => 'تم حذف خطة الإنتاج بنجاح.']);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_PLAN, 'destroy', (int) $id);

            return $this->safeErrorJson($e, 'حدث خطأ أثناء حذف خطة الإنتاج.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // انتقالات الحالة
    // ─────────────────────────────────────────────────────────────────

    /**
     * اعتمادُ الخطّة — الخطوةُ التي تفصل من يخطّط عمّن يُقرّ.
     * قبلها لا يُولَّد شيء.
     */
    public function approve($id)
    {
        $plan = ProductionPlan::withCount('items')->findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        try {
            $plan->status->assertCanTransitionTo(ProductionPlanStatus::Approved);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($plan->items_count === 0) {
            return redirect()->back()->with('error', 'لا يمكن اعتماد خطّة بلا بنود.');
        }

        $plan->update(['status' => ProductionPlanStatus::Approved, 'updated_by' => auth()->id()]);

        OperationLog::record(
            'approved',
            ManufacturingSources::LOG_PLAN,
            $plan->id,
            'تم اعتماد خطة الإنتاج **'.$plan->name.'**',
        );

        return redirect()->back()->with('success', 'تم اعتماد الخطّة — يمكن الآن توليد أوامر التصنيع.');
    }

    /** فكُّ الاعتماد — ما دام لم يُولَّد أمرٌ بعد. */
    public function revertToDraft($id)
    {
        $plan = ProductionPlan::findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        try {
            $plan->status->assertCanTransitionTo(ProductionPlanStatus::Draft);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($plan->hasGeneratedOrders()) {
            return redirect()->back()->with('error', 'لا يمكن فكّ الاعتماد — وُلِّد عن بنودها أوامرُ تصنيع.');
        }

        $plan->update(['status' => ProductionPlanStatus::Draft, 'updated_by' => auth()->id()]);

        OperationLog::record(
            'updated',
            ManufacturingSources::LOG_PLAN,
            $plan->id,
            'تم فكّ اعتماد خطة الإنتاج **'.$plan->name.'**',
        );

        return redirect()->back()->with('success', 'أُعيدت الخطّة مسوّدة.');
    }

    /**
     * إلغاءُ الخطّة — نهائيّ، ولا يمسّ أوامرها المولَّدة.
     *
     * الأوامرُ مستنداتٌ قائمةٌ بذاتها لها حالتُها وقيودُها؛ إلغاءُ الخطّة
     * يقول «لن يُولَّد منها المزيد»، لا «أُلغيَ ما أُنتج».
     */
    public function cancel(Request $request, $id)
    {
        $plan = ProductionPlan::findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        try {
            $plan->status->assertCanTransitionTo(ProductionPlanStatus::Cancelled);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $reason = trim(mb_substr((string) $request->input('reason', ''), 0, 500));

        $plan->update([
            'status' => ProductionPlanStatus::Cancelled,
            'notes' => $reason ? trim($plan->notes."\n[إلغاء] ".$reason) : $plan->notes,
            'updated_by' => auth()->id(),
        ]);

        OperationLog::record(
            'cancelled',
            ManufacturingSources::LOG_PLAN,
            $plan->id,
            'تم إلغاء خطة الإنتاج **'.$plan->name.'**'.($reason ? ' — '.$reason : ''),
        );

        return redirect()->back()->with('success', 'أُلغيت الخطّة. أوامرُها المولَّدة لم تُمَسّ.');
    }

    // ─────────────────────────────────────────────────────────────────
    // التوليد
    // ─────────────────────────────────────────────────────────────────

    /**
     * توليدُ أمرِ تصنيعٍ لكلّ بندٍ لم يُولَّد أمرُه — في معاملةٍ واحدة.
     */
    public function generateOrders($id)
    {
        $plan = ProductionPlan::findOrFail($id);
        $this->authorizeBranchScope($plan->branch_id);

        DB::beginTransaction();

        try {
            $result = $this->generateOrdersForPlan($plan);

            if ($result['created'] === 0) {
                DB::rollBack();

                return redirect()->back()
                    ->with('warning', 'لا بنود جديدة — كلُّ بنود الخطّة وُلِّدت أوامرُها.');
            }

            $this->syncPlanStatusFromItems($plan);

            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_PLAN,
                $plan->id,
                sprintf(
                    'تم توليد %d أمر تصنيع من خطة الإنتاج **%s** (%s)',
                    $result['created'],
                    $plan->name,
                    implode('، ', $result['codes'])
                ),
            );

            DB::commit();

            /*
                العدّادُ الشهريّ تغيّر بأكثر من واحد في طلبٍ واحد.

                `markBulkChange` لا `mark`: القياسُ الحيّ المحفوظ في الطلب
                صار كاذباً (قُرئ قبل الإنشاء)، فيُمسَح ليُعاد العدّ ويُرفَع
                الاستهلاك إلى فوترة بعد الاستجابة. و`ManufacturOrders` ليس
                في `PlanLimitObserver` فلا شيء غيرُ هذا السطر يُبلغ عنه.
            */
            UsagePush::markBulkChange(StockPlanFeatures::LIMIT_MANUFACTURING_ORDERS);

            $success = sprintf('تم توليد %d أمر تصنيع: %s', $result['created'], implode('، ', $result['codes']));

            if ($result['reserved'] !== []) {
                $success .= sprintf(
                    ' — حُجزت مواد %d منها في مستودع المواد.',
                    count($result['reserved'])
                );
            }

            $redirect = redirect()->back()->with('success', $success);

            /*
                النقصُ لا يُسقط التوليد — يُقال بعده، كما تفعل شاشةُ الأمر
                (`ReservesOrderMaterials::reservationShortageMessage`).
            */
            if ($warning = $this->planReservationWarning($plan, $result)) {
                $redirect->with('warning', $warning);
            }

            return $redirect;
        } catch (\DomainException|PlanLimitExceededException $e) {
            DB::rollBack();

            // قاعدةُ عملٍ مقصودة — نصُّها للمستخدم كما هو
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_PLAN, 'generateOrders', (int) $id);

            return redirect()->back()
                ->with('error', $this->safeError($e, 'حدث خطأ أثناء توليد أوامر التصنيع.'));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // مساعدات
    // ─────────────────────────────────────────────────────────────────

    /**
     * قوائمُ الشاشة — واحدةٌ لـ`create` و`edit` فلا تنحرف إحداهما.
     *
     * قوائمُ المواد والمسارات والمستودعات مصفّاةٌ بالفرع النشط: خطّةٌ
     * تُبنى على وصفةِ فرعٍ آخر تُولّد أمراً يصرف من مخزونٍ لا يملكه.
     */
    private function formData(): array
    {
        return [
            'products' => Product::select('id', 'name')->orderBy('name')->get(),
            'employees' => Employee::select('id', 'first_name', 'middle_name')->get(),
            'clients' => Client::select('id', 'trade_name')->get(),
            'accounts' => Account::select('id', 'name', 'code')->orderBy('code')->get(),
            'storehouses' => $this->scopeBranchColumn(StoreHouse::query())->select('id', 'name')->get(),
            'paths' => $this->scopeToActiveBranch(ProductionPath::query())->select('id', 'name')->get(),
            'materials' => $this->scopeToActiveBranch(ProductionMaterials::query())
                ->select('id', 'name', 'product_id', 'quantity')->get(),
        ];
    }

    /**
     * المراجعُ الواصلة في الطلب داخل نطاق الفرع — نمط
     * `ManufacturingOrdersController::assertOrderReferencesWithinBranch`،
     * ومعه مراجعُ كلِّ بند: القائمةُ في الصفحة مصفّاة، والمعرّفُ في الطلب لا.
     */
    private function assertPlanReferencesWithinBranch(Request $request): void
    {
        if ($request->filled('storehouse_id')) {
            $this->assertBranchScope(
                StoreHouse::whereKey($request->input('storehouse_id'))->value('branch_id'),
                'storehouse_id'
            );
        }

        foreach ([
            'production_material_id' => ProductionMaterials::class,
            'production_path_id' => ProductionPath::class,
        ] as $field => $model) {
            foreach ((array) $request->input($field, []) as $index => $referenceId) {
                if (empty($referenceId)) {
                    continue;
                }

                $this->assertBranchScope($model::whereKey($referenceId)->value('branch_id'), "$field.$index");
            }
        }
    }

    /**
     * قائمةُ المواد في كلّ بندٍ هي قائمةُ **المنتج نفسِه**.
     *
     * الشاشةُ تُصفّي القائمةَ بالمنتج المختار، والطلبُ يُرسَل بأي أداة:
     * بندُ «خبز» بوصفة «كعك» يُولّد أمراً يصرف موادَّ الكعك ويُدخل خبزاً
     * بتكلفته. والرسالةُ تسمّي الصفَّ كي لا يبحث المستخدمُ عنه.
     */
    private function assertBomsBelongToProducts(Request $request): void
    {
        $bomIds = array_filter((array) $request->input('production_material_id', []));
        $boms = ProductionMaterials::whereIn('id', $bomIds)->get(['id', 'name', 'product_id'])->keyBy('id');
        $productNames = Product::whereIn('id', array_filter((array) $request->input('product_id', [])))
            ->pluck('name', 'id');

        $errors = [];
        $rowNumber = 0;

        foreach ((array) $request->input('product_id', []) as $index => $productId) {
            if (empty($productId)) {
                continue;
            }

            $rowNumber++;
            $bom = $boms->get((int) $request->input("production_material_id.$index"));

            if ($bom && (int) $bom->product_id !== (int) $productId) {
                $errors["production_material_id.$index"] = [sprintf(
                    'البند رقم %d: قائمة المواد «%s» لا تخصّ المنتج «%s» — اختر قائمة مواد من قوائم هذا المنتج.',
                    $rowNumber,
                    $bom->name,
                    $productNames[$productId] ?? '#'.$productId
                )];
            }
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /**
     * يُحدّث بنودَ الخطّة من الطلب: يُعدّل القائم، ويُنشئ الجديد، ويحذف
     * ما رُفع من الشاشة — ولا يمسّ بنداً وُلِّد أمرُه، لا تعديلاً ولا
     * حذفاً. الشاشةُ لا تعرض تلك البنودَ للتحرير أصلاً، والحارسُ هنا لأنّ
     * الطلبَ يُرسَل بأي أداة.
     *
     * يُستدعى داخل معاملة مفتوحة.
     */
    private function syncItems(ProductionPlan $plan, ProductionPlanRequest $request): void
    {
        $keptIds = [];

        foreach ((array) $request->input('product_id', []) as $index => $productId) {
            if (empty($productId)) {
                continue;
            }

            $payload = [
                'product_id' => $productId,
                'production_material_id' => $request->input("production_material_id.$index"),
                'production_path_id' => $request->input("production_path_id.$index"),
                'planned_quantity' => (float) $request->input("planned_quantity.$index", 0),
                'notes' => $request->input("item_notes.$index") ?: null,
            ];

            $itemId = $request->input("item_id.$index");

            // `whereNull` يمنع الكتابة فوق بندٍ وُلِّد أمرُه
            $existing = $itemId
                ? $plan->items()->whereKey($itemId)->whereNull('manufactur_order_id')->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $plan->items()->create($payload)->id;
        }

        $plan->items()
            ->whereNotIn('id', $keptIds ?: [0])
            ->whereNull('manufactur_order_id')
            ->delete();
    }
}
