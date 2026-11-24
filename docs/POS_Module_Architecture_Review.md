# مراجعة معمارية شاملة لوحدة نقاط البيع (Modules/Pos)

> تاريخ المراجعة: 2026-08-27
> نطاق المراجعة: `Modules/Pos/Http/Controllers/*` (7 ملفات، 7,835 سطر) + `Modules/Pos/routes/web.php` + `routes/api.php` + `Services/PosProductService.php` + الجداول والصلاحيات والقوالب المرتبطة.

---

## 0. الملخص التنفيذي

الوحدة **تعمل**، لكنها ليست مصممة. هي شاشة بيع مكتوبة داخل Controller واحد بحجم 3,864 سطراً، تحيط بها ست شاشات إدارية مبعثرة بلا طبقة نطاق (Domain Layer) ولا طبقة خدمات ولا سياسة صلاحيات موحّدة. المنطق المحاسبي والمخزني وحساب الضريبة وتوليد QR وإرسال إشعارات تيليجرام كلها داخل الـ Controller نفسه.

**التقييم الصريح:**

| المحور | التقييم | الملاحظة |
|---|---|---|
| فصل المسؤوليات (SoC) | 2/10 | Controller واحد يحوي 60% من كود الوحدة |
| نموذج النطاق (Domain Model) | 2/10 | لا توجد كيانات POS حقيقية؛ كل شيء يُعاد استخدامه من `Invoice` |
| سلامة المحاسبة | 3/10 | قيود تُبتلع أخطاؤها بصمت، وحالات لا تُنشئ قيوداً إطلاقاً |
| الصلاحيات والتدقيق | 2/10 | `ReportsController` بلا أي حماية؛ لا Audit Trail خاص بالـ POS |
| قابلية التوسع (فروع/أجهزة متعددة) | 2/10 | إعدادات مفردة عالمية، لا `branch_id` في الجلسة، لا Offline |
| جودة الطبقة الأمامية للبيانات (Service) | 5/10 | `PosProductService` بداية صحيحة لكنها مُتجاوَزة داخل الـ Controller |
| اكتمال الوظائف مقارنة بأنظمة POS احترافية | 3/10 | نواقص جوهرية (الدرج النقدي، الكوبونات، العملاء، الاسترجاع، الـ Offline) |
| **الإجمالي** | **≈ 2.7 / 10** | نموذج أولي متقدّم (Advanced Prototype)، وليس منتجاً قابلاً للتوسع |

**الحكم:** الوحدة تحتاج إعادة هيكلة جوهرية وليس تحسيناً تدريجياً. الأخطاء ليست تجميلية — هناك **ثقوب محاسبية حقيقية** توثَّق أدناه بالسطر والرقم.

---

## 1. خريطة الوضع الحالي

### 1.1 الأحجام

| الملف | الأسطر | الدوال العامة | الدوال الخاصة | التقييم |
|---|---:|---:|---:|---|
| `SalesStartController.php` | 3,864 | 22 | 30 | **God Controller** |
| `ReportsController.php` | 2,091 | 12 | 8 | تكرار هائل (6 تقارير بنفس البنية) |
| `SessionsController.php` | 1,047 | 12 | 5 | مقبول بنيوياً، معطوب تنفيذياً |
| `DevicesController.php` | 262 | 6 | 0 | CRUD مقبول |
| `SettingsController.php` | 234 | 7 | 0 | صلاحيات خاطئة + دوال ميتة |
| `ShiftController.php` | 218 | 7 | 0 | CRUD مقبول |
| `PosController.php` | 119 | 9 | 0 | **هيكل فارغ + تكرار** |
| `Services/PosProductService.php` | ~430 | 12 | 8 | الجزء الأنظف في الوحدة |

### 1.2 الملفات الموجودة فعلاً في الوحدة

```
Modules/Pos/
├── Http/Controllers/      7 ملفات
├── Services/              1 ملف فقط (PosProductService)
├── Providers/             3 ملفات (قياسية nwidart)
├── database/migrations/   فارغ (.gitkeep فقط)  ← الجداول كلها في database/migrations الجذر
├── database/seeders/      PosDatabaseSeeder (فارغ)
├── resources/views/       21 قالب
├── routes/                web.php + api.php
└── tests/                 فارغ تماماً (.gitkeep)
```

**لا يوجد في الوحدة:** Models، Requests (Form Requests)، Policies، Enums، Events، Listeners، Jobs، Actions، DTOs، Repositories، Resources (API)، Exceptions، Contracts، أي اختبار واحد.

---

## 2. ما صُمِّم بشكل صحيح ✅

هذه الأجزاء يجب **الحفاظ عليها** في إعادة الهيكلة:

1. **مفهوم الجلسة (`PosSession`) كمحور للضبط النقدي.** التصميم صحيح مفاهيمياً: رصيد افتتاحي → معاملات → رصيد متوقع → رصيد فعلي → فرق. وجدول `pos_session_details` كـ Ledger للحركات فكرة سليمة.

2. **حساب المجاميع من الخادم لا من الواجهة.** `SalesStartController.php:153-190` — يُعاد حساب `subtotal` و`tax` و`grandTotal` على الخادم ويُسجَّل الفرق مع القيمة المرسلة. هذا **الشيء الأصح في الوحدة كلها** وهو دفاع صحيح ضد التلاعب من العميل.

3. **`PosProductService` كطبقة خدمة.** فصل استعلامات المنتجات/التصنيفات/العملاء/طرق الدفع في خدمة مستقلة هو الاتجاه المعماري الصحيح — المشكلة أنه لم يُكمَل.

4. **دعم الوحدات (`product_unit_id` + `conversion_factor`) وحفظ `quantity_in_base_unit` و`conversion_factor` و`unit_name` داخل بند الفاتورة.** هذا قرار ممتاز: تجميد قيم التحويل وقت البيع يحمي التكلفة والمرتجع من تغيّر بيانات الوحدات لاحقاً (`SalesStartController.php:3225-3236`).

5. **ربط الجهاز (`CashierDevice`) بمخزن (`store_id`)، واشتقاق مخزن البيع من الجهاز عبر الجلسة** (`getEmployeeWarehouse()` سطر 833). المفهوم صحيح (المخزون يتبع الجهاز لا الموظف).

6. **منع تعدد الجلسات:** فحص الجلسة النشطة للمستخدم وللجهاز وللوردية قبل الفتح (`SessionsController.php:521-536`).

7. **التحقق من الكميات قبل بدء المعاملة**، ودعم المنتجات المجمّعة (`compiled`) والفورية (`Instant`) مع خصم المكوّنات.

8. **فحوصات المرتجع المركّبة** (`SalesStartController.php:2963-3078`): منع تجاوز الكمية الأصلية، ومنع تجاوز المبلغ المتبقي، ومراعاة الوحدة في مفتاح المطابقة `product_id_unit_id`. منطق دقيق ومكتوب بعناية.

9. **كتالوج الصلاحيات `app/Constants/permissions/PosPermissions.php`** — مصمَّم جيداً ويفصل «الإغلاق» عن «تأكيد الإغلاق» (Segregation of Duties). المشكلة أنه غير مُطبَّق في الكود.

---

## 3. الأخطاء المعمارية — مرتبة حسب الخطورة

### 🔴 المستوى 1: أخطاء تُنتج بيانات مالية خاطئة

#### 3.1 القيود المحاسبية تُبتلع أخطاؤها بصمت داخل معاملة ناجحة

```php
// SalesStartController.php:1418-1522
private function createJournalEntries(...) {
    try { /* ... إنشاء القيد ... */ }
    catch (\Exception $e) {
        Log::error('خطأ في إنشاء القيود المحاسبية: ' . $e->getMessage());
        // ← لا rethrow، لا rollback
    }
}
```

نفس النمط في: `createCostOfSalesEntry():1790`، `createPaymentJournalEntry():1921`، `autoSettleInvoice():1948`، `createAutoReceipt():1982`، `createTaxInvoiceRecords():1572`، `createReturnCostOfSalesEntry():3612`.

**الأثر:** `store()` يفتح `DB::beginTransaction()` ثم يُنشئ الفاتورة ويخصم المخزون. إذا فشل القيد المحاسبي لأي سبب (حساب مفقود، عمود ناقص، قيمة null)، يُلتقط الاستثناء داخلياً ويُسجَّل في اللوج، ثم **يصل التنفيذ إلى `DB::commit()` بنجاح**. النتيجة: **فاتورة مباعة، مخزون منقوص، ولا قيد محاسبي**. الميزانية لا تتوازن ولا أحد يعلم.

هذا أخطر عيب في الوحدة.

#### 3.2 المرتجع لا يُنشئ أي قيد محاسبي إذا لم تكن الفاتورة مدفوعة بالكامل

```php
// SalesStartController.php:3431
if ($originalInvoice->payment_status == 1) {
    // ... كل القيود المحاسبية للمرتجع هنا ...
}
// لا يوجد else
```

**الأثر:** استرجاع بضاعة على فاتورة آجلة أو مدفوعة جزئياً → **تُنشأ فاتورة المرتجع، وتُعاد البضاعة للمخزون، ويُسجَّل الحدث في الجلسة، ولا يُنشأ ولا قيد واحد.** المخزون يزيد محاسبياً في الواقع دون أن يزيد في الدفاتر، ورصيد العميل لا يُعدَّل.

#### 3.3 المدفوعات لا تُحفظ في جدول `payments` إطلاقاً

```php
// SalesStartController.php:2148-2166
private function processPayments($invoiceId, $payments)
{
    $totalPaid = collect($payments)->sum('amount');
    $validMethods = PaymentMethod::whereIn('id', ...)->where('status', 0)->count();
    if ($validMethods !== $paymentMethodIds->count()) { throw ...; }
    Log::info('تم معالجة المدفوعات للفاتورة: ' . $invoiceId, [...]);   // ← هذا كل ما تفعله
}
```

الدالة اسمها «معالجة المدفوعات» لكنها **تتحقق وتسجّل في اللوج فقط**. لا `Payment::create()`. التفصيل الوحيد الباقي للمدفوعات هو:
- عمود `invoices.payment_method` (رقم إذا طريقة واحدة، **JSON مضغوط** إذا متعددة — `createInvoice():1359-1376`)،
- وسجل `pos_session_details` بحقلي `cash_amount`/`card_amount`.

**الأثر:** لا يمكن بناء تقرير «تحصيل حسب طريقة الدفع» موثوق، ولا تسوية Card Settlement مع البنك، ولا مطابقة مع مزوّد الدفع.

**مضاعفة:** الشرط `->where('status', 0)` يناقض بقية النظام الذي يستخدم `->where('status', 'active')` (`PosProductService`, `SettingsController:79`). إما أن الفحص يفشل دائماً أو يمرّ دائماً — وفي الحالتين هو فحص وهمي.

#### 3.4 توليد رقم الفاتورة عرضة لسباق (Race Condition)

```php
// SalesStartController.php:1335-1342
$lastInvoice = Invoice::where('type','pos')->orderBy('id','desc')->first();
$nextNumber = $lastInvoice ? intval(substr($lastInvoice->code, -5)) + 1 : 1;
while (Invoice::where('code', 'POS'.str_pad($nextNumber,5,'0',STR_PAD_LEFT))->exists()) { $nextNumber++; }
```

بلا قفل ولا `lockForUpdate()`. جهازا كاشير يبيعان في نفس اللحظة → نفس الرقم. ونفس النمط في المرتجع (سطر 3087) وفي سند القبض (`createAutoReceipt():1960`).

**الأسوأ:** المشروع يملك أصلاً `next_document_number()` في `app/Helpers/DocumentNumberHelper.php` (محمّل عالمياً عبر composer `files`) — وهو النظام الرسمي للترقيم في التطبيق. الوحدة **تتجاهله بالكامل** وتخترع ترقيماً خاصاً بها. وعلى نفس المنوال `generateInvoiceNumber():302` تُنتج رقماً من `Invoice::latest()->id + 1` وتُمرَّر لقالب الطباعة — رقم لا علاقة له بالفاتورة المطبوعة.

#### 3.5 أرصدة الحسابات تُحدَّث بشكل مُشتق ومنفصل عن القيد

```php
// SalesStartController.php:1510-1515, 1884, 1901, 1917, 3525-3533, 3597-3602
$clientAccount->increment('balance', $grandTotal);
$salesAccount->increment('balance', $amountBeforeTax + $discountAmount);
```

الرصيد يُخزَّن في `accounts.balance` ويُحدَّث يدوياً بجانب `journal_entry_details`. مصدران للحقيقة. إذا فشل أحدهما (وهو ما يحدث بالضبط بسبب البند 3.1) يتباعدان بلا رجعة. الصحيح: `balance` يُشتق من القيود أو يُحدَّث ضمن نفس المعاملة الذرية عبر Observer/Service محاسبي واحد.

#### 3.6 المرتجع لا يعالج المنتجات المجمّعة

```php
// SalesStartController.php:3340
if ($product->type == 'products') {
    // ... إعادة الكمية للمخزون ...
}
// لا فرع لـ compiled
```

البيع يخصم مكوّنات المنتج المجمّع (`handleInstantCompiledProduct`, `handleCompiledSubProduct`)، والمرتجع **لا يعيدها**. مخزون المكوّنات يتناقص بشكل دائم مع كل دورة بيع/إرجاع.

#### 3.7 المرتجع يستخدم مخزناً مختلفاً عن البيع

- البيع: `getEmployeeWarehouse()` → مخزن **الجهاز** من الجلسة (سطر 833).
- المرتجع: `DefaultWarehouses::where('employee_id', ...)` → مخزن **الموظف** (سطر 3100-3110).

منطقان مختلفان تماماً لنفس السؤال. (البند نفسه يُخفَّف جزئياً لأن `store_house_id` يُؤخذ من البند الأصلي في السطر 3248، لكن `$storeHouse` المحسوب يُستخدم كبديل ويُربك القراءة.)

---

### 🔴 المستوى 2: أخطاء أمنية

#### 3.8 `ReportsController` بلا أي حماية — إطلاقاً

لا Constructor، لا `middleware()`، لا `hasPermission()`، لا `abort(403)`. تحقّقتُ بالبحث: **صفر تطابق**.

أي مستخدم مصادق عليه يستطيع فتح:
- `/POS/pos_reports/Shift` → مبيعات كل الورديات لكل الموظفين
- `/POS/pos_reports/Prof` → **ربحية كل وردية وكل تصنيف وكل منتج (بالتكلفة)**
- `/POS/pos_reports/Detailed_Shift_Transactions` → كل حركة نقدية

الصلاحية `pos_view_reports` **موجودة في الكتالوج ولم تُستخدم في أي سطر كود**.

#### 3.9 نقطة نهاية تشخيصية مكشوفة في الإنتاج

```php
// ReportsController.php:2033 — route: pos.reports.category.quickTest
public function quickTest() {
    echo "عدد فواتير POS: {$posInvoicesCount}<br>";
    ...
    echo "نتائج بسيطة: <pre>" . print_r($simpleResults->toArray(), true) . "</pre>";
}
```

`echo` مباشر + `print_r` لمخرجات قاعدة البيانات، ومسجَّل في `routes/web.php:74`. يجب حذفه.

#### 3.10 توكن بوت تيليجرام مكتوب صراحةً في الكود مرتين

```php
// SalesStartController.php:1225 و 1279
$telegramApiUrl = 'https://api.telegram.org/bot7642508596:AAHQ8sST762ErqUpX3Ni0f1WTeGZxiQWyXU/sendMessage';
'chat_id' => '@Salesfatrasmart',
```

سرّ فعّال في المستودع. **يجب إبطاله فوراً (Revoke) ونقله إلى `.env`/إعدادات `Modules/Sitting`.** وهو أيضاً استدعاء HTTP متزامن (`Http::timeout(60)`) داخل **معاملة قاعدة بيانات مفتوحة** أثناء البيع — يُبقي القفل مفتوحاً حتى 60 ثانية إذا تعطّلت تيليجرام. يجب أن يكون Queued Job بعد الـ commit.

#### 3.11 تجاوز فحص الصلاحيات لنقاط النهاية «السريعة»

```php
// SalesStartController.php:58-64, 73-79
protected array $fastEndpoints = ['getProductsByCategory','getProductByBarcode','quickSearchProducts','searchClients'];
// ...
if (in_array($currentAction, $this->fastEndpoints)) { return $next($request); }  // ← تخطي كامل
```

بالإضافة إلى مجموعة مسارات منفصلة في `routes/web.php:36-49` تحت `middleware(['auth'])` فقط — **خارج مجموعة `check.branch` وخارج التوطين**. أي موظف مسجَّل دخول (حتى بلا أي صلاحية POS) يقرأ كتالوج المنتجات والأسعار وأرصدة العملاء (`searchClients` تُرجع `balance`).

المبرَّر المكتوب هو «السرعة»، لكن `canAny()` استعلام واحد مُخزَّن مؤقتاً — التبرير غير قائم.

#### 3.12 التحكم بالوصول مبني على اسم الدور

```php
// SessionsController.php:45, 470, 489, 681, 829
$isManager = $user->hasRoleName('manager');
```

خمسة مواضع تحدد نطاق رؤية البيانات (كل الجلسات مقابل جلساتي) بمقارنة **نصية** لاسم الدور. تسمية دور بالعربية أو إنشاء دور «مشرف» بدل «manager» يكسر التحكم بصمت. النظام يملك صلاحيات دقيقة لهذا الغرض بالضبط (`points_sale_view_all_sessions` مقابل `points_sale_view_own_sessions`) وهي **غير مستخدمة في اشتقاق النطاق**.

هذا يخالف أيضاً القاعدة المعتمدة في المشروع بعدم البناء على أسماء الأدوار.

#### 3.13 صلاحيات `SettingsController` خاطئة موضوعياً

```php
// SettingsController.php:19-33
if (!canAny(['points_sale_open_sessions_all', ..., 'points_sale_confirm_close_sessions_own'])) {
    abort(403, 'غير مصرح لك بإدارة جلسات نقاط البيع');
}
```

هذا Controller يُعدِّل **الحسابات المحاسبية الافتراضية، والبنك الافتراضي، وطرق الدفع، وقالب الفاتورة**. وهو محمي بصلاحيات **الجلسات**. أي كاشير يملك `points_sale_open_sessions_own` يستطيع تغيير حساب البنك الذي تُرحَّل إليه كل مبيعات الشبكة.

الصلاحية الصحيحة `pos_manage_settings` موجودة في الكتالوج و**غير مستخدمة**.

#### 3.14 `DevicesController` و`ShiftController` بلا أي فحص صلاحيات

CRUD كامل (إنشاء/تعديل/حذف أجهزة الكاشير والورديات) مفتوح لأي من يجتاز مجموعة المسارات.

---

### 🟠 المستوى 3: كود معطوب أو ميت

#### 3.15 `SessionsController` يستدعي `Log` غير المستورَد → خطأ قاتل

قائمة الـ imports في الملف: `Controller, CashierDevice, PosSession, PosSessionDetail, shifts_pos, OperationLog, Request, Auth, DB`. **لا يوجد `use Illuminate\Support\Facades\Log;`**

ومع ذلك يُستخدم `Log::` في: `suspend():290,307` · `resume():348,366` · `print():396` · `close():963`.

بما أن الملف داخل `namespace Modules\Pos\Http\Controllers`، سيُحل الاسم إلى `Modules\Pos\Http\Controllers\Log` غير الموجود → `Error` (وليس `Exception`) → **لا يلتقطه `catch (\Exception $e)`** → 500.

الأثر العملي: **مسار الخطأ في إغلاق الجلسة معطوب**. إذا فشل الإغلاق لأي سبب، بدل رسالة مفهومة يحصل المستخدم على 500 بعد أن جرى `DB::rollback()`.

#### 3.16 خمس مسارات مسجَّلة تشير إلى دوال غير موجودة

| المسار في `web.php` | الهدف | الحالة |
|---|---|---|
| `pos.reports.category.data` (سطر 65) | `ReportsController@getCategorySalesReport` | ❌ الدالة غير موجودة |
| `pos.reports.category.export` (سطر 67) | `ReportsController@exportCategorySalesReport` | ❌ غير موجودة |
| `pos.test.report` (سطر 74) | `ReportsController@testCategoryReport` | ❌ غير موجودة |
| `pos.reports.category.print` (سطر 76) | `SalesStartController@printCategorySalesReport` | ❌ غير موجودة |
| `pos.sessions.close-form` (سطر 154) | يُعيد `view('pos::sessions.close')` | ❌ **القالب غير موجود** |

الأخير يعني: **زر «إغلاق الجلسة» يؤدي إلى صفحة خطأ.** (القوالب الموجودة في `views/sessions/`: `create, index, print, show, summary` — لا يوجد `close`.)

#### 3.17 ميزة «الفواتير المعلّقة» (Hold/Park) ميتة بالكامل

ثلاث نقاط نهاية كاملة (`getHeldInvoices():2555`, `resumeHeldInvoice():2591`, `deleteHeldInvoice():2634`) كلها تستعلم عن `->where('status','held')`.

بحثتُ في المستودع كله: **لا يوجد سطر واحد يُنشئ فاتورة بـ `status = 'held'`**. لا يوجد endpoint للتعليق أصلاً. الميزة عبارة عن ثلاث واجهات قراءة/حذف فوق بيانات لا تُخلق أبداً.

#### 3.18 `PosController` هيكل فارغ + تكرار

- `store()`, `update()`, `destroy()` — **أجسام فارغة تماماً**.
- `create()`, `show()`, `edit()` تُعيد `pos::create` / `pos::show` / `pos::edit` — **قوالب غير موجودة**.
- مسجَّل في `routes/api.php` كـ `apiResource` تحت `auth:sanctum` → واجهة API تُعيد 500 أو لا شيء.
- `showPaymentForm()` و`confirmPayment()` فيه **نسخة مكرّرة حرفياً** من نفس الدالتين في `SalesStartController:2167,2214` (والمسار الفعلي في `web.php:57-58` يشير إلى نسخة `SalesStartController`، فنسخة `PosController` ميتة).

#### 3.19 استيراد فئة غير موجودة في ملف المسارات

```php
// Modules/Pos/routes/web.php:10-11
use Modules\Sales\Http\Controllers\InvoicesController;   // غير مستخدم
use Modules\Sales\Http\Controllers\PosController;        // ← الملف غير موجود أصلاً
```

`Modules/Sales/Http/Controllers/PosController.php` غير موجود على القرص. والأخطر: هذا الاستيراد **يحجب** `Modules\Pos\Http\Controllers\PosController` داخل ملف المسارات — أي استخدام لـ `PosController::class` هناك سيشير إلى فئة غير موجودة.

#### 3.20 دوال ميتة مؤكدة

| الدالة | الملف:السطر | السبب |
|---|---|---|
| `updateProductStock()` | SalesStart:2227 | جسم الحلقة معلَّق بالكامل — لا تفعل شيئاً |
| `createInvoiceItems()` | SalesStart:2123 | استُبدلت بـ `createInvoiceItemsWithInventoryManagement` |
| `getFilteredCategories()` | SalesStart:175 | مكرّرة في `PosProductService::getCategories()` |
| `createInvoiceLog()` | SalesStart:1307 | معرَّفة ولا تُستدعى |
| `getSessionStats()` / `checkSession()` | SalesStart:2035,2087 | لا مسار مسجَّل لهما |
| `SettingsController::Shift()` / `Create()` | Settings:223,227 | المسارات تشير لـ `ShiftController` |
| `SessionsController::suspend()` / `resume()` | Sessions:276,327 | **لا مسار مسجَّل** (والكود معطوب أصلاً — البند 3.15) |
| `calculateSessionSummary()` / `validateClosingData()` | Sessions:985,1007 | معرَّفتان ولا تُستدعيان |
| `getDetailedShiftDataAjax()` | Reports:1459 | لا مسار |
| جدولا `pos_transactions` / `pos_transaction_items` | migration 2025_12_29_222200 | نموذج POS بديل قديم، لا يستخدمه أي كود |

**التقدير: ما بين 500 و700 سطر كود ميت داخل الوحدة.**

---

### 🟠 المستوى 4: مشاكل بنيوية وأداء

#### 3.21 ثلاث نسخ مختلفة من مولّد QR الزكاة (ZATCA)

| الدالة | بيانات البائع |
|---|---|
| `generateQRCode():2236` | `config('app.company_name')` و `config('app.vat_number')` مع افتراضي `300000000000003` |
| `generateReturnQRCode():3834` | **مكتوب صراحةً**: `'مؤسسة اعمال خاصة للتجارة'` / `'310213567700003'` |
| `generateInvoiceQRCode():3851` | **مكتوب صراحةً**: نفس القيم أعلاه |

ثلاث مصادر حقيقة لبيانات البائع، اثنتان منها ثابتتان في الكود لعميل واحد. في نظام متعدد المستأجرين هذا يعني **رقم ضريبي خاطئ على فواتير الجميع**.

وهو أيضاً **المرحلة الأولى فقط** من فوترة الزكاة (TLV بسيط). لا يوجد: UUID، ولا Hash تسلسلي (PIH)، ولا XML (UBL 2.1)، ولا ختم تشفيري، ولا تكامل مع بوابة فاتورة. الوحدة **غير مؤهلة لمرحلة الربط والتكامل**.

#### 3.22 تكرار منطقي واسع

| المنطق | مواضع التكرار |
|---|---|
| `updateSessionStatistics()` | متطابق حرفياً في `SalesStart:2009` و`Sessions:697` |
| `getProductImageUrl()` | `SalesStart:2445` و`PosProductService::getProductImage()` و`getCategoryImageUrl()` — ثلاث نسخ |
| `searchClients` | `SalesStart:203` و`PosProductService::searchClients()` |
| جلب المنتجات | `SalesStart::fetchProductsFast():2363` نسخة يدوية من `PosProductService::fetchProducts()` |
| حساب التكلفة (COGS) | `createCostOfSalesEntry():1587` و`calculateReturnCostOfGoods():3630` — ~90 سطراً متطابقاً في كل منهما |
| رسائل التحقق (Validation) | ~60 سطراً مكرّرة حرفياً بين `store()` و`update()` في `Devices` و`Shift` |
| منطق التقارير | **6 مرات**: نفس بنية `period` + `date_from/to` + الفلاتر السبعة + دمج المرتجعات، في `Category/Product/Shift/Prof/Cate/Prod` |

#### 3.23 استعلامات N+1 مؤكدة في مسارات ساخنة

| الموضع | المشكلة |
|---|---|
| `SalesStart:2773-2789` | حلقتان متتاليتان على كل الفواتير، **استعلامان لكل فاتورة** |
| `SalesStart:417-435` | داخل `map()`: `Invoice::where(...)->first()` + `InvoiceItem::whereHas(...)->sum()` لكل فاتورة |
| `SalesStart:1272-1276` | `Product::find()` داخل حلقة الإشعارات (المنتجات محمَّلة أصلاً) |
| `createInvoiceItemsWithInventoryManagement:372` | `Product::find()` لكل بند رغم أن `$availableProducts` محمَّلة في `store()` |
| `handleRegularProduct:485` | `SUM(quantity)` على كل مخازن المنتج **لكل بند** |
| `createCostOfSalesEntry:1610-1611` | `Product::find()` + `StoreHouse::find()` داخل حلقة البنود |

#### 3.24 تقارير الربحية تحسب التكلفة من `purchase_price` الحالي

`calculateReturnCostOfGoods` وتقارير `Prof/Cate/Prod` تعتمد على سعر الشراء **الحالي** للمنتج، لا على التكلفة الفعلية وقت البيع. تغيير سعر الشراء اليوم **يُعيد كتابة ربحية الشهر الماضي**. لا يوجد Cost Layer (لا FIFO ولا Moving Average مُجمَّد في البند).

#### 3.25 تقارير POS تحتسب مرتجعات غير خاصة بالـ POS

استعلام المرتجعات في التقارير (مثال `ReportsController:146-156`) يفلتر بـ `invoices.type = 'returned'` فقط، مع `leftJoin` على `pos_sessions`. أي مرتجع أُنشئ من وحدة المبيعات العادية (بلا `session_id`) **يُطرح من مبيعات نقطة البيع**. صافي مبيعات POS مغلوط بشكل منهجي.

#### 3.26 إعدادات POS مفردة عالمية

`PosGeneralSetting::first()` في كل مكان. الجدول بلا `branch_id` ولا `device_id`. النتيجة: **لا يمكن لفرعين أن يكون لهما بنك افتراضي مختلف، ولا عميل افتراضي مختلف، ولا قالب فاتورة مختلف، ولا طرق دفع مختلفة.** وهذا قاتل لأي توسّع.

وفي `createInvoice():1344`: `PosGeneralSetting::find(1)` — يفترض أن معرّف الإعدادات هو 1 حرفياً.

#### 3.27 «الوردية» ليست وردية

```sql
-- shifts_pos: id, name, parent_id, attachment, description, deleted_at, timestamps
```

**لا يوجد `start_time` ولا `end_time` ولا أيام الأسبوع.** هي شجرة تسميات مرتبطة بمستخدم واحد وفرع، لا جدول زمني. لا يمكن بناء «هل الكاشير يفتح جلسة خارج ورديته؟» ولا تقرير التزام بالورديات.

كذلك `cashier_devices` لا يحوي: رقم تسلسلي، عنوان IP، إعدادات طابعة، معرّف تسجيل زكاة للجهاز، أو حد أقصى نقدي في الدرج.

#### 3.28 أرقام حسابات سحرية

```php
Account::find(52)                                  // SalesStart:1476 — «الخصم المسموح به»
Account::where('id', 45)                           // SalesStart:3423 — «مردود المبيعات»
Account::where('id', 50)                           // SalesStart:3422 — احتياطي تكلفة المبيعات
Account::where('name', 'الخزينة الرئيسية')         // SalesStart:3125,3128 — بحث بالاسم العربي!
```

المشروع يملك `App\Constants\SystemAccountIds` وتستخدمه الوحدة جزئياً — ثم تعود لأرقام صريحة وبحث نصي بالاسم في المواضع نفسها.

#### 3.29 مشاكل في ملف المسارات

- `Route::get('/search', ...)` معرَّف **مرتين**: سطر 33 (خارج التوطين والفرع، بلا `auth`!) وسطر 96 (داخلها).
- المسارات السريعة (36-49) خارج `check.branch` و`localeSessionRedirect`.
- `Route::get('/edit/{id}')` مكرَّر حرفياً في `Devices` (سطران 178 و180).
- استخدام `POST` لعمليات التحديث (`toggle-status`, `Shift/create`) بدل `PUT/PATCH`.
- بادئات غير متسقة: `POST/`, `pos_reports/`, `Sales_Start/`, `session/`, `Settings/`, `Devices/`, `Shift/` — مزيج من camelCase و PascalCase و snake_case.
- أسماء مسارات غير متسقة: `sales_start.index` مقابل `POS.sales_start.index` مقابل `pos.sessions.index` مقابل `pos_reports.index` مقابل `transactions` و`details` و`refresh-stats` (بلا بادئة إطلاقاً — تعارض محتمل عالمياً).
- أسماء دوال بأحرف كبيرة: `Category()`, `Product()`, `Shift()`, `Detailed()`, `Prof()`, `Cate()`, `Prod()` — والثلاثة الأخيرة مختصرات غير مفهومة.

#### 3.30 لا اختبارات إطلاقاً

`Modules/Pos/tests/{Feature,Unit}` تحتويان `.gitkeep` فقط. صفر اختبار على وحدة تمسّ النقد والمخزون والمحاسبة.

---

## 4. Controllers غير ضرورية أو قابلة للدمج

### 4.1 يجب حذفها

| Controller | القرار | السبب |
|---|---|---|
| `PosController` | **حذف** | 6 دوال فارغة أو تشير لقوالب غير موجودة + دالتان مكرّرتان ميتتان. مسار الـ API الوحيد المرتبط به معطوب. |

### 4.2 يجب دمجها

| الحالي | الدمج المقترح |
|---|---|
| `DevicesController` + `ShiftController` + `SettingsController` | → قسم **`Http/Controllers/Admin/`** موحّد بـ `BasePosAdminController` يحمل صلاحية `pos_manage_settings` مركزياً. الثلاثة CRUD إداري بنفس النمط ونفس النواقص (بلا صلاحيات، بتحقق مكرر). |

### 4.3 يجب تفكيكها (الأهم)

`SalesStartController` (3,864 سطر) يجب أن يتفكك إلى **10 controllers رفيعة** + **8 خدمات نطاق**. التفصيل في القسم 6.

`ReportsController` (2,091 سطر) يجب أن يتفكك إلى 6 Report Classes + `PosReportController` واحد رفيع.

---

## 5. مسؤوليات في المكان الخطأ

| المسؤولية | مكانها الحالي | مكانها الصحيح |
|---|---|---|
| إنشاء القيود المحاسبية (5 دوال، ~600 سطر) | `SalesStartController` | `App\Services\Accounts` أو `Pos\Services\Accounting\*` |
| حساب تكلفة المبيعات (COGS) | `SalesStartController` × 2 | `App\Services\Inventory\CostingService` (مشترك مع المبيعات والمشتريات) |
| خصم/إعادة المخزون وأذون المخزن | `SalesStartController` (6 دوال) | `Modules\Stock` — الوحدة المالكة للمخزون |
| تنبيهات نقص المخزون وانتهاء الصلاحية | `SalesStartController:1164,1187` | `Modules\Stock` + Event Listener |
| إرسال تيليجرام | `SalesStartController` × 2 | `Modules\Sitting` (قناة إشعارات) + Queued Job |
| توليد QR الزكاة | `SalesStartController` × 3 | `App\Services\Zatca\QrCodeGenerator` (واحد فقط) |
| إنشاء سند القبض | `SalesStartController:1956` | `App\Services\ReceiptService` (موجود في وحدة الحسابات) |
| ترقيم المستندات | `SalesStartController` × 3 | `next_document_number()` الموجود أصلاً |
| منطق المرتجع الكامل (470 سطراً) | `SalesStartController::processReturn` | `PosReturnService` + `PosReturnController` |
| `addTransaction()` كدالة Controller | `SessionsController:721` | `PosSessionService::recordTransaction()` — دالة عامة في Controller لا تخدم أي مسار |
| تصدير CSV | `SessionsController:185` + `ReportsController` × 3 | `Exports/` classes (Laravel Excel أو Streamed Exporter موحّد) |
| قواعد التحقق (~500 سطر) | داخل كل Controller | `Http/Requests/*` |
| اشتقاق نطاق الرؤية (`isManager`) | مكرر 5 مرات في `SessionsController` | `PosSessionPolicy` + `scopeVisibleTo()` |

---

## 6. الهيكلة المقترحة الكاملة

### 6.1 المبدأ: تقسيم داخلي حسب النطاق (Domain-Oriented Modularity)

الوحدة تُقسَّم إلى **خمسة نطاقات فرعية** واضحة الحدود:

```
Pos/
 ├── Terminal    (شاشة البيع — التشغيل اليومي)
 ├── Session     (الجلسة والدرج النقدي والضبط)
 ├── Catalog     (عرض المنتجات والبحث والباركود)
 ├── Config      (الأجهزة، الورديات، الإعدادات، الطابعات)
 └── Reporting   (التقارير والتحليلات)
```

### 6.2 الشجرة الكاملة المقترحة

```
Modules/Pos/
│
├── Domain/                                   ← منطق الأعمال النقي (بلا Laravel قدر الإمكان)
│   ├── Cart/
│   │   ├── CartLine.php                      (DTO: منتج، وحدة، كمية، سعر، خصم، ضريبة)
│   │   ├── Cart.php                          (تجميع السطور)
│   │   └── CartCalculator.php                ← منطق الحساب الوحيد في النظام
│   ├── Pricing/
│   │   ├── PriceResolver.php                 (قائمة أسعار / سعر وحدة / سعر عميل)
│   │   ├── DiscountEngine.php                (خصم سطر، خصم فاتورة، عروض)
│   │   └── PromotionEngine.php               (اشترِ 1 واحصل على 1، حزم، كوبونات)
│   ├── Tax/
│   │   └── TaxCalculator.php                 (Inclusive / Exclusive، معفى، متعدد الشرائح)
│   ├── Payment/
│   │   ├── PaymentSplit.php                  (دفع مختلط)
│   │   ├── PaymentGatewayContract.php
│   │   └── Gateways/{ShabakaGateway, CashGateway, CreditGateway, WalletGateway}.php
│   └── Enums/
│       ├── PosSessionStatus.php              (open, suspended, pending_review, closed)
│       ├── PosTransactionType.php            (sale, return, void, pay_in, pay_out, drop, opening, closing)
│       ├── PosOrderStatus.php                (draft, held, completed, voided, refunded)
│       └── PosDeviceStatus.php
│
├── Services/                                 ← تنسيق العمليات (Application Layer)
│   ├── Sale/
│   │   ├── CreateSaleService.php             ← المنسّق الوحيد لعملية البيع
│   │   ├── HoldOrderService.php              (تعليق/استئناف — الميزة الناقصة)
│   │   └── VoidSaleService.php               (إلغاء فاتورة — غير موجود إطلاقاً)
│   ├── Return/
│   │   └── ProcessReturnService.php
│   ├── Session/
│   │   ├── OpenSessionService.php
│   │   ├── CloseSessionService.php
│   │   ├── ConfirmSessionCloseService.php    ← الصلاحية موجودة والتنفيذ غائب
│   │   └── CashDrawerService.php             (pay-in / pay-out / drop / count)
│   ├── Catalog/
│   │   ├── PosCatalogService.php             (يستوعب PosProductService الحالي)
│   │   └── BarcodeResolver.php
│   ├── Accounting/
│   │   └── PosJournalPoster.php              ← الواجهة الوحيدة نحو المحاسبة
│   ├── Inventory/
│   │   └── PosStockMovementService.php       ← الواجهة الوحيدة نحو Modules\Stock
│   ├── Printing/
│   │   ├── ReceiptRenderer.php
│   │   └── PrinterProfileResolver.php
│   └── Settings/
│       └── PosSettingsResolver.php           (عام → فرع → جهاز، بتوريث وكاش)
│
├── Http/
│   ├── Controllers/
│   │   ├── Terminal/
│   │   │   ├── TerminalController.php        (index فقط — شاشة البيع)
│   │   │   ├── CatalogController.php         (products/categories/barcode/quick-search)
│   │   │   ├── CartController.php            (تسعير حي، تحقق، حساب)
│   │   │   ├── CheckoutController.php        (store)
│   │   │   ├── HeldOrderController.php       (hold/list/resume/delete)
│   │   │   ├── ReturnController.php          (available/details/process)
│   │   │   ├── PaymentController.php         (بوابات الدفع)
│   │   │   └── ReceiptController.php         (طباعة بيع/مرتجع/جلسة)
│   │   ├── Session/
│   │   │   ├── SessionController.php         (index/create/store/show)
│   │   │   ├── SessionCloseController.php    (closeForm/close/confirm)
│   │   │   └── CashDrawerController.php      (pay-in/pay-out/drop/count)
│   │   ├── Admin/
│   │   │   ├── DeviceController.php
│   │   │   ├── PrinterController.php         ← جديد
│   │   │   ├── ShiftController.php
│   │   │   ├── PaymentMethodController.php   ← جديد (تفعيل + ربط حسابات)
│   │   │   └── SettingsController.php
│   │   └── Reports/
│   │       └── PosReportController.php       (رفيع — يفوّض لـ Reports/*)
│   ├── Requests/                             ← كل التحقق ينتقل هنا
│   │   ├── StoreSaleRequest.php
│   │   ├── ProcessReturnRequest.php
│   │   ├── OpenSessionRequest.php
│   │   ├── CloseSessionRequest.php
│   │   ├── StoreDeviceRequest.php / UpdateDeviceRequest.php
│   │   ├── StoreShiftRequest.php / UpdateShiftRequest.php
│   │   └── UpdatePosSettingsRequest.php
│   ├── Resources/                            ← تنسيق JSON موحّد
│   │   ├── PosProductResource.php
│   │   ├── PosOrderResource.php
│   │   └── PosSessionResource.php
│   └── Middleware/
│       ├── EnsureActiveSession.php           ← بدل تكرار الفحص 12 مرة
│       └── EnsurePosPermission.php
│
├── Models/                                   ← تنتقل من app/Models إلى هنا
│   ├── PosSession.php · PosSessionDetail.php · PosCashMovement.php
│   ├── PosOrder.php · PosOrderLine.php · PosOrderPayment.php
│   ├── PosDevice.php · PosPrinter.php · PosShift.php
│   ├── PosSetting.php · PosHeldOrder.php · PosAuditLog.php
│   └── PosCustomerDisplayLog.php
│
├── Policies/
│   ├── PosSessionPolicy.php                  (own/all + confirm)
│   ├── PosOrderPolicy.php                    (discount / price-edit / void / refund)
│   └── PosSettingsPolicy.php
│
├── Events/  &  Listeners/
│   ├── SaleCompleted → PostSaleJournal, DeductStock, NotifyChannels, UpdateSessionStats
│   ├── ReturnProcessed → PostReturnJournal, RestoreStock
│   ├── SessionOpened / SessionClosed / SessionCloseConfirmed
│   └── CashVarianceDetected → NotifySupervisor
│
├── Jobs/                                     ← كل عمل بطيء يخرج من مسار البيع
│   ├── SendPosNotificationJob.php
│   ├── SyncOfflineOrdersJob.php
│   └── SubmitInvoiceToZatcaJob.php
│
├── Reports/                                  ← كل تقرير كائن مستقل
│   ├── AbstractPosReport.php                 (الفترة + الفلاتر + التصدير — مرة واحدة)
│   ├── CategorySalesReport.php · ProductSalesReport.php
│   ├── ShiftSalesReport.php   · ShiftTransactionsReport.php
│   ├── ProfitabilityReport.php · PaymentMethodReport.php
│   ├── CashVarianceReport.php  · VoidAndDiscountReport.php
│   └── Exports/{CsvExporter, XlsxExporter, PdfExporter}.php
│
├── database/migrations/                      ← الجداول تعود لملكية الوحدة
├── config/pos.php                            ← بدل ['name' => 'Pos'] الحالي
├── routes/{web.php, api.php}
└── tests/{Feature,Unit}                      ← إلزامي
```

### 6.3 الكيانات الأساسية والعلاقات

**جداول جديدة أو مُعاد تصميمها:**

| الكيان | الحقول المفتاحية الجديدة | الغرض |
|---|---|---|
| `pos_sessions` | + `branch_id`, `closed_by`, `confirmed_by`, `confirmed_at`, `confirmation_notes`, `expected_cash`, `expected_card`, `expected_other` | ربط بالفرع + دورة تأكيد الإغلاق |
| `pos_orders` | كيان مستقل عن `invoices`: `session_id`, `device_id`, `branch_id`, `cashier_id`, `customer_id`, `status`, `invoice_id` (nullable) | فصل «طلب نقطة البيع» عن «الفاتورة المحاسبية» |
| `pos_order_lines` | `unit_id`, `conversion_factor`, `unit_cost_at_sale`, `line_discount`, `tax_rate`, `tax_amount`, `promotion_id` | **تجميد التكلفة وقت البيع** يحل البند 3.24 |
| `pos_order_payments` | `order_id`, `payment_method_id`, `amount`, `gateway_ref`, `card_last4`, `status`, `settled_at` | **الجدول الغائب حالياً** (البند 3.3) |
| `pos_cash_movements` | `session_id`, `type` (pay_in/pay_out/drop/count), `amount`, `reason`, `approved_by`, `journal_entry_id` | الدرج النقدي — غائب تماماً |
| `pos_held_orders` | `session_id`, `cashier_id`, `label`, `payload` (JSON), `expires_at` | تفعيل ميزة التعليق الميتة |
| `pos_devices` | + `branch_id`, `serial_number`, `ip_address`, `zatca_device_id`, `max_cash_in_drawer`, `default_printer_id`, `is_offline_capable` | |
| `pos_printers` | `device_id`, `name`, `type` (thermal/a4/kitchen/label), `connection` (usb/network/cloud), `paper_width`, `copies` | **غائب تماماً** |
| `pos_shifts` | + `start_time`, `end_time`, `days_of_week`, `branch_id`, `grace_minutes` | تحويل «التسمية» إلى وردية حقيقية |
| `pos_settings` | + `branch_id` (nullable), `device_id` (nullable) — بتوريث ثلاثي المستويات | يحل البند 3.26 |
| `pos_audit_logs` | `session_id`, `order_id`, `user_id`, `action`, `before`, `after`, `ip`, `device_id`, `approved_by` | **Audit Trail غائب** |

**مخطط العلاقات:**

```
Branch ──1:N── PosDevice ──1:N── PosPrinter
   │              │
   │              └──1:N── PosSession ──N:1── PosShift
   │                          │  │
   │                          │  ├──1:N── PosOrder ──1:N── PosOrderLine ──N:1── Product/ProductUnit
   │                          │  │            │
   │                          │  │            ├──1:N── PosOrderPayment ──N:1── PaymentMethod ──N:1── Account
   │                          │  │            ├──N:1── Client
   │                          │  │            ├──1:1── Invoice (المستند المحاسبي)
   │                          │  │            └──1:N── PosOrder (returns، عبر parent_order_id)
   │                          │  │
   │                          │  ├──1:N── PosCashMovement ──1:1── JournalEntry
   │                          │  └──1:N── PosHeldOrder
   │                          │
   │                          └──N:1── User (cashier) / User (closed_by) / User (confirmed_by)
   │
   └──1:N── StoreHouse ──1:N── ProductDetails
```

**قرار معماري محوري:** فصل `PosOrder` عن `Invoice`.
اليوم `Invoice` يحمل نوعاً `pos` وحقولاً مقحمة (`session_id`, `payment_method` كـ JSON أحياناً). هذا يُثقل جدول الفواتير ويمنع تمثيل الحالات الخاصة بنقطة البيع (مسوّدة، معلّقة، ملغاة، غير مرحّلة). الصحيح: `PosOrder` هو المستند التشغيلي، و`Invoice` هو المستند المحاسبي الذي يُولَّد عنه.

---

## 7. النواقص مقارنة بأنظمة POS الاحترافية

### 7.1 نواقص حرجة (تمنع التشغيل التجاري الجاد)

| # | الناقص | الحالة اليوم |
|---|---|---|
| 1 | **العمل دون اتصال (Offline Mode)** | غائب تماماً. انقطاع الشبكة = توقف البيع. هذا معيار أساسي في أي POS. |
| 2 | **إلغاء فاتورة (Void)** | غائب. لا يمكن إلغاء بيع خاطئ إلا بمرتجع كامل. |
| 3 | **الدرج النقدي (Cash In/Out/Drop)** | غائب. لا يمكن سحب نقد وسط الوردية ولا إيداع فكة. |
| 4 | **تسجيل المدفوعات** | لا تُحفظ (البند 3.3). |
| 5 | **تأكيد إغلاق الجلسة** | الصلاحية موجودة، التنفيذ غائب — الكاشير يقفل عهدته بنفسه. |
| 6 | **قيد محاسبي لفرق الصندوق** | الفرق يُسجَّل في `pos_session_details` فقط، بلا أثر في دفتر الأستاذ. |
| 7 | **تعليق الفاتورة (Hold/Park)** | ميزة ميتة (البند 3.17). |
| 8 | **Audit Trail** | غائب. لا سجل لمن غيّر سعراً أو منح خصماً أو ألغى بنداً. |
| 9 | **موافقة المشرف (Supervisor Override)** | غائبة. لا PIN ولا تصعيد لخصم يتجاوز حداً أو لإلغاء. |
| 10 | **فوترة الزكاة المرحلة الثانية** | QR مرحلة أولى فقط، بلا XML/UUID/PIH/ختم/ربط بوابة. |

### 7.2 نواقص وظيفية أساسية

| المجال | الناقص |
|---|---|
| **المنتجات** | المتغيّرات (Variants: مقاس/لون)، الإضافات (Modifiers)، المنتجات الموزونة (Scale/PLU)، القوائم المفضلة (Favorites Grid)، المنتجات السريعة، الـ Combo/Bundle |
| **التسعير** | قوائم أسعار متعددة، أسعار حسب فئة العميل، حدود دنيا للسعر، تسعير بالكمية (Tiered) |
| **الخصومات والعروض** | لا محرك عروض إطلاقاً: لا كوبونات، لا Buy X Get Y، لا خصم على مستوى السطر (`'discount' => 0` ثابت في كل بند!)، لا خصومات مجدولة، لا حد أقصى للخصم بالصلاحية |
| **العملاء** | لا إنشاء عميل سريع من الشاشة، لا نقاط ولاء في الـ POS رغم وجود `Modules/PointsAndBlances`، لا حد ائتماني، لا رصيد محفظة، لا عرض تاريخ مشتريات |
| **الدفع** | لا تقسيط، لا دفع جزئي متعدد الجلسات، لا قسائم شرائية، لا رد نقدي على البطاقة، لا تسوية بطاقات (Settlement)، لا إكرامية |
| **الفواتير** | لا مسوّدات، لا عروض أسعار، لا تحويل عرض→فاتورة، لا إعادة طباعة مؤرشفة، لا إرسال بالبريد/واتساب |
| **الجلسات** | لا تسليم وردية (Handover) بين كاشيرين، لا عدّ نقدي مفصّل بالفئات (Denomination Count)، لا Blind Close، لا إغلاق تلقائي للجلسات المنسية |
| **الأجهزة** | لا طابعات، لا شاشة عميل، لا ميزان، لا قارئ بطاقات، لا درج نقدي فعلي، لا صادر مطبخ (KDS) |
| **المخزون** | لا نقل بين المخازن من الشاشة، لا جرد سريع، لا حجز مخزون للطلبات المعلّقة |
| **متعدد الفروع** | إعدادات عالمية، لا `branch_id` في الجلسة، تقارير الفرع فلتر اختياري لا نطاق إلزامي |

### 7.3 نواقص تقنية

- لا Rate Limiting على نقاط البحث المفتوحة
- لا Idempotency Key على `store()` → ضغط مزدوج = فاتورتان
- لا Optimistic/Pessimistic Locking على المخزون → بيع متزامن يمكن أن يُنقص تحت الصفر
- لا API Resources → أشكال JSON غير متسقة بين نقاط النهاية
- لا Feature Flags
- لا Health Check للجهاز/الطابعة
- صفر اختبارات

---

## 8. الواجهات المقترحة

### 8.1 الشريط الجانبي (Sidebar)

**الحالي:** 4 روابط فقط (`sales_start.index`, `pos.sessions.index`, `pos.settings.index`, `pos_reports.index`).

**المقترح — مبني على الصلاحيات لا على الأدوار:**

```
🖥️  نقاط البيع
│
├── 🛒  التشغيل اليومي
│   ├── شاشة البيع                    → pos.terminal            [points_sale_*]
│   ├── الفواتير المعلّقة              → pos.held-orders         [points_sale_hold_orders]
│   ├── المرتجعات                      → pos.returns             [points_sale_process_returns]
│   └── جلستي الحالية                  → pos.session.current     [points_sale_view_own_sessions]
│
├── 🗄️  الجلسات والورديات
│   ├── سجل الجلسات                   → pos.sessions.index      [view_own | view_all]
│   ├── جلسات بانتظار التأكيد          → pos.sessions.pending    [confirm_close_*]
│   ├── حركات الصندوق                  → pos.cash-drawer         [pos_manage_cash_drawer]
│   └── فروقات الصندوق                 → pos.reports.variance    [pos_view_reports]
│
├── ⚙️  الإعدادات والإدارة
│   ├── الإعدادات العامة               → pos.settings.general    [pos_manage_settings]
│   ├── أجهزة الكاشير                  → pos.devices             [pos_manage_devices]
│   ├── الطابعات                       → pos.printers            [pos_manage_devices]
│   ├── الورديات                       → pos.shifts              [pos_manage_shifts]
│   ├── طرق الدفع المفعّلة              → pos.payment-methods     [pos_manage_settings]
│   └── العروض والخصومات               → pos.promotions          [pos_manage_promotions]
│
└── 📊  التقارير
    ├── التقارير الأساسية              → pos.reports.index       [pos_view_reports]
    ├── تقارير الربحية                 → pos.reports.profit      [pos_view_profit_reports]  ← صلاحية منفصلة
    └── لوحة التحليلات                 → pos.reports.dashboard   [pos_view_reports]
```

> ملاحظة تنفيذية: عند إضافة الروابط استخدم توجيه `@permission(...) ... @endpermission` المسجَّل في `AppServiceProvider` (لا `@can`)، وتحقّق أولاً من أن `layouts.sidebar` الذي تعدّله هو الذي يستدعيه `master.blade.php` الخاص بالوحدة.

### 8.2 شاشات التشغيل اليومي

| الشاشة | الحالة | المطلوب |
|---|---|---|
| شاشة البيع | موجودة | إضافة: خصم سطر، تعديل سعر بموافقة، شبكة المفضلة، اختصارات لوحة مفاتيح، مؤشر Offline، شاشة عميل |
| فتح جلسة | موجودة | إضافة: عدّ نقدي بالفئات، اختيار فرع، تحذير خارج الوردية |
| إغلاق جلسة | ❌ **القالب غير موجود** | بناؤها: عدّ بالفئات، Blind Close، مقارنة متوقع/فعلي، تبرير الفرق، إرسال للتأكيد |
| تأكيد الإغلاق (مشرف) | ❌ غائبة | جديدة |
| المرتجعات | موجودة (Modal) | شاشة مستقلة + بحث بالباركود/الرقم + سبب الإرجاع |
| الفواتير المعلّقة | ❌ ميتة | تفعيلها بالكامل |
| حركات الصندوق | ❌ غائبة | جديدة: إيداع/سحب/تحويل للخزينة |
| إلغاء فاتورة | ❌ غائبة | جديدة، بموافقة مشرف وسبب إلزامي |
| إعادة الطباعة | جزئية | سجل طباعة + علامة «نسخة» |

### 8.3 شاشات الإدارة والإعدادات

| الشاشة | الحالة |
|---|---|
| الإعدادات العامة | موجودة — تحتاج تقسيماً لتبويبات (عام / محاسبة / ضرائب / طباعة / سلوك) وتوريثاً حسب الفرع/الجهاز |
| الأجهزة | موجودة — تحتاج: فرع، رقم تسلسلي، IP، طابعة افتراضية، حد نقدي، سجل الجلسات |
| الطابعات | ❌ غائبة |
| الورديات | موجودة كتسمية — تحتاج أوقاتاً وأيام أسبوع |
| طرق الدفع | جزئية (تفعيل فقط) — تحتاج ربطاً بحساب محاسبي وعمولة وإعدادات تسوية |
| العروض والخصومات | ❌ غائبة |
| حدود الصلاحيات | ❌ غائبة (حد الخصم، حد الإلغاء، حد فرق الصندوق) |

---

## 9. التقارير

### 9.1 الموجود (6 تقارير)

`Category` · `Product` · `Shift` · `Detailed_Shift_Transactions` · `Shift_Profitability` · `Category_Profitability` · `Product_Profitability`

**المشاكل:** بلا صلاحيات · تكرار سداسي للمنطق · تحتسب مرتجعات غير POS · تكلفة من السعر الحالي · تصدير CSV فقط · جزء من مساراتها معطوب.

### 9.2 التقارير الأساسية المطلوبة (غائبة)

| التقرير | لماذا هو أساسي |
|---|---|
| **الملخص اليومي (Z-Report / X-Report)** | التقرير رقم 1 في أي POS. غائب تماماً. |
| **تحصيل حسب طريقة الدفع** | مستحيل حالياً لعدم حفظ المدفوعات |
| **فروقات الصندوق** | مؤشر الرقابة النقدية الأول |
| **الإلغاءات والخصومات** | كشف التلاعب — غائب |
| **المرتجعات** | تقرير مستقل بالأسباب والموظفين |
| **الضريبة (VAT Summary)** | إلزامي للإقرار الضريبي |
| **أداء الكاشير** | متوسط الفاتورة، عدد المعاملات/ساعة، نسبة الإلغاء |
| **مقارنة الفروع** | لا يمكن دون `branch_id` في الجلسة |

### 9.3 التقارير المتقدمة

- الذروة الزمنية (Heatmap بالساعة/اليوم)
- سلة السوق (المنتجات المُشتراة معاً)
- تحليل العملاء (تكرار الشراء، القيمة الدائمة)
- بطء الحركة (منتجات معروضة ولا تُباع)
- أثر العروض على الهامش
- تسوية البطاقات مقابل كشف البنك
- تحليل الاتجاه (مقارنة فترات)
- تدقيق الجلسات (كل جلسة بفرقها ومن أكّدها)

---

## 10. جرد المديولات: ما يُحذف · ما يُعدَّل · ما يُضاف

> هذا القسم أُضيف بعد جولة فحص ثانية شملت **كل ما يمسّ نقاط البيع خارج `Modules/Pos`**. وقد كشفت الجولة ثلاثة تعارضات معمارية لم تظهر في الفحص الأول، وهي موثّقة في 10.5.

### 10.1 الجرد الكامل لكل ما يخصّ نقاط البيع

**أ) داخل `Modules/Pos`**

| العنصر | العدد | القرار |
|---|---:|---|
| Controllers | 7 | 1 حذف · 6 تعديل/تفكيك |
| Services | 1 | تعديل وتوسيع |
| Providers | 3 | تعديل طفيف |
| Views | 21 | 1 حذف · الباقي تعديل + 9 جديدة |
| Routes | ملفان | إعادة كتابة كاملة |
| Migrations | 0 | ← الجداول كلها خارج الوحدة |
| Models / Requests / Policies / Events / Tests | 0 | إضافة كاملة |

**ب) خارج `Modules/Pos` — وهذا هو الجزء الذي كان غائباً عن التحليل**

| المكوّن | الموقع | القرار |
|---|---|---|
| `PosSession` · `PosSessionDetail` · `PosGeneralSetting` · `CashierDevice` · `shifts_pos` | `app/Models/` | **نقل** إلى الوحدة + إعادة تسمية |
| `PosTransaction` · `PosTransactionItem` | `app/Models/` | **حذف** |
| `CheckPosSession` | `app/Http/Middleware/` | **تفعيل** (مسجَّل وغير مستخدم!) |
| `PosPermissions` | `app/Constants/permissions/` | **تعديل** — توسيع |
| `ApplicationRegistry` (مفتاح `pos`) | `app/Services/Settings/` | يبقى كما هو |
| 8 ملفات هجرة POS | `database/migrations/` | تبقى + هجرات جديدة داخل الوحدة |
| مجموعة إعدادات `pos` (5 مفاتيح) | `Modules/Branches/Services/BranchSettingsCatalog.php` | **تعديل جوهري** — مفاتيح ميتة يجب تفعيلها |
| `DailyClosingController` + `DailyClosingLedger` | `Modules/Sales/` | **حسم تعارض** — نظام إقفال نقدي موازٍ |
| `DashboardController` · `SalesReportsController` · `InvoicesController` · `CreditNotificationController` | `Modules/Sales/` | تعديل قراءة `type='pos'` بعد فصل `PosOrder` |
| شاشات المنتج (`available_pos`) | `Modules/Stock` · `Modules/OnlineStore` | تبقى كما هي |
| `RewardRedemptionController` | `Modules/Ecosystem` | نقطة تكامل الولاء |
| `PlanLimitObserver` · `UsageReporter` | `app/Observers` · `app/Services/Fawtra` | **تعديل** — لا يوجد `PosPlanFeatures` |
| `DataSectionCatalog` · `ArabicSchemaTranslator` | `app/Services/Backup/` | تحديث بعد تغيّر الجداول |
| `resources/views/dashboard.blade.php` | الجذر | تعديل بطاقات POS |

---

### 10.2 ما سيُحذف نهائياً ❌

**أ) ملفات كاملة**

| الملف | السبب |
|---|---|
| `Modules/Pos/Http/Controllers/PosController.php` | 3 دوال بأجسام فارغة + 3 تُعيد قوالب غير موجودة + دالتان مكرّرتان ميتتان |
| `Modules/Pos/routes/api.php` (محتواه) | `apiResource` يشير للـ Controller أعلاه — واجهة API معطوبة |
| `Modules/Pos/resources/views/payments/shabaka-mock.blade.php` | محاكاة بوابة دفع وهمية في الإنتاج — انظر التحذير أدناه |
| `app/Models/PosTransaction.php` | نموذج POS قديم مهجور |
| `app/Models/PosTransactionItem.php` | نفس السبب |

> ⚠️ **تحذير أمني لم يُبرَز كفاية في القسم 3:** المسار `POST /POS/shabaka-payment/{invoiceId}/confirm` (`web.php:58`) محمي بـ `auth` فقط. الدالة `confirmPayment()` تُدرج صفّاً في `payments` بـ `status = 'completed'` و`payment_method_id = 1` **مكتوباً صراحةً**، بمبلغ ورقم عملية يأتيان من الطلب، **بلا أي تحقق من بوابة دفع حقيقية وبلا فحص ملكية الفاتورة**. أي مستخدم مصادق عليه يستطيع تعليم أي فاتورة كمدفوعة. يجب حذف المسار والدالة والقالب فوراً، لا تأجيلها.

**ب) جداول قاعدة بيانات**

| الجدول | السبب |
|---|---|
| `pos_transactions` | نموذج POS بديل قديم — لا يستخدمه أي سطر كود |
| `pos_transaction_items` | نفس السبب |

**ج) دوال ميتة (~600 سطر)**

| الدالة | الملف:السطر |
|---|---|
| `updateProductStock()` | SalesStart:2227 — جسم الحلقة معلَّق |
| `createInvoiceItems()` | SalesStart:2123 — مستبدلة |
| `getFilteredCategories()` | SalesStart:175 — مكرّرة في الخدمة |
| `createInvoiceLog()` | SalesStart:1307 — لا تُستدعى |
| `generateInvoiceNumber()` | SalesStart:302 — تُنتج رقماً لا علاقة له بالفاتورة |
| `showPaymentForm()` / `confirmPayment()` | SalesStart:2214,2167 — تنتقلان لبوابة دفع حقيقية |
| `quickTest()` | Reports:2033 — `echo` + `print_r` لبيانات DB |
| `getDetailedShiftDataAjax()` | Reports:1459 — بلا مسار |
| `Shift()` / `Create()` | Settings:223,227 |
| `calculateSessionSummary()` / `validateClosingData()` | Sessions:985,1007 |

**د) مسارات وآليات**

| العنصر | السبب |
|---|---|
| 5 مسارات معطوبة | أهدافها غير موجودة (جدول البند 3.16) |
| `Route::get('/search')` سطر 33 | مكرر، وخارج `auth` و`check.branch` كلياً |
| مجموعة «Fast AJAX» (سطور 36-49) | تلتف على التوطين والفرع |
| مصفوفة `$fastEndpoints` + تخطّيها | ثغرة صلاحيات بمبرر أداء غير قائم |
| `use Modules\Sales\...\PosController` و`InvoicesController` | فئة غير موجودة + تحجب فئة الوحدة |
| `Route::get('/edit/{id}')` المكرر | Devices — سطران 178 و180 |
| توكن تيليجرام المكتوب صراحةً | SalesStart:1225,1279 — **يُبطَل ويُنقل للإعدادات** |
| بيانات البائع الثابتة في QR | SalesStart:3836-3837, 3853-3854 |
| أرقام الحسابات السحرية (45, 50, 52) والبحث بالاسم العربي | SalesStart:1476, 3422-3423, 3125 |

---

### 10.3 ما سيُعدَّل فقط (يبقى ويُصحَّح) ✏️

| العنصر | حجم التغيير | التعديلات |
|---|---|---|
| `PosProductService` | **توسيع** | يصبح `Catalog/PosCatalogService` + `BarcodeResolver`؛ يُحذف تكرار الصور؛ تُحقن إعدادات الفرع بدل `StoreHouse::first()` |
| `DevicesController` | متوسط | صلاحية `pos_manage_devices` · التحقق → FormRequest · منع الحذف عند وجود جلسات · حقول جديدة (فرع، تسلسلي، IP، طابعة، حد نقدي) · `try/catch` في `destroy()` |
| `ShiftController` | متوسط | صلاحية `pos_manage_shifts` · التحقق → FormRequest · **إضافة `start_time`/`end_time`/`days_of_week`** لتصير وردية حقيقية |
| `SettingsController` | كبير | **تصحيح الصلاحية** لـ `pos_manage_settings` · حذف الدالتين الميتتين · توريث الإعدادات (عام→فرع→جهاز) · **قراءة مفاتيح `Modules/Branches`** · تبويبات |
| `SessionsController` | كبير | إضافة `use ...Log` · استبدال `hasRoleName('manager')` بـ `PosSessionPolicy` (5 مواضع) · تسجيل مسارَي suspend/resume · **بناء قالب `sessions/close`** · إضافة تأكيد الإغلاق · نقل المنطق لخدمات |
| `ReportsController` | تفكيك | حماية بالصلاحيات · حذف التشخيص · تفكيك إلى `Reports/*` فوق `AbstractPosReport` · تصحيح فلترة المرتجعات |
| `SalesStartController` | **تفكيك كامل** | 3,864 سطر → 8 controllers رفيعة + 8 خدمات نطاق |
| `CheckPosSession` middleware | **تفعيل** | مسجَّل كـ `check.pos.session` في `bootstrap/app.php:149` **ولا يُستخدم في أي مسار** — يحل محلّ 12 فحص جلسة مكرّراً داخل الـ Controller |
| `PosPermissions` | توسيع | إضافة: `pos_manage_devices` · `pos_manage_shifts` · `pos_manage_cash_drawer` · `pos_void_invoice` · `pos_approve_refund` · `pos_view_profit_reports` · `pos_manage_promotions` · `pos_offline_mode` |
| `BranchSettingsCatalog` (Branches) | تفعيل | 5 مفاتيح POS موجودة و**لا يقرؤها أي سطر في `Modules/Pos`** — تُربط بالتنفيذ الفعلي |
| `shifts_pos.php` | إعادة تسمية | → `PosShift` (يخالف PSR ويربك القراءة) |
| `Invoice.php` | تنظيف | إزالة حقول POS المقحمة بعد فصل `PosOrder` |
| `PlanLimitObserver` / `UsageReporter` | تعديل | ربط بـ `PosPlanFeatures` الجديد |
| `ApplicationRegistry` | بلا تغيير | بادئة `POS` صحيحة ومطابقة |

**نقل الملكية (بلا تغيير منطق):**
- نماذج POS من `app/Models/` → `Modules/Pos/Models/`
- منطق المخزون وأذون المخزن → `Modules/Stock`
- منطق القيود المحاسبية → `Modules/Account` / `App\Services\Accounts`
- إشعارات تيليجرام → `Modules/Sitting` (قناة إشعارات + Queued Job)

> ⚠️ **الهجرات القائمة تبقى في مكانها.** نقل ملف هجرة مطبَّق يكسر جدول `migrations` المفهرس بالاسم. الهجرات **الجديدة فقط** تُكتب داخل `Modules/Pos/database/migrations`.

---

### 10.4 المديولات الجديدة ➕

**القرار الأول: لا تُنشأ مديولات `nwidart` جديدة لأجزاء نقاط البيع.**

إنشاء `Modules/PosReports` أو `Modules/PosSettings` سيُضيف ServiceProvider ومسارات وهجرات مستقلة، ويتطلب تسجيل بادئاته في `ApplicationRegistry::groups()` وإلا حجبه `EnsureModuleEnabled`، ويُضاعف مشكلة الحدود بدل حلّها. التقسيم الصحيح **داخلي** (نطاقات) لا **أفقي** (مديولات).

**أ) النطاقات الداخلية الجديدة — داخل `Modules/Pos`**

| النطاق | المسؤولية | الحالة اليوم |
|---|---|---|
| `Terminal` | شاشة البيع، السلة، الدفع، الإيصال | مبعثر في `SalesStartController` |
| `Session` | الجلسة، الدرج النقدي، الإقفال، التأكيد | جزئي في `SessionsController` |
| `Catalog` | المنتجات، الباركود، البحث | `PosProductService` (أنظف جزء) |
| `Config` | الأجهزة، الطابعات، الورديات، الإعدادات | ثلاثة Controllers منفصلة |
| `Reporting` | التقارير والتحليلات | `ReportsController` |
| **`Pricing`** 🆕 | الخصومات، العروض، الكوبونات، قوائم الأسعار | **غائب كلياً** |
| **`Payment`** 🆕 | بوابات الدفع، الدفع المختلط، التسوية | **غائب** (محاكاة وهمية فقط) |
| **`Offline`** 🆕 | التخزين المحلي وطابور المزامنة | **غائب** |
| **`Audit`** 🆕 | سجل التدقيق وموافقات المشرف | **غائب** |

**ب) مديولات مستقلة جديدة — مرشّحان فقط**

| المديول | التبرير | التوقيت |
|---|---|---|
| ✅ **`Modules/Promotions`** | العروض والكوبونات تخدم **ثلاث وحدات** (`Pos` + `OnlineStore` + `Sales`). وضعها داخل `Pos` يجبر المتجر الإلكتروني على الاعتماد على وحدة نقاط البيع — انعكاس تبعية خاطئ. | المرحلة 4 |
| ⚠️ **`Modules/Zatca`** | الفوترة الإلكترونية المرحلة الثانية تخدم `Sales` + `Pos` + `Purchases`. اليوم مولّد QR مكرر ثلاث مرات بثلاث مصادر بيانات مختلفة. | المرحلة 4 — أو خدمة مشتركة `App\Services\Zatca` إن كان النطاق محدوداً |

**ج) ما لا يجب إنشاؤه**

`Modules/PosReports` · `Modules/PosSettings` · `Modules/PosDevices` · `Modules/CashDrawer` — كلها نطاقات داخلية، لا حدود مديول.

**د) ملفات بنية جديدة داخل `Modules/Pos`** (لا يوجد منها شيء اليوم)

`Models/` (12) · `Http/Requests/` (9) · `Http/Resources/` (3) · `Http/Middleware/` (2) · `Policies/` (3) · `Events/` + `Listeners/` (8) · `Jobs/` (3) · `Domain/` (Cart · Pricing · Tax · Payment · Enums) · `Reports/` (9) · `database/migrations/` · `config/pos.php` · `tests/` · `app/Constants/planFeatures/PosPlanFeatures.php`

---

### 10.5 تعارضات معمارية بين المديولات — يجب حسمها قبل البناء 🔥

هذه أخطر ما كشفته جولة الفحص الثانية، ولا يمكن تجاهلها في أي إعادة هيكلة.

#### التعارض 1: نظاما إعدادات متوازيان لنقاط البيع

| النظام | الموقع | النطاق | الحالة |
|---|---|---|---|
| `PosGeneralSetting` | `Modules/Pos` | **عالمي مفرد** | مُستخدَم فعلياً |
| مجموعة `pos` في `BranchSettingsCatalog` | `Modules/Branches` | **لكل فرع** | **لا يقرؤها أي سطر في `Modules/Pos`** |

المفاتيح الميتة في `Modules/Branches`: `enable_pos` · `require_shift_open` · `allow_cash_refund` · `max_refund_amount` (افتراضي 500) · `pos_print_receipt_auto`.

**المفارقة:** ثلاثة من هذه المفاتيح هي بالضبط الضوابط التي وصفتُها في القسم 7 بأنها «غائبة تماماً» — إلزام فتح الوردية، والسماح بالمرتجع النقدي، وحدّ المرتجع بلا موافقة. **هي ليست غائبة من التصميم، بل غائبة من التنفيذ**: المسؤول يضبطها في شاشة الفروع فلا يحدث شيء.

**القرار المطلوب:** توحيد على `PosSettingsResolver` بتوريث ثلاثي `عام → فرع → جهاز`، يقرأ من `Modules/Branches` للمستوى الفرعي عبر `branch_setting()`، ويُبقي `PosGeneralSetting` للمستوى العام فقط.

#### التعارض 2: نظاما إقفال نقدي متوازيان

| النظام | الموقع | المحور |
|---|---|---|
| إغلاق `PosSession` | `Modules/Pos` | **الجلسة** (جهاز + وردية) |
| `DailyClosingController` + `DailyClosingLedger` | `Modules/Sales` | **الموظف/اليوم** |

`DailyClosingLedger` نظام ناضج (إقفال يدوي وتلقائي وجماعي، تقارير، طباعة) ويجمع `type IN ('normal','pos')` — أي **يحتسب مبيعات نقاط البيع في إقفاله**. والنتيجة أن نفس النقد يُقفَل مرتين بمنطقين مختلفين، ولا رابط بينهما.

**القرار المطلوب — أحد ثلاثة:**
1. الجلسة هي الأصل، والإقفال اليومي يستهلك جلسات مُقفلة ومؤكَّدة (**الأفضل معمارياً**)
2. الإقفال اليومي هو الأصل، وجلسة POS تُغذّيه فقط
3. فصل صريح: الجلسة = عهدة الدرج، والإقفال اليومي = عهدة الموظف الكلية

الخيار 1 هو الصحيح: العهدة تُسلَّم على مستوى الدرج ثم تُجمَّع على مستوى الموظف.

#### التعارض 3: نقاط البيع غير محكومة بخطة الاشتراك

`app/Constants/planFeatures/` تحتوي: `Account` · `Branches` · `Hr` · `Sales` · `Stock`. **لا يوجد `PosPlanFeatures`.**

الأثر: عدد الأجهزة والجلسات والفروع في نقاط البيع **غير محدود بأي خطة**؛ الشيء الوحيد المحسوب هو أن فواتير `pos` تُعدّ ضمن سقف فواتير المبيعات المشترك (`SalesPlanFeatures:196`). أي أن نقاط البيع — وهي غالباً أعلى الوحدات قيمة تجارياً — لا تُسعَّر.

**القرار المطلوب:** إنشاء `PosPlanFeatures` بحدود: عدد الأجهزة · عدد الجلسات المتزامنة · إتاحة الوضع دون اتصال · إتاحة تقارير الربحية · إتاحة محرك العروض.

---

## 10.6 ما نُفِّذ فعلاً ✅

> نُفِّذ في جلسة 2026-08-28، ومُطبَّقٌ على قاعدة البيانات ومُختبَرٌ عليها.

### البنية الجديدة

| الطبقة | الملفات | ملاحظة |
|---|---|---|
| `Http/Controllers/Concerns/` | 6 سمات | الفترات · العملة · الترقيم · الحسابات · النطاق · الضريبة |
| `Services/` | 4 خدمات | `PosJournalPoster` · `PosSessionService` · `CashDrawerService` · `PosPaymentRecorder` |
| `Models/` | 5 نماذج | `PosOrderPayment` · `PosCashMovement` · `PosHeldOrder` · `PosPrinter` · `PosAuditLog` |
| `Reports/` | 7 ملفات | أساسٌ مجرّد + 6 تقارير، بـ29 وجه تجميع |
| `Http/Requests/` | 4 طلبات | التحقّق خرج من المتحكّمات |
| `Jobs/` | 1 | تيليجرام إلى الطابور بعد `commit` |
| `database/migrations/` | 7 هجرات | **مطبَّقة كلُّها** |

### المعايير السبعة المطلوبة

| المطلب | التنفيذ | التحقّق |
|---|---|---|
| **الفترات المحاسبية** | `RespectsAccountingPeriods` — منعُ الكتابة في فترةٍ مقفلة (بيع/مرتجع/درج/إقفال)، وعمود `accounting_period_id` على الفاتورة والجلسة والدفعة وحركة الدرج | الفاتورة والقيود خُتمت بـ`فترة=372` |
| **العملات** | `UsesDocumentCurrency` — عمود `currency` على الجلسة، والافتراضيةُ من شاشة العملات، وتجميدٌ بعد الحفظ. حُذفت `'SAR'` من 14 موضعاً و«ر.س» من الرسائل | `عملة=SAR` مقروءةً من الإعداد |
| **تسلسل المستندات** | 4 أقسام في `DocumentSections` + `GeneratesDocumentNumbers`. حُذفت حلقاتُ `while ... exists()` بلا قفل | `SES-000001` · `POS-000001` · `PRT-000001` · `CDM-000001` |
| **قوالب المستندات** | جدولُ `pos_printers` + نموذجه، وبياناتُ البائع من `OrganizationInfo` في الإيصالَين وفي QR | الإيصالان كانا يطبعان **اسمَي منشأتين مختلفتين** |
| **حساب الضريبة** | `CalculatesPosTax` فوق `App\Support\TaxCalculator` — يقرأ `type` (مضافة/شاملة) الذي كان مُهمَلاً، ولا `0.15` مخمَّنة | `included(115@15%) ⇒ وعاء 100 ضريبة 15` |
| **الثوابت ← الحسابات** | `ResolvesAccountingAccounts` — حُذفت `Account::find(52)` و`where('id',45)` و`where('id',50)` و`where('name','الخزينة الرئيسية')` | مفتاحان جديدان: `pos_cash_over` · `pos_cash_short` |
| **السجلات والصلاحيات والفروع والأدوار** | `pos_audit_logs` بعمود `approved_by` · 25 صلاحية جديدة · `branch_id` على الجلسة والفاتورة · `ScopesToPosAccess` بدل `hasRoleName('manager')` | 66 مساراً كلُّها محروسة · 25/25 صلاحية للأدوار الإدارية |

### أخطاء أُصلحت — بعضُها كشفه التشغيل الفعلي

| # | الخطأ | كيف ظهر |
|---:|---|---|
| 1 | **الرصيد الافتتاحي يُحتسب مرّتين** في «المتوقَّع» — `SUM(cash_amount)` كانت تشمل صفَّ الافتتاح | اختبار التشغيل: متوقَّع 1060 بدل 560 |
| 2 | **قيدٌ غير متوازن** عند دفعِ العميل أكثرَ من الفاتورة — المدين بالمستلَم والدائن بالمُستحقّ | أمسكه حارسُ التوازن الجديد نفسُه |
| 3 | `invoices.accounting_period_id` غير موجود — كنتُ أفترضه | فشلُ 13 تقريراً |
| 4 | MySQL يرفض `SELECT DATE(x)` مع `GROUP BY DATE(x)` | فشلُ 6 تجميعاتٍ زمنية → حُلّت بالتجميع بالاسم المستعار |
| 5 | حركةُ سحبٍ تُسجَّل **مرّتين** في التدقيق (عند الطلب وعند الاعتماد) | 4 سجلات بدل 3 |
| 6 | `Invoice::$fillable` يحجب الأعمدة الجديدة | الفرعُ والفترةُ فارغان على الفاتورة رغم وجودهما على القيد |
| 7 | تعديلُ هجرةٍ مطبَّقة لا يُعيد تشغيلها | فُصلت إلى `..._000007` |

### التحقّق النهائي

```
كل ملفات PHP سليمة            33 قالباً يُصرَّف بلا خطأ
66 مسار POS — 0 هدف مفقود      29/29 تقريراً × تجميعاً يعمل
7 هجرات مطبَّقة                25/25 صلاحية ممنوحة للأدوار الإدارية
```

**دورةُ حياةٍ كاملة مُختبَرة:** فتحُ جلسة ← منعُ جلسةٍ ثانية ← إيداعٌ في الدرج ← سحبٌ بانتظار الاعتماد ← اعتمادٌ يُنشئ القيد ← بيعٌ (3 قيود متوازنة، مخزون 50→48، تكلفةٌ مجمَّدة 60) ← مرتجعٌ جزئي (قيدان متوازنان، مخزون 46→48) ← منعُ إرجاعٍ يتجاوز المتاح ← إقفالٌ بفرق (قيدُ فرقٍ متوازن) ← تأكيدُ المشرف ← 3 سجلات تدقيق.

### 10.6.1 ختمُ السياق في المودل لا في المتحكّم

كان السياق (الفرع/الفترة/الموظف/العملة) يُملأ **في الخدمات** — وهذا يفلت من كل طريقٍ آخر يُنشئ السجل: أمرُ كرون، بذرة، استيراد، وحدةٌ أخرى. فأُلحقت الموديلات بنمط المشروع نفسِه (`BelongsToAccountingPeriod` · `HasDocumentCurrency`)، وأُضيفت السمة الناقصة.

**`App\Models\Concerns\StampsOwnerContext`** — تعمل على `creating`، وتملأ `branch_id` و`employee_id` و`user_id` و`created_by`. ثلاث قواعد:

- **لا تملأ إلّا الفارغ** — ما ضبطه المتحكّم صراحةً يبقى.
- **لا تملأ إلّا العمود الموجود** — تُقرأ أعمدة الجدول مرّةً لكل صنف.
- **لا ترمي أبداً** — غيابُ جلسة مصادقة (بذرة/كرون) يُحفظ بلا سياق، ويُكتب في اللوغ.

| المودل | الفرع يُشتقّ من | استثناءات |
|---|---|---|
| `PosSession` | **الجهاز** | — |
| `PosCashMovement` · `PosOrderPayment` · `PosHeldOrder` · `PosAuditLog` | الجلسة → الجهاز | — |
| `Invoice` | الجلسة (لفواتير POS وحدها) | `branch_id` ← `pos_branch_id` |
| `PosPrinter` | الجهاز | — |
| `CashierDevice` · `shifts_pos` | المستخدم | `user_id` (هو الكاشير المسنَد، لا المُنشئ) |

**أهمُّ قرارٍ فيها:** الفرعُ يُشتقّ من **المستند لا من المستخدم**. البيعُ يقع في فرع الجهاز الذي وقف عليه العميل، لا في فرع الموظف الذي سجّل دخوله — ومشرفٌ يملك `data_scope_all` يفتح جلسةً على جهازٍ في فرعٍ آخر كانت مبيعاتُه تُنسَب إلى فرعه هو.

> **مُختبَر:** مستخدمٌ في الفرع 5 فتح جلسةً على جهازٍ في الفرع 8 — فأخذت الجلسةُ والحركةُ والفاتورةُ الفرعَ **8**.

وأُضيف `BelongsToAccountingPeriod` إلى `Invoice` (بتاريخ `invoice_date`): `journal_entries` و`receipts` و`expenses` و`warehouse_permits` كلُّها تختم فترتها، والفاتورةُ — وهي أصلُ أكثر تلك القيود — كانت لا تختمها. وهذا يشمل فواتير المبيعات والمشتريات لا نقاط البيع وحدها.

وحُذف الملءُ اليدوي من `PosSessionService` و`CashDrawerService` و`PosPaymentRecorder` و`SalesStartController` — الاختباراتُ الثلاثة تُخرج النتائجَ نفسَها بعده.

### 10.6.2 إكمالُ ما تبقّى

| البند | ما نُفِّذ |
|---|---|
| **السلال المعلَّقة** | كانت ثلاثَ نقاطٍ تستعلم عن `invoices.status` — **وعمودٌ بهذا الاسم لا وجود له في الجدول**، فكانت ترمي `Unknown column` في كل نداء لا تُرجع قائمةً فارغة. صارت على `pos_held_orders`، ومعها نقطةُ التعليق التي لم تكن، وحدٌّ لعدد السلال المفتوحة، وصلاحيةٌ تنتهي بانتهاء الجلسة. |
| **إلغاء الفاتورة** | `VoidSaleService` — يُعيد البضاعة (بأنواع المنتجات الثلاثة)، ويعكس **كل** قيدٍ وُلد من الفاتورة سطراً بسطر، ويُنقص الجلسة بصفٍّ مضادّ لا بحذف، ويمنع الإلغاء على فاتورةٍ لها مرتجع أو من جلسةٍ أُقفلت. وأعمدةُ `voided_at`/`voided_by`/`void_reason` بدل عمود حالةٍ عامّ. |
| **الطابعات** | `PrintersController` + شاشاته، وافتراضيةٌ واحدة لكل نطاق، ومنعُ حذف طابعةٍ مربوطةٍ بجهاز. |
| **تعديل السعر** | `points_sale_edit_product_prices` صارت تُفحص فعلاً: السعرُ يُقارَن بسعر الوحدة المختارة، والتعديلُ يحتاج صلاحيةً ويُكتب في سجلّ التدقيق — لأعلى ولأسفل معاً. |
| **إعادة الطباعة** | تُكتب في سجلّ التدقيق — إيصالٌ ثانٍ لعمليةٍ واحدة وسيلةٌ معروفة للتلاعب. |
| **المرتجع النقدي** | صار يحتاج شرطين: إذنَ الفرع **وصلاحيةَ المستخدم** (`pos.returns.cash_refund`). |
| **قالبان مفقودان** | `settings.devices.show` و`settings.shift.show` — الثاني كان مكسوراً قبل هذا العمل. |

**وخللٌ ثالثٌ كشفه التشغيل:** صفوفُ الإلغاء تحمل مبالغَ سالبة، فلولا إدخالُها في مجاميع الجلسة لبقيت مبيعاتُ جلسةٍ أُلغيت كلُّ فواتيرها كما هي.

---

### 10.7 نظامُ الباقات وحدود الاستخدام

> **صلاحياتُ الباقة ليست صلاحياتِ النظام.** الأولى تقول «ماذا اشترت المنشأة»، والثانية «من يفعل ماذا داخلها». لا يتداخلان: مديرٌ يملك كل صلاحيات النظام لا يفتح شاشةً لم تُشترَ، ومنشأةٌ اشترت الميزة لا تفتحها لموظفٍ بلا صلاحية.

#### في لوحة فوترة

**`config/pos_features.php`** — قسمٌ جديد يكتشفه `FeatureSectionRegistry` تلقائياً (٧ حدود · ١٦ صلاحية · ٥ مجموعات)، بلا سطر كودٍ في أي مكانٍ آخر. ومفاتيحُ الباقة **أخشن** من مفاتيح النظام عمداً: `pos_returns` واحدةٌ تقابل ثلاث صلاحيات نظام — فالعميل يشتري «المرتجعات» لا يشتري «اعتماد المرتجع».

| الحدّ | مجانية | أساسية | متقدمة | شاملة | مؤسسات |
|---|---:|---:|---:|---:|---:|
| أجهزة الكاشير | 0 | 1 | 3 | 10 | ∞ |
| الجلسات المتزامنة | 0 | 1 | 2 | 5 | ∞ |
| الطابعات | 0 | 1 | 4 | 15 | ∞ |
| الورديات | 0 | 2 | 6 | 20 | ∞ |
| الجلسات شهرياً | 0 | 60 | 250 | 1000 | ∞ |
| حركات الدرج | 0 | 100 | 500 | 2000 | ∞ |
| السلال المعلَّقة | 0 | 0 | 200 | 1000 | ∞ |
| **الصلاحيات** | 0/16 | 8/16 | 13/16 | 15/16 | 16/16 |

و**٢٠ إضافةً قابلة للبيع** تولّدت آلياً من هذه الحدود عبر `PackageAddonsSeeder`.

**قراران يستحقّان التوضيح:**

- **لا نقاطَ بيعٍ في المجانية** — الصندوق ميزةٌ تشغيلية لا تجريبية.
- **فاتورةُ نقطة البيع ليست حدّاً هنا** — تُعدّ في `sales_invoices` ضمن قسم المبيعات (`type IN (normal, pos, online)`)، وتكرارُ عدّادها يجعلها تستهلك رصيدين.

#### في نظام العميل

| الملف | ما تغيّر |
|---|---|
| `PosPlanFeatures` | ١٦ صلاحية + ٧ حدود، مطابقةً **حرفياً** لمفاتيح الكتالوج — والمفتاح المجهول يُعامَل «مسموحاً بلا حدّ»، فالخطأ الإملائي يُنتج حارساً صامتاً لا عطلاً يُلاحَظ |
| `PlanLimitObserver` | ٦ نماذج نقاط بيع مُلزَمة عند الإنشاء |
| `UsageReporter` | ٧ ميزات تُقاس وتُرفَع. و`pos_concurrent_sessions` يقيس **المفتوح الآن** لا المُنشأ في فترة — والمعلَّقة تُعدّ مع النشِطة: الكاشير الذي علّق جلسته لم يُقفل عهدته |
| `routes/web.php` | **٧٤ مساراً، كلُّها بحارسَين**: `plan:` للباقة و`permission.role:` للنظام |

`SubscriptionSync` لم يحتج تعديلاً — يخزّن ما ترسله اللوحة كما هو، فقسمُ نقاط البيع يصل مع أول مزامنة.

**حالةُ الفحص الآن:** الحدود تُقرأ «غير معروفة ⇒ مسموحة» لأن لقطة هذا العميل تسبق إضافة القسم. وهو السلوك الموثَّق للكتالوج الذي يسبق النظام — وتصير مُلزِمةً فور أول مزامنة.

### 10.7.1 الحسابات المحاسبية: مفتاحان جديدان في الشاشة لا ثابتان في الكود

نقاطُ البيع تستهلك تسعةَ حسابات. سبعةٌ منها كانت مُعرَّفةً في
`AccountingSettingsSeeder` أصلاً، وكان المتحكّم **لا يقرؤها**: كان يقرأ
`Account::find(52)` و`Account::where('id', 45)` و — الأسوأ —
`Account::where('name', 'الخزينة الرئيسية')`. فتعديلُ تسميةِ الخزينة من الشاشة،
وهو تعديلٌ مشروعٌ لا أحدَ يظنّه خطراً، كان يكسر قيدَ المرتجع بصمت.

صارت التسعةُ كلُّها تمرّ بـ`AccountingSettings::id()` بترتيب: **الإعدادُ المربوط
← وإلا الثابتُ احتياطاً**. الاحتياطُ مقصود — النظام يعمل قبل أن يربط المحاسب
مفتاحاً، وربطُه يسري فور حفظه بلا نشر.

**والمفتاحان الجديدان `pos_cash_over` و`pos_cash_short` بلا احتياطٍ عمداً:**
لا حسابَ في الشجرة يقابلهما طبيعياً، والقرارُ للمحاسب. فإن لم يُربطا لم يُنشأ
قيدُ الفرق ويُسجَّل ذلك في اللوغ — بدل أن يُرحَّل الفرق إلى حسابٍ مخمَّن.

واثنان لا واحد: الزيادةُ إيرادٌ عارض والعجزُ مصروف، ودمجُهما يُخفي حجمَ كلٍّ
منهما في ميزان المراجعة — وهو بالضبط ما يُراد قياسه.

**وأُضيفت العمليتان إلى كتالوج `OPERATIONS`** في `AccountingSettingsController`
فيرى المحاسبُ في الشاشة **لماذا** يُطلب منه المفتاحان وأيَّ قيدٍ سيولّدان:

| العملية | مدين | دائن |
|---|---|---|
| فائض عند إقفال جلسة | خزينة الكاشير | `pos_cash_over` |
| عجز عند إقفال جلسة | `pos_cash_short` | خزينة الكاشير |

والطرفُ غير المربوط يُعرض «—» في الشاشة، وعمودُ «يُستخدم في» صار يذكرهما.

**التحقّق** — بالتبديل بين حسابين من الشجرة:

```
١) بلا ربط          → cashOverAccount() = null   ✔ لا قيد يُكتب
٢) رُبط بـ«الأصول»   → cashOverAccount() = 1      ✔ تبع الإعداد
٣) رُبط بـ«الخصوم»   → cashOverAccount() = 2      ✔ تبع الإعداد
```

الثالثةُ هي الفيصل: لو كان ثابتاً برمجياً لما تغيّر شيء.

وطرقُ الدفع لا تُحلّ من الإعدادات العامة بل من `payment_methods.account_id`
لكل طريقةٍ على حدة (هجرة `000006`) — لأن لكل طريقةٍ حسابَها هي، ثم البنكُ
الافتراضي من إعدادات نقطة البيع، ثم خزينةُ الكاشير. ولا ثابتَ في السلسلة.

### 10.8 الواجهات: الصلاحيات والباقات في الشاشة نفسها

الحراسةُ على المسار والمتحكّم تمنع الوصول، ولا تمنع **عرضَ ما لا يُوصَل إليه**.
فكان المستخدم يرى زرّاً فيضغطه بعد أن يبني سلّته، فيُردّ بـ403 وقد فقد ما أدخله.
والمنعُ الصامت أسوأ من المنع المعلن.

**القاعدةُ المطبَّقة — وهي طبقتان لا تختلطان:**

| | صلاحيةُ النظام (من يفعل؟) | ميزةُ الباقة (ماذا اشترت المنشأة؟) |
|---|---|---|
| **الأثر في الشاشة** | **تُخفي** البند | **تُقفله ويبقى ظاهراً** |
| **لماذا** | من لا شأن له بالشاشة لا يُعرض له بابٌ ليس بابه | إخفاؤها يجعل المنشأة لا تعرف أنها موجودة، فلا تسألُ عنها ولا تشتريها |
| **الوجهة** | لا شيء — البند غير موجود | شاشةُ الاشتراك، لا شاشتُه (يُردّ عنها بـ402 على كل حال) |
| **الترتيب** | يُفحَص أولاً | يُفحَص بعده فقط |

وحدُّ الاستخدام **لا يُخفي ولا يُقفل** — شارةٌ فقط. من استنفد سقفَ الأجهزة يحتاج
شاشتَها أكثرَ من غيره: ليعطّل جهازاً لم يعد يُستعمل، أو ليرى ما عنده قبل أن يشتري
إضافة. وإخفاؤها يقفل عليه بابَ الخروج من الحدّ نفسِه. والشارةُ لا تظهر قبل ٨٠٪ كي
لا تصير زخرفةً دائمة يتوقّف أحدٌ عن رؤيتها.

**والقفلُ يغلب الشارة**: ميزةٌ لم تُشترَ لا حدَّ لها يُعرض.

**ما عُدِّل:**

- **`layouts/sidebar.blade.php`** — كان بلا أيّ وعيٍ بالباقة (`SubscriptionGate: 0`).
  صار كلُّ بندٍ يمرّ بـ`$planHref`/`$planAttrs`/`$planMark`، والقسمُ كلُّه يُقفل حين
  لا تشمل الباقة شيئاً منه. ويقرأ `limit()` لا `liveLimit()`: الثانيةُ تُطلق
  `COUNT(*)` لكل ميزة، وخمسةُ استعلاماتٍ على **كل شاشة** ثمنٌ باهظ لشارة —
  والفرقُ بينهما دورةُ مزامنةٍ واحدة، وهو مقبولٌ لتنبيهٍ لا لمنع.
- **`settings/index.blade.php`** — أُعيدت كتابته: بطاقاتُه الثلاث كانت تُعرض للجميع
  فيقود نصفُها إلى 403، وترميزُه مكسور (`</a>` داخل البطاقة قبل إغلاق `div.card`)،
  و`padding: 100px` ثابتٌ يكسر الشاشة الصغيرة، ورابطُ أيقوناتٍ من CDN يخالف
  `feather` المستعملة في الوحدة كلّها. وأُضيفت بطاقةُ الطابعات الناقصة.
- **`devices/index`، `shift/index`** — كانت تستقبل `$canManage` من المتحكّم ولا
  تستعمله إطلاقاً. (وفي `shift/index` كان `@if` غيرَ مغلق.)
- **`sessions/index`** — أُعيدت كتابةُ قائمة الإجراءات بفحصٍ لكل جلسة على حدة، مع
  إضافة فرع `pending_review` الذي كان ناقصاً فتظهر الجلسةُ المنتظرة بلا أيّ إجراء.
- **`sessions/summary`** — زرُّ التأكيد ونموذجُه كانا غائبين رغم وصول `$canConfirm`.
- **`sales_start/index`** — زرّا التعليق والاسترداد بلا حراسة.
- **`sessions/show`** — زرُّ الإقفال بلا حراسة: المسارُ يفتح الشاشة بـ`view_own|view_all`
  والإقفالُ يطلب `close_own|close_all`. وحُرِس **المودلُ كلُّه** لا الزرُّ وحده —
  إخفاءُ الزرّ يترك النموذج في الصفحة ويُفتح بسطرٍ في طرفية المتصفّح. وأُضيف
  فحصُ `canBeClosed()` كذلك، فجلسةٌ مغلقة كانت تعرض زرَّ إقفالها.
- **`cash_drawer/index`** — الشاشةُ تُفتح بـ`view` والتسجيلُ يطلب
  `pay_in|pay_out|drop`. فصار النموذجُ لا يظهر لمن لا يملك أيّاً منها، **وقائمةُ
  الأنواع تُصفّى بحسب ما يملكه**: من يُدخل الفكّة ليس بالضرورة من يُخرج المصروف
  النثري ولا من يُرحّل النقد إلى الخزينة.

**التحقّق:** صُيِّر الشريط بلقطةِ باقةٍ مقيَّدة مُصطنَعة (الطرفية والجلسات والإعدادات
مسموحة، والباقي ممنوع، والجلساتُ ١٠٠٪ والأجهزةُ ٩٥٪):

```
         بدء البيع        → sales-start
         الجلسات          → session   [شارة: امتلأ]
  🔒     بانتظار التأكيد   → شاشة الاشتراك
  🔒     الدرج النقدي      → شاشة الاشتراك
  🔒     أجهزة الكاشير     → شاشة الاشتراك
  🔒     الطابعات          → شاشة الاشتراك
  🔒     الورديات          → شاشة الاشتراك
         إعدادات نقاط البيع → index
  🔒     التقارير          → شاشة الاشتراك
```

ستةُ بنودٍ مقفولة تحمل أيقونةَ القفل وتلميحَ السبب وتقود إلى شاشة الاشتراك، ولا
بندَ مخفيّ. وشارةُ الجلسات ظهرت، وشارةُ الأجهزة (٩٥٪) **لم تظهر** لأن قفلها
يغلبها — وهو المقصود. و٣٩ قالباً تُصرَّف بلا خطأ.

### ما لم يُنفَّذ بعد

المرحلتان 4 و5 من الخطة: محرّكُ العروض (`Modules/Promotions`)، والعملُ دون اتصال، وفوترةُ الزكاة المرحلة الثانية، وشاشةُ العميل وKDS، وربطُ `Modules/Sales/DailyClosing` بجلسات POS المؤكَّدة (التعارض 2).

---

## 11. خارطة الطريق المقترحة

> الرموز: 🗑️ حذف · ✏️ تعديل · ➕ إضافة · ⚖️ حسم تعارض

### المرحلة 0 — الإطفاء الفوري (أيام)

| # | المهمة | النوع | الأثر |
|---:|---|---|---|
| 1 | **إبطال توكن تيليجرام** ونقله للإعدادات | 🗑️ | سرّ مكشوف في المستودع |
| 2 | **حذف مسار وقالب ودالة `shabaka-payment`** | 🗑️ | أي مستخدم يعلّم أي فاتورة كمدفوعة |
| 3 | حماية `ReportsController` بـ `pos_view_reports` + `pos_view_profit_reports` | ✏️ | تكاليف وهوامش مكشوفة |
| 4 | **إزالة `catch` الصامت من كل دوال القيود** → إعادة رمي الاستثناء | ✏️ | فواتير بلا قيود |
| 5 | إضافة `else` لقيود المرتجع (البند 3.2) | ✏️ | مرتجعات بلا قيود |
| 6 | حذف `quickTest` والمسارات الخمسة المعطوبة | 🗑️ | |
| 7 | إضافة `use ...Log;` في `SessionsController` | ✏️ | مسار الخطأ في الإقفال |
| 8 | تصحيح صلاحيات `SettingsController` إلى `pos_manage_settings` | ✏️ | كاشير يغيّر حساب البنك |
| 9 | بناء قالب `sessions/close.blade.php` المفقود | ➕ | زر الإغلاق معطوب |
| 10 | حذف مجموعة «Fast AJAX» ومصفوفة `$fastEndpoints` | 🗑️ | تجاوز صلاحيات |
| 11 | حذف الاستيرادين الخاطئين ومسار `/search` المكرر من `web.php` | 🗑️ | |

### المرحلة 1 — سلامة البيانات وتنظيف الملكية (2-4 أسابيع)

| # | المهمة | النوع |
|---:|---|---|
| 12 | جدول `pos_order_payments` + حفظ فعلي للمدفوعات | ➕ |
| 13 | التحويل إلى `next_document_number()` مع قفل | ✏️ |
| 14 | تجميد `unit_cost_at_sale` في بند البيع | ➕ |
| 15 | معالجة المنتجات المجمّعة في المرتجع | ✏️ |
| 16 | توحيد اشتقاق المخزن بين البيع والمرتجع | ✏️ |
| 17 | مولّد QR واحد يقرأ بيانات البائع من الإعدادات (بدل 3 نسخ) | ✏️ |
| 18 | إخراج تيليجرام والإشعارات إلى Queued Jobs بعد الـ commit | ✏️ |
| 19 | Idempotency Key على `store()` | ➕ |
| 20 | **حذف `PosController` + `routes/api.php` + `PosTransaction(Item)` + جدولَيهما** | 🗑️ |
| 21 | **حذف الدوال الميتة العشر** (~600 سطر) | 🗑️ |
| 22 | استبدال أرقام الحسابات السحرية بـ `SystemAccountIds` | ✏️ |
| 23 | **تفعيل `check.pos.session` على مسارات البيع** بدل 12 فحصاً مكرراً | ✏️ |

### المرحلة 2 — استخراج النطاق وحسم التعارضات (4-8 أسابيع)

| # | المهمة | النوع |
|---:|---|---|
| 24 | `CreateSaleService` + `ProcessReturnService` + `PosJournalPoster` + `PosStockMovementService` | ➕ |
| 25 | تفكيك `SalesStartController` إلى 8 controllers رفيعة | ✏️ |
| 26 | نقل كل التحقق إلى `Http/Requests` (9 ملفات) | ➕ |
| 27 | `PosSessionPolicy` + `PosOrderPolicy` واستبدال `hasRoleName('manager')` بالكامل | ➕ |
| 28 | `AbstractPosReport` وتفكيك `ReportsController` إلى `Reports/*` | ✏️ |
| 29 | تصحيح فلترة المرتجعات في التقارير (البند 3.25) | ✏️ |
| 30 | نقل نماذج POS إلى `Modules/Pos/Models` + إعادة تسمية `shifts_pos` → `PosShift` | ✏️ |
| 31 | **⚖️ التعارض 1:** `PosSettingsResolver` بتوريث عام→فرع→جهاز، وتفعيل مفاتيح `Modules/Branches` الخمسة الميتة | ⚖️ |
| 32 | **⚖️ التعارض 3:** إنشاء `PosPlanFeatures` وربطه بـ `PlanLimitObserver`/`UsageReporter` | ⚖️ |
| 33 | توسيع `PosPermissions` بالصلاحيات الثماني الناقصة | ✏️ |
| 34 | أول حزمة اختبارات: البيع، المرتجع، إغلاق الجلسة، القيود | ➕ |

### المرحلة 3 — إكمال الوظائف (8-12 أسبوعاً)

| # | المهمة | النوع |
|---:|---|---|
| 35 | نطاق `Session`: الدرج النقدي (`pos_cash_movements`) + تأكيد الإقفال + قيد فرق الصندوق | ➕ |
| 36 | **⚖️ التعارض 2:** ربط `Modules/Sales/DailyClosing` بجلسات POS المؤكَّدة (الجلسة هي الأصل) | ⚖️ |
| 37 | تنفيذ ضوابط الفرع فعلياً: `require_shift_open` · `allow_cash_refund` · `max_refund_amount` | ✏️ |
| 38 | الإلغاء (Void) مع موافقة المشرف | ➕ |
| 39 | نطاق `Audit`: `PosAuditLog` + موافقات المشرف | ➕ |
| 40 | تفعيل التعليق (Hold) فعلياً عبر `pos_held_orders` | ✏️ |
| 41 | نطاق `Config`: الطابعات + ملفات تعريفها + أوقات الورديات | ➕ |
| 42 | `branch_id` في الجلسة + فصل `PosOrder` عن `Invoice` | ➕ |
| 43 | Z-Report و X-Report وتقرير طرق الدفع وفروقات الصندوق | ➕ |
| 44 | دمج `DevicesController` + `ShiftController` + `SettingsController` تحت `Admin/` | ✏️ |

### المرحلة 4 — النضج والتوسع (12+ أسبوعاً)

| # | المهمة | النوع |
|---:|---|---|
| 45 | **`Modules/Promotions`** — مديول مستقل يخدم Pos + OnlineStore + Sales | ➕ |
| 46 | نطاق `Payment`: بوابات دفع حقيقية + تسوية البطاقات | ➕ |
| 47 | نطاق `Offline`: IndexedDB + طابور مزامنة | ➕ |
| 48 | **`Modules/Zatca`** أو `App\Services\Zatca` — الفوترة الإلكترونية المرحلة الثانية | ➕ |
| 49 | تكامل الولاء مع `Modules/PointsAndBlances` عبر `Modules/Ecosystem` | ➕ |
| 50 | شاشة العميل + شاشة المطبخ (KDS) | ➕ |

---

## 12. الخلاصة

الوحدة كُتبت بجهد حقيقي — منطق الوحدات والمنتجات المجمّعة وفحوصات المرتجع تُظهر فهماً جيداً للأعمال. لكن هذا المنطق وُضع كله في مكان واحد خاطئ، فتحوّل إلى كتلة يستحيل اختبارها أو تعديلها بأمان.

**أربع نقاط يجب أن تُعالَج قبل أي شيء آخر:**

1. **مسار `shabaka-payment` المكشوف** — أي مستخدم مصادق عليه يعلّم أي فاتورة كمدفوعة، بلا بوابة دفع حقيقية.
2. **القيود المحاسبية التي تفشل بصمت** — كل يوم تشغيل يضيف فواتير بلا قيود، ولا أحد يعلم.
3. **`ReportsController` المكشوف بلا صلاحيات** — تكاليف وهوامش كل منتج متاحة لأي مستخدم.
4. **المدفوعات التي لا تُحفَظ** — لا رقابة نقدية ولا تسوية بنكية ممكنة بدونها.

**وثلاثة تعارضات معمارية تُحسم قبل البناء** (القسم 10.5): نظاما إعدادات متوازيان، ونظاما إقفال نقدي متوازيان، ووحدة غير محكومة بخطة اشتراك.

**ملاحظة مشجّعة:** جزء معتبر من «النواقص» ليس غائباً عن التصميم — بل غائباً عن التنفيذ. الـ middleware `CheckPosSession` مكتوب ومسجَّل ولا يُستخدم. وضوابط المرتجع وإلزام الوردية معرَّفة في `Modules/Branches` ولا يقرؤها أحد. وكتالوج الصلاحيات مصمَّم بدقة ومُهمَل. **الفجوة بين ما بُني وما وُصِّل أصغر مما تبدو عليه** — وهذا يجعل المرحلتين 0 و1 أقصر مما يوحي حجم الوحدة.

الاتجاه واضح: **استخراج طبقة نطاق (Domain)، وتفكيك الـ God Controller، وتوصيل ما هو مبنيّ أصلاً.** الأساس يكفي لبناء نظام احترافي عليه — لكن ليس بالاستمرار في الإضافة فوقه.
