<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetDep;
use App\Models\AssetDepreciation;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Revenue;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use TCPDF;

class AssetsController extends Controller
{
    use AccountingOperations;

    public function __construct()
    {
        // صلاحيات الأصول الثابتة — الكتالوج الجديد account.assets.*
        $this->middleware('permission.role:account.assets.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.assets.show', ['only' => ['show']]);
        $this->middleware('permission.role:account.assets.create', ['only' => ['create', 'store', 'getNextAssetCode']]);
        $this->middleware('permission.role:account.assets.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.assets.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:account.assets.sell', ['only' => ['showSellForm', 'sell']]);
        $this->middleware('permission.role:account.assets.transactions', ['only' => ['transactions']]);
        $this->middleware('permission.role:account.assets.print', ['only' => ['generatePdf']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // وسياقُ الفرع من الشريط العلوي — المستند يخصّ الفرع الذي أُنشئ فيه
        $query = AssetDepreciation::with(['employee', 'client', 'depreciation'])
            ->forBranch(\App\Support\HeaderBranchScope::nodeId());

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->code.'%');
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date_2')) {
            $query->whereDate('created_at', '>=', $request->from_date_2);
        }

        if ($request->filled('to_date_2')) {
            $query->whereDate('created_at', '<=', $request->to_date_2);
        }

        $assets = $query->latest()->paginate(10);

        $employees = Employee::all();

        return view('account::asol.index', compact('assets', 'employees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // جلب جميع حسابات الأصول الثابتة بشكل تراتبي (الأب وجميع الأبناء والأحفاد)
        // باستثناء حسابات مجمع الإهلاك
        $accounts = $this->getAllFixedAssetAccounts();

        // جميع الحسابات (لحساب النقدية والبنوك)
        $accounts_all = Account::whereNotIn('parent_id', [AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS)])->get();

        // حسابات النقدية والبنوك فقط
        $cash_bank_accounts = Account::where(function ($query) {
            $query->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })->get();

        $employees = Employee::all();
        $clients = Client::all();

        // توليد الكود التالي للأصل
        $nextAssetCode = $this->generateNextAssetCode();

        return view('account::asol.create', compact('accounts', 'employees', 'accounts_all', 'cash_bank_accounts', 'nextAssetCode'));
    }

    /**
     * جلب جميع حسابات الأصول الثابتة بشكل تراتبي
     * يشمل الحساب الأب وجميع الأبناء والأحفاد
     * باستثناء حسابات مجمع الإهلاك
     */
    private function getAllFixedAssetAccounts()
    {
        // جلب كل الحسابات التي تنتمي لفرع الأصول الثابتة
        $allAccounts = collect();

        // جلب الحسابات المباشرة تحت الأصول الثابتة (باستثناء مجمع الإهلاك)
        $directChildren = Account::where('parent_id', AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS))
            ->where('id', '!=', AccountingSettings::id('accumulated_depreciation', SystemAccountIds::ACCUMULATED_DEPRECIATION))
            ->orderBy('code')
            ->get();

        foreach ($directChildren as $account) {
            $allAccounts->push($account);
            // جلب الأحفاد بشكل تراتبي
            $this->getChildAccounts($account->id, $allAccounts);
        }

        return $allAccounts;
    }

    /**
     * جلب الحسابات الفرعية بشكل تراتبي
     */
    private function getChildAccounts($parentId, &$collection)
    {
        $children = Account::where('parent_id', $parentId)
            ->orderBy('code')
            ->get();

        foreach ($children as $child) {
            $collection->push($child);
            // استدعاء تراتبي للأحفاد
            $this->getChildAccounts($child->id, $collection);
        }
    }

    /**
     * توليد كود الأصل التالي
     */
    public function generateNextAssetCode(): string
    {
        /*
            كان الرقم يُشتقّ من كود آخر أصلٍ بالمعرّف — بادئة `AST-` مكتوبة
            وخمس خانات ثابتة وبلا تصفير دوري. وأصلٌ حُذف يترك رقمه شاغراً
            فيُعاد استعماله، وأصلٌ رُقّم يدوياً بصيغةٍ أخرى يكسر النمط كلّه.

            صار من قسم «الأصول» في شاشة ترقيم المستندات، معايَنةً بلا
            استهلاك — الحجز يقع عند الحفظ في `store()`.
        */
        return peek_document_number('origin');
    }

    /**
     * الحصول على الكود التالي للأصل عبر AJAX
     */
    public function getNextAssetCode()
    {
        return response()->json([
            'code' => $this->generateNextAssetCode(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // شراء الأصل يولّد قيداً بتاريخ الشراء
        $this->guardPeriod($request->input('date_price'), 'date_price');

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'code' => 'nullable|unique:asset_depreciations,code',
            'name' => 'required|string|max:255',
            'date_price' => 'required|date',
            'date_service' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'place' => 'nullable|string|max:255',
            'region_age' => 'nullable|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'purchase_value' => 'required|numeric|min:0',
            'currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'cash_account' => 'required|exists:accounts,id',
            'tax1' => 'nullable|in:1,2,3',
            'tax2' => 'nullable|in:1,2,3',
            'employee_id' => 'nullable|exists:employees,id',
            'client_id' => 'nullable|exists:clients,id',
            'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
            'dep_method' => 'nullable|in:1,2,3,4',
            'salvage_value' => 'nullable|numeric|min:0',
            'dep_rate' => 'nullable|numeric|min:0',
            'duration' => 'nullable|integer|min:1',
            'period' => 'nullable|in:1,2,3',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100',
            'declining_duration' => 'nullable|integer|min:1',
            'declining_period' => 'nullable|in:monthly,quarterly,yearly',
            'unit_name' => 'nullable|string|max:255',
            'total_units' => 'nullable|integer|min:1',
            'depreciation_end_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            // توليد كود الأصل تلقائياً إذا لم يتم إدخاله
            // الحفظ يستهلك الرقم فعلاً، فلا يتكرّر بين مستخدمَين فتحا الشاشة معاً
            $assetCode = $request->code ?: next_document_number('origin');

            // =====================================================
            // 1. إنشاء حساب الأصل في شجرة الحسابات
            // =====================================================
            $assetAccount = new Account;
            $assetAccount->name = $request->name;
            $assetAccount->type = 'sub'; // حساب فرعي
            $assetAccount->is_active = 1;
            $assetAccount->parent_id = $request->account_id; // الحساب الأب المختار
            $assetAccount->code = $this->generateNextCode($request->account_id);
            $assetAccount->balance_type = Account::DEBIT; // الأصول مدينة بطبيعتها
            $assetAccount->account_type = Account::ASSETS;
            $assetAccount->balance = 0;
            $assetAccount->save();

            // =====================================================
            // 2. إنشاء سجل الأصل
            // =====================================================
            $asset = new AssetDepreciation;
            $asset->code = $assetCode;
            $asset->name = $request->name;
            $asset->date_price = $request->date_price;
            $asset->date_service = $request->date_service;
            $asset->place = $request->place;
            $asset->region_age = $request->region_age;
            $asset->quantity = $request->quantity;
            $asset->description = $request->description;
            $asset->purchase_value = $request->purchase_value;
            $asset->currency = $request->currency;
            $asset->cash_account = $request->cash_account;
            $asset->tax1 = $request->tax1;
            $asset->tax2 = $request->tax2;
            $asset->account_id = $assetAccount->id; // ربط الأصل بالحساب المنشأ
            $asset->employee_id = $request->employee_id;
            $asset->client_id = $request->client_id;

            // تحديد حالة الأصل (مهلك أم لا)
            if ($request->region_age && $request->region_age > 0) {
                $purchaseDate = Carbon::parse($request->date_price);
                $depreciationYears = $request->region_age;
                $fullyDepreciatedDate = $purchaseDate->copy()->addYears(intval($depreciationYears));

                if (Carbon::now()->greaterThan($fullyDepreciatedDate)) {
                    $asset->status = 3; // مهلك
                } else {
                    $asset->status = 1; // في الخدمة
                }
            } else {
                $asset->status = 1; // في الخدمة
            }

            // رفع المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $file->storeAs('assets/attachments', $fileName, 'public');
                $asset->attachments = 'assets/attachments/'.$fileName;
            }

            $asset->save();

            // =====================================================
            // 3. إنشاء سجل الإهلاك إذا تم تحديد طريقة إهلاك
            // =====================================================
            if ($request->filled('dep_method') && $request->dep_method != 4) {
                $assetDep = new AssetDep;
                $assetDep->asset_id = $asset->id;
                $assetDep->salvage_value = $request->salvage_value ?? 0;
                $assetDep->dep_method = $request->dep_method;

                // القسط الثابت
                if ($request->dep_method == 1) {
                    $assetDep->dep_rate = $request->dep_rate;
                    $assetDep->duration = $request->duration;
                    $assetDep->period = $request->period;
                }

                // القسط المتناقص
                if ($request->dep_method == 2) {
                    $assetDep->dep_rate = $request->depreciation_rate;
                    $assetDep->duration = $request->declining_duration;
                    $assetDep->period = $request->declining_period == 'monthly' ? 2 : ($request->declining_period == 'yearly' ? 3 : 2);
                }

                // وحدات الإنتاج
                if ($request->dep_method == 3) {
                    $assetDep->unit_name = $request->unit_name;
                    $assetDep->total_units = $request->total_units;
                    $assetDep->end_date = $request->depreciation_end_date;
                }

                $assetDep->book_value = $asset->purchase_value;
                $assetDep->acc_dep = 0;
                $assetDep->save();
            }

            // =====================================================
            // 4. إنشاء قيد اليومية لشراء الأصل
            /*
                القيد المحاسبي — بالتعريف المشترك نفسه الذي يستعمله التعديل.

                كان يُبنى هنا سطرين متوازنين على قيمة الشراء وحدها، ويُبنى في
                `update()` بصيغةٍ ثانية. فافترقا: الأول يتجاهل الضريبة،
                والثاني يقرأ عموداً غير موجود. التعريفُ واحدٌ الآن.
            */
            $journal = $this->writePurchaseEntry(
                $asset,
                (int) $assetAccount->id,
                $request->cash_account ? (int) $request->cash_account : null
            );

            // =====================================================
            // 5. تسجيل السجل (Log)
            // =====================================================
            OperationLog::record(
                'created',
                'finance_log',
                $asset->id,
                'تم إضافة أصل جديد: '.$request->name.' بقيمة '.number_format($asset->purchase_value, 2).' - قيد رقم: '.($journal?->id ?? 0)
            );

            DB::commit();

            return redirect()->route('Assets.show', $asset->id)
                ->with('success_message', 'تم إضافة الأصل بنجاح وإنشاء القيد المحاسبي')
                ->with('asset_id', $asset->id)
                ->with('journal_id', $journal?->id);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.Assets', 'store');

            DB::rollback();
            Log::error('خطأ في إضافة الأصل: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء إضافة الأصل: '.$e->getMessage()]);
        }
    }

    /**
     * توليد الكود التالي للحساب في الشجرة
     */
    /**
     * كود الحساب الفرعي التالي — يُستدعى من الشاشة عبر AJAX أيضاً.
     *
     * كان يبني كوداً منقوطاً (`113.1`) و`accounts.code` عمودٌ صحيح، فيُقصّ
     * إلى `113` ويصطدم بحسابٍ قائم. التوليد الآن في
     * `AccountingOperations::nextChildAccountCode()`.
     */
    /**
     * مرجع قيد شراء الأصل — صيغةٌ واحدة يُكتَب بها ويُبحَث عنها.
     *
     * كان `store()` يكتب `'ASP-'.$asset->id` بينما يبحث `update()` عن
     * `$asset->id` مجرَّداً. فلا يجد القيدَ أبداً، ويُتخطّى بلوكُ التعديل
     * كلُّه صامتاً: يتغيّر الأصل ويبقى قيدُه على قيمته الأولى.
     */
    public static function purchaseEntryReference(int $assetId): string
    {
        return 'ASP-'.$assetId;
    }

    /**
     * نسبة الضريبة بحسب نوعها.
     *
     * الأنواع ثلاثة في النموذج: 1 قيمةٌ مضافة، و2 صفرية، و3 معفاة.
     * والنسبة تُقرأ من «إعدادات الضريبة» لا من رقمٍ مكتوب — فالنموذج
     * يعرض «15%» بينما القيمة الفعلية في `tax_sittings`.
     */
    protected function taxRate($taxType): float
    {
        if ((int) $taxType !== 1) {
            return 0.0;
        }

        $rate = (float) (DB::table('tax_sittings')->value('tax') ?? 0);

        // تُقبل الصيغتان: 0.15 و15
        return $rate > 1 ? $rate / 100 : $rate;
    }

    /** ضريبة الأصل — مجموع الضريبتين على قيمة الشراء. */
    protected function assetTaxAmount($asset): float
    {
        $base = (float) $asset->purchase_value;

        return round($base * ($this->taxRate($asset->tax1) + $this->taxRate($asset->tax2)), 2);
    }

    /**
     * كتابة قيد شراء الأصل — إنشاءً أو إعادةَ كتابة.
     *
     * **ما كان**
     *
     * القيد يُبنى في موضعين بصيغتين مختلفتين: `store()` يكتب سطرين
     * متوازنين على قيمة الشراء، و`update()` يعيد بناءهما بحساب
     * `$asset->asset_account` — وهو **عمودٌ لا وجود له** في
     * `asset_depreciations` (أعمدتها `account_id` و`cash_account`). فحتى لو
     * وُجد القيد لَكُتب السطر المدين بحسابٍ فارغ.
     *
     * وكلاهما يتجاهل الضريبة رأساً: أصلٌ بضريبة قيمةٍ مضافة يُسجَّل بقيمته
     * قبل الضريبة، فلا تدخل «الضريبة المدفوعة» الميزانية، ولا يُنقص من
     * النقدية ما دُفع فعلاً. مثالٌ قائم في القاعدة: الأصل #6 بقيمة 15,000
     * و`tax1 = 1`، وقيده 15,000 مدين و15,000 دائن — بلا سطرِ ضريبةٍ بمقدار
     * 2,250، والمدفوع الحقيقي 17,250.
     *
     * **ما صار**
     *
     * تعريفٌ واحد يستدعيه الطرفان، فلا يفترقان مرّةً أخرى. والقيد ثلاثة
     * أسطر حين تكون ضريبة:
     *
     *     مدين  حساب الأصل            قيمة الشراء
     *     مدين  الضريبة المدفوعة      مقدار الضريبة
     *     دائن  النقدية/البنك         المجموع
     *
     * والحسابُ المدين هو `account_id` من الأصل — حسابُه في الشجرة.
     */
    protected function writePurchaseEntry(
        $asset,
        int $assetAccountId,
        ?int $cashAccountId,
        ?JournalEntry $existing = null
    ): ?JournalEntry {
        if (! $cashAccountId) {
            return $existing;
        }

        $value = (float) $asset->purchase_value;
        $tax = $this->assetTaxAmount($asset);
        $total = round($value + $tax, 2);

        $journal = $existing ?: new JournalEntry;
        $journal->date = Carbon::parse($asset->date_price);
        $journal->description = 'شراء أصل ثابت: '.$asset->name.' - كود: '.$asset->code;
        $journal->reference_number = self::purchaseEntryReference((int) $asset->id);
        $journal->reference_type = ReferenceType::ASSET_PURCHASE;
        $journal->status = 1;
        $journal->is_posted = 1;
        $journal->created_by_employee = $journal->created_by_employee ?: auth()->id();
        $journal->approved_by_employee = auth()->id();
        $journal->save();

        // تُمسَح البنود وتُكتب من جديد: التعديل قد يغيّر عددها
        $journal->details()->delete();

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $assetAccountId,
            'description' => 'شراء أصل: '.$asset->name,
            'debit' => $value,
            'credit' => 0,
            'is_debit' => true,
        ]);

        if ($tax > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID),
                'description' => 'ضريبة القيمة المضافة على شراء أصل: '.$asset->name,
                'debit' => $tax,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $cashAccountId,
            'description' => 'دفع ثمن أصل: '.$asset->name,
            'debit' => 0,
            'credit' => $total,
            'is_debit' => false,
        ]);

        Account::updateParentBalances($assetAccountId, true);
        Account::updateParentBalances($cashAccountId, true);

        return $journal;
    }

    public function generateNextCode($parentId)
    {
        $code = $this->nextChildAccountCode($parentId);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($code);
        }

        return $code;
    }

    public function transactions($accountId)
    {

        $journalEntries = JournalEntryDetail::where('account_id', $accountId)
            ->with('account')
            ->get();

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

        // إرسال البيانات إلى الواجهة
        return view('account::accounts_chart.tree_details', compact('treasury', 'operationsPaginator', 'branches'));
    }

    private function getTreasury($id)
    {
        return Account::findOrFail($id);
    }

    private function getBranches()
    {
        return Branch::all();
    }

    private function getTransactions($id)
    {
        return JournalEntryDetail::where('account_id', $id)
            ->with(['journalEntry' => function ($query) {
                $query->with('invoice', 'client');
            }])
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
        $currentBalance = 0;
        $allOperations = [];

        // معالجة المدفوعات
        foreach ($transactions as $transaction) {
            $amount = $transaction->debit > 0 ? $transaction->debit : $transaction->credit;
            $type = $transaction->debit > 0 ? 'إيداع' : 'سحب';

            $currentBalance = $this->updateBalance($currentBalance, $amount, $type);

            $allOperations[] = [
                'operation' => '  قبد رقم # '.$transaction->journalEntry->id,
                'deposit' => $type === 'إيداع' ? $amount : 0,
                'withdraw' => $type === 'سحب' ? $amount : 0,
                'balance_after' => $currentBalance,
                'journalEntry' => $transaction->journalEntry->id,
                'date' => $transaction->journalEntry->date,
                'invoice' => $transaction->journalEntry->invoice,
                'client' => $transaction->journalEntry->client,
                'type' => 'transaction',
            ];
        }

        // معالجة سندات الصرف
        foreach ($expenses as $expense) {
            $currentBalance -= $expense->amount;

            $allOperations[] = [
                'operation' => 'سند صرف: '.$expense->description,
                'deposit' => 0,
                'withdraw' => $expense->amount,
                'balance_after' => $currentBalance,
                'date' => $expense->date,
                'invoice' => null,
                'client' => $expense->client,
                'type' => 'expense',
            ];
        }

        // معالجة سندات القبض
        foreach ($revenues as $revenue) {
            $currentBalance += $revenue->amount;

            $allOperations[] = [
                'operation' => 'سند قبض: '.$revenue->description,
                'deposit' => $revenue->amount,
                'withdraw' => 0,
                'balance_after' => $currentBalance,
                'date' => $revenue->date,
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

    public function show(string $id)
    {
        try {
            // البحث عن الأصل مع علاقاته
            $asset = AssetDepreciation::with(['employee', 'client', 'account', 'depreciation'])->findOrFail($id);

            // البحث عن الحساب المرتبط بالأصل
            $account = Account::find($asset->account_id);

            // جلب العمليات المالية إذا كان الحساب موجوداً
            $operationsPaginator = null;
            if ($account) {
                // جلب بيانات الخزينة
                $treasury = $this->getTreasury($account->id);
                $branches = $this->getBranches();

                // جلب العمليات المالية
                $transactions = $this->getTransactions($account->id);
                $transfers = $this->getTransfers($account->id);
                $expenses = $this->getExpenses($account->id);
                $revenues = $this->getRevenues($account->id);

                // معالجة العمليات وحساب الرصيد
                $allOperations = $this->processOperations($transactions, $transfers, $expenses, $revenues, $treasury);

                // ترتيب العمليات حسب التاريخ
                usort($allOperations, function ($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                // تقسيم العمليات إلى صفحات
                $operationsPaginator = $this->paginateOperations($allOperations);
            }

            return view('account::asol.show', compact('asset', 'account', 'operationsPaginator'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Assets', 'show');

            return redirect()->route('Assets.index')
                ->with('error', 'حدث خطأ أثناء عرض الأصل: '.$e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $asset = AssetDepreciation::findOrFail($id);
        // جلب جميع حسابات الأصول الثابتة بشكل تراتبي
        $accounts = $this->getAllFixedAssetAccounts();
        $accounts_all = Account::whereNotIn('parent_id', [AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS)])->get();
        // حسابات النقدية والبنوك فقط
        $cash_bank_accounts = Account::where(function ($query) {
            $query->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })->get();
        $employees = Employee::all();
        $clients = Client::all();

        return view('account::asol.edit', compact('asset', 'accounts', 'employees', 'clients', 'accounts_all', 'cash_bank_accounts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->guardPeriod($request->input('date_price'), 'date_price');

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'code' => 'required|unique:asset_depreciations,code,'.$id,
            'name' => 'required|string|max:255',
            'date_price' => 'required|date',
            'date_service' => 'required|date',
            'account_id' => 'nullable|exists:accounts,id',
            'place' => 'nullable|string|max:255',
            'region_age' => 'nullable|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'purchase_value' => 'required|numeric|min:0',
            'currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'cash_account' => 'nullable|exists:accounts,id',
            'tax1' => 'nullable|in:1,2,3',
            'tax2' => 'nullable|in:1,2,3',
            'employee_id' => 'nullable|exists:employees,id',
            'client_id' => 'nullable|exists:clients,id',
            'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
            'dep_method' => 'nullable|in:1,2,3,4',
            'salvage_value' => 'nullable|numeric',
            'dep_rate' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'period' => 'nullable|integer',
            'depreciation_rate' => 'nullable|numeric',
            'declining_duration' => 'nullable|integer',
            'declining_period' => 'nullable|integer',
            'unit_name' => 'nullable|string',
            'total_units' => 'nullable|integer',
            'depreciation_end_date' => 'nullable|date',

        ]);

        try {
            DB::beginTransaction();

            // البحث عن الأصل مع علاقة الإهلاك
            $asset = AssetDepreciation::with('depreciation')->findOrFail($id);
            $originalPurchaseValue = $asset->purchase_value;
            $originalCashAccount = $asset->cash_account;

            // تسجيل السجل
            OperationLog::record(
                'updated',
                'finance_log',
                $asset->id,
                'تم تعديل اصل **'.$request->name.'**'
            );

            // تحديث بيانات الأصل
            $asset->code = $request->code;
            $asset->name = $request->name;
            $asset->date_price = $request->date_price;
            $asset->date_service = $request->date_service;
            $asset->place = $request->place;
            $asset->region_age = $request->region_age;
            $asset->quantity = $request->quantity;
            $asset->description = $request->description;
            $asset->purchase_value = $request->purchase_value;
            $asset->currency = $request->currency;
            $asset->cash_account = $request->cash_account;
            $asset->tax1 = $request->tax1;
            $asset->tax2 = $request->tax2;
            $asset->employee_id = $request->employee_id;
            $asset->client_id = $request->client_id;

            // تحديد حالة الأصل (مهلك أم لا)
            if ($request->region_age && $request->region_age > 0) {
                $purchaseDate = Carbon::parse($request->date_price);
                $depreciationYears = $request->region_age;
                $fullyDepreciatedDate = $purchaseDate->copy()->addYears(intval($depreciationYears));

                if (Carbon::now()->greaterThan($fullyDepreciatedDate)) {
                    $asset->status = 3; // مهلك
                } else {
                    $asset->status = 1; // في الخدمة
                }
            } else {
                $asset->status = 1; // في الخدمة
            }

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                if ($asset->attachments) {
                    Storage::disk('public')->delete($asset->attachments);
                }
                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $filePath = $file->storeAs('assets/attachments', $fileName, 'public');
                $asset->attachments = 'assets/attachments/'.$fileName;
            }

            // تحديث الحساب المرتبط إذا كان موجوداً (يمكنك تخزين ID الحساب في مكان آخر)
            // نظراً لعدم وجود عمود asset_account في الجدول، سنتجاوز هذه الخطوة

            // حفظ التغييرات على الأصل
            $asset->save();

            // تحديث أو إنشاء سجل الإهلاك
            if ($request->filled('dep_method') && $request->dep_method != 4) {
                $assetDep = $asset->depreciation ?? new AssetDep;
                $assetDep->asset_id = $asset->id;
                $assetDep->salvage_value = $request->salvage_value ?? 0;
                $assetDep->dep_method = $request->dep_method;

                // القسط الثابت
                if ($request->dep_method == 1) {
                    $assetDep->dep_rate = $request->dep_rate;
                    $assetDep->duration = $request->duration;
                    $assetDep->period = $request->period;
                }

                // القسط المتناقص
                if ($request->dep_method == 2) {
                    $assetDep->dep_rate = $request->depreciation_rate;
                    $assetDep->duration = $request->declining_duration;
                    $assetDep->period = $request->declining_period == 'monthly' ? 2 : ($request->declining_period == 'yearly' ? 3 : 2);
                }

                // وحدات الإنتاج
                if ($request->dep_method == 3) {
                    $assetDep->unit_name = $request->unit_name;
                    $assetDep->total_units = $request->total_units;
                    $assetDep->end_date = $request->depreciation_end_date;
                }

                // تحديث القيمة الدفترية إذا تغيرت قيمة الشراء
                if (! $assetDep->exists) {
                    $assetDep->book_value = $asset->purchase_value;
                    $assetDep->acc_dep = 0;
                } else {
                    // إذا كان السجل موجوداً، نعيد حساب القيمة الدفترية
                    $assetDep->book_value = $asset->purchase_value - $assetDep->acc_dep;
                }

                $assetDep->save();
            } elseif ($request->dep_method == 4 && $asset->depreciation) {
                // حذف سجل الإهلاك إذا تم اختيار "بدون إهلاك"
                $asset->depreciation->delete();
            }

            // حساب الفرق في القيمة الشرائية
            $valueDifference = $request->purchase_value - $originalPurchaseValue;

            /*
                إعادة كتابة قيد الشراء بعد التعديل.

                كان البحث `where('reference_number', $asset->id)` بينما
                `store()` يكتب `'ASP-'.$asset->id`. فلا يُعثر على القيد أبداً
                ويُتخطّى البلوك كلُّه صامتاً: تتغيّر قيمة الأصل ويبقى قيدُه
                على قيمته الأولى، فتختلّ الميزانية بفارق التعديل.

                وكان يبني السطر المدين بـ`$asset->asset_account` — عمودٌ لا
                وجود له في الجدول — فحتى لو عُثر على القيد لَكُتب بحسابٍ فارغ.

                وكلاهما — الإنشاء والتعديل — يتجاهل الضريبة. صارا يستدعيان
                `writePurchaseEntry()` نفسها، فلا يفترقان ثانيةً، والضريبة
                فيها سطرٌ قائم.

                وأرصدةُ الحسابات تُحدَّث داخلها بـ`updateParentBalances`؛
                والتعديل اليدوي على `balance` الذي كان هنا أُزيل: كان يخصم
                الفارق من النقدية بينما القيد يُعاد بناؤه كاملاً، فيُحتسَب
                الأثر مرّتين.
            */
            $existing = JournalEntry::where(
                'reference_number',
                self::purchaseEntryReference((int) $asset->id)
            )->first();

            $this->writePurchaseEntry(
                $asset,
                (int) $asset->account_id,
                $request->cash_account ? (int) $request->cash_account : null,
                $existing
            );

            // إن تغيّر حساب النقدية أُعيد احتساب رصيد القديم أيضاً
            if ($originalCashAccount && $originalCashAccount != $request->cash_account) {
                Account::updateParentBalances((int) $originalCashAccount, true);
            }

            DB::commit();

            return redirect()->route('Assets.index')
                ->with('success_message', 'تم تحديث الأصل بنجاح')
                ->with('asset_id', $asset->id);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Assets', 'update');

            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء تحديث الأصل. '.$e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            // البحث عن الأصل
            $asset = AssetDepreciation::findOrFail($id);
            OperationLog::record(
                'deleted',
                'finance_log',
                $asset->id, // ID النشاط المرتبط
                'تم حذف الاصل **'.$asset->name.'**'
            );
            // حذف الصورة إذا كانت موجودة
            if ($asset->attachments) {
                Storage::disk('public')->delete($asset->attachments);
            }

            // البحث عن الحساب المرتبط بالأصل وحذفه
            $account = ChartOfAccount::where('code', $asset->code)->first();
            if ($account) {
                $account->delete();
            }

            // حذف القيود المحاسبية المرتبطة
            $journalEntries = JournalEntry::where('reference_number', 'ASSET-'.$asset->id)->get();
            foreach ($journalEntries as $entry) {
                $entry->details()->delete(); // حذف تفاصيل القيد
                $entry->delete(); // حذف القيد
            }

            // حذف الأصل
            $asset->delete();

            DB::commit();

            return redirect()->route('Assets.index')
                ->with('success_message', 'تم حذف الأصل بنجاح')
                ->with('success_details', [
                    'حساب الأستاذ' => 'منى العليا #11204',
                    'مجمع إهلاك - منى العليا' => '#224001',
                    'مصروف إهلاك - منى العليا' => '#32001',
                ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Assets', 'destroy');

            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف الأصل: '.$e->getMessage());
        }
    }

    /**
     * بيع الأصل
     */
    public function sell(Request $request, $id)
    {
        $validated = $request->validate([
            'sale_price' => 'required|numeric|min:0',
            'sale_date' => 'required|date',
            'cash_account' => 'required|exists:accounts,id',
            'tax1' => 'nullable|in:1,2,3',
            'tax2' => 'nullable|in:1,2,3',
        ]);

        /*
            بيع الأصل يُرحّل قيد تخلّصٍ كامل — نقدية مدينة، والأصل ومجمّع
            إهلاكه دائنان، والفرق ربحٌ أو خسارة. وكان يُكتب في أي تاريخ:
            شهرٌ أُقفل في «الفترات المحاسبية» لا يمنعه. والحارس بتاريخ
            **البيع** لا باليوم، لأن القيد يُرحَّل بذلك التاريخ.
        */
        $this->guardPeriod($validated['sale_date'], 'sale_date');

        /*
            بيع الأصل يُرحّل قيد تخلّصٍ كامل — نقدية مدينة، والأصل ومجمّع
            إهلاكه دائنان، والفرق ربحٌ أو خسارة. وكان يُكتب في أي تاريخ:
            شهرٌ أُقفل في «الفترات المحاسبية» لا يمنعه. والحارس بتاريخ
            **البيع** لا باليوم، لأن القيد يُرحَّل بذلك التاريخ.
        */
        $this->guardPeriod($validated['sale_date'], 'sale_date');

        try {
            DB::beginTransaction();

            // البحث عن الأصل
            $asset = AssetDepreciation::with('depreciation')->findOrFail($id);

            // حساب الربح أو الخسارة
            $bookValue = $asset->depreciation ? $asset->depreciation->book_value : $asset->purchase_value;
            $profit = $validated['sale_price'] - $bookValue;

            // إنشاء رقم مرجعي للعملية
            $reference = 'ASSET-SALE-'.$asset->id;

            // إنشاء قيد محاسبي للبيع
            $journalEntry = new JournalEntry;
            $journalEntry->date = $validated['sale_date'];
            $journalEntry->description = 'بيع الأصل: '.$asset->name;
            $journalEntry->reference_number = $reference;
            $journalEntry->created_by_employee = auth()->id();
            $journalEntry->save();

            // مدين - حساب النقدية (استلام المبلغ)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $validated['cash_account'],
                'debit' => $validated['sale_price'],
                'credit' => 0,
                'description' => 'استلام مبلغ بيع الأصل: '.$asset->name,
            ]);

            // دائن - حساب الأصل (إخراج الأصل)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $asset->account_id,
                'debit' => 0,
                'credit' => $bookValue,
                'description' => 'بيع الأصل: '.$asset->name,
            ]);

            // إذا كان هناك ربح أو خسارة
            if ($profit != 0) {
                // يمكنك تحديد حسابات الربح والخسارة من الإعدادات
                // هنا نستخدم حساب افتراضي، يمكنك تعديله
                $profitLossAccountId = $profit > 0 ? 50 : 51; // مثال: 50 = ربح، 51 = خسارة

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $profitLossAccountId,
                    'debit' => $profit < 0 ? abs($profit) : 0,
                    'credit' => $profit > 0 ? $profit : 0,
                    'description' => ($profit > 0 ? 'ربح' : 'خسارة').' بيع الأصل: '.$asset->name,
                ]);
            }

            // تحديث رصيد حساب النقدية (يزيد)
            $cashAccount = Account::find($validated['cash_account']);
            if ($cashAccount) {
                $cashAccount->balance += $validated['sale_price'];
                $cashAccount->save();
            }

            // تحديث رصيد حساب الأصل (ينقص)
            $assetAccount = Account::find($asset->account_id);
            if ($assetAccount) {
                $assetAccount->balance -= $bookValue;
                $assetAccount->save();
            }

            // تحديث حالة الأصل
            $asset->update([
                'status' => 2, // تم البيع
                'sale_price' => $validated['sale_price'],
                'sale_date' => $validated['sale_date'],
                'reference' => $reference,
            ]);

            // تسجيل السجل
            OperationLog::record(
                'recorded',
                'finance_log',
                $asset->id,
                'تم بيع الأصل **'.$asset->name.'** بمبلغ '.$validated['sale_price']
            );

            DB::commit();

            return redirect()->route('Assets.show', $asset->id)
                ->with('success_message', 'تم بيع الأصل بنجاح')
                ->with('success_details', [
                    'سعر البيع' => number_format($validated['sale_price'], 2),
                    'القيمة الدفترية' => number_format($bookValue, 2),
                    $profit > 0 ? 'الربح' : 'الخسارة' => number_format(abs($profit), 2),
                ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.Assets', 'sell');

            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء بيع الأصل: '.$e->getMessage());
        }
    }

    /**
     * عرض صفحة بيع الأصل
     */
    public function showSellForm($id)
    {
        $asset = AssetDepreciation::with('depreciation')->findOrFail($id);
        $accounts_all = Account::whereNotIn('parent_id', [6])->get();

        return view('account::asol.sell', compact('asset', 'accounts_all'));
    }

    /**
     * إنشاء تقرير PDF للأصل
     */
    public function generatePdf($id)
    {
        $asset = AssetDepreciation::findOrFail($id);

        // إنشاء PDF جديد
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تعيين معلومات الوثيقة
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('تقرير الأصل التفصيلي');

        // تعيين الهوامش
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // تعطيل رأس وتذييل الصفحة
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // إضافة صفحة جديدة
        $pdf->AddPage();

        // تعيين اتجاه النص من اليمين إلى اليسار
        $pdf->setRTL(true);

        // تعيين الخط
        $pdf->SetFont('aealarabiya', '', 14);

        // بداية المحتوى
        $html = view('account::asol.pdf', compact('asset'))->render();

        // إضافة المحتوى للـ PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // إخراج الملف
        return $pdf->Output('asset-report.pdf', 'I');
    }

    /**
     * حساب تاريخ نهاية الإهلاك
     */
    private function calculateEndDate($startDate, $duration, $period)
    {
        $start = Carbon::parse($startDate);

        switch ($period) {
            case 1: // يومي
                return $start->addDays($duration);
            case 2: // شهري
                return $start->addMonths($duration);
            case 3: // سنوي
                return $start->addYears($duration);
            default:
                return $start->addYears($duration);
        }
    }
}
