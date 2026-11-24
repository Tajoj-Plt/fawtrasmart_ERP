# 🌳 شجرة جداول نظام النقاط والعلاقات

## 📊 هيكل الجداول حسب الأقسام

```
نظام النقاط والحوافز (Points & Rewards Ecosystem)
│
├── 📁 الجداول الخارجية (موجودة مسبقاً)
│   ├── 👥 employees ─────────────────────┐
│   ├── 👤 clients ───────────────────────┤
│   ├── 🏢 branches ──────────────────────┤── تُستخدم كـ Foreign Keys
│   ├── 👨‍💼 users ────────────────────────┤
│   └── 🏬 departments ───────────────────┘
│
├── 📁 القسم الأول: الإعدادات الأساسية
│   │
│   ├── ⚙️ points_settings ◄────────────── إعدادات النقاط لكل عملية
│   │       │
│   │       └──► يرتبط بـ: branches, users
│   │
│   └── 💰 points_transactions ◄────────── معاملات النقاط
│           │
│           ├──► يرتبط بـ: employees, clients, branches, users
│           └──► يُستخدم من: points_batches, referrals
│
├── 📁 القسم الثاني: نظام المستويات
│   │
│   ├── 🏆 points_tiers ◄───────────────── المستويات (برونزي، فضي، ذهبي...)
│   │       │
│   │       ├──► يُستخدم من: employees.tier_id, clients.tier_id
│   │       ├──► يُستخدم من: tier_upgrades_log
│   │       ├──► يُستخدم من: rewards.min_tier_id
│   │       └──► يُستخدم من: challenges.min_tier_id
│   │
│   └── 📋 tier_upgrades_log ◄──────────── سجل الترقيات
│           │
│           └──► يرتبط بـ: points_tiers (old/new), employees, clients
│
├── 📁 القسم الثالث: نظام المكافآت
│   │
│   ├── 🎁 rewards ◄────────────────────── المكافآت المتاحة
│   │       │
│   │       ├──► يرتبط بـ: points_tiers, branches, users
│   │       └──► يُستخدم من: reward_redemptions
│   │
│   └── 🔄 reward_redemptions ◄─────────── عمليات استبدال المكافآت
│           │
│           └──► يرتبط بـ: rewards, employees, clients, users, branches
│
├── 📁 القسم الرابع: نظام الإنجازات
│   │
│   ├── 🏅 achievements ◄───────────────── الإنجازات والشارات
│   │       │
│   │       ├──► يُستخدم من: user_achievements
│   │       └──► يُستخدم من: achievements_summary
│   │
│   └── 🎖️ user_achievements ◄──────────── إنجازات المستخدمين
│           │
│           └──► يرتبط بـ: achievements, employees, clients
│
├── 📁 القسم الخامس: نظام التحديات
│   │
│   ├── 🎯 challenges ◄─────────────────── التحديات والمسابقات
│   │       │
│   │       ├──► يرتبط بـ: points_tiers, branches, users
│   │       ├──► يُستخدم من: challenge_participants
│   │       ├──► يُستخدم من: challenge_teams
│   │       └──► يُستخدم من: challenge_transactions
│   │
│   ├── 👥 challenge_participants ◄─────── المشاركين في التحديات
│   │       │
│   │       ├──► يرتبط بـ: challenges, employees, clients
│   │       └──► يُستخدم من: challenge_transactions
│   │
│   ├── 🤝 challenge_teams ◄────────────── فرق التحديات
│   │       │
│   │       └──► يرتبط بـ: challenges, employees
│   │
│   └── 📋 challenge_transactions ◄────── معاملات التحديات
│           │
│           └──► يرتبط بـ: challenges, challenge_participants, points_transactions, employees, clients
│
├── 📁 القسم السادس: نظام الإحالات
│   │
│   ├── ⚙️ referral_settings ◄──────────── إعدادات الإحالة
│   │       │
│   │       └──► يرتبط بـ: branches
│   │
│   └── 🔗 referrals ◄──────────────────── سجل الإحالات
│           │
│           └──► يرتبط بـ: clients (referrer/referred), points_transactions
│
├── 📁 القسم السابع: انتهاء صلاحية النقاط
│   │
│   ├── ⚙️ points_expiry_settings ◄─────── إعدادات الصلاحية
│   │       │
│   │       └──► يرتبط بـ: branches
│   │
│   └── 📦 points_batches ◄─────────────── دفعات النقاط (FIFO)
│           │
│           └──► يرتبط بـ: points_transactions, employees, clients
│
├── 📁 القسم الثامن: نظام الإشعارات
│   │
│   ├── 📝 points_notification_templates ◄─ قوالب الإشعارات
│   │       │
│   │       └──► يُستخدم من: points_notifications_log
│   │
│   └── 📤 points_notifications_log ◄───── سجل الإشعارات المرسلة
│           │
│           └──► يرتبط بـ: templates, employees, clients
│
└── 📁 القسم التاسع: التقارير والإحصائيات
    │
    ├── 📊 points_daily_summary ◄────────── ملخص يومي للنقاط
    │       │
    │       └──► يرتبط بـ: branches
    │
    └── 📈 achievements_summary ◄────────── ملخص الإنجازات
            │
            └──► يرتبط بـ: achievements
```

---

## 🎨 تصنيف الجداول حسب الواجهات المطلوبة

### 🟢 واجهات كاملة (CRUD: إضافة، عرض، تعديل، حذف)

```
├── ⚙️ points_settings
│   └── إعدادات النقاط لكل عملية - يحتاج تحكم كامل
│
├── 🏆 points_tiers
│   └── المستويات - إضافة/تعديل/حذف المستويات
│
├── 🎁 rewards
│   └── المكافآت - إدارة المكافآت المتاحة
│
├── 🏅 achievements
│   └── الإنجازات - إنشاء وتعديل الإنجازات
│
├── 🎯 challenges
│   └── التحديات - إدارة المسابقات والتحديات
│
├── ⚙️ referral_settings
│   └── إعدادات الإحالة - تخصيص نظام الإحالة
│
├── ⚙️ points_expiry_settings
│   └── إعدادات الصلاحية - ضبط انتهاء النقاط
│
└── 📝 points_notification_templates
    └── قوالب الإشعارات - تخصيص رسائل الإشعارات
```

### 🟡 واجهات جزئية (إضافة وعرض فقط - بدون تعديل/حذف)

```
├── 💰 points_transactions
│   └── معاملات النقاط - إضافة يدوية + عرض السجل
│       ⚠️ لا يُفضل التعديل/الحذف للحفاظ على سلامة السجلات
│
└── 🔄 reward_redemptions
    └── عمليات الاستبدال - طلب + تغيير الحالة فقط
        ⚠️ لا يُحذف بل يُلغى (تغيير الحالة)
```

### 🔵 واجهات عرض فقط (Read Only - سجلات)

```
├── 📋 tier_upgrades_log
│   └── سجل الترقيات - عرض تاريخ الترقيات فقط
│
├── 🎖️ user_achievements
│   └── إنجازات المستخدمين - عرض من حصل على ماذا
│
├── 👥 challenge_participants
│   └── المشاركين - عرض المشاركين وتقدمهم
│
├── � challenge_transactions
│   └── معاملات التحديات - عرض المعاملات المرتبطة بكل تحدي
│
├── �🔗 referrals
│   └── سجل الإحالات - عرض الإحالات وحالتها
│
├── 📦 points_batches
│   └── دفعات النقاط - عرض تفاصيل FIFO
│
├── 📤 points_notifications_log
│   └── سجل الإشعارات - عرض ما تم إرساله
│
├── 📊 points_daily_summary
│   └── الملخص اليومي - إحصائيات يومية
│
└── 📈 achievements_summary
    └── ملخص الإنجازات - إحصائيات الإنجازات
```

### ⚫ جداول علاقات فقط (لا تحتاج واجهة - تُدار تلقائياً)

```
└── 🤝 challenge_teams
    └── فرق التحديات - تُنشأ تلقائياً عند إنشاء تحدي جماعي
```

---

## 📋 ترتيب البناء المقترح

```
المرحلة 1️⃣ - الأساسيات (مكتملة ✅)
│
├── ✅ points_settings
├── ✅ points_transactions  
├── ✅ points_tiers
└── ✅ tier_upgrades_log

المرحلة 2️⃣ - المكافآت
│
├── 🔲 rewards
└── 🔲 reward_redemptions

المرحلة 3️⃣ - الإنجازات
│
├── 🔲 achievements
├── 🔲 user_achievements
└── 🔲 achievements_summary

المرحلة 4️⃣ - التحديات
│
├── 🔲 challenges
├── 🔲 challenge_participants
├── 🔲 challenge_teams
└── 🔲 challenge_transactions

المرحلة 5️⃣ - الإحالات
│
├── 🔲 referral_settings
└── 🔲 referrals

المرحلة 6️⃣ - الصلاحية والانتهاء
│
├── 🔲 points_expiry_settings
└── 🔲 points_batches

المرحلة 7️⃣ - الإشعارات
│
├── 🔲 points_notification_templates
└── 🔲 points_notifications_log

المرحلة 8️⃣ - التقارير
│
└── 🔲 points_daily_summary
```

---

## 🔢 ملخص إحصائي

```
📊 إجمالي الجداول: 20 جدول

🟢 واجهات CRUD كاملة:     8 جداول
🟡 إضافة وعرض فقط:        2 جداول  
🔵 عرض فقط:               9 جداول
⚫ علاقات تلقائية:         1 جدول
```
