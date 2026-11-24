<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مبيعاتُ الجلسات والورديات.
 *
 * محورُه الجلسةُ لا الفاتورة، فيقرأ من `pos_sessions` مباشرةً: الإحصاءاتُ
 * محفوظةٌ فيها (`total_sales`، `total_cash`، `total_card`) ولا تحتاج تجميعاً
 * من الفواتير في كل عرض.
 */
class ShiftSalesReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_shift_sales';
    }

    public function title(): string
    {
        return 'تقرير مبيعات الجلسات والورديات — نقاط البيع';
    }

    public function groupings(): array
    {
        return [
            'session' => 'حسب الجلسة',
            'shift' => 'حسب الوردية',
            'cashier' => 'حسب الكاشير',
            'device' => 'حسب الجهاز',
            'branch' => 'حسب الفرع',
            'date' => 'حسب التاريخ',
        ];
    }

    private function dimension(): array
    {
        return match ($this->groupBy()) {
            'shift' => [
                'select' => ['shifts_pos.id as dim_id', 'shifts_pos.name as dim_label'],
                'group' => ['shifts_pos.id', 'shifts_pos.name'],
                'label' => 'الوردية',
            ],
            'cashier' => [
                'select' => ['users.id as dim_id', 'users.name as dim_label'],
                'group' => ['users.id', 'users.name'],
                'label' => 'الكاشير',
            ],
            'device' => [
                'select' => ['cashier_devices.id as dim_id', 'cashier_devices.device_name as dim_label'],
                'group' => ['cashier_devices.id', 'cashier_devices.device_name'],
                'label' => 'الجهاز',
            ],
            'branch' => [
                'select' => ['branches.id as dim_id', 'branches.name as dim_label'],
                'group' => ['branches.id', 'branches.name'],
                'label' => 'الفرع',
            ],
            'date' => [
                'select' => [DB::raw('DATE(pos_sessions.started_at) as dim_id')],
                'group' => ['dim_id'],
                'label' => 'التاريخ',
            ],
            default => [
                'select' => [
                    'pos_sessions.id as dim_id',
                    DB::raw("CONCAT(pos_sessions.session_number, ' — ', COALESCE(users.name, '')) as dim_label"),
                ],
                'group' => ['pos_sessions.id', 'dim_label'],
                'label' => 'الجلسة',
            ],
        };
    }

    private function sessionQuery()
    {
        $query = DB::table('pos_sessions')
            ->leftJoin('users', 'pos_sessions.user_id', '=', 'users.id')
            ->leftJoin('cashier_devices', 'pos_sessions.device_id', '=', 'cashier_devices.id')
            ->leftJoin('shifts_pos', 'pos_sessions.shift_id', '=', 'shifts_pos.id')
            ->leftJoin('branches', 'pos_sessions.branch_id', '=', 'branches.id')
            ->whereNull('pos_sessions.deleted_at');

        $scope = $this->scope();

        $this->scopeReportPeriod($query, $scope, 'pos_sessions.accounting_period_id', 'pos_sessions.ended_at');

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('pos_sessions.branch_id', $branchIds ?: [0]);
        }

        $this->scopeToVisibleUsers($query, 'pos_sessions.user_id');

        $r = $this->request;

        return $query
            ->when($r->filled('branch_id'), fn ($q) => $q->where('pos_sessions.branch_id', $r->branch_id))
            ->when($r->filled('device_id'), fn ($q) => $q->where('pos_sessions.device_id', $r->device_id))
            ->when($r->filled('shift_id'), fn ($q) => $q->where('pos_sessions.shift_id', $r->shift_id))
            ->when($r->filled('employee_id'), fn ($q) => $q->where('pos_sessions.user_id', $r->employee_id))
            ->when($r->filled('session_id'), fn ($q) => $q->where('pos_sessions.id', $r->session_id))
            ->when($r->filled('status'), fn ($q) => $q->where('pos_sessions.status', $r->status))
            ->when($this->filterCurrency($r), fn ($q, $c) => $q->where('pos_sessions.currency', $c));
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        return collect(
            $this->sessionQuery()
                ->select(array_merge($dimension['select'], [
                    DB::raw('COUNT(*) as sessions_count'),
                    DB::raw('SUM(pos_sessions.total_transactions) as invoices_count'),
                    DB::raw('SUM(CAST(pos_sessions.total_sales AS DECIMAL(18,2))) as total_amount'),
                    DB::raw('SUM(CAST(pos_sessions.total_returns AS DECIMAL(18,2))) as returns_amount'),
                    DB::raw('SUM(CAST(pos_sessions.total_cash AS DECIMAL(18,2))) as cash_amount'),
                    DB::raw('SUM(CAST(pos_sessions.total_card AS DECIMAL(18,2))) as card_amount'),
                    DB::raw('SUM(CAST(pos_sessions.opening_balance AS DECIMAL(18,2))) as opening_total'),
                    DB::raw('SUM(CAST(pos_sessions.difference AS DECIMAL(18,2))) as variance_total'),
                    DB::raw('MIN(pos_sessions.started_at) as first_start'),
                    DB::raw('MAX(pos_sessions.ended_at) as last_end'),
                ]))
                ->groupBy(...$dimension['group'])
                ->get()
        )->pipe(fn ($rows) => $this->ensureLabels($rows))->map(function ($row) {
            $row->net_amount = round((float) $row->total_amount - (float) $row->returns_amount, 2);
            $row->total_quantity = 0;
            $row->returns_quantity = 0;
            $row->net_quantity = 0;
            $row->returns_count = 0;

            // متوسّطُ الفاتورة — أدلُّ على أداء الكاشير من مجموعِ مبيعاته
            $row->avg_invoice = (int) $row->invoices_count > 0
                ? round((float) $row->total_amount / (int) $row->invoices_count, 2)
                : 0.0;

            return $row;
        });
    }

    protected function totals($rows): array
    {
        return [
            'sessions_count' => (int) $rows->sum('sessions_count'),
            'invoices_count' => (int) $rows->sum('invoices_count'),
            'total_amount' => round((float) $rows->sum('total_amount'), 2),
            'returns_amount' => round((float) $rows->sum('returns_amount'), 2),
            'net_amount' => round((float) $rows->sum('net_amount'), 2),
            'cash_amount' => round((float) $rows->sum('cash_amount'), 2),
            'card_amount' => round((float) $rows->sum('card_amount'), 2),
            'variance_total' => round((float) $rows->sum('variance_total'), 2),
            'rows_count' => $rows->count(),
        ];
    }

    public function exportColumns(): array
    {
        return [
            $this->dimension()['label'] => 'dim_label',
            'عدد الجلسات' => 'sessions_count',
            'عدد الفواتير' => 'invoices_count',
            'الرصيد الافتتاحي' => fn ($r) => number_format((float) $r->opening_total, 2),
            'إجمالي المبيعات' => fn ($r) => number_format((float) $r->total_amount, 2),
            'المرتجعات' => fn ($r) => number_format((float) $r->returns_amount, 2),
            'صافي المبيعات' => fn ($r) => number_format((float) $r->net_amount, 2),
            'نقداً' => fn ($r) => number_format((float) $r->cash_amount, 2),
            'بطاقات' => fn ($r) => number_format((float) $r->card_amount, 2),
            'متوسط الفاتورة' => fn ($r) => number_format((float) $r->avg_invoice, 2),
            'فرق الصندوق' => fn ($r) => number_format((float) $r->variance_total, 2),
        ];
    }
}
