<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SpeaksSystemLanguage;
use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\notifications;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * تنبيهات الأقساط — قبل الاستحقاق، ويومَه، وبعده.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * جدولُ الأقساط شاشةٌ تُفتَح لا تنبيهٌ يصل: من لم يفتحها لم يعرف أنّ قسطاً
 * حلّ أجلُه. والاتفاقيةُ قد تمتدّ ستّة وثلاثين شهراً، وتذكّرُ مواعيدها
 * بلا تنبيهٍ عملٌ يدويّ يُنسى.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ثلاثُ لحظات
 * ══════════════════════════════════════════════════════════════════════
 *
 *   **قبل الاستحقاق** — `--before=3` أيام: تذكيرٌ استباقيّ يُمكّن من
 *   التحصيل قبل أن يتأخّر القسط أصلاً. صفرٌ يُلغيه.
 *
 *   **يومَ الاستحقاق** — «بدأ القسط»: اللحظةُ التي يصير فيها مطالَباً به.
 *
 *   **بعده** — قسطٌ فات ولم يُسدَّد: تنبيهُ تحصيلٍ يحمل أيام التأخّر
 *   والغرامةَ المحمَّلة إن وُجدت.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  لماذا عمودا تاريخٍ لا سجلّ إرسال
 * ══════════════════════════════════════════════════════════════════════
 *
 * `due_notified_at` و`overdue_notified_at` على القسط: التنبيه يُرسَل مرّةً
 * في اليوم للقسط الواحد ولو شُغِّلت المهمّة كلّ ساعة. وبلا هذين العمودين
 * تمتلئ شاشةُ الإشعارات بالقسط نفسه أربعاً وعشرين مرّة، فيُطفئها
 * المستخدمُ كلَّها ولا يرى المهمّ منها.
 *
 * وهما تاريخان لا رايتان: القسط المتأخّر يُذكَّر به **كلّ يوم** ما بقي
 * متأخّراً — تنبيهُ تحصيلٍ يُرسَل مرّةً واحدة ثمّ يصمت تنبيهٌ لا يُحصِّل.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما يقرأه من الإعدادات
 * ══════════════════════════════════════════════════════════════════════
 *
 *   موعد التشغيل وحالته   ← «المهام المجدولة» (`cron_jobs`)
 *   تفعيل الإشعار وقنواته ← «إعدادات الإشعارات» (`installment_due`
 *                            و`installment_overdue`)
 *   رمز العملة وكسورها    ← «العملات»
 *   صيغة التاريخ          ← اللغة النشطة
 */
class AlertInstallmentsDue extends Command
{
    use SpeaksSystemLanguage;

    protected $signature = 'installments:due-alerts
                            {--before=3 : أيام التذكير قبل الاستحقاق — 0 يُلغيه}
                            {--dry-run : عرض ما سيُرسَل بلا إنشاء إشعارات}';

    protected $description = 'ينبّه بالأقساط التي حان استحقاقها أو قاربته أو تأخّرت';

    public function handle(): int
    {
        // الإشعار يُحفظ بلغة النظام لا بلغة الإطار — انظر السمة
        $this->useSystemLanguage();

        $before = max(0, (int) $this->option('before'));
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $sent = $this->alertDue($today, $before, $dryRun)
            + $this->alertOverdue($today, $dryRun);

        $this->info($dryRun
            ? sprintf('عرض فقط — %d تنبيهاً كان سيُرسَل.', $sent)
            : sprintf('أُرسل %d تنبيهاً للأقساط.', $sent));

        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════
    //  المستحقّ اليوم وما قاربه
    // ══════════════════════════════════════════════════════════════

    private function alertDue(Carbon $today, int $before, bool $dryRun): int
    {
        $horizon = $today->copy()->addDays($before);

        $rows = $this->base()
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $horizon->toDateString())
            ->where(function ($q) use ($today) {
                // مرّةً في اليوم للقسط الواحد
                $q->whereNull('due_notified_at')
                    ->orWhereDate('due_notified_at', '<', $today->toDateString());
            })
            ->orderBy('due_date')
            ->get();

        $sent = 0;

        foreach ($rows as $detail) {
            $agreement = $detail->installment;

            if (! $agreement) {
                continue;
            }

            $daysAhead = (int) $today->diffInDays(Carbon::parse($detail->due_date), false);

            $description = __(
                $daysAhead <= 0
                    ? 'sales_installments.alerts.due_today'
                    : 'sales_installments.alerts.due_soon',
                [
                    'code' => $agreement->code ?: ('#'.$agreement->id),
                    'client' => $agreement->client?->trade_name ?: '—',
                    'number' => $detail->installment_number,
                    'count' => $agreement->installment_number,
                    'amount' => CurrencySettings::format($detail->remaining_amount),
                    'date' => LanguageSettings::formatDate(Carbon::parse($detail->due_date)),
                    'days' => $daysAhead,
                ]
            );

            $this->line('• '.$description);

            if ($dryRun) {
                continue;
            }

            if ($this->push($agreement, $detail, 'installment_due', __('sales_installments.alerts.due_title'), $description)) {
                $detail->forceFill(['due_notified_at' => $today->toDateString()])->save();
                $sent++;
            }
        }

        return $sent;
    }

    // ══════════════════════════════════════════════════════════════
    //  المتأخّر
    // ══════════════════════════════════════════════════════════════

    private function alertOverdue(Carbon $today, bool $dryRun): int
    {
        $rows = $this->base()
            ->whereDate('due_date', '<', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('overdue_notified_at')
                    ->orWhereDate('overdue_notified_at', '<', $today->toDateString());
            })
            ->orderBy('due_date')
            ->get();

        $sent = 0;

        foreach ($rows as $detail) {
            $agreement = $detail->installment;

            if (! $agreement) {
                continue;
            }

            /* الغرامة المذكورة هي **المحمَّلة** لا المستحقّة نظرياً: تنبيهٌ
               يقول رقماً لم يُقيَّد يُطالِب العميلَ بما ليس في دفاترنا. */
            $fee = (float) $detail->late_fee_remaining;

            $description = __(
                $fee > 0 ? 'sales_installments.alerts.overdue_with_fee' : 'sales_installments.alerts.overdue',
                [
                    'code' => $agreement->code ?: ('#'.$agreement->id),
                    'client' => $agreement->client?->trade_name ?: '—',
                    'number' => $detail->installment_number,
                    'amount' => CurrencySettings::format($detail->remaining_amount),
                    'date' => LanguageSettings::formatDate(Carbon::parse($detail->due_date)),
                    'days' => $detail->days_late,
                    'fee' => CurrencySettings::format($fee),
                ]
            );

            $this->line('• '.$description);

            if ($dryRun) {
                continue;
            }

            if ($this->push($agreement, $detail, 'installment_overdue', __('sales_installments.alerts.overdue_title'), $description)) {
                $detail->forceFill(['overdue_notified_at' => $today->toDateString()])->save();
                $sent++;
            }
        }

        return $sent;
    }

    // ══════════════════════════════════════════════════════════════
    //  مشترَكات
    // ══════════════════════════════════════════════════════════════

    /** الأقساط غير المسدَّدة في اتفاقياتٍ قائمة — أساسُ الاستعلامين. */
    private function base()
    {
        return InstallmentDetail::query()
            ->with(['installment.client', 'installment.invoice'])
            ->unpaid()
            ->whereNotNull('due_date')
            ->whereHas('installment', fn ($a) => $a->whereNotIn('status', [
                Installment::STATUS_CANCELLED,
                Installment::STATUS_COMPLETED,
            ]));
    }

    /**
     * كتابة الإشعار.
     *
     * `receiver_id` من أنشأ الاتفاقية: هو المسؤول عن تحصيلها. ومن أراد
     * توسيع الدائرة — للمدراء أو للعميل نفسه — يضبط ذلك في «إعدادات
     * الإشعارات» لهذا النوع، فتتولّاه `NotificationDispatcher`.
     */
    private function push(Installment $agreement, InstallmentDetail $detail, string $type, string $title, string $body): bool
    {
        try {
            $row = notifications::create([
                'user_id' => null,
                'receiver_id' => $agreement->created_by,
                'client_id' => $agreement->client_id,
                'title' => $title,
                'description' => $body,
                'type' => $type,
                'read' => 0,
            ]);

            // نوعٌ مُطفأ في الإعدادات يُلغي الإدراج بلا استثناء ويُرجع
            // نموذجاً بلا معرّف — فلا يُوسَم القسط بأنّه نُبِّه عنه
            return (bool) ($row->id ?? null);
        } catch (Throwable $e) {
            $this->warn(sprintf(
                'تعذّر تنبيه القسط #%d من %s: %s',
                $detail->installment_number,
                $agreement->code ?: ('#'.$agreement->id),
                $e->getMessage()
            ));

            return false;
        }
    }
}
