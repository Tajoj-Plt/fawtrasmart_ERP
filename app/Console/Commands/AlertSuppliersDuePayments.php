<?php

namespace App\Console\Commands;

use App\Enums\PurchaseDocumentType;
use App\Models\notifications;
use App\Models\PurchaseInvoice;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * تنبيه مستحقّات الموردين — فواتير الشراء التي حان سدادها أو تأخّر.
 *
 * الأمر مسجَّل في `CronRegistry` وله صفّ في «المهام المجدولة» منذ التهيئة،
 * لكنه لم يكن مطبَّقاً: وصفه في الجدول يقول «(غير مطبَّقة بعد)» وحقل
 * `handler` يشير إلى أمرٍ لا وجود له، فكان تشغيله يفشل صامتاً.
 *
 * ما يقرأه من الإعدادات:
 *   • موعد التشغيل وحالته ← «المهام المجدولة» (جدول `cron_jobs`)
 *   • تفعيل الإشعار وقنواته ← «إعدادات الإشعارات» (نوع `purchase_due_payment`)
 *   • رمز العملة وكسورها ← «العملات»
 *   • صيغة التاريخ ← اللغة النشطة
 *
 * لا يُرسَل تنبيه واحد لكل فاتورة: التنبيه مجمَّع لكل مورّد، فمورّدٌ له
 * عشرون فاتورة متأخّرة لا يملأ جرس الإشعارات بعشرين سطراً.
 */
class AlertSuppliersDuePayments extends Command
{
    /**
     * لا عمود لتاريخ الاستحقاق في `purchase_invoices`، فالتقادم يُقاس من
     * تاريخ الفاتورة: ما مضى على تاريخه أكثر من `--days` ولم يُسدَّد بالكامل.
     */
    protected $signature = 'suppliers:due-payments-alerts
                            {--days=30 : عمر الاستحقاق بالأيام منذ تاريخ الفاتورة}
                            {--dry-run : عرض ما سيُرسَل بلا إنشاء إشعارات}';

    protected $description = 'ينبّه على فواتير الموردين التي حان موعد سدادها أو تأخّرت';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $horizon = Carbon::today()->subDays($days);

        /* المستحقُّ بعد المرتجع والإشعار المدين لا قبلهما.

           `due_value` على فاتورة الشراء لا يتغيّر حين تُردّ بضاعتها: المرتجعُ
           والإشعارُ المدين مستندان مستقلّان يشيران إليها بـ`reference_id`.
           فكانت فاتورةٌ رُدَّت كلُّها تُنبَّه يومياً بكامل مبلغها — وشاشةُ
           السداد نفسُها ترفض الدفع عليها لأن لها مرتجعاً
           (`PurchasePaymentController::blockingReturnFor`). */
        $invoices = PurchaseInvoice::query()
            ->with('supplier')
            ->withSum(['relatedDocuments as returned_total' => fn ($query) => $query->whereIn('type', [
                PurchaseDocumentType::RETURN->value,
                PurchaseDocumentType::DEBIT_NOTE->value,
            ])], 'grand_total')
            ->where('type', PurchaseDocumentType::INVOICE->value)
            ->where('due_value', '>', 0)
            ->whereNotNull('date')
            ->whereDate('date', '<=', $horizon)
            ->orderBy('date')
            ->get()
            ->each(fn ($invoice) => $invoice->net_due = round((float) $invoice->due_value - (float) $invoice->returned_total, 2))
            ->filter(fn ($invoice) => $invoice->net_due >= 0.005)
            ->values();

        if ($invoices->isEmpty()) {
            $this->info('لا توجد مستحقّات موردين خلال المدّة المحدَّدة.');

            return self::SUCCESS;
        }

        $today = Carbon::today();
        $sent = 0;

        foreach ($invoices->groupBy('supplier_id') as $supplierId => $supplierInvoices) {
            $supplier = $supplierInvoices->first()->supplier;
            $name = $supplier->trade_name ?? ('#'.$supplierId);

            $total = (float) $supplierInvoices->sum('net_due');
            // الأقدم أولى بالسداد، وهو ما يُذكر في التنبيه
            $oldest = Carbon::parse($supplierInvoices->min('date'));

            $overdue = $supplierInvoices->filter(
                fn ($invoice) => Carbon::parse($invoice->date)->lt($today->copy()->subDays($days))
            );

            $description = trans_choice(
                'purchase_cycle.invoice.due_alert',
                $supplierInvoices->count(),
                [
                    'supplier' => $name,
                    'count' => $supplierInvoices->count(),
                    'amount' => CurrencySettings::format($total),
                    'date' => LanguageSettings::formatDate($oldest),
                    'overdue' => $overdue->count(),
                ]
            );

            $this->line(sprintf('• %s — %s', $name, $description));

            if ($dryRun) {
                continue;
            }

            // الإنشاء يمرّ بحارس «إعدادات الإشعارات»: نوعٌ مُطفأ لا يُكتب أصلاً
            notifications::create([
                'user_id' => null,
                'receiver_id' => null,
                'title' => __('purchase_cycle.invoice.due_alert_title'),
                'description' => $description,
                'type' => 'purchase_due_payment',
                'read' => 0,
                'status' => 'unread',
            ]);

            $sent++;
        }

        $this->info($dryRun
            ? 'عرض فقط — لم تُنشأ إشعارات.'
            : sprintf('تم إرسال %d تنبيهاً لمستحقّات الموردين.', $sent));

        return self::SUCCESS;
    }
}
