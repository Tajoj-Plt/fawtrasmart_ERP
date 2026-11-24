# حل مشكلة الزيارات المنسية | Forgotten Visits Solution

**التاريخ**: 2026-09-17 | **الحالة**: ✅ مُنفَّذ

---

## المشكلة الأصلية

عندما ينسى موظف إنهاء الزيارة عند العميل ويمشي من عنده:
1. الزيارة تبقى في حالة `STARTED` أو `IN_PROGRESS`
2. يتم حجب الموظف من المتابعة بفحص `CheckIncompleteVisits`
3. الموظف لا يستطيع إنهاء الزيارة من بُعد
4. المدير/المشرف لا يستطيع إنهاؤها نيابة عنه
5. لا يوجد تنبيه واضح أو فرصة للتصحيح

---

## الحل المُطبَّق

تم إضافة نظام شامل لإدارة الزيارات المنسية على 3 مستويات:

### 1️⃣ مستوى الصلاحيات (Permissions Layer)

#### الصلاحيات الجديدة
أُضيفت مجموعة صلاحيات جديدة تحت اسم `forgotten_visits`:

| الصلاحية | الدور | الوصف |
|---------|------|-------|
| `clients_view_forgotten_visits_own` | موظف عادي | عرض زياراته المنسية |
| `clients_close_forgotten_visits_own` | موظف عادي | إنهاء زيارته المنسية من البعيد |
| `clients_view_forgotten_visits_all` | مدير/مشرف | عرض زيارات كل الموظفين |
| `clients_force_close_forgotten_visits` | مدير/مشرف | إنهاء أي زيارة منسية |
| `clients_cancel_forgotten_visits` | مدير/مشرف | إلغاء زيارة منسية بالكامل |

**الملف**: `app/Constants/permissions/ClientsPermissions.php`

```php
'forgotten_visits' => [
    'label' => 'إدارة الزيارات المنسية',
    'description' => 'التعامل مع الزيارات التي بدأها الموظف ولم يُنهِها',
    'icon' => 'clock-alert',
    'controller' => 'Client/ForgottenVisitsController + VisitController',
    'permissions' => [
        'clients_view_forgotten_visits_own' => 'عرض زياراته المنسية',
        'clients_close_forgotten_visits_own' => 'إنهاء زيارة منسية له',
        'clients_view_forgotten_visits_all' => 'عرض الزيارات المنسية لجميع الموظفين',
        'clients_force_close_forgotten_visits' => 'إنهاء أي زيارة منسية',
        'clients_cancel_forgotten_visits' => 'إلغاء زيارة منسية (بسبب)',
    ],
]
```

### 2️⃣ مستوى الوظائف (Controller Methods)

#### الدوال الجديدة في `VisitController`

##### 1. `listForgottenVisits(Request $request)`
عرض قائمة بالزيارات المنسية للموظف نفسه أو لكل الموظفين حسب الصلاحية.

**المعاملات**:
- `scope` (query): `'own'` أو `'all'` — النطاق المطلوب

**الاستجابة**:
```json
{
  "success": true,
  "forgotten_visits": [
    {
      "id": 1,
      "employee_id": 5,
      "client_id": 100,
      "visit_date": "2026-09-17",
      "stage": "in_progress",
      "started_at": "2026-09-17T08:00:00",
      "employee": { "id": 5, "name": "أحمد محمد" },
      "client": { "id": 100, "trade_name": "متجر النور" }
    }
  ],
  "count": 3,
  "scope": "own"
}
```

**الشروط**:
- الزيارة لم تُنهَ بعد (`stage` ∈ `['arrived', 'in_progress', 'paused']`)
- بدأت منذ أكثر من 4 ساعات أو تاريخها أمس
- الموظف لديه صلاحية العرض المناسبة

**رابط الاستدعاء**:
```
GET /forgotten-visits?scope=own
GET /forgotten-visits?scope=all
```

---

##### 2. `closeForgottenVisit(Request $request, $visitId)`
إنهاء زيارة منسية عن بُعد — سواء من قبل الموظف نفسه أو المدير.

**المعاملات**:
```json
{
  "outcome": "successful",      // أو "unsuccessful", "no_answer", إلخ
  "notes": "الموظف لم يعد متاحاً"
}
```

**الاستجابة**:
```json
{
  "success": true,
  "message": "تم إنهاء الزيارة بنجاح",
  "visit": {
    "id": 1,
    "stage": "completed",
    "status": "completed",
    "departure_time": "2026-09-17T12:45:00",
    "outcome": "successful"
  }
}
```

**الشروط**:
- إذا كانت الزيارة للموظف نفسه: يحتاج صلاحية `clients_close_forgotten_visits_own`
- إذا كانت لموظف آخر: يحتاج صلاحية `clients_force_close_forgotten_visits`
- يتم تسجيل العملية في `OperationLog`
- يتم إرسال إخطار للموظف الأصلي (إذا أغلقها مدير)

**رابط الاستدعاء**:
```
POST /forgotten-visits/{visit}/close
```

**التأثيرات الجانبية**:
1. تحديث الزيارة: `stage = 'completed'`, `status = 'completed'`
2. تسجيل `departure_time` الحالي
3. تسجيل سجل نشاط تفصيلي
4. إرسال إخطار للموظف المسؤول (إذا أغلقها مدير)
5. **تُحرّر الموظف من حجب `CheckIncompleteVisits`**

---

##### 3. `cancelForgottenVisit(Request $request, $visitId)`
إلغاء زيارة منسية بالكامل (عندما تكون خطأً أو لا تستحق التكملة).

**المعاملات**:
```json
{
  "cancel_reason": "الموظف لم يدخل المتجر — أُغلق"
}
```

**الاستجابة**:
```json
{
  "success": true,
  "message": "تم إلغاء الزيارة بنجاح",
  "visit": {
    "id": 1,
    "stage": "cancelled",
    "status": "cancelled",
    "cancel_reason": "الموظف لم يدخل المتجر — أُغلق"
  }
}
```

**الشروط**:
- يحتاج صلاحية `clients_cancel_forgotten_visits`
- الموظف لا يلغي زيارات غيره إلا إذا كان مدير/مشرف
- السبب إجباري (`cancel_reason` مطلوب)

**رابط الاستدعاء**:
```
POST /forgotten-visits/{visit}/cancel
```

### 3️⃣ مستوى التوجيه (Routes)

تمّ إضافة 3 routes جديدة إلى `Modules/Client/routes/web.php`:

```php
// عرض الزيارات المنسية
GET /forgotten-visits
    → VisitController@listForgottenVisits
    → permission: clients_view_forgotten_visits_own|clients_view_forgotten_visits_all

// إنهاء زيارة منسية
POST /forgotten-visits/{visit}/close
    → VisitController@closeForgottenVisit
    → permission: clients_close_forgotten_visits_own|clients_force_close_forgotten_visits

// إلغاء زيارة منسية
POST /forgotten-visits/{visit}/cancel
    → VisitController@cancelForgottenVisit
    → permission: clients_cancel_forgotten_visits
```

---

## كيفية الاستخدام

### للموظف العادي (بدء ونسيان الزيارة)

1. **عرض الزيارات المنسية**:
   ```bash
   GET /forgotten-visits?scope=own
   ```
   يعرض قائمة بالزيارات التي بدأها ولم ينهِها.

2. **إنهاء الزيارة من البعيد**:
   ```bash
   POST /forgotten-visits/123/close
   {
     "outcome": "successful",
     "notes": "كنت بعيد عن المتجر"
   }
   ```

### للمدير/المشرف

1. **مراجعة زيارات الموظفين**:
   ```bash
   GET /forgotten-visits?scope=all
   ```
   يعرض كل الزيارات المنسية لجميع الموظفين.

2. **إنهاء زيارة موظف**:
   ```bash
   POST /forgotten-visits/123/close
   {
     "outcome": "no_answer",
     "notes": "الموظف لم يرد على الهاتف"
   }
   ```
   يتم إرسال إخطار للموظف بأن زيارته أُغلقت.

3. **إلغاء زيارة خاطئة**:
   ```bash
   POST /forgotten-visits/123/cancel
   {
     "cancel_reason": "خطأ في التسجيل — لم تكن هناك زيارة"
   }
   ```

---

## التكامل مع النظام الحالي

### مع `CheckIncompleteVisits` Middleware

عندما يقوم الموظف (أو المدير نيابة عنه) بإنهاء الزيارة المنسية:
1. يتم تحديث `stage` و `status` إلى `'completed'`
2. يُحرَّر الموظف من حجب `CheckIncompleteVisits` في الطلب التالي
3. لا يعود هناك حاجة لتبرير الزيارة

### مع `OperationLog`

تُسجَّل جميع العمليات (الإنهاء والإلغاء) مع:
- اسم المدير الذي أغلقها (إذا أغلقها مدير)
- الحالة السابقة والجديدة
- السبب أو الملاحظات

### مع Notifications

يتم إرسال إخطارات:
1. **عند إغلاق زيارة موظف (من قبل مدير)**:
   ```
   النوع: forgotten_visit_closed
   الرسالة: "المدير أحمد أنهى الزيارة المنسية عند العميل متجر النور"
   ```

2. **في سجل النشاط**:
   ```
   الفعل: closed_forgotten
   الوصف: إنهاء زيارة منسية (من قبل: المدير/الموظف)
   ```

---

## منح الصلاحيات للأدوار

### للموظفين العاديين (Employees)

أضف الصلاحيات:
```php
'clients_view_forgotten_visits_own'
'clients_close_forgotten_visits_own'
```

هذا يسمح لكل موظف بـ:
- رؤية زياراته المنسية
- إنهاء زيارته المنسية عن بُعد

### للمديرين/المشرفين (Managers)

أضف الصلاحيات:
```php
'clients_view_forgotten_visits_own'      // لرؤية زياراتهم أيضاً
'clients_close_forgotten_visits_own'
'clients_view_forgotten_visits_all'      // لرؤية زيارات الموظفين
'clients_force_close_forgotten_visits'   // لإنهاء أي زيارة
'clients_cancel_forgotten_visits'        // لإلغاء زيارة
```

### أوامر Artisan

لمزامنة الصلاحيات بعد الترحيل:

```bash
php artisan permissions:sync
php artisan optimize:clear
```

---

## ملفات التعديل

### 1️⃣ `app/Constants/permissions/ClientsPermissions.php`
- **التعديل**: إضافة مجموعة صلاحيات `forgotten_visits` مع 5 صلاحيات جديدة
- **السطور**: ~235-260

### 2️⃣ `Modules/Client/Http/Controllers/VisitController.php`
- **التعديل**: إضافة 3 دوال جديدة:
  - `listForgottenVisits()`
  - `closeForgottenVisit()`
  - `cancelForgottenVisit()`
- **السطور**: ~330 إلى النهاية

### 3️⃣ `Modules/Client/routes/web.php`
- **التعديل**: إضافة 3 routes جديدة
- **السطور**: ~715-735

---

## الاختبار (Testing)

### حالات الاختبار المهمة

#### 1. موظف ينسى الزيارة
```
1. تسجيل الدخول بحساب موظف
2. بدء زيارة عند عميل
3. عدم إنهاء الزيارة (إغلاق الصفحة)
4. الذهاب إلى صفحة أخرى
   → يتم حجب الموظف بـ CheckIncompleteVisits
5. GET /forgotten-visits?scope=own
   → الزيارة تظهر في القائمة
6. POST /forgotten-visits/123/close
   → الزيارة تُنهى وينحل الحجب
```

#### 2. مدير ينهي زيارة موظف
```
1. تسجيل الدخول بحساب مدير
2. GET /forgotten-visits?scope=all
   → تظهر زيارات جميع الموظفين
3. POST /forgotten-visits/123/close
   → تُنهى الزيارة
4. إخطار يُرسل للموظف
5. سجل نشاط يُكتب باسم المدير
```

#### 3. إلغاء زيارة خاطئة
```
1. POST /forgotten-visits/123/cancel
   { "cancel_reason": "تسجيل خاطئ" }
   → تُلغى الزيارة
2. لا يتم حساب الزيارة في التقارير
```

---

## الفوائد

✅ **للموظفين**:
- لا يعودون محجوبين من العمل إذا نسوا إنهاء زيارة
- إمكانية إنهاء الزيارة من أي مكان بضغطة زرّ
- إخطار واضح بالزيارات المنسية

✅ **للمديرين**:
- إمكانية إنهاء زيارة موظف دون الانتظار
- تحكم أفضل على جودة تسجيل الزيارات
- سجل دقيق لمَن أغلق الزيارة ومتى

✅ **للنظام**:
- لا تراكم زيارات غير مكتملة قديمة
- تقارير دقيقة بلا بيانات معلقة
- سجلات تفصيلية لكل حالة

---

## الملاحظات المهمة

⚠️ **تعريف الزيارة المنسية**:
- لا تزال في حالة `STARTED` أو `IN_PROGRESS`
- بدأت منذ أكثر من 4 ساعات (قابل للتعديل)
- أو تاريخ الزيارة هو اليوم السابق

⚠️ **الموقع الجغرافي**:
- لا يُتحقَّق من الموقع عند إنهاء زيارة منسية من البعيد
- (بخلاف إنهاء الزيارة العادي من مساحة العمل)

⚠️ **التأثير على التبريرات**:
- إذا كانت الزيارة تحتاج تبرير، يتم حل المشكلة بإنهاء الزيارة
- لا حاجة لتقديم تبرير بعدها

---

## أمثلة من الحياة الواقعية

### مثال 1: موظف بطاريته انتهت
```
الموظف: أنا في الطريق وبطاريتي انتهت ولم أستطع إنهاء الزيارة
الحل: يدخل لاحقاً ويفتح التطبيق → /forgotten-visits → يختار الزيارة → click "إنهاء" ✅
```

### مثال 2: موظف غاب مفاجئ
```
الموظف: دخلت العميل وأصابني شيء طارئ
المدير: الموظف غاب ولم ينهِ الزيارة
الحل: المدير يفتح /forgotten-visits → يختار زيارة الموظف → click "إنهاء" ✅
النتيجة: موظف يتلقى إخطار "المدير أنهى زيارتك"
```

### مثال 3: خطأ تسجيل
```
الموظف: دخلت الزيارة بالخطأ للعميل الخاطئ
الحل: المدير يفتح /forgotten-visits → اختر الزيارة → click "إلغاء" + السبب ✅
النتيجة: الزيارة تُحذف من التقارير كلياً
```

---

## الخطوات التالية (مستقبلية)

- [ ] إضافة UI component في لوحة التحكم
- [ ] إضافة Cron job يبلّغ عن زيارات منسية قديمة
- [ ] تكامل مع SMS/Telegram للتنبيهات الفورية
- [ ] تقرير يومي عن الزيارات المنسية
- [ ] رسم بياني لمعدل الزيارات المنسية (KPI)

---

**تم إعداد هذا الحل بتاريخ 2026-09-17** ✅
