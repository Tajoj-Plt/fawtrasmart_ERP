<?php

namespace Database\Seeders;

use App\Models\AttendanceDays;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayList;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * بيانات تجريبية لوحدة الموارد البشرية.
 *
 * سببُ وجودها: شاشاتُ الوحدة كانت تُختبر على قاعدةٍ شبه فارغة، وقد أثبتت
 * التجربةُ أن ذلك يُخفي أعطالاً حقيقية — شاشةُ محددات الحضور مثلاً كانت
 * «تعمل» لأن الجدول فارغ فقط، وشاشةُ طلبات الإجازة كانت تسقط عند أول
 * سجلّ. فحصُ المسارات بلا بيانات يقيس القليل.
 *
 * موضعُه هنا لا في Modules/HR/database/seeders لأن الجذر يربط
 * Modules\\ بـ Modules/ حسّاسةً لحالة الأحرف، فمجلّدُ البذور صغيرُ الأحرف
 * لا يُحمَّل أصلاً (وHRDatabaseSeeder هناك ميتٌ للسبب نفسه).
 *
 * كل ما يُنشئه هذا الملف موسومٌ بـ SEED_TAG في حقلٍ نصّي، ليُحذف نظيفاً:
 *
 *     php artisan db:seed --class="Modules\\HR\\Database\\Seeders\\HrDemoDataSeeder"
 *     php artisan tinker --execute="(new Modules\\HR\\Database\\Seeders\\HrDemoDataSeeder)->purge();"
 */
class HrDemoDataSeeder extends Seeder
{
    public const SEED_TAG = '[demo-hr]';

    public function run(): void
    {
        $department = Department::query()->first();
        $branchId = DB::table('branches')->value('id');
        $jobRoleId = $this->ensureJobRole();
        $creatorId = (int) (DB::table('users')->value('id') ?? 1);

        $employees = $this->seedEmployees($department?->id, $branchId, $jobRoleId, $creatorId);
        $leaveTypes = $this->seedLeaveTypes();

        $this->seedUserAccounts($employees, $jobRoleId, $branchId);
        $this->seedHolidayLists();
        $this->seedLeavePolicies();
        $this->seedLeaveRequests($employees, $leaveTypes);
        $this->seedAttendanceDays($employees);
        $this->seedDeterminants();

        $this->command?->info('بيانات HR التجريبية جاهزة — الوسم: '.self::SEED_TAG);
    }

    /** يحذف كل ما زرعه هذا الملف ولا يمسّ سواه. */
    public function purge(): void
    {
        $ids = Employee::where('notes', 'like', '%'.self::SEED_TAG.'%')->pluck('id');

        $userIds = User::whereIn('employee_id', $ids)->pluck('id');
        DB::table('employee_targets')->whereIn('user_id', $userIds)->delete();
        User::whereIn('employee_id', $ids)->delete();
        LeaveRequest::whereIn('employee_id', $ids)->delete();
        AttendanceDays::whereIn('employee_id', $ids)->delete();
        DB::table('employee_attendance_configs')->whereIn('employee_id', $ids)->delete();
        Employee::whereIn('id', $ids)->forceDelete();

        LeaveType::where('description', 'like', '%'.self::SEED_TAG.'%')->delete();
        LeavePolicy::where('description', 'like', '%'.self::SEED_TAG.'%')->delete();

        $lists = HolidayList::where('name', 'like', '%'.self::SEED_TAG.'%')->pluck('id');
        Holiday::whereIn('holiday_list_id', $lists)->delete();
        HolidayList::whereIn('id', $lists)->delete();

        DB::table('attendance_determinants')->where('name', 'like', '%'.self::SEED_TAG.'%')->delete();
    }

    /**
     * الدورُ الذي يُسنَد للموظف التجريبي.
     *
     * انتبه: رغم اسمه، `employees.Job_role_id` يشير إلى جدول **roles**
     * (سبَاتي) لا إلى `job_roles` — والتحقّقُ في EmployeeRequest يقول
     * `exists:roles,id` صراحةً. وقد أخذت نسخةٌ أولى من هذا الملف المعرّفَ من
     * `job_roles` فوقع على الرقم 1، وهو في `roles` دورُ **owner** بـ٨٩٥
     * صلاحية. أي أن كل موظفٍ تجريبيٍّ يُمنَح حساباً كان يصير مالكاً للنظام.
     *
     * نختار الآن الدورَ الأقلَّ صلاحيةً صراحةً (employee عادةً).
     */
    private function ensureJobRole(): int
    {
        $employeeRole = DB::table('roles')->where('name', 'employee')->value('id');

        if ($employeeRole) {
            return (int) $employeeRole;
        }

        // وإلا فأقلُّ الأدوار صلاحيةً — لا الأوّل أياً كان
        $leastPrivileged = DB::table('roles')
            ->leftJoin('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
            ->groupBy('roles.id')
            ->orderByRaw('COUNT(role_has_permissions.permission_id) ASC')
            ->value('roles.id');

        if ($leastPrivileged) {
            return (int) $leastPrivileged;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => 'employee',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return Collection<int, Employee> */
    private function seedEmployees(?int $departmentId, ?int $branchId, ?int $jobRoleId, int $creatorId)
    {
        $people = [
            // gender عمود tinyint: 1 = ذكر، 2 = أنثى (كما في الصفوف القائمة)
            ['سالم', 'عبدالله', 'الحربي', 1],
            ['نورة', 'محمد', 'القحطاني', 2],
            ['خالد', 'إبراهيم', 'الدوسري', 1],
            ['مها', 'سعد', 'العتيبي', 2],
            ['ياسر', 'فهد', 'الشمري', 1],
            ['ريم', 'ناصر', 'الزهراني', 2],
        ];

        return collect($people)->map(function (array $p, int $i) use ($departmentId, $branchId, $jobRoleId, $creatorId) {
            [$first, $middle, $nick, $gender] = $p;

            $email = 'demo.hr.'.($i + 1).'@example.test';

            /*
                withTrashed: الفهرسُ الفريد على البريد يشمل المؤرشفين، فبحثٌ
                عاديٌّ لا يجد الموظفَ المؤرشف ثم يحاول إدراجَ نسخةٍ ثانية
                فيصطدم بـ1062. ومن أُرشِف يُستعاد بدل أن يُستنسَخ.
            */
            $existing = Employee::withTrashed()->where('email', $email)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing;
            }

            return Employee::create(
                array_merge(['email' => $email], [
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'nickname' => $nick,
                    'employee_code' => 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'status' => 1,
                    'gender' => $gender,
                    'department_id' => $departmentId,
                    'branch_id' => $branchId,
                    'Job_role_id' => $jobRoleId,
                    'date_of_birth' => Carbon::now()->subYears(28 + $i)->toDateString(),
                    'mobile_number' => '05000000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'created_by' => $creatorId,
                    'notes' => self::SEED_TAG.' موظف تجريبي',
                ])
            );
        });
    }

    /** @return Collection<int, LeaveType> */
    private function seedLeaveTypes()
    {
        $types = [
            ['إجازة سنوية', 30, 15],
            ['إجازة مرضية', 15, 7],
            ['إجازة اضطرارية', 5, 2],
            ['إجازة بدون راتب', 60, 30],
        ];

        return collect($types)->map(fn (array $t) => LeaveType::firstOrCreate(
            ['name' => $t[0]],
            [
                'max_days_per_year' => $t[1],
                'max_consecutive_days' => $t[2],
                'requires_approval' => true,
                'color' => '#6366f1',
                'description' => self::SEED_TAG,
            ]
        ));
    }

    /**
     * حساباتُ دخولٍ للموظفين التجريبيين.
     *
     * شاشةُ «أداء الموظفين» تبني قائمتها من
     * `User::whereNotNull('employee_id')` — أي أن الموظفَ بلا حساب دخول لا
     * يظهر فيها أصلاً مهما كان له من حضورٍ وإجازات. فبلا هذه الحسابات تبقى
     * الشاشةُ فارغةً ولا يمكن اختبارها.
     *
     * الدورُ صريحٌ هنا: أقلُّ الأدوار صلاحيةً، لا أوّلُ دورٍ في الجدول.
     */
    private function seedUserAccounts($employees, int $roleId, ?int $branchId): void
    {
        foreach ($employees as $employee) {
            User::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'password' => Hash::make(Str::random(32)), // لا دخولَ فعلياً بها
                    'role' => 'employee',
                    'role_id' => $roleId,
                    'branch_id' => $branchId,
                ]
            );
        }
    }

    private function seedHolidayLists(): void
    {
        $list = HolidayList::firstOrCreate(['name' => 'العطل الرسمية '.self::SEED_TAG]);

        foreach ([['2026-09-23'], ['2026-12-25'], ['2027-01-01']] as $i => $date) {
            Holiday::firstOrCreate(
                ['holiday_list_id' => $list->id, 'holiday_date' => $date[0]],
                ['is_active' => 1, 'is_paid' => 1, 'named' => 'عطلة تجريبية '.($i + 1)]
            );
        }
    }

    private function seedLeavePolicies(): void
    {
        foreach ([['سياسة الموظفين الإداريين', 1], ['سياسة الموظفين الميدانيين', 1]] as $p) {
            LeavePolicy::firstOrCreate(
                ['name' => $p[0]],
                ['status' => $p[1], 'description' => self::SEED_TAG]
            );
        }
    }

    private function seedLeaveRequests($employees, $leaveTypes): void
    {
        $statuses = ['pending', 'approved', 'rejected', 'pending', 'approved'];

        foreach ($employees as $i => $employee) {
            foreach (range(0, 2) as $n) {
                $start = Carbon::now()->addDays(($i * 7) + ($n * 3) - 10);
                $days = 1 + (($i + $n) % 4);

                LeaveRequest::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'start_date' => $start->toDateString(),
                    ],
                    [
                        'request_type' => 'leave',
                        'leave_type_id' => $leaveTypes[($i + $n) % $leaveTypes->count()]->id,
                        'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                        'days' => $days,
                        'description' => 'طلب تجريبي '.self::SEED_TAG,
                        'status' => $statuses[($i + $n) % count($statuses)],
                    ]
                );
            }
        }
    }

    private function seedAttendanceDays($employees): void
    {
        $statuses = ['present', 'present', 'late', 'absent', 'present'];

        foreach ($employees as $i => $employee) {
            foreach (range(0, 9) as $d) {
                $date = Carbon::now()->subDays($d)->toDateString();
                $status = $statuses[($i + $d) % count($statuses)];

                AttendanceDays::firstOrCreate(
                    ['employee_id' => $employee->id, 'attendance_date' => $date],
                    [
                        'status' => $status,
                        'start_shift' => '08:00:00',
                        'end_shift' => '16:00:00',
                        'check_in_time' => $status === 'absent' ? null : ($status === 'late' ? '08:35:00' : '07:58:00'),
                        'check_out_time' => $status === 'absent' ? null : '16:05:00',
                        'late_minutes' => $status === 'late' ? 35 : 0,
                        'early_leave_minutes' => 0,
                        'overtime_minutes' => 0,
                    ]
                );
            }
        }
    }

    private function seedDeterminants(): void
    {
        foreach ([['محدد المقر الرئيسي', 1, 1], ['محدد الفروع', 0, 1]] as $d) {
            DB::table('attendance_determinants')->updateOrInsert(
                ['name' => $d[0].' '.self::SEED_TAG],
                [
                    'status' => 0,
                    'enable_ip_verification' => $d[1],
                    'enable_location_verification' => $d[2],
                    'latitude' => 24.7136,
                    'longitude' => 46.6753,
                    'radius' => 200,
                    'radius_type' => 1, // عمود tinyint لا نص
                    'capture_employee_image' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
