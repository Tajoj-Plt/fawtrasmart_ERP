<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Competitor;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * كتالوج المنافسين — الشاشة التي تملأ قائمة الاختيار في «رصد المنافسين»
 * داخل ملاحظة الزيارة.
 *
 * الرصد كان يكتب اسم المنافس نصّاً حرّاً في كل مرة، فيتفرّق المنافس الواحد
 * على عدّة أسماء ولا يُجمع حضورُه في تقرير. الاسم يُعرَّف هنا مرّةً ويُختار
 * هناك.
 *
 * **الحذف مقيَّد**: منافسٌ له أرصادٌ محفوظة لا يُحذف — حذفُه يُفرِّغ اسمَه من
 * تقارير سابقة (المفتاح الأجنبي `nullOnDelete`). البديل تعطيلُه: يختفي من
 * قائمة الاختيار وتبقى أرصاده مقروءة.
 */
class CompetitorController extends Controller
{
    public const LOG_MODULE = 'ClientCompetitors';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients_competitors_view')->only('index');
        $this->middleware('permission:clients_competitors_add')->only('store');
        $this->middleware('permission:clients_competitors_edit')->only(['update', 'toggle']);
        $this->middleware('permission:clients_competitors_delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = in_array($request->input('status'), ['active', 'inactive'], true) ? $request->input('status') : '';

        /* فرعُ الشريط العلويّ — الكتالوجُ واحدٌ للمنشأة (منه يختار كلُّ مندوب)،
           لكنّ **الأرصادَ** تتبع عملاءَها: عمودُ «الأرصاد» وبلاطةُ «لهم أرصاد» عن
           عملاء الفرع المختار وما تحته. وقفلُ الحذف يبقى على الأرصاد كلِّها
           (`observations_count`) — منافسٌ رُصد في فرعٍ آخر لا يُعرض للحذف. */
        $node = HeaderBranchScope::nodeId();
        $inBranch = fn ($q) => $q->when($node, fn ($o) => $o->whereHas('client', fn ($c) => $c->inTreeNode($node)));

        /* فرعٌ في الشريط ⇒ القائمةُ نفسُها على الفرع افتراضاً: المنافسون الذين
           رُصدوا عند عملائه (قال المستخدم إن الشاشة «لا تعطي حسب الفرع» حين
           تغيّرت الأعدادُ وبقيت القائمةُ كاملة). والكتالوجُ كلُّه بمفتاحٍ واحد
           (`observed=0`) — لإضافة اسمٍ أو تعديله. وبلا فرعٍ: الكتالوجُ كما كان. */
        $observed = $request->has('observed') ? $request->boolean('observed') : $node !== null;
        $scoped = fn ($q) => $q->when($observed, fn ($c) => $c->whereHas('observations', $inBranch));

        $competitors = $scoped(Competitor::withCount(['observations', 'observations as branch_observations_count' => $inBranch]))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // البلاطاتُ على ما تعرضه القائمة (الفرعُ أو الكتالوجُ كلُّه)، لا على البحث
        $stats = [
            'total' => $scoped(Competitor::query())->count(),
            'active' => $scoped(Competitor::where('is_active', true))->count(),
            'inactive' => $scoped(Competitor::where('is_active', false))->count(),
            'observations' => Competitor::whereHas('observations', $inBranch)->count(),
        ];

        $headerBranch = $node ? Branch::findCached($node) : null;

        return view('client::competitors.index', compact('competitors', 'search', 'status', 'stats', 'headerBranch', 'observed'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('competitors', 'name')],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'اسم المنافس مطلوب.',
            'name.unique' => 'هذا المنافس مسجَّل من قبل.',
        ]);

        $competitor = Competitor::create($validated + ['is_active' => true]);

        OperationLog::record('competitor_added', self::LOG_MODULE, $competitor, 'إضافة منافس «'.$competitor->name.'»');

        return redirect()->route('clients.competitors.index')->with('success', 'تمت إضافة المنافس.');
    }

    public function update(Request $request, $id)
    {
        $competitor = Competitor::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('competitors', 'name')->ignore($competitor->id)],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'اسم المنافس مطلوب.',
            'name.unique' => 'هذا المنافس مسجَّل من قبل.',
        ]);

        $competitor->update($validated);

        OperationLog::record('competitor_updated', self::LOG_MODULE, $competitor, 'تعديل منافس «'.$competitor->name.'»');

        return redirect()->route('clients.competitors.index')->with('success', 'تم تعديل المنافس.');
    }

    /** التعطيل بديلُ الحذف لمنافسٍ له أرصاد — يختفي من الاختيار وتبقى أرصاده. */
    public function toggle($id)
    {
        $competitor = Competitor::findOrFail($id);
        $competitor->update(['is_active' => ! $competitor->is_active]);

        OperationLog::record(
            $competitor->is_active ? 'competitor_activated' : 'competitor_deactivated',
            self::LOG_MODULE,
            $competitor,
            ($competitor->is_active ? 'تفعيل' : 'تعطيل').' منافس «'.$competitor->name.'»'
        );

        return redirect()->back()->with('success', $competitor->is_active ? 'تم تفعيل المنافس.' : 'تم تعطيل المنافس — لن يظهر في قائمة الاختيار.');
    }

    public function destroy($id)
    {
        $competitor = Competitor::withCount('observations')->findOrFail($id);

        if ($competitor->observations_count > 0) {
            return redirect()->back()->with(
                'error',
                'لا يمكن حذف «'.$competitor->name.'»: له '.$competitor->observations_count.' رصداً محفوظاً. عطّله بدل حذفه لتبقى تقاريره سليمة.'
            );
        }

        $name = $competitor->name;
        $competitor->delete();

        OperationLog::record('competitor_deleted', self::LOG_MODULE, (int) $id, 'حذف منافس «'.$name.'»');

        return redirect()->route('clients.competitors.index')->with('success', 'تم حذف المنافس.');
    }
}
