<?php

namespace Modules\Ecosystem\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Ecosystem\PointsGeneralSetting;

class PointsGeneralSettingController extends Controller
{
    /**
     * Display the general settings page
     */
    public function index()
    {
        $settings = PointsGeneralSetting::instance();
        return view('ecosystem::points.general-settings.index', compact('settings'));
    }

    /**
     * Update all general settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            // النظام الرئيسي
            'system_enabled' => 'nullable|boolean',
            'enable_employee_points' => 'nullable|boolean',
            'enable_client_points' => 'nullable|boolean',

            // إعدادات النقاط العامة
            'currency_name' => 'required|string|max:50',
            'currency_name_plural' => 'required|string|max:50',
            'currency_symbol' => 'required|string|max:10',
            'min_transfer_points' => 'required|integer|min:0',
            'max_daily_earn' => 'required|integer|min:0',
            'points_decimal_places' => 'required|integer|min:0|max:4',

            // الأنظمة الفرعية
            'enable_tiers_system' => 'nullable|boolean',
            'enable_rewards_system' => 'nullable|boolean',
            'enable_achievements_system' => 'nullable|boolean',
            'enable_challenges_system' => 'nullable|boolean',
            'enable_referrals_system' => 'nullable|boolean',
            'enable_expiry_system' => 'nullable|boolean',
            'enable_notifications_system' => 'nullable|boolean',

            // إعدادات المكافآت
            'require_redemption_approval' => 'nullable|boolean',
            'allow_partial_redemption' => 'nullable|boolean',
            'max_daily_redemptions' => 'required|integer|min:0',
            'redemption_cooldown_hours' => 'required|integer|min:0',

            // إعدادات التحديات
            'auto_enroll_challenges' => 'nullable|boolean',
            'allow_challenge_teams' => 'nullable|boolean',
            'max_active_challenges' => 'required|integer|min:1',

            // إعدادات العرض
            'show_points_in_profile' => 'nullable|boolean',
            'show_leaderboard' => 'nullable|boolean',
            'show_tier_badge' => 'nullable|boolean',
            'show_achievements_public' => 'nullable|boolean',
            'leaderboard_size' => 'required|integer|min:3|max:100',

            // إعدادات الأمان
            'log_all_transactions' => 'nullable|boolean',
            'enable_fraud_detection' => 'nullable|boolean',
            'max_points_per_transaction' => 'required|integer|min:1',
            'suspicious_threshold' => 'required|integer|min:1',

            // إضافية
            'terms_conditions' => 'nullable|string|max:10000',
        ]);

        // Handle boolean toggles (unchecked checkboxes won't be sent)
        $booleanFields = [
            'system_enabled', 'enable_employee_points', 'enable_client_points',
            'enable_tiers_system', 'enable_rewards_system', 'enable_achievements_system',
            'enable_challenges_system', 'enable_referrals_system', 'enable_expiry_system',
            'enable_notifications_system', 'require_redemption_approval', 'allow_partial_redemption',
            'auto_enroll_challenges', 'allow_challenge_teams',
            'show_points_in_profile', 'show_leaderboard', 'show_tier_badge',
            'show_achievements_public', 'log_all_transactions', 'enable_fraud_detection',
        ];

        foreach ($booleanFields as $field) {
            $validated[$field] = $request->has($field) ? 1 : 0;
        }

        $settings = PointsGeneralSetting::instance();
        $settings->update($validated);

        return redirect()->route('ecosystem.points.general-settings.index')
            ->with('success', 'تم حفظ الإعدادات العامة بنجاح');
    }

    /**
     * Toggle a specific subsystem
     */
    public function toggleSubsystem(Request $request, $subsystem)
    {
        $settings = PointsGeneralSetting::instance();
        $field = "enable_{$subsystem}_system";

        if (!array_key_exists($field, $settings->getAttributes()) && !in_array($field, $settings->getFillable())) {
            return response()->json(['success' => false, 'message' => 'نظام فرعي غير صالح'], 400);
        }

        $settings->{$field} = !$settings->{$field};
        $settings->save();

        return response()->json([
            'success' => true,
            'enabled' => (bool) $settings->{$field},
            'message' => $settings->{$field} ? 'تم تفعيل النظام' : 'تم تعطيل النظام',
        ]);
    }

    /**
     * Toggle the entire system on/off
     */
    public function toggleSystem(Request $request)
    {
        $settings = PointsGeneralSetting::instance();
        $settings->system_enabled = !$settings->system_enabled;
        $settings->save();

        return response()->json([
            'success' => true,
            'enabled' => (bool) $settings->system_enabled,
            'message' => $settings->system_enabled ? 'تم تفعيل نظام النقاط' : 'تم تعطيل نظام النقاط',
        ]);
    }
}
