<?php

namespace App\Listeners;

use App\Models\SessionLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Log;

/**
 * يكتب سجلّ الجلسات من أحداث المصادقة.
 *
 * **لماذا من الأحداث لا من `AuthController`؟**
 *
 * لأن الدخول يقع من أكثر من مسار: `AuthController` و
 * `AuthenticatedSessionController` و`LoginController`، وإعادة تعيين
 * كلمة المرور تسجّل الدخول أيضاً، و«تذكّرني» يدخل المستخدم بلا مرور
 * بأيٍّ منها. الحدث `Login` تُطلقه Laravel في كل هذه الحالات، فالتسجيل
 * عنده يغطّيها جميعاً — وأيّ مسار دخول يُضاف مستقبلاً يُسجَّل بلا تعديل.
 *
 * **ولماذا كلّ شيء داخل `try`؟**
 *
 * لأن فشل التسجيل يجب ألّا يمنع الدخول أو الخروج. مستخدم لا يستطيع
 * الدخول لأن سطراً في سجلّ لم يُكتب عطلٌ أشدّ من سجلٍّ ناقص.
 */
class RecordSessionActivity
{
    /**
     * **لا تُسمَّ هذه التوابع `handleX`.**
     *
     * الاكتشاف التلقائي للأحداث يلتقط كل تابع عامّ اسمه يبدأ بـ `handle`
     * وأول وسيطه مُلمَّح بنوع حدث، ويسجّله بنفسه. ومزوّدو الأحداث في
     * الوحدات العشرين يفعّلون الاكتشاف دون تخصيص مجلّده، فيمسحون
     * `app/Listeners` هذا. فلو سُمّيت `handleLogin` لسُجّلت مرّتين:
     * مرّةً من `EventServiceProvider` صراحةً ومرّةً بالاكتشاف — وصفّان
     * لكل دخول في سجلّ الجلسات. البادئة `on` تُخرجها من نمط الاكتشاف،
     * فيبقى التسجيل الصريح وحده.
     */

    /**
     * مفتاح رقم الصفّ داخل الجلسة.
     *
     * مُعرّف الجلسة وحده لا يكفي رابطاً: `AuthenticatedSessionController`
     * يستدعي `session()->regenerate()` بعد الدخول، فيتغيّر المُعرّف الذي
     * سُجّل ويصير الصفّ يتيماً — لا يُغلق عند الخروج ويظهر «مفتوحاً» أبداً.
     * محتوى الجلسة ينجو من إعادة التوليد، فالرقم المحفوظ فيه يبقى صحيحاً.
     */
    public const KEY = 'session_log_id';

    /** يفتح صفّاً جديداً عند نجاح الدخول */
    public function onLogin(Login $event): void
    {
        $this->guard(function () use ($event) {
            $request = request();
            $session = $request?->hasSession() ? $request->session() : null;

            // حدثان لجلسة واحدة يقعان أحياناً (دخول يعقبه دخول آخر في
            // الطلب نفسه) — الصفّ الموجود لا يُكرَّر
            if ($session?->has(self::KEY)) {
                return;
            }

            $agent = $request?->userAgent();

            $row = SessionLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'attempted_email' => $event->user->email ?? null,
                'session_id' => $this->sessionId(),
                'status' => 'active',
                'login_at' => now(),
                'last_seen_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $agent,
                'guard' => $event->guard,
                'remembered' => (bool) $event->remember,
            ] + SessionLog::parseAgent($agent));

            $session?->put(self::KEY, $row->id);
        });
    }

    /** يغلق الصفّ عند الخروج بطلب المستخدم */
    public function onLogout(Logout $event): void
    {
        $this->guard(function () use ($event) {
            $request = request();
            $session = $request?->hasSession() ? $request->session() : null;

            $this->close(
                logId: $session?->pull(self::KEY),
                userId: $event->user?->getAuthIdentifier(),
                sessionId: $this->sessionId(),
                reason: 'manual'
            );
        });
    }

    /** الخروج من بقية الأجهزة يُغلق صفوفها لا الصفّ الحالي */
    public function onOtherDeviceLogout(OtherDeviceLogout $event): void
    {
        $this->guard(function () use ($event) {
            $current = $this->sessionId();

            SessionLog::open()
                ->where('user_id', $event->user?->getAuthIdentifier())
                ->when($current, fn ($query) => $query->where('session_id', '!=', $current))
                ->get()
                ->each(fn (SessionLog $row) => $this->finish($row, 'other_device'));
        });
    }

    /**
     * محاولة فاشلة.
     *
     * كلمة المرور المكتوبة لا تُلمس ولا تُسجَّل — ولا تُخزَّن حتى مُجزَّأة:
     * سجلٌّ أمنيّ يحفظ ما حاول به المهاجم يصير هو نفسه ثغرة.
     */
    public function onFailed(Failed $event): void
    {
        $this->guard(function () use ($event) {
            $request = request();
            $agent = $request?->userAgent();

            SessionLog::create([
                'user_id' => $event->user?->getAuthIdentifier(),
                'attempted_email' => $event->credentials['email'] ?? ($event->user->email ?? null),
                'status' => 'failed',
                'login_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $agent,
                'guard' => $event->guard,
            ] + SessionLog::parseAgent($agent));
        });
    }

    /** تجاوز حدّ المحاولات — يُسجَّل مرّة واحدة عند وقوع الحظر */
    public function onLockout(Lockout $event): void
    {
        $this->guard(function () use ($event) {
            $request = $event->request ?: request();
            $agent = $request?->userAgent();

            SessionLog::create([
                'attempted_email' => $request?->input('email'),
                'status' => 'locked',
                'login_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $agent,
            ] + SessionLog::parseAgent($agent));
        });
    }

    // ==================== الداخل ====================

    /**
     * يغلق الجلسة المفتوحة.
     *
     * ثلاث محاولات مرتّبة بالدقّة: الرقم المحفوظ في الجلسة، ثم مُعرّف
     * الجلسة، ثم آخر صفّ مفتوح للمستخدم. التدرّج مقصود — الجلسة تُبطَل
     * أحياناً قبل إطلاق الحدث فلا يبقى مفتاح ولا مُعرّف، وبدون الاحتياط
     * الأخير تبقى صفوف مفتوحة في السجل إلى الأبد.
     */
    protected function close(?int $logId, ?int $userId, ?string $sessionId, string $reason): void
    {
        $row = null;

        // الرقم المحفوظ في الجلسة أدقّ ما لدينا
        if ($logId) {
            $row = SessionLog::open()->find($logId);
        }

        if (! $row && $sessionId) {
            $row = SessionLog::open()->where('session_id', $sessionId)->latest('id')->first();
        }

        // آخر احتياط: الجلسة قد تُبطَل قبل إطلاق الحدث فلا يبقى مفتاح ولا
        // مُعرّف. بدون هذا يبقى الصفّ مفتوحاً في السجل إلى الأبد
        if (! $row && $userId) {
            $row = SessionLog::open()->where('user_id', $userId)->latest('id')->first();
        }

        if ($row) {
            $this->finish($row, $reason);
        }
    }

    protected function finish(SessionLog $row, string $reason): void
    {
        $now = now();

        $row->update([
            'status' => 'ended',
            'logout_reason' => $reason,
            'logout_at' => $now,
            'last_seen_at' => $now,
            'duration_seconds' => $row->login_at ? max(0, $now->diffInSeconds($row->login_at, true)) : null,
        ]);
    }

    /**
     * مُعرّف الجلسة الحالي، أو `null` في سياق بلا جلسة (أمر مجدول، واجهة
     * برمجية) — وهو سياق يقع فيه الدخول فعلاً في المهامّ المجدولة.
     */
    protected function sessionId(): ?string
    {
        try {
            $request = request();

            return $request?->hasSession() ? $request->session()->getId() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** يبتلع أي خطأ: السجل لا يجوز أن يمنع الدخول أو الخروج */
    protected function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('تعذّر تسجيل نشاط الجلسة', ['error' => $e->getMessage()]);
        }
    }
}
