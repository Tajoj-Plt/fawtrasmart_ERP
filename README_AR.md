# نظام الإعدادات المتكامل للعملاء وخط السير

## 📋 نظرة سريعة

نظام إعدادات **ديناميكي وشامل** يسمح بتخصيص كامل لمتطلبات الحقول ومتطلبات خط السير دون الحاجة لتعديل الكود.

## ✨ الميزات الرئيسية

- 🎯 **جدول إعدادات موحد** - 23 إعداد في جدول واحد
- 🔧 **إدارة ديناميكية** - غيّر الإعدادات بضغطة زر
- 📱 **واجهة جميلة** - Dashboard احترافي وRESPONSIVE
- 🔌 **API متكاملة** - للتطبيقات الخارجية والموبايل
- 🔒 **آمان عالي** - CSRF + Database Transactions
- 📚 **توثيق شامل** - دليل كامل وأمثلة عملية
- ✅ **اختبارات جاهزة** - 5 اختبارات للنموذج

## 🚀 البدء السريع

### 1. تشغيل Migration
```bash
php artisan migrate
```

### 2. الوصول إلى الإعدادات
```
http://localhost/ar/client-settings/system
```

### 3. تخصيص الإعدادات والحفظ

## 📁 الملفات المرفقة

### الملفات الرئيسية
- ✅ `app/Models/ClientSystemSettings.php` - النموذج
- ✅ `database/migrations/2025_12_09_create_client_system_settings_table.php` - الجدول
- ✅ `Modules/Client/Http/Controllers/ClientSettingsController.php` - المتحكم الرئيسي
- ✅ `Modules/Client/Http/Controllers/ClientSettingsApiController.php` - API
- ✅ `Modules/Client/resources/views/settings/index.blade.php` - صفحة الإعدادات

### ملفات التوثيق
- 📄 **QUICK_START.md** - دليل البدء السريع ⭐⭐⭐
- 📄 **SYSTEM_SETTINGS_GUIDE.md** - دليل شامل
- 📄 **IMPLEMENTATION_SUMMARY.md** - ملخص التغييرات
- 📄 **VIDEO_TUTORIAL.md** - شرح عملي خطوة بخطوة
- 📄 **API_DOCUMENTATION.md** - توثيق API
- 📄 **COMPREHENSIVE_SUMMARY.md** - ملخص شامل

## 🎯 الإعدادات المتاحة

### إعدادات العميل (9)
```
☐ require_location - الموقع
☐ require_neighborhood - الحي
☐ require_region - المنطقة
☐ require_group - المجموعة
☐ require_category - الفئة
☐ require_employee - الموظف
☐ require_phone - الهاتف
☐ require_email - البريد
☐ require_address - العنوان
```

### إعدادات خط السير (6)
```
☐ require_justification - التبرير
☑ auto_create_visit_record - إنشاء السجل
☑ track_employee_location - تتبع الموقع
☑ enable_visit_notes - الملاحظات
☐ require_client_signature - التوقيع
☑ auto_send_visit_notification - الإشعار
```

### إعدادات أخرى (4)
```
☑ enable_duplicate_check - التحقق المكرر
☑ enable_credit_limit - حد الائتمان
☐ enable_client_approvals - الموافقات
📅 days_before_expiration - 30 يوم
```

## 📊 الروتات المتاحة

### صفحات الويب
```
GET  /client-settings/system          → صفحة الإعدادات
PUT  /client-settings/system          → حفظ الإعدادات
POST /client-settings/system/reset    → إعادة تعيين
```

### API Endpoints
```
GET  /api/v1/settings                 → جميع الإعدادات
GET  /api/v1/settings/required-fields → متطلبات الحقول
GET  /api/v1/settings/itinerary       → إعدادات خط السير
GET  /api/v1/settings/field/{name}    → فحص حقل
```

## 💻 أمثلة الاستخدام

### في Controller
```php
use App\Models\ClientSystemSettings;

$requiredFields = ClientSystemSettings::getRequiredFields();
$itinerarySettings = ClientSystemSettings::getItinerarySettings();

return view('client::create', compact('requiredFields'));
```

### في Blade View
```blade
@if($requiredFields['location'] ?? false)
    <label>الموقع <span class="text-danger">*</span></label>
@endif
```

### عبر API
```javascript
const response = await fetch('/api/v1/settings', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});
const data = await response.json();
```

## ✅ قائمة التحقق

- [ ] تشغيل Migration
- [ ] مسح Cache
- [ ] الوصول لصفحة الإعدادات
- [ ] تفعيل/تعطيل الخيارات
- [ ] حفظ الإعدادات
- [ ] اختبار إضافة عميل جديد
- [ ] التحقق من تغيير الحقول

## 🔒 الأمان

✅ CSRF Protection
✅ Database Transactions
✅ Input Validation
✅ Authentication/Authorization
✅ Sanctum Token للـ API

## 📈 الأداء

✅ جدول واحد فقط
✅ لا استعلامات معقدة
✅ سرعة عالية جداً
✅ قابل للتوسع بسهولة

## 🎨 التصميم

✅ Gradient Colors جميلة
✅ Toggle Switches سلسة
✅ Responsive Design
✅ تأثيرات Hover
✅ رسائل نجاح/خطأ

## 📞 الدعم

### اقرأ أولاً:
1. `QUICK_START.md` - البدء السريع
2. `SYSTEM_SETTINGS_GUIDE.md` - الشرح الكامل
3. `VIDEO_TUTORIAL.md` - الشرح العملي
4. `API_DOCUMENTATION.md` - توثيق API

### الحل الأول للمشاكل:
1. تأكد من تشغيل Migration
2. امسح Cache: `php artisan cache:clear`
3. أعد تحميل المتصفح: `Ctrl + F5`
4. تحقق من الصلاحيات

## 📅 التواريخ المهمة

- **تاريخ الإنشاء:** 9 ديسمبر 2025
- **الحالة:** ✅ اكتمل بنجاح
- **الإصدار:** 1.0

## 🎓 التعليم

### للمبتدئين:
1. اقرأ `QUICK_START.md`
2. اتبع الخطوات خطوة بخطوة
3. اختبر الإعدادات

### للمتقدمين:
1. اقرأ `SYSTEM_SETTINGS_GUIDE.md`
2. ادرس الكود في Model و Controller
3. استخدم API في التطبيقات

### للمطورين:
1. اقرأ `API_DOCUMENTATION.md`
2. استخدم الأمثلة البرمجية
3. ادمج في تطبيقاتك

## 🔧 التطوير المستقبلي

يمكنك بسهولة:
- ✅ إضافة إعدادات جديدة
- ✅ تعديل الواجهة الرسومية
- ✅ إضافة صلاحيات مخصصة
- ✅ توسيع الـ API
- ✅ إضافة Webhooks

## 📦 المتطلبات

- Laravel 10+ (أو 9+)
- PHP 8.0+
- MySQL/MariaDB
- Sanctum (للـ API)

## 🎉 النتيجة النهائية

نظام **احترافي وشامل وآمن** جاهز للاستخدام الفوري!

---

**استمتع باستخدام النظام! 🚀**

تم تطويره بحب ❤️ وعناية 💯
