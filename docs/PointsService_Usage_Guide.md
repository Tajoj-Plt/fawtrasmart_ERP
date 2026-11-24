# دليل استخدام PointsService
## خدمة النقاط المركزية

> **هام:** هذا السرفيز لا يُعدّل على أي كنترولر أو كود موجود. يتم استدعاؤه من أي مكان في النظام (موديولات، كنترولرات، Jobs، Events).

---

## الفكرة الأساسية

بدلاً من تكرار عمليات النقاط في كل مكان (حساب النقاط → فحص الإعدادات → إنشاء المعاملة → ...)، يكفي استدعاء:

```php
// للموظفين
$result = app(PointsService::class)->awardEmployee($employeeId, 'invoice_created', [...]);

// للعملاء
$result = app(PointsService::class)->awardClient($clientId, 'invoice_paid', [...]);
```

والسرفيز يتكفل بتنفيذ **10 عمليات** تلقائياً:
1. حساب النقاط من `PointsSetting` (أو يدوياً)
2. تطبيق مضاعف المستوى (`points_multiplier` من `PointsTier`)
3. التحقق من `PointsGeneralSetting` (تفعيل، حدود، كشف احتيال)
4. إنشاء `PointsTransaction` مع `metadata` (JSON)
5. إنشاء `PointsBatch` (انتهاء الصلاحية)
6. فحص ترقية `PointsTier` → `TierUpgradeLog`
7. فحص ومنح `Achievement` عبر `AchievementService`
8. تحديث تقدم `Challenge` عبر `ChallengeService`
9. إرسال كل الإشعارات عبر `PointsNotificationService`
10. تحديث `PointsDailySummary` (الملخص اليومي للتقارير)

---

## الملفات المُنشأة

| الملف | الوصف |
|---|---|
| `app/Services/PointsService.php` | الخدمة المركزية |
| `app/Services/PointsResult.php` | كائن النتيجة |

---

## الطرق المتاحة (API)

### طرق الموظفين

| الطريقة | الوصف |
|---|---|
| `awardEmployee(int $employeeId, string $actionType, array $options)` | منح نقاط لموظف |
| `deductEmployee(int $employeeId, string $actionType, array $options)` | خصم نقاط من موظف |
| `canAwardEmployee(int $employeeId, string $actionType, array $options)` | معاينة قبل المنح |
| `awardEmployeesBulk(array $ids, string $actionType, array $options)` | منح لعدة موظفين |

### طرق العملاء

| الطريقة | الوصف |
|---|---|
| `awardClient(int $clientId, string $actionType, array $options)` | منح نقاط لعميل |
| `deductClient(int $clientId, string $actionType, array $options)` | خصم نقاط من عميل |
| `canAwardClient(int $clientId, string $actionType, array $options)` | معاينة قبل المنح |
| `awardClientsBulk(array $ids, string $actionType, array $options)` | منح لعدة عملاء |

### طرق عامة

| الطريقة | الوصف |
|---|---|
| `award(array $params)` | منح نقاط (يحتاج `user_type`) |
| `deduct(array $params)` | خصم نقاط (يحتاج `user_type`) |
| `canAward(array $params)` | معاينة قبل المنح |
| `reverse(PointsTransaction, ?string)` | عكس معاملة سابقة |
| `awardBulk(array $paramsList)` | منح دفعة عامة |

### معالجة الإجراءات التلقائية (الأهم)

| الطريقة | الوصف |
|---|---|
| `processAction(string $actionType, array $context)` | يبحث عن جميع الإعدادات المطابقة (موظف + عميل) ويُنشئ معاملات لكل واحدة |
| `processActions(array $actions)` | معالجة عدة إجراءات دفعة واحدة |
| `getSettingsForAction(string $actionType, ?int $branchId)` | معاينة الإعدادات المطابقة بدون تنفيذ |

---

## أمثلة عملية — الموظفين

### 1. منح نقاط عند إنشاء فاتورة (موظف)

```php
use App\Services\PointsService;

$result = app(PointsService::class)->awardEmployee(
    $invoice->employee_id,
    'invoice_created',
    [
        'amount'      => $invoice->total,
        'description' => 'فاتورة رقم ' . $invoice->invoice_number,
        'branch_id'   => $invoice->branch_id,
        'reference_type' => get_class($invoice),
        'reference_id'   => $invoice->id,
    ]
);
```

### 2. خصم نقاط عند التأخير (موظف)

```php
$result = app(PointsService::class)->deductEmployee(
    $employee->id,
    'late_arrival',
    [
        'description' => 'تأخير في الحضور - ' . now()->toDateString(),
    ]
);
```

### 3. منح نقاط عند إتمام زيارة (موظف)

```php
$result = app(PointsService::class)->awardEmployee(
    $visit->employee_id,
    'visit_completed',
    [
        'branch_id'      => $visit->branch_id,
        'reference_type' => 'App\\Models\\Visit',
        'reference_id'   => $visit->id,
    ]
);
```

### 4. منح نقاط إنشاء سند قبض (موظف)

```php
$result = app(PointsService::class)->awardEmployee(
    $receipt->employee_id,
    'receipt_created',
    [
        'amount'      => $receipt->amount,
        'description' => 'سند قبض رقم ' . $receipt->receipt_number,
        'branch_id'   => $receipt->branch_id,
    ]
);
```

### 5. منح نقاط لعدة موظفين بعد تدريب

```php
$results = app(PointsService::class)->awardEmployeesBulk(
    [1, 2, 3, 5, 8],  // معرفات الموظفين
    'training_completed',
    ['description' => 'إتمام دورة تدريبية - الربع الأول']
);

$successCount = collect($results)->filter->success->count();
echo "تم منح النقاط لـ {$successCount} من أصل " . count($results) . " موظف";
```

---

## أمثلة عملية — العملاء

### 6. منح نقاط عند دفع فاتورة (عميل)

```php
$result = app(PointsService::class)->awardClient(
    $invoice->client_id,
    'invoice_paid',
    [
        'amount'      => $invoice->paid_amount,
        'branch_id'   => $invoice->branch_id,
        'reference_type' => 'App\\Models\\Invoice',
        'reference_id'   => $invoice->id,
    ]
);
```

### 7. منح نقاط عند إحالة عميل جديد

```php
$result = app(PointsService::class)->awardClient(
    $referrer->id,
    'referral',
    [
        'description' => 'إحالة العميل: ' . $newClient->name,
    ]
);

if ($result->hasAchievements()) {
    // العميل حقق إنجازاً! مثل "سفير العلامة"
    $achievementNames = $result->getAchievementNames();
}
```

### 8. منح نقاط عند إتمام طلب (عميل)

```php
$result = app(PointsService::class)->awardClient(
    $order->client_id,
    'order_completed',
    [
        'amount'      => $order->total,
        'branch_id'   => $order->branch_id,
        'reference_type' => get_class($order),
        'reference_id'   => $order->id,
    ]
);
```

### 9. خصم نقاط عند إلغاء حجز (عميل)

```php
$result = app(PointsService::class)->deductClient(
    $reservation->client_id,
    'reservation_cancelled',
    [
        'description' => 'إلغاء الحجز رقم ' . $reservation->id,
    ]
);
```

### 10. منح نقاط لعدة عملاء في حملة تسويقية

```php
$clientIds = Client::where('status_id', 1)->pluck('id')->toArray();

$results = app(PointsService::class)->awardClientsBulk(
    $clientIds,
    'birthday_bonus',
    ['description' => 'مكافأة عيد ميلاد - فبراير 2026']
);
```

---

## معالجة الإجراء التلقائي (processAction) ⭐

> **هذه هي الطريقة الأسهل والأهم** — استدعاء واحد يعالج جميع الإعدادات المطابقة (موظف + عميل).

### مثال: إنشاء سند قبض

```php
use App\Services\PointsService;

// استدعاء واحد فقط — السرفيز يبحث عن كل اعدادات receipt_created
// (إعداد الموظف + إعداد العميل) ويُنشئ معاملة لكل واحد
$results = app(PointsService::class)->processAction('receipt_created', [
    'employee_id'    => $receipt->employee_id,
    'client_id'      => $receipt->client_id,
    'amount'         => $receipt->amount,
    'branch_id'      => $receipt->branch_id,
    'description'    => 'سند قبض رقم ' . $receipt->receipt_number,
    'reference_type' => get_class($receipt),
    'reference_id'   => $receipt->id,
]);

// النتيجة: ['employee' => PointsResult, 'client' => PointsResult, 'settings_found' => 2]

if ($results['employee']?->success) {
    echo "الموظف حصل على " . $results['employee']->pointsAwarded . " نقطة";
}

if ($results['client']?->success) {
    echo "العميل حصل على " . $results['client']->pointsAwarded . " نقطة";
}
```

### مثال: إنشاء فاتورة

```php
$results = app(PointsService::class)->processAction('invoice_created', [
    'employee_id'    => $invoice->employee_id,
    'client_id'      => $invoice->client_id,
    'amount'         => $invoice->total,
    'branch_id'      => $invoice->branch_id,
    'reference_type' => get_class($invoice),
    'reference_id'   => $invoice->id,
]);

// إذا الإعدادات تحتوي:
// - employee + invoice_created → سيُنشأ معاملة للموظف
// - client + invoice_created → سيُنشأ معاملة للعميل
// - لو فقط employee → سيُنشأ فقط للموظف
```

### مثال: معاينة الإعدادات المطابقة (بدون تنفيذ)

```php
$settings = app(PointsService::class)->getSettingsForAction('receipt_created', $branchId);

// $settings = ['employee' => PointsSetting|null, 'client' => PointsSetting|null]

if ($settings['employee']) {
    echo "إعداد الموظف: " . $settings['employee']->points . " نقطة (" . $settings['employee']->calculation_type . ")";
}

if ($settings['client']) {
    echo "إعداد العميل: " . $settings['client']->points . " نقطة (" . $settings['client']->calculation_type . ")";
}
```

### مثال: ربط processAction بكنترولر سند القبض

```php
class ReceiptController extends Controller
{
    public function store(Request $request)
    {
        $receipt = Receipt::create($data);

        // سطر واحد يعالج كل الإعدادات
        app(PointsService::class)->processAction('receipt_created', [
            'employee_id'    => $receipt->employee_id,
            'client_id'      => $receipt->client_id,
            'amount'         => $receipt->amount,
            'branch_id'      => $receipt->branch_id,
            'reference_type' => get_class($receipt),
            'reference_id'   => $receipt->id,
        ]);

        return redirect()->back()->with('success', 'تم حفظ سند القبض');
    }
}
```

### مثال: ربط processAction بـ Observer

```php
// app/Observers/ReceiptObserver.php
class ReceiptObserver
{
    public function created(Receipt $receipt)
    {
        app(\App\Services\PointsService::class)->processAction('receipt_created', [
            'employee_id'    => $receipt->employee_id,
            'client_id'      => $receipt->client_id,
            'amount'         => $receipt->amount,
            'branch_id'      => $receipt->branch_id,
            'reference_type' => get_class($receipt),
            'reference_id'   => $receipt->id,
        ]);
    }
}
```

---

## أمثلة عامة

### 11. معاينة النقاط قبل المنح

```php
$preview = app(PointsService::class)->canAwardClient(
    $client->id,
    'order_completed',
    ['amount' => $order->total]
);

if ($preview->success) {
    echo "سيحصل العميل على {$preview->pointsAwarded} نقطة";
} else {
    echo "لا يمكن المنح: {$preview->message}";
}
```

### 12. عكس معاملة سابقة

```php
$originalTransaction = PointsTransaction::find($transactionId);

$result = app(PointsService::class)->reverse(
    $originalTransaction,
    'إلغاء الفاتورة رقم 1234'
);
```

### 13. منح نقاط يدوية (بدون إعدادات)

```php
$result = app(PointsService::class)->awardEmployee(
    $employee->id,
    'performance_bonus',
    [
        'points'      => 100,  // ← نقاط يدوية تتجاوز الحساب التلقائي
        'description' => 'مكافأة أداء استثنائي - الربع الأول',
    ]
);
```

### 14. تمرير بيانات إضافية (metadata)

```php
$result = app(PointsService::class)->awardEmployee(
    $employee->id,
    'invoice_created',
    [
        'amount'      => $invoice->total,
        'branch_id'   => $invoice->branch_id,
        'metadata'    => [
            'invoice_number' => $invoice->invoice_number,
            'client_name'    => $invoice->client->name,
            'items_count'    => $invoice->items->count(),
        ],
    ]
);
```

---

## التعامل مع النتيجة (PointsResult)

```php
$result = app(PointsService::class)->awardEmployee($id, 'action', [...]);

// الحالات الأساسية
$result->success;          // bool: هل نجحت العملية؟
$result->message;          // string: رسالة النتيجة
$result->pointsAwarded;    // int: عدد النقاط المُنفَّذة
$result->transaction;      // PointsTransaction: المعاملة المُنشأة
$result->batch;            // PointsBatch|null: دفعة الصلاحية

// الترقية
$result->hasTierChange();  // bool: هل تمت ترقية المستوى؟
$result->getNewTierName(); // string|null: اسم المستوى الجديد
$result->getOldTierName(); // string|null: اسم المستوى القديم

// الإنجازات
$result->hasAchievements();    // bool: هل تم تحقيق إنجازات؟
$result->getAchievementNames(); // string: أسماء الإنجازات مفصولة بفاصلة

// التحذيرات (مضاعف المستوى، أخطاء غير حرجة)
$result->hasWarnings();    // bool
$result->warnings;         // array: قائمة التحذيرات

// الملخص اليومي
$result->dailySummaryUpdate; // array|null: تفاصيل تحديث الملخص

// تحويل للمصفوفة أو JSON (مفيد في APIs)
$result->toArray();
$result->toJson();
```

---

## كيفية تطبيق السرفيز في المشروع

### الطريقة 1: الاستدعاء المباشر في الكنترولر

```php
use App\Services\PointsService;

class InvoiceController extends Controller
{
    public function store(Request $request)
    {
        $invoice = Invoice::create($data);

        // منح نقاط للموظف
        app(PointsService::class)->awardEmployee(
            $invoice->employee_id,
            'invoice_created',
            ['amount' => $invoice->total, 'branch_id' => $invoice->branch_id]
        );

        // منح نقاط للعميل
        app(PointsService::class)->awardClient(
            $invoice->client_id,
            'invoice_created',
            ['amount' => $invoice->total, 'branch_id' => $invoice->branch_id]
        );

        return redirect()->back()->with('success', 'تم حفظ الفاتورة');
    }
}
```

### الطريقة 2: عبر Observer

```php
// app/Observers/InvoiceObserver.php
use App\Services\PointsService;

class InvoiceObserver
{
    public function created(Invoice $invoice)
    {
        // نقاط الموظف
        app(PointsService::class)->awardEmployee(
            $invoice->employee_id,
            'invoice_created',
            [
                'amount' => $invoice->total,
                'branch_id' => $invoice->branch_id,
                'reference_type' => get_class($invoice),
                'reference_id' => $invoice->id,
            ]
        );

        // نقاط العميل
        if ($invoice->client_id) {
            app(PointsService::class)->awardClient(
                $invoice->client_id,
                'invoice_created',
                [
                    'amount' => $invoice->total,
                    'branch_id' => $invoice->branch_id,
                    'reference_type' => get_class($invoice),
                    'reference_id' => $invoice->id,
                ]
            );
        }
    }
}
```

### الطريقة 3: عبر Event/Listener

```php
// في Listener
use App\Services\PointsService;
use App\Events\VisitCompleted;

class AwardVisitPoints
{
    public function handle(VisitCompleted $event)
    {
        app(PointsService::class)->awardEmployee(
            $event->visit->employee_id,
            'visit_completed',
            ['branch_id' => $event->visit->branch_id]
        );
    }
}
```

### الطريقة 4: عبر Job

```php
// app/Jobs/AwardPointsJob.php
use App\Services\PointsService;

class AwardPointsJob implements ShouldQueue
{
    public function __construct(
        private string $userType,
        private int $userId,
        private string $actionType,
        private array $options = []
    ) {}

    public function handle(PointsService $service): void
    {
        if ($this->userType === 'employee') {
            $service->awardEmployee($this->userId, $this->actionType, $this->options);
        } else {
            $service->awardClient($this->userId, $this->actionType, $this->options);
        }
    }
}

// الاستدعاء
AwardPointsJob::dispatch('employee', $employeeId, 'sales_target_achieved', ['points' => 50]);
```

---

## جميع action_types المدعومة

### عمليات العملاء (Client)

| action_type | الوصف | نوع الحساب |
|---|---|---|
| `invoice_created` | إنشاء فاتورة | tiered |
| `invoice_paid` | دفع فاتورة | percentage 1% |
| `receipt_created` | سند قبض | per_amount/50 |
| `payment_voucher` | سند صرف | fixed -5 |
| `return_invoice` | مرتجع فاتورة | percentage 0.5% |
| `payment_received` | استلام دفعة | per_amount/100 |
| `visit_completed` | إتمام زيارة | fixed 3 |
| `referral` | إحالة عميل | fixed 50 |
| `order_completed` | إتمام طلب | tiered |
| `review_submitted` | تقديم مراجعة | fixed 10 |
| `profile_completed` | إكمال الملف | fixed 25 |
| `first_purchase` | أول عملية شراء | fixed 100 |
| `birthday_bonus` | مكافأة عيد الميلاد | fixed 50 |
| `subscription_renewed` | تجديد اشتراك | percentage 2% |
| `membership_upgraded` | ترقية عضوية | fixed 75 |
| `reservation_confirmed` | تأكيد حجز | fixed 10 |
| `reservation_cancelled` | إلغاء حجز | fixed -15 |
| `cheque_received` | استلام شيك | per_amount/500 |
| `rental_paid` | دفع إيجار | percentage 1% |

### عمليات الموظفين (Employee)

| action_type | الوصف | نوع الحساب |
|---|---|---|
| `invoice_created` | إنشاء فاتورة | per_amount/200 |
| `receipt_created` | سند قبض | per_amount/500 |
| `payment_voucher` | سند صرف | fixed 2 |
| `payment_collected` | تحصيل مبلغ | percentage 0.5% |
| `visit_completed` | إتمام زيارة | fixed 5 |
| `new_client_acquired` | اكتساب عميل جديد | tiered |
| `performance_bonus` | مكافأة أداء | fixed 30 |
| `late_arrival` | تأخير حضور | fixed -10 |
| `absence` | غياب | fixed -20 |
| `attendance_on_time` | حضور في الوقت | fixed 2 |
| `task_completed` | إتمام مهمة | fixed 5 |
| `sales_target_achieved` | تحقيق هدف مبيعات | tiered |
| `return_processed` | معالجة مرتجع | fixed 3 |
| `inventory_count` | جرد مخزون | fixed 15 |
| `training_completed` | إتمام تدريب | fixed 20 |
| `customer_satisfaction` | رضا عميل | tiered |
| `customer_complaint` | شكوى عميل | fixed -15 |
| `overtime_hours` | ساعات إضافية | per_amount/1 |
| `early_completion` | إنجاز مبكر | fixed 10 |
| `late_delivery` | تأخير تسليم | fixed -5 |
| `cash_sale` | بيع نقدي | percentage 0.25% |
| `overdue_collection` | تحصيل متأخر | percentage 1% |
| `collaboration` | تعاون | fixed 10 |

---

## الإعدادات المطبَّقة تلقائياً

| الإعداد | الفحص | المرحلة |
|---|---|---|
| `system_enabled` | هل النظام مفعّل؟ | قبل كل عملية |
| `enable_employee_points` | هل نقاط الموظفين مفعلة؟ | التحقق |
| `enable_client_points` | هل نقاط العملاء مفعلة؟ | التحقق |
| `max_points_per_transaction` | سقف النقاط لكل معاملة | التحقق |
| `max_daily_earn` | سقف الكسب اليومي | التحقق |
| `enable_fraud_detection` | كشف النشاط المشبوه | التحقق |
| `suspicious_threshold` | عدد المعاملات/ساعة | التحقق |
| `points_multiplier` | مضاعف المستوى (1.5x) | حساب النقاط |
| `PointsSetting` فرع | إعدادات خاصة بالفرع | حساب النقاط |
| `PointsExpirySetting` | صلاحية الدفعة | إنشاء الدفعة |
| `enable_tiers_system` | فحص ترقية المستوى | بعد المعاملة |
| `enable_achievements_system` | فحص الإنجازات | بعد المعاملة |
| `enable_challenges_system` | تحديث التحديات | بعد المعاملة |
| `enable_notifications_system` | إرسال الإشعارات | بعد المعاملة |

---

## الفرق بين الكنترولر الحالي والسرفيز الجديد

| الميزة | الكنترولر الحالي | PointsService |
|---|---|---|
| مضاعف المستوى | لا يُطبَّق | يُطبَّق تلقائياً |
| metadata (JSON) | لا يُملأ | يُملأ تلقائياً |
| الملخص اليومي | لا يُحدَّث | يُحدَّث تلقائياً |
| فصل الموظف/العميل | لا | `awardEmployee`, `awardClient` |
| Bulk منفصل | لا | `awardEmployeesBulk`, `awardClientsBulk` |
| DB::transaction | لا | نعم (كل العمليات atomic) |
| canAward (معاينة) | لا | نعم |
| reverse (عكس) | لا | نعم |

---

## ملاحظات مهمة

1. **لا تعديل على الكنترولر الحالي** — `PointsTransactionController` يبقى كما هو
2. **DB::transaction** — كل عمليات السرفيز تتم داخل transaction لضمان التكامل
3. **Fail-safe** — إذا فشلت عملية فرعية (إشعار، إنجاز، ملخص)، لا تتوقف العملية بل تُسجَّل كتحذير
4. **مضاعف المستوى** — يُطبَّق فقط على award (ليس deduct)
5. **metadata** — يُسجَّل فيه: `transaction_amount`, `original_points`, `multiplier_applied`, `operation`
6. **الملخص اليومي** — يُحدَّث في `points_daily_summary` بعد كل معاملة (earned, spent, users, tiers, achievements)
