<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Tests\TestCase;

/**
 * سلّم نطاق الرؤية للموظفين — سلوكٌ لا شكل كتالوج.
 */
class HrEmployeeScopeLadderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('direct_manager_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name')->default('web');
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name')->default('web');
            $t->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
    }

    private function scopeFor(User $user): ?array
    {
        Auth::login($user);

        $probe = new class
        {
            use ScopesToAccessibleEmployees;

            public function ids(): ?array
            {
                return $this->accessibleEmployeeIds();
            }
        };

        return $probe->ids();
    }

    private function userWith(string ...$permissions): User
    {
        static $seq = 0;
        $seq++;

        $role = Role::create(['name' => 'r'.$seq, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $role->permissions()->attach($permission->id);
        }

        $employee = Employee::create([
            'first_name' => 'emp'.$seq,
            'email' => 'emp'.$seq.'@test.local',
            'department_id' => 10,
        ]);

        return User::create([
            'name' => 'u'.$seq,
            'email' => 'u'.$seq.'@test.local',
            'password' => 'x',
            'employee_id' => $employee->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_department_scope_returns_only_same_department(): void
    {
        $user = $this->userWith('data_scope_department');
        $peer = Employee::create([
            'first_name' => 'زميل',
            'email' => 'peer@test.local',
            'department_id' => 10,
        ]);
        $other = Employee::create([
            'first_name' => 'آخر',
            'email' => 'other@test.local',
            'department_id' => 20,
        ]);

        $ids = $this->scopeFor($user);

        $this->assertNotNull($ids);
        $this->assertContains((int) $user->employee_id, $ids);
        $this->assertContains((int) $peer->id, $ids);
        $this->assertNotContains((int) $other->id, $ids);
    }

    public function test_department_scope_without_department_collapses_to_self(): void
    {
        $role = Role::create(['name' => 'r_dept_none', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'data_scope_department', 'guard_name' => 'web']);
        $role->permissions()->attach($permission->id);

        $me = Employee::create([
            'first_name' => 'بلا قسم',
            'email' => 'nodes@test.local',
            'department_id' => null,
        ]);
        Employee::create([
            'first_name' => 'غيره',
            'email' => 'else@test.local',
            'department_id' => 5,
        ]);

        $user = User::create([
            'name' => 'u_nodes',
            'email' => 'u_nodes@test.local',
            'password' => 'x',
            'employee_id' => $me->id,
            'role_id' => $role->id,
        ]);

        $this->assertSame([(int) $me->id], $this->scopeFor($user));
    }

    public function test_no_scope_defaults_to_self_only(): void
    {
        $me = Employee::create([
            'first_name' => 'أنا',
            'email' => 'me@test.local',
        ]);
        Employee::create([
            'first_name' => 'غيره',
            'email' => 'other2@test.local',
        ]);

        $user = User::create([
            'name' => 'u_plain',
            'email' => 'u_plain@test.local',
            'password' => 'x',
            'employee_id' => $me->id,
            // بلا دور ⇒ بلا data_scope_*
        ]);

        $this->assertSame([(int) $me->id], $this->scopeFor($user));
    }

    public function test_data_scope_all_is_unrestricted(): void
    {
        $user = $this->userWith('data_scope_all');

        $this->assertNull($this->scopeFor($user));
    }
}
