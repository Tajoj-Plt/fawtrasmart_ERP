<?php

namespace App\Services\Attendance;

use App\Models\AttendanceKiosk;

/**
 * «رمز المكتب» — رمزٌ في المكتب يمسحه الموظفُ بجواله فيُسجَّل حضورُه.
 *
 * الرمزُ نصٌّ موقَّع: `FATT|<kiosk>|<part>|<sig>` والتوقيعُ HMAC بمفتاح
 * التطبيق، فلا يُصنع رمزٌ لجهازٍ دون الخادم.
 *
 *   • رمزُ الشاشة: `part` = رقمُ نافذة 30 ثانية، ومفتاحُ توقيعه يتضمّن بصمةَ
 *     الشاشة المقترنة — فلا يُصدَر إلا لشاشة الجهاز نفسِها، ويسقط إن فُكّ
 *     اقترانُها. ويُقبل في نافذته والتي قبلها (دقيقةٌ على الأكثر)، فصورتُه
 *     المرسَلةُ إلى من في البيت تنتهي قبل أن تُستعمل.
 *   • الرمزُ المطبوع: `part` = `P<version>` ثابت — يُقبل فقط وموقعُ الجوال داخل
 *     مكان الجهاز (يفحصه المتحكّم والختم)، ويُبطله «رمز مطبوع جديد».
 */
class OfficeCode
{
    public const PREFIX = 'FATT';

    public const WINDOW_SECONDS = 30;

    public const MODES = [
        'off' => 'غير مفعّل',
        'screen' => 'رمز متغيّر على شاشة الجهاز',
        'printed' => 'رمز مطبوع (يشترط موقع الجوال داخل المكان)',
        'both' => 'الاثنين',
    ];

    public static function allowsScreen(AttendanceKiosk $kiosk): bool
    {
        return in_array($kiosk->office_qr, ['screen', 'both'], true);
    }

    public static function allowsPrinted(AttendanceKiosk $kiosk): bool
    {
        return in_array($kiosk->office_qr, ['printed', 'both'], true);
    }

    public function screenCode(AttendanceKiosk $kiosk, ?int $now = null): ?string
    {
        if (! self::allowsScreen($kiosk) || ! $kiosk->device_token_hash) {
            return null;
        }

        $window = intdiv($now ?? now()->getTimestamp(), self::WINDOW_SECONDS);

        return $this->build($kiosk, (string) $window, $this->screenKey($kiosk));
    }

    public function printedCode(AttendanceKiosk $kiosk): ?string
    {
        if (! self::allowsPrinted($kiosk)) {
            return null;
        }

        return $this->build($kiosk, 'P'.(int) $kiosk->office_qr_version, $this->printedKey($kiosk));
    }

    /**
     * @return array{ok: true, kiosk: AttendanceKiosk, mode: string}|array{ok: false, message: string}
     */
    public function resolve(string $code, ?int $now = null): array
    {
        $parts = explode('|', trim($code));

        if (count($parts) !== 4 || $parts[0] !== self::PREFIX || ! ctype_digit($parts[1])) {
            return ['ok' => false, 'message' => 'هذا ليس رمز حضور المكتب — امسح الرمز الظاهر على شاشة الجهاز أو الملصق في المكتب.'];
        }

        [, $kioskId, $part, $signature] = $parts;
        $kiosk = AttendanceKiosk::active()->with('geofence')->find((int) $kioskId);

        if (! $kiosk || $kiosk->office_qr === 'off') {
            return ['ok' => false, 'message' => 'رمز المكتب هذا موقوف — راجع الموارد البشرية.'];
        }

        if (str_starts_with($part, 'P')) {
            $valid = self::allowsPrinted($kiosk)
                && $part === 'P'.(int) $kiosk->office_qr_version
                && hash_equals($this->sign($kiosk, $part, $this->printedKey($kiosk)), $signature);

            return $valid
                ? ['ok' => true, 'kiosk' => $kiosk, 'mode' => 'printed']
                : ['ok' => false, 'message' => 'هذا الرمز المطبوع قديم أو غير صالح — اطلب الملصق الجديد.'];
        }

        if (! ctype_digit($part) || ! self::allowsScreen($kiosk) || ! $kiosk->device_token_hash) {
            return ['ok' => false, 'message' => 'رمز الشاشة غير صالح — امسح الرمز الظاهر الآن على شاشة الجهاز.'];
        }

        $current = intdiv($now ?? now()->getTimestamp(), self::WINDOW_SECONDS);
        $window = (int) $part;

        if ($window > $current || $window < $current - 1) {
            return ['ok' => false, 'message' => 'انتهت صلاحية هذا الرمز — امسح الرمز الظاهر الآن على شاشة الجهاز (يتغيّر كل 30 ثانية).'];
        }

        return hash_equals($this->sign($kiosk, $part, $this->screenKey($kiosk)), $signature)
            ? ['ok' => true, 'kiosk' => $kiosk, 'mode' => 'screen']
            : ['ok' => false, 'message' => 'رمز الشاشة غير صالح — امسح الرمز الظاهر الآن على شاشة الجهاز.'];
    }

    private function build(AttendanceKiosk $kiosk, string $part, string $key): string
    {
        return implode('|', [self::PREFIX, $kiosk->id, $part, $this->sign($kiosk, $part, $key)]);
    }

    private function sign(AttendanceKiosk $kiosk, string $part, string $key): string
    {
        return substr(hash_hmac('sha256', $kiosk->id.'|'.$part, $key), 0, 20);
    }

    private function screenKey(AttendanceKiosk $kiosk): string
    {
        return config('app.key').'|office-qr-screen|'.$kiosk->device_token_hash;
    }

    private function printedKey(AttendanceKiosk $kiosk): string
    {
        return config('app.key').'|office-qr-printed';
    }
}
