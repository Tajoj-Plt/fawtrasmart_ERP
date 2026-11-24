# خطة توحيد تصميم المتجر الإلكتروني + تطبيق الصلاحيات وحدود الباقة

## الهدف

1. **التصميم**: تحويل كل شاشات `Modules/OnlineStore/resources/views` من تصميمها الحالي (بطاقات `stat-card-modern`, `table-card-modern`, `content-header row`, ألوان يدوية) إلى نظام `stk-*` المشترك المستعمل فعلياً في `Modules/Account/resources/views/finance/expenses/*` (المرجع الذي حدده المستخدم).
2. **الصلاحيات**: كل زر/رابط/بند قائمة يُخفى إن لم يملك المستخدم صلاحية النظام المطابقة (`hasPermission()`), ويُقفَل (يظهر بشارة قفل ويقود لشاشة الاشتراك) إن كانت الميزة خارج باقة الاشتراك (`PlanLock`).
3. **الشريط الجانبي**: إعادة بناء `Modules/OnlineStore/resources/views/layouts/sidebar.blade.php` على نمط `Modules/Account/resources/views/layouts/sidebar.blade.php` (المرجع الذي حدده المستخدم): كل مجموعة محروسة بـ`canAny([...])`، وكل بند محروس بـ`hasPermission('...')` + `PlanLock` لقفل ما هو خارج الباقة.

## المراجع المدروسة قبل التنفيذ

- `Modules/Account/resources/views/finance/expenses/{index,create,show}.blade.php` — نظام `stk-*`: `page-head`, `stk-metrics`/`stk-metric`, `stk-filters`, `stk-table-card`, `stk-def`/`stk-def__row`, `stk-tabs`, `stk-timeline`, `stk-empty`, `stk-state`, `@section('page-actions')`.
- `Modules/Account/resources/views/layouts/sidebar.blade.php` — نمط `canAny([...])` لكل مجموعة، `hasPermission('key')` لكل بند، `PlanLock::locked()/url()/reason()/menuBadge()` + صنف `stk-plan-locked` لقفل ما هو خارج الباقة (لا إخفاءً).
- `resources/views/components/create-first.blade.php` — مكوّن عام `<x-create-first :count :route :permission :plan label hint />` لقوائم منسدلة فارغة تعتمد على مورد من قسمٍ آخر.
- `app/Helpers/PermissionHelper.php` — `hasPermission()`/`canAny()`/`canAll()` تُبنى على `User::hasRolePermission()` (نظام `role_id`) — **هذا هو مصدر الحقيقة الوحيد**، لا Spatie `@can`.
- `app/Support/PlanLock.php` — الفرق الحاسم: **صلاحية ناقصة = إخفاء**، **ميزة خارج الباقة = قفلٌ ظاهر يقود لشاشة الاشتراك**. لا يُخلَطان.
- `Modules/Stock/resources/views/layouts/partials/stock-theme.blade.php` — مصدر تعريف كل أصناف `stk-*` (CSS)، يُستدعى عبر `@include('stock::layouts.partials.stock-theme')` من كل وحدة تستعمل هذا النظام (مثال: `Modules/Account/resources/views/master.blade.php:344`). **OnlineStore لا يستدعيه بعد** — أول خطوة تنفيذية.
- `Modules/Stock/resources/views/components/page-head.blade.php` — مكوّن الترويسة الموحّد؛ OnlineStore لا يملك نظيراً له بعد.
- حالة OnlineStore الحالية (عيّنة `brands/index.blade.php`): ترويسة `content-header row` يدوية، بطاقات `stat-card-modern`، جدول `table-card-modern`، **بلا أي حراسة صلاحية على أي زر** (لا `@if(hasPermission())` ولا `PlanLock` في أي شاشة إدارية).

## المفاتيح الجاهزة لكل مورد (لا يُختَرع شيء، تُستعمَل كما سُجِّلت فعلياً)

مصدرها: `app/Constants/permissions/OnlineStorePermissions.php` (صلاحيات النظام) و`app/Constants/planFeatures/OnlineStorePlanFeatures.php` + `StockPlanFeatures`/`PurchasesPlanFeatures`/`AccountPlanFeatures` (حدود الباقة) — كلاهما جرى تسجيله في الجلسة السابقة عند ربط التوجيهات.

| المجلد | صلاحية النظام (بادئة) | ميزة الباقة (`plan:`) |
|---|---|---|
| attributes | `online_store.attributes.*` | `OnlineStorePlanFeatures::CATALOG` |
| brands | `online_store.brands.*` | `OnlineStorePlanFeatures::CATALOG` (والحدّ عند الإنشاء: `StockPlanFeatures::LIMIT_BRANDS`) |
| categories | `online_store.categories.*` | `OnlineStorePlanFeatures::CATALOG` (والحدّ: `StockPlanFeatures::LIMIT_CATEGORIES`) |
| products | `online_store.products.*` | `OnlineStorePlanFeatures::PRODUCTS` (والحدّ: `StockPlanFeatures::LIMIT_PRODUCTS`) |
| orders | `online_store.orders.*` | `OnlineStorePlanFeatures::ORDERS` |
| reviews | `online_store.reviews.*` | `OnlineStorePlanFeatures::FEEDBACK` |
| questions | `online_store.questions.*` | `OnlineStorePlanFeatures::FEEDBACK` |
| banners | `online_store.banners.*` | `OnlineStorePlanFeatures::CONTENT` (الحدّ: `OnlineStorePlanFeatures::LIMIT_CONTENT`) |
| pages | `online_store.pages.*` | `OnlineStorePlanFeatures::CONTENT` |
| coupons | `online_store.coupons.*` | `OnlineStorePlanFeatures::COUPONS` (الحدّ: `LIMIT_COUPONS`) |
| shipping-methods | `online_store.shipping_methods.*` | `OnlineStorePlanFeatures::SHIPPING` (الحدّ: `LIMIT_SHIPPING`) |
| payment-methods | `online_store.payment_methods.view` | بلا `plan:` (مورد نظامي مشترك، عرضٌ فقط) |
| clients | `online_store.clients.*` | `OnlineStorePlanFeatures::CLIENTS` |
| email-templates, email-logs | `online_store.email_templates.*` / `online_store.email_logs.*` | `OnlineStorePlanFeatures::EMAIL` |
| settings | `online_store.settings.*` | `OnlineStorePlanFeatures::SETTINGS` |
| countries, cities | `online_store.geo.*` | `OnlineStorePlanFeatures::SETTINGS` |
| reports | `online_store.reports.*` | `OnlineStorePlanFeatures::REPORTS` |
| dashboard | `online_store.dashboard.view` | `OnlineStorePlanFeatures::DASHBOARD` |
| suppliers | `purchases.suppliers.*` | `PurchasesPlanFeatures::SUPPLIERS` |
| storehouses | `stock.warehouses.*` | `StockPlanFeatures::STOREHOUSES` |
| store_permits | `stock.permits.*` | `StockPlanFeatures::WAREHOUSE_PERMITS` |
| purchases/invoices_purchase | `purchases.invoices.*` | `PurchasesPlanFeatures::INVOICES` |
| treasury | `account.treasury.*` / `account.treasury_transfers.*` | `AccountPlanFeatures::TREASURY` |

القيم الدقيقة (عرض/إنشاء/تعديل/حذف/تبديل حالة...) موجودة حرفياً في constructor كل كنترولر (`Modules/OnlineStore/Http/Controllers/*.php`) — تُقرأ من هناك عند كتابة كل شاشة، لا تُخمَّن.

## البنية التي ستتكرر في كل شاشة

### index.blade.php
- `@include('onlinestore::components.page-head', ['title'=>.., 'icon'=>.., 'sub'=>.., 'crumbs'=>[...]])` بدل `content-header row`.
- `@section('page-actions')` لزر الإنشاء، محاطاً بـ`@if(hasPermission('online_store.x.create'))` ثم (إن وُجد حدّ) بشارة قفل عبر نفس منطق `create-first`/`PlanLock` إن كانت الميزة نفسها مقفولة بالكامل بالباقة — الأصل: **الصلاحية تُخفي الزر، القفل بالباقة يُبقيه ويحوّله لرابط الاشتراك**.
- بطاقات `stk-metrics`/`stk-metric` بدل `stats-row`/`stat-card-modern` (إن وُجدت إحصائيات في الأصل).
- بطاقة `stk-filters` بدل `filter-card`.
- `stk-table-card` بدل `table-card-modern`؛ صفوف الجدول وأزرار الإجراء (تعديل/حذف/تبديل حالة) كل واحد محروسٌ بصلاحيته الدقيقة.
- حالة الفراغ: `stk-empty` بدل `empty-state-modern`.
- الحفاظ على كل منطق AJAX/JS الحالي كما هو (لا تغيير في المسارات ولا أسماء الحقول) — التغيير في العرض والحراسة فقط.

### create.blade.php / edit.blade.php
- `page-head` + `page-actions` (إلغاء + حفظ) — زر الحفظ محروسٌ بصلاحية الإنشاء/التعديل.
- بطاقات مقسّمة بعناوين `stk-table-head`/`stk-table-head__title` بدل `card-header`.
- كل قائمة منسدلة تعتمد على مورد من قسمٍ آخر (مثال: مورّد في الكوبون، تصنيف في المنتج) تحصل على `<x-create-first>` إن كانت قد تكون فارغة.
- عناصر النموذج المطلوبة تحمل صنف `stk-req` على التسمية.

### show.blade.php
- `page-head` + `page-actions` (تعديل/حذف محروسان بصلاحيتيهما، طباعة إن وُجدت).
- `stk-metrics` للأرقام الرئيسية، `stk-tabs` لتفاصيل/سجلّ نشاط إن وُجد، `stk-def`/`stk-def__row` لقوائم البيانات، `stk-timeline` لسجل النشاط، `stk-empty` عند غياب بيانات فرعية.

### pdf / print_normal / print_thermal (فواتير الشراء، الأذون المخزنية، الطلبات)
- تُراجَع مقابل تصميم الطباعة في `Modules/Account/resources/views/finance/expenses/{pdf,print_normal,print_thermal}.blade.php` (تُقرأ عند التنفيذ) — رأسٌ وتذييلٌ موحّدان، لا حراسة صلاحية هنا (شاشة طباعة تُفتح من رابطٍ محروسٍ أصلاً).

### الشريط الجانبي (`layouts/sidebar.blade.php`)
يُعاد بناؤه بالكامل بنفس هيكل `Modules/Account/resources/views/layouts/sidebar.blade.php`:
```php
@php
    use App\Constants\planFeatures\OnlineStorePlanFeatures;
    use App\Support\PlanLock;
    $planLockUrl = PlanLock::url();
    // مصفوفة $plan[key] = ! PlanLock::locked(FEATURE) لكل ميزة، ومصفوفة $planKeys[key] = FEATURE
    // (تُبنى من الجدول أعلاه، بما فيه الميزات المُعاد استعمالها من Stock/Purchases/Account)
@endphp
...
@if (canAny(['online_store.orders.view', 'online_store.clients.view', 'purchases.suppliers.view']))
    <li class="nav-item has-sub ...">
        ...
        @if (hasPermission('online_store.orders.view'))
            <li class="{{ ... }} {{ $plan['orders'] ? '' : 'stk-plan-locked' }}">
                <a href="{{ $plan['orders'] ? route('online_orders.index') : $planLockUrl }}" {!! $planAttrs('orders') !!}>
                    ...{!! $plan['orders'] ? '' : PlanLock::menuBadge() !!}
                </a>
            </li>
        @endif
        ...
    </li>
@endif
```
كل مجموعة من المجموعات التسع الحالية (إدارة الطلبات، إدارة المنتجات، إدارة المخزون، المالية، التسويق، الشحن، المحتوى، الاتصالات، التقارير، الإعدادات) تُغلَّف بـ`canAny([كل صلاحيات بنودها])`، وكل بند بـ`hasPermission()` + قفل الباقة المطابق من الجدول أعلاه.

## ترتيب التنفيذ

1. **البنية التحتية** (تُنفَّذ مركزياً أولاً، شرطٌ لعمل بقية الصفحات):
   - `Modules/OnlineStore/resources/views/master.blade.php`: إضافة `@include('stock::layouts.partials.stock-theme')`.
   - إنشاء `Modules/OnlineStore/resources/views/components/page-head.blade.php` (نسخة مطابقة لمكوّن Stock/Account — لا فرق بين الوحدات في هذا المكوّن).
   - إعادة بناء `Modules/OnlineStore/resources/views/layouts/sidebar.blade.php`.

2. **نموذج مرجعي كامل** (تُنفَّذ يدوياً لتكون المرجع الحرفي لبقية الدفعات): مجلد `coupons` (index/create/edit/show/usages) ومجلد `brands` (index/create/edit) — الأول لثراء أنواع العرض (بطاقات + تبويبات + استخدامات)، والثاني لأنه أبسط عيّنة سبق فحصها.

3. **دفعات مستقلة بالتوازي** (كل دفعة تُنفَّذ بمعزل عن الأخرى، بلا تداخل ملفات):
   - **أ. الكتالوج**: attributes + categories (الأنماط نفسها المستعملة في brands كمرجع).
   - **ب. المحتوى والتسويق**: banners + pages.
   - **ج. الجغرافيا**: countries + cities.
   - **د. العملاء والموردون**: clients + suppliers.
   - **هـ. المنتجات**: products (+ partials: activity_logs/stock_movements/timeline).
   - **و. الطلبات**: orders (index/show/statistics؛ print/print-invoice/shipping-label تُراجَع تصميم طباعةٍ لا حراسة).
   - **ز. التقييمات والأسئلة**: reviews + questions.
   - **ح. البريد**: email-templates + email-logs.
   - **ط. الشحن والدفع**: shipping-methods + payment-methods (عرضٌ فقط، بلا أزرار إنشاء/تعديل).
   - **ي. التقارير ولوحة التحكم**: reports (index + 7 تقارير فرعية) + dashboard/onlinestore/index + dashboard/client.
   - **ك. الإعدادات**: settings/index + settings/group + partials التسع (accounting/advanced/appearance/checkout/filters/general/notifications/seo/shipping/social).
   - **ل. المخازن**: storehouses (index/create/edit/show + inventory_value/inventory_sheet/summary_inventory_operations).
   - **م. الخزائن**: treasury (index/create/edit/show/create_bank/transfer_create).
   - **ن. الأذون المخزنية**: store_permits (index/create/edit/show/manual_disbursement/manual_conversion/opening_balance + partials/pdf).
   - **س. فواتير الشراء**: purchases/invoices_purchase (index/create/edit/show/compact/print/thermal/pdf + partials/notes_pdf) — تُقارَن أيضاً بتصميم طباعة expenses.
   - **ع. الشريط العلوي وملفات العرض المشتركة**: `layouts/{header,header_client,footer,blank,head_ltr,head_rtl}.blade.php`, `layouts/alerts/*`, `partials/_smart-pagination.blade.php` — **مراجعة حراسة الأزرار فقط بلا إعادة تصميم بصري** (هذه هياكل عامة لا صفحات محتوى، ولم يطلبها المستخدم صراحةً كتصميم جديد بل كمرورٍ عليها).

4. **تحقّق نهائي**: `php -l` على كل ملف PHP متأثر (لا يوجد PHP خام هنا لكن التحقق من توازن Blade عبر فتح كل ملف)، ومراجعة بصرية عبر المتصفح لعيّنة من كل دفعة (brands, coupons, orders, settings, sidebar) بعد التنفيذ.

## قيود صارمة أثناء التنفيذ

- **لا تغيير في منطق الخادم**: المسارات، أسماء الحقول (`name="..."`), نقاط AJAX، لا تتغيّر إطلاقاً — التغيير بصريّ + حراسة فقط.
- **لا حذف صلاحية أو ميزة موجودة فعلاً في الشاشة** — فقط إخفاء ما لا يملكه المستخدم وقفل ما هو خارج الباقة.
- **لا اختراع مفتاح صلاحية أو ميزة باقة جديد** — يُستعمَل ما هو مسجَّل فعلاً في `OnlineStorePermissions`/`OnlineStorePlanFeatures`/كتالوجات Stock و Purchases و Account المُعاد استخدامها.
- التصميم الجديد إلزامي التطابق مع نظام `stk-*` — لا إبقاء لأي صنف من `stat-card-modern`/`table-card-modern`/`badge-modern`/`content-header row`/`entity-*` القديم.
