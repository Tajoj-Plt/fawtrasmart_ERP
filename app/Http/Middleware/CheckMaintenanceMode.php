<?php

namespace App\Http\Middleware;

use App\Services\Maintenance\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * وضع الصيانة مفعَّل ⇐ لا يدخل النظام إلا من سُمّي.
 *
 * ── لماذا لا يكفي `php artisan down`؟ ──
 *
 * ذاك يردّ كلَّ طلبٍ قبل أن تُقرأ الجلسة، فلا يعرف من الطارق — ومخرجُه رابطٌ
 * سرّي يُعطى لمن يُراد له الدخول. والمطلوب هنا غيرُه: يبقى النظام كاملاً
 * لحساباتٍ بعينها، ويُغلق على الباقين. فالفحص يحتاج جلسةً ومستخدماً، وموضعُه
 * إذن داخل مجموعة `web` بعد `StartSession` لا قبلها.
 *
 * ── موضعُه في الصفّ، ولماذا هو الأوّل ──
 *
 * أوّلُ ما يُلحق بالمجموعة، قبل `EnsureModuleEnabled` و`EnsureSubscription`
 * و`CheckSystemUpdates`. وكلٌّ من هؤلاء يحوّل المستخدم إلى شاشته الخاصّة —
 * شاشةِ التطبيقات، أو الاشتراك، أو التحديث. ولو سبق أحدُها لرأى الموظّفُ
 * المحجوبُ شاشةَ اشتراكٍ أو تحديثٍ لا تخصّه، وهي شاشاتٌ داخل النظام أصلاً.
 * الإقفالُ يسبق كلَّ ما يُعرَض داخل المقفَل.
 *
 * ── ما يمرّ رغم الإقفال ──
 *
 * • **شاشة الدخول** — وإلا لما استطاع المسموحُ له أن يدخل ليطفئ الوضع. ودخولُ
 *   من ليس مسموحاً له لا يفتح له شيئاً: أوّلُ طلبٍ بعد الدخول يمرّ من هنا،
 *   فيُخرَج من جلسته وتُعرَض عليه صفحةُ الصيانة.
 * • **الخروج** — من كان داخلاً قبل الإقفال يجب أن يستطيع إنهاء جلسته.
 * • **شاشة وضع الصيانة نفسها** — وإلا صار الإقفال بلا زرِّ إطفاء.
 * • **`/up`** لفحص حياة الخادم، و**`install`** لمعالج التثبيت، و**تبديل اللغة**.
 * • **من يحمل مفتاح الطوارئ** في العنوان أو في جلسته.
 *
 * ولا تُستثنى شاشةُ النسخ الاحتياطي هنا (بخلاف `CheckSystemUpdates`): الصيانة
 * تُفعَّل بقرارٍ من مسؤولٍ هو نفسُه في قائمة المسموح لهم، فالشاشة مفتوحةٌ له
 * أصلاً كسائر النظام.
 */
class CheckMaintenanceMode
{
    /**
     * مساراتٌ تمرّ دائماً — مطابقةٌ بلا بادئة اللغة، كما في `CheckSystemUpdates`.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'login',
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
        // شاشة الوضع نفسها وزرّ إطفائه
        'maintenance-mode',
        'maintenance-mode/*',
    ];

    public function __construct(protected MaintenanceModeService $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->maintenance->isActive()) {
                return $next($request);
            }
        } catch (Throwable) {
            // فحصُ الإقفال لا يجوز أن يُقفل النظام بعطلٍ فيه هو
            return $next($request);
        }

        // مفتاح الطوارئ في العنوان: يُختم في الجلسة ثم يُنظَّف من الرابط، كي
        // لا يبقى السرّ في شريط العنوان ولا في سجلّ الخادم لكل صفحةٍ بعده
        if ($this->maintenance->matchesSecret($request->query(MaintenanceModeService::BYPASS_QUERY_KEY))) {
            $this->maintenance->grantBypass();

            return redirect()->to($request->url());
        }

        if ($this->maintenance->hasBypass() || $this->isExcepted($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($this->maintenance->allows($user)) {
            return $next($request);
        }

        /*
            دخل ثم تبيّن أنه ليس من المسموح لهم ⇒ تُنهى جلسته.

            وإلا بقيت جلسةٌ مفتوحةً لمحجوبٍ طوال مدّة الصيانة، تصطدم بهذه
            الصفحة في كل نقرة ولا تنتهي. وإنهاؤها يجعل حاله كحال أي زائر:
            يرى صفحة الصيانة، وإن جُرّب الدخول ثانيةً بعد الانتهاء دخل.

            والزائر لا يُخرَج من شيء — لا جلسةَ له.
        */
        if ($user !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->block($request);
    }

    /**
     * الردّ على المحجوب — `503` لا `403`.
     *
     * الفرق ليس شكليّاً: `403` تقول «ليس لك أن تدخل»، و`503` تقول «الخدمة
     * متوقّفةٌ مؤقتاً» — وهي الحقيقة هنا. ومحرّكات البحث والمراقِبات تفهم
     * الثانية على أنها حالٌ عابرة فلا تُسقط الصفحة من فهرسها ولا تُطلق إنذار
     * عطلٍ دائم، و`Retry-After` تقول لها متى تعود.
     */
    protected function block(Request $request): Response
    {
        $setting = $this->maintenance->current();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $setting->displayMessage(),
                'maintenance' => true,
            ], 503)->header('Retry-After', 3600);
        }

        return response()
            ->view('errors.maintenance', ['setting' => $setting], 503)
            ->header('Retry-After', 3600);
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

        // الجذر بعد إسقاط بادئة اللغة يصير نصّاً فارغاً لا `/`
        $path = $path === '' ? '/' : $path;

        foreach ($this->except as $pattern) {
            if ($path === $pattern || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
