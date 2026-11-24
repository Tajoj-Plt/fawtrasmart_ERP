<?php

/**
 * ملف اختبار سريع للتحقق من صحة التطبيق
 *
 * لاستخدام هذا الملف:
 * 1. ضعه في مجلد routes أو public
 * 2. أضفه إلى routes:
 *    Route::get('/test-sales-reports', 'TestController@testSalesReports');
 * 3. قم بزيارة المسار في المتصفح
 */

namespace Tests\SalesReports;

use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesReportsTest
{
    public static function testDataAvailability()
    {
        $results = [];

        // 1. التحقق من وجود بيانات المستخدمين
        $employees = User::where('role', 'employee')->get();
        $results['employees_count'] = $employees->count();
        $results['employees_exist'] = $employees->count() > 0 ? '✅' : '❌';

        // 2. التحقق من وجود العملاء
        $clients = Client::all();
        $results['clients_count'] = $clients->count();
        $results['clients_exist'] = $clients->count() > 0 ? '✅' : '❌';

        // 3. التحقق من وجود الفروع
        $branches = Branch::all();
        $results['branches_count'] = $branches->count();
        $results['branches_exist'] = $branches->count() > 0 ? '✅' : '❌';

        // 4. التحقق من وجود تصنيفات العملاء
        $categories = CategoriesClient::all();
        $results['categories_count'] = $categories->count();
        $results['categories_exist'] = $categories->count() > 0 ? '✅' : '❌';

        // 5. التحقق من وجود قيود محاسبية
        $journalEntries = JournalEntry::all();
        $results['journal_entries_count'] = $journalEntries->count();
        $results['journal_entries_exist'] = $journalEntries->count() > 0 ? '✅' : '❌';

        // 6. التحقق من وجود قيود محاسبية من نوع SI, SR, DN, CN
        $salesEntries = JournalEntry::whereIn('reference_type', ['SI', 'SR', 'DN', 'CN'])->get();
        $results['sales_entries_count'] = $salesEntries->count();
        $results['sales_entries_exist'] = $salesEntries->count() > 0 ? '✅' : '❌';

        return $results;
    }

    public static function testRelationships()
    {
        $results = [];

        try {
            // اختبار أول قيد محاسبي للمبيعات
            $entry = JournalEntry::whereIn('reference_type', ['SI', 'SR', 'DN', 'CN'])->first();

            if ($entry) {
                // التحقق من العلاقات
                try {
                    $employee = $entry->createdByEmployee;
                    $results['employee_relationship'] = $employee ? '✅' : '❌';
                } catch (\Exception $e) {
                    $results['employee_relationship'] = '❌ ' . $e->getMessage();
                }

                try {
                    $client = $entry->client;
                    $results['client_relationship'] = $client ? '✅' : '❌';
                } catch (\Exception $e) {
                    $results['client_relationship'] = '❌ ' . $e->getMessage();
                }

                try {
                    $branch = $entry->branch;
                    $results['branch_relationship'] = $branch ? '✅' : '❌';
                } catch (\Exception $e) {
                    $results['branch_relationship'] = '❌ ' . $e->getMessage();
                }

                try {
                    $details = $entry->details;
                    $results['details_relationship'] = $details ? '✅' : '❌';
                    $results['details_count'] = $details ? $details->count() : 0;
                } catch (\Exception $e) {
                    $results['details_relationship'] = '❌ ' . $e->getMessage();
                }

                try {
                    $payments = $entry->payments;
                    $results['payments_relationship'] = $payments ? '✅' : '❌';
                    $results['payments_count'] = $payments ? $payments->count() : 0;
                } catch (\Exception $e) {
                    $results['payments_relationship'] = '❌ ' . $e->getMessage();
                }
            } else {
                $results['no_sales_entries'] = '⚠️ لا توجد قيود محاسبية من نوع مبيعات';
            }
        } catch (\Exception $e) {
            $results['error'] = '❌ ' . $e->getMessage();
        }

        return $results;
    }

    public static function testControllerMethods()
    {
        $results = [];

        try {
            $controller = new \Modules\Sales\Http\Controllers\SalesReportsController();
            $results['controller_exists'] = '✅';

            // التحقق من وجود الدوال
            $results['byEmployee_method'] = method_exists($controller, 'byEmployee') ? '✅' : '❌';
            $results['byCustomer_method'] = method_exists($controller, 'byCustomer') ? '✅' : '❌';
            $results['byEmployeeAjax_method'] = method_exists($controller, 'byEmployeeAjax') ? '✅' : '❌';

        } catch (\Exception $e) {
            $results['controller_error'] = '❌ ' . $e->getMessage();
        }

        return $results;
    }

    public static function testRoutes()
    {
        $results = [];
        $routes = \Route::getRoutes();

        $byEmployeeRoute = $routes->getByName('salesReports.byEmployee');
        $results['byEmployee_route'] = $byEmployeeRoute ? '✅' : '❌';

        $byCustomerRoute = $routes->getByName('salesReports.byCustomer');
        $results['byCustomer_route'] = $byCustomerRoute ? '✅' : '❌';

        return $results;
    }

    public static function testBladeTemplates()
    {
        $results = [];

        $byEmployeePath = resource_path('views/Sales/sals_report/salesRport/Sales_By_Employee.blade.php');
        // المسار الفعلي قد يختلف بناءً على بنية المشروع
        $byEmployeePath = base_path('Modules/Sales/resources/views/sals_report/salesRport/Sales_By_Employee.blade.php');
        $results['Sales_By_Employee.blade.php'] = file_exists($byEmployeePath) ? '✅' : '❌';

        $byCustomerPath = base_path('Modules/Sales/resources/views/sals_report/salesRport/Sales_By_Customer.blade.php');
        $results['Sales_By_Customer.blade.php'] = file_exists($byCustomerPath) ? '✅' : '❌';

        return $results;
    }

    public static function testCssFile()
    {
        $results = [];

        $cssPath = public_path('css/account-reports.css');
        $results['account-reports.css'] = file_exists($cssPath) ? '✅' : '❌';

        return $results;
    }

    public static function generateReport()
    {
        $report = [
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'data_availability' => self::testDataAvailability(),
            'relationships' => self::testRelationships(),
            'controller_methods' => self::testControllerMethods(),
            'routes' => self::testRoutes(),
            'blade_templates' => self::testBladeTemplates(),
            'css_file' => self::testCssFile(),
        ];

        return $report;
    }

    public static function htmlReport()
    {
        $report = self::generateReport();

        $html = '<html dir="rtl">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>اختبار تقارير المبيعات</title>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5; }';
        $html .= '.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }';
        $html .= 'h1 { color: #333; }';
        $html .= 'h2 { color: #666; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
        $html .= 'th, td { padding: 10px; text-align: right; border-bottom: 1px solid #ddd; }';
        $html .= 'th { background-color: #007bff; color: white; }';
        $html .= 'tr:hover { background-color: #f9f9f9; }';
        $html .= '.status { font-weight: bold; }';
        $html .= '.success { color: green; }';
        $html .= '.error { color: red; }';
        $html .= '.warning { color: orange; }';
        $html .= '.timestamp { color: #999; font-size: 12px; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="container">';
        $html .= '<h1>✅ اختبار شامل لنظام تقارير المبيعات</h1>';
        $html .= '<p class="timestamp">' . $report['timestamp'] . '</p>';

        // البيانات المتاحة
        $html .= '<h2>1️⃣ توفر البيانات</h2>';
        $html .= '<table>';
        $html .= '<tr><th>العنصر</th><th>النتيجة</th></tr>';
        foreach ($report['data_availability'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : 'error';
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        // العلاقات
        $html .= '<h2>2️⃣ اختبار العلاقات (Relationships)</h2>';
        $html .= '<table>';
        $html .= '<tr><th>العلاقة</th><th>النتيجة</th></tr>';
        foreach ($report['relationships'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : (strpos($value, '⚠️') !== false ? 'warning' : 'error');
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        // دوال الـ Controller
        $html .= '<h2>3️⃣ دوال الـ Controller</h2>';
        $html .= '<table>';
        $html .= '<tr><th>الدالة</th><th>النتيجة</th></tr>';
        foreach ($report['controller_methods'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : 'error';
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        // المسارات
        $html .= '<h2>4️⃣ اختبار المسارات (Routes)</h2>';
        $html .= '<table>';
        $html .= '<tr><th>المسار</th><th>النتيجة</th></tr>';
        foreach ($report['routes'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : 'error';
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        // قوالب Blade
        $html .= '<h2>5️⃣ قوالب Blade</h2>';
        $html .= '<table>';
        $html .= '<tr><th>الملف</th><th>النتيجة</th></tr>';
        foreach ($report['blade_templates'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : 'error';
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        // ملفات CSS
        $html .= '<h2>6️⃣ ملفات التصميم</h2>';
        $html .= '<table>';
        $html .= '<tr><th>الملف</th><th>النتيجة</th></tr>';
        foreach ($report['css_file'] as $key => $value) {
            $class = strpos($value, '✅') !== false ? 'success' : 'error';
            $html .= '<tr><td>' . $key . '</td><td class="status ' . $class . '">' . $value . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }
}

// إذا تم استدعاء هذا الملف مباشرة
if (php_sapi_name() === 'cli') {
    echo "نتائج الاختبار:\n";
    echo json_encode(SalesReportsTest::generateReport(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
