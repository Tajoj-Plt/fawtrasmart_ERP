<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosSession;
use App\Models\shifts_pos;
use App\Models\User;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Requests\StoreShiftRequest;
use Modules\Pos\Http\Requests\UpdateShiftRequest;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosShiftCategory;

/**
 * ورديّاتُ نقطة البيع.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * **بلا أيّ فحص صلاحية** — كـ`DevicesController` تماماً. وقواعدُ التحقّق
 * ورسائلُها مكتوبةً مرّتين حرفياً في `store()` و`update()`.
 *
 * و«الوردية» نفسُها لم تكن ورديةً: جدولُ `shifts_pos` كان اسماً وأباً
 * ومرفقاً — **بلا `start_time` ولا `end_time` ولا أيام أسبوع**. أي شجرةَ
 * تسمياتٍ لا مدًى زمنياً. فلا يمكن السؤال «أفتَح الكاشيرُ جلسته خارج
 * ورديته؟» ولا بناءُ تقرير التزامٍ بالورديات.
 */
class ShiftController extends Controller
{
    use ScopesToPosAccess;

    /** أيامُ الأسبوع كما تُعرض — الأحد أوّلاً كما في التقويم المحلّي. */
    public const WEEK_DAYS = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function __construct()
    {
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
        $this->middleware('permission.role:pos.shifts.view|pos.shifts.manage', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:pos.shifts.manage', ['only' => [
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ]]);
    }

    public function index(Request $request)
    {
        $query = shifts_pos::with(['category:id,name,color', 'branch:id,name', 'user:id,name']);

        /*
            ═══════════ الورديةُ تُعرض للجميع — بلا نطاقِ رؤية ═══════════

            كان هنا حصران: `scopeToBranch()` ثم `scopeToAssignedUser()`.
            وكلاهما خطأٌ في هذا الموضع بعينه.

            لأنّ الورديةَ **تعريفٌ لا عهدة**: «الصباحية 08:00 — 16:00» وصفُ
            وقتٍ، كقائمة العملات وشرائح الضريبة. لا مالَ فيها ولا مستنداتِ
            أحد. وحقلُ «الموظف المسؤول» تسميةٌ لمن يقودها، لا حاجزُ رؤية.

            وحصرُها يكسر عملها: الكاشيرُ يفتح جلسةً فلا يجد ورديتَه في
            القائمة لأنها لم تُسنَد إليه بالاسم، أو لأنها سُجّلت على فرعٍ
            آخر. فيختار ورديةً ليست ورديتَه لينفُذ — وتنهار تقاريرُ
            الورديات كلُّها.

            وما يُحرَس هو **الجلسة** لا الوردية: جلساتُ الموظف تُقيَّد
            بشجرة الإدارة (`scopeToVisibleSessions`)، وفتحُ الجلسة يُقيَّد
            بنافذة الوردية وبجهاز صاحبها. أمّا قائمةُ التعريفات فمفتوحة.

            والفرعُ يبقى **فلتراً اختيارياً** في الشاشة لمن أراد التضييق —
            لا قيداً مفروضاً.
        */

        $query
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));

        $shifts = $query
            ->withCount(['sessions as open_sessions_count' => fn ($q) => $q->where('status', PosSession::STATUS_ACTIVE)])
            ->latest()
            ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
                ? (int) $request->input('per_page')
                : 15)
            ->withQueryString();

        return view('pos::settings.shift.index', [
            'shifts' => $shifts,
            'categories' => $this->categoryOptions(),
            'branches' => $this->branchOptions(),
            'weekDays' => self::WEEK_DAYS,
            'canManage' => hasPermission('pos.shifts.manage') || $this->hasFullPosScope(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('pos::settings.shift.create', $this->formData());
    }

    public function store(StoreShiftRequest $request)
    {
        $data = $this->prepare($request->validated());

        try {
            if ($request->hasFile('attachment')) {
                $data['attachment'] = $request->file('attachment')->store('shifts-attachments', 'public');
            }

            $shift = shifts_pos::create($data);

            OperationLog::record('created', 'pos_shift', $shift, 'إضافة وردية نقطة بيع: '.$shift->name);

            return redirect()->route('pos.settings.shift.index')
                ->with('success', 'تم إضافة الوردية بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Shift', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ البيانات. يرجى المحاولة مرة أخرى.');
        }
    }

    public function show($id)
    {
        $shift = $this->findInScope($id);

        /*
            ═══════════ ما يرتبط بالوردية ═══════════

            كانت الشاشةُ تعرض عشرَ جلساتٍ وحدها — قائمةً بلا حصيلة. فمن يفتح
            بطاقةَ الوردية لا يعرف كم جلسةً عليها إجمالاً، ولا كم باعت، ولا
            كم مرّةً اختلّ صندوقُها، ولا مَن عمل عليها.

            وكلُّ ذلك مرتبطٌ بها عبر `pos_sessions.shift_id` — فالجلسةُ هي
            الجسرُ إلى الفواتير وحركات الدرج والأجهزة والكاشيرين.

            والحصيلةُ تُحسب باستعلامٍ مجموعٍ واحد لا باستعلامٍ لكل رقم.
        */
        $sessionIds = PosSession::where('shift_id', $shift->id)->pluck('id');

        $stats = PosSession::where('shift_id', $shift->id)
            ->selectRaw('
                COUNT(*) AS sessions_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS open_count,
                COALESCE(SUM(total_sales), 0) AS sales,
                COALESCE(SUM(total_transactions), 0) AS transactions,
                SUM(CASE WHEN ABS(COALESCE(difference, 0)) > 0.01 THEN 1 ELSE 0 END) AS variance_count,
                COALESCE(SUM(CASE WHEN difference > 0 THEN difference ELSE 0 END), 0) AS over_total,
                COALESCE(SUM(CASE WHEN difference < 0 THEN -difference ELSE 0 END), 0) AS short_total
            ', [PosSession::STATUS_ACTIVE])
            ->first();

        return view('pos::settings.shift.show', [
            'shift' => $shift->load(['category:id,name,color', 'children:id,name,parent_id', 'branch:id,name', 'user:id,name']),
            'weekDays' => self::WEEK_DAYS,
            'stats' => $stats,

            'recentSessions' => PosSession::where('shift_id', $shift->id)
                ->with(['user:id,name', 'device:id,device_name'])
                ->latest('started_at')
                ->limit(10)
                ->get(),

            // الكاشيرون الذين عملوا عليها — عبر جلساتها
            'operators' => PosSession::where('shift_id', $shift->id)
                ->with('user:id,name')
                ->selectRaw('user_id, COUNT(*) AS sessions_count, COALESCE(SUM(total_sales), 0) AS sales')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderByDesc('sales')
                ->limit(6)
                ->get(),

            // والأجهزةُ التي فُتحت عليها
            'devices' => PosSession::where('shift_id', $shift->id)
                ->with('device:id,device_name')
                ->selectRaw('device_id, COUNT(*) AS sessions_count')
                ->whereNotNull('device_id')
                ->groupBy('device_id')
                ->orderByDesc('sessions_count')
                ->limit(6)
                ->get(),

            /*
                حركاتُ الدرج المنتظِرة اعتماداً على جلسات هذه الوردية —
                هي ما يعطّل إقفالَها، فتُعرض هنا لا في شاشةٍ أخرى.
            */
            'pendingMovements' => $sessionIds->isEmpty()
                ? 0
                : PosCashMovement::whereIn('session_id', $sessionIds)
                    ->where('status', PosCashMovement::STATUS_PENDING)
                    ->count(),
        ]);
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $shift = $this->findInScope($id);

        return view('pos::settings.shift.edit', $this->formData() + ['shift' => $shift]);
    }

    public function update(UpdateShiftRequest $request, $id)
    {
        $shift = $this->findInScope($id);

        $data = $this->prepare($request->validated());

        try {
            if ($request->hasFile('attachment')) {
                if ($shift->attachment && Storage::disk('public')->exists($shift->attachment)) {
                    Storage::disk('public')->delete($shift->attachment);
                }

                $data['attachment'] = $request->file('attachment')->store('shifts-attachments', 'public');
            }

            $shift->update($data);

            OperationLog::record('updated', 'pos_shift', $shift, 'تعديل وردية نقطة بيع: '.$shift->name);

            return redirect()->route('pos.settings.shift.index')
                ->with('success', 'تم تحديث الوردية بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Shift', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث البيانات.');
        }
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        $shift = $this->findInScope($id);

        try {
            if ($shift->children()->exists()) {
                return back()->with('error', 'لا يمكن حذف هذه الوردية لأن لديها ورديات فرعية.');
            }

            /*
                وردياتٌ لها جلسات لا تُحذف بل تُعطَّل — بنفس قاعدة الأجهزة.

                كان الفحصُ على الورديات الفرعية وحدها، فتُحذف ورديةٌ لها
                مئةُ جلسةٍ سابقة فتفقد تلك الجلساتُ اسمَ ورديتها في كل تقرير.
            */
            $sessionsCount = PosSession::where('shift_id', $shift->id)->count();

            if ($sessionsCount > 0) {
                $shift->update(['is_active' => false]);

                return redirect()->route('pos.settings.shift.index')
                    ->with('success', "الوردية «{$shift->name}» لها {$sessionsCount} جلسة سابقة، فعُطِّلت بدل حذفها حفاظاً على تقاريرها.");
            }

            if ($shift->attachment && Storage::disk('public')->exists($shift->attachment)) {
                Storage::disk('public')->delete($shift->attachment);
            }

            $name = $shift->name;
            $shift->delete();

            OperationLog::record('deleted', 'pos_shift', (int) $id, "حذف وردية نقطة بيع: {$name}");

            return redirect()->route('pos.settings.shift.index')
                ->with('success', "تم حذف الوردية «{$name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Shift', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف الوردية.');
        }
    }

    /* ═══════════════════════ مساعدات ═══════════════════════ */

    /** صلاحيةُ التحرير — لا الرؤية: القائمةُ مفتوحةٌ للجميع. */
    private function canManageShifts(): bool
    {
        return hasPermission('pos.shifts.manage') || $this->hasFullPosScope();
    }

    private function authorizeManage(): void
    {
        if (! $this->canManageShifts()) {
            abort(403, 'غير مصرح لك بإدارة ورديات نقاط البيع.');
        }
    }

    /** تجهيزُ البيانات — الفرعُ المقفَل، والأيامُ مرتَّبةً بلا تكرار. */
    private function prepare(array $data): array
    {
        $data['branch_id'] = $this->resolveWriteBranchId((int) $data['branch_id']);
        // الفرعُ والموظّفُ حقلان مستقلّان في التحقّق — والربطُ بينهما هنا
        $this->assertEmployeeBelongsToBranch(
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['branch_id'] ? (int) $data['branch_id'] : null
        );

        /*
            «مهلة التأخّر» فارغةً تعني **صفراً** لا افتراضَ القاعدة (١٥).

            من مسح الحقلَ قصد إلغاءَ المهلة؛ وإسقاطُ المفتاح هنا يُعيدها إلى
            ربع ساعةٍ لم يطلبها. فتُحسم صراحةً قبل شبكة الأمان.
        */
        if (array_key_exists('grace_minutes', $data)) {
            $data['grace_minutes'] = (int) ($data['grace_minutes'] ?? 0);
        }

        if (array_key_exists('days_of_week', $data)) {
            $days = array_values(array_unique(array_map('intval', $data['days_of_week'] ?? [])));
            sort($days);

            // مصفوفةٌ فارغة تعني «كل الأيام» — تُخزَّن `null` لا `[]`
            $data['days_of_week'] = $days ?: null;
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // وما بقي من `null` صريحٍ إلى عمودٍ لا يقبله يُسقَط فيأخذ افتراضَه
        $data = $this->dropNullsForNotNullColumns('shifts_pos', $data);

        return $data;
    }

    private function findInScope($id): shifts_pos
    {
        $shift = shifts_pos::findOrFail($id);

        /*
            ولا يُردّ أحدٌ عن بطاقة وردية بحجّة الفرع — انظر `index()`:
            التعريفُ مفتوح، والمحروسُ هو الجلسة. وكان الردُّ 403 يمنع
            الكاشيرَ من قراءة أوقات ورديته إن سُجّلت على فرعٍ آخر.
        */
        return $shift;
    }

    private function formData(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'branches' => $this->branchOptions(),
            /*
                الموظفون في نطاق المستخدم لا كلُّ موظفي المنشأة.

                كانت `User::whereNotNull('employee_id')->get()` — فمشرفُ فرعٍ
                يرى موظفي الفروع كلِّها ويُسند الورديةَ إلى من ليس تحته.
            */
            /*
                `employee:id,branch_id` يلزم لتصفية القائمة بالفرع في الشاشة —
                بدونه لا تعرف الواجهةُ لأيّ فرعٍ ينتمي كلُّ موظف، فتعرضهم
                جميعاً ويُسنَد موظّفُ فرعٍ إلى وردية فرعٍ آخر.
            */
            'users' => User::whereNotNull('employee_id')
                ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
                ->with('employee:id,branch_id')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']),
            'weekDays' => self::WEEK_DAYS,
            'canSelectBranch' => $this->canSelectBranch(),
            'currentBranchId' => $this->currentEffectiveBranchId(),
        ];
    }

    /**
     * تصنيفاتُ الورديات المتاحة للاختيار.
     *
     * ═══════════ ما كانت ═══════════
     *
     *     shifts_pos::whereNull('parent_id')->orderBy('name')
     *
     * أي أنّ كلَّ ورديةٍ جذرٍ تُعرض **تصنيفاً**: الوردية الصباحية الحقيقية
     * تظهر خياراً للمسائية. ولم يكن ثمّة سبيلٌ لإنشاء تصنيفٍ إلّا بإنشاء
     * ورديةٍ وهميّةٍ تتسرّب بعدها إلى قائمة الورديات وتقاريرها.
     *
     * ═══════════ وما صارت ═══════════
     *
     * كِيانٌ مستقلّ يُدار من [ShiftCategoriesController]. والمعطَّلُ
     * مستبعَدٌ من القائمة — كما تُستبعد الورديةُ المعطَّلة من قائمة فتح
     * الجلسة.
     */
    private function categoryOptions()
    {
        $query = PosShiftCategory::active()->ordered();

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds ?: [0]);
        }

        return $query->get(['id', 'name', 'color', 'branch_id']);
    }

    private function branchOptions()
    {
        $query = Branch::query()->orderBy('name');

        $ids = $this->accessibleBranchIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get(['id', 'name']);
    }
}
