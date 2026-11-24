<?php

namespace App\Services\Targets;

use App\Models\CollectionsTarget;
use App\Models\SalesTarget;
use App\Models\TargetSetting;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Services\TargetCalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * العمولاتُ المستحقّة التي لم تُصرف بعد — «قيد الانتظار».
 *
 * **لماذا تُحسب ولا تُقرأ؟**
 *
 * جدولُ التسويات يكتب ما **صُرف** وحده. ومن استحقّ عمولةً ولم تُصرف له —
 * خزينتُه لا تكفي، أو لا خزينةَ له، أو لم يحن موعدُ هدفه — لا صفَّ له في أيّ
 * جدول: يُذكر مرّةً في تنبيه التشغيل ثم يغيب. فمن أراد أن يعرف «من ينتظر
 * عمولتَه وكم» لم يجد شاشةً تجيبه.
 *
 * فتُحسب هنا بالقاعدة نفسِها التي تصرف بها التسوية
 * ({@see \App\Console\Commands\ProcessTargetSettlements}): لكلّ هدفٍ نشط،
 * ولكلّ موظفٍ فيه لم تُسوَّ عمولتُه عن الفترة، إنجازُه × نسبةُ شريحته.
 *
 * **أيُّ فترة؟**
 *
 *   • هدفٌ له مدّة ويُصرف عند نهايتها: مدّتُه — كاملةً إن انتهت، ومن بدايتها
 *     إلى اليوم إن كانت جارية (وحينها الرقمُ **متراكمٌ حتى اليوم** لا نهائيّ).
 *   • غيرُه (بلا مدّة، أو دوريٌّ داخل مدّته): آخرُ دورةٍ حلّ موعدُ تسويتها،
 *     مقصوصةً على مدّة الهدف. وبلا جدولةٍ مضبوطة: الشهرُ الجاري حتى اليوم.
 *
 * للعرض وحده — لا يكتب شيئاً ولا يصرف.
 */
class PendingCommissions
{
    /** حلّ موعدُها ولم تُصرف: الخزينةُ لا تكفي */
    public const TREASURY_SHORT = 'treasury_short';

    /** حلّ موعدُها ولم تُصرف: لا خزينةَ للموظف ولا افتراضيّة */
    public const NO_TREASURY = 'no_treasury';

    /** المستخدمُ بلا سجلّ موظف — التسويةُ تتخطّاه */
    public const NO_EMPLOYEE = 'no_employee';

    /** حلّ موعدُها وتنتظر التشغيلَ التالي أو تسويةً يدوية */
    public const AWAITING_RUN = 'awaiting_run';

    /** مدّةُ الهدف لم تنتهِ — تُصرف عند نهايتها */
    public const NOT_DUE = 'not_due';

    /** لا جدولةَ تلقائيّة — تنتظر تسويةً يدوية */
    public const MANUAL = 'manual';

    public function __construct(private TargetCalculationService $calc) {}

    /**
     * @param  string  $type  `TargetSetting::TYPE_SALES` أو `TYPE_COLLECTIONS`
     * @return Collection<int, array{
     *     target_id:int, target_name:string, user_id:int, employee_id:?int, employee_name:string,
     *     from:string, to:string, achieved:float, target_amount:float, percentage:float,
     *     commission:float, state:string, due_on:?string, treasury_name:?string, treasury_balance:?float
     * }>
     */
    public function for(string $type): Collection
    {
        $class = $type === TargetSetting::TYPE_COLLECTIONS ? CollectionsTarget::class : SalesTarget::class;
        $setting = TargetSetting::for($type);
        $method = $setting->calculation_method
            ?: ($type === TargetSetting::TYPE_COLLECTIONS ? 'total_collected' : 'net_sales');

        $targets = $class::with(['achievements.employee', 'commissionTiers'])
            ->where('status', 'active')
            ->get();

        $rows = collect();
        $today = Carbon::now()->toDateString();

        // ما صُرف في هذا العرض يُطرح من رصيد الخزينة لمن بعده — كما تفعل التسوية
        $reserved = [];

        foreach ($targets as $target) {
            $window = $this->windowFor($target, $setting, $today);

            if ($window === null) {
                continue;
            }

            [$from, $to, $baseState, $dueOn] = $window;

            $settledEmployees = $target->settledEmployeeIdsForPeriod($from, $to);

            foreach ($target->achievements as $achievement) {
                $user = $achievement->employee;

                if (! $user) {
                    continue;
                }

                if ($user->employee_id && in_array((int) $user->employee_id, $settledEmployees, true)) {
                    continue;
                }

                $achieved = (float) ($type === TargetSetting::TYPE_COLLECTIONS
                    ? $this->calc->getEmployeeCollections($user->id, $from, $to, $method)
                    : $this->calc->getEmployeeNetSales($user->id, $from, $to, $method));

                $commission = round((float) $target->calculateCommission($achieved, $achievement->target_amount), 2);

                if ($commission <= 0) {
                    continue;
                }

                $treasuryId = $this->treasuryFor($user, $setting);
                $balance = TreasuryBalanceGuard::displayBalance($treasuryId);
                $available = $balance === null ? null : $balance - ($reserved[$treasuryId] ?? 0);

                $state = match (true) {
                    ! $user->employee_id => self::NO_EMPLOYEE,
                    // ما لم يحن موعدُه لا يُسأل عن خزينته اليوم
                    $baseState === self::NOT_DUE => self::NOT_DUE,
                    ! $treasuryId => self::NO_TREASURY,
                    $available !== null && $available + 0.005 < $commission => self::TREASURY_SHORT,
                    default => $baseState,
                };

                if ($treasuryId && in_array($state, [self::AWAITING_RUN, self::MANUAL], true)) {
                    $reserved[$treasuryId] = ($reserved[$treasuryId] ?? 0) + $commission;
                }

                $rows->push([
                    'target_id' => (int) $target->id,
                    'target_name' => (string) $target->name,
                    'user_id' => (int) $user->id,
                    'employee_id' => $user->employee_id ? (int) $user->employee_id : null,
                    'employee_name' => (string) $user->name,
                    'from' => $from,
                    'to' => $to,
                    'achieved' => $achieved,
                    'target_amount' => (float) $achievement->target_amount,
                    'percentage' => $achievement->target_amount > 0
                        ? round($achieved / (float) $achievement->target_amount * 100, 1)
                        : 0.0,
                    'commission' => $commission,
                    'state' => $state,
                    'due_on' => $dueOn,
                    'treasury_name' => $treasuryId ? optional(\App\Models\Account::find($treasuryId))->name : null,
                    'treasury_balance' => $balance,
                ]);
            }
        }

        return $rows;
    }

    /**
     * الفترةُ التي يُسأل عنها هذا الهدف، وحالتُها، وموعدُ صرفها.
     *
     * @return array{0:string, 1:string, 2:string, 3:?string}|null `null` حين لا فترةَ تُسأل عنها
     */
    private function windowFor($target, TargetSetting $setting, string $today): ?array
    {
        if ($target->settlesAtPeriodEnd()) {
            $start = $target->start_date->toDateString();
            $end = $target->end_date->toDateString();

            // لم تبدأ مدّتُه بعد
            if ($start > $today) {
                return null;
            }

            return $end <= $today
                ? [$start, $end, self::AWAITING_RUN, $end]
                : [$start, $today, self::NOT_DUE, $end];
        }

        $scheduled = $setting->enabled && $setting->auto_settlement_enabled && $setting->settlement_frequency;

        if (! $scheduled) {
            $period = $target->clampToPeriod(Carbon::now()->startOfMonth()->toDateString(), $today);

            return $period ? [$period[0], $period[1], self::MANUAL, null] : null;
        }

        $lastDue = $this->lastDueDate($setting);

        if ($lastDue === null) {
            return null;
        }

        [$from, $to] = TargetSettlementSchedule::periodFor($setting, $lastDue);
        $period = $target->clampToPeriod($from, $to);

        return $period ? [$period[0], $period[1], self::AWAITING_RUN, $lastDue->toDateString()] : null;
    }

    /** آخرُ يومٍ حلّ فيه موعدُ التسوية المجدولة — اليومُ نفسُه إن بلغ وقتَها */
    private function lastDueDate(TargetSetting $setting): ?Carbon
    {
        $now = Carbon::now();

        for ($i = 0; $i <= 400; $i++) {
            $day = $now->copy()->subDays($i);

            if (! TargetSettlementSchedule::isDueOn($setting, $day)) {
                continue;
            }

            if ($i === 0 && ! TargetSettlementSchedule::isTimeReached($setting, $now)) {
                continue;
            }

            return $day;
        }

        return null;
    }

    /** الخزينةُ التي تُصرف منها عمولةُ هذا الموظف — قاعدةُ `TargetSettlementService` نفسُها */
    private function treasuryFor(User $user, TargetSetting $setting): ?int
    {
        if ($setting->pay_from_employee_treasury && $user->employee_id) {
            $link = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($link && $link->treasury_id) {
                return (int) $link->treasury_id;
            }
        }

        return $setting->default_commission_treasury_id ? (int) $setting->default_commission_treasury_id : null;
    }
}
