<?php

namespace App\Services;

use App\Mail\PointsNotificationMail;
use App\Models\Client;
use App\Models\Ecosystem\PointsNotificationTemplate;
use App\Models\Ecosystem\PointsNotificationLog;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\UserType;
use App\Models\Ecosystem\PointsGeneralSetting;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PointsNotificationService
{
    /**
     * إرسال إشعار بناءً على نوع الحدث
     *
     * @param string $eventType نوع الحدث (points_earned, reward_redeemed, tier_upgraded, etc.)
     * @param string $userType نوع المستخدم (employee, client)
     * @param int|null $employeeId
     * @param int|null $clientId
     * @param array $data البيانات المتغيرة لتعبئة القالب
     * @return PointsNotificationLog[]
     */
    public function send(string $eventType, string $userType, ?int $employeeId, ?int $clientId, array $data = []): array
    {
        $logs = [];

        // =====================================================
        // فحص تفعيل نظام الإشعارات من الإعدادات العامة
        // =====================================================
        try {
            if (!PointsGeneralSetting::isSubsystemEnabled('notifications')) {
                return $logs;
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
        }

        try {
            // جلب القالب النشط لهذا الحدث (مع مراعاة نوع المستخدم)
            $template = PointsNotificationTemplate::active()
                ->forEvent($eventType)
                ->where(function ($q) use ($userType) {
                    $q->where('user_type', $userType)
                      ->orWhere('user_type', 'both');
                })
                ->first();

            if (!$template) {
                Log::info("لم يتم العثور على قالب إشعار للحدث: {$eventType}, نوع المستخدم: {$userType}");
                return $logs;
            }

            // تجهيز بيانات الإشعار من القالب
            $rendered = $template->render($data);

            // إرسال عبر كل قناة مفعلة
            foreach ($template->getChannels() as $channel) {
                try {
                    $log = PointsNotificationLog::create([
                        'template_id' => $template->id,
                        'user_type' => $userType,
                        'employee_id' => $employeeId,
                        'client_id' => $clientId,
                        'event_type' => $eventType,
                        'channel' => $channel,
                        'title' => $rendered['title'],
                        'body' => $rendered['body'],
                        'status' => NotificationStatus::PENDING->value,
                        'metadata' => array_merge($data, [
                            'template_id' => $template->id,
                        ]),
                    ]);

                    // محاولة الإرسال حسب القناة
                    $this->dispatch($log, $channel);

                    $logs[] = $log;
                } catch (\Throwable $e) {
                    Log::warning("فشل إرسال إشعار عبر قناة {$channel}: " . $e->getMessage(), [
                        'event_type' => $eventType,
                        'channel' => $channel,
                        'user_type' => $userType,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'user_type' => $userType,
                'employee_id' => $employeeId,
                'client_id' => $clientId,
            ]);
        }

        return $logs;
    }

    /**
     * إرسال إشعار لموظف
     */
    public function sendToEmployee(string $eventType, int $employeeId, array $data = []): array
    {
        return $this->send($eventType, UserType::EMPLOYEE->value, $employeeId, null, $data);
    }

    /**
     * إرسال إشعار لعميل
     */
    public function sendToClient(string $eventType, int $clientId, array $data = []): array
    {
        return $this->send($eventType, UserType::CLIENT->value, null, $clientId, $data);
    }

    /**
     * إرسال الإشعار عبر القناة المحددة
     */
    protected function dispatch(PointsNotificationLog $log, string $channel): void
    {
        try {
            switch ($channel) {
                case NotificationChannel::DATABASE->value:
                    // الإشعار الداخلي — محفوظ بالفعل في الجدول
                    $log->markAsSent();
                    break;

                case NotificationChannel::EMAIL->value:
                    $this->dispatchEmail($log);
                    break;

                case NotificationChannel::PUSH->value:
                    $this->dispatchFirebase($log);
                    break;

                case NotificationChannel::SMS->value:
                    // TODO: ربط مع خدمة SMS
                    $log->markAsSent();
                    break;

                default:
                    $log->markAsSent();
                    break;
            }
        } catch (\Throwable $e) {
            $log->markAsFailed($e->getMessage());
        }
    }

    /**
     * إرسال إشعار عبر البريد الإلكتروني
     */
    protected function dispatchEmail(PointsNotificationLog $log): void
    {
        $email = $this->resolveUserEmail($log->user_type, $log->employee_id, $log->client_id);

        if (!$email) {
            $log->markAsFailed('لا يوجد بريد إلكتروني للمستخدم');
            return;
        }

        try {
            Mail::to($email)->send(new PointsNotificationMail(
                $log->title,
                $log->body,
                $log->event_type,
                $log->metadata ?? []
            ));

            $log->markAsSent();
        } catch (\Throwable $e) {
            Log::warning('PointsNotification: فشل إرسال البريد', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $log->markAsFailed('فشل إرسال البريد: ' . $e->getMessage());
        }
    }

    /**
     * إرسال إشعار عبر Firebase Cloud Messaging (FCM)
     * 
     * ⚠️ تحتاج إلى:
     *   1. إعداد FIREBASE_SERVER_KEY في .env
     *   2. حفظ FCM token لكل مستخدم في جدول employees/clients (عمود: fcm_token)
     *   3. أو استخدام حزمة مثل laravel-notification-channels/fcm
     * 
     * حالياً: يحفظ الإشعار كـ "مُرسل" ويكتب تحذير في اللوج
     * عند تفعيل Firebase: أزل التعليق من الكود أدناه
     */
    protected function dispatchFirebase(PointsNotificationLog $log): void
    {
        $fcmToken = $this->resolveUserFcmToken($log->user_type, $log->employee_id, $log->client_id);
        $serverKey = config('services.firebase.server_key');

        // إذا لم يتم إعداد Firebase بعد
        if (!$serverKey) {
            // يحفظ في قاعدة البيانات فقط — يمكن إعادة الإرسال لاحقاً
            $log->update(['metadata' => array_merge($log->metadata ?? [], ['firebase_pending' => true])]);
            $log->markAsSent(); // محفوظ لكن لم يُرسل فعلياً عبر push
            return;
        }

        if (!$fcmToken) {
            $log->markAsFailed('لا يوجد FCM token للمستخدم');
            return;
        }

        try {
            // === الإرسال الفعلي عبر FCM HTTP v1 ===
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $log->title,
                    'body'  => $log->body,
                    'sound' => 'default',
                ],
                'data' => [
                    'event_type' => $log->event_type,
                    'points'     => $log->metadata['points'] ?? null,
                    'click_action' => 'POINTS_NOTIFICATION',
                ],
            ]);

            if ($response->successful()) {
                $log->markAsSent();
            } else {
                $log->markAsFailed('FCM response: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('PointsNotification: فشل إرسال Firebase', [
                'error' => $e->getMessage(),
            ]);
            $log->markAsFailed('فشل Firebase: ' . $e->getMessage());
        }
    }

    /**
     * جلب البريد الإلكتروني للمستخدم
     */
    protected function resolveUserEmail(?string $userType, ?int $employeeId, ?int $clientId): ?string
    {
        try {
            if ($userType === 'employee' || $userType === UserType::EMPLOYEE->value) {
                $employee = Employee::find($employeeId);
                return $employee->email ?? $employee->personal_email ?? null;
            }

            if ($userType === 'client' || $userType === UserType::CLIENT->value) {
                $client = Client::find($clientId);
                return $client->email ?? null;
            }
        } catch (\Throwable $e) {
            // تجاوز
        }

        return null;
    }

    /**
     * جلب FCM Token للمستخدم
     * 
     * ⚠️ يتطلب إضافة عمود fcm_token إلى جدول employees و clients
     * ALTER TABLE employees ADD COLUMN fcm_token VARCHAR(255) NULL;
     * ALTER TABLE clients ADD COLUMN fcm_token VARCHAR(255) NULL;
     */
    protected function resolveUserFcmToken(?string $userType, ?int $employeeId, ?int $clientId): ?string
    {
        try {
            if ($userType === 'employee' || $userType === UserType::EMPLOYEE->value) {
                $employee = Employee::find($employeeId);
                return $employee->fcm_token ?? null;
            }

            if ($userType === 'client' || $userType === UserType::CLIENT->value) {
                $client = Client::find($clientId);
                return $client->fcm_token ?? null;
            }
        } catch (\Throwable $e) {
            // تجاوز — العمود قد لا يكون موجوداً بعد
        }

        return null;
    }

    /**
     * إرسال إشعار كسب نقاط
     */
    public function notifyPointsEarned(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('points_earned', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار استبدال مكافأة
     */
    public function notifyRewardRedeemed(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('reward_redeemed', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار ترقية مستوى
     */
    public function notifyTierUpgraded(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('tier_upgraded', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار إنجاز جديد
     */
    public function notifyAchievementUnlocked(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('achievement_unlocked', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار إنهاء تحدي
     */
    public function notifyChallengeCompleted(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('challenge_completed', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار تأكيد إحالة
     */
    public function notifyReferralConfirmed(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('referral_confirmed', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار اقتراب انتهاء النقاط
     */
    public function notifyPointsExpiring(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('points_expiring', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار الموافقة على الاستبدال
     */
    public function notifyRedemptionApproved(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('redemption_approved', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار تسليم المكافأة
     */
    public function notifyRedemptionDelivered(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('redemption_delivered', $userType, $employeeId, $clientId, $data);
    }

    /**
     * إرسال إشعار إلغاء الاستبدال
     */
    public function notifyRedemptionCancelled(string $userType, ?int $employeeId, ?int $clientId, array $data): array
    {
        return $this->send('redemption_cancelled', $userType, $employeeId, $clientId, $data);
    }

    /**
     * قائمة أنواع الأحداث المدعومة مع وصف ومتغيرات كل حدث
     */
    public static function getEventTypes(): array
    {
        return [
            'points_earned' => [
                'label' => 'كسب نقاط',
                'description' => 'عند كسب المستخدم لنقاط جديدة',
                'variables' => ['user_name', 'points', 'action_type', 'balance_after'],
            ],
            'reward_redeemed' => [
                'label' => 'استبدال مكافأة',
                'description' => 'عند إنشاء طلب استبدال مكافأة',
                'variables' => ['user_name', 'reward_name', 'points_used', 'redemption_number'],
            ],
            'redemption_approved' => [
                'label' => 'الموافقة على الاستبدال',
                'description' => 'عند الموافقة على طلب استبدال',
                'variables' => ['user_name', 'reward_name', 'redemption_number'],
            ],
            'redemption_delivered' => [
                'label' => 'تسليم المكافأة',
                'description' => 'عند تسليم المكافأة',
                'variables' => ['user_name', 'reward_name', 'redemption_number'],
            ],
            'redemption_cancelled' => [
                'label' => 'إلغاء الاستبدال',
                'description' => 'عند إلغاء طلب استبدال',
                'variables' => ['user_name', 'reward_name', 'points_returned', 'redemption_number'],
            ],
            'tier_upgraded' => [
                'label' => 'ترقية المستوى',
                'description' => 'عند ترقية المستخدم لمستوى أعلى',
                'variables' => ['user_name', 'old_tier', 'new_tier', 'points'],
            ],
            'achievement_unlocked' => [
                'label' => 'إنجاز جديد',
                'description' => 'عند تحقيق المستخدم لإنجاز',
                'variables' => ['user_name', 'achievement_name', 'points_awarded'],
            ],
            'challenge_completed' => [
                'label' => 'إنهاء تحدي',
                'description' => 'عند إنهاء تحدي وتوزيع الجوائز',
                'variables' => ['user_name', 'challenge_name', 'rank', 'points_awarded'],
            ],
            'referral_confirmed' => [
                'label' => 'تأكيد إحالة',
                'description' => 'عند تأكيد إحالة',
                'variables' => ['user_name', 'referral_code', 'referred_name'],
            ],
            'points_expiring' => [
                'label' => 'اقتراب انتهاء النقاط',
                'description' => 'عند اقتراب انتهاء صلاحية النقاط',
                'variables' => ['user_name', 'points_expiring', 'expiry_date', 'days_remaining'],
            ],
        ];
    }
}
