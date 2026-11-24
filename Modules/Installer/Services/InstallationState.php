<?php

namespace Modules\Installer\Services;

/**
 * هل النظام مثبَّت؟ — مصدر الجواب الوحيد.
 *
 * العلامة ملفّ على القرص لا صفٌّ في قاعدة البيانات، لسببٍ بسيط: السؤال
 * يُطرح قبل أن تكون هناك قاعدة بيانات أصلاً. أي فحصٍ يمرّ على DB هنا
 * ينهار في اللحظة التي وُجد من أجلها.
 *
 * ويُقرأ الملف مرة واحدة لكل طلب ويُحفظ في متغيّر ساكن: الوسيط
 * EnsureInstalled يعمل على كل طلب web، وقراءة قرصٍ في كل مرة ثمنٌ بلا مقابل.
 */
class InstallationState
{
    /** null = لم يُقرأ بعد في هذا الطلب */
    protected static ?bool $installed = null;

    /**
     * مسار ملف العلامة. تحت storage/app لأنها كتابةٌ مضمونة الصلاحية
     * ومستثناة من git، فلا تُنقل حالةُ تثبيت جهازٍ إلى آخر.
     */
    public static function markerPath(): string
    {
        return storage_path('app/installed');
    }

    public static function isInstalled(): bool
    {
        if (self::$installed !== null) {
            return self::$installed;
        }

        // العلامة صالحة لقاعدة البيانات التي كُتبت من أجلها وحدها.
        //
        // كانت مجرّد «هل الملف موجود؟» — وهو ما جعل تبديل `DB_DATABASE` في
        // `.env` إلى قاعدة فارغة يُبقي النظام يقول «مثبَّت»، فيذهب المستخدم
        // إلى `/login` على قاعدةٍ لا جدول فيها ولا يجد المعالجَ أصلاً. (وقع
        // هذا فعلاً: عُدِّل `.env` من `fawtra_smart` إلى `fawtra` الفارغة،
        // فبقيت علامةُ الأولى تحجب المعالج عن الثانية.)
        //
        // المقارنة قراءةُ إعدادٍ لا استعلام، فلا تكلّف شيئاً على كل طلب.
        if (self::markerMatchesCurrentDatabase()) {
            return self::$installed = true;
        }

        // لا علامة صالحة — قد يكون تركيباً جديداً، وقد يكون نظاماً قائماً سبق
        // وجودَ هذه الوحدة. التفريق بينهما بفحص القاعدة مرةً واحدة: إن
        // وُجدت جداول النظام وبياناتها كُتبت العلامة، فلا يتكرّر الفحص أبداً.
        //
        // بدون هذا كان تركيب الوحدة على نظامٍ عامل يحوّله كلّه إلى معالج
        // التثبيت — أي أن إضافة المُثبِّت تُعطّل ما ثُبِّت.
        // **تثبيتٌ جارٍ الآن ليس تثبيتاً تامّاً.**
        //
        // البذور تُنشئ المستخدم وشجرة الحسابات في منتصف التنفيذ، فيرى
        // الاكتشافُ أدناه «نظاماً قائماً» ويكتب العلامة — فيُقفل المعالجُ على
        // نفسه وهو في وسط عمله: تتوقّف النبضات بتحويلةٍ إلى الجذر، ويبقى
        // النظام نصف مثبَّت بلا بذورٍ أخيرة ولا إعدادات ولا فحص توافق.
        // (هذا بالضبط ما كان يجعل التثبيت «يعلّق ولا يكمل».)
        if (InstallationRunner::isRunning()) {
            return self::$installed = false;
        }

        $probe = self::probeExistingInstallation();

        if ($probe === true) {
            self::markInstalled(['detected' => true, 'note' => 'اكتُشف تركيب قائم قبل وجود وحدة التثبيت']);

            return self::$installed = true;
        }

        // قاعدة متصلة وفارغة = تركيب جديد، مهما كان `.env` مضبوطاً.
        //
        // التفريق بين «فارغة» و«متعذّرة» جوهري: خلطُهما جعل حاجزَ الأمان أدناه
        // يقفل المعالج على قاعدةٍ فارغة لأن `.env` يحمل `APP_KEY` — وهو حال كل
        // من ثبّت مرة ثم أفرغ القاعدة ليعيد التثبيت.
        if ($probe === false) {
            return self::$installed = false;
        }

        // $probe === null: تعذّر الفحص أصلاً — ونظامٌ مضبوط يُعامَل مثبَّتاً.
        //
        // **هذا حاجزٌ أمني لا احتياط.** ملف العلامة تحت `storage/app` وهو
        // مستثنى من git، فنشرٌ لا ينقل `storage` يفقده. فلو تعذّر الاتصال
        // بالقاعدة في تلك اللحظة — انقطاع، أو خادم يعيد الإقلاع — لقُرئ
        // النظام «غير مثبَّت» **وانفتح المعالج للعالم على نظامٍ حيّ**: بلا
        // مصادقة، ويكتب `.env` (فيوجّه القاعدة إلى خادم المهاجم)، ويشغّل
        // `db:wipe`، وينشئ حساب مديرٍ بصلاحيات المالك.
        //
        // فحين يعجز الفحص، لا يُفتح المعالج إلا إذا بدا النظام غير مضبوط
        // أصلاً: `APP_KEY` فارغ. وجودُه يعني أن أحداً هيّأ هذا التركيب من
        // قبل — والقفل عندئذٍ أسلم من الفتح.
        if (self::looksConfigured()) {
            return self::$installed = true;
        }

        return self::$installed = false;
    }

    /**
     * هل يبدو التركيب مهيّأً من قبل؟
     *
     * `APP_KEY` هو الدليل: التركيب الجديد ينسخ `.env.example` بمفتاح فارغ،
     * ولا يُولَّد إلا بـ `key:generate` أو بخطوة قاعدة البيانات في المعالج.
     * ووجود اسم قاعدة بيانات معه يرجّح أن أحداً ضبط هذا النظام.
     */
    protected static function looksConfigured(): bool
    {
        return filled(config('app.key')) && filled(self::currentDatabase());
    }

    /**
     * هل العلامة الموجودة تخصّ قاعدة البيانات المضبوطة الآن؟
     *
     * علامةٌ قديمة بلا حقل `database` تُقبل كما هي: كُتبت قبل وجود هذا الفحص،
     * ورفضُها يعني إعادة إظهار المعالج على أنظمة تعمل — وهو ضررٌ أكبر من
     * الحالة التي يعالجها الفحص.
     */
    protected static function markerMatchesCurrentDatabase(): bool
    {
        if (! file_exists(self::markerPath())) {
            return false;
        }

        $meta = self::readMarker();

        $stamped = $meta['database'] ?? null;

        if ($stamped === null) {
            return true;
        }

        return $stamped === self::currentDatabase();
    }

    /** اسم قاعدة البيانات المضبوطة — من الإعداد لا من اتصالٍ مفتوح. */
    protected static function currentDatabase(): ?string
    {
        $connection = config('database.default');

        return config("database.connections.{$connection}.database");
    }

    /**
     * هل تشير القاعدة إلى نظامٍ مُثبَّت أصلاً؟
     *
     * **ثلاث حالات لا اثنتان** — والخلط بينها هو ما جعل الحاجز الأمني يقفل
     * المعالج على قاعدةٍ فارغة:
     *
     * - `true`  : متصلة وفيها مستخدمون وشجرة حسابات → نظام قائم.
     * - `false` : متصلة وفارغة (أو بلا جداول) → تركيب جديد، افتح المعالج.
     * - `null`  : تعذّر الاتصال أصلاً → لا يُعرف، والقرار للحاجز الأمني.
     *
     * ووجود جدول `users` وحده لا يكفي: الهجرات قد تكون شُغِّلت ثم تعثّر
     * التثبيت، فالدليل أن يكون فيه مستخدم فعلاً وأن تكون الحسابات مزروعة.
     */
    protected static function probeExistingInstallation(): ?bool
    {
        try {
            // اتصالٌ صريح أولاً: هذا وحده ما يفرّق «متعذّرة» عن «فارغة»
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $schema = \Illuminate\Support\Facades\Schema::getConnection()->getSchemaBuilder();

            if (! $schema->hasTable('users') || ! $schema->hasTable('accounts')) {
                return false;
            }

            return \Illuminate\Support\Facades\DB::table('users')->exists()
                && \Illuminate\Support\Facades\DB::table('accounts')->exists();
        } catch (\Throwable $e) {
            // متصلة لكن الاستعلام تعثّر — تُعامَل مجهولةً لا فارغة
            return null;
        }
    }

    /**
     * تسجيل تمام التثبيت.
     *
     * `database` ليس تشخيصياً بل جزءٌ من العلامة نفسها: التثبيت يخصّ قاعدةً
     * بعينها، وتحويلُ `.env` إلى غيرها يُبطله (انظر
     * `markerMatchesCurrentDatabase`). الباقي تشخيصي.
     */
    public static function markInstalled(array $meta = []): void
    {
        $payload = array_merge([
            'installed_at' => now()->toIso8601String(),
            'database' => self::currentDatabase(),
            'version' => config('installer.version', '1.0.0'),
            'php' => PHP_VERSION,
        ], $meta);

        $dir = dirname(self::markerPath());

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            self::markerPath(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        self::$installed = true;
    }

    /**
     * إلغاء العلامة — لإعادة التثبيت. لا يمسّ قاعدة البيانات ولا الملفات:
     * حذفُ البيانات قرارٌ يخصّ صاحب النظام لا معالجَ التثبيت.
     */
    public static function forget(): void
    {
        if (file_exists(self::markerPath())) {
            unlink(self::markerPath());
        }

        self::$installed = false;
    }

    /**
     * بيانات ملف العلامة، أو [] إن لم يكن مثبَّتاً أو تعذّرت قراءته.
     */
    public static function meta(): array
    {
        return self::isInstalled() ? self::readMarker() : [];
    }

    /**
     * هل جاءت العلامة من اكتشافٍ في القاعدة لا من تثبيتٍ أتمّه المعالج؟
     *
     * الفرق عمليّ لا وصفيّ: علامةٌ مكتوبة بالاكتشاف تُعاد كتابتها فور حذفها
     * ما دامت القاعدة مملوءة، فنصيحة «احذف الملف» لا تنفع معها وحدها.
     */
    public static function wasDetectedFromDatabase(): bool
    {
        return (bool) (self::readMarker()['detected'] ?? false);
    }

    /**
     * قراءة ملف العلامة خاماً — دون المرور بـ `isInstalled()`.
     *
     * الفصل ضروري: `isInstalled()` تستدعي هذه لتفحص القاعدة المختومة، فلو
     * استدعتها هذه بدورها لدارت الدالتان على نفسيهما بلا نهاية.
     */
    protected static function readMarker(): array
    {
        $raw = @file_get_contents(self::markerPath());

        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
