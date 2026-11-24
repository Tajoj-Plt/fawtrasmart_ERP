<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeNote;
use App\Services\Clients\ClientVisibilityScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * إسناد الموظفين لعميل بعينه (فردياً، لا الإسناد الجماعي بالمجموعة — ذاك في
 * `ClientAssignmentController`)، ودليل موظفي العملاء («جهات الاتصال»).
 *
 * منقولةٌ من `ClientController` بلا تغيير في المنطق أو الصلاحيات أو أسماء
 * الـ routes — راجع `Modules/Client/routes/web.php`؛ الأسماء
 * (`clients.assign-employees`، `clients.remove-employee`، …) بقيت كما هي،
 * تغيّر الكنترولر المستهدَف فقط.
 *
 * @see \App\Constants\permissions\ClientsPermissions كتالوج الصلاحيات
 */
class ClientEmployeeController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط — نفس قيمة ClientController الأصلية. */
    public const LOG_MODULE = 'Clients';

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary', [
            'only' => ['getClientEmployees'],
        ]);

        $this->middleware('permission:clients_assign_employees', [
            'only' => ['assignEmployees', 'removeEmployee', 'getAssignedEmployees', 'updateClientEmployee', 'clientEmployeesDirectory'],
        ]);
    }

    // الحصول على موظفي العميل
    public function getClientEmployees($id)
    {
        $employees = ClientEmployee::where('client_id', $id)
            ->with('employee')
            ->get()
            ->map(function ($ce) {
                $employee = $ce->employee;
                $name = $employee ? ($employee->nickname ?? ($employee->first_name.' '.$employee->middle_name)) : 'غير معروف';

                return [
                    'id' => $ce->id,
                    'name' => $name,
                    'employee_id' => $ce->employee_id,
                    'employee_number' => $ce->employee_number,
                    'is_primary' => $ce->is_primary,
                    'invoice_id' => $ce->invoice_id,
                ];
            });

        return response()->json([
            'employees' => $employees,
            'count' => $employees->count(),
        ]);
    }

    // تحديث بيانات موظف العميل بشكل فردي
    public function updateClientEmployee(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'employee_number' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
            'invoice_id' => 'nullable|exists:invoices,id',
        ]);

        try {
            $clientEmployee = ClientEmployee::findOrFail($employeeId);
            $clientEmployee->update($validated);

            OperationLog::record(
                'client_employee_updated',
                self::LOG_MODULE,
                $clientEmployee->client_id,
                'تحديث بيانات موظف العميل #'.$employeeId
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات موظف العميل بنجاح',
                'data' => $clientEmployee,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'updateClientEmployee');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function assignEmployees(Request $request, Client $client)
    {
        /* النطاق يُفرَض على الخادم لا على القائمة وحدها.

           «أقسام الموظفين المسموح تعيينهم للعملاء» تُضيّق النافذةَ في الشاشة،
           لكنّ الطلبَ المصنوع بيد يتجاوزها — فيُفحَص هنا أيضاً. والمعيَّنون
           حالياً مستثنَون: خروجُ قسمِهم من النطاق لاحقاً لا يجعل إعادةَ
           إرسالهم مع القائمة كما هي خطأً يمنع كلَّ حفظ. */
        $allowedDepartments = \App\Helpers\ClientSettingsHelper::clientEmployeeDepartmentIds();

        $request->validate([
            'employee_id' => 'required|array',
            'employee_id.*' => [
                'exists:employees,id',
                function ($attribute, $value, $fail) use ($allowedDepartments, $client) {
                    if (! $allowedDepartments) {
                        return; // الفراغ = كل الأقسام
                    }

                    if ($client->employees->contains('id', (int) $value)) {
                        return;
                    }

                    $inScope = \App\Models\Employee::whereKey($value)
                        ->whereIn('department_id', $allowedDepartments)
                        ->exists();

                    if (! $inScope) {
                        $fail('أحد الموظفين المختارين خارج الأقسام المسموح تعيينها للعملاء في إعدادات وحدات العملاء.');
                    }
                },
            ],
        ]);

        try {

            // بدء المعاملة
            DB::beginTransaction();

            // مزامنة الموظفين (إضافة وإزالة)
            $client->employees()->sync($request->employee_id);

            // إنهاء المعاملة
            DB::commit();

            OperationLog::record(
                'employees_assigned',
                self::LOG_MODULE,
                $client,
                'تعيين موظفين للعميل «'.$client->trade_name.'»'
            );

            // إعادة التوجيه مع رسالة نجاح
            return redirect()->back()->with('success', 'تم تعيين الموظفين بنجاح');
        } catch (\Exception $e) {
            // إلغاء المعاملة في حالة الخطأ
            DB::rollBack();

            // تسجيل الخطأ
            OperationLog::failure($e, self::LOG_MODULE, 'assign_employees');

            // إعادة التوجيه مع رسالة خطأ
            return redirect()->back()->with('error', 'حدث خطأ أثناء تعيين الموظفين');
        }
    }

    /**
     * إزالة موظف محدد من عميل
     */
    public function removeEmployee(Request $request, $clientId)
    {
        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'خطأ في البيانات المدخلة');
        }

        try {
            // البحث عن العميل
            $client = Client::findOrFail($clientId);

            // إزالة الموظف
            $client->employees()->detach($request->employee_id);

            // تسجيل عملية الإزالة
            OperationLog::record(
                'employee_removed',
                self::LOG_MODULE,
                (int) $clientId,
                'إزالة الموظف #'.$request->employee_id.' عن العميل #'.$clientId
            );

            // إعادة التوجيه مع رسالة نجاح
            return redirect()->back()->with('success', 'تم إزالة الموظف بنجاح');
        } catch (\Exception $e) {
            // تسجيل الخطأ
            OperationLog::failure($e, self::LOG_MODULE, 'remove_employee');

            // إعادة التوجيه مع رسالة خطأ
            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء إزالة الموظف: '.$e->getMessage());
        }
    }

    /**
     * جلب الموظفين المعينين لعميل
     */
    public function getAssignedEmployees($clientId)
    {
        try {
            // البحث عن العميل مع الموظفين المرتبطين
            $client = Client::with('employees')->findOrFail($clientId);

            // إرجاع استجابة JSON
            return response()->json([
                'success' => true,
                'employees' => $client->employees->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'department' => $employee->department,
                        'role' => $employee->role,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            // تسجيل الخطأ
            OperationLog::failure($e, self::LOG_MODULE, 'get_assigned_employees');

            // إرجاع استجابة خطأ
            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب الموظفين',
                ],
                500,
            );
        }
    }

    /**
     * عرض صفحة موظفي العملاء
     */
    public function clientEmployeesDirectory(Request $request)
    {
        /* ── نطاقُ العملاء: نفسُ «إدارة العملاء» وجهات الاتصال ──
           كان يطابق `clients.branch_id = users.branch_id` حرفياً: الفرعُ
           المحاسبيّ لا التبعيّة، وبلا ما تحت العقدة — فاختيارُ «منطقة الرياض»
           في الشريط لا يُري أحداً (عملاؤها معلَّقون تحت فروعها)، ولا صلاحيةَ
           عرضٍ تُحترم إطلاقاً. `applyDocumentSelectScope()` تجمع المحورين:
           الصلاحية، ثمّ فرعُ الشريط وكلُّ ما تحته (والرئيسيّ = المنشأة كلُّها). */
        $scopeClients = fn ($q) => ClientVisibilityScope::applyDocumentSelectScope($q);

        // جلب موظفي العملاء مع بيانات العميل
        $query = ClientEmployeeNote::with(['client:id,trade_name,code,phone,branch_id'])
            ->select([
                'id',
                'client_id',
                'employee_name',
                'employee_position',
                'employee_phone',
                'image',
                'is_primary',
                'created_at',
            ])
            ->whereNotNull('employee_name')
            ->where('employee_name', '!=', '');

        $query->whereHas('client', $scopeClients);

        // فلترة حسب اسم الموظف
        if ($request->filled('employee_name')) {
            $query->where('employee_name', 'like', '%'.$request->employee_name.'%');
        }

        // فلترة حسب العميل
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // فلترة حسب الوظيفة
        if ($request->filled('position')) {
            $query->where('employee_position', 'like', '%'.$request->position.'%');
        }

        // فلترة حسب رقم الجوال
        if ($request->filled('phone')) {
            $query->where('employee_phone', 'like', '%'.$request->phone.'%');
        }

        // ترتيب حسب الحقل المطلوب
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = (int) $request->get('perPage', 100);
        $employees = $query->paginate($perPage);

        // جلب قائمة العملاء للفلتر (حسب الفرع إذا كان محدداً وليس رئيسي)
        $clientsQuery = Client::select(['id', 'trade_name', 'code']);
        $scopeClients($clientsQuery);
        $clients = $clientsQuery->orderBy('trade_name')->get();

        // جلب الوظائف المميزة (حسب الفرع إذا كان محدداً وليس رئيسي)
        $positionsQuery = ClientEmployeeNote::whereNotNull('employee_position')
            ->where('employee_position', '!=', '')
            ->whereHas('client', $scopeClients);

        $positions = $positionsQuery->distinct()->pluck('employee_position');

        // Check if it's an AJAX request
        if ($request->ajax()) {
            $html = view('client::partials.employees_grid', compact('employees'))->render();

            return response()->json([
                'html' => $html,
                'total' => $employees->total(),
            ]);
        }

        return view('client::client_employees_directory', compact('employees', 'clients', 'positions'));
    }
}
