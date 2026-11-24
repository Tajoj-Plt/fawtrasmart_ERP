<?php

use App\Console\Kernel;
use App\Http\Controllers\SchedulerTickController;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\AdoptEmployeeJobRole;
use App\Http\Middleware\AllowPendingUpdateAccess;
use App\Http\Middleware\ApiKeyAuthentication;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateClient;
use App\Http\Middleware\CheckBranchStatus;
use App\Http\Middleware\CheckIncompleteVisits;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckPermissionRole;
use App\Http\Middleware\CheckPointsSubsystem;
use App\Http\Middleware\CheckPointsSystem;
use App\Http\Middleware\CheckPosSession;
use App\Http\Middleware\CheckRoleName;
use App\Http\Middleware\CheckSystemUpdates;
use App\Http\Middleware\ClientAccessMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnforcePlanLimit;
use App\Http\Middleware\EnsureWebAccessAllowed;
use App\Http\Middleware\EnsureAccountModuleAccess;
use App\Http\Middleware\EnsureClientFeatureEnabled;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSubscription;
use App\Http\Middleware\LockToOpenVisit;
use App\Http\Middleware\NotificationMiddleware;
use App\Http\Middleware\OptionalAuthenticateClient;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\ResolveAccountingPeriod;
use App\Http\Middleware\ReturnToVisitWorkspace;
use App\Http\Middleware\TickSchedulerFromRequests;
use App\Http\Middleware\TrackSessionActivity;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\ErrorLogService;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Modules\Installer\Http\Middleware\EnsureInstalled;
use Modules\Installer\Http\Middleware\RedirectIfInstalled;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // رابطُ نبض المجدول الداخلي — خارج مجموعتَي `web` و`api` عمداً: لا
        // جلسة ولا CSRF ولا حارسَ اشتراكٍ يحوّله إلى صفحة دخول. الرمزُ من
        // `APP_KEY`، انظر `SchedulerTick::token()`
        then: function (): void {
            Route::get('/scheduler/tick/{token}', SchedulerTickController::class)->name('scheduler.tick');
        },
    )
    // ✅ تفعيل جدولة Console\Kernel.php (التي كانت معطّلة في Laravel 11 افتراضياً)
    ->withSchedule(function (Schedule $schedule): void {
        $kernel = app(Kernel::class);
        $reflection = new ReflectionClass($kernel);
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Global Middleware
        $middleware->use([
            // أول الصفّ: نظامٌ غير مثبَّت يُحوَّل إلى /install بدل أن ينهار على
            // قاعدة بيانات غير موجودة.
            //
            // في المكدَّس العام لا في مجموعة `web` عمداً: ما يُلحق بالمجموعة
            // يخضع لترتيب أولويات Laravel، و`Authenticate` مُدرَج في ذلك
            // الترتيب فيسبقه — فيرى المستخدمُ صفحةَ دخولٍ على قاعدة بيانات
            // غير موجودة بدل معالج التثبيت. (جُرِّب: طلب /dashboard/sales/index
            // على نظام بلا قاعدة كان يُحوَّل إلى /login.) والمكدَّس العام يسبق
            // مسارات api كذلك، وهي عاجزة بلا قاعدة أيضاً.
            //
            // الفحص ملفٌّ لا استعلام — وإلا لكان أولَ ما يسقط بغياب القاعدة.
            EnsureInstalled::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        // Web Middleware Group
        $middleware->web(append: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            // حسابُ موظفٍ بلا دور يأخذ دورَ ملفّه — قبل كل حارسٍ يسأل عن
            // صلاحية، وإلا رأى الحارسُ حساباً بلا صلاحيات في الطلب نفسه.
            AdoptEmployeeJobRole::class,
            /*
                وضع الصيانة — أوّلُ حارسٍ بعد الجلسة، وقبل كل حارسٍ بعده.

                لماذا هنا لا في المكدَّس العام؟ لأنه يسأل «من أنت؟» لا «ما هذا
                الطلب؟»: الجواب في الجلسة، والجلسة تبدأ في هذه المجموعة. وهو
                بذلك غير `PreventRequestsDuringMaintenance` أعلاه — ذاك يقفل
                التطبيق على الجميع بلا استثناءٍ لأحد، وهذا يُبقيه مفتوحاً
                لحساباتٍ بعينها.

                ولماذا قبل `EnsureModuleEnabled` و`EnsureSubscription`
                و`CheckSystemUpdates`؟ لأن كلاً منها يحوّل المستخدم إلى شاشةٍ
                **داخل** النظام — شاشةِ التطبيقات أو الاشتراك أو التحديث. ولو
                سبق أحدُها لرأى الموظّفُ المحجوب شاشةً من نظامٍ يُفترض أنه مقفلٌ
                في وجهه. الإقفال يسبق كلَّ ما يُعرَض داخل المقفَل.
            */
            CheckMaintenanceMode::class,
            /*
                دورٌ محصورٌ في التطبيق لا يتصفّح الموقع (`roles.mobile_only`).

                بعد الإقفال العام وقبل كلّ ما يُعرَض داخل النظام، ولنفس علّته:
                من حُجب عن الموقع لا يُحوَّل إلى شاشةٍ فيه. وعلى مجموعة `web`
                وحدها — واجهةُ `api` هي القناةُ المقصودة بالإبقاء.
            */
            EnsureWebAccessAllowed::class,
            CheckIncompleteVisits::class,
            // يمنع فتح صفحات وحدة عُطِّلت من شاشة التطبيقات. في المجموعة
            // العامّة لا على كل مجموعة مسارات على حدة: الوحدات موزّعة على
            // عشرين ملفّ مسارات، ووضعُه في كلٍّ منها يعني أن نسيان ملفّ
            // واحد يترك ثغرة صامتة. وهو يخرج فوراً حين لا يخصّ المسارُ
            // وحدةً معروفة، وحالات الوحدات مقروءة من الذاكرة المؤقتة
            EnsureModuleEnabled::class,
            // حصرُ حساب العميل في بوابته. في المجموعة العامّة لا على مجموعة
            // مسارات البوابة وحدها: العميل لا يُمنع من صفحاته هو بل من كل
            // ما عداها، وشاشاتُ النظام موزّعةٌ على عشرين ملفّ مسارات وكثيرٌ
            // منها بلا صلاحيةٍ تحرسه. ويخرج فوراً حين لا يكون صاحبُ الجلسة
            // حساب عميل — وهو الغالب في كل طلب
            ClientAccessMiddleware::class,
            // يحدّث «آخر ظهور» لصفّ الجلسة في سجلّ الجلسات، بكتابةٍ واحدة
            // كل دقيقة. بدونه لا يُعرف متى انتهت جلسةٌ أُغلق متصفّحها،
            // إذ لا حدث خروج يقع لها
            // يُعيد المندوبَ إلى مساحة عمل الزيارة بعد حفظ فاتورةٍ أو سند
            // قبضٍ أو إشعارٍ دائنٍ أو تبديلٍ فتحه من داخلها. في المجموعة
            // العامّة لأن مسارات الحفظ الأربعة موزّعةٌ على وحدتَي Sales
            // وAccount، وإقحامُ مفهوم الزيارة في متحكّماتها أثقلُ من
            // استبدال هدف إعادة التوجيه من الخارج. ويخرج فوراً حين لا ختم
            // عودةٍ في الجلسة — وهو الغالب في كل طلب
            ReturnToVisitWorkspace::class,
            // مندوبٌ بدأ زيارةً ولم يُنهها لا ينتقل إلى صفحةٍ أخرى — يُعاد إلى
            // مساحة عملها. في المجموعة العامّة لأن «أيّ صفحة» موزّعةٌ على عشرين
            // ملفّ مسارات. ويخرج فوراً لغير GET ولطلبات AJAX، ولمن لا زيارةَ
            // مفتوحةً له — وهو الغالب في كل طلب
            LockToOpenVisit::class,
            TrackSessionActivity::class,
            // لا عمل بلا اشتراك مسجَّل. بعد `EnsureModuleEnabled` وقبل شيء
            // آخر: السؤال يُطرح على كل مسار `web`، والاستثناءات مكتوبة في
            // الوسيط نفسه (الدخول والخروج وشاشتا الاشتراك) كي لا يصير المنع
            // حلقةً مغلقة على من يريد الربط
            EnsureSubscription::class,
            // ترحيلٌ معلَّق ⇐ شاشة «تحديث النظام» قبل أي شاشة أخرى.
            //
            // آخر الصفّ عمداً: لا معنى لعرض شاشة التحديث على من ليس
            // مسجَّل الدخول، ولا على نظامٍ بلا اشتراك — لكلٍّ منهما شاشتُه
            // التي يجب أن تظهر أوّلاً. وهو يخرج فوراً حين لا ترحيلات
            // معلّقة، وجوابُ ذلك السؤال مخزَّنٌ في الكاش لا يُحسب كل طلب
            CheckSystemUpdates::class,
            // آخرُ الصفّ وبلا أثرٍ في الطلب: عملُه كلّه في `terminate()` بعد
            // إرسال الاستجابة — دورةُ المجدول الداخلي، انظر `SchedulerTick`
            TickSchedulerFromRequests::class,
        ]);

        // API Middleware Group
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // ومسارات api كذلك: قفلٌ يحرس الشاشات ولا يحرس الواجهة يُلتفّ عليه
        // بطلبٍ من أي عميل، فيعمل النظام كاملاً من خلفه بلا اشتراك
        $middleware->api(append: [
            EnsureSubscription::class,
            TickSchedulerFromRequests::class,
        ]);

        // طلبات تطبيق الجوال: رأسا JSON يجب أن يسبقا المصادقة، وإلا ردّت بتحويلٍ إلى صفحة الدخول بدل 401
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\MobileApiContext::class,
        );

        // Middleware Aliases
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'check.pos.session' => CheckPosSession::class,
            // عهدةُ الكاشير: مستودعٌ يُخصم منه وخزينةٌ يدخلها النقد
            'pos.custody' => \Modules\Pos\Http\Middleware\EnsurePosCustody::class,
            'auth.session' => AuthenticateSession::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'notification' => NotificationMiddleware::class,
            'check.incomplete.visits' => CheckIncompleteVisits::class,
            'check.branch' => CheckBranchStatus::class,
            'client.access' => ClientAccessMiddleware::class,
            // Store Client Authentication Middleware
            'auth.client' => AuthenticateClient::class,
            'auth.client.optional' => OptionalAuthenticateClient::class,
            /*
                `permission` تبقى وسيطَ Spatie عمداً: هي تسأل
                `$user->canAny()`، فتمرّ على خطّاف `Gate::before` المسجَّل في
                `AppServiceProvider` — وهو يجيب من `users.role_id` ←
                `role_has_permissions`. أي أنّ اسمها Spatie ومصدرَها النظام
                الجديد، فلا انحراف.

                أمّا `role` فكانت تسأل `hasAnyRole()` مباشرةً — و`hasAnyRole()`
                لا تمرّ على `Gate` إطلاقاً، بل تقرأ `model_has_roles` الخام.
                فمستخدمٌ `role_id` يقول «manager» و`model_has_roles` تقول
                «owner» يُردّ عن مساراتِ `role:manager` وحدها بينما يفتح
                كلَّ ما عداها. `CheckRoleName` تقرأ `role_id` كسائر النظام.
            */
            'role' => CheckRoleName::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // ميدل وير مخصَّص لشاشات المستخدمين/الأدوار/الصلاحيات حصراً
            'permission.role' => CheckPermissionRole::class,
            'account.module' => EnsureAccountModuleAccess::class,
            // Points System Middleware
            'points.system' => CheckPointsSystem::class,
            'points.subsystem' => CheckPointsSubsystem::class,

            // API Key Middleware
            'api.key' => ApiKeyAuthentication::class,

            // يمنع الوصول إلى وحدة عُطِّلت من شاشة التطبيقات. بلا وسيط
            // كان التعطيل يُخفي بند القائمة ويترك المسار مفتوحاً
            'module' => EnsureModuleEnabled::class,

            /*
                حدود باقة فوترة — طبقةٌ ثالثة مستقلّة عن الوسيطين أعلاه:
                `module` يسأل «هل الوحدة مفعَّلة في هذا النظام؟»،
                و`permission.role` يسأل «هل يملك المستخدم الصلاحية؟»،
                وهذان يسألان «هل اشترت المنشأة الميزة، وهل بقي في حدّها متّسع؟».

                ولا يمسّان نظام الصلاحيات بحرف: يتراكبان معه فيُرفض من سقط
                في أيّهما.
            */
            'plan' => EnsurePlanFeature::class,
            'plan.limit' => EnforcePlanLimit::class,

            // يمنع الوصول إلى قسمٍ فرعيٍّ من موديول العملاء عُطِّل من
            // «إعدادات وحدات العملاء» (الزيارات/خطوط السير/الموقع/...)
            'client.feature' => EnsureClientFeatureEnabled::class,

            // يترجم «الفترة المحاسبية» المختارة إلى حدَّي التاريخ اللذين
            // يقرؤهما كل تقرير باسمه الخاص
            'accounting.period' => ResolveAccountingPeriod::class,

            // بوّابة شاشة تحديث النظام: تفتحها للزائر ما دام ترحيلٌ معلَّقاً،
            // وتقفلها فور انتهائه — تفصيلُ السبب في الوسيط نفسه
            'update.access' => AllowPendingUpdateAccess::class,

            // معالج التثبيت
            'installed' => EnsureInstalled::class,
            'subscribed' => EnsureSubscription::class,
            'not.installed' => RedirectIfInstalled::class,

            // التحقق من الاشتراك — للـ API
            'subscription' => \Modules\Integration\Http\Middleware\CheckSubscriptionStatus::class,

            // Laravel Localization Middleware
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeCookieRedirect' => LocaleCookieRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
            **كل استثناءٍ يبلغ هذا المعالج يُكتب في «سجلّ الأخطاء».**

            كانت هذه الكتلة فارغةً — `//` وحدها. و`ErrorLogService` موجودة
            ومكتوبة جيّداً، وشاشة «سجلّ الأخطاء» في الإعدادات تعرض جدولها،
            لكن **لا أحد كان يناديها للاستثناءات غير المُلتقَطة**. فكل صفحة
            500 يراها المستخدم لا تترك أثراً في الشاشة التي بُنيت لتُظهرها،
            ولا يبقى منها إلا سطرٌ في ملفّ `laravel.log` لا يقرؤه أحد.

            وهي المكان الصحيح لا الكنترولر: الاستثناء الذي لا تلتقطه كتلة
            `catch` يمرّ من هنا مهما كان مصدره — كنترولراً كان أو وسيطاً أو
            مهمّةً في الطابور أو أمراً في الطرفية.

            **ما لا يُسجَّل ولماذا**

            404 و419 و403 وأخطاء التحقّق ليست أعطالاً في النظام بل نتائجَ
            طبيعية لطلبٍ خاطئ. تسجيلها يُغرق الشاشة بضجيجٍ يُخفي العطل
            الحقيقي — وهو ما يجعل سجلّ الأخطاء يُهجَر بعد أسبوع.
        */
        $exceptions->report(function (Throwable $e) {
            $ignored = [
                // بلوغ حدّ الباقة قاعدةُ عملٍ لا عطل: منشأةٌ بلغت سقفها
                // تُنتج واحدةً مع كل محاولة حفظ، فتُغرق سجلّ الأخطاء بضجيجٍ
                // يُخفي العطل الحقيقي — وهو أوّل ما يجعله يُهجَر
                PlanLimitExceededException::class,
                AuthenticationException::class,
                AuthorizationException::class,
                ModelNotFoundException::class,
                TokenMismatchException::class,
                ValidationException::class,
                HttpException::class,
                NotFoundHttpException::class,
                RouteNotFoundException::class,
            ];

            foreach ($ignored as $class) {
                if ($e instanceof $class) {
                    return;
                }
            }

            /*
                التسجيل نفسه لا يجوز أن يرمي: عطلٌ في قاعدة البيانات يجعل
                الكتابة في `error_logs` تفشل أيضاً، فتتحوّل صفحة الخطأ إلى
                استثناءٍ داخل معالج الاستثناءات — وهو أسوأ ما يُعرَض على
                مستخدم.
            */
            try {
                app(ErrorLogService::class)->log($e);
            } catch (Throwable $ignore) {
                // `laravel.log` وحده يبقى في هذه الحال
            }
        });

        /*
            وحدة `Integration` (`/api/integration/...`) واجهةٌ لتطبيق الجوال
            وحدها — لا مستخدم بشريّ يفتحها في متصفّح. لكن معالج الاستثناءات
            الافتراضي لـ `AuthenticationException` يقرّر JSON أم إعادة توجيه
            بحسب ترويسة `Accept` في الطلب نفسه (`expectsJson()`)؛ وطلبٌ بلا
            `Accept: application/json` صريحة (الافتراضُ في أدواتٍ كثيرة —
            جُرِّب فعلياً في Postman بطلبٍ جديد فارغ) كان يُعاد توجيهه 302 إلى
            `/login` فـ`/en/login` حتى يستقرّ على صفحة الدخول HTML — لا خطأ
            401 بصيغة JSON يستطيع تطبيق الجوال أو Postman تفسيره. فيبدو
            الأمر وكأن التوكن انتهى ويطلب تسجيل دخولاً جديداً، بينما التوكن
            سليمٌ تماماً والمشكلة ترويسة الطلب فقط.
        */
        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/integration/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'انتهت الجلسة أو رمز الدخول غير صالح — سجّل الدخول من جديد.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        // محاولاتٌ كثيرة (throttle) — رسالةٌ عربية بمدّة الانتظار بدل `Too Many Attempts.`
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/integration/*')) {
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json([
                    'success' => false,
                    'message' => "محاولاتٌ كثيرة خلال وقتٍ قصير — أعد المحاولة بعد {$seconds} ثانية.",
                    'code' => 'TOO_MANY_ATTEMPTS',
                    'retry_after' => $seconds,
                ], 429, $e->getHeaders());
            }
        });

        /*
            سجلٌّ غير موجود (`findOrFail`) أو عنوانٌ خاطئ في واجهة الجوال —
            رسالةٌ عربية مفهومة بدل `No query results for model
            [App\Models\...]` الذي يكشف أسماء الموديلات الداخلية ولا يفهمه
            المستخدم. (`ModelNotFoundException` يصل هنا محوَّلاً إلى 404.)
        */
        $exceptions->render(function (NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/integration/*')) {
                return response()->json(['success' => false, 'message' => 'السجل المطلوب غير موجود.'], 404);
            }
        });

        // منعٌ (صلاحية، نطاق، ملكية) — بنفس شكل بقية أخطاء واجهة الجوال: `success` ورسالةٌ عربية
        $exceptions->render(function (HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/integration/*') && $e->getStatusCode() === 403) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'ليست لديك صلاحية لهذا الإجراء.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        });
    })->create();
