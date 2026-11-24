<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDays;
use App\Models\AttendancePunch;

/**
 * نتيجةُ محاولة ختمٍ واحدة — قبولٌ أو ردٌّ باسم الحلقة التي ردّت.
 *
 * `stage` ليست تفصيلاً تجميلياً: هي الفرقُ بين «رُفض تسجيلُك» و«رُفض
 * لأنك خارج نطاق المرفق بـ٤٠٠ متر» — والثانيةُ وحدها تُخبر الموظفَ
 * ماذا يفعل، وتُخبر الموارد البشرية أين الخلل حين تتكرّر.
 */
class StampResult
{
    public const STAGE_EMPLOYEE = 'employee';

    public const STAGE_SHIFT = 'shift';

    public const STAGE_LOCATION = 'location';

    public const STAGE_DAY = 'day';

    public const STAGE_LEAVE = 'leave';

    public const STAGE_PHOTOS = 'photos';

    private function __construct(
        public readonly bool $accepted,
        public readonly string $message,
        public readonly ?string $stage = null,
        public readonly ?string $code = null,
        public readonly ?AttendancePunch $punch = null,
        public readonly ?AttendanceDays $day = null,
        public readonly array $warnings = [],
        public readonly array $data = [],
        public readonly int $httpStatus = 200,
    ) {}

    public static function accepted(
        string $message,
        AttendancePunch $punch,
        AttendanceDays $day,
        array $warnings = [],
        array $data = [],
    ): self {
        return new self(
            accepted: true,
            message: $message,
            punch: $punch,
            day: $day,
            warnings: $warnings,
            data: $data,
        );
    }

    public static function rejected(
        string $stage,
        string $code,
        string $message,
        ?AttendancePunch $punch = null,
        int $httpStatus = 422,
    ): self {
        return new self(
            accepted: false,
            message: $message,
            stage: $stage,
            code: $code,
            punch: $punch,
            httpStatus: $httpStatus,
        );
    }

    public function toResponsePayload(): array
    {
        return array_filter([
            'success' => $this->accepted,
            'message' => $this->message,
            'stage' => $this->stage,
            'code' => $this->code,
            'warnings' => $this->warnings ?: null,
            'data' => $this->data ?: null,
            'punch_id' => $this->punch?->id,
        ], fn ($value) => $value !== null);
    }
}
