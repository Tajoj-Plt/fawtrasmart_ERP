# 🏆 نظام النقاط والحوافز - الهيكل الكامل

```
📦 نظام النقاط والحوافز (Points & Rewards Ecosystem)
│
├── 📊 1. النظام الأساسي للنقاط (Core Points System)
│   │
│   ├── 📋 points_settings (إعدادات النقاط)
│   │   ├── user_type: employee | client
│   │   ├── action_type: نوع العملية
│   │   ├── action_name: اسم العملية
│   │   ├── points: عدد النقاط (+/-)
│   │   ├── is_active: مفعل/معطل
│   │   └── branch_id: خاص بفرع معين
│   │
│   └── 📝 points_transactions (معاملات النقاط)
│       ├── employee_id / client_id
│       ├── action_type: نوع العملية
│       ├── points: النقاط
│       ├── balance_before / balance_after
│       ├── reference_type / reference_id
│       ├── status: pending | completed | cancelled | expired
│       └── expires_at: تاريخ الانتهاء
│
├── 🎖️ 2. نظام المستويات (Tiers System)
│   │
│   ├── 🏅 points_tiers (المستويات)
│   │   ├── name: اسم المستوى
│   │   ├── user_type: employee | client
│   │   ├── min_points / max_points: نطاق النقاط
│   │   ├── color / icon / image
│   │   ├── points_multiplier: مضاعف النقاط (1.5x)
│   │   ├── discount_percentage: نسبة خصم
│   │   ├── priority_support: أولوية دعم
│   │   ├── free_shipping: شحن مجاني
│   │   └── benefits: المميزات (JSON)
│   │
│   ├── 📈 tier_upgrades_log (سجل الترقيات)
│   │   ├── old_tier_id → new_tier_id
│   │   ├── points_at_upgrade
│   │   └── upgrade_type: automatic | manual
│   │
│   └── 🎯 المستويات الافتراضية
│       │
│       ├── 👥 للعملاء:
│       │   ├── 🥉 برونزي (0-499) → 1x، 0% خصم
│       │   ├── 🥈 فضي (500-1,999) → 1.25x، 5% خصم
│       │   ├── 🥇 ذهبي (2,000-4,999) → 1.5x، 10% خصم
│       │   └── 💎 بلاتيني (5,000+) → 2x، 15% خصم
│       │
│       └── 👔 للموظفين:
│           ├── 🌱 مبتدئ (0-999) → 1x
│           ├── ⭐ متميز (1,000-4,999) → 1.25x
│           ├── 🔮 خبير (5,000-9,999) → 1.5x
│           └── 👑 نجم (10,000+) → 2x
│
├── 🎁 3. نظام المكافآت (Rewards System)
│   │
│   ├── 🎀 rewards (المكافآت المتاحة)
│   │   ├── name: اسم المكافأة
│   │   ├── user_type: employee | client | both
│   │   ├── points_required: النقاط المطلوبة
│   │   ├── reward_type:
│   │   │   ├── discount_percentage (خصم نسبة)
│   │   │   ├── discount_amount (خصم مبلغ)
│   │   │   ├── gift (هدية)
│   │   │   ├── voucher (قسيمة)
│   │   │   ├── cash (نقدي)
│   │   │   ├── leave_day (إجازة)
│   │   │   ├── free_shipping (شحن مجاني)
│   │   │   ├── product (منتج)
│   │   │   └── service (خدمة)
│   │   ├── reward_value: قيمة المكافأة
│   │   ├── quantity_available: الكمية المتاحة
│   │   ├── min_tier_id: الحد الأدنى للمستوى
│   │   └── starts_at / expires_at
│   │
│   ├── 📦 reward_redemptions (عمليات الاستبدال)
│   │   ├── redemption_number: رقم العملية
│   │   ├── reward_id: المكافأة
│   │   ├── points_used: النقاط المستخدمة
│   │   ├── status:
│   │   │   ├── pending (معلق)
│   │   │   ├── approved (موافق عليه)
│   │   │   ├── processing (قيد التجهيز)
│   │   │   ├── delivered (تم التسليم)
│   │   │   └── cancelled (ملغي)
│   │   ├── voucher_code: كود القسيمة
│   │   └── delivery_method: pickup | delivery | digital
│   │
│   └── 🎯 المكافآت الافتراضية
│       │
│       ├── 👥 للعملاء:
│       │   ├── خصم 5% ← 100 نقطة
│       │   ├── خصم 10% ← 200 نقطة
│       │   ├── شحن مجاني ← 150 نقطة
│       │   ├── قسيمة 50 ريال ← 500 نقطة
│       │   └── قسيمة 100 ريال ← 1000 نقطة
│       │
│       └── 👔 للموظفين:
│           ├── إجازة نصف يوم ← 500 نقطة
│           ├── إجازة يوم كامل ← 1000 نقطة
│           ├── مكافأة 100 ريال ← 1000 نقطة
│           ├── مكافأة 200 ريال ← 2000 نقطة
│           └── تذكرة سينما ← 300 نقطة
│
├── 🏆 4. نظام الإنجازات (Achievements System)
│   │
│   ├── 🎖️ achievements (الإنجازات)
│   │   ├── name: اسم الإنجاز
│   │   ├── user_type: employee | client | both
│   │   ├── category: تصنيف (loyalty, sales, etc.)
│   │   ├── icon / image / color
│   │   ├── condition_type: نوع الشرط
│   │   ├── condition_operator: >= | > | = | < | <=
│   │   ├── condition_value: القيمة المطلوبة
│   │   ├── condition_period: all_time | yearly | monthly
│   │   ├── bonus_points: نقاط إضافية
│   │   ├── is_hidden: إنجاز سري
│   │   └── is_one_time: مرة واحدة فقط
│   │
│   ├── 🎗️ user_achievements (إنجازات المستخدمين)
│   │   ├── achievement_id
│   │   ├── employee_id / client_id
│   │   ├── achieved_at: تاريخ التحقيق
│   │   └── points_awarded: النقاط الممنوحة
│   │
│   └── 🎯 الإنجازات الافتراضية
│       │
│       ├── 👥 للعملاء:
│       │   ├── 🛒 أول خطوة (1 فاتورة) → +20
│       │   ├── ⭐ عميل نشط (5 فواتير) → +50
│       │   ├── ❤️ عميل مخلص (10 فواتير) → +100
│       │   ├── 👑 عميل VIP (50 فاتورة) → +500
│       │   ├── 👥 سفير العلامة (5 إحالات) → +200
│       │   └── 💬 المقيّم النشط (10 تقييمات) → +50
│       │
│       └── 👔 للموظفين:
│           ├── 🚀 البداية القوية (10 فواتير) → +50
│           ├── ⭐ نجم المبيعات (100 فاتورة) → +500
│           ├── 💰 محصّل محترف (50,000 ريال) → +300
│           ├── ⏰ الملتزم (30 يوم بدون تأخير) → +150
│           ├── 🤝 روح الفريق (10 تعاونات) → +200
│           └── 🎯 صائد العملاء (10 عملاء جدد) → +400
│
├── 🎮 5. نظام التحديات (Challenges System)
│   │
│   ├── 🏁 challenges (التحديات)
│   │   ├── name: اسم التحدي
│   │   ├── user_type: employee | client | both
│   │   ├── challenge_type:
│   │   │   ├── individual (فردي)
│   │   │   ├── team (فريق)
│   │   │   ├── branch (فرع)
│   │   │   └── department (قسم)
│   │   ├── competition_type:
│   │   │   ├── target (هدف محدد)
│   │   │   ├── ranking (ترتيب)
│   │   │   └── first_n (أول N فائزين)
│   │   ├── target_action: العملية المطلوبة
│   │   ├── target_value: الهدف
│   │   ├── reward_points: نقاط الفوز
│   │   ├── max_winners: عدد الفائزين
│   │   ├── starts_at / ends_at
│   │   └── auto_enroll: تسجيل تلقائي
│   │
│   ├── 👤 challenge_participants (المشاركون)
│   │   ├── challenge_id
│   │   ├── employee_id / client_id
│   │   ├── current_progress: التقدم الحالي
│   │   ├── progress_percentage: نسبة الإنجاز
│   │   ├── is_completed: اكتمل؟
│   │   ├── rank: الترتيب
│   │   └── points_awarded: النقاط الممنوحة
│   │
│   ├── 👥 challenge_teams (فرق التحديات)
│   │   ├── challenge_id
│   │   ├── name: اسم الفريق
│   │   ├── leader_employee_id: قائد الفريق
│   │   └── total_progress: إجمالي التقدم
│   │
│   └── 📋 challenge_transactions (معاملات التحديات)
│       ├── challenge_id: التحدي المرتبط
│       ├── challenge_participant_id: المشارك في التحدي
│       ├── points_transaction_id: معاملة النقاط الأصلية
│       ├── user_type: نوع المستخدم (employee/client)
│       ├── employee_id / client_id: المستخدم
│       ├── action_type: نوع الإجراء المطابق
│       ├── progress_value: قيمة التقدم من هذه المعاملة
│       ├── progress_before: التقدم قبل المعاملة
│       ├── progress_after: التقدم بعد المعاملة
│       ├── points: النقاط المكتسبة
│       ├── amount: المبلغ المالي المرتبط
│       ├── description: وصف المعاملة
│       └── metadata: بيانات إضافية (JSON)
│
├── 🔗 6. نظام الإحالات (Referral System)
│   │
│   ├── ⚙️ referral_settings (إعدادات الإحالة)
│   │   ├── referrer_points: نقاط المُحيل (50)
│   │   ├── referred_points: نقاط العميل الجديد (25)
│   │   ├── confirmation_type:
│   │   │   ├── registration (عند التسجيل)
│   │   │   ├── first_purchase (أول شراء)
│   │   │   ├── min_purchase (حد أدنى للشراء)
│   │   │   └── days_active (أيام نشاط)
│   │   ├── confirmation_value: القيمة المطلوبة
│   │   ├── max_referrals_per_month: الحد الشهري
│   │   └── bonus_milestones: مكافآت إضافية (JSON)
│   │
│   └── 📨 referrals (الإحالات)
│       ├── referrer_id: المُحيل
│       ├── referred_id: العميل الجديد
│       ├── referral_code: كود الإحالة
│       ├── status: pending | confirmed | cancelled
│       ├── referrer_points / referred_points
│       └── confirmed_at: تاريخ التأكيد
│
├── ⏳ 7. نظام انتهاء الصلاحية (Points Expiry)
│   │
│   ├── ⚙️ points_expiry_settings (إعدادات الصلاحية)
│   │   ├── user_type: employee | client
│   │   ├── expiry_type:
│   │   │   ├── fifo (الأول يدخل الأول يخرج)
│   │   │   ├── fixed_date (تاريخ ثابت)
│   │   │   ├── anniversary (ذكرى سنوية)
│   │   │   └── never (لا تنتهي)
│   │   ├── expiry_days: أيام الصلاحية (365)
│   │   ├── warning_days: أيام التنبيه (30)
│   │   ├── reminder_days: أيام التذكير [30,14,7,1]
│   │   └── exclude_tiers: المستويات المستثناة
│   │
│   └── 📦 points_batches (دفعات النقاط)
│       ├── transaction_id: المعاملة الأصلية
│       ├── original_points: النقاط الأصلية
│       ├── remaining_points: المتبقية
│       ├── used_points: المستخدمة
│       ├── expired_points: المنتهية
│       ├── expires_at: تاريخ الانتهاء
│       └── status: active | depleted | expired
│
├── 🔔 8. نظام الإشعارات (Notifications)
│   │
│   ├── 📝 points_notification_templates (قوالب الإشعارات)
│   │   ├── event_type: نوع الحدث
│   │   ├── title_ar / title_en
│   │   ├── body_ar / body_en
│   │   ├── channels: [push, sms, email, database]
│   │   └── variables: المتغيرات المتاحة
│   │
│   ├── 📬 points_notifications_log (سجل الإشعارات)
│   │   ├── template_id
│   │   ├── employee_id / client_id
│   │   ├── event_type / title / body
│   │   ├── channel: push | sms | email | database
│   │   └── status: pending | sent | delivered | read
│   │
│   └── 🎯 أنواع الإشعارات:
│       ├── points_earned: "حصلت على X نقطة"
│       ├── points_spent: "تم استخدام X نقطة"
│       ├── tier_upgrade: "ترقية للمستوى X"
│       ├── achievement_unlocked: "إنجاز جديد!"
│       ├── points_expiring: "نقاطك ستنتهي قريباً"
│       ├── reward_approved: "تمت الموافقة على مكافأتك"
│       ├── challenge_new: "تحدي جديد!"
│       └── referral_success: "إحالة ناجحة!"
│
├── 📊 9. التقارير والإحصائيات (Analytics)
│   │
│   ├── 📈 points_daily_summary (ملخص يومي)
│   │   ├── date / user_type / branch_id
│   │   ├── total_earned: المكتسبة
│   │   ├── total_spent: المستبدلة
│   │   ├── total_expired: المنتهية
│   │   ├── transactions_count: عدد العمليات
│   │   ├── unique_users: المستخدمين النشطين
│   │   ├── new_tier_upgrades: الترقيات
│   │   ├── redemptions_count: الاستبدالات
│   │   └── achievements_unlocked: الإنجازات
│   │
│   └── 📊 achievements_summary (ملخص الإنجازات)
│       ├── achievement_id
│       ├── total_achievers: عدد المحققين
│       └── total_points_awarded: النقاط الممنوحة
│
└── 🔧 10. تعديلات الجداول الموجودة
    │
    ├── 👔 employees (الموظفين)
    │   ├── + points_balance: رصيد النقاط
    │   ├── + total_points_earned: إجمالي المكتسب
    │   ├── + total_points_spent: إجمالي المستخدم
    │   ├── + last_points_activity_at: آخر نشاط
    │   └── + tier_id: المستوى الحالي
    │
    └── 👥 clients (العملاء)
        ├── + points_balance: رصيد النقاط
        ├── + total_points_earned: إجمالي المكتسب
        ├── + total_points_spent: إجمالي المستخدم
        ├── + last_points_activity_at: آخر نشاط
        ├── + referred_by: من أحاله
        ├── + referral_code: كود الإحالة
        └── + tier_id: المستوى الحالي
```

---

## 📁 هيكل الملفات المقترح (Laravel)

```
app/
├── Models/
│   ├── Points/
│   │   ├── PointsSetting.php
│   │   ├── PointsTransaction.php
│   │   ├── PointsTier.php
│   │   ├── TierUpgradeLog.php
│   │   ├── Reward.php
│   │   ├── RewardRedemption.php
│   │   ├── Achievement.php
│   │   ├── UserAchievement.php
│   │   ├── Challenge.php
│   │   ├── ChallengeParticipant.php
│   │   ├── ChallengeTeam.php
│   │   ├── Referral.php
│   │   ├── ReferralSetting.php
│   │   ├── PointsExpirySetting.php
│   │   ├── PointsBatch.php
│   │   ├── PointsNotificationTemplate.php
│   │   └── PointsNotificationLog.php
│   │
├── Services/
│   ├── Points/
│   │   ├── PointsService.php           # الخدمة الرئيسية
│   │   ├── TierService.php             # إدارة المستويات
│   │   ├── RewardService.php           # إدارة المكافآت
│   │   ├── AchievementService.php      # إدارة الإنجازات
│   │   ├── ChallengeService.php        # إدارة التحديات
│   │   ├── ReferralService.php         # إدارة الإحالات
│   │   ├── PointsExpiryService.php     # إدارة الصلاحية
│   │   └── PointsNotificationService.php
│   │
├── Observers/
│   ├── InvoicePointsObserver.php       # نقاط الفواتير
│   ├── ReceiptPointsObserver.php       # نقاط السندات
│   ├── VisitPointsObserver.php         # نقاط الزيارات
│   └── AttendancePointsObserver.php    # نقاط الحضور
│   │
├── Jobs/
│   ├── Points/
│   │   ├── ProcessPointsExpiry.php     # معالجة انتهاء الصلاحية
│   │   ├── UpdateDailySummary.php      # تحديث الملخص اليومي
│   │   ├── CheckAchievements.php       # فحص الإنجازات
│   │   ├── UpdateChallengeProgress.php # تحديث التحديات
│   │   └── SendExpiryReminders.php     # إرسال تنبيهات الانتهاء
│   │
├── Events/
│   ├── Points/
│   │   ├── PointsEarned.php
│   │   ├── PointsSpent.php
│   │   ├── TierUpgraded.php
│   │   ├── AchievementUnlocked.php
│   │   ├── ChallengeCompleted.php
│   │   └── ReferralConfirmed.php
│   │
├── Listeners/
│   ├── Points/
│   │   ├── SendPointsNotification.php
│   │   ├── UpdateUserTier.php
│   │   └── LogPointsActivity.php
│   │
└── Http/
    └── Controllers/
        ├── Admin/
        │   ├── PointsSettingsController.php
        │   ├── TiersController.php
        │   ├── RewardsController.php
        │   ├── AchievementsController.php
        │   ├── ChallengesController.php
        │   └── PointsReportsController.php
        │
        └── Api/
            ├── PointsController.php
            ├── RewardsController.php
            ├── AchievementsController.php
            └── ChallengesController.php

database/
├── migrations/
│   ├── 2026_02_05_000001_create_points_settings_table.php
│   ├── 2026_02_05_000002_create_points_transactions_table.php
│   ├── 2026_02_05_000003_create_points_tiers_table.php
│   ├── 2026_02_05_000004_create_rewards_tables.php
│   ├── 2026_02_05_000005_create_achievements_tables.php
│   ├── 2026_02_05_000006_create_challenges_tables.php
│   ├── 2026_02_05_000007_create_referrals_tables.php
│   ├── 2026_02_05_000008_create_points_expiry_tables.php
│   ├── 2026_02_05_000009_create_notifications_tables.php
│   ├── 2026_02_05_000010_create_analytics_tables.php
│   └── 2026_02_05_000011_add_points_fields_to_users.php
│
├── seeders/
│   ├── PointsSettingsSeeder.php
│   ├── TiersSeeder.php
│   ├── RewardsSeeder.php
│   ├── AchievementsSeeder.php
│   └── NotificationTemplatesSeeder.php
│
└── points_system.sql  # ← الملف الشامل
```

---

## 🔄 تدفق العمليات (Workflow)

```
┌─────────────────────────────────────────────────────────────────┐
│                        عملية (فاتورة/زيارة/...)                    │
└─────────────────────────────────┬───────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Observer يلتقط الحدث                          │
└─────────────────────────────────┬───────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│              PointsService.addPoints()                           │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 1. جلب إعدادات النقاط للعملية                              │   │
│  │ 2. حساب المضاعف حسب المستوى                              │   │
│  │ 3. إنشاء معاملة النقاط                                     │   │
│  │ 4. تحديث رصيد المستخدم                                    │   │
│  │ 5. إنشاء دفعة النقاط (للصلاحية)                            │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────┬───────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Event: PointsEarned                           │
└─────────────────────────────────┬───────────────────────────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
                    ▼             ▼             ▼
┌───────────────────────┐ ┌───────────────┐ ┌───────────────────┐
│   TierService         │ │ Achievement   │ │  Challenge        │
│   .checkUpgrade()     │ │ Service       │ │  Service          │
│                       │ │ .check()      │ │  .updateProgress()│
└───────────┬───────────┘ └───────┬───────┘ └─────────┬─────────┘
            │                     │                   │
            ▼                     ▼                   ▼
┌───────────────────────┐ ┌───────────────┐ ┌───────────────────┐
│  ترقية المستوى؟       │ │ إنجاز جديد؟   │ │ تحدي مكتمل؟       │
│  Event: TierUpgraded  │ │ Event:        │ │ Event:            │
│                       │ │ Achievement   │ │ Challenge         │
│                       │ │ Unlocked      │ │ Completed         │
└───────────┬───────────┘ └───────┬───────┘ └─────────┬─────────┘
            │                     │                   │
            └─────────────────────┴───────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│              NotificationService.send()                          │
│                  إرسال الإشعارات للمستخدم                         │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📅 الجدولة (Scheduled Tasks)

```
┌────────────────────────────────────────────────────────────────┐
│                    Laravel Scheduler                            │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  يومياً (Daily):                                                │
│  ├── 00:00 → UpdateDailySummary (تحديث الملخص اليومي)          │
│  ├── 01:00 → ProcessPointsExpiry (معالجة النقاط المنتهية)       │
│  └── 08:00 → SendExpiryReminders (تنبيهات الانتهاء)            │
│                                                                │
│  كل ساعة (Hourly):                                              │
│  └── CheckChallengesStatus (فحص حالة التحديات)                  │
│                                                                │
│  أسبوعياً (Weekly):                                             │
│  └── GenerateWeeklyReports (تقارير أسبوعية)                     │
│                                                                │
│  شهرياً (Monthly):                                              │
│  ├── ResetMonthlyChallenges (إعادة ضبط التحديات الشهرية)        │
│  └── GenerateMonthlyReports (تقارير شهرية)                      │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## 🎯 الأولويات للتنفيذ

| المرحلة | المكون | الأهمية | المدة المقدرة |
|---------|--------|---------|---------------|
| 1️⃣ | النقاط الأساسية + الإعدادات | ⭐⭐⭐ | 2-3 أيام |
| 2️⃣ | المستويات (Tiers) | ⭐⭐⭐ | 1-2 يوم |
| 3️⃣ | المكافآت والاستبدال | ⭐⭐⭐ | 2-3 أيام |
| 4️⃣ | الإنجازات والشارات | ⭐⭐ | 1-2 يوم |
| 5️⃣ | التحديات والمسابقات | ⭐⭐ | 2-3 أيام |
| 6️⃣ | الإحالات | ⭐⭐ | 1 يوم |
| 7️⃣ | انتهاء الصلاحية | ⭐ | 1 يوم |
| 8️⃣ | الإشعارات | ⭐⭐ | 1-2 يوم |
| 9️⃣ | التقارير | ⭐ | 1-2 يوم |
