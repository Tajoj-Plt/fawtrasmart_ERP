<?php

namespace Modules\Integration\Services;

use App\Enums\RecipientType;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Integration\Emails\OtpMail;
use Modules\Integration\Models\IntegrationOtp;

/**
 * توليد رموز التحقق والتثبّت منها — قناة البريد الإلكتروني.
 *
 * الرمز يُخزَّن مُجزّأً، وله صلاحية زمنية وعدد محاولات محدود، ورمزٌ جديد
 * يُبطل ما قبله لنفس الغرض. الإعدادات في config/config.php للوحدة.
 */
class OtpService
{
    /**
     * توليد رمز جديد وإرساله لبريد المستخدم.
     *
     * @return bool أُرسل فعلاً؟ (false = طلبٌ متكرر قبل انقضاء فاصل الإعادة)
     */
    public function send(User $user, string $purpose): bool
    {
        $resendAfter = (int) config('integration.otp.resend_after_seconds', 60);

        // منع إغراق البريد: طلبٌ جديد قبل انقضاء الفاصل يُرفض بهدوء
        $recent = IntegrationOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>=', now()->subSeconds($resendAfter))
            ->exists();

        if ($recent) {
            return false;
        }

        // رمزٌ جديد يُبطل كل ما سبقه لنفس الغرض
        IntegrationOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $length = (int) config('integration.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $ttl = (int) config('integration.otp.ttl_minutes', 10);

        IntegrationOtp::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->deliver($user, $code, $purpose, $ttl);

        return true;
    }

    /**
     * إرسال الرمز عبر نظام قوالب البريد (`auth.otp_code` — قابل للتحرير من
     * شاشة قوالب البريد، ويُسجَّل في email_logs). إن كان القالب محذوفاً أو
     * معطَّلاً لا يضيع الرمز بصمت — يُرسَل بقالب الوحدة الاحتياطي.
     */
    protected function deliver(User $user, string $code, string $purpose, int $ttl): void
    {
        $templateExists = EmailTemplate::query()
            ->byCode('auth.otp_code')
            ->active()
            ->exists();

        if ($templateExists) {
            EmailTemplate::sendTemplatedEmail(
                to: $user->email,
                templateCode: 'auth.otp_code',
                variables: [
                    'customer_name' => $user->name,
                    'otp_code' => $code,
                    'expiry_minutes' => (string) $ttl,
                    'store_name' => config('app.name'),
                    'store_url' => config('app.url'),
                ],
                recipientType: RecipientType::Employee,
                recipientId: $user->id,
            );

            return;
        }

        Mail::to($user->email)->send(new OtpMail($user->name, $code, $purpose));
    }

    /**
     * التثبّت من رمز.
     *
     * @param  bool  $consume  true = يُستهلك الرمز عند النجاح فلا يُستعمل ثانية
     */
    public function verify(User $user, string $purpose, string $code, bool $consume = true): bool
    {
        $otp = IntegrationOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            return false;
        }

        $maxAttempts = (int) config('integration.otp.max_attempts', 5);

        if ($otp->attempts >= $maxAttempts) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        if ($consume) {
            $otp->update(['consumed_at' => now()]);
        }

        return true;
    }
}
