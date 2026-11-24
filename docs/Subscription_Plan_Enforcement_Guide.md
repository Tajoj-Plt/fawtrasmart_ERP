# دليل بناء صلاحيات الباقة وحدود الاستخدام

**الغرض:** أن يُلزِم من يقرؤه وحدةً كاملة بحدود باقة فوترة — صلاحياتٍ وسقوفاً ورفعَ استهلاك — بلا أن يمسّ نظام صلاحيات المنشأة.

الوحدة المرجعية المُنفَّذة بالكامل هي **`Modules/Stock`**. كل نمطٍ في هذا الدليل مأخوذٌ منها ومُجرَّب فيها، فما أشكل عليك فانظر نظيره هناك.

**اقرأ الأقسام ٠ و١ و٢ كاملةً قبل أن تكتب سطراً.** ما بعدها وصفةٌ تُنفَّذ بالترتيب.

---

## ٠. السياق والمتطلّبات

### المشروعان

| المشروع | الدور |
|---------|-------|
| `fawtmaintest1` | **نظام العميل** (ERP). هنا يقع كل العمل الموصوف في هذا الدليل. |
| `fawtra_dashboard` | **لوحة فوترة**. تُعرَّف فيها الباقات والميزات وحدودها. تُقرأ منه ولا يُعدَّل. |

عادةً يكونان متجاورين: `…/GitHub/fawtmaintest1` و`…/GitHub/fawtra_dashboard`.

### البنية التقنية

- Laravel 11 — **`bootstrap/app.php` هو نقطة الدخول الحيّة** للوسائط والمسارات. لا تُعدِّل `app/Http/Kernel.php` (بقيّةُ تخطيط Laravel 10 موجودة لكنها ليست المُستعمَلة للوسائط).
- بنية «مونوليث معياري»: عشرون وحدة تحت `Modules/` عبر `nwidart/laravel-modules`. كلٌّ لها `Http/` و`routes/` و`resources/`.
- المسارات تُحمَّل من `app/Providers/RouteServiceProvider.php` ومن مزوّدي الوحدات.
- **العربية لغةُ الشيفرة**: التعليقات وأسماء الرسائل عربية في كل هذا النظام. اكتب تعليقاتك بالعربية، وفسّر **لماذا** لا **ماذا** — راجع أي ملفّ في `app/Services/Fawtra/` لترى المستوى المطلوب.
- الأسلوب: `vendor/bin/pint` قبل التسليم.

### قبل أن تبدأ

```bash
php artisan optimize:clear
```

> **لا تعتمد على `php artisan test` للتحقّق.** الاختبارات مكسورة على مستوى المستودع لسببٍ لا علاقة له بهذا العمل: هجرة `2025_12_31_000002` تستعمل `ALTER TABLE … MODIFY COLUMN` (صيغة MySQL) و`phpunit.xml` يفرض sqlite، فتسقط الهجرة في `setUp` ويسقط معها كل اختبارٍ يحتاج قاعدة. ~٧٧ فاشلاً قبل أن تلمس شيئاً. التحقّق هنا يقع بأوامر القسم ٧.

---

## ١. الطبقات الثلاث — ولماذا لا يُغني بعضها عن بعض

كل طلبٍ في هذا النظام يمرّ بثلاثة أسئلة مستقلّة تماماً. الخلط بينها هو أصل أغلب أعطال «لماذا لا أستطيع الوصول؟».

| # | السؤال | الآلية | المصدر |
|---|--------|--------|--------|
| ١ | هل الوحدة **مفعَّلة** في هذا النظام؟ | `EnsureModuleEnabled` (وسيط `module`) | جدول `application_settings` + `ApplicationRegistry` |
| ٢ | هل يملك **المستخدم** الصلاحية؟ | `CheckPermissionRole` (وسيط `permission.role`) | `users.role_id` → `role_has_permissions` |
| ٣ | هل **اشترت المنشأة** الميزة، وهل بقي في حدّها متّسع؟ | `EnsurePlanFeature` (`plan`) + `EnforcePlanLimit` (`plan.limit`) | لقطة اشتراك فوترة |

**قاعدة لا تُكسر:** الطبقة الثالثة لا تقرأ من الثانية ولا تكتب فيها. مديرٌ يملك كل صلاحيات النظام لا يفتح شاشةً لم تُشترَ، ومنشأةٌ اشترت الميزة لا تفتحها لموظفٍ بلا صلاحية. **وترقيةُ الباقة يجب ألّا تمرّ بتعديل صلاحيات المستخدمين أبداً.**

وفوق الثلاث جميعاً يقف `EnsureSubscription` — «لا عمل بلا اشتراك نشط» — مُسجَّلٌ على مجموعتَي `web` و`api` معاً في `bootstrap/app.php`. **فلا تُكرِّر فحص نشاط الاشتراك في وحدتك.**

---

## ٢. مصادر الحقيقة — من أين تأتي المفاتيح

**هذا أهمّ قسمٍ في الدليل.** مفتاحٌ لا يعرفه كتالوج فوترة يُعامَل «مسموحاً بلا حدّ» (انظر `SubscriptionGate::allows()` و`limit()`) — وهو الصواب لكتالوجٍ قد يسبق النظام أو يتأخّر عنه. **ونتيجتُه أن الخطأ الإملائي في اسم مفتاح لا يُنتج عطلاً بل حارساً صامتاً لا يحرس.**

### المصدر الأوّل (الكامل): ملفّات الداشبورد

```
fawtra_dashboard/config/sales_features.php
fawtra_dashboard/config/account_features.php
fawtra_dashboard/config/stock_features.php
fawtra_dashboard/config/purchases_features.php
```

هذه **الكتالوج الكامل** لكل ما يمكن أن تحمله أي باقة. يقرؤها `App\Support\FeatureSectionRegistry` في الداشبورد؛ وقسمٌ جديد يُضاف بملفٍّ مطابق بلا كود.

اطبع قسمك:

```bash
cd ../fawtra_dashboard && php -r '
$s = "stock";                       // بدّلها: sales | account | stock | purchases
$c = require "config/{$s}_features.php";
echo "=== LIMITS ===\n";
foreach ($c["limits"] as $k => $v) {
    printf("%-26s %-6s %-12s %s\n", $k, $v["type"], $v["default_period"] ?? "-", $v["source"] ?? "-");
}
echo "\n=== PERMISSIONS ===\n";
foreach ($c["permission_groups"] ?? [] as $g => $gv) {
    echo "-- ".$g."\n";
    foreach ($gv["permissions"] ?? [] as $pk => $pv) {
        printf("     %-32s %s\n", $pk, is_array($pv) ? ($pv["label"] ?? "") : $pv);
    }
}'
```

لكل حدٍّ حقلٌ `source` يقول أي متحكّمٍ في نظام العميل يُنتجه — وهو مدخلك إلى الشيفرة.

### المصدر الثاني (الحالي): لقطة الاشتراك

```bash
php artisan tinker --execute='$r = App\Models\FawtraSubscription::current(); echo json_encode(["permissions" => array_keys($r->planPermissions()), "usage" => collect($r->usage())->map(fn($u) => [$u["key"], $u["type"] ?? null, $u["period"] ?? null, $u["allowance"] ?? null])], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);'
```

### ⚠ الفرق بينهما — ولماذا لا يكفي الثاني

**اللقطة تعرض ما تشمله الباقة الحالية وحدها، لا كل ما يمكن أن يُشترى.** مثالٌ واقعي: كتالوج الداشبورد يعرّف **١٩** حدّاً للمبيعات، والباقة المُركَّبة الآن ترسل **١٥** فقط. فمن بنى على اللقطة وحدها أسقط أربعة حدود صامتةً — تظهر يوم يُرقّي العميل باقته، بلا حارسٍ لها.

> **ابنِ من ملفّ الداشبورد، وتحقّق باللقطة.** واللقطة لازمةٌ على كل حال لأمر التحقّق ٢ في القسم ٧ (مطابقة الكتالوج). وإن لم يكن النظام مربوطاً بلوحةٍ حيّة فالداشبورد وحده يكفي للبناء.

### نوعا الحدود

| النوع | `period` في اللقطة | المعنى | كيف يُقاس |
|-------|--------------------|--------|-----------|
| `quota` | `يومي` / `شهري` / `طوال مدة الباقة` | عدّاد يتراكم في نافذة زمنية | عدّ الصفوف داخل النافذة على `created_at` |
| `cap` | `سقف متزامن` | عددٌ موجودٌ الآن، لا يتراكم | عدّ كل الصفوف |

`-1` تعني **بلا حدّ**. لا تحوّلها إلى صفر بحال.

---

## ٣. الملفات

### مشتركة — موجودة ومُختبَرة، لا تُنشئها من جديد

| الملفّ | الدور |
|--------|-------|
| `app/Http/Middleware/EnsurePlanFeature.php` | وسيط `plan:` — يمنع فتح شاشة ميزةٍ خارج الباقة. `plan:a` أو `plan:a\|b` (تكفي واحدة) |
| `app/Http/Middleware/EnforcePlanLimit.php` | وسيط `plan.limit:` — يمنع الإنشاء فوق السقف. `plan.limit:products` أو `plan.limit:products,5` |
| `app/Observers/PlanLimitObserver.php` | الحارس الأخير على النموذج — أضِف نماذج وحدتك إلى `map()` |
| `app/Exceptions/PlanLimitExceededException.php` | استثناء بلوغ الحدّ — مستثنى من سجلّ الأخطاء في `bootstrap/app.php` |
| `app/Services/Fawtra/SubscriptionGate.php` | `allows()` / `allowsAny()` / `limit()` / **`liveLimit()`** / **`checkLive()`** |
| `app/Services/Fawtra/UsageReporter.php` | `catalog()` — أضِف مفاتيح وحدتك هنا. و`measure()` / `liveCount()` |
| `app/Services/Fawtra/UsagePush.php` | الرفع الفوري: `mark()` / `markBulkChange()` / `ensureDrain()` |
| `resources/views/errors/plan-feature.blade.php` | شاشة «ميزة خارج الباقة» (402) |

**الوسيطان مُسجَّلان أصلاً** في `bootstrap/app.php` باسمَي `plan` و`plan.limit`، والموجّه `@plan` مُسجَّل في `AppServiceProvider`. لا تُعِد تسجيل شيء.

### جديدٌ لكل وحدة — ملفٌّ واحد

`app/Constants/planFeatures/<Module>PlanFeatures.php` — **المصدر الوحيد** لأسماء مفاتيح هذه الوحدة. المرجع الكامل: `app/Constants/planFeatures/StockPlanFeatures.php`.

---

## ٤. الوصفة — تسع خطوات

> نفّذها بالترتيب. الخطوات ١–٣ بناء، و٤–٦ منع، و٧–٨ رفع، و٩ تحقّق.

### الخطوة ١ — استخرج مفاتيح وحدتك

من ملفّ الداشبورد (القسم ٢). سجّل لكل حدٍّ: `key` و`type` و`default_period` و`source`. ولكل صلاحية: مفتاحها ومجموعتها.

**لا تخمّن ولا تشتقّ اسماً من اسم الشاشة.**

### الخطوة ٢ — أنشئ `<Module>PlanFeatures`

```php
<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة المبيعات على كتالوج باقة فوترة — المصدر الوحيد لهذه الأسماء.
 *
 * صلاحيات النظام (`sales.invoices.view`) تقول **من** يفعل ماذا؛ وصلاحيات
 * الباقة هنا (`invoices`) تقول **ماذا اشترت المنشأة**. لا يتداخلان.
 */
class SalesPlanFeatures
{
    /* ========== صلاحيات الباقة — كما تصل في plan.permissions حرفاً بحرف ========== */
    public const INVOICES = 'invoices';
    public const QUOTES = 'quotes';
    // …

    /** كل صلاحيات الوحدة — تكفي واحدة لفتح لوحتها وبند قائمتها الجامع. */
    public static function all(): array
    {
        return [self::INVOICES, self::QUOTES /* … */];
    }

    /* ========== مفاتيح حدود الاستخدام — كما تصل في usage[].key ========== */
    public const LIMIT_INVOICES = 'sales_invoices';
    public const LIMIT_OFFERS = 'offers';
    // …
}
```

> **مفتاح الصلاحية ومفتاح الحدّ ليسا الاسم نفسه غالباً.** الصلاحية `invoices` وحدُّها `sales_invoices`. افصل الثابتين ولا تُعِد استعمال أحدهما مكان الآخر.

> **موضع الملفّ: `app/Constants/planFeatures/`** — مجلّدٌ يجمع مفاتيح الباقات وحدها، فلا تختلط بكتالوجات صلاحيات النظام (`StockPermissions` وأخواتها) الساكنة في `app/Constants/` مباشرةً. والفصل هنا يعكس الفصل المفاهيمي في القسم ١: طبقتان لا تتداخلان، فملفّاهما لا يتجاوران.
>
> واسم المجلّد بحرفٍ صغير (`planFeatures`) ومساحةُ الاسم مطابقةٌ له حرفاً بحرف (`namespace App\Constants\planFeatures;`) — وهذا شرط PSR-4، وكسرُه يعمل على ويندوز ويسقط على خادم لينكس.

### الخطوة ٣ — أضِف الحدود إلى `UsageReporter::catalog()`

أوّلاً اعثر على الجدول الصحيح لكل مفتاح:

```bash
php artisan tinker --execute='foreach (Illuminate\Support\Facades\DB::select("SHOW TABLES") as $t) { $n = array_values((array) $t)[0]; if (preg_match("/invoice|offer|quote/i", $n)) { echo $n." (".Illuminate\Support\Facades\DB::table($n)->count()." صفّاً)".PHP_EOL; } }'
```

ثمّ:

```php
'sales_invoices' => ['kind' => 'quota', 'table' => 'invoices'],
'offers'         => ['kind' => 'cap',   'table' => 'offers'],
```

بلا مدخلٍ هنا **لا تُقاس الميزة محلياً أصلاً** — فيتعذّر إلزام الحدّ ولا يُرفَع الاستهلاك.

وإن كان الجدول مشتركاً بين أنواع مستندات، أضِف حالةً في `measure()`:

```php
'sales_invoices' => (int) $query->whereIn('type', ['normal', 'pos', 'online'])->count(),
```

> **مزلق `activeScope()`:** لا تستعملها لسقفٍ إلا بعد أن تتحقّق أن عمود الحالة **رايةُ نشاطٍ فعلاً**. في `price_lists` قيمة `status` هي `0` في كل صفّ، وفي `warehouse_permits` هي `pending`/`approved`/`rejected`. والترشيح بها يُنتج صفراً في جدولٍ ملآن — وعددٌ أقلّ من الحقيقة في **سقف** ليس نقصَ دقّةٍ بل ثغرةً تسمح بالتجاوز بلا حدود.

### الخطوة ٤ — احرس المسارات

على **مجموعة** كل بادئة، لا على كل مسارٍ على حدة:

```php
Route::prefix('offers')->middleware('plan:'.SalesPlanFeatures::OFFERS)->group(function () { … });
```

وعلى الوحدة كلّها سورٌ خارجي بشرط «واحدةٌ على الأقل»:

```php
Route::prefix('sales')->middleware('plan:'.implode('|', SalesPlanFeatures::all()))->group(…);
```

وعلى مسارات الإنشاء وحدها (`create` و`store` — **لا `edit`/`update`/`destroy`**):

```php
Route::post('/store', [OfferController::class, 'store'])
    ->middleware(['permission.role:sales.offers.create', 'plan.limit:'.SalesPlanFeatures::LIMIT_OFFERS]);
```

> **لا تُهمل `routes/api.php`.** مسارات `api` لا تمرّ بمجموعة `web` ولا بحُرّاسها؛ وقفلٌ يحرس الشاشة ويترك الواجهة مفتوحة يُلتفّ عليه بطلبٍ واحد.
>
> وانتبه أن `apiResource` تسجّل `store` مع البقية — إن أردت `plan.limit` على الإنشاء وحده فسجّل `Route::post` **قبلها** واستثنِ `->except('store')`، فأوّل مسارٍ مطابق هو الذي يعمل:
>
> ```php
> Route::post('offers', [OfferApiController::class, 'store'])
>     ->middleware(['plan:'.SalesPlanFeatures::OFFERS, 'plan.limit:'.SalesPlanFeatures::LIMIT_OFFERS]);
> Route::apiResource('offers', OfferApiController::class)->except('store')
>     ->middleware('plan:'.SalesPlanFeatures::OFFERS);
> ```

> **لا تضع `plan.limit` على مسار الحذف أو التعديل.** من بلغ سقفه يحتاج الحذف تحديداً ليخرج منه، ومنعُه يقفل عليه باب الخروج.

### الخطوة ٥ — أضِف نماذجك إلى `PlanLimitObserver::map()`

```php
Offer::class => SalesPlanFeatures::LIMIT_OFFERS,
```

**لماذا وقد حُرِست المسارات؟** لأن وسيط المسار يحرس البابَ الذي مررتَ منه، والصفوف تُنشأ من أبوابٍ أخرى: عملياتٌ جماعية تُنشئ صفّاً لكل عنصر، وأثرٌ جانبيّ لمسارِ «اعتماد» لا «إنشاء»، واستيرادٌ بعدد أسطر ملفّ، وأوامرُ طرفية ومهامّ طابور لا تمرّ بمسارٍ أصلاً. **الوسيط للرسالة الواضحة، والمراقب للضمانة التي لا تُخترق.**

الكلفة صفر على ميزةٍ بلا حدّ: الفحص يخرج من اللقطة بلا استعلام.

### الخطوة ٦ — العمليات الجماعية التي لا تُعرف كمّيتها

الاستيراد من ملفّ هو الحالة الوحيدة التي لا يصلح لها وسيطٌ على المسار — الكمّية لا تُعرف قبل قراءة الملفّ:

```php
$gate = app(SubscriptionGate::class);

if (! $gate->liveLimit(SalesPlanFeatures::LIMIT_INVOICES)['unlimited']) {
    $rows = collect(Excel::toArray(new InvoicesImport, $request->file('file')))->flatten(1)->count();
    $quota = $gate->checkLive(SalesPlanFeatures::LIMIT_INVOICES, max(1, $rows));

    if (! $quota['allowed']) {
        return redirect()->back()->with('error', $quota['reason']);
    }
}
```

بغير هذا يرمي المراقب عند السطر المتجاوِز، و`Excel::import` لا يلفّ الاستيراد بمعاملة — فيبقى ما سبقه محفوظاً ويُترك المستخدم أمام استيرادٍ نصفُه تمّ.

### الخطوة ٧ — أعلِن عن التغييرات الجماعية ⚠

**هذه الخطوة أكثر ما يُنسى، وأثرُها أن يبقى العميل ممنوعاً عند سقفٍ أفرغه بيده.**

`Model::whereIn(...)->delete()` و`->truncate()` و`Model::insert([...])` تُنفَّذ في المحرّك مباشرةً **بلا أن تُطلق أحداث النموذج**. فالمراقب لا يراها.

اعثر على مواضعها في وحدتك:

```bash
grep -rnE "(Offer|Invoice|Quote)::(where[A-Za-z]*\(|query\(\)).*->(delete|forceDelete|truncate)\(\)" Modules/ app/
grep -rnE "(Offer|Invoice|Quote)::insert\(" Modules/ app/
```

وأعلِن عن كلٍّ منها:

```php
Offer::whereIn('id', $ids)->delete();

// حذفٌ جماعي لا يُطلق حدث `deleted`، فلا يراه PlanLimitObserver
\App\Services\Fawtra\UsagePush::markBulkChange(\App\Constants\planFeatures\SalesPlanFeatures::LIMIT_OFFERS);
```

`markBulkChange()` تعالج شيئين معاً: **العدّاد المحفوظ للطلب** في `SubscriptionGate` (لم يُنقَص لأن الحدث لم يقع)، و**الرقم عند فوترة** (يُعلَّم ليُرفَع بعد الاستجابة).

> المواضع المعالَجة في المخزون أربعة: `ProductsController::bulkDelete()`، وموضعان في `InventoryManagementController` (إلغاء الجرد وحذفه)، وواحد في `ProductExchangeController`.

### الخطوة ٨ — الشريط الجانبي

الشرط الثالث بجانب الاثنين القائمين:

```blade
@php
    use App\Constants\planFeatures\SalesPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $plan = ['offers' => SubscriptionGate::permits(SalesPlanFeatures::OFFERS) /* … */];
    $planAnySales = SubscriptionGate::permits(SalesPlanFeatures::all());
@endphp

@if ($hasAnyPermission && $planAnySales)
    @if (hasPermission('sales.offers.view') && $plan['offers'])
        …
    @endif
@endif
```

أو الموجّه `@plan('offers') … @endplan`.

#### ⚠ الميزة خارج الباقة تُقفَل ولا تُخفى

الحارسان يفترقان في الأثر، والخلط بينهما خطأ متكرّر:

| الحارس | الأثر | لماذا |
|--------|-------|-------|
| **صلاحية النظام** (`hasPermission`) | **إخفاء** | موظّفٌ لا يملك صلاحية المستودعات لا شأن له بها ولا يشتريها؛ وعرضُ قفلٍ له يدعوه إلى بابٍ ليس بابه ويكشف بنية النظام بلا حاجة |
| **ميزة خارج الباقة** (`SubscriptionGate::permits`) | **قفلٌ ظاهر** | إخفاؤها يجعل المنشأة لا تعرف أنها موجودة أصلاً — فلا تسأل عنها ولا تشتريها، وتظنّ النظام لا يملكها |

استعمل `App\Support\PlanLock`:

```blade
@if (hasPermission('sales.offers.view'))          {{-- الصلاحية تُخفي --}}
    @php $locked = \App\Support\PlanLock::locked(SalesPlanFeatures::OFFERS); @endphp

    <li class="{{ $locked ? 'stk-plan-locked' : '' }}">
        <a href="{{ $locked ? \App\Support\PlanLock::url() : route('offers.index') }}"
           @if ($locked) title="{{ \App\Support\PlanLock::reason(SalesPlanFeatures::OFFERS) }}" @endif>
            <span class="menu-item">العروض</span>
            {!! $locked ? \App\Support\PlanLock::menuBadge() : '' !!}
        </a>
    </li>
@endif
```

لونُ القفل **أحمر** لا برتقالي: البرتقالي لغةُ الاقتراب من الحدّ (شارة ٨٠٪)، والقفل حالةٌ قاطعة — وتمييزهما باللون يجعل الفرق يُدرَك بلا قراءة.

والبند المقفول **يقود إلى شاشة الاشتراك** لا إلى شاشته — إذ يُردّ عنها بـ402 على كل حال. وأصنافُ العرض (`stk-plan-locked` و`stk-tile--locked` و`stk-plan-badge`) معرَّفةٌ في `Modules/Stock/resources/views/layouts/partials/stock-theme.blade.php`.

**والقفل يشمل كل واجهةٍ تقود إلى الميزة، لا الشريط وحده**: بلاطات شاشات الإعدادات، وبطاقات اللوحات، وأزرار الاختصار. افحصها جميعاً:

```bash
grep -rln "stk-tile\|route('<مسارٌ محروس>')" --include=*.blade.php Modules/<Module>/resources/views/
```

#### الحدود شارةٌ لا قفل

البند الذي بلغ سقفه يبقى **مفتوحاً**: من استنفد حدّه يحتاج شاشته أكثر من غيره — ليحذف ما لم يعد مستعمَلاً أو ليرى ما عنده. استعمل `limit()` لا `liveLimit()` هنا: هذه قراءةٌ في كل تحميلِ صفحة، وتلك تُطلق `COUNT(*)` لكل ميزة.

ولا تجتمع العلامتان: ميزةٌ لم تُشترَ لا حدّ لها يُعرض.

وتذكّر أن الشريط **للوضوح لا للمنع**: من يعرف العنوان يفتحه رغم القفل. المنع في الوسيط وحده.

### الخطوة ٩ — تحقّق

نفّذ قائمة القسم ٧ كاملة. لا تقل «انتهيت» قبلها.

---

## ٥. `limit()` أم `liveLimit()`؟ — الفرق الذي يصنع الثغرة

| | `limit()` | `liveLimit()` / `checkLive()` |
|---|---|---|
| مصدر `used` | اللقطة المحفوظة | **عدٌّ من قاعدة البيانات الآن** |
| الكلفة | صفر (من الذاكرة) | `COUNT(*)` مرّةً لكل ميزة في الطلب |
| الاستعمال | عرض الشارات والشاشات | **كل قرار منع** |

اللقطة تُجلب كل ١٥ دقيقة والرفعُ إليها دوريٌّ كذلك. فالمنع المبنيّ على `used` المحفوظ يترك نافذةً مقدارها دورةُ مزامنة كاملة، والإنشاء فيها غير محدود عملياً — ثم تأتي المزامنة فتجد الحدّ متجاوَزاً ولا تملك إرجاعه.

`liveLimit()` تأخذ الحدّ من فوترة والمستهلَك من عندنا، وتعتمد **الأكبر** من الرقمين.

---

## ٦. الرفع إلى اللوحة — متى تعرف بما جرى؟

الرفع الدوري وحده يعني أن لوحة فوترة تتأخّر ربع ساعة عن الواقع؛ وفي نظامٍ لم يُضبط فيه `schedule:run` تتأخّر **إلى الأبد** فتبقى أرقامها صفراً مهما أُنشئ محلياً.

فبجانب الدورة الدورية يعمل `App\Services\Fawtra\UsagePush`:

| اللحظة | ما يقع |
|--------|--------|
| عند الإنشاء أو الحذف | `PlanLimitObserver` يُنادي `UsagePush::mark($feature)` — **كتابةٌ في الكاش وحدها، لا نداءَ شبكة** |
| بعد إرسال الاستجابة | `app()->terminating` يرفع الميزات المُعلَّمة وحدها |
| على كل طلبٍ تالٍ | `UsagePush::ensureDrain()` من `EnsureSubscription` يصرّف ما أجّله الكابح |
| كل ١٥ دقيقة | `fawtra:sync` يرفع كل شيء — شبكةُ الأمان لما فات |

**ولماذا لا يُنادى فوترة داخل الحفظ؟** لأن نداءً متزامناً في مسار الحفظ يشتري دقّةَ اللحظة بأربعة أثمان: زمنُ رحلةٍ يُضاف إلى كل حفظ، وحدُّ الطلبات (١٢٠/دقيقة) يُستنفَد في فرعٍ نشط فتُرفض عملياتٌ صحيحة، وانقطاعُ الشبكة عند فوترة يوقف الإنشاء عندنا — ووثيقة الواجهة تنهى عنه صراحةً — والنداءُ داخل معاملةٍ يُبقي الأقفال مفتوحة طوال الرحلة.

**الكبح:** خمسون إنشاءً في طلبٍ واحد = رفعٌ واحد. وخمسون طلباً متتالياً = رفعٌ واحد كل `fawtra.usage.push_cooldown` ثانية (٦٠ افتراضاً)، **وما فات يبقى معلَّماً** يصرّفه `ensureDrain()` مع أوّل تنقّلٍ بعد انتهاء النافذة.

> بلا هذا التصريف يسقط الرفع بصمت: من أنشأ شيئاً بعد ثوانٍ من رفعٍ سابق تبقى ميزته معلَّقة ولا شيء يعود إليها — وقد وقع فعلاً (إذنٌ مخزني أُنشئ بعد ٢٦ ثانية من رفعٍ سابق فبقي غير مرفوع، واللوحة على الرقم القديم).

**لماذا `terminating` لا مهمّة طابور؟** لأن `QUEUE_CONNECTION=sync` هنا، فالمهمّة «المؤجَّلة» تُنفَّذ داخل الطلب فوراً — أي أنها النداء المتزامن نفسه بثوبٍ آخر. ومن يشغّل عامل طابورٍ حقيقياً يربح متانةً أكثر (إعادةُ محاولةٍ تلقائية، وبقاءٌ عبر الطلبات)، وحينها يصلح تحويل `UsagePush::flush()` إلى مهمّة.

### الإعداد

```dotenv
FAWTRA_USAGE_REPORT=true            # الرفع أصلاً
FAWTRA_USAGE_PUSH_ON_WRITE=true     # الرفع فور الإنشاء
FAWTRA_USAGE_PUSH_COOLDOWN=60       # أقصر مدّة بين رفعين (ثانية)
```

> عطِّل `FAWTRA_USAGE_PUSH_ON_WRITE` في بيئة اختبارٍ تشترك مع الإنتاج في المفتاح نفسه — الرفع كتابةٌ في اشتراكٍ حقيقي.

---

## ٧. تعريف «انتهيت» — قائمة التحقّق

نفّذها كلّها. المخرج المطلوب مذكورٌ مع كلٍّ منها.

```bash
# ١) هل بقي مسارٌ بلا حارس؟   المطلوب: صفر (أو مسارات معروفةٌ مبرَّرة)
php artisan tinker --execute='$s = collect(Illuminate\Support\Facades\Route::getRoutes())->filter(fn($r) => preg_match("#(^|/)sales/#", $r->uri())); $bad = $s->filter(fn($r) => ! collect($r->gatherMiddleware())->contains(fn($m) => is_string($m) && str_starts_with($m, "plan:"))); echo "الكل: ".$s->count()." | بلا حارس: ".$bad->count().PHP_EOL; foreach ($bad as $r) { echo "   ".$r->uri().PHP_EOL; }'
```

```bash
# ٢) هل مفاتيحك مطابقة لِما ترسله فوترة؟   المطلوب: القائمة الأولى فارغة
php artisan tinker --execute='$c = array_keys(App\Services\Fawtra\UsageReporter::catalog()); $p = collect(App\Models\FawtraSubscription::current()->usage())->pluck("key")->all(); echo "ترسلها فوترة ولا يعرفها الكتالوج:".PHP_EOL; foreach (array_diff($p, $c) as $k) { echo "   - ".$k.PHP_EOL; } echo "في الكتالوج ولا ترسلها فوترة:".PHP_EOL; foreach (array_diff($c, $p) as $k) { echo "   - ".$k.PHP_EOL; }'
```

```bash
# ٣) هل كل ميزةٍ تُقاس محلياً فعلاً؟   المطلوب: لا NULL لميزةٍ في وحدتك
php artisan tinker --execute='$r = App\Models\FawtraSubscription::current(); $u = app(App\Services\Fawtra\UsageReporter::class); foreach ($r->usage() as $i) { echo str_pad($i["key"], 26)." => ".var_export($u->liveCount($i["key"], $i["period"] ?? null, $r), true).PHP_EOL; }'
```

```bash
# ٤) هل المراقبون مسجَّلون على النماذج؟   المطلوب: true للجميع
php artisan tinker --execute='foreach (App\Observers\PlanLimitObserver::models() as $m) { echo str_pad($m, 40).var_export($m::getEventDispatcher()->hasListeners("eloquent.creating: ".$m), true).PHP_EOL; }'
```

```bash
# ٥) هل الرقم المحلّي يطابق ما عند اللوحة؟   المطلوب: «متطابق» للجميع
php artisan tinker --execute='$r = App\Models\FawtraSubscription::current(); $u = app(App\Services\Fawtra\UsageReporter::class); $c = App\Services\Fawtra\FawtraClient::fromConfig()->get("sync"); $panel = collect($c["data"]["usage"]["usage"] ?? $c["data"]["usage"] ?? [])->keyBy("key"); foreach ($r->usage() as $i) { $k = $i["key"]; $live = $u->liveCount($k, $i["period"] ?? null, $r); $p = $panel[$k]["used"] ?? "?"; printf("%-26s قاعدة=%-7s لوحة=%-7s %s%s", $k, var_export($live, true), $p, ($live === $p ? "متطابق" : "<<< انحراف"), PHP_EOL); }'
```

```bash
# ٦) هل ثمّة كتابةٌ تتجاوز المراقب؟   المطلوب: كلٌّ منها متبوعٌ بـ markBulkChange
grep -rnE "(نماذجك)::(where[A-Za-z]*\(|query\(\)).*->(delete|forceDelete|truncate)\(\)|(نماذجك)::insert\(" Modules/ app/
```

```bash
# ٧) الأسلوب
vendor/bin/pint <الملفات التي عدّلتها>
```

### الاختبار اليدوي — لا يُستغنى عنه

احفظ اللقطة أوّلاً:

```bash
php artisan tinker --execute='file_put_contents(storage_path("app/snapshot-backup.json"), json_encode(App\Models\FawtraSubscription::current()->snapshot, JSON_UNESCAPED_UNICODE));'
```

ثمّ عدّلها مؤقتاً (اجعل ميزةً `false` وسقفاً رقماً أصغر من الموجود)، وتحقّق أن: الشاشة تُردّ بـ402، والإنشاء يُمنع، والبند يختفي من الشريط. **ثمّ أعِد اللقطة الأصلية من النسخة المحفوظة** — اللقطة بياناتُ اشتراكٍ حقيقي.

---

## ٨. مزالق تكرّرت — اقرأها قبل أن تبدأ

1. **مفتاحٌ مكتوبٌ خطأً يُنتج حارساً صامتاً لا عطلاً.** المجهول مسموحٌ بلا حدّ. استعمل ثوابت `<Module>PlanFeatures` ولا تكتب نصّاً حرّاً في المسارات.
2. **اللقطة ليست الكتالوج الكامل.** ابنِ من ملفّ الداشبورد وإلا أسقطتَ ميزاتٍ تظهر عند أوّل ترقية.
3. **`plan.limit` على `update` أو `destroy`** يقفل باب الخروج من الحدّ. الإنشاء وحده.
4. **`routes/api.php` منسيّ.** لا يمرّ بمجموعة `web` ولا بحُرّاسها.
5. **`apiResource` تسبق مسارك المخصَّص.** أوّل مسارٍ مطابق هو الذي يعمل — سجّل الخاصّ قبلها و`->except()` منها.
6. **`activeScope()` على عمودٍ ليس رايةَ نشاط** يُنتج عدّاً أقلّ من الحقيقة — وهو ثغرة في السقوف لا مجرّد خطأ عرض.
7. **الصفوف المحذوفة حذفاً ناعماً** تُستثنى في `measure()` عبر `deleted_at`؛ عدُّها يجعل سقفاً بلغ حدَّه غيرَ قابلٍ للتفريغ بالحذف.
8. **لا تفحص نشاط الاشتراك في `checkLive()`.** `EnsureSubscription` يتكفّل به على كل مسار، وتكرارُه هنا يوقف أوامر الطرفية ومهامّ الطابور — فاشتراكٌ تعثّرت مزامنته يصير نظاماً لا تُصلَح قاعدتُه من الطرفية أيضاً.
9. **لا تسجّل بلوغ الحدّ في سجلّ الأخطاء.** قاعدةُ عملٍ لا عطل — والتسجيل يُغرق الشاشة بضجيجٍ يُخفي العطل الحقيقي.
10. **الحذف الجماعي لا يُطلق أحداث النموذج** — أعلِن عنه بـ`UsagePush::markBulkChange()`، وإلا بقي الرقم عند فوترة أعلى من الحقيقة والعميل ممنوعاً عند سقفٍ أفرغه.
11. **الكابح يجب أن يؤجّل لا أن يُسقط.** أي آليةِ كبحٍ تحتاج من يعود إلى ما أجّلته — وإلّا صار الكبحُ فقداناً صامتاً.
12. **لا تقارن بعلامتك المحلّية وحدها عند الرفع.** اللوحة تُصفّر عدّاداتها عند تغيير الباقة، فتظنّ أنك أبلغتها ورقمُها صفر — ولا شيء يكسر هذا التعادل أبداً. قارِن بـ`used` في اللقطة كذلك (`pushCap`/`pushQuota`).
13. **لا تُخفِ ميزةً خارج الباقة — اقفِلها.** الإخفاء يمنع المنشأة من معرفة أنها موجودة فلا تشتريها. والإخفاء لصلاحيات النظام وحدها.
14. **القفل يشمل كل واجهةٍ تقود إلى الميزة** لا الشريط الجانبي وحده: بلاطات الإعدادات وبطاقات اللوحات وأزرار الاختصار.
15. **لا تشتقّ اسم الجدول من اسم المفتاح.** «سندات القبض» مفتاحُها `incomes` وجدولُها `receipts`، و«دفاتر الشيكات» `check_books` وجدولُها `cheque_books`. خُذ الجدول من نموذج المتحكّم الذي يُنشئ الصفّ.
16. **بعضُ الميزات لا جدول لها.** «الأصول الثابتة» حساباتٌ تحت أبٍ في `accounts` لا صفوفٌ في جدولٍ خاص — وعدُّ الجدول كلِّه يُنتج ٢٥٤ مكان ٩ فيستنفد السقف فوراً. رشِّح بنفس شرط المتحكّم، وطابِق الشرط في `measure()` و`PlanLimitObserver` حرفاً بحرف.
17. **بعد أي اختبارٍ على اللقطة، أعِدها.**

---

## ٩. حالة التطبيق — وما تبقّى

> **الأرقام أدناه لقطةُ لحظةٍ لا حقيقةً ثابتة.** كتالوج فوترة يتغيّر مع تغيّر الباقة، وقد تغيّر فعلاً بين مزامنتين أثناء كتابة هذا الدليل: اختفت سبعُ ميزاتٍ وصارت كل الحدود المتبقّية رقميةً بعد أن كان أغلبها «بلا حدّ». **أعِد تشغيل أوامر التحقّق ٢ و٣ و٥ قبل الاعتماد على أي رقمٍ هنا.**

| الوحدة | الحالة |
|--------|--------|
| **Stock** | ✅ **مكتملة — المرجع.** ٢٠٢/٢٠٣ مسار محروس، ٧ نماذج تحت المراقب، ٢٢ مسار إنشاء محدود، رفعٌ فوري يعمل |
| **Manufacturing** | ❌ **الأعلى أولوية — ثغرةٌ عاملة.** الباقة تُطفئ `stock_manufacturing` و`stock_bom` و`stock_workstations` صراحةً، والوحدة بلا حارسٍ واحد: شاشاتُها مفتوحةٌ لميزاتٍ لم تُشترَ |
| **Sales** | ❌ ١٩ حدّاً في كتالوج الداشبورد بلا إلزام |
| **Account** | ✅ **مكتملة.** ٢٢٩/٢٢٩ مسار محروس، ١٨ مسار إنشاء محدود، ٩ نماذج تحت المراقب، الشريط وشاشتا الإعدادات مقفولتان |
| **Purchases** | ⚠️ فحوصٌ داخل المتحكّمات فقط بلا وسيط، وتستعمل `check()` لا `checkLive()`؛ وبعضها على **مفاتيح وهمية** (`purchase_quotations`, `purchase_price_quotations`) لا ترسلها فوترة — أي استثناءٌ دائم السماح لا حارس |

**مفاتيح وهمية أخرى في الكتالوج** لا ترسلها الباقة الحالية: `periodic_invoices`, `installment_plans`, `gift_offers`, `collections_targets`, `manufacturing_orders`, `bom_lists`, `workstations`. وجودُها لا يضرّ — المجهول لا يُقاس ولا يُلزَم — لكن **لا تحسبها حراسةً قائمة**.

---

## ١٠. مراجع

| الملفّ | لماذا تفتحه |
|--------|--------------|
| `Modules/Stock/routes/web.php` | النمط المرجعي لحراسة المسارات — ١٥ مجموعة بادئة |
| `app/Constants/planFeatures/StockPlanFeatures.php` | النمط المرجعي لملفّ المفاتيح |
| `app/Observers/PlanLimitObserver.php` | الحارس الأخير، وشرح لماذا لا تكفي المسارات |
| `app/Services/Fawtra/SubscriptionGate.php` | `liveLimit()` وشرح ثغرة اللقطة المتأخّرة |
| `app/Services/Fawtra/UsageReporter.php` | الكتالوج والقياس والرفع |
| `app/Services/Fawtra/UsagePush.php` | الرفع الفوري والكبح والتصريف |
| `app/Support/PlanLock.php` | القفل الظاهر: `locked()` / `reason()` / `url()` / `menuBadge()` |
| `app/Http/Middleware/EnsureSubscription.php` | حارس النشاط، ومَوضع `ensureDrain()` |
| `bootstrap/app.php` | تسجيل `plan` و`plan.limit` واستثناء `PlanLimitExceededException` |
| `Modules/Stock/resources/views/layouts/sidebar.blade.php` | النمط المرجعي للشريط الجانبي والشارات |
| `fawtra_dashboard/config/*_features.php` | **الكتالوج الكامل** — مصدر كل مفتاح |
| `fawtra_dashboard/app/Support/FeatureSection.php` | بنية ملفّ القسم في الداشبورد |
