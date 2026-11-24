<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // الصلاحيات الجديدة للموظفين المعينين على عملاء محددين
        $permissions = [
            [
                'name' => 'sales_view_assigned_client_invoices',
                'description' => 'عرض جميع فواتير العملاء المعينين (للموظفين المعينين على عميل)',
                'guard_name' => 'web',
            ],
            [
                'name' => 'sales_edit_delete_assigned_client_invoices',
                'description' => 'تعديل وحذف فواتير العملاء المعينين (للموظفين المعينين على عميل)',
                'guard_name' => 'web',
            ],
            [
                'name' => 'sales_add_payments_assigned_client',
                'description' => 'إضافة مدفوعات للعملاء المعينين',
                'guard_name' => 'web',
            ],
            [
                'name' => 'client_follow_up_add_notes_assigned_client',
                'description' => 'إضافة ملاحظات ومرفقات وتعيينات للعملاء المعينين',
                'guard_name' => 'web',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'sales_view_assigned_client_invoices',
            'sales_edit_delete_assigned_client_invoices',
            'sales_add_payments_assigned_client',
            'client_follow_up_add_notes_assigned_client',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }
    }
};
