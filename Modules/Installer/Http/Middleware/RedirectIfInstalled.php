<?php

namespace Modules\Installer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Installer\Services\InstallationState;
use Symfony\Component\HttpFoundation\Response;

/**
 * يقفل معالج التثبيت بعد تمامه.
 *
 * تركُه مفتوحاً ثغرةٌ لا مجرّد فوضى: صفحاته تكتب `.env` وتُشغّل
 * `migrate:fresh` وتُنشئ مستخدماً بكل الصلاحيات — كلّها بلا مصادقة، لأن
 * المصادقة قبل التثبيت مستحيلة. فالحاجز الوحيد هو ألّا تُفتح بعده.
 *
 * لإعادة التثبيت عمداً: احذف `storage/app/installed` من الخادم — فعلٌ
 * يتطلّب وصولاً إلى الملفات، لا مجرّد معرفة الرابط.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallationState::isInstalled()) {
            return $next($request);
        }

        // شاشة الإتمام تُعرض لمن أتمّ التثبيت في هذه الجلسة وحده.
        //
        // بدون هذا الاستثناء ينتهي التثبيت بنجاح ثم يُقذف صاحبه إلى الجذر
        // بلا رابط دخول ولا ملخّص — وأسوأ ما في المعالج أن يُخفي نتيجته
        if ($request->routeIs('installer.finished') && $request->session()->get('installer.completed')) {
            return $next($request);
        }

        $message = $this->reason();

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return redirect('/')->with('error', $message);
    }

    /**
     * سبب القفل كما هو، لا صيغةٌ واحدة لكل الحالات.
     *
     * الرسالة القديمة كانت تقول «احذف storage/app/installed» دائماً، وهي
     * نصف الحقيقة: إن كانت القاعدة تحمل مستخدمين فالعلامة تُكتب من جديد في
     * أول طلب بعد الحذف — فيعيد المستخدم الكرّة ويظنّ النظام معطوباً. فإمّا
     * أن يُقال له السبب الفعليّ وإمّا فلا يُقال شيء.
     */
    protected function reason(): string
    {
        if (InstallationState::wasDetectedFromDatabase()) {
            return 'قاعدة البيانات المضبوطة تحمل تركيباً قائماً (مستخدمون وشجرة حسابات)، '
                .'فحذف storage/app/installed وحده لا يفتح المعالج — تُكتب العلامة من جديد. '
                .'للتثبيت من الصفر: php artisan install:reset --fresh، أو وجّه DB_DATABASE إلى قاعدة فارغة.';
        }

        return 'النظام مثبَّت بالفعل. لإعادة فتح المعالج: php artisan install:reset '
            .'(أو احذف storage/app/installed من الخادم).';
    }
}
