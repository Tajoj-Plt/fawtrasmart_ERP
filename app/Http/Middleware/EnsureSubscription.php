<?php

namespace App\Http\Middleware;

use App\Models\FawtraSubscription;
use App\Services\Fawtra\FawtraCredentials;
use App\Services\Fawtra\SubscriptionSync;
use App\Services\Fawtra\UsagePush;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * لا عمل بلا اشتراك مسجَّل — على كل مسار لا على شاشةٍ بعينها.
 *
 * **لماذا وسيطٌ عامّ لا فحصٌ في المتحكّمات؟** لأن المنع الذي يُكتب في شاشةٍ
 * شاشة يُنسى في الشاشة الخمسين: يكفي مسارٌ واحد لم يُحرَس ليعمل النظام كلّه
 * من خلاله. والوسيط في المجموعة العامة يجعل السؤال يُطرح على كل طلب —
 * والاستثناء يُكتب صراحةً في قائمةٍ قصيرة تُقرأ في سطور.
 *
 * ─────────────────────── متى يُمنع؟ ───────────────────────
 *   • لا لقطة اشتراك في قاعدة البيانات إطلاقاً — النظام لم يُربط أو مُسح ربطه.
 *   • اللقطة تقول: منتهٍ، أو موقوف، أو غير نشط.
 *
 * ولا يُمنع لتقادم اللقطة: انقطاع الشبكة عن لوحة فوترة أسبوعاً لا يجوز أن
 * يوقف منشأةً تعمل — الوثيقة نفسها توصي بالعمل من آخر لقطة. والشاشة تُظهر
 * تحذير التقادم، والمهمّة الدورية تُصلحه.
 *
 * ─────────────────────── ما لا يُمنع ───────────────────────
 * الخروج، وتسجيل الدخول، وشاشتا الاشتراك — وإلا صار المنعُ حلقةً مغلقة:
 * مستخدمٌ ممنوع لا يستطيع بلوغ الشاشة التي يربط منها اشتراكه ولا حتى الخروج.
 */
class EnsureSubscription
{
    /**
     * مسارات تعمل بلا اشتراك — مطابقةٌ على المسار بلا بادئة اللغة.
     *
     * الشاشتان الأوليان هما مخرج المسؤول: منهما يربط أو يزامن أو يفتح
     * اللوحة. والباقي مصادقةٌ ومعالجُ تثبيت ونقطة صحّة.
     */
    protected array $except = [
        'subscription',
        'subscription/*',
        'settings/fawtra',
        'settings/fawtra/*',
        'fawtra',
        'fawtra/*',
        'subscription-required',

        // شاشة تحديث النظام: قاعدةٌ ببنيةٍ ناقصة قد تكسر شاشة الاشتراك نفسها،
        // فحجبُ التحديث خلف الاشتراك يصنع عقدةً لا تُحَلّ
        'system-update',
        'system-update/*',

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
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fawtra.enforce_subscription', true)) {
            return $next($request);
        }

        // الزائر غير المسجَّل ليس محلّ هذا الفحص: شاشة الدخول تتكفّل به،
        // ومنعُه هنا يُخفي شاشة الدخول نفسها
        if (! $request->user()) {
            return $next($request);
        }

        if ($this->isExcepted($request)) {
            return $next($request);
        }

        $record = $this->currentRecord();

        // كل تنقّل هو فرصة تحقّق: لا يُنتظر بها موعد المهمّة الدورية إن
        // كانت مستحقّة أصلاً — انظر `maybeTriggerAutoSync()` لماذا هذا لا
        // يبطئ الطلب ولا يستنزف حدّ الطلبات
        $this->maybeTriggerAutoSync($record);

        // وكل تنقّل فرصةُ تصريفٍ كذلك: ميزةٌ عُلِّمت ولم تُرفع لأن نافذة
        // الكبح كانت مفتوحة تبقى معلَّقة بلا من يعود إليها — انظر
        // `UsagePush::ensureDrain()`. الكلفة قراءةُ كاشٍ واحدة، والرفع نفسه
        // يقع بعد إرسال الاستجابة لا قبلها
        UsagePush::ensureDrain();

        $state = $this->state($record);

        if ($state === null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $state['message'],
                'code' => $state['code'],
            ], 402);
        }

        // ‏402 Payment Required: الرمز الصحيح لهذه الحالة، ويفرّقها عن 403
        // في السجلّات — «ممنوع لأن لا اشتراك» لا «ممنوع لأن لا صلاحية»
        return response()->view('errors.subscription-required', [
            'reason' => $state['message'],
            'code' => $state['code'],
            'record' => $state['record'],
            'dashboardUrl' => $state['record']?->link('subscription')
                ?: rtrim((string) config('fawtra.base_url'), '/'),
        ], 402);
    }

    /** لقطة الاشتراك المحفوظة، أو null إن كان الجدول غير مُهجَّر بعد. */
    protected function currentRecord(): ?FawtraSubscription
    {
        try {
            return FawtraSubscription::current();
        } catch (Throwable) {
            // الجدول غير مُهجَّر بعد (تركيبٌ في منتصفه) — لا يُقفل النظام
            // بسبب عطل بنيوي، فذاك يمنع إصلاحه أيضاً
            return null;
        }
    }

    /**
     * مزامنة فورية عند استحقاقها — لا تنتظر المهمّة الدورية وحدها.
     *
     * **لماذا؟** المهمّة الدورية (`fawtra:sync` كل ١٥ دقيقة) تعتمد على
     * جدولة النظام (`schedule:run` تُستدعى من كرون النظام أو مجدول ويندوز —
     * انظر CLAUDE.md «الجدولة: من القاعدة لا من الكود»). في تركيبٍ محلّي أو
     * استضافةٍ لم يُضبط فيها ذلك الاستدعاء بعد، لا تُشغَّل هذه المهمّة أبداً
     * ويبقى النظام على لقطة قديمة إلى ما لا نهاية مهما طال الوقت — وهذا هو
     * أصل المشكلة الحقيقي حين يُحذف الاشتراك ولا يزال النظام يعمل به.
     *
     * فهذا الوسيط الذي يمرّ عليه كل تنقّل يصير شبكة أمانٍ إضافية: إن حان
     * موعد المزامنة (`isDueForSync()`) يُشغّلها **بعد** إرسال الاستجابة
     * للمستخدم (`terminating`) — لا نداء شبكة يُضاف إلى زمن انتظار الصفحة —
     * وبقفلٍ قصير (٦٠ ثانية) يمنع تدافع عشرات الطلبات المتزامنة نحو نداءٍ
     * واحد.
     */
    protected function maybeTriggerAutoSync(?FawtraSubscription $record): void
    {
        try {
            if (! app(FawtraCredentials::class)->configured()) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $due = $record === null || $record->isDueForSync();

        if (! $due) {
            return;
        }

        if (! Cache::add('fawtra.sync.auto_trigger_lock', true, 60)) {
            return;
        }

        app()->terminating(function () {
            try {
                app(SubscriptionSync::class)->run();
            } catch (Throwable $e) {
                Log::warning('فوترة: تعذّرت المزامنة التلقائية من الوسيط', ['error' => $e->getMessage()]);
            }
        });
    }

    /**
     * سبب المنع، أو null حين يُسمح.
     *
     * القراءة من قاعدة البيانات مباشرةً لا من `SubscriptionGate`: تلك تفترض
     * النشاط حين لا ربط (`assume_active_when_unlinked`) كي لا تُقفل تركيباً
     * محلياً، وهذا الوسيط يريد العكس تماماً — لا ربط يعني لا دخول.
     *
     * @return array{message:string, code:string, record:?FawtraSubscription}|null
     */
    protected function state(?FawtraSubscription $record): ?array
    {
        if ($record === null || $record->snapshot === null) {
            return [
                'code' => 'NO_SUBSCRIPTION',
                'message' => 'لا يوجد اشتراك مسجَّل لهذا النظام. اربط النظام بلوحة تحكّم فوترة لتفعيله.',
                'record' => $record,
            ];
        }

        if ($record->is_expired) {
            return [
                'code' => 'SUBSCRIPTION_EXPIRED',
                'message' => 'انتهى اشتراكك'
                    .($record->end_date ? ' في '.$record->end_date->format('Y-m-d') : '')
                    .'. جدّده من لوحة تحكّم فوترة ليعود النظام إلى العمل.',
                'record' => $record,
            ];
        }

        if (! $record->is_active) {
            // `consecutive_failures > 0` هنا يعني أن هذه الحالة `is_active=false`
            // لم تأتِ من مزامنةٍ ناجحة قالت «غير نشط»، بل من قفلٍ محلّي فرضته
            // `SubscriptionSync::recordFailure()` بعد رفضٍ قاطع (انظر
            // `SubscriptionSync::shouldLock()`) — مزامنةٌ ناجحة تصفّر العدّاد
            // دائماً. فالرسالة هنا تنقل سبب الرفض الحقيقي لا وصفاً عاماً،
            // وتُطمئن أن المزامنة الدورية تحاول الاتصال تلقائياً
            if ($record->consecutive_failures > 0 && $record->last_error) {
                return [
                    'code' => 'SUBSCRIPTION_SYNC_LOCKED',
                    'message' => 'قُفل الاشتراك محلياً: '.$record->last_error.' يحاول النظام إعادة الاتصال بلوحة فوترة تلقائياً.',
                    'record' => $record,
                ];
            }

            return [
                'code' => 'SUBSCRIPTION_INACTIVE',
                'message' => 'اشتراكك غير نشط حالياً ('.$record->statusLabel().'). راجع لوحة تحكّم فوترة.',
                'record' => $record,
            ];
        }

        return null;
    }

    protected function isExcepted(Request $request): bool
    {
        // بادئة اللغة تُنزَع قبل المطابقة: `/ar/subscription` و`/subscription`
        // مسارٌ واحد، ومطابقةُ النصّ الخام تجعل الاستثناء يسقط بتبديل اللغة
        $path = ltrim($request->path(), '/');
        $segments = explode('/', $path);

        if (isset($segments[0]) && in_array($segments[0], config('laravellocalization.supportedLocales')
            ? array_keys(config('laravellocalization.supportedLocales')) : [], true)) {
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
