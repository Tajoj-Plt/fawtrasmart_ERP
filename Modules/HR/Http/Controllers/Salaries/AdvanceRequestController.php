<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\SalaryAdvanceRequest;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Requests\AdvanceRequestRequest;
use Modules\HR\Support\AdvanceApprovers;
use Modules\HR\Support\SalaryAdvanceService;

/**
 * «طلبات السلف» — الموظفُ يطلب سلفةً (لنفسه) ويتابع الطلبات.
 *
 * الطلبُ يُقدَّم باسم المستخدم نفسِه (`loans.create`). أمّا **القائمةُ
 * والعرضُ والسحب** فيحدّدها نطاقُ الرؤية لا صلاحيةُ «طلباتي»: صاحبُ
 * `data_scope_own` (أو من لا نطاقَ له) يرى طلباتِه هو وحده، والأوسعُ
 * نطاقاً يرى من يشملهم. العرضُ `loans.show`، والسحبُ قبل البتّ
 * (`loans.create`) غيرُ «إلغاء السلفة» الإداريّ (`loans.cancel`).
 *
 * المعتمِدُ من `AdvanceApprovers` وحدها (أقسامُ «إعدادات السلف» ومن يملك
 * `loans.approve`) — لا قائمةُ مستخدمي النظام.
 */
class AdvanceRequestController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:loans.create', ['only' => ['index', 'cancel', 'create', 'store']]);
        $this->middleware('permission.role:loans.show', ['only' => ['show']]);
    }

    /** الطلباتُ المرئية: نطاقُ الموظفين (وفرعُ الشريط العلويّ) — `data_scope_own` = طلباتُه هو. */
    private function visibleQuery()
    {
        return $this->scopeToEmployees(SalaryAdvanceRequest::query());
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $this->visibleQuery()->with(['treasury', 'approvedBy', 'approver', 'employee']);

        if ($request->filled('stage')) {
            $this->applyStage($query, (string) $request->stage);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('requested_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('requested_date', '<=', $request->date_to);
        }

        $perPage = in_array((int) $request->per_page, [15, 25, 50, 100], true) ? (int) $request->per_page : 15;
        $requests = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $base = $this->visibleQuery();
        $statistics = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'active' => (clone $base)->where('status', 'approved')->where('is_settled', false)->count(),
            'closed' => (clone $base)->whereIn('status', ['rejected', 'cancelled'])->count(),
        ];

        $advanceSummary = null;
        if ($user->employee_id && ($emp = Employee::find($user->employee_id))) {
            $advanceSummary = $emp->getAdvanceSummary();
        }

        return view('hr::salaries.advance_requests.index', compact('requests', 'statistics', 'advanceSummary'));
    }

    /** تصفيةٌ بمرحلة دورة الحياة — المرحلةُ تُحسب من أعمدة، فتُترجَم إليها. */
    public static function applyStage($query, string $stage): void
    {
        match ($stage) {
            'pending', 'rejected', 'cancelled' => $query->where('status', $stage),
            'approved' => $query->where('status', 'approved')->whereNull('disbursed_at'),
            'disbursed' => $query->where('status', 'approved')->whereNotNull('disbursed_at')->where('is_settled', false)
                ->where(fn ($q) => $q->whereNull('settled_amount')->orWhere('settled_amount', '<=', 0))
                ->where(fn ($q) => $q->whereNull('paid_installments')->orWhere('paid_installments', 0)),
            'repaying' => $query->where('status', 'approved')->whereNotNull('disbursed_at')->where('is_settled', false)
                ->where(fn ($q) => $q->where('settled_amount', '>', 0)->orWhere('paid_installments', '>', 0)),
            'settled' => $query->where('status', 'approved')->where('is_settled', true),
            default => null,
        };
    }

    public function create()
    {
        $user = Auth::user();

        if (! $user->employee_id) {
            return redirect()->route('hr.advance-requests.index')->with('error', 'ليس لديك سجل موظف مرتبط — طلب السلفة يُقدَّم باسم موظف.');
        }

        $employee = Employee::with(['treasuryEmployee.treasury', 'advanceAccount', 'department'])->find($user->employee_id);

        // ⛔ شرط محاسبي: لا يمكن طلب سلفة دون حساب سلف خاص بالموظف
        if (! $employee || ! $employee->hasAdvanceAccount()) {
            return redirect()->route('hr.advance-requests.index')
                ->with('error', 'لا يمكن طلب سلفة: لا يوجد حساب سلف مرتبط بك في شجرة الحسابات. يرجى التواصل مع إدارة الموارد البشرية.');
        }

        $advanceSummary = $employee->getAdvanceSummary();
        $approverUsers = AdvanceApprovers::for($user);
        $approverDepartments = AdvanceApprovers::departments();

        return view('hr::salaries.advance_requests.create', compact('employee', 'advanceSummary', 'approverUsers', 'approverDepartments'));
    }

    public function store(AdvanceRequestRequest $request)
    {
        $user = Auth::user();

        if (! $user->employee_id) {
            return back()->withInput()->with('error', 'ليس لديك سجل موظف مرتبط');
        }

        $employee = Employee::find($user->employee_id);
        if (! $employee || ! $employee->hasAdvanceAccount()) {
            return redirect()->route('hr.advance-requests.index')
                ->with('error', 'لا يمكن طلب سلفة: لا يوجد حساب سلف مرتبط بك في شجرة الحسابات. يرجى التواصل مع إدارة الموارد البشرية.');
        }

        $validated = $request->validated();

        // المعتمِدُ من القائمة المضبوطة نفسِها — لا أيُّ مستخدمٍ يُرسَل رقمُه
        if (! AdvanceApprovers::allows((int) $validated['approver_user_id'], $user)) {
            return back()->withInput()->withErrors([
                'approver_user_id' => 'الموظف المختار ليس من معتمِدي السلف المضبوطين في إعدادات السلف.',
            ]);
        }

        try {
            DB::beginTransaction();

            $advanceRequest = SalaryAdvanceRequest::create([
                'employee_id' => $employee->id,
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'requested_date' => $validated['requested_date'],
                'status' => 'pending',
                'approver_user_id' => (int) $validated['approver_user_id'],
            ]);

            ModelsLog::create([
                'type' => 'salary_advance_request_log',
                'type_id' => $advanceRequest->id,
                'type_log' => 'log',
                'description' => 'قُدِّم طلب السلفة بمبلغ '.CurrencySettings::format($advanceRequest->amount),
                'created_by' => $user->id,
            ]);

            $employeeName = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? ''));

            // الوصفُ يحمل «#رقم» — منه يستخرج بوبُ الإشعار رقمَ الطلب للاعتماد السريع
            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => (int) $validated['approver_user_id'],
                'title' => 'طلب سلفة جديد',
                'description' => 'طلب سلفة جديد من الموظف '.$employeeName.
                    ' بمبلغ '.CurrencySettings::format($validated['amount']).
                    ' - رقم الطلب #'.$advanceRequest->id,
                'type' => 'advance_request',
                'read' => 0,
                'is_scheduled' => 0,
                'is_sent' => 1,
            ]);

            DB::commit();

            return redirect()->route('hr.advance-requests.show', $advanceRequest->id)
                ->with('success', 'أُرسل طلب السلفة إلى المعتمِد، وستُخطَر بقراره.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.AdvanceRequest', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء إرسال الطلب: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $request = SalaryAdvanceRequest::with(['employee.advanceAccount', 'treasury', 'approvedBy', 'approver', 'disbursedBy', 'cancelledBy'])
            ->findOrFail($id);

        // طلبٌ خارج نطاق المستخدم يُردّ 403
        $this->authorizeEmployeeScope($request->employee_id);

        $logs = ModelsLog::where('type', 'salary_advance_request_log')
            ->where('type_id', $request->id)
            ->with('user')
            ->orderByDesc('id')
            ->get();

        return view('hr::salaries.advance_requests.show', compact('request', 'logs'));
    }

    /**
     * سحبُ الموظف طلبَه قبل البتّ فيه — «ملغى» لا «مرفوض»: الرفضُ قرارُ
     * المعتمِد، والإلغاءُ قرارُ صاحب الطلب.
     */
    public function cancel($id)
    {
        $advance = SalaryAdvanceRequest::where('status', 'pending')->findOrFail($id);
        $this->authorizeEmployeeScope($advance->employee_id);

        try {
            DB::transaction(function () use ($advance) {
                app(SalaryAdvanceService::class)->cancel($advance, 'سحبه الموظف قبل البتّ فيه');

                // إشعارُ الطلب عند المعتمِد لم يعد ينتظر قراراً
                notifications::where('type', 'advance_request')
                    ->where('description', 'like', '%#'.$advance->id)
                    ->where('read', 0)
                    ->update(['read' => 1]);
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceRequest', 'cancel');

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.advance-requests.index')->with('success', 'سُحب الطلب وأُغلق «ملغى».');
    }
}
