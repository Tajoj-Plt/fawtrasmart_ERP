<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * التحصيلُ حسب طريقة الدفع — التقريرُ الذي كان مستحيلاً.
 *
 * ═════════════════════ لماذا مستحيلاً؟ ═════════════════════
 *
 * لأنّ المدفوعات **لم تكن تُحفظ**. `processPayments()` كانت تتحقّق وتكتب في
 * اللوغ وتنتهي، فالأثرُ الوحيد الباقي:
 *
 *   • `invoices.payment_method` — رقمٌ إن كانت الطريقةُ واحدة، ونصَّ JSON
 *     إن كانت متعدّدة. عمودٌ واحدٌ بنوعين، لا يُجمَع ولا يُفهرَس.
 *
 *   • `cash_amount`/`card_amount` في تفاصيل الجلسة — خانتان اثنتان تجمعان
 *     كلَّ ما ليس «أونلاين» في «نقد» وكلَّ ما هو أونلاين في «بطاقة». فمحفظةٌ
 *     إلكترونية وتحويلٌ بنكي وشبكةٌ تصير شيئاً واحداً.
 *
 * وهو أوّلُ ما يُطلب في أيّ نقطة بيع: «كم حصّلنا نقداً وكم شبكةً؟».
 *
 * ═════════════════════ والتسوية ═════════════════════
 *
 * `settled_at` يفصل ما وصل الحسابَ فعلاً عمّا هو في الطريق. فالنقدُ يدخل
 * الدرجَ فوراً، والشبكةُ تُودَع بعد يومٍ أو ثلاثة — والفرقُ بينهما هو ما
 * يجعل مطابقةَ كشف البنك ممكنة.
 */
class PaymentMethodReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_payment_methods';
    }

    public function title(): string
    {
        return 'تقرير التحصيل حسب طريقة الدفع — نقاط البيع';
    }

    public function groupings(): array
    {
        return [
            'method' => 'حسب طريقة الدفع',
            'date' => 'حسب التاريخ',
            'session' => 'حسب الجلسة',
            'branch' => 'حسب الفرع',
            'settlement' => 'حسب حالة التسوية',
        ];
    }

    private function dimension(): array
    {
        return match ($this->groupBy()) {
            'date' => [
                'select' => [DB::raw('DATE(pos_order_payments.created_at) as dim_id')],
                'group' => ['dim_id'],
                'label' => 'التاريخ',
            ],
            'session' => [
                'select' => ['pos_sessions.id as dim_id', 'pos_sessions.session_number as dim_label'],
                'group' => ['pos_sessions.id', 'pos_sessions.session_number'],
                'label' => 'الجلسة',
            ],
            'branch' => [
                'select' => ['branches.id as dim_id', 'branches.name as dim_label'],
                'group' => ['branches.id', 'branches.name'],
                'label' => 'الفرع',
            ],
            'settlement' => [
                'select' => [
                    DB::raw('CASE WHEN pos_order_payments.settled_at IS NULL THEN 0 ELSE 1 END as dim_id'),
                    DB::raw("CASE WHEN pos_order_payments.settled_at IS NULL THEN 'غير مُسوّى' ELSE 'مُسوّى' END as dim_label"),
                ],
                'group' => ['dim_id', 'dim_label'],
                'label' => 'حالة التسوية',
            ],
            default => [
                'select' => ['payment_methods.id as dim_id', 'payment_methods.name as dim_label'],
                'group' => ['payment_methods.id', 'payment_methods.name'],
                'label' => 'طريقة الدفع',
            ],
        };
    }

    /**
     * الاستعلامُ الأساس — من جدول المدفوعات لا من الفواتير.
     *
     * لا يرث `invoiceQuery()`: مصدرُ هذا التقرير `pos_order_payments`،
     * والفاتورةُ فيه علاقةٌ لا محور.
     */
    private function paymentQuery()
    {
        $query = DB::table('pos_order_payments')
            ->join('payment_methods', 'pos_order_payments.payment_method_id', '=', 'payment_methods.id')
            ->leftJoin('pos_sessions', 'pos_order_payments.session_id', '=', 'pos_sessions.id')
            ->leftJoin('branches', 'pos_order_payments.branch_id', '=', 'branches.id')
            ->whereNull('pos_order_payments.deleted_at')
            ->where('pos_order_payments.status', 'completed');

        $scope = $this->scope();

        $this->scopeReportPeriod($query, $scope, 'pos_order_payments.accounting_period_id', 'pos_order_payments.created_at');

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('pos_order_payments.branch_id', $branchIds ?: [0]);
        }

        $this->scopeToVisibleUsers($query, 'pos_sessions.user_id');

        $r = $this->request;

        return $query
            ->when($r->filled('session_id'), fn ($q) => $q->where('pos_order_payments.session_id', $r->session_id))
            ->when($r->filled('branch_id'), fn ($q) => $q->where('pos_order_payments.branch_id', $r->branch_id))
            ->when($r->filled('payment_method_id'), fn ($q) => $q->where('payment_methods.id', $r->payment_method_id))
            ->when($r->filled('employee_id'), fn ($q) => $q->where('pos_sessions.user_id', $r->employee_id))
            ->when($r->boolean('unsettled_only'), fn ($q) => $q->whereNull('pos_order_payments.settled_at'))
            ->when($this->filterCurrency($r), fn ($q, $c) => $q->where('pos_order_payments.currency', $c));
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        return collect(
            $this->paymentQuery()
                ->select(array_merge($dimension['select'], [
                    DB::raw('COUNT(*) as payments_count'),
                    DB::raw('COUNT(DISTINCT pos_order_payments.invoice_id) as invoices_count'),
                    DB::raw('SUM(CAST(pos_order_payments.amount AS DECIMAL(18,2))) as total_amount'),
                    DB::raw('SUM(CAST(pos_order_payments.change_amount AS DECIMAL(18,2))) as change_amount'),
                    DB::raw('SUM(CASE WHEN pos_order_payments.settled_at IS NULL THEN CAST(pos_order_payments.amount AS DECIMAL(18,2)) ELSE 0 END) as unsettled_amount'),
                    DB::raw('AVG(CAST(pos_order_payments.amount AS DECIMAL(18,2))) as avg_amount'),
                ]))
                ->groupBy(...$dimension['group'])
                ->get()
        )->pipe(fn ($rows) => $this->ensureLabels($rows))->map(function ($row) {
            $row->net_amount = (float) $row->total_amount;
            $row->settled_amount = round((float) $row->total_amount - (float) $row->unsettled_amount, 2);

            return $row;
        });
    }

    protected function totals($rows): array
    {
        return [
            'payments_count' => (int) $rows->sum('payments_count'),
            'invoices_count' => (int) $rows->sum('invoices_count'),
            'total_amount' => round((float) $rows->sum('total_amount'), 2),
            'change_amount' => round((float) $rows->sum('change_amount'), 2),
            'settled_amount' => round((float) $rows->sum('settled_amount'), 2),
            'unsettled_amount' => round((float) $rows->sum('unsettled_amount'), 2),
            'net_amount' => round((float) $rows->sum('net_amount'), 2),
            'rows_count' => $rows->count(),
        ];
    }

    public function exportColumns(): array
    {
        return [
            $this->dimension()['label'] => 'dim_label',
            'عدد الدفعات' => 'payments_count',
            'عدد الفواتير' => 'invoices_count',
            'إجمالي التحصيل' => fn ($r) => number_format((float) $r->total_amount, 2),
            'متوسط الدفعة' => fn ($r) => number_format((float) $r->avg_amount, 2),
            'الباقي المُعاد' => fn ($r) => number_format((float) $r->change_amount, 2),
            'مُسوّى' => fn ($r) => number_format((float) $r->settled_amount, 2),
            'غير مُسوّى' => fn ($r) => number_format((float) $r->unsettled_amount, 2),
        ];
    }
}
