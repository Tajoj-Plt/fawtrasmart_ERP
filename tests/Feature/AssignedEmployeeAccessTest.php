<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use App\Traits\AssignedEmployeeAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات نظام صلاحيات الموظفين المعينين على العملاء
 *
 * هذه الاختبارات توضح كيفية عمل النظام الجديد
 */
class AssignedEmployeeAccessTest extends TestCase
{
    use AssignedEmployeeAccess;
    use RefreshDatabase;

    protected $employee1;

    protected $employee2;

    protected $user1;

    protected $user2;

    protected $client;

    protected $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء موظفين
        $this->employee1 = Employee::create(['full_name' => 'أحمد']);
        $this->employee2 = Employee::create(['full_name' => 'محمد']);

        // إنشاء مستخدمين
        $this->user1 = User::create([
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'password' => bcrypt('password'),
            'employee_id' => $this->employee1->id,
        ]);

        $this->user2 = User::create([
            'name' => 'محمد',
            'email' => 'mohammed@example.com',
            'password' => bcrypt('password'),
            'employee_id' => $this->employee2->id,
        ]);

        // إنشاء عميل
        $this->client = Client::create([
            'code' => 'CLI001',
            'trade_name' => 'شركة الاختبار',
            'first_name' => 'علي',
            'last_name' => 'الاختبار',
        ]);

        // تعيين الموظف الأول على العميل
        $this->client->employees()->attach($this->employee1->id);

        // إنشاء فاتورة من قبل الموظف الأول
        $this->invoice = Invoice::create([
            'client_id' => $this->client->id,
            'employee_id' => $this->employee1->id,
            'created_by' => $this->user1->id,
            'code' => 'INV001',
            'grand_total' => 1000,
            'issue_date' => now(),
            'invoice_date' => now(),
        ]);
    }

    /**
     * اختبار التحقق من تعيين الموظف على العميل
     */
    public function test_can_check_if_employee_is_assigned_to_client()
    {
        // الموظف الأول معين على العميل
        $this->assertTrue(
            $this->isEmployeeAssignedToClient($this->employee1->id, $this->client->id)
        );

        // الموظف الثاني غير معين
        $this->assertFalse(
            $this->isEmployeeAssignedToClient($this->employee2->id, $this->client->id)
        );
    }

    /**
     * اختبار التحقق من وصول الموظف للعميل
     */
    public function test_can_check_client_access()
    {
        // الموظف الأول يمكنه الوصول لأنه معين على العميل
        $this->assertTrue(
            $this->canAccessClient($this->user1, $this->client->id)
        );

        // الموظف الثاني لا يمكنه الوصول
        $this->assertFalse(
            $this->canAccessClient($this->user2, $this->client->id)
        );
    }

    /**
     * اختبار فلترة الفواتير حسب الصلاحيات
     */
    public function test_can_filter_invoices_by_user_permissions()
    {
        // إنشاء فاتورة ثانية من الموظف الثاني
        $this->client->employees()->attach($this->employee2->id);

        Invoice::create([
            'client_id' => $this->client->id,
            'employee_id' => $this->employee2->id,
            'created_by' => $this->user2->id,
            'code' => 'INV002',
            'grand_total' => 2000,
            'issue_date' => now(),
            'invoice_date' => now(),
        ]);

        // إعطاء الموظفين صلاحية رؤية فواتير العملاء المعينين
        $this->user1->givePermissionTo('sales.invoices.view_assigned_clients');
        $this->user2->givePermissionTo('sales.invoices.view_assigned_clients');

        // الموظف الأول يرى فواتيره فقط (حالياً فاتورة واحدة)
        $query1 = Invoice::query();
        $filteredInvoices1 = $this->filterInvoicesByUserPermissions($query1, $this->user1);
        $this->assertEquals(1, $filteredInvoices1->count());

        // الموظف الثاني يرى فواتيره فقط
        $query2 = Invoice::query();
        $filteredInvoices2 = $this->filterInvoicesByUserPermissions($query2, $this->user2);
        $this->assertEquals(1, $filteredInvoices2->count());
    }

    /**
     * اختبار التحقق من إمكانية تعديل الفاتورة
     */
    public function test_can_check_invoice_edit_permission()
    {
        // الموظف الأول يمكنه تعديل الفاتورة
        $this->user1->givePermissionTo('sales.invoices.view_assigned_clients');
        $this->assertTrue(
            $this->canEditInvoice($this->user1, $this->invoice)
        );

        // الموظف الثاني لا يمكنه تعديل (لم يتم تعيينه بعد على العميل)
        $this->assertFalse(
            $this->canEditInvoice($this->user2, $this->invoice)
        );
    }

    /**
     * اختبار التحقق من إمكانية إضافة مدفوعات
     */
    public function test_can_check_payment_permission()
    {
        // الموظف الأول معين على العميل وله صلاحية
        $this->user1->givePermissionTo('sales.payments.view_assigned_clients');
        $this->assertTrue(
            $this->canAddPayment($this->user1, $this->client->id)
        );

        // الموظف الثاني ليس معيناً
        $this->assertFalse(
            $this->canAddPayment($this->user2, $this->client->id)
        );
    }

    /**
     * اختبار التحقق من إمكانية إضافة ملاحظات
     */
    public function test_can_check_notes_permission()
    {
        // الموظف الأول معين على العميل وله صلاحية
        $this->user1->givePermissionTo('client_follow_up_add_notes_assigned_client');
        $this->assertTrue(
            $this->canAddClientNotes($this->user1, $this->client->id)
        );

        // الموظف الثاني ليس معيناً
        $this->assertFalse(
            $this->canAddClientNotes($this->user2, $this->client->id)
        );
    }

    /**
     * اختبار سيناريو واقعي: موظفان يعملان على نفس العميل
     */
    public function test_realistic_scenario_two_employees_on_same_client()
    {
        // إضافة الموظف الثاني للعميل
        $this->client->employees()->attach($this->employee2->id);

        // إعطاء كلا الموظفين الصلاحيات المطلوبة
        $this->user1->givePermissionTo([
            'sales.invoices.view_assigned_clients',
            'sales.invoices.view_assigned_clients',
            'sales.payments.view_assigned_clients',
            'client_follow_up_add_notes_assigned_client',
        ]);

        $this->user2->givePermissionTo([
            'sales.invoices.view_assigned_clients',
            'sales.invoices.view_assigned_clients',
            'sales.payments.view_assigned_clients',
            'client_follow_up_add_notes_assigned_client',
        ]);

        // كلا الموظفين يمكنهما الوصول للعميل
        $this->assertTrue($this->canAccessClient($this->user1, $this->client->id));
        $this->assertTrue($this->canAccessClient($this->user2, $this->client->id));

        // كلا الموظفين يمكنهما إضافة مدفوعات
        $this->assertTrue($this->canAddPayment($this->user1, $this->client->id));
        $this->assertTrue($this->canAddPayment($this->user2, $this->client->id));

        // كلا الموظفين يمكنهما إضافة ملاحظات
        $this->assertTrue($this->canAddClientNotes($this->user1, $this->client->id));
        $this->assertTrue($this->canAddClientNotes($this->user2, $this->client->id));
    }
}
