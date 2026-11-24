<?php

namespace App\Services;

use App\Jobs\DeliverNotificationChannels;
use App\Models\Client;
use App\Models\notifications;
use App\Models\NotificationSetting;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * القنوات الإضافية للإشعار: البريد والرسائل النصية.
 *
 * القناة الأساسية (الكتابة في جدول `notifications`) تحدث كما كانت تماماً؛
 * هذه الطبقة تعمل *بعد* نجاحها، وفقط للأنواع التي فعّل المسؤول لها قناة
 * إضافية من شاشة إعدادات الإشعارات. افتراضياً لا شيء هنا يعمل.
 */
class NotificationDispatcher
{
    /**
     * تُستدعى من حدث `created` في نموذج `notifications`.
     *
     * كل ما بداخلها محاط بـ try/catch: فشل إرسال بريد أو رسالة يجب ألا
     * يُسقط العملية الأصلية — إنشاء فاتورة لا يُلغى لأن بوابة الرسائل
     * متوقفة.
     */
    public static function afterCreate(notifications $notification): void
    {
        try {
            // المجدولة تُرسَل لاحقاً عند حلول موعدها، لا عند إنشائها
            if ($notification->is_scheduled && ! $notification->is_sent) {
                return;
            }

            $channels = NotificationSetting::channelsFor($notification->type);

            // القناة الداخلية وحدها لا تحتاج تسليماً — الصفّ كُتب أصلاً
            if ($channels === ['database'] || $channels === []) {
                return;
            }

            DeliverNotificationChannels::dispatchAfterResponse($notification->id);
        } catch (\Throwable $e) {
            Log::warning('تعذّر جدولة قنوات الإشعار الإضافية', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * التسليم الفعلي. تُستدعى من المهمة بعد انتهاء الطلب، ومن الأمر
     * المجدول عند إرسال إشعار مؤجَّل.
     */
    public static function deliver(notifications $notification): void
    {
        $channels = NotificationSetting::channelsFor($notification->type);

        // كل قناة معزولة عن الأخرى: تعطّل البريد يجب ألا يمنع الرسالة النصية
        if (in_array('mail', $channels, true)) {
            try {
                static::mail($notification);
            } catch (\Throwable $e) {
                Log::warning('تعذّر إرسال بريد الإشعار', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (in_array('sms', $channels, true)) {
            try {
                static::sms($notification);
            } catch (\Throwable $e) {
                Log::warning('تعذّر إرسال رسالة الإشعار', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * عناوين البريد المستهدفة حسب إعداد «من يستلم».
     *
     * @return array<int, string>
     */
    public static function mailRecipients(notifications $notification): array
    {
        $targets = NotificationSetting::recipientsFor($notification->type);
        $emails = [];

        if (in_array('receiver', $targets, true) && $notification->receiver_id) {
            $emails[] = User::where('id', $notification->receiver_id)->value('email');
        }

        if (in_array('managers', $targets, true)) {
            $emails = array_merge($emails, User::where('role', 'manager')->pluck('email')->all());
        }

        if (in_array('owners', $targets, true)) {
            $emails = array_merge($emails, static::byRole('owner')->pluck('email')->all());
        }

        if (in_array('client', $targets, true) && $notification->client_id) {
            $emails[] = Client::where('id', $notification->client_id)->value('email');
        }

        return array_values(array_unique(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    /**
     * أرقام الجوال المستهدفة.
     *
     * @return array<int, string>
     */
    public static function smsRecipients(notifications $notification): array
    {
        $targets = NotificationSetting::recipientsFor($notification->type);
        $phones = [];

        if (in_array('receiver', $targets, true) && $notification->receiver_id) {
            $phones[] = User::where('id', $notification->receiver_id)->value('phone');
        }

        if (in_array('managers', $targets, true)) {
            $phones = array_merge($phones, User::where('role', 'manager')->pluck('phone')->all());
        }

        if (in_array('owners', $targets, true)) {
            $phones = array_merge($phones, static::byRole('owner')->pluck('phone')->all());
        }

        if (in_array('client', $targets, true) && $notification->client_id) {
            $phones[] = Client::where('id', $notification->client_id)->value('phone');
        }

        return array_values(array_unique(array_filter($phones)));
    }

    /**
     * مستخدمو دور Spatie. الدور قد لا يكون معرَّفاً في نظام آخر، وحينها
     * ترمي `role()` استثناءً — تُرجَع مجموعة فارغة بدل إسقاط الإرسال.
     */
    protected static function byRole(string $role)
    {
        try {
            return User::role($role)->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    protected static function mail(notifications $notification): void
    {
        $recipients = static::mailRecipients($notification);

        if ($recipients === []) {
            return;
        }

        // تطبيق إعدادات SMTP المحفوظة قبل الإرسال — بدونها يستعمل
        // Laravel إعدادات .env التي قد تكون فارغة
        app(MailerConfigurator::class)->apply();

        $title = $notification->title ?: 'إشعار جديد';
        $body = $notification->description ?: '';

        foreach ($recipients as $email) {
            Mail::raw($body, function ($message) use ($email, $title) {
                $message->to($email)->subject($title);
            });
        }
    }

    protected static function sms(notifications $notification): void
    {
        $gateway = app(SmsGateway::class);

        if (! $gateway->isConfigured()) {
            return;
        }

        $recipients = static::smsRecipients($notification);

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $phone) {
            $gateway->send($phone, static::smsBody($notification));
        }
    }

    /**
     * نصّ الرسالة: قالب مربوط بالنوع إن وُجد، وإلا عنوان الإشعار ووصفه.
     */
    public static function smsBody(notifications $notification): string
    {
        $code = NotificationSetting::map()[$notification->type]['sms_template_code'] ?? null;

        if ($code) {
            $template = SmsTemplate::where('code', $code)->where('is_active', true)->first();

            if ($template) {
                return $template->render([
                    'customer' => optional($notification->client)->trade_name ?? '',
                    'title' => $notification->title ?? '',
                    'description' => $notification->description ?? '',
                ]);
            }
        }

        return trim(($notification->title ?? '').' — '.($notification->description ?? ''), " —\t\n");
    }
}
