<?php

namespace App\Services\Attendance;

use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\AttendanceEntryGuard;

/**
 * الحلقةُ الثالثة من التسلسل: مطابقةُ إحداثيات الجوال أو شبكة الفرع
 * بالنطاق المسموح.
 *
 * ترتيبُ الترشيح مقصود — الأخصُّ أوّلاً:
 *   ١) نطاقُ الجهاز الثابت إن كان الختمُ منه (الجهازُ في مكانٍ بعينه).
 *   ٢) نطاقُ فرع الموظف (المرفقُ الذي يتبعه).
 *   ٣) أيُّ نطاقٍ نشطٍ بلا فرع — سياسةٌ عامّةٌ لمنشأةٍ ذات موقعٍ واحد.
 * ثم لا شيء: بلا نطاقٍ مُعَدّ، لا قيدَ جغرافياً. وهذا اختيارٌ صريح —
 * تفعيلُ الميزة يبدأ بإنشاء نطاق، لا بحجب كلِّ تسجيلٍ في المنشأة يومَ
 * تُرحَّل الهجرة.
 *
 * و`AttendanceEntryGuard` (محدِّدات الحضور القديمة) تبقى طبقةً ثانية
 * فوق هذه: ما هو مُعَدٌّ اليوم في شاشة المحدِّدات يظلّ نافذاً.
 */
class GeofenceGuard
{
    public function __construct(private readonly AttendanceEntryGuard $determinantGuard) {}

    public function resolve(Employee $employee, ?AttendanceKiosk $kiosk = null, ?string $channel = null): ?AttendanceGeofence
    {
        /*
            الموظفُ الميداني لا يُقاس على مكتب فرعه — عملُه عند العملاء
            وفي المواقع. مكانُه ما حُدِّد له في «بطاقات الحضور» (موقع مشروع،
            منطقة عمل)، وإلّا فلا دائرة: يُقبل من أيّ مكانٍ ويُحفظ موقعُه
            على الخريطة في سجلّ التسجيلات.
        */
        if ($channel === AttendancePunch::CHANNEL_FIELD) {
            return $employee->attendance_geofence_id
                ? AttendanceGeofence::active()->find($employee->attendance_geofence_id)
                : null;
        }

        if ($kiosk?->geofence_id) {
            $fence = AttendanceGeofence::active()->find($kiosk->geofence_id);

            if ($fence) {
                return $fence;
            }
        }

        if ($employee->branch_id) {
            $fence = AttendanceGeofence::active()->where('branch_id', $employee->branch_id)->first();

            if ($fence) {
                return $fence;
            }
        }

        return AttendanceGeofence::active()->whereNull('branch_id')->first();
    }

    /**
     * @return array{allowed: bool, code: ?string, message: ?string, distance: ?int, warnings: array<int, string>}
     */
    public function verify(?AttendanceGeofence $fence, StampContext $context, Employee $employee): array
    {
        $warnings = [];
        $distance = $fence?->distanceFrom($context->latitude, $context->longitude);

        if ($fence) {
            $verdict = $this->verifyAgainstFence($fence, $context, $distance);

            if ($verdict !== null) {
                return $verdict + ['distance' => $distance, 'warnings' => $warnings];
            }
        }

        /*
            الطبقةُ الثانية: محدِّدُ الحضور المُسنَد لهذا الموظف. تحذيراتُه
            تُمرَّر ولا تمنع (`location_investigation = 2` يعني «سجّل ونبّه»)،
            وأخطاؤه تمنع.
        */
        $determinant = $this->determinantGuard->check($employee, [
            'location' => $context->hasCoordinates()
                ? ['latitude' => $context->latitude, 'longitude' => $context->longitude]
                : null,
            'ip_address' => $context->ipAddress,
            /*
                صورةُ المحدِّد: كانت الشاشة تُلزم الصورة ولا يصل شيء من
                المسح. أيُّ صورةٍ مرفقة بالختم (photos[].image) تكفي.
            */
            'image' => $this->firstPhotoPayload($context),
        ]);

        $warnings = array_merge($warnings, $determinant['warnings']);

        if (! $determinant['allowed']) {
            return [
                'allowed' => false,
                'code' => 'determinant_rejected',
                'message' => implode(' • ', $determinant['errors']),
                'distance' => $distance,
                'warnings' => $warnings,
            ];
        }

        return [
            'allowed' => true,
            'code' => null,
            'message' => null,
            'distance' => $distance,
            'warnings' => $warnings,
        ];
    }

    /**
     * فحصُ النطاق وحده. يُرجِع `null` حين يجتاز الختمُ كلَّ شروطه.
     *
     * @return array{allowed: bool, code: string, message: string}|null
     */
    private function verifyAgainstFence(AttendanceGeofence $fence, StampContext $context, ?int $distance): ?array
    {
        if ($fence->require_network) {
            $networkVerdict = $this->verifyNetwork($fence, $context);

            if ($networkVerdict !== null) {
                return $networkVerdict;
            }
        }

        if (! $fence->require_geo) {
            return null;
        }

        /*
            جهازُ الفرع المربوط بهذا المكان لا يحتاج إحداثيات: مكانُه معلومٌ
            بالربط نفسه، وأغلبُه شاشةٌ ثابتة بلا GPS. أما الجوالُ فلا يُعرف
            مكانُه إلا بها.

            **ومفتاحُ «يُشترَط الموقع على هذا الجهاز» يُلغي هذا الإعفاء**:
            «جهازُ الفرع» قد يكون جوّالَ مشرفٍ أو لوحاً متنقّلاً يُقترَن
            مرّةً ثمّ يُحمل، فلا يُثبت الربطُ وحده مكانَ المسح. ومن شغّله
            لزمت الإحداثياتُ على الجهاز كما تلزم على الجوال، ويُفحَص نصفُ
            القطر والدقّةُ أدناه. انظر
            `2026_10_03_130000_add_require_location_to_attendance_kiosks`.
        */
        if (! $context->hasCoordinates()
            && $context->kiosk
            && ! $context->kiosk->require_location
            && (int) $context->kiosk->geofence_id === (int) $fence->id) {
            return null;
        }

        if (! $context->hasCoordinates()) {
            return [
                'allowed' => false,
                'code' => 'location_required',
                'message' => 'تفعيلُ خدمة الموقع مطلوبٌ للتسجيل — لم تصل إحداثياتٌ من الجهاز.',
            ];
        }

        /*
            دقّةُ القراءة قبل المسافة: جوالٌ يبلّغ هامشَ خطأٍ ٢ كم قد يكون
            «داخل النطاق» بالصدفة. ردُّه بطلب قراءةٍ أدقّ أصدقُ من قبولٍ
            لا يُثبت شيئاً — ومن رفضٍ يقول «أنت خارج النطاق» وهو لا يعلم.
        */
        if ($fence->max_accuracy_meters > 0
            && $context->accuracyMeters !== null
            && $context->accuracyMeters > $fence->max_accuracy_meters) {
            return [
                'allowed' => false,
                'code' => 'location_inaccurate',
                'message' => sprintf(
                    'دقّةُ الموقع غير كافية (±%d متر، والمسموح ±%d). انتقل إلى مكانٍ مكشوف وأعد المحاولة.',
                    $context->accuracyMeters,
                    $fence->max_accuracy_meters
                ),
            ];
        }

        if (! $fence->hasCircle()) {
            // نطاقٌ يُلزم الإحداثيات ولا مركزَ له: إعدادٌ ناقص، لا يُحتسب قيداً.
            return null;
        }

        if ($distance === null || $distance > $fence->radius_meters) {
            return [
                'allowed' => false,
                'code' => 'outside_geofence',
                'message' => sprintf(
                    'أنت خارج نطاق «%s» — تبعد %s متراً والمسموح %d متر.',
                    $fence->name,
                    $distance === null ? 'مسافةً غير معلومة' : number_format($distance),
                    $fence->radius_meters
                ),
            ];
        }

        return null;
    }

    /**
     * @return array{allowed: bool, code: string, message: string}|null
     */
    private function verifyNetwork(AttendanceGeofence $fence, StampContext $context): ?array
    {
        $ranges = array_filter((array) $fence->allowed_ip_ranges);
        $ssids = array_filter((array) $fence->allowed_wifi_ssids);

        // شبكةٌ مطلوبةٌ بلا قائمةٍ تُطابَق بها: إعدادٌ ناقص، لا قيد.
        if ($ranges === [] && $ssids === []) {
            return null;
        }

        if ($ranges !== [] && $context->ipAddress !== null && self::ipMatches($context->ipAddress, $ranges)) {
            return null;
        }

        if ($ssids !== [] && $context->wifiSsid !== null
            && in_array($context->wifiSsid, $ssids, true)) {
            return null;
        }

        return [
            'allowed' => false,
            'code' => 'network_not_allowed',
            'message' => sprintf('التسجيلُ من هذه الشبكة غير مسموح — استخدم شبكة «%s».', $fence->name),
        ];
    }

    /**
     * مطابقةُ عنوانٍ بقائمةِ مدياتٍ بصيغة CIDR أو عناوينَ مفردة.
     *
     * `AttendanceDeterminant::isValidIP` كانت تُطابق بـ`in_array` الحرفية:
     * شبكةُ مقرٍّ كاملةٌ بـDHCP تعني تعدادَ ٢٥٤ عنواناً يدوياً في الإعداد،
     * ثم بطلانَها كلَّها حين يتغيّر المدى. الشبكةُ تُوصَف بمداها.
     */
    public static function ipMatches(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            $range = trim((string) $range);

            if ($range === '') {
                continue;
            }

            if (! str_contains($range, '/')) {
                if ($range === $ip) {
                    return true;
                }

                continue;
            }

            [$subnet, $bits] = explode('/', $range, 2);
            $bits = (int) $bits;

            $ipBinary = @inet_pton($ip);
            $subnetBinary = @inet_pton(trim($subnet));

            // عائلتان مختلفتان (IPv4 مقابل IPv6) لا تتقابلان
            if ($ipBinary === false || $subnetBinary === false
                || strlen($ipBinary) !== strlen($subnetBinary)) {
                continue;
            }

            if ($bits < 0 || $bits > strlen($ipBinary) * 8) {
                continue;
            }

            $wholeBytes = intdiv($bits, 8);
            $remainderBits = $bits % 8;

            if ($wholeBytes > 0 && strncmp($ipBinary, $subnetBinary, $wholeBytes) !== 0) {
                continue;
            }

            if ($remainderBits === 0) {
                return true;
            }

            $mask = ~((1 << (8 - $remainderBits)) - 1) & 0xFF;

            if ((ord($ipBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * أول حمولة صورة من ختم المسح — لشرط «التقاط صورة الموظف» في المحدِّد.
     */
    private function firstPhotoPayload(StampContext $context): ?string
    {
        foreach ($context->photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $image = $photo['image'] ?? $photo['data'] ?? null;
            if (is_string($image) && $image !== '') {
                return $image;
            }
        }

        return null;
    }
}
