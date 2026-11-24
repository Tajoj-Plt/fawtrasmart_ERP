# تصفير الحركات وتوليد بيانات تجريبية

ملفّان يُضافان إلى `app/Console/Commands/`:

| الملف | الأمر | ما يفعله |
|---|---|---|
| `WipeTransactionalData.php` | `data:wipe-transactions` | حذف المبيعات والمشتريات والقيود والأطراف والمخزون، وإعادة الأرصدة والعدّادات |
| `SeedDemoTransactions.php` | `data:seed-demo` | توليد عملاء ومورّدين وفواتير ومرتجعات وإشعارات دائنة ومدفوعات بقيودها المتوازنة |

---

## قبل أي شيء — نقطتان توقفان التنفيذ

**١. ملفّ `.env` في هذا المشروع يقول `APP_ENV=production`.**

وفيه اسم منشأة حقيقي (`موسسة مشتاق الوتر`)، ومفاتيح اشتراك Fawtra حيّة
(`FAWTRA_CLIENT_ID` / `FAWTRA_SECRET`)، وبيانات SMTP حقيقية، وقاعدة اسمها
`fawtra_6`. اسم المجلَّد `fawtmaintest` وحده ليس دليلاً على أنها قاعدة اختبار.

أمر الحذف **يرفض العمل** ما دام `APP_ENV=production`. هذا مقصود. تحقّق أولاً
من أن `fawtra_6` ليست القاعدة التي يعمل عليها العميل، ثم إمّا تُغيّر
`APP_ENV=local` أو تُمرّر `--force`.

**٢. لا نسخة احتياطية عندك.** استعمل `--backup` — الأمر يأخذ `mysqldump`
ويتوقّف عن الحذف إن فشلت النسخة.

---

## الترتيب الموصى به

```bash
# ١. معاينة: ما الجداول وكم صفّاً في كلٍّ منها — بلا حذف
php artisan data:wipe-transactions --dry-run

# ٢. فحص متطلّبات التوليد قبل الحذف (أصناف، مستودع، حسابات مربوطة)
php artisan data:seed-demo --check

# ٣. الحذف مع نسخة احتياطية — يسأل عن اسم القاعدة للتأكيد
php artisan data:wipe-transactions --backup

# ٤. التوليد
php artisan data:seed-demo --clients=20 --suppliers=20 --sales=60 --purchases=40 --months=6
```

الخطوة ٢ **قبل** الخطوة ٣ عن قصد: إن كان حساب `sales` أو `vat_payable` غير
مربوط فستعرف ذلك وقاعدتك ما زالت كاملة، لا بعد أن أفرغتها.

---

## `data:wipe-transactions`

```
--groups=sales,purchases,accounting,parties,stock   المجموعات المطلوبة (الافتراضي: كلّها)
--dry-run                                            معاينة الأعداد بلا حذف
--backup                                             mysqldump إلى storage/app/backups
--force                                              تخطّي حارس الإنتاج وسؤال التأكيد
```

### المجموعات وما تمسّه

| المجموعة | الجداول |
|---|---|
| `sales` | `invoices`, `invoice_items`, `taxs_invoice`, `invoice_transfers`, `credit_notifications`, `periodic_invoices(+instances)`, `installments(+details, payments)`, `sales_commissions`, `sales_target_achievements/settlements`, `collections_target_*`, `receipts`, `receipt_vouchers`, `payments_process` |
| `purchases` | `purchase_invoices`, `purchase_orders`, `purchase_quotations(+supplier, view)`, `purchase_requested`, `payment_vouchers(+details)`, `supply_orders` |
| `accounting` | `journal_entries`, `journal_entry_details`, `journal_entry_invoices`, `transactions` |
| `parties` | `clients` وجداولها التابعة، `suppliers`, `contacts`, `credit_limit`, `doubtful_debts` |
| `stock` | `warehouse_permits(+products)`, `product_details`, `inventory_items`, `inventory_adjustments(+logs)`, `daily_closings(+stock_details)` |

مرتجعات المبيعات ليست جدولاً مستقلاً: هي صفوف في `invoices` بـ `type='returned'`.
ومرتجعات الشراء صفوف في `purchase_invoices` بـ `type='Return'`. فحذف الجدولين
يأخذها معه.

### ما يُعاد ضبطه بعد الحذف

هذا هو سبب وجود الأمر أصلاً — الحذف وحده يترك القاعدة غير متّسقة:

- **`accounts.balance`** يعود إلى `opening_balance`. العمود يُزاد ويُنقَص مع كل
  قيد (`$account->balance += …`) ولا يُحسب من القيود، فحذف القيود وحده يترك
  أرصدةً معلَّقة لا مصدر لها.
- **حسابات العملاء والمورّدين** في الشجرة تُحذف مع أصحابها (مع `parties`) —
  حسابٌ بلا طرفٍ يبقى في قوائم الاختيار اسماً بلا معنى.
- **`chart_of_accounts` نوع `invoice`**: حسابات فرعية يولّدها
  `JournalEntryService` لكل فاتورة، تصير يتيمة بعد الحذف.
- **`serial_settings.current_number`** يعود صفراً، وإلا بدأت أول فاتورة في
  قاعدة فارغة برقم ٤٧٣.
- الذاكرة المؤقتة + `optimize:clear`.

### ⚠️ ثلاث نقاط انتبه لها

1. **`product_details` ضمن `stock`.** هذا الجدول يحمل حركات الأصناف — ومنها
   **الرصيد الافتتاحي للمخزون**. حذفه يصفّر كميّات كل الأصناف. إن أردت إبقاء
   المخزون فاحذف بلا هذه المجموعة:
   `--groups=sales,purchases,accounting,parties`
2. **`expenses` غير مشمول** رغم ارتباطه بـ `payment_vouchers`. حذف السندات
   يترك مصروفات تشير إلى سندٍ محذوف. أضفه يدوياً إن أردت.
3. **الأصناف والمستودعات والضرائب والقوالب والصلاحيات لا تُمَسّ.** هذا مقصود:
   التوليد يحتاجها.

---

## `data:seed-demo`

```
--clients=20 --suppliers=20 --sales=60 --purchases=40
--months=6      توزيع التواريخ على ٦ أشهر ماضية
--check         فحص المتطلّبات وعرض الحسابات المربوطة بلا كتابة
```

### ما يُولَّد

- عملاء + حساب لكلٍّ منهم تحت `clients_account` (بنفس نمط
  `clients:backfill-accounts`)
- مورّدون + حساب لكلٍّ منهم تحت حساب المورّدين
- فواتير شراء وبنودها، ولثلثيها سداد (كامل أو جزئي)، ولنحو ١٥٪ مرتجع شراء
- فواتير مبيعات وبنودها، ولثلثيها سند قبض، ولنحو ١٥٪ مرتجع مبيعات، ولنحو ١٠٪
  إشعار دائن
- **قيد محاسبي متوازن لكل مستند**، على الحسابات نفسها التي تستعملها الشاشات:
  تُقرأ من «الإعدادات ← الحسابات المحاسبية» وتسقط إلى ثوابت `SystemAccountIds`
  احتياطاً — كما يفعل `InvoicesController` و`InvoicesPurchaseController`.

### القيود المولَّدة

| المستند | مدين | دائن |
|---|---|---|
| فاتورة مبيعات | العميل (الإجمالي) + الخصم المسموح به | المبيعات (قبل الخصم) + ضريبة المخرجات |
| مرتجع مبيعات | مردود المبيعات + ضريبة المخرجات | العميل |
| إشعار دائن | مردود المبيعات + ضريبة المخرجات | العميل |
| سند قبض | الخزينة | العميل |
| فاتورة شراء | المشتريات (الصافي) + ضريبة المدخلات | المورّد |
| مرتجع شراء | المورّد | المشتريات + ضريبة المدخلات |
| سداد للمورّد | المورّد | الخزينة |

كل قيد يُفحص توازنه **قبل** كتابته؛ وقيد غير موازن يُسقط التوليد كلّه —
والتوليد داخل معاملة واحدة، فلا يبقى نصف بيانات. وفي آخر التشغيل يُطبع فحص
إجمالي: مجموع المدين مقابل مجموع الدائن.

### ثلاث حدود صريحة

1. **لا حركة مخزون.** الأمر لا يكتب `warehouse_permits` ولا `product_details`،
   فالكميّات لا تتغيّر. الفواتير سليمة محاسبياً، ومخزنياً غير مُثبَتة.
   إضافة ذلك تحتاج تكرار منطق أذون المستودع في `InvoicesPurchaseController`
   — قُل لي إن أردته.
2. **لا قيود تكلفة مبيعات (COGS).** الشاشات تولّدها بمتوسط تكلفة يُحسب من
   حركات المستودع، وهي غائبة هنا للسبب أعلاه. أثره: هامش الربح في التقارير
   يساوي المبيعات كاملة.
3. **الأكواد لا تمرّ بـ `DocumentNumberService`.** كلّها بالصيغة
   `DEMO-SAL-00001` — وهذا متعمَّد: كل ما وُلِّد يُميَّز بنظرة، ويُحذف وحده
   بـ `WHERE code LIKE 'DEMO-%'` بلا مساس بغيره. لكن عدّادات الترقيم لا
   تتقدّم معه.

---

## التراجع

```bash
# استرجاع النسخة التي أخذها --backup
"C:/xampp/mysql/bin/mysql" -u root fawtra_6 < storage/app/backups/pre-wipe-fawtra_6-XXXXXX.sql

# أو حذف البيانات التجريبية وحدها دون لمس غيرها
DELETE FROM journal_entry_details WHERE journal_entry_id IN
  (SELECT id FROM journal_entries WHERE reference_number LIKE 'DEMO-%');
DELETE FROM journal_entries WHERE reference_number LIKE 'DEMO-%';
DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE code LIKE 'DEMO-%');
DELETE FROM invoices          WHERE code LIKE 'DEMO-%';
DELETE FROM purchase_invoices WHERE code LIKE 'DEMO-%';
DELETE FROM credit_notifications WHERE credit_number LIKE 'DEMO-%';
DELETE FROM receipts          WHERE code LIKE 'DEMO-%';
DELETE FROM clients           WHERE code LIKE 'DEMO-%';
DELETE FROM suppliers         WHERE number_suply LIKE 'DEMO-%';
```

(الحذف اليدوي لا يعيد `accounts.balance` — بعده شغّل
`data:wipe-transactions --groups=accounting --dry-run` لترى الأثر، أو صفّر
الأرصدة يدوياً.)
