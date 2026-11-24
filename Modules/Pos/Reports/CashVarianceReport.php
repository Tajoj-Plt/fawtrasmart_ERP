<?php

namespace Modules\Pos\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * فروقاتُ الصندوق — مؤشّرُ الرقابة النقدية الأوّل.
 *
 * ═════════════════════ ولماذا لم يكن موجوداً؟ ═════════════════════
 *
 * لأنّ الفرقَ لم يكن قابلاً للقياس. كان يُكتب في `pos_session_details`
 * بنوعٍ `cash_adjustment` — صفٌّ في جدولٍ تشغيلي بلا أثرٍ في دفتر الأستاذ.
 * وكان «المتوقَّع» يُحسب عند العرض بصيغةٍ مكتوبةٍ في خمسة مواضع
 * (`opening + total_cash - total_returns`) **بلا اعتبارٍ لحركات الدرج** —
 * لأنّ حركات الدرج لم تكن موجودة أصلاً.
 *
 * فالفرقُ الذي وُضع ليقيس الخطأ كان يقيس الحركاتِ المشروعةَ غيرَ المسجَّلة.
 *
 * والآن `expected_cash` مجمَّدٌ لحظةَ الإقفال ويشمل الدرج، و`difference`
 * له قيدٌ محاسبي، و`confirmed_by` يقول من اعتمده.
 */
class CashVarianceReport extends AbstractPosReport
{
    public function key(): string
    {
        return 'pos_cash_variance';
    }

    public function title(): string
    {
        return 'تقرير فروقات الصندوق — نقاط البيع';
    }

    public function permission(): string
    {
        return 'pos.reports.variance';
    }

    public function groupings(): array
    {
        return [
            'session' => 'حسب الجلسة',
            'cashier' => 'حسب الكاشير',
            'device' => 'حسب الجهاز',
            'branch' => 'حسب الفرع',
            'date' => 'حسب التاريخ',
        ];
    }

    private function dimension(): array
    {
        return match ($this->groupBy()) {
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
                'select' => [DB::raw('DATE(pos_sessions.ended_at) as dim_id')],
                'group' => ['dim_id'],
                'label' => 'التاريخ',
            ],
            default => [
                'select' => ['pos_sessions.id as dim_id', 'pos_sessions.session_number as dim_label'],
                'group' => ['pos_sessions.id', 'pos_sessions.session_number'],
                'label' => 'الجلسة',
            ],
        };
    }

    private function sessionQuery()
    {
        $query = DB::table('pos_sessions')
            ->leftJoin('users', 'pos_sessions.user_id', '=', 'users.id')
            ->leftJoin('cashier_devices', 'pos_sessions.device_id', '=', 'cashier_devices.id')
            ->leftJoin('branches', 'pos_sessions.branch_id', '=', 'branches.id')
            ->whereNull('pos_sessions.deleted_at')
            // الجلساتُ المقفلة وحدها لها فرق — النشطةُ لم تُعدّ بعد
            ->whereIn('pos_sessions.status', ['closed', 'pending_review']);

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
            ->when($r->filled('employee_id'), fn ($q) => $q->where('pos_sessions.user_id', $r->employee_id))
            ->when($r->filled('shift_id'), fn ($q) => $q->where('pos_sessions.shift_id', $r->shift_id))
            // الفروقُ وحدها — الجلساتُ المتوازنة لا تُراجَع
            ->when($r->boolean('variance_only'), fn ($q) => $q->whereRaw('ABS(COALESCE(pos_sessions.difference, 0)) > 0.01'))
            ->when($r->boolean('unconfirmed_only'), fn ($q) => $q->whereNull('pos_sessions.confirmed_at'))
            ->when($this->filterCurrency($r), fn ($q, $c) => $q->where('pos_sessions.currency', $c));
    }

    public function rows(): Collection
    {
        $dimension = $this->dimension();

        return collect(
            $this->sessionQuery()
                ->select(array_merge($dimension['select'], [
                    DB::raw('COUNT(*) as sessions_count'),
                    DB::raw('SUM(CAST(pos_sessions.opening_balance AS DECIMAL(18,2))) as opening_total'),
                    DB::raw('SUM(CAST(pos_sessions.total_sales AS DECIMAL(18,2))) as total_amount'),
                    DB::raw('SUM(CAST(pos_sessions.total_cash AS DECIMAL(18,2))) as cash_total'),
                    DB::raw('SUM(CAST(pos_sessions.expected_cash AS DECIMAL(18,2))) as expected_total'),
                    DB::raw('SUM(CAST(pos_sessions.actual_closing_balance AS DECIMAL(18,2))) as actual_total'),
                    DB::raw('SUM(CAST(pos_sessions.difference AS DECIMAL(18,2))) as net_variance'),
                    // الفائضُ والعجزُ منفصلان: دمجُهما يُخفي حجمَ كلٍّ منهما
                    DB::raw('SUM(CASE WHEN pos_sessions.difference > 0 THEN pos_sessions.difference ELSE 0 END) as over_total'),
                    DB::raw('SUM(CASE WHEN pos_sessions.difference < 0 THEN ABS(pos_sessions.difference) ELSE 0 END) as short_total'),
                    DB::raw('SUM(CASE WHEN ABS(COALESCE(pos_sessions.difference, 0)) > 0.01 THEN 1 ELSE 0 END) as variance_sessions'),
                    DB::raw('SUM(CASE WHEN pos_sessions.confirmed_at IS NULL THEN 1 ELSE 0 END) as unconfirmed_sessions'),
                    DB::raw('MAX(ABS(COALESCE(pos_sessions.difference, 0))) as max_variance'),
                ]))
                ->groupBy(...$dimension['group'])
                ->get()
        )->pipe(fn ($rows) => $this->ensureLabels($rows))->map(function ($row) {
            $row->net_amount = (float) $row->net_variance;

            /*
                نسبةُ الجلسات ذات الفرق — أدلُّ من المبلغ وحده.

                كاشيرٌ فرقُه ١٠ ريالات في ٥٠ جلسة ليس كمن فرقُه ١٠ في جلستين.
            */
            $row->variance_rate = $row->sessions_count > 0
                ? round(((float) $row->variance_sessions / (float) $row->sessions_count) * 100, 2)
                : 0.0;

            return $row;
        });
    }

    protected function totals($rows): array
    {
        $sessions = (int) $rows->sum('sessions_count');
        $withVariance = (int) $rows->sum('variance_sessions');

        return [
            'sessions_count' => $sessions,
            'variance_sessions' => $withVariance,
            'variance_rate' => $sessions > 0 ? round(($withVariance / $sessions) * 100, 2) : 0.0,
            'expected_total' => round((float) $rows->sum('expected_total'), 2),
            'actual_total' => round((float) $rows->sum('actual_total'), 2),
            'over_total' => round((float) $rows->sum('over_total'), 2),
            'short_total' => round((float) $rows->sum('short_total'), 2),
            'net_variance' => round((float) $rows->sum('net_variance'), 2),
            'net_amount' => round((float) $rows->sum('net_variance'), 2),
            'unconfirmed_sessions' => (int) $rows->sum('unconfirmed_sessions'),
            'rows_count' => $rows->count(),
        ];
    }

    public function exportColumns(): array
    {
        return [
            $this->dimension()['label'] => 'dim_label',
            'عدد الجلسات' => 'sessions_count',
            'جلسات بفرق' => 'variance_sessions',
            'نسبة الفروق %' => fn ($r) => number_format((float) $r->variance_rate, 2),
            'الرصيد المتوقَّع' => fn ($r) => number_format((float) $r->expected_total, 2),
            'الرصيد الفعلي' => fn ($r) => number_format((float) $r->actual_total, 2),
            'إجمالي الفائض' => fn ($r) => number_format((float) $r->over_total, 2),
            'إجمالي العجز' => fn ($r) => number_format((float) $r->short_total, 2),
            'صافي الفرق' => fn ($r) => number_format((float) $r->net_variance, 2),
            'أكبر فرق' => fn ($r) => number_format((float) $r->max_variance, 2),
            'بلا تأكيد' => 'unconfirmed_sessions',
        ];
    }
}
