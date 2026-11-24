<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BusinessData;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LeaveType;
use App\Models\Log as ModelsLog;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Revenue;
use App\Models\SalarySlip;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Visit;
use App\Models\WarehousePermits;
use App\Services\ActivityLogService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sitting\Http\Requests\UpdateCompanyInfoRequest;
use ZipArchive;

class SittingInfoController extends Controller
{
    public function __construct()
    {
        // حماية بصلاحية تعديل الإعدادات العامة
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        $userId = auth()->user()->id;

        $client = Client::where('user_id', $userId)->first();
        $account_setting = AccountSetting::where('user_id', $userId)->first();
        $businessData = BusinessData::where('user_id', $userId)->first();

        // جلب البيانات الحقيقية لاستهلاك الباقة
        $usageData = [
            'invoices' => Invoice::where('created_by', $userId)->count(),
            'clients' => Client::where('user_id', $userId)->count(),
            'products' => Product::where('created_by', $userId)->count(),
            'employees' => Employee::where('created_by', $userId)->count(),
            'suppliers' => Supplier::where('created_by', $userId)->count(),
            'expenses' => Expense::where('created_by', $userId)->count(),
            'revenues' => Revenue::where('created_by', $userId)->count(),
            'purchase_orders' => PurchaseInvoice::where('created_by', $userId)->count(),
        ];

        // حساب مساحة التخزين (بالجيجابايت)
        $storageSize = $this->calculateStorageSize($userId);
        $usageData['storage_gb'] = $storageSize;

        // الحدود القصوى للباقة (يمكنك تخصيصها حسب نوع الباقة)
        $packageLimits = [
            'invoices' => 100,
            'clients' => 50,
            'products' => 200,
            'employees' => 10,
            'suppliers' => 30,
            'expenses' => 100,
            'revenues' => 100,
            'purchase_orders' => 50,
            'storage_gb' => 50, // 50 جيجا
        ];

        return view('sitting::accountInfo.index', compact('client', 'account_setting', 'businessData', 'usageData', 'packageLimits'));
    }

    /**
     * حساب مساحة التخزين المستخدمة
     */
    private function calculateStorageSize($userId)
    {
        $uploadsPath = public_path('uploads');
        $size = 0;

        if (is_dir($uploadsPath)) {
            $size = $this->getDirSize($uploadsPath);
        }

        // تحويل من بايت إلى جيجابايت
        return round($size / (1024 * 1024 * 1024), 3);
    }

    /**
     * حساب حجم المجلد
     */
    private function getDirSize($path)
    {
        $size = 0;

        if (! is_dir($path)) {
            return 0;
        }

        foreach (glob(rtrim($path, '/').'/*', GLOB_NOSORT) as $each) {
            $size += is_file($each) ? filesize($each) : $this->getDirSize($each);
        }

        return $size;
    }

    public function create()
    {
        return view('sitting::accountInfo.create');
    }

    public function Backup()
    {

        return view('sitting::accountInfo.Backup');
    }

    public function download(Request $request)
    {
        // التحقق من البيانات المدخلة
        $request->validate([
            'data_types' => 'required|array',
            'data_types.*' => 'in:invoices,clients,purchase_orders,products,expenses,revenues,employees,suppliers,quotations,sales_orders,warehouses,accounts,payments,projects,tasks,appointments,visits,contracts,attendances,leaves,salaries,users,branches,activity_logs',
            'file_format' => 'required|in:xml,json,csv',
        ]);

        // إنشاء ملف ZIP
        $zip = new ZipArchive;
        $zipFileName = 'backup_'.now()->format('Ymd_His').'.zip';
        $zipFilePath = storage_path('app/'.$zipFileName);

        if ($zip->open($zipFilePath, ZipArchive::CREATE) === true) {
            foreach ($request->data_types as $dataType) {
                // جلب البيانات بناءً على الاختيار
                $data = collect(); // استخدام collection فارغة كقيمة افتراضية

                try {
                    switch ($dataType) {
                        case 'invoices':
                            $data = Invoice::all();
                            break;
                        case 'clients':
                            $data = Client::all();
                            break;
                        case 'purchase_orders':
                            $data = PurchaseOrder::all();
                            break;
                        case 'products':
                            $data = Product::all();
                            break;
                        case 'expenses':
                            $data = Expense::all();
                            break;
                        case 'revenues':
                            $data = Revenue::all();
                            break;
                        case 'employees':
                            $data = Employee::all();
                            break;
                        case 'suppliers':
                            $data = Supplier::all();
                            break;
                        case 'quotations':
                            $data = Quote::all();
                            break;
                        case 'sales_orders':
                            $data = Order::all();
                            break;
                        case 'warehouses':
                            $data = WarehousePermits::all();
                            break;
                        case 'accounts':
                            $data = Account::all();
                            break;
                        case 'payments':
                            $data = PaymentMethod::all();
                            break;
                        case 'projects':
                            $data = Project::all();
                            break;
                        case 'tasks':
                            $data = Task::all();
                            break;
                        case 'appointments':
                            $data = Appointment::all();
                            break;
                        case 'visits':
                            $data = Visit::all();
                            break;
                        case 'contracts':
                            $data = Contract::all();
                            break;
                        case 'attendances':
                            $data = Attendance::all();
                            break;
                        case 'leaves':
                            $data = LeaveType::all();
                            break;
                        case 'salaries':
                            $data = SalarySlip::all();
                            break;
                        case 'users':
                            $data = User::all();
                            break;
                        case 'branches':
                            $data = Branch::all();
                            break;
                        case 'activity_logs':
                            $data = ActivityLog::all();
                            break;
                        default:
                            continue 2; // تخطي هذا العنصر إذا لم يكن مدعوماً
                    }
                } catch (\Exception $e) {
                    // في حالة حدوث خطأ (مثل Model غير موجود)، نستمر للعنصر التالي
                    continue;
                }

                // تحويل البيانات إلى التنسيق المطلوب
                $fileContent = '';
                $fileName = $dataType.'_'.now()->format('Ymd_His');

                switch ($request->file_format) {
                    case 'xml':
                        $fileContent = $this->arrayToXml($data->toArray());
                        $fileName .= '.xml';
                        break;
                    case 'json':
                        $fileContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $fileName .= '.json';
                        break;
                    case 'csv':
                        $fileContent = $this->arrayToCsv($data->toArray());
                        $fileName .= '.csv';
                        break;
                }

                // إضافة الملف إلى الأرشيف
                $zip->addFromString($fileName, $fileContent);
            }

            $zip->close();

            // تسجيل العملية في السجل
            try {
                ModelsLog::create([
                    'type' => 'setting',
                    'type_log' => 'backup',
                    'description' => 'تم تنزيل نسخة احتياطية - عدد الجداول: '.count($request->data_types),
                    'created_by' => auth()->id(),
                ]);
            } catch (\Exception $e) {
                // تجاهل أخطاء السجل
            }
        }

        // تنزيل ملف ZIP
        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    /**
     * تحويل المصفوفة إلى XML
     */
    private function arrayToXml(array $data, $rootNodeName = 'root', $xml = null)
    {
        if ($xml === null) {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><'.$rootNodeName.'/>');
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $key = is_numeric($key) ? 'item' : $key;
                $this->arrayToXml($value, $key, $xml->addChild($key));
            } else {
                $key = is_numeric($key) ? 'item' : $key;
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    /**
     * تحويل المصفوفة إلى CSV
     */
    private function arrayToCsv(array $data)
    {
        if (count($data) === 0) {
            return '';
        }

        // فتح مخرجات CSV في الذاكرة
        $output = fopen('php://temp', 'w');

        // كتابة رأس CSV (الأعمدة)
        fputcsv($output, array_keys($data[0]));

        // كتابة البيانات
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        // إرجاع المحتوى كسلسلة نصية
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function store(UpdateCompanyInfoRequest $request)
    {
        $activityLog = new ActivityLogService;
        $userId = auth()->id();

        try {
            DB::beginTransaction();

            // تحديث أو إنشاء بيانات المنشأة
            $businessData = BusinessData::firstOrCreate(['user_id' => $userId]);

            $businessFields = [
                'business_name' => $request->business_name ?? $request->trade_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'mobile' => $request->mobile,
                'street_address1' => $request->street1,
                'street_address2' => $request->street2,
                'city' => $request->city,
                'region' => $request->region,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'tax_number' => $request->tax_number,
                'commercial_registration' => $request->commercial_registration,
                'business_email' => $request->business_email,
                'website' => $request->website,
                'business_type' => $request->business_type,
            ];

            $activityLog->logChanges(__('settings.messages.business_updated_log'), $businessData, $businessFields);
            $businessData->update($businessFields);

            // تحديث أو إنشاء العميل لضمان المزامنة
            $client = Client::where('user_id', $userId)->first();
            if (! $client) {
                $client = new Client;
                $client->user_id = $userId;
            }

            $clientFields = [
                'trade_name' => $request->trade_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'mobile' => $request->mobile,
                'street1' => $request->street1,
                'street2' => $request->street2,
                'city' => $request->city,
                'region' => $request->region,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'tax_number' => $request->tax_number,
                'commercial_registration' => $request->commercial_registration,
                'email' => $request->business_email,
                'currency' => $request->currency,
            ];

            $client->fill($clientFields);
            $client->save();

            // تحديث إعدادات الحساب
            $accountSetting = AccountSetting::firstOrCreate(['user_id' => $userId]);
            $settingFields = array_filter([
                'currency' => $request->currency,
                'timezone' => $request->timezone,
                'business_type' => $request->business_type,
            ], fn ($v) => $v !== null);

            if (! empty($settingFields)) {
                $activityLog->logChanges(__('settings.messages.account_updated_log'), $accountSetting, $settingFields);
                $accountSetting->update($settingFields);
            }

            DB::commit();

            return redirect()->route('AccountInfo.index')->with('success', __('settings.messages.business_saved'));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SittingInfo', 'store');

            DB::rollBack();

            return redirect()->back()->withInput()->with('error', __('settings.messages.save_error', ['error' => $e->getMessage()]));
        }
    }
}
