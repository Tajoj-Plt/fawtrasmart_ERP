# Integration API — توثيق واجهة تطبيق الجوال (Flutter)

واجهة برمجية مخصّصة **للمناديب/الموظفين فقط** — كل حساب مستخدم مرتبط بسجل موظف (`users.employee_id`). أي حساب غير مرتبط بموظف يُرفض بـ `403`.

- **Base URL:** `{{base_url}}/api/integration/v1`
- **الصيغة:** JSON — أرسل دائماً الترويسة `Accept: application/json`
- **المصادقة:** Laravel Sanctum (Bearer Token) — الترويسة `Authorization: Bearer {token}`
- **مجموعة Postman جاهزة:** [`Integration.postman_collection.json`](./Integration.postman_collection.json)

## شكل الاستجابة الموحّد

```json
{
    "success": true,
    "message": "نص عربي يصلح للعرض مباشرة في التطبيق",
    "data": { }
}
```

| الرمز | المعنى |
|-------|--------|
| `200` | نجاح |
| `401` | بيانات دخول خاطئة أو رمز وصول مفقود/منتهٍ |
| `402` | الاشتراك منتهٍ أو غير نشط (وسيط `EnsureSubscription` العام) |
| `403` | الحساب ليس حساب موظف |
| `422` | خطأ تحقق من المدخلات، أو رمز OTP خاطئ/منتهٍ |
| `429` | تجاوز حد الطلبات (Throttling) |

أخطاء التحقق (422) تأتي بصيغة Laravel القياسية: `{"message": "...", "errors": {"field": ["..."]}}`.

---

## 1) المصادقة

### 1.1 تسجيل الدخول

`POST /auth/login` — *(عام — 10 طلبات/دقيقة)*

| الحقل | النوع | إلزامي | ملاحظات |
|-------|------|--------|---------|
| `email` | string | ✔ | بريد حساب المستخدم |
| `password` | string | ✔ | |
| `device_name` | string | ✖ | اسم الجهاز؛ الدخول بنفس الاسم يُبطل الرمز القديم لنفس الجهاز |

**استجابة ناجحة:**

```json
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح.",
    "data": {
        "token": "1|xxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer",
        "email_verified": false,
        "profile": { "user": { }, "employee": { } }
    }
}
```

- `401` بيانات خاطئة، `403` الحساب ليس موظفاً.
- احفظ الـ `token` وأرسله في كل الطلبات المحمية: `Authorization: Bearer {token}`.

### 1.2 تسجيل الخروج

`POST /auth/logout` — *(محمي)* — يُبطل رمز الجهاز الحالي فقط؛ بقية الأجهزة تبقى متصلة.

---

## 2) استعادة كلمة المرور (OTP بالبريد)

التسلسل: **forgot → (verify-otp اختياري) → reset**

الرمز: 6 أرقام، صالح **10 دقائق**، أقصى **5 محاولات** خاطئة، وطلب رمز جديد قبل مرور **60 ثانية** يُتجاهل. الرمز الجديد يُبطل القديم.

### 2.1 طلب رمز الاستعادة

`POST /auth/password/forgot` — *(عام — 5 طلبات/دقيقة)*

| الحقل | النوع | إلزامي |
|-------|------|--------|
| `email` | string | ✔ |

الاستجابة **دائماً ناجحة** وبنفس النص وُجد البريد أم لا — حماية من تعداد المستخدمين:

```json
{ "success": true, "message": "إن كان البريد مسجَّلاً لدينا فسيصلك رمز التحقق خلال دقائق." }
```

### 2.2 التحقق من الرمز (اختياري)

`POST /auth/password/verify-otp` — *(عام — 10 طلبات/دقيقة)*

خطوة وسطى للتطبيق: تحقّق من الرمز **دون استهلاكه** قبل عرض شاشة كلمة المرور الجديدة.

| الحقل | النوع | إلزامي |
|-------|------|--------|
| `email` | string | ✔ |
| `otp` | string (6 أرقام) | ✔ |

- `200` الرمز صحيح، `422` خاطئ أو منتهٍ.

### 2.3 تعيين كلمة المرور الجديدة

`POST /auth/password/reset` — *(عام — 5 طلبات/دقيقة)*

| الحقل | النوع | إلزامي | ملاحظات |
|-------|------|--------|---------|
| `email` | string | ✔ | |
| `otp` | string (6 أرقام) | ✔ | يُستهلك عند النجاح |
| `password` | string | ✔ | 8 أحرف فأكثر |
| `password_confirmation` | string | ✔ | |

عند النجاح تُبطل **كل** رموز الوصول القائمة (خروج إجباري من كل الأجهزة) ويجب تسجيل الدخول من جديد.

---

## 3) توثيق البريد الإلكتروني *(المسارات محمية)*

### 3.1 إرسال رمز التوثيق

`POST /auth/email/send-otp` — *(محمي — 5 طلبات/دقيقة)* — بلا جسم طلب؛ يُرسل الرمز لبريد المستخدم الحالي. إن كان البريد موثَّقاً مسبقاً تعود رسالة تفيد بذلك دون إرسال.

### 3.2 التوثيق بالرمز

`POST /auth/email/verify` — *(محمي — 10 طلبات/دقيقة)*

| الحقل | النوع | إلزامي |
|-------|------|--------|
| `otp` | string (6 أرقام) | ✔ |

عند النجاح يُسجَّل `email_verified_at` ويصير `email_verified: true` في الملف الشخصي.

---

## 4) الملف الشخصي *(كل المسارات محمية)*

### 4.1 جلب الملف الشخصي

`GET /profile`

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "أحمد محمد",
            "email": "ahmad@example.com",
            "phone": "0501234567",
            "avatar_url": "https://.../storage/avatars/xxx.jpg",
            "email_verified": true,
            "branch_id": 1,
            "last_login_at": "2026-09-20 10:30:00"
        },
        "employee": {
            "id": 5,
            "employee_code": "EMP-0005",
            "first_name": "أحمد",
            "middle_name": "محمد",
            "nickname": null,
            "full_name": "أحمد محمد",
            "photo_url": null,
            "email": "ahmad@example.com",
            "personal_email": null,
            "mobile_number": "0501234567",
            "phone_number": null,
            "language": "ar",
            "status": "active",
            "employee_type": "field",
            "hire_date": "2024-01-15",
            "date_of_birth": "1995-06-01",
            "gender": "male",
            "country": "SA",
            "job_title": "مندوب مبيعات",
            "department": "المبيعات",
            "branch": "الفرع الرئيسي",
            "emergency_contact": { "name": null, "phone": null, "relation": null },
            "address": { "line_1": null, "line_2": null, "city": null, "region": null, "postal_code": null }
        }
    }
}
```

### 4.2 تعديل الملف الشخصي

`PUT /profile` — كل الحقول اختيارية (أرسل ما تريد تعديله فقط):

| الحقل | يُحدَّث في | ملاحظات |
|-------|-----------|---------|
| `name` | حساب المستخدم | |
| `phone` | حساب المستخدم | |
| `mobile_number`, `phone_number` | سجل الموظف | |
| `personal_email` | سجل الموظف | بريد صالح |
| `language` | سجل الموظف | مثل `ar` / `en` |
| `nickname` | سجل الموظف | |
| `emergency_contact_name/phone/relation` | سجل الموظف | جهة الطوارئ |
| `current_address_1`, `current_address_2`, `city`, `region`, `postal_code` | سجل الموظف | العنوان الحالي |

> حقول التوظيف (المسمّى، القسم، الفرع، الراتب، الحالة، الرقم الوظيفي…) **لا** تُقبل من التطبيق — تُدار من شاشات HR فقط، وأي حقل خارج القائمة أعلاه يُتجاهل.

الاستجابة تُعيد الملف الشخصي كاملاً بعد التعديل (نفس صيغة 4.1).

### 4.3 تحديث الصورة الشخصية

`POST /profile/avatar` — **multipart/form-data**

| الحقل | النوع | إلزامي | ملاحظات |
|-------|------|--------|---------|
| `avatar` | file | ✔ | صورة jpg/jpeg/png/webp — حتى 2MB |

```json
{ "success": true, "message": "تم تحديث الصورة الشخصية بنجاح.", "data": { "avatar_url": "https://.../storage/avatars/xxx.jpg" } }
```

### 4.4 تغيير كلمة المرور (وأنا مسجَّل دخول)

`POST /profile/change-password`

| الحقل | النوع | إلزامي | ملاحظات |
|-------|------|--------|---------|
| `current_password` | string | ✔ | تُطابَق مع كلمة المرور الحالية |
| `password` | string | ✔ | 8 أحرف فأكثر، مختلفة عن الحالية |
| `password_confirmation` | string | ✔ | |

عند النجاح تُبطل رموز **الأجهزة الأخرى** ويبقى الجهاز الحالي متصلاً.

---

## ملاحظات تقنية للمطوّر

- **الوحدة:** `Modules/Integration` — مسارات في `routes/api.php`، متحكّمات في `Http/Controllers/Api/`، رموز التحقق في جدول `integration_otps` (مُجزّأة hash لا نصاً صريحاً).
- **بوابة الموظفين:** الوسيط `Modules\Integration\Http\Middleware\EnsureEmployeeUser` يفحص `User::isEmployee()` (ارتباط `employee_id`) — لا الاعتماد على عمود `role` النصي.
- **البريد الصادر:** يُرسَل بإعدادات SMTP المخزّنة في قاعدة البيانات (شاشة الإعدادات ← البريد) عبر `MailerConfigurator` — لا يكفي ضبط `.env` وحده.
- **قالب رسالة OTP:** يُستخدم قالب النظام `auth.otp_code` من شاشة «قوالب البريد» (قابل للتحرير هناك)، وكل إرسال يُسجَّل في «سجل البريد» (`email_logs`) بنوع مستلم «موظف». إن حُذف القالب أو عُطِّل، تُرسَل الرسالة بقالب احتياطي داخل الوحدة (`integration::emails.otp`) فلا يضيع الرمز.
- **إعدادات OTP:** قابلة للتغيير في `Modules/Integration/config/config.php` (الطول، الصلاحية، عدد المحاولات، فاصل الإعادة).
- **الاشتراك:** وسيط `EnsureSubscription` مُلحق بمجموعة `api` كلها — اشتراك منتهٍ يعيد `402` بصيغة JSON على المسارات المحمية.
- **الترحيل:** `php artisan migrate --path=Modules/Integration/database/migrations`
