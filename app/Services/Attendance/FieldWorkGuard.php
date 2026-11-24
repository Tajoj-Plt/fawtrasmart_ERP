<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDays;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;

/**
 * الزيارةُ داخل الدوام: الموظفُ الميداني لا يبدأ زيارةَ عميلٍ إلا وهو في دوامه.
 *
 * كانت «بدء الزيارة» تُقبل في أي ساعة — قبل تسجيل الحضور، وبعد الانصراف،
 * وأثناء الاستراحة — فيظهر في الإقفال اليومي وقتٌ عند العملاء خارج الدوام
 * المسجَّل، ولا يُعرف أيُّهما الصحيح.
 *
 * ثمّ صارت القيودُ الثلاثةُ **إعداداً** لا قاعدةً مثبَّتة — انظر
 * {@see visitStartBlock()}.
 *
 * القيدُ على الميداني وحده (`attendance_channel` = field | both): موظفُ
 * الفرع لا يزور عملاء، ومن لا ملفَّ وظيفيّاً له (حساب إداري) لا حضورَ له.
 *
 * والجسر: الزياراتُ تحمل `users.id`، والحضورُ يحمل `employees.id` —
 * `users.employee_id` بينهما.
 */
class FieldWorkGuard
{
    public function __construct(private readonly AttendanceStampService $stamps) {}

    public function employeeForUser(?int $userId): ?Employee
    {
        $employeeId = $userId ? User::whereKey($userId)->value('employee_id') : null;

        return $employeeId ? Employee::find($employeeId) : null;
    }

    public function isFieldEmployee(?Employee $employee): bool
    {
        return $employee !== null && in_array($employee->attendance_channel, ['field', 'both'], true);
    }

    /** يومُ الدوام المفتوح الآن (يراعي الوردية الليلية). */
    public function currentDay(Employee $employee): ?AttendanceDays
    {
        return $this->stamps->dayRow($employee, $this->stamps->resolveCheckoutDate($employee, Carbon::today()));
    }

    /**
     * سببُ منع «بدء الزيارة» الآن، أو null إن كانت مسموحة.
     *
     * **ثلاثةُ قيودٍ، لكلٍّ مفتاحُه** في «الدوام والحضور» (مشغَّلةٌ افتراضاً
     * كما كانت): اشتراطُ الحضور، والمنعُ بعد الانصراف، والمنعُ في الاستراحة.
     * كانت مثبَّتةً بلا مفتاح — وهي سياسةُ منشأةٍ لا قانونَ طبيعة: منشأةٌ
     * يبدأ مندوبُها طريقَه إلى أوّل عميلٍ ثمّ يسجّل حضورَه من عنده لم تكن
     * تستطيع ذلك أصلاً.
     *
     * @see AttendanceSetting
     * @see 2026_10_03_140000_add_visit_shift_gates_to_attendance_settings
     */
    public function visitStartBlock(?int $userId): ?string
    {
        $employee = $this->employeeForUser($userId);

        if (! $this->isFieldEmployee($employee)) {
            return null;
        }

        $setting = AttendanceSetting::current();

        // الأعمدةُ قد تغيب في قاعدةٍ لم يُرحَّل عليها بعد — والغيابُ يعني «مشغَّل» كما كان
        $enabled = fn (string $key) => (bool) ($setting->{$key} ?? true);

        $day = $this->currentDay($employee);

        if ($enabled('visit_requires_check_in') && (! $day || ! $day->check_in_time)) {
            return 'سجّل حضورك أولاً من «سجّل حضوري» — لا تبدأ زيارة قبل بداية دوامك.';
        }

        if ($day && $day->check_out_time && $enabled('visit_blocked_after_check_out')) {
            return 'سجّلت انصرافك اليوم الساعة '.substr((string) $day->check_out_time, 0, 5).' — لا تبدأ زيارة بعد انتهاء دوامك.';
        }

        if ($day && $day->break_started_at && $enabled('visit_blocked_during_break')) {
            return 'أنت في استراحة منذ '.$day->break_started_at->format('H:i').' — أنهِ الاستراحة من «سجّل حضوري» ثم ابدأ الزيارة.';
        }

        return null;
    }

    /**
     * زيارةٌ بدأها الموظف ولم يُنهِها — تمنع الاستراحةَ والانصراف.
     */
    public function openVisit(Employee $employee): ?Visit
    {
        $userId = User::where('employee_id', $employee->id)->value('id');

        if (! $userId) {
            return null;
        }

        return Visit::where('employee_id', $userId)
            ->whereNotNull('started_at')
            ->whereNull('departure_time')
            ->whereIn('stage', [Visit::STAGE_IN_PROGRESS, Visit::STAGE_PAUSED])
            ->where('started_at', '>=', Carbon::now()->subDay())
            ->with('client')
            ->latest('started_at')
            ->first();
    }

    public function openVisitMessage(Visit $visit, string $action): string
    {
        $client = $visit->client?->display_name ?? 'العميل';

        return 'لديك زيارة مفتوحة عند «'.$client.'» بدأت '.$visit->started_at->format('H:i')
            .' — أنهِ الزيارة أولاً ثم '.$action.'.';
    }
}
