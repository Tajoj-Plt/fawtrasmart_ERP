<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\notifications;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use App\Support\PaymentTerms;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * تنبيه مستحقّات العملاء — فواتير المبيعات الآجلة التي حلّ أجلُها أو قارب.
 *
 * ══════════ الفتحةُ التي يملؤها ══════════
 *
 * `invoices:payment-reminders` مسجَّلةٌ في {@see \App\Services\Cron\CronRegistry}
 * ولها صفٌّ في «المهام المجدولة» منذ التهيئة، ولا صنفَ أمرٍ يحمل الاسم —
 * فتشغيلُها يفشل صامتاً كما كان يفشل `suppliers:due-payments-alerts` قبل
 * أن يُكتب. وهذا الصنفُ هو ما كان ينقصها، لا أمرٌ جديدٌ إلى جانبها: صفٌّ
 * ثالثٌ لنفس المعنى يعني جدولاً فيه بندان أحدهما ميّت.
 *
 * ══════════ ولماذا لم يكن يعمل ══════════
 *
 * نظيرُه في المشتريات (`suppliers:due-payments-alerts`) قائمٌ منذ التهيئة،
 * ولا نظير له في المبيعات — والسبب أنّ الأجل نفسَه لم يكن يُحفَظ: حقل
 * «شروط الدفع» في شاشة الفاتورة كان يُرسَل باسم `terms` ويُكتب باسم
 * `terms`، ولا عمودَ بهذا الاسم في `invoices`، فيُسقطه `$fillable` بصمت.
 * فلا تاريخَ استحقاقٍ في المنظومة كلِّها يُبنى عليه تنبيه.
 *
 * ══════════ ما يفترق به عن نظير المشتريات ══════════
 *
 * ذاك يقيس التقادمَ من تاريخ الفاتورة بمدّةٍ واحدةٍ لكلّ الموردين
 * (`--days=30`) لأنّ `purchase_invoices` بلا عمود أجل. وهذا يقرأ أجلَ كلّ
 * فاتورةٍ من الفاتورة نفسِها: عميلٌ على «صافي ٧» وآخرُ على «صافي ٦٠» لا
 * يُقاسان بمسطرةٍ واحدة. و`--before` هنا ليست عمرَ الاستحقاق بل مهلةَ
 * التذكير **الاستباقيّ** قبله — كما في `installments:due-alerts`.
 *
 * ══════════ ومَن يصله ══════════
 *
 * مسؤولُ المبيعات على الفاتورة (`employee_id`) لا مُنشئُها: هو من يتابع
 * التحصيل وهو من يُحاسَب عليه. والعميلُ يُوسَم في الإشعار (`client_id`)
 * فتبلغه رسالةٌ أو بريدٌ متى أُضيف «العميل» إلى «من يستلم» في «إعدادات
 * الإشعارات» — ويبقى الأمرُ قرارَ الإدارة لا قرارَ هذا الأمر.
 *
 * والتنبيهُ مجمَّعٌ لكلّ عميل لا لكلّ فاتورة: عميلٌ له عشرون فاتورةً
 * متأخّرة لا يملأ جرسَ الإشعارات بعشرين سطراً.
 *
 * ما يقرأه من الإعدادات:
 *   • موعد التشغيل وحالته ← «المهام المجدولة» (جدول `cron_jobs`)
 *   • تفعيل الإشعار وقنواته ومستلموه ← «إعدادات الإشعارات» (نوع `sales_due_payment`)
 *   • رمز العملة وكسورها ← «العملات»
 *   • صيغة التاريخ ← اللغة النشطة
 */
class SendInvoicePaymentReminders extends Command
{
    protected $signature = 'invoices:payment-reminders
                            {--before= : أيامُ التذكير الاستباقيّ قبل الاستحقاق، وصفرٌ يقصره على المتأخّر}
                            {--dry-run : عرض ما سيُرسَل بلا إنشاء إشعارات}';

    protected $description = 'ينبّه على فواتير المبيعات الآجلة التي حلّ أجلُها أو قارب';

    public function handle(): int
    {
        $before = $this->option('before') === null
            ? PaymentTerms::SOON_WINDOW
            : max(0, (int) $this->option('before'));

        $dryRun = (bool) $this->option('dry-run');

        /* الترشيحُ الخشن في القاعدة والدقيقُ في PHP.

           الحدُّ الأقصى للأجل يُقصّ بـ`DATE_ADD` فلا يُجلب الجدولُ كلُّه،
           ثمّ يُعاد الحسابُ بـ`PaymentTerms` نفسِه الذي تقرؤه الشاشات —
           فلا يقول التنبيهُ ما تخالفه الشاشة. */
        $invoices = Invoice::query()
            ->notVoided()
            ->with('client:id,trade_name,first_name,last_name')
            ->whereIn('type', ['normal', 'pos', 'online'])
            ->whereNotNull('payment_terms')
            ->where('payment_terms', '>', 0)
            ->whereNotNull('issue_date')
            ->whereRaw('(COALESCE(due_value, 0) - COALESCE(returned_payment, 0)) >= 0.005')
            ->whereRaw(
                'DATE_ADD(issue_date, INTERVAL CAST(payment_terms AS UNSIGNED) DAY) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)',
                [$before]
            )
            ->orderBy('issue_date')
            ->get();

        // الحسبةُ الدقيقة — وتُسقط ما لا مهلةَ صالحةً له
        $rows = $invoices
            ->map(fn ($invoice) => ['invoice' => $invoice, 'terms' => PaymentTerms::of($invoice)])
            ->filter(fn ($row) => $row['terms'] !== null && $row['terms']->daysRemaining <= $before);

        if ($rows->isEmpty()) {
            $this->info(__('payment_terms.alert.none'));

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($rows->groupBy(fn ($row) => $row['invoice']->client_id) as $clientId => $clientRows) {
            $client = $clientRows->first()['invoice']->client;

            $name = $client
                ? ($client->trade_name ?: trim(($client->first_name ?? '').' '.($client->last_name ?? '')))
                : ('#'.$clientId);

            $total = $clientRows->sum(fn ($row) => (float) $row['invoice']->due_value - (float) ($row['invoice']->returned_payment ?? 0));

            // الأقربُ أجلاً هو ما يُذكر، والمتأخّرُ يُعدّ على حدة
            $earliest = $clientRows->min(fn ($row) => $row['terms']->dueDate->timestamp);
            $overdue = $clientRows->filter(fn ($row) => $row['terms']->state === PaymentTerms::OVERDUE)->count();

            $replace = [
                'client' => $name,
                'count' => $clientRows->count(),
                'amount' => CurrencySettings::format($total),
                'date' => LanguageSettings::formatDate(Carbon::createFromTimestamp($earliest)),
                'overdue' => $overdue,
            ];

            /* مفتاحان صريحان لا `trans_choice`: قاعدةُ الجمع العربية ستُّ
               صيغ، وسلسلةٌ من صيغتين تُقرأ بها قراءةً خاطئة — انظر
               {@see \App\Support\PaymentTerms::label()}. */
            $description = __(
                $clientRows->count() === 1 ? 'payment_terms.alert.body_one' : 'payment_terms.alert.body',
                $replace
            );

            $this->line(sprintf('• %s — %s', $name, $description));

            if ($dryRun) {
                continue;
            }

            /* مسؤولُ المبيعات على أقرب الفواتير أجلاً — بمستخدمه لا
               بموظّفه: `receiver_id` عمودُ مستخدمين. ومن لا مستخدمَ له
               يبقى الإشعارُ عامّاً تصرفه «إعدادات الإشعارات» بأدوارها. */
            $employeeId = $clientRows
                ->sortBy(fn ($row) => $row['terms']->dueDate->timestamp)
                ->first()['invoice']->employee_id;

            $receiverId = $employeeId
                ? User::where('employee_id', $employeeId)->value('id')
                : null;

            // الإنشاء يمرّ بحارس «إعدادات الإشعارات»: نوعٌ مُطفأ لا يُكتب أصلاً
            notifications::create([
                'user_id' => null,
                'receiver_id' => $receiverId,
                'client_id' => $clientId,
                'title' => __('payment_terms.alert.title'),
                'description' => $description,
                'type' => 'sales_due_payment',
                'read' => 0,
            ]);

            $sent++;
        }

        $this->info($dryRun
            ? __('payment_terms.alert.dry_run')
            : __('payment_terms.alert.sent', ['count' => $sent]));

        return self::SUCCESS;
    }
}
