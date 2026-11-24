<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Integration\Http\Requests\ForgotPasswordRequest;
use Modules\Integration\Http\Requests\LoginRequest;
use Modules\Integration\Http\Requests\ResetPasswordRequest;
use Modules\Integration\Http\Requests\VerifyEmailRequest;
use Modules\Integration\Http\Requests\VerifyResetOtpRequest;
use Modules\Integration\Http\Resources\EmployeeProfileResource;
use Modules\Integration\Models\IntegrationOtp;
use Modules\Integration\Services\OtpService;

/**
 * مصادقة تطبيق الجوال (Flutter) — للمناديب/الموظفين حصراً.
 *
 * كل مسار هنا يتأكد أن الحساب مرتبط بسجل موظف (isEmployee) قبل أي عملية —
 * حسابات العملاء والمدراء غير المرتبطين بموظف لا تدخل من هذه البوابة.
 */
class AuthController extends Controller
{
    public function __construct(protected OtpService $otpService) {}

    /**
     * تسجيل الدخول — يُرجع رمز Sanctum + الملف الشخصي.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with(['employee.job_title', 'employee.department', 'employee.branch'])
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if (! $user->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الواجهة مخصّصة لحسابات الموظفين فقط.',
            ], 403);
        }

        $deviceName = $request->device_name ?: 'mobile-app';

        // جهازٌ يعيد الدخول باسمٍ سبق = رمزه القديم يُبطل ويُستبدل
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => $user->email_verified_at !== null,
                'profile' => new EmployeeProfileResource($user),
            ],
        ]);
    }

    /**
     * تسجيل الخروج — إبطال رمز الجهاز الحالي فقط.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * نسيت كلمة المرور — إرسال رمز تحقق لبريد الموظف.
     *
     * الاستجابة واحدة وُجد البريد أم لا، كي لا تكشف الواجهة أي البريدات
     * مسجَّلة (user enumeration).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = $this->employeeUserByEmail($request->email);

        if ($user) {
            $this->otpService->send($user, IntegrationOtp::PURPOSE_PASSWORD_RESET);
        }

        return response()->json([
            'success' => true,
            'message' => 'إن كان البريد مسجَّلاً لدينا فسيصلك رمز التحقق خلال دقائق.',
        ]);
    }

    /**
     * التثبّت من رمز الاستعادة دون استهلاكه — خطوة وسطى اختيارية
     * تتيح للتطبيق التحقق قبل عرض شاشة كلمة المرور الجديدة.
     */
    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $user = $this->employeeUserByEmail($request->email);

        if (! $user || ! $this->otpService->verify($user, IntegrationOtp::PURPOSE_PASSWORD_RESET, $request->otp, consume: false)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'الرمز صحيح.',
        ]);
    }

    /**
     * تعيين كلمة مرور جديدة بالرمز — يستهلك الرمز ويُبطل كل رموز
     * الوصول القائمة (خروج إجباري من كل الأجهزة).
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->employeeUserByEmail($request->email);

        if (! $user || ! $this->otpService->verify($user, IntegrationOtp::PURPOSE_PASSWORD_RESET, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح. سجّل الدخول بكلمة المرور الجديدة.',
        ]);
    }

    /**
     * إرسال رمز توثيق البريد الإلكتروني للمستخدم الحالي.
     */
    public function sendEmailVerificationOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'success' => true,
                'message' => 'البريد الإلكتروني موثَّق مسبقاً.',
            ]);
        }

        $sent = $this->otpService->send($user, IntegrationOtp::PURPOSE_EMAIL_VERIFICATION);

        return response()->json([
            'success' => true,
            'message' => $sent
                ? 'تم إرسال رمز التوثيق إلى بريدك الإلكتروني.'
                : 'أُرسل رمز قبل قليل — انتظر دقيقة قبل طلب رمز جديد.',
        ]);
    }

    /**
     * توثيق البريد الإلكتروني بالرمز.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'success' => true,
                'message' => 'البريد الإلكتروني موثَّق مسبقاً.',
            ]);
        }

        if (! $this->otpService->verify($user, IntegrationOtp::PURPOSE_EMAIL_VERIFICATION, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم توثيق البريد الإلكتروني بنجاح.',
        ]);
    }

    /**
     * حساب موظفٍ ببريده — أو null إن لم يوجد أو لم يكن موظفاً.
     */
    protected function employeeUserByEmail(string $email): ?User
    {
        $user = User::where('email', $email)->first();

        return ($user && $user->isEmployee()) ? $user : null;
    }
}
