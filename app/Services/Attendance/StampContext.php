<?php

namespace App\Services\Attendance;

use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use Illuminate\Http\Request;

/**
 * ظرفُ الختم: من أين جاء، وبأيّ وسيلة، وما الذي قاسه الجهاز.
 *
 * كائنٌ واحدٌ يعبر حلقاتِ التسلسل كلَّها بدل تمرير ستّة وسائطَ متفرّقة
 * تتبدّل أسماؤها بين طبقةٍ وأخرى. وكلُّ حقلٍ هنا يُملأ من **الخادم أو
 * الجهاز**، لا من حقلٍ يكتبه الموظف: عنوانُ IP من `$request->ip()` لا من
 * جسم الطلب، والوقتُ من ساعة الخادم لا من ساعة الجوال.
 */
class StampContext
{
    public function __construct(
        public readonly string $punchType,
        public readonly string $channel,
        public readonly string $method,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $accuracyMeters = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $wifiSsid = null,
        public readonly ?string $userAgent = null,
        public readonly ?AttendanceKiosk $kiosk = null,
        public readonly ?string $rawBarcode = null,
        public readonly ?int $userId = null,
        public readonly array $photos = [],
        public readonly ?string $entryNote = null,
    ) {}

    /**
     * يبني الظرفَ من الطلب مع تثبيت ما لا يجوز أن يأتي من العميل.
     */
    public static function fromRequest(
        Request $request,
        string $punchType,
        string $channel,
        string $method,
        ?AttendanceKiosk $kiosk = null,
    ): self {
        $hasCoordinates = $request->filled('latitude') && $request->filled('longitude');

        return new self(
            punchType: $punchType,
            channel: $channel,
            method: $method,
            latitude: $hasCoordinates ? (float) $request->input('latitude') : null,
            longitude: $hasCoordinates ? (float) $request->input('longitude') : null,
            accuracyMeters: $request->filled('accuracy') ? (int) round((float) $request->input('accuracy')) : null,
            ipAddress: $request->ip(),
            wifiSsid: $request->input('wifi_ssid'),
            userAgent: $request->userAgent(),
            kiosk: $kiosk,
            rawBarcode: $request->input('barcode'),
            userId: $request->user()?->id,
            photos: (array) $request->input('photos', []),
            entryNote: $request->input('reason'),
        );
    }

    public function isCheckIn(): bool
    {
        return $this->punchType === AttendancePunch::TYPE_IN;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * ظرفٌ جديد بإحداثيات — يُستعمل حين يغيب GPS الجهاز فيُؤخذ موقع
     * نطاق المرفق المقترن كي لا يبقى سجل الحضور بلا موقع.
     */
    public function withCoordinates(?float $latitude, ?float $longitude, ?int $accuracyMeters = null): self
    {
        return new self(
            punchType: $this->punchType,
            channel: $this->channel,
            method: $this->method,
            latitude: $latitude,
            longitude: $longitude,
            accuracyMeters: $accuracyMeters ?? $this->accuracyMeters,
            ipAddress: $this->ipAddress,
            wifiSsid: $this->wifiSsid,
            userAgent: $this->userAgent,
            kiosk: $this->kiosk,
            rawBarcode: $this->rawBarcode,
            userId: $this->userId,
            photos: $this->photos,
            entryNote: $this->entryNote,
        );
    }
}
