<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\shifts_pos;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Requests\ShiftCategoryRequest;
use Modules\Pos\Models\PosShiftCategory;

/**
 * إدارةُ تصنيفات الورديات.
 *
 * ═════════════════════ لماذا شاشةٌ مستقلّة ═════════════════════
 *
 * كان «التصنيف» في شاشة الوردية حقلاً اسمه «التصنيف الرئيسي» خلفه
 * `shifts_pos.parent_id` — أي أنّ التصنيفَ **ورديةٌ أخرى**. فمن أراد
 * تصنيفاً وجب عليه إنشاءُ ورديةٍ وهميّةٍ لها وقتُ بدءٍ ونهاية وأيّامُ
 * دوام، ثم تظهر تلك الوهميّةُ في قائمة الورديات وفي تقاريرها وفي قائمة
 * الورديات المتاحة لفتح جلسة.
 *
 * ولا شاشةَ تُدار منها: لا قائمةَ ولا إضافةَ ولا حذف. ولذلك لم يستعملها
 * أحد — أربعُ ورديات في قاعدة البيانات، **صفرٌ** منها يستعمل `parent_id`.
 *
 * ═════════════════════ الصلاحيات ═════════════════════
 *
 * `pos.shift_categories.view` و`pos.shift_categories.manage` — مستقلّتان
 * عن `pos.shifts.*`.
 *
 * كانتا مربوطتين بها، وهو خلطُ مستويين: الوردياتُ تُنشأ كلَّ أسبوع
 * والتصنيفاتُ تُضبط مرّةً ثم تُترك. فمن يملك اليوميَّ لا يلزم أن يملك
 * البنيويّ.
 *
 * والخطرُ المعروف في صلاحيةٍ جديدة أنها تُنشأ في `permissions` ولا تُربَط
 * بدور، فتُقفَل الشاشةُ على من كان يفتحها — ويسدُّه
 * [الترحيل 2026_08_30_150000]: يمنحها لكلِّ دورٍ يملك نظيرَها على
 * الورديات، بالنظير لا بالتعميم.
 */
class ShiftCategoriesController extends Controller
{
    use ScopesToPosAccess;

    public function __construct()
    {
        // بوّابةُ الوحدة — أيُّ صلاحية نقاط بيع تفتح الباب، والتدقيقُ بعده
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });

        // مطابقةٌ لما يفرضه المسار — والتكرارُ مقصود، كما في `ShiftController`
        $this->middleware('permission.role:pos.shift_categories.view|pos.shift_categories.manage', ['only' => ['index']]);
        $this->middleware('permission.role:pos.shift_categories.manage', ['only' => [
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ]]);
    }

    public function index(Request $request)
    {
        $query = PosShiftCategory::with('branch:id,name');

        $this->scopeToBranch($query);

        $query
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));

        /*
            عددُ الورديات في كل تصنيف — هو ما يجعل القائمةَ مقروءة.

            تصنيفٌ فارغٌ يُحذف بلا تردّد، وتصنيفٌ عليه اثنتا عشرةَ وردية
            لا يُمسّ. وبدون العدد يُفتح كلُّ تصنيفٍ لمعرفة ذلك.
        */
        $categories = $query
            ->withCount([
                'shifts',
                'shifts as active_shifts_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->ordered()
            ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
                ? (int) $request->input('per_page')
                : 15)
            ->withQueryString();

        return view('pos::settings.shift_categories.index', [
            'categories' => $categories,
            'branches' => $this->branchOptions(),
            'canManage' => hasPermission('pos.shift_categories.manage') || $this->hasFullPosScope(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('pos::settings.shift_categories.create', $this->formData());
    }

    public function store(ShiftCategoryRequest $request)
    {
        $data = $this->prepare($request->validated());

        try {
            $category = PosShiftCategory::create($data);

            OperationLog::record('created', 'pos_shift_category', $category,
                'إضافة تصنيف ورديات: '.$category->name);

            return redirect()->route('pos.settings.shift_categories.index')
                ->with('success', "تم إضافة التصنيف «{$category->name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.ShiftCategory', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ التصنيف. يرجى المحاولة مرة أخرى.');
        }
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $category = $this->findInScope($id);

        return view('pos::settings.shift_categories.edit',
            $this->formData() + ['category' => $category]);
    }

    public function update(ShiftCategoryRequest $request, $id)
    {
        $category = $this->findInScope($id);

        $data = $this->prepare($request->validated());

        try {
            $category->update($data);

            OperationLog::record('updated', 'pos_shift_category', $category,
                'تعديل تصنيف ورديات: '.$category->name);

            return redirect()->route('pos.settings.shift_categories.index')
                ->with('success', "تم تحديث التصنيف «{$category->name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.ShiftCategory', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث التصنيف.');
        }
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        $category = $this->findInScope($id);

        try {
            /*
                تصنيفٌ عليه ورديات لا يُحذف.

                لا لأنّ الحذفَ يكسر شيئاً — `category_id` يقبل الفراغ —
                بل لأنّ الحذفَ الصامت يُفرِّغ تصنيفَ عشرِ ورديات دفعةً
                واحدةً بلا أن يرى المستخدمُ ما فقد. فيُطلَب منه نقلُها
                أوّلاً، فيصير الفعلُ مقصوداً.
            */
            $shiftsCount = shifts_pos::where('category_id', $category->id)->count();

            if ($shiftsCount > 0) {
                return back()->with('error',
                    "لا يمكن حذف التصنيف «{$category->name}»: عليه {$shiftsCount} وردية. "
                    .'انقلها إلى تصنيفٍ آخر أو أفرغ تصنيفها أولاً، أو عطِّل هذا التصنيف بدل حذفه.');
            }

            $name = $category->name;
            $category->delete();

            OperationLog::record('deleted', 'pos_shift_category', (int) $id,
                "حذف تصنيف ورديات: {$name}");

            return redirect()->route('pos.settings.shift_categories.index')
                ->with('success', "تم حذف التصنيف «{$name}» بنجاح");
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.ShiftCategory', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف التصنيف.');
        }
    }

    /* ═══════════════════════ مساعدات ═══════════════════════ */

    private function authorizeManage(): void
    {
        if (! hasPermission('pos.shift_categories.manage') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بإدارة تصنيفات الورديات.');
        }
    }

    private function formData(): array
    {
        return [
            'branches' => $this->branchOptions(),
            'canSelectBranch' => $this->canSelectBranch(),
            'currentBranchId' => $this->currentEffectiveBranchId(),
        ];
    }

    private function prepare(array $data): array
    {
        // الفرعُ المقفَل يغلب المُرسَل — كما في كل شاشات الوحدة
        $data['branch_id'] = $this->resolveWriteBranchId((int) $data['branch_id']);

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // لونٌ فارغٌ يُخزَّن `null` لا سلسلةً فارغة، فيقع على الافتراضي
        $data['color'] = filled($data['color'] ?? null) ? strtoupper($data['color']) : null;

        return $this->dropNullsForNotNullColumns('pos_shift_categories', $data);
    }

    private function findInScope($id): PosShiftCategory
    {
        $category = PosShiftCategory::findOrFail($id);

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null && ! in_array((int) $category->branch_id, $branchIds, true)) {
            abort(403, 'هذا التصنيف خارج نطاق صلاحياتك.');
        }

        return $category;
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
