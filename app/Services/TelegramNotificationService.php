<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DailyClosing;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * كل ما يُرسَل عبر بوت Telegram، والإعدادات مصدرها الوحيد
 * `TelegramSetting::current()` (شاشة «إعدادات Telegram» في Sitting) — لا
 * `env()` ولا ثوابت مكتوبة في الكود.
 *
 *   • تقرير الإقفال اليومي — فور إقفال الموظف يومه.
 *   • إشعارات المبيعات — فاتورة جديدة (من شاشتها أو بتحويل عرض سعر/أمر
 *     بيع)، وانخفاض الكمية، وقرب انتهاء الصلاحية.
 *
 * **إشعارات المبيعات تُرسَل بعد التزام المعاملة لا داخلها.** كانت الطلبات
 * في منتصف حفظ الفاتورة: تعذُّرُ الوصول إلى Telegram يُسقط الفاتورة كلَّها،
 * وفاتورةٌ تراجعت معاملتُها كانت تُعلَن كأنّها صدرت، وأقفالُ صفوف المخزون
 * تبقى معلّقةً حتى ينتهي الطلب. و`sendMessage()` لا ترمي أبداً.
 */
class TelegramNotificationService
{
    private const LOG_MODULE = 'TelegramNotifications';

    private ?TelegramSetting $settings = null;

    public function settings(): TelegramSetting
    {
        return $this->settings ??= TelegramSetting::current();
    }

    /**
     * ملخّص نصّي بأرقام الإقفال **المعتمدة** — لا استعلامات مستقلة.
     */
    public function sendEmployeeDailyReport(DailyClosing $closing): void
    {
        $settings = $this->settings();

        if (! $settings->daily_report_enabled || ! $settings->chat_id) {
            return;
        }

        $employee = $closing->employee;
        $date = Carbon::parse($closing->date)->format('Y-m-d');

        $lines = [
            '📊 التقرير اليومي للموظف',
            '👤 '.($employee->name ?? '#'.$closing->employee_id),
            '📅 '.$date,
            '━━━━━━━━━━━━━━━━',
        ];

        if ($settings->report_show_sales) {
            $lines[] = '💰 المبيعات: '.number_format((float) $closing->reported_sales, 2).' ر.س';
        }
        if ($settings->report_show_returns) {
            $lines[] = '🔄 المرتجعات: '.number_format((float) $closing->reported_returns, 2).' ر.س';

            // المرتجع النقدي المصروف للعميل — يُذكر حين وُجد؛ وهو مطروحٌ من التحصيلات
            if ((float) ($closing->reported_cash_refunds ?? 0) > 0) {
                $lines[] = '↩️ مرتجع نقدي مصروف: '.number_format((float) $closing->reported_cash_refunds, 2).' ر.س';
            }
        }
        if ($settings->report_show_collections) {
            $lines[] = '💵 التحصيلات: '.number_format((float) $closing->reported_collections, 2).' ر.س';
        }
        if ($settings->report_show_expenses) {
            $lines[] = '💸 المصروفات: '.number_format((float) $closing->reported_expenses, 2).' ر.س';
        }
        if ($settings->report_show_stock) {
            $lines[] = '📦 كمية المخزون المصرَّح بها: '.number_format((float) $closing->reported_stock_quantity, 2);
        }

        $this->sendMessage($settings->chat_id, implode("\n", $lines));
    }

    /**
     * فاتورة مبيعات صدرت — `$sourceLabel`/`$sourceNumber` حين حُوّلت من
     * عرض سعر أو أمر بيع.
     *
     * الرسالة تُبنى بعد الالتزام من الفاتورة المحفوظة نفسها: البنود لم تُكتب
     * بعدُ حين يُستدعى هذا من منتصف الحفظ.
     */
    public function salesInvoiceCreated(Invoice $invoice, ?string $sourceLabel = null, ?string $sourceNumber = null): void
    {
        if (! $this->settings()->sales_invoice_enabled) {
            return;
        }

        $invoiceId = $invoice->id;

        $this->afterCommit(function () use ($invoiceId, $sourceLabel, $sourceNumber) {
            $invoice = Invoice::find($invoiceId);

            if (! $invoice) {
                return;
            }

            $client = Client::find($invoice->client_id);
            $employee = Employee::find($invoice->employee_id);
            $creator = User::find($invoice->created_by);

            $lines = ['🧾 فاتورة مبيعات جديدة', 'رقم الفاتورة: '.$invoice->code];

            if ($sourceLabel) {
                $lines[] = 'من '.$sourceLabel.($sourceNumber ? ' رقم '.$sourceNumber : '');
            }

            $lines[] = 'العميل: '.($client->trade_name ?? '—');
            $lines[] = 'مسؤول البيع: '.($employee?->full_name ?: '—');
            $lines[] = 'أنشأها: '.($creator->name ?? '—');
            $lines[] = '━━━━━━━━━━━━━━━━';

            foreach (InvoiceItem::where('invoice_id', $invoice->id)->get() as $item) {
                $lines[] = '▫️ '.($item->item ?: '—').' — '.self::quantity((float) $item->quantity)
                    .($item->unit_name ? ' '.$item->unit_name : '')
                    .' × '.CurrencySettings::format((float) $item->unit_price);
            }

            $lines[] = '━━━━━━━━━━━━━━━━';
            $lines[] = 'الضريبة: '.CurrencySettings::format((float) $invoice->tax_total);
            $lines[] = 'الإجمالي: '.CurrencySettings::format((float) $invoice->grand_total);
            $lines[] = 'التاريخ: '.($invoice->invoice_date ?: optional($invoice->created_at)->format('Y-m-d'));

            $this->sendMessage($this->settings()->salesChatId(), implode("\n", $lines), null);
        });
    }

    /** كمية منتجٍ هبطت تحت حدّ التنبيه بسبب بيع. */
    public function lowStockAlert(string $productName, float $quantity, ?string $storeName = null): void
    {
        if (! $this->settings()->low_stock_alert_enabled) {
            return;
        }

        $lines = ['⚠️ تنبيه انخفاض الكمية', 'المنتج: '.$productName, 'الكمية المتبقية: '.self::quantity($quantity)];

        if ($storeName) {
            $lines[] = 'المستودع: '.$storeName;
        }

        $text = implode("\n", $lines);

        $this->afterCommit(fn () => $this->sendMessage($this->settings()->salesChatId(), $text, null));
    }

    /** منتجٌ بِيع وتاريخُ انتهائه ضمن أيام التنبيه المضبوطة عليه. */
    public function expiryAlert(string $productName, string $expiryDate, int $remainingDays): void
    {
        if (! $this->settings()->expiry_alert_enabled) {
            return;
        }

        $text = implode("\n", [
            '⏳ تنبيه قرب انتهاء الصلاحية',
            'المنتج: '.$productName,
            'تاريخ الانتهاء: '.$expiryDate,
            'المدة المتبقية: '.$remainingDays.' يوم',
        ]);

        $this->afterCommit(fn () => $this->sendMessage($this->settings()->salesChatId(), $text, null));
    }

    /**
     * بعد التزام المعاملة الجارية، أو فوراً إن لم تكن معاملة.
     * معاملةٌ تراجعت تُسقط الإشعار معها.
     */
    private function afterCommit(callable $callback): void
    {
        DB::afterCommit($callback);
    }

    /**
     * `$parseMode = null` نصٌّ خام: أسماء المنتجات والعملاء تحمل `_` و`*`
     * فتُرفض رسالة Markdown كلّها.
     */
    private function sendMessage(?string $chatId, string $text, ?string $parseMode = 'Markdown'): void
    {
        $token = $this->settings()->bot_token;
        if (! $token || ! $chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            // المهلة خيارُ اتصال لا حقلٌ في جسم الطلب — كانت تُرسَل إلى Telegram نفسه
            Http::connectTimeout(5)->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'send_message');
        }
    }

    private static function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
