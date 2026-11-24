<?php

namespace Modules\Account\Http\Controllers\Finance;

use App\Constants\Banks;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Models\Receipt;
use App\Models\Revenue;
use App\Models\Role;
use App\Models\Treasury;
use App\Rules\Iban;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Concerns\GuardsTreasuryAccess;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class TreasuryController extends Controller
{
    use AccountingOperations;
    use GuardsTreasuryAccess;
    use ScopesToEmployeeHierarchy;

    /**
     * صلاحيات الخزائن والتحويلات — الكتالوج الجديد account.treasury.* /
     * account.treasury_transfers.*. لم يكن لهذا المتحكّم أي حماية صلاحياتٍ
     * إطلاقاً من قبل.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.treasury.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.treasury.show', ['only' => ['show', 'getOperationsData']]);
        $this->middleware('permission.role:account.treasury.create', ['only' => ['create', 'store', 'generateNextCode']]);
        $this->middleware('permission.role:account.treasury.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.treasury.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:account.treasury.toggle_status', ['only' => ['updateStatus']]);
        $this->middleware('permission.role:account.treasury.bank_create', ['only' => ['create_account_bank', 'store_account_bank']]);
        $this->middleware('permission.role:account.treasury.bank_edit', ['only' => ['edit_account_bank', 'update_account_bank']]);
        $this->middleware('permission.role:account.treasury_transfers.view', ['only' => ['getTransfersData']]);
        $this->middleware('permission.role:account.treasury_transfers.create', ['only' => ['transferCreate', 'transferTreasuryStore']]);
        $this->middleware('permission.role:account.treasury_transfers.edit', ['only' => ['transferEdit', 'transferTreasuryUpdate']]);
    }

    /**
     * إرجاع معرفات الفرع المحدد + كل أبنائه (شجرة كاملة) لاستخدامها في الفلترة.
     */
    private function resolveBranchTreeIds($branchId): array
    {
        // الفرعُ المطلوب مقصوصاً على نطاق المستخدم — الرئيسيّ أو غيرُه لا يفتح ما ليس له
        $branchId = $this->requestedBranchNode($branchId);
        if (! $branchId) {
            return [0];
        }

        $branch = Branch::find($branchId);
        if (! $branch) {
            return [(int) $branchId];
        }

        return $branch->descendantBranchIds()->push((int) $branch->id)->unique()->values()->all();
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Account::query();

        // Apply filters based on request parameters
        if ($request->has('keywords') && $request->keywords) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->keywords.'%')
                    ->orWhere('code', 'like', '%'.$request->keywords.'%');
            });
        }

        if ($request->has('category') && $request->category) {
            // Category 1 = bank account, Category 2 = treasury
            $type = $request->category == 1 ? 1 : 0;
            $query->where('type_accont', $type);
        }

        if ($request->has('status') && $request->status !== null && $request->status !== '') {
            // Status 1 = active, Status 0 = inactive
            $isActive = $request->status == 1 ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        if ($request->has('currency') && $request->currency) {
            $query->where('currency', $request->currency);
        }

        // فلترة الفرع: الأب الرئيسي + كل أبنائه
        if ($request->has('branch') && $request->branch) {
            $branchIds = $this->resolveBranchTreeIds($request->branch);
            $query->whereIn('branch_id', $branchIds);
        }

        // نطاق البيانات: خزائنه هو، أو خزائن من يديرهم إن كان يديرهم، أو الكل بلا قيد
        $treasuryIds = $this->accessibleTreasuryIds();
        if ($treasuryIds !== null) {
            $query->whereIn('id', $treasuryIds ?: [0]);
        } else {
            // عرض جميع الخزائن للمستخدمين الآخرين
            $query->whereIn('parent_id', [AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT), AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT)]);
        }

        // Order and paginate (preserve query string in pagination links)
        $treasuries = $query->orderBy('id', 'DESC')->paginate(10)->appends($request->query());

        // الفروع الرئيسية (الجذور) فقط — الفلترة في الخزائن على الأب الرئيسي
        $branches = $this->branchFilterOptions();

        /* شاشةُ تعديل الحساب البنكي تصل بمعرّف صفّ `treasuries`، والجدولُ
           يعرض صفوف `accounts` — فتُمرَّر الترجمةُ بينهما، وإلّا بقي زرُّ
           تعديل الحساب البنكي غائباً كما كان. */
        $bankTreasuryIds = Treasury::whereIn('account_id', $treasuries->pluck('id'))
            ->pluck('id', 'account_id')
            ->all();

        return view('account::finance.treasury.index', compact('treasuries', 'branches', 'bankTreasuryIds'));
    }

    public function create()
    {
        $employees = Employee::orderBy('first_name')->get();
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        return view('account::finance.treasury.carate', compact('employees', 'roles', 'branches'));
    }

    public function store(Request $request)
    {
        /*
            التحقق من صحة البيانات.

            وقيمةُ الشرط تُتحقَّق من وجودها: معرّفُ موظفٍ أو دورٍ أو فرعٍ
            لا يقابله صفّ يقفل الخزينة على لا أحد، ولا يُكتشف ذلك إلا يوم
            يعجز صاحبُها عن الإيداع فيها.
        */
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|in:0,1',
            'deposit_permissions' => 'required|integer|in:0,1,2,3',
            'withdraw_permissions' => 'required|integer|in:0,1,2,3',
            'v_employee_id' => 'required_if:deposit_permissions,1|nullable|exists:employees,id',
            'v_functional_role_id' => 'required_if:deposit_permissions,2|nullable|exists:roles,id',
            'v_branch_id' => 'required_if:deposit_permissions,3|nullable|exists:branches,id',
            'c_employee_id' => 'required_if:withdraw_permissions,1|nullable|exists:employees,id',
            'c_functional_role_id' => 'required_if:withdraw_permissions,2|nullable|exists:roles,id',
            'c_branch_id' => 'required_if:withdraw_permissions,3|nullable|exists:branches,id',
        ], [
            'name.required' => 'اسم الخزينة مطلوب',
            'v_employee_id.required_if' => 'اختر الموظف المسموح له بالإيداع.',
            'v_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالإيداع.',
            'v_branch_id.required_if' => 'اختر الفرع المسموح له بالإيداع.',
            'c_employee_id.required_if' => 'اختر الموظف المسموح له بالسحب.',
            'c_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالسحب.',
            'c_branch_id.required_if' => 'اختر الفرع المسموح له بالسحب.',
            'v_employee_id.exists' => 'الموظف المختار للإيداع غير موجود.',
            'v_functional_role_id.exists' => 'الدور الوظيفي المختار للإيداع غير موجود.',
            'v_branch_id.exists' => 'الفرع المختار للإيداع غير موجود.',
            'c_employee_id.exists' => 'الموظف المختار للسحب غير موجود.',
            'c_functional_role_id.exists' => 'الدور الوظيفي المختار للسحب غير موجود.',
            'c_branch_id.exists' => 'الفرع المختار للسحب غير موجود.',
        ]);

        // إنشاء الخزينة في جدول Treasury
        $treasury = new Treasury;
        $treasury->name = $request->name;
        $treasury->type = 0; // نوع الحساب (خزينة)
        $treasury->status = 1; // حالة الخزينة
        $treasury->description = $request->description ?? 'خزينة جديدة'; // وصف الخزينة
        $treasury->deposit_permissions = $request->deposit_permissions;
        $treasury->withdraw_permissions = $request->withdraw_permissions;

        /*
            قيمة الشرط تتبع نوعه.

            كان `else` يلتقط الحالتين معاً — «الكل» (0) و«فرع محدد» (3) —
            فيكتب معرّف فرعٍ في خزينةٍ شرطُها **الكل**. القيمة لا تُقرأ في
            تلك الحال فلا ضرر مباشر، لكنّها تُقرأ عند المراجعة: خزينةٌ
            مفتوحة للجميع تحمل رقم فرعٍ يوهم بقيدٍ لا وجود له. وحين يُقلَب
            الشرط إلى «فرع» لاحقاً يسري قيدٌ لم يختره أحد.

            و«الكل» تُخزَّن `null` صراحةً: الغياب هو المعنى.
        */
        $scopeValue = function (int $scope, array $sources) use ($request) {
            return match ($scope) {
                1 => $request->input($sources['employee']),
                2 => $request->input($sources['role']),
                3 => $request->input($sources['branch']),
                default => null,   // 0 = الكل
            };
        };

        $treasury->value_of_deposit_permissions = $scopeValue(
            (int) $request->deposit_permissions,
            ['employee' => 'v_employee_id', 'role' => 'v_functional_role_id', 'branch' => 'v_branch_id']
        );

        $treasury->value_of_withdraw_permissions = $scopeValue(
            (int) $request->withdraw_permissions,
            ['employee' => 'c_employee_id', 'role' => 'c_functional_role_id', 'branch' => 'c_branch_id']
        );

        // حفظ الخزينة مرة واحدة فقط
        $treasury->save();

        // إنشاء الحساب المرتبط في جدول Account
        $account = new Account;
        $account->name = $request->name;
        $account->type_accont = 0; // نوع الحساب (خزينة)
        $account->is_active = $request->is_active ?? 1; // حالة الحساب (افتراضي: نشط)
        $account->parent_id = AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT); // الأب الافتراضي خزائن الكاش

        // $account->code = $this->generateNextCode(13);

        $account->balance_type = 'debit'; // نوع الرصيد (مدين)

        $account->balance_type = 'debit'; // نوع الرصيد (مدين)
        $account->code = 0;

        $account->save();

        $account->code = $account->id; // يمكن تعديل منطق التكويد لاحقاً
        $account->save();

        /*
            ربط الخزينة بحسابها.

            كان السطر مكتوباً هنا ثم عُطّل بتعليق — وبتعطيله انقطع الطريق
            بين شرطَي الإيداع والسحب (وهما على `treasuries`) وبين العمليات
            (وهي تشير إلى `accounts`). فبقيت الصلاحيتان تُحفظان ولا يقرؤهما
            شيء.

            والربط في هذا الاتجاه — `treasuries.account_id` — لأن الحساب قد
            يوجد بلا خزينة، والخزينة لا تقوم بلا حساب.
        */
        $treasury->account_id = $account->id;
        $treasury->save();

        // تسجيل النشاط في جدول السجلات
        OperationLog::record(
            'created',
            'finance_log',
            $treasury->id, // ID النشاط المرتبط
            'تم اضافة خزينة  **'.$request->name.'**'
        );

        // إعادة التوجيه مع رسالة نجاح
        return redirect()->route('treasury.index')->with('success', 'تم إضافة الخزينة بنجاح!');
    }

    /**
     * كود الخزينة الجديدة تحت حساب الخزائن أو البنوك.
     *
     * كان يعيد `1` حين لا أبناء للأب — كودُ الجذر — ثم يقفز إلى أوّل رقمٍ
     * حرّ أياً كان موقعه من الشجرة، فتُولَد خزينةٌ كودها لا يمتّ لأبيها بصلة.
     */
    public function generateNextCode($parentId)
    {
        return $this->nextChildAccountCode($parentId);
    }

    public function transferCreate()
    {
        // جلب خزائن الموظفين مع تفاصيل الخزينة (Account)
        $treasuries = Account::whereIn('parent_id', [AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT), AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT)])->get();

        return view('account::finance.treasury.transferCreate', compact('treasuries'));
    }

    public function transferTreasuryStore(Request $request)
    {
        $request->validate([
            'from_treasury_id' => 'required|exists:accounts,id',
            'to_treasury_id' => 'required|exists:accounts,id|different:from_treasury_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'from_currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            // التحويل بين عملتين مختلفتين يقتضي سعر صرف — ولا سعر هنا
            'to_currency' => ['required', 'string', 'size:3', 'same:from_currency'],
            'attachments' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:4096',
        ], [
            'from_treasury_id.required' => 'الخزينة المحوَّل منها مطلوبة',
            'from_treasury_id.exists' => 'الخزينة المحوَّل منها غير موجودة',
            'to_treasury_id.required' => 'الخزينة المحوَّل إليها مطلوبة',
            'to_treasury_id.exists' => 'الخزينة المحوَّل إليها غير موجودة',
            'to_treasury_id.different' => 'يجب اختيار خزينة مختلفة عن المصدر',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقمًا',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'transfer_date.required' => 'تاريخ التحويل مطلوب',
            'transfer_date.date' => 'تاريخ التحويل غير صالح',
            'from_currency.required' => 'عملة المصدر مطلوبة',
            'from_currency.exists' => 'عملة المصدر غير مفعَّلة في إعدادات النظام',
            'to_currency.required' => 'عملة الوجهة مطلوبة',
            'to_currency.same' => 'التحويل بين عملتين مختلفتين غير مدعوم — اختر العملة نفسها للطرفين',
            'attachments.file' => 'المرفق يجب أن يكون ملفًا صالحًا',
            'attachments.mimes' => 'الامتدادات المسموحة: jpg,jpeg,png,pdf,doc,docx,zip',
            'attachments.max' => 'أقصى حجم للمرفق 4 ميجابايت',
        ]);

        /*
            الفترة وشرطا الخزينتين **بعد** التحقّق من المدخلات، لا قبله.
            والتحويل حركتان لا واحدة: **سحبٌ** من المصدر و**إيداعٌ** في
            الوجهة — فمن لا يملك السحب من خزينةٍ لا يُفرغها بتحويلها.
        */
        $this->guardPeriod($request->input('transfer_date'), 'transfer_date');
        $this->guardTreasuryWithdraw($request->input('from_treasury_id'), 'from_treasury_id');
        $this->guardTreasuryDeposit($request->input('to_treasury_id'), 'to_treasury_id');

        $fromTreasury = Account::find($request->from_treasury_id);
        $toTreasury = Account::find($request->to_treasury_id);

        // إلغاء تقييد التحويل على خزائن محددة للموظف بطلب المستخدم: السماح باختيار أي خزينة

        // تحقق من توفر الرصيد
        if ($fromTreasury->balance < $request->amount) {
            return back()->withErrors(['error' => 'الرصيد غير كافٍ في الخزينة المختارة.']);
        }

        /*
            كان `try` معطَّلاً بالتعليق و`DB::beginTransaction()` وحده عارياً:
            أيّ إخفاق بين فتح المعاملة و`commit` — قيدٌ مرفوض أو مرفقٌ تعذّر
            حفظه — يترك المعاملة مفتوحة والمستخدمَ أمام صفحة خطأ خام، بلا
            تراجعٍ وبلا سطرٍ في سجلّ الأخطاء.
        */
        DB::beginTransaction();

        try {
            // خصم من الخزينة المرسلة
            // $fromTreasury->updateBalance($request->amount, 'subtract');

            // // إضافة إلى الخزينة المستقبلة
            // $toTreasury->updateBalance($request->amount, 'add');

            // توليد رقم مرجعي للتحويل (يمكن استبداله بسيري متزايد لاحقاً)
            $refNumber = 'TRF-'.now()->format('YmdHis').'-'.$fromTreasury->id.'-'.$toTreasury->id;

            // حفظ المرفق إن وجد
            $attachmentPath = null;
            if ($request->hasFile('attachments')) {
                $attachmentPath = $request->file('attachments')->store('journal_attachments', 'public');
            }

            // إنشاء القيد المحاسبي للتحويل
            $description = 'تحويل من: '.$fromTreasury->name.' إلى: '.$toTreasury->name;
            if ($request->filled('notes')) {
                $description .= ' | ملاحظات: '.$request->notes;
            }

            $journalEntry = JournalEntry::create([
                'reference_number' => $refNumber,
                'date' => $request->transfer_date,
                'description' => $description,
                'status' => 1,
                'is_posted' => 1,
                // عملة التحويل هي عملة الخزينة المصدر — وإلا الافتراضية للنظام
                'currency' => $fromTreasury->currency ?: CurrencySettings::code(),
                'reference_type' => ReferenceType::TRANSFER,
                'linking_type' => LinkingType::SINGLE,
                'attachment' => $attachmentPath,
                'created_by_employee' => Auth::id(),
            ]);

            // 1) قيد دائن للخزينة المحوَّل منها (نقصان)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $fromTreasury->id,
                'description' => 'تحويل من '.($fromTreasury->code ?? $fromTreasury->id),
                'debit' => 0,
                'credit' => $request->amount,
                'is_debit' => false,
            ]);

            // 2) قيد مدين للخزينة المستقبِلة (زيادة)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $toTreasury->id,
                'description' => 'تحويل إلى '.($toTreasury->code ?? $toTreasury->id),
                'debit' => $request->amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            DB::commit();

            $this->recordOperation(
                'created',
                'finance_log',
                $journalEntry->id,
                'تحويل مبلغ '.number_format((float) $request->amount, 2)
                    .' من الخزينة <strong>'.$fromTreasury->name.'</strong>'
                    .' إلى <strong>'.$toTreasury->name.'</strong>'
            );

            return redirect()->route('treasury.index')->with('success', 'تم التحويل وإنشاء القيد المحاسبي بنجاح!');
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Treasury', 'transferTreasuryStore');

            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', $this->recordFailure($e, 'finance_log', 'transfer'));
        }
    }

    public function transferEdit($id)
    {
        /* قيدُ تحويلٍ فقط — كانت تقبل أيَّ قيدٍ في النظام بمعرّفه، فتسقط
           الشاشة على قيدٍ ليس تحويلاً، ويحفظُ نموذجُها فوق قيد فاتورةٍ أو
           سندٍ سطورَ تحويلٍ مكانَ سطوره. */
        $transfer = JournalEntry::where('reference_type', ReferenceType::TRANSFER)->findOrFail($id);
        $this->authorizeCreatorScope($transfer->created_by_employee);

        // جلب الخزائن
        $treasuries = Account::whereIn('type_accont', [0, 1])
            ->active()
            ->orderBy('name', 'ASC')
            ->get();

        return view('account::finance.treasury.transferEdit', compact('transfer', 'treasuries'));
    }

    public function transferTreasuryUpdate(Request $request, $id)
    {
        $request->validate([
            'from_treasury_id' => 'required|exists:accounts,id',
            'to_treasury_id' => 'required|exists:accounts,id|different:from_treasury_id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        /*
            تعديل التحويل يُعيد كتابة **قيده المحاسبي** بتاريخ اليوم — فهو
            كتابةٌ محاسبية كاملة كالإنشاء. وكان `transferTreasuryStore`
            محروساً وهذا بلا حارس، فيُلتفّ على إقفال الشهر بالتعديل بدل
            الإنشاء.
        */
        $this->guardPeriod(now()->toDateString());

        // التعديل يُعيد كتابة طرفَي التحويل — فيُفحص شرطاهما
        $this->guardTreasuryWithdraw($request->input('from_treasury_id'), 'from_treasury_id');
        $this->guardTreasuryDeposit($request->input('to_treasury_id'), 'to_treasury_id');

        // جلب التحويل المطلوب — قيدُ تحويلٍ في نطاق المستخدم، لا أيُّ قيد (انظر transferEdit)
        $journalEntry = JournalEntry::where('reference_type', ReferenceType::TRANSFER)->findOrFail($id);
        $this->authorizeCreatorScope($journalEntry->created_by_employee);

        // جلب الخزينة المصدر والهدف
        $fromTreasury = Account::find($request->from_treasury_id);
        $toTreasury = Account::find($request->to_treasury_id);

        // تحقق من توفر الرصيد في الخزينة المصدر
        if ($fromTreasury->balance < $request->amount) {
            return back()->withErrors(['error' => 'الرصيد غير كافٍ في الخزينة المختارة.']);
        }

        // التراجع عن التحويل السابق
        // foreach ($journalEntry->details as $detail) {
        //     if ($detail->is_debit) {
        //         // التراجع عن الإضافة إلى الخزينة المستقبلة
        //         $toTreasury->updateBalance($detail->debit, 'subtract');
        //     } else {
        //         // التراجع عن الخصم من الخزينة المرسلة
        //         $fromTreasury->updateBalance($detail->credit, 'add');
        //     }
        // }

        // تحديث القيد المحاسبي
        /* رقمُ القيد ومُنشئُه لا يُمسّان بالتعديل: كان الرقمُ (`TRF-…`)
           يُستبدل بمعرّف الخزينة المصدر، والمنشئُ بمن عدّل — فيخرج القيدُ
           من نطاق رؤية صاحبه. */
        $journalEntry->update([
            'date' => now(),
            'description' => 'تحويل المالية',
            'status' => 1,
        ]);

        // تحديث تفاصيل القيد المحاسبي
        foreach ($journalEntry->details as $detail) {
            if ($detail->is_debit) {
                // تحديث الخزينة المستقبلة
                $detail->update([
                    'account_id' => $toTreasury->id,
                    'description' => 'تحويل المالية إلى '.$toTreasury->code,
                    'debit' => $request->amount,
                    'credit' => 0,
                ]);
            } else {
                // تحديث الخزينة المرسلة
                $detail->update([
                    'account_id' => $fromTreasury->id,
                    'description' => 'تحويل المالية من '.$fromTreasury->code,
                    'debit' => 0,
                    'credit' => $request->amount,
                ]);
            }
        }

        // // تطبيق التحويل الجديد
        // $fromTreasury->updateBalance($request->amount, 'subtract');
        // $toTreasury->updateBalance($request->amount, 'add');

        OperationLog::record('updated', 'Account.Treasury', $journalEntry,
            'عدّل تحويلاً بين الخزائن: '.$fromTreasury->name.' ← '.$toTreasury->name
            .' بمبلغ '.$request->amount);

        return redirect()->route('treasury.index')->with('success', 'تم تحديث التحويل بنجاح!');
    }

    /**
     * شاشةُ التعديل تصل بمعرّف **الحساب** (الفهرس يعرض `accounts`)، لكنّ
     * شرطَي الإيداع والسحب يسكنان صفَّ `treasuries` — وهو ما يقرؤه
     * `App\Support\TreasuryAccessGuard`. فيُحمَّل الصفّان معاً: الحسابُ
     * للاسم والحالة، والخزينةُ للشرطين.
     */
    public function edit($id)
    {
        $account = Account::findOrFail($id);
        $treasury = Treasury::where('account_id', $account->id)->first();

        $employees = Employee::orderBy('first_name')->get();
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        return view('account::finance.treasury.edit', compact('account', 'treasury', 'employees', 'roles', 'branches'));
    }

    /**
     * حفظُ التعديل.
     *
     * **كان يكتب الشرطين في `accounts`** — `Account::findOrFail($id)` —
     * وجدولُ الحسابات لا يُقرأ منه شرطٌ قط: الحارسُ يسأل
     * `treasuries.deposit_permissions`. فكان تعديلُ صلاحية الخزينة من
     * الشاشة لا أثر له أبداً: الرسالةُ «راجع إعدادات الخزينة» تُرشد إلى
     * شاشةٍ لا تُغيّر شيئاً، ويبقى الموظفُ ممنوعاً من خزينته.
     *
     * والقيمةُ تُتحقَّق من وجودها: معرّفُ موظفٍ أو دورٍ أو فرعٍ محذوف
     * يقفل الخزينة على لا أحد.
     */
    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        /*
            حساباتٌ قديمة لا صفَّ لها في `treasuries` — «الخزينة الرئيسية»
            منها — فالتعديلُ لا يجوز أن يُردّ لأجل ذلك: يُنشأ الصفُّ
            ويُربَط بالحساب، وإلّا تعذّر حفظُ اسمٍ أو شرطٍ على أقدم خزائن
            المنشأة. والشرطُ الافتراضيّ «الكل» — وهو حالُها اليوم بلا صفّ.
        */
        $treasury = Treasury::firstOrNew(['account_id' => $account->id]);

        if (! $treasury->exists) {
            $treasury->type = (int) $account->type_accont === 1 ? 1 : 0;
            $treasury->status = (int) $account->is_active === 1 ? 1 : 0;
            $treasury->deposit_permissions = 0;
            $treasury->withdraw_permissions = 0;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:0,1',
            'deposit_permissions' => 'required|in:0,1,2,3',
            'withdraw_permissions' => 'required|in:0,1,2,3',
            'v_employee_id' => 'required_if:deposit_permissions,1|nullable|exists:employees,id',
            'v_functional_role_id' => 'required_if:deposit_permissions,2|nullable|exists:roles,id',
            'v_branch_id' => 'required_if:deposit_permissions,3|nullable|exists:branches,id',
            'c_employee_id' => 'required_if:withdraw_permissions,1|nullable|exists:employees,id',
            'c_functional_role_id' => 'required_if:withdraw_permissions,2|nullable|exists:roles,id',
            'c_branch_id' => 'required_if:withdraw_permissions,3|nullable|exists:branches,id',
        ], [
            'v_employee_id.required_if' => 'اختر الموظف المسموح له بالإيداع.',
            'v_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالإيداع.',
            'v_branch_id.required_if' => 'اختر الفرع المسموح له بالإيداع.',
            'c_employee_id.required_if' => 'اختر الموظف المسموح له بالسحب.',
            'c_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالسحب.',
            'c_branch_id.required_if' => 'اختر الفرع المسموح له بالسحب.',
            'v_employee_id.exists' => 'الموظف المختار للإيداع غير موجود.',
            'v_functional_role_id.exists' => 'الدور الوظيفي المختار للإيداع غير موجود.',
            'v_branch_id.exists' => 'الفرع المختار للإيداع غير موجود.',
            'c_employee_id.exists' => 'الموظف المختار للسحب غير موجود.',
            'c_functional_role_id.exists' => 'الدور الوظيفي المختار للسحب غير موجود.',
            'c_branch_id.exists' => 'الفرع المختار للسحب غير موجود.',
        ]);

        $oldName = $treasury->name;

        /* قيمةُ الشرط تتبع نوعَه، و«الكل» تُخزَّن `null` — كما في الإنشاء. */
        $scopeValue = fn (int $scope, array $keys) => match ($scope) {
            1 => $request->input($keys['employee']),
            2 => $request->input($keys['role']),
            3 => $request->input($keys['branch']),
            default => null,   // 0 = الكل
        };

        $treasury->name = $validated['name'];
        $treasury->description = $validated['description'] ?? $treasury->description;
        $treasury->status = $request->filled('status') ? (int) $validated['status'] : $treasury->status;
        $treasury->deposit_permissions = (int) $validated['deposit_permissions'];
        $treasury->withdraw_permissions = (int) $validated['withdraw_permissions'];

        $treasury->value_of_deposit_permissions = $scopeValue(
            (int) $validated['deposit_permissions'],
            ['employee' => 'v_employee_id', 'role' => 'v_functional_role_id', 'branch' => 'v_branch_id']
        );

        $treasury->value_of_withdraw_permissions = $scopeValue(
            (int) $validated['withdraw_permissions'],
            ['employee' => 'c_employee_id', 'role' => 'c_functional_role_id', 'branch' => 'c_branch_id']
        );

        $treasury->save();

        // الحسابُ يتبع الخزينةَ في الاسم والحالة، فلا يفترق العرضان
        $account->name = $treasury->name;
        $account->is_active = (int) $treasury->status === 1 ? 1 : 0;
        $account->save();

        OperationLog::record(
            'updated',
            'finance_log',
            $treasury->id, // ID النشاط المرتبط
            'تم تعديل الخزينة من **'.$oldName.'** إلى **'.$treasury->name.'**'
        );

        return redirect()->route('treasury.index')->with(['success' => 'تم تحديث الخزينة بنجاح !!']);
    }

    // Add the destroy method to handle DELETE requests
    public function destroy($id)
    {
        // البحث عن العنصر باستخدام الـ ID
        $treasury = Account::find($id);

        // إذا لم يتم العثور على العنصر
        if (! $treasury) {
            return redirect()
                ->back()
                ->with(['error' => 'الخزينة غير موجود!']);
        }

        // تسجيل النشاط في جدول السجلات قبل الحذف
        OperationLog::record(
            'deleted',
            'finance_log',
            $treasury->id,
            'تم حذف خزينة **'.$treasury->name.'**'
        );

        // حذف الخزينة
        $treasury->delete();

        // إعادة التوجيه مع رسالة نجاح
        return redirect()->route('treasury.index')->with('success', 'تم حذف الخزينة بنجاح!');
    }

    public function create_account_bank()
    {
        $employees = Employee::orderBy('first_name')->get();
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $banks = Banks::forCountry();

        return view('account::finance.treasury.create_account_bank', compact('employees', 'roles', 'branches', 'banks'));
    }

    public function store_account_bank(Request $request)
    {
        // Generated by Copilot
        // التحقق من صحة البيانات
        $request->validate([
            'name' => 'required|string|max:255',
            /*
                البنكُ من الفهرس لا نصٌّ حرّ: الحقلُ الحرّ يُسجِّل «الراجحي»
                و«الرّاجحي» و«al rajhi» بنوكاً ثلاثة، ويتعذّر عندها أيُّ
                تجميعٍ أو مطابقةٍ آلية.
            */
            'bank_name' => ['required', 'string', Rule::in(array_keys(Banks::forCountry()))],

            /*
                رقمُ الحساب آيبانٌ مُتحقَّقٌ منه: صيغةً وطولاً وخانتَي تحقّق
                (mod-97) ومطابقةً لرمز البنك المختار. الطولُ وحدَه لا يكفي —
                خانةٌ مبدَّلة تُنتج رقماً صحيحَ الطول فاسدَ المضمون، والحوالةُ
                ترتدّ بعد أيام.
            */
            'account_number' => ['required', 'string', new Iban($request->input('bank_name'))],
            'currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'status' => 'required|integer|in:0,1',
            'description' => 'nullable|string',
            'deposit_permissions' => 'required|integer|in:0,1,2,3',
            'withdraw_permissions' => 'required|integer|in:0,1,2,3',
            // قيمةُ الشرط تتبع نوعَه، ووجودُها يُتحقَّق: معرّفٌ محذوف يقفل الحساب على لا أحد
            'v_employee_id' => 'required_if:deposit_permissions,1|nullable|exists:employees,id',
            'v_functional_role_id' => 'required_if:deposit_permissions,2|nullable|exists:roles,id',
            'v_branch_id' => 'required_if:deposit_permissions,3|nullable|exists:branches,id',
            'c_employee_id' => 'required_if:withdraw_permissions,1|nullable|exists:employees,id',
            'c_functional_role_id' => 'required_if:withdraw_permissions,2|nullable|exists:roles,id',
            'c_branch_id' => 'required_if:withdraw_permissions,3|nullable|exists:branches,id',
        ], [
            'v_employee_id.required_if' => 'اختر الموظف المسموح له بالإيداع.',
            'v_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالإيداع.',
            'v_branch_id.required_if' => 'اختر الفرع المسموح له بالإيداع.',
            'c_employee_id.required_if' => 'اختر الموظف المسموح له بالسحب.',
            'c_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالسحب.',
            'c_branch_id.required_if' => 'اختر الفرع المسموح له بالسحب.',
            'v_employee_id.exists' => 'الموظف المختار للإيداع غير موجود.',
            'v_functional_role_id.exists' => 'الدور الوظيفي المختار للإيداع غير موجود.',
            'v_branch_id.exists' => 'الفرع المختار للإيداع غير موجود.',
            'c_employee_id.exists' => 'الموظف المختار للسحب غير موجود.',
            'c_functional_role_id.exists' => 'الدور الوظيفي المختار للسحب غير موجود.',
            'c_branch_id.exists' => 'الفرع المختار للسحب غير موجود.',
            'name.required' => 'اسم الحساب مطلوب',
            'name.string' => 'اسم الحساب يجب أن يكون نصاً',
            'name.max' => 'اسم الحساب يجب ألا يتجاوز 255 حرفاً',
            'bank_name.required' => 'اسم البنك مطلوب',
            'bank_name.in' => 'اختر بنكاً من القائمة.',
            'account_number.required' => 'رقم الآيبان مطلوب',
            'currency.required' => 'العملة مطلوبة',
            'currency.size' => 'رمز العملة يجب أن يكون من ثلاثة أحرف',
            'currency.exists' => 'العملة المختارة غير مفعَّلة في إعدادات النظام',
            'status.required' => 'الحالة مطلوبة',
            'status.integer' => 'الحالة يجب أن تكون قيمة صحيحة',
            'status.in' => 'الحالة يجب أن تكون 0 أو 1',
            'deposit_permissions.required' => 'صلاحيات الإيداع مطلوبة',
            'deposit_permissions.integer' => 'صلاحيات الإيداع يجب أن تكون قيمة صحيحة',
            'deposit_permissions.in' => 'صلاحيات الإيداع يجب أن تكون واحدة من القيم المسموحة',
            'withdraw_permissions.required' => 'صلاحيات السحب مطلوبة',
            'withdraw_permissions.integer' => 'صلاحيات السحب يجب أن تكون قيمة صحيحة',
            'withdraw_permissions.in' => 'صلاحيات السحب يجب أن تكون واحدة من القيم المسموحة',
        ]);

        DB::beginTransaction();
        try {
            // إنشاء الحساب البنكي في جدول Treasury
            $treasury = new Treasury;
            $treasury->name = $request->name;
            $treasury->type = 1; // نوع الحساب (بنكي)
            $treasury->bank_name = $request->bank_name;
            // الآيبان يُخزَّن موحَّداً: بلا فراغاتٍ ولا شرطات وبحروفٍ كبيرة،
            // فالمقارنةُ والبحثُ لا يتعثّران بصيغةِ إدخالٍ مختلفة.
            $treasury->account_number = Banks::normalize($request->account_number);
            $treasury->currency = $request->currency; // Store as integer (0 for Riyal, 1 for Pound)
            $treasury->status = $request->status;
            $treasury->description = $request->description ?? 'حساب بنكي جديد';
            $treasury->deposit_permissions = $request->deposit_permissions;
            $treasury->withdraw_permissions = $request->withdraw_permissions;

            // permissions-----------------------------------

            // view employee
            /*
                قيمة الشرط تتبع نوعه — كما في إنشاء الخزينة.

                كان `else` يلتقط «الكل» (0) و«فرع محدد» (3) معاً، ويكتب
                `''` حين لا فرع. فحسابٌ مفتوح للجميع يحمل قيمةً توهم بقيد.
            */
            $scopeValue = fn (int $scope, array $keys) => match ($scope) {
                1 => $request->input($keys['employee']),
                2 => $request->input($keys['role']),
                3 => $request->input($keys['branch']),
                default => null,   // 0 = الكل
            };

            $treasury->value_of_deposit_permissions = $scopeValue(
                (int) $request->deposit_permissions,
                ['employee' => 'v_employee_id', 'role' => 'v_functional_role_id', 'branch' => 'v_branch_id']
            );

            // view employee
            $treasury->value_of_withdraw_permissions = $scopeValue(
                (int) $request->withdraw_permissions,
                ['employee' => 'c_employee_id', 'role' => 'c_functional_role_id', 'branch' => 'c_branch_id']
            );

            // حفظ الحساب البنكي مرة واحدة فقط
            $treasury->save();

            // إنشاء الحساب المرتبط في جدول Account
            $account = new Account;
            $account->name = $request->name;
            $account->type_accont = 1; // نوع الحساب (بنكي)
            $account->is_active = $request->status; // حالة الحساب (تتطابق مع حالة الخزينة)
            $account->parent_id = AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT); // الأب الافتراضي للحسابات البنكية
            $account->code = $this->generateNextCode(AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
            $account->balance_type = 'debit'; // نوع الرصيد (مدين)
            $account->save();

            // الربط الذي بغيابه لا تُقرأ صلاحيتا الإيداع والسحب أبداً
            $treasury->account_id = $account->id;
            $treasury->save();

            // تسجيل النشاط في جدول السجلات
            OperationLog::record(
                'created',
                'finance_log',
                $treasury->id, // ID النشاط المرتبط
                'تم اضافة حساب بنكي  **'.$request->name.'**'
            );

            DB::commit();

            // إعادة التوجيه مع رسالة نجاح
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'أُضيف الحساب البنكي «'.$treasury->name.'».',
                    'redirect' => route('treasury.index'),
                ]);
            }

            return redirect()->route('treasury.index')->with('success', 'تم إضافة الحساب البنكي بنجاح!');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Treasury', 'store_account_bank');

            DB::rollback();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تعذّر حفظ الحساب البنكي.',
                    'error_details' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء حفظ الحساب البنكي: '.$e->getMessage()]);
        }
    }

    public function edit_account_bank($id)
    {
        $treasury = Treasury::findOrFail($id);
        $employees = Employee::orderBy('first_name')->get();
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $banks = Banks::forCountry();

        return view('account::finance.treasury.edit_account_bank', compact('treasury', 'employees', 'roles', 'branches', 'banks'));
    }

    public function update_account_bank(Request $request, $id)
    {
        $treasury = Treasury::findOrFail($id);

        // Generated by Copilot
        // التحقق من صحة البيانات
        $request->validate([
            'name' => 'required|string|max:255',
            /*
                البنكُ من الفهرس لا نصٌّ حرّ: الحقلُ الحرّ يُسجِّل «الراجحي»
                و«الرّاجحي» و«al rajhi» بنوكاً ثلاثة، ويتعذّر عندها أيُّ
                تجميعٍ أو مطابقةٍ آلية.
            */
            'bank_name' => ['required', 'string', Rule::in(array_keys(Banks::forCountry()))],

            /*
                رقمُ الحساب آيبانٌ مُتحقَّقٌ منه: صيغةً وطولاً وخانتَي تحقّق
                (mod-97) ومطابقةً لرمز البنك المختار. الطولُ وحدَه لا يكفي —
                خانةٌ مبدَّلة تُنتج رقماً صحيحَ الطول فاسدَ المضمون، والحوالةُ
                ترتدّ بعد أيام.
            */
            'account_number' => ['required', 'string', new Iban($request->input('bank_name'))],
            'currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'status' => 'required|integer|in:0,1',
            'description' => 'nullable|string',
            'deposit_permissions' => 'required|integer|in:0,1,2,3',
            'withdraw_permissions' => 'required|integer|in:0,1,2,3',
            // قيمةُ الشرط تتبع نوعَه، ووجودُها يُتحقَّق: معرّفٌ محذوف يقفل الحساب على لا أحد
            'v_employee_id' => 'required_if:deposit_permissions,1|nullable|exists:employees,id',
            'v_functional_role_id' => 'required_if:deposit_permissions,2|nullable|exists:roles,id',
            'v_branch_id' => 'required_if:deposit_permissions,3|nullable|exists:branches,id',
            'c_employee_id' => 'required_if:withdraw_permissions,1|nullable|exists:employees,id',
            'c_functional_role_id' => 'required_if:withdraw_permissions,2|nullable|exists:roles,id',
            'c_branch_id' => 'required_if:withdraw_permissions,3|nullable|exists:branches,id',
        ], [
            'v_employee_id.required_if' => 'اختر الموظف المسموح له بالإيداع.',
            'v_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالإيداع.',
            'v_branch_id.required_if' => 'اختر الفرع المسموح له بالإيداع.',
            'c_employee_id.required_if' => 'اختر الموظف المسموح له بالسحب.',
            'c_functional_role_id.required_if' => 'اختر الدور الوظيفي المسموح له بالسحب.',
            'c_branch_id.required_if' => 'اختر الفرع المسموح له بالسحب.',
            'v_employee_id.exists' => 'الموظف المختار للإيداع غير موجود.',
            'v_functional_role_id.exists' => 'الدور الوظيفي المختار للإيداع غير موجود.',
            'v_branch_id.exists' => 'الفرع المختار للإيداع غير موجود.',
            'c_employee_id.exists' => 'الموظف المختار للسحب غير موجود.',
            'c_functional_role_id.exists' => 'الدور الوظيفي المختار للسحب غير موجود.',
            'c_branch_id.exists' => 'الفرع المختار للسحب غير موجود.',
            'name.required' => 'اسم الحساب مطلوب',
            'name.string' => 'اسم الحساب يجب أن يكون نصاً',
            'name.max' => 'اسم الحساب يجب ألا يتجاوز 255 حرفاً',
            'bank_name.required' => 'اسم البنك مطلوب',
            'bank_name.in' => 'اختر بنكاً من القائمة.',
            'account_number.required' => 'رقم الآيبان مطلوب',
            'currency.required' => 'العملة مطلوبة',
            'currency.size' => 'رمز العملة يجب أن يكون من ثلاثة أحرف',
            'currency.exists' => 'العملة المختارة غير مفعَّلة في إعدادات النظام',
            'status.required' => 'الحالة مطلوبة',
            'status.integer' => 'الحالة يجب أن تكون قيمة صحيحة',
            'status.in' => 'الحالة يجب أن تكون 0 أو 1',
            'deposit_permissions.required' => 'صلاحيات الإيداع مطلوبة',
            'deposit_permissions.integer' => 'صلاحيات الإيداع يجب أن تكون قيمة صحيحة',
            'deposit_permissions.in' => 'صلاحيات الإيداع يجب أن تكون واحدة من القيم المسموحة',
            'withdraw_permissions.required' => 'صلاحيات السحب مطلوبة',
            'withdraw_permissions.integer' => 'صلاحيات السحب يجب أن تكون قيمة صحيحة',
            'withdraw_permissions.in' => 'صلاحيات السحب يجب أن تكون واحدة من القيم المسموحة',
        ]);

        DB::beginTransaction();
        try {
            $treasury->name = $request->name;
            $treasury->type = 1; // حساب بنكي
            $treasury->status = $request->status;
            $treasury->bank_name = $request->bank_name;
            // الآيبان يُخزَّن موحَّداً: بلا فراغاتٍ ولا شرطات وبحروفٍ كبيرة،
            // فالمقارنةُ والبحثُ لا يتعثّران بصيغةِ إدخالٍ مختلفة.
            $treasury->account_number = Banks::normalize($request->account_number);
            $treasury->currency = $request->currency; // Store as integer (0 for Riyal, 1 for Pound)
            $treasury->description = $request->description;
            $treasury->deposit_permissions = $request->deposit_permissions;
            $treasury->withdraw_permissions = $request->withdraw_permissions;

            /*
                قيمةُ الشرط تتبع نوعَه — نفسُ ما صُحِّح في الإنشاء.

                كان `else` يلتقط «الكل» (0) و«فرع محدد» (3) معاً، ويكتب `''`
                حين لا فرع. والعمودُ `int`، فيردّ المحرّكُ الحفظَ كلَّه:
                «Incorrect integer value: ''» — فيتعذّر **تعديلُ أيّ حساب
                بنكيّ** صلاحيتُه «الكل»، وهي الحالةُ الافتراضية.

                والإصلاحُ كان قد وقع في `store_account_bank` ولم يُنقَل إلى
                هنا، فصحّت الإضافةُ وبقي التعديلُ مكسوراً.
            */
            $scopeValue = fn (int $scope, array $keys) => match ($scope) {
                1 => $request->input($keys['employee']),
                2 => $request->input($keys['role']),
                3 => $request->input($keys['branch']),
                default => null,   // 0 = الكل
            };

            $treasury->value_of_deposit_permissions = $scopeValue(
                (int) $request->deposit_permissions,
                ['employee' => 'v_employee_id', 'role' => 'v_functional_role_id', 'branch' => 'v_branch_id']
            );

            $treasury->value_of_withdraw_permissions = $scopeValue(
                (int) $request->withdraw_permissions,
                ['employee' => 'c_employee_id', 'role' => 'c_functional_role_id', 'branch' => 'c_branch_id']
            );

            $treasury->update();

            // Update the associated account as well
            $account = Account::where('name', $treasury->getOriginal('name'))->first();
            if ($account) {
                $account->name = $request->name;
                $account->is_active = $request->status;
                $account->update();
            }

            OperationLog::record('updated', 'Account.Treasury', $treasury,
                'عدّل الحساب البنكي «'.$treasury->name.'» ('.$treasury->bank_name.')');

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'حُدِّث الحساب البنكي «'.$treasury->name.'».',
                    'redirect' => route('treasury.index'),
                ]);
            }

            return redirect()->route('treasury.index')->with('success', 'تم تحديث الحساب البنكي بنجاح!');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Treasury', 'update_account_bank');

            DB::rollback();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تعذّر تحديث الحساب البنكي.',
                    'error_details' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء تحديث الحساب البنكي: '.$e->getMessage()]);
        }
    }

    public function show($id)
    {
        $treasury = $this->getTreasury($id);
        $branches = $this->getBranches();

        // إذا كان الطلب AJAX، إرجاع البيانات فقط
        if (request()->ajax()) {
            return $this->getOperationsData($id);
        }

        return view('account::finance.treasury.show', compact('treasury', 'branches'));
    }

    public function getOperationsData($id)
    {
        $treasury = $this->getTreasury($id);

        // الاعتماد الكلي على القيود المحاسبية التي تخص هذه الخزينة
        $transactions = $this->getTransactions($id);

        // تجهيز العمليات من تفاصيل القيود فقط
        $allOperations = $this->processAllOperations($transactions, $treasury);

        // تطبيق الفلاتر (تاريخ/نوع/مبلغ)
        $allOperations = $this->applyFilters($allOperations);

        // تقسيم إلى صفحات
        $operationsPaginator = $this->paginateOperations($allOperations);

        if (request()->ajax()) {
            return response()->json([
                'operations' => $operationsPaginator->items(),
                'pagination' => [
                    'current_page' => $operationsPaginator->currentPage(),
                    'last_page' => $operationsPaginator->lastPage(),
                    'per_page' => $operationsPaginator->perPage(),
                    'total' => $operationsPaginator->total(),
                    'has_more_pages' => $operationsPaginator->hasMorePages(),
                    'prev_page_url' => $operationsPaginator->previousPageUrl(),
                    'next_page_url' => $operationsPaginator->nextPageUrl(),
                    'from' => $operationsPaginator->firstItem(),
                    'to' => $operationsPaginator->lastItem(),
                ],
            ]);
        }

        return compact('operationsPaginator');
    }

    private function getTreasury($id)
    {
        return Account::findOrFail($id);
    }

    /*
        **سطورُ قيود الخزينة كلُّها — بلا سقف.**

        كان الجلبُ يقف عند أقدم ١٠٠٠ سطر (`limit(1000)` بترتيبٍ تصاعدي)،
        فتختفي كلُّ حركةٍ بعد تاريخٍ ما في الخزائن النشطة، ويُحسب الرصيدُ
        الجاري ناقصاً. والسقفُ كان لتجنّب الذاكرة مع تحميل مودلات القيد
        والفاتورة والعميل؛ والشاشةُ لا تقرأ منها إلا أعمدةً قليلة، فتُجلب
        تلك الأعمدةُ وحدها بانضمامٍ خام.
    */
    private function getTransactions($id)
    {
        return DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('d.account_id', $id)
            ->select([
                'd.debit',
                'd.credit',
                'e.id as entry_id',
                'e.description',
                'e.date',
                'e.created_at',
                'e.accounting_period_id',
            ])
            ->orderBy('d.id')
            ->get();
    }

    private function getTransfers($id)
    {
        return JournalEntry::whereHas('details', function ($query) use ($id) {
            $query->where('account_id', $id);
        })
            ->with(['details.account'])
            ->where('reference_type', ReferenceType::TRANSFER)
            ->orderBy('created_at', 'desc')
            ->limit(1000) // Add limit to prevent memory issues
            ->get();
    }

    private function getExpenses($id)
    {
        return Expense::where('treasury_id', $id)
            ->with(['expenses_category', 'account.branch', 'client'])
            ->orderBy('created_at', 'asc')
            ->limit(1000) // Add limit to prevent memory issues
            ->get();
    }

    private function getRevenues($id)
    {
        return Revenue::where('account_id', $id)
            ->with(['account', 'paymentVoucher', 'treasury', 'bankAccount', 'journalEntry'])
            ->orderBy('created_at', 'asc')
            ->limit(1000) // Add limit to prevent memory issues
            ->get();
    }

    private function getReceipts($id)
    {
        return Receipt::where('treasury_id', $id)
            ->with(['client', 'incomes_category', 'account'])
            ->orderBy('created_at', 'asc')
            ->limit(1000) // Add limit to prevent memory issues
            ->get();
    }

    private function getPayments($id)
    {
        return PaymentsProcess::where('treasury_id', $id)
            ->with(['client', 'invoice', 'employee'])
            ->orderBy('created_at', 'asc')
            ->limit(1000) // Add limit to prevent memory issues
            ->get();
    }

    private function processAllOperations($transactions, $treasury)
    {
        $allOperations = [];

        foreach ($transactions as $row) {
            // قيم المدين/الدائن لتأثير القيد على الخزينة
            $deposit = (float) ($row->debit ?? 0);
            $withdraw = (float) ($row->credit ?? 0);
            $netAmount = $deposit - $withdraw; // صافي الأثر

            // نوع العملية من وصف القيد
            $description = trim($row->description ?? '') ?: 'قيد محاسبي';
            $operationType = $this->detectOperationType($description);
            $operationLabel = $this->operationLabelFromType($operationType, $description);

            // التاريخ
            $dateValue = $row->date ?: $row->created_at;
            $timestamp = $dateValue ? (int) strtotime($dateValue) : 0;

            $allOperations[] = [
                'operation' => $operationLabel,
                'description' => $description,
                'deposit' => $deposit > 0 ? $deposit : 0,
                'withdraw' => $withdraw > 0 ? $withdraw : 0,
                'balance_after' => 0,
                'date' => $dateValue,
                'type' => $operationType,
                'journal_entry_id' => (int) $row->entry_id,
                // الحركةُ ترث فترةَ قيدها: لا جدولَ لها تُختَم فيه
                'accounting_period_id' => $row->accounting_period_id,
                'timestamp' => $timestamp,
                'operation_amount' => $netAmount,
                'created_at' => $row->created_at,
            ];
        }

        // ترتيب تصاعدي لحساب الرصيد بشكل صحيح (التاريخ ثم رقم القيد)
        usort($allOperations, function ($a, $b) {
            return [$a['timestamp'], $a['journal_entry_id']] <=> [$b['timestamp'], $b['journal_entry_id']];
        });

        $currentBalance = 0;
        foreach ($allOperations as &$operation) {
            $currentBalance += $operation['operation_amount'];
            $operation['balance_after'] = $currentBalance;
            $operation['balance_change'] = ($operation['operation_amount'] >= 0 ? '+' : '').number_format($operation['operation_amount'], 2);
        }
        unset($operation);

        // ترتيب نهائي للعرض (تنازلي)
        return array_reverse($allOperations);
    }

    private function detectOperationType($description)
    {
        $lower = mb_strtolower($description);
        if (str_contains($lower, 'سند قبض')) {
            return 'receipt';
        }
        if (str_contains($lower, 'سند صرف')) {
            return 'expense';
        }
        if (str_contains($lower, 'تحويل')) {
            return 'transfer';
        }
        if (str_contains($lower, 'دفع')) {
            return 'payment';
        }
        if (str_contains($lower, 'إيراد') || str_contains($lower, 'ايراد')) {
            return 'revenue';
        }

        return 'general';
    }

    private function operationLabelFromType($type, $fallback)
    {
        switch ($type) {
            case 'receipt': return 'سند قبض';
            case 'expense': return 'سند صرف';
            case 'transfer': return 'تحويل';
            case 'payment': return 'عملية دفع';
            case 'revenue': return 'إيراد';
            default: return $fallback;
        }
    }

    private function applyFilters($operations)
    {
        /*
            **حركةُ الخزينة تُنسب إلى الفترة بقيدها.**

            لا جدولَ للحركة: هي سطورُ قيودٍ، فترثُ الفترةَ من قيدها المختوم.
            والعملياتُ هنا مصفوفةٌ مبنيّةٌ سلفاً، فيُرشَّح كلُّ عنصرٍ بمعرّف
            فترة قيده — لا بمدىً يُقارَن بتاريخه.

            وكانت تُعرض منذ أوّل عمليةٍ في تاريخ الخزينة، فيُقرأ رصيدُ سنواتٍ
            على أنّه حركةُ الفترة.
        */
        $period = AccountingPeriodFilter::resolve(request());
        $operationType = request('operation_type');
        $amountFrom = request('amount_from');
        $amountTo = request('amount_to');

        /*
            العملياتُ مصفوفةٌ مبنيّةٌ سلفاً لا استعلام، فيُرشَّح كلُّ عنصرٍ
            بمعرّف فترة قيده. و«سنةٌ كاملة» تعني فتراتِها كلَّها.
        */
        if (! $period['all'] && ! $period['no_calendar']) {
            $wanted = $period['period_ids']
                ?: ($period['period_id'] !== null ? [$period['period_id']] : []);

            if ($period['none'] || $wanted) {
                $operations = array_filter($operations, function ($op) use ($period, $wanted) {
                    $entryPeriod = $op['accounting_period_id'] ?? null;

                    return $period['none']
                        ? $entryPeriod === null
                        : in_array((int) $entryPeriod, array_map('intval', $wanted), true);
                });
            }
        }

        // حقلا «من/إلى تاريخ» في الشاشة كانا يُرسَلان ولا يُقرآن
        $fromDate = request('from_date');
        $toDate = request('to_date');

        if ($fromDate || $toDate) {
            $operations = array_filter($operations, function ($op) use ($fromDate, $toDate) {
                $day = substr((string) $op['date'], 0, 10);

                return (! $fromDate || $day >= $fromDate) && (! $toDate || $day <= $toDate);
            });
        }

        if ($operationType) {
            $operations = array_filter($operations, function ($op) use ($operationType) {
                return $op['type'] === $operationType;
            });
        }

        if ($amountFrom) {
            $operations = array_filter($operations, function ($op) use ($amountFrom) {
                $amount = max($op['deposit'], $op['withdraw']);

                return $amount >= $amountFrom;
            });
        }

        if ($amountTo) {
            $operations = array_filter($operations, function ($op) use ($amountTo) {
                $amount = max($op['deposit'], $op['withdraw']);

                return $amount <= $amountTo;
            });
        }

        return array_values($operations);
    }

    private function updateBalance($currentBalance, $amount, $type)
    {
        return $type === 'إيداع' ? $currentBalance + $amount : $currentBalance - $amount;
    }

    private function paginateOperations($allOperations)
    {
        $requestedPerPage = (int) request()->get('per_page', 50);
        $perPage = in_array($requestedPerPage, [20, 50, 100]) ? $requestedPerPage : 50;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedOperations = array_slice($allOperations, $offset, $perPage);

        return new LengthAwarePaginator(
            $paginatedOperations,
            count($allOperations),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function updateStatus($id)
    {
        // البحث عن العنصر باستخدام الـ ID
        $treasury = Account::find($id);

        // إذا لم يتم العثور على العنصر
        if (! $treasury) {
            return redirect()
                ->back()
                ->with(['error' => 'الخزينة غير موجود!']);
        }

        // تحديث حالة العنصر
        $newStatus = ! $treasury->is_active;
        $treasury->update(['is_active' => $newStatus]);

        // تعطيل خزينةٍ يمنع الصرف منها — تغييرٌ يستحقّ أثراً
        OperationLog::record($newStatus ? 'activated' : 'deactivated',
            'Account.Treasury', $treasury,
            ($newStatus ? 'فعّل' : 'عطّل').' الخزينة «'.$treasury->name.'»');

        // تحديث حالة الحساب المرتبط (في حالة الحاجة إلى تحديث حساب منفصل)
        // نظرًا لأن الخزائن والحسابات ممثلة بنفس نموذج Account في هذا السياق،
        // فإن التحديث أعلاه يكفي

        // إعادة التوجيه مع رسالة نجاح
        $statusMessage = $newStatus ? 'نشط' : 'غير نشط';

        return redirect()
            ->back()
            ->with(['success' => 'تم تغيير حالة الخزينة إلى '.$statusMessage.' بنجاح!']);
    }

    public function getTransfersData($id)
    {
        $transfers = $this->getTransfers($id);
        $formattedTransfers = [];

        foreach ($transfers as $transfer) {
            $fromAccount = null;
            $toAccount = null;
            $amount = $transfer->details->sum('debit');

            foreach ($transfer->details as $detail) {
                if ($detail->is_debit) {
                    $toAccount = $detail->account;
                } else {
                    $fromAccount = $detail->account;
                }
            }

            if ($fromAccount && $toAccount) {
                $formattedTransfers[] = [
                    'id' => $transfer->id,
                    'reference_number' => $transfer->reference_number,
                    'date' => $transfer->date,
                    'from_account' => $fromAccount,
                    'to_account' => $toAccount,
                    'amount' => $amount,
                ];
            }
        }

        return response()->json([
            'transfers' => $formattedTransfers,
        ]);
    }

    private function getBranches()
    {
        return Branch::all();
    }
}
