<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('points_general_settings')) {
            Schema::create('points_general_settings', function (Blueprint $table) {
                $table->bigIncrements('id');

                // ========== تفعيل/تعطيل النظام الرئيسي ==========
                $table->tinyInteger('system_enabled')->default(1)->comment('تفعيل نظام النقاط');
                $table->tinyInteger('enable_employee_points')->default(1)->comment('تفعيل نقاط الموظفين');
                $table->tinyInteger('enable_client_points')->default(1)->comment('تفعيل نقاط العملاء');

                // ========== إعدادات النقاط العامة ==========
                $table->string('currency_name')->default('نقطة')->comment('اسم عملة النقاط');
                $table->string('currency_name_plural')->default('نقاط')->comment('اسم عملة النقاط (جمع)');
                $table->string('currency_symbol')->default('⭐')->comment('رمز النقاط');
                $table->integer('min_transfer_points')->default(10)->comment('الحد الأدنى لتحويل النقاط');
                $table->integer('max_daily_earn')->default(0)->comment('الحد الأقصى اليومي للنقاط (0 = لا حد)');
                $table->integer('points_decimal_places')->default(0)->comment('عدد الخانات العشرية');

                // ========== تفعيل الأنظمة الفرعية ==========
                $table->tinyInteger('enable_tiers_system')->default(1)->comment('تفعيل نظام المستويات');
                $table->tinyInteger('enable_rewards_system')->default(1)->comment('تفعيل نظام المكافآت');
                $table->tinyInteger('enable_achievements_system')->default(1)->comment('تفعيل نظام الإنجازات');
                $table->tinyInteger('enable_challenges_system')->default(1)->comment('تفعيل نظام التحديات');
                $table->tinyInteger('enable_referrals_system')->default(1)->comment('تفعيل نظام الإحالات');
                $table->tinyInteger('enable_expiry_system')->default(1)->comment('تفعيل نظام انتهاء الصلاحية');
                $table->tinyInteger('enable_notifications_system')->default(1)->comment('تفعيل نظام الإشعارات');

                // ========== إعدادات المكافآت ==========
                $table->tinyInteger('require_redemption_approval')->default(1)->comment('تتطلب موافقة على الاستبدال');
                $table->tinyInteger('allow_partial_redemption')->default(0)->comment('السماح بالاستبدال الجزئي');
                $table->integer('max_daily_redemptions')->default(3)->comment('الحد الأقصى لعمليات الاستبدال يومياً');
                $table->integer('redemption_cooldown_hours')->default(0)->comment('فترة الانتظار بين الاستبدالات (ساعات)');

                // ========== إعدادات التحديات ==========
                $table->tinyInteger('auto_enroll_challenges')->default(0)->comment('التسجيل التلقائي في التحديات');
                $table->tinyInteger('allow_challenge_teams')->default(1)->comment('السماح بفرق التحديات');
                $table->integer('max_active_challenges')->default(5)->comment('الحد الأقصى للتحديات النشطة');

                // ========== إعدادات العرض ==========
                $table->tinyInteger('show_points_in_profile')->default(1)->comment('عرض النقاط في الملف الشخصي');
                $table->tinyInteger('show_leaderboard')->default(1)->comment('عرض لوحة المتصدرين');
                $table->tinyInteger('show_tier_badge')->default(1)->comment('عرض شارة المستوى');
                $table->tinyInteger('show_achievements_public')->default(0)->comment('عرض الإنجازات للعامة');
                $table->integer('leaderboard_size')->default(10)->comment('عدد المتصدرين في اللوحة');

                // ========== إعدادات الأمان ==========
                $table->tinyInteger('log_all_transactions')->default(1)->comment('تسجيل كل العمليات');
                $table->tinyInteger('enable_fraud_detection')->default(0)->comment('تفعيل كشف الاحتيال');
                $table->integer('max_points_per_transaction')->default(10000)->comment('الحد الأقصى للنقاط لكل معاملة');
                $table->integer('suspicious_threshold')->default(5000)->comment('حد النقاط المشبوهة');

                // ========== إعدادات إضافية ==========
                $table->text('terms_conditions')->nullable()->comment('شروط وأحكام نظام النقاط');
                $table->json('maintenance_schedule')->nullable()->comment('جدول الصيانة');
                $table->timestamp('last_maintenance_at')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('points_general_settings');
    }
};
