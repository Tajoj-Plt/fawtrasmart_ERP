<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SpeaksSystemLanguage;
use App\Models\notifications;
use App\Services\Installments\LateFeeCharger;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * احتساب غرامات تأخير الأقساط وترحيلُها.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما يفعله
 * ══════════════════════════════════════════════════════════════════════
 *
 * يمرّ على كلّ قسطٍ **متأخّرٍ غيرِ مسدَّد** في اتفاقيةٍ **لها سياسةُ
 * غرامة**، ويحمّل عليه ما استحقّ إلى اليوم بعد أيام السماح، ثمّ يرحّل
 * الفرق قيداً:
 *
 *     من ح/ العميل  →  إلى ح/ غرامات تأخير الأقساط
 *
 * والتحميلُ بالفرق لا بالمجموع، فتكرارُ التشغيل في اليوم الواحد لا
 * يُضاعف شيئاً — التفصيل في `LateFeeCharger`.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما يقرأه من الإعدادات
 * ══════════════════════════════════════════════════════════════════════
 *
 *   موعد التشغيل وحالته   ← «المهام المجدولة» (`cron_jobs`)
 *   حساب الغرامات         ← «الحسابات المحاسبية» (`installment_late_fees`)
 *   سياسة الغرامة         ← الاتفاقية نفسُها: النوع والقيمة والسماح
 *                            والأساس والسقف
 *   الفترات المحاسبية     ← لا يُرحَّل قيدٌ في فترةٍ أُقفلت
 *   تفعيل التنبيه وقنواته ← «إعدادات الإشعارات» (`installment_late_fee`)
 *   رمز العملة وكسورها    ← «العملات»
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ولماذا لا يُحمَّل شيءٌ افتراضياً
 * ══════════════════════════════════════════════════════════════════════
 *
 * اتفاقيةٌ لم يُضبَط فيها `late_fee_type` لا يُحمَّل عليها قرشٌ مهما
 * تأخّرت أقساطُها. فتشغيلُ هذه المهمّة على نظامٍ لم تُضبَط فيه سياسةُ
 * غرامةٍ قطّ لا يفعل شيئاً، ولا يفاجئ أحداً بذمّةٍ لم يتّفق عليها.
 */
class ChargeInstallmentLateFees extends Command
{
    use SpeaksSystemLanguage;

    protected $signature = 'installments:late-fees
                            {--dry-run : عرض ما سيُحمَّل بلا كتابة ولا قيد}
                            {--agreement= : حصرُ التشغيل باتفاقيةٍ بعينها}
                            {--no-notify : لا يُرسل تنبيهاً بالغرامات المحمَّلة}';

    protected $description = 'يحتسب غرامات تأخير الأقساط ويرحّلها إلى حساب الغرامات';

    public function handle(): int
    {
        // الإشعار يُحفظ بلغة النظام لا بلغة الإطار — انظر السمة
        $this->useSystemLanguage();

        $dryRun = (bool) $this->option('dry-run');

        /* الفترة المحاسبية تُفحَص مرّةً قبل الحلقة لا داخلها: القيد يُرحَّل
           بتاريخ اليوم في كلّ الصفوف، فالفحصُ واحدٌ لها جميعاً. */
        if (! $dryRun && $reason = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            $this->error($reason);

            return self::FAILURE;
        }

        $query = LateFeeCharger::chargeable();

        if ($agreementId = $this->option('agreement')) {
            $query->where('installments_id', (int) $agreementId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('لا أقساط متأخّرة تستحقّ غرامةً اليوم.');

            return self::SUCCESS;
        }

        $charged = 0;
        $total = 0.0;
        $failures = 0;
        $touched = [];

        foreach ($rows as $detail) {
            try {
                $delta = LateFeeCharger::charge($detail, $dryRun);
            } catch (Throwable $e) {
                /* حسابٌ غير مربوطٍ أو عميلٌ بلا حساب: يُبلَّغ ويُتخطّى.
                   إسقاطُ المهمّة كلِّها لأجل اتفاقيةٍ واحدة يُعطّل غرامات
                   الباقي إلى أن يُصلَح ذلك الصفّ. */
                $this->warn(sprintf(
                    '• القسط #%d من %s — %s',
                    $detail->installment_number,
                    $detail->installment?->code ?: ('#'.$detail->installments_id),
                    $e->getMessage()
                ));

                $failures++;

                continue;
            }

            if ($delta <= 0) {
                continue;
            }

            $charged++;
            $total += $delta;

            $agreement = $detail->installment;

            $this->line(sprintf(
                '• %s — القسط #%d، تأخّرٌ %d يوماً، غرامة %s',
                $agreement?->code ?: ('#'.$detail->installments_id),
                $detail->installment_number,
                $detail->chargeable_late_days,
                CurrencySettings::format($delta)
            ));

            if ($agreement) {
                // التجميع بالاتفاقية: عميلٌ له عشرة أقساطٍ متأخّرة لا يملأ
                // جرس الإشعارات بعشرة أسطر
                $key = $agreement->id;
                $touched[$key]['agreement'] = $agreement;
                $touched[$key]['amount'] = ($touched[$key]['amount'] ?? 0) + $delta;
                $touched[$key]['count'] = ($touched[$key]['count'] ?? 0) + 1;
            }
        }

        if (! $dryRun && ! $this->option('no-notify')) {
            $this->notify($touched);
        }

        $this->info($dryRun
            ? sprintf('عرض فقط — %d قسطاً يستحقّ %s ولم يُكتب شيء.', $charged, CurrencySettings::format($total))
            : sprintf('حُمِّلت غرامات %d قسطاً بمجموع %s.', $charged, CurrencySettings::format($total)));

        if ($failures > 0) {
            $this->warn(sprintf('%d قسطاً تعذّر ترحيل غرامته — انظر ما سبق.', $failures));
        }

        return self::SUCCESS;
    }

    /**
     * تنبيهٌ واحدٌ لكلّ اتفاقية بما حُمِّل عليها.
     *
     * الإنشاء يمرّ بحارس «إعدادات الإشعارات»: نوعٌ مُطفأ لا يُكتب أصلاً.
     *
     * @param  array<int, array{agreement: \App\Models\Installment, amount: float, count: int}>  $touched
     */
    private function notify(array $touched): void
    {
        foreach ($touched as $row) {
            $agreement = $row['agreement'];

            try {
                notifications::create([
                    'user_id' => null,
                    'receiver_id' => $agreement->created_by,
                    'client_id' => $agreement->client_id,
                    'title' => __('sales_installments.late_fee.alert_title'),
                    'description' => __('sales_installments.late_fee.alert_body', [
                        'code' => $agreement->code ?: ('#'.$agreement->id),
                        'client' => $agreement->client?->trade_name ?: '—',
                        'count' => $row['count'],
                        'amount' => CurrencySettings::format($row['amount']),
                    ]),
                    'type' => 'installment_late_fee',
                    'read' => 0,
                ]);
            } catch (Throwable $e) {
                // تعذّر التنبيه لا يُبطل قيداً رُحِّل
                $this->warn('تعذّر إرسال تنبيه الغرامة: '.$e->getMessage());
            }
        }
    }
}
