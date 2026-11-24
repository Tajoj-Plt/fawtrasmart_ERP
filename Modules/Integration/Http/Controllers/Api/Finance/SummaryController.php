<?php

namespace Modules\Integration\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Modules\Account\Http\Controllers\Concerns\BuildsAccountingDashboard;

/**
 * الملخّص المالي — صافي الفترة (قبض/صرف)، أرصدة الخزائن وحركتها، وآخر
 * السندات. كلّه بنطاق المستخدم عبر {@see BuildsAccountingDashboard} —
 * نفس ما تبنيه لوحة الماليّة في الويب: صاحب `data_scope_all` يرى الكل،
 * المشرف يرى نفسه ومن يديرهم، والموظف يرى نفسه.
 */
class SummaryController extends Controller
{
    use BuildsAccountingDashboard;

    public function __invoke(): JsonResponse
    {
        $revenueMovement = $this->movementSides(Account::REVENUES);
        $expenseMovement = $this->movementSides(Account::EXPENSES);

        $treasuries = $this->treasuryAccounts()->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'balance' => (float) $a->balance,
        ])->values();

        $recent = fn (string $model) => $this->recentVouchers($model, 5)
            ->map(fn ($v) => ['id' => $v->id, 'code' => $v->code, 'date' => $v->date, 'amount' => (float) $v->amount])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $this->dashboardPeriod()['label'] ?? null,
                'receipts_total' => $revenueMovement['credit'] - $revenueMovement['debit'],
                'expenses_total' => $expenseMovement['debit'] - $expenseMovement['credit'],
                'treasury_flow' => $this->treasuryFlow(),
                'treasuries' => $treasuries,
                'recent_receipts' => $recent(Receipt::class),
                'recent_expenses' => $recent(Expense::class),
            ],
        ]);
    }
}
