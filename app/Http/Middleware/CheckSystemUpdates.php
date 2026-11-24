<?php

namespace App\Http\Middleware;

use App\Services\Maintenance\SystemUpdateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ترحيلٌ معلَّق ⇐ شاشة «تحديث النظام» قبل أي شاشة أخرى.
 *
 * **لماذا؟** التركيب على استضافةٍ مشتركة بلا SSH يُحدَّث برفع الملفّات
 * بالـFTP، ولا شيء بعدها يشغّل الترحيلات. فيبقى النظام يعمل على بنيةٍ
 * قديمة: الشاشة الجديدة تُعطي «عمود غير موجود»، والحقل الجديد يُحفظ ولا
 * يظهر، والصلاحية الجديدة تُخفي بندَها عن الجميع. والعطل لا يُنسب إلى
 * سببه — يظهر بعد أيام في شاشةٍ لا علاقة لها بما رُفِع.
 *
 * فالوسيط يجعل الاكتشاف تلقائياً: أوّل صفحة تُفتَح بعد رفع التحديث هي
 * شاشة التحديث نفسها، لا شاشةٌ ستنهار بعد ثلاث نقرات.
 *
 * **وحتى صفحة الدخول تُحوَّل.** كان الزائر مستثنى، فكان المسؤول يرى شاشة
 * دخولٍ عادية ثم يُحوَّل بعدها — أي أن النظام يُفتَح صفحةً قبل أن يُحدَّث.
 * والآن: أوّلُ ما يُعرَض على أي زائرٍ ما دام ترحيلٌ معلَّقاً هو شاشة التحديث.
 * وحتى تكون بلوغها ممكناً بلا حلقةٍ مغلقة أُخرجت مساراتُها من مجموعة `auth`
 * إلى حارسها الخاصّ `AllowPendingUpdateAccess` — وهو يحصر ما يستطيعه الزائر
 * في تشغيل الترحيلات وحدها، ويقفل الشاشة فور انتهائها.
 *
 * ───────────────────────── ما لا يُحوَّل ─────────────────────────
 * • كل ما ليس `GET` وكل ما يتوقّع JSON: تحويلُ نداء AJAX إلى صفحة HTML
 *   يُنتج عطلاً غامضاً في الواجهة بدل رسالةٍ مفهومة.
 * • شاشة التحديث نفسها (وإلا صار التحويل حلقةً مغلقة)، وشاشة **النسخ
 *   الاحتياطي** — الترحيل لا يُشغَّل قبل نسخة، فمنعُ الوصول إليها يجعل
 *   النصيحة مستحيلة التنفيذ. والخروج والتثبيت وتبديل اللغة.
 * • من أجّل التحديث بزرّ «متابعة الآن»: الجلسة تحمل مهلةً قصيرة. بلا هذا
 *   المخرج يُحتجَز موظّفٌ لا يملك قرار الترحيل في شاشةٍ لا يستطيع تجاوزها.
 *
 * وفي كل الأحوال — محوَّلاً كان الطلب أو مؤجَّلاً — يُشارَك عدد الترحيلات
 * المعلّقة مع كل قالب باسم `$systemUpdatePending`، فيظهر الشريط في أعلى
 * الشاشات لمن أجّل. (`layouts.partials.system-update-banner`)
 */
class CheckSystemUpdates
{
    /** مفتاح تأجيل التحويل في الجلسة. */
    public const SNOOZE_KEY = 'system_update.snoozed_until';

    /** مدّة التأجيل بالدقائق — قصيرةٌ عمداً: التأجيل ليس إلغاءً. */
    public const SNOOZE_MINUTES = 30;

    /**
     * مسارات لا تُحوَّل — مطابقةٌ على المسار بلا بادئة اللغة، كما في
     * `EnsureSubscription`.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'system-update',
        'system-update/*',
        // النسخة الاحتياطية شرطُ الترحيل لا خصمُه
        'backup',
        'backup/*',
        'accountInfo/backup',
        'accountInfo/backup/*',

        // `login` ليست هنا عمداً — طلبُ صاحب النظام: «ما يوديني حتى صفحة
        // حتى ولو تسجيل الدخول». أمّا `logout` فتبقى: من دخل قبل رفع
        // التحديث يجب أن يستطيع الخروج
        'logout',
        'register',
        'password/*',
        'forgot-password',
        'reset-password/*',
        'install',
        'install/*',
        'up',
        'lang/*',
        'locale/*',

        // بوّابة العميل ليست شاشةَ إدارة: عميلٌ يفتح حسابه لا يُعرَض عليه
        // ترحيلٌ معلَّق ولا يملك تشغيله
        'client-portal',
        'client-portal/*',
    ];

    public function __construct(protected SystemUpdateService $updates) {}

    public function handle(Request $request, Closure $next): Response
    {
        // الزائر لم يعُد مستثنى — انظر رأس الصنف. ويبقى استثناءٌ واحد:
        // بوّابة العملاء لها حارسها وشاشاتُها، ومستخدمُها ليس من يشغّل
        // ترحيلاً على قاعدة بيانات المنشأة
        if ($request->user('client') !== null && ! $request->user()) {
            return $next($request);
        }

        try {
            $pending = $this->updates->pendingMigrationCount();
        } catch (Throwable) {
            // فحصُ التحديث لا يجوز أن يُسقط النظام الذي جاء يحرسه
            return $next($request);
        }

        View::share('systemUpdatePending', $pending);

        if ($pending === 0) {
            return $next($request);
        }

        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return $next($request);
        }

        if ($this->isExcepted($request) || $this->isSnoozed($request)) {
            return $next($request);
        }

        // وحدة الإعدادات معطَّلة في `modules_statuses.json` ⇐ لا مسار ولا
        // شاشة. التحويل إلى مسارٍ غير مسجَّل يرمي في كل صفحة، فيتحوّل
        // الحارسُ إلى عطلٍ يُسقط النظام كلَّه
        if (! Route::has('SystemUpdate.index')) {
            return $next($request);
        }

        /* تُكتب مرّةً ولا تُدهَس: المستخدم بعد التحويل قد يتنقّل داخل شاشة
           التحديث نفسها (يفتحها ثم يعود إليها)، فكلُّ طلبٍ جديدٍ يكتب فوقها
           يُضيّع الوجهةَ الأصلية ويُبقي آخر عنوانٍ لا معنى له. */
        if (! $request->session()->has(self::INTENDED_KEY)) {
            $request->session()->put(self::INTENDED_KEY, $request->fullUrl());
        }

        return redirect()->route('SystemUpdate.index')->with(
            'update_required',
            'رُفِعت ملفّات تحديثٍ جديدة إلى النظام و'.$pending.' ترحيلاً بانتظار التنفيذ. '
            .'شغّل التحديث من هذه الشاشة قبل متابعة العمل — الشاشات الجديدة قد تُعطي أخطاءً حتى يُنفَّذ.'
        );
    }

    /**
     * مفتاح الجلسة الذي يحفظ الصفحة التي قُطع المستخدم عندها.
     *
     * `SystemUpdateController` يعيده إليها بعد نجاح التحديث. بدونه كان
     * المستخدم يُقذف إلى شاشة التحديث من منتصف عمله ثم يُترك على الشاشة
     * نفسها بعد الانتهاء، فيبحث عن طريق العودة بيده — ومن قُطع وهو يحرّر
     * فاتورةً لا يتذكّر أين كان.
     */
    public const INTENDED_KEY = 'system_update.intended_url';

    /** هل أجّل المستخدم التحويل، ومهلته سارية؟ */
    protected function isSnoozed(Request $request): bool
    {
        $until = $request->session()->get(self::SNOOZE_KEY);

        return $until !== null && (int) $until > time();
    }

    /** مطابقة المسار على قائمة الاستثناء، بلا بادئة اللغة. */
    protected function isExcepted(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        $segments = explode('/', $path);
        $locales = array_keys((array) config('laravellocalization.supportedLocales', []));

        if (isset($segments[0]) && in_array($segments[0], $locales, true)) {
            array_shift($segments);
            $path = implode('/', $segments);
        }

        foreach ($this->except as $pattern) {
            if ($path === $pattern || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
