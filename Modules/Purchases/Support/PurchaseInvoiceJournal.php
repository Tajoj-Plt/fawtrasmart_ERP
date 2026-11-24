<?php

namespace Modules\Purchases\Support;

use App\Models\Account;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseInvoice;
use App\Support\AccountingSettings;

/**
 * سطورُ قيد فاتورة الشراء — **الموضعُ الوحيد** الذي تُكتب فيه.
 *
 * ══════════════════ لماذا مكانٌ واحد ══════════════════
 *
 * لفاتورة الشراء ثلاثةُ مواليد: إنشاءٌ مباشر، وتعديلٌ لفاتورةٍ قائمة،
 * وتحويلٌ من استلام مشتريات. وكان لكلٍّ منها شيفرةٌ تبني القيدَ بنفسها،
 * فتباعدت الثلاثة:
 *
 *   • الإنشاء يكتب خمسة سطور (مشتريات، ضريبة، شحن، خصم، مورد).
 *   • التعديل كان يرقّع ثلاثةً منها ويترك الشحنَ والخصم — فيختلّ التوازن.
 *   • التحويل كان يكتب **ثلاثة سطور** بمعادلة `grand_total − total_tax`:
 *     تكلفةُ الشحن مدفونةٌ في ح/ المشتريات، والخصمُ بلا سطر، وضريبةُ
 *     الشحن غيرُ مفصولة.
 *
 * فالفاتورة الواحدة يختلف قيدُها باختلاف الباب الذي دخلت منه — وهو ما
 * طلب المستخدم رفعَه: «ضروري يكون نفس إنشاء الفاتورة بنفس القيود».
 *
 * الآن يستدعي الثلاثةُ `writeDetails()`، فلا سبيل إلى التباعد: المعادلة
 * موضعُها واحد.
 *
 * ══════════════════ الأرصدة ══════════════════
 *
 * لا يمسّ هذا الصنفُ عمودَ `accounts.balance`: يردّ ما تحرّك ليطبّقه
 * المستدعي، لأنّ المستدعين يختلفون في ذلك — `InvoicesPurchaseController`
 * يحرّك العمود بيده (والتعديلُ يطبّق الفرق عن السطور المحذوفة)، بينما
 * متحكّما المرتجع والإشعار يشتقّان الأرصدة من القيود.
 */
class PurchaseInvoiceJournal
{
    /**
     * حسابٌ واجب من «إعدادات الحسابات المحاسبية» — بالمفتاح وحده.
     *
     * لا ثابت برمجي احتياطياً: الإعدادات هي المصدر الوحيد لحسابات فواتير
     * الشراء، والمفتاح غير المربوط يُرَدّ برسالة تسمّيه لا بـ`null` يتحوّل
     * بعد أسطر إلى خطأ غامض في القيد.
     *
     * @throws \Exception
     */
    public static function requireAccount(string $key): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.'.$key),
            ]));
        }

        return $account;
    }

    /**
     * حسابُ ضريبة شحن المشتريات — وإلّا حسابُ ضريبة المشتريات نفسُه.
     *
     * الفصلُ **اختياريّ** عمداً، على قياس `shippingVatAccountId()` في
     * المبيعات: منشأةٌ لا تريد وعاءين للضريبة تترك المفتاح غير مربوط
     * فيرجع القيدُ إلى `vat_paid` كما كان، ولا تسقط فاتورةٌ واحدة بسبب
     * مفتاحٍ لم يطلبه أحد.
     *
     * @throws \Exception إن لم يكن `vat_paid` مربوطاً
     */
    public static function shippingVatAccount(): Account
    {
        return AccountingSettings::account('shipping_vat_paid')
            ?? self::requireAccount('vat_paid');
    }

    /**
     * أتُفصَل ضريبةُ الشحن في القيد فعلاً؟
     *
     * متى رجع الحسابان إلى حسابٍ واحد فلا معنى لسطرين متجاورين باسمٍ
     * واحد — وهو العطلُ نفسُه الذي دفع إلى الفصل في المبيعات. فيُجمعان
     * في سطرٍ واحد كما كانا.
     */
    public static function splitsShippingVat(float $shippingTax): bool
    {
        if (round($shippingTax, 2) <= 0) {
            return false;
        }

        $shippingVat = AccountingSettings::account('shipping_vat_paid');

        if (! $shippingVat) {
            return false;
        }

        return (int) $shippingVat->id !== (int) self::requireAccount('vat_paid')->id;
    }

    /**
     * صافي البضاعة وحدها: بلا ضريبة ولا شحن، وبردّ الخصم الذي حُسم منها.
     *
     * معادلةٌ واحدة لكلّ المسارات. وكان التحويلُ والتعديلُ يستعملان
     * `grand_total − total_tax` التي تبتلع الشحن ولا تردّ الخصم.
     */
    public static function itemsSubtotal(PurchaseInvoice $invoice): float
    {
        return round(
            (float) $invoice->grand_total
            - (float) ($invoice->total_tax ?? 0)
            - (float) ($invoice->shipping_cost ?? 0)
            + (float) ($invoice->total_discount ?? 0),
            2
        );
    }

    /**
     * يكتب سطورَ القيد الخمسة (أو الستّة عند فصل ضريبة الشحن).
     *
     * @param  string  $label  اسمُ المستند في بيان السطر — «فاتورة شراء» عادةً
     * @return array<int, float> معرّف الحساب => المبلغ المرحَّل إليه
     *
     * @throws \Exception إن كان مفتاحٌ واجبٌ غير مربوط
     */
    public static function writeDetails(
        int $journalEntryId,
        PurchaseInvoice $invoice,
        Account $supplierAccount,
        string $label = 'فاتورة شراء'
    ): array {
        $code = $invoice->code;
        $deltas = [];

        /* يجمع السطر ويردّ أثره على الرصيد بالاصطلاح القائم: المبلغ يُضاف
           بمقداره في اتجاه طبيعة الحساب، لا بإشارة مدين/دائن. */
        $line = function (int $accountId, string $description, float $debit, float $credit) use ($journalEntryId, &$deltas): void {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntryId,
                'account_id' => $accountId,
                'description' => $description,
                'debit' => $debit,
                'credit' => $credit,
                'is_debit' => $debit > 0,
            ]);

            $deltas[$accountId] = ($deltas[$accountId] ?? 0) + $debit + $credit;
        };

        $shippingCost = round((float) ($invoice->shipping_cost ?? 0), 2);
        $totalDiscount = round((float) ($invoice->total_discount ?? 0), 2);
        $totalTax = round((float) ($invoice->total_tax ?? 0), 2);
        $grandTotal = round((float) $invoice->grand_total, 2);
        $subtotal = self::itemsSubtotal($invoice);

        // ١ المشتريات (مدين)
        $line(self::requireAccount('purchases')->id, 'مشتريات - '.$label.' '.$code, $subtotal, 0);

        /* ٢ ضريبة المدخلات (مدين) — البنود والشحن في سطرين متمايزين.

           كان السطرُ واحداً بمجموع `total_tax`، فلا يُعرف كم منه ضريبةُ
           البضاعة وكم ضريبةُ التوصيل إلّا بجردٍ يدويّ عند الإقرار. */
        if ($totalTax > 0) {
            $shippingTax = round((float) ($invoice->shipping_tax ?? 0), 2);
            $splits = self::splitsShippingVat($shippingTax);
            $itemsTax = $splits ? round($totalTax - $shippingTax, 2) : $totalTax;

            if ($itemsTax > 0) {
                $line(self::requireAccount('vat_paid')->id, 'ضريبة القيمة المضافة - '.$label.' '.$code, $itemsTax, 0);
            }

            if ($splits) {
                $line(self::shippingVatAccount()->id, 'ضريبة شحن مشتريات - '.$label.' '.$code, $shippingTax, 0);
            }
        }

        // ٣ تكاليف الشحن (مدين)
        if ($shippingCost > 0) {
            $line(self::requireAccount('purchase_shipping')->id, 'تكاليف الشحن - '.$label.' '.$code, $shippingCost, 0);
        }

        // ٤ الخصم المكتسب (دائن) — حسابٌ غير «خصم مسموح به» في جانب البيع
        if ($totalDiscount > 0) {
            $line(self::requireAccount('purchase_discount')->id, 'خصم مكتسب - '.$label.' '.$code, 0, $totalDiscount);
        }

        // ٥ ذمم المورد (دائن)
        $line($supplierAccount->id, 'ذمم الموردين - '.$label.' '.$code, 0, $grandTotal);

        return $deltas;
    }

    /**
     * تطبيقُ ما ردّه الكاتبُ على أرصدة الحسابات.
     *
     * @param  array<int, float>  $deltas
     */
    public static function applyDeltas(array $deltas): void
    {
        foreach ($deltas as $accountId => $amount) {
            if (round((float) $amount, 2) == 0.0) {
                continue;
            }

            $account = Account::find($accountId);

            if (! $account) {
                continue;
            }

            $account->balance += $amount;
            $account->save();
        }
    }
}
