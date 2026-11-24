<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckSystemUpdates;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Maintenance\SystemUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // التحقق من بيانات الاعتماد أولاً بدون تسجيل دخول
        $user = User::where('email', $request->input('email'))->first();

        if ($user && Hash::check($request->input('password'), $user->password)) {

            // التحقق من صلاحية دخول الموظف قبل السماح بتسجيل الدخول
            if ($user->isEmployee()) { // ✅ isEmployee() بدلاً من عمود role النصي
                $employee = \App\Models\Employee::find($user->employee_id);

                /*
                    كان الشرط يفحص employee_type == 1 ("موظف" لا "مستخدم") —
                    حقلٌ لا علاقة له بإذن الدخول، بل يميّز نوع الموظف لأغراض
                    الخزينة/المستودع الافتراضيين. فمُنع من الدخول كل موظفٍ
                    اختير له النوع "موظف" حتى لو فعّل مُنشئ حسابه صراحةً
                    خانة "السماح بالدخول الى النظام" (allow_system_access) —
                    وهي الحقل المخصَّص فعلاً لهذا الغرض.
                */
                /*
                    ملفٌّ مفقود يمنع الدخول — ولم يكن يمنعه.

                    كان الشرطُ `if ($employee && ...)`: فإن لم يُعثر على
                    الموظف مرّ الفحصُ كلُّه بلا فحص. وصار ذلك ثغرةً حين
                    أُضيفت الأرشفة (`SoftDeletes`)، إذ تردّ `find()` عدماً
                    للمؤرشَف — **فيدخل من أُرشف حسابُه**. والحالُ نفسُها لمن
                    حُذف موظفُه حذفاً صلباً قبل الأرشفة وبقي حسابُه.

                    فمن كان حسابُه حسابَ موظفٍ ولا ملفَّ له لا يدخل.
                */
                if (! $employee) {
                    return back()
                        ->withInput($request->only('email'))
                        ->withErrors(['email' => 'ملفُّ هذا الموظف غير متاح — راجع مدير النظام.']);
                }

                if (! $employee->allow_system_access) {
                    return back()
                        ->withInput($request->only('email'))
                        ->withErrors(['email' => 'هذا الموظف ليس لديه صلاحية دخول للنظام.']);
                }
            }

            /*
                دورٌ محصورٌ في التطبيق لا يدخل الموقع (`roles.mobile_only`).

                يُرفض هنا قبل `Auth::login` لا بعده: لو دخل ثمّ طُرد بالحارس
                لسُجّل له دخولٌ ناجحٌ في سجلّ النشاط وحُدّث «آخر دخول»، ولوُثِّق
                جهازُه جهازاً موثوقاً — أثرٌ كاملٌ لدخولٍ لم يقع.
            */
            if (\App\Support\RoleAccess::mobileOnly($user)) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => \App\Support\RoleAccess::MOBILE_ONLY_NOTICE]);
            }

            // تسجيل الدخول بعد التحقق من الصلاحيات
            Auth::login($user);

            // تحديث بيانات آخر تسجيل دخول في جدول المستخدمين
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // تسجيل النشاط وسجل الأجهزة الموثوقة
            $activityLog = new ActivityLogService;
            $activityLog->log('auth', 'تم تسجيل الدخول بنجاح إلى النظام');

            $this->registerTrustedDevice($user, $request);

            /*
                كانت هذه القائمة تسمح بثلاثة أدوارٍ حرفياً فقط
                (manager/employee/client) وتُخرج أي دورٍ آخر فوراً بخطأ
                «الدور غير صالح» — بما فيها owner وadmin، وهما دورا نظامٍ
                حقيقيان (RolesSeeder). عميلٌ وحده له وجهة مختلفة؛ كل من
                عداه يذهب للوحة التحكّم العامة.
            */
            if (auth()->user()->hasRoleName('client')) { // ✅ hasRoleName() بدلاً من عمود role النصي
                return redirect()->intended(route('clients.personal'));
            }

            /*
                تحديثٌ مرفوعٌ لم يُنفَّذ ⇐ شاشة «تحديث النظام» هي أوّل ما
                يُفتَح بعد الدخول، لا لوحة التحكّم.

                الوسيط `CheckSystemUpdates` كان سيحوّل إليها في الطلب التالي
                على أي حال، لكنّ التحويل من هنا يُلغي القفزة الوسيطة: لا
                تُبنى لوحةُ تحكّمٍ باستعلاماتها كلِّها على قاعدةٍ بنيتُها
                ناقصة لتُرمى بعد لحظة. وهو الموضع الذي طلبه صاحب النظام:
                «حتى في تسجيل الدخول».

                والتأجيل يسقط مع كل دخولٍ جديد: مهلةُ «متابعة الآن» تخصّ
                جلسةً انتهت، لا الجلسةَ التي تبدأ الآن.
            */
            $request->session()->forget(CheckSystemUpdates::SNOOZE_KEY);

            if (Route::has('SystemUpdate.index') && app(SystemUpdateService::class)->pendingMigrationCount() > 0) {
                return redirect()->route('SystemUpdate.index')->with(
                    'update_required',
                    'رُفِعت ملفّات تحديثٍ جديدة إلى النظام، وترحيلاتُها بانتظار التنفيذ. '
                    .'شغّل التحديث من هذه الشاشة قبل متابعة العمل — الشاشات والحقول الجديدة '
                    .'لا تعمل حتى يُنفَّذ.'
                );
            }

            return redirect()->intended(route('dashboard'));
        }

        /*
            البريد وحده يعود مع الخطأ (`only('email')`): شاشة الدخول تملأه من
            `old('email')` فلا يُعاد كتابته بعد كل محاولة فاشلة — وكلمة المرور
            لا تعود قط، فالجلسة تحتفظ بالمُدخلات المُعادة لطلبٍ كامل.
        */
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'بيانات الاعتماد غير صحيحة.']);
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            $activityLog = new ActivityLogService;
            $activityLog->log('auth', 'تم تسجيل الخروج من النظام');
        }

        // تسجيل خروج المستخدم من Laravel Auth
        Auth::logout();

        // حذف user_id من الـ session
        $request->session()->forget('user_id');

        // إلغاء كامل الـ session (تنظيف شامل)
        $request->session()->invalidate();

        // إنشاء token جديد للحماية من CSRF attacks
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بنجاح');
    }

    /**
     * تسجيل الجهاز الحالي كجهاز موثوق إذا لم يكن مسجلاً
     */
    protected function registerTrustedDevice(User $user, Request $request)
    {
        $userAgent = $request->userAgent() ?? '';
        $ip = $request->ip();

        // توليد بصمة بسيطة للجهاز بناءً على المتصفح ونظام التشغيل
        $fingerprint = md5($userAgent);

        $deviceExists = TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->exists();

        if (! $deviceExists) {
            $activityLog = new ActivityLogService;

            // تحليل بسيط لنظام التشغيل والمتصفح ونوع الجهاز
            // استخدام دوال التحليل المباشر أو المساعدة
            $browser = 'غير معروف';
            if (preg_match('/Chrome/i', $userAgent)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Firefox/i', $userAgent)) {
                $browser = 'Firefox';
            } elseif (preg_match('/Safari/i', $userAgent)) {
                $browser = 'Safari';
            }

            $os = 'غير معروف';
            if (preg_match('/Windows/i', $userAgent)) {
                $os = 'Windows';
            } elseif (preg_match('/Mac/i', $userAgent)) {
                $os = 'macOS';
            } elseif (preg_match('/Linux/i', $userAgent)) {
                $os = 'Linux';
            } elseif (preg_match('/iPhone|iPad/i', $userAgent)) {
                $os = 'iOS';
            } elseif (preg_match('/Android/i', $userAgent)) {
                $os = 'Android';
            }

            TrustedDevice::create([
                'user_id' => $user->id,
                'device_name' => $browser.' on '.$os,
                'device_fingerprint' => $fingerprint,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'browser' => $browser,
                'os' => $os,
                'is_trusted' => true,
                'last_used_at' => now(),
                'expires_at' => now()->addDays(180), // صالح لمدة 180 يوم
            ]);
        } else {
            TrustedDevice::where('user_id', $user->id)
                ->where('device_fingerprint', $fingerprint)
                ->update([
                    'last_used_at' => now(),
                    'ip_address' => $ip,
                ]);
        }
    }
}
