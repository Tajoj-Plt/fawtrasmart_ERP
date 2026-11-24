# العميل الافتراضي (Default Client)

## الغرض
عميل نظامي افتراضي يُستخدم كـ **fallback** في العمليات التي لا تملك عميلاً محدداً:
- فواتير بلا عميل مرتبط
- تحويلات مخزنية داخلية
- عمليات نظامية عامة

## المعرّف
```php
Client::DEFAULT_CLIENT_ID // = 1
// أو
\Database\Seeders\DefaultClientSeeder::DEFAULT_CLIENT_ID // = 1
```

## البيانات
| الحقل | القيمة |
|------|--------|
| المعرّف | 1 |
| الكود | DEFAULT |
| الاسم التجاري | عميل افتراضي (نظام) |
| الهاتف | 0000000000 |
| البريد | default@system.local |
| حد الائتمان | 0 |
| الرصيد الافتتاحي | 0 |
| الحالة | نشط |
| متوقف عن الائتمان | نعم ✓ |

## الاستخدام

### جلب العميل الافتراضي
```php
// الطريقة الأولى
$defaultClient = Client::find(Client::DEFAULT_CLIENT_ID);

// الطريقة الثانية
$defaultClient = Client::where('code', 'DEFAULT')->first();

// الطريقة الثالثة
$defaultClient = Client::where('id', 1)->first();
```

### التحقق من كونه عميلاً افتراضياً
```php
if ($client->id === Client::DEFAULT_CLIENT_ID) {
    // هذا هو العميل الافتراضي
}
```

### استخدام في العمليات النظامية
```php
// إنشاء فاتورة بدون عميل محدد
$invoice = Invoice::create([
    'client_id' => Client::DEFAULT_CLIENT_ID,
    'amount' => 100,
    // ...
]);

// تحويل مخزني بلا عميل
$transfer = InventoryTransfer::create([
    'client_id' => Client::DEFAULT_CLIENT_ID,
    // ...
]);
```

## تنصيب جديد
عند تشغيل `php artisan migrate:fresh --seed`، سيتم:
1. ✅ إنشاء العميل الافتراضي تلقائياً
2. ✅ تخصيصه كـ fallback في النظام
3. ✅ تفعيل flag "متوقف عن الائتمان" لمنع حسابات سالبة

## ملاحظات مهمة
- **لا تحذف** العميل الافتراضي — قد تعطّل عمليات نظامية كثيرة
- **لا تغيّر معرّفه** (ID) — استخدم القيمة `1` دائماً
- **لا تعدّل بيانات الاتصال** — مجرد placeholder للنظام
