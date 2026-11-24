<?php

namespace App\Traits;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait للتعامل مع صلاحيات الموظفين المعينين على العملاء
 * يوفر دوال مساعدة للتحقق من صلاحيات الوصول للعميل والفواتير
 */
trait AssignedEmployeeAccess
{
    /**
     * التحقق من أن الموظف معين على العميل المحدد
     */
    public function isEmployeeAssignedToClient($employeeId, $clientId): bool
    {
        return Client::find($clientId)
            ?->employees()
            ->where('employee_id', $employeeId)
            ->exists() ?? false;
    }

    /**
     * التحقق من أن الموظف يمكنه الوصول للعميل
     * إما أنه معين على العميل أو لديه الصلاحية الكاملة
     */
    public function canAccessClient($user, $clientId): bool
    {
        // نطاقٌ كامل — يرى كل شيء بلا قيد
        if (hasPermission('data_scope_all')) {
            return true;
        }

        // إذا كان معين على هذا العميل
        if ($user->employee && $this->isEmployeeAssignedToClient($user->employee->id, $clientId)) {
            return true;
        }

        return false;
    }

    /**
     * فلترة استعلام الفواتير بناءً على صلاحيات الموظف
     */
    public function filterInvoicesByUserPermissions($query, $user): Builder
    {
        // نطاقٌ كامل — الاستعلام بلا قيد
        if (hasPermission('data_scope_all')) {
            return $query;
        }

        // إذا كان لديه صلاحية رؤية فواتير العملاء المُسنَدين إليه
        if (hasPermission('sales.invoices.view_assigned_clients')) {
            if ($user->employee) {
                // جلب الفواتير الخاصة بالعملاء المعين عليهم الموظف
                return $query->whereHas('client', function ($q) use ($user) {
                    $q->whereHas('employees', function ($eq) use ($user) {
                        $eq->where('employee_id', $user->employee->id);
                    });
                });
            }
        }

        // افتراضي: رؤية فقط الفواتير التي أنشأها هو
        if (hasPermission('sales.invoices.view')) {
            return $query->where('created_by', $user->id);
        }

        return $query->whereRaw('1=0'); // لا يمكن رؤية أي شيء
    }

    /**
     * التحقق من أن الموظف يمكنه تعديل/حذف الفاتورة
     */
    public function canEditInvoice($user, $invoice): bool
    {
        // نطاقٌ كامل — يعدّل أي فاتورة
        if (hasPermission('data_scope_all')) {
            return true;
        }

        // إذا كان لديه صلاحية فواتير العملاء المُسنَدين إليه
        if (hasPermission('sales.invoices.view_assigned_clients')) {
            if ($user->employee && $this->isEmployeeAssignedToClient($user->employee->id, $invoice->client_id)) {
                return true;
            }
        }

        // وإلا: صلاحية التعديل تسري على ما أنشأه هو وحده
        if (hasPermission('sales.invoices.edit')) {
            return $invoice->created_by === $user->id;
        }

        return false;
    }

    /**
     * التحقق من أن الموظف يمكنه إضافة مدفوعات للعميل
     */
    public function canAddPayment($user, $clientId): bool
    {
        // نطاقٌ كامل — يقبض من أي عميل
        if (hasPermission('data_scope_all')) {
            return true;
        }

        // إذا كان لديه صلاحية مدفوعات العملاء المُسنَدين إليه
        if (hasPermission('sales.payments.view_assigned_clients')) {
            if ($user->employee && $this->isEmployeeAssignedToClient($user->employee->id, $clientId)) {
                return true;
            }
        }

        // وإلا: صلاحية إنشاء الدفعة وحدها
        if (hasPermission('sales.payments.create')) {
            return true;
        }

        return false;
    }

    /**
     * التحقق من أن الموظف يمكنه إضافة ملاحظات للعميل
     */
    public function canAddClientNotes($user, $clientId): bool
    {
        // إذا كان لديه صلاحية إضافة ملاحظات لجميع العملاء
        if (hasPermission('client_follow_up_add_notes_attachments_appointments_all')) {
            return true;
        }

        // إذا كان لديه صلاحية إضافة ملاحظات للعملاء المعينين
        if (hasPermission('client_follow_up_add_notes_assigned_client')) {
            if ($user->employee && $this->isEmployeeAssignedToClient($user->employee->id, $clientId)) {
                return true;
            }
        }

        // إذا كان لديه صلاحية إضافة ملاحظاته فقط
        if (hasPermission('client_follow_up_add_notes_attachments_appointments_own')) {
            return true;
        }

        return false;
    }
}
