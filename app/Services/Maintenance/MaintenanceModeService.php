<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceModeSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * وضع الصيانة: النظام مقفلٌ إلا على من سُمّي.
 *
 * **متى يُستعمل؟** حين يُراد العمل على النظام بلا مستخدمين فيه: ترحيلٌ كبير،
 * أو استرجاعُ نسخةٍ احتياطية، أو تصحيحُ بياناتٍ يدوي. تشغيلُ أيٍّ من هذه
 * والموظّفون يُصدرون فواتير يعني بياناتٍ تُكتب في أثناء العملية فتضيع أو
 * تتناقض — والفاتورة التي كُتبت أثناء الاسترجاع لا يعرف أحدٌ أنها فُقدت.
 *
 * **وهو غير `php artisan down`.** ذاك يُسقط التطبيق كلَّه قبل أن يبلغ الجلسةَ
 * والقاعدة، فلا يعرف من أنت أصلاً — ومخرجُه الوحيد رابطٌ سرّي. وهذا يعرف
 * المستخدم: يقرأ الجلسة، ويسأل «أهذا الحساب من المسموح لهم؟». فيبقى المسؤول
 * يعمل في نظامٍ كامل بينما هو مقفلٌ على الباقين — وهو المطلوب هنا.
 *
 * ── الحالة تُقرأ على كل طلب، فمن أين تُقرأ؟ ──
 *
 * من الكاش. الوسيط يسأل هذا السؤال في كل طلبٍ يصل النظام، واستعلامٌ لكل طلبٍ
 * ثمنٌ يُدفع دائماً لأجل حالةٍ تتغيّر مرّتين في السنة. والمدّة طويلة عمداً،
 * لأن الصحّة لا تُبنى عليها: كلُّ كتابةٍ تمرّ بـ`enable()`/`disable()` وكلتاهما
 * تُسقط المفتاح فوراً. فالكاش هنا لا يتأخّر عن الحقيقة، وإنما يوفّر تكرارها.
 */
class MaintenanceModeService
{
    private const CACHE_KEY = 'maintenance_mode.state';

    private const CACHE_TTL = 3600;

    /** مفتاح الجلسة الذي يحمل تجاوزَ الطوارئ بعد استعمال الرابط السرّي. */
    public const BYPASS_SESSION_KEY = 'maintenance_mode.bypass';

    /** اسم معامل العنوان الذي يحمل المفتاح السرّي. */
    public const BYPASS_QUERY_KEY = 'maintenance_key';

    /** ذاكرةُ الطلب الواحد — الوسيط والقالب يسألان الحالة نفسها. */
    private ?MaintenanceModeSetting $memo = null;

    /**
     * الحالة الحالية.
     *
     * ولا ترمي أبداً: قارئُها وسيطٌ يعمل على كل طلب، وقاعدةٌ لم تُهجَّر بعد
     * (الجدول غير موجود) أو كاشٌ معطَّل يجب أن يعنيا «النظام مفتوح» لا صفحةَ
     * خطأٍ في كل مسار. القفلُ فعلٌ يُطلَب صراحةً، لا نتيجةُ عطل.
     */
    public function current(): MaintenanceModeSetting
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            // مصفوفةٌ فارغة لا `null` حين لا صفّ: `Cache::remember` تعامل
            // `null` المخزَّنة كأنها غيابٌ عن الكاش، فتُعيد الاستعلام على كل
            // طلبٍ في نظامٍ لم يُهجَّر بعدُ — وهو أكثر ما يكون فيه ذلك
            $attributes = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn () => Schema::hasTable('maintenance_mode_settings')
                    ? (optional(MaintenanceModeSetting::query()->orderBy('id')->first())->getAttributes() ?? [])
                    : []
            );
        } catch (Throwable) {
            $attributes = [];
        }

        $setting = new MaintenanceModeSetting;

        if (is_array($attributes) && $attributes !== []) {
            // `setRawAttributes` لا `fill`: الصفّ يُعاد من الكاش بقيمه الخام
            // كما هي في القاعدة، والتحويلات (`casts`) تعمل عليها بعدُ. أمّا
            // `fill` فتحترم `$fillable` فتُسقط `id` وتُبقي الصفّ بلا هويّة
            $setting->setRawAttributes($attributes, true);
            $setting->exists = true;
        }

        return $this->memo = $setting;
    }

    public function isActive(): bool
    {
        return $this->current()->is_active === true;
    }

    /**
     * هل يمرّ هذا المستخدم؟
     *
     * الزائر لا يمرّ: لا اسمَ له يُطابَق على القائمة. وشرطُ «الوضع مفعَّل»
     * مقروءٌ هنا لا في المستدعي، فتُقرأ الدالّة كما تُقرأ في الوسيط: من يُسمح له؟
     */
    public function allows(?User $user): bool
    {
        if (! $this->isActive()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return in_array((int) $user->id, $this->current()->allowedIds(), true);
    }

    /**
     * تفعيل الوضع.
     *
     * **من يفعّل يبقى داخلاً — دائماً.** إضافةُ المفعِّل إلى القائمة قسراً لا
     * اعتماداً على أن يختار نفسه: الشاشة تعرض قائمةَ مستخدمين، ومن يقفل النظام
     * ناسياً أن يعلّم نفسه يُقفله على نفسه — وهو على استضافةٍ بلا سطر أوامر لا
     * يملك بعدها إلا الرابط السرّي. فالنسيان هنا لا يُترك ممكناً أصلاً.
     *
     * والمفتاح السرّي يُولَّد مرّةً ويبقى: تبديلُه مع كل تفعيلٍ يُبطل الرابط
     * الذي حفظه المسؤول عنده، وهو رابطُ الطوارئ الذي لا يُطلَب إلا حين لا
     * يعمل غيره.
     *
     * @param  array<int, int|string>  $userIds
     */
    public function enable(array $userIds, ?string $message, ?int $activatedBy): MaintenanceModeSetting
    {
        $allowed = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($activatedBy !== null && ! in_array($activatedBy, $allowed, true)) {
            $allowed[] = $activatedBy;
        }

        return $this->write([
            'is_active' => true,
            'message' => $message,
            'allowed_user_ids' => $allowed,
            'secret' => $this->current()->secret ?: Str::random(48),
            'activated_by' => $activatedBy,
            'activated_at' => now(),
        ]);
    }

    /**
     * إيقاف الوضع.
     *
     * القائمة والرسالة تبقيان: من أقفل النظام لصيانةٍ يقفله ثانيةً لصيانةٍ
     * أخرى بالمجموعة نفسها، ومسحُها يعني إعادةَ اختيار المستخدمين في كل مرّة.
     */
    public function disable(): MaintenanceModeSetting
    {
        return $this->write([
            'is_active' => false,
            'activated_at' => null,
        ]);
    }

    /** تعديل القائمة والرسالة والوضعُ مفعَّل — بلا إعادة تفعيل. */
    public function updateAllowed(array $userIds, ?string $message, ?int $activatedBy): MaintenanceModeSetting
    {
        $allowed = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($activatedBy !== null && ! in_array($activatedBy, $allowed, true)) {
            $allowed[] = $activatedBy;
        }

        return $this->write([
            'message' => $message,
            'allowed_user_ids' => $allowed,
        ]);
    }

    /**
     * هل تحمل هذه الجلسة تجاوزَ الطوارئ؟
     *
     * التجاوز يُمنح للجلسة لا للطلب: لو لزم حملُ المفتاح في كل عنوان لتسرّب
     * في كل رابطٍ يُنسَخ ويُشارَك. فيُقرأ مرّةً من العنوان، ويُختم في الجلسة،
     * ويُنسى من شريط العنوان.
     */
    public function grantBypass(): void
    {
        session()->put(self::BYPASS_SESSION_KEY, $this->current()->secret);
    }

    public function hasBypass(): bool
    {
        $secret = $this->current()->secret;

        if (! $secret) {
            return false;
        }

        return hash_equals($secret, (string) session(self::BYPASS_SESSION_KEY));
    }

    /** أهذا هو المفتاح السرّي؟ — مقارنةٌ ثابتةُ الزمن، فهو سرٌّ يُخمَّن. */
    public function matchesSecret(?string $candidate): bool
    {
        $secret = $this->current()->secret;

        return $secret !== null && $candidate !== null && hash_equals($secret, $candidate);
    }

    /** توليد مفتاحٍ جديد — يُبطل كل رابطٍ قديم وكلَّ جلسةٍ تحمل تجاوزاً. */
    public function rotateSecret(): MaintenanceModeSetting
    {
        return $this->write(['secret' => Str::random(48)]);
    }

    /** رابط الطوارئ كاملاً — يُعرَض في الشاشة ليُحفَظ خارج النظام. */
    public function bypassUrl(): ?string
    {
        $secret = $this->current()->secret;

        return $secret ? url('/').'?'.self::BYPASS_QUERY_KEY.'='.$secret : null;
    }

    /**
     * الكتابة والإسقاط معاً — لا كتابةَ بلا إسقاطٍ للكاش.
     *
     * جُمعتا في دالّةٍ واحدة لأن فصلَهما عطلٌ صامت: يُحفظ الصفّ ولا يُسقط
     * المفتاح، فيبقى الوسيط يقرأ الحالة القديمة ساعةً كاملة — يُطفَأ الوضع
     * فيبقى النظام مقفلاً، أو يُقفَل فيبقى مفتوحاً.
     */
    private function write(array $attributes): MaintenanceModeSetting
    {
        $setting = MaintenanceModeSetting::query()->orderBy('id')->first()
            ?? new MaintenanceModeSetting;

        $setting->fill($attributes)->save();

        $this->memo = null;
        Cache::forget(self::CACHE_KEY);

        return $setting;
    }
}
