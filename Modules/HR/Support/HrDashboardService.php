<?php

namespace Modules\HR\Support;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\Contract;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\SalaryAdvanceRequest;
use App\Support\CurrencySettings;
use App\Support\DurationLabel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * محرّكُ لوحة تحكّم الموظفين — على قاعدة لوحة العملاء.
 *
 * **القاعدةُ الواحدة: كلُّ رقمٍ يفتح موظفيه.**
 *
 * كلُّ مقياسٍ يُبنى **خريطةً** (`employeeId => تفاصيل`) ثمّ يُعَدُّ منها،
 * و{@see drillDown()} يقرأ الخريطةَ نفسَها. فطولُ القائمة التي تُفتح يساوي
 * الرقمَ المعروض حتماً — لا عدٌّ بـ`count()` على استعلامٍ وقائمةٌ تُبنى
 * باستعلامٍ آخر يفترقان مع أوّل شرطٍ يُنسى. وهي عينُ العلّة التي من أجلها
 * أُعيد بناءُ `App\Services\ClientDashboardService`.
 *
 * **ولا رقمَ بلا تعريفٍ دقيق.** لكلّ شريحةٍ سطرٌ يقول من أين تُقرأ، فلا
 * يُترك المستخدمُ يحدس: «غائب اليوم» من `attendance_days.status` لا من غياب
 * صفٍّ، و«لم يُسجَّل» موظفٌ نشطٌ في يومِ عملٍ بلا صفٍّ أصلاً — والفرقُ
 * بينهما هو الفرقُ بين غيابٍ محسوبٍ ومهمّةٍ ليلية لم تعمل.
 *
 * **الفترةُ تُطبَّق على الحركة وحدها** (التعيين، وأيام الحضور، والصرفات،
 * والطلبات) — لا على قاعدة الموظفين: لوحةٌ تُنقص عدّةَ الموظفين بتغيير
 * الفترة تقرأ «القوى العاملة» خطأً. ونفسُ الدرس في لوحة العملاء.
 *
 * @see \Modules\HR\Http\Controllers\DashboardController
 */
class HrDashboardService
{
    /** تسمياتُ حالات مسير الراتب — لا خريطةَ لها في النموذج */
    private const PAYROLL_STATUSES = [
        'draft' => 'مسوّدة',
        'pending' => 'بانتظار الصرف',
        'approved' => 'معتمد',
        'disbursed' => 'مصروف',
        'reversed' => 'معكوس',
        'cancelled' => 'ملغى',
    ];

    /** @var array<int, string> اسمُ الوردية برقمها */
    private array $shifts = [];

    /** @var array<int, Employee> الموظفون في النطاق — مفتاحُهم رقمُهم */
    private array $employees = [];

    /** @var array<string, array<int, array<string, mixed>>> خرائطُ الشرائح */
    private array $maps = [];

    /** @var array<string, array<string, mixed>> وصفُ كل شريحةٍ: عنوانُها وتعريفُها وأعمدتُها */
    private array $segments = [];

    private Carbon $from;

    private Carbon $to;

    private Carbon $today;

    /** @param  array<int, int>|null  $scopeIds  `null` = بلا حصر */
    public function __construct(
        private readonly ?array $scopeIds,
        Carbon $from,
        Carbon $to,
        private readonly array $filters = [],
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
        $this->today = Carbon::today();
    }

    // ════════════════════════════════════════════════════════════
    //  البناء
    // ════════════════════════════════════════════════════════════

    /**
     * كلُّ أرقام اللوحة. تُبنى الخرائطُ مرّةً ويُعَدّ منها.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $this->loadEmployees();

        $force = $this->buildWorkforce();
        $move = $this->buildMovement();
        $today = $this->buildToday();
        $attendance = $this->buildAttendance();
        $leaves = $this->buildLeaves();
        $payroll = $this->buildPayroll();
        $advances = $this->buildAdvances();
        $deductions = $this->buildDeductions();
        $contracts = $this->buildContracts();
        $distribution = $this->buildDistribution();
        $readiness = $this->buildReadiness();

        return [
            'period' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'force' => $force,
            'move' => $move,
            'today' => $today,
            'attendance' => $attendance,
            'leaves' => $leaves,
            'payroll' => $payroll,
            'advances' => $advances,
            'deductions' => $deductions,
            'contracts' => $contracts,
            'distribution' => $distribution,
            'readiness' => $readiness,
        ];
    }

    /**
     * قائمةُ شريحةٍ بعينها — من الخريطة التي عُدَّ منها الرقمُ نفسُه.
     *
     * @return array{title: string, hint: string, columns: array<int, array<string, string>>, rows: array<int, array<string, mixed>>}
     */
    public function drillDown(string $segment): array
    {
        $this->loadEmployees();
        $this->build();

        $meta = $this->segments[$segment] ?? null;

        if (! $meta) {
            return [
                'title' => 'شريحة غير معروفة',
                'hint' => 'المفتاح «'.$segment.'» لا يقابل شريحةً في هذه اللوحة.',
                'columns' => [],
                'rows' => [],
            ];
        }

        $rows = array_values($this->maps[$segment] ?? []);

        return [
            'title' => $meta['title'],
            'hint' => $meta['hint'],
            'columns' => $meta['columns'],
            'rows' => $rows,
            'context' => $this->context(),
        ];
    }

    /**
     * لماذا قد تعود الشريحةُ فارغة.
     *
     * قائمةٌ فارغةٌ بلا تفسيرٍ تُقرأ «الشاشة معطّلة»، والأسبابُ ثلاثةٌ لا رابع:
     * نطاقُ الرؤية لا يشمل أحداً، أو فلترُ الشاشة ضيّق العدد، أو الشريحةُ
     * نفسُها لا سجلَّ لها. فتُعرَض الثلاثةُ مع القائمة الفارغة ليُعرف أيُّها.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $labels = [
            'department_id' => 'القسم',
            'job_title_id' => 'المسمّى الوظيفي',
            'branch_id' => 'الفرع',
            'employee_status' => 'الحالة الوظيفية',
        ];

        $applied = [];
        foreach ($labels as $key => $label) {
            if (! empty($this->filters[$key])) {
                $applied[] = $label;
            }
        }

        return [
            'scope' => $this->scopeIds === null ? null : count($this->scopeIds),
            'visible' => count($this->employees),
            'filters' => $applied,
            'period' => $this->from->toDateString().' → '.$this->to->toDateString(),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  الموظفون في النطاق
    // ════════════════════════════════════════════════════════════

    private function loadEmployees(): void
    {
        if ($this->employees !== []) {
            return;
        }

        $query = Employee::query()
            ->with([
                'department:id,name', 'job_title:id,name', 'branch:id,name',
                'job_type:id,name', 'job_level:id,name',
                'direct_manager:id,first_name,middle_name,nickname',
            ])
            ->whereNull('deleted_at');

        if ($this->scopeIds !== null) {
            $query->whereIn('id', $this->scopeIds ?: [0]);
        }

        foreach (['department_id', 'job_title_id', 'branch_id'] as $column) {
            if (! empty($this->filters[$column])) {
                $query->where($column, $this->filters[$column]);
            }
        }

        if (! empty($this->filters['employee_status'])) {
            $query->where('status', $this->filters['employee_status']);
        }

        $this->employees = $query->orderBy('first_name')->get()->keyBy('id')->all();

        /*
         * الورديات باسمها: `Employee::shift()` علاقةٌ إلى صفوف الحضور
         * لا إلى جدول `shifts`، فـ`shift_id` لا يُحلّ بتحميلٍ مسبق. والجدولُ
         * صغيرٌ (ورديتان هنا) فخريطةٌ واحدةٌ أرخصُ من علاقة.
         */
        try {
            $this->shifts = DB::table('shifts')->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
        } catch (\Throwable $e) {
            $this->shifts = [];
        }
    }

    /** @return array<int, int> */
    private function employeeIds(): array
    {
        return array_keys($this->employees);
    }

    /**
     * سجلّاتٌ تخصّ موظفاً داخل النطاق فعلاً.
     *
     * الرقمُ المعروضُ على البطاقة يُعَدُّ من الصفوف التي يفتحها النقر، لكنّ
     * **المبالغَ** كانت تُجمع من المجموعة الخام قبل إسقاط ما لا يُعرَف صاحبُه
     * — فتُظهر البطاقةُ مبلغاً والقائمةُ فارغة. فالترشيحُ هنا مرّةً واحدةً عند
     * الجلب يجعل العددَ والمبلغَ والقائمةَ من مصدرٍ واحد.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Support\Collection<int, T>  $records
     * @return \Illuminate\Support\Collection<int, T>
     */
    private function ownedByScope($records)
    {
        return $records->filter(fn ($record) => isset($this->employees[(int) $record->employee_id]))->values();
    }

    /**
     * أعلى رأس العمل؟ — نشطٌ أو تحت التجربة.
     *
     * «القائم» في هذه اللوحة ليس «من لم تنتهِ خدمتُه»: الموقوفُ وغيرُ النشط
     * لا يُنتظر منهما حضورٌ اليوم ولا يُحاسَبان على ورديةٍ ناقصة، وإدخالُهما
     * في عدّة اليوم كان يضخّم «لم يُسجَّل» ويُفسد نسبةَ الحضور — ثمانيةُ
     * موظفين غيرِ نشطين في هذه القاعدة وحدها.
     */
    private function onDuty(Employee $employee): bool
    {
        return EmploymentStatus::fromValue($employee->status)->isActive();
    }

    /** صفُّ موظفٍ في قائمةٍ منسدلة — الأعمدةُ المشتركة في كل شريحة. */
    private function employeeRow(Employee $employee, array $extra = []): array
    {
        $status = EmploymentStatus::fromValue($employee->status);

        return array_merge([
            'id' => $employee->id,
            'name' => $this->displayName($employee),
            'code' => $employee->employee_code ?: '—',
            'department' => $employee->department->name ?? '—',
            'job_title' => $employee->job_title->name ?? '—',
            'job_level' => $employee->job_level->name ?? '—',
            'job_type' => $employee->job_type->name ?? '—',
            'branch' => $employee->branch->name ?? '—',
            'manager' => $employee->direct_manager ? $this->displayName($employee->direct_manager) : '—',
            'shift' => $this->shifts[(int) $employee->shift_id] ?? '—',
            'status' => $status->label(),
            'status_color' => $this->statusColor($status),
            'hired' => $employee->hire_date ? Carbon::parse($employee->hire_date)->toDateString() : '—',
            'mobile' => $employee->mobile_number ?: ($employee->phone_number ?: '—'),
            'email' => $employee->email ?: '—',
            'url' => route('employee.show', $employee->id),
        ], $extra);
    }

    private function displayName(Employee $employee): string
    {
        $name = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? ''));

        return $name !== '' ? $name : ($employee->nickname ?: '#'.$employee->id);
    }

    private function statusColor(EmploymentStatus $status): string
    {
        return match ($status) {
            EmploymentStatus::ACTIVE => '#28c76f',
            EmploymentStatus::PROBATION => '#00cfe8',
            EmploymentStatus::SUSPENDED => '#ff9f43',
            EmploymentStatus::RESIGNED, EmploymentStatus::TERMINATED => '#ea5455',
            default => '#7b8194',
        };
    }

    /**
     * تسجيلُ شريحةٍ: صفوفُها، وعنوانُها، وتعريفُها، وأعمدتُها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, string>>|null  $columns  `null` = أعمدةُ الموظف القياسية
     */
    private function segment(string $key, string $title, string $hint, array $rows, ?array $columns = null): int
    {
        $this->maps[$key] = $rows;
        $this->segments[$key] = [
            'title' => $title,
            'hint' => $hint,
            'columns' => $columns ?? $this->employeeColumns(),
        ];

        return count($rows);
    }

    /**
     * أعمدةُ ملفّ الموظف في كلّ قائمة — **كاملةً لا مختصرة**.
     *
     * القائمةُ تُفتح ليُعمَل بها لا لتُقرأ وحدها: من يرى «عشرة بلا وردية»
     * يحتاج قسمَهم ومديرَهم وجوّالَهم ليتصرّف، وتصديرُ CSV يأخذ ما تعرضه
     * الأعمدةُ لا أكثر. والجدولُ ينزلق أفقيّاً فلا تضيق الشاشة.
     *
     * وأعمدةُ الشريحة (`$extra`) تأتي **قبل** الملفّ لا بعده: من فتح
     * «سلفٌ في السداد» يسأل عن المبلغ أوّلاً، والملفُّ تفصيلٌ بعده.
     *
     * @return array<int, array<string, string>>
     */
    private function employeeColumns(array $extra = []): array
    {
        return array_merge(
            [['key' => 'name', 'label' => 'الموظف', 'type' => 'client']],
            $extra,
            [
                ['key' => 'code', 'label' => 'الرقم الوظيفي', 'type' => 'text'],
                ['key' => 'status', 'label' => 'الحالة', 'type' => 'status'],
                ['key' => 'department', 'label' => 'القسم', 'type' => 'text'],
                ['key' => 'job_title', 'label' => 'المسمّى الوظيفي', 'type' => 'text'],
                ['key' => 'job_level', 'label' => 'المستوى الوظيفي', 'type' => 'text'],
                ['key' => 'job_type', 'label' => 'نوع التوظيف', 'type' => 'text'],
                ['key' => 'branch', 'label' => 'الفرع', 'type' => 'text'],
                ['key' => 'manager', 'label' => 'المدير المباشر', 'type' => 'text'],
                ['key' => 'shift', 'label' => 'الوردية', 'type' => 'text'],
                ['key' => 'hired', 'label' => 'تاريخ التعيين', 'type' => 'date'],
                ['key' => 'mobile', 'label' => 'الجوال', 'type' => 'text'],
                ['key' => 'email', 'label' => 'البريد', 'type' => 'text'],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════
    //  أ) القوى العاملة — لقطةٌ لا فترة
    // ════════════════════════════════════════════════════════════

    private function buildWorkforce(): array
    {
        $byStatus = [];
        foreach ($this->employees as $employee) {
            $byStatus[(int) $employee->status][] = $employee;
        }

        $total = $this->segment(
            'force.total',
            'كل الموظفين في نطاقك',
            'كلُّ موظفٍ غيرِ مؤرشَف في نطاق رؤيتك — بأيّ حالةٍ وظيفية. الفترةُ لا تمسّ هذا الرقم.',
            array_map(fn (Employee $e) => $this->employeeRow($e), array_values($this->employees))
        );

        $rows = [];
        $statusTotals = [];

        foreach (EmploymentStatus::cases() as $status) {
            $list = $byStatus[$status->value] ?? [];
            $key = 'force.status_'.$status->value;

            $count = $this->segment(
                $key,
                'الموظفون — '.$status->label(),
                '`employees.status` = '.$status->value.' («'.$status->label().'»).',
                array_map(fn (Employee $e) => $this->employeeRow($e), $list)
            );

            $statusTotals[$status->value] = $count;

            if ($count > 0) {
                $rows[] = [
                    'key' => 'status_'.$status->value,
                    'label' => $status->label(),
                    'count' => $count,
                    'color' => $this->statusColor($status),
                ];
            }
        }

        /*
         * «على رأس العمل» حالتان لا واحدة (نشط وتحت التجربة)، فلها شريحتُها:
         * ربطُ البطاقة بـ`force.status_1` كان يعرض رقماً ويفتح قائمةً أقصر منه
         * بمقدار من هم تحت التجربة.
         */
        $onDutyRows = [];
        foreach ($this->employees as $employee) {
            if ($this->onDuty($employee)) {
                $onDutyRows[] = $this->employeeRow($employee);
            }
        }

        $active = $this->segment(
            'force.active',
            'من هم على رأس العمل',
            'النشط ومن هو تحت التجربة معاً — وهما وحدَهما من يُصرف له ويُنتظر منه حضور.',
            $onDutyRows
        );

        return [
            'total' => $total,
            'active' => $active,
            'rows' => $rows,
            'by_status' => $statusTotals,
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ب) الحركة في الفترة
    // ════════════════════════════════════════════════════════════

    private function buildMovement(): array
    {
        $hires = [];
        $leavers = [];

        foreach ($this->employees as $employee) {
            if ($employee->hire_date && $this->inPeriod($employee->hire_date)) {
                $hires[] = $this->employeeRow($employee, [
                    'event_date' => Carbon::parse($employee->hire_date)->toDateString(),
                ]);
            }

            // تاريخُ آخر يومِ عملٍ أولاً، وإلّا تاريخُ إنهاء الخدمة
            $left = $employee->last_working_day ?: $employee->termination_date;

            if ($left && $this->inPeriod($left)) {
                $leavers[] = $this->employeeRow($employee, [
                    'event_date' => Carbon::parse($left)->toDateString(),
                    'reason' => $employee->termination_reason ?: '—',
                ]);
            }
        }

        $hiresCount = $this->segment(
            'move.hires',
            'تعييناتُ الفترة',
            '`employees.hire_date` داخل الفترة المحددة.',
            $hires,
            $this->employeeColumns([['key' => 'event_date', 'label' => 'تاريخ التعيين', 'type' => 'date']])
        );

        $leaversCount = $this->segment(
            'move.leavers',
            'مغادرو الفترة',
            'آخرُ يومِ عملٍ (أو تاريخُ إنهاء الخدمة إن لم يُسجَّل) داخل الفترة.',
            $leavers,
            $this->employeeColumns([
                ['key' => 'event_date', 'label' => 'آخر يوم عمل', 'type' => 'date'],
                ['key' => 'reason', 'label' => 'السبب', 'type' => 'longtext'],
            ])
        );

        // معدّلُ الدوران: المغادرون ÷ متوسّطِ العدّة (البداية والنهاية) — تعريفٌ قياسي
        $headcount = count($this->employees);
        $turnover = $headcount > 0 ? round($leaversCount / max(1, $headcount - $hiresCount / 2) * 100, 1) : 0.0;

        return [
            'hires' => $hiresCount,
            'leavers' => $leaversCount,
            'net' => $hiresCount - $leaversCount,
            'turnover_rate' => $turnover,
        ];
    }

    private function inPeriod(mixed $date): bool
    {
        if (! $date) {
            return false;
        }

        $moment = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $moment->betweenIncluded($this->from, $this->to);
    }

    // ════════════════════════════════════════════════════════════
    //  ج) حضورُ اليوم
    // ════════════════════════════════════════════════════════════

    private function buildToday(): array
    {
        $rows = AttendanceDays::query()
            ->whereIn('employee_id', $this->employeeIds() ?: [0])
            ->whereDate('attendance_date', $this->today)
            ->get()
            ->keyBy('employee_id');

        $buckets = [
            'present' => [], 'late' => [], 'absent' => [], 'half_day' => [],
            'leave' => [], 'off' => [], 'not_recorded' => [], 'open' => [],
        ];

        foreach ($this->employees as $id => $employee) {
            $status = EmploymentStatus::fromValue($employee->status);

            // من ليس على رأس العمل لا يُنتظر منه حضور — موقوفاً كان أو غيرَ نشطٍ أو منتهيةً خدمتُه
            if (! $status->isActive()) {
                continue;
            }

            $day = $rows->get($id);

            if (! $day) {
                $buckets['not_recorded'][] = $this->employeeRow($employee, [
                    'detail' => 'لا صفَّ حضورٍ لهذا اليوم',
                    'late' => '—',
                ]);

                continue;
            }

            $detail = trim(
                ($day->check_in_time ? 'دخول '.substr((string) $day->check_in_time, 0, 5) : 'بلا دخول')
                .($day->check_out_time ? ' · خروج '.substr((string) $day->check_out_time, 0, 5) : '')
            );

            $row = $this->employeeRow($employee, [
                'detail' => $detail,
                // بالساعات والدقائق لا بعددٍ خام — «ساعة و10 دقائق» تُقرأ، و«70» تُحسب
                'late' => (int) $day->late_minutes > 0 ? DurationLabel::minutes((int) $day->late_minutes) : '—',
            ]);

            if ($day->is_on_leave) {
                $buckets['leave'][] = $row;
            } elseif ($day->is_holiday || $day->is_weekly_off) {
                $buckets['off'][] = $row;
            } else {
                /*
                  * حالةٌ غيرُ معروفةٍ (أو فارغة) تعني صفّاً أُنشئ ولم تُحسم
                  * حالتُه بعد — فيُعَدُّ مع «لم يُسجَّل» لأنّه مثلُه ينتظر
                  * معالجةً، لكنّ تفصيلَه يقول إنّ له صفّاً.
                  */
                $bucket = match ((string) $day->status) {
                    'present' => 'present',
                    'late' => 'late',
                    'absent' => 'absent',
                    'half_day' => 'half_day',
                    // قيمتان في الـenum قد تردان بلا علامةٍ في الصفّ
                    'leave' => 'leave',
                    'holiday' => 'off',
                    default => 'not_recorded',
                };
                $buckets[$bucket][] = $row;
            }

            if ($day->check_in_time && ! $day->check_out_time) {
                $buckets['open'][] = $row;
            }
        }

        $columns = $this->employeeColumns([
            ['key' => 'detail', 'label' => 'التفصيل', 'type' => 'text'],
            ['key' => 'late', 'label' => 'التأخير', 'type' => 'text'],
        ]);

        $labels = [
            'present' => ['حاضر', '`attendance_days.status = present` ليومِ اليوم، وليس إجازةً ولا عطلة.', '#28c76f'],
            'late' => ['متأخر', '`status = late` — تجاوز مهلةَ السماح ولم يبلغ حدَّ الغياب.', '#ff9f43'],
            'absent' => ['غائب', '`status = absent` — إمّا لم يسجّل، أو تأخّر فوق حدّ الغياب.', '#ea5455'],
            'half_day' => ['نصف يوم', '`status = half_day` — صافي عمله تحت نسبة «نصف يوم».', '#00cfe8'],
            'leave' => ['في إجازة', 'الصفُّ معلَّمٌ `is_on_leave` بإجازةٍ معتمدة.', '#7367f0'],
            'off' => ['عطلة', 'الصفُّ معلَّمٌ عطلةً أسبوعيةً أو رسمية.', '#7b8194'],
            'not_recorded' => ['لم يُسجَّل', 'موظفٌ قائمٌ بلا صفِّ حضورٍ لهذا اليوم إطلاقاً — لم يسجّل ولم تُعالَج حالتُه بعد.', '#b8bcc8'],
            'open' => ['دوامٌ مفتوح', 'سجّل دخولَه ولم يسجّل خروجَه بعد.', '#00cfe8'],
        ];

        $result = ['rows' => []];

        foreach ($buckets as $key => $list) {
            [$label, $hint, $color] = $labels[$key];

            $count = $this->segment('today.'.$key, 'حضور اليوم — '.$label, $hint, $list, $columns);
            $result[$key] = $count;

            if (! in_array($key, ['open', 'not_recorded'], true) && $count > 0) {
                $result['rows'][] = ['key' => $key, 'label' => $label, 'count' => $count, 'color' => $color];
            }
        }

        $expected = $result['present'] + $result['late'] + $result['absent'] + $result['half_day'];
        $result['attendance_rate'] = $expected > 0
            ? round(($result['present'] + $result['late'] + $result['half_day']) / $expected * 100, 1)
            : null;

        return $result;
    }

    // ════════════════════════════════════════════════════════════
    //  د) الحضور في الفترة
    // ════════════════════════════════════════════════════════════

    private function buildAttendance(): array
    {
        $days = AttendanceDays::query()
            ->whereIn('employee_id', $this->employeeIds() ?: [0])
            ->whereBetween('attendance_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get();

        $perEmployee = [];
        $totals = ['late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0, 'absent_days' => 0, 'present_days' => 0];

        foreach ($days as $day) {
            $id = (int) $day->employee_id;
            $perEmployee[$id] ??= ['late_days' => 0, 'late_minutes' => 0, 'absent_days' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0, 'present_days' => 0];

            if ($day->is_on_leave || $day->is_holiday || $day->is_weekly_off) {
                continue;
            }

            $late = (int) $day->late_minutes;
            $early = (int) $day->early_leave_minutes;
            $overtime = (int) $day->overtime_minutes;

            if ($late > 0) {
                $perEmployee[$id]['late_days']++;
                $perEmployee[$id]['late_minutes'] += $late;
                $totals['late_minutes'] += $late;
            }

            if ((string) $day->status === 'absent') {
                $perEmployee[$id]['absent_days']++;
                $totals['absent_days']++;
            }

            if (in_array((string) $day->status, ['present', 'late', 'half_day'], true)) {
                $perEmployee[$id]['present_days']++;
                $totals['present_days']++;
            }

            $perEmployee[$id]['early_minutes'] += $early;
            $perEmployee[$id]['overtime_minutes'] += $overtime;
            $totals['early_minutes'] += $early;
            $totals['overtime_minutes'] += $overtime;
        }

        $columns = $this->employeeColumns([
            ['key' => 'late_days', 'label' => 'أيام تأخير', 'type' => 'number'],
            ['key' => 'late_label', 'label' => 'مجموع التأخير', 'type' => 'text'],
            ['key' => 'absent_days', 'label' => 'أيام غياب', 'type' => 'number'],
            ['key' => 'overtime_label', 'label' => 'إضافي', 'type' => 'text'],
        ]);

        $build = fn (callable $filter) => array_values(array_filter(array_map(
            function (int $id, array $stats) use ($filter) {
                if (! $filter($stats)) {
                    return null;
                }

                $employee = $this->employees[$id] ?? null;

                return $employee ? $this->employeeRow($employee, [
                    'late_days' => $stats['late_days'],
                    'late_label' => DurationLabel::minutes($stats['late_minutes']),
                    'absent_days' => $stats['absent_days'],
                    'overtime_label' => DurationLabel::minutes($stats['overtime_minutes']),
                ]) : null;
            },
            array_keys($perEmployee),
            $perEmployee
        )));

        $lateEmployees = $this->segment(
            'att.late_employees',
            'موظفون تأخّروا في الفترة',
            'له يومٌ واحدٌ على الأقل بـ`late_minutes > 0` داخل الفترة — دون أيام الإجازة والعطل.',
            $build(fn (array $s) => $s['late_minutes'] > 0),
            $columns
        );

        $absentEmployees = $this->segment(
            'att.absent_employees',
            'موظفون غابوا في الفترة',
            'له يومٌ واحدٌ على الأقل بـ`status = absent` داخل الفترة — دون الإجازات والعطل.',
            $build(fn (array $s) => $s['absent_days'] > 0),
            $columns
        );

        $overtimeEmployees = $this->segment(
            'att.overtime_employees',
            'موظفون لهم وقتٌ إضافي',
            'مجموعُ `overtime_minutes` داخل الفترة أكبرُ من صفر.',
            $build(fn (array $s) => $s['overtime_minutes'] > 0),
            $columns
        );

        // أكثرُ عشرةٍ تأخيراً — جدولٌ في اللوحة
        $top = $perEmployee;
        uasort($top, fn ($a, $b) => $b['late_minutes'] <=> $a['late_minutes']);
        $topLate = [];
        foreach (array_slice($top, 0, 10, true) as $id => $stats) {
            if ($stats['late_minutes'] <= 0 || ! isset($this->employees[$id])) {
                continue;
            }
            $topLate[] = [
                'key' => $id,
                'name' => $this->displayName($this->employees[$id]),
                'department' => $this->employees[$id]->department->name ?? '—',
                'late_days' => $stats['late_days'],
                'late_minutes' => $stats['late_minutes'],
                'late_label' => DurationLabel::minutes($stats['late_minutes']),
                'absent_days' => $stats['absent_days'],
                'url' => route('employee.show', $id),
            ];
        }

        return [
            'late_employees' => $lateEmployees,
            'absent_employees' => $absentEmployees,
            'overtime_employees' => $overtimeEmployees,
            'late_minutes' => $totals['late_minutes'],
            'late_label' => DurationLabel::minutes($totals['late_minutes']),
            'early_minutes' => $totals['early_minutes'],
            'early_label' => DurationLabel::minutes($totals['early_minutes']),
            'overtime_minutes' => $totals['overtime_minutes'],
            'overtime_label' => DurationLabel::minutes($totals['overtime_minutes']),
            'absent_days' => $totals['absent_days'],
            'present_days' => $totals['present_days'],
            'top_late' => $topLate,
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  هـ) الإجازات
    // ════════════════════════════════════════════════════════════

    private function buildLeaves(): array
    {
        $requests = $this->ownedByScope(
            LeaveRequest::query()->whereIn('employee_id', $this->employeeIds() ?: [0])->get()
        );

        $columns = $this->employeeColumns([
            ['key' => 'range', 'label' => 'المدّة', 'type' => 'text'],
            ['key' => 'days', 'label' => 'الأيام', 'type' => 'number'],
            ['key' => 'request_status', 'label' => 'حالة الطلب', 'type' => 'text'],
        ]);

        $row = function (LeaveRequest $request) {
            $employee = $this->employees[(int) $request->employee_id] ?? null;

            if (! $employee) {
                return null;
            }

            return $this->employeeRow($employee, [
                'range' => Carbon::parse($request->start_date)->toDateString().' → '.Carbon::parse($request->end_date)->toDateString(),
                'days' => (float) $request->days,
                'request_status' => $request->status_text,
            ]);
        };

        $pending = $this->segment(
            'leave.pending',
            'طلباتُ إجازةٍ بانتظار الاعتماد',
            '`leave_requests.status = pending` — بأيّ تاريخ، فالطلبُ المعلَّق لا يُخفيه مرورُ الفترة.',
            array_values(array_filter(array_map($row, $requests->where('status', 'pending')->all()))),
            $columns
        );

        $onLeaveToday = $this->segment(
            'leave.today',
            'في إجازةٍ اليوم',
            'إجازةٌ معتمدةٌ تشمل تاريخَ اليوم.',
            array_values(array_filter(array_map($row, $requests->filter(
                fn ($r) => $r->status === 'approved'
                    && Carbon::parse($r->start_date)->lte($this->today)
                    && Carbon::parse($r->end_date)->gte($this->today)
            )->all()))),
            $columns
        );

        $approvedInPeriod = $this->segment(
            'leave.approved_period',
            'إجازاتٌ معتمدةٌ تقع في الفترة',
            'إجازةٌ معتمدةٌ يتقاطع مداها مع الفترة المحددة.',
            array_values(array_filter(array_map($row, $requests->filter(
                fn ($r) => $r->status === 'approved'
                    && Carbon::parse($r->start_date)->lte($this->to)
                    && Carbon::parse($r->end_date)->gte($this->from)
            )->all()))),
            $columns
        );

        return [
            'pending' => $pending,
            'today' => $onLeaveToday,
            'approved_period' => $approvedInPeriod,
            'days_in_period' => round((float) $requests->filter(
                fn ($r) => $r->status === 'approved'
                    && Carbon::parse($r->start_date)->lte($this->to)
                    && Carbon::parse($r->end_date)->gte($this->from)
            )->sum('days'), 1),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  و) الرواتب
    // ════════════════════════════════════════════════════════════

    private function buildPayroll(): array
    {
        $runs = $this->ownedByScope(
            PayrollRun::query()->whereIn('employee_id', $this->employeeIds() ?: [0])->get()
        );

        $columns = $this->employeeColumns([
            ['key' => 'period', 'label' => 'الفترة', 'type' => 'text'],
            ['key' => 'net', 'label' => 'الصافي', 'type' => 'money'],
            ['key' => 'run_status', 'label' => 'حالة الصرفة', 'type' => 'text'],
            ['key' => 'when', 'label' => 'التاريخ', 'type' => 'date'],
        ]);

        $row = function (PayrollRun $run) {
            $employee = $this->employees[(int) $run->employee_id] ?? null;

            if (! $employee) {
                return null;
            }

            return $this->employeeRow($employee, [
                'period' => substr((string) $run->period_from, 0, 10).' → '.substr((string) $run->period_to, 0, 10),
                'net' => round((float) $run->net_salary, 2),
                'run_status' => self::PAYROLL_STATUSES[(string) $run->status] ?? (string) $run->status,
                'when' => substr((string) ($run->disbursed_at ?: $run->scheduled_date), 0, 10),
            ]);
        };

        $disbursedRuns = $runs->filter(fn ($r) => $r->disbursed_at && $this->inPeriod($r->disbursed_at));
        $pendingRuns = $runs->filter(fn ($r) => (string) $r->status === 'pending');

        $disbursed = $this->segment(
            'pay.disbursed',
            'رواتبُ صُرفت في الفترة',
            '`payroll_runs.disbursed_at` داخل الفترة — حقيقةُ الصرف لا موعدُه.',
            array_values(array_filter(array_map($row, $disbursedRuns->all()))),
            $columns
        );

        $pending = $this->segment(
            'pay.pending',
            'صرفاتٌ معلَّقةٌ لم تُصرف',
            '`status = pending` — بأيّ فترة، فالمعلَّقُ لا يُخفيه مرورُ الفترة.',
            array_values(array_filter(array_map($row, $pendingRuns->all()))),
            $columns
        );

        $netTotal = round((float) $disbursedRuns->sum('net_salary'), 2);

        return [
            'disbursed' => $disbursed,
            'pending' => $pending,
            'net_total' => $netTotal,
            'net_label' => CurrencySettings::format($netTotal),
            'pending_total' => round((float) $pendingRuns->sum('net_salary'), 2),
            'deductions_total' => round((float) $disbursedRuns->sum('total_deductions'), 2),
            'advances_total' => round((float) $disbursedRuns->sum('total_advances_deducted'), 2),
            'additions_total' => round((float) $disbursedRuns->sum('total_additions'), 2),
            'average_net' => $disbursed > 0 ? round($netTotal / $disbursed, 2) : 0.0,
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ز) السلف
    // ════════════════════════════════════════════════════════════

    private function buildAdvances(): array
    {
        $advances = $this->ownedByScope(
            SalaryAdvanceRequest::query()->whereIn('employee_id', $this->employeeIds() ?: [0])->get()
        );

        $columns = $this->employeeColumns([
            ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money'],
            ['key' => 'remaining', 'label' => 'المتبقّي', 'type' => 'money'],
            ['key' => 'stage', 'label' => 'المرحلة', 'type' => 'text'],
            ['key' => 'when', 'label' => 'تاريخ الطلب', 'type' => 'date'],
        ]);

        $row = function (SalaryAdvanceRequest $advance) {
            $employee = $this->employees[(int) $advance->employee_id] ?? null;

            if (! $employee) {
                return null;
            }

            return $this->employeeRow($employee, [
                'amount' => round((float) $advance->amount, 2),
                'remaining' => round((float) $advance->remainingAmount(), 2),
                'stage' => $advance->stageLabel(),
                'when' => substr((string) $advance->requested_date, 0, 10),
                'url' => route('hr.advance-approvals.show', $advance->id),
            ]);
        };

        $pendingList = $advances->filter(fn ($a) => $a->stage() === 'pending');
        $awaitingList = $advances->filter(fn ($a) => $a->stage() === 'approved');
        $repayingList = $advances->filter(fn ($a) => in_array($a->stage(), ['disbursed', 'repaying'], true));

        $pending = $this->segment(
            'adv.pending',
            'سلفٌ بانتظار الاعتماد',
            'مرحلةُ الطلب `pending` في `SalaryAdvanceRequest::stage()`.',
            array_values(array_filter(array_map($row, $pendingList->all()))),
            $columns
        );

        $awaiting = $this->segment(
            'adv.awaiting_disbursement',
            'سلفٌ معتمدةٌ لم تُصرف',
            'اعتُمدت ولم يُكتب لها قيدُ صرف — المرحلة `approved`.',
            array_values(array_filter(array_map($row, $awaitingList->all()))),
            $columns
        );

        $repaying = $this->segment(
            'adv.repaying',
            'سلفٌ قائمةٌ في دورة السداد',
            'صُرفت ولم تُسدَّد بالكامل — المرحلة `disbursed` أو `repaying`.',
            array_values(array_filter(array_map($row, $repayingList->all()))),
            $columns
        );

        $outstanding = 0.0;
        foreach ($repayingList as $advance) {
            $outstanding += (float) $advance->remainingAmount();
        }

        return [
            'pending' => $pending,
            'awaiting_disbursement' => $awaiting,
            'repaying' => $repaying,
            'outstanding' => round($outstanding, 2),
            'outstanding_label' => CurrencySettings::format($outstanding),
            'disbursed_in_period' => round((float) $advances->filter(
                fn ($a) => $a->disbursed_at && $this->inPeriod($a->disbursed_at)
            )->sum('amount'), 2),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ح) الخصميات
    // ════════════════════════════════════════════════════════════

    private function buildDeductions(): array
    {
        $deductions = $this->ownedByScope(
            Deduction::query()->whereIn('employee_id', $this->employeeIds() ?: [0])->get()
        );

        $columns = $this->employeeColumns([
            ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money'],
            ['key' => 'kind', 'label' => 'النوع', 'type' => 'text'],
            ['key' => 'ded_status', 'label' => 'الحالة', 'type' => 'text'],
            ['key' => 'when', 'label' => 'التاريخ', 'type' => 'date'],
        ]);

        $row = function (Deduction $deduction) {
            $employee = $this->employees[(int) $deduction->employee_id] ?? null;

            if (! $employee) {
                return null;
            }

            return $this->employeeRow($employee, [
                'amount' => round((float) $deduction->amount, 2),
                'kind' => Deduction::types()[$deduction->deduction_type] ?? (string) $deduction->deduction_type,
                'ded_status' => Deduction::statuses()[$deduction->status] ?? (string) $deduction->status,
                'when' => substr((string) $deduction->date, 0, 10),
            ]);
        };

        $pendingList = $deductions->where('status', 'pending');
        $approvedList = $deductions->where('status', 'approved');
        $periodList = $deductions->filter(fn ($d) => $this->inPeriod($d->date) && ! in_array($d->status, ['cancelled', 'rejected'], true));

        $pending = $this->segment(
            'ded.pending',
            'خصمياتٌ بانتظار الاعتماد',
            '`deductions.status = pending` — لا تُخصم من راتبٍ حتى تُعتمد.',
            array_values(array_filter(array_map($row, $pendingList->all()))),
            $columns
        );

        $approved = $this->segment(
            'ded.approved',
            'خصمياتٌ معتمدةٌ لم تُقتطع بعد',
            '`status = approved` — لها قيدُها وتنتظر الاقتطاع من أوّل راتب.',
            array_values(array_filter(array_map($row, $approvedList->all()))),
            $columns
        );

        $inPeriod = $this->segment(
            'ded.period',
            'خصمياتُ الفترة',
            'تاريخُ الخصمية داخل الفترة، دون الملغاة والمرفوضة.',
            array_values(array_filter(array_map($row, $periodList->all()))),
            $columns
        );

        return [
            'pending' => $pending,
            'approved' => $approved,
            'period' => $inPeriod,
            'period_total' => round((float) $periodList->sum('amount'), 2),
            'period_label' => CurrencySettings::format((float) $periodList->sum('amount')),
            'pending_total' => round((float) $pendingList->sum('amount'), 2),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ط) العقود
    // ════════════════════════════════════════════════════════════

    /**
     * نهايةُ العقد الفعلية — أو `null` إن كان غيرَ محدّد المدّة.
     *
     * `end_date` ليس العمودَ الوحيد: `type_contract = 1` عقدُ **مدّة**،
     * نهايتُه تُحسب من `start_date` بـ`duration` و`duration_unit`
     * (١ يوم، ٢ شهر، ٣ سنة). وعقدٌ بلا نهايةٍ ولا مدّةٍ هو عقدٌ غيرُ
     * محدّد المدّة لا عقدٌ ناقص — وكان الفلترُ `whereNotNull('end_date')`
     * يُسقطه فيُحسب صاحبُه «بلا عقد».
     */
    private function contractEnd(Contract $contract): ?Carbon
    {
        if ($contract->end_date) {
            return Carbon::parse($contract->end_date)->startOfDay();
        }

        $duration = (int) $contract->duration;
        $unit = (int) $contract->duration_unit;

        if ((int) $contract->type_contract !== 1 || $duration < 1 || $unit < 1 || ! $contract->start_date) {
            return null;
        }

        $start = Carbon::parse($contract->start_date)->startOfDay();

        $end = match ($unit) {
            1 => $start->copy()->addDays($duration),
            2 => $start->copy()->addMonthsNoOverflow($duration),
            3 => $start->copy()->addYearsNoOverflow($duration),
            default => null,
        };

        // آخرُ يومٍ داخلَ المدّة لا أوّلُ يومٍ بعدها
        return $end?->subDay();
    }

    private function buildContracts(): array
    {
        $contracts = $this->ownedByScope(
            Contract::query()->whereIn('employee_id', $this->employeeIds() ?: [0])->get()
        );

        /*
         * العبرةُ بأحدثِ عقدٍ لكلّ موظف. فمن جُدّد عقدُه له صفّان، والقديمُ
         * منتهٍ — وعدُّ الصفوف كان يضعه في «منتهية» و«سارية» في آنٍ واحد،
         * فيظهر على الشاشة عقدٌ منتهٍ لموظفٍ عقدُه مجدّد.
         */
        $latest = [];

        foreach ($contracts as $contract) {
            $id = (int) $contract->employee_id;

            /*
             * التصنيفُ على **كلّ من له عقد**، لا على من هو على رأس العمل وحده.
             *
             * قُصِرت الخاناتُ على العاملين أوّلًا طلباً للاتّساق، فاختفت عقودٌ
             * منتهيةٌ قائمةٌ فعلاً لأنّ أصحابَها موقوفون أو منتهيةٌ خدمتُهم —
             * وهي أولى ما يُنظر فيه لا أحرى ما يُخفى. وعمودُ «الحالة» في
             * القائمة يقول أيُّهم على رأس العمل. و«بلا عقد» وحدَها تبقى على
             * العاملين، إذ لا معنى لمطالبة من انتهت خدمتُه بعقد.
             */
            if (! isset($this->employees[$id])) {
                continue;
            }

            $current = $latest[$id] ?? null;

            if (! $current) {
                $latest[$id] = $contract;

                continue;
            }

            $fresh = Carbon::parse($contract->start_date);
            $kept = Carbon::parse($current->start_date);

            if ($fresh->gt($kept) || ($fresh->eq($kept) && (int) $contract->id > (int) $current->id)) {
                $latest[$id] = $contract;
            }
        }

        $columns = $this->employeeColumns([
            ['key' => 'contract_code', 'label' => 'رقم العقد', 'type' => 'text'],
            ['key' => 'start_date', 'label' => 'البداية', 'type' => 'date'],
            ['key' => 'end_label', 'label' => 'النهاية', 'type' => 'text'],
            ['key' => 'days_left', 'label' => 'المتبقّي', 'type' => 'days'],
            ['key' => 'amount', 'label' => 'القيمة', 'type' => 'money'],
            ['key' => 'approval', 'label' => 'الاعتماد', 'type' => 'text'],
        ]);

        $row = function (Contract $contract) {
            $employee = $this->employees[(int) $contract->employee_id] ?? null;

            if (! $employee) {
                return null;
            }

            $end = $this->contractEnd($contract);

            return $this->employeeRow($employee, [
                'contract_code' => (string) ($contract->code ?: '—'),
                'start_date' => $contract->start_date ? Carbon::parse($contract->start_date)->toDateString() : '—',
                'end_date' => $end?->toDateString(),
                'end_label' => $end ? $end->toDateString() : 'غير محدّد المدّة',
                'days_left' => $end ? (int) $this->today->diffInDays($end, false) : null,
                'amount' => round((float) $contract->amount, 2),
                'approval' => (string) $contract->approval_status === 'approved' ? 'معتمد' : 'بانتظار الاعتماد',
            ]);
        };

        $buckets = ['expiring' => [], 'expired' => [], 'open' => [], 'running' => [], 'unapproved' => []];
        $horizon = $this->today->copy()->addDays(30);

        foreach ($latest as $contract) {
            // عقدٌ لم يُعتمد ليس سارياً، فلا يُحسب في «سار» ولا في «منتهٍ»
            if ((string) $contract->approval_status !== 'approved') {
                $buckets['unapproved'][] = $contract;

                continue;
            }

            $end = $this->contractEnd($contract);

            if (! $end) {
                $buckets['open'][] = $contract;
            } elseif ($end->lt($this->today)) {
                $buckets['expired'][] = $contract;
            } elseif ($end->lte($horizon)) {
                $buckets['expiring'][] = $contract;
            } else {
                $buckets['running'][] = $contract;
            }
        }

        $byEnd = function (array $list) {
            usort($list, fn ($a, $b) => ($this->contractEnd($a)?->getTimestamp() ?? PHP_INT_MAX)
                <=> ($this->contractEnd($b)?->getTimestamp() ?? PHP_INT_MAX));

            return $list;
        };

        $make = fn (string $key, string $title, string $hint, array $list): int => $this->segment(
            'contract.'.$key,
            $title,
            $hint,
            array_values(array_filter(array_map($row, $byEnd($list)))),
            $columns
        );

        $expiring = $make(
            'expiring',
            'عقودٌ تنتهي خلال ٣٠ يوماً',
            'أحدثُ عقدٍ معتمدٍ للموظف تنتهي مدّتُه بين اليوم وبعد ثلاثين يوماً — والمدّةُ تُقرأ من `end_date` أو تُحسب من `duration`.',
            $buckets['expiring']
        );

        $expired = $make(
            'expired',
            'عقودٌ منتهيةٌ لم تُجدّد',
            'أحدثُ عقدٍ معتمدٍ للموظف انتهت مدّتُه قبل اليوم — فمن جُدّد عقدُه لا يظهر هنا.',
            $buckets['expired']
        );

        $open = $make(
            'open',
            'عقودٌ غيرُ محدّدة المدّة',
            'عقدٌ بلا `end_date` وبلا مدّةٍ محسوبة — سارٍ حتّى يُنهَى، ولا يُنتظر له تجديد.',
            $buckets['open']
        );

        $running = $make(
            'running',
            'عقودٌ ساريةٌ بمدّةٍ باقية',
            'أحدثُ عقدٍ معتمدٍ تنتهي مدّتُه بعد أكثر من ثلاثين يوماً.',
            $buckets['running']
        );

        $unapproved = $make(
            'unapproved',
            'عقودٌ بانتظار الاعتماد',
            '`contracts.approval_status` ليست `approved` — العقدُ مكتوبٌ ولم يُعتمد، فلا يُعَدُّ سارياً.',
            $buckets['unapproved']
        );

        // موظفٌ على رأس العمل لا صفَّ له في `contracts` إطلاقاً
        $withContract = array_keys($latest);
        $without = [];

        foreach ($this->employees as $id => $employee) {
            if (! $this->onDuty($employee) || in_array($id, $withContract, true)) {
                continue;
            }

            $without[] = $this->employeeRow($employee);
        }

        $noContract = $this->segment(
            'contract.none',
            'موظفون على رأس العمل بلا عقد',
            'لا صفَّ في `contracts` لهذا الموظف إطلاقاً — ومن ليس على رأس العمل مستثنًى.',
            $without
        );

        return [
            'expiring' => $expiring,
            'expired' => $expired,
            'open' => $open,
            'running' => $running,
            'unapproved' => $unapproved,
            'none' => $noContract,
            'covered' => count($latest),
            'on_duty' => count(array_filter($this->employees, fn ($e) => $this->onDuty($e))),
            'expiring_rows' => array_slice($this->maps['contract.expiring'], 0, 8),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ي) التوزيع
    // ════════════════════════════════════════════════════════════

    private function buildDistribution(): array
    {
        // [عنوانُ المحور، كيف يُقرأ اسمُه، العمود، تسميةُ الفراغ]
        // وتسميةُ الفراغ مكتوبةٌ لا مُركَّبة: «بلا ».$label تُنتج «بلا القسم»
        $axes = [
            'department' => ['القسم', fn (Employee $e) => $e->department->name ?? null, 'department_id', 'بلا قسم'],
            'job_title' => ['المسمّى الوظيفي', fn (Employee $e) => $e->job_title->name ?? null, 'job_title_id', 'بلا مسمّى وظيفي'],
            'branch' => ['الفرع', fn (Employee $e) => $e->branch->name ?? null, 'branch_id', 'بلا فرع'],
        ];

        $result = [];

        foreach ($axes as $axis => [$label, $resolver, $column, $emptyLabel]) {
            $groups = [];

            foreach ($this->employees as $employee) {
                if (! $this->onDuty($employee)) {
                    continue;
                }

                $name = $resolver($employee) ?: $emptyLabel;
                $key = $employee->{$column} ? (string) $employee->{$column} : 'none';
                $groups[$key] ??= ['label' => $name, 'rows' => []];
                $groups[$key]['rows'][] = $this->employeeRow($employee);
            }

            $rows = [];
            foreach ($groups as $key => $group) {
                $count = $this->segment(
                    'dist.'.$axis.'_'.$key,
                    $label.' — '.$group['label'],
                    'الموظفون القائمون في «'.$group['label'].'» (من انتهت خدمتُه مستثنى).',
                    $group['rows']
                );

                $rows[] = ['key' => $axis.'_'.$key, 'label' => $group['label'], 'count' => $count];
            }

            usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
            $result[$axis] = ['label' => $label, 'rows' => $rows];
        }

        return $result;
    }

    // ════════════════════════════════════════════════════════════
    //  ك) الجاهزية — فراغاتٌ تُعطّل عملاً لاحقاً
    // ════════════════════════════════════════════════════════════

    private function buildReadiness(): array
    {
        $checks = [
            'no_department' => ['بلا قسم', fn (Employee $e) => ! $e->department_id, 'لا قسمَ في ملفّه — يسقط من كل تقريرٍ مبنيٍّ على الأقسام ومن نطاق الرؤية «قسمي».'],
            'no_job_title' => ['بلا مسمّى وظيفي', fn (Employee $e) => ! $e->job_title_id, 'لا مسمّى — يظهر فارغاً في العقد والقسيمة والمستندات.'],
            'no_manager' => ['بلا مدير مباشر', fn (Employee $e) => ! $e->direct_manager_id, 'لا مديرَ مباشر — لا يجد من يعتمد إجازتَه وسلفتَه، ولا يظهر في الشجرة التنظيمية.'],
            'no_shift' => ['بلا وردية', fn (Employee $e) => ! $e->shift_id, 'لا ورديةَ مُسنَدة — يُقاس حضورُه على «بداية الدوام الافتراضية» من الإعدادات لا على جدولٍ حقيقي.'],
            'no_hire_date' => ['بلا تاريخ التحاق', fn (Employee $e) => ! $e->hire_date, 'لا تاريخَ التحاق — لا تُحسب مكافأةُ نهاية الخدمة ولا مدّةُ الخدمة.'],
            'no_payroll_account' => ['بلا حساب رواتب', fn (Employee $e) => ! $e->salary_payable_account_id, 'لا حسابَ ذمّةٍ في شجرة الحسابات — لا يُقيَّد له راتب. يُصلَح بأمر `employees:backfill-accounts` أو بحفظ البطاقة.'],
        ];

        $result = ['rows' => [], 'total' => 0];

        foreach ($checks as $key => [$label, $test, $hint]) {
            $rows = [];

            foreach ($this->employees as $employee) {
                if (! $this->onDuty($employee)) {
                    continue;
                }

                if ($test($employee)) {
                    $rows[] = $this->employeeRow($employee);
                }
            }

            $count = $this->segment('ready.'.$key, 'جاهزية — '.$label, $hint, $rows);

            $result['rows'][] = ['key' => $key, 'label' => $label, 'count' => $count, 'hint' => $hint];
            $result['total'] += $count;
        }

        /*
         * مجموعُ النواقص أعلاه يَعُدُّ الموظفَ مرّةً لكلِّ فراغٍ في ملفّه، فلا
         * يصحّ أن يكون رقمَ بطاقةٍ تفتح موظفين. والرقمُ الذي يُسأل عنه فعلاً:
         * كم موظفاً في ملفّه فراغٌ واحدٌ على الأقلّ — وقائمتُه تقول أيَّ فراغ.
         */
        $withGaps = [];

        foreach ($this->employees as $employee) {
            if (! $this->onDuty($employee)) {
                continue;
            }

            $gaps = [];

            foreach ($checks as [$label, $test]) {
                if ($test($employee)) {
                    $gaps[] = $label;
                }
            }

            if ($gaps) {
                $withGaps[] = $this->employeeRow($employee, ['gaps' => implode('، ', $gaps)]);
            }
        }

        $result['employees_with_gaps'] = $this->segment(
            'ready.any',
            'موظفون في ملفّاتهم فراغ',
            'كلُّ موظفٍ قائمٍ ينقص ملفَّه حقلٌ واحدٌ على الأقلّ من حقول الجاهزية الستّة، وعمودُ «الناقص» يقول أيَّها.',
            $withGaps,
            $this->employeeColumns([['key' => 'gaps', 'label' => 'الناقص', 'type' => 'longtext']])
        );

        return $result;
    }
}
