# دليل المطوّر — وحدة الحجوزات

> المرجع البنيوي للوحدة: أين يعيش كل شيء، ولماذا وُضع هناك، وأين تُعدّل.

---

## 1. المعمارية في سطر

```
المتحكّم  →  يقرأ الطلب، ينادي الخدمة، يترجم النتيجة إلى شاشة
الخدمة    →  كل قواعد العمل، والمعاملات، والقفل، والقيود
النموذج   →  البيانات والسِّمات المشتركة (فرع، فترة، عملة، حذف ناعم)
التعداد   →  الحالات وجدول الانتقالات المسموحة
```

**قاعدة حاكمة:** لا متحكّم يكتب حجزاً بيده. كل إنشاء وتعديل يمرّ بـ
`ReservationBookingService` — لأن الحجز يُنشأ من الشاشة والـAPI ونقطة البيع
وأمر الكرون، وما وُضع في متحكّم واحد يفلت من البقية.

---

## 2. بنية الملفات

> **2026-09-16:** لم تعد الحجوزات وحدةً مستقلّة. نُقلت بكاملها إلى وحدة العملاء
> (`Modules/Client`) وصارت قسماً في شريط العملاء الجانبي («الحجوزات والمواعيد»).
> أسماءُ المسارات (`Reservations.*`، `appointments.*`) والعنوانُ `Reservations/…`
> والجداولُ والصلاحياتُ ومفتاحُ `booking_management` لم تتغيّر.

```
Modules/Client/
├── Reservations/                         المنطق — Modules\Client\Reservations\…
│   ├── Console/                          reservations:reminders|self-check|sync-appointments، appointments:generate-recurring
│   ├── Enums/ReservationStatus.php       الحالات + جدول الانتقالات
│   ├── Events/  Listeners/  Exceptions/
│   ├── Models/                           Reservation، ReservationService، ReservationSetting، …
│   └── Services/                         الحجز، التوفّر، التسعير، الانتقالات، مزامنة الموعد، مشاركة الموعد
├── Http/Controllers/Reservations/        متحكّمات الحجوزات والمواعيد (+ Concerns/ScopesReservations)
├── Http/Requests/Reservations/           Form Requests
├── Providers/
│   ├── ReservationsServiceProvider.php       الأوامر + مزوّد الأحداث (يسجّله ClientServiceProvider)
│   └── ReservationsEventServiceProvider.php  جدول الأحداث ← الإشعار
├── database/migrations/2026_09_*_reservation*  ترحيلات القسم (يحمّلها ClientServiceProvider)
├── database/seeders/Reservation*Seeder.php     Modules\Client\Database\Seeders
├── resources/views/reservations/         الواجهات — client::reservations.*
│   ├── master.blade.php                  يعرض شريطَ العملاء `client::layouts.sidebar`
│   ├── appointments/                     المواعيد
│   ├── components/  partials/            settings-theme (rsv-)، select_scripts، form، modals…
│   └── services/ settings/ reports/ guide/
└── routes/reservations.php               يحمّله RouteServiceProvider الوحدة تحت `web`
```

### نظام التنسيق: منقولٌ عن المبيعات لا مُخترَع

`partials/settings-theme.blade.php` هو ثيمُ المبيعات
(`sales::sitting.partials.settings-theme`) منقولاً حرفاً بحرف مع تبديل
البادئة وحدَها:

| المبيعات | الحجوزات |
|---|---|
| `sl-` | `rsv-` |
| `--sl-` | `--rsv-` |
| `slHub` | `rsvHub` |
| `data-sl-` | `data-rsv-` |

فالبادئةُ مبدَّلةٌ **قصداً**: صنفٌ باسم `sl-card` في صفحة حجوزات يرث
تنسيقَ المبيعات إن حُمِّل ثيمُها يوماً في الصفحة نفسها، ويصمت إن لم
يُحمَّل — وكلا الحالتين عطلٌ صامت. والفحصُ
`[index] لا أثر لبادئة المبيعات sl- في الناتج` يحرس هذا.

وفوق المنقول أصنافٌ خاصّةٌ بالوحدة لا نظيرَ لها في المبيعات:
`rsv-slots` و`rsv-slot` (شبكة المواعيد المتاحة)، `rsv-timeline`
(مسار الحالات)، و`rpt-*` (شجرة «تابع لـ» المشتركة).

**والشريطُ مسطَّح**: بنودٌ متتالية بلا عناوين مجموعات ولا أكورديون —
على قياس `sales::layouts.sidebar` و`Client`. ولا بندَ «عودة»: شعارُ
«فوترة» في الترويسة هو طريقُ العودة، كما في الوحدتين.

**خارج الوحدة:**

| الملف | الدور |
|---|---|
| `app/Constants/permissions/ReservationsPermissions.php` | كتالوج الصلاحيات (27) |
| `app/Enums/ReferenceType.php` | ثلاث حالات مضافة: `RSVD` / `RSVC` / `RSVR` |
| `app/Models/Booking.php` · `BookingSetting.php` | نموذجا توافق خلفي — **لا يُبنى عليهما** |
| `database/seeders/AccountingSettingsSeeder.php` | ستة مفاتيح حسابات `reservation_*` |
| `app/Services/Settings/ApplicationRegistry.php` | بادئة `Reservations` على `booking_management` |

---

## 3. قاعدة البيانات

### تصحيح التسمية (الهجرة 000001)

كان الوضع مقلوباً:

| قبل | بعد |
|---|---|
| `bookings` = الحجوزات | `reservations` |
| `reservations` = **الإعدادات** | `reservation_settings` |

كلا الجدولين كان فارغاً (فُحص قبل القرار) فلا بيانات تُنقل.
`App\Models\Booking` و`BookingSetting` يشيران إلى الاسمين الجديدين توافقاً
مع `Client::bookings()` وتبويب حجوزات العميل.

### الجداول

| الجدول | الدور |
|---|---|
| `reservations` | مستند الحجز |
| `reservation_settings` | إعدادات الوحدة (صفّ واحد) |
| `reservation_services` | كتالوج الخدمات (البُعد الزمني لمنتج) |
| `reservation_service_employee` | الموظفون المؤهَّلون لكل خدمة |
| `reservation_working_hours` | الدوام — للفرع وللموظف بجدول واحد |
| `reservation_holidays` | العطل والأيام الاستثنائية |
| `reservation_status_histories` | خطّ حياة الحجز |
| `reservation_reminders` | التذكيرات المُرسلة (مفتاح فريد = عدم التكرار) |

### أعمدة تستحق الانتباه

| العمود | ملاحظة |
|---|---|
| `status` | `string` لا `ENUM` — إضافة حالة يجب ألّا تعني `ALTER TABLE` |
| `blocked_start` / `blocked_end` | الفترة المحجوزة فعلاً = الوقتان ± البُفَر. **مخزَّنة لا محسوبة** |
| `employee_id` | يشير إلى `employees` لا `users` |
| `discount_amount` | الخصم بالمبلغ دائماً — لا يُعاد اشتقاقه في أي شاشة |
| `subtotal` | الوعاء **بعد** الخصم، فـ`subtotal + tax = total` |

#### لماذا `blocked_*` مخزَّنان؟

حسابهما في الاستعلام يعني ضمّ `reservation_services` في كل فحص تعارض
وحساب إزاحة وقت في SQL — وحساب الوقت يختلف بين MySQL و SQLite فيفترق
سلوك الحارس بين الإنتاج والاختبارات.

وأهمّ من ذلك: بُفَر الخدمة تتغيّر. تعديل «وقت التنظيف» كان سيعيد تفسير
حجوزات ماضية بأثر رجعي فينقلب حجز صحيح إلى متعارض. العمود المخزَّن يجمّد
ما كان وقت الحجز — كما تُجمَّد العملة والفترة المحاسبية.

---

## 4. النماذج والسِّمات

`Reservation` تستعمل أربع سِمات، لكلٍّ سبب:

| السِّمة | ما تفعله |
|---|---|
| `StampsOwnerContext` | تختم `branch_id`/`employee_id`/`created_by` عند الإنشاء |
| `BelongsToBranch` | `forBranch()` تشمل العقدة وما تحتها في الشجرة |
| `BelongsToAccountingPeriod` | تختم الفترة من `reservation_date` |
| `HasDocumentCurrency` | تملأ العملة مرة ثم تجمّدها |

`accountingDateColumn()` معاد تعريفها إلى `reservation_date` (الافتراضي `date`).

---

## 5. الحالات وسير العمل

`ReservationStatus` تحمل **الحالات وجدول الانتقالات معاً**.

```
DRAFT ─► PENDING ─► CONFIRMED ─► CHECKED_IN ─► IN_PROGRESS ─► COMPLETED
                         │             │
                         └──► RESCHEDULED / CANCELLED / NO_SHOW / REJECTED
```

**لماذا الجدول في التعداد لا في الخدمة؟** لأن السؤال «هل يجوز هذا
الانتقال؟» يُطرح في ثلاثة مواضع: الخدمة تمنع، والمتحكّم يُظهر الأزرار
الجائزة، والقالب يلوّن الشارة. ولو كُتب في الخدمة لقرأه القالب بتخمين
يفترق عنها بعد أول تعديل — فيظهر زرّ يرفضه الحفظ.

### دوال مفتاحية

| الدالّة | الدور |
|---|---|
| `allowedTransitions()` | الحالات التي يجوز الانتقال إليها |
| `isFinal()` | لا مخرج منها |
| `occupiesSlot()` | **هل يُحتسب في كشف التعارض؟** الملغى ومن لم يحضر: لا |
| `occupyingValues()` | لشرط `whereIn` — يستعمله `scopeOccupying()` |
| `color()` / `badgeClass()` | مصدر واحد للّون، فلا تفترق القائمة عن التقويم |

### إضافة حالة جديدة

1. أضف `case` في `ReservationStatus`.
2. أضف سطرها في `label()` و`color()` و`badgeClass()` و`icon()` و`allowedTransitions()` و`occupiesSlot()` — **الـ`match` بلا `default` يجبرك على ذلك**.
3. أضف صلاحيتها في `permissionFor()` بـ`ReservationStatusTransitionService` إن كانت تحتاج واحدة.
4. أضف زرّها في خريطة `$map` بـ`show.blade.php`.

لا هجرة — العمود `string`.

---

## 5.5. الخدمة والمنتج — لماذا جدولان لا جدول

سؤالٌ يُطرح دائماً: في `products` نوعٌ اسمه «خدمي» — فلماذا لا تكون خدمة
الحجز منتجاً من ذلك النوع مباشرةً؟

**الجواب: لأن الجدولين يجيبان سؤالين مختلفين.**

`products` جدول **ما يُباع**: 50 عموداً، أكثرها لا معنى له في موعد —
`barcode`, `storehouse_id`, `weight`, `dimensions`, `expiry_date`,
`low_stock_thershold`, `purchase_unit_id`, `track_inventory`…

و`reservation_services` جدول **قابليّة الحجز**: خمسة أشياء لا يعرفها
`products` ولا مكان لها فيه:

| الحقل | لماذا لا يصلح في `products` |
|---|---|
| `duration` | كم يستغرق الموعد — لا معنى له لسلعة |
| `buffer_before` / `buffer_after` | وقتا التحضير والتنظيف على تقويم الموظف |
| `max_daily` | حدّ يومي للحجز، لا للبيع |
| الموظفون المؤهَّلون | جدول ربط `reservation_service_employee` |

وإضافتها إلى `products` تعني حشر همّ الحجوزات في جدول تقرؤه **خمس وحدات**
(المخزون · نقاط البيع · المتجر · المشتريات · المبيعات) — خمسة عملاء لعمود
لا يخصّ أياً منهم.

### لكنّ الازدواج كان حقيقياً — وأُزيل

الخطأ لم يكن في وجود الجدولين، بل في أن `reservation_services` كان يُلزم
`name` **حتى حين يُربَط بمنتج**، والمنتج يحمل الاسم نفسه. فيُعدَّل اسم
المنتج في كتالوج المخزون ويبقى اسم الخدمة على ما كان، فيقرأ المستخدم اسمين
لشيء واحد.

**القاعدة الآن — المنتج هو المصدر:**

| الحقل | المصدر |
|---|---|
| الاسم | `ReservationService::displayName()` ← المنتج، والحقل المحلّي تجاوز |
| السعر | `effectivePrice()` ← المنتج، والحقل المحلّي تجاوز |
| الضريبة | `effectiveTaxId()` ← المنتج، والحقل المحلّي تجاوز |
| الحساب المحاسبي | المنتج وحده — لا تجاوز |
| المدّة والبُفَر والحدّ والموظفون | الخدمة وحدها — لا مصدر آخر |

`name` صار `nullable` (الهجرة `000012`)، و`StoreReservationServiceRequest`
يُلزمه بـ`required_without:product_id` — إلزامي فقط حين لا منتج.

> **تحذير عند القراءة:** `displayName()` ترجع إلى `$this->product?->name`،
> فأي تحميل مسبق يجب أن يشمل المنتج:
> `->with(['service:id,name,product_id', 'service.product:id,name'])`
> وإلّا صار كل صفّ استعلاماً إضافياً.

---

## 6. الخدمات

### `ReservationAvailabilityService` — الحارس

سبعة أسئلة، كلٌّ كافٍ وحده للمنع:

1. **المدى** — داخل نافذة الحجز المسموحة؟
2. **العطلة** — يوم عطلة للفرع أو الموظف؟
3. **الدوام** — داخل ساعات العمل؟
4. **الإجازة** — للموظف إجازة معتمدة؟ (`leave_requests.status = 'approved'`)
5. **التعارض** — حجز آخر متداخل؟
6. **الحدّ** — بلغ الفرع أو الخدمة حدّه اليومي؟
7. **التكرار** — للعميل حجز في اليوم نفسه والإعداد يمنعه؟

**التداخل الصارم:** `<` و`>` لا `<=` و`>=`. حجز ينتهي 11:00 وآخر يبدأ
11:00 لا يتداخلان — وبـ`<=` كان يُمنع الحجز المتلاصق كلّه.

**سلّم الدوام:** أوقات الموظف إن ضُبطت ← أوقات فرعه ← الإعداد العام.
`workingWindows()` تُعيد `null` (لم يُضبط شيء) أو مجموعة فارغة (مضبوط أنه
مغلق) — **والخلط بينهما يقلب اليوم المهمَل إلى مغلق أو العكس**.

**`availableSlots()` تمرّ على الحارس نفسه**، فلا تعرض الشاشة فترة يرفضها
الحفظ.

### `ReservationBookingService` — المعاملة والقفل

```
DB::transaction ┬─► lockDay()      قفل صفوف الموظف في ذلك اليوم
                ├─► assertSlotAvailable()   يقرأ ما خلف القفل
                └─► الكتابة
```

**فحص التوفّر وحده لا يمنع الحجز المزدوج**: طلبان متزامنان يفحصان معاً
فيجدان الموعد خالياً ثم يكتبان معاً. والفهرس الفريد لا يكفي — يمنع
التطابق التام ولا يمنع **التداخل**.

القفل على صفوف الموظف في ذلك اليوم لا على الجدول: قفل الجدول يسلسل كل
حجوزات المنشأة، وقفل صفّ بعينه لا يمنع إدراج صفّ جديد متداخل.

### `ReservationPricingService` — من يحسب

```
الأساس   = سعر الوحدة
الخصم    = نسبة من الأساس أو مبلغ مقطوع (مقصوص عند الأساس)
الوعاء   = الأساس − الخصم
الضريبة  = من الوعاء، بطريقتها (مضافة/شاملة) عبر TaxCalculator
```

**الضريبة بعد الخصم لا قبله** — وإلا خرج إقرار ضريبي أعلى من المحصَّل.
والطريقة تُقرأ من سجلّ الضريبة (`tax_sittings.type`) لا تُخمَّن.

الجافاسكربت **يعرض تقديراً**، وهذه الخدمة **تقرّر**. نقطة `Reservations.quote`
تُرجع ما ستحفظه الخدمة نفسها.

### `ReservationAccountingService` — المبدأ المحاسبي

> **العربون التزام لا إيراد.**

| العملية | القيد |
|---|---|
| قبض دفعة | مدين الخزينة / دائن `reservation_deposits` |
| ردّ | مدين `reservation_deposits` / دائن الخزينة |
| رسم إلغاء | مدين `reservation_deposits` / دائن `reservation_cancellation_fee` |

ثلاثة حرّاس قبل أي قيد: الفترة المحاسبية، وربط الحسابات، وحدود المبلغ.

**بلا احتياط برمجي:** `AccountingSettings::id($key)` بلا `SystemAccountIds`
fallback. مفتاح غير مربوط يوقف العملية برسالة تسمّيه.

### `ReservationInvoiceService` — لحظة الإيراد

```
مدين : reservation_deposits    بقدر ما قُبض مقدَّماً
مدين : clients_account         بقدر ما بقي
دائن : reservation_revenue     صافي الخدمة بعد الخصم
دائن : vat_payable             إن كانت خاضعة
```

القيد متوازن بالبناء، **ويُفحص توازنه قبل الكتابة** — التقريب إلى منزلتين
في أربعة مبالغ قد يترك كسراً، وقيد غير متوازن يفسد ميزان المراجعة صامتاً.

الفاتورة `invoices.type = 'normal'` وبند `invoice_items.type = 'product'`
و`discount_type` عدد (1=مبلغ، 2=نسبة) — اصطلاح النظام لا اصطلاح الوحدة.

---

## 7. الأحداث والمستمعون

`ReservationEvent` أب لعشرة أحداث، كلّها تحمل `$reservation`.

**تُطلق بعد نجاح الحفظ لا قبله** — المستمعون يرسلون رسائل لا تُلغى
بـrollback.

`EventServiceProvider::$listen` **جدول صريح** و`$shouldDiscoverEvents = false`:
الاكتشاف يربط بحسب توقيع `handle()`، ومستمعنا يوقّع على الأب فيلتقط
الأحداث العشرة كلها بما فيها أحداث الدفع التي لا إشعار لها.

---

## 8. الصلاحيات

27 صلاحية في `ReservationsPermissions`، سبع مجموعات.

```bash
php artisan permissions:sync-catalog
```

```bash
php artisan db:seed --class="Modules\Reservations\Database\Seeders\ReservationsPermissionsSeeder"
```

> **المزامنة تُنشئ الصلاحية ولا تربطها بدور.** بدون السيدر الثاني تبقى
> الوحدة غير مرئية لأحد. السيدر تراكمي: يمنح الناقص ولا يسحب شيئاً.

### محورا الحراسة

| المحور | الأداة |
|---|---|
| العملية | `permission:reservations.*` في بانيات المتحكّمات |
| البيانات | `DataScope` عبر `ScopesReservations` |

`ScopesReservations::scopedQuery()` تقيّد بالفرع، وبمحور الأشخاص تقيّد
بـ`employee_id` لا `created_by` — الحجز يخصّ مقدّم الخدمة، ومن أدخله موظف
استقبال قد لا يكون طرفاً فيه.

**`authorizeReservation()` / `findScoped()` تُنادى في كل شاشة تأخذ حجزاً
بمعرّفه** — بدونها يكفي تخمين رقم في الرابط لقراءة حجز فرع آخر (IDOR).

---

## 9. الإعدادات

`ReservationSetting::current()` — صفّ واحد، memoized لعمر الطلب.

### خريطة من يقرأ كل مفتاح

| المفتاح | يقرؤه |
|---|---|
| `is_enabled` | شاشة الإعدادات |
| `default_branch_id` · `default_status` | `BookingService::create()` |
| `allow_repeated_booking` | `Availability::assertNoDuplicateForClient()` |
| `allow_overbooking` | `Availability::assertSlotAvailable()` |
| `max_daily_reservations` | `Availability::assertDailyLimits()` |
| `default_duration` | `BookingService::resolveWindow()` · `availableSlots()` |
| `min_duration` · `max_duration` | `Availability::assertDurationWithinLimits()` |
| `slot_interval` | `Availability::availableSlots()` |
| `min_advance_minutes` · `max_advance_days` | `Availability::assertWithinBookingWindow()` |
| `treat_unset_days_as_closed` | `Availability::assertWithinWorkingHours()` · `availableSlots()` |
| `allow_cancellation` · `allow_cancel_after_confirm` · `cancellation_deadline_hours` | `TransitionService::assertSettingsAllow()` |
| `cancellation_fee_type` · `cancellation_fee_value` | `Pricing::cancellationFee()` |
| `allow_rescheduling` · `reschedule_deadline_hours` · `max_reschedule_attempts` | `TransitionService::assertSettingsAllow()` |
| `require_client` | `BookingService::create()` · `StoreReservationRequest` |
| `require_mobile` · `require_email` | `StoreReservationRequest::withValidator()` |
| `allow_new_client` | القوالب |
| `notify_*` | `SendReservationNotification::handle()` |
| `reminder_offsets` | `SendReservationReminders::handle()` |
| `require_deposit` · `deposit_type` · `deposit_value` | `Pricing::requiredDeposit()` |
| `allow_overpayment` | `BookingService` · `AccountingService` |
| `auto_invoice_on` | `InvoiceService::shouldAutoInvoice()` |
| `default_payment_method_id` · `default_tax_id` | القوالب · `Pricing::quoteForService()` |
| `default_treasury_id` | `AccountingService::defaultTreasuryId()` |
| `calendar_default_view` | `ReservationsController::calendar()` |

> **حدّث هذا الجدول مع كل مفتاح جديد.** مفتاح يُعرض ولا يقرؤه أحد يَعِد
> المستخدم بضبط لا يقع.

### إضافة مفتاح

1. هجرة تضيف العمود بقيمة افتراضية.
2. `$fillable` + `$casts` في `ReservationSetting`.
3. قاعدة في `ReservationSettingsController::rules()` (المنطقية تُلتقط تلقائياً في `booleanKeys()`).
4. حقل في `settings/index.blade.php` — **ومعه `<input type="hidden" value="0">` إن كان checkbox**.
5. **اقرأه في مكان ما.**
6. سطر في الجدول أعلاه.

---

## 10. المسارات

43 مساراً ببادئة `Reservations/`. الصلاحيات في بانيات المتحكّمات لا في
ملف المسارات (اصطلاح `Modules/Purchases`).

**مسارات AJAX قبل `{reservation}`** — `ajax/slots` لو جاءت بعده لطابقته
كمعرّف. و`whereNumber('reservation')` تحرس الباقي.

---

## 11. التقارير

13 تقريراً من **تعريف واحد** في `ReservationReportsController::definitions()`.
تختلف في ثلاثة أشياء: العنوان، ومحور التجميع، والتصفية المسبقة.

### إضافة تقرير

سطر واحد في `definitions()`. المحاور المدعومة: `date` · `status` ·
`employee_id` · `branch_id` · `service_id` · `client_id`.

التجميع كلّه في القاعدة (`groupBy` + `SUM(CASE WHEN …)`) — والتسميات تُجلب
دفعة واحدة في `labelsFor()` منعاً لـN+1.

`CASE` لا `IF` — مدعومة في MySQL و SQLite معاً.

---

## 12. الكرون

```bash
php artisan reservations:reminders --window=720 --dry-run
```

مسجَّل في `CronRegistry::commands()` و`CronJobsSeeder` **قبل هذه الوحدة** —
كان صفّاً بلا صنف ينفّذه.

**عدم التكرار بنيوي**: `reservation_reminders` بمفتاح فريد
`(reservation_id, offset_minutes)`، والصفّ **يُكتب قبل الإرسال** — فلو
تسابق تشغيلان اصطدم الثاني بالفهرس.

---

## 13. الاختبار

### `reservations:self-check`

```bash
php artisan reservations:self-check --force
```

46 فحصاً على القاعدة الحيّة داخل معاملة **تُلغى في النهاية**. يغطّي
السيناريوهات العشرة المطلوبة. صالح للتشغيل بعد كل تركيب أو تحديث.

> يستهلك بضعة أرقام من عدّادات الترقيم (`SerialSetting` يُحدَّث في معاملة
> داخلية) — وهو الأثر الوحيد الباقي.

### PHPUnit

`tests/Feature/ReservationBookingTest.php` (30 اختباراً) و
`ReservationAccountingTest.php` (14 اختباراً).

> ⚠️ **لا تعمل اليوم** — وليس لخلل فيها. `RefreshDatabase` تبني قاعدة
> SQLite من مجموعة الهجرات كاملة، وتسع هجرات على الأقل تستعمل صيغة MySQL
> خامّاً (`ALTER TABLE … CHANGE`, `MODIFY COLUMN`) فيسقط الترحيل قبل أن
> يبدأ أول اختبار. **حالة سابقة لهذه الوحدة تصيب اختبارات المشروع كلها.**
> راجع `IMPLEMENTATION_REPORT.md` § القيود المعروفة.

---

## 13.5. القوالب: الشريط الجانبي وشجرة «تابع لـ»

### الشريط الجانبي الخاص بالوحدة

`reservations::master` نسخةٌ من `master` الجذري بتبديلين لا ثالث لهما:

1. الشريط ← `UnifiedSidebar::view(reservations::layouts.sidebar)` — فمن
   فعّل «القائمة الموحّدة» على دوره يبقى يرى الجامعة.
2. `partials.styles` يُحقن مرّةً هنا لا في كل شاشة.

وما عداه حرفاً بحرف: نفس المكتبات وترتيب تحميلها — فلا jQuery مرّتين ولا
Bootstrap بمستمعَين، وهما العطلان اللذان يقتلان select2 والمودالات صامتَين.

> **`$settings` مزلقٌ حقيقي هنا.** `@extends` يمرّر متغيّرات الشاشة إلى
> القالب الأمّ ومنه إلى الشريط، وشاشةُ إعدادات الحجوزات تُعرّف `$settings`
> نموذجاً. فالشريط يفحص `is_array($settings)` قبل استعماله وإلّا اختفى
> بلا خطأ يُرى — نفس الاحتراز في شريطَي العملاء والمبيعات.

### شجرة «تابع لـ»

المُنتقي مشتركٌ: `reports.partials.affiliation-tree`، وهو نفسه الذي
تستعمله تقارير المبيعات والمشتريات والعملاء (33 شاشة).

**عُمِّم بوسائط اختيارية**، وافتراضاتها هي السلوك القائم حرفاً بحرف —
فالشاشات التي تُضمّنه بلا وسائط تُخرج المعرّفات نفسها
(`#tree_node`، `#affiliationTree`) والدالّتين العامّتين اللتين تناديهما
أزرار «إعادة التعيين» في تقارير العملاء.

| الوسيط | الافتراض | متى يُمرَّر |
|---|---|---|
| `treeNodes` | `[]` | دائماً — `DataScope::filterNodes(AffiliationTree::nodes())` |
| `name` | `tree_node` | `branch_id` حين يكون حقلَ إدخالٍ لا فلتراً |
| `showExact` | `true` للاسم الافتراضي، وإلّا `false` | — |
| `value` | — | القيمة المحفوظة في نماذج التعديل |
| `label` | `تابع لـ` | — |

المعرّفات تُشتقّ من `name`، فمُنتقيان في صفحةٍ واحدة لا يتصادمان.

**فلتراً** (`tree_node`): يقرؤه `ScopesReservations::applyFilters()`،
و`forBranch()` تشمل العقدة وما تحتها، و`exact_node` تقصرها على العقدة.
والاسم القديم `branch_id` يبقى مقبولاً لروابط محفوظة.

**حقلَ إدخال** (`name => branch_id`): يكتب في الحقل الذي يحفظه المتحكّم
مباشرةً — بلا حقلٍ وسيطٍ ولا نصٍّ يربط بينهما. و`showExact` مطفأة لأن
«هذه الجهة وحدها» فلترٌ لا معنى له في إدخال.

> **لماذا شجرة لا قائمة مسطّحة؟** المسطّحة تعرض عُقد الجذر وحدها، وأكثرها
> **مناطق** لا فروع. فمن اختار «منطقة الرياض» طابق معرّفها وحده ولم يُرجَع
> له شيء، وحجوزات منطقته كلّها تحت أبنائها — وهي العلّة الموثّقة في
> `BelongsToBranch::scopeForBranch`.

### تهيئة القوائم

`partials/select_scripts.blade.php` يُحقن من `master` بعد `select2.full.min.js`.
يمسح `.rsv-scope select` ويستثني ثلاثة صراحةً:

- `.rpt-tree-select` — الشجرة تُزيّن نفسها وتُبنى وتُهدَم مع كل تغيير مستوى.
- `#rsv-client` — له تهيئة AJAX خاصّة.
- `[data-no-select2]` — مخرجٌ صريح.

ويتخطّى ما هُيّئ سلفاً (`$select.data('select2')`)، وينزع الصنف `select2`
عن الحاوية كي لا يلتقطها مسحٌ عامٌّ لاحق فيُنتج قائمةً فارغة فوق حقلٍ ممتلئ.

---
## 14. نقاط التوسعة

| تريد | افعل |
|---|---|
| حالة جديدة | § 5 |
| مفتاح إعداد | § 9 |
| تقرير | § 11 |
| قناة إشعار | خريطة `SendReservationNotification::map()` + إعدادات الإشعارات |
| حقل في الحجز | هجرة + `$fillable` + `partials/form.blade.php` + `StoreReservationRequest` |
| قاعدة توفّر | دالّة `assert*` في `AvailabilityService` + نداؤها في `assertSlotAvailable()` |
| قيد محاسبي | دالّة في `AccountingService` تتبع نمط `recordPayment()`: حارس فترة + `requireAccount()` + معاملة |

---

## 15. مزالق موثّقة

| المزلق | القاعدة |
|---|---|
| `Client->name` فارغ دائماً | استعمل `display_name` |
| `employees` لا `last_name` فيها | استعمل `full_name` (first + middle + nickname) |
| `invoices.type` | `normal` لا `sales` |
| `invoice_items.type` | `enum('product','packege')` |
| `discount_type` في جداول الفواتير | عدد 1/2 لا نص |
| الضريبة | `tax_sittings` لا `taxes` |
| `Blade::compile()` | تأخذ **مساراً**؛ `compileString()` تُرجع الشيفرة |
| `RedirectResponse` | لا `Conditionable` — لا `when()` عليها |
| checkbox في نموذج إعدادات | يحتاج `<input type="hidden" value="0">` وإلا استحال إطفاؤه |
| `preg_replace` الفاشلة | تُعيد `null` فتُفرغ الملف — و`php -l` يعدّ الملف الفارغ سليماً |
| jQuery / Bootstrap | محمَّلان في `master` — إعادة تحميلهما تقتل select2 والمودالات |
| `select2` | على منتقٍ بعينه لا `$('select')` عامّاً |
| `@section('styles')` | `master` لا يُخرجها — ضمّن التنسيق داخل `content` |
| `Command::run()` | عامّة في الأب — لا تُعرَّف خاصّة في أمرك |
