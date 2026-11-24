# دليل نظام اللغات (Languages System)

توثيق كامل لنظام إدارة لغات النظام داخل وحدة `Modules/Sitting`، بعد مطابقته وظيفياً
للنظام المرجعي في مشروع `hirafiyun/Modules/Dashboard`.

**تاريخ التنفيذ:** 2026-08-11
**الوحدة:** `Modules/Sitting`
**الجدول:** `system_languages`
**الموديل:** `App\Models\SystemLanguage`

---

## 1. الخلاصة التنفيذية

النظام الحالي **لم يُبنَ من الصفر**. كان موجوداً بالفعل (موديل + خدمة + مستودع + نماذج تحقق)
لكنه ناقص الوظائف، فتمّت **توسعته ودمجه** لا تكراره.

### ما كان موجوداً قبل التنفيذ

| المكوّن | الحالة السابقة |
|---|---|
| `App\Models\SystemLanguage` | موجود — جدول `system_languages` بـ 23 عموداً |
| `LanguageService` | موجود — `store` / `update` / `delete` / `toggleActive` فقط |
| `LanguageRepository` | موجود — مع كاش `active_system_languages` و `default_system_language` |
| `StoreLanguageRequest` / `UpdateLanguageRequest` | موجودان — `authorize()` تُعيد `true` دائماً |
| `LanguageSeeder` | موجود — **لكنه لم يُشغَّل قط، والجدول كان فارغاً** |
| المسارات | 3 مسارات فقط: `index` + `storeAccountLanguage` + `toggleActive` |
| الواجهات | صفحة واحدة `sittingAccount/language.blade.php` (عرض + تفعيل + تعيين افتراضية) |
| الصلاحيات | **لا يوجد أي فحص صلاحيات على الإطلاق** |

### الفجوات التي عُولجت

1. `create` / `store` / `edit` / `update` / `destroy` / `show` / `setDefault` — لا مسارات لها.
   الدالة `edit()` كانت **كوداً ميتاً** بلا مسار يستدعيها.
2. لا صفحات إضافة/تعديل/عرض.
3. لا صلاحيات، ولا تحقق من التكرار في `name` أو `locale`.
4. لا حقل `translation_file` (موجود في النظام المرجعي).
5. `storeAccountLanguage` كانت تكرّر منطق اللغة الافتراضية بدل مشاركته.
6. حالة التفعيل في قاعدة البيانات **لم تكن مرتبطة إطلاقاً** بمبدّل اللغة في الواجهة.

---

## 2. قاعدة البيانات

### الجدول: `system_languages`

الجدول الأصلي أُنشئ في هجرتين سابقتين ولم يُمسّ أيٌّ من أعمدته:

- `2026_07_29_181001_create_system_languages_table.php`
- `2026_07_31_185741_add_erp_fields_to_system_languages_table.php`

### الهجرة الجديدة (إضافية بالكامل)

`database/migrations/2026_08_11_100000_add_translation_file_to_system_languages_table.php`

| التغيير | التفصيل |
|---|---|
| إضافة عمود | `translation_file` — `string` nullable، مسار ملف الترجمة المرفوع |
| توسعة عمود | `flag` من `varchar(10)` إلى `varchar(50)` |
| فهارس | `is_active` و `is_default` و `sort_order` |

> **لا حذف، لا إسقاط جدول، لا إعادة تسمية أعمدة، ولا فقدان بيانات.**
> كل خطوة محمية بـ `Schema::hasColumn` و `Schema::hasTable` و فحص وجود الفهرس،
> فالهجرة آمنة للتشغيل المتكرر.

### الأعمدة الكاملة (23 + 1)

```
id, code, locale, name, native_name, flag, translation_file, direction,
is_active, is_default, sort_order, date_format, time_format, number_format,
timezone, calendar_type, thousands_separator, decimal_separator,
font_family, font_size, color, description, created_at, updated_at
```

---

## 3. الملفات المعدَّلة والمضافة

### مضافة

| الملف | الغرض |
|---|---|
| `database/migrations/2026_08_11_100000_add_translation_file_to_system_languages_table.php` | الحقول الناقصة |
| `Modules/Sitting/Http/Requests/LanguageRequestMessages.php` | أسماء الحقول ورسائل التحقق المشتركة |
| `Modules/Sitting/resources/views/languages/index.blade.php` | قائمة اللغات |
| `Modules/Sitting/resources/views/languages/create.blade.php` | إضافة لغة |
| `Modules/Sitting/resources/views/languages/edit.blade.php` | تعديل لغة |
| `Modules/Sitting/resources/views/languages/show.blade.php` | تفاصيل لغة |
| `Modules/Sitting/resources/views/languages/_form.blade.php` | نموذج مشترك بين الإضافة والتعديل |
| `resources/lang/ar/languages.php` | الترجمة العربية |
| `resources/lang/en/languages.php` | الترجمة الإنجليزية |

### معدَّلة

| الملف | التعديل |
|---|---|
| `app/Models/SystemLanguage.php` | `translation_file` في `fillable`، نطاقات `active`/`ordered`/`search`، `isRtl()`، `flagIsImage()`، `default()`، وتفريغ الكاش تلقائياً عند أي كتابة |
| `app/Models/JobRole.php` | 6 صلاحيات جديدة |
| `app/Helpers/PermissionHelper.php` | `languagePermissions()` و `canLanguages()` و `checkLanguagePermission()` |
| `app/Providers/AppServiceProvider.php` | ربط مبدّل اللغة بجدول اللغات |
| `Modules/Sitting/Http/Controllers/LanguageController.php` | إضافة `create`/`show`/`setDefault`/`toggleStatus` + فحص صلاحيات + تسجيل أخطاء |
| `Modules/Sitting/Services/LanguageService.php` | `setDefault()`، رفع ملف الترجمة، منطق الافتراضية، التسجيل |
| `Modules/Sitting/Repositories/LanguageRepository.php` | `paginateFiltered()`، `stats()`، `getActiveCodes()`، `unsetDefaultExcept()` |
| `Modules/Sitting/Http/Requests/StoreLanguageRequest.php` | تحقق كامل + `authorize()` حقيقية |
| `Modules/Sitting/Http/Requests/UpdateLanguageRequest.php` | نفس الشيء مع تجاهل السجل الحالي |
| `Modules/Sitting/routes/web.php` | مجموعة `languages.*` |
| `Modules/Sitting/resources/views/layouts/sidebar.blade.php` | ربط رابط القائمة بالصلاحية |
| `resources/views/layouts/sidebar_client.blade.php` | ربط رابط القائمة بالصلاحية |
| `database/seeders/LanguageSeeder.php` | آمن للتكرار + احترام الافتراضية القائمة |
| `database/seeders/DatabaseSeeder.php` | تسجيل `LanguageSeeder` |

---

## 4. المسارات (Routes)

### المسارات القائمة — لم تُمسّ أسماؤها

أسماؤها مستخدمة في القائمة الجانبية، وتغييرها كان سيكسر الروابط:

```php
GET    SittingAccount/language          → SittingAccount.language
POST   SittingAccount/language          → SittingAccount.language.store
POST   SittingAccount/language/toggle   → SittingAccount.language.toggleActive
```

### المسارات الجديدة

```php
GET    languages                     → languages.index
GET    languages/create              → languages.create
POST   languages                     → languages.store
GET    languages/{id}                → languages.show
GET    languages/{id}/edit           → languages.edit
PUT    languages/{id}                → languages.update
DELETE languages/{id}                → languages.destroy
POST   languages/{id}/toggle-status  → languages.toggle-status
POST   languages/{id}/set-default    → languages.set-default
```

> جميعها داخل `LaravelLocalization::setLocale()` مع الوسائط
> `localeSessionRedirect` و `localizationRedirect` و `localeViewPath` و `check.branch` و `auth`،
> أي أن الروابط الفعلية تحمل بادئة اللغة: `/ar/languages/create`.

---

## 5. الصلاحيات

### الصلاحيات الجديدة في `JobRole::$job_roles`

```
settings_languages_view
settings_languages_create
settings_languages_edit
settings_languages_delete
settings_languages_activate
settings_languages_set_default
```

### آلية الاحتياط — نقطة مهمة

مسارات اللغات **لم تكن محمية بأي صلاحية قبل هذا التنفيذ**. لو فُرضت الصلاحيات
الجديدة وحدها لفقد كل من يدير الإعدادات حالياً وصوله للشاشة فوراً.

لذلك كل عملية تقبل صلاحيتها الدقيقة **أو** صلاحيات الإعدادات العامة الموجودة أصلاً:

| العملية | الصلاحيات المقبولة |
|---|---|
| `view` | `settings_languages_view` أو `settings_general_view` أو `settings_edit_general_settings` |
| `create` | `settings_languages_create` أو `settings_edit_general_settings` |
| `edit` | `settings_languages_edit` أو `settings_edit_general_settings` |
| `delete` | `settings_languages_delete` أو `settings_edit_general_settings` |
| `activate` | `settings_languages_activate` أو `settings_edit_general_settings` |
| `set_default` | `settings_languages_set_default` أو `settings_edit_general_settings` |

### الاستعمال

```php
// في PHP
if (canLanguages('create')) { ... }
checkLanguagePermission('delete');   // يوقف الطلب بـ403

// في Blade
@if (canLanguages('edit'))
    <a href="...">تعديل</a>
@endif
```

> تعتمد على `canAny()` لا `hasPermissionTo()`، لأن الأخيرة **ترمي استثناء**
> `PermissionDoesNotExist` إن لم تكن الصلاحية مسجّلة في قاعدة البيانات.

---

## 6. منطق اللغة الافتراضية

القواعد المطبَّقة في `LanguageService`:

1. **لغة افتراضية واحدة فقط** — تعيين لغة كافتراضية يلغي الصفة عن الباقي داخل معاملة واحدة.
2. **لا يمكن حذف اللغة الافتراضية** — يجب تعيين بديلة أولاً.
3. **لا يمكن تعطيل اللغة الافتراضية** — لا من زر التعطيل ولا من نموذج التعديل.
4. **لا يمكن سحب صفة الافتراضية بالتعديل** دون تعيين بديلة.
5. **لا يمكن تعيين لغة معطّلة كافتراضية** — يجب تفعيلها أولاً.
6. تعيين لغة كافتراضية أثناء الإضافة/التعديل **يفرض تفعيلها**.

### المزامنة مع إعدادات الحساب

عند تغيير اللغة الافتراضية، تُنفَّذ `syncDefaultToAccount()` تلقائياً:

```php
AccountSetting::firstOrCreate(['user_id' => auth()->id()])->update(['language' => $code]);
session(['locale' => $code]);
app()->setLocale($code);
```

وهو **نفس السلوك القديم** لشاشة "حفظ اللغة الافتراضية"، لكن المنطق صار في مكان واحد
بدل تكراره: `storeAccountLanguage()` تستدعي `setDefault()` الآن.

---

## 7. التفعيل / التعطيل وربطه بمبدّل اللغة

### السلوك

اللغة المعطّلة **تختفي من مبدّل اللغة في الواجهة**، لكن **بياناتها تبقى كاملة في الجدول**.

### آلية التنفيذ

مبدّل اللغة في أكثر من 20 قالباً يستعمل `LaravelLocalization::getSupportedLocales()`
التي تقرأ من `config/laravellocalization.php`. بدل تعديل 20 قالباً، تُصفّى القائمة
مركزياً في `AppServiceProvider::boot()`:

```php
$filtered = array_intersect_key($configured, array_flip($activeCodes));
config(['laravellocalization.supportedLocales' => $filtered]);
```

### ثلاث حمايات تمنع كسر التوجيه

1. **لا تُضاف لغات** غير معرّفة أصلاً في ملف الإعداد — التصفية تقاطع لا اتحاد.
2. **تُلغى التصفية بالكامل** إن كانت النتيجة فارغة أو خالية من `config('app.locale')`.
3. **أي خطأ يُبتلع بصمت** (قاعدة بيانات غير مهيّأة، جدول مفقود) فيبقى إعداد الملف كما هو.

### ⚠️ لماذا `boot()` وليس `register()`

المحاولة الأولى وضعت التصفية في `register()` فلم تعمل إطلاقاً وبصمت.
السبب: `LaravelLocalizationServiceProvider` يستدعي `mergeConfigFrom` أثناء `register()`،
و `mergeConfig` في لارافيل 11 **يدمج المصفوفات المتداخلة دمجاً عميقاً**، فيعيد كل
اللغات التي حُذفت. الحل: التصفية في `boot()` بعد اكتمال كل `register()`.

### الحفاظ على السلوك القائم

ربط المبدّل بقاعدة البيانات كان سيُسقط `hi` و `bn` (موجودتان في ملف الإعداد وغائبتان
عن الجدول). لذلك أُضيفتا للـ Seeder **مفعّلتين**، فبقي المبدّل يعرض نفس اللغات الخمس
كما قبل التعديل تماماً — لكنه صار خاضعاً للتحكم من قاعدة البيانات.

---

## 8. التحقق (Validation)

| الحقل | القاعدة |
|---|---|
| `code` | مطلوب، `max:10`، `alpha_dash`، **فريد** |
| `name` | مطلوب، `max:100`، **فريد** |
| `native_name` | اختياري، `max:100` |
| `locale` | اختياري، `max:20`، `alpha_dash`، **فريد** |
| `direction` | مطلوب، `in:ltr,rtl` |
| `flag` | اختياري، `max:50` |
| `sort_order` | اختياري، عدد صحيح، `min:0` |
| `translation_file` | اختياري، ملف، `max:2048`، **JSON حصراً** |
| باقي الحقول الإقليمية والمظهرية | اختيارية بحدود طول مناسبة |

في `UpdateLanguageRequest` تُستعمل `Rule::unique(...)->ignore($id)` فلا يصطدم السجل بنفسه.

### التطبيع قبل التحقق

`prepareForValidation()` تحوّل `code` و `locale` إلى حروف صغيرة وتزيل الفراغات،
وتحوّل `is_active` و `is_default` إلى قيم منطقية. و `LanguageService::normalize()`
تجعل `locale` يتبع `code` تلقائياً إن تُرك فارغاً.

---

## 9. أمان ملف الترجمة

رفع ملف الترجمة **JSON حصراً**. قبول `php` كان سيعني تنفيذ ملف مرفوع على الخادم.

```php
$decoded = json_decode(file_get_contents($file->getRealPath()), true);
if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('ملف الترجمة غير صالح...');
}

// الرمز يُبنى منه مسار: يُقيَّد حتى لا يخرج عن مجلد اللغات
$safeCode = preg_replace('/[^a-z0-9_-]/', '', strtolower($code));

// يُعاد ترميزه من المصفوفة المفكَّكة: ما يُكتب هو ما فُهم لا ما وصل
File::put(base_path("resources/lang/{$safeCode}.json"), json_encode($decoded, ...));
```

ثلاث طبقات: **لا تنفيذ** (يُقرأ نصاً ويُفكَّك JSON)، **لا اجتياز مسار**
(`../` مستحيلة بعد التنقية)، و**لا مرور للمحتوى الخام** (يُعاد ترميزه من المصفوفة).

---

## 10. التسجيل (Logging)

استُعمل نظام التسجيل الموجود في المشروع، ولم يُنشأ نظام جديد.

> ملاحظة: النظام المرجعي يسجّل في `ActivityLog` و `SystemEvent`، لكن جدول
> `activity_logs` **غير موجود في قاعدة بيانات هذا المشروع**، و `SystemEvent`
> لا وجود له. فاستُعمل البديل الفعلي الموجود.

| الوجهة | ماذا يُسجَّل |
|---|---|
| `App\Models\Log` | كل عملية: `type='setting'`، الوصف، المنفِّذ، IP، المسار |
| `App\Services\EventLogService` | حدث لكل عملية بأسماء ثابتة |
| `App\Services\ErrorLogService` | كل استثناء يقع في المتحكّم |

### أسماء الأحداث

```
language_created  language_updated  language_deleted
language_activated  language_deactivated  language_default_changed
```

التسجيل ملفوف بـ `try/catch` — فشل التسجيل لا يُفشل العملية نفسها.

---

## 11. الترجمة

`resources/lang/ar/languages.php` و `resources/lang/en/languages.php`.

اللغات الأخرى (`ur` و `hi` و `bn` و `ger`) تعتمد على `fallback_locale` وهي `en` —
وقد اختُبرت وتعمل.

### ⚠️ قاعدة مهمة عند استعمال `__()`

الدالة `__()` **تُعيد المفتاح نصاً** إن غابت الترجمة. لذلك لا يجوز أبداً:

```blade
{{-- خطأ: ينهار بـ foreach() argument must be of type array|object, string given --}}
@foreach (__('languages.guidelines') as $g)
```

الصحيح:

```blade
@php
    $guidelines = __('languages.guidelines');
    $guidelines = is_array($guidelines) ? $guidelines : [];
@endphp
@if (! empty($guidelines))
    @foreach ($guidelines as $guideline) ... @endforeach
@endif
```

---

## 12. الواجهات

| الملف | المحتوى |
|---|---|
| `index.blade.php` | 4 بطاقات إحصائية، بحث، فلترة بالحالة والاتجاه (متقدّمة قابلة للطيّ)، جدول 9 أعمدة، قائمة إجراءات منسدلة، ترقيم |
| `create.blade.php` | نموذج الإضافة |
| `edit.blade.php` | نموذج التعديل + تنبيه عند اللغة الافتراضية |
| `show.blade.php` | 4 بطاقات: أساسية، إقليمية، مظهر، ملف الترجمة + أزرار الإجراءات |
| `_form.blade.php` | **نموذج مشترك** بين الإضافة والتعديل — لا تكرار |

### التوافق مع تصميم الـ ERP

- `@extends('sitting::master')`
- أصناف Vuexy/Bootstrap القائمة: `card` و `badge badge-light-*` و `feather icon-*` و `fa fa-*`
- `@include('layouts.alerts.success')` و `@include('layouts.alerts.error')` الموجودتان
- `@include('components.custom-pagination')` بدل ترقيم جديد
- متجاوب وRTL بالكامل

### عرض العلم

يدعم الشكلين معاً عبر `flagIsImage()`: اسم ملف (`sa.svg` → صورة من `public/flags/`)
أو رمز Emoji (يُعرض نصاً)، وإن غاب الاثنان يُعرض أول حرفين من الرمز في مربع ملوّن.

---

## 13. الـ Seeder

`LanguageSeeder` آمن للتشغيل المتكرر:

- يطابق على `code` فلا يكرّر السجلات.
- **لا يمسّ** `is_active` ولا `is_default` ولا `sort_order` للسجلات القائمة.
- يحدّث الحقول الوصفية الفارغة فقط (`$existing->name ?: $lang['name']`).
- يحترم اللغة الافتراضية القائمة ولا يفرض العربية من جديد.
- يضمن وجود لغة افتراضية واحدة **على الأقل** وواحدة **فقط** بعد التشغيل.

اللغات المزروعة: `ar` (افتراضية) و `en` و `ur` و `hi` و `bn` — جميعها مفعّلة.

---

## 14. نتائج الاختبار

### منطق الأعمال — 26/26 ✅

الإنشاء، التعديل، الحذف، التفعيل/التعطيل، تعيين الافتراضية، تحويل الرمز لحروف صغيرة،
`locale` التلقائي، منع حذف الافتراضية، منع تعطيلها، منع سحب صفتها، منع تعيين المعطّلة
كافتراضية، لغة افتراضية واحدة فقط، مزامنة `AccountSetting` والجلسة، البحث، الفلترة،
الإحصائيات، اختفاء المعطّلة من المبدّل وعودتها، وبقاء بياناتها.

### التحقق ✅

رفض الرمز المكرر، الاسم المكرر، المحلية المكررة، الاتجاه غير الصالح، والرمز الحاوي
على محارف مسار (`../etc`). وتجاهل قيود السجل نفسه عند التعديل.

### التصيير — 35/35 ✅

القوالب الخمسة × 7 لغات (`ar` و `en` و `ur` و `hi` و `bn` و `ger` و `xx` غير الموجودة).

### التكامل ✅

كل `route()` وكل `@include` يُحلّ، المتحكّم يُبنى من الحاوية، والتسجيل يصل
إلى `logs` و `event_logs`.

---

## 15. الأخطاء التي ظهرت أثناء التنفيذ وحُلَّت

### أ) `foreach() argument must be of type array|object, string given`

**السبب:** خطأ حقيقي في القالب — `@foreach` مباشرة على ناتج `__()`.
الاختبار الأولي جمّع القوالب (`Blade::compileString`) لكنه **لم يصيّرها**، فمرّ الخطأ.

**الحل:** حماية القيمة بـ `is_array()` قبل التكرار، واستبدال
`__('languages.guidelines.2')` في `edit.blade.php` بمفتاح نصّي مستقل
`default_language_note`. ثم أُضيف اختبار تصيير فعلي لكل القوالب في 7 لغات.

### ب) `Call to undefined function canLanguages()` ومفاتيح ترجمة غير محلولة

ظهرت الصفحة مرة بمفاتيح خام (`languages.list` و `languages.actions` …) بدل النصوص،
ومرة بخطأ `canLanguages()` غير معرّفة.

**السبب:** ليس خطأ في الكود، بل **قراءة أثناء الكتابة**. الملفات كانت تُكتب
(`PermissionHelper.php` و `resources/lang/{ar,en}/languages.php`) في اللحظة نفسها
التي وصلت فيها طلبات المتصفح، فقرأ الخادم حالة غير مكتملة أو لم يرَ الملفات الجديدة بعد
(`realpath_cache_ttl = 120` ثانية).

> **فرضية opcache كانت خاطئة** وقد أُسقطت: التشخيص المباشر من عملية Apache أثبت أن
> `opcache` **غير مُحمَّل أصلاً** على هذا الخادم.

**التحقق النهائي** — قراءة مباشرة من عملية الخادم نفسها:

```
langPath = ...\resources\lang       locale = en     fallback = en
[ar] is_file = YES   readable = YES
[en] is_file = YES   readable = YES
__('languages.list') @ar = قائمة اللغات      resolved = YES
loader->load(ar, languages) = array count=87
```

وتصيير الصفحات الأربع من داخل الخادم: **صفر مفاتيح غير مترجمة** بالعربية والإنجليزية.

**الحل:** تحديث الصفحة فقط (`Ctrl+F5`). المشكلة عابرة ولا تتكرر.

### د) صور الأعلام مكسورة

**السبب:** الـ Seeder يخزّن `sa.svg` و `us.svg` … بينما **مجلد `public/flags` غير موجود**،
فكان `flagIsImage()` يُعيد `true` لمجرد وجود الامتداد فتُعرض أيقونة صورة مكسورة.

**الحل:** `flagIsImage()` صارت تتحقق من وجود الملف فعلاً في `public/flags`
(مع `basename()` لمنع اجتياز المسارات)، وأُضيفت `flagIsText()` للتمييز بين رمز Emoji
واسم ملف مفقود. فإن غاب الملف يسقط العرض إلى مربّع رمز اللغة الملوّن بدل الصورة المكسورة،
وتُظهر صفحة التفاصيل تنبيهاً بأن الملف مفقود.

> لتفعيل الأعلام كصور: أنشئ `public/flags/` وضع فيه `sa.svg` و `us.svg` و `pk.svg`
> و `in.svg` و `bd.svg`. أو استبدل قيمة العلم برمز Emoji من شاشة التعديل.

### ج) التصفية في `register()` لا تعمل بصمت

مشروحة في القسم 7 — الحل نقلها إلى `boot()`.

---

## 16. حالة التشغيل

### ما نُفِّذ فعلاً ✅

```bash
php artisan migrate --force                        # الهجرة طُبِّقت
php artisan db:seed --class=PermissionTableSeeder  # الصلاحيات الست أُنشئت
php artisan db:seed --class=LanguageSeeder         # 5 لغات زُرعت
```

### هل تلزم أوامر إضافية؟ لا

- `composer dump-autoload` **غير مطلوب**: الملف الجديد الوحيد
  (`LanguageRequestMessages.php`) يُحلّ عبر PSR-4، ولم تُضف مداخل جديدة إلى
  `autoload.files` — بل أُضيفت دوال داخل ملف مُدرَج أصلاً.
- `optimize:clear` **غير مطلوب**: لا يوجد `bootstrap/cache/config.php`، وقوالب Blade
  يُعاد تجميعها تلقائياً عند تغيّر تاريخ التعديل.
- **إعادة تشغيل Apache غير مطلوبة**: `opcache` غير مُحمَّل على هذا الخادم أصلاً.

> إن ظهرت مفاتيح خام بعد تعديل ملفات الترجمة مباشرة، فهي مهلة
> `realpath_cache_ttl` (120 ثانية). تحديث الصفحة يكفي.

### للتحقق في أي وقت

```bash
php artisan tinker --execute="app()->setLocale('ar'); echo __('languages.list');"
# المتوقع: قائمة اللغات
```

---

## 17. ملفات معلَّقة

`Modules/Sitting/resources/views/sittingAccount/language.blade.php`

الشاشة القديمة. لم تعد مستعملة بعد تحويل `index()` إلى `sitting::languages.index`،
ولا يشير إليها أي كود. **لم تُحذف** لأنها غير متتبَّعة في git فالحذف غير قابل للتراجع.
حذفها آمن متى شئت.

---

## 18. مرجع سريع

```php
// الحصول على اللغة الافتراضية
$default = SystemLanguage::default();

// اللغات المفعّلة مرتّبة (مع كاش)
$languages = app(LanguageRepository::class)->getActiveLanguages();

// أكواد اللغات المفعّلة
$codes = app(LanguageRepository::class)->getActiveCodes();

// تعيين لغة كافتراضية (مع كل الحمايات والمزامنة والتسجيل)
app(LanguageService::class)->setDefault($id);

// فحص الاتجاه
if ($language->isRtl()) { ... }
```

### مفاتيح الكاش

```
active_system_languages
default_system_language
active_system_language_codes
```

تُفرَّغ تلقائياً عند أي `saved` أو `deleted` على `SystemLanguage`
(عبر `booted()` في الموديل)، فلا حاجة لتفريغها يدوياً حتى من الـ Seeders.

> **ملاحظة:** لا تُخزَّن النتيجة الفارغة في `active_system_language_codes` إطلاقاً،
> لأن جدولاً لم يُزرع بعد كان سيُجمّد الحالة على "لا لغات" لمدة ساعة كاملة.
> هذا خطأ وقع فعلاً أثناء التنفيذ وعولج.
