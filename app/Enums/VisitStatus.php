<?php

namespace App\Enums;

/**
 * دورة حياة زيارة خط السير (`employee_client_visits.visit_status`)
 * منفصلة عن العمود القديم `status` (active/unactive) الذي تعتمد عليه
 * أنظمة أخرى ولا يُمسّ.
 */
enum VisitStatus: string
{
    /**
     * مخطَّطة - لم تبدأ بعد
     */
    case PLANNED = 'planned';

    /**
     * جارية - بدأ المندوب الزيارة
     */
    case STARTED = 'started';

    /**
     * مكتملة - انتهت الزيارة
     */
    case COMPLETED = 'completed';

    /**
     * فائتة - انقضى يومها ولم تُنفَّذ
     */
    case MISSED = 'missed';

    /**
     * ملغاة
     */
    case CANCELLED = 'cancelled';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'مخطَّطة',
            self::STARTED => 'جارية',
            self::COMPLETED => 'مكتملة',
            self::MISSED => 'فائتة',
            self::CANCELLED => 'ملغاة',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::STARTED => 'Started',
            self::COMPLETED => 'Completed',
            self::MISSED => 'Missed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * الحصول على لون الحالة للعرض (Bootstrap badge)
     */
    public function color(): string
    {
        return match ($this) {
            self::PLANNED => 'secondary',
            self::STARTED => 'primary',
            self::COMPLETED => 'success',
            self::MISSED => 'danger',
            self::CANCELLED => 'dark',
        };
    }
}
