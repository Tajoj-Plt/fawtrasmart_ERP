<?php

namespace Modules\Client\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Helpers\WeekHelper;
use App\Http\Controllers\Controller;
use App\Imports\ClientsImport;
use App\Mail\TestMail;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Models\CreditLimit;
use App\Models\DoubtfulDebt;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\Expense;
use App\Models\GeneralClientSetting;
use App\Models\HiddenClient;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Location;
use App\Models\Log as ModelsLog;
use App\Models\Neighborhood;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\Receipt;
use App\Models\Region_groub;
use App\Models\Revenue;
use App\Models\SerialSetting;
use App\Models\Statuses;
use App\Models\Target;
use App\Models\User;
use App\Models\Visit;
use App\Support\OperationLog;
use App\Traits\AssignedEmployeeAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Client\Http\Requests\ClientRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

// PDF Generation

class ClientControllerbackup extends Controller
{
    use AssignedEmployeeAccess;

    public function __construct()
    {
        // صلاحيات العملاء
        $this->middleware('permission:clients_view_own_clients', ['only' => ['index', 'getAjaxResponse', 'getClientMap']]);
        $this->middleware('permission:clients_add_client', ['only' => ['create', 'store']]);
        $this->middleware('permission:clients_edit_delete_all_clients', ['only' => ['edit_question', 'update', 'updateStatus', 'updateOpeningBalance', 'updateCreditLimit']]);
        $this->middleware('permission:clients_delete', ['only' => ['destroy']]);
        $this->middleware('permission:clients_view_all_activity_logs|clients_view_own_activity_log', ['only' => ['show_activity_log']]);
    }

    // ... existing methods ...

    public function getClientDetails($id)
    {
        $client = Client::with(['status', 'neighborhood', 'branch'])->find($id);
        if (! $client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        return response()->json(['client' => $client]);
    }

    // الحصول على آخر ملاحظة للعميل للتحقق من تغيير الموظف
    public function getLastNote($id)
    {
        $lastNote = ClientRelation::where('client_id', $id)
            ->with(['clientEmployee.employee'])
            ->latest()
            ->first();

        if (! $lastNote) {
            return response()->json(['lastNote' => null]);
        }

        $employeeName = 'غير معروف';
        if ($lastNote->clientEmployee && $lastNote->clientEmployee->employee) {
            $employee = $lastNote->clientEmployee->employee;
            $employeeName = $employee->nickname ?? ($employee->first_name.' '.$employee->middle_name);
        }

        return response()->json([
            'lastNote' => [
                'id' => $lastNote->id,
                'client_employee_id' => $lastNote->client_employee_id,
                'employee_name' => $employeeName,
            ],
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

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات موظف العميل بنجاح',
                'data' => $clientEmployee,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'updateClientEmployee');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function generateNotesPdf($id)
    {
        // تحميل العميل مع الملاحظات
        $client = Client::with(['employee'])->findOrFail($id);
        $ClientRelations = ClientRelation::where('client_id', $id)
            ->with(['employee', 'clientEmployee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // إنشاء PDF جديد باستخدام TCPDF
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تعيين معلومات الوثيقة
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('ملاحظات العميل - '.$client->trade_name);

        // تعيين الهوامش
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // تعطيل رأس وتذييل الصفحة
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // تعيين الاتجاه من اليمين إلى اليسار
        $pdf->setRTL(true);

        // إضافة صفحة جديدة
        $pdf->AddPage();

        // تعيين الخط العربي
        $pdf->SetFont('aealarabiya', '', 12);

        // إنشاء محتوى HTML
        $html = view('client::partials.notes_pdf', compact('client', 'ClientRelations'))->render();

        // إضافة المحتوى للـ PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // إخراج الملف
        $filename = 'ملاحظات_العميل_'.$client->code.'.pdf';

        return $pdf->Output($filename, 'I');
    }

    public function getClientInvoices($id)
    {
        $user = auth()->user();

        // التحقق من أن المستخدم يمكنه الوصول للعميل
        if (! $this->canAccessClient($user, $id)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية للوصول لفواتير هذا العميل',
            ], 403);
        }

        // جلب الفواتير مع تطبيق الفلترة حسب الصلاحيات
        $query = Invoice::where('client_id', $id);
        $invoices = $this->filterInvoicesByUserPermissions($query, $user)
            ->with(['status', 'payments'])
            ->orderBy('date', 'desc')
            ->paginate(10);

        return response()->json(['invoices' => $invoices]);
    }

    // public function getClientNotes($id)
    // {

    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     return response()->json(['notes' => $notes]);
    // }

    public function searchClients(Request $request)
    {
        $term = $request->term;

        $clients = Client::where(function ($query) use ($term) {
            $query
                ->where('trade_name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%');
        })
            ->with(['status', 'neighborhood'])
            ->limit(10)
            ->get();

        return response()->json(['clients' => $clients]);
    }

    public function needsModification(Request $request)
    {
        $employeeId = $request->employee_id;
        $year = $request->year;
        $week = $request->week;

        $clients = Client::whereHas('employeeClientVisit', function ($query) use ($employeeId, $year, $week) {
            $query->where('employee_id', $employeeId)->where('year', $year)->where('week_number', $week)->where('needs_modification', true);
        })
            ->with(['neighborhood', 'employeeClientVisit'])
            ->get();

        return response()->json(['data' => $clients]);
    }

    private function applyFilters($baseQuery, $request)
    {
        if ($request->filled('client')) {
            $baseQuery->where('id', $request->client);
        }

        if ($request->filled('name')) {
            $baseQuery->where('trade_name', 'like', '%'.$request->name.'%');
        }

        if ($request->filled('status')) {
            // إذا كانت القيمة 'no_status' فنبحث عن العملاء الذين ليس لهم حالة محددة
            if ($request->status === 'no_status') {
                $baseQuery->whereNull('status_id');
            } else {
                $baseQuery->where('status_id', $request->status);
            }
        }

        if ($request->filled('region')) {
            $baseQuery->whereHas('Neighborhoodname.Region', function ($q) use ($request) {
                $q->where('id', $request->region);
            });
        }

        if ($request->filled('neighborhood')) {
            $baseQuery->whereHas('Neighborhoodname', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->neighborhood.'%')
                    ->orWhere('id', $request->neighborhood);
            });
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $baseQuery->whereBetween('created_at', [
                $request->date_from.' 00:00:00',
                $request->date_to.' 23:59:59',
            ]);
        } elseif ($request->filled('date_from')) {
            $baseQuery->where('created_at', '>=', $request->date_from.' 00:00:00');
        } elseif ($request->filled('date_to')) {
            $baseQuery->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->filled('categories')) {
            $baseQuery->where('category_id', $request->categories);
        }

        if ($request->filled('user')) {
            $baseQuery->where('created_by', $request->user);
        }

        if ($request->filled('type')) {
            $baseQuery->where('type', $request->type);
        }

        if ($request->filled('employee')) {
            $baseQuery->where('employee_id', $request->employee);
        }

        // فلترة حسب آخر فاتورة
        if ($request->filled('last_invoice_period')) {
            $dates = $this->getPeriodDates($request->last_invoice_period);

            $baseQuery->whereHas('invoices', function ($q) use ($dates) {
                $q->whereBetween('invoice_date', [$dates['start'], $dates['end']]);
            });
        }

        // فلترة حسب آخر دفعة (من خلال الفواتير)
        if ($request->filled('last_payment_period')) {
            $dates = $this->getPeriodDates($request->last_payment_period);

            $baseQuery->whereHas('invoices.payments', function ($q) use ($dates) {
                $q->whereBetween('created_at', [$dates['start'], $dates['end']])
                    ->where('type', 'client payments');
            });
        }

        // فلترة حسب آخر نشاط (دفعة أو سند قبض)
        // فلترة حسب آخر نشاط فعلي (فاتورة أو دفعة أو سند قبض)
        if ($request->filled('last_activity_period')) {
            $dates = $this->getPeriodDates($request->last_activity_period);

            $baseQuery->whereIn('id', function ($subQuery) use ($dates) {
                $subQuery->select('client_id')
                    ->from(function ($inner) {
                        $inner->select('invoices.client_id', 'invoices.invoice_date as date')
                            ->from('invoices')

                            // الدفعات من جدول payments_process
                            ->unionAll(
                                \DB::table('payments_process')
                                    ->join('invoices', 'payments_process.invoice_id', '=', 'invoices.id')
                                    ->select('invoices.client_id', 'payments_process.created_at as date')
                            )

                            // السندات
                            ->unionAll(
                                \DB::table('accounts')
                                    ->join('receipts', 'accounts.id', '=', 'receipts.account_id')
                                    ->select('accounts.client_id', 'receipts.created_at as date')
                            );
                    }, 'activities')
                    ->whereRaw('activities.date = (
                SELECT MAX(a2.date)
                FROM (
                    SELECT invoices.client_id, invoices.invoice_date as date
                    FROM invoices

                    UNION ALL
                    SELECT invoices.client_id, payments_process.created_at as date
                    FROM payments_process
                    JOIN invoices ON payments_process.invoice_id = invoices.id

                    UNION ALL
                    SELECT accounts.client_id, receipts.created_at as date
                    FROM accounts
                    JOIN receipts ON accounts.id = receipts.account_id
                ) as a2
                WHERE a2.client_id = activities.client_id
            )')
                    ->whereBetween('activities.date', [$dates['start'], $dates['end']]);
            });
        }

    }

    // دالة مساعدة لحساب الفترات
    private function getPeriodDates($period)
    {
        $now = now();

        switch ($period) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];

            case 'week':
                return [
                    'start' => $now->copy()->subDays(7)->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay(),
                ];

            case 'two_weeks':
                return [
                    'start' => $now->copy()->subDays(14)->startOfDay(),
                    'end' => $now->copy()->subDays(8)->endOfDay(),
                ];

            case 'month':
                return [
                    'start' => $now->copy()->subDays(30)->startOfDay(),
                    'end' => $now->copy()->subDays(15)->endOfDay(),
                ];

            case 'three_months':
                return [
                    'start' => $now->copy()->subDays(90)->startOfDay(),
                    'end' => $now->copy()->subDays(31)->endOfDay(),
                ];

            case 'six_months':
                return [
                    'start' => $now->copy()->subDays(180)->startOfDay(),
                    'end' => $now->copy()->subDays(91)->endOfDay(),
                ];

            case 'year':
                return [
                    'start' => $now->copy()->subDays(365)->startOfDay(),
                    'end' => $now->copy()->subDays(181)->endOfDay(),
                ];

            case 'more_than_year':
                return [
                    'start' => $now->copy()->subYears(100),
                    'end' => $now->copy()->subDays(365)->endOfDay(),
                ];

            default:
                return [
                    'start' => $now->subYears(100),
                    'end' => $now,
                ];
        }
    }

    public function currentRoute(Request $request)
    {
        $employeeId = $request->employee_id;
        $year = $request->year;
        $week = $request->week;

        $clients = Client::whereHas('employeeClientVisit', function ($query) use ($employeeId, $year, $week) {
            $query->where('employee_id', $employeeId)->where('year', $year)->where('week_number', $week);
        })
            ->with(['neighborhood', 'employeeClientVisit'])
            ->get();

        return response()->json(['data' => $clients]);
    }

    private function calculateClientData($clients, $currentYear)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');
        $monthlyTarget = 648;

        // جلب البيانات المالية
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')->pluck('reference_number')->toArray();
        $excludedInvoiceIds = array_unique(array_merge($returnedInvoiceIds, Invoice::where('type', 'returned')->pluck('id')->toArray()));

        $invoices = Invoice::whereIn('client_id', $clientIds)->where('type', 'normal')->whereNotIn('id', $excludedInvoiceIds)->get();

        $invoiceIdsByClient = $invoices->groupBy('client_id')->map->pluck('id');

        $payments = PaymentsProcess::whereIn('invoice_id', $invoices->pluck('id'))->whereYear('created_at', $currentYear)->get()->groupBy('invoice_id');

        $receipts = Receipt::with('account')->whereHas('account', fn ($q) => $q->whereIn('client_id', $clientIds))->whereYear('created_at', $currentYear)->get()->groupBy(fn ($receipt) => $receipt->account->client_id);

        $months = [
            'يناير' => 1,
            'فبراير' => 2,
            'مارس' => 3,
            'أبريل' => 4,
            'مايو' => 5,
            'يونيو' => 6,
            'يوليو' => 7,
            'أغسطس' => 8,
            'سبتمبر' => 9,
            'أكتوبر' => 10,
            'نوفمبر' => 11,
            'ديسمبر' => 12,
        ];

        $getClassification = function ($percentage, $collected = 0) {
            if ($collected == 0) {
                return ['group' => 'D', 'class' => 'secondary'];
            }
            if ($percentage > 100) {
                return ['group' => 'A++', 'class' => 'primary'];
            }
            if ($percentage >= 60) {
                return ['group' => 'A', 'class' => 'success'];
            }
            if ($percentage >= 30) {
                return ['group' => 'B', 'class' => 'warning'];
            }

            return ['group' => 'C', 'class' => 'danger'];
        };

        return $clients
            ->map(function ($client) use ($invoiceIdsByClient, $payments, $receipts, $months, $monthlyTarget, $getClassification, $currentYear) {
                $invoiceIds = $invoiceIdsByClient[$client->id] ?? collect();

                $clientData = [
                    'id' => $client->id,
                    'monthly' => [],
                    'invoices_count' => $invoiceIds->count(),
                    'payments_count' => $invoiceIds->sum(fn ($id) => isset($payments[$id]) ? $payments[$id]->count() : 0),
                    'receipts_count' => isset($receipts[$client->id]) ? $receipts[$client->id]->count() : 0,
                    'total_collected' => 0,
                ];

                $totalYearlyCollected = 0;

                foreach ($months as $monthName => $monthNumber) {
                    $paymentsTotal = 0;
                    if ($invoiceIds->isNotEmpty()) {
                        foreach ($invoiceIds as $invoiceId) {
                            if (isset($payments[$invoiceId])) {
                                $paymentsTotal += $payments[$invoiceId]->filter(fn ($payment) => Carbon::parse($payment->created_at)->year == $currentYear && Carbon::parse($payment->created_at)->month == $monthNumber)->sum('amount');
                            }
                        }
                    }

                    $receiptsTotal = isset($receipts[$client->id]) ? $receipts[$client->id]->filter(fn ($receipt) => Carbon::parse($receipt->created_at)->year == $currentYear && Carbon::parse($receipt->created_at)->month == $monthNumber)->sum('amount') : 0;

                    $monthlyCollected = $paymentsTotal + $receiptsTotal;
                    $totalYearlyCollected += $monthlyCollected;

                    $percentage = $monthlyTarget > 0 ? round(($monthlyCollected / $monthlyTarget) * 100, 2) : 0;
                    $classification = $getClassification($percentage, $monthlyCollected);

                    $clientData['monthly'][$monthName] = [
                        'collected' => $monthlyCollected,
                        'payments_total' => $paymentsTotal,
                        'receipts_total' => $receiptsTotal,
                        'target' => $monthlyTarget,
                        'percentage' => $percentage,
                        'group' => $classification['group'],
                        'group_class' => $classification['class'],
                        'month_number' => $monthNumber,
                    ];
                }

                $clientData['total_collected'] = $totalYearlyCollected;

                return $clientData;
            })
            ->keyBy('id');
    }

    private function getClientDueBalances($clients)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');

        return Account::whereIn('client_id', $clientIds)->selectRaw('client_id, SUM(balance) as total_due')->groupBy('client_id')->pluck('total_due', 'client_id');
    }

    private function getClientTotalSales($clients)
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id');
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')->pluck('reference_number')->toArray();
        $excludedInvoiceIds = array_unique(array_merge($returnedInvoiceIds, Invoice::where('type', 'returned')->pluck('id')->toArray()));

        return Invoice::whereIn('client_id', $clientIds)->where('type', 'normal')->whereNotIn('id', $excludedInvoiceIds)->groupBy('client_id')->selectRaw('client_id, SUM(grand_total) as total_sales')->pluck('total_sales', 'client_id');
    }

    private function calculateClientDistances($clients, $user)
    {
        $clientDistances = [];
        $userLocation = Location::where('employee_id', $user->id)->latest()->first();

        // إذا لم يكن هناك موقع للمستخدم، إرجاع بيانات فارغة
        if (! $userLocation) {
            foreach ($clients as $client) {
                $clientDistances[$client->id] = [
                    'distance' => null,
                    'message' => 'موقعك غير معروف',
                    'within_range' => false,
                ];
            }

            return $clientDistances;
        }

        foreach ($clients as $client) {
            // البحث عن آخر موقع للعميل
            $clientLocation = $client->locations()->latest()->first();

            if ($clientLocation && $clientLocation->latitude && $clientLocation->longitude) {
                $distanceKm = $this->calculateDistance(
                    $userLocation->latitude,
                    $userLocation->longitude,
                    $clientLocation->latitude,
                    $clientLocation->longitude
                );

                $clientDistances[$client->id] = [
                    'distance' => $distanceKm,
                    'message' => $distanceKm !== null ? 'تم الحساب بنجاح' : 'خطأ في حساب المسافة',
                    'within_range' => $distanceKm !== null && $distanceKm <= 0.3,
                    'distance_text' => $distanceKm !== null ?
                        ($distanceKm < 1 ?
                            round($distanceKm * 1000).' متر' :
                            round($distanceKm, 2).' كم'
                        ) : 'غير معروف',
                ];
            } else {
                $clientDistances[$client->id] = [
                    'distance' => null,
                    'message' => 'موقع العميل غير معروف',
                    'within_range' => false,
                    'distance_text' => 'غير معروف',
                ];
            }
        }

        return $clientDistances;
    }

    public function getMapData(Request $request)
    {
        $user = auth()->user();

        // تأكد من استخدام نفس العلاقة المستخدمة في index
        $baseQuery = Client::with([
            'employee',
            'status_client:id,name,color',  // استخدام نفس العلاقة
            'locations',
            'neighborhood.region',
            'branch:id,name',
        ]);

        $noClients = false;

        $currentDate = now();
        $weekInfo = WeekHelper::getWeekAndYear($currentDate);
        $currentWeek = $weekInfo['week'];
        $currentYear = $weekInfo['year'];

        $englishDays = WeekHelper::getEnglishDays();
        $currentDayOfWeek = $currentDate->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;
        $currentDayNameEn = $englishDays[$adjustedDayOfWeek];

        // تطبيق منطق الصلاحيات الجديد
        if (! hasPermission('clients_view_all_clients')) {
            // التحقق من صلاحية عرض العملاء حسب خط السير اليومي
            if (hasPermission('clients_view_by_daily_itinerary')) {
                // جلب العملاء المجدولين لليوم الحالي في الأسبوع والسنة الحالية (نفس طريقة الموظف الأصلية)
                $clientVisits = EmployeeClientVisit::where('employee_id', $user->id)
                    ->where('day_of_week', $currentDayNameEn)
                    ->where('year', $currentYear)
                    ->where('week_number', $currentWeek)
                    ->pluck('client_id');

                if ($clientVisits->isNotEmpty()) {
                    $baseQuery->whereIn('id', $clientVisits);
                } else {
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            }
            // التحقق من صلاحية عرض العملاء الخاصة به فقط (العملاء المسندين للموظف)
            elseif (hasPermission('clients_view_own_clients')) {
                // جلب العملاء المسندين للموظف من جدول client_employee
                $assignedClientIds = ClientEmployee::where('employee_id', $user->employee_id)
                    ->pluck('client_id');

                if ($assignedClientIds->isNotEmpty()) {
                    $baseQuery->whereIn('id', $assignedClientIds);
                } else {
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            }
            // للموظف الذي ليس لديه صلاحيات خاصة - استخدام خط السير اليومي
            elseif ($user->role === 'employee') {
                $clientVisits = EmployeeClientVisit::where('employee_id', $user->id)
                    ->where('day_of_week', $currentDayNameEn)
                    ->where('year', $currentYear)
                    ->where('week_number', $currentWeek)
                    ->pluck('client_id');

                if ($clientVisits->isNotEmpty()) {
                    $baseQuery->whereIn('id', $clientVisits);
                } else {
                    $noClients = true;
                }
            }
            // للمستخدم المرتبط بفرع معين
            elseif ($user->branch_id) {
                $mainBranchName = Branch::where('is_main', true)->value('name');
                $currentBranchName = Branch::find($user->branch_id)->name;

                if ($currentBranchName !== $mainBranchName) {
                    $baseQuery->where('branch_id', $user->branch_id);
                }
            }
        }

        // **الإضافة المهمة: تطبيق فلتر العملاء المخفيين قبل تطبيق الفلاتر الأخرى**
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        // تطبيق الفلاتر
        $this->applyFilters($baseQuery, $request);

        if ($noClients) {
            return response()->json(['clients' => []]);
        }

        // جلب العملاء مع المواقع فقط
        $clients = $baseQuery
            ->whereHas('locations', function ($query) {
                $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', '')
                    ->where('longitude', '!=', '')
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);
            })
            ->get();

        // تنسيق البيانات للخريطة
        $clientsForMap = [];

        foreach ($clients as $client) {
            $location = $client->locations;

            if ($location && $location->latitude && $location->longitude && $location->latitude != 0 && $location->longitude != 0) {
                // استخدام نفس المنطق من الصفحة الرئيسية
                $statusColor = '#4361ee'; // اللون الافتراضي
                $statusName = 'غير محدد';

                // التحقق من وجود status_client (نفس ما في الصفحة الرئيسية)
                if ($client->status_client) {
                    $statusColor = $client->status_client->color ?: '#4361ee';
                    $statusName = $client->status_client->name ?: 'غير محدد';
                }

                $clientsForMap[] = [
                    'id' => $client->id,
                    'lat' => (float) $location->latitude,
                    'lng' => (float) $location->longitude,
                    'trade_name' => $client->trade_name ?? 'غير محدد',
                    'code' => $client->code ?? 'غير محدد',
                    'phone' => $client->phone ?? 'غير متوفر',
                    'address' => $location->address ?? 'غير محدد',
                    'status' => $statusName,
                    'statusColor' => $statusColor, // تأكد من إرسال اللون الصحيح
                    'branch' => optional($client->branch)->name ?? 'غير محدد',
                    'employee' => optional($client->employee)->name ?? 'غير محدد',
                ];
            }
        }

        return response()->json([
            'clients' => $clientsForMap,
            'total' => count($clientsForMap),
            'debug' => [
                'user_role' => $user->role,
                'total_clients_found' => $clients->count(),
                'clients_with_location' => count($clientsForMap),
                'hidden_clients_count' => count($hiddenClientIds),
                'hidden_client_ids' => $hiddenClientIds,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $baseQuery = Client::with(['employee', 'status:id,name,color', 'locations', 'neighborhood.region', 'branch:id,name', 'account', 'categoriesClient']);
        $noClients = false;

        $currentDate = now();
        $weekInfo = WeekHelper::getWeekAndYear($currentDate);
        $currentWeek = $weekInfo['week'];
        $currentYear = $weekInfo['year'];

        $arabicDays = WeekHelper::getArabicDays();
        $englishDays = WeekHelper::getEnglishDays();

        $currentDayOfWeek = $currentDate->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;

        $currentDayName = $arabicDays[$adjustedDayOfWeek];
        $currentDayNameEn = $englishDays[$adjustedDayOfWeek];
        $GeneralClientSettings = GeneralClientSetting::all();

        // إنشاء query منفصل لجلب جميع العملاء للـ select (بدون فلاتر الجدول الزمني)
        $allClientsQuery = Client::select(['id', 'trade_name', 'code']);

        // تطبيق صلاحيات المستخدم الأساسية فقط على allClients (بدون الفلاتر الزمنية)
        if (! hasPermission('clients_view_all_clients')) {
            // التحقق من صلاحية عرض العملاء حسب خط السير اليومي (للـ select سيتم جلب كل عملاء خط السير)
            if (hasPermission('clients_view_by_daily_itinerary')) {
                // للموظف: جلب جميع العملاء المخصصين له (بغض النظر عن اليوم)
                $allEmployeeClients = EmployeeClientVisit::where('employee_id', $user->id)
                    ->where('day_of_week', $currentDayNameEn)
                    ->where('year', $currentYear)
                    ->where('week_number', $currentWeek)
                    ->pluck('client_id');

                if ($allEmployeeClients->isNotEmpty()) {
                    $allClientsQuery->whereIn('id', $allEmployeeClients);
                } else {
                    $allClientsQuery = $allClientsQuery->whereRaw('1 = 0');
                }
            }
            // التحقق من صلاحية عرض العملاء الخاصة به فقط (العملاء المسندين للموظف)
            elseif (hasPermission('clients_view_own_clients')) {
                // جلب العملاء المسندين للموظف من جدول client_employee
                $assignedClientIds = ClientEmployee::where('employee_id', $user->employee_id)
                    ->pluck('client_id');

                if ($assignedClientIds->isNotEmpty()) {
                    $allClientsQuery->whereIn('id', $assignedClientIds);
                } else {
                    $allClientsQuery = $allClientsQuery->whereRaw('1 = 0');
                }
            }
            // للموظف الذي ليس لديه صلاحيات خاصة
            elseif ($user->role === 'employee') {
                // للموظف: جلب جميع العملاء المخصصين له (بغض النظر عن اليوم)
                $allEmployeeClients = EmployeeClientVisit::where('employee_id', $user->id)
                    ->distinct('client_id')
                    ->pluck('client_id');

                if ($allEmployeeClients->isNotEmpty()) {
                    $allClientsQuery->whereIn('id', $allEmployeeClients);
                } else {
                    $allClientsQuery = $allClientsQuery->whereRaw('1 = 0');
                }
            }
            // للمستخدم المرتبط بفرع معين

            elseif ($user->branch_id) {
                $mainBranchName = Branch::where('is_main', true)->value('name');
                $currentBranchName = Branch::find($user->branch_id)->name;

                if ($currentBranchName !== $mainBranchName) {
                    $allClientsQuery->where('branch_id', $user->branch_id);
                }
            }
        }

        // جلب جميع العملاء للـ select بناءً على صلاحيات المستخدم
        $allClients = $allClientsQuery->orderBy('trade_name')->get();

        // تطبيق منطق الصلاحيات الجديد
        if (! hasPermission('clients_view_all_clients')) {
            // التحقق من صلاحية عرض العملاء حسب خط السير اليومي
            if (hasPermission('clients_view_by_daily_itinerary')) {
                // جلب العملاء المجدولين لليوم الحالي في الأسبوع والسنة الحالية (نفس طريقة الموظف الأصلية)
                $clientVisits = EmployeeClientVisit::where('employee_id', $user->id)
                    ->where('day_of_week', $currentDayNameEn)
                    ->where('year', $currentYear)
                    ->where('week_number', $currentWeek)
                    ->pluck('client_id');

                if ($clientVisits->isNotEmpty()) {
                    $baseQuery->whereIn('id', $clientVisits);
                } else {
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            }
            // التحقق من صلاحية عرض العملاء الخاصة به فقط (العملاء المسندين للموظف)
            elseif (hasPermission('clients_view_own_clients')) {
                // جلب العملاء المسندين للموظف من جدول client_employee
                $assignedClientIds = ClientEmployee::where('employee_id', $user->employee_id)
                    ->pluck('client_id');

                if ($assignedClientIds->isNotEmpty()) {
                    $baseQuery->whereIn('id', $assignedClientIds);
                } else {
                    $noClients = true;
                    $baseQuery->whereRaw('1 = 0');
                }
            } else {
                // لا توجد صلاحية للعرض
                $noClients = true;
                $baseQuery->whereRaw('1 = 0');
            }
        }

        // تطبيق فلترة الموظف على البيانات المعروضة (بعد فلترة الصلاحيات) - فقط إذا لم تكن هناك صلاحيات أعلى
        if ($user->role === 'employee' && ! hasPermission('clients_view_all_clients') && ! hasPermission('clients_view_by_daily_itinerary') && ! hasPermission('clients_view_own_clients')) {
            $clientVisits = EmployeeClientVisit::where('employee_id', $user->id)->where('day_of_week', $currentDayNameEn)->where('year', $currentYear)->where('week_number', $currentWeek)->pluck('client_id');

            if ($clientVisits->isNotEmpty()) {
                $baseQuery->whereIn('id', $clientVisits);
            } else {
                $noClients = true;
            }
        } elseif ($user->branch_id && ! hasPermission('clients_view_all_clients') && ! hasPermission('clients_view_by_daily_itinerary') && ! hasPermission('clients_view_own_clients')) {
            $mainBranchName = Branch::where('is_main', true)->value('name');
            $currentBranchName = Branch::find($user->branch_id)->name;

            if ($currentBranchName !== $mainBranchName) {
                $baseQuery->where('branch_id', $user->branch_id);
            }
        }

        // جلب العملاء المخفيين للمستخدم الحالي من قاعدة البيانات
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);

        // استبعاد العملاء المخفيين من الـ query
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        // تطبيق الفلاتر على البيانات المعروضة فقط
        $this->applyFilters($baseQuery, $request);

        // إذا كان طلب AJAX، نرجع البيانات فقط
        if ($request->ajax()) {
            return $this->getAjaxResponse($baseQuery, $request, $noClients, $user);
        }

        // للطلب العادي، نرجع البيانات الأساسية للعرض الأولي
        $perPage = (int) $request->get('perPage', 50);

        // إنشاء paginator فارغ إذا لم يكن هناك عملاء
        if ($noClients) {
            $clients = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $clients = $baseQuery->paginate($perPage)->appends($request->query());
        }

        // جلب بيانات مبسطة للخريطة فقط (مع الفلاتر المطبقة)
        $mapQuery = clone $baseQuery;
        $mapClients = $noClients
            ? collect()
            : $mapQuery
                ->select(['id', 'trade_name', 'code', 'phone', 'status_id', 'branch_id'])
                ->with(['status_client:id,name,color', 'locations:id,client_id,latitude,longitude', 'branch:id,name'])
                ->get();

        // حساب المسافات للعملاء المعروضين فقط
        $clientDistances = $this->calculateClientDistances($clients, $user);

        // حساب البيانات المالية للعملاء المعروضين فقط
        $clientsData = $this->calculateClientData($clients, $currentYear);
        $clientDueBalances = $this->getClientDueBalances($clients);

        $userBranch = $user->branch ?? null;

        if ($userBranch && $userBranch->is_main) {
            // ✅ إذا كان الفرع رئيسي → جلب كل المجموعات
            $regionGroups = Region_groub::all();
        } else {
            // ✅ إذا لم يكن رئيسي → جلب مجموعات الفرع نفسه فقط
            $regionGroups = Region_groub::where('branch_id', $userBranch->id ?? null)->get();
        }

        return view('client::index', [
            'clients' => $clients,
            'allClients' => $allClients,
            'mapClients' => $mapClients,
            'clientsData' => $clientsData,
            'clientDueBalances' => $clientDueBalances,
            'clientDistances' => $clientDistances,
            'Neighborhoods' => Neighborhood::all(),
            'GeneralClientSettings' => $GeneralClientSettings,
            'users' => User::all(),
            'categories' => CategoriesClient::all(),
            'employees' => Employee::all(),
            'creditLimit' => CreditLimit::first(),
            'statuses' => Statuses::all()->keyBy('id'),
            'Region_groups' => $regionGroups,
            'target' => Target::find(2)->value ?? 648,
            'monthlyTarget' => 648,
            'months' => ['يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4, 'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8, 'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12],
            'currentYear' => $currentYear,
            'currentDayName' => $currentDayName,
            'hiddenClients' => $hiddenClientIds,
        ]);
    }

    private function getAjaxResponse($baseQuery, $request, $noClients, $user)
    {
        $perPage = (int) $request->get('perPage', 50);
        $page = $request->get('page', 1);

        // إذا لم يكن هناك عملاء، إرجاع paginator فارغ
        if ($noClients) {
            $emptyPaginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json([
                'success' => true,
                'html' => view('client::partials.client_cards', [
                    'clients' => $emptyPaginator,
                    'clientsData' => [],
                    'clientDueBalances' => [],
                    'clientDistances' => [],
                    'statuses' => Statuses::all()->keyBy('id'),
                    'months' => [
                        'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4,
                        'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8,
                        'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
                    ],
                    'currentYear' => now()->year,
                    'clientTotalSales' => [],
                ])->render(),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'on_first_page' => true,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
            ]);
        }

        // جلب العملاء المخفيين للمستخدم الحالي من قاعدة البيانات
        $hiddenClientIds = HiddenClient::getHiddenClientsForUser($user->id);

        // استبعاد العملاء المخفيين من query قبل جلب البيانات
        if (! empty($hiddenClientIds)) {
            $baseQuery->whereNotIn('id', $hiddenClientIds);
        }

        $clients = $baseQuery->get();

        if ($clients->isEmpty()) {
            $emptyPaginator = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json([
                'success' => true,
                'html' => view('client::partials.client_cards', [
                    'clients' => $emptyPaginator,
                    'clientsData' => [],
                    'clientDueBalances' => [],
                    'clientDistances' => [],
                    'statuses' => Statuses::all()->keyBy('id'),
                    'months' => [
                        'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4,
                        'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8,
                        'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
                    ],
                    'currentYear' => now()->year,
                    'clientTotalSales' => [],
                ])->render(),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'on_first_page' => true,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
            ]);
        }

        // حساب المسافات
        $clientDistances = $this->calculateClientDistances($clients, $user);

        // ترتيب العملاء حسب المسافة
        $clients = $clients->sortBy(function ($client) use ($clientDistances) {
            $distance = $clientDistances[$client->id]['distance'] ?? null;

            return $distance === null ? PHP_INT_MAX : $distance;
        })->values();

        // إنشاء الترقيم
        $pagedClients = new LengthAwarePaginator(
            $clients->forPage($page, $perPage),
            $clients->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // حساب البيانات المالية للصفحة الحالية فقط
        $clientsData = $this->calculateClientData($pagedClients, now()->year);
        $clientDueBalances = $this->getClientDueBalances($pagedClients);

        return response()->json([
            'success' => true,
            'html' => view('client::partials.client_cards', [
                'clients' => $pagedClients,
                'clientsData' => $clientsData,
                'clientDueBalances' => $clientDueBalances,
                'clientDistances' => $clientDistances,
                'statuses' => Statuses::all()->keyBy('id'),
                'months' => [
                    'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4,
                    'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8,
                    'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
                ],
                'currentYear' => now()->year,
                'clientTotalSales' => $this->getClientTotalSales($pagedClients),
            ])->render(),
            'pagination' => [
                'current_page' => $pagedClients->currentPage(),
                'last_page' => $pagedClients->lastPage(),
                'has_more_pages' => $pagedClients->hasMorePages(),
                'on_first_page' => $pagedClients->onFirstPage(),
                'per_page' => $pagedClients->perPage(),
                'total' => $pagedClients->total(),
                'from' => $pagedClients->firstItem(),
                'to' => $pagedClients->lastItem(),
            ],
        ]);
    }

    public function hideFromMap(Request $request, $clientId)
    {
        try {
            $client = Client::findOrFail($clientId);
            $userId = auth()->id();

            // ✅ الخطوة 1: جلب آخر زيارة للعميل من نفس الموظف اليوم
            $lastVisit = Visit::where('employee_id', $userId)
                ->where('client_id', $clientId)
                ->whereDate('visit_date', now()->toDateString())
                ->latest()
                ->first();

            // ✅ التحقق من وجود زيارة
            if (! $lastVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكنك إخفاء العميل. يجب تسجيل زيارة أولاً.',
                ]);
            }

            // ✅ الخطوة 2: تسجيل الانصراف مباشرة (أو تحديثه إذا كان موجود)
            $lastVisit->update([
                'departure_time' => now(),

            ]);

            Log::info('تم تسجيل/تحديث الانصراف عند الإخفاء', [
                'visit_id' => $lastVisit->id,
                'employee_id' => $userId,
                'client_id' => $clientId,
                'departure_time' => now(),
            ]);

            // ✅ الخطوة 3: التحقق من عدم وجود إخفاء سابق لا يزال فعال
            $existingHidden = HiddenClient::where('user_id', $userId)
                ->where('client_id', $clientId)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingHidden) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل مخفي بالفعل.',
                ]);
            }

            // ✅ الخطوة 4: إنشاء سجل جديد للإخفاء
            HiddenClient::create([
                'user_id' => $userId,
                'client_id' => $clientId,
                'hidden_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            // حساب مدة الزيارة
            $visitDuration = $lastVisit->arrival_time->diffInMinutes(now());

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الانصراف وإخفاء العميل بنجاح لمدة 24 ساعة.',
                'client_id' => $clientId,
                'client_name' => $client->trade_name,
                'visit_id' => $lastVisit->id,
                'arrival_time' => $lastVisit->arrival_time->format('H:i'),
                'departure_time' => now()->format('H:i'),
                'visit_duration' => $visitDuration.' دقيقة',
                'expires_at' => now()->addHours(24)->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في إخفاء العميل: '.$e->getMessage(), [
                'client_id' => $clientId,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إخفاء العميل.',
            ], 500);
        }
    }

    public function storeVisit(Request $request, $clientId)
    {
        // التحقق من صحة البيانات المدخلة
        $validated = $request->validate([
            'current_latitude' => 'required|numeric|between:-90,90',
            'current_longitude' => 'required|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ], [
            'current_latitude.required' => 'لم يتم تحديد موقعك الحالي',
            'current_longitude.required' => 'لم يتم تحديد موقعك الحالي',
            'current_latitude.between' => 'إحداثيات الموقع غير صحيحة',
            'current_longitude.between' => 'إحداثيات الموقع غير صحيحة',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ]);

        // جلب بيانات العميل
        $client = Client::findOrFail($clientId);

        // جلب موقع العميل من جدول locations
        $clientLocation = Location::where('client_id', $client->id)
            ->latest()->first();

        // التحقق من وجود موقع للعميل
        if (! $clientLocation || is_null($clientLocation->latitude) || is_null($clientLocation->longitude)) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد موقع مسجل لهذا العميل. يرجى تحديث بيانات العميل أولاً.',
                ], 400);
            }

            return redirect()->back()
                ->with('error', 'لا يوجد موقع مسجل لهذا العميل. يرجى تحديث بيانات العميل أولاً.')
                ->withInput();
        }

        // حساب المسافة بين الموقع الحالي للموظف وموقع العميل
        $distance = $this->calculateDistance(
            $validated['current_latitude'],
            $validated['current_longitude'],
            $clientLocation->latitude,
            $clientLocation->longitude
        );

        // الحد الأقصى المسموح به (0.3 كيلومتر)
        $maxDistance = 0.3;

        // التحقق من أن المستخدم ضمن النطاق المسموح
        if ($distance > $maxDistance) {
            $errorMessage = sprintf(
                'أنت خارج نطاق موقع العميل. المسافة الحالية: %.2f كم. يجب أن تكون ضمن نطاق %.1f كم.',
                $distance,
                $maxDistance
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

            return redirect()->back()
                ->with('error', $errorMessage)
                ->with('warning', 'يرجى الاقتراب من موقع العميل وإعادة المحاولة.')
                ->withInput();
        }

        // التحقق من عدم وجود زيارة مفتوحة (بدون انصراف) لنفس العميل في نفس اليوم
        $existingVisit = Visit::where('employee_id', auth()->id())
            ->where('client_id', $client->id)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNull('departure_time')
            ->first();

        if ($existingVisit) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لديك زيارة مفتوحة بالفعل لهذا العميل اليوم. يرجى إنهاء الزيارة الحالية (الانصراف) قبل تسجيل زيارة جديدة.',
                ], 400);
            }

            return redirect()->back()
                ->with('warning', 'لديك زيارة مفتوحة بالفعل لهذا العميل اليوم.')
                ->with('info', 'يرجى إنهاء الزيارة الحالية (الانصراف) قبل تسجيل زيارة جديدة.');
        }

        // تسجيل الزيارة في قاعدة البيانات
        try {
            $visit = Visit::create([
                'employee_id' => auth()->id(),
                'client_id' => $client->id,
                'visit_date' => now(),
                'arrival_time' => now(),
                'employee_latitude' => $validated['current_latitude'],
                'employee_longitude' => $validated['current_longitude'],
                'client_latitude' => $clientLocation->latitude,
                'client_longitude' => $clientLocation->longitude,
                'distance' => $distance,
                'notes' => $validated['notes'] ?? null,
                'recording_method' => 'manual',
                'is_approved' => false,
            ]);

            // تسجيل في الـ Log
            Log::info('تم تسجيل زيارة جديدة', [
                'visit_id' => $visit->id,
                'employee_id' => auth()->id(),
                'client_id' => $client->id,
                'location_id' => $clientLocation->id,
                'distance' => $distance,
            ]);

            $successMessage = sprintf(
                'تم تسجيل الزيارة بنجاح! المسافة من موقع العميل: %.2f كم',
                $distance
            );

            // إذا كان الطلب AJAX، نرجع JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'visit_id' => $visit->id,
                    'redirect' => route('clients.index'),
                ], 200);
            }

            // النقل لصفحة الـ index بعد نجاح تسجيل الزيارة
            return redirect()->route('clients.index')
                ->with('success', $successMessage)
                ->with('info', 'يمكنك الآن إنهاء الزيارة وإضافة المزيد من الملاحظات.');

        } catch (\Exception $e) {
            Log::error('خطأ في تسجيل الزيارة: '.$e->getMessage(), [
                'employee_id' => auth()->id(),
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تسجيل الزيارة. يرجى المحاولة مرة أخرى.',
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تسجيل الزيارة. يرجى المحاولة مرة أخرى.')
                ->withInput();
        }
    }

    public function showInMap(Request $request, $clientId)
    {
        try {
            $client = Client::findOrFail($clientId);
            $userId = auth()->id();

            // حذف السجل من قاعدة البيانات
            HiddenClient::where('user_id', $userId)
                ->where('client_id', $clientId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم إظهار العميل بنجاح',
                'client_id' => $clientId,
                'client_name' => $client->trade_name,
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في إظهار العميل: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إظهار العميل',
            ], 500);
        }
    }

    public function getHiddenClients(Request $request)
    {
        try {
            $userId = auth()->id();

            // جلب العملاء المخفيين من قاعدة البيانات مع معلومات العميل
            $hiddenClients = HiddenClient::where('user_id', $userId)
                ->where('expires_at', '>', now())
                ->with('client:id,trade_name,code')
                ->get()
                ->map(function ($hidden) {
                    return [
                        'id' => $hidden->client_id,
                        'name' => $hidden->client->trade_name,
                        'code' => $hidden->client->code,
                        'hidden_at' => $hidden->hidden_at->format('Y-m-d H:i:s'),
                        'expires_at' => $hidden->expires_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'hidden_clients' => $hiddenClients,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'getHiddenClients');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب العملاء المخفيين',
            ], 500);
        }
    }

    // تعديل دالة getAjaxResponse لاستبعاد العملاء المخفيين من قاعدة البيانات
    public function updateCreditLimit(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'value' => 'required|numeric|min:0',
        ]);

        try {
            // تحديث أو إنشاء الحد الائتماني للعميل
            CreditLimit::updateOrCreateForClient(
                $request->client_id,
                $request->value
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحد الائتماني بنجاح!',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'updateCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث الحد الائتماني لجميع العملاء
     */
    /**
     * جلب قائمة العملاء بصيغة JSON لـ Select2
     */
    /**
     * جلب قائمة العملاء بصيغة JSON لـ Select2 مع Pagination
     */
    public function getClientsForSelect(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->input('search', '');
            $page = $request->input('page', 1);
            $perPage = 30;

            // إنشاء query للعملاء بناءً على صلاحيات المستخدم
            $query = Client::select(['id', 'trade_name', 'code']);

            // تطبيق الصلاحيات
            if (! hasPermission('clients_view_all_clients')) {
                if ($user->role === 'employee') {
                    // للموظف: جلب جميع العملاء المخصصين له
                    $allEmployeeClients = EmployeeClientVisit::where('employee_id', $user->id)
                        ->distinct('client_id')
                        ->pluck('client_id');

                    if ($allEmployeeClients->isNotEmpty()) {
                        $query->whereIn('id', $allEmployeeClients);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($user->branch_id) {
                    $mainBranchName = Branch::where('is_main', true)->value('name');
                    $currentBranchName = Branch::find($user->branch_id)->name;

                    if ($currentBranchName !== $mainBranchName) {
                        $query->where('branch_id', $user->branch_id);
                    }
                }
            }

            // البحث
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('trade_name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            }

            // جلب العملاء مع Pagination
            $clients = $query->orderBy('trade_name')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $clients->items(),
                'total' => $clients->total(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في getClientsForSelect: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function updateAllClientsCreditLimit(Request $request)
    {
        $request->validate([
            'value' => 'required|numeric|min:0',
            'exclude_clients' => 'nullable|array',
            'exclude_clients.*' => 'numeric|exists:clients,id',
        ]);

        try {
            $user = auth()->user();
            $excludeClientIds = $request->input('exclude_clients', []);

            // الحصول على قائمة العملاء بناءً على صلاحيات المستخدم
            $query = Client::query();

            if (! hasPermission('clients_view_all_clients')) {
                if ($user->role === 'employee') {
                    $query->where('employee_id', $user->id);
                } elseif ($user->branch_id) {
                    $query->where('branch_id', $user->branch_id);
                }
            }

            // استثن العملاء المختارين من قبل المستخدم
            if (! empty($excludeClientIds)) {
                $query->whereNotIn('id', $excludeClientIds);
            }

            // تحديث الحد الائتماني لجميع العملاء
            $clients = $query->pluck('id');

            foreach ($clients as $clientId) {
                CreditLimit::updateOrCreateForClient($clientId, $request->value);
            }

            $count = $clients->count();
            $excludedCount = count($excludeClientIds);

            $message = "تم تحديث الحد الائتماني لـ {$count} عميل بنجاح!";
            if ($excludedCount > 0) {
                $message .= "<br><small class='text-muted'>تم استثناء {$excludedCount} عميل من التحديث</small>";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count,
                'excluded_count' => $excludedCount,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'updateAllClientsCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الحصول على الحد الائتماني لعميل
     */
    public function getClientCreditLimit($clientId)
    {
        try {
            $creditLimit = CreditLimit::getClientCreditLimit($clientId);

            return response()->json([
                'success' => true,
                'creditLimit' => $creditLimit ? $creditLimit->value : 0,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'getClientCreditLimit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {
        $user = auth()->user(); // المستخدم الحالي

        $employees = User::where('role', 'employee')->get();
        $categories = CategoriesClient::all();
        $branches = Branch::all();
        $lastClient = Client::orderBy('code', 'desc')->first();
        $newCode = $lastClient ? $lastClient->code + 1 : 3000;

        $GeneralClientSettings = GeneralClientSetting::all();

        // إذا كان الجدول فارغًا، قم بإنشاء قيم افتراضية (مفعلة بالكامل)
        if ($GeneralClientSettings->isEmpty()) {
            $defaultSettings = [
                ['key' => 'image', 'name' => 'صورة', 'is_active' => true],
                ['key' => 'type', 'name' => 'النوع', 'is_active' => true],
                ['key' => 'birth_date', 'name' => 'تاريخ الميلاد', 'is_active' => true],
                ['key' => 'location', 'name' => 'الموقع على الخريطة', 'is_active' => true],
                ['key' => 'opening_balance', 'name' => 'الرصيد الافتتاحي', 'is_active' => true],
                ['key' => 'credit_limit', 'name' => 'الحد الائتماني', 'is_active' => true],
                ['key' => 'credit_duration', 'name' => 'المدة الائتمانية', 'is_active' => true],
                ['key' => 'national_id', 'name' => 'رقم الهوية الوطنية', 'is_active' => true],
                ['key' => 'addresses', 'name' => 'عناوين متعددة', 'is_active' => true],
                ['key' => 'link', 'name' => 'الرابط', 'is_active' => true],
            ];

            $GeneralClientSettings = collect($defaultSettings)->map(fn ($item) => (object) $item);
        }

        // 🔹 جلب المجموعات بناءً على نوع المستخدم أو نوع الفرع
        if ($user->role === 'employee') {
            // الموظف يرى فقط مجموعاته
            $Regions_groub = $user->regionGroups;

        } else {
            // إذا المستخدم إداري أو مدير
            // نتحقق من أن الفرع غير رئيسي
            $branch = $user->branch ?? null; // نفترض أن عند المستخدم علاقة branch

            if ($branch && ! $branch->is_main) {
                // عرض مجموعات الفرع فقط
                $Regions_groub = Region_groub::where('branch_id', $branch->id)->get();
            } else {
                // عرض كل المجموعات إذا الفرع رئيسي
                $Regions_groub = Region_groub::all();
            }
        }

        return view('client::create', compact(
            'employees',
            'branches',
            'newCode',
            'categories',
            'GeneralClientSettings',
            'Regions_groub'
        ));
    }

    private function getNeighborhoodFromGoogle($latitude, $longitude)
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY'); // احصل على API Key من .env
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=$apiKey&language=ar";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (! empty($data['results'])) {
            foreach ($data['results'][0]['address_components'] as $component) {
                if (in_array('sublocality', $component['types']) || in_array('neighborhood', $component['types'])) {
                    return $component['long_name']; // اسم الحي
                }
            }
        }

        return 'لم يتم العثور على الحي';
    }

    /**
     * Generate PDF of all clients
     */
    public function generateClientsPdf(Request $request)
    {
        $type = $request->get('type', 'all'); // الافتراضي: تصدير الكل

        // إنشاء الاستعلام الأساسي
        $query = Client::with([
            'status_client',
            'branch',
            'neighborhood.region',
            'categoriesClient',
            'invoices',
        ]);

        // ✅ تطبيق الفلاتر باستخدام الدالة المساعدة
        $this->applyFilters($query, $request);

        // ✅ تحديد نوع التصدير
        if ($type === 'filtered') {
            $clients = $query->orderBy('trade_name')->get();
        } elseif ($type === 'page') {
            $page = $request->get('page', 1);
            $perPage = 100; // نفس عدد العملاء في الصفحة
            $clients = $query->paginate($perPage, ['*'], 'page', $page)->items();
        } else {
            $clients = $query->orderBy('trade_name')->get();
        }

        // ✅ إنشاء PDF باستخدام TCPDF
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('قائمة العملاء');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);
        $pdf->AddPage();
        $pdf->SetFont('aealarabiya', '', 10);

        // ✅ إنشاء المحتوى HTML
        $html = '<h2 style="text-align:center">قائمة العملاء</h2>';
        $html .= '<p style="text-align:center; font-size:12px;">
        نوع التقرير: '.($type === 'filtered' ? 'حسب الفلترة' : ($type === 'page' ? 'الصفحة الحالية' : 'الكل')).'
    </p>';

        $html .= '<table border="1" cellpadding="5" style="width: 100%">
        <thead>
            <tr style="background-color: #f8f9fa; font-weight: bold; text-align: center;">
                <th>الكود</th>
                <th>الاسم التجاري</th>
                <th>الهاتف</th>
                <th>الحالة</th>
                <th>الفرع</th>
                <th>الفئة</th>
                <th>الرصيد</th>
                <th>الحد الائتماني</th>
                <th>تاريخ التسجيل</th>
                <th>آخر فاتورة</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($clients as $client) {
            // ✅ آخر فاتورة
            $lastInvoice = $client->invoices->sortByDesc('invoice_date')->first();

            // ✅ الرصيد الإجمالي من جدول accounts
            $due = Account::ledger()->where('client_id', $client->id)->sum('balance');

            $html .= '<tr>
            <td>'.e($client->code ?? '-').'</td>
            <td>'.e($client->trade_name ?? '-').'</td>
            <td>'.e($client->phone ?? '-').'</td>
            <td>'.e(optional($client->status_client)->name ?? '-').'</td>
            <td>'.e(optional($client->branch)->name ?? '-').'</td>
            <td>'.e(optional($client->categoriesClient)->name ?? '-').'</td>
            <td>'.number_format($due, 2).'</td>
            <td>'.e($client->credit_limit ?? '-').'</td>
            <td>'.optional($client->created_at)->format('Y-m-d').'</td>
            <td>'.optional($lastInvoice)->invoice_date.'</td>
        </tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p style="text-align:center; margin-top:10px;">
        تم إنشاء التقرير بتاريخ: '.now()->format('Y-m-d H:i').'
    </p>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'تقرير_العملاء_'.now()->format('Y-m-d').'.pdf';

        return $pdf->Output($filename, 'D'); // D = تحميل مباشر
    }

    public function store(ClientRequest $request)
    {
        $data_request = $request->except('_token');
        $rules = [
            'region_id' => ['required'],
        ];

        $messages = [
            'region_id.required' => 'حقل المجموعة مطلوب.',
        ];

        $request->validate($rules, $messages);

        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
        } else {
            return redirect()->back()->with('error', 'الإحداثيات غير موجودة');
        }

        $client = new Client;
        $client->status_id = 3;

        // الحصول على الرقم الحالي لقسم العملاء من جدول serial_settings
        $serialSetting = SerialSetting::where('section', 'customer')->first();
        $currentNumber = $serialSetting ? $serialSetting->current_number : 1;

        // تعيين id للعميل الجديد
        $client->code = $currentNumber;
        $client->fill($data_request);

        // معالجة الصورة
        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments');
            if ($file->isValid()) {
                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('assets/uploads/'), $filename);
                $client->attachments = $filename;
            }
        }

        // حفظ العميل أولاً
        $client->save();

        // حفظ جهات الاتصال الأساسية
        $mainContact = [
            'first_name' => $client->trade_name,
            'phone' => $client->phone,
            'mobile' => $client->mobile,
            'email' => $client->email,
            'is_primary' => true,
        ];

        $client->contacts()->create($mainContact);

        // حفظ الموظفين المرتبطين وجمع معرفاتهم
        $employeeIds = [];

        if (auth()->user()->role === 'manager') {
            if ($request->has('employee_client_id')) {
                foreach ($request->employee_client_id as $employee_id) {
                    $client_employee = new ClientEmployee;
                    $client_employee->client_id = $client->id;
                    $client_employee->employee_id = $employee_id;
                    $client_employee->save();
                    $employeeIds[] = $employee_id;
                }
            }
        } elseif (auth()->user()->role === 'employee') {
            $employeeId = auth()->user()->employee_id;
            ClientEmployee::create([
                'client_id' => $client->id,
                ($employeeId = auth()->id()), // رقم المستخدم من جدول users
            ]);
            $employeeIds[] = $employeeId;
        }

        // تسجيل الإحداثيات
        $client->locations()->create([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $neighborhoodName = $this->getNeighborhoodFromGoogle($latitude, $longitude);
        $Neighborhood = new Neighborhood;
        $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
        $Neighborhood->region_id = $request->region_id;
        $Neighborhood->client_id = $client->id;
        $Neighborhood->save();

        // إنشاء مستخدم جديد إذا تم توفير البريد الإلكتروني
        $password = Str::random(10);
        $full_name = $client->trade_name.' '.$client->first_name.' '.$client->last_name;
        if ($request->email != null) {
            User::create([
                'name' => $full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => 'client',
                'client_id' => $client->id,
                'password' => Hash::make($password),
            ]);
        }

        // تسجيل إشعار نظام جديد
        ModelsLog::create([
            'type' => 'client',
            'type_id' => $client->id,
            'type_log' => 'log',
            'description' => 'تم إضافة عميل **'.$client->trade_name.'**',
            'created_by' => auth()->id(),
        ]);

        // زيادة الرقم الحالي بمقدار 1
        if ($serialSetting) {
            $serialSetting->update(['current_number' => $currentNumber + 1]);
        }

        // إنشاء حساب فرعي باستخدام trade_name
        $customers = Account::where('id', SystemAccountIds::CLIENTS_ACCOUNT)->first();
        if ($customers) {
            $customerAccount = new Account;
            $customerAccount->name = $client->trade_name;
            $customerAccount->client_id = $client->id;

            // تعيين نوع الحساب المالي ليكون مطابقًا للحساب الأب
            $customerAccount->account_type = $customers->account_type;

            $lastChild = Account::where('parent_id', $customers->id)->orderBy('code', 'desc')->first();
            $newCode = $lastChild ? $this->generateNextCode($lastChild->code) : $customers->code.'1';

            while (Account::where('code', $newCode)->exists()) {
                $newCode = $this->generateNextCode($newCode);
            }

            $customerAccount->code = $newCode;
            $customerAccount->balance_type = 'debit';
            $customerAccount->parent_id = $customers->id;
            $customerAccount->is_active = false;

            // تعيين الرصيد الافتتاحي ونوعه في الحساب فقط
            $customerAccount->opening_balance = $request->opening_balance ?? 0;
            $customerAccount->opening_balance_type = $customers->balance_type; // مطابق لنوع الرصيد الأب
            $customerAccount->is_active = true;

            $customerAccount->save();

            // إنشاء قيد الرصيد الافتتاحي إذا كان هناك رصيد
            if ($customerAccount->opening_balance != 0) {
                $customerAccount->createOrUpdateOpeningBalanceJournalEntry();
            }
        }

        // حفظ جهات الاتصال الإضافية
        if ($request->has('contacts') && is_array($request->contacts)) {
            foreach ($request->contacts as $contact) {
                $client->contacts()->create($contact);
            }
        }

        // إضافة العميل إلى خط سير جميع الموظفين المسؤولين عنه
        if (! empty($employeeIds)) {
            $now = now();
            $currentDate = $now->copy();
            $currentYear = $now->year;

            // نحسب أول سبت في السنة
            $firstSaturday = Carbon::createFromDate($currentYear, 1, 1)->startOfWeek(Carbon::SATURDAY);

            // إذا أول يوم في السنة كان سبت، نستخدمه
            if (Carbon::createFromDate($currentYear, 1, 1)->dayOfWeek === Carbon::SATURDAY) {
                $firstSaturday = Carbon::createFromDate($currentYear, 1, 1);
            }

            // نحسب الفرق بالأسابيع
            $daysDiff = $firstSaturday->diffInDays($currentDate);
            $currentWeek = (int) floor($daysDiff / 7) + 1;

            // اليوم الحالي بعد تعديل الترتيب بحيث السبت هو 0
            $adjustedDayOfWeek = ($now->dayOfWeek + 1) % 7;
            $englishDays = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            $dayOfWeek = strtolower($englishDays[$adjustedDayOfWeek]);

            foreach ($employeeIds as $employeeId) {
                // التأكد من أن employeeId صحيح قبل الإضافة
                if (! empty($employeeId)) {
                    EmployeeClientVisit::updateOrCreate(
                        [
                            'employee_id' => $employeeId, // user_id
                            'client_id' => $client->id,
                            'day_of_week' => $dayOfWeek,
                            'year' => $currentYear,
                            'week_number' => $currentWeek,
                            'status' => 'active',
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            }
        }

        // التحقق من كون الطلب AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => '✨ تم إضافة العميل بنجاح!',
                'client_id' => $client->id,
                'redirect_url' => route('clients.show', $client->id),
            ]);
        }

        return redirect()->route('clients.show', $client->id)->with('success', '✨ تم إضافة العميل بنجاح!');
    }

    protected function getCorrectWeekNumber(Carbon $date)
    {
        // تاريخ السبت الماضي كبداية للأسبوع
        $weekStart = $date->copy()->startOfWeek(Carbon::SATURDAY);

        // تاريخ 1 يناير من نفس السنة
        $janFirst = Carbon::create($weekStart->year, 1, 1);

        // إذا كان 1 يناير ليس سبتاً، نبدأ العد من السبت التالي
        if ($janFirst->dayOfWeek != Carbon::SATURDAY) {
            $janFirst->next(Carbon::SATURDAY);
        }

        // حساب الفرق بالأسابيع + 1
        $weekNumber = $janFirst->diffInWeeks($weekStart) + 1;

        // التأكد من أن رقم الأسبوع لا يتجاوز 52
        return min($weekNumber, 52);
    }

    public function registerVisit($id)
    {
        $client = Client::find($id);

        return view('client::register_visit', compact('client'));
    }

    /**
     * الحصول على رقم الترتيب التالي للزيارة
     */

    /**
     * الحصول على رقم الترتيب التالي للزيارة
     */

    /**
     * إنهاء الزيارة وتسجيل وقت المغادرة
     */
    public function completeVisit(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);

        // التحقق من أن الزيارة تخص الموظف الحالي
        if ($visit->employee_id !== auth()->id()) {
            return redirect()->back()
                ->with('error', 'لا يمكنك إنهاء زيارة لا تخصك.');
        }

        // التحقق من أن الزيارة لم تنتهِ بالفعل
        if ($visit->status === 'completed') {
            return redirect()->back()
                ->with('warning', 'هذه الزيارة منتهية بالفعل.');
        }

        // تحديث حالة الزيارة
        $visit->update([
            'departure_time' => now(),
            'status' => 'completed',
            'notes' => $request->input('final_notes', $visit->notes),
        ]);

        return redirect()->route('visits.index')
            ->with('success', 'تم إنهاء الزيارة بنجاح.');
    }

    public function send_email($id)
    {
        $employee = User::where('client_id', $id)->first();

        if (! $employee || empty($employee->email)) {
            return redirect()->back()->with('error', 'العميل لا يمتلك بريدًا إلكترونيًا للدخول.');
        }

        // توليد كلمة مرور جديدة عشوائية
        $newPassword = $this->generateRandomPassword();

        // تحديث كلمة المرور في قاعدة البيانات بعد تشفيرها
        $employee->password = Hash::make($newPassword);
        $employee->save();

        // إعداد بيانات البريد
        $details = [
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => $newPassword, // إرسال كلمة المرور الجديدة مباشرة
        ];

        // إرسال البريد
        Mail::to($employee->email)->send(new TestMail($details));
        ModelsLog::create([
            'type' => 'hr_log',
            'type_id' => $employee->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم ارسال بيانات الدخول **'.$employee->name.'**',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        // return back()->with('message', 'تم إرسال البريد بنجاح!');
        return redirect()
            ->back()
            ->with(['success' => 'تم  ارسال البريد بنجاح .']);
    }

    private function generateRandomPassword($length = 10)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }

    protected function generateUniqueAccountCode($parentId, $parentCode)
    {
        $lastChild = Account::where('parent_id', $parentId)->orderBy('code', 'desc')->first();

        $baseCode = $lastChild ? (int) $lastChild->code + 1 : $parentCode.'001';

        $counter = 1;
        $newCode = $baseCode;

        while (Account::where('code', $newCode)->exists()) {
            $newCode = $baseCode.'_'.$counter;
            $counter++;

            if ($counter > 100) {
                throw new \RuntimeException('فشل توليد كود فريد');
            }
        }

        return $newCode;
    }

    // إضافة هذه الدالة في نفس وحدة التحكم
    private function generateNextCode(string $lastChildCode): string
    {
        // استخراج الرقم الأخير من الكود
        $lastNumber = intval(substr($lastChildCode, -1));
        // زيادة الرقم الأخير بمقدار 1
        $newNumber = $lastNumber + 1;

        // إعادة بناء الكود مع الرقم الجديد
        return substr($lastChildCode, 0, -1).$newNumber;
    }

    public function update(ClientRequest $request, $id)
    {
        $rules = [
            'region_id' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ];

        $messages = [
            'region_id.required' => 'حقل المجموعة مطلوب.',
            'latitude.required' => 'العميل ليس لديه موقع مسجل الرجاء تحديد الموقع على الخريطة',
            'longitude.required' => 'العميل ليس لديه موقع مسجل الرجاء تحديد الموقع على الخريطة',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'يرجى التحقق من البيانات المدخلة',
                ],
                422,
            );
        }

        // بدء المعاملة لضمان سلامة البيانات
        DB::beginTransaction();

        try {
            $data_request = $request->except('_token', 'contacts');
            $client = Client::findOrFail($id);
            $oldData = $client->getOriginal();

            $latitude = $request->latitude ?? $client->latitude;
            $longitude = $request->longitude ?? $client->longitude;

            $data_request = $request->except('_token', 'contacts', 'latitude', 'longitude');

            // حذف الموظفين السابقين فقط إذا كان المستخدم مدير
            if (auth()->user()->role === 'manager') {
                ClientEmployee::where('client_id', $client->id)->delete();

                if ($request->has('employee_client_id')) {
                    foreach ($request->employee_client_id as $employee_id) {
                        ClientEmployee::create([
                            'client_id' => $client->id,
                            'employee_id' => $employee_id,
                        ]);
                    }
                }
            } elseif (auth()->user()->role === 'employee') {
                $employee_id = auth()->user()->employee_id;

                // التحقق إذا هو أصلاً مسؤول
                $alreadyExists = ClientEmployee::where('client_id', $client->id)->where('employee_id', $employee_id)->exists();

                if (! $alreadyExists) {
                    ClientEmployee::create([
                        'client_id' => $client->id,
                        'employee_id' => $employee_id,
                    ]);
                }
            }

            // 1. معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    // حذف الملف القديم إن وجد
                    if ($client->attachments) {
                        $oldFilePath = public_path('assets/uploads/').$client->attachments;
                        if (File::exists($oldFilePath)) {
                            File::delete($oldFilePath);
                        }
                    }

                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $data_request['attachments'] = $filename;
                }
            }

            // 2. تحديث بيانات العميل الأساسية
            $client->update($data_request);

            // تحديث اسم الحساب ليطابق الاسم التجاري للعميل
            $clientAccount = Account::ledger()->where('client_id', $client->id)->first();
            if ($clientAccount) {
                $clientAccount->update(['name' => $client->trade_name]);
            }

            // 3. معالجة الإحداثيات - الطريقة المؤكدة
            $client->locations()->delete(); // حذف جميع المواقع القديمة

            $client->locations()->create([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'client_id' => $client->id,
            ]);

            $neighborhoodName = $this->getNeighborhoodFromGoogle($request->latitude, $request->longitude);

            // البحث عن الحي الحالي للعميل
            $Neighborhood = Neighborhood::where('client_id', $client->id)->first();

            if ($Neighborhood) {
                // إذا كان لديه حي، قم بتحديثه
                $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
                $Neighborhood->region_id = $request->region_id;
                $Neighborhood->save();
            } else {
                // إذا لم يكن لديه حي، أضف حيًا جديدًا
                $Neighborhood = new Neighborhood;
                $Neighborhood->name = $neighborhoodName ?? 'غير محدد';
                $Neighborhood->region_id = $request->region_id;
                $Neighborhood->client_id = $client->id;
                $Neighborhood->save();
            }

            // 4. تحديث بيانات المستخدم
            if ($request->email) {
                $full_name = implode(' ', array_filter([$client->trade_name, $client->first_name, $client->last_name]));

                $userData = [
                    'name' => $full_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                ];

                $user = User::where('client_id', $client->id)->first();

                if ($user) {
                    $user->update($userData);
                } else {
                    $userData['password'] = Hash::make(Str::random(10));
                    $userData['role'] = 'client';
                    $userData['client_id'] = $client->id;
                    User::create($userData);
                }
            }

            // 6. معالجة جهات الاتصال
            if ($request->has('contacts')) {
                $existingContacts = $client->contacts->keyBy('id');
                $newContacts = collect($request->contacts);

                // الحذف
                $contactsToDelete = $existingContacts->diffKeys($newContacts->whereNotNull('id')->keyBy('id'));
                $client->contacts()->whereIn('id', $contactsToDelete->keys())->delete();

                // التحديث والإضافة
                foreach ($request->contacts as $contact) {
                    if (isset($contact['id']) && $existingContacts->has($contact['id'])) {
                        $existingContacts[$contact['id']]->update($contact);
                    } else {
                        $client->contacts()->create($contact);
                    }
                }
            }

            // 7. تسجيل العملية في السجل
            ModelsLog::create([
                'type' => 'client',
                'type_id' => $client->id,
                'type_log' => 'update',
                'description' => 'تم تحديث بيانات العميل: '.$client->trade_name,
                'created_by' => auth()->id(),
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($client->getAttributes()),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✨ تم تحديث بيانات العميل بنجاح!',
                'redirect_url' => route('clients.index'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'update');

            DB::rollBack();

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء التحديث: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function edit_question($id)
    {
        $client = Client::findOrFail($id);
        $employees = Employee::all();
        $branches = Branch::all();
        $location = Location::where('client_id', $id)->first();

        $user = auth()->user();

        // ✅ تحديد الفرع الخاص بالمستخدم الحالي
        $userBranch = $user->branch ?? null;

        if ($userBranch && $userBranch->is_main) {
            // ✅ إذا كان الفرع رئيسي → جلب كل المجموعات
            $Regions_groub = Region_groub::all();
        } else {
            // ✅ إذا لم يكن رئيسي → جلب مجموعات الفرع نفسه فقط
            $Regions_groub = Region_groub::where('branch_id', $userBranch->id ?? null)->get();
        }

        // ✅ الإعدادات العامة
        $GeneralClientSettings = GeneralClientSetting::all();
        if ($GeneralClientSettings->isEmpty()) {
            $defaultSettings = [
                ['key' => 'image', 'name' => 'صورة', 'is_active' => true],
                ['key' => 'type', 'name' => 'النوع', 'is_active' => true],
                ['key' => 'birth_date', 'name' => 'تاريخ الميلاد', 'is_active' => true],
                ['key' => 'location', 'name' => 'الموقع على الخريطة', 'is_active' => true],
                ['key' => 'opening_balance', 'name' => 'الرصيد الافتتاحي', 'is_active' => true],
                ['key' => 'credit_limit', 'name' => 'الحد الائتماني', 'is_active' => true],
                ['key' => 'credit_duration', 'name' => 'المدة الائتمانية', 'is_active' => true],
                ['key' => 'national_id', 'name' => 'رقم الهوية الوطنية', 'is_active' => true],
                ['key' => 'addresses', 'name' => 'عناوين متعددة', 'is_active' => true],
                ['key' => 'link', 'name' => 'الرابط', 'is_active' => true],
            ];

            $GeneralClientSettings = collect($defaultSettings)->map(function ($item) {
                return (object) $item;
            });
        }

        $categories = CategoriesClient::all();

        return view('client::edit', compact(
            'client',
            'branches',
            'employees',
            'categories',
            'Regions_groub',
            'location',
            'GeneralClientSettings'
        ));
    }

    public function destroy($id)
    {
        $client = Client::findOrFail($id);

        // التحقق من وجود فواتير مرتبطة بالعميل
        if ($client->invoices()->exists()) {
            return redirect()->back()->with('error', 'لا يمكن حذف العميل لأنه يحتوي على فواتير مرتبطة.');
        }

        // حذف المدفوعات المرتبطة
        if ($client->payments()->exists()) {
            $client->payments()->delete();
        }

        // حذف إشعارات الائتمان المرتبطة
        if ($client->creditNotifications()->exists()) {
            $client->creditNotifications()->delete();
        }

        // حذف مدخلات المجلة المرتبطة
        if ($client->journalEntries()->exists()) {
            $client->journalEntries()->delete();
        }

        // حذف المرفقات إذا وجدت
        if ($client->attachments) {
            $attachments = explode(',', $client->attachments);
            foreach ($attachments as $attachment) {
                $path = public_path('uploads/clients/'.trim($attachment));
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        // حذف العميل
        $client->delete();

        return redirect()->back()->with('success', 'تم حذف العميل وجميع البيانات المرتبطة به بنجاح');
    }

    public function show($id)
    {
        // تحميل العميل المحدد مع جميع العلاقات الضرورية (مع استبعاد الفواتير الreturnedة)
        $client = Client::with([
            'invoices' => function ($query) {
                $query->where('type', '!=', 'returned')
                    ->whereNotIn('id', function ($subQuery) {
                        $subQuery->select('reference_number')
                            ->from('invoices')
                            ->whereNotNull('reference_number');
                    })
                    ->orderBy('created_at', 'desc');
            },
            'invoices.payments',
            'appointments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'employee',
            'account',
            'payments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'appointmentNotes' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'visits.employee' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // تحميل البيانات الإضافية
        // $installment = Installment::with('invoice.client')->get();
        $employees = Employee::all();
        $account = Account::all();
        $statuses = Statuses::all();

        // تحميل الحجوزات والعضويات
        $bookings = Booking::where('client_id', $id)->get();
        // تحميل الفواتير (مع التصفية الإضافية للتأكد)
        // تجهيز الفواتير - جلب الكل بدون pagination
        $invoices = $client->invoices()
            ->where('type', '!=', 'returned')
            ->whereNull('reference_number')
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز المواعيد - جلب الكل بدون pagination
        $appointments = $client->appointments()
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز المدفوعات - جلب الكل بدون pagination
        $payments = PaymentsProcess::whereHas('invoice', function ($query) use ($client) {
            $query->where('client_id', $client->id);
        })
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز الزيارات - جلب الكل بدون pagination
        $visits = Visit::where('client_id', $id)
            ->with('employee')
            ->orderBy('created_at', 'desc')
            ->get();

        // تجهيز الملاحظات - مع pagination
        $ClientRelations = ClientRelation::where('client_id', $id)
            ->with(['employee', 'clientEmployee'])
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        if (request()->ajax()) {
            return response()->json([
                'html' => view('client::partials.tabs.notes-list', compact('ClientRelations', 'client'))->render(),
                'pagination' => $ClientRelations->links('client::partials.client-notes-pagination')->toHtml(),
            ]);
        }

        $invoice_due = $invoices->sum('due_value');
        $due = Account::ledger()->where('client_id', $id)->sum('balance');

        // تحميل الملاحظات
        $appointmentNotes = $client->appointmentNotes;

        // تحميل الفئات والعلاقات الأخرى
        $categories = CategoriesClient::all();

        // إنشاء كود جديد للعميل (إن وجد)
        do {
            $lastClient = Client::orderBy('code', 'desc')->first();
            $newCode = $lastClient ? $lastClient->code + 1 : 1;
        } while (Client::where('code', $newCode)->exists());

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        $account = Account::ledger()->where('client_id', $id)->first();

        if (! $account) {
            return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل.');
        }

        $accountId = $account->id;
        // جلب بيانات الخزينة
        $treasury = $this->getTreasury($accountId);
        $branches = $this->getBranches();

        // جلب العمليات المالية
        $transactions = $this->getTransactions($accountId);
        $transfers = $this->getTransfers($accountId);
        $expenses = $this->getExpenses($accountId);
        $revenues = $this->getRevenues($accountId);

        // معالجة العمليات وحساب الرصيد
        $allOperations = $this->processOperations($transactions, $transfers, $expenses, $revenues, $treasury);

        // ترتيب العمليات حسب التاريخ
        usort($allOperations, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // تقسيم العمليات إلى صفحات
        $operationsPaginator = $this->paginateOperations($allOperations);

        // جلب الحد الائتماني للعميل
        $creditLimit = CreditLimit::getClientCreditLimit($id);

        // جلب إعدادات العميل العامة
        $GeneralClientSettings = GeneralClientSetting::where('is_active', true)->get();

        // إرسال البيانات إلى الواجهة
        return view('client::show', compact(
            'client', 'treasury', 'account', 'operationsPaginator', 'branches',
            'ClientRelations', 'visits', 'due', 'invoice_due', 'statuses',
            'account', 'employees', 'bookings',
            'invoices', 'payments', 'appointmentNotes', 'account_setting',
            'creditLimit', 'GeneralClientSettings'
        ));
    }

    public function paginate(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $type = $request->input('type', 'invoices');
        $perPage = $request->input('per_page', 10);
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        switch ($type) {
            case 'invoices':
                $items = $client->invoices()
                    ->where('type', '!=', 'returned')
                    ->whereNull('reference_number')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.invoices-table', [
                    'client' => $client,
                    'invoices' => $items,
                    'currencySymbol' => $account_setting->currency ?? 'SAR',
                ])->render();

            case 'payments':
                $items = $client->payments()
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.payments-table', [
                    'client' => $client,
                    'payments' => $items,
                    'currencySymbol' => $account_setting->currency ?? 'SAR',
                ])->render();

            case 'appointments':
                $items = $client->appointments()
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.appointments-table', [
                    'client' => $client,
                    'appointments' => $items,
                ])->render();

            case 'visits':
                $items = Visit::where('client_id', $id)
                    ->with('employee')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.visits-table', [
                    'client' => $client,
                    'visits' => $items,
                ])->render();

            case 'notes':
                $items = ClientRelation::where('client_id', $id)
                    ->with('employee')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                return view('client::partials.show.notes-table', [
                    'client' => $client,
                    'ClientRelations' => $items,
                ])->render();

            default:
                return response()->json(['error' => 'نوع غير صالح'], 400);
        }
    }

    /**
     * Generate PDF of client notes
     */
    public function exportPdf(Request $request)
    {
        $type = $request->get('type', 'all'); // الافتراضي تصدير الكل

        // بناء الاستعلام الأساسي
        $query = Client::with(['status_client', 'branch', 'neighborhood.region', 'categoriesClient', 'account', 'balance']);

        if ($type === 'filtered') {
            // تطبيق نفس فلاتر الصفحة (مثلاً حسب الحالة أو الفرع)
            // هنا افترض أن الفلاتر تأتي من الـ session أو الـ request
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->filled('status_id')) {
                $query->where('status_id', $request->status_id);
            }

            $clients = $query->get();

        } elseif ($type === 'page') {
            // جلب الصفحة الحالية فقط (حسب pagination)
            $page = $request->get('page', 1);
            $perPage = 10; // نفس عدد العناصر في pagination
            $clients = $query->paginate($perPage, ['*'], 'page', $page)->items();

        } else {
            // تصدير الكل
            $clients = $query->orderBy('trade_name')->get();
        }

        // إنشاء PDF
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('تقرير العملاء');

        $pdf->SetMargins(10, 10, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setRTL(true);
        $pdf->AddPage();
        $pdf->SetFont('aealarabiya', '', 11);

        // إنشاء المحتوى
        $html = view('client::partials.clients_pdf', compact('clients', 'type'))->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('clients_report.pdf', 'I');
    }

    public function exportAllClients()
    {
        // جلب جميع العملاء مع العلاقات الأساسية المهمة للتصدير
        $clients = Client::with([
            'branch',
            'categoriesClient',
            'status_client',
        ])->get();

        // تجهيز البيانات بصيغة منظمة للتصدير
        $data = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'code' => $client->code ?? '-',
                'trade_name' => $client->trade_name ?? '-',
                'first_name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
                'phone' => $client->phone ?? '-',
                'category' => $client->categoriesClient->name ?? $client->category ?? '-',
                'branch' => $client->branch->name ?? '-',
                'status' => $client->status_client->name ?? 'نشط',
                'distance' => $client->locations->map_url ?? '-',
                'created_at' => optional($client->created_at)->format('Y-m-d'),
                'last_invoice' => optional($client->invoices()->latest('invoice_date')->first())->invoice_date ?? '-',

                'balance' => number_format($client->Balance(), 2),
                'credit_limit' => $client->credit_limit ?? '-',
                'credit_period' => $client->credit_period ?? '-',
                'address' => $client->formatted_address ?? '-',
            ];
        });

        return response()->json($data);
    }

    public function updateStatus(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $client->notes = $request->notes; // تحديث الملاحظات بالحالة الجديدة
        $client->save();

        return response()->json(['success' => true]);
    }

    public function contact()
    {
        $clients = Client::all();

        return view('client::contacts.contact_mang', compact('clients'));
    }

    public function contacts(Request $request)
    {
        $query = Client::query()->with(['employee', 'status']);

        // البحث الأساسي (يشمل جميع الحقول المهمة)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('trade_name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('trade_name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('status', function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        // البحث المتقدم (حسب الحقول المحددة)
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.$request->input('phone').'%');
        }

        if ($request->filled('mobile')) {
            $query->where('mobile', 'like', '%'.$request->input('mobile').'%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%'.$request->input('email').'%');
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->input('city').'%');
        }

        if ($request->filled('region')) {
            $query->where('region', 'like', '%'.$request->input('region').'%');
        }

        $clients = $query->paginate(25)->withQueryString();

        $employees = Employee::all(); // لاستخدامها في dropdown الموظفين
        $statuses = Statuses::all(); // لاستخدامها في dropdown الحالات

        return view('client::contacts.contact_mang', compact('clients', 'employees', 'statuses'));
    }

    public function show_contant($id)
    {
        $client = Client::with(['appointments.notes', 'appointments.client'])->findOrFail($id);
        $notes = AppointmentNote::with(['appointment', 'user'])
            ->whereHas('appointment', function ($query) use ($id) {
                $query->where('client_id', $id);
            })
            ->latest()
            ->get();

        return view('client.contacts.show_contant', compact('client', 'notes'));
    }

    public function mang_client(Request $request)
    {
        $clientGroups = Region_groub::all();
        $invoices = Invoice::all();
        $notes = ClientRelation::all();
        $clients = Client::with([
            'invoices',
            'appointmentNotes.employee',
            'clientRelations' => function ($query) {
                $query->with(['employee', 'location'])->orderBy('date', 'desc');
            },
        ])
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->full_name,
                    'phone' => $client->phone,
                    'balance' => $client->balance,
                    'invoices' => $client->invoices->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'number' => $invoice->code,
                            'date' => $invoice->invoice_date->format('Y-m-d'),
                            'amount' => $invoice->grand_total,
                            'status' => $invoice->payment_status,
                            'remaining' => $invoice->remaining_amount,
                            'paymentMethod' => $invoice->payment_method,
                        ];
                    }),
                    'notes' => $client->appointmentNotes->map(function ($note) {
                        return [
                            'id' => $note->id,
                            'date' => $note->date,
                            'employee' => $note->employee->name ?? 'غير محدد',
                            'content' => $note->description,
                            'status' => $note->status,
                        ];
                    }),
                    'relations' => $client->clientRelations->map(function ($relation) {
                        return [
                            'id' => $relation->id,
                            'status' => $relation->status,
                            'process' => $relation->process,
                            'time' => $relation->time,
                            'date' => $relation->date,
                            'employee' => $relation->employee->name ?? 'غير محدد',
                            'description' => $relation->description,
                            'location' => $relation->location
                                ? [
                                    'id' => $relation->location->id,
                                    'address' => $relation->location->address,
                                    'coordinates' => $relation->location->coordinates,
                                ]
                                : null,
                            'site_type' => $relation->site_type,
                            'competitor_documents' => $relation->competitor_documents,
                            'additional_data' => $relation->additional_data,
                        ];
                    }),
                ];
            });

        return view('client::relestion_mang_client', [
            'clients' => $clients,
            'invoices' => $invoices,
            'notes' => $notes,
            'clientGroups' => $clientGroups,
        ]);
    }

    public function getClientData($clientId)
    {
        $client = Client::find($clientId);

        if (! $client) {
            return response()->json(['error' => 'العميل غير موجود'], 404);
        }

        // جلب الفواتير الخاصة بهذا العميل فقط
        $invoices = Invoice::where('client_id', $clientId)
            ->with(['client', 'createdByUser', 'employee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // جلب الملاحظات الخاصة بهذا العميل
        $notes = ClientRelation::where('client_id', $clientId)->with('employee')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'client' => $client,
            'invoices' => $invoices,
            'notes' => $notes,
        ]);
    }

    public function getAllClients()
    {
        $clients = Client::with('latestStatus')->orderBy('created_at', 'desc')->get();

        return response()->json($clients);
    }

    public function updateOpeningBalance(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $client = Client::findOrFail($id);

            // الحصول على الحساب المرتبط بالعميل
            $Account = Account::ledger()->where('client_id', $id)->first();

            if (! $Account) {
                DB::rollBack();

                return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل');
            }

            // تحديث الرصيد الافتتاحي ونوعه
            $Account->opening_balance = $request->opening_balance ?? 0;
            $Account->opening_balance_type = $Account->balance_type; // مطابق لنوع الحساب
            $Account->save();

            // إنشاء أو تحديث قيد الرصيد الافتتاحي
            if ($Account->opening_balance != 0) {
                $Account->createOrUpdateOpeningBalanceJournalEntry();
            } else {
                // إذا كان الرصيد يساوي صفر، احذف القيد القديم
                $Account->deleteExistingOpeningBalanceEntries();
            }

            DB::commit();

            return redirect()->route('clients.show', $id)->with('success', 'تم تحديث الرصيد الافتتاحي بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'updateOpeningBalance');

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getNextClient(Request $request)
    {
        $currentClientId = $request->query('currentClientId');
        $nextClient = Client::where('id', '>', $currentClientId)->orderBy('id', 'asc')->first();

        if ($nextClient) {
            $nextClient->load('notes'); // تحميل الملاحظات المرتبطة

            return response()->json(['client' => $nextClient]);
        }

        return response()->json(['client' => null]);
    }

    public function getPreviousClient(Request $request)
    {
        $currentClientId = $request->query('currentClientId');
        $previousClient = Client::where('id', '<', $currentClientId)->orderBy('id', 'desc')->first();

        if ($previousClient) {
            $previousClient->load('notes'); // تحميل الملاحظات المرتبطة

            return response()->json(['client' => $previousClient]);
        }

        return response()->json(['client' => null]);
    }

    public function getFirstClient()
    {
        $firstClient = Client::orderBy('id', 'asc')->first();
        if ($firstClient) {
            $firstClient->load('notes');

            return response()->json(['client' => $firstClient]);
        }

        return response()->json(['client' => null]);
    }

    public function mang_client_store(ClientRequest $request)
    {
        $data_request = $request->except('_token');

        // إنشاء العميل
        $client = new Client;

        // الحصول على الرقم الحالي لقسم العملاء من جدول serial_settings
        $serialSetting = SerialSetting::where('section', 'customer')->first();

        // إذا لم يتم العثور على إعدادات، نستخدم 1 كقيمة افتراضية
        $currentNumber = $serialSetting ? $serialSetting->current_number : 1;

        // تعيين id للعميل الجديد باستخدام الرقم الحالي
        // $client->id = $currentNumber;

        // تعيين الكود للعميل الجديد (إذا كان الكود مطلوبًا أيضًا)
        $client->code = $currentNumber;

        // تعبئة البيانات الأخرى
        $client->fill($data_request);

        // معالجة الصورة
        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments');
            if ($file->isValid()) {
                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('assets/uploads/'), $filename);
                $client->attachments = $filename;
            }
        }

        // حفظ العميل
        $client->save();

        // تسجيل اشعار نظام جديد
        ModelsLog::create([
            'type' => 'client',
            'type_id' => $client->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم اضافة  عميل **'.$client->trade_name.'**',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        // زيادة الرقم الحالي بمقدار 1
        if ($serialSetting) {
            $serialSetting->update(['current_number' => $currentNumber + 1]);
        }

        // إنشاء حساب فرعي باستخدام trade_name
        $customers = Account::where('name', 'العملاء')->first(); // الحصول على حساب العملاء الرئيسي
        if ($customers) {
            $customerAccount = new Account;
            $customerAccount->name = $client->trade_name; // استخدام trade_name كاسم الحساب
            $customerAccount->client_id = $client->id;

            // تعيين نوع الحساب المالي ليكون مطابقًا للحساب الأب
            $customerAccount->account_type = $customers->account_type;

            // تعيين كود الحساب الفرعي بناءً على كود الحسابات
            $lastChild = Account::where('parent_id', $customers->id)->orderBy('code', 'desc')->first();
            $newCode = $lastChild ? $this->generateNextCode($lastChild->code) : $customers->code.'1'; // استخدام نفس منطق توليد الكود
            $customerAccount->code = $newCode; // تعيين الكود الجديد للحساب الفرعي

            $customerAccount->balance_type = 'debit'; // أو 'credit' حسب الحاجة
            $customerAccount->parent_id = $customers->id; // ربط الحساب الفرعي بحساب العملاء
            $customerAccount->is_active = false;

            // تعيين الرصيد الافتتاحي ونوعه
            $customerAccount->opening_balance = 0; // لا يوجد رصيد افتتاحي في هذا السياق
            $customerAccount->opening_balance_type = $customers->balance_type; // مطابق لنوع الرصيد الأب
            $customerAccount->is_active = true;

            $customerAccount->save();

            // سيتم إنشاء قيد الرصيد الافتتاحي تلقائيًا من خلال نموذج الحساب إذا لزم الأمر
        }

        // حفظ جهات الاتصال المرتبطة بالعميل
        if ($request->has('contacts') && is_array($request->contacts)) {
            foreach ($request->contacts as $contact) {
                $client->contacts()->create($contact);
            }
        }

        return redirect()->route('clients.mang_client')->with('success', '✨ تم إضافة العميل بنجاح!');
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat1) || is_null($lon1) || is_null($lat2) || is_null($lon2)) {
            return 0;
        }

        if (abs($lat1) > 90 || abs($lon1) > 180 || abs($lat2) > 90 || abs($lon2) > 180) {
            return 0;
        }

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));
        $distance = 6371000 * $c; // بالمتر

        return $distance / 1000; // تحويل إلى كيلومتر
    }

    public function forceShow(Client $client)
    {
        if (auth()->user()->role !== 'manager') {
            abort(403, 'أنت لا تملك الصلاحية لتنفيذ هذا الإجراء.');
        }

        $client->update([
            'force_show' => true,
            'last_note_at' => null,
        ]);

        ModelsLog::create([
            'type' => 'client',
            'type_log' => 'update',
            'description' => 'تم إظهار العميل '.$client->trade_name.' في الخريطة قبل انتهاء المدة',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم إظهار العميل في الخريطة بنجاح');
    }

    // دالة مساعدة لحساب المسافة

    /**
     * حساب المسافة باستخدام Haversine formula
     */
    public function mang_client_details($id)
    {
        try {
            // Find the client
            $client = Client::with(['employee'])->findOrFail($id);

            // Get all clients for the sidebar
            $clients = Client::orderBy('created_at', 'desc')->get();

            // Get notes and appointments
            $notes = AppointmentNote::with(['user'])
                ->latest()
                ->get();
            $appointments = Appointment::all();
            $employees = Employee::all();

            // Get previous and next client IDs
            $previousClient = Client::where('id', '<', $id)->orderBy('id', 'desc')->first();
            $nextClient = Client::where('id', '>', $id)->orderBy('id', 'asc')->first();

            // If it's an AJAX request, return JSON
            if (request()->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'client' => $client,
                        'trade_name' => $client->trade_name,
                        'phone' => $client->phone,
                        'email' => $client->email,
                        'status' => $client->status,
                        'employee' => $client->employee
                            ? [
                                'name' => $client->employee->name,
                                'department' => $client->employee->department,
                                'role' => $client->employee->role,
                            ]
                            : null,
                    ],
                ]);
            }

            // For regular requests, return the view
            return view('client.relestion_mang_client', compact('clients', 'client', 'employees', 'notes', 'appointments', 'previousClient', 'nextClient'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'mang_client_details');

            // If it's an AJAX request, return error response
            if (request()->ajax()) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'حدث خطأ أثناء تحميل بيانات العميل',
                    ],
                    422,
                );
            }

            // For regular requests, redirect with error
            return redirect()->route('clients.mang_client')->with('error', 'حدث خطأ أثناء تحميل بيانات العميل');
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->query('query'); // تغيير اسم المتغير هنا

            $clients = Client::with(['latestStatus', 'employee'])
                ->where(function ($queryBuilder) use ($searchTerm) {
                    // استخدام اسم مختلف للـ Query Builder
                    $queryBuilder
                        ->where('trade_name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('code', 'like', '%'.$searchTerm.'%')
                        ->orWhere('phone', 'like', '%'.$searchTerm.'%')
                        ->orWhere('email', 'like', '%'.$searchTerm.'%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $clients->items(),
                'pagination' => [
                    'total' => $clients->total(),
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'search');

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء البحث',
                ],
                500,
            );
        }
    }

    public function assignEmployees(Request $request, Client $client)
    {
        // التحقق من صحة البيانات
        $request->validate([
            'employee_id' => 'required|array',
            'employee_id.*' => 'exists:employees,id',
        ]);

        try {

            // بدء المعاملة
            DB::beginTransaction();

            // مزامنة الموظفين (إضافة وإزالة)
            $client->employees()->sync($request->employee_id);

            // إنهاء المعاملة
            DB::commit();

            // إعادة التوجيه مع رسالة نجاح
            return redirect()->back()->with('success', 'تم تعيين الموظفين بنجاح');
        } catch (\Exception $e) {
            // إلغاء المعاملة في حالة الخطأ
            DB::rollBack();

            // تسجيل الخطأ
            Log::error('خطأ في تعيين الموظفين: '.$e->getMessage());

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
            Log::info('تمت إزالة الموظف', [
                'client_id' => $clientId,
                'employee_id' => $request->employee_id,
            ]);

            // إعادة التوجيه مع رسالة نجاح
            return redirect()->back()->with('success', 'تم إزالة الموظف بنجاح');
        } catch (\Exception $e) {
            // تسجيل الخطأ
            Log::error('خطأ في إزالة الموظف', [
                'message' => $e->getMessage(),
                'client_id' => $clientId,
                'employee_id' => $request->employee_id,
            ]);

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
            Log::error('خطأ في جلب الموظفين المعينين: '.$e->getMessage());

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

    public function import(Request $request)
    {
        set_time_limit(500);
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        Excel::import(new ClientsImport, $request->file('file'));

        return redirect()->back()->with('success', 'تم استيراد العملاء بنجاح!');
    }

    /**
     * استيراد العملاء من ملف Excel
     */
    public function importExcel(Request $request)
    {
        try {
            set_time_limit(500);
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv',
            ], [
                'file.required' => 'يرجى اختيار ملف للاستيراد',
                'file.mimes' => 'يجب أن يكون الملف بصيغة Excel (xlsx, xls) أو CSV',
            ]);

            $file = $request->file('file');

            try {
                $imported = Excel::import(new ClientsImport, $file);

                return response()->json([
                    'success' => true,
                    'message' => '✅ تم استيراد العملاء بنجاح! يرجى تحديث الصفحة.',
                ]);
            } catch (\Exception $importError) {
                // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
                OperationLog::failure($importError, 'Client.Clientbackup', 'importExcel');

                // معالجة أخطاء قاعدة البيانات بشكل أفضل
                $errorMsg = $importError->getMessage();

                if (strpos($errorMsg, 'trade_name') !== false) {
                    $message = '❌ خطأ: يجب أن يحتوي كل صف على (الاسم التجاري) أو (اسم العميل)';
                } elseif (strpos($errorMsg, 'mobile') !== false) {
                    $message = '❌ خطأ: يجب أن يحتوي كل صف على رقم جوال صحيح';
                } elseif (strpos($errorMsg, 'Integrity constraint') !== false) {
                    $message = '❌ خطأ: بيانات غير صحيحة في الملف. تأكد من ملء الحقول الإجبارية.';
                } else {
                    $message = '❌ خطأ في الاستيراد: '.$errorMsg;
                }

                throw new \Exception($message);
            }
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'importExcel');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * الحصول على نموذج استيراد Excel
     */
    public function getImportTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // إضافة رؤوس الأعمدة
            $headers = [
                'كود العميل',
                'الاسم التجاري',
                'اسم العميل',
                'اسم العائلة',
                'رقم الهاتف',
                'رقم الجوال',
                'البريد الإلكتروني',
                'التصنيف',
                'الفرع',
                'المنطقة',
                'الحي',
                'العنوان',
                'الرصيد الافتتاحي',
                'حد الائتمان',
                'مدة الائتمان بالأيام',
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index).'1', $header);
                // تنسيق رؤوس الأعمدة
                $sheet->getStyle(chr(65 + $index).'1')->getFont()->setBold(true);
                $sheet->getStyle(chr(65 + $index).'1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF2980B9');
                $sheet->getStyle(chr(65 + $index).'1')->getFont()->getColor()->setARGB('FFFFFFFF');
            }

            // تعيين عرض الأعمدة
            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension(chr(65 + $i))->setWidth(18);
            }

            $writer = new Xlsx($spreadsheet);
            $temp_file = tempnam(sys_get_temp_dir(), 'clients_template_');
            $writer->save($temp_file);

            return response()->download($temp_file, 'نموذج_استيراد_العملاء.xlsx');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'getImportTemplate');

            return response()->json([
                'success' => false,
                'message' => '❌ خطأ في إنشاء النموذج: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * عرض صفحة الاستيراد
     */
    public function showImportPage()
    {
        return view('client::import', [
            'branches' => Branch::all(),
            'categories' => CategoriesClient::all(),
        ]);
    }

    public function updateStatusClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'status_id' => 'required|exists:statuses,id',
        ]);

        DB::beginTransaction();

        try {
            $client = Client::findOrFail($request->client_id);
            $client->status_id = $request->status_id;
            $client->save();

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'تم تغيير حالة العميل بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error in updateStatusClient: '.$e->getMessage());

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء تغيير حالة العميل.');
        }
    }

    public function statement($id)
    {
        $client = Client::find($id);
        $account = Account::ledger()->where('client_id', $id)->first();

        if (! $account) {
            return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا العميل.');
        }

        $accountId = $account->id;

        // جلب بيانات الخزينة
        $treasury = $this->getTreasury($accountId);
        $branches = $this->getBranches();

        // جلب العمليات المالية
        $transactions = $this->getTransactions($accountId);
        $transfers = $this->getTransfers($accountId);
        $expenses = $this->getExpenses($accountId);
        $revenues = $this->getRevenues($accountId);

        // معالجة العمليات
        $allOperations = [];

        // معالجة المعاملات (Transactions)
        foreach ($transactions as $transaction) {
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $isDebit = $transaction->debit > 0;

            // جلب نوع العملية من ReferenceType
            $journalEntry = $transaction->journalEntry;
            $referenceType = $journalEntry->reference_type ?? null;

            // تحويل Enum إلى string value إذا كان ضرورياً
            if ($referenceType && is_object($referenceType)) {
                $referenceType = $referenceType->value ?? (string) $referenceType;
            }
            $referenceType = (string) $referenceType; // تحويل آمن لنص

            // الحصول على تفاصيل العملية (الاسم العربي والأيقونة)
            $operationDetails = $this->getOperationTypeDetails($referenceType);
            $operationType = $operationDetails['name'];

            // بناء الرفرنس (الرقم والنوع)
            $referenceDisplay = '';
            if ($journalEntry->reference_number) {
                $referenceDisplay = $journalEntry->reference_number;
            } elseif ($journalEntry->invoice) {
                $referenceDisplay = $journalEntry->invoice->code ?? $journalEntry->invoice->id;
            }

            // معالجة خاصة لقيود الديون المعدومة
            // إذا كان الوصف يحتوي على "ديون معدومة"، يجب أن تكون عملية تقليل (withdraw)
            $description = $journalEntry->description ?? $transaction->description ?? '';
            $isWriteOffDebt = strpos(strtolower($description), 'ديون معدومة') !== false;

            // للديون المعدومة، نعتبرها كـ withdraw (تقليل) بغض النظر عن debit/credit
            if ($isWriteOffDebt) {
                $isDebit = false; // اجعلها withdraw بدلاً من deposit
            }

            $operation = [
                'date' => $journalEntry->date ?? $transaction->created_at,
                'type' => $operationType,
                'reference_type' => $referenceType,
                'description' => $description,
                'reference' => $referenceDisplay,
                'amount' => $amount,
                'is_deposit' => $isDebit,
                'deposit' => $isDebit ? $amount : 0,
                'withdraw' => ! $isDebit ? $amount : 0,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }    // معالجة المصروفات (Expenses)
        foreach ($expenses as $expense) {
            $operation = [
                'date' => $expense->date ?? $expense->created_at,
                'type' => 'مصروفات',
                'reference_type' => 'EXP',
                'description' => $expense->description ?? '',
                'reference' => $expense->voucher_number ? $expense->voucher_number : '',
                'amount' => $expense->amount,
                'is_deposit' => false,
                'deposit' => 0,
                'withdraw' => $expense->amount,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }

        // معالجة الإيرادات (Revenues)
        foreach ($revenues as $revenue) {
            $operation = [
                'date' => $revenue->date ?? $revenue->created_at,
                'type' => 'إيرادات',
                'reference_type' => 'REV',
                'description' => $revenue->description ?? '',
                'reference' => $revenue->voucher_number ? $revenue->voucher_number : '',
                'amount' => $revenue->amount,
                'is_deposit' => false,
                'deposit' => 0,
                'withdraw' => $revenue->amount,
                'balance' => 0,
            ];

            $allOperations[] = $operation;
        }

        // تنظيف البيانات والتأكد من وجود التاريخ
        $allOperations = array_filter($allOperations, function ($operation) {
            return isset($operation['date']) && ! empty($operation['date']);
        });

        // ترتيب العمليات حسب التاريخ والوقت (من الأقدم للأحدث)
        usort($allOperations, function ($a, $b) {
            $dateA = is_string($a['date']) ? strtotime($a['date']) : $a['date']->timestamp;
            $dateB = is_string($b['date']) ? strtotime($b['date']) : $b['date']->timestamp;

            return $dateA - $dateB; // ترتيب تصاعدي (من الأقدم للأحدث)
        });

        // حساب الرصيد بعد كل عملية
        // نبدأ من صفر لأن العمليات تشمل القيد الافتتاحي (OPB)
        $runningBalance = 0;

        foreach ($allOperations as $key => &$operation) {
            if ($operation['is_deposit']) {
                // إذا كانت إيداع (فاتورة مبيعات أو رصيد افتتاحي)
                $runningBalance += $operation['amount'];
            } else {
                // إذا كانت سحب (تحصيل)
                $runningBalance -= $operation['amount'];
            }

            // تحديث الرصيد بعد العملية
            $operation['balance'] = $runningBalance;
        }
        unset($operation);

        // تقسيم العمليات إلى صفحات
        $perPage = 50;
        $currentPage = request()->get('page', 1);
        $operationsPaginator = new LengthAwarePaginator(
            array_slice($allOperations, ($currentPage - 1) * $perPage, $perPage),
            count($allOperations),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // حساب الإجماليات
        $totalDeposits = array_reduce($allOperations, function ($carry, $item) {
            return $carry + $item['deposit'];
        }, 0);

        $totalWithdraws = array_reduce($allOperations, function ($carry, $item) {
            return $carry + $item['withdraw'];
        }, 0);

        // إرسال البيانات إلى الواجهة
        return view('client::statement', compact(
            'treasury',
            'account',
            'operationsPaginator',
            'branches',
            'client',
            'totalDeposits',
            'totalWithdraws'
        ));
    }

    private function getTreasury($id)
    {
        return Account::findOrFail($id);
    }

    private function getBranches()
    {
        return Branch::all();
    }

    /**
     * تحويل رموز ReferenceType إلى نصوص عربية مع تحديد اتجاه التأثير
     */
    private function getOperationTypeDetails($referenceType)
    {
        // التأكد من أن referenceType هي نص وليس null أو Enum
        if (! is_string($referenceType)) {
            // إذا كانت Enum، استخرج القيمة
            if (is_object($referenceType)) {
                if (property_exists($referenceType, 'value')) {
                    $referenceType = $referenceType->value;
                } else {
                    $referenceType = (string) $referenceType;
                }
            } else {
                $referenceType = (string) $referenceType;
            }
        }

        $typeMapping = [
            'OPB' => ['name' => 'رصيد افتتاحي', 'icon' => '📊'],
            'JV' => ['name' => 'قيد يومية عام', 'icon' => '📝'],
            'PV' => ['name' => 'سند صرف', 'icon' => '💸'],
            'RV' => ['name' => 'سند قبض', 'icon' => '💰'],
            'PI' => ['name' => 'فاتورة مشتريات', 'icon' => '📥'],
            'PR' => ['name' => 'مردودات مشتريات', 'icon' => '↩️'],
            'SI' => ['name' => 'فاتورة مبيعات', 'icon' => '📤'],
            'SR' => ['name' => 'مردودات مبيعات', 'icon' => '↪️'],
            'DN' => ['name' => 'إشعار مدين', 'icon' => '📋'],
            'CN' => ['name' => 'إشعار دائن', 'icon' => '📃'],
            'AW' => ['name' => 'مخزن معتمد', 'icon' => '📦'],
            'EXP' => ['name' => 'مصروفات', 'icon' => '💳'],
            'ADV' => ['name' => 'سلفة/عهدة', 'icon' => '🏦'],
            'STK' => ['name' => 'تسوية مخزون', 'icon' => '📊'],
            'TRF' => ['name' => 'تحويل داخلي', 'icon' => '🔄'],
            'PAY' => ['name' => 'مدفوعات موردين', 'icon' => '💳'],
            'REC' => ['name' => 'تحصيلات عملاء', 'icon' => '💵'],
            'SAL' => ['name' => 'مسير رواتب', 'icon' => '💼'],
            'DEP' => ['name' => 'إهلاك أصول', 'icon' => '📉'],
            'ACC' => ['name' => 'تسوية قيود محاسبية', 'icon' => '⚙️'],
            'PCAN' => ['name' => 'إلغاء دفعة', 'icon' => '❌'],
        ];

        return $typeMapping[$referenceType] ?? ['name' => $referenceType ?: 'عملية عامة', 'icon' => '❓'];
    }

    private function getTransactions($id)
    {
        return JournalEntryDetail::where('account_id', $id)
            ->with([
                'journalEntry' => function ($query) {
                    $query->with('invoice', 'client');
                },
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getTransfers($id)
    {
        return JournalEntry::whereHas('details', function ($query) use ($id) {
            $query->where('account_id', $id);
        })
            ->with(['details.account'])
            ->where('description', 'تحويل المالية')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getExpenses($id)
    {
        return Expense::where('treasury_id', $id)
            ->with(['expenses_category', 'vendor', 'employee', 'branch', 'client'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function getRevenues($id)
    {
        return Revenue::where('treasury_id', $id)
            ->with(['account', 'paymentVoucher', 'treasury', 'bankAccount', 'journalEntry'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function processOperations($transactions, $transfers, $expenses, $revenues, $treasury)
    {
        $allOperations = [];

        // معالجة المعاملات (Transactions)
        foreach ($transactions as $transaction) {
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $type = $transaction->debit > 0 ? 'إيداع' : 'سحب';

            $allOperations[] = [
                'operation' => $transaction->description ?? ($type === 'إيداع' ? 'فاتورة مبيعات' : 'تحصيل'),
                'deposit' => $type === 'إيداع' ? $amount : 0,
                'withdraw' => $type === 'سحب' ? $amount : 0,
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'journalEntry' => $transaction->journalEntry->id ?? null,
                'date' => $transaction->journalEntry->date ?? $transaction->created_at,
                'invoice' => $transaction->journalEntry->invoice ?? null,
                'client' => $transaction->journalEntry->client ?? null,
                'type' => 'transaction',
            ];
        }

        // معالجة المصروفات (Expenses)
        foreach ($expenses as $expense) {
            $allOperations[] = [
                'operation' => 'سند صرف: '.($expense->description ?? ''),
                'deposit' => 0,
                'withdraw' => $expense->amount,
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'date' => $expense->date ?? $expense->created_at,
                'invoice' => null,
                'client' => $expense->client,
                'type' => 'expense',
            ];
        }

        // معالجة الإيرادات (Revenues)
        foreach ($revenues as $revenue) {
            $allOperations[] = [
                'operation' => 'سند قبض: '.($revenue->description ?? ''),
                'deposit' => 0,
                'withdraw' => $revenue->amount, // نعتبر الإيرادات كسحب (مبلغ سالب)
                'balance_after' => 0, // سيتم حسابه لاحقاً
                'date' => $revenue->date ?? $revenue->created_at,
                'invoice' => null,
                'client' => null,
                'type' => 'revenue',
            ];
        }

        return $allOperations;
    }

    private function updateBalance($currentBalance, $amount, $type)
    {
        return $type === 'إيداع' ? $currentBalance + $amount : $currentBalance - $amount;
    }

    private function paginateOperations($allOperations)
    {
        $perPage = 15;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedOperations = array_slice($allOperations, $offset, $perPage);

        return new LengthAwarePaginator($paginatedOperations, count($allOperations), $perPage, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function addnotes(Request $request)
    {
        $user = auth()->user();

        // التحقق من صلاحية إضافة ملاحظة من أي موقع
        $hasLocationBypassPermission = hasPermission('client_follow_up_add_notes_from_any_location');

        // التحقق من صحة البيانات المدخلة - جعل الموقع اختياري إذا كان لدى المستخدم صلاحية التجاوز
        $locationValidation = $hasLocationBypassPermission ? 'nullable|numeric' : 'required|numeric';

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'client_employee_id' => 'required|exists:client_employee_notes,id',
            'process' => 'nullable|string|max:255',
            'description' => 'required|string',
            'deposit_count' => 'required|integer|min:0', // إلزامي
            'site_type' => 'required|in:independent_booth,grocery,supplies,markets,station', // إلزامي
            'competitor_documents' => 'required|integer|min:0', // إلزامي
            'attachments' => 'required|array|min:1', // إلزامي - على الأقل صورة واحدة
            'attachments.*' => 'required|file|mimes:jpg,jpeg,png|max:102400', // صور فقط
            'current_latitude' => $locationValidation,
            'current_longitude' => $locationValidation,
        ], [
            'deposit_count.required' => 'حقل عدد العهدة إلزامي',
            'client_employee_id.required' => 'حقل موظف العميل إلزامي',
            'client_employee_id.exists' => 'موظف العميل المحدد غير صالح',
            'site_type.required' => 'حقل نوع الموقع إلزامي',
            'site_type.in' => 'نوع الموقع غير صالح',
            'competitor_documents.required' => 'حقل عدد استندات المنافسين إلزامي',
            'attachments.required' => 'يجب إرفاق صورة واحدة على الأقل',
            'attachments.min' => 'يجب إرفاق صورة واحدة على الأقل',
            'attachments.*.mimes' => 'يجب أن تكون المرفقات صور فقط (jpg, jpeg, png)',
        ]);

        DB::beginTransaction();

        try {
            // التحقق من الموقع للموظفين فقط (إذا لم يكن لديه صلاحية التجاوز)
            if (auth()->user()->role === 'employee' && ! $hasLocationBypassPermission) {
                $employeeLocation = Location::where('employee_id', auth()->id())
                    ->latest()
                    ->first();

                // إنشاء أو تحديث موقع الموظف
                if (! $employeeLocation) {
                    $employeeLocation = Location::create([
                        'employee_id' => auth()->id(),
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                } else {
                    // تحديث الموقع الحالي
                    $employeeLocation->update([
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }

                // التحقق من موقع العميل
                $clientLocation = Location::where('client_id', $request->client_id)
                    ->latest()
                    ->first();

                if (! $clientLocation) {
                    throw new \Exception('لم يتم تسجيل موقع العميل! الرجاء التواصل مع الإدارة.');
                }

                // حساب المسافة
                $distance = $this->calculateDistance(
                    $employeeLocation->latitude,
                    $employeeLocation->longitude,
                    $clientLocation->latitude,
                    $clientLocation->longitude
                );

                // التحقق من المسافة
                if ($distance > 0.3) {
                    throw new \Exception('يجب أن تكون ضمن نطاق 0.3 كيلومتر من العميل! المسافة الحالية: '.round($distance, 2).' كم');
                }
            }

            // باقي الكود كما هو...
            // تحديث حالة الزيارة إلى active
            EmployeeClientVisit::where('employee_id', auth()->id())
                ->where('client_id', $request->client_id)
                ->update(['status' => 'active']);

            // الحصول على بيانات العميل والحالات
            $client = Client::findOrFail($request->client_id);
            $underReviewStatus = Statuses::where('name', 'تحت المراجعة')->first();
            $activeStatus = Statuses::where('name', 'نشط')->first();
            $followUpStatus = Statuses::where('name', 'متابعة')->first();

            // إنشاء سجل الملاحظة
            $clientRelation = ClientRelation::create([
                'employee_id' => auth()->id(),
                'client_id' => $request->client_id,
                'status' => $request->status ?? 'pending',
                'process' => $request->process,
                'description' => $request->description,
                'client_employee_id' => $request->client_employee_id,
                'deposit_count' => $request->deposit_count,
                'site_type' => $request->site_type,
                'competitor_documents' => $request->competitor_documents,
                'additional_data' => json_encode([
                    'deposit_count' => $request->deposit_count,
                    'site_type' => $request->site_type,
                    'competitor_documents' => $request->competitor_documents,
                    'latitude' => $request->current_latitude,
                    'longitude' => $request->current_longitude,
                ]),
            ]);

            // إنشاء إشعار واحد فقط
            $notificationData = [
                'user_id' => auth()->id(),
                'receiver_id' => auth()->id(),
                'type' => 'client_note',
                'title' => 'تم إضافة ملاحظة للعميل '.$client->trade_name,
                'description' => $request->description,
                'read' => false,
            ];

            notifications::create($notificationData);

            if ($underReviewStatus && $activeStatus && $client->status_id == $underReviewStatus->id) {
                $client->status_id = $activeStatus->id;
                $client->save();

                ModelsLog::create([
                    'type' => 'status_change',
                    'type_log' => 'log',
                    'description' => 'تم تغيير حالة العميل من "تحت المراجعة" إلى "نشط" تلقائياً',
                    'created_by' => auth()->id(),
                    'related_id' => $client->id,
                    'related_type' => Client::class,
                ]);
            }

            $client->last_note_at = now();
            $client->save();

            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $destinationPath = public_path('assets/uploads/notes');

                        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg', 'image/png'])) {
                            // حفظ الصور بصيغة JPEG دائماً للضغط الأفضل
                            $filename = time().'_'.uniqid().'.jpg';

                            // حساب جودة الضغط حسب حجم الملف
                            $originalSize = $file->getSize();
                            $quality = 75; // جودة افتراضية

                            if ($originalSize > 10 * 1024 * 1024) {
                                $quality = 40; // ضغط أكثر للملفات الكبيرة جداً
                            } elseif ($originalSize > 5 * 1024 * 1024) {
                                $quality = 50;
                            } elseif ($originalSize > 2 * 1024 * 1024) {
                                $quality = 60;
                            }

                            $this->compressAndSaveImage($file->path(), $destinationPath.'/'.$filename, $quality);
                        } else {
                            // للملفات الأخرى (PDF وغيرها)
                            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

                            if ($file->getMimeType() === 'application/pdf' && $file->getSize() > 5 * 1024 * 1024) {
                                $file->move($destinationPath, $filename);
                            } else {
                                $file->move($destinationPath, $filename);
                            }
                        }

                        $attachments[] = $filename;
                    }
                }
                $clientRelation->attachments = json_encode($attachments);
                $clientRelation->save();
            }

            if (auth()->user()->role === 'employee') {
                $location = Location::where('employee_id', auth()->id())
                    ->latest()
                    ->first();

                if ($location) {
                    $location->update([
                        'client_relation_id' => $clientRelation->id,
                        'client_id' => $request->client_id,
                        'latitude' => $request->current_latitude,
                        'longitude' => $request->current_longitude,
                    ]);
                }
            }

            ModelsLog::create([
                'type' => 'client_note',
                'type_log' => 'log',
                'description' => 'تم إضافة ملاحظة للعميل: '.$request->description,
                'created_by' => auth()->id(),
                'related_id' => $client->id,
                'related_type' => Client::class,
            ]);

            DB::commit();

            return redirect()
                ->route('clients.show', $request->client_id)
                ->with('success', 'تم إضافة الملاحظة بنجاح'.($client->wasChanged('status_id') ? ' وتغيير حالة العميل إلى متابعة!' : '!'));
        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'addnotes');

            DB::rollBack();

            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'addnotes');

            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'فشل إضافة الملاحظة: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * كتابة الديون المعدومة - تسوية الديون بعدم السداد
     * Write off bad debts - Zero out client account with journal entry
     */
    public function writeOffBadDebt(Request $request, $clientId)
    {
        try {
            DB::beginTransaction();

            // Validate input
            $validated = $request->validate([
                'write_off_type' => 'required|in:full,partial,negative_to_zero',
                'amount' => 'nullable|numeric|min:0.01',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:1000',
            ], [
                'write_off_type.required' => 'يجب اختيار نوع التسوية',
                'amount.numeric' => 'المبلغ يجب أن يكون رقماً صحيحاً',
                'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            ]);

            // Fetch client
            $client = Client::findOrFail($clientId);

            // Fetch client account
            $clientAccount = Account::ledger()->where('client_id', $clientId)->first();
            if (! $clientAccount) {
                return back()->with('error', 'لا يوجد حساب لهذا العميل.');
            }

            // Get current balance
            $currentBalance = floatval($clientAccount->balance ?? 0);

            // تحديد المبلغ والوصف بناءً على نوع التسوية
            $writeOffType = $validated['write_off_type'];
            $amountToWriteOff = 0;
            $typeDescription = '';
            $isNegativeBalance = $currentBalance < 0;

            if ($writeOffType === 'full') {
                // تسوية كاملة
                if ($currentBalance == 0) {
                    return back()->with('error', 'رصيد العميل صفر، لا توجد حاجة للتسوية.');
                }
                $amountToWriteOff = abs($currentBalance);
                $typeDescription = 'تسوية كاملة للمديونية';

            } elseif ($writeOffType === 'partial') {
                // تسوية جزئية بمبلغ محدد
                if (! isset($validated['amount'])) {
                    return back()->with('error', 'يجب إدخال المبلغ للتسوية الجزئية.');
                }
                $amount = floatval($validated['amount']);
                if ($amount > abs($currentBalance)) {
                    return back()->with('error', 'المبلغ المدخل أكبر من رصيد العميل.');
                }
                if ($amount <= 0) {
                    return back()->with('error', 'المبلغ يجب أن يكون موجباً.');
                }
                $amountToWriteOff = $amount;
                $typeDescription = 'تسوية جزئية بمبلغ '.number_format($amount, 2);

            } elseif ($writeOffType === 'negative_to_zero') {
                // تحويل الرصيد السالب إلى صفر
                if ($currentBalance >= 0) {
                    return back()->with('error', 'الرصيد الحالي ليس سالباً، لا توجد حاجة لتحويله إلى صفر.');
                }
                $amountToWriteOff = abs($currentBalance);
                $typeDescription = 'تحويل الرصيد السالب إلى صفر';
            }

            // Get bad debt account
            $badDebtAccount = Account::findOrFail(SystemAccountIds::BAD_DEBTS);

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => 'WO-'.$client->id.'-'.time(),
                'date' => now()->toDateString(),
                'description' => $typeDescription.' - '.$client->trade_name,
                'status' => 1,
                'currency' => 'SAR',
                'client_id' => $clientId,
                'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                'created_by_employee' => auth()->id(),
                'approved_by_employee' => auth()->id(),
                'is_posted' => 1,
                'branch_id' => auth()->user()->branch_id,
            ]);

            // القيد المحاسبي الصحيح:
            // إذا كان الرصيد موجب (مدين): العميل عليه مبلغ
            // إذا كان الرصيد سالب (دائن): العميل له مبلغ

            if ($isNegativeBalance) {
                // رصيد سالب (دائن) - العميل له رصيد عندنا
                // 1) حساب العميل (مدين) - نقلل رصيده الدائن
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال رصيد دائن - '.$client->trade_name.' ('.$typeDescription.')',
                    'debit' => $amountToWriteOff,
                    'credit' => 0,
                ]);

                // 2) حساب الديون المعدومة (دائن) - نسجل الفرق
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'رصيد دائن معدوم - '.$client->trade_name,
                    'debit' => 0,
                    'credit' => $amountToWriteOff,
                ]);
            } else {
                // رصيد موجب (مدين) - العميل عليه مبلغ
                // 1) حساب الديون المعدومة (مدين)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'مصروف ديون معدومة - '.$client->trade_name,
                    'debit' => $amountToWriteOff,
                    'credit' => 0,
                ]);

                // 2) حساب العميل (دائن) - نقلل مديونيته
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال مديونية العميل - '.$client->trade_name.' ('.$typeDescription.')',
                    'debit' => 0,
                    'credit' => $amountToWriteOff,
                ]);
            }

            // تحديث رصيد العميل
            if ($writeOffType === 'full' || $writeOffType === 'negative_to_zero') {
                // تصفير الحساب تماماً
                $clientAccount->balance = 0;
            } else {
                // تقليل الرصيد بالمبلغ المحدد
                $clientAccount->balance = $currentBalance - $amountToWriteOff;
            }
            $clientAccount->save();

            // Log action
            ModelsLog::create([
                'module' => 'العملاء',
                'action' => 'تسوية ديون معدومة',
                'description' => $typeDescription.' للعميل: '.$client->trade_name.' - المبلغ: '.number_format($amountToWriteOff, 2).' - السبب: '.$validated['reason'],
                'reference_id' => $clientId,
                'reference_type' => 'Client',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            $message = '';
            if ($writeOffType === 'full') {
                $message = 'تم تسوية المديونية بالكامل بنجاح وتصفير الحساب. رقم القيد: '.$journalEntry->id;
            } elseif ($writeOffType === 'partial') {
                $message = 'تم تسوية مبلغ '.number_format($amountToWriteOff, 2).' من المديونية بنجاح. رقم القيد: '.$journalEntry->id;
            } elseif ($writeOffType === 'negative_to_zero') {
                $message = 'تم تحويل الرصيد السالب إلى صفر بنجاح. رقم القيد: '.$journalEntry->id;
            }

            return redirect()->route('clients.show', $clientId)
                ->with('success', $message);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'writeOffBadDebt');

            DB::rollBack();

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إضافة مديونية مشكوك فيها بشكل سريع (بدون حقول)
     */
    public function addDoubtfulDebtQuick($clientId)
    {
        try {
            DB::beginTransaction();

            // جلب العميل
            $client = Client::findOrFail($clientId);

            // جلب حساب العميل
            $clientAccount = Account::ledger()->where('client_id', $clientId)->first();
            if (! $clientAccount) {
                return redirect()->back()->with('error', '❌ لا يوجد حساب للعميل');
            }

            // حساب الرصيد الحالي
            $currentBalance = abs(floatval($clientAccount->balance ?? 0));

            if ($currentBalance == 0) {
                return redirect()->back()->with('error', '❌ الحساب مُسوّى - لا توجد مديونية');
            }

            // الحصول على حساب الديون المشكوك فيها من الثابت
            $doubtfulDebtAccount = Account::findOrFail(SystemAccountIds::ALLOWANCE_FOR_DOUBTFUL_DEBTS);

            if (! $doubtfulDebtAccount) {
                return redirect()->back()->with('error', '❌ لا يوجد حساب محدد للديون المشكوك فيها');
            }

            // حساب مبلغ المخصص = الرصيد الكامل للعميل
            $provisionAmount = $currentBalance;

            // إنشاء قيد اليومية
            $journalEntry = JournalEntry::create([
                'reference_number' => 'DD-'.$client->id,
                'date' => now()->toDateString(),
                'description' => 'مديونية مشكوك فيها - '.$client->trade_name,
                'status' => 1, // معتمد مباشرة
                'currency' => 'SAR',
                'client_id' => $clientId,
                'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                'created_by_employee' => auth()->id(),
                'approved_by_employee' => auth()->id(),
                'is_posted' => 1,
                'branch_id' => auth()->user()->branch_id,
            ]);

            // تفاصيل القيد
            // 1. حساب الديون المشكوك فيها (مدين) - المخصص الكامل
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $doubtfulDebtAccount->id,
                'description' => 'مديونية مشكوك فيها - '.$client->trade_name,
                'debit' => $provisionAmount,
                'credit' => 0,
                'reference' => $clientAccount->id,
                'currency' => 'SAR',
                'is_debit' => true,
            ]);

            // 2. حساب العميل (دائن) - المبلغ الكامل المديون (متساوي مع المدين)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $clientAccount->id,
                'description' => 'تصفية حساب - مديونية مشكوك فيها - '.$client->trade_name,
                'debit' => 0,
                'credit' => $provisionAmount,
                'reference' => $clientAccount->id,
                'currency' => 'SAR',
                'is_debit' => false,
            ]);

            // تصفير رصيد حساب العميل
            $clientAccount->save();

            // تسجيل الديون المشكوك فيها في قاعدة البيانات
            DoubtfulDebt::create([
                'client_id' => $clientId,
                'account_id' => $clientAccount->id,
                'original_balance' => $currentBalance,
                'provision_amount' => $provisionAmount,
                'provision_percentage' => 100,
                'journal_entry_id' => $journalEntry->id,
                'status' => 'pending',
                'collected_amount' => 0,
                'created_by' => auth()->id(),
            ]);

            // تسجيل سجل العملية
            ModelsLog::create([
                'module' => 'العملاء',
                'action' => 'إضافة مديونية مشكوك فيها وتصفيه الحساب',
                'description' => 'تسجيل سريع - مديونية مشكوك فيها للعميل: '.$client->trade_name.
                               ' - المبلغ الكامل المصفر: '.$currentBalance.
                               ' - مبلغ المخصص (100%): '.$provisionAmount.
                               ' - تم تصفيه الحساب في قيد واحد',
                'reference_id' => $clientId,
                'reference_type' => 'Client',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('clients.show', $clientId)
                ->with('toast_type', 'success')
                ->with('toast_message', '✅ تم تسجيل مديونية مشكوك فيها وتصفيه الحساب بنجاح! المبلغ المصفر: '.number_format($currentBalance, 2).
                                       ' - المخصص: '.number_format($provisionAmount, 2).' - رقم القيد: '.$journalEntry->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في إضافة مديونية مشكوك فيها سريعة', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', '❌ حدث خطأ: '.$e->getMessage());
        }
    }

    public function showWriteOffBranchDebtForm()
    {
        $branches = Branch::all();

        return view('client::write_off_branch_debt', compact('branches'));
    }

    public function writeOffBranchDebt(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Fetch the branch
            $branch = Branch::findOrFail($validated['branch_id']);

            // Get all clients in this branch that have outstanding debt
            $clientsWithDebt = Client::where('branch_id', $validated['branch_id'])
                ->whereHas('branch')
                ->get();

            if ($clientsWithDebt->isEmpty()) {
                return back()->with('warning', 'لا توجد عملاء في هذا الفرع.');
            }

            // Get bad debt account
            $badDebtAccount = Account::findOrFail(SystemAccountIds::BAD_DEBTS);

            $processedCount = 0;
            $totalWrittenOff = 0;
            $skippedCount = 0;

            // Process each client
            foreach ($clientsWithDebt as $client) {
                // Fetch client account
                $clientAccount = Account::ledger()->where('client_id', $client->id)->first();
                if (! $clientAccount) {
                    $skippedCount++;

                    continue;
                }

                // Get current balance
                $currentBalance = floatval($clientAccount->balance ?? 0);

                // Skip if balance is zero or negative (already paid or creditor)
                if ($currentBalance <= 0) {
                    $skippedCount++;

                    continue;
                }

                // Create journal entry for this client
                $journalEntry = JournalEntry::create([
                    'reference_number' => 'WO-BATCH-'.$branch->id.'-'.$client->id.'-'.time(),
                    'date' => now()->toDateString(),
                    'description' => 'تسوية ديون معدومة جماعية - الفرع: '.$branch->name.' - العميل: '.$client->trade_name,
                    'status' => 1,
                    'currency' => 'SAR',
                    'client_id' => $client->id,
                    'reference_type' => ReferenceType::JOURNAL_VOUCHER,
                    'created_by_employee' => auth()->id(),
                    'approved_by_employee' => auth()->id(),
                    'is_posted' => 1,
                    'branch_id' => $validated['branch_id'],
                ]);

                $amount = $currentBalance;

                // Correct entry:
                // 1) Bad Debt Account (Debit)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $badDebtAccount->id,
                    'description' => 'مصروف ديون معدومة - '.$client->trade_name,
                    'debit' => $amount,
                    'credit' => 0,
                ]);

                // 2) Client Account (Credit)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'إقفال مديونية العميل - '.$client->trade_name,
                    'debit' => 0,
                    'credit' => $amount,
                ]);

                // Zero out client account balance
                $clientAccount->balance = 0;
                $clientAccount->save();

                $totalWrittenOff += $amount;
                $processedCount++;
            }

            // Log the batch action
            ModelsLog::create([
                'module' => 'العملاء',
                'action' => 'تسوية ديون معدومة جماعية للفرع',
                'description' => 'تسوية ديون معدومة جماعية للفرع: '.$branch->name.
                                ' - عدد العملاء المعالجين: '.$processedCount.
                                ' - إجمالي المبلغ المسوى: '.number_format($totalWrittenOff, 2).' ريال'.
                                ' - السبب: '.$validated['reason'],
                'reference_id' => $branch->id,
                'reference_type' => 'Branch',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            $message = 'تم تسوية الديون المعدومة بنجاح للفرع: '.$branch->name.
                      ' | عدد العملاء المعالجين: '.$processedCount.
                      ' | إجمالي المبلغ المسوى: '.number_format($totalWrittenOff, 2).' ريال';

            if ($skippedCount > 0) {
                $message .= ' | عدد العملاء المتخطاهم (بدون ديون): '.$skippedCount;
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('خطأ في تسوية ديون معدومة جماعية', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إضافة ملاحظة جديدة من موظف العميل
     */
    public function storeEmployeeNote(Request $request, $id)
    {
        try {
            $request->validate([
                'employee_name' => 'required|string|max:255',
                'employee_position' => 'nullable|string|max:100',
                'employee_phone' => 'required|string|max:20',
                'is_primary' => 'nullable|boolean',
                'invoice_id' => 'nullable|exists:invoices,id',
                'note' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ], [
                'employee_name.required' => 'اسم الموظف إلزامي',
                'employee_phone.required' => 'رقم جوال الموظف إلزامي',
            ]);

            $user = auth()->user();
            $data = [
                'client_id' => $id,
                'employee_id' => $user->id,
                'employee_name' => $request->employee_name,
                'employee_position' => $request->employee_position ?? 'موظف',
                'employee_phone' => $request->employee_phone,
                'is_primary' => $request->is_primary ? true : false,
                'invoice_id' => $request->invoice_id,

            ];

            // معالجة الصورة وضغطها إن وجدت
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time().'_'.uniqid().'.jpg';
                $destinationPath = public_path('uploads/client_notes');

                // إنشاء المجلد إذا لم يكن موجوداً
                if (! file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // ضغط الصورة وحفظها بجودة 70%
                $this->compressAndSaveImage($image->getRealPath(), $destinationPath.'/'.$imageName, 70);
                $data['image'] = 'uploads/client_notes/'.$imageName;
            }

            $note = ClientEmployeeNote::create($data);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الملاحظة بنجاح',
                'note' => [
                    'id' => $note->id,
                    'note' => $note->note,
                    'image' => $note->image,
                    'employee_name' => $note->employee_name,
                    'employee_position' => $note->employee_position,
                    'employee_phone' => $note->employee_phone,
                    'is_primary' => $note->is_primary,
                    'invoice_id' => $note->invoice_id,
                    'created_at' => $note->created_at->format('Y-m-d H:i'),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'storeEmployeeNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب ملاحظات العميل من الموظفين
     */
    public function getEmployeeNotes($id)
    {
        try {
            $notes = ClientEmployeeNote::where('client_id', $id)
                ->with('employee') // جلب بيانات موظف النظام الذي أنشأ الملاحظة
                ->latest()
                ->get()
                ->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'note' => $note->note,
                        'image' => $note->image,
                        'employee_name' => $note->employee_name ?? 'غير محدد', // موظف العميل
                        'employee_position' => $note->employee_position ?? 'موظف', // وظيفة موظف العميل
                        'created_by' => $note->employee->name ?? 'غير معروف', // موظف النظام الذي أنشأ الملاحظة
                        'created_at' => $note->created_at->format('Y-m-d H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'notes' => $notes,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'getEmployeeNotes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ضغط الصورة وتحجيمها وحفظها - استخدام GD للضغط الفعلي
     */
    private function compressAndSaveImage($sourceFile, $destinationPath, $quality = 70)
    {
        try {
            $imageInfo = getimagesize($sourceFile);
            if (! $imageInfo) {
                return false;
            }

            $mime = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];

            // إنشاء الصورة حسب النوع
            switch ($mime) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($sourceFile);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($sourceFile);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($sourceFile);
                    break;
                default:
                    $image = imagecreatefromjpeg($sourceFile);
            }

            // تحجيم الصورة إذا كانت أكبر من 1920x1920
            $maxDimension = 1920;
            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                $ratio = min($maxDimension / $originalWidth, $maxDimension / $originalHeight);
                $newWidth = intval($originalWidth * $ratio);
                $newHeight = intval($originalHeight * $ratio);

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled(
                    $resizedImage,
                    $image,
                    0, 0, 0, 0,
                    $newWidth, $newHeight,
                    $originalWidth, $originalHeight
                );
                imagedestroy($image);
                $image = $resizedImage;
            }

            // تعيين الامتداد الصحيح
            if (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'png') {
                imagepng($image, $destinationPath, intval($quality / 10)); // جودة PNG 0-9
            } elseif (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'gif') {
                imagegif($image, $destinationPath);
            } else {
                // حفظ بصيغة JPEG مع جودة منخفضة للضغط الأفضل
                imagejpeg($image, $destinationPath, intval($quality));
            }

            imagedestroy($image);

            return true;
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'compressAndSaveImage');

            return false;
        }
    }

    /**
     * عرض صفحة تعديل الملاحظة
     */
    public function editNote($noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::with(['client', 'employee', 'clientEmployee'])->findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                   ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل هذه الملاحظة');
        }

        $client = $note->client;

        return view('client::notes.edit_client_note', compact('note', 'client'));
    }

    /**
     * تحديث الملاحظة
     */
    public function updateNote(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                   ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل هذه الملاحظة');
        }

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'client_employee_id' => 'required|exists:client_employee_notes,id',
            'description' => 'required|string',
            'deposit_count' => 'required|integer|min:0',
            'site_type' => 'required|in:independent_booth,grocery,supplies,markets,station',
            'competitor_documents' => 'required|integer|min:0',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png|max:102400',
        ], [
            'client_employee_id.required' => 'حقل موظف العميل إلزامي',
            'client_employee_id.exists' => 'موظف العميل المحدد غير صالح',
            'deposit_count.required' => 'حقل عدد العهدة إلزامي',
            'site_type.required' => 'حقل نوع الموقع إلزامي',
            'site_type.in' => 'نوع الموقع غير صالح',
            'competitor_documents.required' => 'حقل عدد استندات المنافسين إلزامي',
            'attachments.*.mimes' => 'يجب أن تكون المرفقات صور فقط (jpg, jpeg, png)',
        ]);

        DB::beginTransaction();

        try {
            // تحديث البيانات الأساسية
            $note->update([
                'description' => $request->description,
                'client_employee_id' => $request->client_employee_id,
                'deposit_count' => $request->deposit_count,
                'site_type' => $request->site_type,
                'competitor_documents' => $request->competitor_documents,
                'additional_data' => json_encode([
                    'deposit_count' => $request->deposit_count,
                    'site_type' => $request->site_type,
                    'competitor_documents' => $request->competitor_documents,
                ]),
            ]);

            // معالجة المرفقات الجديدة إذا وجدت
            if ($request->hasFile('attachments')) {
                $existingAttachments = json_decode($note->attachments, true) ?? [];
                $attachments = $existingAttachments;

                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $destinationPath = public_path('assets/uploads/notes');
                        $filename = time().'_'.uniqid().'.jpg';

                        // ضغط وحفظ الصورة
                        $originalSize = $file->getSize();
                        $quality = 75;
                        if ($originalSize > 10 * 1024 * 1024) {
                            $quality = 40;
                        } elseif ($originalSize > 5 * 1024 * 1024) {
                            $quality = 50;
                        } elseif ($originalSize > 2 * 1024 * 1024) {
                            $quality = 60;
                        }

                        $this->compressAndSaveImage($file->path(), $destinationPath.'/'.$filename, $quality);
                        $attachments[] = $filename;
                    }
                }

                $note->attachments = json_encode($attachments);
                $note->save();
            }

            // تسجيل السجل
            ModelsLog::create([
                'type' => 'client_note_update',
                'type_log' => 'log',
                'description' => 'تم تعديل ملاحظة للعميل: '.$note->client->trade_name,
                'created_by' => auth()->id(),
                'related_id' => $note->client_id,
                'related_type' => Client::class,
            ]);

            DB::commit();

            return redirect()
                ->route('clients.show', $note->client_id)
                ->with('success', 'تم تعديل الملاحظة بنجاح!');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'updateNote');

            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'فشل تعديل الملاحظة: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * حذف الملاحظة
     */
    public function destroyNote($noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canDelete = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                     ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canDelete) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية حذف هذه الملاحظة');
        }

        $clientId = $note->client_id;
        $clientName = $note->client->trade_name ?? 'غير معروف';

        DB::beginTransaction();

        try {
            // حذف المرفقات من الخادم
            $attachments = json_decode($note->attachments, true);
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    $filePath = public_path('assets/uploads/notes/'.$attachment);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            // حذف الملاحظة
            $note->delete();

            // تسجيل السجل
            ModelsLog::create([
                'type' => 'client_note_delete',
                'type_log' => 'log',
                'description' => 'تم حذف ملاحظة للعميل: '.$clientName,
                'created_by' => auth()->id(),
                'related_id' => $clientId,
                'related_type' => Client::class,
            ]);

            DB::commit();

            return redirect()
                ->route('clients.show', $clientId)
                ->with('toast_message', 'تم حذف الملاحظة بنجاح!')
                ->with('toast_type', 'success');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Clientbackup', 'destroyNote');

            DB::rollBack();

            return redirect()
                ->back()
                ->with('toast_message', 'فشل حذف الملاحظة: '.$e->getMessage())
                ->with('toast_type', 'error');
        }
    }

    /**
     * حذف مرفق من الملاحظة
     */
    public function deleteNoteAttachment(Request $request, $noteId)
    {
        $user = auth()->user();
        $note = ClientRelation::findOrFail($noteId);

        // التحقق من الصلاحيات
        $canEdit = $user->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                   ($user->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->employee_id == $user->id);

        if (! $canEdit) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذه الملاحظة'], 403);
        }

        $attachmentIndex = $request->input('attachment_index');
        $attachments = json_decode($note->attachments, true) ?? [];

        if (! isset($attachments[$attachmentIndex])) {
            return response()->json(['success' => false, 'message' => 'المرفق غير موجود'], 404);
        }

        // حذف الملف من الخادم
        $filePath = public_path('assets/uploads/notes/'.$attachments[$attachmentIndex]);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // إزالة من المصفوفة
        array_splice($attachments, $attachmentIndex, 1);
        $note->attachments = json_encode(array_values($attachments));
        $note->save();

        return response()->json(['success' => true, 'message' => 'تم حذف المرفق بنجاح']);
    }
}
