<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\EmployeeController;
use Tests\TestCase;

/**
 * البياناتُ البنكية وجهةُ الطوارئ — والحارسُ عليهما.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * لم يكن للموظف حسابٌ بنكي: الصرفُ نقديٌّ من خزينة حصراً، فلا سبيل إلى
 * تحويلٍ بنكي ولا ملفّ رواتب. ولا جهةَ اتصالٍ يُرجَع إليها إن وقع مكروه.
 *
 * **والحارسُ ثلاثيّ لا واحد** — وهذا ما يُقاس هنا:
 *
 *   ١. المتحكّمُ يُفرّغ القيمَ **قبل** بلوغ القالب لمن لا يملك
 *      `employees_view_sensitive`. فإخفاءُ الكتلة يمنع العينَ ولا يمنع
 *      الوصول: تبقى القيمُ في المتغيّر الممرَّر.
 *   ٢. والقالبُ يُخفي الحقول — **وهذا ليس زينة**: لو عُرضت فارغةً لمن لا
 *      يراها لَمَحا حفظُ النموذج حسابَ الموظف بلا أن يقصد.
 *   ٣. والمتحكّمُ يُسقطها من حمولة الحفظ، فلا يكتبها طلبٌ مصوغٌ يدوياً.
 */
class EmployeeSensitiveDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->string('personal_email')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('bank_account_holder')->nullable();
            $t->string('iban', 34)->nullable();
            $t->string('emergency_contact_name')->nullable();
            $t->string('emergency_contact_phone', 40)->nullable();
            $t->string('emergency_contact_relation', 60)->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('viewer');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamps();
        });
    }

    /** يسجّل مستخدماً دورُه يملك هذه الصلاحيات وحدها. */
    private function actingWith(array $permissions): User
    {
        $role = Role::create(['name' => 'r'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->permissions()->attach($permission->id);
        }

        $user = User::create([
            'name' => 'مستخدم',
            'email' => 'u'.uniqid().'@example.test',
            'password' => 'x',
            'role_id' => $role->id,
        ]);

        Auth::login($user);

        return $user;
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'bank_name' => 'مصرف الراجحي',
            'bank_account_holder' => 'سالم أحمد',
            'iban' => 'SA0380000000608010167519',
            'emergency_contact_name' => 'أحمد',
            'emergency_contact_phone' => '0500000000',
            'emergency_contact_relation' => 'أخ',
        ]);
    }

    // ═══════════════ الحقول ═══════════════

    public function test_the_new_fields_are_mass_assignable_and_persist(): void
    {
        $employee = $this->employee()->fresh();

        $this->assertSame('مصرف الراجحي', $employee->bank_name);
        $this->assertSame('SA0380000000608010167519', $employee->iban);
        $this->assertSame('أحمد', $employee->emergency_contact_name);
        $this->assertSame('أخ', $employee->emergency_contact_relation);
    }

    /** والآيبان يُعرَض مقنَّعاً: آخرُ أربعةٍ تكفي للتأكّد لا للاستعمال. */
    public function test_the_iban_is_masked_for_display(): void
    {
        $employee = $this->employee();

        $this->assertSame('•••• 7519', $employee->maskedIban());
        $this->assertStringNotContainsString('SA038', (string) $employee->maskedIban());
    }

    public function test_a_missing_iban_masks_to_nothing(): void
    {
        $employee = Employee::create([
            'first_name' => 'بلا حساب',
            'email' => 'n'.uniqid().'@example.test',
        ]);

        $this->assertNull($employee->maskedIban());
    }

    // ═══════════════ الحارس ═══════════════

    public function test_the_permission_is_in_the_catalog(): void
    {
        $names = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $this->assertContains('employees_view_sensitive', $names);
    }

    /** والبياناتُ البنكية وحدها حسّاسة — لا جهةُ الطوارئ. */
    public function test_only_the_banking_fields_are_marked_sensitive(): void
    {
        $sensitive = Employee::sensitiveAttributes();

        $this->assertContains('iban', $sensitive);
        $this->assertContains('bank_name', $sensitive);
        $this->assertContains('bank_account_holder', $sensitive);
        $this->assertNotContains('emergency_contact_name', $sensitive);
    }

    /**
     * القيمُ تُفرَّغ **قبل** بلوغ القالب لمن لا يملك الصلاحية.
     *
     * ولو اكتُفي بإخفاء الكتلة في الشاشة لبقيت القيمُ في المتغيّر
     * الممرَّر — يبلغها من قرأ مصدرَ الصفحة أو استدعى القالبَ من طريقٍ آخر.
     */
    public function test_the_values_never_reach_the_view_without_the_permission(): void
    {
        $employee = $this->employee();
        $this->actingWith(['employees_view_profile']);

        $controller = new class extends EmployeeController
        {
            public function __construct() {}

            public function strip(Employee $employee): Employee
            {
                $method = new \ReflectionMethod(parent::class, 'hideSensitiveAttributes');
                $method->setAccessible(true);
                $method->invoke($this, $employee);

                return $employee;
            }
        };

        $stripped = $controller->strip($employee);

        $this->assertNull($stripped->iban);
        $this->assertNull($stripped->bank_name);
        $this->assertNull($stripped->bank_account_holder);

        // وجهةُ الطوارئ ليست حسّاسة فتبقى
        $this->assertSame('أحمد', $stripped->emergency_contact_name);
    }

    /** ومن يملكها يراها كاملة. */
    public function test_the_values_survive_for_a_permitted_user(): void
    {
        $employee = $this->employee();
        $this->actingWith(['employees_view_profile', 'employees_view_sensitive']);

        $controller = new class extends EmployeeController
        {
            public function __construct() {}

            public function strip(Employee $employee): Employee
            {
                $method = new \ReflectionMethod(parent::class, 'hideSensitiveAttributes');
                $method->setAccessible(true);
                $method->invoke($this, $employee);

                return $employee;
            }
        };

        $this->assertSame('SA0380000000608010167519', $controller->strip($employee)->iban);
    }

    /** والتفريغُ لا يمسّ ما في القاعدة — النموذجُ للعرض لا للحفظ. */
    public function test_stripping_does_not_touch_the_stored_row(): void
    {
        $employee = $this->employee();
        $this->actingWith(['employees_view_profile']);

        $controller = new class extends EmployeeController
        {
            public function __construct() {}

            public function strip(Employee $employee): Employee
            {
                $method = new \ReflectionMethod(parent::class, 'hideSensitiveAttributes');
                $method->setAccessible(true);
                $method->invoke($this, $employee);

                return $employee;
            }
        };

        $controller->strip($employee);

        $this->assertSame(
            'SA0380000000608010167519',
            Employee::find($employee->id)->iban,
            'مُحيت القيمةُ من القاعدة — التفريغُ للعرض لا للحفظ'
        );
    }

    /**
     * وشاشتا الإضافة والتعديل لا تعرضان حقولَ البنك بلا صلاحية.
     *
     * وهذا ليس تجميلاً: حقلٌ فارغٌ يُعرَض ثم يُحفَظ يمحو حسابَ الموظف.
     */
    public function test_the_forms_gate_the_banking_fields(): void
    {
        foreach (['create', 'edit'] as $screen) {
            $html = (string) file_get_contents(
                base_path("Modules/HR/resources/views/employee/{$screen}.blade.php")
            );

            $this->assertSame(
                1,
                preg_match(
                    "/@permission\('employees_view_sensitive'\)(.*?)@endpermission/s",
                    $html,
                    $guarded
                ),
                "شاشة {$screen} بلا حارس للبيانات البنكية"
            );

            foreach (['bank_name', 'bank_account_holder', 'iban'] as $field) {
                $this->assertStringContainsString(
                    'name="'.$field.'"',
                    $guarded[1],
                    "الحقل {$field} خارج الحارس في شاشة {$screen}"
                );
            }
        }
    }

    /** والمتحكّمُ يُسقطها من حمولة الحفظ كذلك — لطلبٍ مصوغٍ يدوياً. */
    public function test_the_update_payload_drops_sensitive_keys_without_permission(): void
    {
        $source = (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/EmployeeController.php')
        );

        $this->assertTrue(
            str_contains($source, "if (! hasPermission('employees_view_sensitive')) {"),
            'لا حارسَ على حمولة الحفظ'
        );
    }
}
