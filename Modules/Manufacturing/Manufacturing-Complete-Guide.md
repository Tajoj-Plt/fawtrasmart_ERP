# موديول Manufacturing — التدقيق والمعالجة وإكمال النظام

**المسار:** `Modules/Manufacturing`
**النطاق:** 10 كنترولرات (5,984 سطر PHP) + 63 ملف Blade (12,857 سطر) + المسارات والمزودات
**تاريخ الفحص:** 22 أغسطس 2026

> **هذا المستند يجمع ثلاثة أعمال في واحد:** ما هي العيوب، وكيف تُصلَح سطراً بسطر، وبأي ترتيب — مع ما ينقص النظام ليكون قسم تصنيع حقيقياً.
>
> **كل عيب يظهر مرة واحدة**، ومعه دليله وكوده الحالي والبديل ورقم مرحلته. لا حاجة للتنقل بين ملفات.

---

## الفهرس

| القسم | المحتوى |
|---|---|
| **[0](#0)** | الملخص التنفيذي — الحقيقة غير المريحة وجدول الأولويات |
| **[1](#1)** | تعريف «شغّال» — الثوابت العشرون التي هي عقد التسليم |
| **[2](#2)** | خطة التنفيذ — المراحل والاعتماديات والجدول الزمني |
| **[3](#3)** | كيف تقرأ سجلّ العيوب |
| **[4](#4)** | **المرحلة 0** — الاكتشاف والقرارات الستة |
| **[5](#5)** | **المرحلة 1** — إيقاف النزيف (10 بنود) |
| **[6](#6)** | **المرحلة 2** — الأساس: الخدمات والهجرات وآلة الحالات (7 بنود) |
| **[7](#7)** | **المرحلة 3** — دورة أمر التصنيع (12 عيباً) |
| **[8](#8)** | **المرحلة 4** — قوائم المواد BOM (8 عيوب) |
| **[9](#9)** | **المرحلة 5** — محطات العمل والتكاليف غير المباشرة (8 عيوب) |
| **[10](#10)** | **المرحلة 6** — الأمان والفروع (7 عيوب) |
| **[11](#11)** | **المرحلة 7** — الأداء والواجهات والتقارير (11 بنداً) |
| **[12](#12)** | **المرحلة 8** — إصلاح البيانات القائمة |
| **[13](#13)** | **المرحلة 9** — الاختبارات والقبول |
| **[14](#14)** | **إكمال النظام** — النواقص والإضافات (14 إضافة) |
| **[15](#15)** | ما لا يجب فعله |
| **[16](#16)** | ملاحق — سكربت الاستكشاف، Larastan، ما يحتاج تحققاً، خريطة المعرّفات |

---

<a id="0"></a>

# 0. الملخص التنفيذي

## 0.1 الحقيقة غير المريحة

**الموديول ليس جاهزاً للإنتاج، وليست المشكلة في جودة الكود بل في سلامة البيانات.**

ثلاثة أشياء مؤكدة بالفحص المباشر:

1. **دالة إنهاء أمر التصنيع (`completeOrder`) تسقط بخطأ 500 في كل مرة.** السطر 2689 يستدعي `Log::` بلا استيراد، وهو `Error` لا `Exception` فلا يلتقطه أي `catch`. المسار الرئيسي للموديول معطّل بسطر واحد.

2. **إلغاء أمر تصنيع بعد استلام المواد يبتلع المواد الخام نهائياً.** الدالة المسؤولة عن إرجاعها (`updateProductInventory`) جسمها **معلّق بالكامل**، فهي تُرجع `null` وتُظهر رسالة نجاح.

3. **الأرقام المالية كلها تُحسب في JavaScript وتُقبل من الطلب كما هي** (`last_total_cost`, `raw_total`, `total_cost`, `total`) ثم تدخل قيود اليومية مباشرةً بحالة «مُرحَّل». أي مستخدم يعدّل حقلاً مخفياً في أدوات المطوّر يستطيع كتابة قيد يومية بالمبلغ الذي يريد.

**الحكم:** لا تُشغّل هذا الموديول على بيانات حقيقية قبل إتمام المرحلتين 1 و3. ما دون ذلك (الأداء، التكرار، الترجمة) يمكن تأجيله؛ فقدان المخزون وانحراف الدفاتر لا يمكن.

## 0.2 نقطة عمياء لا يكشفها أي جدول أولويات

القيود المحاسبية الخمسة الحالية **تُحمّل** حساب «مخزون تحت التشغيل» (WIP) بمجموع البنود المحسوب، و**تُقرضه** بـ `last_total_cost` القادم من الواجهة. الفرق بينهما يتراكم في الحساب إلى الأبد ولا يظهر في أي تقرير.

**ينقصك قيد سادس — قيد انحراف الإنتاج.** بدونه، حساب WIP لن يصفر أبداً مهما أصلحتَ ما عداه. تفصيله في [§7.5](#p3-05) و[§14 — A1](#add-a1).

## 0.3 مستويات الثقة

| الرمز | المعنى |
|---|---|
| ✅ **مؤكد** | تحققتُ منه بقراءة الكود مباشرةً |
| ⚠️ **مرجح** | استنتاج قوي من أدلة داخل الكود |
| ❓ **يحتاج تحقق** | يعتمد على ملفات خارج الموديول (الهجرات، الموديلات، الـ Middleware) |

## 0.4 جدول الأولويات

| # | العيب | المعرّف | الخطورة | الجهد | الثقة |
|---|---|---|---|---|---|
| 1 | `Log::` غير مستوردة — `completeOrder` معطّلة كلياً | [P1-01](#p1-01) | 🔴 قاتل | سطر | ✅ |
| 2 | `updateProductInventory` فارغة — الإلغاء يفقد المخزون | [P3-01](#p3-01) | 🔴 قاتل | يوم | ✅ |
| 3 | مسار `orders/delete` يشير لدالة غير موجودة | [P1-02](#p1-02) | 🔴 قاتل | سطر | ✅ |
| 4 | 4 مسارات BOM لدوال غير موجودة (`login_to`, `updatePassword`…) | [P1-03](#p1-03) | 🔴 قاتل | 16 سطراً | ✅ |
| 5 | `Storage::` غير مستوردة — حذف ملاحظة بمرفق يسقط | [P1-04](#p1-04) | 🔴 قاتل | سطران | ✅ |
| 6 | `downloadExport` لمسار غير مسجّل — زر التصدير 500 | [P1-05](#p1-05) | 🔴 قاتل | يوم | ✅ |
| 7 | المبالغ المالية من العميل بلا إعادة حساب | [P2-05](#p2-05) | 🔴 حرج | نصف يوم للـTrait + يومان للتطبيق | ✅ |
| 8 | `returnMaterials` بلا سقف كمية — خلق مخزون من العدم | [P3-02](#p3-02) | 🔴 حرج | نصف يوم | ✅ |
| 9 | `BOMController@update` يخصم المخزون مرتين | [P4-02](#p4-02) | 🔴 حرج | يوم | ✅ |
| 10 | عكس القيود بالبحث النصّي — مضاعفة + LIKE injection | [P3-06](#p3-06) | 🔴 حرج | يوم | ✅ |
| 11 | غياب كامل لعزل الفروع (`branch_id` = صفر نتيجة) | [P6-01](#p6-01) | 🔴 حرج | 4 أيام | ✅ |
| 12 | حساب WIP لا يُقفل — رصيد شاذ يتراكم لكل أمر | [P3-05](#p3-05) | 🔴 حرج | يومان | ✅ |
| 13 | حذف عبر `GET` بلا CSRF (مساران) | [P1-07](#p1-07) | 🟠 خطير | ساعة | ✅ |
| 14 | `{!! Str::markdown() !!}` على وصف من المستخدم — XSS مخزَّن | [P6-04](#p6-04) | 🟠 خطير | ساعة | ✅ |
| 15 | `esc()` لا تُهرِّب `"` — حقن سمات HTML | [P6-05](#p6-05) | 🟠 خطير | ساعة | ✅ |
| 16 | صفر `lockForUpdate` — سباق على أرصدة المخزون | [P2-03](#p2-03) | 🟠 خطير | يومان | ✅ |
| 17 | تناقض نوع `status` — اللوحة والتقارير تعرض بيانات خاطئة | [P7-01](#p7-01) | 🟠 خطير | يوم | ✅ |
| 18 | 9 دوال تفتح معاملة وتخرج بلا `rollBack` | [P3-08](#p3-08) | 🟠 عالٍ | نصف يوم | ✅ |
| 19 | `whereNotNull` بلا أثر لأن `store()` يكتب `0` بدل `null` | [P4-01](#p4-01) | 🟠 عالٍ | نصف يوم | ✅ |
| 20 | ~15 دالة ميتة + ملفان ميتان + 4 نسخ من «الإنهاء» | [P7-08](#p7-08) | 🟡 متوسط | يومان | ✅ |
| 21 | N+1 وتحميل جداول كاملة في كل شاشة | [P7-03](#p7-03) | 🟡 متوسط | يومان | ✅ |
| 22 | ~2 ميغابايت مكتبات CDN غير مستعملة على كل صفحة | [P7-06](#p7-06) | 🟡 متوسط | نصف يوم | ✅ |
| 23 | i18n ≈ صفر (5 استدعاءات `trans()` في 63 ملفاً) | [P7-10](#p7-10) | 🟡 متوسط | 4 أيام | ✅ |

---

<a id="1"></a>

# 1. تعريف «شغّال» — الثوابت العشرون

لا معنى لخطة بلا تعريف قابل للقياس. هذه الثوابت (invariants) يجب أن تصمد بعد كل عملية. **هي عقد التسليم**، وكل مرحلة تُقاس بها.

## 1.1 محاسبياً

| # | الثابت | كيف يُختبر | يُخدم في |
|---|---|---|---|
| **A1** | كل قيد متوازن: `\|Σمدين − Σدائن\| ≤ 0.01` | استعلام على `journal_entry_details` يرجع صفر صفوف | [P2-04](#p2-04) |
| **A2** | `accounts.balance` = مجموع تفاصيل القيود لكل حساب | تقرير تسوية | [P2-04](#p2-04), [P4-02](#p4-02) |
| **A3** | **حساب WIP يصفر لكل أمر مكتمل** | `Σ مدين WIP للأمر = Σ دائن WIP` | [P3-05](#p3-05) |
| **A4** | كل قيد مرتبط بمستنده عبر `reference_type + reference_id` | صفر قيود بلا مرجع | [P3-06](#p3-06), [P3-07](#p3-07) |
| **A5** | لا حذف لقيد مُرحَّل — عكس مسجَّل فقط | صفر `DELETE` على `journal_entries` | [P3-06](#p3-06) |
| **A6** | لا ترحيل داخل فترة محاسبية مقفلة | كل عملية تفحص `accountingPeriodBlock` | [P3-09](#p3-09) |
| **A7** | التكلفة تُحسب في الخادم لا في المتصفح | صفر حقول مالية تُقبل بلا إعادة حساب | [P2-05](#p2-05) |

## 1.2 مخزنياً

| # | الثابت | كيف يُختبر | يُخدم في |
|---|---|---|---|
| **S1** | لا رصيد سالب في أي مستودع | `WHERE quantity < 0` يرجع صفراً | [P2-03](#p2-03) |
| **S2** | كل حركة مخزون لها إذن مخزني | صفر تعديلات بلا `warehouse_permit` | [P3-01](#p3-01) |
| **S3** | `grand_total` للإذن = مجموع بنوده | صفر إذونات بفارق > 0.01 | [P3-11](#p3-11) |
| **S4** | المرتجع ≤ المصروف لكل مادة في كل أمر | تحقق في الكود + استعلام رقابي | [P3-02](#p3-02) |
| **S5** | قيمة المخزون = الكمية × متوسط التكلفة المرجح | لا كتابة فوق `unit_price` | [P2-03](#p2-03) |
| **S6** | كل عملية قابلة للعكس، والعكس مسجَّل بإذن مقابل | إلغاء أمر يُنشئ إذن إرجاع | [P3-01](#p3-01) |
| **S7** | لا سباق تحت التزامن | اختبار طلبين متوازيين | [P2-03](#p2-03) |

## 1.3 تصنيعياً

| # | الثابت | كيف يُختبر | يُخدم في |
|---|---|---|---|
| **M1** | آلة حالات صريحة — الانتقالات المسموحة فقط | كل انتقال يمرّ بـ `canTransitionTo()` | [P2-01](#p2-01) |
| **M2** | لا تعديل لأمر بعد استلام المواد | `update()` يرفض بحارس | [P3-09](#p3-09) |
| **M3** | تكلفة الأمر = مواد + مصروفات + تشغيل − هالك، محسوبة في الخادم | تطابق مع مجموع البنود ±0.01 | [P2-05](#p2-05) |
| **M4** | فرق الكمية الفعلية عن المخططة يُرحَّل كقيد انحراف | لا فرق يبتلعه النظام صامتاً | [P3-05](#p3-05) |
| **M5** | كل مسار في الواجهة يصل إلى دالة موجودة | صفر `BadMethodCallException` | [P1-02](#p1-02), [P1-03](#p1-03) |
| **M6** | مسار واحد للإنهاء لا أربعة | حذف النسخ الثلاث الميتة | [P7-08](#p7-08) |

---

<a id="2"></a>

# 2. خطة التنفيذ

## 2.1 الخريطة والاعتماديات

```
المرحلة 0  الاكتشاف والقرارات          2 يوم    ← أنت هنا
    │
    ▼
المرحلة 1  إيقاف النزيف                 1 يوم    ← مستقلة، ابدأها فوراً
    │
    ▼
المرحلة 2  الأساس (Foundation)          5 أيام   🔒 تحجب كل ما بعدها
    │
    ├──────────────┬──────────────┬──────────────┐
    ▼              ▼              ▼              ▼
المرحلة 3      المرحلة 4      المرحلة 5      المرحلة 6
دورة الأمر      BOM          محطات+تكاليف     الأمان
 7 أيام        4 أيام         3 أيام         4 أيام
    │              │              │              │
    └──────────────┴──────────────┴──────────────┘
                   ▼
        المرحلة 7  الأداء والواجهات والتقارير  6 أيام
                   ▼
        المرحلة 8  إصلاح البيانات القائمة      3 أيام
                   ▼
        المرحلة 9  الاختبارات والقبول          4 أيام
                   ▼
        إكمال النظام — الإضافات               حسب الاختيار
```

**الإجمالي حتى المرحلة 9: ~39 يوم عمل ≈ 8 أسابيع لمطور واحد متفرّغ** (بلا [P7-10](#p7-10) الذي يضيف 4 أيام كمشروع مستقل).

## 2.2 القيود الصلبة

- المرحلة 2 **تحجب** 3 و4 و5 — لا تبدأها قبل إتمامها.
- المرحلة 3 و4 يمكن أن تتوازيا **إن كان مطوران**، لكن كلتيهما تعتمد على مخرجات 2 كاملةً.
- المرحلة 8 (إصلاح البيانات) **بعد** استقرار 3 و4 — وإلا أصلحتَ بيانات ثم أفسدها كود لم يُصلح بعد.
- المرحلة 6 (الأمان) تُقدَّم إلى الأسبوع الثاني **إن كان النظام يعمل على بيانات حقيقية بمستخدمين متعددي الفروع اليوم**.

## 2.3 الجدول الزمني

### مسار متحفّظ — مطور واحد (8 أسابيع)

| الأسبوع | المراحل |
|---|---|
| 1 | 0 + 1 + بداية 2 |
| 2 | 2 (الأساس) |
| 3-4 | 3 (دورة الأمر) + الإضافتان A1 و A2 |
| 5 | 4 (BOM) |
| 6 | 5 (محطات وتكاليف) + 6 (الأمان) |
| 7 | 7 (أداء وتقارير) + الإضافة A3 + 8 (البيانات) |
| 8 | 9 (اختبارات) + نشر |

### مسار مضغوط — مطوران (5 أسابيع)

| الأسبوع | المطور أ | المطور ب |
|---|---|---|
| 1 | 0 + 1 + 2 | اختبارات + Factories |
| 2 | 2 (إتمام) | 6 (الأمان) |
| 3 | 3 (دورة الأمر) | 4 (BOM) |
| 4 | 3 (إتمام) + A1 + A2 | 5 + 7 |
| 5 | 8 (البيانات) | 9 (اختبارات) |

> التفرّع في الأسبوع 3 ممكن **فقط** بعد إتمام المرحلة 2 كاملةً.

---

<a id="3"></a>

# 3. كيف تقرأ سجلّ العيوب

كل عيب فيما يلي مرتَّب في مرحلته، وله بنية ثابتة:

> ### `Pن-XX` — عنوان العيب
> **الملف:** المسار والسطر · **الخطورة** · **الثقة** · **يخدم الثابت:** —
>
> **المشكلة** — ماذا يحدث ولماذا.
> **الكود الحالي** — منسوخ حرفياً من ملفك.
> **البديل** — جاهز للصق.
> **ملاحظات** — شروط أو تحذيرات.

**رموز حالة الباتش:**

| الرمز | المعنى |
|---|---|
| 🟢 **جاهز للصق** | باتش كامل، لا يحتاج قراراً منك |
| 🟡 **يحتاج تأكيداً** | البديل صحيح لكن يعتمد على اسم عمود أو ثابت عندك |
| 🔵 **يحتاج تصميماً** | لا يُحل بباتش — قرار معماري، أعطيتك الهيكل والاتجاه |

**فجوتان في الترقيم مقصودتان:** لا يوجد **P4-04** (مدموج في [P4-01](#p4-01) لأنه أثره المباشر)، ولا **P6-03** (عيب `sort_by`/`per_page` عُولج في [P2-06](#p2-06) ضمن الأساس).

**حقل «يخدم الثابت»** يظهر في الترويسة حين يخدم البند ثابتاً بعينه من [§1](#1)؛ البنود التنظيمية والأدائية بلا هذا الحقل.

**قاعدة أرقام الأسطر:** الأرقام تعود لحالة الملفات وقت الفحص. بعد أول تعديل ستزيح. **ابحث عن مقتطف «الكود الحالي» نصّياً** لا بالسطر.

**قبل أي شيء:**
```bash
git checkout -b fix/manufacturing
mysqldump -u root fawtmaintest > backup-before-manufacturing-fix.sql
```
كل مرحلة في commit مستقل. لا تدمج المراحل.

---

<a id="4"></a>

# 4. المرحلة 0 — الاكتشاف والقرارات (2 يوم)

## 4.1 شغّل سكربت الاستكشاف

```bash
cd C:\xampp\htdocs\fawtmaintest\fawtmaintest\web\fawtmaintest\Modules\Manufacturing
php mfg-discovery.php > mfg-discovery.txt
```

السكربت (`mfg-discovery.php` في نفس المجلد) **للقراءة فقط** — لا يكتب في قاعدة البيانات ولا في الملفات. يجد جذر المشروع بنفسه.

## 4.2 القرارات الستة التي يحسمها

| # | السؤال | لماذا يوقف كل شيء |
|---|---|---|
| 1 | `product_details` رصيد جارٍ أم سجلّ حركات؟ | يحدد شكل **كل** عملية مخزنية. الجواب الخاطئ = إفساد سجل المخزون كله |
| 2 | ما قيم `STATUS_*` الفعلية؟ | آلة الحالات مبنية عليها، واللوحة تقرأها أرقاماً اليوم |
| 3 | هل يوجد `branch_id` وGlobal Scope؟ | يحدد إن كان IDOR ثغرة قائمة أم دَين تقني |
| 4 | ما أعمدة `manufactur_orders_items` فعلاً؟ | ثلاث دوال حساب تكلفة تقرأ أعمدة قد لا توجد |
| 5 | ما مفاتيح الترقيم المتاحة؟ | كل إذن جديد يحتاج مفتاحاً صحيحاً |
| 6 | كم بياناً فاسداً في النظام الآن؟ | يحدد حجم المرحلة 8 |

## 4.3 القرارات المعمارية وتوصيتي

| القرار | الخيارات | التوصية |
|---|---|---|
| **ProductDetails** | رصيد جارٍ / سجلّ حركات | يحسمه السكربت — لا تخمّن. وإن لم تُشغّله، فهذا الاستعلام يحسمه في ثانية |
| **مصدر الحقيقة للتكلفة** | البنود المحفوظة / `last_total_cost` | **البنود**، و`last_total_cost` يُشتقّ منها ويُخزَّن للعرض |
| **تسعير المخزون** | متوسط مرجح / FIFO / آخر سعر | **متوسط مرجح** — أبسط تنفيذاً وكافٍ لمعظم التصنيع |
| **عزل الفروع** | Global Scope / شرط يدوي / لا شيء | **Global Scope + Policy** |
| **الحالات** | Enum / نصوص / أرقام | **Enum مدعوم بنصّ** (`backed by string`) |
| **الإنهاء** | `completeOrder` / `finishOrder` | **`completeOrder` فقط**، وحذف الثلاثة الباقية |

### 4.4 أداة حسم القرار 1 بلا السكربت

```sql
SELECT product_id, store_house_id, COUNT(*) c
FROM product_details
GROUP BY product_id, store_house_id
HAVING c > 1
LIMIT 20;
```

| النتيجة | الحكم | الأثر |
|---|---|---|
| **صفر صفوف** | **رصيد جارٍ (BALANCE)** ✓ | كل باتشات هذا المستند صحيحة كما هي |
| **صفوف مكررة** | **سجلّ حركات (LEDGER)** ⚠️ | **كل `->first()` ثم `-=` في الموديول يعدّل حركة عشوائية**، وكل الباتشات المخزنية تحتاج الصياغة البديلة في [P2-03](#p2-03) |

وللتأكيد، افحص أعمدة الجدول:
```sql
SHOW COLUMNS FROM product_details;
```
وجود `type` و`date` و`time` و`type_of_operation` يرجّح **سجلّ الحركات** بقوة.

**معيار القبول:** ملف `mfg-discovery.txt` جاهز، والقرارات الستة محسومة كتابةً، ونسخة احتياطية موجودة.

---

<a id="5"></a>

# 5. المرحلة 1 — إيقاف النزيف (1 يوم)

**مستقلة تماماً — ابدأها اليوم بلا انتظار السكربت.** عشرة بنود: ستة منها تعمل 500 على مستخدميك الآن ([P1-01](#p1-01)…[P1-06](#p1-06))، وثلاثة تصحّح ثغرات وتناقضات، وواحد يمنع تكرار الستة.

---

<a id="p1-01"></a>

## P1-01 — `Log::` غير مستوردة: `completeOrder` معطّلة كلياً

**الملف:** `Http/Controllers/ManufacturingOrdersController.php:2689` · 🔴 قاتل · ✅ مؤكد · **يخدم:** M5 · 🟢 **جاهز للصق**

### المشكلة

الـ namespace هو `Modules\Manufacturing\Http\Controllers`. الاستيراد الوحيد للوج هو `use App\Models\Log as ModelsLog;` (سطر 15) — **لا يوجد `use Illuminate\Support\Facades\Log;`**. وكل الاستدعاءات الأخرى في الملف (40+) تكتب `\Log::` بالشرطة الخلفية؛ **هذا الاستدعاء الوحيد بدونها**.

PHP يبحث عن `Modules\Manufacturing\Http\Controllers\Log` → `Error: Class not found`. وهو **`Error` لا `Exception`**، ويقع قبل `DB::beginTransaction()` وخارج الـ `try`.

**الأثر:** `POST manufacturing/orders/{id}/complete` — المسار الذي تستدعيه الواجهة فعلياً — **يُنتج 500 دائماً**. إنهاء أي أمر تصنيع مستحيل.

### الكود الحالي

```php
        Log::info('=== بداية إنهاء أمر التصنيع ===', [
            'order_id' => $id,
            'request_data' => $request->all(),
        ]);
```

### البديل

```php
        \Log::info('=== بداية إنهاء أمر التصنيع ===', [
            'order_id' => $id,
        ]);
```

### ملاحظات

1. الشرطة الخلفية `\` هي الإصلاح الفعلي.
2. حذفتُ `'request_data' => $request->all()` عمداً — تسجيل كامل حمولة الطلب في اللوج نمط خطر (نفس المشكلة في `WorkstationsController:258, 410`).
3. **الأنظف على المدى الطويل:** أضف `use Illuminate\Support\Facades\Log as LogFacade;` واستبدل كل `\Log::` بـ `LogFacade::`. لكن هذا 40+ استبدال — أجّله للمرحلة 7.

---

<a id="p1-02"></a>

## P1-02 — مسار الحذف يشير لدالة غير موجودة

**الملف:** `routes/web.php:206` · 🔴 قاتل · ✅ مؤكد · **يخدم:** M5 · 🟡 **يحتاج تأكيداً**

### المشكلة

`ManufacturingOrdersController` يحوي `destroy()` (سطر 942) و`deleteNote()` — **ولا يحوي `delete()`**. والواجهة تستعمل المسار فعلياً في موضعين:
- `resources/views/orders/partials/delete-modal.blade.php:32`
- `resources/views/orders/index.blade.php:187`

**الأثر:** زر «حذف نهائي» يرمي `BadMethodCallException` → 500 دائماً. وبالمقابل `destroy()` كود ميت لا يصله أحد.

### الكود الحالي

```php
Route::get('/delete/{id}', [ManufacturingOrdersController::class, 'delete'])
    ->name('manufacturing.orders.delete')
    ->middleware('permission:stock.manufacturing_orders.delete');
```

### البديل — يُطبَّق مع [P3-07](#p3-07) معاً، لا قبله

```php
Route::delete('/delete/{id}', [ManufacturingOrdersController::class, 'destroy'])
    ->name('manufacturing.orders.delete')
    ->middleware('permission:stock.manufacturing_orders.delete');
```

### ⛔ تحذير ترتيب

**لا تُطبّق هذا وحده.** الدالة `destroy()` نفسها معطوبة ([P3-07](#p3-07)): تحذف بمعايير لا تطابق شيئاً، بلا معاملة، وبلا عكس مخزني. تفعيلها اليوم أسوأ من الـ500.

**الترتيب الصحيح:** أصلح `destroy()` ← ثم غيّر المسار ← ثم غيّر الواجهة ([P1-07](#p1-07)).

**حل مؤقت لليوم** — عطّل الزر بدل إصلاحه:
```blade
{{-- resources/views/orders/partials/delete-modal.blade.php:32 --}}
<button type="button" class="btn btn-danger" disabled title="الحذف معطّل مؤقتاً — قيد الإصلاح">
    <i class="feather icon-trash-2"></i> حذف نهائي
</button>
```

---

<a id="p1-03"></a>

## P1-03 — أربعة مسارات BOM لدوال غير موجودة، وخطر أمني كامن

**الملف:** `routes/web.php:124-138` · 🔴 قاتل · ✅ مؤكد · **يخدم:** M5 · 🟢 **جاهز للصق**

### المشكلة

| المسار | الدالة المطلوبة | الحالة في `BOMController` |
|---|---|---|
| `POST /BOM/updatePassword/{id}` | `updatePassword` | **مفقودة** |
| `GET /BOM/login/to/{id}` | `login_to` | **مفقودة** |
| `GET /BOM/export/view` | `export_view` | **مفقودة** |
| `POST /BOM/export` | `export` | **مفقودة** |

دوال `BOMController` الفعلية: `index, getData, create, store, edit, update, show, destroy, get_cost_total, createIssuanceJournalEntry, createWarehousePermit, finish`.

**الخطر الحقيقي ليس الـ500.** الاسمان `updatePassword` و`login/to/{id}` نمطان معروفان من كنترولر المستخدمين (`login_to` = انتحال شخصية / impersonation). أحدهما محمي بصلاحية `stock.bom.view` — أضعف صلاحية في النظام. لو نسخ مطوّر جسم الدالة يوماً من كنترولر المستخدمين (وهو الاحتمال الأرجح، بدليل أن الاسمين نُسخا بالفعل)، لصار **أي مستخدم يملك «عرض قوائم المواد» قادراً على تسجيل الدخول كأي مستخدم آخر** — تصعيد صلاحيات كامل.

### الكود الحالي

```php
                        Route::post('/updatePassword/{id}', [BOMController::class, 'updatePassword'])
                            ->name('Bom.updatePassword')
                            ->middleware('permission:stock.bom.edit');
                        Route::delete('/delete/{id}', [BOMController::class, 'destroy'])
                            ->name('Bom.destroy')
                            ->middleware('permission:stock.bom.delete');
                        Route::get('/login/to/{id}', [BOMController::class, 'login_to'])
                            ->name('Bom.login_to')
                            ->middleware('permission:stock.bom.view');
                        Route::get('/export/view', [BOMController::class, 'export_view'])
                            ->name('Bom.export_view')
                            ->middleware('permission:stock.bom.view');
                        Route::post('/export', [BOMController::class, 'export'])
                            ->name('Bom.export')
                            ->middleware('permission:stock.bom.view');
```

### البديل — احذف الأربعة، أبقِ `Bom.destroy` وحده

```php
                        Route::delete('/delete/{id}', [BOMController::class, 'destroy'])
                            ->name('Bom.destroy')
                            ->middleware('permission:stock.bom.delete');
```

### ملاحظات

**لماذا الحذف لا الإضافة:** إن كان التصدير مطلوباً فعلاً، اكتبه لاحقاً بصلاحية مستقلة `stock.bom.export` وبمسار `POST` واحد. أما `login_to` و`updatePassword` فلا مكان لهما في موديول تصنيع بأي حال.

---

<a id="p1-04"></a>

## P1-04 — `Storage::` غير مستوردة

**الملف:** `Http/Controllers/ManufacturingOrdersController.php:1137-1139` · 🔴 قاتل · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

`Storage` غير مستوردة ولا مسبوقة بـ `\`. والـ `catch (\Exception $e)` في السطر 1159 **لا يلتقط `Error`**.

**خبث المشكلة:** الخطأ كامن — لا يظهر إلا حين تكون `$note->attachment` غير فارغة (بسبب short-circuit في `&&`). يمرّ في الاختبار ويسقط في الإنتاج.

### الكود الحالي

```php
            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }
```

### البديل

أضف في أعلى الملف مع بقية الـ`use`:
```php
use Illuminate\Support\Facades\Storage;
```
وأبقِ الكود كما هو. (`Storage` تُستعمل في موضعين فقط، فالاستيراد أنظف.)

**أو الإصلاح الفوري بسطرين** إن لم تُرِد لمس الاستيرادات:
```php
            if ($note->attachment && \Storage::disk('public')->exists($note->attachment)) {
                \Storage::disk('public')->delete($note->attachment);
            }
```

---

<a id="p1-05"></a>

## P1-05 — زر التصدير في التكاليف غير المباشرة يعطي 500

**الملف:** `Http/Controllers/IndirectCostsController.php:145-167` · 🔴 قاتل · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

`downloadExport` يظهر في هذا السطر فقط في الموديول كله، **ولا مسار بهذا الاسم**. `route()` يرمي `RouteNotFoundException`.

وفوق ذلك الدالة **لا تصدّر شيئاً** — كود غير مكتمل تُرك في الإنتاج، مع `->get()` بلا حد أعلى، وتمرير `$request->all()` كاملاً إلى مُولّد الروابط.

### الكود الحالي

```php
    public function export(Request $request)
    {
        // نفس منطق الفلترة من getData
        $query = IndirectCost::with(['account', 'indirectCostItems.manufacturingOrder', 'indirectCostItems.journalEntry']);

        // تطبيق نفس الفلاتر...
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        // ... باقي الفلاتر

        $indirectCosts = $query->get();

        // يمكن استخدام مكتبة مثل Laravel Excel للتصدير
        return response()->json([
            'success' => true,
            'message' => 'سيتم تصدير '.$indirectCosts->count().' سجل',
            'download_url' => route('manufacturing.indirectcosts.downloadExport', ['filters' => $request->all()]),
        ]);
    }
```

### البديل — تصدير CSV فعلي بلا مكتبات

```php
    public function export(Request $request)
    {
        $query = IndirectCost::with(['account', 'indirectCostItems.manufacturingOrder']);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $fileName = 'indirect-costs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM ليقرأ Excel العربية صحيحاً

            fputcsv($out, ['#', 'التاريخ', 'الحساب', 'الوصف', 'الإجمالي']);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        optional($row->date)->format('Y-m-d') ?? $row->date,
                        optional($row->account)->name ?? '—',
                        $row->description,
                        $row->total,
                    ]);
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
```

### ملاحظات

**عدّل الواجهة** — الزر كان يتوقع JSON فيه `download_url`؛ صار الردّ ملفاً. في `resources/views/indirectCosts/index.blade.php` استبدل نداء AJAX بـ:
```js
window.location = exportUrl + '?' + $('#filter-form').serialize();
```

**بديل أسرع لليوم الأول** إن لم تُرِد لمس الواجهة: احذف مسار التصدير من `routes/web.php:267-269` وأخفِ الزر. أهون من ترك 500 معروضاً للمستخدمين.

**تحسينان مهمّان في الباتش:** `chunk(500)` بدل `->get()`، وحذف `$request->all()` من مُولّد الروابط (كان يفتح باب حقن معاملات في العنوان).

---

<a id="p1-06"></a>

## P1-06 — `routes/api.php` يكسر `route:cache`

**الملف:** `routes/api.php` · 🔴 قاتل · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

`ManufacturingController` غير موجود، و`RouteServiceProvider` يحمّل هذا الملف فعلاً (`mapApiRoutes`). النتيجة: **`php artisan route:cache` يفشل بـ `ReflectionException`** ⇒ نشر الإنتاج بالكاش معطّل.

### الكود الحالي

```php
use Modules\Manufacturing\Http\Controllers\ManufacturingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('manufacturing', ManufacturingController::class)->names('manufacturing');
});
```

### البديل — أفرغ الملف مع إبقاء الهيكل

```php
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — وحدة التصنيع
|--------------------------------------------------------------------------
|
| لا توجد نقاط API فعّالة بعد. كان هنا `apiResource` يشير إلى
| `ManufacturingController` — وهو كنترولر غير موجود، فكان `route:cache`
| يفشل عند كل نشر.
|
| عند إضافة نقاط، ابدأ بـ ProductionMaterialsApiController@getItems
| ولا تنسَ: middleware('auth') + permission، ولا تُعِد purchase_price
| بلا تحقق من الصلاحية.
|
*/
```

### تحقق

```bash
php artisan route:clear && php artisan route:cache
```

---

<a id="p1-07"></a>

## P1-07 — حذف عبر `GET` بلا CSRF (مساران)

**الملفات:** `routes/web.php:206, 344` + قالبان · 🟠 خطير · ✅ مؤكد · 🟡 **يحتاج تأكيداً**

### المشكلة

`<img src="https://erp/ar/manufacturing/orders/delete/5">` في أي بريد أو صفحة يحذف الأمر رقم 5 عند فتحه من متصفح مسجَّل الدخول. وأي مسبِّق تحميل أو زاحف أو إضافة متصفح تزور الرابط تُنفّذ الحذف.

**والمفارقة:** بقية الموديول تفعلها **صحيحاً** — `indirectCosts/show.blade.php:220-231` و`bom/index.blade.php:305` و`workstations/index.blade.php:270` كلها `POST` + `@method('DELETE')` + `@csrf`. العطل في مسارين فقط.

### (أ) أوامر التصنيع

**`routes/web.php:206`** — انظر [P1-02](#p1-02) (يُطبَّق بعد إصلاح `destroy()`).

**`resources/views/orders/partials/delete-modal.blade.php:32`** — الكود الحالي:
```blade
                <a href="{{ route('manufacturing.orders.delete', $order->id) }}" class="btn btn-danger">
                    <i class="feather icon-trash-2"></i> حذف نهائي
                </a>
```

البديل:
```blade
                <form id="delete-form" action="{{ route('manufacturing.orders.delete', $order->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </button>
                </form>
```

**`resources/views/orders/index.blade.php`** — الكود الحالي يمرّر الرابط لسمة `href`:
```js
                destroy: @json(route('manufacturing.orders.delete', ':id')),
```
ولاحقاً `$('#del_link').attr('href', routes.destroy.replace(':id', ...))`.

البديل:
```js
$('#delete-form').attr('action', routes.destroy.replace(':id', $(this).data('id')));
```

### (ب) مسارات الإنتاج

**`routes/web.php:344`** — الكود الحالي:
```php
                            Route::get('/delete/{id}', [SettingsController::class, 'paths_destroy'])
                                ->name('manufacturing.paths.delete')
                                ->middleware('permission:stock.production_paths.delete');
```
البديل:
```php
                            Route::delete('/delete/{id}', [SettingsController::class, 'paths_destroy'])
                                ->name('manufacturing.paths.delete')
                                ->middleware('permission:stock.production_paths.delete');
```

**`resources/views/settings/Paths/index.blade.php:157-161`** — الكود الحالي:
```js
                $('#del_link').attr(
                    'href',
                    '{{ route('manufacturing.paths.delete', ['id' => '_ID_']) }}'.replace('_ID_', $(this).data('id'))
                );
```
البديل:
```js
                $('#del_form').attr(
                    'action',
                    '{{ route('manufacturing.paths.delete', ['id' => '_ID_']) }}'.replace('_ID_', $(this).data('id'))
                );
```
واستبدل `<a id="del_link">` في نافذة الحذف بـ:
```blade
<form id="del_form" method="POST" class="d-inline">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger">حذف</button>
</form>
```

---

<a id="p1-08"></a>

## P1-08 — اسم مسار مكرر

**الملف:** `routes/web.php:176-181` · 🟡 متوسط · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

اسم `manufacturing.orders.deleteNote` معرَّف مرتين. في Laravel يفوز التسجيل الأخير في جدول الأسماء ⇒ `route('manufacturing.orders.deleteNote', ...)` يولّد `/orders/delete-note/{id}` لا `/orders/note/{id}`. يعمل صدفةً لأن كليهما `DELETE` ويستدعي نفس الدالة — لكن `Route::has` وتخزين المسارات المؤقت قد يفاجئان.

### الكود الحالي

```php
                        Route::delete('/note/{noteId}', [ManufacturingOrdersController::class, 'deleteNote'])
                            ->name('manufacturing.orders.deleteNote')
                            ->middleware('permission:stock.manufacturing_orders.notes');
                        Route::delete('/delete-note/{noteId}', [ManufacturingOrdersController::class, 'deleteNote'])
                            ->name('manufacturing.orders.deleteNote')
                            ->middleware('permission:stock.manufacturing_orders.notes');
```

### البديل — احذف الثاني

```php
                        Route::delete('/note/{noteId}', [ManufacturingOrdersController::class, 'deleteNote'])
                            ->name('manufacturing.orders.deleteNote')
                            ->middleware('permission:stock.manufacturing_orders.notes');
```

### تحقق قبل الحذف

```bash
grep -rn "delete-note" resources/ Http/
```
إن وُجد استعمال نصّي للعنوان، بدّله بـ `route('manufacturing.orders.deleteNote', id)`.

---

<a id="p1-09"></a>

## P1-09 — `finish()` تستدعي View غير موجود

**الملف:** `Http/Controllers/ManufacturingOrdersController.php:735` · 🔴 قاتل · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

خطآن: الصيغة بالنقطة بدل `manufacturing::orders.finish` (صيغة الموديول)، و**الملف غير موجود أصلاً** — `resources/views/orders/` يحوي `create, edit, index, receive-materials, return-materials, show, partials/` فقط.

### الكود الحالي

```php
        return view('manufacturing.orders.finish', compact('order', 'warehouses'));
```

### البديل

```php
        return redirect()->route('manufacturing.orders.show', $order->id);
```

### الأنظف

احذف الدالة `finish()` والمسارين المرتبطين بها في `routes/web.php:155-160`، لأن `finishOrder` نفسها **غير مستدعاة من أي واجهة** (انظر [P7-08](#p7-08)). المسار الحيّ الوحيد للإنهاء هو `completeOrder`.

---

<a id="p1-10"></a>

## P1-10 — ركّب Larastan اليوم

**🟢 جاهز للصق** · **يخدم:** M5

**سبعة من التسعة أعلاه كان يكشفها بأمر واحد.** تركيبه اليوم يمنع تكرارها.

```bash
composer require --dev larastan/larastan
```

`phpstan.neon`:
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 5
    paths:
        - Modules/Manufacturing
    ignoreErrors:
        - '#Unsafe usage of new static#'
```

```bash
vendor/bin/phpstan analyse Modules/Manufacturing
```

**ما كان سيكشفه فوراً:** `Log::` غير المستوردة، `Storage::` غير المستوردة، `ManufacturingController` غير الموجود، `delete()` غير الموجودة، الدوال الأربع المفقودة في `BOMController`، والمتغيرات غير المستعملة في `WorkstationsController`.

**وأضفه إلى CI:**
```yaml
- name: Static analysis
  run: vendor/bin/phpstan analyse --error-format=github
```

---

## معيار قبول المرحلة 1

```bash
php artisan route:clear && php artisan route:cache    # ينجح
vendor/bin/phpstan analyse Modules/Manufacturing      # لا أخطاء class-not-found
php artisan view:clear && php artisan config:clear
```

ويدوياً:
- افتح أمر تصنيع بحالة «تم استلام المواد» ← اضغط «إنهاء» ← **لا 500** (P1-01)
- افتح ملاحظة بمرفق ← احذفها ← **لا 500** (P1-04)
- اضغط «تصدير» في التكاليف غير المباشرة ← **ينزل ملف** (P1-05)

---

<a id="6"></a>

# 6. المرحلة 2 — الأساس (5 أيام) 🔒 تحجب كل ما بعدها

هذه المرحلة **لا تُغيّر سلوكاً ظاهراً للمستخدم**. تبني الطبقة التي تعتمد عليها كل المراحل التالية.

**لماذا لا يمكن تخطّيها:** اليوم يُلمس المخزون في **14 موضعاً بأربعة أنماط مختلفة**، وتُكتب القيود في كل كنترولر على حدة، والحالات تُكتب مرة ثابتاً ومرة نصّاً خاماً. إصلاح كل موضع على حدة يعني تكرار نفس العيوب في كل قسم، وتباعُدَ النسخ بعد أول تعديل.

---

<a id="p2-01"></a>

## P2-01 — `ManufacturOrderStatus` وآلة الحالات (يوم)

**🔵 يحتاج تصميماً** · **يخدم:** M1 · **مشروط بالقرار 2 من [§4.2](#4)**

### المشكلة

ثلاث جهات تقرأ العمود نفسه بنوعين مختلفين، والكنترولر نفسه يكتبه مرة ثابتاً ومرة نصّاً خاماً:

```php
1611  'status' => 'materials_received',                        // نص خام
2221  'status' => ManufacturOrders::STATUS_MATERIALS_RECEIVED, // ثابت
 876  'status' => 'closed',                                    // نص خام
2789  'status' => ManufacturOrders::STATUS_COMPLETED,          // ثابت
```

**وأثره الظاهر** موصوف في [P7-01](#p7-01): كل أمر في اللوحة يُعرَض «مسوّدة»، وأشرطة التقدّم صفر، ومُرشِّح الحالة في التقارير يُرجع نتائج خاطئة.

### الحل

```
app/Enums/ManufacturOrderStatus.php
```

```php
<?php

namespace App\Enums;

enum ManufacturOrderStatus: string
{
    case Pending = 'pending';
    case MaterialsReceived = 'materials_received';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'جديد',
            self::MaterialsReceived => 'تم استلام المواد',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتمل',
            self::Closed => 'مغلق',
            self::Cancelled => 'ملغي',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::MaterialsReceived => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Closed => 'dark',
            self::Cancelled => 'danger',
        };
    }

    /** للقوائم المنسدلة */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }

    /**
     * الانتقالات المسموحة. كل انتقال غير مذكور هنا يُرفض.
     *
     * هذا ما ينقص فعلاً: اليوم لا يوجد جدول انتقالات، بل شروط
     * متناثرة في كل دالة (canBeCompleted، canBeCancelled، …)
     * وبعض الدوال بلا شرط إطلاقاً.
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending           => [self::MaterialsReceived, self::Cancelled],
            self::MaterialsReceived => [self::InProgress, self::Completed, self::Pending, self::Cancelled],
            self::InProgress        => [self::Completed, self::Cancelled],
            self::Completed         => [self::Closed, self::MaterialsReceived],  // الأخيرة = التراجع
            self::Closed            => [self::Completed],                        // إعادة الفتح
            self::Cancelled         => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }
}
```

**وفي الموديل:**
```php
    protected $casts = [
        'status' => ManufacturOrderStatus::class,
    ];
```

### الخريطة البصرية

```
                    ┌────────────── (إرجاع كامل) ──────────────┐
                    ▼                                          │
  pending ──────────────────► materials_received ──────────────┘
     │                               │
     │                               ├──────────► in_progress
     │                               │                  │
     │                               ▼                  ▼
     │                          completed ◄─────────────┘
     │                            │   ▲
     │                            │   └──── (إعادة فتح) ──── closed
     │                            │                            ▲
     │                            ├──── (تراجع) ──► materials_received
     │                            └────────────────────────────┘
     │
     └──► cancelled  ◄──── (من pending أو materials_received أو in_progress)
```

### ⚠️ أثر جانبي على باتشات لاحقة

بعد تفعيل الـ`cast` يصير `$order->status` كائناً لا نصّاً. هذا يكسر:

**في [P3-07](#p3-07):**
```php
// يرمي: Object of class ManufacturOrderStatus could not be converted to string
'لا يمكن حذف أمر تصنيع في حالة «'.$order->status.'».'
// الصحيح:
'لا يمكن حذف أمر تصنيع في حالة «'.$order->status->label().'».'
```

**في [P3-09](#p3-09):**
```php
// قبل:  if ($order->status !== ManufacturOrders::STATUS_PENDING)
// بعد:  if ($order->status !== ManufacturOrderStatus::Pending)
```

> **لهذا رتّبتُ الـ Enum في المرحلة 2 لا في المرحلة 1** — لكي تُكتب باتشات المرحلة 3 عليه مباشرةً بدل إعادة كتابتها.

### توحيد الكتابة

استبدل كل نصّ خام في `ManufacturingOrdersController`:
```
 876  'status' => 'closed'               →   ManufacturOrderStatus::Closed
 916  'status' => 'completed'            →   ManufacturOrderStatus::Completed
1340  'status' => 'completed'            →   ManufacturOrderStatus::Completed
1403  'status' => 'completed'            →   ManufacturOrderStatus::Completed
1464  'status' => 'completed'            →   ManufacturOrderStatus::Completed
1611  'status' => 'materials_received'   →   ManufacturOrderStatus::MaterialsReceived
```
(1403 و1464 داخل `finishOrderWithPermitCleanup` و`finishOrderWithSelectivePermitDeletion` — إن حذفتهما في [P7-08](#p7-08) سقط السطران معهما.)

---

<a id="p2-02"></a>

## P2-02 — الهجرات الستّ (يوم)

**🟡 يحتاج تأكيداً** · مجلد `database/migrations` **فارغ اليوم** (`.gitkeep` وحده).

| الهجرة | الغرض | الثابت |
|---|---|---|
| `journal_entries`: `reversed_at`, `reversed_by_entry_id`, `is_reversal`, `reverses_entry_id` | عكس مسجَّل بدل حذف أو بحث نصّي | **A5** |
| `journal_entries`: فهرس `(reference_type, reference_id)` | ربط القيد بمستنده — يسرّع النظام كله | **A4** |
| `client_relations`: `reference_id` + فهرس | ربط الملاحظة بالأمر (السطر المعلّق) | — |
| `manufactur_orders` وأخواتها: `branch_id` + فهرس | عزل الفروع | — |
| `production_materials_items`: تحويل المفاتيح الأجنبية إلى `nullable` + تنظيف الأصفار | إصلاح `?? 0` | **M3** |
| `product_details`: قيد فريد على `(product_id, store_house_id)` | **فقط إن كان رصيداً جارياً** | **S5** |

### هجرة العكس المحاسبي

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable()->after('reversed_at');
            $table->boolean('is_reversal')->default(false)->after('reversed_by_entry_id');
            $table->unsignedBigInteger('reverses_entry_id')->nullable()->after('is_reversal');

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropColumn(['reversed_at', 'reversed_by_entry_id', 'is_reversal', 'reverses_entry_id']);
        });
    }
};
```
وأضف الأعمدة الأربعة إلى `$fillable` في `App\Models\JournalEntry`.

**الفائدة الجانبية:** الفهرس `(reference_type, reference_id)` يسرّع كل استعلامات ربط القيود بالمستندات في النظام كله، لا في هذا الموديول فقط.

### هجرة تنظيف المفاتيح الأجنبية

```php
    public function up(): void
    {
        // كل هذه كانت تُكتب 0 بدل NULL، فصار whereNotNull بلا أثر
        foreach ([
            'raw_product_id', 'raw_production_stage_id',
            'expenses_account_id', 'expenses_production_stage_id',
            'workstation_id', 'manu_production_stage_id',
            'end_life_product_id', 'end_life_production_stage_id',
        ] as $col) {
            DB::table('production_materials_items')->where($col, 0)->update([$col => null]);
        }
    }
```
**وتأكد أن الأعمدة `nullable`** في الهجرة الأصلية — إن لم تكن، غيّرها أولاً.

> ⚠️ **هجرة `product_details` مشروطة بالقرار 1.** لا تكتبها قبل ناتج السكربت. إن كان الجدول سجلّ حركات فالقيد الفريد سيرفض كل الصفوف.

### وعرّف الثوابت هنا — لا في المرحلة 7

الأرقام السحرية `15` و`17` مبعثرة في الموديول كله، وباتشات المراحل 3 و4 تستعملها. عرّفها **الآن** في `App\Constants\SystemAccountIds`:
```php
    public const PERMISSION_SOURCE_MANUFACTURING_ORDER = 15;
    public const PERMISSION_SOURCE_BOM = 17;
```
ثم استبدل كل `15` و`17` في الموديول بهما تدريجياً مع كل باتش. (النموذج الصحيح موجود عندك: `BOMController:599` يستعمل `SystemAccountIds::PERMISSION_SOURCE_COMPILED_PRODUCT_ADD`.)

---

<a id="p2-03"></a>

## P2-03 — `InventoryService`: نقطة واحدة لكل مسّ للمخزون (يومان)

**🔵 يحتاج تصميماً** · **أهم مخرج في المرحلة كلها** · **يخدم:** S1, S5, S6, S7

### المشاكل الثلاث التي يحلّها دفعةً واحدة

#### (أ) صفر أقفال — سباق على المخزون

`grep -c 'lockForUpdate\|sharedLock'` على كل ملفات الموديول = **0**.

النمط المعطوب (`ManufacturingOrdersController:2104-2194`):
```php
                    $productDetails = ProductDetails::where('product_id', $item->raw_product_id)
                        ->where('store_house_id', $request->storehouse_id)
                        ->first();

                    $availableQty = $productDetails ? $productDetails->quantity : 0;
                    if ($availableQty < $item->raw_quantity) { /* نقص */ }

                    // ... عشرات السطور وإنشاء إذن بينهما ...

                    $productDetails->quantity -= $item->raw_quantity;
                    $productDetails->save();
```

**سيناريو الفشل:** أمران يستلمان نفس المادة من نفس المستودع في آنٍ واحد، الرصيد 100، كلٌّ يطلب 80. كلاهما يقرأ 100 ويجتاز الفحص، ثم `read 100 → write 20` مرتين ⇒ **الرصيد النهائي 20 بدل −60، أي أُنشئ 60 وحدة من العدم.**

الأسوأ أن القراءة–التعديل–الكتابة تكتب القيمة المطلقة، فتمحو تعديل المعاملة الأخرى **حتى مع القفل الصفّي**.

**المواضع:**

| الملف | السطر | العملية |
|---|---|---|
| `ManufacturingOrdersController` | 2104، 2170، 2193 | `receiveMaterials` — خصم |
| | 1549، 1555 | `undoCompletion` — خصم |
| | 1713-1715 | `createWarehousePermitForOrder` — إضافة |
| | 2401-2413 | `returnMaterials` — إضافة |
| | 2756-2769 | `completeOrder` — إضافة |
| | 2867-2871 | `processWasteMaterials` — إضافة |
| `BOMController` | 535-541 | خصم (+ عيب `DB::raw` أدناه) |
| | 621-627، 642-648 | إضافة (+ نفس العيب) |

#### (ب) الكتابة فوق `unit_price` تمحو متوسط التكلفة

`ManufacturingOrdersController:2756-2768` و`1702-1715`:
```php
$productDetails->quantity += $actualQuantity;
$productDetails->unit_price = $unitPrice;      // ← يمحو تكلفة الرصيد القائم
```

لو كان في المستودع 1000 وحدة بتكلفة 10، وأُضيفت 10 وحدات بتكلفة 50، صار **الرصيد كله (1010 وحدة) بتكلفة 50** ⇒ تضخيم فوري لقيمة المخزون بـ 40,000 بلا قيد مقابل.

#### (ج) `updateOrCreate` مع `DB::raw` — INSERT مستحيل + حقن محتمل

`BOMController:540, 626, 647`:
```php
            ProductDetails::updateOrCreate(
                [
                    'store_house_id' => $defaultStoreHouse->id,
                    'product_id' => $item->raw_product_id,
                ],
                ['quantity' => \DB::raw("quantity - $item->raw_quantity")]
            );
```

**العطب المؤكد:** عند عدم وجود السجل ينفّذ `INSERT` بقيمة `quantity - 5` — عبارة تشير للعمود داخل `INSERT` ⇒ `Unknown column 'quantity' in 'field list'`. أي أن **أول صرف لمادة في مستودع لا سجل لها فيه يفشل ويُرجع المعاملة كاملة.**

⚠️ **وحقن SQL من الدرجة الثانية:** القيمة تُدرج في السلسلة بلا binding، ومصدرها عمود `raw_quantity` المكتوب من `$request` بلا تحقق نوعي.

**الباتش الموضعي للصرف** (`BOMController:534-541`) — إن أردت إصلاحاً فورياً قبل `InventoryService`:
```php
            // خصم ذرّي مشروط: لا سباق، ولا رصيد سالب، ولا DB::raw بقيمة
            // مُدرَجة نصّياً. `updateOrCreate` هنا كان يفشل عند أول صرف
            // لمادة لا سجل لها في المستودع، لأن INSERT لا يمكنه أن يشير
            // إلى العمود `quantity` قبل وجوده.
            $affected = ProductDetails::where('store_house_id', $defaultStoreHouse->id)
                ->where('product_id', $item->raw_product_id)
                ->where('quantity', '>=', $item->raw_quantity)
                ->decrement('quantity', $item->raw_quantity);

            if ($affected === 0) {
                throw new \RuntimeException(sprintf(
                    'رصيد غير كافٍ للمادة «%s» في المستودع «%s».',
                    optional(Product::find($item->raw_product_id))->name ?? '#'.$item->raw_product_id,
                    $defaultStoreHouse->name
                ));
            }
```

**وللإضافة** (السطور 621-627 و642-648):
```php
            $row = ProductDetails::firstOrCreate(
                [
                    'store_house_id' => $defaultStoreHouse->id,
                    'product_id' => $bom->product_id,
                ],
                ['quantity' => 0, 'unit_price' => 0]
            );

            ProductDetails::whereKey($row->id)->increment('quantity', $bom->quantity);
```

### الحل

```
Modules/Manufacturing/Services/InventoryService.php
```

| الدالة | العقد |
|---|---|
| `available($productId, $warehouseId): float` | الرصيد الحقيقي — نقطة واحدة تعرف كيف يُحسب |
| `issue($productId, $warehouseId, $qty, $ref)` | خصم **ذرّي مشروط**؛ يرمي إن لم يكفِ الرصيد |
| `receive($productId, $warehouseId, $qty, $unitPrice, $ref)` | إضافة **بمتوسط تكلفة مرجح** — لا كتابة فوق السعر |
| `reverse($permitId)` | عكس إذن كامل — يخدم الإلغاء والتراجع والحذف |

**نموذج `receive` — المتوسط المرجح:**
```php
    public function receive(int $productId, int $warehouseId, float $qty, float $unitPrice, ?string $ref = null): void
    {
        $row = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            ProductDetails::create([
                'product_id' => $productId,
                'store_house_id' => $warehouseId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ]);

            return;
        }

        // متوسط مرجح — لا كتابة فوق السعر.
        $oldValue = $row->quantity * $row->unit_price;
        $newValue = $qty * $unitPrice;
        $newQty = $row->quantity + $qty;

        $row->unit_price = $newQty > 0 ? round(($oldValue + $newValue) / $newQty, 4) : $unitPrice;
        $row->quantity = $newQty;
        $row->save();
    }
```

**نموذج `issue` — الخصم الذرّي المشروط:**
```php
    public function issue(int $productId, int $warehouseId, float $qty, ?string $ref = null): void
    {
        // decrement يكتب `quantity = quantity - N` في الخادم — لا سباق أصلاً.
        // والشرط >= يضمن ألا يصير الرصيد سالباً.
        $affected = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->where('quantity', '>=', $qty)
            ->decrement('quantity', $qty);

        if ($affected === 0) {
            throw new \RuntimeException(sprintf(
                'رصيد غير كافٍ للمادة «%s» في المستودع المحدد.',
                optional(Product::find($productId))->name ?? '#'.$productId
            ));
        }
    }
```

> **لماذا `decrement` أفضل من `lockForUpdate` + `-=`:** الثاني يكتب القيمة المطلقة فيمحو تعديل معاملة أخرى حتى مع القفل. الأول يترك الحساب للخادم.

### 🟡 مشروط بالقرار 1 — طبيعة `product_details`

في الملف الواحد، للجدول نفسه تفسيران متعارضان:

**(أ) سجلّ حركات — صفّ لكل حركة** (في الدوال الميتة):
```php
750: ProductDetails::create([... 'type' => 'in', 'date' => ..., 'time' => ...,
     'type_of_operation' => 'إنتاج', 'subaccount' => 'manufacturing_order_'.$order->id]);
```

**(ب) رصيد جارٍ — صفّ واحد لكل (منتج، مستودع)** (في المسارات الحيّة):
```php
2756: ProductDetails::firstOrCreate(['product_id'=>..., 'store_house_id'=>...], ['quantity'=>0, ...]);
2193: $productDetails->quantity -= $item->raw_quantity;
```

**أسماء الأعمدة (`type`, `date`, `time`, `type_of_operation`, `comments`) تشير بقوة إلى سجلّ حركات.** إن كان كذلك ⇒ **`->first()` في السطور 2104، 2170، 1549 يلتقط حركة عشوائية ويعدّل كميتها**، فيُفسد سجلّ الحركات ويعطي رصيداً خاطئاً.

**الشكل البديل إن كان سجلّ حركات:**
```php
    // إضافة حركة، لا تعديل رصيد
    ProductDetails::create([
        'product_id' => $productId,
        'store_house_id' => $warehouseId,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'type' => 'out',
        'date' => now()->toDateString(),
        'time' => now()->toTimeString(),
        'type_of_operation' => 'أمر تصنيع',
        'subaccount' => 'manufacturing_order_'.$order->id,
    ]);
```

**والرصيد يُقرأ بجمع الداخل ناقص الخارج — والدالة موجودة عندك بالفعل**، `ManufacturingOrdersController:851-860`:
```php
    // دالة للحصول على الكمية المتاحة للمنتج
    private function getProductAvailableQuantity($productId)
    {
        return ProductDetails::where('product_id', $productId)
            ->where('type', 'in')
            ->sum('quantity') -
            ProductDetails::where('product_id', $productId)
                ->where('type', 'out')
                ->sum('quantity');
    }
```

**لكنها ناقصة** — لا تقيّد بالمستودع، فتجمع الرصيد عبر كل المستودعات. أضِف المعامل:
```php
    private function getProductAvailableQuantity($productId, $warehouseId = null)
    {
        $base = fn (string $type) => ProductDetails::where('product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('store_house_id', $warehouseId))
            ->where('type', $type)
            ->sum('quantity');

        return (float) $base('in') - (float) $base('out');
    }
```

> **المفارقة:** هذه الدالة — المنطق الوحيد الذي يعامل الجدول كسجلّ حركات — **لا تُستدعى من أي مكان في الموديول**. وجودها دليل قوي على أن الجدول سجلّ حركات فعلاً، وأن أحدهم كتبها صحيحةً ثم كتب بقية الكود على التفسير الآخر.

### القاعدة بعد هذه المرحلة

**لا مسّ مباشر لـ `ProductDetails` في أي كنترولر.** كل حركة مخزون تمرّ من هنا.

---

<a id="p2-04"></a>

## P2-04 — `ManufacturingJournalService`: نقطة واحدة لكل قيد (يوم)

**🔵 يحتاج تصميماً** · **يخدم:** A1, A2, A4, A5

```
Modules/Manufacturing/Services/ManufacturingJournalService.php
```

| الدالة | العقد |
|---|---|
| `post(array $lines, $refType, $refId, $desc, $date)` | ينشئ قيداً **بعد التحقق من توازنه**؛ يرمي إن اختلّ |
| `reverse($refType, $refId)` | يعكس بـ `reference_id` لا بالوصف؛ يتخطى المعكوس سلفاً |
| `syncAccountBalances(array $accountIds)` | تحديث الأرصدة الهرمية — نقطة واحدة |

**نواة `post`:**
```php
    public function post(array $lines, $refType, int $refId, string $desc, $date = null): JournalEntry
    {
        $debit  = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($debit - $credit) > 0.01) {
            throw new \RuntimeException(sprintf(
                'قيد غير متوازن (%s): مدين %s ≠ دائن %s. أُلغيت العملية.',
                $desc, number_format($debit, 2), number_format($credit, 2)
            ));
        }

        $entry = JournalEntry::create([
            'date' => $date ?? now(),
            'description' => $desc,
            'status' => 1,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'created_by_employee' => auth()->id(),
        ]);

        foreach ($lines as $l) {
            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $l['account_id'],
                'description' => $l['description'] ?? $desc,
                'debit' => $l['debit'] ?? 0,
                'credit' => $l['credit'] ?? 0,
            ]);
        }

        $this->syncAccountBalances(array_column($lines, 'account_id'));

        return $entry;
    }
```

### مشكلة الأرصدة التي يحلّها `syncAccountBalances`

اليوم `createMaterialsReceiveJournalEntry` يعدّل `Account::balance` مباشرة (سطر 2551-2558)، بينما `createManufacturingJournalEntries` — خمسة قيود كاملة — **لا تلمس `Account::balance` ولا `updateBalancesHierarchically` إطلاقاً**.

**النتيجة:** أرصدة الحسابات المخزَّنة تعكس حركة صرف المواد فقط ولا تعكس الإنتاج ⇒ **`accounts.balance` ≠ مجموع `journal_entry_details`** ⇒ أي تقرير يقرأ الرصيد المخزَّن يخالف ميزان المراجعة. (الثابت **A2**.)

وكذلك `$account->balance -= $amount; $account->save()` هو قراءة‑تعديل‑كتابة بلا قفل ⇒ نفس سباق [P2-03](#p2-03) لكن على الأرصدة المحاسبية.

### القاعدة بعد هذه المرحلة

**لا `JournalEntry::create` مباشرة في أي كنترولر.** كل قيد يمرّ من هنا. هذه القاعدة وحدها تمنع عودة نصف عيوب القيود.

---

<a id="p2-05"></a>

## P2-05 — المبالغ المالية من العميل: `RecalculatesTotals` (نصف يوم)

**🔵 يحتاج تصميماً** · 🔴 حرج · ✅ مؤكد · **يخدم:** A7, M3

### المشكلة

| الحقل | الموضع |
|---|---|
| `last_total_cost` | `BOMController:133, 251` · `ManufacturingOrdersController:305, 597` · `OrdersController:78, 207` |
| `raw_total`, `expenses_total`, `manu_total`, `end_life_total` | `BOMController:148-174` |
| `total_cost` | `WorkstationsController:205` |
| `total` | `IndirectCostsController:219, 288` |

كلها تُحسب في JavaScript وتُقبل كما هي.

**`BOMController@finish` (سطر 684-713) يبني قيد يومية** مجموع مدينه `last_total_cost + endLifeTotal` ومجموع دائنه `rawTotal + Σexpenses + Σworkstations` — **ولا شيء في الكود يضمن تساويهما**. القيد يدخل الأستاذ بحالة `status = 1` (مُرحَّل) وتتغير أرصدة الحسابات بالفارق.

**تعديل بسيط في أدوات المطوّر يكفي لتزوير تكلفة تصنيع وقيد يومية مرحّل.**

### الحل

```
Modules/Manufacturing/Http/Controllers/Concerns/RecalculatesTotals.php
```

```php
<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

trait RecalculatesTotals
{
    /** يُعيد حساب إجمالي سطرٍ من كمية وسعر، ولا يثق بإجمالي العميل. */
    protected function lineTotal($quantity, $unitPrice): float
    {
        return round(((float) $quantity) * ((float) $unitPrice), 2);
    }

    /**
     * يتحقق أن القيد متوازن قبل الترحيل.
     *
     * @throws \RuntimeException
     */
    protected function assertBalanced(float $debit, float $credit, string $context = ''): void
    {
        if (abs(round($debit, 2) - round($credit, 2)) > 0.01) {
            throw new \RuntimeException(sprintf(
                'قيد غير متوازن%s: مدين %s ≠ دائن %s (الفرق %s). أُلغيت العملية.',
                $context ? ' ('.$context.')' : '',
                number_format($debit, 2),
                number_format($credit, 2),
                number_format($debit - $credit, 2)
            ));
        }
    }
}
```

### اربطه بالكنترولرات

بلا هذا لن يعمل `$this->lineTotal(...)`:
```php
class BOMController extends Controller
{
    use GeneratesDocumentNumbers, RecalculatesTotals, ResolvesAccountingAccounts, RespectsAccountingPeriods, UsesDocumentCurrency;
```
*(اقرأ سطر الـ`use` القائم داخل كل صنف وأضف الاسم إليه — لا تستبدله حرفياً بما كُتب أعلاه.)*

### التطبيق في `BOMController@store` (سطر 148-150)

الكود الحالي:
```php
                        'raw_unit_price' => $request->raw_unit_price[$index] ?? 0,
                        'raw_quantity' => $request->raw_quantity[$index] ?? 0,
                        'raw_total' => $request->raw_total[$index] ?? 0,
```
البديل:
```php
                        'raw_unit_price' => $request->raw_unit_price[$index] ?? 0,
                        'raw_quantity' => $request->raw_quantity[$index] ?? 0,
                        'raw_total' => $this->lineTotal(
                            $request->raw_quantity[$index] ?? 0,
                            $request->raw_unit_price[$index] ?? 0
                        ),
```
وطبّق المثل على `end_life_total`. أما `expenses_total` و`manu_total` فحسابهما يعتمد على `cost_type` — راجع منطق الـ JS في `bom/create.blade.php` وانقله كما هو إلى PHP.

### و`last_total_cost` — لا تقبله من الطلب إطلاقاً

بعد حفظ السطور:
```php
            // الإجمالي يُشتقّ من السطور المحفوظة، لا يُقبل من الواجهة
            $items = $productionMaterial->ProductionMaterialsItem();
            $productionMaterial->update([
                'last_total_cost' => round(
                    $items->sum('raw_total')
                    + $items->sum('expenses_total')
                    + $items->sum('manu_total')
                    - $items->sum('end_life_total'),
                    2
                ),
            ]);
```

> **ابقِ حساب JS كما هو** — هو للعرض الفوري للمستخدم. الفرق أن الخادم لم يعد يثق به.

### مقارنات float للمال — إصلاح مرافق

```php
3007: if ($rawMaterialsCost == 0 && $expensesCost == 0 && $manufacturingCost == 0) { ... }
3137: if ($netCost > 0) { ... } else { throw ... }
1689/2722: $unitPrice = $order->last_total_cost / $order->quantity;   // ← بلا حارس
```

- `== 0` قد يفشل على بقايا مثل `1.4e-14` ⇒ قيد بمبلغ عملي صفر.
- القسمة على صفر ترمي **`DivisionByZeroError`** — وهو `Error` لا `Exception` ⇒ **`catch (\Exception $e)` لا يلتقطه** ⇒ `DB::rollBack()` لا يُنفَّذ ⇒ معاملة معلّقة.
- **الحارس موجود في دالة مجاورة ولم يُستعمل** — `calculateUnitPrice` سطر 1657: `$order->quantity > 0 ? ($totalCost / $order->quantity) : 0`.

**الإصلاح:** `round($x, 2)` قبل كل مقارنة مالية، وحارس `quantity > 0` قبل كل قسمة.

---

<a id="p2-06"></a>

## P2-06 — `SafeListing`: قائمة بيضاء للترتيب وسقف للترقيم (نصف يوم)

**🟢 جاهز للصق** · 🟠 عالٍ · ✅ مؤكد

### المشكلة — ثلاثة كنترولرات، نفس العيب، ثلاثة أسماء معاملات

`ManufacturingOrdersController@getData:93-99` و`WorkstationsController@getData:47-53`:
```php
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
```

`IndirectCostsController@getData:97-102`:
```php
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 10);
```

- **`sort_order`:** Laravel يرمي `InvalidArgumentException` لأي قيمة غير `asc|desc` ⇒ **500 غير معالج بمجرد `?sort_order=x`**.
- **`sort_by`:** ليس حقن SQL (الـ Grammar يغلّف الاسم)، لكن أي عمود مختلَق يعطي `Unknown column` يُعرض نصّه للمستخدم ⇒ **تعداد أعمدة/جداول عبر فروق الاستجابة**.
- **`per_page` بلا سقف:** `?per_page=1000000` ⇒ استنزاف ذاكرة / DoS.

> **لاحظ التباعد:** الواجهة تُرسل ما يوافق كل كنترولر، فتوحيد الأسماء يكسر الواجهات. لذلك الـ Trait **يقبل الاسمين معاً**، ويمكنك توحيدهما لاحقاً بهدوء.

### الحل

```
Modules/Manufacturing/Http/Controllers/Concerns/SafeListing.php
```

```php
<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait SafeListing
{
    protected function applySafeSort($query, Request $request, array $allowed, string $default = 'created_at')
    {
        $field = $request->get('sort_field', $request->get('sort_by', $default));
        $dir = strtolower($request->get('sort_direction', $request->get('sort_order', 'desc')));

        if (! in_array($field, $allowed, true)) {
            $field = $default;
        }

        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return $query->orderBy($field, $dir);
    }

    protected function safePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->get('per_page', $default);

        return $perPage > 0 ? min($perPage, $max) : $default;
    }

    /** لا تسرّب نصّ الاستثناء للمستخدم إلا في وضع التطوير. */
    protected function safeError(\Throwable $e, string $message): string
    {
        return $message.(config('app.debug') ? ' ('.$e->getMessage().')' : '');
    }
}
```

### اربطه بالكنترولرات الثلاثة

```php
// مثال: IndirectCostsController.php — السطر 22 حالياً
    use ResolvesAccountingAccounts, UsesDocumentCurrency;
// يصير
    use ResolvesAccountingAccounts, SafeListing, UsesDocumentCurrency;
```
مع `use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;` في أعلى الملف.

### التطبيق

```php
// ManufacturingOrdersController@getData
        $this->applySafeSort($query, $request, [
            'id', 'code', 'name', 'quantity', 'status', 'delivery_date', 'created_at',
        ]);
        $orders = $query->paginate($this->safePerPage($request, 15));

// WorkstationsController@getData
        $this->applySafeSort($query, $request, ['id', 'name', 'code', 'total_cost', 'created_at']);
        $workstations = $query->paginate($this->safePerPage($request, 10));

// IndirectCostsController@getData
        $this->applySafeSort($query, $request, ['id', 'date', 'total', 'created_at']);
        $perPage = $this->safePerPage($request, 10);
```

---

<a id="p2-07"></a>

## P2-07 — اختبارات الأساس (نصف يوم)

اختبارات وحدة على الخدمتين **قبل أن يستعملهما أحد**:

- خصم أكبر من الرصيد يرمي `RuntimeException`
- خصمان متزامنان لا يُنتجان رصيداً سالباً
- متوسط التكلفة يُحسب صحيحاً (1000×10 + 10×50 ⇒ متوسط 10.396)
- قيد غير متوازن يُرفض قبل الكتابة
- `reverse()` يعكس القيد ولا يعكسه مرتين

---

## معيار قبول المرحلة 2

- الخدمتان مكتوبتان ومُختبَرتان
- الـ Enum وجدول الانتقالات جاهزان ومربوطان بالموديل
- الهجرات مطبَّقة على بيئة اختبار
- الـ Traits الاثنان (`RecalculatesTotals` و`SafeListing`) مربوطان بالكنترولرات — الثالث `BelongsToBranch` في المرحلة 6
- **وصفر تغيير في سلوك الواجهة** — هذه المرحلة بنية تحتية لا وظائف

---

<a id="7"></a>

# 7. المرحلة 3 — دورة أمر التصنيع (7 أيام)

**هذا القلب.** أعِد كتابة كل عملية لتمرّ بالخدمتين من المرحلة 2.

| اليوم | العملية | العيوب |
|---|---|---|
| 1 | `store` / `update` | [P3-09](#p3-09) |
| 2 | `receiveMaterials` | [P3-04](#p3-04) |
| 3 | `returnMaterials` | [P3-02](#p3-02), [P3-03](#p3-03) |
| 4-5 | `completeOrder` | [P3-05](#p3-05), [P3-11](#p3-11) |
| 6 | `undoCompletion` | [P3-06](#p3-06), [P3-10](#p3-10) |
| 7 | `cancelOrder` + `destroy` | [P3-01](#p3-01), [P3-07](#p3-07) |
| — | عابر للكل | [P3-08](#p3-08), [P3-12](#p3-12) |

---

<a id="p3-01"></a>

## P3-01 — إلغاء الأمر يبتلع المواد الخام نهائياً

**الملف:** `ManufacturingOrdersController.php:1826-1846` و`2894-2909` · 🔴 قاتل · ✅ مؤكد · **يخدم:** S2, S6 · 🔵 **يحتاج تصميماً**

### المشكلة

**جسم الدالة معلّق بالكامل. الدالة no-op تُرجع `null`.**

```php
    // تحديث رصيد المنتج في المستودع
    private function updateProductInventory($productId, $warehouseId, $quantity, $operation = 'add')
    {
        // تحديث رصيد المنتج في المستودع
        // يمكنك تخصيص هذا حسب بنية قاعدة البيانات الخاصة بك
        /*
        $inventory = ProductInventory::firstOrCreate([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        if ($operation === 'add') {
            $inventory->increment('quantity', $quantity);
        } else {
            $inventory->decrement('quantity', $quantity);
        }
        */
    }
```

**السبب الجذري:** الكود المعلّق يشير إلى موديل `ProductInventory` — **وهذا الموديل غير مستعمل في أي مكان آخر في الموديول**. المخزون الفعلي في `ProductDetails`. أي أن الدالة كُتبت لبنية قاعدة بيانات مختلفة ثم عُلّقت ونُسيت.

**ومع ذلك `cancelOrder` يستدعيها:**
```php
            // إذا تم استلام المواد، يجب إرجاعها للمخزون
            if ($order->status === ManufacturOrders::STATUS_MATERIALS_RECEIVED && $order->storehouse_id) {
                foreach ($order->manufacturOrdersItem as $item) {
                    if ($item->raw_product_id && $item->raw_quantity > 0) {
                        $this->updateProductInventory(
                            $item->raw_product_id,
                            $order->storehouse_id,
                            $item->raw_quantity,
                            'add'
                        );
                    }
                }
            }
```

**السيناريو المؤكد:** أمر تصنيع استُلمت مواده (خُصمت في `receiveMaterials` سطر 2193) ثم أُلغي → الأمر يُوسم «ملغى»، والمستخدم يرى «تم إلغاء أمر التصنيع بنجاح»، **والمواد الخام تبقى مخصومة إلى الأبد**. لا قيد عكسي، لا إذن مرتجع، لا شيء.

**الدوال الشقيقة الفارغة نفسها:** `recordProductMovement` (1805)، `deleteRelatedStockMovements` (1880)، و`getProductStockBefore` (1889) التي **تُرجع 0 دائماً** ثم تُستخدم لحساب `stock_before`/`stock_after` ⇒ سجل حركة المخزون بلا قيمة تدقيقية.

### البديل (أ) — أعِد كتابة الدالة على البنية الفعلية

**قبل المرحلة 2** (أو إن أردت باتشاً فورياً بلا انتظار `InventoryService`):

```php
    /**
     * تعديل رصيد منتج في مستودع تعديلاً ذرّياً.
     *
     * تُستدعى داخل معاملة مفتوحة فقط. تُرجع الكمية بعد التعديل.
     *
     * كان جسم الدالة معلّقاً بالكامل ويشير إلى موديل ProductInventory
     * غير المستعمل في أي مكان — المخزون الفعلي في ProductDetails.
     *
     * @param  string  $operation  'add' أو 'subtract'
     *
     * @throws \RuntimeException إذا كان الرصيد لا يكفي للخصم
     */
    private function updateProductInventory($productId, $warehouseId, $quantity, $operation = 'add')
    {
        if (! $productId || ! $warehouseId || $quantity <= 0) {
            return null;
        }

        $row = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($operation === 'add') {
            if (! $row) {
                $row = ProductDetails::create([
                    'product_id' => $productId,
                    'store_house_id' => $warehouseId,
                    'quantity' => 0,
                    'unit_price' => 0,
                ]);
            }
            $row->quantity += $quantity;
        } else {
            if (! $row || $row->quantity < $quantity) {
                throw new \RuntimeException(sprintf(
                    'رصيد غير كافٍ: المنتج #%s في المستودع #%s — المتوفر %s، المطلوب %s',
                    $productId, $warehouseId, $row->quantity ?? 0, $quantity
                ));
            }
            $row->quantity -= $quantity;
        }

        $row->save();

        return $row->quantity;
    }
```

> 🟡 **مشروط بالقرار 1.** الباتش أعلاه مبني على تفسير «الرصيد الجاري». إن كان الجدول سجلّ حركات فاستعمل الصياغة البديلة في [P2-03](#p2-03).

> **بعد المرحلة 2** احذف الدالة كلياً واستدعِ `InventoryService::receive()` / `::issue()` مباشرةً.

### البديل (ب) — إذن إرجاع حقيقي لا تعديل صامت

```php
            // إذا تم استلام المواد، تُرجَع للمخزون بإذنٍ مرتجع لا بتعديل صامت
            if ($order->status === ManufacturOrderStatus::MaterialsReceived && $order->storehouse_id) {
                $returnPermit = WarehousePermits::create([
                    'store_houses_id' => $order->storehouse_id,
                    'permission_type' => 1, // إضافة
                    'permission_source_id' => SystemAccountIds::PERMISSION_SOURCE_MANUFACTURING_ORDER,
                    'reference_id' => $order->id,
                    'permission_date' => now(),
                    'number' => $this->resolveDocumentNumber(null, 'warehouse-add'),
                    'details' => 'إرجاع مواد بسبب إلغاء أمر التصنيع: '.$order->name,
                    'grand_total' => 0,
                    'created_by' => auth()->id(),
                    'status' => 1,
                ]);

                $returnedTotal = 0;

                foreach ($order->manufacturOrdersItem as $item) {
                    if (! $item->raw_product_id || $item->raw_quantity <= 0) {
                        continue;
                    }

                    $unitPrice = $item->raw_unit_price ?? 0;
                    $lineTotal = $this->lineTotal($item->raw_quantity, $unitPrice);

                    WarehousePermitsProducts::create([
                        'warehouse_permits_id' => $returnPermit->id,
                        'product_id' => $item->raw_product_id,
                        'quantity' => $item->raw_quantity,
                        'unit_price' => $unitPrice,
                        'total' => $lineTotal,
                    ]);

                    // عبر الخدمة — بقفل ومتوسط تكلفة
                    app(InventoryService::class)->receive(
                        $item->raw_product_id,
                        $order->storehouse_id,
                        $item->raw_quantity,
                        $unitPrice,
                        'cancel-order-'.$order->id
                    );

                    $returnedTotal += $lineTotal;
                }

                $returnPermit->update(['grand_total' => $returnedTotal]);

                // القيد المحاسبي المقابل — إلزامي، لا يُبتلع
                $storehouse = StoreHouse::find($order->storehouse_id);
                $this->createMaterialsReturnJournalEntry($returnPermit, $order, $storehouse, $returnedTotal);
            }
```

### ملاحظات

**🟡 مفتاح الترقيم:** الموديول يستعمل ثلاثة مفاتيح فقط:
```
'warehouse-add'         BOMController:589
'warehouse-dispose'     BOMController:504
'manufacturing-orders'  ManufacturingOrdersController:168
```
`'warehouse-receive'` لا وجود له. استعمل `'warehouse-add'` أو أنشئ مفتاحاً جديداً في إعدادات الترقيم أولاً.

**ولاحظ التباعد:** `returnMaterials` تولّد رقمها يدوياً بـ `'MFG-RTN-'.$order->code.'-'.time()` ولا تمرّ بالعدّاد إطلاقاً. **وحّدها كلها على `resolveDocumentNumber`** — انظر [P6-08](#p6-08).

**`getProductStockBefore` لا تحذفها بل اكتبها:**
```php
    private function getProductStockBefore($productId, $warehouseId)
    {
        return (float) (ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->value('quantity') ?? 0);
    }
```
لأن `stock_before`/`stock_after` هما السجل التدقيقي للمخزون، وهما صفر في كل الصفوف اليوم.

---

<a id="p3-02"></a>

## P3-02 — `returnMaterials` يسمح بخلق مخزون من العدم

**الملف:** `ManufacturingOrdersController.php:2342-2348` و`2376-2416` · 🔴 حرج · ✅ مؤكد · **يخدم:** S4 · 🟢 **جاهز للصق**

### المشكلة — ثلاث ثغرات مركّبة

**الكود الحالي (التحقق):**
```php
        $request->validate([
            'storehouse_id' => 'required|exists:store_houses,id',
            'return_items' => 'required|array',
            'return_items.*.product_id' => 'required|exists:products,id',
            'return_items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);
```

**الكود الحالي (المعالجة):**
```php
                // الحصول على سعر المادة من البند الأصلي
                $orderItem = $order->manufacturOrdersItem()
                    ->where('raw_product_id', $item['product_id'])
                    ->first();

                $unitPrice = $orderItem ? ($orderItem->raw_unit_price ?? 0) : 0;
                $total = $item['quantity'] * $unitPrice;
```

1. **لا سقف للكمية.** إرسال `quantity = 999999` يُضاف مباشرة للمخزون، مع قيد محاسبي مقابل يُدين المستودع بنفس القيمة.
2. **لا تحقق من انتماء المنتج للأمر.** `exists:products,id` فقط. إن لم يجد البند يمضي بسعر 0 بدل الرفض ⇒ **يمكن إضافة أي منتج في النظام إلى أي مستودع** عبر أمر تصنيع لا علاقة له به.
3. **لا تراكم.** لا حساب لما أُرجع سابقاً ⇒ يمكن استدعاء الإرجاع مراراً على نفس البنود.

### البديل — أضف بعد `$order = ManufacturOrders::findOrFail($id);` وقبل حلقة المعالجة

```php
            // ما صُرف فعلاً لكل مادة في هذا الأمر
            $issuedByProduct = $order->manufacturOrdersItem()
                ->whereNotNull('raw_product_id')
                ->where('raw_quantity', '>', 0)
                ->get()
                ->keyBy('raw_product_id');

            // ما أُرجع سابقاً لكل مادة (من إذونات الإرجاع السابقة)
            $alreadyReturned = WarehousePermitsProducts::query()
                ->whereIn('warehouse_permits_id', function ($q) use ($order) {
                    $q->select('id')->from('warehouse_permits')
                        ->where('reference_id', $order->id)
                        ->where('permission_source_id', 15)
                        ->where('permission_type', 1);
                })
                ->selectRaw('product_id, SUM(quantity) AS qty')
                ->groupBy('product_id')
                ->pluck('qty', 'product_id');

            $errors = [];
            foreach ($request->return_items as $item) {
                if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                    continue;
                }

                $pid = $item['product_id'];

                // (1) المنتج يجب أن يكون ضمن بنود هذا الأمر
                if (! $issuedByProduct->has($pid)) {
                    $errors[] = 'المنتج #'.$pid.' ليس ضمن مواد هذا الأمر.';
                    continue;
                }

                // (2) لا يتجاوز المرتجع ما صُرف، بعد خصم ما أُرجع سابقاً
                $issued = (float) $issuedByProduct[$pid]->raw_quantity;
                $returned = (float) ($alreadyReturned[$pid] ?? 0);
                $remaining = $issued - $returned;

                if ((float) $item['quantity'] > $remaining + 0.0001) {
                    $errors[] = sprintf(
                        'المنتج «%s»: المتاح للإرجاع %s فقط (صُرف %s، أُرجع سابقاً %s).',
                        optional(Product::find($pid))->name ?? ('#'.$pid),
                        $remaining,
                        $issued,
                        $returned
                    );
                }
            }

            if (! empty($errors)) {
                DB::rollBack();

                return redirect()->back()->withInput()
                    ->with('error', "تعذّر إرجاع المواد:\n\n• ".implode("\n• ", $errors));
            }
```

**وفي حلقة المعالجة، استبدل:**
```php
                $unitPrice = $orderItem ? ($orderItem->raw_unit_price ?? 0) : 0;
```
**بـ:**
```php
                // البند مضمون الوجود بعد التحقق أعلاه
                $unitPrice = $issuedByProduct[$item['product_id']]->raw_unit_price ?? 0;
```

---

<a id="p3-03"></a>

## P3-03 — إرجاع جزئي يعيد الحالة إلى «جديد»: حلقة استنزاف

**الملف:** `ManufacturingOrdersController.php:2446-2451` · 🔴 حرج · ✅ مؤكد · **يخدم:** M1, S4 · 🟢 **جاهز للصق**

### المشكلة

```php
            // تحديث حالة أمر التصنيع إلى "جديد" بعد إرجاع المواد
            $order->update([
                'status' => ManufacturOrders::STATUS_PENDING, // إرجاع الحالة إلى جديد
                'materials_received_at' => null,
                'updated_by' => auth()->id(),
            ]);
```

أياً كانت الكمية المرتجعة — ولو وحدة واحدة من عشرين مادة — يُعاد الأمر إلى «جديد» ويُمسح تاريخ الاستلام ⇒ `canReceiveMaterials()` تصير `true` ⇒ **يمكن تنفيذ `receiveMaterials` ثانيةً فيُخصم كامل المواد مرة أخرى**، بينما لم يُرجَع منها إلا جزء.

**حلقة استنزاف مخزون قابلة للتكرار بلا حد.**

### البديل — لا ترجع الحالة إلا عند إرجاع كل شيء

```php
            // الحالة لا تعود إلى «جديد» إلا إذا أُرجعت كل المواد المصروفة.
            // الإرجاع الجزئي يُبقي الأمر في حالته، وإلا أمكن استلام المواد
            // مرة ثانية فتُخصم كاملةً وقد أُرجع جزؤها فقط.
            $stillOutstanding = false;

            foreach ($issuedByProduct as $pid => $orderItem) {
                $issued = (float) $orderItem->raw_quantity;

                $returnedNow = collect($request->return_items)
                    ->where('product_id', $pid)
                    ->sum('quantity');

                $returnedBefore = (float) ($alreadyReturned[$pid] ?? 0);

                if ($issued - $returnedBefore - (float) $returnedNow > 0.0001) {
                    $stillOutstanding = true;
                    break;
                }
            }

            if ($stillOutstanding) {
                $order->update(['updated_by' => auth()->id()]);
            } else {
                $order->update([
                    'status' => ManufacturOrderStatus::Pending,
                    'materials_received_at' => null,
                    'updated_by' => auth()->id(),
                ]);
            }
```

**متطلب:** يستعمل `$issuedByProduct` و`$alreadyReturned` من [P3-02](#p3-02) — طبّقهما معاً.

---

<a id="p3-04"></a>

## P3-04 — ابتلاع فشل القيد بعد خصم المخزون

**الملف:** `ManufacturingOrdersController.php:2569-2576` و`2668-2675` · 🟠 عالٍ · ✅ مؤكد · **يخدم:** A1, S2 · 🟢 **جاهز للصق**

### المشكلة

`createMaterialsReceiveJournalEntry` تُستدعى في السطر 2217 **بعد** خصم المخزون (2193) وداخل نفس المعاملة. ابتلاع الاستثناء يعني: **المخزون يُخصم، والقيد لا يُكتب، والمستخدم يرى «تم استلام المواد بنجاح»**.

وهو مناقض صراحةً لسياستك المعلنة في `completeOrder:2782`: «القيود المحاسبية إلزامي — إذا فشل يتم إلغاء العملية بالكامل».

> ⚠️ **موضعان مختلفان — لا تعاملهما نسخةً واحدة.**

### (أ) `createMaterialsReceiveJournalEntry` — السطور 2569-2576

الكود الحالي:
```php
        } catch (\Exception $e) {
            OperationLog::failure($e, 'manufacturing_order', 'returnMaterials');
            \Log::error('Failed to create materials receive journal entry', [
                'error' => $e->getMessage(),
                'permit_id' => $permit->id,
            ]);
            // لا نوقف العملية بسبب فشل القيد
        }
```

البديل:
```php
        } catch (\Exception $e) {
            // كان اسم العملية 'returnMaterials' — خطأ نسخ ولصق يوجّه
            // التحقيق في سجلّ الأعطال إلى الدالة الخطأ.
            OperationLog::failure($e, 'manufacturing_order', 'receiveMaterials');
            \Log::error('Failed to create materials receive journal entry', [
                'error' => $e->getMessage(),
                'permit_id' => $permit->id,
            ]);

            // القيد إلزامي: المخزون خُصم قبل هذه النقطة، وابتلاع الفشل هنا
            // يترك مخزوناً منقوصاً بلا أثر في الدفاتر. نرمي الاستثناء ليصعد
            // إلى الـ catch الخارجي فيُرجِع المعاملة كاملة.
            throw $e;
        }
```

### (ب) `createMaterialsReturnJournalEntry` — السطور 2668-2675

هنا **اسم العملية `'returnMaterials'` صحيح** ولا يُلمس، ورسالة اللوج تقول `return` لا `receive`. الإصلاح الوحيد رمي الاستثناء:
```php
        } catch (\Exception $e) {
            OperationLog::failure($e, 'manufacturing_order', 'returnMaterials');
            \Log::error('Failed to create materials return journal entry', [
                'error' => $e->getMessage(),
                'permit_id' => $permit->id,
            ]);

            throw $e;   // بدل «لا نوقف العملية بسبب فشل القيد»
        }
```

> ⛔ **لا تنسخ باتش (أ) على (ب)** — ستُفسد اسم العملية الصحيح في سجلّ الأعطال.

### (ج) المخارج الصامتة الثلاثة قبلهما

ثلاثة شروط **بمتغيرات مختلفة** — كلٌّ يحتاج رسالته:
```php
// السطر 2495
        if (! $storeAccount) {
            throw new \RuntimeException(
                'المستودع «'.($storehouse->name ?? '#'.$permit->store_houses_id).'» بلا حساب محاسبي مرتبط.'
            );
        }

// السطر 2505 — متغيّر آخر: معرّف حساب الأمر
        if (! $targetAccountId) {
            throw new \RuntimeException(
                'أمر التصنيع #'.$order->id.' بلا حساب محاسبي (account_id) — لا يمكن ترحيل حركة المواد.'
            );
        }

// السطر 2513 — متغيّر ثالث: الحساب نفسه غير موجود
        if (! $targetAccount) {
            throw new \RuntimeException('الحساب المحاسبي #'.$targetAccountId.' غير موجود.');
        }
```
**اقرأ الشروط الثلاثة في ملفك قبل الاستبدال** — أسماء المتغيرات مأخوذة من كودك، لكن نصّ كل شرط قد يختلف قليلاً.

### (د) وحوّل الخصم إلى الخدمة

استبدل الحلقة في 2161-2204 لتستدعي `InventoryService::issue()` بدل `$productDetails->quantity -= ...`. هذا يُنهي فحص التوفر المنفصل عن الخصم (TOCTOU) لأن الخدمة تفحص وتخصم في عملية ذرّية واحدة.

---

<a id="p3-05"></a>

## P3-05 — حساب WIP لا يُقفل: القيد السادس المفقود

**الملف:** `ManufacturingOrdersController.php:2939-3223` · 🔴 حرج · ✅ مؤكد · **يخدم:** A3, M4 · 🔵 **يحتاج تصميماً**

### المشكلة

القيود متوازنة **كلٌّ على حدة** (كل قيد مدين واحد ودائن واحد بنفس المبلغ) — هذا صحيح. **لكن المجموع الدوري لحساب WIP لا يصفر.**

```php
$calculatedTotalCost = $rawMaterialsCost + $expensesCost + $manufacturingCost;   // 2975
$totalCost = $order->last_total_cost ?? 0;                                       // 2978
if ($totalCost <= 0 && $calculatedTotalCost > 0) {                               // 2979
    $totalCost = $calculatedTotalCost;
}
$netCost = $totalCost - $wasteMaterialsValue;                                    // 3011
```

- القيود 1+2+3 **تُدين** WIP بـ `$calculatedTotalCost` (محسوب من البنود).
- القيد 4 **يُقرض** WIP بـ `$netCost`، والقيد 5 بـ `$waste` ⇒ إجمالي الإقراض = `$totalCost` (**من الواجهة**).

⇒ **الفرق المتبقي في WIP = `$calculatedTotalCost − $totalCost`**، وهو ليس صفراً كلّما كان `last_total_cost > 0` ومختلفاً عن مجموع البنود — **وهذا هو الوضع الطبيعي**. الفرق يتراكم في حساب WIP إلى الأبد ولا يظهر في أي تقرير أو تسوية.

### مشكلة ثانية مرافقة — قيمة المخزون ≠ المبلغ المُرحَّل

```php
$actualQuantity = $request->actual_quantity ?? $order->quantity;   // 2719
$unitPrice = $order->last_total_cost / $order->quantity;           // 2722
$totalValue = $actualQuantity * $unitPrice;                        // 2723 ← الإذن والمخزون بهذا
...
$netCost = $totalCost - $wasteMaterialsValue;                      // 3011 ← القيد بهذا (للكمية الكاملة)
```

إن كانت الكمية الفعلية أقل من المخطَّطة (وهو الوضع الطبيعي لوجود الهالك)، فالإذن و`ProductDetails` يحملان `actualQuantity × unitPrice`، بينما القيد 4 يُدين البضاعة الجاهزة بتكلفة **الكمية الكاملة** ⇒ **قيمة مخزون البضاعة الجاهزة في الدفاتر تفوق قيمتها في نظام المخزون** بفارق `(quantity − actual_quantity) × unitPrice`.

### الحل — القيود الستة بعد الإصلاح

| # | القيد | مدين | دائن |
|---|---|---|---|
| 1 | صرف المواد الخام للإنتاج | WIP | مخزون المواد |
| 2 | تحميل المصروفات | WIP | حسابات المصروفات |
| 3 | تحميل أجور التشغيل | WIP | حسابات الأجور/المحطات |
| 4 | استلام المنتج التام | مخزون البضاعة الجاهزة | WIP |
| 5 | استلام الهالك | مخزون الهالك | WIP |
| **6** | **انحراف الإنتاج (جديد)** | **انحراف التصنيع** | **WIP** |

```
مبلغ الانحراف = (التكلفة المحمّلة على WIP) − (قيمة المنتج التام + الهالك المضافة للمخزون)
```

**بوجوده:** مجموع مدين WIP = مجموع دائنه لكل أمر ← الثابت **A3** يصمد.
**بغيابه:** فرقٌ يتراكم إلى الأبد ولا يظهر في أي تقرير.

### الهيكل المقترح

```php
    private function buildManufacturingEntries(ManufacturOrders $order, float $actualQty, int $warehouseId): array
    {
        $raw        = round((float) $order->manufacturOrdersItem()->sum('raw_total'), 2);
        $expenses   = round((float) $order->manufacturOrdersItem()->sum('expenses_total'), 2);
        $labor      = round((float) $order->manufacturOrdersItem()->sum('manu_total'), 2);
        $wipCharged = round($raw + $expenses + $labor, 2);

        $unitCost      = $order->quantity > 0 ? round($wipCharged / $order->quantity, 4) : 0;
        $finishedValue = round($actualQty * $unitCost, 2);
        $wasteValue    = round((float) $this->calculateWasteMaterialsTotal($order), 2);

        // ما تبقّى في WIP بعد إخراج المنتج التام والهالك
        $variance = round($wipCharged - $finishedValue - $wasteValue, 2);

        $lines = [
            // 1-3: تحميل WIP
            ['account_id' => $this->wipAccount(),      'debit' => $raw,      'credit' => 0, 'description' => 'مواد خام'],
            ['account_id' => $this->rawStockAccount(), 'debit' => 0,         'credit' => $raw],
            // ... المصروفات والأجور بنفس النمط
            // 4: المنتج التام
            ['account_id' => $this->finishedGoodsAccount(), 'debit' => $finishedValue, 'credit' => 0],
            ['account_id' => $this->wipAccount(),            'debit' => 0,   'credit' => $finishedValue],
            // 5: الهالك
            ['account_id' => $this->wasteAccount(), 'debit' => $wasteValue, 'credit' => 0],
            ['account_id' => $this->wipAccount(),    'debit' => 0,          'credit' => $wasteValue],
        ];

        // 6: قيد الانحراف — يُقفل WIP
        if (abs($variance) > 0.01) {
            if ($variance > 0) {
                // تكلفة محمّلة أكثر من القيمة المستلمة ⇒ انحراف غير مواتٍ
                $lines[] = ['account_id' => $this->varianceAccount(), 'debit' => $variance, 'credit' => 0,
                            'description' => 'انحراف إنتاج غير مواتٍ — أمر #'.$order->id];
                $lines[] = ['account_id' => $this->wipAccount(),      'debit' => 0, 'credit' => $variance];
            } else {
                $lines[] = ['account_id' => $this->wipAccount(),      'debit' => abs($variance), 'credit' => 0];
                $lines[] = ['account_id' => $this->varianceAccount(), 'debit' => 0, 'credit' => abs($variance),
                            'description' => 'انحراف إنتاج موات — أمر #'.$order->id];
            }
        }

        return $lines;
    }
```
ثم يُمرَّر كله إلى `ManufacturingJournalService::post()` الذي يرفض غير المتوازن قبل الكتابة.

### ملاحظات

> 🟡 **حساب «انحراف التصنيع» قد لا يكون موجوداً في شجرة حساباتك.** ناتج السكربت سيبيّن ذلك، وقد يلزم إنشاؤه ضمن المصروفات. **هذا سؤال للمحاسب لا للمطوّر.**

> **لاحظ أن `$order->last_total_cost` لم يعد يدخل الحساب إطلاقاً** — `$wipCharged` يُشتقّ من البنود. هذا ما يجعل الثابت **A7** يصمد وهو تطبيق [P2-05](#p2-05).

---

<a id="p3-06"></a>

## P3-06 — عكس القيود بالبحث النصّي: مضاعفة + LIKE injection

**الملف:** `ManufacturingOrdersController.php:1575-1607` · 🔴 حرج · ✅ مؤكد · **يخدم:** A4, A5 · 🟢 **جاهز للصق**

### المشكلة

```php
            // عكس القيود المحاسبية المرتبطة بأمر التصنيع (بدلاً من حذفها)
            // نستخدم description لأن جدول journal_entries لا يحتوي على manufacturing_order_id
            $journalEntries = JournalEntry::where('description', 'like', '%أمر التصنيع: '.$order->name.' (#'.$order->id.')%')
                ->with('details')
                ->get();

            $reversedCount = 0;
            foreach ($journalEntries as $originalEntry) {
                // إنشاء قيد عكسي جديد
                $reversalEntry = JournalEntry::create([
                    'date' => now(),
                    'description' => '🔄 عكس قيد - '.$originalEntry->description,
                    'status' => 1,
                    'reference_type' => $originalEntry->reference_type,
                    'client_id' => $originalEntry->client_id,
                    'employee_id' => $originalEntry->employee_id,
                    'created_by_employee' => auth()->id(),
                ]);
```

**(أ) التعليق يكذب على قارئه.** القيود تُنشأ في السطرين 3031-3032 بـ `reference_type => ReferenceType::MANUFACTURING_ORDER` و`reference_id => $order->id`. **المفتاح الصحيح موجود ومُستخدم.** البحث النصّي لا مبرر له.

**(ب) عكسٌ مزدوج مؤكد.** القيد العكسي يُكتب بالوصف `'🔄 عكس قيد - '.$originalEntry->description` — وهو نص **يطابق نفس نمط `LIKE`**. بعد دورة `complete → undo → complete → undo` ثانية، الاستعلام يلتقط **القيود الأصلية القديمة + القيود العكسية القديمة + قيود الدورة الجديدة** ويعكسها جميعاً ⇒ **تضخيم مضاعف في الأستاذ العام**.

**(ج) LIKE injection.** `$order->name` يدخل نمط `LIKE` بلا هروب `%` و`_`، وهو مدخل يتحكم به المستخدم (`'name' => $request->name` سطر 294). **اسم أمر مثل `%` يجعل النمط يطابق كل قيود النظام ⇒ عكس جماعي للأستاذ العام.**

**(د) هشاشة الاسم.** تعديل `name` بعد الإتمام يكسر المطابقة نهائياً ⇒ لا يُعكس شيء، والدالة تُثبّت بنجاح.

**(هـ) القيد العكسي بلا `reference_id`** ⇒ لا يمكن ربطه بالأمر لاحقاً.

### البديل

```php
            // عكس القيود المحاسبية المرتبطة بأمر التصنيع.
            //
            // كان البحث هنا نصّياً في `description` بحجّة أن الجدول لا يحمل
            // معرّف الأمر — وهو غير صحيح: القيود تُنشأ بـ reference_type
            // و reference_id (انظر createManufacturingJournalEntries).
            // والبحث النصّي كان يلتقط القيود العكسية نفسها فيُضاعفها،
            // ويقبل `%` من اسم الأمر فيطابق دفتر الأستاذ كله.
            $journalEntries = JournalEntry::where('reference_type', ReferenceType::MANUFACTURING_ORDER)
                ->where('reference_id', $order->id)
                ->whereNull('reversed_at')          // لا يُعكس ما عُكس
                ->where('is_reversal', false)       // ولا يُعكس قيدٌ عكسي
                ->with('details')
                ->get();

            $reversedCount = 0;
            foreach ($journalEntries as $originalEntry) {
                $reversalEntry = JournalEntry::create([
                    'date' => now(),
                    'description' => 'عكس قيد - '.$originalEntry->description,
                    'status' => 1,
                    'reference_type' => $originalEntry->reference_type,
                    'reference_id' => $originalEntry->reference_id,   // كان مفقوداً
                    'is_reversal' => true,
                    'reverses_entry_id' => $originalEntry->id,
                    'client_id' => $originalEntry->client_id,
                    'employee_id' => $originalEntry->employee_id,
                    'created_by_employee' => auth()->id(),
                ]);

                foreach ($originalEntry->details as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reversalEntry->id,
                        'account_id' => $detail->account_id,
                        'description' => 'عكس: '.$detail->description,
                        'debit' => $detail->credit,
                        'credit' => $detail->debit,
                    ]);
                }

                $originalEntry->update([
                    'reversed_at' => now(),
                    'reversed_by_entry_id' => $reversalEntry->id,
                ]);

                $reversedCount++;
            }
```

**يحتاج هجرة [P2-02](#p2-02) أولاً** (الأعمدة الأربعة). والأفضل نقل هذا كله إلى `ManufacturingJournalService::reverse()` من [P2-04](#p2-04).

---

<a id="p3-07"></a>

## P3-07 — `destroy()`: حذف بمعايير لا تطابق شيئاً

**الملف:** `ManufacturingOrdersController.php:942-955` · 🔴 حرج · ✅ مؤكد · **يخدم:** A4, A5, A6 · 🟢 **جاهز للصق**

### المشكلة

```php
    public function destroy($id)
    {
        $order = ManufacturOrders::findOrFail($id);

        // حذف القيد المحاسبي المرتبط
        JournalEntry::where('type_id', $order->id)
            ->where('description', 'LIKE', '%أمر تصنيع%') // للحيطة لعدم حذف قيود أخرى بالخطأ
            ->delete();

        $order->manufacturOrdersItem()->delete();
        $order->delete();

        return redirect()->route('manufacturing.orders.index')->with(['success' => 'تم حذف البيانات بنجاح.']);
    }
```

1. **`type_id` لا يُكتب أبداً** — المكتوب `reference_id`.
2. **النمط لا يطابق:** كل الأوصاف المُنشأة تقول **«أمر التصنيع»** (بأل التعريف، سطور 3029، 3072، 3106، 3140، 3178)، والنمط يبحث عن **«أمر تصنيع»** بدونها.
3. **لا معاملة، لا `try/catch`، لا فحص فترة محاسبية، لا عكس مخزني، ولا فحص حالة** — يمكن حذف أمر منتهٍ ومُرحَّل.

### البديل

```php
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = ManufacturOrders::with('manufacturOrdersItem')->findOrFail($id);

            // (1) لا يُحذف أمرٌ خرج عن حالة المسودّة/الملغى.
            //     المكتمل والمُرحَّل يُلغى (cancelOrder) أو يُتراجَع عنه
            //     (undoCompletion) — الحذف يترك دفتراً لا مستند له.
            if (! in_array($order->status, [
                ManufacturOrderStatus::Pending,
                ManufacturOrderStatus::Cancelled,
            ], true)) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لا يمكن حذف أمر تصنيع في حالة «'.$order->status->label().'». ألغِ الأمر أو تراجع عن إنهائه أولاً.'
                );
            }

            // (2) الفترة المحاسبية
            if ($block = $this->accountingPeriodBlock($order->created_at ?? now())) {
                DB::rollBack();

                return redirect()->back()->with('error', $block);
            }

            // (3) لا يُحذف أمرٌ له أثر مخزني قائم
            $hasPermits = WarehousePermits::where('reference_id', $order->id)
                ->where('permission_source_id', 15)
                ->exists();

            if ($hasPermits) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لهذا الأمر إذونات مخزنية. أرجع المواد وتراجع عن الإنهاء قبل الحذف.'
                );
            }

            // (4) القيود بالمفتاح الصحيح — لا بالوصف
            $entries = JournalEntry::where('reference_type', ReferenceType::MANUFACTURING_ORDER)
                ->where('reference_id', $order->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->delete();
            }

            $order->manufacturOrdersItem()->delete();
            $order->delete();

            OperationLog::record('deleted', 'manufacturing_order', $id,
                'تم حذف أمر التصنيع **'.$order->name.'**');

            DB::commit();

            return redirect()->route('manufacturing.orders.index')
                ->with(['success' => 'تم حذف البيانات بنجاح.']);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, 'manufacturing_order', 'destroy');

            return redirect()->back()->with('error', $this->safeError($e, 'تعذّر حذف أمر التصنيع.'));
        }
    }
```

> **بعد هذا الباتش فقط** طبّق [P1-02](#p1-02) و[P1-07](#p1-07)(أ).

> ⚠️ **`$order->status->label()`** يفترض تفعيل الـ Enum من [P2-01](#p2-01). قبله استعمل `$order->status` نصّاً.

---

<a id="p3-08"></a>

## P3-08 — تسع دوال تفتح معاملة وتخرج بلا `rollBack`

**🟠 عالٍ · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

```php
        DB::beginTransaction();
        try {
            $order = ManufacturOrders::findOrFail($id);

            if (! $order->canBeCompleted()) {
                return redirect()->back()->with(['error' => '...']);   // ← لا rollBack
            }
```

المعاملة تُترك مفتوحة حتى نهاية الطلب. في FPM العادي تُغلق ضمنياً، لكن تحت **Octane / queue / اتصال مُجمَّع** تتسرّب إلى الطلب التالي، وقد تُثبَّت كتابات لاحقة داخلها أو يُقفل صفّ لمدة طويلة.

### المواضع

| الملف | الدالة | سطر البدء | سطر الخروج |
|---|---|---|---|
| `ManufacturingOrdersController` | `closeOrder` | 865 | 871 |
| | `reopenOrder` | 905 | 911 |
| | `finishOrder` | 1306 | 1312 |
| | `finishOrderWithPermitCleanup` | 1373 | 1379 |
| | `undoCompletion` | 1501 | 1506 |
| | `returnMaterials` | 2350 | 2356 |
| | `completeOrder` | 2700 | 2715 |
| | `cancelOrder` | 2885 | 2891 |
| `BOMController` | `finish` | 557 | 567 |

### البديل

```php
            if (! $order->canBeCompleted()) {
                DB::rollBack();

                return redirect()->back()->with(['error' => '...']);
            }
```

### كيف تجدها كلها

```bash
grep -n "return redirect()" Http/Controllers/ManufacturingOrdersController.php
```
ثم لكل نتيجة، تحقق إن كانت بين `DB::beginTransaction()` و`DB::commit()` بلا `rollBack` قبلها.

> **النموذج الصحيح موجود عندك** — `receiveMaterials` تفعلها صحيحةً في 5 مواضع (2087، 2095، 2124، 2175، 2207). انسخ منها.

---

<a id="p3-09"></a>

## P3-09 — `update()` بلا حارس حالة: تعديل أمر مُرحَّل

**الملف:** `ManufacturingOrdersController.php:579-585` · 🟠 عالٍ · ✅ مؤكد · **يخدم:** M2, A6 · 🟢 **جاهز للصق**

### المشكلة

لا فحص لـ `status` ولا `accountingPeriodBlock` (بخلاف `finishOrder` و`undoCompletion` و`receiveMaterials` و`returnMaterials` و`completeOrder` — كلها تفحصه).

**النتائج المؤكدة:**
1. يمكن تعديل كميات وتكاليف أمرٍ **مكتمل ومُرحَّل محاسبياً** ⇒ القيود المُثبَّتة تصف واقعاً لم يعد موجوداً.
2. `$order->manufacturOrdersItem()->delete()` ثم إعادة الإنشاء ⇒ **معرّفات البنود تتغير كلياً**، وأي مرجع خارجي إليها ينكسر.
3. بعد `receiveMaterials`، يستطيع المستخدم تغيير `raw_quantity` ⇒ **لا سبيل بعدها لمعرفة ما صُرف فعلاً**، مما يُبطل إمكانية تصحيح [P3-01](#p3-01) و[P3-02](#p3-02) لاحقاً.

### الكود الحالي

```php
    public function update(ManufacturOrderRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = ManufacturOrders::findOrFail($id);

            $order->update([
```

### البديل

```php
    public function update(ManufacturOrderRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = ManufacturOrders::findOrFail($id);

            // (1) لا يُعدَّل أمرٌ خرج عن حالة «جديد».
            //     بعد استلام المواد صار للأمر أثرٌ مخزني، وتعديل
            //     raw_quantity يمحو ما صُرف فعلاً فلا يبقى سبيل لعكسه.
            //     وبعد الإنهاء صار له قيدٌ مُرحَّل يصف أرقاماً لم تعد قائمة.
            if ($order->status !== ManufacturOrderStatus::Pending) {
                DB::rollBack();

                return redirect()->back()->withInput()->with(
                    'error',
                    'لا يمكن تعديل أمر تصنيع بعد استلام المواد. أرجع المواد أولاً ثم عدّل.'
                );
            }

            // (2) الفترة المحاسبية — كما في finishOrder و completeOrder
            if ($block = $this->accountingPeriodBlock($order->created_at ?? now())) {
                DB::rollBack();

                return redirect()->back()->withInput()->with('error', $block);
            }

            $order->update([
```

---

<a id="p3-10"></a>

## P3-10 — `finishOrder` لا يحفظ `warehouse_permit_id`: التراجع لا يجد ما يعكسه

**الملف:** `ManufacturingOrdersController.php:1336-1343` · 🟠 عالٍ · ✅ مؤكد · **يخدم:** S6 · 🟢 **جاهز للصق**

### المشكلة

`finishOrder` ينشئ إذناً ولا يحفظ معرّفه في الأمر، بينما `completeOrder` (2792) يحفظه:

```php
$warehousePermit = $this->createWarehousePermitForOrder($order, $request);   // 1336
// تحديث حالة الأمر مع الإذن الجديد        ← التعليق يقول ذلك
$order->update([                                                              // 1339
    'status' => 'completed',
    'completed_at' => now(),
    'updated_by' => auth()->id(),
]);                                                                            // ولا أثر لـ warehouse_permit_id
```

ثم `undoCompletion` يبحث احتياطياً بـ:
```php
$warehousePermit = WarehousePermits::where('reference_id', $order->id)   // 1522
    ->where('permission_source_id', 15)
    ->where('permission_type', 1)
```
لكن `createWarehousePermitForOrder` ينشئ الإذن بـ `permission_source_id = 3` (سطر 1676)، و`permission_type` غير مُمرَّر (⇒ NULL)، و`reference_id` غير مُمرَّر (⇒ NULL).

**سلسلة الفشل:** أمرٌ أُنهي عبر `finishOrder` ⇒ البحث الاحتياطي يفشل ⇒ `undoCompletion` **يُثبّت بنجاح ويعيد الحالة دون خصم المنتج المُنتَج من المخزون** ⇒ مخزون وهمي دائم.

### البديل — احذف المسار كله

`finishOrder` **غير مستدعاة من أي واجهة** (انظر [P7-08](#p7-08)). المسار الحيّ الوحيد هو `completeOrder`. الإصلاح الصحيح:

1. احذف `finishOrder` و`finishOrderWithPermitCleanup` و`finishOrderWithSelectivePermitDeletion` و`finish` و`deleteOldWarehousePermit`.
2. احذف مساريها في `routes/web.php:155-160`.
3. أبقِ `completeOrder` وحدها.

**إن أردت الإبقاء عليها مؤقتاً**، فأضف على الأقل:
```php
$order->update([
    'status' => ManufacturOrderStatus::Completed,
    'completed_at' => now(),
    'warehouse_permit_id' => $warehousePermit->id,   // كان مفقوداً
    'updated_by' => auth()->id(),
]);
```
ووحّد `permission_source_id` و`permission_type` و`reference_id` مع `completeOrder`.

### جدول المقارنة — لماذا وجودهما معاً خطر

| | `finishOrder` (1291) | `completeOrder` (2682) |
|---|---|---|
| المسار | `POST orders/finish/{id}` | `POST orders/{id}/complete` |
| **مستخدم في الواجهة؟** | **لا** | **نعم** |
| قيود محاسبية | **لا شيء** | 5 قيود |
| `permission_source_id` | **3** | **15** |
| `permission_type` | **NULL** | 1 |
| `reference_id` | **NULL** | `$order->id` |
| يحفظ `warehouse_permit_id`؟ | **لا** | نعم |
| فحص الفترة المحاسبية | `delivery_date` | `now()` |

---

<a id="p3-11"></a>

## P3-11 — `grand_total` للإذن ≠ مجموع بنوده

**الملف:** `ManufacturingOrdersController.php:1666-1725` · 🟠 عالٍ · ✅ مؤكد · **يخدم:** S3 · 🟢 **جاهز للصق**

### المشكلة

```php
// حساب المجموع الإجمالي للإذن (المنتج الرئيسي + المواد الهالكة)
$grandTotal = $this->calculateOrderTotalCost($order);
$wasteMaterialsTotal = $this->calculateWasteMaterialsTotal($order);
$totalAmount = $grandTotal + $wasteMaterialsTotal;
...
'details' => 'إذن استلام من أمر التصنيع: ... (يشمل المنتج الرئيسي والمواد الهالكة)',
'grand_total' => $totalAmount,
```

الإذن يُنشأ بإجمالي **يشمل** الهالك، ونصّه يعلن ذلك — لكن الدالة تضيف بنداً واحداً فقط (المنتج الرئيسي، 1692-1699) وتُرجع (1725). ودالة إضافة بنود الهالك `addWasteMaterialsToSamePermit` (1729) **لا تُستدعى إطلاقاً**.

⇒ **فرق بقيمة الهالك كاملة بين رأس الإذن وبنوده.** أي تقرير يوازن الرأس بالبنود سيُظهر فرقاً.

### البديل

**القاعدة العامة:** لا تكتب `grand_total` مسبقاً. اكتبه بعد إضافة كل البنود:
```php
        // بعد حلقة إضافة كل البنود
        $permit->update([
            'grand_total' => WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->sum('total'),
        ]);
```
هذا يجعل الثابت **S3** يصمد بالبناء لا بالانضباط. طبّقه في **كل** موضع يُنشئ إذناً:
`createWarehousePermitForOrder` (1661)، `receiveMaterials` (2143)، `returnMaterials` (2360)، `completeOrder` (2727)، و`BOMController::createWarehousePermit` (483) و`finish`.

---

<a id="p3-12"></a>

## P3-12 — ثلاث آليات حساب تكلفة متضاربة تقرأ أعمدة غير موجودة

**الملف:** `ManufacturingOrdersController.php:829, 1170, 1203, 1234` · 🔴 حرج (كمصيدة) · ✅ مؤكد · 🟢 **جاهز للصق (حذف)**

### المشكلة

```php
// calculateCostFromProductDetails (1170):
$orderItems = $order->manufacturOrdersItem()->get();
    ProductDetails::where('product_id', $item->product_id)      // ← $item->product_id
    $totalCost += ($item->quantity * $unitPrice);                // ← $item->quantity
```

مقابل ما يكتبه `store()` فعلياً (سطور 316-323، 610-617):
```php
'raw_product_id' => ..., 'raw_quantity' => ..., 'raw_unit_price' => ...
```

**لا يُكتب في `manufactur_orders_items` أي عمود `product_id` أو `quantity` أو `unit_price` في أي مكان من الملف.**

| الدالة | النتيجة الفعلية |
|---|---|
| `calculateCostFromProductDetails` (1170) | `$totalCost` = **0 دائماً** |
| `calculateCostFromPurchaseHistory` (1203) | `whereNotNull('product_id')` ⇒ **`SQLSTATE 42S22`** أو 0 صفوف |
| `getCostBredown` (829) | نفس المشكلة + `$order->labor_cost` و`$order->additional_costs` لا يُكتبان في أي مكان |
| `calculateCostAlternativeMethod` (1234) | تسلسل احتياطي على دالتين تُرجعان صفراً، وينتهي بـ `$estimatedCost = (... ?? 0) * $order->quantity * 0.8;` ← **معامل 0.8 سحري بلا مصدر** |

الحساب المُستخدَم فعلاً في الإنتاج (`createManufacturingJournalEntries`) يستعمل الأعمدة الصحيحة. أي أن **الآليات الأربع غير متسقة، وثلاث منها معطوبة وميتة**.

### البديل — احذفها

```
 829  getCostBredown                    (وخطأ إملائي: Bredown)
1170  calculateCostFromProductDetails
1203  calculateCostFromPurchaseHistory
1234  calculateCostAlternativeMethod
```

**وجودها مصيدة** لأي مطوّر يستدعيها لاحقاً ظاناً أنها تعمل. المصدر الوحيد للتكلفة بعد [P2-05](#p2-05) هو مجموع البنود.

> 🟡 **تحقق من ناتج السكربت (القسم 10)** — يطبع لك إن كان `product_id`/`quantity` موجوداً في `manufactur_orders_items` أم لا.

---

## معيار قبول المرحلة 3

دورة كاملة على بيئة اختبار — **إنشاء → استلام → إنهاء → تراجع → استلام → إنهاء → إغلاق** — وبعدها:

- ✅ كل الثوابت A1–A7 وS1–S7 وM1–M6 تصمد
- ✅ **حساب WIP يساوي صفراً للأمر**
- ✅ المخزون يعود بالضبط عند التراجع
- ✅ إلغاء أمر بعد استلام المواد يُنشئ إذن إرجاع ويعيد المخزون
- ✅ إرجاع 999999 وحدة يُرفض برسالة واضحة
- ✅ إرجاع جزئي لا يسمح باستلام ثانٍ

---

<a id="8"></a>

# 8. المرحلة 4 — قوائم المواد BOM (4 أيام)

| اليوم | العملية | العيوب |
|---|---|---|
| 1 | `store` — المفاتيح الأجنبية والإجماليات | [P4-01](#p4-01) |
| 2 | `update` — الخصم المزدوج وعكس الأرصدة | [P4-02](#p4-02) |
| 3 | `finish` — التوازن والتفكيك | [P4-05](#p4-05) |
| 4 | `destroy` + `getData` + المتفرقات | [P4-03](#p4-03), [P4-06](#p4-06)…[P4-09](#p4-09) |

---

<a id="p4-01"></a>
<a id="p4-04"></a>

## P4-01 — `whereNotNull` بلا أثر: `store()` يكتب `0` لا `null`

**الملف:** `BOMController.php:147-174` (و`update` في 265-296) · 🟠 عالٍ · ✅ مؤكد · **يخدم:** M3 · 🟢 **جاهز للصق**

### المشكلة

**الكود الحالي:**
```php
                        'raw_production_stage_id' => $request->raw_production_stage_id[$index] ?? 0,
                        ...
                        'expenses_account_id' => $request->expenses_account_id[$index] ?? 0,
                        ...
                        'workstation_id' => $request->workstation_id[$index] ?? 0,
                        ...
                        'end_life_product_id' => $request->end_life_product_id[$index] ?? 0,
```

**مقابل ما يقرأه `finish()` و`createWarehousePermit()`:**
```php
$rawItems         = $bom->ProductionMaterialsItem()->whereNotNull('raw_product_id')->get();
$expenseItems     = $bom->ProductionMaterialsItem()->whereNotNull('expenses_account_id')->get();
$workstationItems = $bom->ProductionMaterialsItem()->whereNotNull('workstation_id')->get();
$endLifeItems     = $bom->ProductionMaterialsItem()->whereNotNull('end_life_product_id')->get();
```

بما أن كل حقل غير معبّأ يُكتب `0` لا `NULL`، فإن `whereNotNull` **يطابق كل سطور القائمة** في المجموعات الأربع.

**النتائج:**
- المجموعات الأربع متطابقة تماماً، والتصفية بلا أثر.
- في `createWarehousePermit` تُنشأ `WarehousePermitsProducts` بـ `product_id = 0` لكل سطر مصروفات، ويُحاول تحديث `ProductDetails` لمنتج رقمه 0 ⇒ صفوف مخزون وهمية أو خرق مفتاح أجنبي.
- في `finish` حلقة `endLifeItems` (سطر 630) **بلا أي حارس** ⇒ تكتب كل السطور بمنتج 0 وكمية 0.

### البديل

```php
                        // المفاتيح الأجنبية تُكتب NULL لا 0، وإلا صار
                        // `whereNotNull('expenses_account_id')` في finish()
                        // يطابق كل السطور، فتُنشأ بنود إذن بمنتج رقمه 0.
                        'raw_production_stage_id' => $request->raw_production_stage_id[$index] ?: null,
                        ...
                        'expenses_account_id' => $request->expenses_account_id[$index] ?: null,
                        ...
                        'workstation_id' => $request->workstation_id[$index] ?: null,
                        ...
                        'end_life_product_id' => $request->end_life_product_id[$index] ?: null,
```

**والحقول العددية تبقى `?? 0`** (`raw_quantity`, `raw_unit_price`, `expenses_price`, `operating_time`, ...).

### وأضف حارساً في حلقة `endLifeItems` (سطر 630)

هي الحلقة الوحيدة بلا حارس — بينما `expenseItems` (726) و`workstationItems` (749) محميتان:
```php
            foreach ($endLifeItems as $endItem) {
                if (! $endItem->end_life_product_id || $endItem->end_life_quantity <= 0) {
                    continue;
                }
                // ... باقي الكود
```

### ⚠️ البيانات القائمة تحتاج تنظيفاً

هجرة التنظيف في [P2-02](#p2-02). **وتأكد أن الأعمدة `nullable`** في الهجرة الأصلية.

---

<a id="p4-02"></a>

## P4-02 — `update()` يخصم المخزون مرتين ولا يعكس أرصدة الحسابات

**الملف:** `BOMController.php:309-336` · 🔴 حرج · ✅ مؤكد · **يخدم:** A2, S6 · 🟡 **يحتاج تأكيداً**

### المشكلة

**الكود الحالي:**
```php
            // تحديث القيود (حذف القديم وإنشاء جديد)
            // تحديث القيود (حذف القديم وإنشاء جديد)
            // 1. حذف الأذونات القديمة المرتبطة بهذا الـ BOM
            $oldPermits = WarehousePermits::where('reference_id', $id)
                ->where('permission_source_id', 17) // BOM
                ->get();

            foreach ($oldPermits as $p) {
                // حذف القيود المرتبطة بالإذن (STOCK_ADJUSTMENT)
                JournalEntry::where('reference_id', $p->id)
                    ->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->delete();

                // حذف منتجات الإذن
                WarehousePermitsProducts::where('warehouse_permits_id', $p->id)->delete();

                $p->delete();
            }

            // تنظيف أي قيود قديمة للنظام السابق (Safety check)
            JournalEntry::where('type_id', $id)->where('type', 'production_material_issue')->delete();
            JournalEntry::where('type_id', $id)->where('type', 'production_material')->delete();

            // إنشاء الجديد
            $permit = $this->createWarehousePermit($productionMaterial);
            if ($permit) {
                $this->createIssuanceJournalEntry($permit, $productionMaterial);
            }
```

**(أ) المخزون يُخصم مرتين.** الإذن القديم يُحذف، لكن **لا يُعاد للمخزون ما خصمه**، ثم يُنشأ إذن جديد يخصم مرة أخرى. **عشر تعديلات لقائمة مواد = عشرة أضعاف الخصم.**

**(ب) أرصدة الحسابات لا تُعكس.** `createIssuanceJournalEntry` يعدّل `balance` مباشرة (سطر 464-474)، وحذف `JournalEntry` لا يُرجعه:
```php
$storeAccount->balance -= $permit->grand_total;
$storeAccount->save();
Account::updateBalancesHierarchically($storeAccount->id);
```
⇒ رصيد المخزن ورصيد WIP ينحرفان **دائماً** بمقدار قيمة القائمة عند كل تعديل ⇒ **ميزان المراجعة يفقد اتساقه** (الثابت A2).

**(ج)** ❓ `JournalEntry::delete()` على الاستعلام لا يشغّل أحداث الموديل ولا يحذف `JournalEntryDetail` ⇒ سطور قيود يتيمة (ما لم يوجد `ON DELETE CASCADE`).

### البديل

```php
            // 1. عكس أثر الأذونات القديمة قبل حذفها — لا حذفٌ صامت
            $oldPermits = WarehousePermits::where('reference_id', $id)
                ->where('permission_source_id', 17) // BOM
                ->get();

            foreach ($oldPermits as $p) {
                // (أ) إعادة الكميات المخصومة إلى المخزون
                $oldLines = WarehousePermitsProducts::where('warehouse_permits_id', $p->id)->get();

                foreach ($oldLines as $line) {
                    if (! $line->product_id || $line->quantity <= 0) {
                        continue;
                    }

                    $row = ProductDetails::where('product_id', $line->product_id)
                        ->where('store_house_id', $line->store_house_id ?? $p->store_houses_id)
                        ->lockForUpdate()
                        ->first();

                    if ($row) {
                        // إذن صرف (permission_type = 2) ⇒ العكس إضافة
                        $row->quantity += ($p->permission_type == 2 ? $line->quantity : -$line->quantity);
                        $row->save();
                    }
                }

                // (ب) عكس أثر القيد على أرصدة الحسابات قبل حذفه
                $oldEntries = JournalEntry::where('reference_id', $p->id)
                    ->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->with('details')
                    ->get();

                foreach ($oldEntries as $entry) {
                    foreach ($entry->details as $d) {
                        $acc = Account::find($d->account_id);
                        if (! $acc) {
                            continue;
                        }
                        // عكس ما فعله createIssuanceJournalEntry
                        $acc->balance -= ((float) $d->debit - (float) $d->credit);
                        $acc->save();
                        Account::updateBalancesHierarchically($acc->id);
                    }

                    $entry->details()->delete();   // لا تعتمد على CASCADE
                    $entry->delete();
                }

                WarehousePermitsProducts::where('warehouse_permits_id', $p->id)->delete();
                $p->delete();
            }

            // 2. إنشاء الجديد
            $permit = $this->createWarehousePermit($productionMaterial);
            if ($permit) {
                $this->createIssuanceJournalEntry($permit, $productionMaterial);
            }
```

### ملاحظات

**ما حُذف:** السطران `JournalEntry::where('type_id', ...)->where('type', ...)` — **لا يطابقان شيئاً أصلاً**، لأن `createIssuanceJournalEntry` لا يكتب `type` ولا `type_id` إطلاقاً (يكتب `reference_type` و`reference_id`). كانا وهماً يطمئن قارئ الكود.

> 🟡 **تحقق من اتجاه الرصيد.** الباتش يفترض أن `createIssuanceJournalEntry` يزيد الرصيد بالمدين وينقصه بالدائن (`balance += debit - credit`). راجع سطر 464-474 في ملفك وتأكد أن العكس يطابق الأصل بالضبط. **هذا الجزء لا يُطبَّق بلا مراجعة محاسب.**

> ✅ **لا استيرادات ناقصة هنا:** `Account` و`ReferenceType` و`ProductDetails` و`WarehousePermitsProducts` كلها مستوردة بالفعل في `BOMController`.

> 🟡 **`$line->store_house_id` قد يكون فارغاً.** `BOMController::createWarehousePermit` يكتبه (سطر 529)، لكن `WarehousePermitsProducts::create` في `ManufacturingOrdersController` (2182، 2390) **لا يكتبه**. لذلك الاحتياط `?? $p->store_houses_id` ضروري لا تجميلي.

> **الأنظف بعد المرحلة 2:** استبدل كتلة العكس كلها بـ `InventoryService::reverse($p->id)` و`ManufacturingJournalService::reverse(...)`.

---

<a id="p4-03"></a>

## P4-03 — `destroy()`: معايير حذف لا تطابق ما كُتب أصلاً

**الملف:** `BOMController.php:382-400` · 🔴 حرج · ✅ مؤكد · **يخدم:** A4, S6 · 🟡 **يحتاج تأكيداً**

### المشكلة

```php
    public function destroy($id)
    {
        $productionMaterial = ProductionMaterials::findOrFail($id);

        // حذف القيود المحاسبية
        JournalEntry::where('type_id', $productionMaterial->id)
            ->where('type', 'production_material')
            ->delete();

        // حذف الاذونات المخزنية
        WarehousePermits::where('reference_id', $productionMaterial->id)
            ->where('reference_type', ProductionMaterials::class)
            ->delete();

        $productionMaterial->ProductionMaterialsItem()->delete();
        $productionMaterial->delete();

        return redirect()->route('BOM.index')->with(['error' => 'تم حذف البيانات بنجاح.']);
    }
```

1. **القيود لا تُحذف أبداً.** `createIssuanceJournalEntry` يكتب `reference_type = STOCK_ADJUSTMENT` و`reference_id = $permit->id`، ولا يكتب `type` ولا `type_id` ⇒ **صفر تطابق**، قيود يتيمة تبقى في الدفتر.
2. **الأذونات لا تُحذف أبداً.** `createWarehousePermit` يكتب `'reference_type' => 'normal'`، والحذف يبحث عن `App\Models\ProductionMaterials` ⇒ **صفر تطابق**. ولاحظ التناقض: `update()` يبحث بـ `permission_source_id = 17` وينجح، و`destroy()` يبحث بمعيار آخر ويفشل — **معياران مختلفان في نفس الملف لنفس السجل**.
3. **رسالة النجاح تحت مفتاح `error`** ⇒ الواجهة تعرض حذفاً ناجحاً على أنه خطأ أحمر.
4. **لا معاملة** رغم مساسه بأربعة جداول، ولا `try/catch`، ولا فحص فترة محاسبية.
5. **لا عكس للمخزون**، ولا فحص هل تستعملها أوامر تصنيع قائمة.

### البديل

```php
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $bom = ProductionMaterials::findOrFail($id);

            // (1) لا تُحذف قائمة يستعملها أمر تصنيع قائم
            $usedByOrders = ManufacturOrders::where('production_material_id', $bom->id)->exists();
            if ($usedByOrders) {
                DB::rollBack();

                return redirect()->route('BOM.index')->with([
                    'error' => 'لا يمكن حذف هذه القائمة — تستعملها أوامر تصنيع قائمة.',
                ]);
            }

            // (2) الفترة المحاسبية
            if ($block = $this->accountingPeriodBlock($bom->created_at ?? now())) {
                DB::rollBack();

                return redirect()->route('BOM.index')->with(['error' => $block]);
            }

            // (3) الأذونات بالمعيار الصحيح — permission_source_id لا reference_type
            $permits = WarehousePermits::where('reference_id', $bom->id)
                ->where('permission_source_id', 17)
                ->get();

            foreach ($permits as $permit) {
                // عكس الأثر المخزني قبل الحذف
                $lines = WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->get();

                foreach ($lines as $line) {
                    if (! $line->product_id || $line->quantity <= 0) {
                        continue;
                    }

                    $row = ProductDetails::where('product_id', $line->product_id)
                        ->where('store_house_id', $line->store_house_id ?? $permit->store_houses_id)
                        ->lockForUpdate()
                        ->first();

                    if ($row) {
                        $row->quantity += ($permit->permission_type == 2 ? $line->quantity : -$line->quantity);
                        $row->save();
                    }
                }

                // القيود بالمفتاح الصحيح
                $entries = JournalEntry::where('reference_id', $permit->id)
                    ->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->with('details')
                    ->get();

                foreach ($entries as $entry) {
                    foreach ($entry->details as $d) {
                        $acc = Account::find($d->account_id);
                        if ($acc) {
                            $acc->balance -= ((float) $d->debit - (float) $d->credit);
                            $acc->save();
                            Account::updateBalancesHierarchically($acc->id);
                        }
                    }
                    $entry->details()->delete();
                    $entry->delete();
                }

                WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->delete();
                $permit->delete();
            }

            $bom->ProductionMaterialsItem()->delete();
            $bom->delete();

            OperationLog::record('deleted', 'production_material', $id, 'تم حذف قائمة المواد **'.$bom->name.'**');

            DB::commit();

            return redirect()->route('BOM.index')->with(['success' => 'تم حذف البيانات بنجاح.']);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, 'production_material', 'destroy');

            return redirect()->route('BOM.index')->with(['error' => $this->safeError($e, 'تعذّر حذف قائمة المواد.')]);
        }
    }
```

### ملاحظات

> ✅ **اسم العمود مؤكَّد:** `production_material_id` هو الصحيح — يكتبه `ManufacturingOrdersController` (سطر 303، 595) و`BOMController` (143، 262).

> ⚠️ **استيراد ناقص:** `BOMController` **لا يستورد** `ManufacturOrders`. أضف:
> ```php
> use App\Models\ManufacturOrders;
> ```

---

<a id="p4-05"></a>

## P4-05 — `finish()`: قيد قد لا يتوازن + 248 سطراً + فحص تكرار ضعيف

**الملف:** `BOMController.php:550-797` · 🔴 حرج · ✅ مؤكد · **يخدم:** A1 · 🔵 **يحتاج تصميماً**

### المشكلة (أ) — القيد قد لا يتوازن

```php
if ($bom->last_total_cost > 0)  → debit  = $bom->last_total_cost      // المنتج التام
if ($endLifeTotal > 0)          → debit  = $endLifeTotal              // الهالك
if ($rawTotal > 0)              → credit = $rawTotal                  // WIP
foreach ($expenseItems ...)     → credit = $exp->expenses_total
foreach ($workstationItems ...) → credit = $work->manu_total
```

مجموع المدين = `last_total_cost + endLifeTotal`، ومجموع الدائن = `rawTotal + Σexpenses + Σworkstations`. **لا شيء في الكود يضمن تساويهما**، و`last_total_cost` يأتي من الطلب مباشرة.

**الحل:** بعد [P2-05](#p2-05) يصير `last_total_cost` مشتقاً من البنود، ثم:
```php
            $this->assertBalanced($totalDebit, $totalCredit, 'إنهاء تصنيع BOM #'.$bom->id);
            DB::commit();
```
حيث تُراكم `$totalDebit` و`$totalCredit` مع كل `JournalEntryDetail::create`. أو الأفضل: مرّر السطور كلها إلى `ManufacturingJournalService::post()`.

### المشكلة (ب) — فحص التكرار ضعيف

```php
    $exists = WarehousePermits::where('reference_id', $bom->id)
        ->where('permission_type', 1)
        ->exists();
```
`reference_id` غير مقيّد بنوع المرجع ⇒ **أي إذن إضافة من مصدر آخر يحمل نفس الرقم يمنع إنهاء التصنيع خطأً**. أضف:
```php
        ->where('permission_source_id', SystemAccountIds::PERMISSION_SOURCE_COMPILED_PRODUCT_ADD)
```

### المشكلة (ج) — اتجاه تعديل الرصيد مشكوك فيه

```php
// BOMController.php:738-740
$acc->balance -= $exp->expenses_total; // (Depends on nature, usually expense is Debit, so Credit reduces it? OR Capitalizing means Credit Expense)
// Or if Liability, Credit increases it.
// Assuming standard capitalization logic.

// BOMController.php:766
$acc->balance -= $work->manu_total; // Or += if Liability
```

الاتجاه يُطبَّق بالطرح دائماً بصرف النظر عن طبيعة الحساب (أصل/التزام/مصروف). حساب التزام (أجور مستحقة) يجب أن يزيد بالدائن لا أن ينقص.

> ⚠️ **التعليقات نفسها دليل على أن المنطق كُتب على غير تأكد.** هذه ليست مسألة مطوّر — **اعرضها على محاسب** قبل تعديل أي سطر.

### المشكلة (د) — 248 سطراً في دالة واحدة

`finish()` تقوم بـ: فحص الفترة، فحص التكرار، تجميع 4 مجموعات، إنشاء إذن، تحديث المخزون في حلقتين، إنشاء قيد، إنشاء 5 أنواع من سطور القيد، تحديث أرصدة 4 فئات حسابات، وتسجيل لوج.

**فكّكها إلى `BomFinishService`** بدوال مسمّاة — انظر [§14 — الخدمات](#14).

---

<a id="p4-06"></a>

## P4-06 — `StoreHouse::first()`: كل صرف يقع في أول مستودع في الجدول

**الملف:** `BOMController.php:485` و`582` · 🟠 عالٍ · ✅ مؤكد · 🟡 **يحتاج تأكيداً**

```php
        $defaultStoreHouse = StoreHouse::first();      // أول مستودع في الجدول كيفما كان
```

كل صرف واستلام يقع في **أول مستودع بترتيب الجدول**، بغض النظر عن فرع المستخدم أو اختياره. في نظام متعدد الفروع هذا خطأ وظيفي ومحاسبي.

**البديل:** المستودع من الطلب، أو من فرع المستخدم:
```php
        $defaultStoreHouse = $request->filled('store_house_id')
            ? StoreHouse::findOrFail($request->store_house_id)
            : StoreHouse::where('branch_id', auth()->user()->branch_id)->firstOrFail();
```
🟡 يتطلب إضافة حقل اختيار المستودع في نموذج BOM، وتطبيق [P6-01](#p6-01) أولاً.

**وأصلح معه:** `'stock_before' => 0, 'stock_after' => 0` (سطر 530-531، 617-618، 638-639) — ثابتة صفراً في كل السطور ⇒ سجل حركة المخزون بلا قيمة تدقيقية.

---

<a id="p4-07"></a>

## P4-07 — `default = 1` غير محصور بالمنتج ويُلغي سجلاً واحداً فقط

**الملف:** `BOMController.php:119-124` · 🟠 عالٍ · ✅ مؤكد · 🟢 **جاهز للصق**

### الكود الحالي

```php
        if ($request->default == 1) {
            $existingDefault = ProductionMaterials::where('default', 1)->first();   // ← first()
            if ($existingDefault) { $existingDefault->update(['default' => 0]); }
        }
```

- `first()` يلغي **سجلاً واحداً** فقط؛ لو كان هناك أكثر من افتراضي (وهو ممكن لأن لا قيد فريد) تبقى البقية.
- **غير محصور بـ `product_id`**: تعيين قائمة مواد افتراضية للمنتج «أ» **يلغي الافتراضية عن المنتج «ب»**.
- وغير محصور بالفرع.

### البديل

```php
        if ($request->default == 1) {
            ProductionMaterials::where('product_id', $request->product_id)
                ->where('default', 1)
                ->update(['default' => 0]);
        }
```

**وأضف قيداً فريداً** في هجرة: `unique(['product_id', 'default'])` مشروطاً بـ `default = 1` — أو تحقق على مستوى التطبيق إن كان المحرك لا يدعم الفهارس الجزئية.

---

<a id="p4-08"></a>

## P4-08 — `getData()`: عمود «المنتج» فارغ دائماً، وفلتر بلا معنى

**الملف:** `BOMController.php:40, 47, 68-70, 76` · 🟡 متوسط · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة (أ) — عمود المنتج فارغ

```php
        $query = ProductionMaterials::query();     // ← بلا with('product')
        $materials = $query->paginate(10);         // ← 10 مثبتة
        return response()->json(['data' => $materials->items(), ...]);
```
والواجهة تعرض (`bom/index.blade.php:159`):
```js
+ '<td>' + escapeHtml(material.product ? material.product.name : '—') + '</td>'
```

`items()` يُسلسل الموديلات بلا علاقات محمّلة ⇒ **عمود «المنتج» يعرض «—» في كل الصفوف**.

**الإصلاح:** `ProductionMaterials::with('product:id,name')` + `$this->safePerPage($request, 10)`.

### المشكلة (ب) — فلتر «عملية الإنتاج» منطق دائري

```php
$operations = ProductionMaterials::select('id', 'name')->get();   // «عمليات الإنتاج» = قوائم المواد نفسها!
...
if ($request->filled('operation_id')) {
    $query->where('operation_id', $request->operation_id);        // عمود لا يُكتب في store() ولا update()
}
```

القائمة المنسدلة تُملأ من نفس جدول قوائم المواد، والفلترة على عمود `operation_id` **لا يُكتب في أي مكان**. إن لم يكن العمود موجوداً في المخطط فالفلتر يرفع `Unknown column` → 500.

**الإصلاح:** احذف الفلتر، أو اربطه بجدول `production_paths` الصحيح.

---

<a id="p4-09"></a>

## P4-09 — تناقضات صغيرة في BOM

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

| # | العيب | الملف:السطر | الإصلاح |
|---|---|---|---|
| 1 | `edit()` بلا `findOrFail` بينما `show`/`update`/`finish` تستعمله | `BOMController:207` | `find` → `findOrFail` |
| 2 | `create()` يعرض **كل** المنتجات كمواد خام، و`edit()` يفلتر `material_type='raw'` ⇒ **اختيار المستخدم الأصلي يختفي عند التعديل** | 96-98 مقابل 209-211 | وحّد الفلتر |
| 3 | `Product::all()` **مرتين** في نفس الطلب | 96-97 | استعلام واحد |
| 4 | `show()` يحمّل خمسة جداول كاملة (`Account::all()` — شجرة الحسابات كلها) | 355-359 | `select()` + `with()` أو AJAX |
| 5 | `update()` — `updateOrCreate` بمفتاح `id` من الطلب: **لا حذف للسطور المُزالة** | 260-264 | احذف كل السطور وأعِد الإنشاء داخل المعاملة (كما يفعل `IndirectCostsController@update:291`) |
| 6 | `get_cost_total($id)` — دالة بلا مسار، ومنطقياً تخصّ `WorkstationsController` | 402 | انقلها أو احذفها |
| 7 | أرقام سحرية: `17`، `permission_type = 2`، `'normal'`، `paginate(10)` — بينما نفس الملف يستعمل `SystemAccountIds::PERMISSION_SOURCE_COMPILED_PRODUCT_ADD` في السطر 599 | متفرقة | ثوابت |

---

## معيار قبول المرحلة 4

- ✅ تعديل قائمة مواد عشر مرات ← المخزون يُخصم **مرة واحدة** لا عشراً
- ✅ أرصدة الحسابات لا تنحرف بعد التعديل (الثابت A2)
- ✅ حذف قائمة تستعملها أوامر يُرفض برسالة واضحة
- ✅ عمود «المنتج» في القائمة يعرض الاسم لا «—»
- ✅ صفر سطور بمفاتيح أجنبية = 0

---

<a id="9"></a>

# 9. المرحلة 5 — محطات العمل والتكاليف غير المباشرة (3 أيام)

| اليوم | العمل | البنود |
|---|---|---|
| 1 | `WorkstationsController`: `findOrFail`، الحذف بفحص المراجع، `total_cost` في الخادم، `SafeListing` | [P5-01](#p5-01), [P5-02](#p5-02), [P5-03](#p5-03), [P5-05](#p5-05) |
| 2 | **قرار الإهلاك التلقائي** — تُفعَّل الكتلة المعطّلة (127 سطراً) أم يُخفى الحقل؟ | [P5-04](#p5-04) |
| 3 | `IndirectCostsController`: `clone` قبل `paginate`، الحذف بمعاملة وقيد عكسي، `total` في الخادم | [P5-06](#p5-06), [P5-07](#p5-07), [P5-08](#p5-08) |

---

<a id="p5-01"></a>

## P5-01 — `WorkstationsController`: `find()` بلا فحص null

**الملف:** `WorkstationsController.php:270, 327, 423` · 🔴 حرج · ✅ مؤكد · 🟢 **جاهز للصق**

```php
        $workstation = WorkStations::find($id);
        $oldAutomaticDepreciation = $workstation->automatic_depreciation;   // ← Fatal لو null
```

`find()` بلا `findOrFail` ثم قراءة خاصية مباشرة → `Error: Attempt to read property on null` = 500.

**والأثر أسوأ في `update()`** لأن الاستثناء يقع **بعد `DB::beginTransaction()`** (سطر 325)؛ يلتقطه الـ catch العام (402) فيُرجع «حدث خطأ أثناء تعديل محطة العمل» — **رسالة مضلّلة لمعرّف غير موجود بدل 404**.

**الإصلاح:** `find` → `findOrFail` في المواضع الثلاثة.

---

<a id="p5-02"></a>

## P5-02 — `destroy()`: حذف صلب بلا فحص مراجع ولا معاملة

**الملف:** `WorkstationsController.php:70-95` · 🟠 عالٍ · ✅ مؤكد · 🟡 **يحتاج تأكيداً**

```php
        $workstation = WorkStations::findOrFail($id);
        $workstation->delete();
```

- **لا فحص إن كانت المحطة مستعملة** في `ProductionMaterialsItem.workstation_id` أو `ManufacturOrdersItem.workstation_id`. حذفها يترك قوائم مواد وأوامر تشير إلى محطة معدومة، **وتنكسر `WorkStations::find($work->workstation_id)` في `BOMController@finish:750` ⇒ `$ws->account_wages` على null ⇒ استثناء أثناء إنهاء التصنيع.**
- **لا حذف لـ `WorkStationsCost` التابعة** (بينما `update()` يحذفها صراحةً في السطر 349) ⇒ سطور تكلفة يتيمة.
- **لا `DB::transaction`** رغم المساس بجدولين.
- ❓ هل `WorkStations` يستعمل `SoftDeletes`؟ إن لا، فالحذف صلب ولا سبيل لاستعادة بيانات التكلفة التاريخية التي تعتمد عليها قيود سابقة.

**البديل:** نفس نمط [P4-03](#p4-03) — معاملة + فحص مراجع + حذف التوابع + رسالة واضحة عند الرفض.

---

<a id="p5-03"></a>

## P5-03 — `total_cost` يُقبل من العميل ولا يُحتسب

**الملف:** `WorkstationsController.php:205-208` · 🔴 حرج · ✅ مؤكد · **يخدم:** A7 · 🟢 **جاهز للصق**

```php
            'total_cost'  => $request->input('total_cost'),
            'cost_wages'  => $request->input('cost_wages'),
            'cost_origin' => $request->input('cost_origin'),
```

`total_cost` يُفترض أنه `cost_wages + cost_origin + Σ cost_expenses`، لكنه يُقبل كما أرسله المتصفح. **وهذه القيمة تُقرأ في `BOMController@create:103` وتغذّي تكلفة التشغيل في قوائم المواد ثم القيود المحاسبية.**

⇒ رقم تكلفة يتحكم به المستخدم يسري إلى دفتر اليومية.

**البديل:** بعد حفظ سطور التكلفة:
```php
            $workstation->update([
                'total_cost' => round(
                    (float) $workstation->cost_wages
                    + (float) $workstation->cost_origin
                    + (float) $workstation->costs()->sum('amount'),
                    2
                ),
            ]);
```
🟡 اسم العلاقة وعمود المبلغ من ناتج السكربت.

---

<a id="p5-04"></a>

## P5-04 — الإهلاك التلقائي: حقل يُعرض كأنه يعمل وهو معطّل

**الملف:** `WorkstationsController.php:109-192, 226-233, 277-319, 358-386` · 🟠 عالٍ · ✅ مؤكد · 🔵 **يحتاج قراراً**

### المشكلة

- كتلة `createAutomaticDepreciationEntries` معطّلة بتعليق — **84 سطراً** (109-192)
- كتلة `deleteExistingDepreciationEntries` معطّلة — **43 سطراً** (277-319)
- الاستدعاءان معطّلان أيضاً (226-233، 382-386)
- ونتيجةً لذلك هذا المنطق في `update()` (361-379) **بلا أثر إطلاقاً**:

```php
        $shouldCreateDepreciation = false;
        $depreciationMessage = '';
        if ($workstation->automatic_depreciation == 1 && $workstation->origin && $workstation->cost_origin > 0) {
            if ($oldAutomaticDepreciation == 0) {
                $shouldCreateDepreciation = true;
                $depreciationMessage = 'تم تفعيل الإهلاك التلقائي وإنشاء قيد جديد.';
            } elseif (...) { ... }
        }
```
`$shouldCreateDepreciation` و`$depreciationMessage` يُحسبان **ولا يُقرآن أبداً**. ومعهما `$oldAutomaticDepreciation`/`$oldOrigin`/`$oldCostOrigin`.

- واستيرادات غير مستعملة نتيجةً لذلك: `LinkingType`، `ReferenceType`، والـ traits `ResolvesAccountingAccounts` و`UsesDocumentCurrency`.

### ⚠️ الأهم وظيفياً

**الحقل `automatic_depreciation` يُحفظ ويُعرض للمستخدم كخيار فعّال (سطر 210، 344) بينما لا ينتج عنه أي قيد.** المستخدم يظن أن الإهلاك التلقائي يعمل وهو لا يعمل.

### القرار المطلوب

| الخيار | الجهد | التوصية |
|---|---|---|
| **تفعيل الإهلاك** — إحياء الكتلتين وربطهما بجدولة شهرية | 4 أيام | إن كان الإهلاك مطلوباً محاسبياً |
| **إخفاء الحقل** من الواجهة وحذف الكود الميت | ساعة | **أوصي بهذا الآن** — ثم أعِد التفعيل كمشروع مستقل |

**ما لا يجوز:** تركه كما هو.

---

<a id="p5-05"></a>

## P5-05 — عيوب متفرقة في `WorkstationsController`

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

| # | العيب | السطر | الإصلاح |
|---|---|---|---|
| 1 | `'links' => $workstations->links()->render()` — تصيير Blade داخل JSON، **والحقل لا يُستعمل** (الواجهة تبني ترقيمها بنفسها)، وروابطه تشير لمسار AJAX لا لصفحة الفهرس | 65 | احذف السطر |
| 2 | `WorkStations::select()` بلا وسائط — عديم الأثر ومربك | 34 | احذفه أو حدّد الأعمدة |
| 3 | `auth()->user()->id` بدل `auth()->id()` — يرفع خطأ على null إن انتهت الجلسة | 211، 345 | `auth()->id()` |
| 4 | `Log::error(..., ['request_data' => $request->all()])` — يكتب كامل حمولة الطلب في اللوج | 256-260، 407-412 | احذف `request_data` |
| 5 | `sort_by`/`per_page` بلا قائمة بيضاء | 47-53 | [P2-06](#p2-06) |

---

<a id="p5-06"></a>

## P5-06 — `IndirectCosts`: `sum`/`avg` بعد `paginate()` على نفس الـ builder

**الملف:** `IndirectCostsController.php:102-121` · 🟠 عالٍ · ✅ مؤكد · 🟢 **جاهز للصق**

### المشكلة

```php
        $perPage = $request->get('per_page', 10);
        $indirectCosts = $query->paginate($perPage);
        ...
            'summary' => [
                'total_records' => $indirectCosts->total(),
                'total_amount' => $query->sum('total'),
                'avg_amount' => $query->avg('total'),
            ],
```

`paginate()` يُعدّل الـ builder نفسه بـ `forPage()` أي يضيف `LIMIT/OFFSET`. الاستعلامان التاليان يرثانه:
- في الصفحة 1 (`OFFSET 0`) النتيجة صحيحة **صدفةً**.
- **من الصفحة 2 فما فوق، صف التجميع يُتجاوَز بالـ OFFSET فيعود فارغاً ⇒ `total_amount` و`avg_amount` صفران** بينما البيانات موجودة.

### البديل

```php
        // `paginate()` يضيف LIMIT/OFFSET إلى الـ builder نفسه، فكان
        // sum/avg يرثانه ويعودان صفراً من الصفحة الثانية فصاعداً.
        $summaryQuery = (clone $query);

        $perPage = $this->safePerPage($request, 10);
        $indirectCosts = $query->paginate($perPage);

        $summary = $summaryQuery
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(total), 0) AS s, COALESCE(AVG(total), 0) AS a')
            ->first();
        ...
            'summary' => [
                'total_records' => (int) $summary->c,
                'total_amount' => (float) $summary->s,
                'avg_amount' => (float) $summary->a,
            ],
```

**فائدة إضافية:** استعلام تجميعي واحد بدل اثنين.

---

<a id="p5-07"></a>

## P5-07 — `IndirectCosts@destroy()`: بلا معاملة ولا عكس محاسبي

**الملف:** `IndirectCostsController.php:345-354` · 🟠 عالٍ · ✅ مؤكد · 🟡 **يحتاج تأكيداً**

```php
        $indirectCost = IndirectCost::findOrFail($id);
        $indirectCost->indirectCostItems()->delete();
        $indirectCost->delete();
        OperationLog::record('deleted', 'indirect_cost', $id, ...);
```

- **جدولان بلا `DB::transaction`** — فشل بين العمليتين يترك أباً بلا أبناء أو العكس.
- سطور `IndirectCostItem` مرتبطة بـ `restriction_id` (قيود يومية) و`manufacturing_order_id` — حذفها يفكّ توزيع التكلفة على الأوامر **دون أي قيد عكسي**، فتبقى التكلفة محمّلة عليها في التقارير.
- **لا فحص `accountingPeriodBlock`** (بينما `BOMController` يفحصه) ⇒ حذف داخل فترة مقفلة.
- `OperationLog::record` بعد الحذف بمعرّف صار معدوماً؛ و`show()` يستعمل `whereHas('indirect_cost')` (سطر 334) ⇒ **سجل الحذف لن يظهر أبداً**.

**البديل:** نفس نمط [P4-03](#p4-03).

---

<a id="p5-08"></a>

## P5-08 — عيوب متفرقة في `IndirectCosts`

**🟡 متوسط · ✅ مؤكد**

| # | العيب | السطر | الإصلاح |
|---|---|---|---|
| 1 | `'total' => $request->total` بلا مطابقة مع مجموع السطور — يمكن حفظ إجمالي 1,000,000 وسطور مجموعها 10 | 219، 288 | [P2-05](#p2-05) |
| 2 | `sort_field`/`per_page` بلا قائمة بيضاء | 97-102 | [P2-06](#p2-06) |
| 3 | ثلاثة جداول كاملة في كل فتح للصفحة (`Account`, `Product`, `ManufacturOrders`) — الأخير ينمو بلا حد | 27-29 | بحث AJAX (Select2 remote) |
| 4 | `$restriction->date->format('Y-m-d')` يفترض cast لـ Carbon | 203 | ❓ تحقق من `$casts` |
| 5 | `\Str` بأسلوب الاسم المستعار العام | 204 | `use Illuminate\Support\Str;` |

> **نقطة إيجابية:** `getRestrictionsByDate` (183-188) هو **الموضع الوحيد في الكنترولرات الخمسة** الذي يستعمل `$request->validate()` صراحةً.

---

## معيار قبول المرحلة 5

- ✅ حذف محطة عمل مستعملة يُرفض برسالة واضحة
- ✅ إحصائيات التكاليف صحيحة في الصفحة الثانية وما بعدها
- ✅ `total_cost` للمحطة يساوي مجموع مكوّناته
- ✅ حقل الإهلاك التلقائي إمّا يعمل وإمّا مخفيّ — لا حالة ثالثة

---

<a id="10"></a>

# 10. المرحلة 6 — الأمان والفروع (4 أيام)

> ⚠️ **قدّم هذه المرحلة إلى الأسبوع الثاني** إن كان النظام يعمل على بيانات حقيقية بمستخدمين متعددي الفروع اليوم.

| اليوم | العمل | البنود |
|---|---|---|
| 1 | `BelongsToBranch` + هجرات `branch_id` + **ملء العمود للبيانات القائمة** | [P6-01](#p6-01) خطوات 1-3 |
| 2 | Policies + `authorize()` في كل دالة كتابة | [P6-01](#p6-01) خطوات 4-5 |
| 3 | الملاحظات: الربط بالأمر، `getNotes`، `deleteNote` | [P6-02](#p6-02) |
| 4 | XSS + تسريب الاستثناءات + الترقيم + الـAPI | [P6-04](#p6-04)…[P6-08](#p6-08) |

---

<a id="p6-01"></a>

## P6-01 — غياب كامل لعزل الفروع: IDOR منهجي

**🔴 حرج · ✅ مؤكد · 🔵 يحتاج تصميماً**

### المشكلة

بحثتُ عن `branch_id` في **كامل الموديول** (كل ملفات PHP): **صفر نتيجة**. ولا وجود لأي `authorize()` أو `Gate::` أو `->can()` في أي كنترولر.

كل الدوال تعمل بالمعرّف وحده:
```
BOMController:207/243/351/384/559        ProductionMaterials::find/findOrFail($id)
WorkstationsController:73/270/327/423    WorkStations::find/findOrFail($id)
IndirectCostsController:259/281/347      IndirectCost::findOrFail($id)
ManufacturingOrdersController            13 دالة: edit, show, update, finish, finishOrder,
                                         undoCompletion, closeOrder, reopenOrder,
                                         receiveMaterials, returnMaterials, completeOrder,
                                         cancelOrder, addNote
```
وقوائم `getData` لا تقيّد الاستعلام بفرع.

⚠️ الوسيط `check.branch` (web.php:39) اسمه يوحي بالتحقق من *وجود* فرع مختار لا من *ملكية السجل*.

**إن لم يوجد Global Scope:** مستخدم الفرع «أ» يستطيع فتح وتعديل و**حذف** و**إنهاء** سجلات الفرع «ب» بتغيير `{id}` في العنوان.

### الخطوة صفر — تحقق أولاً

ناتج السكربت (القسم 8) يجيب. أو يدوياً:
```bash
grep -rn "class CheckBranch" app/Http/Middleware/
grep -rn "BelongsToBranch\|addGlobalScope" app/Models/ManufacturOrders.php app/Models/ProductionMaterials.php
```
إن وُجد Global Scope فالمشكلة أخفّ مما وصفتُ.

### الحل — خمس خطوات بترتيب إلزامي

#### (1) Trait

`app/Models/Concerns/BelongsToBranch.php`:
```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        // كل استعلام يُقيَّد بفرع المستخدم تلقائياً
        static::addGlobalScope('branch', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $branchId = auth()->user()->branch_id ?? null;

            if ($branchId && ! auth()->user()->hasRole('super-admin')) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
            }
        });

        // وكل إنشاء يحمل فرع المستخدم
        static::creating(function ($model) {
            if (empty($model->branch_id) && auth()->check()) {
                $model->branch_id = auth()->user()->branch_id ?? null;
            }
        });
    }
}
```

#### (2) هجرة العمود

```php
Schema::table('manufactur_orders', function (Blueprint $table) {
    $table->unsignedBigInteger('branch_id')->nullable()->after('id')->index();
});
```
وكرّرها لـ `production_materials` و`work_stations` و`indirect_costs`.

#### (3) ملء العمود للبيانات القائمة

```php
DB::table('manufactur_orders')->whereNull('branch_id')->update(['branch_id' => 1]);
```

#### (4) تفعيل الـ Trait على الموديلات الأربعة

```php
use App\Models\Concerns\BelongsToBranch;

class ManufacturOrders extends Model
{
    use BelongsToBranch;
```

#### (5) Policy لعمليات الكتابة

`app/Policies/ManufacturOrderPolicy.php`:
```php
public function update(User $user, ManufacturOrders $order): bool
{
    return $user->branch_id === $order->branch_id
        && $user->can('stock.manufacturing_orders.edit');
}
```
واستدعِها في كل دالة كتابة:
```php
$order = ManufacturOrders::findOrFail($id);
$this->authorize('update', $order);
```

### ⚠️⚠️ أخطر خطوة في الخطة كلها

**الـ Global Scope يُخفي البيانات فوراً.** لو طُبِّق قبل ملء `branch_id` **اختفت كل البيانات عن كل المستخدمين في لحظة**.

**الترتيب إلزامي:** هجرة العمود ← ملء القيم ← تفعيل الـ Scope ← اختبار على بيئة اختبار ← نشر.

---

<a id="p6-02"></a>

## P6-02 — الملاحظات: تسريب بين كل الأوامر + حذف أي ملاحظة في النظام

**الملف:** `ManufacturingOrdersController.php:1035-1052, 1092-1098, 1131-1153` · 🔴 حرج · ✅ مؤكد · 🟡 **يحتاج تأكيداً**

### (أ) الملاحظة لا تُربط بالأمر أصلاً

**الكود الحالي (سطر 1035-1044):**
```php
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                // 'quotation_id' => $id,
                'employee_id' => auth()->user()->id,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'manufacturing_order', // نوع مختلف للفواتير
            ]);
```

**السطر الذي يربط الملاحظة بالأمر معلَّق.**

البديل:
```php
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                'reference_id' => $order->id,          // الربط الذي كان معلّقاً
                'employee_id' => auth()->id(),
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'manufacturing_order',
            ]);
```

> 🟡 **اسم العمود:** كان المعلَّق `quotation_id` (اسم من سياق عروض الأسعار). **الأصحّ إضافة `reference_id` بهجرة** ([P2-02](#p2-02)) لأن `ClientRelation` جدول مشترك. إن أردت أقصر طريق اليوم، فعّل `'quotation_id' => $id` — لكنه اسم مضلّل سيؤذي من يقرأ الكود بعدك.

**وأصلح الإشعار (سطر 1047-1052)** — `$order->user_id` عمود لا يُكتب في أي مكان (الموديل يُنشأ بـ `created_by`):
```php
            notifications::create([
                'user_id' => $order->created_by,
                'receiver_id' => $order->created_by,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم إضافة ملاحظة جديدة لأمر التصنيع رقم '.$order->code,
            ]);
```

### (ب) `getNotes` يتجاهل معامله

**الكود الحالي (سطر 1092-1098):**
```php
    public function getNotes($id)
    {
        try {
            $notes = ClientRelation::where('type', 'manufacturing_order')
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->get();
```

المعامل `$id` **مُستقبَل ومُهمَل**. النتيجة: **كل أمر تصنيع يعرض ملاحظات كل أوامر التصنيع في النظام كلّه، عبر كل الفروع.** وبلا `paginate` أيضاً.

البديل:
```php
    public function getNotes($id = null)
    {
        try {
            // كان `$id` مُستقبَلاً ومُهمَلاً، فكان كل أمر يعرض ملاحظات
            // كل أوامر التصنيع في النظام — عبر كل الفروع.
            $notes = ClientRelation::where('type', 'manufacturing_order')
                ->when($id, function ($q) use ($id) {
                    $order = ManufacturOrders::findOrFail($id);
                    $q->where('reference_id', $order->id);
                })
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->paginate(50);
```
وعدّل `$formattedNotes = $notes->map(...)` لتعمل على `$notes->getCollection()`.

> ⚠️ **`$id = null` مقصود.** المسار `routes/web.php:185` (`/get-general-notes`) يوجّه إلى **نفس الدالة بلا معرّف**. لو جعلتَ `$id` إلزامياً لكسرتَ ذلك المسار.
>
> **الأنظف** — افصل الدالتين:
> ```php
> Route::get('/{id}/get-notes', [ManufacturingOrdersController::class, 'getNotes'])
>     ->name('manufacturing.orders.getNotes');
> Route::get('/get-general-notes', [ManufacturingOrdersController::class, 'getGeneralNotes'])
>     ->name('manufacturing.orders.getGeneralNotes');
> ```
> لكن راجع أولاً: `getGeneralNotes` و`addGeneralNote` و`deleteGeneralNote` **لا تستدعيها أي واجهة**. الأرجح أن تُحذف الثلاثة ومساراتها.

### (ج) `deleteNote` بلا أي تحقق

**الكود الحالي (سطر 1131-1153):**
```php
    public function deleteNote($noteId)
    {
        try {
            $note = ClientRelation::findOrFail($noteId);

            // حذف المرفق إذا كان موجود
            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }

            $order = $note;
            $order->type = 'manufacturing_order';
            $process = $note->process;

            $note->delete();

            // تسجيل النشاط
            OperationLog::record(
                'logged',
                'manufacturing_order',
                $order->id,
                sprintf('تم حذف ملاحظة "%s" من امر التصنيع رقم %s', $process, $order->code),
            );
```

`ClientRelation` جدول مشترك بين أنواع مستندات متعددة. لا فلترة على `type`، ولا تحقق من الفرع، ولا من الملكية ⇒ **أي مستخدم يملك `permission:stock.manufacturing_orders.notes` يستطيع حذف أي ملاحظة في النظام بأي `id`** — بما فيها ملاحظات عروض الأسعار وفواتير الشراء.

**وعبثٌ منطقي:** `$order = $note;` ثم `$order->code` — و`ClientRelation` لا يملك عمود `code` ⇒ **كل سجلات الحذف تُكتب برقم أمر فارغ**، و`OperationLog::record` يمرّر **معرّف الملاحظة** في خانة معرّف الأمر.

البديل:
```php
    public function deleteNote($noteId)
    {
        try {
            // كان `findOrFail` بلا أي قيد: أي مستخدم يملك صلاحية الملاحظات
            // كان يحذف أي سجل في `client_relations` — بما فيه ملاحظات
            // عروض الأسعار وفواتير الشراء.
            $note = ClientRelation::where('id', $noteId)
                ->where('type', 'manufacturing_order')
                ->firstOrFail();

            $order = ManufacturOrders::findOrFail($note->reference_id);
            $this->authorize('update', $order);   // بعد تطبيق P6-01

            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }

            $process = $note->process;
            $note->delete();

            OperationLog::record(
                'logged',
                'manufacturing_order',
                $order->id,                      // معرّف الأمر، لا معرّف الملاحظة
                sprintf('تم حذف ملاحظة "%s" من أمر التصنيع رقم %s', $process, $order->code),
            );
```

---

<a id="p6-04"></a>

## P6-04 — XSS مخزَّن عبر `{!! Str::markdown() !!}`

**5 ملفات · 🟠 خطير · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

```blade
{!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
```

| الملف | السطر |
|---|---|
| `orders/partials/activities-tab.blade.php` | 33 |
| `indirectCosts/show.blade.php` | 174 |
| `workstations/show.blade.php` | 252 |
| `settings/Paths/show.blade.php` | 136 |
| `bom/show.blade.php` | 357 |

`Str::markdown()` في Laravel يمرّر CommonMark بإعداد `html_input => 'allow'` و`allow_unsafe_links => true` افتراضياً ⇒ `<img src=x onerror=alert(1)>` داخل وصف السجل يخرج كما هو. والوصف مركَّب من مدخلات المستخدم (اسم الأمر مثلاً).

### البديل

```blade
{!! Str::markdown($log->description ?? 'لا يوجد وصف', [
    'html_input' => 'strip',
    'allow_unsafe_links' => false,
]) !!}
```

### الأنظف — توجيه واحد

في `ManufacturingServiceProvider::boot()`:
```php
        Blade::directive('safeMarkdown', function ($expression) {
            return "<?php echo \Illuminate\Support\Str::markdown($expression, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]); ?>";
        });
```
ثم في الملفات الخمسة:
```blade
@safeMarkdown($log->description ?? 'لا يوجد وصف')
```
**فلا يستطيع أحد أن ينسى المعاملات في الملف السادس.**

---

<a id="p6-05"></a>

## P6-05 — `esc()` لا تُهرِّب علامة الاقتباس: حقن سمات HTML

**12 موضعاً · 🟠 خطير · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

**الكود الحالي — متطابق حرفياً في 10 ملفات:**
```js
            const esc = value => $('<div>').text(value == null ? '' : value).html();
```
**وفي `bom/index.blade.php:142` باسم `escapeHtml`.**

**وفي `orders/partials/notes-modal.blade.php:131` بصيغة أخرى:**
```js
    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }
```

`innerHTML` يُهرِّب `<` و`>` و`&` فقط — **لا يُهرِّب `"`**. وحين تُوضع النتيجة داخل سمة محاطة باقتباس مزدوج:

| الملف | السطر | المقتطف |
|---|---|---|
| `orders/index.blade.php` | 239 | `data-name="' + esc(order.name) + '"` |
| `bom/index.blade.php` | 180 | نفس النمط |
| `workstations/index.blade.php` | 144 | نفس النمط |
| `indirectCosts/index.blade.php` | 300 | `data-account="' + esc(account.name) + '"` |
| `indirectCosts/create.blade.php` / `edit.blade.php` | 228 / 292 | `data-description="' + esc(item.description) + '"` |

**الاستغلال:** اسم أمر تصنيع = `x" onmouseover="alert(document.cookie)` ⇒ يُنفَّذ عند تمرير الماوس على عنصر «حذف» في القائمة.

### البديل

```js
            // `innerHTML` يُهرِّب < و > و & فقط. القيمة تُوضع أحياناً داخل
            // سمة محاطة باقتباس مزدوج (data-name="...")، فاسمٌ فيه " يخرج
            // من السمة ويحقن onmouseover=. لذلك نُهرِّب الاقتباسين أيضاً.
            const esc = value => $('<div>').text(value == null ? '' : value).html()
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
```

### المواضع الاثنا عشر

```
resources/views/orders/index.blade.php:190
resources/views/orders/partials/form.blade.php:372
resources/views/workstations/index.blade.php:114
resources/views/workstations/create.blade.php:246
resources/views/workstations/edit.blade.php:274
resources/views/indirectCosts/index.blade.php:249
resources/views/indirectCosts/create.blade.php:218
resources/views/indirectCosts/edit.blade.php:282
resources/views/bom/create.blade.php:289
resources/views/bom/edit.blade.php:505
resources/views/bom/index.blade.php:142                    ← اسمها escapeHtml
resources/views/orders/partials/notes-modal.blade.php:131  ← صيغة function لا سهم
```

> هذا الباتش مكرر 12 مرة — وهو بالضبط سبب [P7-05](#p7-05) (ملف JS مشترك). أصلحه 12 مرة اليوم، ثم وحّده في المرحلة 7.

### وموضع واحد غير مُهرَّب أصلاً — `orders/index.blade.php:210`

```js
// الحالي:
                const [label, tone] = states[order.status] || [order.status || 'غير محدد', 'secondary'];
// البديل:
                const [label, tone] = states[order.status] || [esc(order.status || 'غير محدد'), 'secondary'];
```
(القيمة تُدرَج بلا تهريب في السطر 228 داخل `<span class="badge">`، بينما كل الخلايا الأخرى في نفس الدالة مُهرَّبة — تناقض داخلي.)

### وموضع ثالث — رابط مرفق بلا فحص المخطَّط

`orders/partials/notes-modal.blade.php:155`:
```js
? '<a href="' + escapeHtml(note.attachment_url) + '" target="_blank" rel="noopener" …
```
`escapeHtml` لا تمنع `javascript:`. أضف فحصاً للمخطَّط قبل الإدراج.

---

<a id="p6-06"></a>

## P6-06 — تسريب `$e->getMessage()` للمستخدم (20+ موضعاً)

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

```php
'message' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),      // MOC:127 وفي 12 موضعاً آخر
'error' => 'حدث خطأ ما: '.$e->getMessage(),                     // BOMController:201, 344, 795
'error' => $e->getMessage(),                                     // ProductionMaterialsApiController:85
```

نص الاستثناء الخام يشمل أجزاء استعلامات SQL وأسماء جداول وأعمدة ومسارات ملفات.

### البديل

استعمل `safeError()` من `SafeListing` ([P2-06](#p2-06)):
```php
'message' => $this->safeError($e, 'حدث خطأ في جلب البيانات.'),
```

### كيف تجدها

```bash
grep -rn "getMessage()" Http/Controllers/ | grep -v "app.debug" | grep -v "Log::"
```

> **النموذج الصحيح موجود عندك** — `receiveMaterials:2303`:
> ```php
>                 if (config('app.debug')) {
>                     $errorMessage .= ': '.$e->getMessage();
>                 }
> ```

---

<a id="p6-07"></a>

## P6-07 — `ProductionMaterialsApiController`: تسريب أسعار الشراء

**الملف:** `ProductionMaterialsApiController.php:19-49, 82-87` · 🟠 خطير · ⚠️ مرجح · 🟡 **يحتاج تأكيداً**

### المشكلة (أ) — تسريب هيكل التكلفة

```php
                ->with(['rawProduct' => function ($query) {
                    $query->select('id', 'name', 'sale_price', 'purchase_price', 'sub_unit_id');
                }, 'rawProduct.subUnit'])
```

تُعاد `purchase_price` (سعر التكلفة) لأي مستدعٍ، بلا تحقق من الفرع ولا من صلاحية. أي مستخدم مصادَق يستطيع تعداد المعرّفات (1..N) واستخراج **هيكل تكلفة كل منتج في النظام** — أسعار الشراء وكميات التركيب. من أحسّ بيانات نظام ERP تجاري.

> **ملاحظة:** لا مسار مسجّل لهذه الدالة داخل الموديول، فحالياً لا وصول إليها. ❓ تحقق من مسارات التطبيق الرئيسية.

### المشكلة (ب) — دلالة `$id` ملتبسة

```php
    public function getItems($id)
    {
        $productionMaterial = ProductionMaterials::find($id);          // يجرّب كـ BOM id

        if (! $productionMaterial) {                                    // وإلا يجرّبه كـ product_id
            $productionMaterial = ProductionMaterials::where('product_id', $id)
                ->where('status', 1)
                ->first();
        }
```

لو كان `5` معرّف قائمة مواد للمنتج «أ»، وأراد المستدعي قائمة **المنتج رقم 5** (منتج «ب»)، لعادت له قائمة المنتج «أ» — **بيانات خاطئة صامتة، لا خطأ ولا تحذير**. وهذا وارد جداً لأن الجدولين يبدآن الترقيم من 1.

كذلك المسار الثاني يفلتر بـ `status = 1` والأول لا يفلتر، و`first()` يختار عشوائياً **بتجاهل حقل `default` الموجود لهذا الغرض بالذات**.

**الإصلاح:** نقطتا وصول منفصلتان:
```php
Route::get('/bom/{bom}/items', ...);
Route::get('/products/{product}/default-bom-items', ...);   // تفلتر default=1
```

### المشكلة (ج) — `??` لا يلتقط الصفر

```php
        $unitPrice = $item->raw_unit_price ?? $item->rawProduct->purchase_price ?? $item->rawProduct->sale_price ?? 0;
```

`??` يعمل على `null` فقط. وبما أن `BOMController@store` يكتب `?? 0` — أي **صفر لا null** — فالسعر المُعاد يكون `0` ولا يُستبدل بسعر شراء المنتج أبداً. **عيب متسلسل من [P4-01](#p4-01)**، ويُحلّ تلقائياً بعده. أو استعمل `?:` صراحةً.

---

<a id="p6-08"></a>

## P6-08 — توليد أرقام المستندات

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

| الموضع | الكود | المشكلة |
|---|---|---|
| `MOC:1664` | `'MO-'.$order->code.'-'.date('Ymd').'-'.rand(1000, 9999)` | 9000 احتمال ⇒ تصادم متوقع (~50% بعد ~112 إذناً لنفس الأمر في نفس اليوم) |
| `MOC:2150` | `'MFG-'.$order->code.'-'.time()` | لا يمرّ بالعدّاد |
| `MOC:2368` | `'MFG-RTN-'.$order->code.'-'.time()` | لا يمرّ بالعدّاد |
| `MOC:168` | `previewDocumentNumber('manufacturing-orders')` | **معاينة بلا استهلاك** ⇒ مستخدمان يفتحان شاشة الإنشاء يريان نفس الرقم |
| `OrdersController:37` | `str_pad(DB::table('manufactur_orders')->count() + 1, 6, '0')` | سباق + رقم مكرر بعد أي حذف (الملف ميت — [P7-08](#p7-08)) |

**الإصلاح:** وحّدها كلها على `resolveDocumentNumber(null, $key)` — الدالة الموجودة في `GeneratesDocumentNumbers` والمستعملة صحيحةً في `BOMController:504, 589`.

**و`code` الأمر:** يُقبل من الطلب (`'code' => $request->code` سطر 295) بلا تحقق من التفرّد هنا. ❓ تحقق من `ManufacturOrderRequest`، وأضف قيداً فريداً على مستوى قاعدة البيانات على أي حال.

---

## معيار قبول المرحلة 6

- ✅ مستخدم الفرع «أ» يحصل على **403** عند طلب سجل الفرع «ب» — لا 200 ولا 404
- ✅ ملاحظات الأمر رقم 5 تعرض ملاحظاته وحدها
- ✅ اسم أمر فيه `"` أو `<script>` لا ينفّذ شيئاً في أي شاشة
- ✅ رسالة خطأ في الإنتاج لا تحوي اسم جدول ولا استعلاماً

---

<a id="11"></a>

# 11. المرحلة 7 — الأداء والواجهات والتقارير (6 أيام)

> **تنبيه جدولة:** كانت هذه المرحلة «التقارير واللوحة» بثلاثة أيام، ثم ابتلعت كل عمل الأداء والواجهات. البنود الأحد عشر تحتاج ستة أيام على الأقل، و**P7-10 (i18n) وحده أربعة أيام** — افصله إلى مشروع مستقل إن ضاق الوقت، فهو الوحيد الذي لا يمسّ سلامة البيانات.

| اليوم | البنود |
|---|---|
| 1 | [P7-01](#p7-01) توحيد `status` — اللوحة والتقارير |
| 2 | [P7-02](#p7-02) + [P7-03](#p7-03) الاستعلامات وN+1 |
| 3 | [P7-09](#p7-09) التقارير الرقابية الثلاثة |
| 4 | [P7-05](#p7-05) + [P7-06](#p7-06) ملف JS مشترك والمكتبات |
| 5 | [P7-04](#p7-04) توحيد النماذج و`@error` |
| 6 | [P7-07](#p7-07) + [P7-08](#p7-08) + [P7-11](#p7-11) الحراسة والتنظيف |
| — | [P7-10](#p7-10) i18n — **مشروع مستقل، 4 أيام** |

---

<a id="p7-01"></a>

## P7-01 — تناقض نوع `status`: اللوحة والتقارير تعرض بيانات خاطئة

🟠 خطير · ✅ مؤكد · **يخدم:** M1 · 🟡 **يحتاج تأكيداً**

### المشكلة — ثلاث جهات، نوعان

**الجهة الأولى — سلاسل نصّية** (`orders/index.blade.php:87-94`، `partials/status.blade.php`، `action-buttons.blade.php`، `finish-order-modal.blade.php`):
```php
'pending', 'materials_received', 'in_progress', 'completed', 'closed', 'cancelled'
```

**الجهة الثانية — أعداد صحيحة** (`dashboard.blade.php:19-24, 185`):
```blade
$orderStates = [
    0 => ['مسوّدة', 'secondary'], 1 => ['قيد التنفيذ', 'warning'],
    2 => ['مكتمل', 'success'],   3 => ['ملغى', 'danger'],
];
@php [$label, $tone] = $orderStates[(int) $order->status] ?? [$order->status, 'secondary']; @endphp
```

`(int) 'pending'` = **0** ⇒ **كل أمر في «أحدث الأوامر» يُعرَض «مسوّدة»** مهما كانت حالته.

و`ManufacturingDashboardController.php:52-57`:
```php
        return [
            'total' => $total,
            'draft' => (int) ($counts[0] ?? 0),
            'in_progress' => (int) ($counts[1] ?? 0),
            'done' => (int) ($counts[2] ?? 0),
            'cancelled' => (int) ($counts[3] ?? 0),
            'by_status' => $counts,
        ];
```
`$counts` مفاتيحه سلاسل ⇒ **مؤشّرا «قيد التنفيذ» و«مكتملة» صفران دائماً**، وكل أشرطة التقدّم في اللوحة صفر.

**الجهة الثالثة** — `ManufacturingReportsController::STATUSES` (0..3) + `reports/orders.blade.php` ⇒ يُعرض `pending` نيّئاً. ومُرشِّح الحالة يفعل `(int) $request->input('status')` ⇒ **اختيار «مسودّة» يُرجع كل السجلات غير الرقمية** في MySQL بالوضع غير الصارم.

### الحل — بعد [P2-01](#p2-01)

**(1) `ManufacturingDashboardController.php:52-57`** — البديل:
```php
        return [
            'total' => $total,
            // كانت المفاتيح أرقاماً (0..3) والعمود يخزّن سلاسل، فكانت
            // كل هذه القيم صفراً دائماً وأشرطة التقدّم في اللوحة فارغة.
            'draft' => (int) ($counts[ManufacturOrderStatus::Pending->value] ?? 0),
            'in_progress' => (int) ($counts[ManufacturOrderStatus::InProgress->value] ?? 0)
                           + (int) ($counts[ManufacturOrderStatus::MaterialsReceived->value] ?? 0),
            'done' => (int) ($counts[ManufacturOrderStatus::Completed->value] ?? 0)
                    + (int) ($counts[ManufacturOrderStatus::Closed->value] ?? 0),
            'cancelled' => (int) ($counts[ManufacturOrderStatus::Cancelled->value] ?? 0),
            'by_status' => $counts,
        ];
```

**(2) `dashboard.blade.php:19-24, 185`** — البديل:
```blade
@php
    $st = $order->status instanceof \App\Enums\ManufacturOrderStatus
        ? $order->status
        : \App\Enums\ManufacturOrderStatus::tryFrom((string) $order->status);
@endphp
<span class="badge badge-light-{{ $st?->tone() ?? 'secondary' }}">{{ $st?->label() ?? $order->status }}</span>
```

**(3) `ManufacturingReportsController`** — استبدل الخريطة الرقمية بـ `ManufacturOrderStatus::options()`، **واحذف `(int)` من السطر 250 وحده**:
```php
// الحالي
$f['status'] = $request->filled('status') ? (int) $request->input('status') : null;
// البديل
$f['status'] = $request->filled('status') ? (string) $request->input('status') : null;
```
> السطر 262 لا يحوي `(int)` — هو `->when($f['status'] !== null, fn ($q) => $q->where('o.status', $f['status']))` ويعمل صحيحاً بمجرد إصلاح 250.

**(4) `getFilterOptions` (سطر 140-145)** — الكود الحالي:
```php
                'statuses' => [
                    ['value' => 'active', 'label' => 'نشط'],
                    ['value' => 'in_progress', 'label' => 'قيد التنفيذ'],
                    ['value' => 'completed', 'label' => 'منتهي'],
                    ['value' => 'cancelled', 'label' => 'ملغى'],
                ],
```
البديل:
```php
                'statuses' => \App\Enums\ManufacturOrderStatus::options(),
```
**لاحظ:** `active` لا يكتبها الكنترولر في أي مكان، و`pending`/`materials_received`/`closed` غائبة عن القائمة ⇒ **خياران يُرجعان نتائج فارغة، وثلاث حالات حقيقية لا يمكن الفلترة عليها.**

---

<a id="p7-02"></a>

## P7-02 — استعلام Eloquent داخل قالب Blade + N+1 في القوالب

**🔴 حرج (أداء) · ✅ مؤكد · 🟢 جاهز للصق**

### (أ) استعلام كامل في `permits-tab.blade.php:14-20`

```blade
@php
    $permits = App\Models\WarehousePermits::where('permission_source_id', 15)
        ->where('reference_id', $order->id)
        ->where('permission_type', $type)
        ->where('created_at', '>=', $order->created_at)
        ->with(['items.product', 'storeHouse'])
        ->orderBy('created_at', 'desc')
        ->get();
@endphp
```

طبقة العرض تستعلم قاعدة البيانات مباشرة، بمعرّف سحري `15` مكتوب باليد، والقالب يُستدعى **مرتين** لكل عرض أمر.

### (ب) N+1 في `cost-section.blade.php:41-85`

خمس علاقات lazy لكل صفّ (`rawProduct`, `rawProductionStage`, `expensesAccount`, `workStation`, `endLifeProduct`)، والقالب يُستدعى **4 مرات** لكل أمر ⇒ **لأمرٍ بـ20 بنداً: ~40-80 استعلاماً إضافياً في صفحة واحدة**.

### (ج) `unit-name.blade.php:12-24` — أسوأ حالة

**حتى 6 استعلامات لكل صفّ**، ويُدرَج داخل `@foreach` في `receive-materials.blade.php:141` و`return-materials.blade.php:303`.

### الحل — باتش واحد يعالج الثلاثة

**`ManufacturingOrdersController@show` (سطر 706-720)** — الكود الحالي:
```php
    public function show($id)
    {
        $order = ManufacturOrders::findOrFail($id);
        $storehouse = StoreHouse::all();
        $logs = ModelsLog::where('type', 'manufacturing_order')
            ->where('type_id', $id)
            ->whereHas('manufacturing_order') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('manufacturing::orders.show', compact('order', 'logs', 'storehouse'));
    }
```

البديل:
```php
    public function show($id)
    {
        $order = ManufacturOrders::with([
            'manufacturOrdersItem.rawProduct',
            'manufacturOrdersItem.rawProductionStage',
            'manufacturOrdersItem.expensesAccount',
            'manufacturOrdersItem.workStation',
            'manufacturOrdersItem.endLifeProduct',
        ])->findOrFail($id);

        $storehouse = StoreHouse::all();

        // كانت الأذونات تُستعلَم داخل permits-tab.blade.php — استعلامان
        // في طبقة العرض بمعرّف سحري مكتوب باليد. استعلام واحد هنا،
        // مجموعاً بالنوع، يغني عنهما.
        $permitsByType = WarehousePermits::where('permission_source_id', SystemAccountIds::PERMISSION_SOURCE_MANUFACTURING_ORDER)
            ->where('reference_id', $order->id)
            ->where('created_at', '>=', $order->created_at)
            ->with(['items.product', 'storeHouse'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('permission_type');

        $logs = ModelsLog::where('type', 'manufacturing_order')
            ->where('type_id', $id)
            ->whereHas('manufacturing_order')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('manufacturing::orders.show', compact('order', 'logs', 'storehouse', 'permitsByType'));
    }
```

**وفي القالب:**
```blade
@php $permits = ($permitsByType[$type] ?? collect()); @endphp
```

**والثوابت** عُرِّفت في [P2-02](#p2-02) — استعملها هنا بدل `15`.

> 🟡 تحقق من أسماء العلاقات الخمس في موديل `ManufacturOrdersItem` — استخرجتُها من `cost-section.blade.php:45-79`.

---

<a id="p7-03"></a>

## P7-03 — N+1 في الكنترولرات: 2000 استعلام لكل فتح لشاشة الإنشاء

**الملف:** `MOC:170-183, 227` (ومكرر في 481-491 و1935-1945) · 🟠 عالٍ · ✅ مؤكد · 🟢 **جاهز للصق**

### الكود الحالي

```php
        // جلب المنتجات النهائية ونصف المصنعة للمنتج المراد تصنيعه
        $products = Product::all();
        // جلب المواد الخام مع وحداتها للجداول
        $raw_materials = Product::with([
            'productUnits' => function ($query) {
                $query->where('is_active', true)
                    ->with(['categoryUnit' => function ($q) {
                        $q->with('unit');
                    }]);
            },
            'category.categoryUnits' => function ($query) {
                $query->where('is_active', true)->with('unit');
            },
        ])->get();
```
ثم لاحقاً (سطر 227):
```php
            if ($product->productDetails && $product->productDetails->count() > 0) {
                $availableQuantity = $product->productDetails->sum('quantity');
```

`productDetails` **ليست ضمن `with()`** ⇒ استعلام lazy لكل منتج. **مع 2000 منتج = 2000 استعلام** لكل فتح.

**والكتلة مكررة حرفياً ثلاث مرات** (~195 سطراً) ⇒ ثلاثة أماكن للإصلاح.

### البديل

```php
        // كان هنا استعلامان كاملان على جدول المنتجات (Product::all ثم
        // Product::with) — ثم lazy-load لـ productDetails داخل map،
        // أي استعلام إضافي لكل منتج. الحصيلة ~2001 استعلاماً لـ2000 منتج.
        $raw_materials = Product::query()
            ->withSum('productDetails as available_quantity', 'quantity')
            ->with([
                'productUnits' => function ($query) {
                    $query->where('is_active', true)
                        ->with(['categoryUnit' => fn ($q) => $q->with('unit')]);
                },
                'category.categoryUnits' => fn ($query) => $query->where('is_active', true)->with('unit'),
            ])
            ->get();

        $products = $raw_materials;   // نفس المجموعة — لا استعلام ثانٍ
```
وفي الـ map (سطر 227-229):
```php
            $availableQuantity = (float) ($product->available_quantity ?? 0);
```

**الأثر:** 2000 منتج ⇒ من ~2001 استعلام إلى **استعلام واحد**.

**والأفضل** — استخرج الكتلة إلى دالة واحدة بدل تكرارها ثلاثاً:
```php
    private function rawMaterialsWithUnits()
    {
        // كانت هذه الكتلة (~65 سطراً) مكررة حرفياً ثلاث مرات:
        // create() و edit() و showReceiveMaterialsForm()
    }
```

> 🟡 تحقق من اسم العلاقة `productDetails` في موديل `Product`.

**وأصلح معه:** `'links' => $orders->links()->render()` (سطر 112) — تصيير Blade كامل داخل استجابة JSON، والترقيم مُرسَل بالفعل ككائن في السطور 104-111.

---

<a id="p7-04"></a>

## P7-04 — توحيد `create`/`edit` وإضافة `@error`

**🟡 متوسط · ✅ مؤكد · 🔵 يحتاج تصميماً**

### المشكلة — نسخ ولصق بين شاشتين

| المجلد | الحالي | بعد التوحيد |
|---|---|---|
| `bom/` | `create` 466 سطراً + `edit` 665 | `create` ~30 + `edit` ~30 + `partials/form` ~450 |
| `workstations/` | `create` 362 + `edit` 386 | `create` ~30 + `edit` ~30 + `partials/form` ~340 |
| `indirectCosts/` | `create` 386 + `edit` 440 | `create` ~30 + `edit` ~30 + `partials/form` ~380 |

**دليل قاطع على ضرر النسخ** — في `workstations/create.blade.php` يوجد هذا التعليق:
```js
/* كان النمط `^[a-zA-Z0-9]+$` يرفض الشرطة، والكود المقترَح من
   إعدادات الترقيم يحملها — فيُرفض النموذج ولا يُحفظ شيء. */
```
**والإصلاح لم يُنقل إلى `edit.blade.php`.** أي أن نفس العيب لا يزال حياً في شاشة التعديل.

**واختلافات سلوكية غير مقصودة** بين `indirectCosts/create` و`edit`:
```diff
-  if (! response.success) { Swal.fire({ icon:'error', title:'تعذّر جلب القيود' }); return; }
+  if (! response.success) return;                          // edit يبتلع الخطأ صامتاً
-  $('.js-restriction').prop('disabled', ! restrictions.length);
+  // edit لا يُعطّل القائمة إطلاقاً
```

**و`bom/edit.blade.php` يحوي نسختين من نفس المنطق داخل الملف الواحد** — `@switch` في Blade للصفوف المحفوظة (~230-425) و`builders` في JS للصفوف الجديدة (~500-660).

### الحل — النمط الصحيح موجود عندك بالفعل

`orders/create.blade.php` و`orders/edit.blade.php` كلاهما **32 سطراً فقط**، ويشتركان في `orders/partials/form.blade.php`:

```blade
{{-- workstations/create.blade.php --}}
@extends('manufacturing::master')

@section('content')
    @include('manufacturing::workstations.partials.form', [
        'workstation' => null,
        'action' => route('manufacturing.workstations.store'),
        'method' => 'POST',
        'title' => 'إضافة محطة عمل',
    ])
@endsection
```

### وأضف `@error` أثناء التوحيد

النمط الصحيح موجود في `settings/Paths/form.blade.php:32-37` — وهو **الحقلان الوحيدان من ~120** في الموديول:
```blade
<div class="col-md-6 mb-2">
    <label class="form-label" for="name">اسم محطة العمل <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name"
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $workstation->name ?? '') }}">
    @error('name')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
```

**لماذا يهم:** `old()` مستعمل جيداً (68 موضعاً) فالإدخال يُحفظ عند الرفض — لكن **المستخدم لا يعرف أي حقل رُفض** في نموذج فيه أربعة جداول ديناميكية.

---

<a id="p7-05"></a>

## P7-05 — ملف JS مشترك للموديول

**🟡 متوسط · ✅ مؤكد · 🔵 يحتاج تصميماً**

### المشكلة

**3,253 سطر JS مضمّن في Blade — 25% من الواجهات.**

| المكرَّر | عدد النسخ |
|---|---|
| `paginationHtml()` | **4** (~130 سطر مكرر) |
| `esc()` / `escapeHtml()` | **12** |
| `money()`, `date()`, `fixed()`, `render()`, `load()` | 4 لكلٍّ |
| محرّك «صفوف الجدول» | **3** |
| `renumber()` + `addRow` + `js-remove` | 2 |

**وقد تباعدت النسخ فعلاً:** `bom/index.blade.php:192` يفتقد حارس `! last`:
```js
if (last <= 1) return '';                  // bom/index
if (! last || last <= 1) return '';        // الثلاثة الأخرى
```
⇒ عند استجابة بلا `pagination` يصير `last = undefined` ⇒ ترقيم بأزرار `NaN`.

### الحل

`resources/views/partials/js-helpers.blade.php`:
```blade
{{--
    دوالٌ كانت منسوخة حرفياً في كل شاشة قائمة. أيّ إصلاح كان يلزمه
    أربعة تعديلات — وقد تباعدت النسخ فعلاً (نسخة bom/index تفتقد
    حارس `! last`).
--}}
@once
<script>
window.MFG = (function () {
    const esc = value => $('<div>').text(value == null ? '' : value).html()
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const money = value => Number(value || 0).toLocaleString(
        (window.AppCurrency && window.AppCurrency.locale) || 'en-US',
        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
    );

    const date = value => value ? String(value).substring(0, 10) : '—';

    function paginationHtml(pagination) {
        const last = pagination && pagination.last_page;
        if (! last || last <= 1) return '';

        const current = pagination.current_page || 1;
        let html = '<ul class="pagination pagination-sm mb-0">';

        for (let p = 1; p <= last; p++) {
            html += '<li class="page-item' + (p === current ? ' active' : '') + '">'
                  + '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
        }

        return html + '</ul>';
    }

    return { esc, money, date, paginationHtml };
})();
</script>
@endonce
```

وفي كل شاشة قائمة:
```blade
@include('manufacturing::partials.js-helpers')
<script>
    const { esc, money, date, paginationHtml } = window.MFG;
    // ... باقي كود الشاشة
</script>
```

**التوفير:** ~500 سطر مكرر، و**عيبٌ واحد يُصلح مرة واحدة** بدل 12.

---

<a id="p7-06"></a>

## P7-06 — المكتبات: ~2 ميغابايت غير مستعملة على كل صفحة

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

`master.blade.php` يحمّل **27 وسم `<script src>` و10 أوراق أنماط**. المستعمل فعلياً:

| المكتبة | مستعملة؟ |
|---|---|
| DataTables + Buttons + print + flash + html5 | ✗ — الاستعمال الوحيد `$('#fawtra').DataTable()` سطر 445، و**`#fawtra` غير موجود في أي ملف** |
| jszip + pdfmake + vfs_fonts (~1.5 ميغا) | ✗ — تابعان لأزرار DataTables الميتة |
| selectize | ✗ — `$('').selectize()` سطر 471 — **مُحدِّد فارغ** |
| TinyMCE 7 (**بمفتاح API مكشوف في المصدر**) | ✗ — صفر استعمال |
| Spectrum (منتقي ألوان) | ✗ — `settings/manual/index.blade.php:76` يعلّق صراحةً أنه استُبدل بـ`<input type="color">` |
| toastr, blueimp-file-upload, Google Maps | ✗ — صفر استعمال |
| Font Awesome | جزئياً — `master` و`test-calculator` فقط؛ بقية الموديول على `feather` |

**حذف سطرين ميتين (`#fawtra` و`$('')`) يُسقط 8 من الـ27 مكتبة.**

### مشاكل مرافقة

**(أ) SweetAlert2 يُحمَّل مرتين على كل صفحة.** `master:88, 430` من CDN، ثم **14 شاشة** تُعيد تحميله (11 من CDN، 3 من الأصول المحلية). و**صفر استعمال لـ`@once` في الموديول كله.**

> التعليق في `orders/partials/form.blade.php:13` يقول إن هذا أُصلح هناك: «Select2 وSweetAlert كانا يُحمَّلان من CDN فوق نسختَي المشروع». **عمّمه على الـ11 ملفاً الباقية.**

**(ب) تعارض إصدارات.** `master:83-84` تحمّل jQuery 3.6 + **Bootstrap 5.3.0-alpha1** (إصدار ما قبل إنتاج)، ثم السطر 418 يحمّل `vendors.min.js` التي تتضمن jQuery و**Bootstrap 4**.

**الشاهد على المرض** أن كل النوافذ والتبويبات تكتب السمتين معاً:
```blade
data-toggle="tab" data-bs-toggle="tab"
data-dismiss="modal" data-bs-dismiss="modal"
```

**التوحيد على الأصل المحلي:**
```blade
{{-- في master.blade.php وحده — واحذف الـ11 وسم CDN من الشاشات --}}
<script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
```

**و`@once` لكل سكربت مشترك:**
```blade
@once
    @push('scripts')
        <script src="{{ asset('...') }}"></script>
    @endpush
@endonce
```

**اختر واحداً:**
- إن كان الثيم Vuexy على BS4 ⇒ احذف سطر BS5 من `master:84` وأزل كل `data-bs-*`
- إن أردت BS5 ⇒ رقّ إلى `5.3.x` مستقر وأزل كل `data-toggle`/`data-dismiss` القديمة

**لا تُبقِ الاثنين.**

**(ج) بصمة SRI خاطئة.** `master:91` و`:439` تحملان **نفس `integrity` لملفَّي CSS وJS مختلفين** (مستحيل) ⇒ المتصفح يحجب أحدهما. تختفي المشكلة تلقائياً بحذف selectize.

---

<a id="p7-07"></a>

## P7-07 — حراسة المسارات الخارجية وتعارض الصلاحيات

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

### (أ) مسارات خارج الموديول بلا حارس

الموديول يستعمل `Route::has()` كحارس في مواضع ويتركه في أخرى — **لنفس نوع المسار**:

| محميّ ✓ | غير محميّ ✗ |
|---|---|
| `order-info.blade.php:295` — `@if (Route::has($route))` | `order-info.blade.php:21` — `route('products.show', ...)` |
| `action-buttons.blade.php:15` — `Route::has('...show-return-materials')` | `permits-tab.blade.php:64, 82` — `route('store_permits_management.show', ...)` |
| `modals.blade.php:17, 24, 31` | `action-buttons.blade.php:23` — `route('...show-receive-materials')` |
| `dashboard.blade.php:116`, `sidebar.blade.php:120` | `sidebar.blade.php:92, 113` — `route('dashboard')` · `master.blade.php:792` — `route('logout')` |

`products.show`, `store_permits_management.show`, `clients.show`, `dashboard`, `logout` كلها **خارج هذه الوحدة**. إن اختفى أيٌّ منها ⇒ `RouteNotFoundException` **وصفحة 500 كاملة، لا رابط مفقود**.

**الحل — مكوّن واحد:**
```blade
{{-- resources/views/components/safe-link.blade.php --}}
@if (Route::has($route))
    <a href="{{ route($route, $params) }}" {{ $attributes }}>{{ $slot }}</a>
@else
    <span {{ $attributes }}>{{ $slot }}</span>
@endif
```
```blade
<x-manufacturing::safe-link route="products.show" :params="$order->product->id">
    {{ $order->product->name }}
</x-manufacturing::safe-link>
```

> ناتج السكربت (القسم 6) يخبرك أيّ من هذه المسارات مفقود فعلاً اليوم.

### (ب) تعارض أسماء الصلاحيات

| المكان | الصلاحية |
|---|---|
| `routes/web.php:310, 313, 316, 319, 322` | `stock.settings.manufacturing` |
| `layouts/sidebar.blade.php:83` | `stock.manufacturing_settings.view` |
| `dashboard.blade.php:110` | `stock.manufacturing_settings.view` |

اسمان مختلفان لنفس الشيء ⇒ ⚠️ **بند «إعدادات التصنيع» قد لا يظهر في الشريط الجانبي ولا في اللوحة لأحدٍ أبداً**، رغم أن الصفحة تعمل عند كتابة عنوانها.

```sql
SELECT name FROM permissions WHERE name LIKE '%manufacturing%settings%' OR name LIKE '%settings%manufacturing%';
```
(ناتج السكربت، القسم 7، يجيب.)

### (ج) التحويل القديم يبني عنواناً غير موجود

`routes/web.php:376-381` — القائمة البيضاء تشمل `manufacturing`:
```php
->where('section', 'BOM|Workstations|IndirectCosts|Settings|manufacturing')
```
⇒ `stock/manufacturing/orders/create` → **`manufacturing/manufacturing/orders/create`** ⇒ 404. وهذا بالضبط ما يكسر رابط `test-calculator.blade.php:560`.

⚠️ والقائمة **لا تشمل** `orders` ولا `reports` ولا `test-calculator` ⇒ العناوين القديمة لها لا تُحوَّل.

---

<a id="p7-08"></a>

## P7-08 — الكود الميت

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

> ⚠️ **احذف بعد أن تنجح المرحلتان 3 و4 وتستقرّا في الإنتاج أسبوعاً — لا قبل.**

### (أ) ملفات كاملة

```bash
git rm Http/Controllers/OrdersController.php
git rm resources/views/components/layouts/master.blade.php
git rm resources/views/orders/partials/styles.blade.php
```

**`OrdersController.php` — 276 سطراً ميتة بالكامل. الأدلة القاطعة:**
1. **لا مسار واحد يشير إليه** — `routes/web.php` يستورد `ManufacturingOrdersController` فقط.
2. **أسماء العروض خاطئة أصلاً:** `view('manufacturing.orders.index')` بالنقطة بدل `manufacturing::orders.index`، وأحدها بحالة أحرف مختلفة: `view('Manufacturing.Orders.create')` ⇒ **الملف لم يعمل قط على نظام ملفات حسّاس لحالة الأحرف (Linux)**.
3. **نموذج بياناته يتعارض مع الكنترولر الحيّ:** يكتب **سطراً واحداً يجمع الفئات الأربع** (مادة خام + مصروف + تشغيل + هالك في نفس الصف)، بينما `ManufacturingOrdersController` يكتب **صفاً منفصلاً لكل فئة** ويعتمد `edit()` على هذا التقسيم. لو أُعيد تفعيله لتضاعفت التكاليف في العرض والقيود.
4. **آثار نسخ ولصق فاضحة:** عند فشل حفظ **أمر تصنيع** يُعاد المستخدم إلى شاشة **قوائم المواد** (`redirect()->route('BOM.index')`)، و`OperationLog::record('logged', 'bom', ...)` بنوع `'bom'` لأمر تصنيع.

**تحقق قبل الحذف:**
```bash
grep -rn "OrdersController" --include=*.php . | grep -v "ManufacturingOrdersController"
```

**`components/layouts/master.blade.php`** (29 سطراً) — بقية سقالة `nwidart`، لا `@extends` ولا `@include` ولا `view()` يشير إليه.

**`orders/partials/styles.blade.php`** (12 سطراً) — محتواه تعليق فقط، وتبريره («أُبقي الملف لأن `show` كان يستدعيه») لم يعد قائماً.

### (ب) دوال ميتة في `ManufacturingOrdersController`

```
 747  addProductDetailsForManufacturedProduct
 770  addWasteMaterialsToProductDetails
 798  removeProductDetailsForManufacturingOrder
 829  getCostBredown                          (وخطأ إملائي: Bredown)
1170  calculateCostFromProductDetails         ← معطوبة (P3-12)
1203  calculateCostFromPurchaseHistory        ← معطوبة
1234  calculateCostAlternativeMethod          ← معطوبة (+ معامل 0.8 سحري)
1265  deleteOldWarehousePermit
1291  finishOrder                             ← P3-10
1371  finishOrderWithPermitCleanup            ← public بلا مسار وبلا validate()
1435  finishOrderWithSelectivePermitDeletion  ← public بلا مسار وبلا validate()
1646  calculateProductTotal
1729  addWasteMaterialsToSamePermit
1762  recordInventoryMovements
1805  recordProductMovement                   ← جسمها تعليق
1869  reverseInventoryMovement
1880  deleteRelatedStockMovements             ← جسمها تعليق
 963  createJournalEntry                      ← كتلة معلّقة 49 سطراً
```

**⚠️ استثناءان لا يُحذفان:**
- **`getProductAvailableQuantity` (852)** — هي المنطق الصحيح الوحيد لحساب الرصيد. **أبقِها**، وقد تحتاجها في [P2-03](#p2-03).
- **`getProductStockBefore` (1889)** — لا تحذفها بل **اكتبها** (انظر [P3-01](#p3-01)).

### (ج) كتل معلّقة في `WorkstationsController`

انظر [P5-04](#p5-04) — **127 سطراً معطّلة**، والحقل يُعرض للمستخدم كأنه يعمل.

### (د) كود ميت في `master.blade.php`

```js
$('#fawtra').DataTable({ ... });     // السطر 445 — #fawtra غير موجود في أي ملف
$('').selectize({ sortField: 'text' });  // السطر 471 — مُحدِّد فارغ
```

### (هـ) صفحة يتيمة

**`test-calculator.blade.php`** (681 سطراً) — له مسار ومتحكّم لكن **لا رابط إليه من أي مكان** (ليس في الشريط الجانبي ولا اللوحة ولا الإعدادات). ويحمل:
- `@section('css')` **ميتة** — `master.blade.php` لا يحوي `@yield('css')` ⇒ 55 سطر CSS مهمَل
- **رابط مكسور** (`/ar/stock/manufacturing/orders/create` ⇒ 404 — انظر [P7-07](#p7-07)(ج))
- **عطل حذف مزدوج:** مُعالجان لنفس الحدث (vanilla سطر 589، jQuery سطر 671، وتعليقه يقول «تكرار للتأكد من العمل») ⇒ **تنبيه خطأ بعد كل حذف ناجح**
- 6 `console.log` و5 `alert()`

**مرشَّح للحذف أو النقل لبيئة التطوير.**

### (و) مسارات مُعرَّفة بلا استعمال في الواجهة

```
Bom.login_to, Bom.export, Bom.export_view, Bom.updatePassword    ← تُحذف في P1-03
manufacturing.orders.finish, manufacturing.orders.finish.process ← تُحذف في P3-10
manufacturing.orders.cancel
manufacturing.orders.addGeneralNote, getGeneralNotes, deleteGeneralNote
manufacturing.orders.filter-options
indirectcosts.getRestrictionsByDate (النسخة القديمة سطر 57)
stock.manufacturing.test-calculator
```

---

<a id="p7-09"></a>

## P7-09 — التقارير الرقابية الثلاثة (جديد)

**🟢 جاهز للصق · يخدم:** A1, A2, A3, S1, S3

**ليست ترفاً — هي ما يكشف عودة أي عيب بعد الإصلاح.** اجعلها صفحة واحدة يفتحها المحاسب أسبوعياً.

### (1) تقرير تسوية WIP

```sql
SELECT o.id, o.code, o.name,
       SUM(CASE WHEN d.account_id = :wip THEN d.debit  ELSE 0 END) AS wip_debit,
       SUM(CASE WHEN d.account_id = :wip THEN d.credit ELSE 0 END) AS wip_credit,
       SUM(CASE WHEN d.account_id = :wip THEN d.debit - d.credit ELSE 0 END) AS residue
FROM manufactur_orders o
JOIN journal_entries e ON e.reference_id = o.id AND e.reference_type = :ref
JOIN journal_entry_details d ON d.journal_entry_id = e.id
WHERE o.status IN ('completed', 'closed')
GROUP BY o.id, o.code, o.name
HAVING ABS(residue) > 0.01
ORDER BY ABS(residue) DESC;
```
**المطلوب: صفر صفوف** (الثابت A3).

### (2) تقرير القيود غير المتوازنة

```sql
SELECT e.id, e.description, SUM(d.debit) dr, SUM(d.credit) cr,
       ABS(SUM(d.debit) - SUM(d.credit)) diff
FROM journal_entries e
JOIN journal_entry_details d ON d.journal_entry_id = e.id
GROUP BY e.id, e.description
HAVING diff > 0.01
ORDER BY diff DESC;
```
**المطلوب: صفر صفوف** (الثابت A1).

### (3) تقرير المخزون السالب والإذونات غير المتوازنة

```sql
-- رصيد سالب (الثابت S1)
SELECT * FROM product_details WHERE quantity < 0;

-- رأس الإذن ≠ بنوده (الثابت S3)
SELECT p.id, p.number, p.grand_total, COALESCE(SUM(l.total),0) lines_total
FROM warehouse_permits p
LEFT JOIN warehouse_permits_products l ON l.warehouse_permits_id = p.id
WHERE p.permission_source_id IN (15, 17)
GROUP BY p.id, p.number, p.grand_total
HAVING ABS(p.grand_total - COALESCE(SUM(l.total),0)) > 0.01;
```

---

<a id="p7-10"></a>

## P7-10 — الترجمة و RTL/LTR

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

### المشكلة

`__()` أو `trans()` أو `@lang` تظهر في **ملف واحد من 63** — **5 استدعاءات في 12,857 سطراً** (في `layouts/sidebar.blade.php`). كل ما عداها عربيّ مكتوب مباشرة.

**والشريط الجانبي نفسه مختلط:** 5 بنود عبر `trans()` وبندان (`'لوحة التصنيع'` سطر 39، `'تقارير التصنيع'` سطر 74) عربيّان مباشرة ⇒ بتبديل اللغة تُترجَم خمسة بنود ويبقى بندان بالعربية في قائمة إنجليزية.

**ومفتاح مكتوب خطأ:** `trans('main_trans.Sittings')` (سطر 80) — الصواب `Settings`. إن لم يوجد المفتاح تُطبَع السلسلة الحرفية.

**و`ManufacturingServiceProvider::registerTranslations()` يشير إلى `module_path($this->name, 'lang')` — مسار غير موجود.**

**و20 استدعاء Select2 يفرض `dir: 'rtl'` بلا شرط** ⇒ في واجهة إنجليزية تبقى كل القوائم المنسدلة RTL.

**وتنسيق الأرقام مثبَّت على `en-US`** في 4 شاشات رغم وجود `window.AppCurrency` قابل للضبط ⇒ ضبط فواصل العملة في الإعدادات بلا أثر في جداول القوائم.

### الحل

**(1) أنشئ `lang/ar/manufacturing.php`:**
```php
<?php

return [
    'orders' => [
        'title' => 'أوامر التصنيع',
        'create' => 'إضافة أمر تصنيع',
        'no_orders' => 'لا أوامر تصنيع',
    ],
    'status' => [
        'pending' => 'جديد',
        'materials_received' => 'تم استلام المواد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'closed' => 'مغلق',
        'cancelled' => 'ملغي',
    ],
    'actions' => [
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
        'edit' => 'تعديل',
    ],
];
```
و`lang/en/manufacturing.php` بالمقابل.

**(2) أصلح المفتاح الخاطئ** — `sidebar.blade.php:80`: `Sittings` → `Settings`.

**(3) أصلح `dir` المفروض** — 20 موضعاً:
```js
// الحالي:
$('.select2').select2({ dir: 'rtl', width: '100%' });
// البديل:
$('.select2').select2({ dir: $('html').attr('dir') || 'rtl', width: '100%' });
```

**(4) استعمل `window.AppCurrency`** بدل `'en-US'` الصلب في `orders/index:191`، `workstations/index:117`، `indirectCosts/index:250, 417` — يفعله باتش [P7-05](#p7-05) تلقائياً.

**(5) CSS منطقي بدل فيزيائي:** `master:159-160` (`bottom: 20px; right: 20px`) و`:230, 261` (`text-align: right`) ⇒ `inset-inline-end` و`text-align: start`. (النموذج الصحيح موجود في `notes-modal.blade.php:137`.)

---

<a id="p7-11"></a>

## P7-11 — تنظيف الإنتاج

**🟡 متوسط · ✅ مؤكد · 🟢 جاهز للصق**

| # | العيب | المواضع | الإصلاح |
|---|---|---|---|
| 1 | `console.log` في الإنتاج — **9 مواضع، منها 3 تطبع إحداثيات موقع المستخدم** | `master:663, 735, 846` · `test-calculator:573, 576, 581, 602, 605, 648` | احذفها |
| 2 | `alert()`/`confirm()` الأصلية رغم توفّر SweetAlert في كل صفحة | `notes-modal:225` · `bom/index:315` · `test-calculator:595, 608, 661, 663, 675` | SweetAlert |
| 3 | `@csrf` داخل نموذج GET ⇒ **الرمز يتسرّب إلى سجلات الخادم في سلسلة الاستعلام** | `bom/index:32` · `workstations/index:32` | احذف `@csrf` |
| 4 | الرابط المكسور في `test-calculator:560` | — | `route('manufacturing.orders.create')` |
| 5 | الحذف المزدوج في `test-calculator` (مُعالجان لنفس الحدث) | 589 و671 | احذف أحدهما |
| 6 | **لا `<!DOCTYPE html>`** ⇒ المتصفح في Quirks Mode، و`<head>` مزدوج | `master:11` و`:19` | أضف الـ doctype وأزل التكرار |
| 7 | 7 معالجات `onclick`/`onmouseover` مضمّنة ⇒ تمنع أي CSP بلا `unsafe-inline` | `action-buttons:69,72` · `reports/index:88-89` · `reports/layout:95` · `test-calculator:311,436` | مستمعات أحداث |
| 8 | التبويبات بلا `aria-selected`/`aria-controls`/`role="tab"` | `nav-tabs.blade.php:40-43` | أضفها |
| 9 | `href="javascript:void(0)"` بدل `<button>` | `action-buttons:69, 72, 76` | `<button type="button">` |
| 10 | `'auth'` مكرر **ثلاث مرات** في نفس التسلسل | `routes/web.php:32, 36-45, 49` | مرة واحدة |
| 11 | الصلاحيات باسم `stock.*` رغم أن الموديول صار `manufacturing` | كل الملف | دَين تقني — وثّقه أو رحّله |

---

## معيار قبول المرحلة 7

- ✅ لوحة التصنيع تعرض الأعداد الصحيحة لا أصفاراً
- ✅ عرض أمر بـ20 بنداً يُنفّذ أقل من 15 استعلاماً (كان 40-80)
- ✅ فتح شاشة إنشاء أمر يُنفّذ أقل من 10 استعلامات (كان ~2001)
- ✅ التقارير الرقابية الثلاثة موجودة وتُفتح من قائمة التقارير
- ✅ حجم الصفحة أقل من 500KB (كان ~2.5MB)

---

<a id="12"></a>

# 12. المرحلة 8 — إصلاح البيانات القائمة (3 أيام) ⚠️

**المرحلة التي ينساها الجميع.** إصلاح الكود لا يُصلح ما أفسده الكود القديم.

> ⛔ **لا تبدأها قبل استقرار المرحلتين 3 و4** — وإلا أصلحتَ بيانات ثم أفسدها كود لم يُصلح بعد.

ناتج السكربت (القسم 10) يقول لك حجم كل مشكلة بالضبط:

| المشكلة | الاستعلام الكاشف | الإصلاح |
|---|---|---|
| **أوامر ملغاة أكلت مواداً خاماً** | أوامر `cancelled` بـ`materials_received_at` غير فارغ | تقرير جرد + إذونات تسوية يدوية **بموافقة محاسب** |
| **قيود غير متوازنة** | [P7-09](#p7-09) تقرير (2) | قيود تسوية بمرجع واضح — لا تعديل القيد الأصلي |
| **سطور قيود يتيمة** | `LEFT JOIN journal_entries WHERE e.id IS NULL` | حذفها أو ربطها |
| **إذونات رأسها ≠ بنودها** | [P7-09](#p7-09) تقرير (3) | إعادة حساب `grand_total` من البنود |
| **مفاتيح أجنبية = 0** | `WHERE raw_product_id = 0` … | هجرة التحويل إلى `NULL` ([P2-02](#p2-02)) |
| **قيود مضاعفة من العكس النصّي** | `description LIKE '%عكس قيد%'` | حصرها ومراجعتها **واحداً واحداً** |
| **`branch_id` فارغ** | `WHERE branch_id IS NULL` | ملؤه **قبل** تفعيل الـ Scope ([P6-01](#p6-01)) |
| **رصيد سالب** | `WHERE quantity < 0` | تقرير + تسوية |
| **حساب WIP الشاذ المتراكم** | [P7-09](#p7-09) تقرير (1) | قيد تسوية واحد يُقفل الرصيد التاريخي |

## قاعدة صارمة

**كل تصحيح بيانات محاسبية يكون بقيد تسوية موثَّق، لا بـ `UPDATE` مباشر.**

الدفتر لا يُعدَّل — يُصحَّح بقيد. أي `UPDATE journal_entry_details SET debit = ...` يُلغي كل قيمة تدقيقية للنظام.

## الترتيب

1. **جرد فعلي للمخزون** قبل أي شيء — أنت لا تعرف الرصيد الصحيح من النظام، فالنظام هو المتهم.
2. صحّح `product_details` بإذونات تسوية بأثر معلوم.
3. أقفل الفروق المحاسبية بقيود تسوية.
4. نظّف البيانات البنيوية (المفاتيح الصفرية، السطور اليتيمة، `branch_id`).
5. أعِد تشغيل التقارير الرقابية — يجب أن تعرض صفراً.

**معيار القبول:** التقارير الرقابية الثلاثة من [P7-09](#p7-09) تعرض **صفراً في كل الفئات**.

---

<a id="13"></a>

# 13. المرحلة 9 — الاختبارات والقبول (4 أيام)

## 13.1 الوضع الحالي

```
tests/Feature/.gitkeep      ← فارغ
tests/Unit/.gitkeep         ← فارغ
```

**موديول محاسبي/مخزني بـ 6,000 سطر PHP بصفر اختبارات.** مع الكمّ الموصوف في هذا المستند، أي إصلاح سيكسر شيئاً آخر بلا أن يلاحظه أحد.

## 13.2 الخطة

| اليوم | العمل |
|---|---|
| 1 | Factories + بيئة اختبار (منتجات، مستودعات، حسابات، BOM) |
| 2 | اختبارات دورة الحياة الكاملة |
| 3 | اختبار لكل ثابت من العشرين في [§1](#1) |
| 4 | اختبار التزامن + Seeder الصلاحيات + CI |

## 13.3 الاختبارات الثلاثة الأولى — اكتبها في المرحلة 1 لا هنا

**شاهدها تفشل اليوم، ثم تنجح بعد كل مرحلة.** هذا أوثق دليل على أن الإصلاح نجح فعلاً.

`tests/Feature/ManufacturingOrderLifecycleTest.php`:
```php
<?php

namespace Modules\Manufacturing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function إلغاء_أمر_بعد_استلام_المواد_يعيد_المخزون()
    {
        // يفشل اليوم — updateProductInventory فارغة (P3-01)
        $order = $this->createOrderWithMaterials(qty: 10);
        $pid = $order->manufacturOrdersItem->first()->raw_product_id;
        $before = $this->stockOf($pid);

        $this->post(route('manufacturing.orders.receive-materials', $order->id), [...]);
        $this->post(route('manufacturing.orders.cancel', $order->id), [
            'cancellation_reason' => 'اختبار',
        ]);

        $this->assertEquals($before, $this->stockOf($pid),
            'المخزون لم يعد إلى ما كان عليه بعد الإلغاء');
    }

    /** @test */
    public function الإرجاع_لا_يتجاوز_ما_صرف()
    {
        // يفشل اليوم — لا سقف كمية (P3-02)
        $order = $this->createOrderWithMaterials(qty: 10);
        $pid = $order->manufacturOrdersItem->first()->raw_product_id;

        $this->post(route('manufacturing.orders.receive-materials', $order->id), [...]);

        $response = $this->post(route('manufacturing.orders.return-materials', $order->id), [
            'storehouse_id' => 1,
            'return_items' => [['product_id' => $pid, 'quantity' => 999999]],
        ]);

        $response->assertSessionHas('error');
        $this->assertLessThanOrEqual(10, $this->stockOf($pid));
    }

    /** @test */
    public function كل_قيد_مرحل_متوازن_وحساب_WIP_يصفر()
    {
        // يفشل اليوم — المبالغ من العميل (P2-05) والقيد السادس مفقود (P3-05)
        $order = $this->createOrderWithMaterials(qty: 10);
        $this->post(route('manufacturing.orders.receive-materials', $order->id), [...]);
        $this->post(route('manufacturing.orders.complete', $order->id), [...]);

        $entries = \App\Models\JournalEntry::where('reference_id', $order->id)->with('details')->get();
        $this->assertNotEmpty($entries, 'لم تُنشأ أي قيود');

        // A1 — كل قيد متوازن
        foreach ($entries as $e) {
            $this->assertEqualsWithDelta(
                $e->details->sum('debit'), $e->details->sum('credit'), 0.01,
                "القيد #{$e->id} غير متوازن"
            );
        }

        // A3 — حساب WIP يصفر للأمر
        $wipResidue = $entries->flatMap->details
            ->where('account_id', $this->wipAccountId())
            ->sum(fn ($d) => $d->debit - $d->credit);

        $this->assertEqualsWithDelta(0, $wipResidue, 0.01,
            'حساب مخزون تحت التشغيل لم يُقفل — ينقص قيد الانحراف');
    }
}
```

## 13.4 Seeder الصلاحيات

`database/seeders/ManufacturingPermissionsSeeder.php` — الـ30+ صلاحية **غير مُنشأة في أي مكان داخل الموديول** ⇒ نشر نظيف = موديول بلا صلاحيات، لا أحد يصل إليه.

```php
        $permissions = [
            // أوامر التصنيع
            'stock.manufacturing_orders.view', 'stock.manufacturing_orders.create',
            'stock.manufacturing_orders.edit', 'stock.manufacturing_orders.show',
            'stock.manufacturing_orders.delete', 'stock.manufacturing_orders.finish',
            'stock.manufacturing_orders.undo_finish', 'stock.manufacturing_orders.close',
            'stock.manufacturing_orders.reopen', 'stock.manufacturing_orders.cancel',
            'stock.manufacturing_orders.notes', 'stock.manufacturing_orders.receive_materials',
            'stock.manufacturing_orders.return_materials', 'stock.manufacturing_orders.cost_breakdown',

            // قوائم المواد
            'stock.bom.view', 'stock.bom.create', 'stock.bom.edit',
            'stock.bom.show', 'stock.bom.delete',

            // محطات العمل
            'stock.workstations.view', 'stock.workstations.create',
            'stock.workstations.edit', 'stock.workstations.show', 'stock.workstations.delete',

            // التكاليف غير المباشرة
            'stock.indirect_costs.view', 'stock.indirect_costs.create',
            'stock.indirect_costs.edit', 'stock.indirect_costs.show',
            'stock.indirect_costs.delete', 'stock.indirect_costs.export',

            // مسارات الإنتاج
            'stock.production_paths.view', 'stock.production_paths.create',
            'stock.production_paths.edit', 'stock.production_paths.delete',

            // التقارير والإعدادات
            'stock.reports.manufacturing', 'stock.settings.manufacturing',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
```
وسجّله في `ManufacturingDatabaseSeeder::run()` (الفارغ حالياً).

> 🟡 **قارن القائمة بالمسجَّل فعلاً عندك أولاً:**
> ```sql
> SELECT name FROM permissions WHERE name LIKE 'stock.%' ORDER BY name;
> ```
> الأسماء أعلاه مستخرجة من `routes/web.php`، وناتج السكربت (القسم 7) يعرض المسجَّل.

## 13.5 معيار القبول النهائي

```bash
php artisan test --filter=Manufacturing
```
**أخضر بالكامل، والثوابت العشرون تصمد.**

---

<a id="14"></a>

# 14. إكمال النظام — النواقص والإضافات

هذا ما ينقص ليكون **قسم تصنيع حقيقياً** لا مجرد شاشات تعمل.

## 14.1 نواقص بنيوية

| # | الناقص | الحالة اليوم | الأثر |
|---|---|---|---|
| **N1** | **الهجرات** | `database/migrations/.gitkeep` فارغ | **الموديول غير قابل للنشر على بيئة نظيفة** |
| **N2** | **Seeder الصلاحيات** | `run()` فارغة | نشر نظيف = موديول بلا صلاحيات |
| **N3** | **الاختبارات** | صفر | كل إصلاح مقامرة |
| **N4** | **ملفات الترجمة** | مجلد `lang/` غير موجود، والمزوّد يشير إليه | i18n مستحيل |
| **N5** | **الموديلات والخدمات** | مجلد `app/` غير موجود رغم أن `composer.json` يعرّف `"Modules\Manufacturing\": "app/"` | كل الموديلات من `App\Models\*` ⇒ **الموديول ليس مستقلاً بأي معنى** |
| **N6** | **Form Requests للمصفوفات** | `getData`, `destroy`, `finish`, `export`, `getStats`, `getItems` بلا أي تحقق | مفاتيح أجنبية بقيمة 0 تُكتب بلا اعتراض |
| **N7** | **سجلّ تدقيق حقيقي** | `stock_before`/`stock_after` صفر في كل السطور | سجل حركة المخزون بلا قيمة |
| **N8** | **تخزين مؤقت للحسابات** | شجرة الحسابات تُبنى بالتعاود في كل طلب، **وبلا حارس عمق** | ❓ دورة في `parent_id` = انهيار المكدس |

### N6 — الثغرة الجوهرية في التحقق

| الكنترولر | الدالة | التحقق |
|---|---|---|
| BOMController | store/update | `ProductionMaterialRequest` ✔ |
| BOMController | getData, destroy, finish, get_cost_total | **لا شيء** |
| WorkstationsController | store/update | `WorkStationRequest` ✔ |
| WorkstationsController | getData, destroy | **لا شيء** |
| IndirectCostsController | store/update | `IndirectCostsRequest` ✔ |
| IndirectCostsController | getData, export, getStats, destroy | **لا شيء** |
| IndirectCostsController | getRestrictionsByDate | `$request->validate()` inline ✔ |
| ProductionMaterialsApiController | getItems | **لا شيء** |

**وحتى الـ Form Requests الموجودة ❓ لا تغطي مصفوفات السطور** — لا شيء يضمن أن `raw_product_id` و`raw_quantity` بنفس الطول، ولا أن `raw_product_id.*` موجود في جدول المنتجات.

**القواعد المطلوبة:**
```php
'raw_product_id.*' => 'required|exists:products,id',
'raw_quantity.*'   => 'required|numeric|min:0.0001',
'expenses_account_id.*' => 'nullable|exists:accounts,id',
```

### N5 — طبقة الخدمات المقترحة

```
Modules/Manufacturing/Services/
├── InventoryService.php             ← P2-03 (المرحلة 2)
├── ManufacturingJournalService.php  ← P2-04 (المرحلة 2)
├── ReceiveMaterialsService.php      ← من receiveMaterials (299 سطراً)
├── CompleteOrderService.php         ← من completeOrder
├── UndoCompletionService.php        ← من undoCompletion
└── BomFinishService.php             ← من BOMController::finish (248 سطراً)
```

### تكرار PHP لم يُعالَج بعد

| المكرَّر | المواضع | الحجم |
|---|---|---|
| كتلة `productsWithUnits` | `MOC` 186-243، 494-551، 1948-2005 | **~195 سطراً** — يعالجها [P7-03](#p7-03) |
| كتل حفظ البنود الأربع | `MOC@store` 310-382 و`@update` 604-676 | **~145 سطراً مكررة حرفياً** |
| بلوك السطور الأربعيني الحقول | `BOMController` 142-175 و265-296 · `OrdersController` 84-112 و215-243 | **4 نسخ** — وقد بدأ التباعد فعلاً (`?? 0` في BOM مقابل `?? null` في Orders) |
| بلوك السجلات (logs) | `BOMController:360-368` · `WorkstationsController:425-433` · `IndirectCostsController:332-340` | **3 نسخ** — مرشّح مثالي لـ Trait أو Scope |
| بلوك القوائم المنسدلة | `BOMController::create/edit/show` · `OrdersController::create/edit` | 5 نسخ |

**والحل لكتل `@php` الضخمة في القوالب** — `orders/partials/form.blade.php:19-138` (120 سطر PHP): **View Model** أو `Resource` يُبنى في الكنترولر ويُمرَّر جاهزاً، لا منطق في طبقة العرض.

**الدوال الأطول اليوم:**

| الدالة | الطول |
|---|---|
| `receiveMaterials` | **299 سطراً** |
| `createManufacturingJournalEntries` | **285** |
| `BOMController::finish` | **248** |
| `BOMController::update` | 121 |
| `orders/partials/form.blade.php` — كتلة `@php` | **120 سطر PHP في قالب** |

---

## 14.2 إضافات إلزامية للتشغيل الصحيح

**ضمّها للمراحل أعلاه — ليست اختيارية.**

<a id="add-a1"></a>

### A1 — قيد انحراف الإنتاج · **يومان** · ضمن [P3-05](#p3-05)

**لماذا إلزامية:** بدونها حساب WIP لا يصفر أبداً — خلل محاسبي دائم يتراكم مع كل أمر.

**التفصيل الكامل في [P3-05](#p3-05).** خلاصته: القيد السادس يُقفل ما تبقّى في WIP بعد إخراج المنتج التام والهالك، فيصمد الثابت **A3**.

> 🟡 قد يلزم إنشاء حساب «انحراف التصنيع» في شجرة حساباتك. **سؤال للمحاسب لا للمطوّر.**

### A2 — متوسط التكلفة المرجح · **يومان** · ضمن [P2-03](#p2-03)

**لماذا إلزامية:** بدونها إضافة 10 وحدات بسعر 50 تجعل 1000 وحدة بسعر 10 **كلها بسعر 50** ⇒ تضخيم فوري لقيمة المخزون بـ40,000 بلا قيد مقابل.

### A3 — تقرير تسوية WIP · **يوم** · ضمن [P7-09](#p7-09)

**لماذا إلزامية:** الرقيب الوحيد على A1. بدونه لن تعرف أن الخلل عاد.

### A4 — فحص توفر المواد عند إنشاء الأمر · **يوم**

موجود جزئياً في `receiveMaterials` (سطر 2102-2120) — **لكنه يظهر بعد فوات الأوان**. المطلوب: تنبيه عند إنشاء الأمر يقول «المادة س ناقصة 30 وحدة» قبل أن يعتمد المستخدم الأمر.

---

## 14.3 إضافات مهمة (بعد استقرار الأساس)

| # | الإضافة | القيمة | الجهد |
|---|---|---|---|
| **A5** | **تقرير تكلفة الأمر (Job Cost Sheet)** | ورقة واحدة: المخطط مقابل الفعلي لكل بند مع الانحراف. **أهم تقرير في أي نظام تصنيع** — بدونه لا يعرف أحد أين ذهبت التكلفة | 3 أيام |
| **A6** | **توزيع التكاليف غير المباشرة آلياً** | اليوم التوزيع يدوي بالكامل. المطلوب: أساس تحميل (ساعات تشغيل / قيمة مواد / كمية إنتاج) يوزّع تلقائياً على الأوامر | 4 أيام |
| **A7** | **مراحل الإنتاج الفعلية** | جدول `production_stages` موجود وغير مُفعَّل — الأمر ينتقل دفعةً واحدة من «مواد» إلى «مكتمل». المطلوب: تتبّع لكل مرحلة بتكلفتها وزمنها | 5 أيام |
| **A8** | **إعادة العمل (Rework)** | اليوم: أمر فشل جزئياً ⇒ لا سبيل إلا الإلغاء وفقدان التكلفة كاملة | 3 أيام |
| **A9** | **إقفال الفترة الإنتاجية** | منع التعديل بأثر رجعي بعد إقفال الشهر — مكمّل للفترة المحاسبية | يومان |

---

## 14.4 إضافات لاحقة (حسب طبيعة نشاطك)

| # | الإضافة | متى تحتاجها | الجهد |
|---|---|---|---|
| **A10** | تتبع الدفعات (Lot/Batch) وتاريخ الصلاحية | أغذية، أدوية، كيماويات | 5 أيام |
| **A11** | طاقة محطات العمل والجدولة | إذا كانت المحطات عنق زجاجة | 6 أيام |
| **A12** | تخطيط احتياجات المواد (MRP) | إذا كثرت الأوامر المتزامنة | 8 أيام |
| **A13** | تسعير الهالك كصنف قابل للبيع | إذا كان الهالك ذا قيمة تجارية | يومان |
| **A14** | ترميز QR / باركود لأوامر التصنيع | إذا كان التتبع في الورشة يدوياً | 3 أيام |

---

<a id="15"></a>

# 15. ما لا يجب فعله

بصراحة، لأن كل بند منها رأيته يُفسد مشروعاً:

1. **لا تبدأ بالواجهات.** الشاشات تبدو معطوبة، لكن العطب الحقيقي في الطبقة تحتها. إصلاح الواجهة أولاً يخفي الأعراض ويُبقي المرض.

2. **لا تُصلح عيباً بلا اختبار يثبته.** مع 6,000 سطر بصفر اختبارات، كل إصلاح احتمالُ كسرِه لشيء آخر عالٍ.

3. **لا تُفعّل الحذف قبل إصلاح `destroy()`.** الـ500 الذي تراه اليوم يحميك من حذف يترك دفتراً بلا مستند.

4. **لا تُطبّق الـ Global Scope قبل ملء `branch_id`.** ستختفي كل البيانات عن كل المستخدمين في لحظة.

5. **لا تُصلح البيانات قبل الكود.** ستُصلحها ثم يُفسدها الكود القديم مرة أخرى.

6. **لا تدمج مرحلتين في commit واحد.** حين ينكسر شيء بعد أسبوعين، ستحتاج أن تعرف أي مرحلة كسرته.

7. **لا تكتب `JournalEntry::create` في كنترولر بعد المرحلة 2.** القاعدة الوحيدة التي تمنع عودة كل عيوب القيود.

8. **لا تمسّ `ProductDetails` مباشرة بعد المرحلة 2.** كل حركة عبر `InventoryService`.

9. **لا تصحّح رصيد حساب بـ `UPDATE`.** بقيد تسوية موثَّق فقط. الدفتر لا يُعدَّل.

10. **لا تنسخ باتشاً من موضع إلى «موضع مشابه» بلا قراءة.** هذا بالضبط ما أنتج نصف العيوب في هذا المستند — انظر [P3-04](#p3-04) و[P7-04](#p7-04).

---

<a id="16"></a>

# 16. الملاحق

## 16.1 ما يحتاج تحققاً خارج الموديول

بنود لم أستطع حسمها لأن ملفاتها خارج `Modules/Manufacturing`. **سكربت الاستكشاف يجيب عن معظمها.**

| # | البند | لماذا يهم | يجيب عنه السكربت |
|---|---|---|---|
| 1 | **هجرة `product_details`** — ledger أم balance؟ | يحدد إن كان [P2-03](#p2-03) خطأً في التفاصيل أم انهياراً كاملاً في حساب المخزون | القسم 1 ✓ |
| 2 | **أعمدة `manufactur_orders_items`** — هل يوجد `product_id`/`quantity`؟ | يؤكد [P3-12](#p3-12) | القسم 10 ✓ |
| 3 | **قيم ثوابت `STATUS_*`** | يحدد حجم تضارب الحالات ([P7-01](#p7-01)) | القسمان 3 و4 ✓ |
| 4 | **`CheckBranch` middleware** — يقيّد الاستعلامات أم يتحقق من الجلسة فقط؟ | يحدد إن كان [P6-01](#p6-01) ثغرة قائمة أم دَيناً تقنياً | القسم 8 ✓ |
| 5 | **Global Scope على الموديلات** | نفس السبب | القسم 4 ✓ |
| 6 | **قواعد الـ Form Requests الأربعة** | يحدد أي تحقق موجود فعلاً | القسم 5 ✓ |
| 7 | **`stock::components.page-head`** — هل يستعمل `{!! !!}` مع `sub`؟ | ثغرة XSS إضافية محتملة عبر اسم الأمر | ✗ — افحصه يدوياً |
| 8 | **الصلاحيات المسجّلة** | يحدد إن كان بند الإعدادات يظهر لأحد ([P7-07](#p7-07)) | القسم 7 ✓ |
| 9 | **`ON DELETE CASCADE`** على `journal_entry_details` و`work_stations_costs` | حجم مشكلة السطور اليتيمة | القسم 2 ✓ |
| 10 | **`ProductionMaterialsApiController`** — هل له مسار في مسارات التطبيق؟ | يحدد إن كان تسريب أسعار الشراء قائماً ([P6-07](#p6-07)) | القسم 6 ✓ |

## 16.2 ما هو سليم — يستحق الذكر

لئلا يبدو المستند أسود بالكامل:

- ✅ **`@csrf` مكتمل** على كل نماذج POST الـ27 (النماذج الثلاثة بلا `@csrf` هي GET/AJAX فقط)
- ✅ **`@json()` مستعمل بانضباط** لتمرير PHP إلى JS في كل المواضع الحساسة تقريباً — لا حقن سلاسل خام
- ✅ **`optional()` و`??`** مستعملان بكثافة على العلاقات، فلا انهيارات null في العرض
- ✅ **`old()` مستعمل جيداً** (68 موضعاً) — الإدخال يُحفظ عند الرفض
- ✅ **`receiveMaterials` هي النموذج الصحيح داخل الملف:** `rollBack` قبل كل خروج (5 مواضع)، و`config('app.debug')` قبل عرض الاستثناء، وفحص توفر قبل الخصم
- ✅ **`orders/partials/form.blade.php`** نموذج صحيح لتوحيد `create`/`edit` (كلاهما 32 سطراً فقط)
- ✅ **القيود المحاسبية متوازنة كلٌّ على حدة** — المشكلة في المجموع الدوري لا في القيد المفرد
- ✅ **التعليقات العربية في `routes/web.php`** توثيق ممتاز لتاريخ الهجرة من `Modules/Stock` — نادر ومفيد
- ✅ **البنية التحتية للثوابت موجودة** (`SystemAccountIds`, `ReferenceType`) ومستعملة في مواضع — تحتاج تعميماً فقط

> **الخلاصة:** الفريق يعرف كيف يُكتب الكود الصحيح — الدليل أن النمط الصحيح موجود داخل نفس الملفات. المشكلة انضباط لا معرفة، وتراكم نسخ ولصق بلا تنظيف. **ولهذا المرحلة 2 (الأساس) هي الأهم: تجعل النمط الصحيح هو الطريق الوحيد.**

## 16.3 سكربت الاستكشاف

`mfg-discovery.php` — في نفس مجلد الموديول.

```bash
cd C:\xampp\htdocs\fawtmaintest\fawtmaintest\web\fawtmaintest\Modules\Manufacturing
php mfg-discovery.php > mfg-discovery.txt
```

**للقراءة فقط** — لا يكتب في قاعدة البيانات ولا في الملفات. يجد جذر المشروع بنفسه.

**ما يطبعه:**

| القسم | المحتوى |
|---|---|
| 0 | البيئة، إصدار المحرّك، `sql_mode` (الوضع الصارم؟)، عزل المعاملات |
| **1** | **السؤال الحاسم — طبيعة `product_details`** + قيم `type` + الأرصدة السالبة |
| 2 | `SHOW CREATE TABLE` لـ21 جدولاً (أعمدة، أنواع، فهارس، مفاتيح أجنبية) |
| 3 | القيم الفعلية لـ`status` و`permission_type` و`reference_type` في البيانات |
| 4 | الموديلات: `$fillable`, `$casts`, الثوابت، **Global Scopes**, العلاقات |
| 5 | Enums والثوابت وقواعد الـ Form Requests والـ Traits |
| 6 | المسارات المسجَّلة + الأسماء المكررة + المسارات الخارجية المفقودة |
| 7 | الصلاحيات المسجَّلة فعلاً |
| 8 | أعمدة الفرع + مصدر `CheckBranch` |
| 9 | كيف يتعامل `Modules/Stock` مع `ProductDetails` — النمط المرجعي |
| **10** | **صحة البيانات القائمة** — القيود غير المتوازنة، الأوامر الملغاة، الإذونات غير المتوازنة، المفاتيح الصفرية |
| 11 | إعدادات الترقيم والفترات المحاسبية |

## 16.4 أدوات تكشف هذه الأخطاء تلقائياً

انظر [P1-10](#p1-10). **ركّبه اليوم.**

## 16.5 خريطة المعرّفات القديمة

إن كنت تتابع من التقرير أو دليل الإصلاحات السابقين.

> **تنبيه:** أُعيد عدّ عدة أرقام أثناء الدمج بعد تدقيق آلي على الملفات المصدرية، فبعضها يخالف التقرير الأول. **الأرقام هنا هي الصحيحة:**
>
> | البند | التقرير الأول | الصحيح هنا |
> |---|---|---|
> | دوال بلا `rollBack` | 8 | **9** (شاملةً `BOMController@finish`) |
> | سطر خروج `BOMController@finish` | 563 | **567** |
> | `console.log` في الإنتاج | 11 (منها 4 للموقع) | **9** (منها **3** للموقع) |
> | استدعاءات `dir:'rtl'` المفروضة | 17 | **20** |
> | مواضع `esc()` | 5 | **12** (10 متطابقة + `escapeHtml` في ملفين) |
> | سطر `order-info.blade.php` لـ`products.show` | 236 | **21** (الملف 112 سطراً فقط) |
> | سطر شارة الحالة غير المُهرَّبة | 228 | **210** |

| التقرير القديم | دليل الإصلاحات | هنا |
|---|---|---|
| 2.1 | F-1 | [P1-01](#p1-01) |
| 2.2 | F-2 | [P1-02](#p1-02) |
| 2.3 | F-3 | [P1-03](#p1-03) |
| 2.4 | F-4 | [P1-04](#p1-04) |
| 2.5 | F-5 | [P1-05](#p1-05) |
| 2.6 | F-6 | [P1-06](#p1-06) |
| 2.7 | F-9 | [P1-09](#p1-09) |
| 4.3 | F-7 | [P1-07](#p1-07) |
| §8 | F-8 | [P1-08](#p1-08) |
| ملحق أ | — | [P1-10](#p1-10) |
| 6 | C-5 | [P2-01](#p2-01), [P7-01](#p7-01) |
| 3.7, 3.11, 3.12, 3.17 | C-2, C-3, N-4 | [P2-03](#p2-03) |
| 3.8 | A-1 | [P2-05](#p2-05) |
| 4.6 | A-4 | [P2-06](#p2-06) |
| 3.1 | S-1 | [P3-01](#p3-01) |
| 3.2 | S-2 | [P3-02](#p3-02) |
| 3.3 | S-3 | [P3-03](#p3-03) |
| 3.16 | S-9 | [P3-04](#p3-04) |
| 3.9 | — | [P3-05](#p3-05) |
| 3.5 | S-5 | [P3-06](#p3-06) |
| 3.6 (أ) | S-6 (أ) | [P3-07](#p3-07) |
| 3.14 | S-7 | [P3-08](#p3-08) |
| 3.15 | S-8 | [P3-09](#p3-09) |
| 3.10, 7.4 | — | [P3-10](#p3-10) |
| 7.3 | — | [P3-11](#p3-11) |
| 7.2 | — | [P3-12](#p3-12) |
| 3.18 | — | [P2-05](#p2-05) (نهاية البند) |
| — | C-1 | [P2-03](#p2-03) + [§4.2](#4) القرار 1 |
| 3.13 | C-4 | [P4-01](#p4-01) |
| 3.4 | S-4 | [P4-02](#p4-02) |
| 3.6 (ب) | S-6 (ب) | [P4-03](#p4-03) |
| 4.1 | A-2 | [P6-01](#p6-01) |
| 4.2 | A-3 | [P6-02](#p6-02) |
| 4.4 | A-5 (أ) | [P6-04](#p6-04) |
| 4.5 | A-5 (ب) | [P6-05](#p6-05) |
| 4.7 | A-6 | [P6-06](#p6-06) |
| 4.8 | — | [P6-07](#p6-07) |
| 4.9 | — | [P6-08](#p6-08) |
| 5.3, 5.4 | P-2 | [P7-02](#p7-02) |
| 5.1, 5.2 | P-1 | [P7-03](#p7-03) |
| 5.5 | P-3 | [P5-06](#p5-06) |
| 7.7, 10.x | Q-2, Q-3 | [P7-04](#p7-04) |
| 7.7 | Q-1 | [P7-05](#p7-05) |
| 5.6, 5.7, 5.8 | Q-4 | [P7-06](#p7-06) |
| 10.x | Q-5 | [P7-07](#p7-07) |
| 7.1, 7.5, 7.6 | C-6 | [P7-08](#p7-08) |
| 9.10 | N-3 | [P7-10](#p7-10) |
| 10.x | Q-6 | [P7-11](#p7-11) |
| 7.8 | — | [§14.1](#14) — N5 (الدوال الطويلة) |
| 9.1 | N-1 | [§13](#13) |
| 9.2 | — | [§14.1](#14) — N1 (الهجرات) |
| 9.3 | N-2 | [§13.4](#13) (Seeder الصلاحيات) |
| 9.4 | N-3 | [P7-10](#p7-10) + [§14.1](#14) — N4 |
| 9.5 | N-4 | [§14.1](#14) — N5 (طبقة الخدمات) |
| 9.6 | — | [§14.1](#14) — N6 (Form Requests) |
| 9.7 | — | [§14.2](#14) — A1 (قيد الانحراف) |
| 9.8 | — | [§14.1](#14) — N8 (التخزين المؤقت) |
| 9.9 | — | [§14.1](#14) — N7 (سجل التدقيق) |
| 10.1 | — | [§14.1](#14) — N5 + [P2-01](#p2-01) (معماري) |
| 10.2 | — | [P2-04](#p2-04), [P2-05](#p2-05), [P3-05](#p3-05) (محاسبي) |
| 10.3 | — | [P2-03](#p2-03), [P6-08](#p6-08) (مخزني) |
| 10.4 | Q-1..Q-4 | [P7-04](#p7-04)…[P7-06](#p7-06) (واجهات) |
| 10.5 | Q-5, Q-6 | [P7-07](#p7-07), [P7-11](#p7-11), [P1-10](#p1-10) (جودة) |

---

## الخطوة التالية

1. **شغّل السكربت وأرسل `mfg-discovery.txt`** — يحسم القرارات الستة.
2. **ابدأ المرحلة 1 اليوم** — مستقلة تماماً ولا تنتظر شيئاً.
3. **اكتب الاختبارات الثلاثة من [§13.3](#13)** وشاهدها تفشل — ستكون مقياسك بعد كل مرحلة.

**عند وصول ناتج السكربت** أكتب لك: `InventoryService` و`ManufacturingJournalService` بكودهما الكامل مبنيَّين على بنية جداولك الفعلية، و`ManufacturOrderStatus` بجدول انتقالاته، والهجرات الستّ بأسمائها الصحيحة، وتقريراً بحجم البيانات الفاسدة وخطة إصلاحها.

**وأخبرني بشيء واحد لا يعرفه السكربت:** هل النظام يعمل على بيانات حقيقية الآن، أم ما زال قيد التطوير؟ الجواب يغيّر ترتيب المرحلتين 6 و8 جذرياً.
