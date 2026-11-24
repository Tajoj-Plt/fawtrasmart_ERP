<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Employee;
use App\Models\TreasuryEmployee;
use App\Support\AccountingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * API Controller للحصول على خزينة الموظف
 * يستخدم في صفحة إنشاء السلفة عبر AJAX
 *
 * الخزينةُ: المربوطةُ بالموظف في «خزائن الموظفين» (`treasury_employees.treasury_id`
 * معرّفُ **حساب**)، وإلا «الخزينة الرئيسية» من «الحسابات المحاسبية» — كما
 * يحلّها `PayrollAccountant::resolveTreasury()`. وكان الاحتياطُ بحثاً بالاسم
 * العربي حرفياً، فلا يجد شيئاً في منشأةٍ سمّت خزينتها غير ذلك.
 *
 * والرصيدُ المُعاد **للعرض وحده** — قرارُ الصرف يُبنى على
 * `TreasuryBalanceGuard::lockAndAssert()` تحت قفل.
 */
class EmployeeTreasuryApiController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:loans.create|loans.disburse', ['only' => ['getEmployeeTreasury']]);
    }

    /**
     * الحصول على خزينة الموظف المحدد
     */
    public function getEmployeeTreasury(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employee = Employee::find($request->employee_id);

        // لا تُكشف خزينةُ موظفٍ خارج نطاق المستخدم بتغيير المعرّف في الطلب
        if (! $employee || ! $this->canAccessEmployee($employee->id)) {
            return response()->json([
                'success' => false,
                'message' => 'الموظف غير موجود أو خارج نطاقك',
            ], 404);
        }

        try {
            $treasury = null;
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $employee->id)->first();
            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $treasury = Account::find($treasuryEmployee->treasury_id);
            }

            $treasury = $treasury ?: AccountingSettings::account('main_treasury');

            if (! $treasury) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد خزينة متاحة — اربط «الخزينة الرئيسية» في الحسابات المحاسبية.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'employee' => [
                    'id' => $employee->id,
                    'name' => trim($employee->first_name.' '.$employee->middle_name),
                ],
                'treasury' => [
                    'id' => $treasury->id,
                    'name' => $treasury->name,
                    'balance' => $treasury->balance ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في جلب خزينة الموظف', ['employee_id' => $employee->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'تعذّر جلب خزينة الموظف.',
            ], 500);
        }
    }
}
