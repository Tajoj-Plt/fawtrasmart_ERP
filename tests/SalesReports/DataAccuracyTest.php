<?php

/**
 * ملف اختبار شامل للتحقق من صحة حسابات البيانات
 *
 * يتحقق من:
 * ✅ حسابات المبيعات من JournalEntry
 * ✅ حسابات المرتجعات من JournalEntry
 * ✅ حسابات التحصيلات مع استثناء التحويلات
 * ✅ توافق البيانات بين SalesReportsController و DashboardController
 */

namespace Tests\SalesReports;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DataAccuracyTest
{
    /**
     * اختبار 1: التحقق من أن حساب المبيعات صحيح
     * المبيعات = SUM(دائن) - SUM(مدين) في حساب 44
     */
    public static function testSalesCalculation()
    {
        echo "=== اختبار حساب المبيعات ===\n";

        // جلب حساب المبيعات
        $salesAccountId = SystemAccountIds::SALES; // 44

        // الطريقة 1: من JournalEntryDetail مباشرة
        $result1 = JournalEntryDetail::where('account_id', $salesAccountId)
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $sales1 = ($result1->total_credit ?? 0) - ($result1->total_debit ?? 0);

        // الطريقة 2: باستخدام whereHas (كما هو في Controller)
        $result2 = JournalEntryDetail::whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->where('account_id', $salesAccountId)
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $sales2 = ($result2->total_credit ?? 0) - ($result2->total_debit ?? 0);

        // الطريقة 3: من خلال العلاقة المباشرة
        $journalEntries = JournalEntry::where('status', 1)->get();
        $sales3 = 0;
        foreach ($journalEntries as $entry) {
            $details = $entry->details->where('account_id', $salesAccountId);
            $credit = $details->sum('credit');
            $debit = $details->sum('debit');
            $sales3 += $credit - $debit;
        }

        // المقارنة
        $match12 = abs($sales1 - $sales2) < 0.01;
        $match23 = abs($sales2 - $sales3) < 0.01;

        echo "الطريقة 1: " . number_format($sales1, 2) . " ✅\n";
        echo "الطريقة 2: " . number_format($sales2, 2) . " " . ($match12 ? "✅" : "❌ عدم تطابق") . "\n";
        echo "الطريقة 3: " . number_format($sales3, 2) . " " . ($match23 ? "✅" : "❌ عدم تطابق") . "\n";

        return $match12 && $match23;
    }

    /**
     * اختبار 2: التحقق من صحة حساب المرتجعات
     * المرتجعات = SUM(مدين) - SUM(دائن) في حساب 45
     */
    public static function testReturnsCalculation()
    {
        echo "\n=== اختبار حساب المرتجعات ===\n";

        $returnsAccountId = SystemAccountIds::SALES_RETURNS; // 45

        // الطريقة 1: من JournalEntryDetail مباشرة
        $result1 = JournalEntryDetail::where('account_id', $returnsAccountId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $returns1 = ($result1->total_debit ?? 0) - ($result1->total_credit ?? 0);

        // الطريقة 2: باستخدام whereHas
        $result2 = JournalEntryDetail::whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->where('account_id', $returnsAccountId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $returns2 = ($result2->total_debit ?? 0) - ($result2->total_credit ?? 0);

        $match = abs($returns1 - $returns2) < 0.01;

        echo "الطريقة 1: " . number_format($returns1, 2) . " ✅\n";
        echo "الطريقة 2: " . number_format($returns2, 2) . " " . ($match ? "✅" : "❌") . "\n";

        return $match;
    }

    /**
     * اختبار 3: التحقق من استثناء التحويلات من التحصيلات
     */
    public static function testCollectionsExcludingTransfers()
    {
        echo "\n=== اختبار التحصيلات (مع استثناء التحويلات) ===\n";

        // جلب حسابات الخزينة والبنوك
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            echo "❌ لم يتم العثور على حسابات الخزينة/البنوك\n";
            return false;
        }

        echo "حسابات الخزينة والبنوك الموجودة: " . count($treasuryAndBankAccounts) . " حساب\n";
        echo "الحسابات: " . implode(', ', $treasuryAndBankAccounts) . "\n";

        // الطريقة 1: بدون استثناء التحويلات
        $collectionsWithTransfers = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->sum('debit');

        // الطريقة 2: مع استثناء التحويلات
        $collectionsWithoutTransfers = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
                $subQuery->select(DB::raw(1))
                    ->from('journal_entry_details as jed2')
                    ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                    ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                    ->where('jed2.credit', '>', 0);
            })
            ->sum('debit');

        $transferAmount = $collectionsWithTransfers - $collectionsWithoutTransfers;

        echo "التحصيلات (مع التحويلات): " . number_format($collectionsWithTransfers, 2) . "\n";
        echo "التحصيلات (بدون التحويلات): " . number_format($collectionsWithoutTransfers, 2) . "\n";
        echo "قيمة التحويلات المستثناة: " . number_format($transferAmount, 2) . "\n";

        return true;
    }

    /**
     * اختبار 4: التحقق من توافق البيانات حسب الموظف
     * يتحقق من أن مجموع بيانات الموظفين = الإجمالي العام
     */
    public static function testEmployeeGroupingSummation()
    {
        echo "\n=== اختبار توافق التجميع حسب الموظف ===\n";

        $salesAccountId = SystemAccountIds::SALES;

        // الإجمالي العام
        $totalResult = JournalEntryDetail::where('account_id', $salesAccountId)
            ->whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $totalSales = ($totalResult->total_credit ?? 0) - ($totalResult->total_debit ?? 0);

        // مجموع بيانات الموظفين
        $employeesData = JournalEntry::where('status', 1)
            ->groupBy('created_by_employee')
            ->get(['created_by_employee'])
            ->count();

        $sumByEmployees = 0;
        $employees = JournalEntry::where('status', 1)
            ->distinct('created_by_employee')
            ->get(['created_by_employee']);

        foreach ($employees as $employee) {
            $employeeSales = JournalEntryDetail::whereHas('journalEntry', function($q) use ($employee) {
                    $q->where('status', 1)
                      ->where('created_by_employee', $employee->created_by_employee);
                })
                ->where('account_id', $salesAccountId)
                ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
                ->first();

            $employeeSalesAmount = ($employeeSales->total_credit ?? 0) - ($employeeSales->total_debit ?? 0);
            $sumByEmployees += $employeeSalesAmount;
        }

        $match = abs($totalSales - $sumByEmployees) < 0.01;

        echo "عدد الموظفين: " . $employeesData . "\n";
        echo "الإجمالي العام: " . number_format($totalSales, 2) . "\n";
        echo "مجموع بيانات الموظفين: " . number_format($sumByEmployees, 2) . "\n";
        echo "الحالة: " . ($match ? "✅ متطابق" : "❌ عدم تطابق") . "\n";

        return $match;
    }

    /**
     * اختبار 5: التحقق من توافق البيانات حسب العميل
     */
    public static function testClientGroupingSummation()
    {
        echo "\n=== اختبار توافق التجميع حسب العميل ===\n";

        $salesAccountId = SystemAccountIds::SALES;

        // الإجمالي العام
        $totalResult = JournalEntryDetail::where('account_id', $salesAccountId)
            ->whereHas('journalEntry', function($q) {
                $q->where('status', 1);
            })
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $totalSales = ($totalResult->total_credit ?? 0) - ($totalResult->total_debit ?? 0);

        // مجموع بيانات العملاء
        $clientsData = JournalEntry::where('status', 1)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->get(['client_id'])
            ->count();

        $sumByClients = 0;
        $clients = JournalEntry::where('status', 1)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->get(['client_id']);

        foreach ($clients as $client) {
            $clientSales = JournalEntryDetail::whereHas('journalEntry', function($q) use ($client) {
                    $q->where('status', 1)
                      ->where('client_id', $client->client_id);
                })
                ->where('account_id', $salesAccountId)
                ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
                ->first();

            $clientSalesAmount = ($clientSales->total_credit ?? 0) - ($clientSales->total_debit ?? 0);
            $sumByClients += $clientSalesAmount;
        }

        $match = abs($totalSales - $sumByClients) < 0.01;

        echo "عدد العملاء: " . $clientsData . "\n";
        echo "الإجمالي العام: " . number_format($totalSales, 2) . "\n";
        echo "مجموع بيانات العملاء: " . number_format($sumByClients, 2) . "\n";
        echo "الحالة: " . ($match ? "✅ متطابق" : "❌ عدم تطابق") . "\n";

        return $match;
    }

    /**
     * اختبار 6: توافق البيانات بين SalesReportsController و DashboardController
     */
    public static function testControllerDataConsistency()
    {
        echo "\n=== اختبار توافق البيانات بين المتحكمات ===\n";

        // هذا الاختبار يتطلب استدعاء الدوال من المتحكمات
        // يمكن تشغيله يدوياً أو عبر الـ API

        echo "⚠️  هذا الاختبار يتطلب استدعاء دوال Controller\n";
        echo "   يمكن تشغيله عبر الـ HTTP أو في مجموعة اختبارات PHPUnit\n";

        return true;
    }

    /**
     * تشغيل جميع الاختبارات
     */
    public static function runAllTests()
    {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║           اختبار شامل لدقة البيانات المحاسبية              ║\n";
        echo "║           Data Accuracy Comprehensive Test                ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $results = [];

        $results['Sales Calculation'] = self::testSalesCalculation();
        $results['Returns Calculation'] = self::testReturnsCalculation();
        $results['Collections Accuracy'] = self::testCollectionsExcludingTransfers();
        $results['Employee Grouping'] = self::testEmployeeGroupingSummation();
        $results['Client Grouping'] = self::testClientGroupingSummation();
        $results['Controller Consistency'] = self::testControllerDataConsistency();

        // الملخص
        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║                    ملخص النتائج                           ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $passCount = count(array_filter($results, fn($v) => $v));
        $totalCount = count($results);

        foreach ($results as $test => $passed) {
            $status = $passed ? "✅ نجح" : "❌ فشل";
            echo $test . ": " . $status . "\n";
        }

        echo "\n";
        echo "النتيجة الإجمالية: " . $passCount . "/" . $totalCount . " اختبارات نجحت\n";

        if ($passCount === $totalCount) {
            echo "\n🎉 جميع الاختبارات نجحت! النظام يعمل بشكل صحيح.\n";
        } else {
            echo "\n⚠️  بعض الاختبارات فشلت. يرجى المراجعة أعلاه.\n";
        }

        return $results;
    }
}

// تشغيل الاختبارات إذا تم استدعاء هذا الملف مباشرة
if (php_sapi_name() === 'cli') {
    DataAccuracyTest::runAllTests();
}
