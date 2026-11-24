# التركيب — دورة أمر التصنيع

**كل ملف هنا مبنيّ على قراءة نظامك.** فُحصت كلها بـ`php -l`، وتُحقِّق من أن كل عمود مستعمَل موجود في `$fillable` وفي الجدول.

---

## قبل البدء

```bash
cd C:\xampp\htdocs\fawtmaintest\fawtmaintest\web\fawtmaintest
git checkout -b fix/manufacturing-lifecycle
mysqldump -u root fawtmaintest > backup-before-manufacturing.sql
```

**لا تتخطَّ النسخة الاحتياطية** — الهجرة `000005` تغيّر ENUM، و`000006` تغيّر أنواع أعمدة.

---

## ١) الملفات

| من الحزمة | إلى |
|---|---|
| `app/Enums/ManufacturOrderStatus.php` | `app/Enums/` |
| `Modules/Manufacturing/Constants/ManufacturingSources.php` | أنشئ المجلد |
| `Modules/Manufacturing/Services/*.php` | أنشئ المجلد |
| `Modules/Manufacturing/Http/Controllers/Concerns/*.php` | أنشئ المجلد |
| `database/migrations/2026_08_22_*.php` | `Modules/Manufacturing/database/migrations/` |

```bash
composer dump-autoload
```

مجلد هجرات الموديول موجود وفارغ، و`ManufacturingServiceProvider::boot()` يحمّله بـ`loadMigrationsFrom`.

---

## ٢) تعديلات الموديل — إلزامية

### `app/Models/ManufacturOrders.php`

```php
use App\Enums\ManufacturOrderStatus;

protected $fillable = [
    // … الحقول القائمة كما هي …
    'warehouse_permit_id',          // ← أضِف
];

protected $casts = [
    'status' => ManufacturOrderStatus::class,   // ← أضِف
];
```

**واحذف الثوابت والدوال المساعدة** — صارت في الـ Enum:

```php
// احذف:
const STATUS_PENDING = 'pending';
const STATUS_MATERIALS_RECEIVED = 'materials_received';
const STATUS_COMPLETED = 'completed';
const STATUS_CANCELLED = 'cancelled';

public function isCompleted() { … }
public function canBeCompleted() { … }
public function canReceiveMaterials() { … }
public function canBeCancelled() { … }
```

**البديل في القوالب والكود:**

| القديم | الجديد |
|---|---|
| `$order->isCompleted()` | `$order->status === ManufacturOrderStatus::Completed` |
| `$order->canBeCompleted()` | `$order->status->canComplete()` |
| `$order->canReceiveMaterials()` | `$order->status->canReceiveMaterials()` |
| `$order->canBeCancelled()` | `$order->status->canCancel()` |
| `ManufacturOrders::STATUS_PENDING` | `ManufacturOrderStatus::Pending` |
| `{{ $order->status }}` في قالب | `{{ $order->status->label() }}` |

> ⚠️ **`$order->status` صار كائناً.** أي دمج نصّي مباشر يرمي
> `Object of class … could not be converted to string`. ابحث:
> ```bash
> grep -rn '\$order->status' Modules/Manufacturing/resources/views/
> ```

### `app/Models/WarehousePermitsProducts.php`

```php
protected $fillable = [
    // … كما هي …
    'store_house_id',    // ← أضِف
];
```

`BOMController:529` يكتبه اليوم فيُهمَل صامتاً بحماية الإسناد الجماعي.

---

## ٣) الهجرات

```bash
php artisan migrate --path=Modules/Manufacturing/database/migrations
```

| الهجرة | ما تفعله | ملاحظة |
|---|---|---|
| `000001` | مفتاح «انحراف تكاليف الإنتاج» في الحسابات المحاسبية | قد تطبع تحذيراً — متوقّع |
| `000002` | فهرس `(reference_type, reference_id)` على القيود | يخدم النظام كله |
| `000003` | `client_relations.reference_id` — ربط الملاحظة بأمرها | |
| `000004` | تحويل المفاتيح الأجنبية الصفرية إلى NULL | تطبع عدد ما نُظِّف |
| `000005` | توسيع ENUM الحالة + `warehouse_permit_id` | **تطبع تحذيراً إن وُجدت أوامر بحالة فارغة — اقرأه** |
| `000006` | الكميات الكسرية | **اختيارية** — تخطَّها إن كانت موادك بالحبة |

---

## ٤) اربط حساب الانحراف — بلا هذا لن يكتمل أي أمر

**الإعدادات ← الحسابات المحاسبية ← المخزون والتصنيع ← «انحراف تكاليف الإنتاج»**

الأنسب: حساب مصروف جديد تحت «مصروفات التصنيع» (`SystemAccountIds::MANUFACTURING_EXPENSES_PARENT = 2962`).

**هذا الرفض مقصود:** الترحيل بلا حساب انحراف يترك WIP لا يصفر أبداً — وهو العيب الذي جئنا نُصلحه.

---

## ٥) المتحكّم

في `Modules/Manufacturing/Http/Controllers/ManufacturingOrdersController.php`:

### أضف الاستيرادات

```php
use App\Enums\ManufacturOrderStatus;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\ManufacturingOrderLifecycle;
use Modules\Manufacturing\Http\Controllers\Concerns\RecalculatesTotals;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;          // ← يُصلح P1-01 نهائياً
use Illuminate\Support\Facades\Storage;      // ← يُصلح P1-04
```

### أضف السمات إلى سطر `use` داخل الصنف

```php
class ManufacturingOrdersController extends Controller
{
    use AuthorizesRequests,
        GeneratesDocumentNumbers,
        ManufacturingOrderLifecycle,
        RecalculatesTotals,
        ResolvesAccountingAccounts,
        RespectsAccountingPeriods,
        SafeListing,
        UsesDocumentCurrency;
```

### احذف الدوال المستبدَلة

```
receiveMaterials              returnMaterials
completeOrder                 undoCompletion
cancelOrder                   closeOrder
reopenOrder                   destroy
processWasteMaterials         createManufacturingJournalEntries
createWarehousePermitForOrder createMaterialsReceiveJournalEntry
createMaterialsReturnJournalEntry
```

### واحذف الميتة معها — واحداً وعشرين موضعاً

```
finish                                  finishOrder
finishOrderWithPermitCleanup            finishOrderWithSelectivePermitDeletion
deleteOldWarehousePermit                addWasteMaterialsToSamePermit
addProductDetailsForManufacturedProduct addWasteMaterialsToProductDetails
removeProductDetailsForManufacturingOrder
getCostBredown                          calculateCostFromProductDetails
calculateCostFromPurchaseHistory        calculateCostAlternativeMethod
calculateProductTotal                   calculateOrderTotalCost
calculateUnitPrice                      calculateWasteMaterialsTotal
recordInventoryMovements                recordProductMovement
reverseInventoryMovement                deleteRelatedStockMovements
getProductStockBefore                   updateProductInventory
getWarehouseName                        getProductAvailableQuantity
createJournalEntry (الكتلة المعلّقة 963-1011)
```

> **استثناء:** أبقِ `getAccountDropdownOptions` — تستعملها `create` و`edit`.

### احذف المسارات الميتة

في `routes/web.php`:
```php
// احذف — دوالها محذوفة ولا واجهة تستدعيها
Route::get('/finish/{id}', …)->name('manufacturing.orders.finish');
Route::post('/finish/{id}', …)->name('manufacturing.orders.finish.process');
Route::delete('/delete-note/{noteId}', …)   // اسم مكرر
```

**وصحّح مسار الحذف** — صار `destroy` موجودة وآمنة:
```php
Route::delete('/delete/{id}', [ManufacturingOrdersController::class, 'destroy'])
    ->name('manufacturing.orders.delete')
    ->middleware('permission:stock.manufacturing_orders.delete');
```
وفي `orders/partials/delete-modal.blade.php:32` بدّل `<a href>` بنموذج `POST` + `@method('DELETE')` + `@csrf`.

---

## ٦) الاختبار اليدوي — دورة كاملة

| # | الخطوة | المتوقَّع |
|---|---|---|
| 1 | أنشئ أمر تصنيع بمواد ومصروفات وتشغيل | الرقم بالبادئة `MO` من الإعدادات |
| 2 | استلم المواد | إذن **صرف** بالبادئة `WOUT`، مصدره **17**، والمخزون ينقص |
| 3 | افتح دليل الحسابات | WIP **مدين** بقيمة المواد |
| 4 | حاول استلاماً ثانياً | يُرفض: «انتقال غير مسموح» |
| 5 | أرجع كمية أكبر مما صُرف | يُرفض برسالة تسمّي المتاح |
| 6 | أرجع جزءاً | الحالة **لا** تعود «جديد» |
| 7 | أتمّ الأمر بكمية فعلية أقلّ | إذن **إضافة** `WIN` مصدره **3**، والمنتج في المستودع |
| 8 | راجع القيد | **ستة أطراف**، آخرها انحراف الإنتاج |
| 9 | راجع WIP للأمر | **صفر** |
| 10 | تراجع عن الإتمام | المنتج يخرج، والقيود تُعكس بقيدٍ مقابل لا بحذف |
| 11 | تراجع مرتين متتاليتين | لا تضاعف — `is_reversed` يمنع |
| 12 | ألغِ الأمر | إذن إرجاع مصدره **16**، والمواد تعود |
| 13 | افتح سجل الأنشطة | سطر لكل عملية بالوصف الصحيح |
| 14 | افتح سجل الأحداث | `manufacturing_order.completed` وأخواته |

**استعلام التحقق النهائي — يجب أن يُرجع صفر صفوف:**

```sql
SELECT o.id, o.code, o.name,
       ROUND(SUM(d.debit - d.credit), 2) AS wip_residue
FROM manufactur_orders o
JOIN journal_entries e
  ON e.reference_id = o.id AND e.reference_type = 'MO'
JOIN journal_entry_details d
  ON d.journal_entry_id = e.id
JOIN accounting_settings s
  ON s.key = 'work_in_progress_inventory' AND s.account_id = d.account_id
WHERE o.status IN ('completed', 'closed')
GROUP BY o.id, o.code, o.name
HAVING ABS(wip_residue) > 0.01;
```

---

## ٧) ما تبقّى

| البند | الحجم |
|---|---|
| حراس `update()` — الحالة والفترة | ساعة |
| الملاحظات: الربط و`getNotes` و`deleteNote` | ساعتان |
| `BomFinishService` — تفكيك `finish()` (248 سطراً) | يومان |
| Policies + `BelongsToBranch` | يومان |
| قالب طباعة أمر التصنيع | يوم |
| إعدادات إشعارات التصنيع | يوم |
| الاختبارات الآلية | 3 أيام |
| إصلاح بيانات `permission_source_id = 15` | يوم |

**استعلام يحسم الأخير — شغّله وأرسل الناتج:**

```sql
SELECT permission_source_id, permission_type, COUNT(*) AS n,
       MIN(created_at) AS first_at, MAX(created_at) AS last_at
FROM warehouse_permits
WHERE reference_id IN (SELECT id FROM manufactur_orders)
GROUP BY permission_source_id, permission_type
ORDER BY n DESC;
```

---

## ٨) إن انكسر شيء

| العرَض | السبب الأرجح |
|---|---|
| «الحساب المحاسبي production_variance غير مربوط» | الخطوة ٤ لم تُنفَّذ — **وهذا مقصود** |
| `Object of class ManufacturOrderStatus could not be converted to string` | قالب يدمج `$order->status` نصّاً — استعمل `->label()` |
| «انتقال غير مسموح من X إلى Y» | آلة الحالات تعمل. راجع `allowedNext()` إن كان سير عملك يحتاج انتقالاً آخر |
| «حساب مخزون تحت التشغيل لم يُقفل» | قيد الانحراف لم يُرحَّل — راجع ربط الحساب |
| «رصيد غير كافٍ» عند التراجع | بيع بعض المنتج بعد الإتمام. التراجع غير ممكن — استعمل تسوية جرد |
| `Call to undefined method … authorize()` | ينقص `use AuthorizesRequests` |

**التراجع الكامل:**
```bash
git checkout .
mysql -u root fawtmaintest < backup-before-manufacturing.sql
```
