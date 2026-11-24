<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'client_id',
        'phone',
        'password',
        'role',
        'role_id',
        'branch_id',
        'employee_id',
        'active_project_id',
        'avatar',
        'last_login_at',
        'last_login_ip',
        'visit_proximity_radius_km',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    /**
     * Get the region groups for the user.
     */

    /**
     * الحصول على العملاء المستهدفين للموظف
     */

    // في نموذج User
    public function clientVisits()
    {
        return $this->hasMany(EmployeeClientVisit::class, 'employee_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function target()
    {
        return $this->hasOne(EmployeeTarget::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'created_by');
    }

    public function employeeClients()
    {
        return $this->hasMany(ClientEmployee::class, 'employee_id');
    }

    /**
     * هل هذا الحساب مرتبطٌ بسجلّ موظّف؟ — الارتباط الفعلي (`employee_id`)
     * لا عمود `role` النصي القديم، الذي قد ينحرف عن الواقع مع كل تعديلٍ
     * لا يمرّ بشاشة إدارة المستخدمين.
     */
    public function isEmployee()
    {
        return ! is_null($this->employee_id);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * الدور الأساسي للمستخدم (عمود `role_id`) — علاقةٌ واحدة صريحة، بخلاف
     * `roles()` القادمة من HasRoles (علاقة متعدّدة عبر `model_has_roles`).
     * الاسم مقصودٌ مغايراً كي لا يتصادم مع دالّة Spatie.
     */
    public function primaryRole()
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    /**
     * نقطة الكتابة الوحيدة لدور المستخدم — `role_id` هو مصدر الحقيقة،
     * وما دونه مُشتقٌّ منه لا مكتوبٌ باستقلال عنه.
     *
     * كانت `UsersController::store()`/`update()` تكتب ثلاثة أشياء يدوياً
     * في كل حفظ (`role_id`، وعمود `role` النصي، ومزامنة Spatie)، ووعدت
     * هذه الدالّة — بالاسم نفسه — منذ مهاجرة `role_id` بأن تحلّ محلّ ذلك
     * التكرار، لكنها لم تُكتب قط؛ فبقي `role_id` يُكتب مرّةً ثم لا يُضمَن
     * بقاؤه متوافقاً مع الآخرَين خارج تلك الشاشة تحديداً.
     *
     * فحص الصلاحيات الفعلي (`CheckPermissionRole`, `hasRolePermission()`)
     * يقرأ `role_id` مباشرةً — لا `model_has_roles`. `syncRoles()` هنا
     * لتوافق ما تبقّى من الشيفرة يستعمل `hasRole()`/`hasPermissionTo()`
     * الأصليتين من Spatie فحسب. أمّا عمود `role` النصي فبقي **مقصوداً**:
     * عشرات الاستعلامات عبر النظام (`User::where('role', 'employee')`)
     * تقرأ عموده الخام مباشرةً من القاعدة، لا عبر موديل PHP — فحذف الكتابة
     * إليه يُسقط تلك الاستعلامات صامتة لأي مستخدمٍ يُعدَّل بعد هذا التاريخ.
     * تنظيفها فعلياً نقلٌ لعشرات الملفات عبر النظام كله، خارج نطاق هذا
     * الإصلاح.
     */
    public function assignPrimaryRole(\Spatie\Permission\Models\Role $role): void
    {
        $this->role_id = $role->id;
        $this->role = $role->name;
        $this->save();

        $this->syncRoles([$role->name]);
    }

    /**
     * حسابُ موظفٍ بلا دور؟ يأخذ دورَ ملفّه (`employees.Job_role_id`).
     *
     * للحساب **الفارغ** وحده: من له دورٌ لا يُمسّ، لأن الملفّ في القواعد
     * القائمة قد يقول «مندوب مبيعات» لحساب المالك. يستدعيه وسيط
     * `AdoptEmployeeJobRole` عند كل طلب، والأمر `hr:sync-login-roles
     * --only-missing` يفعل الشيء نفسه دفعةً واحدة.
     *
     * @return bool هل أُسنِد دور؟
     */
    public function adoptEmployeeJobRole(): bool
    {
        if ($this->role_id !== null || ! $this->employee_id) {
            return false;
        }

        $roleId = Employee::whereKey($this->employee_id)->value('Job_role_id');
        $role = $roleId ? \Spatie\Permission\Models\Role::find($roleId) : null;

        if (! $role) {
            return false;
        }

        $this->assignPrimaryRole($role);
        $this->unsetRelation('primaryRole');

        return true;
    }

    /**
     * هل صلاحيات دور المستخدم (`role_id` ← `role_has_permissions`) تشمل
     * هذه الصلاحية؟ المصدر الوحيد الذي يستعمله `CheckPermissionRole` وكل
     * فحص صلاحيةٍ جديد في النظام — لا `model_has_roles` ولا صلاحيات
     * مُسنَدة للمستخدم مباشرة خارج دوره. مستخدمٌ بلا دور لا يملك شيئاً.
     *
     * تقبل قائمة بدائل مفصولة بـ`|` كما في الميدل وير — يكفي أن يملك
     * الدور واحدةً منها.
     */
    public function hasRolePermission(string $permission): bool
    {
        $role = $this->primaryRole;

        if (! $role) {
            return false;
        }

        $candidates = str_contains($permission, '|') ? explode('|', $permission) : [$permission];

        return $role->permissions()->whereIn('name', $candidates)->exists();
    }

    /** هل هذا اسم دور المستخدم الأساسي (`role_id`) بالضبط؟ */
    public function hasRoleName(string $name): bool
    {
        return $this->primaryRole?->name === $name;
    }

    /**
     * هل يملك دور المستخدم أي صلاحيةٍ ببادئة معيّنة (مثلاً `account.`)؟
     * لبوابات دخولٍ عامة (كالداشبورد) لا تخصّ فعلاً بعينه — أي صلاحية واحدة
     * من صلاحيات الوحدة تكفي، لا اشتراط اسمٍ محدَّد.
     */
    public function hasAnyPermissionWithPrefix(string $prefix): bool
    {
        $role = $this->primaryRole;

        if (! $role) {
            return false;
        }

        return $role->permissions()->where('name', 'like', $prefix.'%')->exists();
    }

    // داخل App\Models\User
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * حصر المستخدمين بفرع: إما فرع الحساب (users.branch_id) أو فرع سجل الموظف (employees.branch_id)
     * حتى يظهر في لوحات الأداء كامل فريق الفرع حتى لو كان حساب المستخدم مسجّلاً على فرع آخر.
     */
    public function scopeWhereAccountOrEmployeeBranch($query, $branchId)
    {
        if ($branchId === null || $branchId === '' || (string) $branchId === '0') {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereHas('employee', function ($eq) use ($branchId) {
                    $eq->where('branch_id', $branchId);
                });
        });
    }

    /** كـ`whereAccountOrEmployeeBranch` لكن للعقدة وكلّ ما تحتها. */
    public function scopeWhereAccountOrEmployeeBranchTree($query, $branchId)
    {
        if ($branchId === null || $branchId === '' || (string) $branchId === '0') {
            return $query;
        }

        $ids = \App\Support\AffiliationTree::descendantIds((int) $branchId);

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('branch_id', $ids)
                ->orWhereHas('employee', function ($eq) use ($ids) {
                    $eq->whereIn('branch_id', $ids);
                });
        });
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function regionGroups()
    {
        return $this->belongsToMany(Branch::class, 'employee_group', 'employee_id', 'group_id', 'employee_id')
            ->where('branches.is_territory', true)
            ->using(EmployeeGroup::class)
            ->withPivot(['direction_id', 'expires_at']);
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'employee_id');
    }

    public function job_role()
    {
        return $this->hasMany(JobRole::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'employee_id');
    }

    public function notes()
    {
        return $this->hasMany(ClientRelation::class, 'employee_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentsProcess::class, 'employee_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /**
     * العلاقة مع مساحات العمل التي يديرها المستخدم
     */
    public function managedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'admin_id');
    }

    /**
     * العلاقة مع المشاريع التي أنشأها المستخدم
     */
    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    /**
     * العلاقة مع المشاريع التي يشارك فيها المستخدم
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * العلاقة مع المهام التي أنشأها المستخدم
     */
    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * العلاقة مع المهام المعينة للمستخدم
     */
    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * العلاقة مع التعليقات التي كتبها المستخدم
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * الحصول على المشاريع التي يديرها المستخدم
     */
    public function managedProjects()
    {
        return $this->projects()->wherePivot('role', 'manager');
    }

    public function currentBranch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function accountSetting()
    {
        return $this->hasOne(AccountSetting::class);
    }

    public function businessData()
    {
        return $this->hasOne(BusinessData::class);
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/'.$this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=7367f0&color=fff';
    }
}
