<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;

/**
 * حسابات قيود المبيعات من شاشة «الحسابات المحاسبية» وحدها.
 *
 * **ما كان**
 *
 *     $salesAccount = Account::find(SystemAccountIds::SALES);   // 44
 *     $vatAccount   = Account::find(SystemAccountIds::VAT_PAYABLE);
 *
 * أربعة عشر موضعاً في `InvoicesController` تكتب رقم الحساب في الشيفرة.
 * فما يربطه المحاسب في شاشة «الحسابات المحاسبية» — وهي شاشةٌ تعرض ثمانين
 * مفتاحاً وتحفظها — لم يكن يسري على قيدٍ واحد من قيود المبيعات: يغيّر
 * حساب المبيعات في الشاشة، فتبقى الفاتورة تُرحَّل إلى الحساب ٤٤.
 *
 * **ما صار**
 *
 *   الإعداد المربوط والمفعَّل ← وإلّا فلا حساب.
 *
 * **لا احتياط برمجي هنا عمداً.** لا `$fallback` في أيّ دالّة، فلا يمكن
 * تمرير ثابتٍ من `SystemAccountIds` أصلاً. سبب المنع أنّ الاحتياط
 * الصامت يُبطل الشاشة من حيث لا يُرى: مفتاحٌ نُسي ربطه أو أُطفئ يواصل
 * الترحيل إلى الحساب المكتوب في الشيفرة، فيظنّ المحاسب أنّ ما ضبطه
 * ساري المفعول وهو لا يسري. والغياب هنا يُقال صراحةً برسالة تسمّي
 * الحساب وتدلّ على شاشته.
 *
 * الثوابت تبقى في `App\Constants\SystemAccountIds` لأنّ
 * `AccountingSettingsSeeder` يقرأ منها القيم الأوّلية عند أوّل تعبئة —
 * فالجدول يبدأ معبّأً بما كان النظام يعمل به. لكنّها لا تُقرأ بعد ذلك
 * من هنا.
 *
 * **متى `require`؟**
 *
 * القيد لا يقبل حساباً مفقوداً: `requireAccountingAccount()` ترمي برسالة
 * تسمّي الحساب، بدل `null` يتحوّل بعد أسطر إلى قيدٍ بطرفٍ واحد. أمّا
 * القراءات والتقارير فتستعمل `accountingAccountId()` وتتحمّل الغياب.
 *
 * @see \Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts النسخة الأمّ (تُبقي الاحتياط)
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
     * حسابُ **ضريبة الشحن** في قيد البيع.
     *
     * `shipping_vat_payable` إن ربطه المحاسب، وإلّا `vat_payable` نفسُه.
     *
     * ── لماذا فصلٌ اختياريّ ──
     *
     * قيدُ الفاتورة يفصل ضريبةَ البنود عن ضريبة الشحن في سطرين، وكان
     * السطران يُرحَّلان إلى الحساب نفسِه. فيقرأ المحاسبُ «ضريبة المبيعات»
     * مرّتين بمبلغين ولا يعرف أيَّهما التوصيل — وشاشةُ القيد تعرض **اسم
     * الحساب** لا بيانَ السطر، فتسميةُ البيان وحدها لا تُرى.
     *
     * والرجوعُ إلى `vat_payable` عند غياب الربط مقصود: منشأةٌ لا تريد
     * حساباً ثانياً للضريبة تبقى على ما كانت، ولا يُفرَض عليها حسابٌ
     * ولا يُوقَف حفظُ فاتورتها لمفتاحٍ لم تطلبه. ولذلك `accountingAccountId`
     * لا `requireAccountingAccountId` هنا.
     */
    protected function shippingVatAccountId(): int
    {
        return $this->accountingAccountId('shipping_vat_payable')
            ?? $this->requireAccountingAccountId('vat_payable');
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
