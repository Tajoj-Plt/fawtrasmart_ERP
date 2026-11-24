<?php

namespace Modules\Sales\Http\Controllers;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\SalesTargetSettlement;
use App\Models\TargetSetting;
use App\Models\TreasuryEmployee;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Support\CurrencySettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyCommissionController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم بلا حراسةٍ إطلاقاً — لا في بانيةٍ ولا على مسار —
            و`payout`/`payoutAll` تصرفان مالاً من الخزينة وتكتبان قيداً.

            وقصرُ الشاشة على عمولات الضاغط نفسِه (`$user->employee_id`) قيدُ
            **نطاق** لا حراسة: يمنع أن يصرف أحدٌ لغيره، ولا يمنع من لا شأن له
            بالمبيعات أن يفتحها أصلاً.

            والصرفُ بصلاحيةٍ مستقلّة عن العرض: منشأةٌ تريد أن يرى المندوبُ ما
            استحقّ ولا يصرفه بيده تمنح الأولى وتمنع الثانية.
        */
        $this->middleware('permission:sales.commissions.view', ['only' => ['index', 'history']]);
        $this->middleware('permission:sales.commissions.payout', ['only' => ['payout', 'payoutAll']]);
        // والصرفُ صرفُ عمولة: «صرف العمولة» من مجموعة الموارد البشرية فوق
        // صلاحية المبيعات («و» — الأدوارُ الحاملةُ للأولى تحمل الثانية).
        $this->middleware('permission.role:commissions.disburse', ['only' => ['payout', 'payoutAll']]);
    }

    /**
     * العمولات الأسبوعية المعلّقة لهذا الموظف — تعريفٌ واحد للشاشة وللصرف.
     *
     * كانت الشروط مكتوبةً ثلاث مرّات. و«معلّقة» تعني: لم تُضَف إلى راتب، ولم
     * تُصرف من هنا، **ولم يُصرف لها قيدٌ في التسوية نفسِها**. الشرط الأخير
     * هو الجديد: `TargetSettlementService` يصرف بسندٍ وقيد، ومع «يتطلب
     * اعتماداً» يكتب التسويةَ غيرَ مسوّاة — فكانت تظهر هنا وتُصرف ثانيةً.
     */
    private function pendingWeekly(int $employeeId): Builder
    {
        return SalesTargetSettlement::where('employee_id', $employeeId)
            ->where('is_settled', false)
            ->where('paid_directly', false)
            ->whereNull('direct_journal_entry_id')
            ->where('commission_amount', '>', 0)
            ->whereHas('salesTarget', function ($q) {
                $q->where('commission_period', 'weekly');
            });
    }

    /**
     * عرض صفحة عمولاتي الأسبوعية
     */
    public function index()
    {
        $user = Auth::user();
        $employeeId = $user->employee_id;

        // إذا لم يكن هناك employee_id، نعرض الصفحة مع رسالة
        if (! $employeeId) {
            $pendingCommissions = collect();
            $paidCommissions = collect();
            $treasury = null;
            $totalPending = 0;
            $totalPaidThisMonth = 0;
            $noEmployee = true;

            return view('sales::weekly-commissions.index', compact(
                'pendingCommissions',
                'paidCommissions',
                'treasury',
                'totalPending',
                'totalPaidThisMonth',
                'noEmployee'
            ));
        }

        $noEmployee = false;

        // جلب العمولات الأسبوعية غير المصروفة
        $pendingCommissions = $this->pendingWeekly((int) $employeeId)
            ->with(['salesTarget:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        // جلب العمولات المصروفة (آخر 30 يوم)
        $paidCommissions = SalesTargetSettlement::where('employee_id', $employeeId)
            ->where('paid_directly', true)
            ->where('direct_paid_at', '>=', Carbon::now()->subDays(30))
            ->whereHas('salesTarget', function ($q) {
                $q->where('commission_period', 'weekly');
            })
            ->with(['salesTarget:id,name'])
            ->orderBy('direct_paid_at', 'desc')
            ->get();

        // جلب خزينة الموظف
        $treasury = null;
        $treasuryEmployee = TreasuryEmployee::where('employee_id', $employeeId)->first();
        if ($treasuryEmployee) {
            $treasury = Account::find($treasuryEmployee->treasury_id);
        }

        // إحصائيات
        $totalPending = $pendingCommissions->sum('commission_amount');
        $totalPaidThisMonth = $paidCommissions->sum('commission_amount');

        return view('sales::weekly-commissions.index', compact(
            'pendingCommissions',
            'paidCommissions',
            'treasury',
            'totalPending',
            'totalPaidThisMonth',
            'noEmployee'
        ));
    }

    /**
     * صرف عمولة أسبوعية
     */
    public function payout(Request $request, $id)
    {
        $user = Auth::user();
        $employeeId = $user->employee_id;

        /* الفحوص قبل فتح المعاملة: كانت داخلها، فكلُّ ردٍّ مبكّر يترك
           معاملةً مفتوحة بلا `rollBack`. */
        if (! $employeeId) {
            return redirect()->back()->with('error', 'ليس لديك حساب موظف مرتبط');
        }

        // جلب خزينة الموظف
        $treasuryEmployee = TreasuryEmployee::where('employee_id', $employeeId)->first();
        if (! $treasuryEmployee || ! $treasuryEmployee->treasury_id) {
            return redirect()->back()->with('error', 'لا توجد خزينة مرتبطة بحسابك');
        }

        $employeeTreasury = Account::find($treasuryEmployee->treasury_id);
        if (! $employeeTreasury) {
            return redirect()->back()->with('error', 'خزينة الموظف غير موجودة');
        }

        /* حسابُ العمولات من «إعدادات الأهداف» — الحسابُ نفسُه الذي تُقيِّد
           عليه التسويةُ (`TargetSettlementService`). كان يُبحث عنه بالاسم
           («عمولات الموظفين» ثم «عمولات المبيعات») فيُقيَّد الصرفُ الأسبوعيّ
           على حسابٍ غير المضبوط. */
        $commissionsAccount = Account::find(TargetSetting::for(TargetSetting::TYPE_SALES)->commission_account_id);
        if (! $commissionsAccount) {
            return redirect()->back()->with('error', 'حساب العمولة غير مضبوط في إعدادات الأهداف');
        }

        try {
            DB::beginTransaction();

            /* التسويةُ مقفلةٌ حتى نهاية الصرف: ضغطتان متتاليتان على «صرف» —
               أو «صرف» مع «صرف الكل» — كانتا تقرآنها معلّقةً معاً فتكتبان
               قيدين. الثانيةُ تنتظر، ثم لا تجدها معلّقة. */
            $settlement = $this->pendingWeekly((int) $employeeId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            // ⛔ كفايةُ رصيد الخزينة تحت قفل — قبل القيد؛ الخزينةُ لا تصير سالبة
            TreasuryBalanceGuard::lockAndAssert($employeeTreasury->id, (float) $settlement->commission_amount, 'صرف العمولة');

            // إنشاء القيد المحاسبي
            $journalEntry = JournalEntry::create([
                'reference_number' => 'WCOM-'.$settlement->id.'-'.time(),
                'date' => now(),
                'description' => 'صرف عمولة أسبوعية - '.($settlement->salesTarget->name ?? 'هدف مبيعات'),
                'status' => 1,
                'currency' => CurrencySettings::code(),
                'created_by_employee' => $user->id,
                'reference_type' => ReferenceType::EXPENSES,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // مدين: عمولات الموظفين (مصروف)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $commissionsAccount->id,
                'description' => 'عمولة أسبوعية - '.($settlement->salesTarget->name ?? ''),
                'debit' => $settlement->commission_amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // دائن: خزينة الموظف (نقد)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $employeeTreasury->id,
                'description' => 'صرف عمولة من '.$employeeTreasury->name,
                'debit' => 0,
                'credit' => $settlement->commission_amount,
                'is_debit' => false,
            ]);

            // تحديث التسوية
            $settlement->update([
                'paid_directly' => true,
                'direct_journal_entry_id' => $journalEntry->id,
                'direct_paid_at' => now(),
            ]);

            DB::commit();

            Log::info('تم صرف عمولة أسبوعية', [
                'settlement_id' => $settlement->id,
                'employee_id' => $employeeId,
                'amount' => $settlement->commission_amount,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return redirect()->back()->with('success',
                'تم صرف العمولة بنجاح: '.number_format($settlement->commission_amount, 2).' ر.س'
            );

        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'هذه العمولة صُرفت من قبل أو لم تعد معلّقة');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في صرف العمولة الأسبوعية: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء صرف العمولة: '.$e->getMessage());
        }
    }

    /**
     * صرف جميع العمولات المعلقة
     */
    public function payoutAll(Request $request)
    {
        $user = Auth::user();
        $employeeId = $user->employee_id;

        // الفحوص قبل فتح المعاملة — انظر `payout()`
        if (! $employeeId) {
            return redirect()->back()->with('error', 'ليس لديك حساب موظف مرتبط');
        }

        // جلب خزينة الموظف
        $treasuryEmployee = TreasuryEmployee::where('employee_id', $employeeId)->first();
        if (! $treasuryEmployee || ! $treasuryEmployee->treasury_id) {
            return redirect()->back()->with('error', 'لا توجد خزينة مرتبطة بحسابك');
        }

        $employeeTreasury = Account::find($treasuryEmployee->treasury_id);
        if (! $employeeTreasury) {
            return redirect()->back()->with('error', 'خزينة الموظف غير موجودة');
        }

        /* حسابُ العمولات من «إعدادات الأهداف» — الحسابُ نفسُه الذي تُقيِّد
           عليه التسويةُ (`TargetSettlementService`). كان يُبحث عنه بالاسم
           («عمولات الموظفين» ثم «عمولات المبيعات») فيُقيَّد الصرفُ الأسبوعيّ
           على حسابٍ غير المضبوط. */
        $commissionsAccount = Account::find(TargetSetting::for(TargetSetting::TYPE_SALES)->commission_account_id);
        if (! $commissionsAccount) {
            return redirect()->back()->with('error', 'حساب العمولة غير مضبوط في إعدادات الأهداف');
        }

        try {
            DB::beginTransaction();

            // مقفلةٌ حتى نهاية الصرف — إرسالٌ مزدوج لا يصرفها مرّتين
            $settlements = $this->pendingWeekly((int) $employeeId)
                ->lockForUpdate()
                ->get();

            if ($settlements->isEmpty()) {
                DB::rollBack();

                return redirect()->back()->with('warning', 'لا توجد عمولات معلقة للصرف');
            }

            $totalAmount = $settlements->sum('commission_amount');

            // ⛔ كفايةُ رصيد الخزينة للمجموع تحت قفل — قبل القيد
            TreasuryBalanceGuard::lockAndAssert($employeeTreasury->id, (float) $totalAmount, 'صرف العمولات');

            // إنشاء قيد محاسبي واحد لجميع العمولات
            $journalEntry = JournalEntry::create([
                'reference_number' => 'WCOM-BULK-'.time(),
                'date' => now(),
                'description' => 'صرف عمولات أسبوعية متعددة ('.$settlements->count().' عمولة)',
                'status' => 1,
                'currency' => CurrencySettings::code(),
                'created_by_employee' => $user->id,
                'reference_type' => ReferenceType::EXPENSES,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // مدين: عمولات الموظفين
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $commissionsAccount->id,
                'description' => 'عمولات أسبوعية متعددة',
                'debit' => $totalAmount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // دائن: خزينة الموظف
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $employeeTreasury->id,
                'description' => 'صرف عمولات من '.$employeeTreasury->name,
                'debit' => 0,
                'credit' => $totalAmount,
                'is_debit' => false,
            ]);

            // تحديث جميع التسويات
            foreach ($settlements as $settlement) {
                $settlement->update([
                    'paid_directly' => true,
                    'direct_journal_entry_id' => $journalEntry->id,
                    'direct_paid_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('تم صرف عمولات أسبوعية متعددة', [
                'employee_id' => $employeeId,
                'count' => $settlements->count(),
                'total_amount' => $totalAmount,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return redirect()->back()->with('success',
                'تم صرف '.$settlements->count().' عمولة بإجمالي '.number_format($totalAmount, 2).' ر.س'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في صرف العمولات الأسبوعية: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء صرف العمولات: '.$e->getMessage());
        }
    }

    /**
     * عرض سجل العمولات المصروفة
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $employeeId = $user->employee_id;

        if (! $employeeId) {
            return redirect()->back()->with('error', 'ليس لديك حساب موظف مرتبط');
        }

        $query = SalesTargetSettlement::where('employee_id', $employeeId)
            ->where('paid_directly', true)
            ->whereHas('salesTarget', function ($q) {
                $q->where('commission_period', 'weekly');
            })
            ->with(['salesTarget:id,name']);

        // فلترة بالتاريخ
        if ($request->filled('from_date')) {
            $query->whereDate('direct_paid_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('direct_paid_at', '<=', $request->to_date);
        }

        $commissions = $query->orderBy('direct_paid_at', 'desc')->paginate(20);

        return view('sales::weekly-commissions.history', compact('commissions'));
    }
}
