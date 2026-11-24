<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;

/**
 * حسابات قيود وحدة العملاء من شاشة «الحسابات المحاسبية» وحدها.
 *
 * **ما كان**
 *
 *     $treasuryAccount = Account::findOrFail(SystemAccountIds::MAIN_TREASURY);
 *     $doubtful = Account::findOrFail(SystemAccountIds::ALLOWANCE_FOR_DOUBTFUL_DEBTS);
 *
 * `DoubtfulDebtController` وحده يكتب رقم الحساب في الشيفرة في ستّة مواضع:
 * الخزينة، وحساب العميل، ومخصّص الديون المشكوك فيها، والديون المعدومة.
 * فما يربطه المحاسب في شاشة «الحسابات المحاسبية» — وفيها `main_treasury`
 * و`clients_account` و`allowance_for_doubtful_debts` و`bad_debts` مفاتيحُ
 * معرَّفةٌ تُحفَظ — لم يكن يسري على قيدٍ واحد من قيود العملاء.
 *
 * **ما صار**
 *
 *   الإعداد المربوط والمفعَّل ← وإلّا فلا حساب.
 *
 * **لا احتياط برمجي هنا عمداً.** لا `$fallback` في أيّ دالّة، فلا يمكن
 * تمرير ثابتٍ من `SystemAccountIds` أصلاً. سببُ المنع أنّ الاحتياط الصامت
 * يُبطل الشاشة من حيث لا يُرى: مفتاحٌ نُسي ربطه أو أُطفئ يواصل الترحيل إلى
 * الحساب المكتوب في الشيفرة، فيظنّ المحاسب أنّ ما ضبطه ساري المفعول وهو
 * لا يسري. والغياب هنا يُقال صراحةً برسالة تسمّي الحساب وتدلّ على شاشته.
 *
 * **متى `require`؟** القيد لا يقبل حساباً مفقوداً: `requireAccountingAccount()`
 * ترمي برسالة تسمّي الحساب، بدل `null` يتحوّل بعد أسطر إلى قيدٍ بطرفٍ
 * واحد. أمّا القراءات والتقارير فتستعمل `accountingAccountId()`.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts النسخة الأمّ
 * @see \Database\Seeders\AccountingSettingsSeeder التعبئة الأولى للمفاتيح
 */
trait ResolvesAccountingAccounts
{
    /**
     * معرّف الحساب المضبوط لمفتاح — أو `null` إن لم يُربط.
     *
     * @param  string  $key  مفتاح الإعداد، انظر AccountingSettingsSeeder::definitions()
     */
    protected function accountingAccountId(string $key): ?int
    {
        return AccountingSettings::id($key);
    }

    /**
     * سجلّ الحساب — أو `null` إن لم يُربط المفتاح أو حُذف حسابه.
     */
    protected function accountingAccount(string $key): ?Account
    {
        return AccountingSettings::account($key);
    }

    /**
     * الحساب أو استثناء — لمولّدات القيود التي لا يصحّ أن تكمل بلا حساب.
     *
     * @throws \Exception إن لم يُربط المفتاح في شاشة «الحسابات المحاسبية»
     */
    protected function requireAccountingAccount(string $key): Account
    {
        $account = $this->accountingAccount($key);

        if (! $account) {
            throw new \Exception(__('settings.accounting_settings.account_not_mapped', [
                'account' => $this->accountingAccountLabel($key),
            ]));
        }

        return $account;
    }

    /**
     * معرّف الحساب أو استثناء — بنفس ضمانة `requireAccountingAccount()`.
     */
    protected function requireAccountingAccountId(string $key): int
    {
        return (int) $this->requireAccountingAccount($key)->id;
    }

    /**
     * اسم الحساب كما يعرفه المستخدم في الشاشة — لرسائل الخطأ.
     *
     * يُقرأ من صفّ الإعداد نفسه، فلا يحتاج كل مفتاح جديد مفتاحَ ترجمة يقابله.
     */
    protected function accountingAccountLabel(string $key): string
    {
        try {
            return AccountingSetting::where('key', $key)->value('name') ?: $key;
        } catch (\Throwable $e) {
            return $key;
        }
    }
}
