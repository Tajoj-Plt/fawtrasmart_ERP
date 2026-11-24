<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Store\ClientDevice;
use App\Services\OnlineStoreSettingsService;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class StoreAuthController extends Controller
{
    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * تسجيل عميل جديد
     * Register new client
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string|max:20|unique:clients,phone',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'trade_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female',
            'avatar' => 'nullable|image|max:2048',
        ], [
            'first_name.required' => 'الاسم الأول مطلوب',
            'last_name.required' => 'الاسم الأخير مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صالح',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.confirmed' => 'كلمة المرور غير متطابقة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'birth_date.date' => 'تاريخ الميلاد غير صالح',
            'birth_date.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'avatar.image' => 'يجب أن تكون الصورة ملف صورة',
            'avatar.max' => 'حجم الصورة يجب أن لا يتجاوز 2 ميجابايت',
        ], [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
            'password' => 'كلمة المرور',
            'trade_name' => 'الاسم التجاري',
            'birth_date' => 'تاريخ الميلاد',
            'gender' => 'الجنس',
            'avatar' => 'الصورة الشخصية',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // رفع الصورة الشخصية إذا وجدت
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('clients/avatars', 'public');
            }

            // إنشاء العميل
            $client = Client::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'mobile' => $request->phone,
                'password' => Hash::make($request->password),
                'trade_name' => $request->trade_name ?? $request->first_name.' '.$request->last_name,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'avatar' => $avatarPath,
                'is_store_client' => true,
                'status_id' => 5, // حالة نشط
                'code' => $this->generateClientCode(),
            ]);

            // جلب إعدادات التحقق (التحقق من رقم الهاتف مُلغى نهائياً)
            $requireEmailVerification = OnlineStoreSettingsService::requireEmailVerification();

            $requiresVerification = false;
            $verificationType = null;
            $verificationMessage = 'تم إنشاء الحساب بنجاح';

            // إرسال رمز التحقق حسب الإعدادات
            if ($requireEmailVerification) {
                $otp = $this->generateOTP();
                $this->storeOTP($client->id, $otp, 'email_verification');
                $this->sendVerificationEmail($client, $otp);
                $requiresVerification = true;
                $verificationType = 'email';
                $verificationMessage = 'تم إنشاء الحساب بنجاح. تم إرسال رمز التحقق إلى بريدك الإلكتروني.';
            }

            // إرسال بريد الترحيب
            $this->sendWelcomeEmail($client);

            // إنشاء التوكن
            $token = $client->createToken('store-client-token')->plainTextToken;

            OperationLog::record('created', 'client', $client, 'تم تسجيل عميل جديد **'.$client->full_name.'**');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $verificationMessage,
                'data' => [
                    'client' => $this->formatClientData($client),
                    'token' => $token,
                    'requires_verification' => $requiresVerification,
                    'verification_type' => $verificationType,
                    'require_email_verification' => $requireEmailVerification,
                    'require_phone_verification' => false,
                ],
            ], 201);

        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollBack();

            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'register');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحساب',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تسجيل الدخول
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string',
            'remember_me' => 'nullable|boolean',
        ], [
            'email.required_without' => 'البريد الإلكتروني أو رقم الهاتف مطلوب',
            'phone.required_without' => 'رقم الهاتف أو البريد الإلكتروني مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ], [
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
            'password' => 'كلمة المرور',
            'remember_me' => 'تذكّرني',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // البحث عن العميل
            $client = Client::where('is_store_client', true)
                ->where(function ($query) use ($request) {
                    if ($request->filled('email')) {
                        $query->where('email', $request->email);
                    } elseif ($request->filled('phone')) {
                        $query->where(function ($q) use ($request) {
                            $q->where('phone', $request->phone)
                                ->orWhere('mobile', $request->phone);
                        });
                    }
                })
                ->first();

            // رسالةٌ واحدة للحالتين (لا حساب / كلمة مرور خاطئة): لا نكشف أيّ الأرقام والبريد مسجَّل لدينا
            if (! $client || ! $client->password || ! Hash::check((string) $request->password, $client->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الدخول غير صحيحة',
                ], 401);
            }

            // جلب إعدادات التحقق (التحقق من رقم الهاتف مُلغى نهائياً)
            $requireEmailVerification = OnlineStoreSettingsService::requireEmailVerification();

            // التحقق من حالة التحقق المطلوبة
            $requiresVerification = false;
            $verificationType = null;
            $verificationMessage = null;

            // التحقق من البريد الإلكتروني إذا كان مطلوباً
            if ($requireEmailVerification && is_null($client->email_verified_at)) {
                $requiresVerification = true;
                $verificationType = 'email';
                $verificationMessage = 'يرجى التحقق من بريدك الإلكتروني أولاً';

                // إرسال رمز تحقق جديد
                $otp = $this->generateOTP();
                $this->storeOTP($client->id, $otp, 'email_verification');
                $this->sendVerificationEmail($client, $otp);
            }

            // حذف التوكنات القديمة (اختياري)
            // $client->tokens()->delete();

            // إنشاء توكن جديد
            $tokenExpiration = $request->remember_me ? now()->addDays(30) : now()->addDays(7);
            $token = $client->createToken('store-client-token', ['*'], $tokenExpiration)->plainTextToken;

            // تحديث آخر تسجيل دخول
            $client->update(['last_login_at' => now()]);

            // إذا كان التحقق مطلوباً، إرجاع معلومات التحقق
            if ($requiresVerification) {
                return response()->json([
                    'success' => true,
                    'message' => $verificationMessage,
                    'data' => [
                        'client' => $this->formatClientData($client),
                        'token' => $token,
                        'expires_at' => $tokenExpiration->toIso8601String(),
                        'requires_verification' => true,
                        'verification_type' => $verificationType,
                        'require_email_verification' => $requireEmailVerification,
                        'require_phone_verification' => false,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'data' => [
                    'client' => $this->formatClientData($client),
                    'token' => $token,
                    'expires_at' => $tokenExpiration->toIso8601String(),
                    'requires_verification' => false,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'login');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تسجيل الخروج
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $client = $this->client();

            // حذف التوكن الحالي
            $client->currentAccessToken()?->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'logout');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الخروج',
            ], 500);
        }
    }

    /**
     * تسجيل الخروج من جميع الأجهزة
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $client = $this->client();

            // حذف جميع التوكنات
            $client->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'logoutAll');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
            ], 500);
        }
    }

    /**
     * نسيت كلمة المرور - إرسال رمز التحقق
     * Forgot password - Send OTP
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
        ], [
            'email.required_without' => 'البريد الإلكتروني أو رقم الهاتف مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // البحث عن العميل
            $client = Client::where('is_store_client', true)
                ->where(function ($query) use ($request) {
                    if ($request->filled('email')) {
                        $query->where('email', $request->email);
                    } elseif ($request->filled('phone')) {
                        $query->where(function ($q) use ($request) {
                            $q->where('phone', $request->phone)
                                ->orWhere('mobile', $request->phone);
                        });
                    }
                })
                ->first();

            if (! $client) {
                // لأسباب أمنية، نرجع نفس الرسالة حتى لو لم يكن الحساب موجود
                return response()->json([
                    'success' => true,
                    'message' => 'إذا كان الحساب موجوداً، سيتم إرسال رمز التحقق',
                ]);
            }

            // إنشاء رمز التحقق
            $otp = $this->generateOTP();
            $this->storeOTP($client->id, $otp, 'password_reset');

            // إرسال رمز التحقق
            if ($request->filled('email')) {
                $this->sendPasswordResetEmail($client, $otp);
            } else {
                $this->sendPasswordResetSMS($client, $otp);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رمز التحقق بنجاح',
                'data' => [
                    'expires_in' => 600, // 10 دقائق
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'forgotPassword');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال رمز التحقق',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * إعادة تعيين كلمة المرور
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'otp.required' => 'رمز التحقق مطلوب',
            'otp.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'password.required' => 'كلمة المرور الجديدة مطلوبة',
            'password.confirmed' => 'كلمة المرور غير متطابقة',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // البحث عن العميل
            $client = Client::where('is_store_client', true)
                ->where(function ($query) use ($request) {
                    if ($request->filled('email')) {
                        $query->where('email', $request->email);
                    } elseif ($request->filled('phone')) {
                        $query->where(function ($q) use ($request) {
                            $q->where('phone', $request->phone)
                                ->orWhere('mobile', $request->phone);
                        });
                    }
                })
                ->first();

            if (! $client) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ], 404);
            }

            // التحقق من رمز OTP
            if (! $this->checkOTP($client->id, $request->otp, 'password_reset')) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
                ], 400);
            }

            // تحديث كلمة المرور
            $client->update([
                'password' => Hash::make($request->password),
            ]);

            // حذف جميع التوكنات (تسجيل الخروج من جميع الأجهزة)
            $client->tokens()->delete();

            // حذف رمز OTP
            $this->deleteOTP($client->id, 'password_reset');

            OperationLog::record('updated', 'client', $client, 'تم إعادة تعيين كلمة المرور للعميل **'.$client->full_name.'**');

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مرة أخرى.',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'resetPassword');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تغيير كلمة المرور (للمستخدم المسجل)
     * Change password (for logged in user)
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
            'password.required' => 'كلمة المرور الجديدة مطلوبة',
            'password.confirmed' => 'كلمة المرور غير متطابقة',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();

            // التحقق من كلمة المرور الحالية
            if (! Hash::check($request->current_password, $client->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة',
                ], 400);
            }

            // تحديث كلمة المرور
            $client->update([
                'password' => Hash::make($request->password),
            ]);

            // إرسال بريد تأكيد تغيير كلمة المرور
            $this->sendPasswordChangedEmail($client, $request);

            // حذف التوكنات الأخرى (اختياري)
            // $client->tokens()->where('id', '!=', $client->currentAccessToken()->id)->delete();

            OperationLog::record('updated', 'client', $client, 'تم تغيير كلمة المرور للعميل **'.$client->full_name.'**');

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'changePassword');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور',
            ], 500);
        }
    }

    /**
     * التحقق من رمز OTP
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
            'type' => 'required|in:email_verification,password_reset',
        ], [
            'otp.required' => 'رمز التحقق مطلوب',
            'otp.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'type.required' => 'نوع التحقق مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();

            // التحقق من رمز OTP
            if (! $this->checkOTP($client->id, $request->otp, $request->type)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
                ], 400);
            }

            // تحديث حالة التحقق
            if ($request->type === 'email_verification') {
                $client->update(['email_verified_at' => now()]);
            }

            // حذف رمز OTP
            $this->deleteOTP($client->id, $request->type);

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق بنجاح',
                'data' => [
                    'client' => $this->formatClientData($client->fresh()),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'verifyOtp');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق',
            ], 500);
        }
    }

    /**
     * إعادة إرسال رمز التحقق
     * Resend OTP
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:email_verification',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();

            // التحقق من عدم إرسال رمز خلال آخر دقيقة
            $cacheKey = "otp_cooldown_{$client->id}_{$request->type}";
            if (Cache::has($cacheKey)) {
                $remainingSeconds = Cache::get($cacheKey) - time();

                return response()->json([
                    'success' => false,
                    'message' => "يرجى الانتظار {$remainingSeconds} ثانية قبل إعادة الإرسال",
                    'data' => ['retry_after' => $remainingSeconds],
                ], 429);
            }

            // إنشاء رمز جديد
            $otp = $this->generateOTP();
            $this->storeOTP($client->id, $otp, $request->type);

            // إرسال رمز التحقق
            $this->sendVerificationEmail($client, $otp);

            // تعيين فترة انتظار (60 ثانية)
            Cache::put($cacheKey, time() + 60, 60);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رمز التحقق بنجاح',
                'data' => [
                    'expires_in' => 600,
                    'retry_after' => 60,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'resendOtp');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال رمز التحقق',
            ], 500);
        }
    }

    /**
     * عرض الملف الشخصي
     * Get profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $client = $this->client();
            $client->load(['addresses', 'defaultAddress']);

            // جلب الإعدادات من الـ Service
            $storeInfo = OnlineStoreSettingsService::getStoreInfo();
            $socialLinks = OnlineStoreSettingsService::getSocialSettings();
            $seo = OnlineStoreSettingsService::getSeoSettings();

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => $this->formatClientData($client),
                    'addresses' => $client->addresses->where('is_active', true)->values(),
                    'stats' => [
                        'orders_count' => $client->onlineOrders()->count(),
                        // حالة الطلب المكتمل في النظام «delivered» (لا توجد «completed»)
                        'completed_orders' => $client->onlineOrders()->where('status', 'delivered')->count(),
                        'wishlist_count' => $client->wishlistItems()->count(),
                        'reviews_count' => $client->productReviews()->count(),
                        'total_spent' => $client->onlineOrders()->where('status', 'delivered')->sum('total_amount'),
                    ],
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'profile');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
            ], 500);
        }
    }

    /**
     * تحديث الملف الشخصي
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:clients,email,'.$this->client()->id,
            'phone' => 'sometimes|string|max:20|unique:clients,phone,'.$this->client()->id,
            'trade_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female',
            'street1' => 'nullable|string|max:255',
            'street2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:2048',
        ], [
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'phone.unique' => 'رقم الهاتف مستخدم مسبقاً',
            'birth_date.date' => 'تاريخ الميلاد غير صالح',
            'birth_date.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'avatar.image' => 'يجب أن يكون الملف صورة',
            'avatar.max' => 'حجم الصورة يجب أن لا يتجاوز 2 ميجابايت',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();
            $data = $request->only([
                'first_name', 'last_name', 'trade_name', 'birth_date', 'gender',
                'street1', 'street2', 'city', 'region', 'postal_code', 'country',
            ]);

            // رفع الصورة الشخصية
            if ($request->hasFile('avatar')) {
                // حذف الصورة القديمة إذا وجدت
                if ($client->avatar && \Storage::disk('public')->exists($client->avatar)) {
                    \Storage::disk('public')->delete($client->avatar);
                }
                $data['avatar'] = $request->file('avatar')->store('clients/avatars', 'public');
            }

            // التحقق من تغيير البريد الإلكتروني
            if ($request->filled('email') && $request->email !== $client->email) {
                $data['email'] = $request->email;
                $data['email_verified_at'] = null;

                // تحديث البيانات أولاً
                $client->update($data);
                $client->refresh();

                // ثم إرسال رمز تحقق جديد
                $otp = $this->generateOTP();
                $this->storeOTP($client->id, $otp, 'email_verification');
                $this->sendVerificationEmail($client, $otp);

                OperationLog::record('updated', 'client', $client, 'تم تحديث بيانات الملف الشخصي للعميل **'.$client->full_name.'** وتغيير البريد الإلكتروني (بانتظار التحقق)');

                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث البيانات بنجاح. تم إرسال رمز التحقق إلى البريد الجديد.',
                    'data' => [
                        'client' => $this->formatClientData($client),
                        'requires_email_verification' => true,
                    ],
                ]);
            }

            // التحقق من تغيير رقم الهاتف
            if ($request->filled('phone') && $request->phone !== $client->phone) {
                $data['phone'] = $request->phone;
                $data['mobile'] = $request->phone;
                $data['phone_verified_at'] = null;
            }

            // رفع الصورة الشخصية
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('clients/avatars', 'public');
                $data['attachments'] = '/storage/'.$path;
            }

            $client->update($data);

            OperationLog::record('updated', 'client', $client, 'تم تحديث بيانات الملف الشخصي للعميل **'.$client->full_name.'**');

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'data' => [
                    'client' => $this->formatClientData($client->fresh()),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'updateProfile');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تسجيل جهاز للإشعارات
     * Register device for push notifications
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'platform' => 'required|in:ios,android,web',
            'device_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();

            // تحديث أو إنشاء الجهاز
            ClientDevice::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'device_token' => $request->device_token,
                ],
                [
                    'platform' => $request->platform,
                    'device_name' => $request->device_name ?? $request->platform,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الجهاز بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'registerDevice');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الجهاز',
            ], 500);
        }
    }

    /**
     * إزالة جهاز
     * Remove device
     */
    public function removeDevice(Request $request, string $deviceId): JsonResponse
    {
        try {
            $client = $this->client();

            $deleted = ClientDevice::where('client_id', $client->id)
                ->where('id', $deviceId)
                ->delete();

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'الجهاز غير موجود',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الجهاز بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'removeDevice');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة الجهاز',
            ], 500);
        }
    }

    /**
     * قائمة الأجهزة المسجلة
     * List registered devices
     */
    public function devices(Request $request): JsonResponse
    {
        try {
            $client = $this->client();
            $devices = ClientDevice::where('client_id', $client->id)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $devices,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'devices');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
            ], 500);
        }
    }

    /**
     * حذف الحساب
     * Delete account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $this->client();

            // التحقق من كلمة المرور
            if (! Hash::check($request->password, $client->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور غير صحيحة',
                ], 400);
            }

            // التحقق من عدم وجود طلبات قيد التنفيذ
            $pendingOrders = $client->onlineOrders()
                ->whereIn('status', ['pending', 'processing', 'shipped'])
                ->count();

            if ($pendingOrders > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الحساب. لديك طلبات قيد التنفيذ.',
                ], 400);
            }

            DB::beginTransaction();

            // إرسال بريد تأكيد حذف الحساب قبل الحذف
            $this->sendAccountDeletedEmail($client);

            // حذف البيانات المرتبطة
            $client->wishlistItems()->delete();
            $client->devices()->delete();
            $client->cart()->delete();
            $client->tokens()->delete();

            OperationLog::record('deleted', 'client', $client, 'تم حذف حساب العميل **'.$client->full_name.'**');

            // تعطيل الحساب بدلاً من حذفه نهائياً
            $client->update([
                'is_store_client' => false,
                'email' => 'deleted_'.$client->id.'_'.$client->email,
                'phone' => 'deleted_'.$client->id.'_'.$client->phone,
                'deleted_reason' => $request->reason,
                'deleted_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الحساب بنجاح',
            ]);

        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في register() أعلاه
            DB::rollBack();

            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'deleteAccount');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الحساب',
            ], 500);
        }
    }

    /**
     * التحقق من صلاحية التوكن
     * Check token validity
     */
    public function checkToken(Request $request): JsonResponse
    {
        try {
            $client = $this->client();

            if (! $client) {
                return response()->json([
                    'success' => false,
                    'message' => 'التوكن غير صالح',
                    'valid' => false,
                ], 401);
            }

            return response()->json([
                'success' => true,
                'valid' => true,
                'data' => [
                    'client' => $this->formatClientData($client),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'checkToken');

            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'التوكن غير صالح',
            ], 401);
        }
    }

    // ==================== دوال مساعدة ====================

    /**
     * تنسيق بيانات العميل
     */
    private function formatClientData(Client $client): array
    {
        // إنشاء رابط الصورة الكامل
        $avatarUrl = null;
        if ($client->avatar) {
            // إذا كان الرابط كاملاً بالفعل
            if (filter_var($client->avatar, FILTER_VALIDATE_URL)) {
                $avatarUrl = $client->avatar;
            }
            // إذا كان يبدأ بـ /storage/
            elseif (str_starts_with($client->avatar, '/storage/')) {
                $avatarUrl = url($client->avatar);
            }
            // إذا كان يبدأ بـ storage/
            elseif (str_starts_with($client->avatar, 'storage/')) {
                $avatarUrl = url('/'.$client->avatar);
            }
            // إذا كان مسار نسبي
            else {
                $avatarUrl = url('/storage/'.$client->avatar);
            }
        }

        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'full_name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'trade_name' => $client->trade_name,
            'birth_date' => $client->birth_date?->format('Y-m-d'),
            'gender' => $client->gender,
            'gender_label' => $client->gender === 'male' ? 'ذكر' : ($client->gender === 'female' ? 'أنثى' : null),
            'avatar' => $avatarUrl,
            'email_verified' => ! is_null($client->email_verified_at),
            'phone_verified' => ! is_null($client->phone_verified_at),
            'address' => [
                'street1' => $client->street1,
                'street2' => $client->street2,
                'city' => $client->city,
                'region' => $client->region,
                'postal_code' => $client->postal_code,
                'country' => $client->country,
            ],
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }

    /**
     * توليد كود عميل فريد من تسلسل «العملاء» في الإعدادات (يحترم البادئة والأصفار
     * والتصفير الدوري) — كان يزيد `current_number` يدوياً ويرجع بدونها.
     */
    private function generateClientCode(): string
    {
        return next_document_number('customer');
    }

    /**
     * توليد رمز OTP
     */
    private function generateOTP(): string
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * تخزين رمز OTP
     */
    private function storeOTP(int $clientId, string $otp, string $type): void
    {
        $key = "otp_{$clientId}_{$type}";
        Cache::put($key, Hash::make($otp), 600); // صالح لمدة 10 دقائق
    }

    /**
     * التحقق من رمز OTP (دالة داخلية)
     * Verify OTP (internal helper)
     */
    private function checkOTP(int $clientId, string $otp, string $type): bool
    {
        $key = "otp_{$clientId}_{$type}";
        $storedOtp = Cache::get($key);

        if (! $storedOtp) {
            return false;
        }

        return Hash::check($otp, $storedOtp);
    }

    /**
     * حذف رمز OTP
     */
    private function deleteOTP(int $clientId, string $type): void
    {
        $key = "otp_{$clientId}_{$type}";
        Cache::forget($key);
    }

    /**
     * الحصول على المتغيرات الأساسية للإيميل من الإعدادات
     * Get base email variables from settings
     */
    private function getBaseEmailVariables(): array
    {
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();

        return [
            'store_name' => $storeInfo['name'] ?? config('app.name', 'المتجر الإلكتروني'),
            'store_url' => url('/'),
            'store_phone' => $storeInfo['phone'] ?? '',
            'store_email' => $storeInfo['email'] ?? '',
            'support_email' => $storeInfo['email'] ?? config('mail.from.address', 'support@example.com'),
            'store_logo' => $storeInfo['logo'] ?? '',
            'store_address' => $storeInfo['address'] ?? '',
            'unsubscribe_url' => url('/unsubscribe'),
        ];
    }

    /**
     * إرسال بريد الترحيب
     */
    private function sendWelcomeEmail(Client $client): void
    {
        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'auth.welcome',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'customer_email' => $client->email,
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'sendWelcomeEmail', $client);
            \Log::error('فشل إرسال بريد الترحيب: '.$e->getMessage());
        }
    }

    /**
     * إرسال بريد التحقق
     */
    private function sendVerificationEmail(Client $client, string $otp): void
    {
        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'auth.otp_code',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'otp_code' => $otp,
                    'expiry_minutes' => '10',
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'sendVerificationEmail', $client);
            \Log::error('فشل إرسال بريد التحقق: '.$e->getMessage());
        }
    }

    /**
     * إرسال بريد إعادة تعيين كلمة المرور
     */
    private function sendPasswordResetEmail(Client $client, string $otp): void
    {
        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'auth.password_reset',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'reset_url' => url('/reset-password?token='.base64_encode($client->email)),
                    'reset_code' => $otp,
                    'expiry_minutes' => '10',
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'sendPasswordResetEmail', $client);
            \Log::error('فشل إرسال بريد إعادة التعيين: '.$e->getMessage());
        }
    }

    /**
     * إرسال بريد تأكيد تغيير كلمة المرور
     */
    private function sendPasswordChangedEmail(Client $client, Request $request): void
    {
        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'auth.password_changed',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'changed_at' => now()->format('Y-m-d H:i:s'),
                    'ip_address' => $request->ip(),
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'sendPasswordChangedEmail', $client);
            \Log::error('فشل إرسال بريد تأكيد تغيير كلمة المرور: '.$e->getMessage());
        }
    }

    /**
     * إرسال بريد تأكيد حذف الحساب
     */
    private function sendAccountDeletedEmail(Client $client): void
    {
        try {
            $baseVars = $this->getBaseEmailVariables();

            EmailTemplate::sendTemplatedEmail(
                to: $client->email,
                templateCode: 'auth.account_deleted',
                variables: array_merge($baseVars, [
                    'customer_name' => $client->first_name.' '.$client->last_name,
                    'deleted_at' => now()->format('Y-m-d H:i:s'),
                ]),
                recipientType: RecipientType::Customer,
                recipientId: $client->id
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreAuth', 'sendAccountDeletedEmail', $client);
            \Log::error('فشل إرسال بريد تأكيد حذف الحساب: '.$e->getMessage());
        }
    }

    /**
     * إرسال رمز إعادة تعيين كلمة المرور عبر SMS.
     * نفس المنطق: نرسله بالبريد أيضاً لضمان وصوله وتسجيله.
     */
    private function sendPasswordResetSMS(Client $client, string $otp): void
    {
        \Log::info("Password Reset SMS OTP for {$client->phone}: {$otp}");

        if (! empty($client->email)) {
            $this->sendPasswordResetEmail($client, $otp);
        }
    }
}
