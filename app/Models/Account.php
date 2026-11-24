<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Enums\ReferenceType;
use App\Constants\SystemAccountIds;
use Carbon\Carbon;

class Account extends Model
{
    use HasFactory;


    protected $table = 'accounts';
    /* `type_accont` و`supplier_id` عمودان حقيقيّان كانا خارج القائمة،
       فيسقطان بصمتٍ من `Account::create()` — يُحفظ الحساب بلا علامته
       ولا مورّده، ثمّ لا يجده من يبحث عنه بهما فيُنشئ حساباً آخر في كلّ
       مرّة. (تُسنَد `type_accont` في شاشة الخزائن بالإسناد المباشر، وهو
       ما يتخطّى القائمة أصلاً — فلم يظهر العطب هناك.)
       ولا خطر في إضافتهما: لا موضع في النظام يمرّر طلباً خاماً إلى
       `Account::create()`. */
    protected $fillable = ['name', 'code', 'parent_id', 'type', 'account_type', 'balance_type', 'category', 'balance', 'is_active', 'is_system', 'is_store_treasury', 'branch_id', 'client_id', 'supplier_id', 'type_accont', 'opening_balance', 'opening_balance_type', 'storehouse_id'];

    // Define constants for balance types
    const DEBIT = 'debit';
    const CREDIT = 'credit';

    /*
        علامتا حسابَي المحفظة في العمود `type_accont`.

        **لماذا تلزم هذه العلامة نطاقاً خاصّاً؟** صار للعميل الواحد حسابان
        يحملان `client_id`: حسابُ ذممه وحسابُ محفظته، وكذلك المورد. فكلُّ
        استعلامٍ قديمٍ من شكل `Account::where('client_id', $id)->first()`
        صار قد يلتقط المحفظة بدل الذمّة، و`->sum('balance')` صار يجمع
        الاثنين فيخرج رصيدٌ لا يصف شيئاً — لا الذمّة ولا المحفظة.

        فـ`ledger()` هي «حساب الطرف» كما كان يفهمه ذلك الكود كلُّه،
        و`wallets()` هي المحفظة صراحةً لمن أرادها.
    */
    const WALLET_CLIENT = 'client_wallet';

    const WALLET_SUPPLIER = 'supplier_wallet';

    /** حساب ذمّة الطرف — لا محفظته */
    public function scopeLedger($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('type_accont')
                ->orWhereNotIn('type_accont', [self::WALLET_CLIENT, self::WALLET_SUPPLIER]);
        });
    }

    /** حسابات المحافظ وحدها */
    public function scopeWallets($query)
    {
        return $query->whereIn('type_accont', [self::WALLET_CLIENT, self::WALLET_SUPPLIER]);
    }

    // Define constants for account types
    const ASSETS = 'assets';
    const LIABILITIES = 'liabilities';
    const EQUITY = 'equity';
    const REVENUES = 'revenues';
    const EXPENSES = 'expenses';

    // Translation mappings for account types
    public static $accountTypeTranslations = [
        self::ASSETS => 'الأصول',
        self::LIABILITIES => 'الخصوم',
        self::EQUITY => 'حقوق الملكية',
        self::REVENUES => 'الإيرادات',
        self::EXPENSES => 'المصروفات'
    ];
    /**
     * Relationship to get the client associated with this account
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relationship to get the parent account
     */
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Relationship to get the branch associated with this account
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function journalEntries()
    {
        return $this->hasMany(JournalEntryDetail::class, 'account_id');
    }
    /**
     * Relationship to get the cost center associated with this account
     * This relationship accesses cost centers through journal entry details
     */
    public function costCenter()
    {
        // Since accounts don't directly have cost centers, we'll define this
        // as a relationship that can be used when joining with journal entries
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Relationship to get the supplier associated with this account
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relationship to get the storehouse associated with this account
     */
    public function storehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'storehouse_id');
    }

    /**
     * الموظف الذي يكون هذا الحساب هو حساب رواتبه المستحقة (شجرة الخصوم).
     * علاقة عكسية لـ Employee::salary_payable_account_id
     */
    public function salaryPayableEmployee()
    {
        return $this->hasOne(Employee::class, 'salary_payable_account_id');
    }

    /**
     * الموظف الذي يكون هذا الحساب هو حساب سلفه (شجرة الأصول).
     * علاقة عكسية لـ Employee::employee_advance_account_id
     */
    public function advanceEmployee()
    {
        return $this->hasOne(Employee::class, 'employee_advance_account_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'account_id');
    }
    // في ملف Account.php أضف هذه العلاقة
    public function journalEntryDetails()
    {
        return $this->hasMany(JournalEntryDetail::class, 'account_id');
    }
    /**
     * Scope a query to only include active accounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope for searchable accounts
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchable($query)
    {
        return $query->select('id', 'name', 'code', 'parent_id')
                    ->orderBy('name');
    }

    /**
     * Scope a query to only include system accounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * خزائن/حسابات بنكية تابعة للمتجر الإلكتروني فقط — تستعملها شاشات
     * المتجر بدل عرض كل خزائن النظام (بما فيها خزائن الموظفين الشخصية).
     */
    public function scopeOnlineStore($query)
    {
        return $query->where('is_store_treasury', true);
    }

    /**
     * Relationship to get children accounts
     */
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Relationship to get the opening balance journal entry
     */
    public function openingBalanceJournalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'reference_number', 'id')
                    ->where('is_opening_balance', true);
    }

    /**
     * تحديث رصيد الحساب بناءً على مجموع أرصدة الحسابات الفرعية
     * هذه الدالة تعمل بشكل تراكمي من الأسفل إلى الأعلى
     *
     * @param int|null $accountId معرف الحساب (إذا كان null سيتم تحديث جميع الحسابات)
     * @param bool $recursive هل نريد تحديث جميع الآباء بشكل تراكمي
     * @return bool
     */
    public static function updateParentBalances($accountId = null, $recursive = true)
    {
        DB::beginTransaction();

        try {
            if ($accountId) {
                // تحديث حساب معين وآبائه
                $account = self::find($accountId);
                if (!$account) {
                    return false;
                }

                // If this is a leaf account (no children), update its balance from journal entries
                if ($account->children()->count() == 0) {
                    $account->updateBalanceFromJournalEntries();
                } else {
                    // For parent accounts, update balance as sum of children
                    self::updateSingleAccountBalance($account);
                }

                // تحديث جميع الآباء بشكل تراكمي
                if ($recursive && $account->parent_id) {
                    self::updateAncestorsBalances($account->parent_id);
                }
            } else {
                // تحديث جميع الحسابات من الأسفل إلى الأعلى
                self::updateAllAccountsBalances();
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('خطأ في تحديث أرصدة الحسابات: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث رصيد حساب واحد بناءً على مجموع أرصدة أبنائه
     * Generated by Copilot
     *
     * @param Account $account
     * @return void
     */
    private static function updateSingleAccountBalance($account)
    {
        // ✅ القاعدة الصحيحة (محدَّثة):
        // كل حساب = (قيوده المباشرة) + (مجموع أرصدة أبنائه)
        // - الحساب الطرفي (بدون أبناء): فقط من قيوده
        // - الحساب الأب: قيوده المباشرة + أرصدة أبنائه
        $balance = self::computeFullBalance($account->id, $account->balance_type);
        $account->balance = $balance;
        $account->save();
    }

    /**
     * حساب الرصيد الكامل لأي حساب: قيوده المباشرة + مجموع أرصدة الأبناء.
     * للحسابات الطرفية، تكون مكوِّنة الأبناء = 0 فيرجع الرصيد من القيود فقط.
     *
     * @param int $accountId
     * @param string $balanceType (debit|credit) لتحديد اتجاه الرصيد
     * @return float
     */
    private static function computeFullBalance($accountId, $balanceType)
    {
        // 1) رصيد القيود المباشرة على الحساب نفسه
        $totalDebits  = JournalEntryDetail::where('account_id', $accountId)->sum('debit');
        $totalCredits = JournalEntryDetail::where('account_id', $accountId)->sum('credit');
        $ownBalance = ($balanceType === self::DEBIT)
            ? ($totalDebits - $totalCredits)
            : ($totalCredits - $totalDebits);

        // 2) مجموع أرصدة الأبناء (يرجع 0 إذا لا يوجد أبناء)
        $childrenBalance = self::calculateParentBalanceFromChildren($accountId);

        return (float) ($ownBalance + $childrenBalance);
    }

    /**
     * تحديث أرصدة جميع الحسابات الأب بشكل تراكمي
     *
     * @param int $parentId
     * @return void
     */
    private static function updateAncestorsBalances($parentId, array $seen = [])
    {
        // أبٌ تكرّر = حلقة في الشجرة (حسابٌ أبٌ لنفسه) — يقف بدل أن يدور بلا نهاية
        if (isset($seen[(int) $parentId])) {
            return;
        }
        $seen[(int) $parentId] = true;

        $parent = self::find($parentId);

        if (!$parent) {
            return;
        }

        // تحديث رصيد الأب الحالي
        self::updateSingleAccountBalance($parent);

        // إذا كان له أب آخر، نحدثه أيضاً
        if ($parent->parent_id) {
            self::updateAncestorsBalances($parent->parent_id, $seen);
        }
    }

    /**
     * تحديث جميع أرصدة الحسابات من الأسفل إلى الأعلى
     * الطريقة الأمثل: نبدأ من المستوى الأعمق ونصعد
     * Generated by Copilot
     *
     * @return void
     */
    private static function updateAllAccountsBalances()
    {
        // First, update all leaf accounts (accounts without children) from journal entries
        // Include both active and inactive accounts
        $leafAccounts = self::whereDoesntHave('children')->get();

        foreach ($leafAccounts as $account) {
            $account->updateBalanceFromJournalEntries();
        }

        // الحصول على أقصى عمق في الشجرة
        $maxDepth = self::getMaxDepth();

        // البدء من الأسفل (أعمق مستوى) والصعود
        for ($depth = $maxDepth; $depth >= 0; $depth--) {
            $accounts = self::getAccountsByDepth($depth);

            foreach ($accounts as $account) {
                // Skip leaf accounts as they were already processed
                if ($account->children()->count() == 0) {
                    continue;
                }

                // ✅ Parent balance = own direct entries + sum of children's balances
                $account->balance = self::computeFullBalance($account->id, $account->balance_type);
                $account->save();
            }
        }
    }

    /**
     * الحصول على أقصى عمق في شجرة الحسابات
     *
     * @return int
     */
    private static function getMaxDepth()
    {
        $maxDepth = 0;

        $rootAccounts = self::whereNull('parent_id')->get();

        foreach ($rootAccounts as $root) {
            $depth = self::calculateDepth($root->id);
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * حساب عمق الحساب في الشجرة
     *
     * @param int $accountId
     * @param int $currentDepth
     * @return int
     */
    private static function calculateDepth($accountId, $currentDepth = 0)
    {
        $children = self::where('parent_id', $accountId)->get();

        if ($children->isEmpty()) {
            return $currentDepth;
        }

        $maxChildDepth = $currentDepth;

        foreach ($children as $child) {
            $childDepth = self::calculateDepth($child->id, $currentDepth + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }

    /**
     * الحصول على الحسابات حسب العمق المحدد
     *
     * @param int $targetDepth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function getAccountsByDepth($targetDepth)
    {
        $accounts = collect();

        if ($targetDepth == 0) {
            return self::whereNull('parent_id')->get();
        }

        $rootAccounts = self::whereNull('parent_id')->get();

        foreach ($rootAccounts as $root) {
            $accounts = $accounts->merge(
                self::getAccountsAtDepth($root->id, 0, $targetDepth)
            );
        }

        return $accounts;
    }

    /**
     * الحصول على الحسابات عند عمق معين
     *
     * @param int $accountId
     * @param int $currentDepth
     * @param int $targetDepth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function getAccountsAtDepth($accountId, $currentDepth, $targetDepth)
    {
        if ($currentDepth == $targetDepth) {
            return collect([self::find($accountId)]);
        }

        $accounts = collect();
        $children = self::where('parent_id', $accountId)->get();

        foreach ($children as $child) {
            $accounts = $accounts->merge(
                self::getAccountsAtDepth($child->id, $currentDepth + 1, $targetDepth)
            );
        }

        return $accounts;
    }

    /**
     * تحديث أرصدة فرع معين
     *
     * @param int $branchId
     * @return bool
     */
    public static function updateBranchBalances($branchId)
    {
        DB::beginTransaction();

        try {
            // First, update all leaf accounts (accounts without children) in this branch from journal entries
            // Include both active and inactive accounts
            $leafAccounts = self::where('branch_id', $branchId)
                ->whereDoesntHave('children')
                ->get();

            foreach ($leafAccounts as $account) {
                $account->updateBalanceFromJournalEntries();
            }

            // تحديث الأرصدة من الأسفل إلى الأعلى
            $maxDepth = self::getMaxDepthForBranch($branchId);

            for ($depth = $maxDepth; $depth >= 0; $depth--) {
                $accounts = self::getAccountsByDepthForBranch($branchId, $depth);

                foreach ($accounts as $account) {
                    // Skip leaf accounts as they were already processed
                    if ($account->children()->count() == 0) {
                        continue;
                    }

                    // ✅ Parent balance = own entries + children sum
                    $account->balance = self::computeFullBalance($account->id, $account->balance_type);
                    $account->save();
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('خطأ في تحديث أرصدة فرع ' . $branchId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على أقصى عمق لفرع معين
     *
     * @param int $branchId
     * @return int
     */
    private static function getMaxDepthForBranch($branchId)
    {
        $maxDepth = 0;

        $rootAccounts = self::whereNull('parent_id')
            ->where('branch_id', $branchId)
            ->get();

        foreach ($rootAccounts as $root) {
            $depth = self::calculateDepthForBranch($root->id, $branchId);
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * حساب العمق لفرع معين
     *
     * @param int $accountId
     * @param int $branchId
     * @param int $currentDepth
     * @return int
     */
    private static function calculateDepthForBranch($accountId, $branchId, $currentDepth = 0)
    {
        $children = self::where('parent_id', $accountId)
            ->where('branch_id', $branchId)
            ->get();

        if ($children->isEmpty()) {
            return $currentDepth;
        }

        $maxChildDepth = $currentDepth;

        foreach ($children as $child) {
            $childDepth = self::calculateDepthForBranch($child->id, $branchId, $currentDepth + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }

    /**
     * الحصول على حسابات فرع معين حسب العمق
     *
     * @param int $branchId
     * @param int $targetDepth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function getAccountsByDepthForBranch($branchId, $targetDepth)
    {
        $accounts = collect();

        if ($targetDepth == 0) {
            return self::whereNull('parent_id')
                ->where('branch_id', $branchId)
                ->get();
        }

        $rootAccounts = self::whereNull('parent_id')
            ->where('branch_id', $branchId)
            ->get();

        foreach ($rootAccounts as $root) {
            $accounts = $accounts->merge(
                self::getAccountsAtDepthForBranch($root->id, $branchId, 0, $targetDepth)
            );
        }

        return $accounts;
    }

    /**
     * الحصول على الحسابات عند عمق معين لفرع معين
     *
     * @param int $accountId
     * @param int $branchId
     * @param int $currentDepth
     * @param int $targetDepth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function getAccountsAtDepthForBranch($accountId, $branchId, $currentDepth, $targetDepth)
    {
        if ($currentDepth == $targetDepth) {
            return collect([self::find($accountId)]);
        }

        $accounts = collect();
        $children = self::where('parent_id', $accountId)
            ->where('branch_id', $branchId)
            ->get();

        foreach ($children as $child) {
            $accounts = $accounts->merge(
                self::getAccountsAtDepthForBranch($child->id, $branchId, $currentDepth + 1, $targetDepth)
            );
        }

        return $accounts;
    }

    /**
     * Update account balance based on opening balance
     */
    public function updateBalanceFromOpeningBalance()
    {
        if ($this->opening_balance > 0) {
            $this->balance = $this->opening_balance;
            $this->save();
        }
    }

    /**
     * Create or update opening balance journal entry
     */
/**
 * Create or update opening balance journal entry
 */
/**
 * @param  int|null  $counterpartyAccountId  الحساب المقابل — افتراضياً حساب
 *                                            الأرصدة الافتتاحية العام. مُرِّر
 *                                            صراحةً من شاشات التسوية (كمحفظة
 *                                            العملاء) كي لا تختلط قيودها
 *                                            بالقيود الافتتاحية الحقيقية
 * @param  string|null  $description  وصف القيد — افتراضياً "رصيد افتتاحي"
 */
public function createOrUpdateOpeningBalanceJournalEntry(?int $counterpartyAccountId = null, ?string $description = null)
{
    // Delete any existing opening balance journal entries for this account
    $this->deleteExistingOpeningBalanceEntries();

    // If no opening balance, return
    if ($this->opening_balance == 0) {
        return;
    }

    $counterpartyAccountId = $counterpartyAccountId ?? SystemAccountIds::OPENING_BALANCE;
    $description = $description ?? 'قيد رصيد افتتاحي لحساب: ' . $this->name;

    // Create the journal entry
    $journalEntry = new JournalEntry();
    $journalEntry->reference_number = 'OB-' . $this->id; // OB for Opening Balance
    $journalEntry->date = now();
    $journalEntry->description = $description;
    $journalEntry->status = 1; // Approved
    $journalEntry->reference_type = \App\Enums\ReferenceType::OPENING_BALANCE; // Set the reference type to Opening Balance
    $journalEntry->created_by_employee = auth()->id() ?? 1;
    $journalEntry->approved_by_employee = auth()->id() ?? 1;

    // Save the journal entry
    $journalEntry->save();

    // Create the opening balance journal entry with proper double-entry accounting
    // Using SystemAccountIds::OPENING_BALANCE (or the passed-in counterparty) for counterpart entries

    // First, create the entry for this account
    $accountEntry = new JournalEntryDetail();
    $accountEntry->journal_entry_id = $journalEntry->id;
    $accountEntry->account_id = $this->id;
    $accountEntry->description = 'رصيد افتتاحي لحساب: ' . $this->name;

    // Second, create the counterparty entry
    $counterpartyEntry = new JournalEntryDetail();
    $counterpartyEntry->journal_entry_id = $journalEntry->id;
    $counterpartyEntry->account_id = $counterpartyAccountId;
    $counterpartyEntry->description = 'نظير الرصيد الافتتاحي لـ ' . $this->name;

    // ✅ تطبيق القاعدة الصحيحة:
    // إذا كان نوع رصيد الحساب موجود، نستخدمه
    // إذا لم يكن موجود أو فارغ، نعتمد على طبيعة الحساب (balance_type)

    $effectiveBalanceType = $this->opening_balance_type ?? $this->balance_type;

    // Apply the double-entry principle: debits equal credits
    if ($effectiveBalanceType === self::DEBIT) {
        // ✅ إذا كان الحساب مدين (أو رصيده مدين):
        // الحساب نفسه يُسجل في الجانب المدين
        $accountEntry->debit = $this->opening_balance;
        $accountEntry->credit = 0;

        // حساب الأرصدة الافتتاحية (المقابل) يُسجل في الجانب الدائن
        $counterpartyEntry->debit = 0;
        $counterpartyEntry->credit = $this->opening_balance;
    } else {
        // ✅ إذا كان الحساب دائن (أو رصيده دائن):
        // حساب الأرصدة الافتتاحية (المقابل) يُسجل في الجانب المدين
        $counterpartyEntry->debit = $this->opening_balance;
        $counterpartyEntry->credit = 0;

        // الحساب نفسه يُسجل في الجانب الدائن
        $accountEntry->debit = 0;
        $accountEntry->credit = $this->opening_balance;
    }

    // Save both entries
    $accountEntry->save();
    $counterpartyEntry->save();

    // Verify that the journal entry is balanced
    $totalDebit = $journalEntry->details()->sum('debit');
    $totalCredit = $journalEntry->details()->sum('credit');

    if ($totalDebit != $totalCredit) {
        // Log error for unbalanced entry
        \Log::error("Unbalanced journal entry for opening balance: Account={$this->name}, Debit=$totalDebit, Credit=$totalCredit");
    }
}
    /**
     * Get the proper counterparty account ID for opening balances
     * Using the supplier account for supplier accounts, otherwise the opening balance account
     */
    private function getProperCounterpartyAccountId()
    {
        // If this is a supplier account, use the supplier account as counterparty
        if ($this->supplier_id) {
            return SystemAccountIds::SUPPLIERS;
        }

        // For other accounts, use the generic opening balance account
        return SystemAccountIds::OPENING_BALANCE;
    }

    /**
     * Delete all opening balance journal entries for this account
     *
     * `$entry->details()->delete()` هو استعلام حذفٍ مباشر (query builder)،
     * فلا يستحصل نماذج ولا يُطلق حدث `JournalEntryDetail::deleted` —
     * الحدث الذي يعيد حساب رصيد الحساب وأسلافه في الحالة العادية. فكان
     * رصيد الحساب (وطرفه المقابل) يبقى كما كان قبل الحذف حتى يُعاد حسابه
     * من مصدرٍ آخر، بينما `opening_balance` صار صفراً فعلاً
     */
    public function deleteExistingOpeningBalanceEntries()
    {
        // Find all opening balance journal entries for this account
        // البحث عن القيود الافتتاحية بناءً على reference_type والرقم المرجعي
        $openingEntries = JournalEntry::where('reference_number', 'OB-' . $this->id)
                                    ->where('reference_type', \App\Enums\ReferenceType::OPENING_BALANCE)
                                    ->get();

        if ($openingEntries->isEmpty()) {
            return;
        }

        // الحسابان المتأثران بالحذف: الحساب نفسه وطرفه المقابل — يُجمعان
        // قبل الحذف إذ لا وجود لتفاصيل القيد بعده
        $affectedAccountIds = JournalEntryDetail::whereIn('journal_entry_id', $openingEntries->pluck('id'))
            ->pluck('account_id')
            ->push($this->id)
            ->unique()
            ->filter();

        // Delete each entry and its details
        foreach ($openingEntries as $entry) {
            $entry->details()->delete();
            $entry->delete();
        }

        foreach ($affectedAccountIds as $accountId) {
            self::updateParentBalances($accountId, true);
        }
    }

    /**
     * Delete opening balance journal entry
     */
    public function deleteOpeningBalanceJournalEntry()
    {
        $this->deleteExistingOpeningBalanceEntries();
    }

    /**
     * البحث عن الحسابات المرتبطة بصنف مصروف معين
     * Generated by Copilot
     *
     * @param int $expenseCategoryId معرف صنف المصروف
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAccountsByExpenseCategory($expenseCategoryId)
    {
        $category = \App\Models\ExpensesCategory::find($expenseCategoryId);

        if (!$category) {
            return collect();
        }

        return self::where(function($query) use ($category) {
            $query->where('name', 'LIKE', '%' . $category->name . '%')
                  ->orWhere('name', $category->name);
        })
        ->where('is_active', 1)
        ->get();
    }

    /**
     * جلب جميع معرفات حسابات المصروفات بناءً على أصناف المصروفات
     * Generated by Copilot
     *
     * @return array
     */
    public static function getAllExpenseAccountIds()
    {
        $expenseAccountIds = [];

        // البحث عن الحسابات بناءً على أصناف المصروفات النشطة
        $expenseCategories = \App\Models\ExpensesCategory::where('status', 'active')->get();

        foreach ($expenseCategories as $category) {
            $matchingAccounts = self::where(function($query) use ($category) {
                $query->where('name', 'LIKE', '%' . $category->name . '%')
                      ->orWhere('name', $category->name);
            })
            ->where('is_active', 1)
            ->pluck('id')
            ->toArray();

            $expenseAccountIds = array_merge($expenseAccountIds, $matchingAccounts);
        }

        // إضافة جميع أحفاد حساب المصروفات الرئيسي
        $parentIds = [SystemAccountIds::EXPENSES];
        $expenseAccountIds[] = SystemAccountIds::EXPENSES;

        while (!empty($parentIds)) {
            $children = self::whereIn('parent_id', $parentIds)
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

    /**
     * Check if account can be safely deleted
     */
    public function canBeDeleted()
    {
        // Check if account is a system account
        if ($this->is_system) {
            return [
                'can_delete' => false,
                'reason' => 'هذا حساب نظام ولا يمكن حذفه.'
            ];
        }

        // Check if account has children accounts
        if ($this->children()->count() > 0) {
            return [
                'can_delete' => false,
                'reason' => 'الحساب يحتوي على حسابات فرعية. يرجى حذف الحسابات الفرعية أولاً.'
            ];
        }

        // Check if account has journal entries
        if ($this->hasMany(JournalEntryDetail::class, 'account_id')->count() > 0) {
            return [
                'can_delete' => false,
                'reason' => 'الحساب يحتوي على قيود محاسبية. يرجى حذف القيود أولاً.'
            ];
        }

        return [
            'can_delete' => true,
            'reason' => ''
        ];
    }

    /**
     * Check if account is a system account
     */
    public function isSystemAccount()
    {
        return $this->is_system;
    }

    /**
     * حساب رصيد الحساب الأب من مجموع أرصدة الأبناء مع مراعاة طبيعة الحسابات
     * Generated by Copilot
     *
     * يتم حساب الرصيد بناءً على طبيعة الحساب (مدين/دائن):
     * - الحسابات التي لها نفس طبيعة الأب: تُجمع أرصدتها
     * - الحسابات التي لها طبيعة عكسية للأب: تُطرح أرصدتها
     *
     * مثال: حساب صافي المبيعات (دائن بطبيعته)
     * - المبيعات (دائن): 10,000 → تُجمع
     * - مردودات المبيعات (مدين): 1,000 → تُطرح
     * - النتيجة: 10,000 - 1,000 = 9,000
     *
     * @param int $parent_id معرف الحساب الأب
     * @return float صافي رصيد الحساب الأب
     */
    private static function calculateParentBalanceFromChildren($parent_id)
    {
        // جلب الحساب الأب لمعرفة طبيعته
        $parent_account = self::find($parent_id);

        if (!$parent_account) {
            return 0;
        }

        // جلب جميع الحسابات الأبناء (النشطة وغير النشطة)
        $children = self::where('parent_id', $parent_id)->get();

        // إذا لم يوجد أبناء، نرجع صفر
        if ($children->isEmpty()) {
            return 0;
        }

        // طبيعة الحساب الأب (مدين أو دائن)
        $parent_balance_type = $parent_account->balance_type;

        // حساب صافي الرصيد مع مراعاة طبيعة كل حساب ابن
        $net_balance = 0;

        foreach ($children as $child) {
            // إذا كان الابن له نفس طبيعة الأب → نجمع رصيده
            // إذا كان الابن له طبيعة عكسية → نطرح رصيده
            if ($child->balance_type === $parent_balance_type) {
                // نفس الطبيعة: نجمع الرصيد
                $net_balance += $child->balance;
            } else {
                // طبيعة عكسية: نطرح الرصيد
                $net_balance -= $child->balance;
            }
        }

        return $net_balance;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        /*
            الأبُ لا يكون الحسابَ نفسه ولا أحدَ أحفاده — من أيّ مسار حفظ، لا من
            شاشة التعديل وحدها. حسابٌ أبٌ لنفسه يُدخل كلَّ تقريرٍ يصعد الشجرة
            في حلقةٍ تستنفد الذاكرة (هجرة `repair_account_parent_cycles`).
        */
        static::saving(function ($account) {
            if (! $account->parent_id || ! $account->isDirty('parent_id') || ! $account->exists) {
                return;
            }

            $ownId = (int) $account->id;
            $node = (int) $account->parent_id;
            $seen = [];

            while ($node && ! isset($seen[$node])) {
                if ($node === $ownId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'parent_id' => ['لا يمكن اختيار الحساب نفسه أو أحد حساباته الفرعية كحساب رئيسي له.'],
                    ]);
                }
                $seen[$node] = true;
                $node = (int) static::whereKey($node)->value('parent_id');
            }
        });

        // When creating an account, create opening balance journal entry if needed
        static::created(function ($account) {
            if ($account->opening_balance != 0) {
                $account->createOrUpdateOpeningBalanceJournalEntry();
            }
        });

        // When deleting an account, delete opening balance journal entry
        static::deleting(function ($account) {
            $account->deleteOpeningBalanceJournalEntry();
        });
    }

    /**
     * Update account balance according to our requirements:
     * - Leaf accounts (without children) get their balance from journal entries
     * - Parent accounts (with children) get their balance as sum of children's balances
     *
     * @param int|null $accountId The account ID to update (null for all accounts)
     * @return bool
     */
    public static function updateBalancesHierarchically($accountId = null)
    {
        DB::beginTransaction();

        try {
            if ($accountId) {
                // Update specific account and its hierarchy
                $account = self::find($accountId);
                if (!$account) {
                    return false;
                }

                // Update this account and all its descendants
                self::updateAccountAndDescendants($account);

                // Update all ancestors
                if ($account->parent_id) {
                    self::updateAncestorsBalances($account->parent_id);
                }
            } else {
                // Update all accounts hierarchically
                self::updateAllBalancesHierarchically();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating account balances hierarchically: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update account and all its descendants
     *
     * @param Account $account
     * @return void
     */
    private static function updateAccountAndDescendants($account)
    {
        // First, recursively update all children
        $children = $account->children;
        foreach ($children as $child) {
            self::updateAccountAndDescendants($child);
        }

        // Then update this account
        self::updateSingleAccountBalance($account);
    }

    /**
     * Update all accounts hierarchically from bottom to top
     * Generated by Copilot
     *
     * @return void
     */
    private static function updateAllBalancesHierarchically()
    {
        // First, update all leaf accounts (accounts without children) from journal entries
        // Include both active and inactive accounts
        $leafAccounts = self::whereDoesntHave('children')->get();

        foreach ($leafAccounts as $account) {
            $account->updateBalanceFromJournalEntries();
        }

        // Then update parent accounts from bottom to top
        $maxDepth = self::getMaxDepth();

        // Process from deepest level to root level
        for ($depth = $maxDepth; $depth >= 0; $depth--) {
            $accountsAtDepth = self::getAccountsByDepth($depth);

            foreach ($accountsAtDepth as $account) {
                // Skip leaf accounts as they were already processed
                if ($account->children()->count() == 0) {
                    continue;
                }

                // ✅ Parent balance = own entries + children sum
                $account->balance = self::computeFullBalance($account->id, $account->balance_type);
                $account->save();
            }
        }
    }

    /**
     * حساب وتحديث رصيد الحساب بناءً على القيود اليومية
     * Generated by Copilot
     *
     * للحسابات بدون أبناء: يتم حساب الرصيد من القيود اليومية
     * للحسابات التي لديها أبناء: الرصيد = مجموع أرصدة الأبناء
     *
     * @return float The updated balance
     */
    public function updateBalanceFromJournalEntries()
    {
        // ✅ القاعدة الصحيحة (محدَّثة):
        // رصيد أي حساب = قيوده المباشرة + مجموع أرصدة أبنائه (إن وُجدوا)
        // - الحساب الطرفي: من قيوده فقط
        // - الحساب الأب الذي له قيود مباشرة: قيوده + أبناؤه
        $balance = self::computeFullBalance($this->id, $this->balance_type);
        $this->balance = $balance;
        $this->save();
        return $balance;
    }

    /**
     * Recalculate and update balances for all accounts based on journal entries
     * This is a comprehensive function to ensure all account balances are accurate
     * according to the general ledger principle
     *
     * Implementation ensures:
     * 1. Accounts without children have balances calculated from their journal entries
     * 2. Accounts with children have balances as the sum of their children's balances
     *
     * @return bool
     */
    public static function updateAllBalancesFromJournalEntries()
    {
        DB::beginTransaction();

        try {
            // First, update balances for all accounts without children (leaf accounts)
            // These accounts get their balances directly from journal entries
            // Include both active and inactive accounts
            $leafAccounts = self::whereDoesntHave('children')->get();

            foreach ($leafAccounts as $account) {
                // Calculate the total debits and credits for this account
                $totalDebits = JournalEntryDetail::where('account_id', $account->id)->sum('debit');
                $totalCredits = JournalEntryDetail::where('account_id', $account->id)->sum('credit');

                // Calculate the net balance based on account type
                $netBalance = $account->calculateNetBalance($totalDebits, $totalCredits);

                // Update the account balance
                $account->balance = $netBalance;
                $account->save();
            }

            // Then, update parent account balances from bottom to top
            // Get all accounts with children, ordered by depth (deepest first)
            $maxDepth = self::getMaxDepth();

            // Process from deepest level to root level
            for ($depth = $maxDepth; $depth >= 0; $depth--) {
                $accountsAtDepth = self::getAccountsByDepth($depth);

                foreach ($accountsAtDepth as $account) {
                    // Skip leaf accounts as they were already processed
                    if ($account->children()->count() == 0) {
                        continue;
                    }

                    // ✅ Parent balance = own entries + children sum
                    $account->balance = self::computeFullBalance($account->id, $account->balance_type);
                    $account->save();
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating account balances from journal entries: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the current actual balance for this account based on all journal entries
     * This function calculates the balance without updating the stored value
     *
     * @return float The actual balance
     */
    public function getActualBalanceFromJournalEntries()
    {
        // Calculate the total debits and credits for this account from all journal entries
        $totalDebits = JournalEntryDetail::where('account_id', $this->id)->sum('debit');
        $totalCredits = JournalEntryDetail::where('account_id', $this->id)->sum('credit');

        // Calculate and return the net balance based on account type
        return $this->calculateNetBalance($totalDebits, $totalCredits);
    }

    /**
     * Calculate net balance based on account type
     * For debit-normal accounts (assets, expenses): balance = debits - credits
     * For credit-normal accounts (liabilities, equity, revenues): balance = credits - debits
     *
     * @param float $totalDebits
     * @param float $totalCredits
     * @return float
     */
    private function calculateNetBalance($totalDebits, $totalCredits)
    {
        if ($this->balance_type === self::DEBIT) {
            // Debit-normal accounts (assets, expenses)
            return $totalDebits - $totalCredits;
        } else {
            // Credit-normal accounts (liabilities, equity, revenues)
            return $totalCredits - $totalDebits;
        }
    }

    public function updateChildrenAccountTypes($accountType)
    {
        // Update all direct children
        $this->children()->update(['account_type' => $accountType]);

        // Recursively update grandchildren
        foreach ($this->children as $child) {
            $child->updateChildrenAccountTypes($accountType);
        }
    }
    /**
     * Get the translated name of the account type
     *
     * @return string
     */
    public function getAccountTypeTranslationAttribute()
    {
        return self::$accountTypeTranslations[$this->account_type] ?? $this->account_type ?? 'غير محدد';
    }

    /**
     * حساب أعمار الديون (Days Late) للحساب
     * يحسب عدد الأيام من أول قيد أو تاريخ الإنشاء
     *
     * @param \Carbon\Carbon|null $toDate التاريخ المرجعي (الافتراضي: اليوم)
     * @return int عدد الأيام المتأخرة
     */
    public function getDaysLate($toDate = null)
    {
        if (!$toDate) {
            $toDate = Carbon::now();
        } else {
            $toDate = Carbon::parse($toDate);
        }

        // إذا كان هناك قيود محاسبية، استخدم أقدم قيد
        if ($this->journalEntries && $this->journalEntries->count() > 0) {
            $oldestEntry = $this->journalEntries->first();
            $entryDate = Carbon::parse($oldestEntry->created_at)->startOfDay();
            return $entryDate->diffInDays($toDate);
        }

        // إذا لم يكن هناك قيود، استخدم تاريخ إنشاء الحساب
        if (!empty($this->created_at)) {
            $accountDate = Carbon::parse($this->created_at)->startOfDay();
            return $accountDate->diffInDays($toDate);
        }

        return 0;
    }

    /**
     * توزيع الرصيد على فترات الأعمار المختلفة
     * بناءً على عدد الأيام المتأخرة
     *
     * @param int|null $daysLate عدد الأيام المتأخرة (إذا كان null، سيتم حسابه)
     * @param \Carbon\Carbon|null $toDate التاريخ المرجعي
     * @return array توزيع الرصيد على الفترات
     */
    public function getAgingDistribution($daysLate = null, $toDate = null)
    {
        // حساب أيام التأخير إذا لم يتم تمريرها
        if ($daysLate === null) {
            $daysLate = $this->getDaysLate($toDate);
        }

        $balance = (float) $this->balance;

        // توزيع الرصيد على الفترات الزمنية حسب أيام التأخير
        if ($daysLate == 0) {
            return [
                'today' => $balance,
                'days1to30' => 0,
                'days31to60' => 0,
                'days61to90' => 0,
                'days91to120' => 0,
                'daysOver120' => 0,
            ];
        } elseif ($daysLate >= 1 && $daysLate <= 30) {
            return [
                'today' => 0,
                'days1to30' => $balance,
                'days31to60' => 0,
                'days61to90' => 0,
                'days91to120' => 0,
                'daysOver120' => 0,
            ];
        } elseif ($daysLate >= 31 && $daysLate <= 60) {
            return [
                'today' => 0,
                'days1to30' => 0,
                'days31to60' => $balance,
                'days61to90' => 0,
                'days91to120' => 0,
                'daysOver120' => 0,
            ];
        } elseif ($daysLate >= 61 && $daysLate <= 90) {
            return [
                'today' => 0,
                'days1to30' => 0,
                'days31to60' => 0,
                'days61to90' => $balance,
                'days91to120' => 0,
                'daysOver120' => 0,
            ];
        } elseif ($daysLate >= 91 && $daysLate <= 120) {
            return [
                'today' => 0,
                'days1to30' => 0,
                'days31to60' => 0,
                'days61to90' => 0,
                'days91to120' => $balance,
                'daysOver120' => 0,
            ];
        } else {
            return [
                'today' => 0,
                'days1to30' => 0,
                'days31to60' => 0,
                'days61to90' => 0,
                'days91to120' => 0,
                'daysOver120' => $balance,
            ];
        }
    }

    /**
     * جلب معلومات أعمار الديون الكاملة للحساب
     * تجمع معلومات الأيام والتوزيع
     *
     * @param \Carbon\Carbon|null $toDate التاريخ المرجعي
     * @return array معلومات أعمار الديون
     */
    public function getDebtAgingInfo($toDate = null)
    {
        $daysLate = $this->getDaysLate($toDate);
        $aging = $this->getAgingDistribution($daysLate, $toDate);

        return [
            'days_late' => $daysLate,
            'aging_distribution' => $aging,
            'today' => $aging['today'],
            'days1to30' => $aging['days1to30'],
            'days31to60' => $aging['days31to60'],
            'days61to90' => $aging['days61to90'],
            'days91to120' => $aging['days91to120'],
            'daysOver120' => $aging['daysOver120'],
        ];
    }

    /**
     * Get all IDs of descendant accounts (children, grandchildren, etc.)
     *
     * @return array
     */
    public function getDescendantIds()
    {
        $ids = [];
        $children = $this->children;
        
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        
        return $ids;
    }

    /**
     * Get this account ID and all its descendant IDs
     * 
     * @return array
     */
    public function getAccountAndDescendantIds()
    {
        return array_merge([$this->id], $this->getDescendantIds());
    }
}
