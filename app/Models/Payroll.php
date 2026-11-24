<?php

namespace App\Models;

use App\Support\AffiliationTree;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مسير الرواتب = مظلة زمنية لتغطية الموظفين، لا عملية صرف.
 *
 * **ما يفعله**
 *
 *   من يُشمَل (`payroll_employee` أو فلتر قسم/مسمى/فرع) ومتى
 *   (`start_date` → `end_date`). القسيمة تنتمي إليه بـ`payroll_id`.
 *   اعتماده يفتح إنشاء القسائم وهو **الاعتمادُ الوحيد للصرف**: صرفاتُ قسائمه
 *   تُصرف في يوم صرفها بلا اعتمادٍ آخر. إيقافه يوقف توليد الصرفات وصرفَها.
 *
 * **ما لا يفعله**
 *
 *   لا سند ولا قيد ولا صافٍ. الصرف في `PayrollRun`. و`PayrollBatch`
 *   («مسير الصرفات») أُلغي شرطاً — بياناته باقيةٌ تاريخاً.
 */
class Payroll extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'registration_date',
        'start_date',
        'end_date',
        'select_emp_role',
        'receiving_cycle',
        'department_id',
        'jop_title_id',
        'branch_id',
        'status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_ACTIVE => 'نشط (موافق عليه)',
            self::STATUS_INACTIVE => 'موقوف',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status ?? self::STATUS_ACTIVE] ?? '—';
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(JopTitle::class, 'jop_title_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'payroll_employee', 'payroll_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class, 'payroll_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PayrollBatch::class, 'payroll_id');
    }

    public function isDraft(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_INACTIVE;
    }

    public function isRunningAt($date = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }
        $date = $date ? Carbon::parse($date) : now();
        if ($this->start_date && $date->lt($this->start_date)) {
            return false;
        }
        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    public function isRunning(): bool
    {
        return $this->isRunningAt(now());
    }

    /**
     * هل يشمل المسيرُ هذا الموظف؟
     *
     * المسيرُ المحفوظ بموظفين (`payroll_employee`) يشمل من فيه وحدهم — وهو
     * حالُ كلّ مسيرٍ يُنشأ اليوم، بالقواعد كان أو بالاختيار. والمسيرُ القديم
     * بلا صفوفٍ يُسأل عنه بقواعده بالتعريف نفسِه الذي يجمع به الإنشاءُ
     * موظّفيه ({@see ruleEmployeesQuery()}) — لا بمطابقةٍ تامّةٍ للفرع كما
     * كان، فتقول شاشةُ القسيمة «لا يشمله» لموظّفٍ ضمّه الإنشاءُ نفسُه.
     */
    public function coversEmployee(int $employeeId): bool
    {
        if ($this->employees()->exists()) {
            return $this->employees()->where('employees.id', $employeeId)->exists();
        }

        return self::ruleEmployeesQuery($this->department_id, $this->jop_title_id, $this->branch_id)
            ->where('employees.id', $employeeId)
            ->exists();
    }

    /**
     * معرّفاتُ الموظفين الذين يشملهم المسير — المحفوظون فيه، أو قواعدُه
     * لمسيرٍ قديمٍ بلا صفوف. مصدرٌ واحدٌ تقرؤه شاشةُ العرض والتصدير
     * وقائمةُ موظفي القسيمة، فلا يختلف عددٌ بين شاشتين.
     *
     * @return array<int, int>
     */
    public function memberEmployeeIds(): array
    {
        $ids = $this->employees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();

        if ($ids !== []) {
            return $ids;
        }

        return self::ruleEmployeesQuery($this->department_id, $this->jop_title_id, $this->branch_id)
            ->pluck('employees.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * قيمةُ قاعدةٍ من النموذج — أو `null` حين تعني «الكل».
     *
     * «الكل» تصل بأشكالٍ شتّى: `''` من خيارٍ فارغ، و`'all'` من خيار
     * «كل الأقسام»، و`0`/`'0'`، و`null` من حقلٍ معطَّل لا يُرسَل، ومصفوفةٌ
     * من قائمةٍ متعدّدة. وكان `'all'` وحده يُعامَل غياباً للشرط؛ فمرّت
     * البقيّةُ إلى `where('department_id', 'all')` — ومقارنةُ عمودٍ رقميٍّ
     * بنصٍّ في MySQL تقلبه صفراً، فلا يُطابق أحد.
     *
     * @return array<int, int>|null
     */
    public static function ruleValues($value): ?array
    {
        $values = array_values(array_unique(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (int) $v : 0, (array) $value),
            fn ($v) => $v > 0
        )));

        return $values === [] ? null : $values;
    }

    /**
     * الموظفون النشطون المنطبقون على قواعد المسير: قسمٌ ومسمّىً وفرع.
     *
     * **الفرعُ يشمل ما تحته**، ويُقرأ من موضعين: `employees.branch_id`
     * أوّلاً، فإن خلا فالفرعُ الأساسيّ للموظف (`employee_branch.is_main`)
     * ثمّ فرعُ حساب دخوله (`users.branch_id`). وكان يُقرأ من الأوّل وحده —
     * وأكثرُ موظفي قاعدة التطوير فرعُهم محفوظٌ في حساب الدخول لا في
     * بطاقتهم — فيخرج مسيرُ «فرعٍ + كل الأقسام + كل المسميات» بأربعة
     * موظفين من أصل عشرات.
     *
     * و«النشطون» هم `Employee::active()` — النشطُ ومن تحت التجربة — لا
     * `status = 1` وحده، فلا يسقط موظّفٌ تحت التجربة من المسير.
     */
    public static function ruleEmployeesQuery($departmentId, $jobTitleId, $branchId): Builder
    {
        $query = Employee::query()->active();

        if ($departments = self::ruleValues($departmentId)) {
            $query->whereIn('employees.department_id', $departments);
        }

        if ($titles = self::ruleValues($jobTitleId)) {
            $query->whereIn('employees.job_title_id', $titles);
        }

        $branches = self::ruleValues($branchId);

        if ($branches) {
            $nodeIds = [];
            foreach ($branches as $branch) {
                $nodeIds = array_merge($nodeIds, AffiliationTree::descendantIds($branch));
            }
            $nodeIds = array_values(array_unique(array_map('intval', $nodeIds)));

            $query->where(function (Builder $q) use ($nodeIds) {
                $q->whereIn('employees.branch_id', $nodeIds)
                    ->orWhere(function (Builder $q2) use ($nodeIds) {
                        $q2->whereNull('employees.branch_id')
                            ->where(function (Builder $q3) use ($nodeIds) {
                                $q3->whereExists(function ($sub) use ($nodeIds) {
                                    $sub->selectRaw('1')->from('employee_branch')
                                        ->whereColumn('employee_branch.employee_id', 'employees.id')
                                        ->where('employee_branch.is_main', 1)
                                        ->whereIn('employee_branch.branch_id', $nodeIds);
                                })->orWhereExists(function ($sub) use ($nodeIds) {
                                    $sub->selectRaw('1')->from('users')
                                        ->whereColumn('users.employee_id', 'employees.id')
                                        ->whereIn('users.branch_id', $nodeIds);
                                });
                            });
                    });
            });
        }

        return $query;
    }

    public static function findActiveForEmployee(int $employeeId, $date = null): ?self
    {
        $date = $date ? Carbon::parse($date) : now();

        return self::query()
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $date->toDateString());
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->get()
            ->first(function ($p) use ($employeeId) {
                return $p->coversEmployee($employeeId);
            });
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($q)
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopeInactive($q)
    {
        return $q->where('status', self::STATUS_INACTIVE);
    }
}
