# سير عمل معاملة النقاط (Points Transaction Workflow)

## 📋 نظرة عامة

هذا المستند يشرح العمليات التلقائية التي تحدث عند **إنشاء معاملة نقاط جديدة** في النظام.

---

## 🔄 مراحل إنشاء معاملة النقاط

### المرحلة 1️⃣: التحقق من الصحة والإعدادات العامة

#### الحقول المطلوبة
```php
- user_type: نوع المستخدم (employee/client)
- employee_id أو client_id: معرف المستخدم
- action_type: نوع الإجراء (مثال: purchase, attendance, referral)
- points: عدد النقاط (يمكن حسابه تلقائياً)
- status: الحالة (pending/completed/cancelled)
- transaction_amount: مبلغ المعاملة (اختياري)
- branch_id: معرف الفرع (اختياري)
- expires_at: تاريخ الانتهاء (اختياري)
```

#### حساب النقاط التلقائي
- يتم البحث في `points_settings` عن إعداد مطابق لـ:
  - `user_type`
  - `action_type`
  - `branch_id` (إن وجد)
- إذا وُجد إعداد، يتم حساب النقاط تلقائياً بناءً على:
  - `calculation_type`: نوع الحساب (fixed/percentage/tiered)
  - `transaction_amount`: مبلغ المعاملة

#### فحوصات الأمان من `points_general_settings`

| الفحص | الوصف | الإجراء عند الفشل |
|-------|--------|-------------------|
| **تفعيل نقاط الموظفين** | `enable_employee_points = true` | رفض المعاملة |
| **تفعيل نقاط العملاء** | `enable_client_points = true` | رفض المعاملة |
| **الحد الأقصى للمعاملة** | `max_points_per_transaction` | رفض إذا تجاوز الحد |
| **الحد اليومي للكسب** | `max_daily_earn` | حساب المكتسب اليوم ورفض إذا تجاوز |
| **كشف النشاط المشبوه** | `enable_fraud_detection` | عد المعاملات/ساعة ورفض إذا تجاوز |

---

### المرحلة 2️⃣: إنشاء معاملة النقاط الأساسية

#### إنشاء سجل في جدول `points_transactions`

```php
PointsTransaction::create([
    'user_type' => $userType,
    'employee_id' => $employeeId,
    'client_id' => $clientId,
    'action_type' => $actionType,
    'points' => $points,
    'transaction_amount' => $amount,
    'description' => $description,
    'status' => $status,
    'branch_id' => $branchId,
    'expires_at' => $expiresAt,
    
    // حقول محسوبة تلقائياً
    'balance_before' => $balanceBefore,    // الرصيد قبل المعاملة
    'balance_after' => $balanceAfter,      // الرصيد بعد المعاملة
    'points_month' => now()->month,        // شهر المعاملة
    'points_year' => now()->year,          // سنة المعاملة
    'created_by' => Auth::id(),            // المستخدم المنشئ
]);
```

#### حساب الأرصدة
```php
// حساب الرصيد قبل المعاملة (من معاملات الشهر الحالي)
$balanceBefore = PointsTransaction::where('user_type', $userType)
    ->where($idColumn, $userId)
    ->where('points_month', now()->month)
    ->where('points_year', now()->year)
    ->where('status', 'completed')
    ->sum('points');

// حساب الرصيد بعد المعاملة
$balanceAfter = $balanceBefore + $points;
```

---

### المرحلة 3️⃣: العمليات التلقائية (عند Status = COMPLETED فقط)

#### 🎁 إنشاء دفعة النقاط (Points Batch)

**الشرط:** النقاط موجبة (`points > 0`)

**الغرض:** تتبع انتهاء صلاحية النقاط بدقة حسب نظام FIFO

**الجدول:** `points_batches`

```php
PointsBatch::create([
    'transaction_id' => $transaction->id,
    'user_type' => $userType,
    'employee_id' => $employeeId,
    'client_id' => $clientId,
    'original_points' => $points,           // النقاط الأصلية
    'remaining_points' => $points,          // النقاط المتبقية
    'used_points' => 0,                     // النقاط المستخدمة
    'expired_points' => 0,                  // النقاط المنتهية
    'earned_at' => now(),                   // تاريخ الكسب
    'expires_at' => $expiryDate,            // تاريخ الانتهاء
    'status' => 'active',
]);
```

#### حساب تاريخ الانتهاء

يعتمد على `points_expiry_settings`:

| نوع الانتهاء | الحساب | مثال |
|--------------|---------|------|
| **FIFO** | `now() + expiry_days + grace_period_days` | 365 يوم + 30 يوم سماح |
| **FIXED_DATE** | أقرب تاريخ ثابت قادم (مثل نهاية السنة) | 31/12/2026 |
| **ANNIVERSARY** | ذكرى انضمام المستخدم | تاريخ تعيين الموظف |
| **NEVER** | لا تنتهي الصلاحية | `null` |

**استثناءات المستويات:**
- إذا كان المستخدم في مستوى مستثنى (`excluded_tier_ids`)، لا تنتهي نقاطه

#### 📧 إشعار كسب النقاط

**الخدمة:** `PointsNotificationService::notifyPointsEarned()`

**البيانات المرسلة:**
```php
[
    'user_name' => 'محمد أحمد',
    'points' => 100,
    'action_type' => 'purchase',
    'balance_after' => 1500,
]
```

**القنوات:**
- إشعارات داخل النظام (database)
- بريد إلكتروني (إذا مفعل)
- SMS (إذا مفعل)

---

#### 🏆 فحص وتسجيل ترقية المستوى (Tier Upgrade)

**الوظيفة:** `checkAndLogTierUpgrade()`

##### الخطوات:

1. **حساب النقاط الشهرية الإجمالية:**
```php
$monthlyPoints = PointsTransaction::where('user_type', $userType)
    ->where($idColumn, $userId)
    ->where('points_month', now()->month)
    ->where('points_year', now()->year)
    ->where('points', '>', 0)
    ->where('status', 'completed')
    ->sum('points');
```

2. **تحديد المستوى المناسب:**
```php
$newTier = PointsTier::getTierForPoints($userType, $monthlyPoints);
```

3. **مقارنة مع المستوى السابق:**
```php
$lastUpgrade = TierUpgradeLog::where('user_type', $userType)
    ->where($idColumn, $userId)
    ->orderBy('created_at', 'desc')
    ->first();

$lastTierId = $lastUpgrade ? $lastUpgrade->new_tier_id : null;
```

4. **تسجيل الترقية (إذا تغير المستوى):**

**الجدول:** `tier_upgrade_logs`

```php
TierUpgradeLog::create([
    'user_type' => $userType,
    'employee_id' => $employeeId,
    'client_id' => $clientId,
    'old_tier_id' => $lastTierId,           // المستوى السابق
    'new_tier_id' => $newTier->id,          // المستوى الجديد
    'points_at_upgrade' => $monthlyPoints,  // النقاط عند الترقية
    'upgrade_type' => 'automatic',          // نوع الترقية
    'upgraded_by' => Auth::id(),
    'created_at' => now(),
]);
```

##### 📧 إشعار ترقية المستوى

**الخدمة:** `PointsNotificationService::notifyTierUpgraded()`

**البيانات:**
```php
[
    'user_name' => 'محمد أحمد',
    'old_tier' => 'برونزي',
    'new_tier' => 'فضي',
    'points' => 1500,
]
```

---

#### 🥇 فحص ومنح الإنجازات (Achievements)

**الخدمة:** `AchievementService::checkAchievements()`

##### الخطوات:

1. **جلب جميع الإنجازات النشطة** من `achievements`

2. **فحص شروط كل إنجاز:**
   - إنجازات النقاط (points-based)
   - إنجازات المعاملات (transaction-based)
   - إنجازات الأنشطة (activity-based)

3. **منح الإنجازات الجديدة:**

**الجدول:** `user_achievements`

```php
UserAchievement::create([
    'achievement_id' => $achievement->id,
    'employee_id' => $employeeId,
    'client_id' => $clientId,
    'achieved_at' => now(),
    'points_awarded' => $achievement->bonus_points,
]);
```

4. **إضافة نقاط المكافأة:**
```php
// يتم إنشاء معاملة نقاط جديدة للمكافأة
PointsTransaction::create([
    'action_type' => 'achievement_bonus',
    'points' => $achievement->bonus_points,
    'status' => 'completed',
    // ...
]);
```

##### 📧 إشعار تحقيق الإنجاز

**الخدمة:** `PointsNotificationService::notifyAchievementUnlocked()`

**البيانات:**
```php
[
    'user_name' => 'محمد أحمد',
    'achievement_name' => 'المشتري المميز',
    'bonus_points' => 500,
]
```

**رسالة النجاح:**
```
✅ تم إضافة معاملة النقاط بنجاح
🏆 تم تحقيق إنجازات جديدة: المشتري المميز، العميل الذهبي
```

---

#### 🎮 تحديث تقدم التحديات (Challenge Progress)

**الخدمة:** `ChallengeService::updateProgressForTransaction()`

##### الخطوات:

1. **البحث عن التحديات النشطة للمستخدم:**
```php
$activeParticipations = ChallengeParticipant::whereHas('challenge', function($q) {
    $q->where('status', 'active')
      ->where('starts_at', '<=', now())
      ->where('ends_at', '>=', now());
})
->where($idColumn, $userId)
->get();
```

2. **تحديث التقدم لكل تحدي:**

**الجدول:** `challenge_participants`

```php
// تحديث current_progress بناءً على نوع التحدي
$participant->update([
    'current_progress' => $newProgress,
    'progress_percentage' => ($newProgress / $targetValue) * 100,
    'updated_at' => now(),
]);
```

3. **فحص إكمال التحدي:**
```php
if ($participant->current_progress >= $challenge->target_value) {
    $participant->update([
        'is_completed' => true,
        'completed_at' => now(),
        'points_awarded' => $challenge->reward_points,
    ]);
    
    // إنشاء معاملة نقاط للمكافأة
    PointsTransaction::create([
        'action_type' => 'challenge_completion',
        'points' => $challenge->reward_points,
        // ...
    ]);
}
```

---

## 📊 ملخص الجداول المتأثرة

| الجدول | العملية | التوقيت | الشرط |
|--------|---------|---------|-------|
| **points_transactions** | إنشاء (INSERT) | دائماً | - |
| **points_batches** | إنشاء (INSERT) | عند الحفظ | نقاط موجبة + مكتملة |
| **tier_upgrade_logs** | إنشاء (INSERT) | عند الإكمال | تغيير المستوى |
| **user_achievements** | إنشاء (INSERT) | عند الإكمال | تحقيق شروط إنجاز جديد |
| **challenge_participants** | تحديث (UPDATE) | عند الإكمال | وجود تحديات نشطة |
| **notifications** | إنشاء (INSERT) | عند الإكمال | حسب كل عملية |

---

## 📧 ملخص الإشعارات المرسلة

### 1. إشعار كسب النقاط
- **المحفز:** نقاط موجبة + مكتملة
- **المستلم:** المستخدم (الموظف أو العميل)
- **المحتوى:** كسبت {points} نقطة من {action_type}. رصيدك الحالي: {balance_after}

### 2. إشعار ترقية المستوى
- **المحفز:** تغيير المستوى
- **المستلم:** المستخدم
- **المحتوى:** تهانينا! تمت ترقيتك من مستوى {old_tier} إلى {new_tier}

### 3. إشعار تحقيق الإنجاز
- **المحفز:** إنجاز جديد
- **المستلم:** المستخدم
- **المحتوى:** 🏆 تهانينا! حققت إنجاز "{achievement_name}" وحصلت على {bonus_points} نقطة مكافأة

### 4. إشعار إكمال التحدي
- **المحفز:** إنجاز تحدي
- **المستلم:** المستخدم
- **المحتوى:** 🎉 أكملت تحدي "{challenge_name}" وحصلت على {reward_points} نقطة!

---

## 🔄 مخطط سير العمل (Workflow Diagram)

```
┌─────────────────────────────────────────┐
│   إنشاء معاملة نقاط جديدة               │
└──────────────┬──────────────────────────┘
               │
               ├─► التحقق من الصحة (Validation)
               ├─► فحص الإعدادات العامة (General Settings)
               ├─► حساب النقاط التلقائي (Auto Calculate)
               ├─► حساب الأرصدة (Balance Calculation)
               │
               ▼
┌─────────────────────────────────────────┐
│   حفظ في points_transactions            │
└──────────────┬──────────────────────────┘
               │
        ┌──────┴──────┐
        │             │
    [معلقة]     [مكتملة] ◄── التركيز هنا
        │             │
        │             ├─► 🎁 إنشاء دفعة نقاط (Batch)
        │             │      - حساب تاريخ الانتهاء
        │             │      - حفظ في points_batches
        │             │
        │             ├─► 📧 إشعار كسب النقاط
        │             │
        │             ├─► 🏆 فحص ترقية المستوى
        │             │      - حساب النقاط الشهرية
        │             │      - تحديد المستوى المناسب
        │             │      - حفظ في tier_upgrade_logs
        │             │      - 📧 إشعار ترقية المستوى
        │             │
        │             ├─► 🥇 فحص الإنجازات
        │             │      - فحص شروط الإنجازات
        │             │      - حفظ في user_achievements
        │             │      - إضافة نقاط المكافأة
        │             │      - 📧 إشعار تحقيق الإنجاز
        │             │
        │             └─► 🎮 تحديث التحديات
        │                    - تحديث current_progress
        │                    - فحص الإكمال
        │                    - منح نقاط المكافأة
        │
        ▼
┌─────────────────────────────────────────┐
│   إعادة توجيه مع رسائل النجاح           │
└─────────────────────────────────────────┘
```

---

## 💡 ملاحظات مهمة

### الأداء (Performance)
- جميع العمليات التلقائية تتم في نفس الطلب (Synchronous)
- استخدام `try-catch` لضمان عدم فشل المعاملة الأساسية إذا فشلت عملية فرعية
- يتم تسجيل الأخطاء في لوجات النظام للمراجعة

### المعاملات المعلقة (Pending)
- لا تنفذ العمليات التلقائية للمعاملات المعلقة
- عند تحديث الحالة إلى `completed`، تُنفذ العمليات في `update()`

### النقاط السالبة (Negative Points)
- لا يتم إنشاء دفعة نقاط للنقاط السالبة
- يتم خصمها من أقدم دفعة متاحة (FIFO)
- لا يتم إرسال إشعار كسب نقاط

### نقاط المكافآت (Bonus Points)
- نقاط الإنجازات والتحديات تُنشأ كمعاملات منفصلة
- تمر بنفس سير العمل (قد تحقق إنجازات أخرى!)

---

## 🔍 مثال عملي

### السيناريو
موظف يقوم بعملية بيع بقيمة 1000 ريال

### المدخلات
```php
[
    'user_type' => 'employee',
    'employee_id' => 42,
    'action_type' => 'sales_transaction',
    'transaction_amount' => 1000,
    'status' => 'completed',
    'branch_id' => 5,
]
```

### النتائج التلقائية

#### 1. معاملة النقاط
```
✅ تم إنشاء معاملة #1523
- النقاط: 100 (10% من 1000)
- الرصيد قبل: 850
- الرصيد بعد: 950
```

#### 2. دفعة النقاط
```
✅ تم إنشاء دفعة #782
- النقاط الأصلية: 100
- النقاط المتبقية: 100
- تنتهي في: 16/02/2027 (بعد 365 يوم)
```

#### 3. ترقية المستوى
```
🏆 ترقية المستوى!
- المستوى السابق: برونزي (0-499 نقطة)
- المستوى الجديد: فضي (500-999 نقطة)
- النقاط الشهرية: 950
```

#### 4. الإنجازات
```
🥇 تم تحقيق إنجازين:
1. "أول 100 معاملة" → +50 نقطة مكافأة
2. "محترف المبيعات" → +100 نقطة مكافأة
```

#### 5. التحديات
```
🎮 تحديث التحديات:
1. "تحدي المبيعات الشهري"
   - التقدم: 8500/10000 ريال (85%)
   - متبقي: 1500 ريال
```

#### 6. الإشعارات
```
📧 تم إرسال 4 إشعارات:
1. كسب 100 نقطة
2. ترقية من برونزي إلى فضي
3. إنجاز "أول 100 معاملة"
4. إنجاز "محترف المبيعات"
```

### الرصيد النهائي
```
الرصيد الأصلي: 850
+ نقاط المعاملة: 100
+ مكافأة الإنجاز 1: 50
+ مكافأة الإنجاز 2: 100
= الرصيد النهائي: 1100 نقطة 🎉
```

---

## 📚 المراجع

### الملفات ذات الصلة
- `Modules/Ecosystem/Http/Controllers/PointsTransactionController.php`
- `app/Services/AchievementService.php`
- `app/Services/ChallengeService.php`
- `app/Services/PointsNotificationService.php`
- `app/Models/Ecosystem/PointsTransaction.php`
- `app/Models/Ecosystem/PointsBatch.php`
- `app/Models/Ecosystem/TierUpgradeLog.php`

### النماذج (Models)
- `PointsTransaction`
- `PointsBatch`
- `PointsTier`
- `TierUpgradeLog`
- `Achievement`
- `UserAchievement`
- `Challenge`
- `ChallengeParticipant`
- `PointsSetting`
- `PointsGeneralSetting`
- `PointsExpirySetting`

---

**تاريخ التوثيق:** 16 فبراير 2026  
**الإصدار:** 1.0  
**الحالة:** محدّث ✅
