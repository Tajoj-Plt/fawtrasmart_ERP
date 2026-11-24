<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# مشروع هوية - Hawiya Project

نظام إدارة شامل للدورات التدريبية والاستشارات مبني بـ Laravel.

## 📋 جدول المحتويات

- [متطلبات النظام](#متطلبات-النظام)
- [تثبيت المشروع](#تثبيت-المشروع)
- [معالج التثبيت وإعادة التثبيت](#-معالج-التثبيت-installer)
- [الاشتراك والربط بلوحة فوترة](#-الاشتراك-والربط-بلوحة-فوترة)
- [المهام المجدولة](#️-المهام-المجدولة)
- [تشغيل المشروع](#تشغيل-المشروع)
- [أوامر التنظيف والصيانة](#أوامر-التنظيف-والصيانة)
- [أوامر مفيدة للتطوير](#أوامر-مفيدة-للتطوير)
- [أوامر سريعة](#أوامر-سريعة)
- [بيانات الدخول الافتراضية](#بيانات-الدخول-الافتراضية)
- [استكشاف الأخطاء](#استكشاف-الأخطاء)

## 🔧 متطلبات النظام

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL >= 8.0
- Git

## 🚀 تثبيت المشروع

### 1. استنساخ المشروع

```bash
git clone https://github.com/your-username/Hawiya.git
cd Hawiya
```

### 2. تثبيت التبعيات

**تثبيت تبعيات PHP:**
```bash
composer install
```

**تثبيت تبعيات JavaScript:**
```bash
npm install
```

### 3. إعداد البيئة

**نسخ ملف البيئة:**
```bash
copy .env.example .env
```

**توليد مفتاح التطبيق:**
```bash
php artisan key:generate
```

### 4. إعداد قاعدة البيانات

قم بتحديث ملف `.env` بمعلومات قاعدة البيانات:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hawiya
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. تشغيل المايجريشن والسيدرز

**تشغيل المايجريشن:**
```bash
php artisan migrate
```

**تشغيل السيدرز (البيانات الأولية):**
```bash
php artisan db:seed
```

**تشغيل المايجريشن والسيدرز معاً:**
```bash
php artisan migrate:fresh --seed
```

### 6. إنشاء رابط التخزين
```bash
php artisan storage:link
```

### 7. بناء الأصول
```bash
npm run dev
```

```bash
npm run build
```

## 🧭 معالج التثبيت (Installer)

النظام يُثبَّت من متصفّح لا من الطرفية: افتح `/install` على تركيبٍ جديد
فيقودك المعالج خطوةً خطوة — المتطلبات، تفعيل الاشتراك، قاعدة البيانات،
الإعدادات الأساسية، حساب المدير، ثم التنفيذ الحيّ.

**التنفيذ نبضاتٌ قصيرة لا طلبٌ واحد**: الهجرات تُنفَّذ زُمَراً بميزانية ثلاث
ثوانٍ للنبضة، فلا يصطدم التثبيت بمهلة الخادم مهما طال (‎+٥٣٠‎ مهمة في نحو
٧٠ نداءً). وعند أي خطأ يتوقّف ويعرض نصّه، ويُستأنف من المهمة المتعثّرة
نفسها لا من البداية.

### إعادة التثبيت من الصفر

```bash
# يُسقط كل جداول القاعدة ويحذف علامة التثبيت وحالته، ثم يفتح /install
php artisan install:reset --fresh

# بلا سؤال تأكيد (للسكربتات)
php artisan install:reset --fresh --force
```

### إعادة فتح المعالج بلا مسح البيانات

```bash
php artisan install:reset
```

> **لماذا لا يكفي حذف `storage/app/installed`؟** لأن العلامة ليست الحارس
> الوحيد: إن وجد النظامُ في القاعدة مستخدمين وشجرةَ حسابات اعتبرها تركيباً
> قائماً وأعاد كتابة العلامة في أوّل طلب. فمن يحذف الملف ويعيد التحميل يرى
> الرسالة نفسها. استعمل `install:reset --fresh` بدلاً من ذلك.

### فحص المتطلبات قبل التثبيت

```bash
php artisan installer:check
```

### تثبيت من الطرفية بلا متصفّح

```bash
php artisan installer:install \
    --admin-name="مدير النظام" \
    --admin-email=admin@example.com \
    --admin-password='كلمة-مرور-قوية' \
    --company="اسم المنشأة" \
    --language=ar --currency=SAR --timezone=Asia/Riyadh --tax=15
```

## 🔗 الاشتراك والربط بلوحة فوترة

الاشتراك **يُدار في لوحة تحكّم فوترة وحدها**: لا تسجيل ولا تجديد ولا ترقية
من داخل هذا النظام. وما يفعله النظام هو المزامنة في الاتجاهين — يسحب الباقة
وصلاحياتها وحدود الاستخدام والفواتير، ويرفع استهلاكه المحلّي.

```bash
# مزامنة فورية (تتجاهل رمز المزامنة وفترة التراجع) — تطبع الباقة والحدود
php artisan fawtra:sync --force

# مزامنة عادية: تُنفَّذ تلقائياً كل ١٥ دقيقة عبر cron:run-due
php artisan fawtra:sync

# عرض الحالة المحفوظة بلا أي اتصال بالشبكة
php artisan fawtra:sync --status
```

**المهمة الدورية `fawtra_subscription_sync` محميّة**: لا تظهر في شاشة المهام
المجدولة، ولا تُعدَّل ولا تُوقَف ولا تُحذف — لا من الشاشة ولا من قاعدة
البيانات عبر النموذج.

### إعدادات `.env` الخاصّة بفوترة

```env
FAWTRA_BASE_URL=https://dashboard.fawtrasmart.com   # عنوان اللوحة بلا بادئة /api/integration/v1
FAWTRA_CLIENT_ID=fwck_...                           # من: مفاتيح التكامل ← إصدار مفتاح
FAWTRA_SECRET=fwsk_...                              # يُعرض مرّة واحدة — ولا يُخزَّن في قاعدة البيانات
FAWTRA_DOMAIN=erp.example.com                       # يجب أن يطابق المسجَّل عند فوترة (داخلٌ في التوقيع)

FAWTRA_ENFORCE_SUBSCRIPTION=true   # لا دخول لأي شاشة بلا اشتراك نشط
FAWTRA_USAGE_REPORT=true           # رفع الاستهلاك إلى اللوحة في الدورة نفسها
FAWTRA_SYNC_INTERVAL=900           # ثوانٍ بين مزامنتين
```

> **تحذير:** `FAWTRA_ENFORCE_SUBSCRIPTION=false` تُطفئ قفل الاشتراك — للتطوير
> المحلّي وحده. وإطفاؤها في الإنتاج يعني نظاماً يعمل بلا اشتراك يعرفه أحد.
>
> و`FAWTRA_USAGE_REPORT=false` تمنع بيئةَ اختبارٍ تشترك مع الإنتاج في المفتاح
> نفسه من إنقاص رصيد عميلٍ حقيقي.

### حين يُقفل النظام على المستخدم

يظهر جدارٌ عند كل مسار (رمز `402`) بأحد ثلاثة أسباب: لا لقطة اشتراك في
قاعدة البيانات، أو الاشتراك منتهٍ، أو موقوف. والمخارج تبقى مفتوحة: شاشتا
الاشتراك، والدخول والخروج، والمعالج. للتشخيص:

```bash
php artisan fawtra:sync --status       # الحالة المحفوظة: الباقة، الانتهاء، آخر خطأ
php artisan fawtra:sync --force        # محاولة حيّة تطبع السبب الحقيقي للإخفاق
```

## ⏱️ المهام المجدولة

```bash
# ما هو مستحقّ الآن دون تنفيذه
php artisan cron:run-due --dry-run

# تنفيذ مهمة بعينها إجبارياً
php artisan cron:run-due --job=fawtra_subscription_sync
```

على الخادم يكفي سطرٌ واحد في cron:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## ▶️ تشغيل المشروع

### تشغيل الخادم المحلي

```bash
php artisan serve
```

سيكون المشروع متاحاً على: `http://localhost:8000`

### تشغيل Vite للتطوير

```bash
npm run dev
```

### تشغيل الطوابير (Queues)

```bash
php artisan queue:work
```

## 🧹 أوامر التنظيف والصيانة

### تنظيف الكاش

**تنظيف كاش التطبيق:**
```bash
php artisan cache:clear
```

**تنظيف كاش التكوين:**
```bash
php artisan config:clear
```

**تنظيف كاش الروتات:**
```bash
php artisan route:clear
```

**تنظيف كاش العروض:**
```bash
php artisan view:clear
```

**تنظيف جميع أنواع الكاش:**
```bash
php artisan optimize:clear
```

### إعادة بناء الكاش

**إعادة بناء كاش التكوين:**
```bash
php artisan config:cache
```

**إعادة بناء كاش الروتات:**
```bash
php artisan route:cache
```

**إعادة بناء كاش العروض:**
```bash
php artisan view:cache
```

**تحسين التطبيق للإنتاج:**
```bash
php artisan optimize
```

### تنظيف قاعدة البيانات

**إعادة تعيين قاعدة البيانات مع السيدرز:**
```bash
php artisan migrate:fresh --seed
```

**إعادة تشغيل المايجريشن فقط:**
```bash
php artisan migrate:refresh
```

**التراجع عن آخر مايجريشن:**
```bash
php artisan migrate:rollback
```

### تنظيف الجلسات والملفات المؤقتة

**تنظيف الجلسات المنتهية الصلاحية:**
```bash
php artisan session:gc
```

**تنظيف ملفات الطوابير الفاشلة:**
```bash
php artisan queue:flush
```

**تنظيف ملفات السجلات القديمة:**
```bash
php artisan log:clear
```

## 🛠️ أوامر مفيدة للتطوير

### إنشاء مكونات جديدة

**إنشاء كونترولر:**
```bash
php artisan make:controller ExampleController
```

**إنشاء موديل مع مايجريشن:**
```bash
php artisan make:model Example -m
```

**إنشاء سيدر:**
```bash
php artisan make:seeder ExampleSeeder
```

**إنشاء مايجريشن:**
```bash
php artisan make:migration create_examples_table
```

### فحص النظام

**فحص حالة التطبيق:**
```bash
php artisan about
```

**عرض الروتات:**
```bash
php artisan route:list
```

**فحص التبعيات:**
```bash
composer show
```

**فحص الأمان:**
```bash
composer audit
```

## ⚡ أوامر سريعة

### إعداد سريع للمشروع

**إعداد كامل للمشروع (أمر واحد):**
```bash
composer install && npm install && copy .env.example .env && php artisan key:generate && php artisan migrate:fresh --seed && php artisan storage:link && npm run build
```

### تنظيف شامل

**تنظيف شامل للكاش والملفات المؤقتة:**
```bash
php artisan optimize:clear && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

### إعادة تشغيل التطوير

**إعادة تشغيل سريعة للتطوير:**
```bash
php artisan migrate:fresh --seed && php artisan optimize:clear
```

## 🔐 بيانات الدخول الافتراضية

بعد تشغيل السيدرز، يمكنك الدخول باستخدام:

### المدير العام
- **البريد الإلكتروني:** `admin@hawiya.com`
- **كلمة المرور:** `password`

### مدير النظام
- **البريد الإلكتروني:** `manager@hawiya.com`
- **كلمة المرور:** `password`

## 🔧 استكشاف الأخطاء وإصلاحها

### مشاكل شائعة وحلولها

#### خطأ في الاتصال بقاعدة البيانات

1. تأكد من صحة إعدادات قاعدة البيانات في ملف `.env`
2. تأكد من تشغيل خادم MySQL
3. تأكد من وجود قاعدة البيانات المحددة

**إنشاء قاعدة البيانات:**
```bash
mysql -u root -p -e "CREATE DATABASE hawiya;"
```

#### خطأ في الصلاحيات

**إعطاء صلاحيات للمجلدات:**
```bash
chmod -R 755 storage bootstrap/cache
```

#### مشكلة في الكاش

**تنظيف شامل للكاش:**
```bash
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

#### مشكلة في التبعيات

**إعادة تثبيت تبعيات PHP:**
```bash
rm -rf vendor
composer install
```

**إعادة تثبيت تبعيات JavaScript:**
```bash
rm -rf node_modules
npm install
```

## 📁 هيكل المشروع
 php artisan db:seed --class=PermissionSeeder
 php artisan db:seed --class=RolePermissionSeeder

## 🛒 أوامر المتجر الإلكتروني

### إنشاء جداول المتجر

**إنشاء مايجريشن المتجر:**
```bash
php artisan migrate --path=database/migrations/2026_01_03_000001_create_online_store_tables.php
```

**تشغيل مايجريشن المتجر مع إعادة تعيين الجداول:**
```bash
php artisan migrate --path=database/migrations/2024_01_01_000022_create_email_templates_table.php
php artisan migrate --path=database/migrations/2024_01_01_000023_create_email_logs_table.php
```

### تشغيل بيانات المتجر الأساسية

**تشغيل جميع سيدرز المتجر:**
```bash
php artisan db:seed --class=EmailTemplateSeeder
php artisan db:seed --class=OnlineStoreSettingsSeeder
php artisan db:seed --class=StorePagesSeeder
php artisan db:seed --class=ShippingMethodsSeeder
php artisan db:seed --class=StoreCouponsSeeder
php artisan db:seed --class=StoreBannerSeeder
```

**تشغيل سيدرز المتجر بأمر واحد:**
```bash
php artisan db:seed --class=BrandsSeeder && php artisan db:seed --class=OnlineStoreSettingsSeeder && php artisan db:seed --class=StorePagesSeeder && php artisan db:seed --class=ShippingMethodsSeeder && php artisan db:seed --class=StoreCouponsSeeder
```

### إعداد المتجر الإلكتروني بالكامل

**إعداد شامل للمتجر (مايجريشن + سيدرز):**
```bash
php artisan migrate --path=database/migrations/2026_01_03_000001_create_online_store_tables.php &&
php artisan db:seed --class=BrandsSeeder 
php artisan db:seed --class=OnlineStoreSettingsSeeder 
php artisan db:seed --class=StorePagesSeeder  
php artisan db:seed --class=ShippingMethodsSeeder 
php artisan db:seed --class=StoreCouponsSeeder
```

**إعادة إعداد المتجر من الصفر:**
```bash
php artisan migrate:fresh --seed --path=database/migrations/2026_01_03_000001_create_online_store_tables.php
```

### سيدرز المتجر المتاحة

#### BrandsSeeder - البراندات والعلامات التجارية
```bash
php artisan db:seed --class=BrandsSeeder
```
يحتوي على 20 براند متنوع (هواتف، إلكترونيات، أزياء، عناية شخصية، براندات محلية)

#### OnlineStoreSettingsSeeder - إعدادات المتجر
```bash
php artisan db:seed --class=OnlineStoreSettingsSeeder
```
يحتوي على إعدادات شاملة (عامة، مظهر، دفع، شحن، SEO، إشعارات)

#### StorePagesSeeder - صفحات المتجر
```bash
php artisan db:seed --class=StorePagesSeeder
```
يحتوي على 8 صفحات أساسية (من نحن، شروط الخدمة، سياسة الخصوصية، FAQ، اتصل بنا)

#### ShippingMethodsSeeder - طرق الشحن
```bash
php artisan db:seed --class=ShippingMethodsSeeder
```
يحتوي على 6 طرق شحن متنوعة مع مناطق الشحن المختلفة

#### StoreCouponsSeeder - كوبونات وعروض المتجر
```bash
php artisan db:seed --class=StoreCouponsSeeder
```
يحتوي على 10 كوبونات متنوعة (ترحيب، موسمية، خصم ثابت، شحن مجاني) 


### تنفيذ قواعد بيانات ضخمه 
```bash
cd C:\xampp\mysql\bin
```

```bash
mysql -u root -p fawtra_smart < "C:\Users\MetaCode\Downloads\u591480869_fawtramoshtaq (3).sql"
```