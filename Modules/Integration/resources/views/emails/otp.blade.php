<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background-color:#f4f5fa; font-family: Tahoma, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0"
                       style="background:#ffffff; border-radius:8px; padding:32px; text-align:right;">
                    <tr>
                        <td>
                            <h2 style="color:#333; margin:0 0 16px;">
                                مرحباً {{ $userName }}
                            </h2>
                            <p style="color:#555; font-size:15px; line-height:1.8; margin:0 0 24px;">
                                @if ($purpose === \Modules\Integration\Models\IntegrationOtp::PURPOSE_PASSWORD_RESET)
                                    وصلنا طلبٌ لاستعادة كلمة المرور لحسابك. استخدم الرمز التالي في التطبيق لإتمام العملية:
                                @else
                                    استخدم الرمز التالي في التطبيق لتوثيق بريدك الإلكتروني:
                                @endif
                            </p>
                            <div style="background:#f4f5fa; border-radius:8px; padding:16px; text-align:center; margin-bottom:24px;">
                                <span style="font-size:32px; letter-spacing:8px; font-weight:bold; color:#7367f0;">{{ $code }}</span>
                            </div>
                            <p style="color:#888; font-size:13px; line-height:1.8; margin:0;">
                                الرمز صالح لمدة {{ config('integration.otp.ttl_minutes', 10) }} دقائق.
                                إن لم تكن أنت من طلب هذا الرمز فتجاهل هذه الرسالة — لن يتغيّر شيء في حسابك.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
