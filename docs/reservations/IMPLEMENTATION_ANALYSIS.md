# تحليل تنفيذ وحدة الحجوزات — Reservations

> وثيقة ما قبل التنفيذ. تُسجّل ما وجدتُه في النظام قبل كتابة سطر واحد، وما
> قرّرتُ إعادة استعماله، وما قرّرتُ إنشاءه، ولماذا.
>
> القاعدة الحاكمة: **بنية النظام القائمة تسبق الخطة المقترحة**. حيثما اختلف
> اصطلاح النظام عن المقترح في طلب العمل، اتُّبع اصطلاح النظام وسُجّل السبب هنا.

---

## 1. البيئة والبنية القائمة

| البند | القيمة |
|---|---|
| PHP | 8.2.12 |
| Laravel | ^11.0 (مع بقايا اصطلاح 10: `app/Http/Kernel.php` حيّ للجدولة القديمة) |
| قاعدة البيانات | MySQL/MariaDB (الاختبارات: SQLite in-memory عبر `phpunit.xml`) |
| البنية | Modular monolith — `nwidart/laravel-modules`، 20 وحدة تحت `Modules/` |
| الصلاحيات | Spatie + كتالوج مركزي `app/Constants/permissions/` |
| اللغة | العربية أساسية RTL — `mcamara/laravel-localization` |

**لا Repositories في المشروع.** المنطق المشترك في `app/Services/` والوحدات
تستدعيه. فلم أُنشئ طبقة Repository كما اقترح طلب العمل — إدخالُ طبقةٍ لا
نظير لها في تسعة عشر وحدةً أخرى يخالف القاعدة 65.

---

## 2. الحالة الراهنة لوحدة الحجوزات

الوحدة **موجودة ومفعَّلة** (`modules_statuses.json: "Reservations": true`) لكنها
هيكل CRUD لا أكثر:

| الموجود | التقييم |
|---|---|
| `Modules/Reservations/` | مزوّدات + متحكّم واحد + 6 قوالب (3210 سطر) |
| جدول `bookings` | 8 أعمدة: منتج، موظف، تاريخ، وقت بدء/نهاية، عميل، حالة ENUM |
| جدول `reservations` | **ليس الحجوزات** — هو جدول *الإعدادات* (`duration`, `payment_online`)، يقرؤه `App\Models\BookingSetting` |
| `App\Http\Controllers\Reservations\ReservationsController` | **متحكّم يتيم**: لا مسار يشير إليه، وقوالبه (`reservations.index`) غير موجودة |
| الصلاحيات | **صفر**. لا صلاحية واحدة معرَّفة — والقائمة الجانبية تعترف بذلك صراحةً في تعليقها |
| المحاسبة | **صفر**. لا مبلغ ولا عملة ولا ضريبة ولا قيد |
| الفرع | **صفر**. لا `branch_id` — كل الفروع ترى كل الحجوزات |

### ما ينقص جوهرياً

لا مال، لا فرع، لا صلاحية، لا ترقيم، لا سجلّ، لا منع تعارض، لا أوقات عمل،
لا سير عمل، لا إشعارات، لا تقارير.

---

## 3. ما سيُعاد استعماله (لا يُنشأ)

| الحاجة | الموجود المُعاد استعماله | الموضع |
|---|---|---|
| ترقيم المستند | `next_document_number('reservation')` — **قسم `reservation` معرَّف أصلاً** ببادئة `RSV` | `app/Support/DocumentSections.php:50` |
| الحسابات المحاسبية | `AccountingSettings::id($key)` من شاشة «الحسابات المحاسبية» | `app/Support/AccountingSettings.php` |
| الفترة المحاسبية | `AccountingPeriodGuard::blockReason()` + سِمة `BelongsToAccountingPeriod` | `app/Support/`, `app/Models/Concerns/` |
| القيود | `JournalEntryService::createJournalEntry()` | `app/Services/Accounts/` |
| العملة | سِمة `HasDocumentCurrency` + `CurrencySettings` | `app/Models/Concerns/` |
| الضريبة | `TaxCalculator::compute()` (يحترم included/excluded) + جدول `taxes` | `app/Support/TaxCalculator.php` |
| الفرع | سِمة `BelongsToBranch` + `StampsOwnerContext` + `AffiliationTree` | `app/Traits/`, `app/Models/Concerns/` |
| نطاق الرؤية | `App\Support\DataScope` (all/region/branch/team/own) | `app/Support/DataScope.php` |
| السجلّات الثلاثة | `OperationLog::record()` / `::failure()` — يكتب في `logs` و`event_logs` و`error_logs` معاً | `app/Support/OperationLog.php` |
| طرق الدفع | جدول `payment_methods` + `App\Models\PaymentMethod` | — |
| الخدمات المُباعة | `products` بنوع `ProductType::SERVICES` | `app/Enums/ProductType.php` |
| العملاء | `App\Models\Client` (الاسم عبر `display_name` لا `name`) | — |
| الفاتورة | `App\Models\Invoice` + `InvoiceItem` | — |
| سند القبض | `App\Models\Receipt` | — |
| الكرون | **`reservations:reminders` مسجَّل أصلاً** في `CronRegistry` و`CronJobsSeeder` وينتظر الأمر | `app/Services/Cron/CronRegistry.php:379` |
| التفعيل/التعطيل | `ApplicationRegistry::groups()` + وسيط `EnsureModuleEnabled` | `app/Services/Settings/` |

> **أهمّ اكتشاف:** ثلاثة عقودٍ كانت مُعدّةً سلفاً وتنتظر مَن يفي بها —
> قسم الترقيم `reservation`، ووظيفة الكرون `reservations:reminders`،
> وحالتا `ActionType::RESERVATION_CONFIRMED/CANCELLED`. لم يُنشأ لها بديل.

---

## 4. القرارات المعمارية والانحرافات عن الخطة المقترحة

### 4.1 تصحيح تسمية الجدولين (لا إنشاء جدول ثالث)

الوضع القائم مقلوب: `bookings` هو جدول الحجوزات، و`reservations` هو جدول
الإعدادات. أي جدول جديد باسم `reservations` مستحيل، والبقاء على المقلوب
يعني وحدةً اسمها الحجوزات وجدولُها اسمه شيء آخر.

**تحقّقتُ من العدد قبل القرار: كلا الجدولين صفر صفوف.** فالتسمية تُصحَّح
بأمانٍ تامّ:

```
reservations (إعدادات) ──rename──► reservation_settings
bookings     (حجوزات)  ──rename──► reservations
```

والتوافق الخلفي محفوظ: `App\Models\Booking` و`App\Models\BookingSetting`
يبقيان ويُوجَّهان بـ`$table` إلى الاسمين الجديدين، فلا ينكسر
`Client::bookings()` ولا تبويب الحجوزات في شاشة العميل.

### 4.2 الحالة: PHP Enum لا جدول حالات

طلب العمل يقول «لا تستخدم ENUM إذا كان النظام يعتمد جداول ديناميكية».
**النظام لا يعتمدها**: `app/Enums/` فيه ~40 تعداداً وهو الاصطلاح المعتمد
(`OrderStatus`, `PaymentStatus`, `BatchStatus`…). فالحالات تُعرَّف في
`ReservationStatus` enum ومعها **جدولُ الانتقالات المسموحة** داخل التعداد
نفسه.

لكن عمود قاعدة البيانات يصير `string` لا `ENUM` — إضافةُ حالةٍ يجب ألّا
تتطلّب `ALTER TABLE`.

### 4.3 الحسابات المحاسبية: مفاتيح جديدة في الشاشة المركزية

لا إعداد حسابات مستقلّ للوحدة. أُضيفت ستّة مفاتيح إلى
`AccountingSettingsSeeder::definitionsUnlinked()` فتظهر في شاشة «الحسابات
المحاسبية» **بلا ربط**، ويربطها المحاسب بيده:

`reservation_revenue` · `reservation_deposits` · `reservation_discount`
`reservation_cancellation_fee` · `reservation_refund` · `reservation_rounding`

وتُقرأ بـ`AccountingSettings::id()` **بلا احتياط برمجي** — لا
`SystemAccountIds` fallback: القرار للمحاسب، وغيابُ الربط يوقف العملية
برسالة واضحة لا يخمّن معها النظام حساباً.

### 4.4 الإعدادات: جدول أعمدة لا حقيبة JSON

اصطلاح النظام لإعدادات الوحدة هو صفٌّ واحد بأعمدة صريحة
(`ClientModuleSetting`, `SalesInvoiceSetting`, `PurchaseInvoiceSetting`,
`PosGeneralSetting`). فـ`reservation_settings` تتوسّع بالأعمدة على النمط
نفسه، ومعها `ReservationSetting::current()` singleton كـ`ClientModuleSetting::current()`.

### 4.5 الخدمة: امتدادٌ للمنتج لا بديلٌ عنه

`reservation_services` **لا تكرّر** المنتجات: تحمل `product_id` (اختياري)
وتضيف ما لا يعرفه المنتج — المدّة، وقت التحضير، وقت التنظيف، الحدّ اليومي،
والموظفون المؤهَّلون. السعر والضريبة يُقرآن من المنتج المربوط ما لم تُعرَّف
قيمة خاصّة بالخدمة.

### 4.6 المدفوعات: `Receipt` قبل الفاتورة، الفاتورة بعدها

- **دفعة مقدّمة بلا فاتورة** ⇒ سند قبض (`Receipt`) + قيد:
  `مدين: الخزينة / دائن: دفعات حجوزات مقدَّمة (التزام)`
- **فاتورة** ⇒ `Invoice` + `InvoiceItem` بالمسار القائم، ويُقفَل الالتزام
  المقدَّم عند إصدارها.

---

## 5. تكامل الصلاحيات

كتالوج جديد `ReservationsPermissions extends BasePermissionCatalog` يُسجَّل
في `PermissionCatalog::CATALOGS`، ثم `php artisan permissions:sync-catalog`.

> **درسٌ مطبَّق:** `permissions:sync` **لا يربط الصلاحية بأي دور**، فالشاشة
> تبقى غير مرئية للجميع بعد المزامنة. لذلك يرافق الكتالوجَ سيدرٌ يربط
> الصلاحيات بدور المدير.

محورا الحراسة معاً:

- **محور العملية** — `permission:reservations.create` على المسار.
- **محور البيانات** — `DataScope` على الاستعلام *وعلى القوائم المنسدلة معاً*.

---

## 6. المخاطر والتعارضات المرصودة

| # | الخطر | المعالجة |
|---|---|---|
| 1 | تسمية الجدولين المقلوبة | إعادة تسمية آمنة (0 صفوف) + نماذج توافق خلفي |
| 2 | متحكّم يتيم `App\Http\Controllers\Reservations\` بقوالب معدومة | يُترك ولا يُوسَّع؛ مسجَّل هنا كدَينٍ فنّي |
| 3 | `EnsureModuleEnabled` يحتاج بادئة مسار في `ApplicationRegistry` | تُسجَّل بادئة `Reservations` |
| 4 | تعارض الحجز تحت التزامن (Double Booking) | `lockForUpdate` داخل `DB::transaction` + فحص التداخل |
| 5 | فترة محاسبية مقفلة | `AccountingPeriodGuard` قبل كل عملية مالية — لا قبل الحجز نفسه |
| 6 | `Client->name` فارغ دائماً | يُستعمل `display_name` |
| 7 | تحميل jQuery مكرّراً يُعطّل select2/DataTables | القوالب لا تعيد تحميل jQuery فوق `master` |
| 8 | أسطر الملفات LF | كل كتابة بأسطر LF |

---

## 7. خطة التنفيذ

1. **الهجرات** — إعادة التسمية، توسعة `reservations`، الجداول الجديدة، توسعة `reservation_settings`.
2. **التعداد والنماذج** — `ReservationStatus` + النماذج بالسِّمات القائمة.
3. **الخدمات** — التوفّر، الانتقالات، التسعير، المحاسبة، الفوترة.
4. **الصلاحيات** — الكتالوج + السيدر + ربط الأدوار.
5. **المتحكّمات والمسارات** — CRUD + سير العمل + الدفع + الخدمات + أوقات العمل + الإعدادات + اللوحة + التقارير.
6. **القوالب** — على تصميم النظام القائم، RTL، Select2، DataTables.
7. **الأحداث والإشعارات والتذكيرات** — `reservations:reminders`.
8. **الاختبارات** — السيناريوهات الواقعية.
9. **التوثيق** — دليل المستخدم ودليل المطوّر والتقرير النهائي.
