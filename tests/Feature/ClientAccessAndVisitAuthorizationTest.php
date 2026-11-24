<?php

namespace Tests\Feature;

use App\Helpers\ClientSettingsHelper;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ClientAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Client\Http\Controllers\ClientController;
use Modules\Client\Http\Controllers\ClientEmployeeController;
use Modules\Client\Http\Controllers\ClientNoteController;
use Modules\Client\Http\Controllers\VisitWorkspaceController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * يغطي المصفوفة المطلوبة من مهمّة «صلاحيات العملاء + الاشتراك + حماية
 * الزيارة»: من يرى أيّ عميل (view_all/view_assigned)، ومنعُ تسجيل زيارةٍ
 * لعميلٍ خارج نطاق المستخدم (الثغرة المُصلَحة في `ClientController::
 * registerVisit()`/`storeVisit()`)، وبقاء صلاحيات الملاحظات/الإسناد بعد
 * نقلها إلى `ClientNoteController`/`ClientEmployeeController`.
 *
 * الجداولُ تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL (`ALTER TABLE ... MODIFY COLUMN`) لا يفهمها sqlite الذي
 * يفرضه `phpunit.xml` — نفس ما يفعله [HrPermissionMiddlewareTest] و
 * [InstallerAdminRoleTest] للسبب ذاته، وهو عطبٌ سابقٌ لهذا التعديل لا ناتجٌ
 * عنه (تحقّقتُ بتشغيل ClientAssignmentTest القائم فوجدته يفشل بالخطأ نفسه).
 */
class ClientAccessAndVisitAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('employee');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->decimal('visit_proximity_radius_km', 5, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name')->default('web');
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
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

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('last_name')->nullable();
            $t->timestamps();
        });

        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->string('trade_name')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('status_id')->nullable();
            $t->boolean('exempt_visit_location')->default(false);
            $t->boolean('exempt_exchange_transaction')->default(false);
            $t->timestamps();
        });

        Schema::create('client_employee', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        // applyPermissionCascade() يبدأ بفحص clients_view_by_employee_groups.
        Schema::create('employee_group', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('group_id');
            $t->timestamps();
        });

        // خط السير اليومي — nullable(): عمود day_of_week نصّي بسيط هنا، لا
        // ENUM كما في MySQL الحقيقي (لا يؤثر على منطق المقارنة النصّية).
        Schema::create('employee_client_visits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('client_id');
            $t->string('day_of_week');
            $t->unsignedSmallInteger('year');
            $t->unsignedSmallInteger('week_number');
            $t->timestamps();
        });

        // registerVisit() يبحث عن زيارةٍ مفتوحة اليوم قبل إنشاء واحدة، و
        // VisitWorkspaceController::start() يقرأ/يكتب أعمدة البدء والموقع.
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('client_id');
            $t->dateTime('visit_date')->nullable();
            $t->dateTime('departure_time')->nullable();
            $t->dateTime('arrival_time')->nullable();
            $t->dateTime('started_at')->nullable();
            $t->dateTime('paused_at')->nullable();
            $t->unsignedInteger('total_paused_seconds')->default(0);
            $t->string('stage')->nullable();
            $t->string('visit_number')->nullable();
            $t->decimal('client_latitude', 10, 8)->nullable();
            $t->decimal('client_longitude', 11, 8)->nullable();
            $t->decimal('employee_latitude', 10, 8)->nullable();
            $t->decimal('employee_longitude', 11, 8)->nullable();
            $t->string('recording_method')->nullable();
            $t->boolean('is_approved')->default(false);
            $t->timestamps();
        });

        // authorizeVisitOwner() يستدعي Auth::user()->hasRole('admin') — Spatie.
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });

        // ClientSettingsHelper::visitProximityRadiusKm() يقرأ من هنا عبر
        // ClientSettings::group('visit_proximity_settings')، وينشئ الصفّ
        // بافتراضاته (٠٫٣ كم) إن لم يوجد.
        Schema::create('general_client_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name')->nullable();
            $t->decimal('visit_proximity_radius_km', 5, 2)->nullable();
            $t->timestamps();
        });

        // registerVisit() يحتاج موقع العميل المسجَّل لإنشاء الزيارة عليه.
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->decimal('latitude', 10, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->timestamps();
        });

        // next_document_number('visit') يقرأ/يكتب هذا الجدول (DocumentNumberService).
        Schema::create('serial_settings', function (Blueprint $t) {
            $t->id();
            $t->string('section');
            $t->unsignedBigInteger('current_number')->default(0);
            $t->unsignedTinyInteger('number_of_digits')->default(6);
            $t->string('prefix')->nullable();
            $t->string('separator')->nullable();
            $t->string('suffix')->nullable();
            $t->unsignedTinyInteger('mode')->default(0);
            $t->boolean('reset_yearly')->default(false);
            $t->string('reset_period')->default('never');
            $t->dateTime('last_reset_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    /** دورٌ بصلاحياتٍ مُحدَّدة، ومستخدمٌ (اختيارياً موظَّف) عليه — بلا مزامنة Spatie: hasRolePermission() تقرأ role_id وحده. */
    private function userWithPermissions(array $permissions, ?int $employeeId = null): User
    {
        static $seq = 0;
        $seq++;

        $role = Role::create(['name' => 'role_'.$seq, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $permission = Permission::create(['name' => $name, 'guard_name' => 'web']);
            $role->permissions()->attach($permission->id);
        }

        $user = User::create([
            'name' => 'user_'.$seq,
            'email' => 'user_'.$seq.'@example.test',
            'password' => 'x',
            'role_id' => $role->id,
            'employee_id' => $employeeId,
        ]);

        // hasPermission() global يقرأ Auth::user() لا وسيط الدالّة — بلا هذا
        // فحوصات الصلاحيات كلّها ترى مستخدماً مجهولاً وتُخفق بصمت.
        $this->actingAs($user);

        return $user;
    }

    // A: clients.view_all (clients_view_all_clients) يرى جميع العملاء
    public function test_user_with_view_all_permission_sees_every_client(): void
    {
        Client::create(['code' => 'C1', 'trade_name' => 'One']);
        Client::create(['code' => 'C2', 'trade_name' => 'Two']);
        Client::create(['code' => 'C3', 'trade_name' => 'Three']);

        $user = $this->userWithPermissions(['clients_view_all_clients']);

        $ids = app(ClientAccessService::class)->getAccessibleClientIds($user);

        $this->assertCount(3, $ids);
    }

    // B: clients.view_assigned (clients_view_own_clients) يرى المسنَدين فقط
    public function test_user_with_view_assigned_permission_sees_only_assigned_clients(): void
    {
        $employee = Employee::create(['first_name' => 'Ahmed']);
        $assigned = Client::create(['code' => 'C1', 'trade_name' => 'Assigned']);
        $other = Client::create(['code' => 'C2', 'trade_name' => 'NotAssigned']);

        ClientEmployee::create(['client_id' => $assigned->id, 'employee_id' => $employee->id]);

        $user = $this->userWithPermissions(['clients_view_own_clients'], $employee->id);

        $ids = app(ClientAccessService::class)->getAccessibleClientIds($user);

        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($other->id));
    }

    /**
     * انحدار: دورٌ يملك `clients_view_by_employee_groups` (نطاقها فارغ —
     * لا مجموعات مسنَدة، الحالة الشائعة) و`clients_view_by_daily_itinerary`
     * معاً (تُعرَضان متجاورتين في شاشة الصلاحيات فتُفعَّلان معاً غالباً).
     * قبل الإصلاح: كانت `applyPermissionCascade()` تتوقّف عند أوّل صلاحيةٍ
     * يملكها المستخدم بصرف النظر عن نتيجتها — فتُطبِّق `1=0` عند الفارغة
     * الأولى ولا تصل أبداً إلى نطاق خطّ السير الحقيقي المعبَّأ.
     */
    public function test_daily_itinerary_clients_still_show_when_employee_groups_permission_is_also_granted_but_empty(): void
    {
        $employee = Employee::create(['first_name' => 'Ahmed']);
        $itineraryClient = Client::create(['code' => 'C1', 'trade_name' => 'OnItinerary']);
        Client::create(['code' => 'C2', 'trade_name' => 'NotOnItinerary']);

        $user = $this->userWithPermissions(['clients_view_by_employee_groups', 'clients_view_by_daily_itinerary'], $employee->id);

        // نفس السياق (يوم/أسبوع/سنة) الذي تحسبه الخدمة فعلياً — لا تاريخ ثابت
        $context = app(ClientAccessService::class)->todayItineraryContext();

        // لا صفوف في employee_group لهذا الموظف — نطاقها فارغ عن قصد
        \App\Models\EmployeeClientVisit::create([
            'employee_id' => $user->id,
            'client_id' => $itineraryClient->id,
            'day_of_week' => $context['dayNameEn'],
            'year' => $context['year'],
            'week_number' => $context['week'],
        ]);

        $ids = app(ClientAccessService::class)->getAccessibleClientIds($user);

        $this->assertTrue($ids->contains($itineraryClient->id), 'عميل خط السير يجب أن يظهر رغم صلاحية المجموعات الفارغة');
    }

    // C: بلا أي صلاحية عرض ولا ارتباط موظّف => لا شيء
    public function test_user_without_any_view_permission_sees_nothing(): void
    {
        Client::create(['code' => 'C1', 'trade_name' => 'One']);

        $user = $this->userWithPermissions([]);

        $ids = app(ClientAccessService::class)->getAccessibleClientIds($user);

        $this->assertCount(0, $ids);
        $this->assertFalse(app(ClientAccessService::class)->canAccessClient($user, 1));
    }

    // F: تسجيل زيارة لعميل خارج نطاق المستخدم => 403 (الثغرة المُصلَحة)
    public function test_register_visit_denies_access_to_client_outside_users_scope(): void
    {
        $accessible = Client::create(['code' => 'C1', 'trade_name' => 'Mine']);
        $outsider = Client::create(['code' => 'C2', 'trade_name' => 'NotMine']);

        $employee = Employee::create(['first_name' => 'Ahmed']);
        ClientEmployee::create(['client_id' => $accessible->id, 'employee_id' => $employee->id]);

        $user = $this->userWithPermissions(['clients_view_own_clients', 'clients_visits_track'], $employee->id);
        $this->actingAs($user);

        $this->expectException(HttpException::class);

        try {
            app(ClientController::class)->registerVisit($outsider->id);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    // نفس الحارس: يسمح بزيارة عميلٍ داخل نطاق المستخدم (لا يمنع بلا داعٍ)
    public function test_register_visit_allows_access_to_a_client_within_users_scope(): void
    {
        $accessible = Client::create(['code' => 'C1', 'trade_name' => 'Mine']);
        $employee = Employee::create(['first_name' => 'Ahmed']);
        ClientEmployee::create(['client_id' => $accessible->id, 'employee_id' => $employee->id]);
        \App\Models\Location::create(['client_id' => $accessible->id, 'latitude' => 24.7136, 'longitude' => 46.6753]);

        $user = $this->userWithPermissions(['clients_view_own_clients', 'clients_visits_track'], $employee->id);
        $this->actingAs($user);

        // لا صفحة وسيطة بعد الآن — تنشئ الزيارة مباشرةً (بمرحلة STAGE_ARRIVED،
        // بلا started_at) وتحوّل إلى مساحة العمل مباشرةً.
        $response = app(ClientController::class)->registerVisit($accessible->id);

        $this->assertStringContainsString('workspace', $response->getTargetUrl());

        $visit = \App\Models\Visit::where('client_id', $accessible->id)->latest()->first();
        $this->assertNotNull($visit);
        $this->assertNull($visit->started_at, 'المؤقّت يجب ألا يبدأ إلا بالضغط على «بدء الزيارة»');
        $this->assertSame(\App\Models\Visit::STAGE_ARRIVED, $visit->stage);
    }

    // المؤقّت لا يبدأ إلا بالضغط على «بدء الزيارة»، وذلك مرفوضٌ من بعيدٍ عن موقع العميل
    public function test_starting_a_visit_from_far_away_is_rejected_and_timer_stays_zero(): void
    {
        $client = Client::create(['code' => 'C1', 'trade_name' => 'Mine']);
        $employee = Employee::create(['first_name' => 'Ahmed']);
        $user = $this->userWithPermissions(['clients_view_own_clients'], $employee->id);
        $this->actingAs($user);

        $visit = \App\Models\Visit::create([
            'employee_id' => $user->id,
            'client_id' => $client->id,
            'visit_date' => now(),
            'stage' => \App\Models\Visit::STAGE_ARRIVED,
            'client_latitude' => 24.7136,
            'client_longitude' => 46.6753,
        ]);

        $farRequest = Request::create('/x', 'POST', ['current_latitude' => 24.0, 'current_longitude' => 46.0]);
        $response = app(VisitWorkspaceController::class)->start($farRequest, $visit->id);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['success']);
        $visit->refresh();
        $this->assertNull($visit->started_at, 'زيارة بعيدة عن موقع العميل يجب ألا تبدأ');
        $this->assertSame(0, $visit->elapsed_seconds);
    }

    // نفس الحارس: يسمح بالبدء فعلياً من موقع العميل
    public function test_starting_a_visit_at_the_clients_location_succeeds(): void
    {
        $client = Client::create(['code' => 'C1', 'trade_name' => 'Mine']);
        $employee = Employee::create(['first_name' => 'Ahmed']);
        $user = $this->userWithPermissions(['clients_view_own_clients'], $employee->id);
        $this->actingAs($user);

        $visit = \App\Models\Visit::create([
            'employee_id' => $user->id,
            'client_id' => $client->id,
            'visit_date' => now(),
            'stage' => \App\Models\Visit::STAGE_ARRIVED,
            'client_latitude' => 24.7136,
            'client_longitude' => 46.6753,
        ]);

        $nearRequest = Request::create('/x', 'POST', ['current_latitude' => 24.7136, 'current_longitude' => 46.6753]);
        $response = app(VisitWorkspaceController::class)->start($nearRequest, $visit->id);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $visit->refresh();
        $this->assertNotNull($visit->started_at);
        $this->assertSame(\App\Models\Visit::STAGE_IN_PROGRESS, $visit->stage);
    }

    // H: إضافة ملاحظة تبقى محروسة بنفس صلاحيات ما قبل النقل من ClientController
    public function test_addnotes_is_still_gated_after_moving_to_client_note_controller(): void
    {
        $middleware = app(ClientNoteController::class)->getMiddleware();

        $addNotesRule = collect($middleware)->first(
            fn ($m) => in_array('addnotes', $m['options']['only'] ?? [], true)
        );

        $this->assertNotNull($addNotesRule, 'addnotes() يجب أن يبقى محروساً بصلاحية بعد النقل');
        $this->assertStringContainsString('client_follow_up_add_notes_attachments_appointments_all', $addNotesRule['middleware']);
    }

    // I: إسناد موظّف للعميل يبقى محروساً بنفس الصلاحية بعد النقل إلى ClientEmployeeController
    public function test_assign_employees_is_still_gated_after_moving_to_client_employee_controller(): void
    {
        $middleware = app(ClientEmployeeController::class)->getMiddleware();

        $assignRule = collect($middleware)->first(
            fn ($m) => in_array('assignEmployees', $m['options']['only'] ?? [], true)
        );

        $this->assertNotNull($assignRule, 'assignEmployees() يجب أن يبقى محروساً بصلاحية بعد النقل');
        $this->assertSame('permission:clients_assign_employees', $assignRule['middleware']);
    }

    // نطاق القرب المخصَّص: إعدادٌ عامّ + تجاوزٌ لموظفٍ بعينه فقط — لا حقل على العميل نفسه (بطلبٍ صريح من المستخدم)
    public function test_proximity_resolver_falls_back_to_general_setting_when_employee_has_no_override(): void
    {
        $employee = User::create(['name' => 'e1', 'email' => 'e1@example.test', 'password' => 'x']);

        $this->assertSame(0.3, ClientSettingsHelper::visitProximityRadiusKmFor($employee));
    }

    public function test_proximity_resolver_uses_employee_override_when_set(): void
    {
        $employee = User::create([
            'name' => 'e2', 'email' => 'e2@example.test', 'password' => 'x',
            'visit_proximity_radius_km' => 1.2,
        ]);

        $this->assertSame(1.2, ClientSettingsHelper::visitProximityRadiusKmFor($employee));
    }

    public function test_store_and_destroy_employee_proximity_override_via_settings_screen(): void
    {
        $admin = $this->userWithPermissions(['clients_module_settings_update']);
        $employee = User::create(['name' => 'e3', 'email' => 'e3@example.test', 'password' => 'x']);

        $storeRequest = Request::create('/x', 'POST', ['user_id' => $employee->id, 'radius_meters' => 500]);
        app(\Modules\Client\Http\Controllers\ClientModuleSettingsController::class)->storeEmployeeProximityOverride($storeRequest);

        $this->assertSame(0.5, $employee->fresh()->visit_proximity_radius_km);

        app(\Modules\Client\Http\Controllers\ClientModuleSettingsController::class)->destroyEmployeeProximityOverride($storeRequest, $employee->id);

        $this->assertNull($employee->fresh()->visit_proximity_radius_km);
    }
}
