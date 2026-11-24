<?php

namespace Modules\Account\Http\Controllers\Cheques;

use App\Constants\SystemAccountIds;
use App\Enums\ChequeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayableChequeRequest;
use App\Models\Account;
use App\Models\ChequeBook;
use App\Models\Log as ModelsLog;
use App\Models\PayableCheque;
use App\Services\ChequeService;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Concerns\GuardsTreasuryAccess;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class PayableChequesController extends Controller
{
    use AccountingOperations;
    use GuardsTreasuryAccess;
    use ScopesToEmployeeHierarchy;

    protected ChequeService $chequeService;

    public function __construct(ChequeService $chequeService)
    {
        $this->chequeService = $chequeService;
        // صلاحيات الشيكات الصادرة — الكتالوج الجديد account.payable_cheques.*
        $this->middleware('permission.role:account.payable_cheques.view', ['only' => ['index', 'getCheckbooks', 'getCheckbookInfo']]);
        $this->middleware('permission.role:account.payable_cheques.show', ['only' => ['show']]);
        $this->middleware('permission.role:account.payable_cheques.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.payable_cheques.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.payable_cheques.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:account.payable_cheques.cash', ['only' => ['cash']]);
        $this->middleware('permission.role:account.payable_cheques.reject', ['only' => ['reject']]);
        $this->middleware('permission.role:account.payable_cheques.cancel', ['only' => ['cancel']]);
    }

    /**
     * عرض قائمة الشيكات الصادرة
     */
    public function index(Request $request)
    {
        // من أصدر الشيك هو أو من يديرهم — نفس نطاق سندات الصرف والقبض
        // (ScopesToEmployeeHierarchy)، بحسب التسلسل الإداري لا دورٍ ثابت.
        // وسياقُ الفرع من الشريط العلوي — المستند يخصّ الفرع الذي أُنشئ فيه
        $query = $this->scopeToCreator(
            PayableCheque::with(['bank', 'chequeBook', 'recipientAccount', 'supplier', 'journalEntry'])
        )->forBranch($this->headerBranchNode())->orderBy('id', 'desc');

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /*
            **الفترة علاقةٌ لا مدىً على تاريخ الاستحقاق.**

            الشيكُ يُنسَب إلى فترة **إصداره** — فهو الحدث المحاسبي — ويُختَم
            بها لحظةَ إنشائه. أمّا `due_date` فيبقى فلتراً تجارياً مستقلّاً:
            سجلُّ الشيكات يُفتح لرؤية ما يستحقّ لاحقاً، فحصرُه بفترة الإصدار
            وحدها يُخفي بالضبط ما جاء الباحثُ لأجله.
        */
        $period = AccountingPeriodFilter::resolve($request);
        $query = AccountingPeriodFilter::apply($query, $period);

        // الاستحقاق فلترٌ تجاريّ مستقلّ عن الفترة المحاسبية
        if ($request->filled('due_from')) {
            $query->whereDate('due_date', '>=', $request->due_from);
        }
        if ($request->filled('due_to')) {
            $query->whereDate('due_date', '<=', $request->due_to);
        }

        // فلترة حسب البنك
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }

        $payable_cheques = $query->get();
        $statuses = ChequeStatus::payableStatuses();
        $banks = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        return view('account::cheques.payable_cheques.index', compact('payable_cheques', 'statuses', 'banks'));
    }

    /**
     * صفحة إصدار شيك جديد
     */
    public function create()
    {
        // جلب دفاتر الشيكات النشطة مع بيانات البنك
        $check_books = ChequeBook::with('bank')
            ->where('status', 1) // نشط
            ->orderBy('id', 'desc')
            ->get();

        $accounts = $this->payableRecipientAccounts();

        return view('account::cheques.payable_cheques.create', compact('check_books', 'accounts'));
    }

    /**
     * الحسابات المرشَّحة لتكون "مستفيداً" من شيك صادر.
     *
     * شيك الدفع يُحرَّر لطرفٍ تدين له المنشأة — مورّد أو دائن أو مصروف —
     * لا لخزينتك أو بنكك أنت. كانت القائمة السابقة تضمّ كل حسابٍ طرفي في
     * الشجرة بلا استثناء (نحو 1700 حساب هنا)، فلا شيء يمنع اختيار «الخزينة
     * الرئيسية» نفسها مستفيداً — قيدٌ يزيد خزينتك ويزيد التزامك معاً بلا
     * معاملة حقيقية تقابله. استُبعدت الخزائن والبنوك تحديداً؛ الأنواع
     * الأخرى (موردون، عملاء، مصروفات، إلخ) تبقى كما هي.
     */
    private function payableRecipientAccounts()
    {
        $excludedParents = array_filter([
            AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT),
            AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT),
        ]);

        return Account::whereDoesntHave('children')
            ->whereNotIn('parent_id', $excludedParents)
            ->get();
    }

    /**
     * حفظ شيك صادر جديد
     */
    public function store(PayableChequeRequest $request)
    {
        // قيد الإصدار يُكتب بتاريخ تحرير الشيك
        $this->guardPeriod($request->input('issue_date'), 'issue_date');

        try {
            $attachmentPath = null;

            if ($request->hasFile('attachment')) {
                $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'rar', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf'];
                $maxFileSize = 5 * 1024 * 1024;

                $file = $request->file('attachment');
                $extension = $file->getClientOriginalExtension();
                $fileSize = $file->getSize();

                if (! in_array(strtolower($extension), $allowedExtensions)) {
                    return back()->withErrors(['attachment' => 'نوع الملف غير مدعوم.']);
                }

                if ($fileSize > $maxFileSize) {
                    return back()->withErrors(['attachment' => 'حجم الملف يجب أن لا يتجاوز 5 ميجابايت.']);
                }

                $attachmentPath = $file->store('assets/uploads/cheques/attachments', 'public');
            }

            // جلب دفتر الشيكات للحصول على البنك
            $chequeBook = ChequeBook::findOrFail($request->cheque_book_id);

            // استخدام ChequeService لإصدار الشيك مع القيد
            $cheque = $this->chequeService->issueCheque([
                'amount' => $request->amount,
                'cheque_number' => $request->cheque_number,
                'bank_id' => $chequeBook->bank_id, // جلب البنك من دفتر الشيكات
                'cheque_book_id' => $request->cheque_book_id,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'recipient_account_id' => $request->recipient_account_id,
                'supplier_id' => $request->supplier_id,
                'payee_name' => $request->payee_name,
                'description' => $request->description,
                'attachment' => $attachmentPath,
            ]);

            // تسجيل النشاط
            OperationLog::record(
                'created',
                'finance_log',
                $cheque->id,
                'تم إصدار شيك برقم **'.$request->cheque_number.'** بمبلغ **'.number_format($request->amount, 2).'**'
            );

            return redirect()
                ->route('payable_cheques.index')
                ->with(['success' => 'تم إصدار الشيك وإنشاء القيد المحاسبي بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.PayableCheques', 'store');

            Log::error('خطأ في إصدار الشيك: '.$e->getMessage());

            return back()->withErrors(['error' => 'حدث خطأ: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * عرض تفاصيل الشيك
     */
    public function show($id)
    {
        $payable_cheque = PayableCheque::with([
            'bank',
            'chequeBook',
            'recipientAccount',
            'supplier',
            'journalEntry.details.account',
            'cashJournalEntry.details.account',
        ])->findOrFail($id);

        $this->authorizeCreatorScope($payable_cheque->created_by);

        // سجل النشاطات
        $logs = ModelsLog::where('type', 'finance_log')
            ->where('type_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('account::cheques.payable_cheques.show', compact('payable_cheque', 'logs'));
    }

    /**
     * صفحة تعديل الشيك
     */
    public function edit($id)
    {
        $payable_cheque = PayableCheque::with('chequeBook.bank')->findOrFail($id);

        $this->authorizeCreatorScope($payable_cheque->created_by);

        // لا يمكن تعديل الشيك إذا تم صرفه
        if ($payable_cheque->status !== ChequeStatus::ISSUED) {
            return redirect()->route('payable_cheques.show', $id)
                ->with(['error' => 'لا يمكن تعديل هذا الشيك بعد معالجته']);
        }

        // جلب دفاتر الشيكات النشطة مع بيانات البنك
        $check_books = ChequeBook::with('bank')
            ->where('status', 1) // نشط
            ->get();

        $accounts = $this->payableRecipientAccounts();

        return view('account::cheques.payable_cheques.edit', compact(
            'payable_cheque',
            'check_books',
            'accounts'
        ));
    }

    /**
     * تحديث بيانات الشيك
     */
    public function update(PayableChequeRequest $request, $id)
    {
        $payable_cheque = PayableCheque::findOrFail($id);

        $this->authorizeCreatorScope($payable_cheque->created_by);

        $this->guardPeriod($payable_cheque->issue_date, 'issue_date');
        $this->guardPeriod($request->input('issue_date'), 'issue_date');

        // لا يمكن تعديل الشيك إذا تم صرفه
        if ($payable_cheque->status !== ChequeStatus::ISSUED) {
            return redirect()->route('payable_cheques.show', $id)
                ->with(['error' => 'لا يمكن تعديل هذا الشيك بعد معالجته']);
        }

        // جلب دفتر الشيكات للحصول على البنك
        $chequeBook = ChequeBook::findOrFail($request->cheque_book_id);

        $payable_cheque->amount = $request->amount;
        $payable_cheque->cheque_number = $request->cheque_number;
        $payable_cheque->bank_id = $chequeBook->bank_id; // جلب البنك من دفتر الشيكات
        $payable_cheque->cheque_book_id = $request->cheque_book_id;
        $payable_cheque->issue_date = $request->issue_date;
        $payable_cheque->due_date = $request->due_date;
        $payable_cheque->recipient_account_id = $request->recipient_account_id;
        $payable_cheque->supplier_id = $request->supplier_id;
        $payable_cheque->payee_name = $request->payee_name;
        $payable_cheque->description = $request->description;

        if ($request->hasFile('attachment')) {
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'rar', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf'];
            $maxFileSize = 5 * 1024 * 1024;

            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();

            if (! in_array(strtolower($extension), $allowedExtensions)) {
                return back()->withErrors(['attachment' => 'نوع الملف غير مدعوم.']);
            }

            if ($fileSize > $maxFileSize) {
                return back()->withErrors(['attachment' => 'حجم الملف يجب أن لا يتجاوز 5 ميجابايت.']);
            }

            $payable_cheque->attachment = $file->store('assets/uploads/cheques/attachments', 'public');
        }

        $payable_cheque->save();

        // تحديث القيد المحاسبي
        $this->updateJournalEntry($payable_cheque);

        OperationLog::record(
            'updated',
            'finance_log',
            $payable_cheque->id,
            'تم تعديل بيانات الشيك رقم **'.$payable_cheque->cheque_number.'**'
        );

        return redirect()
            ->route('payable_cheques.show', $id)
            ->with(['success' => 'تم تعديل الشيك الصادر بنجاح']);
    }

    /**
     * صرف الشيك
     */
    public function cash($id)
    {
        // الصرف حدثٌ اليوم — لا في تاريخ الشيك
        $this->guardPeriod(today());

        try {
            $cheque = PayableCheque::findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            /*
                صرف الشيك **سحبٌ** من الحساب البنكي: يُخصم منه المبلغ.
                وشرط السحب مضبوطٌ في شاشة إنشاء الحساب ولم يكن يُقرأ هنا —
                فمن لا يملك السحب من بنكٍ يستطيع إفراغه بصرف شيكاتٍ عليه.
            */
            $this->guardTreasuryWithdraw($cheque->bank_id, 'bank_id');

            $this->chequeService->cashPayableCheque($cheque);

            OperationLog::record(
                'cashed',
                'finance_log',
                $cheque->id,
                'تم صرف الشيك رقم **'.$cheque->cheque_number.'** بمبلغ **'.number_format($cheque->amount, 2).'**'
            );

            return redirect()
                ->route('payable_cheques.show', $id)
                ->with(['success' => 'تم صرف الشيك وإنشاء القيد المحاسبي بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.PayableCheques', 'cash');

            Log::error('خطأ في صرف الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * رفض الشيك
     */
    public function reject(Request $request, $id)
    {
        $this->guardPeriod(today());

        try {
            $cheque = PayableCheque::findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            $reason = $request->input('reason');

            $this->chequeService->rejectPayableCheque($cheque, $reason);

            OperationLog::record(
                'rejected',
                'finance_log',
                $cheque->id,
                'تم رفض الشيك رقم **'.$cheque->cheque_number.'**'.($reason ? ' - السبب: '.$reason : '')
            );

            return redirect()
                ->route('payable_cheques.show', $id)
                ->with(['success' => 'تم تسجيل رفض الشيك وإنشاء القيد العكسي']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.PayableCheques', 'reject');

            Log::error('خطأ في رفض الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * إلغاء الشيك
     */
    public function cancel(Request $request, $id)
    {
        $this->guardPeriod(today());

        try {
            $cheque = PayableCheque::findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            $reason = $request->input('reason');

            $this->chequeService->cancelPayableCheque($cheque, $reason);

            OperationLog::record(
                'cancelled',
                'finance_log',
                $cheque->id,
                'تم إلغاء الشيك رقم **'.$cheque->cheque_number.'**'.($reason ? ' - السبب: '.$reason : '')
            );

            return redirect()
                ->route('payable_cheques.show', $id)
                ->with(['success' => 'تم إلغاء الشيك وإنشاء القيد العكسي']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.PayableCheques', 'cancel');

            Log::error('خطأ في إلغاء الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * جلب دفاتر الشيكات حسب البنك
     */
    public function getCheckbooks($bankId)
    {
        $chequeBooks = ChequeBook::where('bank_id', $bankId)
            ->where('status', 1) // نشط
            ->get(['id', 'cheque_book_number']);

        return response()->json($chequeBooks);
    }

    /**
     * جلب معلومات دفتر الشيكات (للحصول على البنك)
     */
    public function getCheckbookInfo($id)
    {
        $checkbook = ChequeBook::with('bank')->find($id);

        if (! $checkbook) {
            return response()->json(['error' => 'دفتر الشيكات غير موجود'], 404);
        }

        return response()->json([
            'id' => $checkbook->id,
            'cheque_book_number' => $checkbook->cheque_book_number,
            'bank_id' => $checkbook->bank_id,
            'bank_name' => $checkbook->bank->name ?? 'غير محدد',
            'currency' => $checkbook->currency,
            'start_serial_number' => $checkbook->start_serial_number,
            'end_serial_number' => $checkbook->end_serial_number,
        ]);
    }

    /**
     * حذف الشيك مع إنشاء قيد عكسي
     */
    public function delete($id)
    {
        // الحذف يمسّ قيد الإصدار الأصلي بتاريخه هو
        // نقرةٌ ثانية أو صفحةٌ قديمة — رسالةٌ مفهومة لا صفحة ٤٠٤ عارية
        $existing = PayableCheque::find($id);
        if (! $existing) {
            return redirect()->route('payable_cheques.index')->with('error', 'هذا الشيك غير موجود — ربما حُذف مسبقاً.');
        }
        $this->guardPeriod($existing->issue_date);

        try {
            $cheque = PayableCheque::with('journalEntry.details')->findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            /*
                الحذف يعكس قيد **الإصدار الأصلي** فقط، بلا معرفة بأي إجراء
                لاحق. شيكٌ سبق رفضه أو إلغاؤه له بالفعل قيدٌ عكسي خاص بذلك
                الإجراء — لا يتتبّعه أي عمود على السجلّ — فحذفه بعد ذلك
                يُنشئ عكساً ثانياً لنفس القيد الأصلي، ويترك الحسابين
                (المستفيد و«شيكات برسم الدفع») منحرفين بكامل قيمة الشيك.
                الحذف الآمن الوحيد إذاً: شيكٌ لم يُعالَج بعد إصداره إطلاقاً.
            */
            if ($cheque->status !== ChequeStatus::ISSUED) {
                return redirect()->route('payable_cheques.index')
                    ->with(['error' => 'لا يمكن حذف شيك سبق معالجته ('.$cheque->status->getName().') — القيود المحاسبية لهذا الإجراء موثّقة ولا تُحذف']);
            }

            // إنشاء قيد عكسي إذا كان هناك قيد أصلي
            if ($cheque->journalEntry) {
                $this->chequeService->createReversalEntry($cheque, 'حذف الشيك');
            }

            // تسجيل الحذف قبل الحذف الفعلي
            OperationLog::record(
                'deleted',
                'finance_log',
                $cheque->id,
                'تم حذف الشيك رقم **'.$cheque->cheque_number.'** بمبلغ **'.number_format($cheque->amount, 2).'** وإنشاء قيد عكسي'
            );

            // حذف الشيك
            $cheque->delete();

            return redirect()
                ->route('payable_cheques.index')
                ->with(['success' => 'تم حذف الشيك وإنشاء القيد العكسي بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.PayableCheques', 'delete');

            Log::error('خطأ في حذف الشيك: '.$e->getMessage());

            return back()->with(['error' => 'حدث خطأ: '.$e->getMessage()]);
        }
    }

    /**
     * تحديث القيد المحاسبي عند تعديل الشيك
     */
    private function updateJournalEntry(PayableCheque $cheque): void
    {
        $journalEntry = $cheque->journalEntry;
        if (! $journalEntry) {
            return;
        }

        // جلب اسم الحساب المستفيد
        $recipientAccount = Account::find($cheque->recipient_account_id);
        $recipientName = $recipientAccount->name ?? $cheque->payee_name ?? 'غير محدد';

        // جلب اسم البنك
        $bankAccount = Account::find($cheque->bank_id);
        $bankName = $bankAccount->name ?? 'البنك';

        foreach ($journalEntry->details as $detail) {
            if ($detail->debit > 0) {
                $detail->update([
                    'account_id' => $cheque->recipient_account_id,
                    'debit' => $cheque->amount,
                    'description' => "شيك صادر رقم {$cheque->cheque_number} لحساب: {$recipientName}",
                ]);
            } else {
                $detail->update([
                    'credit' => $cheque->amount,
                    'description' => "شيك صادر رقم {$cheque->cheque_number} من بنك: {$bankName}",
                ]);
            }
        }

        $journalEntry->update([
            'description' => "إصدار شيك رقم {$cheque->cheque_number} بمبلغ ".number_format($cheque->amount, 2)." لحساب: {$recipientName} - من بنك: {$bankName}",
        ]);
    }
}
