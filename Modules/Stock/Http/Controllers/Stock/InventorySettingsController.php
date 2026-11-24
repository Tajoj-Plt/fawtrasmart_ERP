<?php


namespace Modules\Stock\Http\Controllers\Stock;
use App\Http\Controllers\Controller;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\GeneralSettings;
use App\Models\Log;
use App\Models\PriceList;
use App\Models\StoreHouse;
use Illuminate\Http\Request;
use App\Support\OperationLog;

class InventorySettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:stock.settings.view', ['only' => ['index']]);
        $this->middleware('permission.role:stock.settings.general', ['only' => ['general', 'store']]);
        $this->middleware('permission.role:stock.settings.employee_warehouse', ['only' => [
            'employee_default_warehouse',
            'employee_default_warehouse_create',
            'employee_default_warehouse_store',
            'employee_default_warehouse_delete',
            'employee_default_warehouse_show',
            'employee_default_warehouse_edit',
            'employee_default_warehouse_update',
        ]]);
    }

    public function index()
    {
        return view('stock::inventory_settings.index');
    }

    public function general()
    {
        $general_settings = GeneralSettings::select()->first();
        $price_lists = PriceList::select('id', 'name')->get();
        $storehouses = StoreHouse::select('id', 'name')->get();
        return view('stock::products_settings.general', compact('price_lists', 'storehouses', 'general_settings'));
    }

    public function store(Request $request)
    {
        $generalSettings = GeneralSettings::select()->first();

        if (!$generalSettings) {

            $generalSettings = new GeneralSettings();
        }

        $generalSettings->sub_account = $request->sub_account;
        $generalSettings->storehouse_id = $request->storehouse_id;
        $generalSettings->price_list_id = $request->price_list_id;
        $generalSettings->enable_negative_stock = $request->has('enable_negative_stock') ? 1 : 0;
        $generalSettings->advanced_pricing_options = $request->has('advanced_pricing_options') ? 1 : 0;
        $generalSettings->enable_stock_requests = $request->has('enable_stock_requests') ? 1 : 0;
        $generalSettings->enable_sales_stock_authorization = $request->has('enable_sales_stock_authorization') ? 1 : 0;
        $generalSettings->enable_purchase_stock_authorization = $request->has('enable_purchase_stock_authorization') ? 1 : 0;
        $generalSettings->track_products_by_serial_or_batch = $request->has('track_products_by_serial_or_batch') ? 1 : 0;
        $generalSettings->allow_negative_tracking_elements = $request->has('allow_negative_tracking_elements') ? 1 : 0;
        $generalSettings->enable_multi_units_system = $request->has('enable_multi_units_system') ? 1 : 0;
        $generalSettings->inventory_quantity_by_date = $request->has('inventory_quantity_by_date') ? 1 : 0;
        $generalSettings->enable_assembly_and_compound_units = $request->has('enable_assembly_and_compound_units') ? 1 : 0;
        $generalSettings->show_available_quantity_in_warehouse = $request->has('show_available_quantity_in_warehouse') ? 1 : 0;

        $generalSettings->save();

        return redirect()->back()->with(['success' => 'تم التعديل بنجاح']);
    }

    public function employee_default_warehouse()
    {
        $warehouses = StoreHouse::select('id', 'name')->get();
        $employees = Employee::select('id', 'first_name', 'middle_name','nickname')->get();
        $default_warehouses = DefaultWarehouses::select()->orderBy('id', 'DESC')->get();
        return view('stock::products_settings.employee_default_warehouse', compact('employees', 'warehouses', 'default_warehouses'));
    }

    public function employee_default_warehouse_create()
    {
        $warehouses = StoreHouse::select('id', 'name')->get();
        $employees = Employee::select('id', 'first_name', 'middle_name', 'nickname')->get();
        return view('stock::products_settings.employee_default_warehouse_create', compact('employees', 'warehouses'));
    }

    public function employee_default_warehouse_store(Request $request)
    {
        /*
            كان الحفظ بلا تحقّق: العمودان `storehouse_id` و`employee_id` لا
            يقبلان NULL، فإرسالٌ ناقص يسقط بخطأ قاعدة بيانات (500). ثم يُقرأ
            `->storehouse->name` و`->employee->first_name` بلا فحص، فمعرّفٌ
            لا يقابله سجلّ يسقط بـ«property on null» — وهو 500 آخر.

            والموظّف الواحد له مستودعٌ افتراضي واحد، فالإسناد يُحدَّث لا يُكرَّر.
        */
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'storehouse_id' => ['required', 'integer', 'exists:store_houses,id'],
        ], [
            'employee_id.required' => 'اختر الموظف',
            'employee_id.exists' => 'الموظف المختار غير موجود',
            'storehouse_id.required' => 'اختر المستودع',
            'storehouse_id.exists' => 'المستودع المختار غير موجود',
        ]);

        $default_warehouse = DefaultWarehouses::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            ['storehouse_id' => $data['storehouse_id']]
        );

        $default_warehouse->load('employee', 'storehouse');

        $employeeName = trim(implode(' ', array_filter([
            optional($default_warehouse->employee)->first_name,
            optional($default_warehouse->employee)->middle_name,
            optional($default_warehouse->employee)->nickname,
        ])));

        OperationLog::record(
            'logged',
            'product_log',
            $default_warehouse->id,
            sprintf(
                'تم تعيين **%s** مستودعًا افتراضيًا للموظف **%s**',
                optional($default_warehouse->storehouse)->name ?? 'مستودع محذوف',
                $employeeName !== '' ? $employeeName : 'موظف محذوف'
            ),
        );

        return redirect()
            ->route('inventory_settings.employee_default_warehouse')
            ->with(['success' => 'تم حفظ المستودع الافتراضي للموظف بنجاح']);
    }

    public function employee_default_warehouse_delete($id)
    {
        $default_warehouse = DefaultWarehouses::find($id);

        // تحميل العلاقات مع الموظف والمستودع
        $default_warehouse->load('employee', 'storehouse');

        // تسجيل اشعار نظام جديد
        OperationLog::record(
            'logged',
            'product_log',
            $default_warehouse->id,
            sprintf(
                'تم حذف المستودع الافتراضي **%s** للموظف **%s %s %s**',
                $default_warehouse->storehouse->name, 
                $default_warehouse->employee->first_name, 
                $default_warehouse->employee->middle_name, 
                $default_warehouse->employee->nickname 
            ),
        );

        // حذف المستودع الافتراضي
        $default_warehouse->delete();
        return redirect()->route('inventory_settings.employee_default_warehouse')->with(['error' => 'تم حذف المستودع الافتراضي للموظف بنجاح']);
    }

    public function employee_default_warehouse_show($id)
    {
        $default_warehouse = DefaultWarehouses::find($id);
        return view('stock::products_settings.employee_default_warehouse_show', compact('default_warehouse'));
    }

    public function employee_default_warehouse_edit($id)
    {
        $default_warehouse = DefaultWarehouses::find($id);
        // تسجيل اشعار نظام جديد

        $warehouses = StoreHouse::select('id', 'name')->get();
        $employees = Employee::select('id', 'first_name', 'middle_name')->get();
        return view('stock::products_settings.employee_default_warehouse_edit', compact('default_warehouse', 'employees', 'warehouses'));
    }

    public function employee_default_warehouse_update(Request $request, $id)
    {
        $default_warehouse = DefaultWarehouses::find($id);

        // حفظ القيم القديمة قبل التحديث
        $oldStorehouseId = $default_warehouse->storehouse_id;
        $oldEmployeeId = $default_warehouse->employee_id;

        // تحميل العلاقات مع الموظف والمستودع القديم
        $default_warehouse->load('employee', 'storehouse');
        $oldStorehouse = StoreHouse::find($oldStorehouseId);
        $oldEmployee = Employee::find($oldEmployeeId);

        // تحديث القيم الجديدة
        $default_warehouse->storehouse_id = $request->storehouse_id;
        $default_warehouse->employee_id = $request->employee_id;
        $default_warehouse->update();

        // تحميل العلاقات مع الموظف والمستودع الجديد
        $default_warehouse->load('employee', 'storehouse');
        $newStorehouse = StoreHouse::find($request->storehouse_id);
        $newEmployee = Employee::find($request->employee_id);

        // بناء النص بناءً على التغييرات
        $description = '';

        if ($oldStorehouseId != $request->storehouse_id && $oldEmployeeId != $request->employee_id) {
            // تم تغيير المستودع والموظف
            $description = sprintf(
                'تم تغيير المستودع الافتراضي والموظف من **%s** (الموظف: **%s %s %s**) إلى **%s** (الموظف: **%s %s %s**)',
                $oldStorehouse->name, // المستودع القديم
                $oldEmployee->first_name,
                $oldEmployee->middle_name,
                $oldEmployee->nickname, // الموظف القديم
                $newStorehouse->name, // المستودع الجديد
                $newEmployee->first_name,
                $newEmployee->middle_name,
                $newEmployee->nickname // الموظف الجديد
            );
        } elseif ($oldStorehouseId != $request->storehouse_id) {
            // تم تغيير المستودع فقط
            $description = sprintf(
                'تم تغيير المستودع الافتراضي من **%s** إلى **%s** للموظف **%s %s %s**',
                $oldStorehouse->name, // المستودع القديم
                $newStorehouse->name, // المستودع الجديد
                $oldEmployee->first_name,
                $oldEmployee->middle_name,
                $oldEmployee->nickname // الموظف
            );
        } elseif ($oldEmployeeId != $request->employee_id) {
            // تم تغيير الموظف فقط
            $description = sprintf(
                'تم تغيير الموظف للمستودع الافتراضي **%s** من **%s %s %s** إلى **%s %s %s**',
                $oldStorehouse->name, // المستودع
                $oldEmployee->first_name,
                $oldEmployee->middle_name,
                $oldEmployee->nickname, // الموظف القديم
                $newEmployee->first_name,
                $newEmployee->middle_name,
                $newEmployee->nickname // الموظف الجديد
            );
        } else {
            // لم يتم تغيير شيء
            $description = 'لم يتم تغيير أي شيء.';
        }

        // تسجيل اشعار نظام جديد
        OperationLog::record('logged', 'product_log', $default_warehouse->id, $description);

        return redirect()->route('inventory_settings.employee_default_warehouse')->with(['success' => 'تم تعديل المستودع الافتراضي للموظف بنجاح']);
    }
}
