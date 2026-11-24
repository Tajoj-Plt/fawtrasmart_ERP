<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointsNotificationTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * أنواع الأحداث يجب أن تتطابق مع PointsNotificationService::getEventTypes()
     * أسماء المتغيرات يجب أن تتطابق مع ما يمرره الكنترولر في مصفوفة $data
     */
    public function run(): void
    {
        $templates = [
            // =====================================================
            // 1. كسب نقاط — يرسله PointsTransactionController::store()
            // =====================================================
            [
                'event_type' => 'points_earned',
                'user_type' => 'both',
                'title_ar' => 'حصلت على نقاط جديدة! 🎉',
                'title_en' => 'You Earned Points! 🎉',
                'body_ar' => 'مبروك {user_name}! حصلت على {points} نقطة من {action_type}. رصيدك الآن {balance_after} نقطة.',
                'body_en' => 'Congratulations {user_name}! You earned {points} points from {action_type}. Your balance is now {balance_after} points.',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'points', 'action_type', 'balance_after']),
                'is_active' => true,
            ],
            // =====================================================
            // 2. استبدال مكافأة — يرسله RewardRedemptionController::store()
            // =====================================================
            [
                'event_type' => 'reward_redeemed',
                'user_type' => 'both',
                'title_ar' => 'تم استبدال النقاط 🎁',
                'title_en' => 'Points Redeemed 🎁',
                'body_ar' => '{user_name}، تم استخدام {points_used} نقطة للحصول على "{reward_name}". رقم الطلب: {redemption_number}.',
                'body_en' => '{user_name}, {points_used} points used to get "{reward_name}". Order #: {redemption_number}.',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'reward_name', 'points_used', 'redemption_number']),
                'is_active' => true,
            ],
            // =====================================================
            // 3. الموافقة على الاستبدال — يرسله RewardRedemptionController::approve()
            // =====================================================
            [
                'event_type' => 'redemption_approved',
                'user_type' => 'both',
                'title_ar' => 'تمت الموافقة على مكافأتك ✅',
                'title_en' => 'Your Reward Was Approved ✅',
                'body_ar' => '{user_name}، تمت الموافقة على طلب استبدال "{reward_name}". رقم الطلب: {redemption_number}.',
                'body_en' => '{user_name}, your redemption request for "{reward_name}" has been approved. Order #: {redemption_number}.',
                'channels' => json_encode(['push', 'sms', 'email', 'database']),
                'variables' => json_encode(['user_name', 'reward_name', 'redemption_number']),
                'is_active' => true,
            ],
            // =====================================================
            // 4. تسليم المكافأة — يرسله RewardRedemptionController::deliver()
            // =====================================================
            [
                'event_type' => 'redemption_delivered',
                'user_type' => 'both',
                'title_ar' => 'مكافأتك جاهزة! 📦',
                'title_en' => 'Your Reward Is Ready! 📦',
                'body_ar' => '{user_name}، مكافأتك "{reward_name}" جاهزة للاستلام. رقم الطلب: {redemption_number}.',
                'body_en' => '{user_name}, your "{reward_name}" reward is ready for pickup. Order #: {redemption_number}.',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'reward_name', 'redemption_number']),
                'is_active' => true,
            ],
            // =====================================================
            // 5. إلغاء الاستبدال — يرسله RewardRedemptionController::cancel()
            // =====================================================
            [
                'event_type' => 'redemption_cancelled',
                'user_type' => 'both',
                'title_ar' => 'تم إلغاء طلب الاستبدال ❌',
                'title_en' => 'Redemption Cancelled ❌',
                'body_ar' => '{user_name}، تم إلغاء طلب استبدال "{reward_name}" واسترجاع {points_returned} نقطة. رقم الطلب: {redemption_number}.',
                'body_en' => '{user_name}, your redemption of "{reward_name}" was cancelled and {points_returned} points were returned. Order #: {redemption_number}.',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'reward_name', 'points_returned', 'redemption_number']),
                'is_active' => true,
            ],
            // =====================================================
            // 6. ترقية المستوى — يرسله PointsTransactionController::checkAndLogTierUpgrade()
            // =====================================================
            [
                'event_type' => 'tier_upgraded',
                'user_type' => 'both',
                'title_ar' => 'ترقية المستوى! 🏆',
                'title_en' => 'Tier Upgrade! 🏆',
                'body_ar' => 'مبروك {user_name}! تمت ترقيتك من "{old_tier}" إلى "{new_tier}" بـ {points} نقطة!',
                'body_en' => 'Congratulations {user_name}! You were upgraded from "{old_tier}" to "{new_tier}" with {points} points!',
                'channels' => json_encode(['push', 'sms', 'database']),
                'variables' => json_encode(['user_name', 'old_tier', 'new_tier', 'points']),
                'is_active' => true,
            ],
            // =====================================================
            // 7. إنجاز جديد — يرسله PointsTransactionController (achievement check)
            // =====================================================
            [
                'event_type' => 'achievement_unlocked',
                'user_type' => 'both',
                'title_ar' => 'إنجاز جديد! 🎖️',
                'title_en' => 'Achievement Unlocked! 🎖️',
                'body_ar' => 'رائع {user_name}! حققت إنجاز "{achievement_name}" وحصلت على {bonus_points} نقطة إضافية!',
                'body_en' => 'Awesome {user_name}! You unlocked "{achievement_name}" and earned {bonus_points} bonus points!',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'achievement_name', 'bonus_points']),
                'is_active' => true,
            ],
            // =====================================================
            // 8. إنهاء تحدي — يرسله ChallengeController
            // =====================================================
            [
                'event_type' => 'challenge_completed',
                'user_type' => 'both',
                'title_ar' => 'أكملت التحدي! 🏆',
                'title_en' => 'Challenge Completed! 🏆',
                'body_ar' => 'مبروك {user_name}! أكملت تحدي "{challenge_name}" وحصلت على {points_awarded} نقطة!',
                'body_en' => 'Congratulations {user_name}! You completed "{challenge_name}" and earned {points_awarded} points!',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'challenge_name', 'rank', 'points_awarded']),
                'is_active' => true,
            ],
            // =====================================================
            // 9. تأكيد إحالة — يرسله ReferralController
            // =====================================================
            [
                'event_type' => 'referral_confirmed',
                'user_type' => 'both',
                'title_ar' => 'إحالة ناجحة! 👥',
                'title_en' => 'Successful Referral! 👥',
                'body_ar' => 'رائع {user_name}! انضم {referred_name} عبر إحالتك. حصلت على نقاط إضافية!',
                'body_en' => 'Great {user_name}! {referred_name} joined through your referral. You earned bonus points!',
                'channels' => json_encode(['push', 'database']),
                'variables' => json_encode(['user_name', 'referral_code', 'referred_name']),
                'is_active' => true,
            ],
            // =====================================================
            // 10. اقتراب انتهاء النقاط — يرسله ExpiryJob
            // =====================================================
            [
                'event_type' => 'points_expiring',
                'user_type' => 'both',
                'title_ar' => 'نقاطك ستنتهي قريباً! ⏰',
                'title_en' => 'Your Points Are Expiring Soon! ⏰',
                'body_ar' => 'تنبيه {user_name}: لديك {points_expiring} نقطة ستنتهي صلاحيتها في {days_remaining} يوم ({expiry_date}). استخدمها الآن!',
                'body_en' => 'Warning {user_name}: You have {points_expiring} points expiring in {days_remaining} days ({expiry_date}). Use them now!',
                'channels' => json_encode(['push', 'sms', 'email', 'database']),
                'variables' => json_encode(['user_name', 'points_expiring', 'expiry_date', 'days_remaining']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as &$template) {
            $template['created_at'] = now();
            $template['updated_at'] = now();
        }

        // إدراج القالب الناقص فقط، بدل مسح الجدول وإعادة بنائه.
        //
        // كان `truncate()` — وهو يسقط دائماً بـ «Cannot truncate a table
        // referenced in a foreign key constraint»: جدول
        // `points_notifications_log` يشير إلى هذا الجدول، وMySQL يمنع TRUNCATE
        // على مرجعٍ لمفتاح أجنبي مهما كان الجدول المشير فارغاً. أي أن هذا
        // السيدر لم يكن ينجح ولا مرة، ويخرج النظام بلا قوالب إشعارات نقاط.
        //
        // و`delete()` ليس بديلاً: سجلّات الإشعارات المرسَلة تشير إلى القوالب،
        // فحذفُها يكسر تاريخ ما أُرسل. المطابقة على `event_type` و`user_type`
        // معاً لأنهما ما يميّز القالب فعلياً.
        foreach ($templates as $template) {
            DB::table('points_notification_templates')->updateOrInsert(
                [
                    'event_type' => $template['event_type'],
                    'user_type' => $template['user_type'],
                ],
                $template
            );
        }
    }
}
