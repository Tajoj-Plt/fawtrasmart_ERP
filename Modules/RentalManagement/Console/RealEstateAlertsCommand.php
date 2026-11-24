<?php

namespace Modules\RentalManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Task;
use Modules\RentalManagement\Support\RealEstateMoney;
use Modules\RentalManagement\Support\RealEstateNotifications as N;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * تنبيهات العقارات اليومية: عقودٌ تقترب نهايتها، وأقساطٌ قارب استحقاقها أو
 * تأخّرت، ومهامّ فات موعدها.
 *
 * **ما تغيّر.** كان الأمرُ يكتب إشعاراً لكلّ قسطٍ ولكلّ من يحمل الصلاحية في
 * كلّ الفروع، بنوع النظام العامّ `error`: تشغيلٌ واحد أخرج ٨٤ إشعاراً. صار:
 *
 *   • **إشعارٌ واحد مجمَّع لكلّ عقدٍ ولكلّ مستلم** — «العقد REC-… : ٣ أقساط
 *     متأخرة بإجمالي …» — لا إشعارٌ لكلّ قسط.
 *   • **المستلمون**: منشئُ العقد، وحَمَلةُ الصلاحية ممّن يرون العقد في قوائمهم
 *     ({@see N::recipientsFor()}) — لا كلُّ حاملٍ في كلّ فرع.
 *   • **النوع** مسمّى ({@see N}) فيُضبط من «إعدادات الإشعارات».
 *   • **«متأخّر»** بعد مهلة السماح `overdue_grace_days`، كما تحسبه
 *     `PaymentSchedule::refreshStatus()` حرفياً — لا بمجرّد فوات التاريخ.
 *
 * والتكرارُ محكومٌ كما كان بـ`last_alert_sent_at`: العقدُ كلّ سبعة أيام،
 * والقسطُ كلّ ثلاثة. والمهمةُ مرّةً واحدة (`re_tasks.due_alert_sent_at`) ما لم
 * يُؤجَّل موعدُها ثم يفُت ثانيةً.
 */
class RealEstateAlertsCommand extends Command
{
    /** أيامٌ بين تنبيهين عن العقد الواحد */
    private const CONTRACT_EVERY_DAYS = 7;

    /** أيامٌ بين تنبيهين عن القسط الواحد */
    private const SCHEDULE_EVERY_DAYS = 3;

    protected $signature = 'realestate:alerts {--dry-run : اعرض ما سيُرسَل دون إرسال}';

    protected $description = 'إدارة العقارات: تنبيه قرب انتهاء العقود واستحقاق الدفعات وتأخّرها والمهام الفائتة.';

    private bool $dry = false;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');

        [$expiringContracts, $expiringSent] = $this->expiringContracts();
        [$paymentContracts, $schedulesCount, $paymentsSent] = $this->payments();
        [$tasksCount, $tasksSent] = $this->overdueTasks();

        $this->info(sprintf(
            '%s %d إشعاراً — عقود تقترب نهايتها: %d (%d إشعاراً)، عقود عليها أقساط: %d تضمّ %d قسطاً (%d إشعاراً)، مهام فائتة: %d (%d إشعاراً)',
            $this->dry ? 'سيُرسَل' : 'أُرسل',
            $expiringSent + $paymentsSent + $tasksSent,
            $expiringContracts, $expiringSent,
            $paymentContracts, $schedulesCount, $paymentsSent,
            $tasksCount, $tasksSent,
        ));

        return self::SUCCESS;
    }

    /**
     * عقودٌ سارية تنتهي خلال مدّة التنبيه.
     *
     * @return array{0: int, 1: int} عدد العقود، عدد الإشعارات
     */
    private function expiringContracts(): array
    {
        $days = (int) RealEstateSettings::get('contract_expiry_alert_days', 60);

        $contracts = Contract::where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->where(fn ($q) => $q->whereNull('last_alert_sent_at')
                ->orWhere('last_alert_sent_at', '<', now()->subDays(self::CONTRACT_EVERY_DAYS)))
            ->with('tenant', 'unit.building')
            ->get();

        $recipients = N::recipientsFor('realestate.contracts.renew', Contract::class, $contracts->pluck('created_by', 'id')->all());
        $sent = 0;

        foreach ($contracts as $contract) {
            $left = max(0, $contract->days_left);
            $title = sprintf('العقد %s ينتهي %s', $contract->code, $left === 0 ? 'اليوم' : 'بعد '.$this->plural($left, 'يوم واحد', 'يومين', 'أيام', 'يوماً'));
            $body = sprintf('%s — %s — ينتهي في %s. ابدأ متابعة التجديد.',
                $contract->tenant->display_name ?? '', $contract->unit->full_label ?? '', $contract->end_date->format('Y-m-d'));

            $sent += $this->notify($recipients[$contract->id] ?? [], N::CONTRACT_EXPIRING, $title, $body);
            $this->dry || $contract->forceFill(['last_alert_sent_at' => now()])->saveQuietly();
        }

        return [$contracts->count(), $sent];
    }

    /**
     * الأقساط: إشعارٌ واحد لكلّ عقد.
     *
     * ما يُطلق التنبيه قسطٌ مفتوح دخل نافذة الاستحقاق ولم يُنبَّه عنه منذ ثلاثة
     * أيام. وما يُكتب في الإشعار **كلُّ** أقساط العقد المفتوحة في النافذة — لا
     * التي حان تنبيهُها وحدها — وإلّا قال الإشعار «قسطٌ متأخّر» وعلى العقد
     * ثلاثة. ثم تُختم كلُّها، فلا يتكرّر العقدُ قبل ثلاثة أيام إلّا بقسطٍ جديد.
     *
     * @return array{0: int, 1: int, 2: int} عدد العقود، عدد الأقساط، عدد الإشعارات
     */
    private function payments(): array
    {
        $dueDays = (int) RealEstateSettings::get('payment_due_alert_days', 5);
        $grace = (int) RealEstateSettings::get('overdue_grace_days', 0);
        $today = now()->startOfDay();

        $window = fn () => PaymentSchedule::open()
            ->whereDate('due_date', '<=', now()->addDays($dueDays)->toDateString())
            ->whereHas('contract', fn ($q) => $q->whereIn('status', Contract::COLLECTIBLE_STATUSES));

        $contractIds = $window()
            ->where(fn ($q) => $q->whereNull('last_alert_sent_at')
                ->orWhere('last_alert_sent_at', '<', now()->subDays(self::SCHEDULE_EVERY_DAYS)))
            ->distinct()->pluck('contract_id');

        if ($contractIds->isEmpty()) {
            return [0, 0, 0];
        }

        $contracts = Contract::whereIn('id', $contractIds)->with('tenant', 'unit.building')->get()->keyBy('id');
        $byContract = $window()->whereIn('contract_id', $contracts->keys())->orderBy('due_date')->get()->groupBy('contract_id');

        $recipients = N::recipientsFor('realestate.payments.create', Contract::class, $contracts->pluck('created_by', 'id')->all());
        $sent = 0;
        $count = 0;

        foreach ($byContract as $contractId => $schedules) {
            $contract = $contracts[$contractId];
            $count += $schedules->count();

            // «متأخّر» = فات أجلُه **ومهلةُ السماح** — نصُّ `PaymentSchedule::refreshStatus()`
            [$late, $upcoming] = $schedules->partition(fn (PaymentSchedule $s) => $s->due_date->copy()->addDays($grace)->lt($today));

            $money = fn (Collection $rows) => RealEstateMoney::format($rows->sum(fn (PaymentSchedule $s) => $s->balance), $contract->currency ?? null);
            $who = trim(($contract->tenant->display_name ?? '').' — '.($contract->unit->full_label ?? ''), ' —');

            if ($late->isNotEmpty()) {
                $type = N::PAYMENT_OVERDUE;
                $title = sprintf('العقد %s: %s بإجمالي %s', $contract->code,
                    $this->plural($late->count(), 'قسط واحد متأخر', 'قسطان متأخران', 'أقساط متأخرة', 'قسطاً متأخراً'), $money($late));
                $body = sprintf('%s — أقدم استحقاق %s', $who, $late->first()->due_date->format('Y-m-d'));

                if ($upcoming->isNotEmpty()) {
                    $body .= sprintf(' — ويستحق قريباً %s بإجمالي %s',
                        $this->plural($upcoming->count(), 'قسط واحد', 'قسطان', 'أقساط', 'قسطاً'), $money($upcoming));
                }
            } else {
                $type = N::PAYMENT_DUE;
                $title = sprintf('العقد %s: %s بإجمالي %s', $contract->code,
                    $this->plural($upcoming->count(), 'قسط واحد يستحق قريباً', 'قسطان يستحقان قريباً', 'أقساط تستحق قريباً', 'قسطاً تستحق قريباً'), $money($upcoming));
                $body = sprintf('%s — أقرب استحقاق %s', $who, $upcoming->first()->due_date->format('Y-m-d'));
            }

            $sent += $this->notify($recipients[$contractId] ?? [], $type, $title, $body);

            $this->dry || PaymentSchedule::whereIn('id', $schedules->pluck('id'))->update(['last_alert_sent_at' => now()]);
        }

        return [$byContract->count(), $count, $sent];
    }

    /**
     * مهامّ مفتوحة فات موعدُها — للمكلَّف بها (أو منشئها إن لم تُسنَد)، مرّةً
     * واحدة. تأجيلُ الموعد بعد التنبيه يعيدها إلى الحساب إن فات ثانيةً.
     *
     * @return array{0: int, 1: int} عدد المهام، عدد الإشعارات
     */
    private function overdueTasks(): array
    {
        // العمود من مهاجرة 2026_10_05_120000 — قبل تشغيلها لا خاتمَ يمنع التكرار، فلا تنبيه
        if (! Schema::hasColumn('re_tasks', 'due_alert_sent_at')) {
            return [0, 0];
        }

        $tasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_at')->where('due_at', '<', now())
            ->where(fn ($q) => $q->whereNull('due_alert_sent_at')->orWhereColumn('due_alert_sent_at', '<', 'due_at'))
            ->get();

        $sent = 0;

        foreach ($tasks as $task) {
            $userId = (int) ($task->assigned_user_id ?: $task->created_by);
            $title = 'مهمة فات موعدها: '.$task->title;
            $body = trim(sprintf('الموعد %s%s', $task->due_at->format('Y-m-d H:i'), $task->related_label ? ' — '.$task->related_label : ''));

            $sent += $this->notify($userId ? [$userId] : [], N::TASK_DUE, $title, $body);
            $this->dry || $task->forceFill(['due_alert_sent_at' => now()])->saveQuietly();
        }

        return [$tasks->count(), $sent];
    }

    /** @param  array<int, int>  $userIds */
    private function notify(array $userIds, string $type, string $title, string $body): int
    {
        if ($this->dry) {
            $this->line(sprintf('[%s] %s', $type, $title));
            $this->line(sprintf('    %s', $body));
            $this->line(sprintf('    ← %s', $userIds === [] ? 'لا مستلم' : implode(' ', array_map(fn ($id) => '#'.$id, $userIds))));

            return count($userIds);
        }

        $sent = 0;

        foreach ($userIds as $userId) {
            $sent += (int) N::send($userId, $type, $title, $body);
        }

        return $sent;
    }

    /** المعدود العربي: واحد، اثنان، ٣–١٠ جمع، ١١ فأكثر مفردٌ منصوب. */
    private function plural(int $count, string $one, string $two, string $few, string $many): string
    {
        return match (true) {
            $count === 1 => $one,
            $count === 2 => $two,
            $count <= 10 => $count.' '.$few,
            default => $count.' '.$many,
        };
    }
}
