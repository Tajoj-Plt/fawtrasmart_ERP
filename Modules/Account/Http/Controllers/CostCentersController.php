<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingPeriodOptions;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;

class CostCentersController extends Controller
{
    // `accountingRange` — الفترة الجارية مدًى افتراضياً حين لا يُطلب غيرها
    use AccountingOperations;

    public function __construct()
    {
        // صلاحيات مراكز التكلفة — الكتالوج الجديد account.cost_centers.*
        $this->middleware('permission.role:account.cost_centers.view', ['only' => ['index', 'view', 'getTree', 'getParents', 'getChildren', 'getAccountDetails', 'getRootParent']]);
        $this->middleware('permission.role:account.cost_centers.create', ['only' => ['store_account', 'getNextCode']]);
        $this->middleware('permission.role:account.cost_centers.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.cost_centers.delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        // النطاقُ نفسُه الذي تقرأ منه الشجرة، فلا تتناقض الأرقامُ مع ما تحتها
        $accounts = $this->scopedCostCenters()->orderBy('code')->get();

        // الشجرةُ تُبنى في الخادم: الصفحةُ تفتح عليها لا على دوّارة تحميل
        $roots = $this->scopedTree();

        /*
            الشاشة كانت شجرةً وجدولاً فارغاً حتى يُنقر عليها. الأرقام الثلاثة
            تُقرأ قبل أي نقرة: كم مركزاً، وكم رئيسياً، وكم فرعياً — وهي أوّل ما
            يُسأل عنه عند مراجعة التوزيع.
        */
        $stats = [
            'total' => $accounts->count(),
            'main' => $accounts->whereNull('parent_id')->count(),
            'sub' => $accounts->whereNotNull('parent_id')->count(),
        ];

        $can = $this->buttonPermissions();

        return view('account::cost_centers.index', compact('accounts', 'roots', 'stats', 'can'));
    }

    /**
     * عرض مركز التكلفة وأبناءه وحركته — على نمط شجرة الحسابات.
     *
     * **ما كان**
     *
     * شاشة واحدة (`index`) تعرض الشجرة كلّها دفعةً واحدة في صفحةٍ من
     * ثمانمئة سطر، ولا تُظهر لمركزٍ بعينه أبناءه ولا ما حُمّل عليه من
     * قيود. فمن أراد أن يعرف كم حُمّل على «مركز التسويق» لا يجد سبيلاً
     * إلّا تقريراً منفصلاً.
     *
     * ولمراكز التكلفة `parent_id` و`code` و`is_main` منذ إنشائها — أي
     * أنها شجرةٌ في القاعدة بلا واجهةِ شجرة.
     *
     * **ما صار**
     *
     * شاشةٌ كشجرة الحسابات: مسارُ الفئات من الجذر، وأبناء المركز مرقَّمين،
     * وحركته المحمَّلة عليه مع مجاميعها. والجذر يُعرَض حين لا يُطلب مركزٌ
     * بعينه.
     */
    public function view(Request $request, $id = null)
    {
        $center = $id ? CostCenter::find($id) : null;

        if ($id && ! $center) {
            return redirect()->route('cost_centers.view_root')
                ->with('error', 'مركز التكلفة غير موجود.');
        }

        // مسار الفئات من الجذر إلى المركز
        $breadcrumb = [];

        if ($center) {
            $cursor = $center;
            $guard = 0;

            while ($cursor && $guard < 20) {
                array_unshift($breadcrumb, [
                    'id' => $cursor->id,
                    'name' => $cursor->name,
                    'code' => $cursor->code,
                ]);

                $cursor = $cursor->parent_id ? CostCenter::find($cursor->parent_id) : null;
                $guard++;
            }
        }

        $perPage = (int) $request->input('per_page', 25);
        $perPage = ($perPage < 5 || $perPage > 200) ? 25 : $perPage;

        /*
            **فلاترُ الحركة — كما في شجرة الحسابات.**

            كانت الشاشة تعرض حركةَ المركز منذ نشأته بلا مدىً ولا فرع: مركزٌ
            عمرُه سنتان يُقرأ رقماً واحداً لا يُنسب إلى فترة، ولا سبيلَ إلى
            سؤال «كم حُمّل عليه هذا الشهر؟» — وهو أوّلُ ما يُسأل.
        */
        /*
            **الحركةُ تُنسب إلى الفترة بعلاقة قيدها.**

            سطرُ القيد لا يحمل فترةً بنفسه — يحملها قيدُه، مختومةً لحظةَ
            إنشائه. فالانضمامُ إلى `journal_entries` قائمٌ أصلاً، والشرطُ
            يقع على عموده لا على تاريخه.

            ومركزٌ عمرُه سنتان يُقرأ رقماً واحداً لا يُنسب إلى فترة.
        */
        $period = AccountingPeriodFilter::resolve($request);
        $branchId = $request->input('branch_id');

        $applyFilters = function ($query) use ($period, $branchId) {
            AccountingPeriodFilter::applyRaw($query, $period);

            if ($branchId) {
                $query->where('e.branch_id', $branchId);
            }

            return $query;
        };

        // الأبناء المباشرون، مع علامةٍ على من له أبناء
        $children = CostCenter::query()
            ->when($center, fn ($q) => $q->where('parent_id', $center->id))
            ->when(! $center, fn ($q) => $q->whereNull('parent_id'))
            ->withCount('children')
            ->orderBy('code')
            ->paginate($perPage, ['*'], 'children_page');

        // معرّفات المركز وذرّيّته، ومعرّفاتُ كلّ ابنٍ وذرّيّته
        $ids = $center ? $this->descendantIds($center->id) : CostCenter::pluck('id')->all();
        $directIds = $center ? [$center->id] : [];

        /*
            **الرصيدُ المحمَّل على كلّ ابنٍ يظهر في صفّه.**

            شجرةُ الحسابات تعرض رصيدَ كلّ حسابٍ فرعيٍّ إلى جانبه، فيُقرأ
            التوزيعُ من نظرةٍ واحدة. وكانت شاشةُ المراكز تعرض الأسماءَ
            والأكواد وحدها: لا يُعرف أيُّ فرعٍ حمل الكلفةَ إلّا بفتح كلٍّ
            منها على حدة.

            والمحمولُ على الابن يشمل ذرّيّتَه: مركزٌ أبٌ لا تُحمَّل عليه
            الحركةُ مباشرةً غالباً، بل على أبنائه — فعرضُ المباشر وحده
            يُظهره صفراً وهو يحمل الملايين.
        */
        $childTotals = [];

        if ($children->count()) {
            $subtreeOf = [];

            foreach ($children as $child) {
                foreach ($this->descendantIds($child->id) as $descendant) {
                    $subtreeOf[$descendant] = $child->id;
                }
            }

            if ($subtreeOf) {
                $rows = $applyFilters(
                    DB::table('journal_entry_details as d')
                        ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                        ->whereIn('d.cost_center_id', array_keys($subtreeOf))
                )
                    ->groupBy('d.cost_center_id')
                    ->selectRaw('d.cost_center_id, COALESCE(SUM(d.debit),0) dr, COALESCE(SUM(d.credit),0) cr, COUNT(*) n')
                    ->get();

                foreach ($rows as $row) {
                    $owner = $subtreeOf[$row->cost_center_id] ?? null;

                    if (! $owner) {
                        continue;
                    }

                    $childTotals[$owner] ??= ['debit' => 0.0, 'credit' => 0.0, 'lines' => 0];
                    $childTotals[$owner]['debit'] += (float) $row->dr;
                    $childTotals[$owner]['credit'] += (float) $row->cr;
                    $childTotals[$owner]['lines'] += (int) $row->n;
                }
            }
        }

        // حركة المركز وذرّيّته
        $movement = $applyFilters(
            DB::table('journal_entry_details as d')
                ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                ->whereIn('d.cost_center_id', $ids)
        )
            ->selectRaw('COALESCE(SUM(d.debit),0) dr, COALESCE(SUM(d.credit),0) cr, COUNT(*) n')
            ->first();

        /*
            المباشرُ يُفصَل عن الذرّيّة: شجرةُ الحسابات تقول «القيود المباشرة
            (لا تشمل قيود الحسابات الفرعية)» صراحةً، وبغير الفصل لا يُعرف
            أيُّ الرقمَين رقمُ هذا المركز نفسِه.
        */
        $direct = $directIds
            ? $applyFilters(
                DB::table('journal_entry_details as d')
                    ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                    ->whereIn('d.cost_center_id', $directIds)
            )
                ->selectRaw('COALESCE(SUM(d.debit),0) dr, COALESCE(SUM(d.credit),0) cr, COUNT(*) n')
                ->first()
            : null;

        $entries = $applyFilters(
            DB::table('journal_entry_details as d')
                ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                ->leftJoin('accounts as a', 'a.id', '=', 'd.account_id')
                ->leftJoin('cost_centers as c', 'c.id', '=', 'd.cost_center_id')
                ->leftJoin('branches as b', 'b.id', '=', 'e.branch_id')
                ->whereIn('d.cost_center_id', $ids)
        )
            ->orderByDesc('e.date')
            ->orderByDesc('d.id')
            ->select('d.id', 'd.debit', 'd.credit', 'd.description', 'd.cost_center_id',
                'e.reference_number', 'e.date', 'e.id as entry_id', 'e.reference_type',
                'a.id as account_id', 'a.name as account_name', 'a.code as account_code',
                'a.balance as account_balance', 'a.balance_type as account_balance_type',
                'c.name as center_name', 'c.code as center_code',
                'b.name as branch_name')
            ->paginate($perPage, ['*'], 'entries_page');

        /*
            **بدايةُ الرصيد الجاري في هذه الصفحة.**

            الترتيبُ من الأحدث إلى الأقدم، فالرصيدُ يمشي نازلاً من صافي المدّة
            كلِّها. وصفحةٌ ثانيةٌ تبدأ من الصافي نفسِه تكرّر أرقامَ الأولى؛
            فتُطرَح منها صافيةُ ما سبقها من صفوف.
        */
        $skipped = ($entries->currentPage() - 1) * $entries->perPage();
        $runningStart = (float) ($movement->dr ?? 0) - (float) ($movement->cr ?? 0);

        if ($skipped > 0) {
            $before = $applyFilters(
                DB::table('journal_entry_details as d')
                    ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                    ->whereIn('d.cost_center_id', $ids)
            )
                ->orderByDesc('e.date')
                ->orderByDesc('d.id')
                ->limit($skipped)
                ->selectRaw('d.debit - d.credit AS net');

            $runningStart -= (float) DB::query()
                ->fromSub($before, 't')
                ->sum('t.net');
        }

        return view('account::cost_centers.view', [
            'center' => $center,
            'breadcrumb' => $breadcrumb,
            'children' => $children,
            'childTotals' => $childTotals,
            'entries' => $entries,
            'perPage' => $perPage,
            'runningStart' => round($runningStart, 2),
            'branchId' => $branchId,
            'period' => $period,
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'movement' => [
                'debit' => (float) ($movement->dr ?? 0),
                'credit' => (float) ($movement->cr ?? 0),
                'net' => (float) ($movement->dr ?? 0) - (float) ($movement->cr ?? 0),
                'lines' => (int) ($movement->n ?? 0),
            ],
            'directMovement' => $direct ? [
                'debit' => (float) $direct->dr,
                'credit' => (float) $direct->cr,
                'net' => (float) $direct->dr - (float) $direct->cr,
                'lines' => (int) $direct->n,
            ] : null,
        ]);
    }

    /**
     * معرّفات المركز وذرّيّته — بجولاتٍ لا بعوديّةٍ لكل عقدة.
     *
     * وبحدٍّ للعمق: شجرةٌ فيها حلقة لا تُوقف الصفحة.
     */
    protected function descendantIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        for ($depth = 0; $depth < 12 && $frontier; $depth++) {
            $frontier = CostCenter::whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')->all();

            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * «رئيسيٌّ» صفةٌ تُشتقّ من الأب، لا خانةٌ تُؤشَّر إلى جانبه.
     *
     * **ما كان**
     *
     * `is_main` و`parent_id` حقلان مستقلّان يُحفظ كلٌّ منهما كما أُرسل. فمن
     * أشّر «مركز تكلفة رئيسي ؟» واختار أباً في الوقت نفسه خرج بسجلٍّ يناقض
     * نفسه: رئيسيٌّ وله أب. والشجرةُ تبنيه ابناً، والإحصاءُ يعدّه رئيسياً،
     * والقائمةُ تصفه بحسب أيّهما قرأت — فيُقرأ المركزُ الواحد على وجهين.
     *
     * وعكسُه أضرّ: من لم يختر أباً ولم يؤشّر الخانة خرج «فرعياً بلا أب» —
     * لا هو في الجذور ولا تحت أحد.
     *
     * **ما صار**
     *
     * الأبُ هو الحقيقةُ الوحيدة: بلا أبٍ فالمركزُ جذرٌ، ومع أبٍ ففرع. ولا
     * سبيلَ إلى حالةٍ ثالثة تناقض نفسها.
     */
    private function resolveParent(Request $request): ?int
    {
        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;

        // تأشيرُ «رئيسي» يعني رفعَ الأب لا مناقضتَه
        if ($request->boolean('is_main')) {
            return null;
        }

        return $parentId;
    }

    /**
     * الأبُ المختار لا يجوز أن يكون المركزَ نفسَه ولا واحداً من ذرّيّته.
     *
     * وإلا انفصل الفرعُ عن الشجرة في حلقةٍ مغلقة: `buildTree` لا تبلغه أبداً
     * فيختفي من الشاشة، وهو قائمٌ في القاعدة يُنسَب إليه إنفاق.
     */
    private function guardParentCycle(?int $parentId, int $costCenterId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $costCenterId) {
            throw ValidationException::withMessages([
                'parent_id' => 'لا يصحّ أن يكون المركز أباً لنفسه.',
            ]);
        }

        if (in_array($parentId, $this->descendantIds($costCenterId), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'لا يصحّ أن يكون الأبُ مركزاً فرعياً تحت هذا المركز.',
            ]);
        }
    }

    public function store_account(Request $request)
    {
        $validated = $request->validateWithBag('storeAccount', [
            'code' => 'required|unique:cost_centers,code|max:10',
            'parent_id' => 'nullable|exists:cost_centers,id',
            'name' => 'required|string|max:255',
        ], [
            'code.required' => 'يجب إدخال الكود.',
            'code.unique' => 'هذا الكود مستخدم من قبل.',
            'parent_id.exists' => 'الحساب الرئيسي غير موجود.',
            'name.required' => 'اسم الحساب مطلوب.',
        ]);

        try {
            // تفرّد الكود مضمونٌ في قواعد التحقّق أعلاه؛ الفحص الذي كان
            // هنا يستعمل `===` معاملَ استعلام — وليس من معاملات SQL
            $costCenter = new CostCenter;

            // الأبُ يحكم الصفة: بلا أبٍ جذرٌ، ومعه فرع
            $costCenter->parent_id = $this->resolveParent($request);
            $costCenter->is_main = $costCenter->parent_id === null ? 1 : 0;

            $costCenter->code = $request->code;
            $costCenter->name = $request->name;
            $costCenter->created_by = auth()->id();
            $costCenter->save();
            AccountingPeriodOptions::forget();

            // تسجيل السجل
            OperationLog::record(
                'created',
                'finance_log',
                $costCenter->id, // ID النشاط المرتبط
                'تم إضافة مركز تكلفة جديد **'.$request->name.'**'
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الحساب بنجاح',
                'data' => $costCenter,
            ], 200);
        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'store_account');

            return response()->json([
                'success' => false,
                'message' => 'حدثت أخطاء في التحقق.',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    /**
     * مراكزُ التكلفة التي يراها المستخدمُ الحالي.
     *
     * ضابطُ الفرع «مشاركة مراكز التكلفة» كان مطبَّقاً في `index` وحدها،
     * بينما `getTree` و`getParents` و`getChildren` تجلب الجدول كلَّه. فالأرقامُ
     * في الترويسة تعدّ مراكزَ الفرع والشجرةُ تحتها تعرض مراكز الفروع كلِّها —
     * رقمان متناقضان في شاشةٍ واحدة، وتسريبُ أسماءِ فرعٍ إلى غيره.
     */
    private function scopedCostCenters()
    {
        $query = CostCenter::query();
        $branch = auth()->user()?->branch;

        if ($branch) {
            $share = $branch->settings()->where('key', 'share_cost_center')->first();

            if ($share && $share->pivot->status == 0) {
                $query->whereHas('createdBy', fn ($q) => $q->where('branch_id', $branch->id));
            }
        }

        return $query;
    }

    /**
     * صلاحياتُ أزرار الشجرة — تُقرأ مرّةً واحدة للصفحة كلِّها.
     *
     * `@permission` يُطلق استعلاماً في كلّ استدعاء، وهو يقع على أربعة أزرارٍ
     * في كلّ صفّ: شجرةٌ من مئتَي مركزٍ ثمانمئةُ استعلامٍ لصفحةٍ واحدة —
     * والجوابُ هو هو في كلّ صفّ، إذ الصلاحيةُ للمستخدم لا للمركز.
     *
     * فتُقرأ هنا أربعَ مرّات وتُمرَّر رايةً إلى القالب.
     */
    private function buttonPermissions(): array
    {
        return [
            'view' => hasPermission('account.cost_centers.view'),
            'create' => hasPermission('account.cost_centers.create'),
            'edit' => hasPermission('account.cost_centers.edit'),
            'delete' => hasPermission('account.cost_centers.delete'),
        ];
    }

    /**
     * جذورُ الشجرة وقد رُبطت ذرّيّتُها — بلا استعلامٍ لكلّ عقدة.
     *
     * الصفوفُ تُحمَّل مرّةً واحدة وتُربط في الذاكرة، فشجرةٌ من مئة مركزٍ
     * استعلامٌ واحد لا مئة. والترتيبُ بالكود داخل كلّ طبقة: مراكزُ الأخ
     * الواحد متجاورةٌ كما تُقرأ.
     */
    private function scopedTree()
    {
        $centers = $this->scopedCostCenters()
            ->withCount('children')
            ->orderBy('code')
            ->get();

        $byParent = $centers->groupBy(fn ($c) => $c->parent_id ?? 0);
        $visible = $centers->keyBy('id');

        foreach ($centers as $center) {
            $center->setRelation('children', $byParent->get($center->id, collect()));
        }

        /*
            جذورُ العرض: من لا أبَ له، **ومن أبوه خارج النطاق**.

            ضابطُ «مشاركة مراكز التكلفة» يحجب مراكزَ الفروع الأخرى، فقد يبقى
            مركزٌ ظاهرٌ أبوه محجوب. والمشيُ من الجذور وحدها لا يبلغه أبداً،
            فيختفي من الشاشة وهو قائمٌ يُنسَب إليه إنفاق. وعرضُه في المستوى
            الأوّل أصدقُ من إخفائه.
        */
        return $centers
            ->filter(fn ($c) => $c->parent_id === null || ! $visible->has($c->parent_id))
            ->values();
    }

    /**
     * الشجرةُ ترميزاً جاهزاً — لتحديث الجدول بعد إضافةٍ أو تعديلٍ أو حذف.
     *
     * **ما كان**
     *
     * الجدولُ يعرض الجذورَ وحدها، ولا تُرى فروعُ مركزٍ إلا بالنقر عليه —
     * فتحلّ محلَّ الجذور. فالشجرةُ لا تُرى شجرةً في أيّ لحظة: إمّا طبقةٌ أو
     * طبقةٌ أخرى، ولا سبيلَ إلى رؤية مركزَين من عمقَين معاً.
     *
     * وكانت إلى جانبه شجرةُ `jstree` في عمودٍ مستقلّ تُحمَّل من CDN خارجيّ:
     * شجرتان لبيانٍ واحد، إحداهما لا تُظهر كوداً ولا نوعاً ولا فروعاً.
     *
     * **ما صار**
     *
     * جدولٌ شجريٌّ واحد يُطوى ويُبسَط، مصدرُ ترميزه قالبٌ واحد (`tree-row`)
     * تقرؤه الصفحةُ عند فتحها ويقرؤه هذا الطريقُ عند كلّ تغيير — فلا يفترق
     * ما يُبنى في الخادم عمّا يُبنى بعد الحفظ.
     */
    public function treeRows()
    {
        return response()->json([
            'success' => true,
            'html' => view('account::cost_centers.partials.tree-body', [
                'roots' => $this->scopedTree(),
                'can' => $this->buttonPermissions(),
            ])->render(),
        ]);
    }

    public function getTree()
    {
        $accounts = CostCenter::all();

        $tree = $this->buildTree($accounts);

        return response()->json($tree);
    }

    private function buildTree($accounts, $parentId = null)
    {
        $branch = [];
        foreach ($accounts as $account) {
            if ($account->parent_id == $parentId) {
                $children = $this->buildTree($accounts, $account->id);
                $branch[] = [
                    'id' => $account->id,
                    'text' => $account->name,
                    'children' => $children,
                ];
            }
        }

        return $branch;
    }

    /**
     * المراكز الجذرية — أوّل ما يُعرض في جدول التفاصيل.
     *
     * الردّ كان الصفوف الخام، فيعرض الجدول الاسمَ والكود لا غير. صار يحمل
     * النوع وعدد الأبناء واسم الأب، فيقرأ المستخدم موقع المركز من الشجرة
     * بلا أن يتنقّل فيها.
     */
    public function getParents()
    {
        return response()->json(
            $this->presentCostCenters(
                CostCenter::withCount('children')->whereNull('parent_id')->orderBy('code')->get()
            )
        );
    }

    public function getChildren($id)
    {
        return response()->json(
            $this->presentCostCenters(
                CostCenter::withCount('children')->with('parent:id,name')
                    ->where('parent_id', $id)->orderBy('code')->get()
            )
        );
    }

    /** صياغة موحّدة لصفوف الجدول */
    private function presentCostCenters($centers)
    {
        return $centers->map(fn ($center) => [
            'id' => $center->id,
            'name' => $center->name,
            'code' => $center->code,
            'is_main' => (int) ($center->is_main ?? 0),
            'children_count' => $center->children_count ?? 0,
            'parent_name' => $center->parent->name ?? null,
        ]);
    }

    public function getNextCode(CostCenter $parent)
    {
        $lastChildCode = CostCenter::where('parent_id', $parent->id)->max('code');

        $nextCode = $lastChildCode ? $lastChildCode + 1 : $parent->code.'1';

        return response()->json(['nextCode' => $nextCode]);
    }

    public function getAccountDetails($parentId)
    {
        try {
            // العثور على الحساب
            $account = CostCenter::find($parentId);

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ], 404);
            }

            if ($account->parent_id == null) {
                $category = $account->name;
            }
            $category = $this->getRootParent($parentId);

            return response()->json([
                'success' => true,
                'mainAccountName' => $account->name,
                'category' => $category,
            ], 200);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'getAccountDetails');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الحساب',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRootParent($accountId)
    {
        try {
            $account = CostCenter::find($accountId);

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ], 404);
            }

            while ($account->parent) {
                $account = $account->parent;
            }

            return $account->name;

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'getRootParent');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الحساب الجد',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($parentId)
    {
        try {
            $account = CostCenter::findOrFail($parentId);
            OperationLog::record(
                'deleted',
                'finance_log',
                $account->id, // ID النشاط المرتبط
                'تم حذف مركز التكلفة **'.$account->name.'**'
            );
            $account->delete(); // الحذف
            AccountingPeriodOptions::forget();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الحساب بنجاح',
            ], 200);
        } catch (ModelNotFoundException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'destroy');

            return response()->json([
                'success' => false,
                'message' => 'الحساب غير موجود',
            ], 404);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'destroy');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف',
            ], 500);
        }

    }

    public function edit($id)
    {
        try {
            $costCenter = CostCenter::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $costCenter,
            ], 200);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'edit');

            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب بيانات العنصر. الرجاء المحاولة مرة أخرى.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|unique:cost_centers,code,'.$id.'|max:10',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:cost_centers,id',
        ], [
            'code.required' => 'يجب إدخال الكود.',
            'code.unique' => 'هذا الكود مستخدم من قبل.',
            'name.required' => 'اسم الحساب مطلوب.',
            'parent_id.exists' => 'الحساب الرئيسي غير موجود.',
        ]);

        try {
            // تحديث العنصر
            $costCenter = CostCenter::findOrFail($id);

            $parentId = $this->resolveParent($request);
            $this->guardParentCycle($parentId, $costCenter->id);

            // الأبُ يحكم الصفة: بلا أبٍ جذرٌ، ومعه فرع
            $costCenter->parent_id = $parentId;
            $costCenter->is_main = $parentId === null ? 1 : 0;

            $costCenter->code = $request->code;
            $costCenter->name = $request->name;
            $costCenter->update();
            AccountingPeriodOptions::forget();

            OperationLog::record(
                'updated',
                'finance_log',
                $costCenter->id, // ID النشاط المرتبط
                'تم تعديل حساب التكلفة **'.$request->name.'**'
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث مركز التكلفة بنجاح.',
            ], 200);
        } catch (ValidationException $e) {
            /*
                خطأُ التحقّق رسالةٌ للمستخدم لا عطبٌ في النظام. وكان يقع في
                `\Exception` أدناه فيُردّ 500 برسالة «حدث خطأ» — والسببُ
                الحقيقيُّ يُطوى.
            */
            return response()->json([
                'success' => false,
                'message' => 'حدثت أخطاء في التحقق.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.CostCenters', 'update');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث. الرجاء المحاولة مرة أخرى.',
            ], 500);
        }
    }
}
