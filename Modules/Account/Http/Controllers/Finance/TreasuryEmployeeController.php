<?php

namespace Modules\Account\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class TreasuryEmployeeController extends Controller
{
    /*
        ربطُ موظّفٍ بخزينة هو ما يمنحه الوصولَ إليها
        ({@see ScopesToEmployeeHierarchy::accessibleTreasuryIds()}). وكانت هذه
        الشاشةُ بلا نطاقٍ ولا تحقّق: تعرض روابطَ المنشأة كلَّها، ومن ملك
        الصلاحيةَ بنطاقٍ ضيّق يربط نفسه بأيّ خزينةٍ فيبلغها، ويحذف ربطَ
        غيره. والحفظُ يقبل الحقلين فارغين.

        الآن: الموظّفون = نطاقُ المستخدم، والخزائنُ = ما يبلغه هو (لا يمنح
        أحدٌ ما لا يملك). ومن لا قيدَ عليه (`null`) كما كان.
    */
    use ScopesToEmployeeHierarchy;

    /**
     * صلاحيات خزائن الموظفين — الكتالوج الجديد account.treasury_employees.*.
     * لم يكن لهذا المتحكّم أي حماية صلاحياتٍ إطلاقاً من قبل.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.treasury_employees.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.treasury_employees.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.treasury_employees.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.treasury_employees.delete', ['only' => ['delete']]);
    }

    /** الموظّفون الذين يديرهم المستخدم هنا — `null` بلا قيد. */
    private function scopedEmployees()
    {
        $ids = $this->accessibleEmployeeIds();

        return Employee::select('id', 'first_name', 'middle_name', 'nickname')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->get();
    }

    /** الخزائن التي يجوز له ربطُ غيره بها: ما يبلغه هو. */
    private function scopedTreasuriesQuery()
    {
        $ids = $this->accessibleTreasuryIds();

        return Account::whereIn('parent_id', [13, 15])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]));
    }

    private function inEmployeeScope($employeeId): bool
    {
        $ids = $this->accessibleEmployeeIds();

        return $ids === null || in_array((int) $employeeId, $ids, true);
    }

    /** التحقّق نفسُه للإضافة والتعديل — `$ignoreId` للتعديل. */
    private function validateLink(Request $request, ?int $ignoreId = null): array
    {
        $employeeIds = $this->accessibleEmployeeIds();
        $treasuryIds = $this->accessibleTreasuryIds();

        return $request->validate([
            'employee_id' => ['required', 'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $employeeIds === null ? $q : $q->whereIn('id', $employeeIds ?: [0]))],
            'treasury_id' => ['required', 'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->whereIn('parent_id', [13, 15])
                    ->when($treasuryIds !== null, fn ($w) => $w->whereIn('id', $treasuryIds ?: [0]))),
                Rule::unique('treasury_employees', 'treasury_id')
                    ->where(fn ($q) => $q->where('employee_id', $request->input('employee_id')))
                    ->ignore($ignoreId)],
        ], [
            'employee_id.required' => 'اختر الموظف.',
            'employee_id.exists' => 'الموظف غير موجود أو خارج نطاقك.',
            'treasury_id.required' => 'اختر الخزينة.',
            'treasury_id.exists' => 'الخزينة غير موجودة أو خارج نطاقك.',
            'treasury_id.unique' => 'هذا الموظف مربوطٌ بهذه الخزينة مسبقاً.',
        ]);
    }

    public function index()
    {
        $scopeIds = $this->accessibleEmployeeIds();
        $treasury_employees = TreasuryEmployee::when($scopeIds !== null, fn ($q) => $q->whereIn('employee_id', $scopeIds ?: [0]))
            ->orderBy('id', 'DESC')->paginate(10);
        $employees = $this->scopedEmployees();
        $treasuries = $this->scopedTreasuriesQuery()
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return view('account::finance.treasury_employee.index', compact('treasury_employees', 'treasuries', 'employees'));
    }

    public function create()
    {
        $employees = $this->scopedEmployees();
        $treasuries = $this->scopedTreasuriesQuery()
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return view('account::finance.treasury_employee.create', compact('treasuries', 'employees'));
    }

    public function store(Request $request)
    {
        $this->validateLink($request);

        // إنشاء سجل جديد في TreasuryEmployee
        $default = TreasuryEmployee::create([
            'treasury_id' => $request->treasury_id,
            'employee_id' => $request->employee_id,
        ]);

        // تسجيل النشاط في ModelsLog
        OperationLog::record(
            'created',
            'product_log',
            $default->id,
            sprintf(
                'تم تعيين الخزينة **%s** كخزينة افتراضية للموظف **%s %s %s**',
                $default->treasury->name,
                $default->employee->first_name,
                $default->employee->middle_name,
                $default->employee->nickname
            )
        );

        // إعادة التوجيه مع رسالة نجاح
        return redirect()
            ->route('finance_settings.treasury_employee')
            ->with(['success' => 'تمت إضافة خزينة الموظف بنجاح']);
    }

    public function edit($id)
    {
        $treasury_employee = TreasuryEmployee::findOrFail($id);
        if (! $this->inEmployeeScope($treasury_employee->employee_id)) {
            abort(403, 'هذا الربط لموظفٍ خارج نطاقك.');
        }
        $employees = $this->scopedEmployees();
        $treasuries = $this->scopedTreasuriesQuery()
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return view('account::finance.treasury_employee.edit', compact('treasury_employee', 'treasuries', 'employees'));
    }

    public function update(Request $request, $id)
    {
        $link = TreasuryEmployee::find($id);
        if (! $link) {
            return redirect()->route('finance_settings.treasury_employee')->with('error', 'هذا الربط غير موجود — ربما حُذف مسبقاً.');
        }
        if (! $this->inEmployeeScope($link->employee_id)) {
            abort(403, 'هذا الربط لموظفٍ خارج نطاقك.');
        }

        $this->validateLink($request, (int) $link->id);

        /* كانت بعد الحفظ فقرةٌ تبني وصفَ التغيير من متغيّراتٍ لم تُعرَّف قطّ
           (`$oldStorehouseId`، `$oldEmployee`…) وتكتبه بصنف `Log` غير
           مستورد — فكلُّ تعديلٍ يُحفظ ثم تسقط الشاشة بخطأ ٥٠٠، ولا يدري
           المستخدم أحُفظ أم لا. الأثرُ مسجَّلٌ هنا بقيمتيه قبل وبعد. */
        $before = ['treasury_id' => $link->treasury_id, 'employee_id' => $link->employee_id];

        $link->update([
            'treasury_id' => (int) $request->treasury_id,
            'employee_id' => (int) $request->employee_id,
        ]);

        // ربط موظّفٍ بخزينة صلاحيةُ صرفٍ فعلية — يُسجَّل تغييرُه
        OperationLog::record('updated', 'Account.TreasuryEmployee', $link,
            'عدّل ربط الخزينة بالموظّف رقم '.$link->id.' — من '.json_encode($before).' إلى '.json_encode($link->only(['treasury_id', 'employee_id'])));

        return redirect()
            ->route('finance_settings.treasury_employee')
            ->with(['success' => 'تم تحديث خزينة الموظف بنجاح']);
    }

    public function delete($id)
    {
        $link = TreasuryEmployee::find($id);

        // نقرةٌ ثانية أو صفحةٌ قديمة — لا صفحة ٤٠٤ عارية
        if (! $link) {
            return redirect()->route('finance_settings.treasury_employee')->with('error', 'هذا الربط غير موجود — ربما حُذف مسبقاً.');
        }
        if (! $this->inEmployeeScope($link->employee_id)) {
            return redirect()->route('finance_settings.treasury_employee')->with('error', 'لا يمكنك إلغاء ربط موظفٍ خارج نطاقك.');
        }

        $linkId = $link->id;

        $link->delete();

        OperationLog::record('deleted', 'Account.TreasuryEmployee', $linkId,
            'ألغى ربط الخزينة بالموظّف رقم '.$linkId);

        return redirect()
            ->route('finance_settings.treasury_employee')
            ->with(['success' => 'تم إلغاء ربط خزينة الموظف بنجاح']);
    }
}
