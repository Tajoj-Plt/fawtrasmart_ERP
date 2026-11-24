<?php

namespace App\Models\Ecosystem;

use Illuminate\Database\Eloquent\Model;

class PointsGeneralSetting extends Model
{
    protected $table = 'points_general_settings';

    protected $fillable = [
        // النظام الرئيسي
        'system_enabled',
        'enable_employee_points',
        'enable_client_points',

        // إعدادات النقاط العامة
        'currency_name',
        'currency_name_plural',
        'currency_symbol',
        'min_transfer_points',
        'max_daily_earn',
        'points_decimal_places',

        // الأنظمة الفرعية
        'enable_tiers_system',
        'enable_rewards_system',
        'enable_achievements_system',
        'enable_challenges_system',
        'enable_referrals_system',
        'enable_expiry_system',
        'enable_notifications_system',

        // إعدادات المكافآت
        'require_redemption_approval',
        'allow_partial_redemption',
        'max_daily_redemptions',
        'redemption_cooldown_hours',

        // إعدادات التحديات
        'auto_enroll_challenges',
        'allow_challenge_teams',
        'max_active_challenges',

        // إعدادات العرض
        'show_points_in_profile',
        'show_leaderboard',
        'show_tier_badge',
        'show_achievements_public',
        'leaderboard_size',

        // إعدادات الأمان
        'log_all_transactions',
        'enable_fraud_detection',
        'max_points_per_transaction',
        'suspicious_threshold',

        // إضافية
        'terms_conditions',
        'maintenance_schedule',
        'last_maintenance_at',
    ];

    protected $casts = [
        'system_enabled' => 'boolean',
        'enable_employee_points' => 'boolean',
        'enable_client_points' => 'boolean',
        'enable_tiers_system' => 'boolean',
        'enable_rewards_system' => 'boolean',
        'enable_achievements_system' => 'boolean',
        'enable_challenges_system' => 'boolean',
        'enable_referrals_system' => 'boolean',
        'enable_expiry_system' => 'boolean',
        'enable_notifications_system' => 'boolean',
        'require_redemption_approval' => 'boolean',
        'allow_partial_redemption' => 'boolean',
        'auto_enroll_challenges' => 'boolean',
        'allow_challenge_teams' => 'boolean',
        'show_points_in_profile' => 'boolean',
        'show_leaderboard' => 'boolean',
        'show_tier_badge' => 'boolean',
        'show_achievements_public' => 'boolean',
        'log_all_transactions' => 'boolean',
        'enable_fraud_detection' => 'boolean',
        'maintenance_schedule' => 'array',
        'last_maintenance_at' => 'datetime',
        'min_transfer_points' => 'integer',
        'max_daily_earn' => 'integer',
        'points_decimal_places' => 'integer',
        'max_daily_redemptions' => 'integer',
        'redemption_cooldown_hours' => 'integer',
        'max_active_challenges' => 'integer',
        'leaderboard_size' => 'integer',
        'max_points_per_transaction' => 'integer',
        'suspicious_threshold' => 'integer',
    ];

    // =====================================================
    // Singleton Helper
    // =====================================================

    /**
     * Get the single settings instance (creates with defaults if not exists)
     */
    public static function instance(): self
    {
        $settings = static::first();
        if (!$settings) {
            $settings = static::create([]);
        }
        return $settings;
    }

    /**
     * Get a specific setting value
     */
    public static function getValue(string $key, $default = null)
    {
        return static::instance()->{$key} ?? $default;
    }

    /**
     * Check if main system is enabled
     */
    public static function isSystemEnabled(): bool
    {
        return (bool) static::getValue('system_enabled', true);
    }

    /**
     * Check if a subsystem is enabled
     */
    public static function isSubsystemEnabled(string $subsystem): bool
    {
        if (!static::isSystemEnabled()) {
            return false;
        }

        $key = "enable_{$subsystem}_system";
        return (bool) static::getValue($key, false);
    }

    // =====================================================
    // Setting Group Helpers
    // =====================================================

    public function getActiveSubsystems(): array
    {
        $subsystems = [];
        $checks = [
            'tiers' => 'enable_tiers_system',
            'rewards' => 'enable_rewards_system',
            'achievements' => 'enable_achievements_system',
            'challenges' => 'enable_challenges_system',
            'referrals' => 'enable_referrals_system',
            'expiry' => 'enable_expiry_system',
            'notifications' => 'enable_notifications_system',
        ];

        foreach ($checks as $name => $field) {
            if ($this->{$field}) {
                $subsystems[] = $name;
            }
        }

        return $subsystems;
    }

    public function getSubsystemLabels(): array
    {
        return [
            'tiers' => 'نظام المستويات',
            'rewards' => 'نظام المكافآت',
            'achievements' => 'نظام الإنجازات',
            'challenges' => 'نظام التحديات',
            'referrals' => 'نظام الإحالات',
            'expiry' => 'نظام انتهاء الصلاحية',
            'notifications' => 'نظام الإشعارات',
        ];
    }
}
