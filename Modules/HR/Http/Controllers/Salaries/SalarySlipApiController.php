<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\SalaryAdvance;
use App\Models\SalaryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Controller للحصول على بيانات العقد والسلف للموظف
 * يستخدم في صفحة إنشاء قسيمة الراتب عبر AJAX
 *
 * ملاحظة (2026-09-24): هذا المتحكّم **غير مربوطٍ بأيّ مسار** — لا يذكره
 * `routes/` ولا `Modules/*\/routes`، ولا تستدعي أيُّ شاشةٍ أو ملف JS دوالَّه
 * (`getContractDetails` / `getEmployeeAdvances` / `getEmployeeContractData`).
 * أُبقي الملفّ مرجعاً فقط؛ لا تفترض أنه يعمل قبل ربطه بمسارٍ وحارس نطاق.
 */
class SalarySlipApiController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:payslips.create|payslips.edit', ['only' => ['getContractDetails', 'getEmployeeAdvances', 'getEmployeeContractData']]);
    }

    /**
     * الحصول على بيانات العقد والسلف للموظف
     */
    public function getEmployeeContractData(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employeeId = $request->employee_id;

        // البحث عن العقد الأحدث للموظف
        $contract = Contract::where('employee_id', $employeeId)
            ->where(function ($query) {
                // العقود التي لم تنتهي بعد أو التي لا تحتوي على تاريخ انتهاء
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->startOfDay());
            })
            ->orderBy('start_date', 'desc')
            ->first();

        // إذا لم نجد عقد ساري، نبحث عن أحدث عقد (حتى لو منتهي)
        if (! $contract) {
            $contract = Contract::where('employee_id', $employeeId)
                ->orderBy('start_date', 'desc')
                ->first();
        }

        // الحصول على السلف التي لم تكتمل والتي يجب خصمها من الراتب
        $advances = SalaryAdvance::where('employee_id', $employeeId)
            ->where('pay_from_salary', 1)
            ->where('paid_installments', '<', DB::raw('total_installments'))
            ->with(['payments' => function ($query) {
                $query->where('status', 'unpaid')
                    ->orderBy('due_date', 'asc');
            }])
            ->get();

        // تحضير بيانات الرد
        $response = [
            'success' => true,
            'contract' => null,
            'salary_items' => [],
            'advances' => [],
            'total_additions' => 0,
            'total_deductions' => 0,
            'basic_salary' => 0,
        ];

        if ($contract) {
            // الحصول على الراتب الأساسي من العقد
            // إذا كان عمود basic_salary موجود استخدمه، وإلا استخدم amount
            $basicSalary = $contract->basic_salary ?? $contract->amount ?? 0;

            $response['contract'] = [
                'id' => $contract->id,
                'basic_salary' => $basicSalary,
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
            ];

            $response['basic_salary'] = $basicSalary;

            // بنود الراتب من العقد نفسه فقط — «قوالب الرواتب» أُلغيت (2026-09-24)
            // ولا رجوعَ إلى قالبٍ عند خلوّ العقد من البنود.
            $contractItems = SalaryItem::where('contracts_id', $contract->id)->get();

            if ($contractItems->isNotEmpty()) {
                foreach ($contractItems as $item) {
                    $calculatedAmount = $item->amount;

                    // حساب القيمة إذا كانت هناك صيغة حسابية
                    if ($item->calculation_formula) {
                        try {
                            $formula = str_replace([
                                'basic',
                                'basic_amount',
                                'basic_salary',
                            ], $basicSalary, $item->calculation_formula);

                            // تأمين الصيغة من الأكواد الضارة
                            if (preg_match('/^[\d\s\+\-\*\/\.\(\)]+$/', $formula)) {
                                $calculatedAmount = eval("return $formula;");
                            }
                        } catch (\Exception $e) {
                            // في حالة خطأ في الصيغة، استخدام المبلغ الأصلي
                            $calculatedAmount = $item->amount;
                        }
                    }

                    $itemData = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'type' => $item->type,
                        'amount' => $calculatedAmount,
                        'calculation_formula' => $item->calculation_formula,
                        'description' => $item->description,
                        'account_id' => $item->account_id,
                    ];

                    $response['salary_items'][] = $itemData;

                    // حساب المجاميع
                    if ($item->type == 1) { // مستحق
                        $response['total_additions'] += $calculatedAmount;
                    } elseif ($item->type == 2) { // مستقطع
                        $response['total_deductions'] += $calculatedAmount;
                    }
                }
            }
        }

        // معالجة السلف والأقساط المستحقة
        foreach ($advances as $advance) {
            $unpaidInstallments = $advance->payments->filter(function ($payment) {
                return $payment->status === 'unpaid';
            })->take(1); // نأخذ أول قسط مستحق فقط

            foreach ($unpaidInstallments as $installment) {
                // جلب المبلغ من جدول installment_payments مباشرة
                $installmentAmount = floatval($installment->amount ?? 0);

                $response['advances'][] = [
                    'advance_id' => $advance->id,
                    'installment_id' => $installment->id,
                    'amount' => $installmentAmount,
                    'due_date' => $installment->due_date,
                    'description' => "قسط سلفة رقم {$advance->id}",
                    'installment_number' => $installment->installment_number ?? 'N/A',
                ];

                $response['total_deductions'] += $installmentAmount;
            }
        }

        return response()->json($response);
    }

    /**
     * الحصول على بيانات العقد فقط
     */
    public function getContractDetails($contractId): JsonResponse
    {
        $contract = Contract::with(['salaryItems', 'employee'])
            ->findOrFail($contractId);

        return response()->json([
            'success' => true,
            'contract' => $contract,
        ]);
    }

    /**
     * الحصول على السلف المستحقة للموظف
     */
    public function getEmployeeAdvances($employeeId): JsonResponse
    {
        $advances = SalaryAdvance::where('employee_id', $employeeId)
            ->where('pay_from_salary', 1)
            ->where('paid_installments', '<', DB::raw('total_installments'))
            ->with('payments')
            ->get();

        return response()->json([
            'success' => true,
            'advances' => $advances,
        ]);
    }
}
