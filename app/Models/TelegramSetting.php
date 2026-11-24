<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات بوت Telegram — صفٌّ واحد يشمل النظام كلّه، والمصدرُ الوحيد لكل
 * ما يُرسَل عبر البوت (`App\Services\TelegramNotificationService`):
 *
 *   • تقرير الإقفال اليومي للموظف — إلى `chat_id`.
 *   • إشعارات المبيعات (فاتورة جديدة، انخفاض الكمية، قرب انتهاء الصلاحية)
 *     — إلى `sales_chat_id`، أو `chat_id` حين تفرغ. مطفأةٌ افتراضاً.
 *
 * لا رمزَ بوتٍ ولا قناةَ في الشيفرة ولا في `config`.
 */
class TelegramSetting extends Model
{
    /** مفاتيح إشعارات المبيعات — تقرؤها الشاشة والمتحكّم منها. */
    public const SALES_FLAGS = [
        'sales_invoice_enabled',
        'low_stock_alert_enabled',
        'expiry_alert_enabled',
    ];

    protected $fillable = [
        'bot_token',
        'chat_id',
        'sales_chat_id',
        'daily_report_enabled',
        'report_show_sales',
        'report_show_collections',
        'report_show_returns',
        'report_show_expenses',
        'report_show_stock',
        ...self::SALES_FLAGS,
    ];

    protected $casts = [
        'daily_report_enabled' => 'boolean',
        'report_show_sales' => 'boolean',
        'report_show_collections' => 'boolean',
        'report_show_returns' => 'boolean',
        'report_show_expenses' => 'boolean',
        'report_show_stock' => 'boolean',
        'sales_invoice_enabled' => 'boolean',
        'low_stock_alert_enabled' => 'boolean',
        'expiry_alert_enabled' => 'boolean',
    ];

    /**
     * `firstOrCreate` مع افتراضات صريحة — لا `first() ?? new self`: نموذجٌ
     * غير محفوظ لا يحمل افتراضات الجدول، والقراءة السحرية لعمودٍ لم يُضبَط
     * تُرجع `null` فتُطفَأ الإشعارات بصمتٍ حتى يزور أحدٌ شاشة الإعدادات
     * ويحفظ. والافتراضات هنا مكتوبةٌ صراحةً لا متروكةً لـ`INSERT` مجرّدة:
     * Eloquent لا يُعيد قراءة القيَم الافتراضية من القاعدة بعد الإدراج.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'daily_report_enabled' => true,
            'report_show_sales' => true,
            'report_show_collections' => true,
            'report_show_returns' => true,
            'report_show_expenses' => true,
            'report_show_stock' => true,
            'sales_invoice_enabled' => false,
            'low_stock_alert_enabled' => false,
            'expiry_alert_enabled' => false,
        ]);
    }

    /** قناة إشعارات المبيعات — المستقلّة إن ضُبطت، وإلّا العامّة. */
    public function salesChatId(): ?string
    {
        return $this->sales_chat_id ?: $this->chat_id;
    }
}
