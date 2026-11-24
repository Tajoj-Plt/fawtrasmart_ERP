<?php

namespace App\Http\Middleware;

use App\Models\EmployeeClientVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class CheckIncompleteVisits
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // ✅ إذا كان المستخدم موظف (استخدام isEmployee() بدلاً من الدور الثابت)
        if (! Auth::check() || ! Auth::user()->isEmployee()) {
            return $next($request);
        }


        $user = Auth::user();

        // ✅ التحقق من صلاحية الإعفاء من التبريرات - إذا معفى نسمح له بالمرور مباشرة
        if (hasPermission('staff_visit_justification_exempt')) {
            return $next($request);
        }

        // تخطي الفحص للروتات المحددة
        if ($this->shouldSkipCheck($request)) {
            return $next($request);
        }

        try {
            // التحقق من وجود الجدول أولاً
            $tableExists = \Illuminate\Support\Facades\Schema::hasTable('employee_client_visits');

            if (! $tableExists) {
                \Log::warning('Middleware: employee_client_visits table does not exist');

                return $next($request);
            }

            // ✅ البحث عن الزيارات غير المكتملة التي تحتاج تبرير
            // ⚠️ مهم: المقارنة بناءً على visit_date (متى تم تسجيل الزيارة)
            // ⚠️ الأمس وما قبله تحتاج تبرير (اليوم والمستقبل لا تحتاج)

            // 1. زيارات غير مكتملة بدون تبريرات نهائياً (الأمس وما قبله فقط)
            //    ولا تُحسَب إن غطّتها فترة تبريرٍ معتمدة — راجع
            //    EmployeeClientVisit::needingJustificationQuery() ولماذا هذا
            //    الاستثناء هو إصلاح مشكلة «تغيير الصلاحية يعيد فتح الفترة».
            $incompleteWithoutJustification = EmployeeClientVisit::needingJustificationQuery($user->id)->count();

            // 2. تبريرات معلقة بانتظار الموافقة (الأمس وما قبله فقط) — نفس الاستثناء.
            $pendingJustifications = EmployeeClientVisit::pendingApprovalQuery($user->id)->count();

            // إجمالي ما يحتاج انتباه (الأمس وما قبله فقط - بناءً على visit_date)
            $totalPending = $incompleteWithoutJustification + $pendingJustifications;

            // إذا كانت هناك زيارات غير مكتملة أو تبريرات معلقة
            if ($totalPending > 0) {
                // السماح بالذهاب إلى صفحات التبريرات والخروج والـ API فقط
                if ($this->isEscapeRoute($request) ||
                    $request->is('api/*') ||
                    $request->ajax()) {
                    return $next($request);
                }

                // إعادة التوجيه إلى صفحة التبريرات مع رسالة واضحة
                $message = 'لديك '.$incompleteWithoutJustification.' زيارات غير مكتملة و '.
                           $pendingJustifications.' تبرير بانتظار الموافقة. '.
                           'يجب إكمال التبريرات قبل متابعة العمل.';

                return redirect()
                    ->route('incomplete.visits.justification')
                    ->with('warning', '⚠️ '.$message)
                    ->with('returnToHome', true)
                    ->with('incompleteCount', $incompleteWithoutJustification)
                    ->with('pendingCount', $pendingJustifications);
            }

            return $next($request);
        } catch (\Exception $e) {
            \App\Support\OperationLog::failure($e, 'VisitJustification', 'check_incomplete_visits', Auth::id());

            // في حالة الخطأ، اسمح بالمرور (حتى لا نعطل التطبيق)
            return $next($request);
        }
    }

    /**
     * Determine if the check should be skipped for specific routes.
     *
     * @return bool
     */
    protected function shouldSkipCheck(Request $request)
    {
        // روتات لا نفحصها
        $skipRoutes = [
            'logout',
            'login',
            'register',
            'profile.show',
            'profile.update',
        ];

        // تخطي لطلبات API
        if ($request->is('api/*')) {
            return true;
        }

        // تخطي لـ AJAX requests
        if ($request->ajax()) {
            return true;
        }

        // تخطي لـ routes محددة
        $currentRoute = Route::currentRouteName();
        $path = $this->pathWithoutLocale($request);
        foreach ($skipRoutes as $route) {
            if ($path === $route || fnmatch($route, $path) || $currentRoute === $route) {
                return true;
            }
        }

        // تخطي لصفحات التبريرات نفسها
        if (fnmatch('employee/visit-justifications*', $path)) {
            return true;
        }

        return false;
    }

    /**
     * المخارج المسموحة أثناء الحجب.
     *
     * **هذه الدالّة هي المخرج من حلقةٍ مغلقة، لا تحسينُ شكل.** كانت
     * المطابقة تُكتب `$request->is('incomplete-visits-justification*')`،
     * وكل مسارات النظام تحت `LaravelLocalization::setLocale()`
     * و`hideDefaultLocaleInURL` تساوي `false` — أي أن العنوان الحقيقي
     * `ar/incomplete-visits-justification`، وهو لا يطابق نمطاً بلا بادئة.
     * فالموظّف الذي عليه زيارةٌ غير مبرَّرة كان يُحوَّل إلى شاشة التبرير،
     * فلا يعرفها هذا الوسيط فيحوّله إليها ثانيةً… بلا نهاية: المتصفّح يقف
     * عند `ERR_TOO_MANY_REDIRECTS` وينصح بمحو ملفّات الارتباط — ومحوها لا
     * يصنع شيئاً، لأن العلّة ليست فيها. وحتى `logout` كانت محجوبةً بالعلّة
     * نفسها، فلم يبقَ للموظّف مخرجٌ من الحلقة إطلاقاً.
     *
     * (السطر الوحيد الذي كان ينجو — `*system-update*` — نجا بحرف `*` في
     * أوّله لا أكثر.)
     *
     * والمطابقة هنا على العنوان بلا بادئة اللغة **وعلى اسم الرابط**: الاسم
     * لا يتغيّر بتغيّر اللغة ولا بإعادة تسمية العنوان، فهو الحارس الذي يبقى
     * إن نُقلت الشاشة غداً.
     */
    protected function isEscapeRoute(Request $request): bool
    {
        $routeName = (string) Route::currentRouteName();

        $escapeRouteNames = [
            'incomplete.visits.justification',
            'incomplete.visits.submit',
            'employee.visit-justifications.*',
            'logout',
            // شاشة «تحديث النظام» تسبق هذا القفل: كلاهما يحوّل إلى
            // شاشته، فلو حجب هذا تلك دار الطلب بينهما بلا نهاية
            'SystemUpdate.*',
        ];

        foreach ($escapeRouteNames as $pattern) {
            if ($routeName === $pattern || fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        $path = $this->pathWithoutLocale($request);

        /* بادئة `*` في أوّل كل نمطٍ مقصودة: الشاشتان تحت مجموعة
           `clients/clients_management` داخل وحدة العملاء، فعنوانهما
           الحقيقي — بعد نزع بادئة اللغة —
           `clients/clients_management/incomplete-visits-justification`.
           نمطٌ بلا `*` في أوّله يطابق الاسم المجرَّد وحده ولا يطابق شيئاً
           مما يصل فعلاً. */
        $escapePaths = [
            '*incomplete-visits-justification',
            '*incomplete-visits-justification/*',
            '*my-visit-justifications',
            '*my-visit-justifications/*',
            '*system-update',
            '*system-update/*',
            'logout',
        ];

        foreach ($escapePaths as $pattern) {
            if ($path === $pattern || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * العنوان بلا بادئة اللغة — كما في `CheckSystemUpdates::isExcepted()`
     * و`EnsureSubscription`. المطابقة على `ar/...` الخام هي الخطأ الذي
     * أنتج الحلقة المغلقة أعلاه.
     */
    protected function pathWithoutLocale(Request $request): string
    {
        $path = ltrim($request->path(), '/');
        $segments = explode('/', $path);
        $locales = array_keys((array) config('laravellocalization.supportedLocales', []));

        if (isset($segments[0]) && in_array($segments[0], $locales, true)) {
            array_shift($segments);
            $path = implode('/', $segments);
        }

        return $path;
    }
}
