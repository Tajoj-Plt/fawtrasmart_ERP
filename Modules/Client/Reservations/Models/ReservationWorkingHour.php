<?php

namespace Modules\Client\Reservations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * فترةُ دوامٍ واحدة — لفرعٍ أو لموظّف.
 *
 * **الصفُّ فترةٌ لا يوم.** يومٌ بفترتين صفّان بنفس `weekday`، والاستراحةُ
 * بينهما هي الفجوة. فلا يحتاج «تعدّد الفترات» بنيةً إضافية.
 */
class ReservationWorkingHour extends Model
{
    public const SCOPE_BRANCH = 'branch';

    public const SCOPE_EMPLOYEE = 'employee';

    protected $table = 'reservation_working_hours';

    protected $fillable = ['scope', 'scope_id', 'weekday', 'start_time', 'end_time', 'is_closed'];

    protected $casts = [
        'scope_id' => 'integer',
        'weekday' => 'integer',
        'is_closed' => 'boolean',
    ];

    /** أسماءُ الأيام — الفهرس يوافق `Carbon::dayOfWeek` بلا تحويل. */
    public const WEEKDAYS = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function scopeFor(Builder $query, string $scope, $scopeId): Builder
    {
        return $query->where('scope', $scope)->where('scope_id', (int) $scopeId);
    }

    public function getWeekdayLabelAttribute(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '—';
    }
}
