<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\Banks;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Treasury;
use App\Rules\Iban;
use App\Services\OnlineStoreSettingsService;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use App\Support\Scopes;
use App\Support\TreasuryAccessGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreTreasuryController extends Controller
{
    /*
     * نفس صلاحيات `account.treasury.*`/`account.treasury_transfers.*` التي
     * تحرس شاشات الخزائن في وحدة الحسابات — هذا الكنترولر يدير نفس
     * موديل/جدول `Account` (فرع الخزائن)، فليس قسماً مستقلاً.
     *
     * وكلُّ إجراءٍ هنا يعمل على **خزينة متجرٍ داخل نطاق المستخدم** وحدها
     * (`findStoreTreasury()`): كانت الإجراءات تقبل معرّف أيّ حسابٍ في شجرة
     * الحسابات — فيُعاد تسمية حسابٍ لا صلة له بالمتجر أو يُحذف من رابط
     * خزائن المتجر.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.treasury.view', ['only' => ['index', 'show', 'getOperationsData']]);
        $this->middleware('permission.role:account.treasury.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.treasury.bank_create', ['only' => ['createBank', 'storeBank']]);
        $this->middleware('permission.role:account.treasury.edit', ['only' => ['edit', 'update', 'editBank', 'updateBank']]);
        $this->middleware('permission.role:account.treasury.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:account.treasury.toggle_status', ['only' => ['updateStatus']]);
        $this->middleware('permission.role:account.treasury_transfers.view', ['only' => ['transferCreate', 'getTransfersData']]);
        $this->middleware('permission.role:account.treasury_transfers.create', ['only' => ['transferStore']]);
    }

    /**
     * عرض قائمة الخزائن والحسابات البنكية
     */
    public function index(Request $request)
    {
        // فلترٌ مرسَلٌ مصفوفةً (`status[]=x`) ليس فلتراً — يُسقَط بدل أن يُسقِط الصفحة
        foreach (['keywords', 'category', 'status', 'branch'] as $filter) {
            if (is_array($request->query($filter))) {
                $request->query->remove($filter);
            }
        }

        $query = $this->storeTreasuryQuery();

        // تطبيق الفلاتر
        if ($request->filled('keywords') && is_string($request->keywords)) {
            $keywords = $request->keywords;
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'like', '%'.$keywords.'%')
                    ->orWhere('code', 'like', '%'.$keywords.'%');
            });
        }

        // النوع من موضع الحساب في الشجرة لا من `type_accont` وحده — انظر TreasuryAccounts
        if ($request->filled('category')) {
            $query->where('parent_id', $request->category == 1 ? $this->bankParentId() : $this->cashParentId());
        }

        if (in_array((string) $request->input('status'), ['0', '1'], true)) {
            $query->where('is_active', (int) $request->status);
        }

        // فلتر الفرع — مقصوصٌ على نطاق المستخدم، ويشمل ما تحت العقدة المختارة
        if ($request->filled('branch') && is_numeric($request->branch)) {
            $node = DataScope::clampNode((int) $request->branch);
            $query->whereIn('branch_id', $node ? $this->branchSubtree($node) : [0]);
        }

        // إحصائيات (بنفس نطاق المتجر والفرع)
        $storeTreasuries = $this->storeTreasuryQuery();
        $totalCount = (clone $storeTreasuries)->count();
        $activeCount = (clone $storeTreasuries)->where('is_active', 1)->count();
        $treasuryCount = (clone $storeTreasuries)->where('parent_id', $this->cashParentId())->count();
        $bankCount = (clone $storeTreasuries)->where('parent_id', $this->bankParentId())->count();
        $totalBalance = (clone $storeTreasuries)->sum('balance');

        // الترتيب والتصفح
        $treasuries = $query->orderBy('id', 'DESC')->paginate(10)->appends($request->query());

        // وصف الخزينة وعملتها يسكنان صفّ `treasuries` لا الحساب
        $treasuryRows = Treasury::whereIn('account_id', $treasuries->pluck('id'))->get()->keyBy('account_id');
        $branchNames = Branch::whereIn('id', $treasuries->pluck('branch_id')->filter()->unique())->pluck('name', 'id');

        $branches = DataScope::branchOptions();
        $defaultTreasuryId = (int) OnlineStoreSettingsService::get('default_treasury_id');
        $bankParentId = $this->bankParentId();

        return view('onlinestore::treasury.index', compact(
            'treasuries',
            'treasuryRows',
            'branchNames',
            'branches',
            'defaultTreasuryId',
            'bankParentId',
            'totalCount',
            'activeCount',
            'treasuryCount',
            'bankCount',
            'totalBalance'
        ));
    }

    /**
     * عرض صفحة إنشاء خزينة جديدة
     */
    public function create()
    {
        $branches = DataScope::branchOptions();
        $defaultBranchId = $this->defaultBranchId();

        return view('onlinestore::treasury.create', compact('branches', 'defaultBranchId'));
    }

    /**
     * حفظ خزينة جديدة
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|in:0,1',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ], [
            'name.required' => 'اسم الخزينة مطلوب',
        ], $this->attributeNames());

        DB::beginTransaction();
        try {
            $isActive = (int) $request->input('is_active', 1);

            /*
                الحساب أولاً بكودٍ حقيقيٍّ من شجرة الحسابات.

                كان يُحفظ بكودٍ مؤقّت `0` ثم يُستبدل بمعرّفه — و`accounts.code`
                فريد، وفي الشجرة حسابٌ قديم ما زال يحمل `0`، فيُرفض كل حفظٍ
                بـ«Duplicate entry '0' for key accounts_code_unique»: هذا سبب
                «الخزينة لا تُحفظ».
            */
            $account = new Account;
            $account->name = $request->name;
            $account->type_accont = 0;
            $account->is_active = $isActive;
            $account->parent_id = $this->cashParentId();
            // نوع الحساب يرثه من أبيه (أصول) — وبلا ذلك يسقط من تقارير تُجمَّع بالنوع
            $account->account_type = Account::where('id', $this->cashParentId())->value('account_type') ?: Account::ASSETS;
            $account->is_store_treasury = true;
            $account->branch_id = $this->resolveWriteBranchId($request->input('branch_id'));
            $account->balance_type = 'debit';
            $account->code = $this->generateNextCode($this->cashParentId());
            $account->save();

            // شرطا الإيداع والسحب «الكل» — خزينة المتجر يحكمها الفرع ونطاق
            // الرؤية، وما يُضبط لاحقاً من شاشة خزائن الحسابات يُحترم كما هو
            $treasury = new Treasury;
            $treasury->name = $request->name;
            $treasury->account_id = $account->id;
            $treasury->type = 0;
            $treasury->status = $isActive;
            $treasury->currency = CurrencySettings::code();
            $treasury->description = $request->description ?: 'خزينة المتجر الإلكتروني';
            $treasury->deposit_permissions = 0;
            $treasury->withdraw_permissions = 0;
            $treasury->save();

            // تسجيل النشاط
            OperationLog::record('created', 'finance_log', $treasury, 'تم اضافة خزينة **'.$request->name.'**');

            DB::commit();

            return redirect()->route('store_treasury.index')->with('success', 'تم إضافة الخزينة بنجاح!');
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollback();

            OperationLog::failure($e, 'OnlineStore.StoreTreasury', 'store');

            return back()->withInput()->withErrors(['error' => 'تعذّر حفظ الخزينة. '.$this->readableError($e)]);
        }
    }

    /**
     * عرض صفحة إنشاء حساب بنكي
     */
    public function createBank()
    {
        $banks = Banks::forCountry();
        $branches = DataScope::branchOptions();
        $defaultBranchId = $this->defaultBranchId();

        return view('onlinestore::treasury.create_bank', compact('banks', 'branches', 'defaultBranchId'));
    }

    /**
     * حفظ حساب بنكي جديد
     *
     * نفس تحقُّق ومنطق Account\TreasuryController::store_account_bank() —
     * بنكٌ من الفهرس لا نصٌّ حرّ، وآيبانٌ مُتحقَّقٌ منه (طولاً وخانتَي تحقّق
     * ومطابقةً لرمز البنك)، مع ربط الخزينة بحسابها المحاسبي.
     */
    public function storeBank(Request $request)
    {
        $request->validate($this->bankRules($request), $this->bankMessages(), $this->attributeNames());

        DB::beginTransaction();
        try {
            $isActive = (int) $request->input('status', 1);

            // إنشاء الحساب المرتبط أولاً — الخزينة تحتاج معرّفه عند الحفظ
            $account = new Account;
            $account->name = $request->name;
            $account->type_accont = 1;
            $account->is_active = $isActive;
            $account->parent_id = $this->bankParentId();
            $account->account_type = Account::where('id', $this->bankParentId())->value('account_type') ?: Account::ASSETS;
            $account->is_store_treasury = true;
            $account->branch_id = $this->resolveWriteBranchId($request->input('branch_id'));
            $account->code = $this->generateNextCode($this->bankParentId());
            $account->balance_type = 'debit';
            $account->save();

            // إنشاء الحساب البنكي في جدول Treasury
            $treasury = new Treasury;
            $treasury->name = $request->name;
            $treasury->account_id = $account->id;
            $treasury->type = 1;
            $treasury->bank_name = $request->bank_name;
            // الآيبان يُخزَّن موحَّداً: بلا فراغاتٍ ولا شرطات وبحروفٍ كبيرة
            $treasury->account_number = Banks::normalize($request->account_number);
            $treasury->currency = $request->currency;
            $treasury->status = $isActive;
            $treasury->description = $request->description ?: 'حساب بنكي للمتجر الإلكتروني';
            $treasury->deposit_permissions = 0;
            $treasury->withdraw_permissions = 0;
            $treasury->save();

            // تسجيل النشاط
            OperationLog::record('created', 'finance_log', $treasury, 'تم اضافة حساب بنكي **'.$request->name.'**');

            DB::commit();

            return redirect()->route('store_treasury.index')->with('success', 'تم إضافة الحساب البنكي بنجاح!');
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollback();

            OperationLog::failure($e, 'OnlineStore.StoreTreasury', 'storeBank');

            return back()->withInput()->withErrors(['error' => 'تعذّر حفظ الحساب البنكي. '.$this->readableError($e)]);
        }
    }

    /**
     * عرض تفاصيل الخزينة
     */
    public function show($id)
    {
        $treasury = $this->findStoreTreasury($id);

        if (request()->ajax()) {
            return $this->getOperationsData($id);
        }

        $treasuryRow = Treasury::where('account_id', $treasury->id)->first();
        $branches = Branch::all();
        $branchName = $treasury->branch_id ? Branch::where('id', $treasury->branch_id)->value('name') : null;
        $currencyCode = $treasuryRow?->currency ?: CurrencySettings::code();
        $isBank = $this->isBankAccount($treasury);
        $isDefaultTreasury = (int) OnlineStoreSettingsService::get('default_treasury_id') === (int) $treasury->id;

        return view('onlinestore::treasury.show', compact('treasury', 'treasuryRow', 'branches', 'branchName', 'currencyCode', 'isBank', 'isDefaultTreasury'));
    }

    /**
     * عرض صفحة تعديل الخزينة
     */
    public function edit($id)
    {
        $account = $this->findStoreTreasury($id);

        // الحساب البنكي له شاشته — بياناته (البنك والآيبان والعملة) لا تُعرض هنا
        if ($this->isBankAccount($account)) {
            return redirect()->route('store_treasury.edit_bank', $account->id);
        }

        $treasury = $account;
        $treasuryRow = Treasury::where('account_id', $account->id)->first();
        $branches = DataScope::branchOptions();

        return view('onlinestore::treasury.edit', compact('treasury', 'treasuryRow', 'branches'));
    }

    /**
     * تحديث الخزينة
     *
     * كان يكتب بلا تحقّقٍ (اسمٌ فارغ ⇒ خطأ قاعدة بيانات)، ويتجاهل الحالة
     * والوصف المرسَلَين من النموذج، ولا يمسّ صفّ `treasuries` — فيفترق اسم
     * الخزينة عن اسم حسابها.
     */
    public function update(Request $request, $id)
    {
        $account = $this->findStoreTreasury($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|in:0,1',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ], [
            'name.required' => 'اسم الخزينة مطلوب',
        ], $this->attributeNames());

        $oldName = $account->name;
        $isActive = $request->filled('is_active') ? (int) $request->is_active : (int) $account->is_active;

        if ($isActive === 0 && ($reason = $this->defaultTreasuryLockReason($account, 'تعطيل'))) {
            return back()->withInput()->withErrors(['error' => $reason]);
        }

        DB::transaction(function () use ($request, $account, $isActive, $oldName) {
            // حساباتٌ قديمة بلا صفٍّ في `treasuries`: يُنشأ ويُربط بدل ردّ التعديل
            $treasury = Treasury::firstOrNew(['account_id' => $account->id]);

            if (! $treasury->exists) {
                $treasury->type = 0;
                $treasury->deposit_permissions = 0;
                $treasury->withdraw_permissions = 0;
            }

            $treasury->name = $request->name;
            $treasury->status = $isActive;
            $treasury->description = $request->description ?: $treasury->description;
            $treasury->save();

            $account->name = $request->name;
            $account->is_active = $isActive;
            $account->branch_id = $this->resolveWriteBranchId($request->input('branch_id'), $account->branch_id);
            $account->save();

            OperationLog::record('updated', 'finance_log', $treasury, 'تم تعديل الخزينة من **'.$oldName.'** إلى **'.$account->name.'**');
        });

        return redirect()->route('store_treasury.index')->with('success', 'تم تحديث الخزينة بنجاح!');
    }

    /**
     * عرض صفحة تعديل حساب بنكي
     *
     * `edit()`/`update()` أعلاه يعملان على الخزينة النقدية، ولا حقل فيهما
     * لاسم البنك أو الآيبان أو العملة — تلك على `Treasury` المرتبطة به عبر
     * `account_id`. فحساب بنكي يحتاج شاشته الخاصة لتُقرأ بياناته وتُعدَّل.
     */
    public function editBank($id)
    {
        $account = $this->findStoreTreasury($id);
        $treasury = Treasury::where('account_id', $account->id)->firstOrFail();
        $banks = Banks::forCountry();
        $branches = DataScope::branchOptions();

        return view('onlinestore::treasury.edit_bank', compact('account', 'treasury', 'banks', 'branches'));
    }

    /**
     * تحديث حساب بنكي
     */
    public function updateBank(Request $request, $id)
    {
        $account = $this->findStoreTreasury($id);
        $treasury = Treasury::where('account_id', $account->id)->firstOrFail();

        $request->validate($this->bankRules($request), $this->bankMessages(), $this->attributeNames());

        $oldName = $treasury->name;
        $isActive = $request->filled('status') ? (int) $request->status : (int) $account->is_active;

        if ($isActive === 0 && ($reason = $this->defaultTreasuryLockReason($account, 'تعطيل'))) {
            return back()->withInput()->withErrors(['error' => $reason]);
        }

        // عملة حسابٍ عليه حركاتٌ لا تتبدّل: أرصدته وقيوده كُتبت بها
        if ($treasury->currency && $request->currency !== $treasury->currency
            && JournalEntryDetail::where('account_id', $account->id)->exists()) {
            return back()->withInput()->withErrors(['currency' => 'لا يمكن تغيير عملة حسابٍ بنكي عليه حركات مالية.']);
        }

        DB::transaction(function () use ($request, $account, $treasury, $isActive, $oldName) {
            $treasury->name = $request->name;
            $treasury->bank_name = $request->bank_name;
            $treasury->account_number = Banks::normalize($request->account_number);
            $treasury->currency = $request->currency;
            $treasury->status = $isActive;
            $treasury->description = $request->description ?: $treasury->description;
            $treasury->save();

            // اسم وحالة الحساب المحاسبي يتبعان الحساب البنكي، فلا يفترقان
            $account->name = $request->name;
            $account->is_active = $isActive;
            $account->branch_id = $this->resolveWriteBranchId($request->input('branch_id'), $account->branch_id);
            $account->save();

            OperationLog::record('updated', 'finance_log', $treasury, 'تم تعديل الحساب البنكي من **'.$oldName.'** إلى **'.$treasury->name.'**');
        });

        return redirect()->route('store_treasury.index')->with('success', 'تم تحديث الحساب البنكي بنجاح!');
    }

    /**
     * حذف الخزينة
     *
     * خزينةٌ عليها حركاتٌ أو رصيدٌ لا تُحذف — حذفها يُيتّم سطور قيودها.
     * وكذلك الخزينة المضبوطة افتراضيةً للمتجر: حذفها يُسقط الدفع في المتجر.
     */
    public function destroy($id)
    {
        $account = $this->findStoreTreasury($id);

        if ($reason = $this->defaultTreasuryLockReason($account, 'حذف')) {
            return redirect()->back()->with('error', $reason);
        }

        if (JournalEntryDetail::where('account_id', $account->id)->exists() || abs((float) $account->balance) > 0.0001) {
            return redirect()->back()->with('error', 'لا يمكن حذف «'.$account->name.'»: عليها حركات مالية. يمكنك تعطيلها بدل حذفها.');
        }

        try {
            DB::transaction(function () use ($account) {
                OperationLog::record('deleted', 'finance_log', $account, 'تم حذف خزينة **'.$account->name.'**');

                Treasury::where('account_id', $account->id)->delete();
                $account->delete();
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StoreTreasury', 'destroy');

            return redirect()->back()->with('error', 'تعذّر حذف «'.$account->name.'»: مرتبطة بمستندات أخرى. يمكنك تعطيلها بدل حذفها.');
        }

        return redirect()->route('store_treasury.index')->with('success', 'تم حذف الخزينة بنجاح!');
    }

    /**
     * تحديث حالة الخزينة
     */
    public function updateStatus($id)
    {
        $treasury = $this->findStoreTreasury($id);

        $newStatus = ! $treasury->is_active;

        if (! $newStatus && ($reason = $this->defaultTreasuryLockReason($treasury, 'تعطيل'))) {
            return redirect()->back()->with('error', $reason);
        }

        DB::transaction(function () use ($treasury, $newStatus) {
            $treasury->update(['is_active' => $newStatus ? 1 : 0]);
            // صفّ الخزينة يتبع حسابها — وهو ما تقرؤه قوائم الاختيار في بقيّة النظام
            Treasury::where('account_id', $treasury->id)->update(['status' => $newStatus ? 1 : 0]);
        });

        $statusMessage = $newStatus ? 'نشط' : 'غير نشط';

        OperationLog::record('updated', 'finance_log', $treasury, 'تم تغيير حالة الخزينة **'.$treasury->name.'** إلى '.$statusMessage);

        return redirect()->back()->with('success', 'تم تغيير حالة الخزينة إلى '.$statusMessage.' بنجاح!');
    }

    /**
     * عرض صفحة إنشاء تحويل
     */
    public function transferCreate()
    {
        // النشطة داخل النطاق وحدها — ومن المصدر ما يجوز السحب منه، وإلى
        // الوجهة ما يجوز الإيداع فيه: فلا يُعرض خيارٌ يُرفض عند الحفظ
        $treasuries = $this->storeTreasuryQuery()->where('is_active', 1)->orderBy('name')->get();

        $currencies = Treasury::whereIn('account_id', $treasuries->pluck('id'))->pluck('currency', 'account_id');
        $defaultCurrency = CurrencySettings::code();
        $treasuries->each(function (Account $account) use ($currencies, $defaultCurrency) {
            $account->setAttribute('currency_code', ($currencies[$account->id] ?? null) ?: $defaultCurrency);
        });

        $fromTreasuries = $treasuries->filter(fn (Account $a) => TreasuryAccessGuard::withdrawBlockReason($a->id) === null)->values();
        $toTreasuries = $treasuries->filter(fn (Account $a) => TreasuryAccessGuard::depositBlockReason($a->id) === null)->values();

        return view('onlinestore::treasury.transfer_create', compact('treasuries', 'fromTreasuries', 'toTreasuries', 'defaultCurrency'));
    }

    /**
     * حفظ التحويل
     */
    public function transferStore(Request $request)
    {
        $request->validate([
            'from_treasury_id' => 'required|integer|exists:accounts,id',
            'to_treasury_id' => 'required|integer|exists:accounts,id|different:from_treasury_id',
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'attachments' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:4096',
        ], [
            'from_treasury_id.required' => 'الخزينة المحوَّل منها مطلوبة',
            'to_treasury_id.required' => 'الخزينة المحوَّل إليها مطلوبة',
            'to_treasury_id.different' => 'يجب اختيار خزينة مختلفة عن المصدر',
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'transfer_date.required' => 'تاريخ التحويل مطلوب',
            'attachments.mimes' => 'الامتدادات المسموحة للمرفق: jpg, jpeg, png, pdf, doc, docx, zip',
            'attachments.max' => 'أقصى حجم للمرفق 4 ميجابايت',
        ], $this->attributeNames());

        // خزينتا متجرٍ داخل نطاق المستخدم — لا أيُّ حسابٍ بمعرّفه
        $fromTreasury = $this->storeTreasuryQuery()->find($request->from_treasury_id);
        $toTreasury = $this->storeTreasuryQuery()->find($request->to_treasury_id);

        if (! $fromTreasury || ! $toTreasury) {
            return back()->withInput()->withErrors(['error' => 'الخزينة المختارة غير تابعة للمتجر الإلكتروني أو خارج نطاق صلاحيتك.']);
        }

        if (! $fromTreasury->is_active || ! $toTreasury->is_active) {
            return back()->withInput()->withErrors(['error' => 'لا يمكن التحويل من خزينة معطَّلة أو إليها.']);
        }

        // التحويل حركتان: سحبٌ من المصدر وإيداعٌ في الوجهة — يُفحص شرطاهما
        if ($denied = TreasuryAccessGuard::withdrawBlockReason($fromTreasury->id)) {
            return back()->withInput()->withErrors(['from_treasury_id' => $denied]);
        }
        if ($denied = TreasuryAccessGuard::depositBlockReason($toTreasury->id)) {
            return back()->withInput()->withErrors(['to_treasury_id' => $denied]);
        }

        // التحويل بين عملتين مختلفتين يقتضي سعر صرف — ولا سعر هنا
        $fromCurrency = $this->treasuryCurrency($fromTreasury);
        if ($fromCurrency !== $this->treasuryCurrency($toTreasury)) {
            return back()->withInput()->withErrors(['to_treasury_id' => 'التحويل بين خزينتين بعملتين مختلفتين غير مدعوم.']);
        }

        if ((float) $fromTreasury->balance < (float) $request->amount) {
            return back()->withInput()->withErrors(['amount' => 'الرصيد غير كافٍ في «'.$fromTreasury->name.'». المتاح: '.CurrencySettings::number($fromTreasury->balance).' '.$fromCurrency]);
        }

        // الفترة المحاسبية — التحويل ينشئ قيداً بتاريخ التحويل
        if ($blocked = AccountingPeriodGuard::blockReason($request->transfer_date)) {
            return back()->withInput()->withErrors(['transfer_date' => $blocked]);
        }

        DB::beginTransaction();
        try {
            // حفظ المرفق
            $attachmentPath = null;
            if ($request->hasFile('attachments')) {
                $attachmentPath = $request->file('attachments')->store('journal_attachments', 'public');
            }

            // إنشاء القيد المحاسبي
            $description = 'تحويل من: '.$fromTreasury->name.' إلى: '.$toTreasury->name;
            if ($request->filled('notes')) {
                $description .= ' | ملاحظات: '.$request->notes;
            }

            $entryData = [
                // من «تسلسل أرقام المستندات» (قسم القيود) لا سلسلةً مركّبة من الوقت
                'reference_number' => next_document_number('entry'),
                'date' => $request->transfer_date,
                'description' => $description,
                'status' => 1,
                'is_posted' => 1,
                'currency' => $fromCurrency,
                'reference_type' => ReferenceType::TRANSFER,
                'linking_type' => LinkingType::SINGLE,
                'attachment' => $attachmentPath,
                'created_by_employee' => Auth::id(),
            ];

            // القيد يخصّ فرع الخزينة المحوَّل منها لا فرع من نفّذ التحويل
            if ($fromTreasury->branch_id) {
                $entryData['branch_id'] = $fromTreasury->branch_id;
            }

            $journalEntry = JournalEntry::create($entryData);

            // قيد دائن للخزينة المحوَّل منها
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $fromTreasury->id,
                'description' => 'تحويل من '.$fromTreasury->name,
                'debit' => 0,
                'credit' => $request->amount,
                'is_debit' => false,
            ]);

            // قيد مدين للخزينة المستقبِلة
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $toTreasury->id,
                'description' => 'تحويل إلى '.$toTreasury->name,
                'debit' => $request->amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            OperationLog::record(
                'transferred',
                'finance_log',
                $journalEntry,
                'تم تحويل **'.CurrencySettings::number($request->amount).' '.$fromCurrency.'** من خزينة **'.$fromTreasury->name.'** إلى خزينة **'.$toTreasury->name.'** بالقيد **'.$journalEntry->reference_number.'**'
            );

            DB::commit();

            return redirect()->route('store_treasury.index')->with('success', 'تم التحويل وإنشاء القيد المحاسبي رقم '.$journalEntry->reference_number.' بنجاح!');
        } catch (ValidationException $e) {
            // حارس الفترة في الموديل (BelongsToAccountingPeriod) — رسالته تُعرض كما هي
            DB::rollBack();

            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            // rollBack() قبل failure() — انظر التعليق في store() أعلاه
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.StoreTreasury', 'transferStore');

            return back()->withInput()->withErrors(['error' => 'فشل التحويل. '.$this->readableError($e)]);
        }
    }

    /**
     * الحصول على بيانات العمليات
     */
    public function getOperationsData($id)
    {
        $treasury = $this->findStoreTreasury($id);
        $transactions = $this->getTransactions($treasury->id);
        $allOperations = $this->processAllOperations($transactions, $treasury);
        $allOperations = $this->applyFilters($allOperations);
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
                    'from' => $operationsPaginator->firstItem(),
                    'to' => $operationsPaginator->lastItem(),
                ],
            ]);
        }

        return compact('operationsPaginator');
    }

    /**
     * الحصول على بيانات التحويلات
     */
    public function getTransfersData($id)
    {
        $id = $this->findStoreTreasury($id)->id;

        $transfers = JournalEntry::whereHas('details', function ($query) use ($id) {
            $query->where('account_id', $id);
        })
            ->with(['details.account'])
            ->where('reference_type', ReferenceType::TRANSFER)
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

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

        return response()->json(['transfers' => $formattedTransfers]);
    }

    // ==================== النطاق والفرع ====================

    private function cashParentId(): int
    {
        return (int) AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT);
    }

    private function bankParentId(): int
    {
        return (int) AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT);
    }

    private function isBankAccount(Account $account): bool
    {
        return (int) $account->parent_id === $this->bankParentId();
    }

    /**
     * خزائن وبنوك المتجر التي يراها المستخدم الحالي.
     *
     * ثلاثة قيود معاً: علامة المتجر (`is_store_treasury`)، وموضع الحساب في
     * الشجرة (ابنٌ للخزائن أو البنوك)، والفرع — العقدة المختارة في الشريط
     * وما تحتها (`HeaderBranchScope::nodeId()`: الرئيسي «الكل» لمن لا قيد
     * عليه وحده) مقصوصةً على نطاق بيانات المستخدم. والخزينة القديمة بلا
     * فرعٍ تبقى ظاهرة — كما في `BelongsToBranch::scopeForBranch()`.
     */
    private function storeTreasuryQuery()
    {
        $query = Account::query()
            ->onlineStore()
            ->whereIn('parent_id', [$this->cashParentId(), $this->bankParentId()]);

        $node = HeaderBranchScope::nodeId();

        foreach ([$node ? $this->branchSubtree((int) $node) : null, DataScope::branchIds()] as $ids) {
            if ($ids !== null) {
                $query->where(function ($q) use ($ids) {
                    $q->whereIn('branch_id', $ids)->orWhereNull('branch_id');
                });
            }
        }

        return $query;
    }

    /** خزينة متجرٍ داخل النطاق — وإلا 404، لا «أيّ حسابٍ بمعرّفه». */
    private function findStoreTreasury($id): Account
    {
        return $this->storeTreasuryQuery()->findOrFail((int) $id);
    }

    /** @return array<int, int> العقدة وكلُّ ما تحتها */
    private function branchSubtree(int $branchId): array
    {
        $branch = Branch::findCached($branchId);

        if (! $branch) {
            return [$branchId];
        }

        return $branch->descendantBranchIds()->push($branchId)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** الفرع المقترح في نموذج الإنشاء — الفرع النشط في الشريط */
    private function defaultBranchId(): ?int
    {
        return current_branch_id();
    }

    /**
     * الفرع الذي تُختم به الخزينة: ما اختاره المستخدم إن كان داخل نطاقه،
     * وإلا ما كانت عليه (تعديل)، وإلا الفرع النشط — ولا تُحفظ خزينة متجرٍ
     * جديدة بلا فرع.
     */
    private function resolveWriteBranchId($requested, $fallback = null): ?int
    {
        $branchId = Scopes::chosenBranchId(auth()->user(), $requested, $fallback ? (int) $fallback : null);

        return $branchId ?: (current_branch_id() ?: Branch::mainId());
    }

    private function treasuryCurrency(Account $account): string
    {
        return Treasury::where('account_id', $account->id)->value('currency') ?: CurrencySettings::code();
    }

    /** الخزينة الافتراضية للمتجر (إعدادات المتجر) لا تُحذف ولا تُعطَّل */
    private function defaultTreasuryLockReason(Account $account, string $verb): ?string
    {
        if ((int) OnlineStoreSettingsService::get('default_treasury_id') !== (int) $account->id) {
            return null;
        }

        return 'لا يمكن '.$verb.' «'.$account->name.'»: هي الخزينة الافتراضية للمتجر. غيّر الخزينة الافتراضية من إعدادات المتجر أولاً.';
    }

    // ==================== التحقّق ====================

    private function bankRules(Request $request): array
    {
        return [
            'name' => 'required|string|max:255',
            'bank_name' => ['required', 'string', Rule::in(array_keys(Banks::forCountry()))],
            'account_number' => ['required', 'string', new Iban(is_string($request->input('bank_name')) ? $request->input('bank_name') : null)],
            'currency' => ['required', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'status' => 'nullable|in:0,1',
            'description' => 'nullable|string|max:1000',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ];
    }

    private function bankMessages(): array
    {
        return [
            'name.required' => 'اسم الحساب مطلوب',
            'bank_name.required' => 'اسم البنك مطلوب',
            'bank_name.in' => 'اختر بنكاً من القائمة.',
            'account_number.required' => 'رقم الآيبان مطلوب',
            'currency.required' => 'العملة مطلوبة',
            'currency.exists' => 'العملة المختارة غير مفعَّلة في إعدادات النظام',
        ];
    }

    /** أسماء الحقول بالعربية — فلا تظهر مفاتيح إنجليزية في رسائل التحقّق */
    private function attributeNames(): array
    {
        return [
            'name' => 'الاسم',
            'description' => 'الوصف',
            'is_active' => 'الحالة',
            'status' => 'الحالة',
            'branch_id' => 'الفرع',
            'bank_name' => 'اسم البنك',
            'account_number' => 'رقم الآيبان',
            'currency' => 'العملة',
            'from_treasury_id' => 'الخزينة المحوَّل منها',
            'to_treasury_id' => 'الخزينة المحوَّل إليها',
            'amount' => 'المبلغ',
            'transfer_date' => 'تاريخ التحويل',
            'notes' => 'الملاحظات',
            'attachments' => 'المرفق',
        ];
    }

    /** رسالة تُعرض للمستخدم — بلا نصّ SQL ولا أسماء جداول */
    private function readableError(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return 'حدث خطأ في قاعدة البيانات — سُجّل في سجلّ الأخطاء. راجع مسؤول النظام.';
        }

        return $e->getMessage();
    }

    // ==================== الدوال المساعدة ====================

    /**
     * كود الحساب الجديد تحت حساب الخزائن أو البنوك — أوّل كودٍ شاغر يبدأ بكود
     * الأب (121 ⇒ 1211، 1212، …)، نفس قاعدة
     * `Account\Concerns\AccountingOperations::nextChildAccountCode()`.
     *
     * كان `max(code) + 1` على عمودٍ نصّي: المقارنة أبجدية فيخرج «9» أكبر من
     * «1211»، ثم يُبحث عن أوّل رقمٍ حرّ أياً كان موقعه من الشجرة.
     */
    private function generateNextCode($parentId): string
    {
        $prefix = (int) Account::where('id', $parentId)->value('code');

        if ($prefix <= 0) {
            throw new \RuntimeException('الحساب الأب للخزائن بلا كود صالح — راجع «الحسابات المحاسبية» في الإعدادات.');
        }

        for ($width = 1; $width <= 4; $width++) {
            $span = 10 ** $width;
            $base = $prefix * $span;

            $taken = Account::whereRaw('CAST(code AS UNSIGNED) BETWEEN ? AND ?', [$base + 1, $base + $span - 1])
                ->pluck('code')
                ->map(fn ($code) => (int) $code)
                ->flip();

            for ($i = 1; $i < $span; $i++) {
                if (! $taken->has($base + $i)) {
                    return (string) ($base + $i);
                }
            }
        }

        throw new \RuntimeException('تعذّر توليد كود للحساب الجديد — المدى ممتلئ.');
    }

    private function getTransactions($id)
    {
        return JournalEntryDetail::where('account_id', $id)
            ->with(['journalEntry' => function ($query) {
                $query->with('invoice', 'client');
            }])
            ->orderBy('created_at', 'asc')
            ->limit(1000)
            ->get();
    }

    private function processAllOperations($transactions, $treasury)
    {
        $allOperations = [];

        foreach ($transactions as $detail) {
            $entry = $detail->journalEntry;
            if (! $entry) {
                continue;
            }

            $deposit = (float) ($detail->debit ?? 0);
            $withdraw = (float) ($detail->credit ?? 0);
            $netAmount = $deposit - $withdraw;

            $description = trim($entry->description ?? 'قيد محاسبي');
            $operationType = $this->detectOperationType($description);
            $operationLabel = $this->operationLabelFromType($operationType, $description);

            $dateValue = $entry->date ?? $entry->created_at;
            $timestamp = strtotime($dateValue);

            $allOperations[] = [
                'operation' => $operationLabel,
                'description' => $description,
                'deposit' => $deposit > 0 ? $deposit : 0,
                'withdraw' => $withdraw > 0 ? $withdraw : 0,
                'balance_after' => 0,
                'date' => $dateValue,
                'type' => $operationType,
                'journal_entry_id' => $entry->id,
                'timestamp' => $timestamp,
                'operation_amount' => $netAmount,
                'created_at' => $entry->created_at ?? now(),
                'sort_key' => $timestamp.'_'.$entry->id,
            ];
        }

        // ترتيب تصاعدي
        usort($allOperations, function ($a, $b) {
            if ($a['timestamp'] != $b['timestamp']) {
                return $a['timestamp'] - $b['timestamp'];
            }

            return strcmp($a['sort_key'], $b['sort_key']);
        });

        $currentBalance = 0;
        foreach ($allOperations as &$operation) {
            $currentBalance += $operation['operation_amount'];
            $operation['balance_after'] = $currentBalance;
            $operation['balance_change'] = ($operation['operation_amount'] >= 0 ? '+' : '').number_format($operation['operation_amount'], 2);
        }
        unset($operation);

        // ترتيب تنازلي للعرض
        usort($allOperations, function ($a, $b) {
            if ($a['timestamp'] != $b['timestamp']) {
                return $b['timestamp'] - $a['timestamp'];
            }

            return strcmp($b['sort_key'], $a['sort_key']);
        });

        return $allOperations;
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
        $fromDate = request('from_date');
        $toDate = request('to_date');
        $operationType = request('operation_type');
        $amountFrom = request('amount_from');
        $amountTo = request('amount_to');

        if ($fromDate) {
            $operations = array_filter($operations, function ($op) use ($fromDate) {
                return strtotime($op['date']) >= strtotime($fromDate);
            });
        }

        if ($toDate) {
            $operations = array_filter($operations, function ($op) use ($toDate) {
                return strtotime($op['date']) <= strtotime($toDate);
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

    private function paginateOperations($allOperations)
    {
        $perPage = 50;
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
}
