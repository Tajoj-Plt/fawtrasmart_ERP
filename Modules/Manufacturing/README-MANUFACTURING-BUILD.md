# الأساس المُنفَّذ — موديول Manufacturing

**ما في هذا المجلد كودٌ حقيقي مبنيّ على قراءة نظامك، لا على افتراض.**
بعد وصولي إلى المشروع كاملاً قرأتُ البنية التحتية وصحّحتُ استنتاجين كانا خاطئين.

---

## 1. ما تغيّر بعد قراءة المشروع

### ✅ القرار الأول محسوم: `product_details` **رصيد جارٍ** لا سجلّ حركات

`StorePermitsManagementController::addToWarehouse` / `removeFromWarehouse` — وهو المرجع في نظامك — يعامله صفّاً واحداً لكل (منتج، مستودع):

```php
$productDetail = ProductDetails::where('store_house_id', $storeHouseId)
    ->where('product_id', $productId)->first();
$productDetail->quantity += $quantity;
$productDetail->save();
```

الأعمدة `type` و`date` و`time` و`type_of_operation` و`subaccount` موجودة في `$fillable` لكن **لا يستعملها المسار الحيّ** — بقايا تصميم أقدم.

**الأثر:** كل الباتشات المخزنية في المستند الموحَّد صحيحة كما هي. و`getProductAvailableQuantity` (السطر 852) التي تحسب `in − out` كانت ستُنتج رصيداً خاطئاً لو استُدعيت.

---

### ❌ استنتاج خاطئ صحّحتُه: `permission_source_id = 15`

`PermissionSourcesSeeder` يعرّف:

| القيمة | المعنى |
|---|---|
| **3** | أمر تصنيع المنتج الرئيسي |
| **4** | أمر تصنيع المواد الهالكة |
| **15** | **تحويل وارد لنقطة البيع** ← ليس تصنيعاً |
| **16** | أمر تصنيع البنود المرتجعة |
| **17** | أمر تصنيع لصرف المواد |

`completeOrder` و`receiveMaterials` و`returnMaterials` تكتب **15**. أي أن **كل إذونات التصنيع مسجَّلة تحت «تحويل وارد لنقطة البيع»** — تقارير المخزون تنسب إنتاج المصنع إلى الكاشير، وأي فلترة بالمصدر تُخطئ الوجهة.

و`finishOrder` كانت تكتب **3** — وهي الصحيحة — لكنها الدالة الميتة التي لا تستدعيها أي واجهة. **في المستند الموحَّد وصفتُ هذا الفرق دَيناً تقنياً؛ هو في الحقيقة عيب بيانات.**

الأرقام الصحيحة في `ManufacturingSources`.

---

### ⚠️ عيب لم أكن أراه: تعديل الأرصدة **مزدوج**

`JournalEntryDetail::created` يستدعي تلقائياً:
```php
Account::updateParentBalances($account->id, true);
```
و`updateParentBalances` **تُعيد احتساب** الرصيد من القيود (`updateBalanceFromJournalEntries`) — لا تزيده.

بينما `BOMController::createIssuanceJournalEntry` و`createMaterialsReceiveJournalEntry` تكتبان يدوياً:
```php
$storeAccount->balance -= $permit->grand_total;
$storeAccount->save();
Account::updateBalancesHierarchically($storeAccount->id);
```

**النتيجة:** التعديل اليدوي إمّا يُمحى بعد سطور، أو يترك قيمةً شاذة بين الكتابتين. والأسوأ أن باتش «عكس الأرصدة» الذي كتبتُه في المستند الموحَّد ([P4-02](#)) **كان سيزيد الخلل** لو طُبِّق.

**الصواب: لا نلمس `Account::balance` إطلاقاً.** `ManufacturingJournalService` لا يلمسه.

---

### ✅ البنية التحتية موجودة وكاملة — الموديول فقط لا يستعملها

| ما طلبتَه | موجود في نظامك | يستعمله التصنيع؟ |
|---|---|---|
| الحسابات المحاسبية بدل الثوابت | `AccountingSettings::id($key, $fallback)` + 80 مفتاحاً مزروعاً | ✗ |
| — مفاتيح التصنيع | `raw_materials_inventory`, `work_in_progress_inventory`, `finished_goods_inventory`, `waste_inventory`, `manufacturing_overhead` | ✗ |
| الترقيم المتسلسل | `next_document_number()` / `peek_document_number()` + قسم `manufacturing-orders` بالبادئة `MO` | جزئياً |
| الفترات المحاسبية | `accountingPeriodBlock($date)` | جزئياً |
| العملات | `CurrencySettings::code()` + `HasDocumentCurrency` + عمود `currency` | ✗ |
| سجل الأنشطة والأخطاء والأحداث | `OperationLog::record()` / `::failure()` / `::diff()` — تكتب في `logs` و`event_logs` و`error_logs` معاً | جزئياً |
| الفروع | `current_branch_id()` + `branch_setting()` + `JournalEntry` يملأ `branch_id` تلقائياً | ✗ |
| قوالب المستندات | `DocumentTemplateSeeder` (133KB) + `DocumentSections` + `PrintSettings` | ✗ |
| معلومات الحساب | `OrganizationInfo` + `BusinessInfo` | ✗ |
| الإشعارات | `NotificationSettingSeeder` (16KB) | جزئياً |
| المظهر | `ThemeSettings` (45KB) | ✓ عبر `master.blade` |

**الحساب الوحيد الناقص: انحراف الإنتاج.** الهجرة تضيف مفتاحه.

---

## 2. الملفات وأين توضع

```
app/Enums/ManufacturOrderStatus.php
    → app/Enums/

Modules/Manufacturing/Constants/ManufacturingSources.php
Modules/Manufacturing/Services/InventoryService.php
Modules/Manufacturing/Services/ManufacturingJournalService.php
Modules/Manufacturing/Http/Controllers/Concerns/RecalculatesTotals.php
Modules/Manufacturing/Http/Controllers/Concerns/SafeListing.php
    → Modules/Manufacturing/   (أنشئ المجلدات؛ الموديول بلا مجلد app/ اليوم)

database/migrations/2026_08_22_00000{1,2,3,4}_*.php
    → Modules/Manufacturing/database/migrations/
      (المجلد موجود وفارغ — والمزوّد يحمّله بـ loadMigrationsFrom)
```

كلها فُحصت بـ`php -l` — سليمة صياغةً.

---

## 3. الترتيب

### الخطوة 1 — الملفات والهجرات

```bash
git checkout -b fix/manufacturing-foundation
# انسخ الملفات
composer dump-autoload
php artisan migrate
```

الهجرة الأولى ستطبع تحذيراً إن لم تجد حساب انحراف. **هذا متوقّع.**

### الخطوة 2 — اربط حساب الانحراف

الإعدادات ← الحسابات المحاسبية ← المخزون والتصنيع ← «انحراف تكاليف الإنتاج».

**الأنسب:** حساب مصروف جديد تحت «مصروفات التصنيع» (`SystemAccountIds::MANUFACTURING_EXPENSES_PARENT = 2962`).

🟡 **سؤال للمحاسب لا للمطوّر:** هل يريد حساباً واحداً للانحراف، أم انحراف كمية وانحراف سعر منفصلين؟ الخدمة تدعم الاثنين — تمرير مفتاحين بدل واحد.

### الخطوة 3 — فعّل الـ Enum

في `App\Models\ManufacturOrders`:
```php
protected $casts = [
    'status' => \App\Enums\ManufacturOrderStatus::class,
];
```

⚠️ **الموديل يعرّف أربعة ثوابت فقط** (`pending`, `materials_received`, `completed`, `cancelled`) بينما المتحكّم يكتب `'closed'` و`'in_progress'` نصّاً خاماً. الـ Enum يغطي الستة. بعد التفعيل:
- استبدل `ManufacturOrders::STATUS_*` بـ `ManufacturOrderStatus::*`
- `$order->status` صار كائناً — ادمجه في النصوص بـ `->label()`

### الخطوة 4 — اربط الـ Traits

```php
class ManufacturingOrdersController extends Controller
{
    use GeneratesDocumentNumbers,
        RecalculatesTotals,          // ← جديد
        ResolvesAccountingAccounts,
        RespectsAccountingPeriods,
        SafeListing,                 // ← جديد
        UsesDocumentCurrency;
```

### الخطوة 5 — أعِد كتابة العمليات

هذا ما تبقّى، وهو الجزء الأكبر. النمط:

```php
public function completeOrder(Request $request, $id)
{
    // ١. الفترة المحاسبية
    if ($block = $this->accountingPeriodBlock(now())) {
        return back()->withInput()->with('error', $block);
    }

    $request->validate([...]);

    DB::beginTransaction();
    try {
        $order = ManufacturOrders::with('manufacturOrdersItem')->findOrFail($id);

        // ٢. الفرع
        $this->authorize('update', $order);

        // ٣. آلة الحالات
        $order->status->assertCanTransitionTo(ManufacturOrderStatus::Completed);

        $inv = app(InventoryService::class);
        $journal = app(ManufacturingJournalService::class);

        // ٤. الإذن — بمصدر صحيح ورقم من الإعدادات وعملة المستند
        $permit = WarehousePermits::create([
            'permission_type'      => ManufacturingSources::TYPE_ADD,
            'permission_source_id' => ManufacturingSources::PERMIT_FINISHED_PRODUCT,
            'reference_id'         => $order->id,
            'reference_type'       => ReferenceType::MANUFACTURING_ORDER,
            'number'               => $this->resolveDocumentNumber(null, ManufacturingSources::SECTION_PERMIT_ADD),
            'permission_date'      => now(),
            'store_houses_id'      => $request->storehouse_id,
            'currency'             => $this->documentCurrency($order),
            'details'              => 'إضافة منتج تام من أمر التصنيع: '.$order->name,
            'grand_total'          => 0,
            'created_by'           => auth()->id(),
            'status'               => ManufacturingSources::STATUS_APPROVED,
        ]);

        // ٥. المخزون — ذرّي، بمتوسط مرجّح، مع stock_before/after
        $inv->addPermitLine($permit, $order->product_id, $actualQty, $unitCost, 'receive');
        $inv->sealPermit($permit);            // الإجمالي من البنود لا العكس

        // ٦. القيود الستة — بحسابات الإعدادات وتوازن مؤكد
        $journal->post($lines, $order->id, 'إتمام أمر التصنيع: '.$order->name,
                       date: now(), currency: $order->currency,
                       clientId: $order->client_id, employeeId: $order->employee_id);

        // ٧. التحقق أن WIP أُقفل
        if (abs($journal->wipResidueFor($order->id)) > 0.01) {
            throw new \RuntimeException('حساب مخزون تحت التشغيل لم يُقفل — راجع قيد الانحراف.');
        }

        $order->update([
            'status' => ManufacturOrderStatus::Completed,
            'completed_at' => now(),
            'warehouse_permit_id' => $permit->id,
            'updated_by' => auth()->id(),
        ]);

        // ٨. السجلات الثلاثة بنداء واحد
        OperationLog::record('completed', ManufacturingSources::LOG_ORDER, $order,
            'تم إتمام أمر التصنيع **'.$order->name.'** — إذن رقم '.$permit->number);

        DB::commit();

        return redirect()->route('manufacturing.orders.show', $order->id)
            ->with('success', 'تم إتمام أمر التصنيع بنجاح.');

    } catch (\Throwable $e) {
        DB::rollBack();
        OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'completeOrder', $id);

        return back()->with('error', $this->safeError($e, 'تعذّر إتمام أمر التصنيع.'));
    }
}
```

**ثمانية عناصر في كل عملية:** فترة ← فرع ← حالة ← إذن ← مخزون ← قيد ← تحقق ← سجل.

---

## 4. القيود الستة بعد الإصلاح

| # | القيد | مدين | دائن |
|---|---|---|---|
| 1 | صرف المواد الخام | `work_in_progress_inventory` | `raw_materials_inventory` |
| 2 | تحميل المصروفات | `work_in_progress_inventory` | حساب المصروف من البند |
| 3 | تحميل أجور التشغيل | `work_in_progress_inventory` | `manufacturing_overhead` |
| 4 | استلام المنتج التام | `finished_goods_inventory` | `work_in_progress_inventory` |
| 5 | استلام الهالك | `waste_inventory` | `work_in_progress_inventory` |
| **6** | **الانحراف** | `production_variance` | `work_in_progress_inventory` |

```
الانحراف = (المحمَّل على WIP) − (قيمة المنتج التام + الهالك)
```

القيد السادس موجب أو سالب — الخدمة تقلب الطرفين تلقائياً.

**التحقق:** `$journal->wipResidueFor($order->id)` يجب أن يُرجع **صفراً** لكل أمر مكتمل.

---

## 5. ما لم يُنجَز بعد

| البند | الحجم | ملاحظة |
|---|---|---|
| إعادة كتابة العمليات السبع في `ManufacturingOrdersController` | 3-4 أيام | النمط أعلاه |
| `BomFinishService` — تفكيك `finish()` (248 سطراً) | يومان | |
| Policies للفروع + `BelongsToBranch` | يومان | `JournalEntry` يملأ `branch_id` تلقائياً؛ باقي الجداول لا |
| قالب طباعة أمر التصنيع | يوم | البنية موجودة في `DocumentTemplateSeeder` |
| إعدادات إشعارات التصنيع | يوم | `NotificationSettingSeeder` هو المرجع |
| الاختبارات | 3 أيام | |
| إصلاح بيانات `permission_source_id = 15` | يوم | **بعد تأكيد أنها من التصنيع لا من نقطة البيع** |

**الاستعلام الذي يحسم الأخير:**
```sql
SELECT permission_source_id, COUNT(*), MIN(created_at), MAX(created_at)
FROM warehouse_permits
WHERE reference_id IN (SELECT id FROM manufactur_orders)
GROUP BY permission_source_id;
```

---

## 6. سؤالان لا يجيب عنهما الكود

**١. هل النظام يعمل على بيانات حقيقية الآن؟**
إن نعم، تُقدَّم مرحلة الفروع وتصير مرحلة إصلاح البيانات أثقل بكثير.

**٢. حساب الانحراف — واحد أم اثنان؟**
سؤال للمحاسب. الخدمة تدعم الاثنين.
