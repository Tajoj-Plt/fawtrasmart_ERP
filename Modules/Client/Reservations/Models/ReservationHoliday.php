<?php

namespace Modules\Client\Reservations\Models;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * عطلةٌ أو يومٌ استثنائيّ مفتوح.
 *
 * `is_working = false` ← مغلق وإن كان ضمن الدوام الأسبوعي.
 * `is_working = true`  ← مفتوح وإن كان خارجه، بأوقاتِه الخاصّة.
 */
class ReservationHoliday extends Model
{
    protected $table = 'reservation_holidays';

    protected $fillable = [
        'name', 'start_date', 'end_date', 'branch_id', 'employee_id',
        'is_working', 'start_time', 'end_time', 'notes', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_working' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * العُطَل التي تشمل تاريخاً بعينه — العامّةُ منها وما يخصّ هذا الفرع
     * وهذا الموظف.
     *
     * `whereNull` في الطرفين مقصود: عطلةٌ بلا فرع تسري على الفروع كلّها،
     * وعطلةٌ بلا موظف تسري على الجميع. وحصرُها بالمطابقة التامّة يُسقط
     * العطلَ الوطنية عن كل فرع.
     */
    public function scopeCovering(Builder $query, $date, $branchId = null, $employeeId = null): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            /*
                العطلةُ على جهةٍ تسري على كلّ ما تحتها: `$branchId` قد يكون
                سلسلةَ الجهة وما فوقها (`nodeChain()`)، فعطلةُ «منطقة الرياض»
                تُغلق فروعَها ومنافذَها دون أن تُسجَّل لكلٍّ منها.
            */
            ->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id');

                $ids = array_values(array_filter(array_map('intval', (array) $branchId)));

                if ($ids !== []) {
                    $q->orWhereIn('branch_id', $ids);
                }
            })
            ->where(function (Builder $q) use ($employeeId) {
                $q->whereNull('employee_id');

                if ($employeeId) {
                    $q->orWhere('employee_id', (int) $employeeId);
                }
            });
    }
}
