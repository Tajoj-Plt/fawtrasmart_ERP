<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;

/**
 * حسابات نقاط البيع من شاشة «الحسابات المحاسبية» بدل الثوابت والأرقام العارية.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * ثلاثةُ أنماطٍ متعايشة في متحكّمٍ واحد:
 *
 *   Account::find(SystemAccountIds::SALES)          // ثابتٌ برمجي
 *   Account::find(52)                                // رقمٌ عارٍ — «الخصم المسموح به»
 *   Account::where('id', 45)                         // رقمٌ عارٍ — «مردود المبيعات»
 *   Account::where('id', 50)                         // رقمٌ عارٍ — تكلفة المبيعات
 *   Account::where('name', 'الخزينة الرئيسية')      // **بحثٌ بالاسم العربي**
 *
 * والأخيرُ أسوأُها: تسميةُ الخزينة تُعدَّل من شاشة الحسابات — وهو تعديلٌ
 * مشروعٌ لا أحدَ يظنّه خطراً — فينكسر قيدُ المرتجع كلُّه بصمت، ويُرحَّل
 * المبلغُ إلى `null` أو لا يُرحَّل إطلاقاً.
 *
 * ومنشأةٌ تُنشئ شجرة حساباتها من الصفر لا تحمل حساباتها الأرقامَ 45 و50
 * و52 — فالثوابتُ تشير إلى حساباتٍ لا وجودَ لها، أو أسوأ: إلى حساباتٍ
 * أخرى تماماً تحمل تلك الأرقام صدفةً.
 *
 * ─────────────────────── ترتيب الحلّ ───────────────────────
 *
 *   الإعداد المربوط والمفعَّل ← وإلا الثابت البرمجي كاحتياط.
 *
 * الاحتياطُ مقصود: النظام يبقى عاملاً قبل أن يربط المحاسب مفتاحاً، ويسري
 * ربطُه فور حفظه بلا نشرٍ جديد. وهو نفس تعاقد `AccountingSettings::id()`
 * ونفس ما تعمل به وحدتا المخزون والمشتريات.
 *
 * ─────────────────────── متى `require`؟ ───────────────────────
 *
 * القيدُ المحاسبي لا يقبل حساباً مفقوداً. و`requireAccountingAccount()`
 * ترمي برسالةٍ تسمّي الحساب وتدلّ على شاشته — بدل `null` يتحوّل بعد أسطرٍ
 * إلى قيدٍ بطرفٍ واحد. وهذا يهمّ هنا بالذات: قيودُ نقاط البيع كانت تُنشأ
 * داخل `try/catch` يبتلع الخطأ ويُسجّله ثم **يمضي إلى `commit`** — فتُحفظ
 * الفاتورةُ ويُخصم المخزون ولا يُكتب قيد. الرميُ هنا هو ما يجعل المعاملة
 * تتراجع كما ينبغي.
 */
trait ResolvesAccountingAccounts
{
    /**
     * معرّف الحساب المضبوط لمفتاح — أو الاحتياط البرمجي.
     *
     * @param  string  $key  مفتاح الإعداد، انظر AccountingSettingsSeeder
     * @param  int|null  $fallback  ثابت `SystemAccountIds` المقابل
     */
    protected function accountingAccountId(string $key, ?int $fallback = null): ?int
    {
        return AccountingSettings::id($key, $fallback);
    }

    /** سجلّ الحساب — أو null إن لم يوجد لا في الإعدادات ولا في الاحتياط. */
    protected function accountingAccount(string $key, ?int $fallback = null): ?Account
    {
        return AccountingSettings::account($key, $fallback);
    }

    /**
     * الحساب أو استثناء — لمولّدات القيود التي لا يصحّ أن تكمل بلا حساب.
     *
     * @throws \Exception إن لم يُربط المفتاح ولم يوجد حساب الاحتياط
     */
    protected function requireAccountingAccount(string $key, ?int $fallback = null): Account
    {
        $account = $this->accountingAccount($key, $fallback);

        if (! $account) {
            throw new \Exception(__('settings.accounting_settings.account_not_mapped', [
                'account' => $this->accountingAccountLabel($key),
            ]));
        }

        return $account;
    }

    /** معرّف الحساب أو استثناء — بنفس ضمانة `requireAccountingAccount()`. */
    protected function requireAccountingAccountId(string $key, ?int $fallback = null): int
    {
        return (int) $this->requireAccountingAccount($key, $fallback)->id;
    }

    /**
     * اسم الحساب كما يعرفه المستخدم في الشاشة — لرسائل الخطأ.
     *
     * يُقرأ من صفّ الإعداد نفسه، فلا يحتاج كل مفتاحٍ جديد مفتاحَ ترجمة يقابله.
     */
    protected function accountingAccountLabel(string $key): string
    {
        try {
            return AccountingSetting::where('key', $key)->value('name') ?: $key;
        } catch (\Throwable $e) {
            return $key;
        }
    }

    /**
     * حسابُ نقاط البيع — إعدادُها أوّلاً، ثم المشترك، ثم الثابت.
     *
     * ═══════════════ لماذا طبقةٌ ثالثة ═══════════════
     *
     * «الحسابات المحاسبية» تخدم المنشأةَ كلَّها: المبيعاتُ فيها هي مبيعاتُ
     * الجملة والتجزئة ونقاط البيع معاً. ومنشأةٌ تريد قراءةَ أداء نقاط البيع
     * من ميزان المراجعة مباشرةً تحتاج حساباً خاصّاً بها — وإلّا لزمها تقريرٌ
     * لكل سؤال.
     *
     * فصار الترتيب:
     *
     *   إعدادُ الفرع  ←  إعدادُ نقاط البيع العام  ←  المشترك  ←  الثابت
     *
     * والسقوطُ مقصود: من لم يضبط شيئاً يبقى نظامُه عاملاً كما كان، ومن ضبط
     * يسري ضبطُه فوراً بلا نشر.
     *
     * @param  string  $column  عمودُه في `pos_general_settings`
     * @param  string  $sharedKey  مفتاحُه في `accounting_settings`
     * @param  int|null  $fallback  ثابتُ `SystemAccountIds` المقابل
     */
    protected function posAccount(string $column, string $sharedKey, ?int $fallback = null): ?Account
    {
        $id = $this->posSettings()?->{$column};

        if ($id && ($account = Account::find($id))) {
            return $account;
        }

        return $this->accountingAccount($sharedKey, $fallback);
    }

    /** بنفس الترتيب، ويرمي إن لم يوجد شيء — لمولّدات القيود. */
    protected function requirePosAccount(string $column, string $sharedKey, ?int $fallback = null): Account
    {
        $account = $this->posAccount($column, $sharedKey, $fallback);

        if (! $account) {
            throw new \Exception(__('settings.accounting_settings.account_not_mapped', [
                'account' => $this->accountingAccountLabel($sharedKey),
            ]));
        }

        return $account;
    }

    /* ═══════════════ حسابات نقاط البيع بأسمائها ═══════════════ */

    /** إيراد المبيعات. */
    protected function salesAccount(): Account
    {
        return $this->requirePosAccount('sales_account_id', 'sales', SystemAccountIds::SALES);
    }

    /** مردودات المبيعات — الطرف المدين في قيد المرتجع. */
    protected function salesReturnsAccount(): Account
    {
        return $this->requirePosAccount('sales_returns_account_id', 'sales_returns', SystemAccountIds::SALES_RETURNS);
    }

    /** تكلفة المبيعات. */
    protected function costOfSalesAccount(): Account
    {
        return $this->requirePosAccount('cost_of_sales_account_id', 'cost_of_sales', SystemAccountIds::COST_OF_SALES);
    }

    /** الخصم المسموح به — كان `Account::find(52)`. */
    protected function allowedDiscountAccount(): ?Account
    {
        return $this->posAccount('discount_account_id', 'allowed_discount', SystemAccountIds::ALLOWED_DISCOUNT);
    }

    /** ضريبة القيمة المضافة المستحقّة. */
    protected function vatPayableAccount(): ?Account
    {
        return $this->posAccount('vat_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE);
    }

    /**
     * خزينة الكاشير — خزينةُ الموظف إن رُبطت، وإلا الخزينة الافتراضية.
     *
     * **ولا عمودَ لها في إعدادات نقاط البيع عمداً**: الخزينةُ عهدةُ الموظّف
     * لا إعدادُ وحدة. لكل كاشيرٍ خزينتُه في `treasury_employees`، وقبضُه
     * يدخلها هي — وثابتٌ واحدٌ يجمع نقدَ الجميع في عهدةٍ لا صاحبَ لها فلا
     * يُعرف على مَن الفرق. والمشتركُ `main_treasury` هو ما يُرجَع إليه حين
     * لا تُربط للموظّف خزينة.
     *
     * كان البحثُ `Account::where('name', 'الخزينة الرئيسية')`. صار المفتاحَ
     * المضبوط في الشاشة، والاسمُ لا يدخل في الحساب.
     */
    protected function treasuryAccountFor(?int $employeeId): Account
    {
        if ($employeeId) {
            $treasuryId = \App\Models\TreasuryEmployee::where('employee_id', $employeeId)->value('treasury_id');

            if ($treasuryId) {
                $account = Account::find($treasuryId);

                if ($account) {
                    return $account;
                }
            }
        }

        return $this->requireAccountingAccount('main_treasury', SystemAccountIds::MAIN_TREASURY);
    }

    /**
     * حساب مخزون مستودعٍ بعينه — من عمود `storehouse_id` على الحساب.
     *
     * يُقرأ ولا يُنشأ هنا: إنشاءُ حسابات المستودعات مسؤوليةُ وحدة المخزون
     * (`Modules\Stock\...\ResolvesAccountingAccounts::warehouseAccount()`)،
     * ونقطةُ البيع تستهلكها ولا تولّدها — وإلّا صار للحساب الواحد مولّدان
     * بقاعدتين، وهو أصلُ الحسابات المعلَّقة في جذر الشجرة.
     */
    protected function inventoryAccountIdFor(?int $storeHouseId): ?int
    {
        if ($storeHouseId) {
            $id = Account::where('storehouse_id', $storeHouseId)->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        $mainStoreHouseId = \App\Models\StoreHouse::where('major', 1)->value('id');

        if ($mainStoreHouseId) {
            $id = Account::where('storehouse_id', $mainStoreHouseId)->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return $this->accountingAccountId('inventory', SystemAccountIds::INVENTORY);
    }

    /**
     * حسابا فرق الدرج — الفائض دائنٌ والعجز مدين.
     *
     * مفتاحان بلا احتياطٍ برمجي عمداً: لا حسابَ في الشجرة يقابلهما طبيعياً،
     * والقرارُ بأيّ حسابٍ يذهبان للمحاسب وحده. فإن لم يُربطا لم يُنشأ قيدُ
     * الفرق — ويُسجَّل ذلك في اللوغ بدل أن يُرحَّل الفرق إلى حسابٍ مخمَّن.
     */
    protected function cashOverAccount(): ?Account
    {
        return $this->posAccount('cash_over_account_id', 'pos_cash_over');
    }

    protected function cashShortAccount(): ?Account
    {
        return $this->posAccount('cash_short_account_id', 'pos_cash_short');
    }
}
