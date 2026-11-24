<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Log as ModelsLog;
use App\Models\SalaryItem;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * بنودُ الرواتب — المصدرُ الواحد لتعريف الاستحقاقات والاستقطاعات.
 *
 * كان إلى جانبها «كتالوج البدلات والاستقطاعات» (`salary_component_types`)
 * يعرّف الشيءَ نفسَه مرّةً ثانية ليضيف علمَين: «خاضع للتأمينات» و«يدخل
 * أجرَ نهاية الخدمة». والعلَمان صارا على البند نفسِه (ترحيل
 * `2026_09_24_100000`)، ومساراتُ الكتالوج تُحوِّل إلى هنا.
 *
 * الشاشةُ تعرض **تعريفاتِ** البنود فقط (`SalaryItem::scopeCatalog`): نُسَخُ
 * البند داخل العقود والقوالب والقسائم صفوفٌ في الجدول نفسِه، وكانت تظهر
 * هنا وتُعدَّل وتُحذف من القائمة — أي حذفُ سطرٍ من قسيمةٍ مصروفة.
 */
class SalaryItemsController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        // كلُّ فعلٍ بصلاحيته (مجموعة `salary_items.*`)؛ التفعيلُ/التعطيلُ تعديلٌ للسجلّ
        $this->middleware('permission.role:salary_items.index', ['only' => ['index']]);
        $this->middleware('permission.role:salary_items.show', ['only' => ['show']]);
        $this->middleware('permission.role:salary_items.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:salary_items.edit', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission.role:salary_items.destroy', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = SalaryItem::query()
            ->catalog()
            ->with(['Account:id,name,code']);

        $this->withUsageCounts($query);

        if ($request->filled('name')) {
            $term = trim((string) $request->name);
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', (int) $request->type);
        }

        if ($request->filled('is_insurable') && Schema::hasColumn('salary_items', 'is_insurable')) {
            $query->where('is_insurable', $request->is_insurable === '1');
        }

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $salaryItems = $query
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($perPage)
            ->appends($request->except('page'));

        if ($request->ajax()) {
            return response()->json([
                'html' => view('hr::salaries.salary_items.partials.table', compact('salaryItems'))->render(),
            ]);
        }

        $totals = [
            'all' => SalaryItem::query()->catalog()->count(),
            'additions' => SalaryItem::query()->catalog()->where('type', SalaryItem::TYPE_ADDITION)->count(),
            'deductions' => SalaryItem::query()->catalog()->where('type', SalaryItem::TYPE_DEDUCTION)->count(),
            'insurable' => Schema::hasColumn('salary_items', 'is_insurable')
                ? SalaryItem::query()->catalog()->where('is_insurable', true)->count()
                : 0,
        ];

        return view('hr::salaries.salary_items.index', compact('salaryItems', 'totals'));
    }

    public function create()
    {
        return view('hr::salaries.salary_items.create', [
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function show($id)
    {
        $salaryItem = $this->findDefinition($id);
        $salaryItem->load(['Account:id,name,code', 'componentType']);

        $usage = $this->usageOf($salaryItem);

        $logs = ModelsLog::where('type', 'salary_log')
            ->where('type_id', $salaryItem->id)
            ->with('user.branch')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('hr::salaries.salary_items.show', compact('salaryItem', 'logs', 'usage'));
    }

    public function edit($id)
    {
        $salaryItem = $this->findDefinition($id);

        return view('hr::salaries.salary_items.edit', [
            'salaryItem' => $salaryItem,
            'accounts' => $this->accountOptions(),
            'usage' => $this->usageOf($salaryItem),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);

        try {
            $salaryItem = SalaryItem::create($validated);

            $this->log($salaryItem->id, 'تم إضافة بند الراتب «'.$salaryItem->name.'»');

            return redirect()->route('SalaryItems.show', $salaryItem->id)->with('success', 'تم إضافة بند الراتب بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.SalaryItems', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء إضافة بند الراتب — لم يُحفظ شيء، وبياناتك باقية في النموذج.');
        }
    }

    public function update(Request $request, $id)
    {
        $salaryItem = $this->findDefinition($id);
        $validated = $this->validatedPayload($request, $salaryItem);

        try {
            $before = $salaryItem->only(['name', 'type', 'status', 'amount', 'calculation_formula', 'is_insurable', 'in_eos_wage', 'account_id']);
            $salaryItem->update($validated);

            $changed = array_keys(array_diff_assoc(
                array_map(fn ($v) => (string) (is_bool($v) ? (int) $v : $v), $salaryItem->only(array_keys($before))),
                array_map(fn ($v) => (string) (is_bool($v) ? (int) $v : $v), $before)
            ));
            $this->log($salaryItem->id, 'تم تعديل بند الراتب «'.$salaryItem->name.'»'
                .($changed ? ' — الحقول: '.implode('، ', array_map(fn ($c) => $this->fieldLabel($c), $changed)) : ''));

            return redirect()->route('SalaryItems.show', $salaryItem->id)->with('success', 'تم تحديث بند الراتب بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.SalaryItems', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث بند الراتب — لم يُحفظ شيء، وبياناتك باقية في النموذج.');
        }
    }

    public function destroy($id)
    {
        $salaryItem = $this->findDefinition($id);
        $usage = $this->usageOf($salaryItem);

        /*
            التعريفُ المستعمَل في عقدٍ أو قسيمةٍ لا يُحذف: نُسَخُه تبقى،
            لكنّ العقدَ يُعاد فتحُه فلا يجد بندَه في القائمة، والنسخُ الجديدة
            لا ترث علمَ التأمينات. التعطيلُ يُخفيه من الاختيار ويُبقي الأثر.

            نُسَخُ «قوالب الرواتب» لا تمنع الحذف: القوالبُ أُلغيت (2026-09-24)
            وصفوفُها باقيةٌ في الجدول بياناتٍ تاريخيةً لا يقرؤها شيء.
        */
        if ($usage['contracts'] > 0 || $usage['slips'] > 0) {
            return back()->with('error',
                'لا يُحذف البند «'.$salaryItem->name.'»: مستعمَلٌ في '
                .$usage['contracts'].' عقد و'.$usage['slips'].' قسيمة. عطّله بدل حذفه.');
        }

        try {
            $name = $salaryItem->name;
            $salaryItem->delete();

            $this->log((int) $id, 'تم حذف بند الراتب «'.$name.'»');

            return redirect()->route('SalaryItems.index')->with('success', 'تم حذف بند الراتب بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.SalaryItems', 'destroy');

            return back()->with('error', 'حدث خطأ أثناء حذف بند الراتب.');
        }
    }

    public function toggleStatus($id)
    {
        $salaryItem = $this->findDefinition($id);

        try {
            $salaryItem->status = (int) $salaryItem->status === SalaryItem::STATUS_ACTIVE
                ? SalaryItem::STATUS_STOPPED
                : SalaryItem::STATUS_ACTIVE;
            $salaryItem->save();

            $active = (int) $salaryItem->status === SalaryItem::STATUS_ACTIVE;
            $this->log($salaryItem->id, ($active ? 'تم تنشيط' : 'تم تعطيل').' بند الراتب «'.$salaryItem->name.'»');

            return back()->with('success', $active ? 'تم تنشيط بند الراتب بنجاح' : 'تم تعطيل بند الراتب بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.SalaryItems', 'toggleStatus');

            return back()->with('error', 'حدث خطأ أثناء تغيير حالة البند.');
        }
    }

    /**
     * التحقّقُ وتجهيزُ الحمولة.
     *
     * الإخفاقُ يعيد إلى النموذج بمدخلاته (`ValidationException` ← `back()->withInput()`)
     * فتعود القيمُ من `old()` ويعلّم `hr::components.form-ux` الحقولَ بأسمائها.
     *
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?SalaryItem $existing = null): array
    {
        $uniqueName = Rule::unique('salary_items', 'name')
            ->where(fn ($q) => $q->where('type', (int) $request->input('type'))
                ->whereNull('salary_slips_id')
                ->whereNull('contracts_id')
                ->whereNull('salary_template_id')
                ->whereNull('origin'));
        if ($existing) {
            $uniqueName->ignore($existing->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'type' => 'required|in:'.SalaryItem::TYPE_ADDITION.','.SalaryItem::TYPE_DEDUCTION,
            'status' => 'required|in:'.SalaryItem::STATUS_ACTIVE.','.SalaryItem::STATUS_STOPPED,
            'description' => 'nullable|string|max:2000',
            'salary_item_value' => 'required|in:'.SalaryItem::VALUE_TYPE_AMOUNT.','.SalaryItem::VALUE_TYPE_CALCULATION_FORMULA,
            'amount' => 'nullable|numeric|min:0|max:99999999|required_if:salary_item_value,'.SalaryItem::VALUE_TYPE_AMOUNT,
            'calculation_formula' => 'nullable|string|max:255|required_if:salary_item_value,'.SalaryItem::VALUE_TYPE_CALCULATION_FORMULA,
            'condition' => 'nullable|string|max:1000',
            'account_id' => 'nullable|exists:accounts,id',
            'is_insurable' => 'nullable|boolean',
            'in_eos_wage' => 'nullable|boolean',
            'reference_value' => 'nullable',
        ], [
            'name.required' => 'اسم البند مطلوب.',
            'name.unique' => 'يوجد بندٌ بهذا الاسم والنوع — الاسمُ هو ما تُورَّث به خصائصُ البند إلى نسخه في العقود والقسائم، فلا يتكرّر.',
            'type.required' => 'اختر نوع البند: مستحق أو مستقطع.',
            'status.required' => 'اختر حالة البند.',
            'salary_item_value.required' => 'اختر طريقة احتساب القيمة.',
            'amount.required_if' => 'أدخل المبلغ، أو اختر «صيغة حسابية».',
            'amount.numeric' => 'المبلغ رقمٌ فقط.',
            'amount.min' => 'المبلغ لا يكون سالباً — الاستقطاعُ يُحدَّد بنوع البند لا بإشارة المبلغ.',
            'calculation_formula.required_if' => 'أدخل الصيغة الحسابية، أو اختر «مبلغ ثابت».',
            'account_id.exists' => 'الحساب المختار غير موجود في دليل الحسابات.',
        ], [
            'name' => 'اسم البند',
            'type' => 'النوع',
            'status' => 'الحالة',
            'amount' => 'المبلغ',
            'calculation_formula' => 'الصيغة الحسابية',
            'account_id' => 'الحساب',
        ]);

        $isAmount = (int) $validated['salary_item_value'] === SalaryItem::VALUE_TYPE_AMOUNT;

        $payload = [
            'name' => trim($validated['name']),
            'type' => (int) $validated['type'],
            'status' => (int) $validated['status'],
            'description' => $validated['description'] ?? null,
            'salary_item_value' => (int) $validated['salary_item_value'],
            // طريقةٌ واحدة فعّالة: المبلغُ أو الصيغة، لا بقايا الأخرى
            'amount' => $isAmount ? $validated['amount'] : null,
            'calculation_formula' => $isAmount ? null : $validated['calculation_formula'],
            'condition' => $validated['condition'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'reference_value' => $request->boolean('reference_value'),
        ];

        if (Schema::hasColumn('salary_items', 'is_insurable')) {
            // الاستقطاعُ لا يكون أجراً خاضعاً ولا أجرَ نهاية خدمة
            $isAddition = $payload['type'] === SalaryItem::TYPE_ADDITION;
            $payload['is_insurable'] = $isAddition && $request->boolean('is_insurable');
            $payload['in_eos_wage'] = $isAddition && $request->boolean('in_eos_wage');
        }

        return $payload;
    }

    /** تعريفُ بندٍ — لا نسخةٌ داخل عقدٍ أو قسيمة. */
    private function findDefinition($id): SalaryItem
    {
        return SalaryItem::query()->catalog()->findOrFail($id);
    }

    /**
     * أين يُستعمَل التعريف: النسخُ تحمل اسمَه واتجاهَه (لا مرجعَ رقمياً إليه).
     *
     * @return array{contracts: int, slips: int}
     */
    private function usageOf(SalaryItem $item): array
    {
        $base = fn () => DB::table('salary_items')
            ->where('name', $item->name)
            ->where('type', $item->type)
            ->where('id', '!=', $item->id);

        return [
            'contracts' => (int) $base()->whereNotNull('contracts_id')->distinct()->count('contracts_id'),
            'slips' => (int) $base()->whereNotNull('salary_slips_id')->distinct()->count('salary_slips_id'),
        ];
    }

    /** عدّادا الاستعمال في استعلام القائمة — بلا N+1. */
    private function withUsageCounts(Builder $query): void
    {
        $query->select('salary_items.*')
            ->selectSub(fn ($q) => $q->from('salary_items as u')
                ->selectRaw('COUNT(DISTINCT u.contracts_id)')
                ->whereColumn('u.name', 'salary_items.name')
                ->whereColumn('u.type', 'salary_items.type')
                ->whereNotNull('u.contracts_id'), 'contracts_usage')
            ->selectSub(fn ($q) => $q->from('salary_items as u')
                ->selectRaw('COUNT(DISTINCT u.salary_slips_id)')
                ->whereColumn('u.name', 'salary_items.name')
                ->whereColumn('u.type', 'salary_items.type')
                ->whereNotNull('u.salary_slips_id'), 'slips_usage');
    }

    /** الحساباتُ للاختيار — الأعمدةُ الثلاثة فقط لا الصفُّ كاملاً. */
    private function accountOptions()
    {
        return Account::query()->orderBy('code')->get(['id', 'name', 'code']);
    }

    private function log(int $id, string $description): void
    {
        ModelsLog::create([
            'type' => 'salary_log',
            'type_id' => $id,
            'type_log' => 'log',
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    private function fieldLabel(string $column): string
    {
        return [
            'name' => 'الاسم',
            'type' => 'النوع',
            'status' => 'الحالة',
            'amount' => 'المبلغ',
            'calculation_formula' => 'الصيغة',
            'is_insurable' => 'الخضوع للتأمينات',
            'in_eos_wage' => 'أجر نهاية الخدمة',
            'account_id' => 'الحساب',
        ][$column] ?? $column;
    }
}
