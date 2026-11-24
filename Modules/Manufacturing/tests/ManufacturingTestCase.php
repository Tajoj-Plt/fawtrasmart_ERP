<?php

namespace Modules\Manufacturing\Tests;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Services\Fawtra\SubscriptionGate;
use Database\Seeders\AccountingSettingsSeeder;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Tests\Support\Fixtures;
use RuntimeException;
use Tests\TestCase;

/*
    الأساس الذي ترث منه كل اختبارات التصنيع.

    يتكفّل بثلاثة أشياء لا ينبغي تكرارها في كل ملف:

      ١) حارسُ القاعدة — يرفض العمل إن لم يكن الاسم قاعدةَ اختبار.
         `RefreshDatabase` يبدأ بـ migrate:fresh، أي إسقاطِ كل جدول.
         إعدادٌ خاطئ في .env أو متغيّرٌ متسرّب من البيئة كفيلٌ بمسح
         قاعدة التطوير كاملةً. الحارس يجعل ذلك مستحيلاً لا بعيداً.

      ٢) شجرةُ الحسابات — تُزرع مرّةً واحدة لكل تشغيل، خارج المعاملة،
         فتبقى متاحةً لكل الاختبارات دون إعادة زرع.

      ٣) بانيةُ البيانات — `$this->fixtures` لتجهيز منتج ومستودع
         وقائمة مواد وأمر تصنيع بسطرٍ واحد.
*/
abstract class ManufacturingTestCase extends TestCase
{
    /*
        نستعير اسماً ثانياً لطريقة السِّمة كي نغلّفها.
        `parent::` لا يصل إلى طرائق السِّمات — فهي تُدمج في الصنف نفسه.
    */
    use RefreshDatabase {
        refreshTestDatabase as protected runFrameworkDatabaseRefresh;
    }

    protected Fixtures $fixtures;

    /** هل هاجرنا مرّةً في هذه العملية؟ — انظر `shouldSkipMigration()`. */
    private static bool $migratedInThisProcess = false;

    protected function setUp(): void
    {
        $this->guardTestDatabase();

        parent::setUp();

        $this->ensureTestIsolationIsReal();
        $this->liftSubscriptionLimits();

        $this->fixtures = new Fixtures;
    }

    /*
        يُستدعى مرّةً واحدة بعد migrate:fresh وقبل فتح المعاملات،
        فما يُزرع هنا يثبت لكل اختبارات التشغيل الواحد.
    */
    protected function afterRefreshingDatabase(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(AccountingSettingsSeeder::class);
    }

    /*
        إعادةُ استخدام القاعدة بين التشغيلات — اختيارية وصريحة.

        الهجرةُ الكاملة تستغرق نحو ست دقائق (٤٦٧ هجرةً في الجذر
        وما يقابلها في المديولات). ذلك مقبولٌ مرّةً، ومُعيقٌ حين
        يُعاد التشغيل عشرات المرّات أثناء كتابة معايير القبول.

        السلوك الافتراضي هو الهجرة الكاملة — الأسلم دائماً. ومن أراد
        السرعة يضبط المتغيّر صراحةً:

            MANUFACTURING_REUSE_DB=1 php artisan test -c Modules/Manufacturing/phpunit.xml

        ولا يُعاد الاستخدام إلا إذا كانت القاعدة مهاجَرةً فعلاً
        وشجرةُ الحسابات مزروعةً فيها. أيُّ هجرةٍ جديدة تعني إسقاطَ
        المتغيّر مرّةً حتى تُطبَّق.
    */
    protected function refreshTestDatabase(): void
    {
        /*
            البوّابةُ السامحة تُربَط قبل الهجرة لا بعدها.

            `AccountSeeder` نفسه يُنشئ عشراتِ الحسابات تحت أب «الأصول
            الثابتة»، و`PlanLimitObserver` يعدّها ويمنع عند السقف — فيسقط
            الزرعُ برسالة «بلغت حدّ الأصول الثابتة: 50 من 50». وربطُها في
            `setUp` وحده يأتي متأخّراً: الزرعُ يقع داخل هذه الدالّة.
        */
        $this->liftSubscriptionLimits();

        if (! RefreshDatabaseState::$migrated && $this->shouldSkipMigration()) {
            RefreshDatabaseState::$migrated = true;
        }

        $this->runFrameworkDatabaseRefresh();

        self::$migratedInThisProcess = true;
    }

    /*
        هل نتخطّى الهجرة؟

        حالتان مختلفتان يخلطهما `RefreshDatabase`:

        ١) **أوّلُ اختبارٍ في العملية.** الهجرةُ الكاملة هي الأصل، ولا
           تُتخطّى إلا بطلبٍ صريح عبر `MANUFACTURING_REUSE_DB`.

        ٢) **اختبارٌ تالٍ في العملية نفسها.** الهجرة هنا **لا تُطلب أبداً**.
           لكنّ الإطار يُصفّر علمَ `$migrated` في التفكيك متى وجد الاتصالَ
           بلا معاملة — شبكةُ أمانٍ في محلّها عموماً، وضارّةٌ هنا: تُعيد
           `migrate:fresh` والزرعَ في منتصف التشغيل، فيبطئ كثيراً ويسقط
           عند سقف الباقة. ونحن نصلح المعاملةَ صراحةً في
           `ensureTestIsolationIsReal()`، فالشبكةُ زائدة.
    */
    private function shouldSkipMigration(): bool
    {
        if (! $this->schemaLooksReady()) {
            return false;
        }

        return self::$migratedInThisProcess
            || filter_var(env('MANUFACTURING_REUSE_DB', false), FILTER_VALIDATE_BOOLEAN);
    }

    /*
        ضمانُ أن عزل الاختبارات حقيقيٌّ لا اسمي.

        ── ما رُصد ──────────────────────────────────────────────────────
        يفتح `RefreshDatabase` معاملةً داخل setUpTraits ثم يتراجع عنها
        عند التفكيك، فلا يبقى أثرٌ لاختبارٍ في تاليه. لكنّ القياس هنا
        أظهر غير ذلك:

            level = 1        ← عدّاد Laravel (في PHP)
            pdo->inTransaction() = false   ← حقيقةُ المُشغّل
            INNODB_TRX لهذا الاتصال = لا شيء
            صفٌّ يُنشأ في الاختبار يراه اتصالٌ آخر فوراً، ويبقى بعده

        أي أنّ مِقبض PDO استُبدل بين فتح المعاملة وبدء جسم الاختبار،
        فبقي عدّاد Laravel على ١ فوق اتصالٍ بلا معاملة. وحين يأتي
        التراجع في التفكيك لا يجد ما يتراجع عنه — فتتسرّب الصفوف.
        وقد ثبت أن المشكلة ليست في المُشغّل: PDO مباشرةً على القاعدة
        نفسها يفتح ويتراجع بلا خلل، وإعادةُ الفتح على الاتصال ذاته
        تنجح (inTransaction تصير true).

        ── العلاج ───────────────────────────────────────────────────────
        نصالح العدّاد مع الواقع: نصفّره ثم نفتح معاملةً فعلية. بعدها
        يجد تراجعُ الإطار معاملةً حقيقية فيؤدّي عمله.

        بلا هذا، كل اختبارٍ يرى بقايا سابقه — وتصبح تأكيداتُ المخزون
        والقيود في المراحل التالية بلا معنى.
    */
    private function ensureTestIsolationIsReal(): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        if ($pdo->inTransaction()) {
            return;
        }

        if ($connection->transactionLevel() > 0) {
            $connection->rollBack(0);
        }

        $connection->beginTransaction();
    }

    /*
        رفعُ سقوف الاشتراك داخل الاختبارات.

        `PlanLimitObserver` مسجَّلٌ على عشرات النماذج في AppServiceProvider،
        ويمنع الإنشاء متى تجاوز العددُ سقفَ الباقة. وقاعدةُ الاختبار بلا
        اشتراكٍ حقيقي، فتُحسب على أدنى باقة ويفشل تجهيزُ البيانات برسالة
        «تجاوزتَ الحدّ» — وهو فشلٌ لا علاقة له بما نختبره.

        نستبدل البوّابةَ بنسخةٍ تسمح دائماً. المنعُ نفسه يبقى قابلاً
        للاختبار عند الحاجة بربط بوّابةٍ مانعةٍ في اختبارٍ بعينه.
    */
    private function liftSubscriptionLimits(): void
    {
        $this->app->instance(SubscriptionGate::class, new class extends SubscriptionGate
        {
            public function checkLive(string $feature, int $quantity = 1): array
            {
                return ['allowed' => true, 'reason' => null, 'remaining' => PHP_INT_MAX];
            }

            public function check(string $feature, int $quantity = 1): array
            {
                return ['allowed' => true, 'reason' => null, 'remaining' => PHP_INT_MAX];
            }
        });
    }

    private function schemaLooksReady(): bool
    {
        return Schema::hasTable('manufactur_orders')
            && Schema::hasTable('accounting_settings')
            && DB::table('accounts')->where('id', SystemAccountIds::PRODUCTION_VARIANCE)->exists();
    }

    /*
        لا شيء يعمل قبل أن يثبت أن القاعدة قاعدةُ اختبار.

        نقرأ الإعداد قبل إقلاع التطبيق حتى لا نعتمد على حاويةٍ قد
        تكون التقطت إعداداً آخر.
    */
    private function guardTestDatabase(): void
    {
        // لا `config()` هنا: الحارس يسبق إقلاعَ التطبيق عمداً،
        // فلا حاويةَ خدماتٍ بعد. البيئة وحدها هي المصدر.
        $connection = env('DB_CONNECTION') ?? 'غير محدَّد';
        $database = env('DB_DATABASE');

        if ($database === null || $database === '') {
            throw new RuntimeException(
                'اختبارات التصنيع: لم تُحدَّد قاعدة بيانات. راجع Modules/Manufacturing/phpunit.xml'
            );
        }

        if (! str_contains(strtolower((string) $database), 'test')) {
            throw new RuntimeException(sprintf(
                'اختبارات التصنيع متوقّفة: القاعدة «%s» لا تبدو قاعدةَ اختبار (اتصال: %s). '
                .'RefreshDatabase يُسقط كل الجداول — شغّل الاختبارات عبر '
                .'«php artisan test -c Modules/Manufacturing/phpunit.xml» فقط.',
                $database,
                $connection
            ));
        }
    }

    /** رصيدُ منتجٍ في مستودع كما يحسبه النظام من حركات المخزون. */
    protected function stockOf(int $productId, ?int $warehouseId = null): float
    {
        $query = DB::table('product_details')->where('product_id', $productId);

        if ($warehouseId !== null) {
            $query->where('store_house_id', $warehouseId);
        }

        return (float) $query->sum('quantity');
    }

    /*
        عدد أذونات المستودع المرتبطة بأمر تصنيع، اختيارياً بمصدرٍ بعينه.

        الرابط زوجٌ متعدّد الأشكال — `reference_type` + `reference_id` —
        لا عمودَ مفتاحٍ مخصَّص. وإغفالُ النوع يخلط أذونَ التصنيع بأذونِ
        مستنداتٍ أخرى تحمل الرقم نفسه.
    */
    protected function permitCount(int $orderId, ?int $source = null): int
    {
        $query = DB::table('warehouse_permits')
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $orderId);

        if ($source !== null) {
            $query->where('permission_source_id', $source);
        }

        return (int) $query->count();
    }
}
