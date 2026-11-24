# دليل نظام الإحالات (Referral System)

## نظرة عامة
نظام الإحالات يسمح للعملاء بإحالة عملاء جدد والحصول على نقاط مكافآت عند تأكيد الإحالة.

---

## المكونات الأساسية

### 1. **Models & Enums**
```
- Referral Model: تخزين سجلات الإحالات
- ReferralSetting Model: إعدادات النظام لكل فرع
- ConfirmationType Enum: نوع تأكيد الإحالة
- ReferralStatus Enum: حالة الإحالة
```

### 2. **Database Tables**

#### `referrals` table
```php
- id
- referrer_id (المحيل)
- referred_id (المحال)
- referral_code (كود الإحالة)
- status (PENDING, CONFIRMED, CANCELLED)
- referrer_points (نقاط المحيل)
- referred_points (نقاط المحال)
- confirmed_at
- cancelled_at
- metadata (JSON - تفاصيل إضافية)
- expires_at (تاريخ انتهاء الإحالة - 30 يوم)
```

#### `referral_settings` table
```php
- id
- branch_id (الفرع)
- referrer_points (نقاط المحيل عند التأكيد)
- referred_points (نقاط المحال عند التأكيد)
- confirmation_type (REGISTRATION, FIRST_PURCHASE, MIN_PURCHASE, DAYS_ACTIVE)
- confirmation_value (قيمة شرط التأكيد)
- max_referrals_per_month (حد أقصى شهري)
- max_referrals_total (حد أقصى إجمالي)
- bonus_milestones (مكافآت عند تجاوز أرقام معينة)
- is_active
```

---

## سير العمل

### 1. **إنشاء إحالة من بوابة العميل**

```blade
<form action="{{ route('client-portal.referral.submit') }}" method="POST">
    @csrf
    <input type="text" name="referred_name" placeholder="اسم الشخص" required>
    <input type="tel" name="referred_phone" placeholder="رقم الهاتف" required>
    <button type="submit">إرسال الإحالة</button>
</form>
```

**المعالجة في Controller:**
```php
public function submitReferral(Request $request)
{
    // 1. التحقق من تسجيل دخول العميل
    // 2. التحقق من إعدادات الإحالة (is_active)
    // 3. التحقق من الحد الأقصى الشهري والإجمالي
    // 4. البحث عن المحال بالهاتف
    // 5. التحقق من عدم إحالة نفسه
    // 6. إنشاء سجل إحالة بحالة PENDING
    // 7. إذا كان المحال مسجل ونوع التأكيد = REGISTRATION:
    //    - تأكيد الإحالة فوراً
    //    - إضافة نقاط للمحيل
}
```

### 2. **تأكيد الإحالة**
الإحالة تُؤكّد عند:
- ✅ التسجيل (REGISTRATION)
- ✅ أول عملية شراء (FIRST_PURCHASE)
- ✅ الوصول لحد أدنى من الشراء (MIN_PURCHASE)
- ✅ التفعيل لعدد معين من الأيام (DAYS_ACTIVE)

### 3. **إضافة النقاط**
عند التأكيد:
```php
PointsTransaction::create([
    'client_id' => $client->id,
    'points' => $settings->referrer_points,
    'description' => 'نقاط من إحالة عميل: ' . $referredName,
    'status' => TransactionStatus::COMPLETED,
    'transaction_type' => 'referral_reward',
]);
```

---

## الحدود والقيود

### 1. **الحد الأقصى الشهري**
```php
if ($settings->max_referrals_per_month > 0) {
    $monthlyCount = Referral::where('referrer_id', $client->id)
        ->whereMonth('created_at', now()->month)
        ->count();
    
    if ($monthlyCount >= $settings->max_referrals_per_month) {
        return back()->with('error', 'وصلت للحد الأقصى هذا الشهر');
    }
}
```

### 2. **الحد الأقصى الإجمالي**
```php
if ($settings->max_referrals_total > 0) {
    $totalCount = Referral::where('referrer_id', $client->id)->count();
    
    if ($totalCount >= $settings->max_referrals_total) {
        return back()->with('error', 'وصلت للحد الأقصى الإجمالي');
    }
}
```

### 3. **عدم السماح بإحالة متكررة**
```php
$existingReferral = Referral::where('referrer_id', $client->id)
    ->where('referred_id', $referred->id)
    ->first();

if ($existingReferral) {
    return back()->with('error', 'لقد قمت بإحالة هذا العميل مسبقاً');
}
```

---

## عرض الإحالات في لوحة العميل

### 1. **كود الإحالة الفريد**
```blade
<div class="referral-code-box">
    <div class="referral-code">{{ $client->referral_code }}</div>
    <button onclick="copyReferralCode()">نسخ الكود</button>
</div>
```

### 2. **إحصائيات الإحالات**
```blade
<div>
    <div>{{ $referralStats['total'] }} إجمالي</div>
    <div>{{ $referralStats['confirmed'] }} مؤكدة</div>
    <div>{{ $referralStats['total_points_earned'] }} نقاط</div>
</div>
```

### 3. **نموذج إرسال إحالة**
```blade
<form action="{{ route('client-portal.referral.submit') }}" method="POST">
    @csrf
    <input type="text" name="referred_name" placeholder="اسم الشخص" required>
    <input type="tel" name="referred_phone" placeholder="رقم الهاتف" required>
    <button type="submit">إرسال الإحالة</button>
</form>
```

### 4. **قائمة الإحالات الأخيرة**
```blade
@foreach($referrals->take(5) as $referral)
    <div>
        <div>{{ $referral->metadata['referred_name'] }}</div>
        <span>{{ $referral->status === 'confirmed' ? 'مؤكدة' : 'معلقة' }}</span>
    </div>
@endforeach
```

---

## التحقق من النظام

### 1. **التأكد من وجود الـ Route**
```bash
php artisan route:list | grep referral
```

يجب أن تظهر:
```
POST /client-portal/referral ........................... client-portal.referral.submit
```

### 2. **التحقق من الهاتف في Database**
```php
$client = Client::find(1);
echo $client->phone;      // نتحقق من رقم الهاتف الأساسي
echo $client->mobile;     // أو رقم الهاتف البديل
```

### 3. **التحقق من إعدادات الإحالة**
```php
$settings = ReferralSetting::getActiveSettings($branchId);
if ($settings && $settings->is_active) {
    echo "النظام مفعل";
}
```

### 4. **اختبار الإحالة**
```php
$referral = Referral::create([
    'referrer_id' => 1,
    'referred_id' => 2,
    'referral_code' => 'ABC123',
    'status' => 'pending',
    'referrer_points' => 100,
    'metadata' => ['referred_name' => 'Test', 'referred_phone' => '966501234567'],
]);
```

---

## المميزات الإضافية

### 1. **مكافآت Milestone**
```php
$bonusPoints = $settings->getBonusPointsForMilestone($referralCount);
// مثال: 50 نقطة إضافية عند 10 إحالات ناجحة
```

### 2. **انتهاء الصلاحية**
```php
// الإحالة تنتهي صلاحيتها بعد 30 يوم من الإنشاء
$referral->expires_at = now()->addDays(30);
```

### 3. **منع الإرسال المكرر**
```javascript
let referralSubmitting = false;

referralForm.addEventListener('submit', function(e) {
    if (referralSubmitting) {
        e.preventDefault();
        return;
    }
    referralSubmitting = true;
});
```

---

## استكشاف الأخطاء

### المشكلة: لا تظهر الإحالات
**الحل:**
1. تحقق من `$referralSettings->is_active === true`
2. تحقق من وجود الفرع `$client->branch_id`
3. تحقق من وجود سجلات في جدول `referrals`

### المشكلة: النموذج لا يرسل البيانات
**الحل:**
1. تأكد من وجود `@csrf` في النموذج
2. تأكد من اسم الـ Route الصحيح
3. تحقق من console للأخطاء

### المشكلة: لا تظهر رسائل الخطأ
**الحل:**
1. تأكد من إضافة `@error('referred_name')` في الـ Blade
2. تحقق من `old('referred_name')` للحفاظ على القيم

---

## مثال عملي كامل

```php
// 1. إعداد الفرع
$branch = Branch::find(1);

// 2. إعداد نظام الإحالات
ReferralSetting::updateOrCreate(
    ['branch_id' => $branch->id],
    [
        'referrer_points' => 100,           // نقاط المحيل
        'referred_points' => 50,            // نقاط المحال
        'confirmation_type' => 'registration',
        'max_referrals_per_month' => 10,
        'max_referrals_total' => 50,
        'is_active' => true,
    ]
);

// 3. عميل يحيل عميل آخر
// يملأ النموذج ويرسله

// 4. النظام ينشئ سجل إحالة
$referral = Referral::create([...]);

// 5. عند تسجيل المحال
if ($referral->confirmation_type === 'registration') {
    $referral->confirm();  // تأكيد الإحالة
    // إضافة نقاط تلقائية
}
```

---

## الملفات ذات الصلة
- `app/Models/Ecosystem/Referral.php`
- `app/Models/Ecosystem/ReferralSetting.php`
- `app/Enums/ConfirmationType.php`
- `app/Enums/ReferralStatus.php`
- `app/Http/Controllers/ClientPortalController.php`
- `resources/views/client-portal/dashboard.blade.php`
- `routes/web.php` (client-portal routing)

---

**آخر تحديث:** 22 فبراير 2026
