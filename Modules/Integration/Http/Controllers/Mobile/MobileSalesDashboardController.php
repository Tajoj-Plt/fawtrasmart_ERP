<?php

namespace Modules\Integration\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Controllers\DashboardController;

/**
 * «أداء المندوبين» لتطبيق الجوال — **شاشة الويب نفسها** (`DashboardController::reps`) بلا حسابٍ ثانٍ.
 *
 * الفرعُ والفترةُ والترتيبُ ومجموعُ الفريق كلُّها كما يحسبها المتحكّم للقالب `sales::dashboard.reps`،
 * والصفوفُ صفوفُ `SalesDashboardMetrics::salesByEmployee()`. يُقرأ هنا ما يمرَّر إلى القالب ويُعاد JSON —
 * فما يراه المستخدم في التطبيق هو رقمُ الويب بعينه.
 *
 * الصلاحيةُ ومفتاحُ التطبيق وميزةُ الباقة على المسار (`routes/mobile_api.php`) كمسار الويب.
 */
class MobileSalesDashboardController extends Controller
{
    public function reps(Request $request): JsonResponse
    {
        $d = app(DashboardController::class)->reps($request)->getData();

        $period = $d['period'];
        $node = fn ($b) => ['id' => (int) $b->id, 'name' => $b->name];

        return response()->json(['success' => true, 'data' => [
            'period' => [
                'key' => $period->key,
                'label' => $period->label,
                'from' => $period->from->format('Y-m-d'),
                'to' => $period->to->format('Y-m-d'),
            ],
            'periods' => collect($d['periodTabs'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'sort' => $d['sort'],
            'sorts' => collect($d['sortOptions'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // الشهرُ الذي تُقاس عليه الأهداف
            'targetMonth' => $d['employeeTargetMonth']->translatedFormat('F Y'),
            'notice' => $d['periodNotice']['message'] ?? null,
            'branch' => [
                'selected' => $d['selectedBranchId'],
                'isMain' => (bool) $d['isMainBranch'],
                'includeChildren' => (bool) $d['includeChildren'],
                // المسارُ من جذر صلاحيته إلى العقدة المختارة، وأبناؤها المباشرون للنزول
                'lineage' => collect($d['branchLineage'])->map($node)->values(),
                'children' => collect($d['branchOptions'])->map($node)->values(),
            ],
            'team' => array_map(fn ($value) => round($value, 2), $d['team']),
            'reps' => collect($d['reps'])->values()->map(fn ($row, $i) => [
                'rank' => $i + 1,
                'employeeId' => $row['employee_id'],
                'name' => $row['name'],
                'invoices' => (int) $row['invoices'],
                'sales' => (float) $row['sales'],
                'returns' => (float) $row['returns_total'],
                'netSales' => (float) $row['net_sales'],
                'collections' => (float) $row['collections'],
                'refunds' => (float) $row['refunds'],
                'refundsCount' => (int) $row['refunds_count'],
                'cancelledReceipts' => (float) $row['cancelled_receipts'],
                'cancelledCount' => (int) $row['cancelled_count'],
                'expenses' => (float) $row['expenses'],
                'expenseDocuments' => (int) $row['expense_documents'],
                'visits' => (int) $row['visits'],
                'visitSuccess' => (int) $row['visit_success'],
                'visitNegative' => (int) $row['visit_negative'],
                'planned' => (int) $row['planned'],
                'planJustified' => (int) $row['plan_justified'],
                'salesTarget' => $row['sales_target'] ?: null,
                'collectionsTarget' => $row['collections_target'] ?: null,
            ]),
        ]]);
    }
}
