<?php

namespace Modules\Account\Http\Controllers\Cheques;

use App\Constants\SystemAccountIds;
use App\Enums\ChequeStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivedChequeRequest;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntryInvoice;
use App\Models\Log as ModelsLog;
use App\Models\PaymentsProcess;
use App\Models\ReceivedCheque;
use App\Services\ChequeService;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\PartialPaymentRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Concerns\GuardsTreasuryAccess;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class ReceivedChequesController extends Controller
{
    use AccountingOperations;
    use GuardsTreasuryAccess;
    use ScopesToEmployeeHierarchy;

    protected ChequeService $chequeService;

    public function __construct(ChequeService $chequeService)
    {
        $this->chequeService = $chequeService;
        // صلاحيات الشيكات المستلمة — الكتالوج الجديد account.received_cheques.*
        $this->middleware('permission.role:account.received_cheques.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.received_cheques.show', ['only' => ['show']]);
        $this->middleware('permission.role:account.received_cheques.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.received_cheques.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.received_cheques.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:account.received_cheques.deposit', ['only' => ['deposit']]);
        $this->middleware('permission.role:account.received_cheques.collect', ['only' => ['collect']]);
        $this->middleware('permission.role:account.received_cheques.reject', ['only' => ['reject']]);
        $this->middleware('permission.role:account.received_cheques.endorse', ['only' => ['endorse']]);
    }

    /**
     * عرض قائمة الشيكات المستلمة
     */
    public function index(Request $request)
    {
        // من استلم الشيك هو أو من يديرهم — نفس نطاق سندات الصرف والقبض
        // (ScopesToEmployeeHierarchy)، بحسب التسلسل الإداري لا دورٍ ثابت.
        // وسياقُ الفرع من الشريط العلوي — المستند يخصّ الفرع الذي أُنشئ فيه
        $query = $this->scopeToCreator(
            ReceivedCheque::with(['recipientAccount', 'collectionAccount', 'bankAccount', 'client', 'journalEntry'])
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

        $received_cheques = $query->get();
        $statuses = ChequeStatus::receivedStatuses();

        // لازمة لنافذة «إيداع للتحصيل» في جدول القائمة — كانت غائبة، فيسقط
        // الإيداع من هنا بخطأ تحقّق صامت (bank_account_id مطلوب ولا حقل يرسله)
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        return view('account::cheques.received_cheques.index', compact('received_cheques', 'statuses', 'bank_accounts'));
    }

    /**
     * صفحة إنشاء شيك جديد
     */
    public function create()
    {
        // جلب حسابات العملاء (التي ليس لها أبناء وتحت فرع العملاء)
        $client_accounts = $this->getClientAccounts();

        // جلب حسابات البنوك لحساب التحصيل
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        return view('account::cheques.received_cheques.create', compact('client_accounts', 'bank_accounts'));
    }

    /**
     * حفظ شيك مستلم جديد
     */
    public function store(ReceivedChequeRequest $request)
    {
        // قيد الاستلام يُكتب بتاريخ استلام الشيك
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

            // استخدام ChequeService لإنشاء الشيك مع القيد
            // العميل يُجلب من recipient_account_id عند التحصيل/التظهير
            $cheque = $this->chequeService->receiveCheck([
                'amount' => $request->amount,
                'cheque_number' => $request->cheque_number,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                // لا ترسل collection_account_id عند الإنشاء
                'recipient_account_id' => $request->recipient_account_id,
                'payee_name' => $request->payee_name,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'drawer_bank' => $request->drawer_bank,
                'drawer_branch' => $request->drawer_branch,
                // بنك الإيداع المتوقَّع فقط — لا يُنشئ قيداً، القيد الفعلي عند «إيداع للتحصيل»
                'bank_account_id' => $request->bank_account_id,
            ]);

            // تسجيل النشاط
            OperationLog::record(
                'created',
                'finance_log',
                $cheque->id,
                'تم استلام شيك برقم **'.$request->cheque_number.'** بمبلغ **'.number_format($request->amount, 2).'**'
            );

            return redirect()
                ->route('received_cheques.index')
                ->with(['success' => 'تم استلام الشيك وإنشاء القيد المحاسبي بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'store');

            Log::error('خطأ في استلام الشيك: '.$e->getMessage());

            return back()->withErrors(['error' => 'حدث خطأ: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * عرض تفاصيل الشيك
     */
    public function show($id)
    {
        $received_cheque = ReceivedCheque::with([
            'recipientAccount',
            'collectionAccount',
            'bankAccount',
            'client',
            'journalEntry.details.account',
            'collectionJournalEntry.details.account',
            'endorsedToAccount',
        ])->findOrFail($id);

        $this->authorizeCreatorScope($received_cheque->created_by);

        // جلب الفواتير غير المسددة للعميل (للتحصيل)
        // نحصل على العميل من حساب المستلم (recipient_account_id)
        $unpaidInvoices = [];
        $recipientAccount = $received_cheque->recipientAccount;
        if ($recipientAccount && $recipientAccount->client) {
            $clientId = $recipientAccount->client->id;
            $unpaidInvoices = Invoice::where('client_id', $clientId)
                ->where('is_paid', false)
                ->where('type', '!=', 'returned')
                ->get();
        }

        // جلب حسابات البنوك للإيداع
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        // جلب حسابات الموردين/الدائنين للتظهير (بناءً على العلاقة مع جدول الموردين)
        $supplier_accounts = Account::whereNotNull('supplier_id')

            ->with('supplier')
            ->get();

        // سجل النشاطات
        $logs = ModelsLog::where('type', 'finance_log')
            ->where('type_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('account::cheques.received_cheques.show', compact(
            'received_cheque',
            'unpaidInvoices',
            'bank_accounts',
            'supplier_accounts',
            'logs'
        ));
    }

    /**
     * صفحة تعديل الشيك
     */
    public function edit($id)
    {
        $received_cheque = ReceivedCheque::findOrFail($id);

        $this->authorizeCreatorScope($received_cheque->created_by);

        // لا يمكن تعديل الشيك إذا تم تحصيله أو تظهيره
        if (! in_array($received_cheque->status, [ChequeStatus::PENDING, ChequeStatus::DEFERRED])) {
            return redirect()->route('received_cheques.show', $id)
                ->with(['error' => 'لا يمكن تعديل هذا الشيك بعد معالجته']);
        }

        // جلب حسابات العملاء (التي ليس لها أبناء وتحت فرع العملاء)
        $client_accounts = $this->getClientAccounts();

        // جلب حسابات البنوك لحساب التحصيل
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        return view('account::cheques.received_cheques.edit', compact('received_cheque', 'client_accounts', 'bank_accounts'));
    }

    /**
     * تحديث بيانات الشيك
     */
    public function update(ReceivedChequeRequest $request, $id)
    {
        $received_cheque = ReceivedCheque::findOrFail($id);

        $this->authorizeCreatorScope($received_cheque->created_by);

        $this->guardPeriod($received_cheque->issue_date, 'issue_date');
        $this->guardPeriod($request->input('issue_date'), 'issue_date');

        // لا يمكن تعديل الشيك إذا تم تحصيله أو تظهيره
        if (! in_array($received_cheque->status, [ChequeStatus::PENDING, ChequeStatus::DEFERRED])) {
            return redirect()->route('received_cheques.show', $id)
                ->with(['error' => 'لا يمكن تعديل هذا الشيك بعد معالجته']);
        }

        $received_cheque->amount = $request->amount;
        $received_cheque->cheque_number = $request->cheque_number;
        $received_cheque->issue_date = $request->issue_date;
        $received_cheque->due_date = $request->due_date;
        $received_cheque->collection_account_id = $request->collection_account_id;
        $received_cheque->recipient_account_id = $request->recipient_account_id;
        $received_cheque->description = $request->description;
        $received_cheque->payee_name = $request->payee_name;
        // العميل يُجلب من recipient_account_id عند التحصيل/التظهير
        $received_cheque->drawer_bank = $request->drawer_bank;
        $received_cheque->drawer_branch = $request->drawer_branch;
        // بنك الإيداع المتوقَّع فقط طالما لم يُودَع بعد (الحالة PENDING/DEFERRED هنا) — بلا أثرٍ محاسبي
        $received_cheque->bank_account_id = $request->bank_account_id;

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
            $received_cheque->attachment = $attachmentPath;
        }

        $received_cheque->save();

        // تحديث القيد المحاسبي
        if ($received_cheque->journalEntry) {
            $this->updateJournalEntry($received_cheque);
        }

        OperationLog::record(
            'updated',
            'finance_log',
            $received_cheque->id,
            'تم تعديل بيانات الشيك رقم **'.$received_cheque->cheque_number.'**'
        );

        return redirect()
            ->route('received_cheques.show', $id)
            ->with(['success' => 'تم تعديل الشيك المستلم بنجاح']);
    }

    /**
     * إيداع الشيك للتحصيل
     */
    public function deposit(Request $request, $id)
    {
        $request->validate([
            'bank_account_id' => 'required|exists:accounts,id',
        ], [
            'bank_account_id.required' => 'يرجى اختيار حساب البنك للإيداع',
            'bank_account_id.exists' => 'حساب البنك المختار غير موجود',
        ]);

        // إيداع الشيك **إيداعٌ** في الحساب البنكي — يُفحص شرط إيداعه
        $this->guardTreasuryDeposit($request->input('bank_account_id'), 'bank_account_id');

        try {
            $cheque = ReceivedCheque::findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            // التحقق من أن البنك المختار صحيح
            $bankAccount = Account::where('id', $request->bank_account_id)
                ->where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
                ->first();

            if (! $bankAccount) {
                return back()->with(['error' => 'حساب البنك المختار غير صحيح.']);
            }

            // تحديث حساب البنك في الشيك
            $cheque->update(['bank_account_id' => $request->bank_account_id]);

            $this->chequeService->depositForCollection($cheque, $request->bank_account_id);

            OperationLog::record(
                'deposited',
                'finance_log',
                $cheque->id,
                'تم إيداع الشيك رقم **'.$cheque->cheque_number.'** للتحصيل في بنك **'.$bankAccount->name.'**'
            );

            return redirect()
                ->route('received_cheques.show', $id)
                ->with(['success' => 'تم إيداع الشيك للتحصيل بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'deposit');

            Log::error('خطأ في إيداع الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * تحصيل الشيك
     */
    public function collect(Request $request, $id)
    {
        // التحصيل حدثٌ اليوم — لا في تاريخ الشيك
        $this->guardPeriod(today());

        try {
            $cheque = ReceivedCheque::with('recipientAccount.client')->findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            $invoiceIds = $request->input('invoice_ids', []);

            $this->chequeService->collectCheque($cheque, $invoiceIds);

            // إعادة تحميل الشيك للحصول على قيد التحصيل
            $cheque->refresh();

            // تسديد الرصيد السابق ثم الفواتير للعميل
            // نحصل على العميل من حساب المستلم (recipient_account_id) مثل سندات القبض
            $recipientAccount = $cheque->recipientAccount;
            if ($recipientAccount && $recipientAccount->client) {
                $clientId = $recipientAccount->client->id;
                $journalEntryId = $cheque->collectionJournalEntry ? $cheque->collectionJournalEntry->id : null;

                Log::info('تحصيل شيك - بيانات العميل:', [
                    'cheque_id' => $cheque->id,
                    'recipient_account_id' => $cheque->recipient_account_id,
                    'account_client_id' => $recipientAccount->client_id,
                    'client_id' => $clientId,
                    'amount' => $cheque->amount,
                    'journal_entry_id' => $journalEntryId,
                ]);

                $this->applyPaymentToClientInvoices($clientId, $cheque->amount, $cheque->id, $journalEntryId, 'cheque_collection');
            } else {
                Log::warning('تحصيل شيك - لم يتم العثور على عميل:', [
                    'cheque_id' => $cheque->id,
                    'recipient_account_id' => $cheque->recipient_account_id,
                    'has_account' => $recipientAccount ? 'yes' : 'no',
                    'account_client_id' => $recipientAccount ? $recipientAccount->client_id : null,
                ]);
            }

            OperationLog::record(
                'collected',
                'finance_log',
                $cheque->id,
                'تم تحصيل الشيك رقم **'.$cheque->cheque_number.'** بمبلغ **'.number_format($cheque->amount, 2).'**'
            );

            return redirect()
                ->route('received_cheques.show', $id)
                ->with(['success' => 'تم تحصيل الشيك بنجاح وإنشاء القيد المحاسبي وتسديد الفواتير']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'collect');

            Log::error('خطأ في تحصيل الشيك: '.$e->getMessage());

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
            $cheque = ReceivedCheque::findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            $reason = $request->input('reason');

            $this->chequeService->rejectCheque($cheque, $reason);

            OperationLog::record(
                'rejected',
                'finance_log',
                $cheque->id,
                'تم رفض الشيك رقم **'.$cheque->cheque_number.'**'.($reason ? ' - السبب: '.$reason : '')
            );

            return redirect()
                ->route('received_cheques.show', $id)
                ->with(['success' => 'تم تسجيل رفض الشيك وإنشاء القيد العكسي']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'reject');

            Log::error('خطأ في رفض الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * تظهير الشيك
     */
    public function endorse(Request $request, $id)
    {
        $this->guardPeriod(today());

        $request->validate([
            'endorsed_to_account_id' => 'required|exists:accounts,id',
        ]);

        try {
            $cheque = ReceivedCheque::with('recipientAccount.client')->findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            $this->chequeService->endorseCheque($cheque, $request->endorsed_to_account_id);

            // إعادة تحميل الشيك للحصول على قيد التظهير
            $cheque->refresh();

            // تسديد الرصيد السابق ثم الفواتير للعميل
            // نحصل على العميل من حساب المستلم (recipient_account_id) مثل سندات القبض
            $recipientAccount = $cheque->recipientAccount;
            if ($recipientAccount && $recipientAccount->client) {
                $clientId = $recipientAccount->client->id;
                $journalEntryId = $cheque->collectionJournalEntry ? $cheque->collectionJournalEntry->id : null;

                Log::info('تظهير شيك - بيانات العميل:', [
                    'cheque_id' => $cheque->id,
                    'recipient_account_id' => $cheque->recipient_account_id,
                    'account_client_id' => $recipientAccount->client_id,
                    'client_id' => $clientId,
                    'amount' => $cheque->amount,
                    'journal_entry_id' => $journalEntryId,
                ]);

                $this->applyPaymentToClientInvoices($clientId, $cheque->amount, $cheque->id, $journalEntryId, 'cheque_endorsement');
            } else {
                Log::warning('تظهير شيك - لم يتم العثور على عميل:', [
                    'cheque_id' => $cheque->id,
                    'recipient_account_id' => $cheque->recipient_account_id,
                    'has_account' => $recipientAccount ? 'yes' : 'no',
                    'account_client_id' => $recipientAccount ? $recipientAccount->client_id : null,
                ]);
            }

            $endorsedAccount = Account::find($request->endorsed_to_account_id);

            OperationLog::record(
                'endorsed',
                'finance_log',
                $cheque->id,
                'تم تظهير الشيك رقم **'.$cheque->cheque_number.'** إلى **'.$endorsedAccount->name.'** وتسديد فواتير العميل'
            );

            return redirect()
                ->route('received_cheques.show', $id)
                ->with(['success' => 'تم تظهير الشيك بنجاح وتسديد الفواتير']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'endorse');

            Log::error('خطأ في تظهير الشيك: '.$e->getMessage());

            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * حذف الشيك مع إنشاء قيد عكسي
     */
    public function delete($id)
    {
        // الحذف يمسّ قيد الاستلام الأصلي بتاريخه هو
        // نقرةٌ ثانية أو صفحةٌ قديمة — رسالةٌ مفهومة لا صفحة ٤٠٤ عارية
        $existing = ReceivedCheque::find($id);
        if (! $existing) {
            return redirect()->route('received_cheques.index')->with('error', 'هذا الشيك غير موجود — ربما حُذف مسبقاً.');
        }
        $this->guardPeriod($existing->issue_date);

        try {
            $cheque = ReceivedCheque::with('journalEntry.details')->findOrFail($id);

            $this->authorizeCreatorScope($cheque->created_by);

            /*
                نفس منطق حذف الشيك الصادر: الحذف يعكس قيد **الاستلام
                الأصلي** فقط. شيكٌ سبق رفضه أو تظهيره أو إيداعه للتحصيل له
                بالفعل قيدٌ خاص بذلك الإجراء لا يتتبّعه أي عمود على السجلّ،
                فحذفه بعد ذلك يُنشئ عكساً ثانياً زائداً ويترك الحسابات
                منحرفة بقيمة الشيك. الحذف الآمن الوحيد: شيكٌ لم يُعالَج
                بعد استلامه إطلاقاً.
            */
            if ($cheque->status !== ChequeStatus::PENDING) {
                return redirect()->route('received_cheques.index')
                    ->with(['error' => 'لا يمكن حذف شيك سبق معالجته ('.$cheque->status->getName().') — القيود المحاسبية لهذا الإجراء موثّقة ولا تُحذف']);
            }

            // إنشاء قيد عكسي إذا كان هناك قيد أصلي
            if ($cheque->journalEntry) {
                $this->chequeService->createReceivedChequeReversalEntry($cheque, 'حذف الشيك');
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
                ->route('received_cheques.index')
                ->with(['success' => 'تم حذف الشيك وإنشاء القيد العكسي بنجاح']);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.ReceivedCheques', 'delete');

            Log::error('خطأ في حذف الشيك: '.$e->getMessage());

            return back()->with(['error' => 'حدث خطأ: '.$e->getMessage()]);
        }
    }

    /**
     * تحديث القيد المحاسبي عند تعديل الشيك
     * قيد: من ح/ شيكات تحت التحصيل - إلى ح/ العميل
     */
    private function updateJournalEntry(ReceivedCheque $cheque): void
    {
        $journalEntry = $cheque->journalEntry;
        if (! $journalEntry) {
            return;
        }

        // جلب اسم حساب العميل/المستلم منه
        $recipientAccount = Account::find($cheque->recipient_account_id);
        $recipientName = $recipientAccount->name ?? $cheque->payee_name ?? 'غير محدد';

        // جلب اسم حساب شيكات تحت التحصيل
        $collectionAccount = Account::find($cheque->collection_account_id ?? AccountingSettings::id('cheques_under_collection', SystemAccountIds::CHEQUES_UNDER_COLLECTION));
        $collectionAccountName = $collectionAccount->name ?? 'شيكات تحت التحصيل';

        // تحديث تفاصيل القيد
        foreach ($journalEntry->details as $detail) {
            if ($detail->debit > 0) {
                // شيكات تحت التحصيل (مدين)
                $detail->update([
                    'account_id' => $cheque->collection_account_id ?? AccountingSettings::id('cheques_under_collection', SystemAccountIds::CHEQUES_UNDER_COLLECTION),
                    'debit' => $cheque->amount,
                    'description' => "شيك مستلم رقم {$cheque->cheque_number} - إلى: {$collectionAccountName}",
                ]);
            } else {
                // حساب العميل (دائن)
                $detail->update([
                    'account_id' => $cheque->recipient_account_id,
                    'credit' => $cheque->amount,
                    'description' => "شيك مستلم رقم {$cheque->cheque_number} - من: {$recipientName}",
                ]);
            }
        }

        $journalEntry->update([
            'description' => "استلام شيك رقم {$cheque->cheque_number} بمبلغ ".number_format($cheque->amount, 2)." من: {$recipientName}".($cheque->drawer_bank ? " - بنك الساحب: {$cheque->drawer_bank}" : ''),
        ]);
    }

    /**
     * جلب حسابات العملاء (التي ليس لها أبناء وتحت فرع العملاء)
     */
    private function getClientAccounts()
    {
        // جلب جميع معرفات الحسابات التي تقع تحت فرع العملاء
        $clientAccountIds = $this->getDescendantAccountIds(AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT));

        // جلب الحسابات التي ليس لها أبناء (leaf accounts) وتقع تحت فرع العملاء
        return Account::whereIn('id', $clientAccountIds)
            ->whereDoesntHave('children')
            ->where('is_active', 1)
            ->get();
    }

    /**
     * جلب جميع معرفات الحسابات الفرعية (بشكل متكرر)
     */
    private function getDescendantAccountIds($parentId): array
    {
        $ids = [$parentId];
        $children = Account::where('parent_id', $parentId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getDescendantAccountIds($childId));
        }

        return $ids;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //              دوال تسديد الرصيد السابق والفواتير (مستوحاة من سندات القبض)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * حساب الرصيد السابق للعميل
     *
     * الرصيد السابق = رصيد الحساب (balance) - إجمالي الفواتير غير المسددة أو المسددة جزئياً
     * هذا يمثل الديون القديمة التي ليس لها فواتير في النظام
     *
     * @param  int  $clientId  معرف العميل
     * @param  float  $receivedAmount  المبلغ المستلم (الذي تم خصمه من الحساب)
     * @return array معلومات الرصيد السابق
     */
    private function calculatePreviousBalance($clientId, $receivedAmount = 0)
    {
        // جلب حساب العميل
        $client_account = Account::ledger()->where('client_id', $clientId)->first();

        if (! $client_account) {
            return [
                'account_balance' => 0,
                'total_invoices_due' => 0,
                'previous_balance' => 0,
                'previous_balance_paid' => 0,
                'remaining_previous_balance' => 0,
            ];
        }

        // رصيد الحساب الأصلي (قبل خصم المبلغ المستلم)
        // نضيف المبلغ المستلم لأن الرصيد تم تحديثه قبل استدعاء هذه الدالة
        $account_balance = abs($client_account->balance) + $receivedAmount;

        // حساب إجمالي المتبقي من الفواتير غير المسددة أو المسددة جزئياً
        $unpaid_invoices = $this->getUnpaidClientInvoices($clientId);
        $total_invoices_due = 0;

        foreach ($unpaid_invoices as $invoice) {
            // المبلغ المتبقي للفاتورة = إجمالي الفاتورة - المدفوعات السابقة
            $paid = $invoice->payments()->sum('amount') ?? 0;
            $remaining = max(0, $invoice->grand_total - $paid);
            $total_invoices_due += $remaining;
        }

        // الرصيد السابق = رصيد الحساب الأصلي - إجمالي المتبقي من الفواتير
        $previous_balance = max(0, $account_balance - $total_invoices_due);

        // حساب المدفوعات السابقة من الرصيد السابق
        $previous_balance_paid = PaymentsProcess::where('client_id', $clientId)
            ->where('type', 'previous_balance')
            ->sum('amount');

        // الرصيد السابق المتبقي (غير المسدد)
        $remaining_previous_balance = max(0, $previous_balance - $previous_balance_paid);

        return [
            'account_balance' => $account_balance,
            'total_invoices_due' => $total_invoices_due,
            'previous_balance' => $previous_balance,
            'previous_balance_paid' => $previous_balance_paid,
            'remaining_previous_balance' => $remaining_previous_balance,
        ];
    }

    /**
     * جلب الفواتير غير المسددة أو المسددة جزئياً للعميل
     *
     * تستثني هذه الدالة:
     * 1. فواتير المرتجع (type = 'returned')
     * 2. الفواتير التي لديها مرتجع مرتبط بها عبر reference_number
     *
     * @param  int  $clientId  معرف العميل
     * @return Collection مجموعة الفواتير غير المسددة مرتبة من الأقدم
     */
    private function getUnpaidClientInvoices($clientId)
    {
        // الحصول على معرفات الفواتير التي لها مرتجع مرتبط بها
        $invoices_with_returns = Invoice::where('type', 'returned')
            ->whereNotNull('reference_number')
            ->pluck('reference_number')
            ->toArray();

        // استخدام is_paid = false للحصول على الفواتير غير المسددة أو المسددة جزئياً
        // مثل ما يفعل سندات القبض IncomesController
        return Invoice::where('client_id', $clientId)
            ->where('is_paid', false)
            ->where('type', '!=', 'returned')
            ->whereNotIn('id', $invoices_with_returns)
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * توزيع مبلغ الشيك على الرصيد السابق ثم الفواتير
     *
     * يتم التسديد بالترتيب التالي:
     * 1. حساب الرصيد السابق (رصيد الحساب - إجمالي الفواتير غير المسددة)
     * 2. تسديد الرصيد السابق أولاً
     * 3. ثم تسديد الفواتير حسب تاريخها (الأقدم أولاً)
     *
     * @param  int  $clientId  معرف العميل
     * @param  float  $amount  المبلغ المراد توزيعه
     * @param  int|null  $chequeId  معرف الشيك
     * @param  int|null  $journalEntryId  معرف القيد المحاسبي
     * @param  string  $type  نوع العملية (cheque_collection أو cheque_endorsement)
     * @return void
     */
    private function applyPaymentToClientInvoices($clientId, $amount, $chequeId = null, $journalEntryId = null, $type = 'cheque_collection')
    {
        $remaining = $amount;
        $invoiceLinks = [];

        Log::info('========== بدء توزيع مدفوعات الشيك ==========');
        Log::info('شيك #'.$chequeId.' - المبلغ المستلم: '.$amount.' - نوع العملية: '.$type);

        // الخطوة الأولى: حساب الرصيد السابق للعميل
        $balance_info = $this->calculatePreviousBalance($clientId, $amount);

        Log::info('تحليل رصيد العميل:', [
            'رصيد_الحساب' => $balance_info['account_balance'],
            'إجمالي_الفواتير_غير_المسددة' => $balance_info['total_invoices_due'],
            'الرصيد_السابق' => $balance_info['previous_balance'],
            'الرصيد_السابق_المتبقي' => $balance_info['remaining_previous_balance'],
        ]);

        // الخطوة الثانية: تسديد الرصيد السابق أولاً (إن وجد)
        if ($balance_info['remaining_previous_balance'] > 0) {
            $amount_to_pay_previous = min($remaining, $balance_info['remaining_previous_balance']);

            if ($amount_to_pay_previous > 0) {
                // تسجيل دفعة للرصيد السابق
                PaymentsProcess::create([
                    'client_id' => $clientId,
                    'type' => 'previous_balance',
                    'payment_date' => now(),
                    'amount' => $amount_to_pay_previous,
                    'payment_status' => 1, // مدفوع
                    'payment_method' => 3, // شيك
                    'reference_number' => $chequeId,
                    'notes' => 'تسديد رصيد سابق عبر شيك رقم #'.$chequeId,
                ]);

                $remaining -= $amount_to_pay_previous;

                Log::info('تم تسديد الرصيد السابق:', [
                    'المبلغ_المسدد' => $amount_to_pay_previous,
                    'المتبقي_للتوزيع' => $remaining,
                ]);
            }
        } else {
            Log::info('لا يوجد رصيد سابق للتسديد');
        }

        // إذا لم يتبقى مبلغ، لا داعي للاستمرار
        if ($remaining <= 0) {
            Log::info('تم استهلاك كامل المبلغ في الرصيد السابق');

            return;
        }

        // الخطوة الثالثة: تسديد الفواتير غير المسددة (الأقدم أولاً)
        $invoices = $this->getUnpaidClientInvoices($clientId);

        Log::info('عدد الفواتير غير المسددة: '.$invoices->count());

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            // حساب المبلغ المتبقي للفاتورة
            $totalPaid = $invoice->payments()->sum('amount') ?? 0;
            $invoiceRemaining = max(0, $invoice->grand_total - $totalPaid);

            if ($invoiceRemaining <= 0) {
                continue;
            }

            // تحديد المبلغ الذي سيتم دفعه لهذه الفاتورة
            $amountToPay = min($remaining, $invoiceRemaining);

            // فاتورةٌ تمنع السداد الجزئي لا يُوزَّع عليها جزء — انظر `PartialPaymentRule`
            if (PartialPaymentRule::blocks($invoice, $amountToPay, $invoiceRemaining)) {
                continue;
            }

            Log::info('تسديد فاتورة #'.$invoice->id, [
                'إجمالي_الفاتورة' => $invoice->grand_total,
                'المدفوع_سابقاً' => $totalPaid,
                'المتبقي_للفاتورة' => $invoiceRemaining,
                'المبلغ_المسدد_الآن' => $amountToPay,
            ]);

            // تسجيل الدفعة
            PaymentsProcess::create([
                'client_id' => $clientId,
                'invoice_id' => $invoice->id,
                'type' => 'client payments',
                'payment_date' => now(),
                'amount' => $amountToPay,
                'payment_status' => 1, // مدفوع
                'payment_method' => 3, // شيك
                'reference_number' => $chequeId,
                'notes' => 'تسديد من شيك رقم #'.$chequeId.' ('.($type == 'cheque_collection' ? 'تحصيل' : 'تظهير').')',
            ]);

            // تحديث حالة الفاتورة باستخدام الدالة المخصصة (مثل سندات القبض)
            $invoice->updatePaymentStatus();

            Log::info('تم تحديث حالة الفاتورة:', [
                'invoice_id' => $invoice->id,
                'is_paid' => $invoice->is_paid,
                'payment_status' => $invoice->payment_status,
                'remaining_amount' => $invoice->remaining_amount,
            ]);

            // إضافة الرابط للقيد المحاسبي
            $invoiceLinks[] = [
                'invoice_id' => $invoice->id,
                'amount' => $amountToPay,
            ];

            $remaining -= $amountToPay;
        }

        // ربط القيد بالفواتير المدفوعة باستخدام جدول journal_entry_invoices
        if (! empty($invoiceLinks) && $journalEntryId) {
            foreach ($invoiceLinks as $link) {
                JournalEntryInvoice::create([
                    'entry_id' => $journalEntryId,
                    'invoice_id' => $link['invoice_id'],
                    'invoice_type' => InvoiceType::SALE->value,
                    'allocation_amount' => $link['amount'],
                    'allocation_percentage' => ($link['amount'] / $amount) * 100,
                ]);
            }
        }

        Log::info('انتهى توزيع مدفوعات الشيك. المبلغ المتبقي: '.$remaining);
    }
}
