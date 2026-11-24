<?php

namespace Modules\Purchases\Http\Controllers\Concerns;

use App\Models\Account;
use App\Support\AccountingSettings;

/**
 * الحساباتُ الاختيارية في قيود المشتريات.
 *
 * نظيرُ `Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts`
 * على جانب الشراء، ومقصورٌ على ما **يجوز** ألّا يكون مربوطاً — فالحسابات
 * الواجبة تبقى على `settingsAccount()` في كلّ متحكّم، وهي التي ترمي
 * برسالةٍ تسمّي المفتاح غير المربوط.
 *
 * ولذلك لا `AccountingSettings::account($key, $fallback)` بثابتٍ هنا: مصدرُ
 * الحسابات هو «إعدادات الحسابات المحاسبية» وحدها.
 */
trait ResolvesPurchaseAccounts
{
    /**
     * حسابٌ واجب — يرميه المتحكّم برسالةٍ تسمّي المفتاح.
     *
     * @throws \Exception
     */
    abstract private function settingsAccount(string $key): Account;

    /**
     * حسابُ ضريبة شحن المشتريات — وإلّا حسابُ ضريبة المشتريات نفسُه.
     *
     * الفصلُ **اختياريّ** عمداً، على قياس `shippingVatAccountId()` في
     * المبيعات: منشأةٌ لا تريد وعاءين للضريبة تترك المفتاح غير مربوط
     * فيرجع القيدُ إلى `vat_paid` كما كان، ولا تسقط فاتورةٌ واحدة بسبب
     * مفتاحٍ لم يطلبه أحد.
     *
     * أمّا `vat_paid` فواجب: ضريبةٌ تُحسَب ولا تجد حساباً يجب أن تُوقف
     * الحفظ لا أن تُرحَّل إلى مكانٍ مُخمَّن.
     *
     * @throws \Exception إن لم يكن `vat_paid` مربوطاً وكان مطلوباً
     */
    private function shippingVatPaidAccount(): Account
    {
        return AccountingSettings::account('shipping_vat_paid')
            ?? $this->settingsAccount('vat_paid');
    }

    /**
     * أتُفصَل ضريبةُ الشحن في القيد فعلاً؟
     *
     * يُسأل قبل بناء السطرين: متى رجع الحسابان إلى حسابٍ واحد فلا معنى
     * لسطرين متجاورين باسمٍ واحد — وهو العطلُ نفسُه الذي دفع إلى الفصل في
     * المبيعات. فيُجمعان في سطرٍ واحد كما كانا.
     */
    private function splitsShippingVat(float $shippingTax): bool
    {
        if (round($shippingTax, 2) <= 0) {
            return false;
        }

        $shippingVat = AccountingSettings::account('shipping_vat_paid');

        if (! $shippingVat) {
            return false;
        }

        return (int) $shippingVat->id !== (int) $this->settingsAccount('vat_paid')->id;
    }
}
