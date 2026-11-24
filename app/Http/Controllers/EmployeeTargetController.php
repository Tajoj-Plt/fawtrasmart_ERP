<?php

namespace App\Http\Controllers;

use App\Models\CreditLimit;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\User;
use App\Models\Target;
use App\Models\EmployeeTarget;
use App\Models\Location;
use App\Models\Client;
use App\Models\Neighborhood;
use App\Models\Region_groub;
use App\Models\Statuses;
use Illuminate\Http\Request;
use App\Models\PaymentsProcess;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Expense;
use App\Models\ClientEmployee;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Constants\SystemAccountIds;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmployeeTargetController extends Controller
{
    public function index()
    {
        $employees = User::where('role', 'employee')->get();
        return view('employee_targets.index', compact('employees'));
    }

    public function showGeneralTarget()
    {
        $target = Target::firstOrCreate(['id' => 1], ['value' => 30000]);
        return view('employee_targets.general', compact('target'));
    }

    public function visitTarget()
    {
        $target = Target::firstOrCreate(['id' => 3], ['value' => 1000]);
        return view('employee_targets.visitTarget', compact('target'));
    }

    public function client_target_store(Request $request)
    {
        $request->validate([
            'value' => 'required|numeric',
        ]);

        $target = Target::updateOrCreate(['id' => 2], $request->only(['value']));
        return redirect()->back()->with('success', 'تم تحديث الهدف بنجاح');
    }

    public function client_target(Request $request)
    {
        $user = auth()->user();
        $baseQuery = Client::with(['employee', 'status_client:id,name,color', 'locations', 'Neighborhoodname.Region', 'branch:id,name']);
        $noClients = false;

        if ($user->isEmployee()) { // ✅ isEmployee() بدلاً من عمود role النصي
            $employeeGroupIds = EmployeeGroup::where('employee_id', $user->employee_id)->pluck('group_id');
            if ($employeeGroupIds->isNotEmpty()) {
                $baseQuery->whereHas('Neighborhoodname.Region', function ($q) use ($employeeGroupIds) {
                    $q->whereIn('id', $employeeGroupIds);
                });
            } else {
                $noClients = true;
            }
        }

        if ($request->filled('client')) {
            $baseQuery->where('id', $request->client);
        }
        if ($request->filled('name')) {
            $baseQuery->where('trade_name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('status')) {
            $baseQuery->where('status_id', $request->status);
        }
        if ($request->filled('region')) {
            $baseQuery->whereHas('Neighborhoodname.Region', function ($q) use ($request) {
                $q->where('id', $request->region);
            });
        }
        if ($request->filled('neighborhood')) {
            $baseQuery->whereHas('Neighborhoodname', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->neighborhood . '%')->orWhere('id', $request->neighborhood);
            });
        }

        $mapQuery = clone $baseQuery;
        $allClients = $noClients ? collect() : $mapQuery->with(['status_client:id,name,color', 'locations:id,client_id,latitude,longitude', 'Neighborhoodname.Region', 'branch:id,name'])->get();
        $userLocation = Location::where('employee_id', $user->employee_id)->latest()->first();
        $target = Target::find(2)->value ?? 648;
        $month = $request->input('month');
        $year = null;
        $monthNum = null;

        if ($month) {
            [$year, $monthNum] = explode('-', $month);
        }
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $clients = Client::with(['employee', 'Neighborhoodname.Region', 'branch', 'locations'])
            ->whereNotIn('status_id', ['2', '6'])
            ->get();

        $clients = $clients->map(function ($client) use ($target, $monthNum, $year, $dateFrom, $dateTo) {
            $returnedInvoiceIds = Invoice::whereNotNull('reference_number')->pluck('reference_number')->toArray();
            $excludedInvoiceIds = array_unique(array_merge($returnedInvoiceIds, Invoice::where('type', 'returned')->pluck('id')->toArray()));
            $invoiceIds = Invoice::where('client_id', $client->id)->where('type', 'normal')->whereNotIn('id', $excludedInvoiceIds)->pluck('id');
            $paymentsQuery = PaymentsProcess::whereIn('invoice_id', $invoiceIds);

            if ($monthNum && $year) {
                $paymentsQuery->whereMonth('created_at', $monthNum)->whereYear('created_at', $year);
            }
            if ($dateFrom && $dateTo) {
                $paymentsQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $paymentsTotal = $paymentsQuery->sum('amount');

            $receiptsTotal = Receipt::whereHas('account', function ($query) use ($client) {
                $query->where('client_id', $client->id);
            })->when($monthNum && $year, function ($query) use ($monthNum, $year) {
                $query->whereMonth('created_at', $monthNum)->whereYear('created_at', $year);
            });

            if ($dateFrom && $dateTo) {
                $receiptsTotal->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $receipts = $receiptsTotal->sum('amount');
            $collected = $paymentsTotal + $receipts;
            $percentage = $target > 0 ? round(($collected / $target) * 100, 2) : 0;

            if ($percentage > 100) {
                $group = 'G';
                $group_class = 'primary';
            } elseif ($percentage >= 60) {
                $group = 'K';
                $group_class = 'success';
            } elseif ($percentage >= 30) {
                $group = 'B';
                $group_class = 'warning';
            } elseif ($percentage >= 10) {
                $group = 'C';
                $group_class = 'danger';
            } else {
                $group = 'D';
                $group_class = 'secondary';
            }

            $client->collected = $collected;
            $client->percentage = $percentage;
            $client->payments = $paymentsTotal;
            $client->receipts = $receipts;
            $client->group = $group;
            $client->group_class = $group_class;

            return $client;
        })->sortByDesc('collected')->values();

        return view('employee_targets.client', [
            'clients' => $clients,
            'allClients' => $allClients,
            'month' => $month,
            'target' => $target,
            'Neighborhoods' => Neighborhood::all(),
            'users' => User::all(),
            'employees' => Employee::all(),
            'creditLimit' => CreditLimit::first(),
            'statuses' => Statuses::select('id', 'name', 'color')->get(),
            'Region_groups' => Region_groub::all(),
            'userLocation' => $userLocation,
        ]);
    }

    /**
     * تقرير التحصيل اليومي - محدث ليستخدم القيود المحاسبية
     * Generated by Copilot - دمج منطق حساب التحصيلات من تقرير أداء الموظف
     */
    public function daily_closing_entry(Request $request)
    {
        $isRangeSearch = false;

        // تحديد نوع البحث
        if ($request->from_date && $request->to_date) {
            $isRangeSearch = true;
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $toDate = Carbon::parse($request->to_date)->endOfDay();
            $selectedDate = null;
        } else {
            $selectedDate = $request->date ? Carbon::parse($request->date) : now();
            $fromDate = $selectedDate->copy()->startOfDay();
            $toDate = $selectedDate->copy()->endOfDay();
        }

        // إحضار جميع المستخدمين النشطين اللي role = employee أو manager
        // Generated by Copilot - فقط الموظفين النشطين
        $employees = User::whereIn('role', ['employee', 'manager'])
            ->whereHas('employee', function($query) {
                // من على رأس العمل — النشطون ومن هم تحت التجربة
                $query->active();
            })
            ->get();
        $defaultTarget = 10000;

        $cards = $employees->map(function ($user) use ($fromDate, $toDate, $defaultTarget) {
            $userId = $user->id;

            // التأكد من أن الموظف نشط
            if (!$user->employee || !$user->employee->isActive()) {
                return null;
            }

            // حساب التحصيلات من القيود المحاسبية (نفس طريقة أداء الموظف)
            $collections = $this->getEmployeeCollectionsFromJournal($userId, $fromDate, $toDate);

            // حساب المصروفات (الطريقة الحالية - من جدول Expense)
            $expensesTotal = Expense::where('created_by', $userId)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->sum('amount');

            $totalCollected = $collections - $expensesTotal;

            $target = $user->target?->monthly_target ?? $defaultTarget;
            $percentage = $target > 0 ? round(($totalCollected / $target) * 100, 2) : 0;

            return [
                'name' => $user->name ?? 'غير معروف',
                'payments' => 0, // لم يعد مستخدماً بشكل منفصل
                'receipts' => 0, // لم يعد مستخدماً بشكل منفصل
                'collections' => $collections, // التحصيلات من القيود المحاسبية
                'expenses' => $expensesTotal,
                'total' => $totalCollected,
                'target' => $target,
                'percentage' => $percentage,
            ];
        })->filter(); // تصفية القيم الـ null

        // ترتيب تنازلي حسب المحصل
        $cards = $cards->sortByDesc('total')->values();

        return view('daily_closing_entry', [
            'cards' => $cards,
            'selectedDate' => $selectedDate ? $selectedDate->toDateString() : null,
            'fromDate' => $request->from_date,
            'toDate' => $request->to_date,
            'isRangeSearch' => $isRangeSearch,
        ]);
    }

    /**
     * الحصول على تحصيلات الموظف من القيود المحاسبية
     * نفس المنطق المستخدم في تقرير أداء الموظف
     * Generated by Copilot - جميع القيود التي تكون فيها الخزينة أو البنك مدينة (دخول أموال)
     * مع استثناء التحويلات بين الخزائن/البنوك
     */
    private function getEmployeeCollectionsFromJournal($userId, $startDate, $endDate)
    {
        // جلب جميع حسابات الخزينة والبنوك
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,  // 13
            SystemAccountIds::BANK_ACCOUNTS_PARENT     // 15
        ])->pluck('id')->toArray();

        // جلب القيود المحاسبية للموظف في الفترة المحددة
        $journalEntryIds = JournalEntry::where('created_by_employee', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 1) // معتمد فقط
            ->pluck('id')
            ->toArray();

        if (empty($journalEntryIds)) {
            return 0;
        }

        // استثناء القيود التي تكون بين خزينتين/بنكين (تحويلات)
        // القيد يعتبر تحويل إذا كان كل من المدين والدائن من حسابات الخزينة/البنوك
        $transferEntryIds = [];
        foreach ($journalEntryIds as $entryId) {
            $details = JournalEntryDetail::where('journal_entry_id', $entryId)->get();

            $hasDebitTreasury = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('debit', '>', 0)->isNotEmpty();
            $hasCreditTreasury = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('credit', '>', 0)->isNotEmpty();

            // إذا كان المدين والدائن كلاهما خزينة/بنك = تحويل
            if ($hasDebitTreasury && $hasCreditTreasury) {
                $transferEntryIds[] = $entryId;
            }
        }

        // استثناء التحويلات
        $validJournalEntryIds = array_diff($journalEntryIds, $transferEntryIds);

        if (empty($validJournalEntryIds)) {
            return 0;
        }

        // حساب التحصيلات: فقط المدين (دخول أموال) في حسابات الخزينة والبنوك
        $collectionsData = JournalEntryDetail::whereIn('journal_entry_id', $validJournalEntryIds)
            ->whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0) // فقط عندما تكون الخزينة/البنك مدينة
            ->selectRaw('SUM(debit) as total_debit')
            ->first();

        return $collectionsData->total_debit ?? 0;
    }

    /**
     * جلب جميع حسابات المصروفات بشكل تكراري
     * Generated by Copilot
     */
    private function getAllExpenseAccountsRecursive()
    {
        $expenseAccountIds = [];

        // البحث عن الحسابات بناءً على أصناف المصروفات
        $expenseCategories = \App\Models\ExpensesCategory::where('status', 'active')->get();

        foreach ($expenseCategories as $category) {
            $matchingAccounts = Account::where(function($query) use ($category) {
                $query->where('name', 'LIKE', '%' . $category->name . '%')
                      ->orWhere('name', $category->name);
            })
            ->where('is_active', 1)
            ->pluck('id')
            ->toArray();

            $expenseAccountIds = array_merge($expenseAccountIds, $matchingAccounts);
        }

        // البحث التكراري من حساب المصروفات الرئيسي
        $parentIds = [SystemAccountIds::EXPENSES]; // 5
        $expenseAccountIds[] = SystemAccountIds::EXPENSES;

        while (!empty($parentIds)) {
            $children = Account::whereIn('parent_id', $parentIds)
                ->where('is_active', 1)
                ->pluck('id')
                ->toArray();

            if (empty($children)) {
                break;
            }

            $expenseAccountIds = array_merge($expenseAccountIds, $children);
            $parentIds = $children;
        }

        return array_unique($expenseAccountIds);
    }

    public function updatevisitTarget(Request $request)
    {
        $request->validate([
            'value' => 'required|numeric',
        ]);

        $target = Target::updateOrCreate(['id' => 3], $request->only(['value']));
        return redirect()->back()->with('success', 'تم تحديث الهدف بنجاح');
    }

    public function updateGeneralTarget(Request $request)
    {
        $request->validate([
            'value' => 'required|numeric',
        ]);

        $target = Target::updateOrCreate(['id' => 1], $request->only(['value']));
        return redirect()->back()->with('success', 'تم تحديث الهدف بنجاح');
    }

    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'targets' => 'required|array',
            'targets.*.user_id' => 'required|exists:users,id',
            'targets.*.monthly_target' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->targets as $targetData) {
            if (!is_numeric($targetData['monthly_target'])) {
                continue;
            }

            EmployeeTarget::updateOrCreate(
                ['user_id' => $targetData['user_id']],
                ['monthly_target' => $targetData['monthly_target']]
            );
        }

        return redirect()->route('employee_targets.index')->with('success', 'تم تحديث التارقت بنجاح!');
    }
}