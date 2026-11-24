<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * نظام النقاط والحوافز المتكامل (Points & Rewards Ecosystem) v2.0
     */
    public function up(): void
    {
        // =====================================================
        // 1. إعدادات النقاط
        // =====================================================
        $this->createIfMissing('points_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['employee', 'client'])->comment('نوع المستخدم');
            $table->string('action_type')->comment('نوع العملية');
            $table->string('action_name')->comment('اسم العملية');
            $table->text('description')->nullable()->comment('وصف العملية');
            $table->integer('points')->default(0)->comment('عدد النقاط - موجب للإضافة وسالب للخصم');
            $table->boolean('is_active')->default(true)->comment('هل الإعداد مفعل');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_type', 'action_type', 'branch_id'], 'points_settings_unique');
        });

        // =====================================================
        // 2. معاملات النقاط
        // =====================================================
        $this->createIfMissing('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['employee', 'client'])->comment('نوع المستخدم');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('معرف الموظف');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete()->comment('معرف العميل');
            $table->string('action_type')->comment('نوع العملية');
            $table->integer('points')->comment('عدد النقاط');
            $table->integer('balance_before')->default(0)->comment('الرصيد قبل العملية');
            $table->integer('balance_after')->default(0)->comment('الرصيد بعد العملية');
            $table->text('description')->nullable()->comment('وصف العملية');
            $table->string('reference_type')->nullable()->comment('نوع المرجع - Invoice, Receipt, Visit, etc.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('معرف المرجع');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'completed', 'cancelled', 'expired'])->default('completed')->comment('حالة المعاملة');
            $table->timestamp('expires_at')->nullable()->comment('تاريخ انتهاء صلاحية النقاط');
            $table->json('metadata')->nullable()->comment('بيانات إضافية');
            $table->timestamps();

            $table->index(['user_type', 'employee_id'], 'points_employee_index');
            $table->index(['user_type', 'client_id'], 'points_client_index');
            $table->index(['action_type'], 'points_action_index');
            $table->index(['reference_type', 'reference_id'], 'points_reference_index');
            $table->index(['created_at'], 'points_date_index');
            $table->index(['status'], 'points_status_index');
        });

        // =====================================================
        // 3. المستويات (Tiers)
        // =====================================================
        $this->createIfMissing('points_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم المستوى');
            $table->string('name_en')->nullable()->comment('اسم المستوى بالإنجليزية');
            $table->enum('user_type', ['employee', 'client'])->comment('نوع المستخدم');
            $table->integer('min_points')->default(0)->comment('الحد الأدنى للنقاط');
            $table->integer('max_points')->nullable()->comment('الحد الأقصى للنقاط - NULL = غير محدود');
            $table->string('color', 7)->nullable()->comment('لون المستوى (HEX)');
            $table->string('icon')->nullable()->comment('أيقونة المستوى');
            $table->string('image')->nullable()->comment('صورة المستوى');
            $table->json('benefits')->nullable()->comment('المميزات والفوائد');
            $table->decimal('points_multiplier', 3, 2)->default(1.00)->comment('مضاعف النقاط');
            $table->decimal('discount_percentage', 5, 2)->nullable()->comment('نسبة خصم دائمة للعملاء');
            $table->boolean('priority_support')->default(false)->comment('أولوية الدعم');
            $table->boolean('free_shipping')->default(false)->comment('شحن مجاني');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0)->comment('ترتيب العرض');
            $table->timestamps();

            $table->index(['user_type'], 'points_tiers_user_type_index');
            $table->index(['min_points'], 'points_tiers_min_points_index');
        });

        // =====================================================
        // 4. سجل ترقيات المستوى
        // =====================================================
        $this->createIfMissing('tier_upgrades_log', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignId('old_tier_id')->nullable()->constrained('points_tiers')->nullOnDelete();
            $table->foreignId('new_tier_id')->constrained('points_tiers')->cascadeOnDelete();
            $table->integer('points_at_upgrade')->comment('النقاط عند الترقية');
            $table->enum('upgrade_type', ['automatic', 'manual'])->default('automatic');
            $table->unsignedBigInteger('upgraded_by')->nullable()->comment('من قام بالترقية اليدوية');
            $table->timestamp('created_at')->nullable();

            $table->index(['employee_id'], 'tier_upgrades_employee_index');
            $table->index(['client_id'], 'tier_upgrades_client_index');
        });

        // =====================================================
        // 5. المكافآت
        // =====================================================
        $this->createIfMissing('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم المكافأة');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable()->comment('وصف المكافأة');
            $table->enum('user_type', ['employee', 'client', 'both'])->comment('من يستطيع الاستبدال');
            $table->integer('points_required')->comment('النقاط المطلوبة للاستبدال');
            $table->enum('reward_type', [
                'discount_percentage',
                'discount_amount',
                'gift',
                'voucher',
                'cash',
                'leave_day',
                'free_shipping',
                'product',
                'service',
                'other',
            ])->comment('نوع المكافأة');
            $table->decimal('reward_value', 10, 2)->nullable()->comment('قيمة المكافأة (نسبة أو مبلغ)');
            $table->unsignedBigInteger('product_id')->nullable()->comment('المنتج (إذا كانت المكافأة منتج)');
            $table->integer('quantity_available')->nullable()->comment('الكمية المتاحة - NULL = غير محدود');
            $table->integer('quantity_redeemed')->default(0)->comment('الكمية المستبدلة');
            $table->integer('max_per_user')->nullable()->comment('الحد الأقصى لكل مستخدم - NULL = غير محدود');
            $table->foreignId('min_tier_id')->nullable()->constrained('points_tiers')->nullOnDelete()->comment('الحد الأدنى للمستوى المطلوب');
            $table->string('image')->nullable();
            $table->text('terms_conditions')->nullable()->comment('الشروط والأحكام');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false)->comment('مكافأة مميزة');
            $table->timestamp('starts_at')->nullable()->comment('تاريخ بداية التوفر');
            $table->timestamp('expires_at')->nullable()->comment('تاريخ انتهاء التوفر');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_type'], 'rewards_user_type_index');
            $table->index(['reward_type'], 'rewards_reward_type_index');
            $table->index(['is_active'], 'rewards_is_active_index');
            $table->index(['points_required'], 'rewards_points_required_index');
        });

        // =====================================================
        // 6. عمليات استبدال المكافآت
        // =====================================================
        $this->createIfMissing('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('redemption_number', 50)->unique()->comment('رقم عملية الاستبدال');
            $table->foreignId('reward_id')->constrained('rewards')->cascadeOnDelete();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->integer('points_used')->comment('النقاط المستخدمة');
            $table->integer('quantity')->default(1)->comment('الكمية المطلوبة');
            $table->decimal('reward_value', 10, 2)->nullable()->comment('قيمة المكافأة وقت الاستبدال');
            $table->enum('status', ['pending', 'approved', 'processing', 'delivered', 'cancelled', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->enum('delivery_method', ['pickup', 'delivery', 'digital', 'automatic'])->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('voucher_code', 50)->nullable()->unique()->comment('كود القسيمة (إذا كانت قسيمة)');
            $table->boolean('voucher_used')->default(false);
            $table->timestamp('voucher_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('تاريخ انتهاء صلاحية الاستبدال');
            $table->json('metadata')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index(['status'], 'reward_redemptions_status_index');
            $table->index(['employee_id'], 'reward_redemptions_employee_index');
            $table->index(['client_id'], 'reward_redemptions_client_index');
        });

        // =====================================================
        // 7. الإنجازات
        // =====================================================
        $this->createIfMissing('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم الإنجاز');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable()->comment('وصف كيفية الحصول على الإنجاز');
            $table->enum('user_type', ['employee', 'client', 'both']);
            $table->string('category', 100)->nullable()->comment('تصنيف الإنجاز (مبيعات، ولاء، تفاعل...)');
            $table->string('icon')->nullable()->comment('أيقونة الشارة');
            $table->string('image')->nullable()->comment('صورة الشارة');
            $table->string('color', 7)->nullable()->comment('لون الشارة');
            $table->string('condition_type', 100)->comment('نوع الشرط');
            $table->enum('condition_operator', ['>=', '>', '=', '<', '<='])->default('>=')->comment('عامل المقارنة');
            $table->integer('condition_value')->comment('القيمة المطلوبة للتحقيق');
            $table->enum('condition_period', ['all_time', 'yearly', 'monthly', 'weekly', 'daily'])->default('all_time')->comment('فترة الشرط');
            $table->integer('bonus_points')->default(0)->comment('نقاط إضافية عند التحقيق');
            $table->boolean('is_hidden')->default(false)->comment('إنجاز سري');
            $table->boolean('is_one_time')->default(true)->comment('يُمنح مرة واحدة فقط');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_type'], 'achievements_user_type_index');
            $table->index(['condition_type'], 'achievements_condition_type_index');
            $table->index(['category'], 'achievements_category_index');
        });

        // =====================================================
        // 8. إنجازات المستخدمين
        // =====================================================
        $this->createIfMissing('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->timestamp('achieved_at')->comment('تاريخ التحقيق');
            $table->integer('achievement_value')->nullable()->comment('القيمة التي حققها (للتوثيق)');
            $table->integer('points_awarded')->default(0)->comment('النقاط الممنوحة');
            $table->boolean('notified')->default(false)->comment('هل تم إرسال إشعار');
            $table->timestamp('created_at')->nullable();

            $table->unique(['achievement_id', 'user_type', 'employee_id', 'client_id'], 'user_achievements_unique');
            $table->index(['employee_id'], 'user_achievements_employee_index');
            $table->index(['client_id'], 'user_achievements_client_index');
            $table->index(['achieved_at'], 'user_achievements_achieved_at_index');
        });

        // =====================================================
        // 9. التحديات
        // =====================================================
        $this->createIfMissing('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم التحدي');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->enum('user_type', ['employee', 'client', 'both']);
            $table->enum('challenge_type', ['individual', 'team', 'branch', 'department'])->default('individual')->comment('نوع التحدي');
            $table->enum('competition_type', ['target', 'ranking', 'first_n'])->default('target')->comment('نوع المنافسة');
            $table->string('target_action')->comment('العملية المطلوبة');
            $table->integer('target_value')->comment('الهدف المطلوب');
            $table->string('target_unit', 50)->nullable()->comment('وحدة القياس');
            $table->integer('reward_points')->comment('نقاط الفوز');
            $table->integer('reward_points_2nd')->nullable()->comment('نقاط المركز الثاني');
            $table->integer('reward_points_3rd')->nullable()->comment('نقاط المركز الثالث');
            $table->integer('max_winners')->nullable()->comment('عدد الفائزين - NULL = الكل من يحقق');
            $table->foreignId('min_tier_id')->nullable()->constrained('points_tiers')->nullOnDelete()->comment('الحد الأدنى للمستوى للمشاركة');
            $table->string('image')->nullable();
            $table->text('rules')->nullable()->comment('قواعد التحدي');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('auto_enroll')->default(false)->comment('تسجيل تلقائي للجميع');
            $table->timestamp('starts_at')->nullable()->comment('تاريخ بداية التحدي');
            $table->timestamp('ends_at')->nullable()->comment('تاريخ نهاية التحدي');
            $table->timestamp('results_announced_at')->nullable()->comment('تاريخ إعلان النتائج');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_type'], 'challenges_user_type_index');
            $table->index(['challenge_type'], 'challenges_challenge_type_index');
            $table->index(['starts_at', 'ends_at'], 'challenges_dates_index');
            $table->index(['is_active'], 'challenges_is_active_index');
        });

        // =====================================================
        // 10. المشاركون في التحديات
        // =====================================================
        $this->createIfMissing('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->integer('current_progress')->default(0)->comment('التقدم الحالي');
            $table->integer('target_value_snapshot')->comment('الهدف وقت التسجيل');
            // LEAST() دالّةٌ في MySQL لا وجود لها في SQLite؛ مقابلُها هناك MIN()
            // بصيغتها العدديّة (وسيطان فأكثر). العمودُ محسوبٌ مخزَّن في الحالتين.
            $capped = DB::connection()->getDriverName() === 'sqlite'
                ? 'MIN((current_progress / target_value_snapshot) * 100, 100)'
                : 'LEAST((current_progress / target_value_snapshot) * 100, 100)';
            $table->decimal('progress_percentage', 5, 2)->storedAs($capped);
            $table->boolean('is_completed')->default(false)->comment('هل أكمل التحدي');
            $table->timestamp('completed_at')->nullable();
            $table->integer('rank')->nullable()->comment('الترتيب');
            $table->integer('points_awarded')->nullable()->comment('النقاط الممنوحة');
            $table->timestamp('points_awarded_at')->nullable();
            $table->timestamp('enrolled_at')->nullable()->comment('تاريخ التسجيل');
            $table->timestamps();

            $table->unique(['challenge_id', 'user_type', 'employee_id', 'client_id'], 'challenge_participants_unique');
            $table->index(['employee_id'], 'challenge_participants_employee_index');
            $table->index(['client_id'], 'challenge_participants_client_index');
            $table->index(['current_progress'], 'challenge_participants_progress_index');
            $table->index(['is_completed'], 'challenge_participants_completed_index');
        });

        // =====================================================
        // 11. فرق التحديات
        // =====================================================
        $this->createIfMissing('challenge_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->string('name')->comment('اسم الفريق');
            $table->foreignId('leader_employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('قائد الفريق');
            $table->integer('total_progress')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->integer('rank')->nullable();
            $table->timestamps();
        });

        // =====================================================
        // 12. إعدادات الإحالة
        // =====================================================
        $this->createIfMissing('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('referrer_points')->default(50)->comment('نقاط للمُحيل');
            $table->integer('referred_points')->default(25)->comment('نقاط للعميل الجديد');
            $table->enum('confirmation_type', ['registration', 'first_purchase', 'min_purchase', 'days_active'])->default('first_purchase')->comment('متى تُؤكد الإحالة');
            $table->decimal('confirmation_value', 10, 2)->nullable()->comment('القيمة المطلوبة للتأكيد');
            $table->integer('max_referrals_per_month')->nullable()->comment('الحد الأقصى شهرياً - NULL = غير محدود');
            $table->integer('max_referrals_total')->nullable()->comment('الحد الأقصى الكلي');
            $table->json('bonus_milestones')->nullable()->comment('مكافآت إضافية عند عدد معين من الإحالات');
            $table->boolean('is_active')->default(true);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
        });

        // =====================================================
        // 13. الإحالات
        // =====================================================
        $this->createIfMissing('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('clients')->cascadeOnDelete()->comment('العميل المُحيل');
            $table->foreignId('referred_id')->constrained('clients')->cascadeOnDelete()->comment('العميل الجديد');
            $table->string('referral_code', 50)->comment('كود الإحالة المستخدم');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending');
            $table->integer('referrer_points')->nullable()->comment('النقاط الممنوحة للمُحيل');
            $table->integer('referred_points')->nullable()->comment('النقاط الممنوحة للعميل الجديد');
            $table->foreignId('referrer_points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();
            $table->foreignId('referred_points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();
            $table->string('confirmation_type', 50)->nullable()->comment('كيف تم التأكيد');
            $table->decimal('confirmation_value', 10, 2)->nullable()->comment('القيمة التي حققها');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('تاريخ انتهاء صلاحية الإحالة');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('referred_id', 'referrals_referred_unique');
            $table->index(['referrer_id'], 'referrals_referrer_index');
            $table->index(['status'], 'referrals_status_index');
            $table->index(['referral_code'], 'referrals_code_index');
        });

        // =====================================================
        // 14. إعدادات انتهاء الصلاحية
        // =====================================================
        $this->createIfMissing('points_expiry_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['employee', 'client']);
            $table->enum('expiry_type', ['fifo', 'fixed_date', 'anniversary', 'never'])->default('fifo')->comment('نوع الانتهاء');
            $table->integer('expiry_days')->nullable()->comment('عدد أيام الصلاحية (للـ FIFO)');
            $table->integer('expiry_month')->nullable()->comment('شهر الانتهاء (1-12) للتاريخ الثابت');
            $table->integer('expiry_day')->nullable()->comment('يوم الانتهاء (1-31)');
            $table->integer('warning_days')->default(30)->comment('أيام التنبيه قبل الانتهاء');
            $table->json('reminder_days')->nullable()->comment('أيام التذكير [30, 14, 7, 1]');
            $table->integer('grace_period_days')->default(0)->comment('فترة سماح بعد الانتهاء');
            $table->json('exclude_tiers')->nullable()->comment('المستويات المستثناة من الانتهاء');
            $table->boolean('is_active')->default(true);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_type'], 'points_expiry_user_type_index');
        });

        // =====================================================
        // 15. دفعات النقاط (للصلاحية FIFO)
        // =====================================================
        $this->createIfMissing('points_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('points_transactions')->cascadeOnDelete()->comment('معاملة النقاط الأصلية');
            $table->integer('original_points')->comment('النقاط الأصلية');
            $table->integer('remaining_points')->comment('النقاط المتبقية');
            $table->integer('used_points')->default(0)->comment('النقاط المستخدمة');
            $table->integer('expired_points')->default(0)->comment('النقاط المنتهية');
            $table->timestamp('earned_at')->comment('تاريخ الاكتساب');
            $table->timestamp('expires_at')->nullable()->comment('تاريخ الانتهاء');
            $table->timestamp('expired_at')->nullable()->comment('تاريخ التنفيذ الفعلي للانتهاء');
            $table->enum('status', ['active', 'depleted', 'expired', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['employee_id'], 'points_batches_employee_index');
            $table->index(['client_id'], 'points_batches_client_index');
            $table->index(['expires_at'], 'points_batches_expires_at_index');
            $table->index(['status'], 'points_batches_status_index');
        });

        // =====================================================
        // 16. قوالب الإشعارات
        // =====================================================
        $this->createIfMissing('points_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->comment('نوع الحدث');
            $table->enum('user_type', ['employee', 'client', 'both']);
            $table->string('title_ar')->comment('عنوان الإشعار بالعربية');
            $table->string('title_en')->nullable();
            $table->text('body_ar')->comment('نص الإشعار بالعربية');
            $table->text('body_en')->nullable();
            // MySQL يرفض DEFAULT على JSON/BLOB/TEXT (SQLSTATE 1101).
            // القيمة الافتراضية ['database'] في النموذج PointsNotificationTemplate.
            $table->json('channels')->comment('قنوات الإرسال: push, sms, email, database');
            $table->json('variables')->nullable()->comment('المتغيرات المتاحة في القالب');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_type', 'user_type'], 'notification_templates_event_user_unique');
            $table->index(['event_type'], 'notification_templates_event_type_index');
        });

        // =====================================================
        // 17. سجل الإشعارات
        // =====================================================
        $this->createIfMissing('points_notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('points_notification_templates')->nullOnDelete();
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('title');
            $table->text('body');
            $table->enum('channel', ['push', 'sms', 'email', 'database']);
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['employee_id'], 'notifications_log_employee_index');
            $table->index(['client_id'], 'notifications_log_client_index');
            $table->index(['event_type'], 'notifications_log_event_type_index');
            $table->index(['status'], 'notifications_log_status_index');
            $table->index(['created_at'], 'notifications_log_created_at_index');
        });

        // =====================================================
        // 18. ملخص النقاط اليومي
        // =====================================================
        $this->createIfMissing('points_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('user_type', ['employee', 'client']);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->integer('total_earned')->default(0)->comment('إجمالي النقاط المكتسبة');
            $table->integer('total_spent')->default(0)->comment('إجمالي النقاط المستبدلة');
            $table->integer('total_expired')->default(0)->comment('إجمالي النقاط المنتهية');
            $table->integer('total_adjusted')->default(0)->comment('إجمالي التعديلات اليدوية');
            $table->integer('transactions_count')->default(0)->comment('عدد العمليات');
            $table->integer('unique_users')->default(0)->comment('عدد المستخدمين النشطين');
            $table->integer('new_tier_upgrades')->default(0)->comment('عدد الترقيات');
            $table->integer('redemptions_count')->default(0)->comment('عدد عمليات الاستبدال');
            $table->decimal('redemptions_value', 12, 2)->default(0)->comment('قيمة المكافآت المستبدلة');
            $table->integer('achievements_unlocked')->default(0)->comment('عدد الإنجازات المحققة');
            $table->integer('challenges_completed')->default(0)->comment('عدد التحديات المكتملة');
            $table->integer('referrals_confirmed')->default(0)->comment('عدد الإحالات المؤكدة');
            $table->timestamps();

            $table->unique(['date', 'user_type', 'branch_id'], 'points_daily_summary_unique');
            $table->index(['date'], 'points_daily_summary_date_index');
            $table->index(['branch_id'], 'points_daily_summary_branch_index');
        });

        // =====================================================
        // 19. ملخص الإنجازات
        // =====================================================
        $this->createIfMissing('achievements_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->integer('total_achievers')->default(0)->comment('إجمالي المحققين');
            $table->integer('total_points_awarded')->default(0)->comment('إجمالي النقاط الممنوحة');
            $table->timestamp('last_achieved_at')->nullable();
            $table->timestamps();

            $table->unique('achievement_id', 'achievements_summary_achievement_unique');
        });

        // =====================================================
        // 20. إضافة حقول النقاط للموظفين
        // =====================================================
        // Schema::table('employees', function (Blueprint $table) {
        //     if (!Schema::hasColumn('employees', 'points_balance')) {
        //         $table->integer('points_balance')->default(0)->after('status')->comment('رصيد النقاط');
        //     }
        //     if (!Schema::hasColumn('employees', 'total_points_earned')) {
        //         $table->integer('total_points_earned')->default(0)->after('points_balance')->comment('إجمالي النقاط المكتسبة');
        //     }
        //     if (!Schema::hasColumn('employees', 'total_points_spent')) {
        //         $table->integer('total_points_spent')->default(0)->after('total_points_earned')->comment('إجمالي النقاط المستخدمة');
        //     }
        //     if (!Schema::hasColumn('employees', 'last_points_activity_at')) {
        //         $table->timestamp('last_points_activity_at')->nullable()->after('total_points_spent')->comment('آخر نشاط للنقاط');
        //     }
        //     if (!Schema::hasColumn('employees', 'tier_id')) {
        //         $table->foreignId('tier_id')->nullable()->after('last_points_activity_at')->constrained('points_tiers')->nullOnDelete()->comment('المستوى الحالي');
        //     }
        // });

        // =====================================================
        // 21. إضافة حقول النقاط للعملاء
        // =====================================================
        // Schema::table('clients', function (Blueprint $table) {
        //     if (!Schema::hasColumn('clients', 'points_balance')) {
        //         $table->integer('points_balance')->default(0)->after('status_id')->comment('رصيد النقاط');
        //     }
        //     if (!Schema::hasColumn('clients', 'total_points_earned')) {
        //         $table->integer('total_points_earned')->default(0)->after('points_balance')->comment('إجمالي النقاط المكتسبة');
        //     }
        //     if (!Schema::hasColumn('clients', 'total_points_spent')) {
        //         $table->integer('total_points_spent')->default(0)->after('total_points_earned')->comment('إجمالي النقاط المستخدمة');
        //     }
        //     if (!Schema::hasColumn('clients', 'last_points_activity_at')) {
        //         $table->timestamp('last_points_activity_at')->nullable()->after('total_points_spent')->comment('آخر نشاط للنقاط');
        //     }
        //     if (!Schema::hasColumn('clients', 'referred_by')) {
        //         $table->unsignedBigInteger('referred_by')->nullable()->after('last_points_activity_at')->comment('من أحال هذا العميل');
        //     }
        //     if (!Schema::hasColumn('clients', 'referral_code')) {
        //         $table->string('referral_code', 50)->nullable()->unique()->after('referred_by')->comment('كود الإحالة الخاص بالعميل');
        //     }
        //     if (!Schema::hasColumn('clients', 'tier_id')) {
        //         $table->foreignId('tier_id')->nullable()->after('referral_code')->constrained('points_tiers')->nullOnDelete()->comment('المستوى الحالي');
        //     }
        // });
    }

    /**
     * إنشاء الجدول إن لم يوجد — الهجرة فشلت سابقاً بعد إنشاء جزء من الجداول.
     */
    private function createIfMissing(string $table, Closure $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف حقول النقاط من الجداول الموجودة
        // Schema::table('clients', function (Blueprint $table) {
        //     $table->dropForeign(['tier_id']);
        //     $table->dropColumn([
        //         'points_balance',
        //         'total_points_earned',
        //         'total_points_spent',
        //         'last_points_activity_at',
        //         'referred_by',
        //         'referral_code',
        //         'tier_id',
        //     ]);
        // });

        // Schema::table('employees', function (Blueprint $table) {
        //     $table->dropForeign(['tier_id']);
        //     $table->dropColumn([
        //         'points_balance',
        //         'total_points_earned',
        //         'total_points_spent',
        //         'last_points_activity_at',
        //         'tier_id',
        //     ]);
        // });

        // حذف الجداول بترتيب عكسي
        Schema::dropIfExists('achievements_summary');
        Schema::dropIfExists('points_daily_summary');
        Schema::dropIfExists('points_notifications_log');
        Schema::dropIfExists('points_notification_templates');
        Schema::dropIfExists('points_batches');
        Schema::dropIfExists('points_expiry_settings');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_settings');
        Schema::dropIfExists('challenge_teams');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('tier_upgrades_log');
        Schema::dropIfExists('points_tiers');
        Schema::dropIfExists('points_transactions');
        Schema::dropIfExists('points_settings');
    }
};
