<?php

namespace Database\Seeders;

use App\Models\Ecosystem\PointsGeneralSetting;
use Illuminate\Database\Seeder;

class PointsGeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * ينشئ صف الإعدادات العامة الوحيد لنظام النقاط (Singleton)
     * القيم الافتراضية تتطابق مع الـ SQL في points_system.sql سطر 926
     */
    public function run(): void
    {
        PointsGeneralSetting::firstOrCreate(
            ['id' => 1], // شرط البحث: الصف الأول فقط
            [
                // ========== تفعيل/تعطيل النظام الرئيسي ==========
                'system_enabled' => true,
                'enable_employee_points' => true,
                'enable_client_points' => true,

                // ========== إعدادات النقاط العامة ==========
                'currency_name' => 'نقطة',
                'currency_name_plural' => 'نقاط',
                'currency_symbol' => '⭐',
                'min_transfer_points' => 10,
                'max_daily_earn' => 0, // 0 = لا حد
                'points_decimal_places' => 0,

                // ========== تفعيل الأنظمة الفرعية ==========
                'enable_tiers_system' => true,
                'enable_rewards_system' => true,
                'enable_achievements_system' => true,
                'enable_challenges_system' => true,
                'enable_referrals_system' => true,
                'enable_expiry_system' => true,
                'enable_notifications_system' => true,

                // ========== إعدادات المكافآت ==========
                'require_redemption_approval' => true,
                'allow_partial_redemption' => false,
                'max_daily_redemptions' => 3,
                'redemption_cooldown_hours' => 0,

                // ========== إعدادات التحديات ==========
                'auto_enroll_challenges' => false,
                'allow_challenge_teams' => true,
                'max_active_challenges' => 5,

                // ========== إعدادات العرض ==========
                'show_points_in_profile' => true,
                'show_leaderboard' => true,
                'show_tier_badge' => true,
                'show_achievements_public' => false,
                'leaderboard_size' => 10,

                // ========== إعدادات الأمان ==========
                'log_all_transactions' => true,
                'enable_fraud_detection' => false,
                'max_points_per_transaction' => 10000,
                'suspicious_threshold' => 5000,
            ]
        );
    }
}
