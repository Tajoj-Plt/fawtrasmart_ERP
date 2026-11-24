# اختبارات مديول التصنيع

## التشغيل

```bash
php artisan test -c Modules/Manufacturing/phpunit.xml
```

ملفُ الإعداد داخل المديول عمداً — لا تغييرَ في `phpunit.xml` الجذر.
ولهذا **لا تلتقط `composer test` هذه المجموعة**؛ تُشغَّل بالأمر أعلاه.
وضمُّها إلى المجموعة العامّة قرارٌ مؤجَّل لأنه تعديلٌ خارج المديول.

### تشغيلٌ أسرع أثناء العمل

الهجرة الكاملة تستغرق نحو ٧٥ ثانية. ولإعادة استخدام القاعدة كما هي:

```bash
MANUFACTURING_REUSE_DB=1 php artisan test -c Modules/Manufacturing/phpunit.xml
```

يهبط الزمن إلى نحو ٧ ثوانٍ. **أسقِط المتغيّر بعد أي هجرةٍ جديدة**
وإلا عملتَ على مخطّطٍ قديم. والافتراضُ دائماً هو الهجرة الكاملة.

## لماذا MySQL لا SQLite

بقيّةُ المشروع تختبر على SQLite في الذاكرة. هذه المجموعة لا تستطيع:

1. **الهجرات تتوقّف.** أولُ هجرةٍ في السلسلة
   (`change_is_online_to_boolean_in_payment_methods`) تنفّذ
   `ALTER TABLE … MODIFY` — صيغةٌ لا يفهمها SQLite. وهي واحدةٌ من
   سبع عشرة هجرةً تستعمل `MODIFY` أو `SHOW COLUMNS`.

2. **`lockForUpdate()` بلا أثر على SQLite.** وهو حارسُ التزامن الذي
   يمنع الإرسال المزدوج (المرحلة ١-ب). اختبارُه هناك يمرّ دائماً
   ولا يثبت شيئاً.

القاعدة `fawtra_manufacturing_test` منفصلةٌ تماماً عن قاعدة التطوير،
ويرفض `ManufacturingTestCase` العملَ إن لم يحمل الاسمُ كلمة `test` —
لأن `RefreshDatabase` يبدأ بإسقاط كل الجداول.

## البنية

| الملف | الدور |
|---|---|
| `phpunit.xml` (جذر المديول) | الإعداد: قاعدة الاختبار والبيئة |
| `tests/bootstrap.php` | يسجّل مساحة `Modules\Manufacturing\Tests\` |
| `tests/ManufacturingTestCase.php` | الأساس: الحارس، الزرع، العزل، الأدوات |
| `tests/Support/Fixtures.php` | بانيةُ البيانات |
| `tests/Feature/HarnessSmokeTest.php` | تحقّقٌ من أن الأدوات نفسها تعمل |

## ما يوفّره الأساس

- **حارسُ القاعدة** — يمنع التشغيل على قاعدةٍ ليست للاختبار.
- **شجرةُ الحسابات** مزروعةً مرّةً لكل تشغيل، ومعها حساب
  «انحراف تكاليف الإنتاج» مربوطاً بمفتاحه.
- **عزلٌ حقيقي** — انظر `ensureTestIsolationIsReal()`؛ معاملةُ الإطار
  كانت تُفتح ثم يُستبدل مِقبض PDO، فتتسرّب صفوفُ كل اختبارٍ إلى تاليه.
- **رفعُ سقوف الاشتراك** — `PlanLimitObserver` يمنع الإنشاء بعد حدّ
  الباقة، وقاعدةُ الاختبار بلا اشتراك.
- **أدوات قياس** — `stockOf()` للرصيد، `permitCount()` لأذون المستودع.

## البانية

```php
$warehouse = $this->fixtures->warehouse();
$flour     = $this->fixtures->product(['name' => 'دقيق']);
$bread     = $this->fixtures->product(['name' => 'خبز']);

$this->fixtures->stock($flour, $warehouse, 100);

$bom = $this->fixtures->bom($bread, ['quantity' => 10]);   // كمية الإنتاج
$this->fixtures->bomRawLine($bom, $flour, 5, 2.5);

$order = $this->fixtures->order($bom, ['quantity' => 20]);
```

بانيةٌ صريحة لا Factories: جداولُ التصنيع مليئةٌ بأعمدة NOT NULL بلا
قيمةٍ افتراضية ومفاتيحَ متشابكة، والمصانع محلُّها `database/factories`
في الجذر — خارج المديول.

## الاختبارات الوظيفية

**لا تُكتب هنا مسبقاً.** كل مرحلةٍ من خارطة الطريق تكتب اختباراتِها
بوصفها معيارَ قبولها — «الصرف لا يجعل الرصيد سالباً»، «مدين = دائن»،
«WIP يصفر». هذه المجموعة تبني الأدوات فقط.
