<?php

namespace Modules\Integration\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\BuildsAccountingDashboard;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;

/**
 * سندات الصرف بأنواعها الثلاثة (عام / لعميل / لمورد) — تفويضٌ إلى
 * {@see ExpensesController}، ومعه كل ما تلتزم به شاشة الويب من إعدادات:
 * الترقيم، الحسابات المحاسبية، الضرائب، الفترات. الصلاحية على المسار
 * (`account.expenses.*`).
 */
class ExpenseController extends Controller
{
    use BuildsAccountingDashboard, DelegatesWebActions;

    public function index(Request $request): JsonResponse
    {
        $this->forceDataListResponse($request);

        return app(ExpensesController::class)->getData($request);
    }

    /**
     * بيانات شاشة الإنشاء — **من create() نفسها** (`viewData()`) لا تجميعٌ
     * يدوي: نفس التصنيفات والموردين والخزائن (المسموح السحب منها) وحسابات
     * العملاء (بنطاق الفريق) وحسابات المصروفات والضرائب والرقم التالي.
     */
    public function createData(Request $request): JsonResponse
    {
        $controller = app(ExpensesController::class);
        $data = $this->viewData($controller->create($request));

        return response()->json([
            'success' => true,
            'data' => [
                'expenses_categories' => $data['expenses_categories'] ?? [],
                'suppliers' => $data['suppliers'] ?? [],
                'treasuries' => $data['treasuries'] ?? [],
                'accounts' => $data['accounts'] ?? [],
                'expense_leaf_accounts' => $data['expenseLeafAccounts'] ?? [],
                'taxs' => $data['taxs'] ?? [],
                'code' => $data['code'] ?? null,
                'MainTreasury' => $data['MainTreasury'] ?? null,
                'treasury_id' => $data['treasury_id'] ?? null,
                'account_setting' => $data['account_setting'] ?? null,
                'branches' => $data['branches'] ?? [],
                'preselect_type' => $data['preselectType'] ?? null,
                'preselect_account_id' => $data['preselectAccountId'] ?? null,
                'preselect_category_id' => $data['preselectCategoryId'] ?? null,
                'client_default_category_id' => $data['clientDefaultCategoryId'] ?? null,
                'can_expense_to_supplier' => $controller->canExpenseToSupplier($request->user()),
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $expense = Expense::with(['expenses_category', 'branch', 'account.client', 'supplier'])->findOrFail($id);
        $this->authorizeCreatorScope($expense->created_by);

        return response()->json(['success' => true, 'data' => $expense]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(ExpensesController::class)->store($request),
            'تم إنشاء سند الصرف بنجاح'
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(ExpensesController::class)->update($request, $id),
            'تم تعديل سند الصرف بنجاح'
        );
    }

    public function cancel($id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(ExpensesController::class)->cancel($id),
            'تم إلغاء سند الصرف بنجاح'
        );
    }

    /** delete() في الويب نفسُها cancel() — انظر ExpensesController::delete(). */
    public function destroy($id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(ExpensesController::class)->delete($id),
            'تم حذف سند الصرف بنجاح'
        );
    }
}
