# 📧 نظام إعدادات SMTP - FAWT Management System

<div align="center">

![SMTP](https://img.shields.io/badge/SMTP-Configuration-blue)
![Laravel](https://img.shields.io/badge/Laravel-9+-red)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple)
![Status](https://img.shields.io/badge/Status-Ready-green)

**نظام متكامل لإدارة إعدادات البريد الإلكتروني SMTP**

[التوثيق](#-التوثيق) • [التثبيت](#-التثبيت-السريع) • [الاستخدام](#-الاستخدام) • [المميزات](#-المميزات)

</div>

---

## 📖 نظرة عامة

نظام SMTP هو حل متكامل يتيح لكل مستخدم في نظام FAWT إعداد وحفظ معلومات حسابه البريدي الخاص لإرسال رسائل البريد الإلكتروني بشكل آمن ومستقل.

### ✨ لماذا هذا النظام؟

- ✅ **لكل مستخدم إعداداته الخاصة** - لا مشاركة للحسابات
- ✅ **أمان كامل** - تشفير تلقائي لكلمات المرور
- ✅ **سهولة الاستخدام** - واجهة عربية بديهية
- ✅ **دعم متعدد** - Gmail, Outlook, وأي خادم SMTP
- ✅ **اختبار فوري** - إرسال بريد تجريبي بنقرة واحدة

---

## 🚀 التثبيت السريع

### المتطلبات
- Laravel 9+ أو أعلى
- PHP 8.0+
- MySQL / MariaDB
- جدول users في قاعدة البيانات

### الخطوات

```bash
# 1. تشغيل Migration
php artisan migrate

# 2. مسح الـ Cache (اختياري)
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. جاهز للاستخدام! 🎉
```

**الوصول إلى الصفحة:**
```
http://your-domain.com/SMPT/index
```

---

## 📚 التوثيق

لدينا 6 ملفات توثيق شاملة لتغطية جميع الاحتياجات:

| الملف | الوصف | الجمهور المستهدف |
|------|-------|-----------------|
| **[SMTP_INDEX.md](SMTP_INDEX.md)** | 📑 الفهرس الرئيسي | الجميع |
| **[SMTP_QUICK_START.md](SMTP_QUICK_START.md)** | ⚡ البدء السريع | مستخدمون جدد |
| **[SMTP_README.md](SMTP_README.md)** | 📖 دليل المستخدم | المستخدمون |
| **[SMTP_INSTALLATION.md](SMTP_INSTALLATION.md)** | 🔧 دليل التثبيت | المثبتون |
| **[SMTP_DOCUMENTATION.md](SMTP_DOCUMENTATION.md)** | 💻 التوثيق الفني | المطورون |
| **[SMTP_SUMMARY.md](SMTP_SUMMARY.md)** | 📊 الخلاصة الشاملة | الإدارة |

### بالإضافة إلى:
- **[smtp_settings_table.sql](smtp_settings_table.sql)** - SQL لإنشاء الجدول يدوياً

**👉 ابدأ من [SMTP_INDEX.md](SMTP_INDEX.md) لاختيار الملف المناسب لك**

---

## 💡 الاستخدام

### للمستخدمين

1. **افتح الصفحة**
   ```
   الإعدادات → إعدادات SMTP
   ```

2. **فعّل SMTP**
   - ✅ حدد: "أرسل البريد بإستعمال حساب SMTP"

3. **أدخل البيانات**
   - 📝 اسم المرسل
   - ✉️ البريد الإلكتروني
   - 🔑 كلمة المرور (App Password لـ Gmail)

4. **احفظ واختبر**
   - 💾 اضغط "حفظ"
   - ✈️ اضغط "إرسال بريد تجريبي"

### 🔑 إعدادات Gmail (الأكثر شيوعاً)

```
خادم SMTP: smtp.gmail.com
المنفذ: 587
التشفير: TLS
كلمة المرور: استخدم App Password
```

**📌 كيفية الحصول على App Password:**
1. https://myaccount.google.com/security
2. فعّل "التحقق بخطوتين"
3. أنشئ "App Password"
4. استخدم الكود المُنشأ

---

## ⭐ المميزات

### 🔐 الأمان
- تشفير تلقائي لكلمات المرور
- حماية CSRF
- التحقق من صحة المدخلات
- ربط بمعرف المستخدم

### 👥 التخصيص
- كل مستخدم له إعداداته
- حفظ متعدد للإعدادات
- إعادة تعيين سريعة

### 🌐 الدعم
- Gmail ✅
- Outlook ✅
- Office 365 ✅
- أي خادم SMTP ✅

### 🎨 الواجهة
- تصميم عربي احترافي
- رسائل توضيحية
- نبذة تعليمية شاملة
- Modals للمساعدة

### ⚡ الأداء
- استعلامات محسّنة
- تخزين آمن
- استجابة سريعة

---

## 📁 هيكل الملفات

```
fawtmaintest/
├── app/Models/
│   └── SmtpSetting.php                    # Model
│
├── database/migrations/
│   └── 2025_01_06_000001_create_smtp_settings_table.php
│
├── Modules/Sitting/
│   ├── Http/Controllers/
│   │   └── SMPTController.php             # Controller
│   │
│   ├── resources/views/smpt/
│   │   └── index.blade.php                # View
│   │
│   ├── routes/
│   │   └── web.php                        # Routes (محدّث)
│   │
│   └── [توثيق]
│       ├── SMTP_INDEX.md                  # الفهرس
│       ├── SMTP_QUICK_START.md            # البدء السريع
│       ├── SMTP_README.md                 # دليل المستخدم
│       ├── SMTP_INSTALLATION.md           # دليل التثبيت
│       ├── SMTP_DOCUMENTATION.md          # التوثيق الفني
│       ├── SMTP_SUMMARY.md                # الخلاصة
│       ├── smtp_settings_table.sql        # SQL
│       └── README.md                      # هذا الملف
```

---

## 🔧 API Routes

| Method | Route | الوظيفة |
|--------|-------|---------|
| GET | `/SMPT/index` | عرض صفحة الإعدادات |
| POST | `/SMPT/store` | حفظ/تحديث الإعدادات |
| DELETE | `/SMPT/reset` | حذف الإعدادات |
| GET | `/SMPT/sendTestMail` | إرسال بريد تجريبي |

---

## 📊 قاعدة البيانات

### جدول smtp_settings

| العمود | النوع | الوصف |
|--------|------|-------|
| id | BIGINT | المعرف الفريد |
| user_id | BIGINT | معرف المستخدم (FK) |
| use_smtp | BOOLEAN | تفعيل SMTP |
| sender_name | VARCHAR(255) | اسم المرسل |
| email | VARCHAR(255) | البريد الإلكتروني |
| password | TEXT | كلمة المرور (مشفرة) |
| smtp_username | VARCHAR(255) | اسم مستخدم SMTP |
| smtp_host | VARCHAR(255) | خادم SMTP |
| smtp_port | INT | المنفذ (587) |
| smtp_encryption | VARCHAR(255) | نوع التشفير (tls) |
| ssl_required | BOOLEAN | متطلب SSL |
| is_active | BOOLEAN | حالة التفعيل |
| created_at | TIMESTAMP | تاريخ الإنشاء |
| updated_at | TIMESTAMP | تاريخ التحديث |

**العلاقات:**
- `user_id` → `users(id)` ON DELETE CASCADE

---

## 🚨 استكشاف الأخطاء

### فشل الاتصال
```
✅ تحقق من الخادم: smtp.gmail.com
✅ تحقق من المنفذ: 587
✅ تحقق من التشفير: TLS
```

### فشل المصادقة
```
✅ Gmail: استخدم App Password (ليس كلمة المرور العادية)
✅ Outlook: كلمة مرور حسابك الأساسية
✅ تحقق من صحة البريد الإلكتروني
```

### الجدول غير موجود
```bash
php artisan migrate
# أو استورد: smtp_settings_table.sql
```

**📚 للمزيد: راجع [SMTP_INSTALLATION.md](SMTP_INSTALLATION.md)**

---

## 📝 أمثلة الاستخدام

### مثال 1: حفظ إعدادات Gmail
```php
// يتم تلقائياً عبر النموذج
POST /SMPT/store
{
    "use_smtp": 1,
    "sender_name": "أحمد محمد",
    "email": "ahmed@gmail.com",
    "password": "xxxx xxxx xxxx xxxx",  // App Password
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_encryption": "tls"
}
```

### مثال 2: جلب الإعدادات في الكود
```php
use App\Models\SmtpSetting;

// جلب إعدادات المستخدم الحالي
$settings = SmtpSetting::getUserSettings(Auth::id());

if ($settings && $settings->use_smtp) {
    // استخدم الإعدادات
    $email = $settings->email;
    $password = $settings->password; // يُفك تشفيرها تلقائياً
}
```

---

## 🎯 خارطة الطريق

### الإصدار الحالي (v1.0.0)
- [x] ✅ حفظ إعدادات SMTP
- [x] ✅ تشفير كلمات المرور
- [x] ✅ دعم Gmail و Outlook
- [x] ✅ إرسال بريد تجريبي
- [x] ✅ واجهة عربية
- [x] ✅ توثيق شامل

### الإصدارات المستقبلية (اختياري)
- [ ] دعم مزودين إضافيين
- [ ] سجل إرسال البريد
- [ ] قوالب البريد
- [ ] إرسال جماعي
- [ ] جدولة البريد

---

## 👥 المساهمة

هذا المشروع جزء من FAWT Management System

### تم التطوير بواسطة:
- 🤖 GitHub Copilot
- 📅 6 نوفمبر 2025

---

## 📄 الترخيص

هذا الكود جزء من نظام FAWT Management System

---

## 🙏 شكر وتقدير

- Laravel Framework
- Bootstrap
- Font Awesome Icons

---

## 📞 الدعم

### للأسئلة والمساعدة:
1. 📖 راجع [SMTP_INDEX.md](SMTP_INDEX.md)
2. 🔍 ابحث في التوثيق المناسب
3. 🐛 تحقق من قسم استكشاف الأخطاء

---

## ✅ الخلاصة

### ✨ تم إنشاء نظام SMTP متكامل يتضمن:

- ✅ **Backend كامل**: Migration, Model, Controller
- ✅ **Frontend محسّن**: View, Forms, Validation
- ✅ **أمان شامل**: Encryption, CSRF, Authentication
- ✅ **توثيق كامل**: 6 ملفات توثيق شاملة
- ✅ **دعم متعدد**: Gmail, Outlook, Custom
- ✅ **واجهة عربية**: RTL Support

### 🚀 جاهز للاستخدام الآن!

```bash
php artisan migrate
```

ثم افتح: `http://your-domain.com/SMPT/index`

---

<div align="center">

**🎉 استمتع بنظام SMTP الجديد! 🎉**

**صُنع بـ 💙 بواسطة GitHub Copilot**

[⬆️ العودة للأعلى](#-نظام-إعدادات-smtp---fawt-management-system)

</div>
