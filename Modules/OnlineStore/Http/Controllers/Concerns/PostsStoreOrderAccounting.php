<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use App\Constants\permissions\PermissionSources;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentMethod;
use App\Models\PermissionSource;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\Store\OnlineOrder;
use App\Models\Store\OnlineOrderStatusHistory;
use App\Models\Store\StoreCoupon;
use App\Models\Store\StoreCouponUsage;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\OnlineStoreSettingsService;
use App\Support\AccountingSettings;
use App\Support\TreasuryAccessGuard;

/**
 * محاسبةُ طلب المتجر في موضعٍ واحد: قيدُ البيع، التحصيل، وعكسُهما.
 *
 * **لماذا سِمةٌ مشتركة؟** كان قيدُ البيع في `Api\StoreOrderController` وعكسُه
 * في `OnlineOrderController`، كلٌّ يحسب مبالغه بطريقته: البيعُ يحسب الشحن
 * عكسياً من الإعداد الحالي، والعكسُ يقرأ المخزَّن في الطلب، وتكلفةُ المبيعات
 * تُعكَس بسعر الشراء **يوم الإرجاع** لا يوم البيع. وإلغاءُ العميل لطلبه من
 * المتجر لم يكن يعكس شيئاً أصلاً (حالةٌ فقط: الإيراد والخزينة والمخزون تبقى).
 *
 * القاعدة هنا: **تقسيمٌ واحد** ({@see orderBreakdown()}) مصدرُه الأرقام
 * المخزَّنة في الطلب، يُرحَّل به البيع ويُعكَس به — فما عُكس هو ما رُحِّل.
 *
 * القيود:
 *
 *   البيع (عند إتمام الطلب)        مدين: ذمّة العميل (الإجمالي) + خصم مسموح به
 *                                  دائن: المبيعات (صافٍ من الضريبة) + ضريبة
 *                                        القيمة المضافة + إيراد الشحن + شركة الشحن
 *                                        + ضريبة الشحن (حسابها المستقل)
 *   تكلفة المبيعات (مرّة واحدة)    مدين: تكلفة المبيعات — دائن: مخزون المستودع
 *   التحصيل (عند القبض الفعلي)     مدين: الخزينة — دائن: ذمّة العميل
 *   المرتجع/الإلغاء                عكسُ قيد البيع بنسبة المردود، وردُّ ما قُبض
 *                                  فعلاً من الخزينة، وإعادةُ المخزون وتكلفته
 *
 * **الدفع عند الاستلام والتحويل البنكي ذمّةٌ لا نقد**: لا تُمَسّ الخزينة حتى
 * يُسجَّل التحصيل. كان كلُّ طلبٍ يُقيَّد «مدفوعاً» في الخزينة لحظةَ إنشائه
 * مهما كانت طريقة الدفع — نقدٌ في الدفاتر لم يدخل الصندوق.
 *
 * تتطلّب السِّمة {@see ResolvesStoreDefaults} في الصنف المستعمِل.
 */
trait PostsStoreOrderAccounting
{
    /**
     * حسابٌ محاسبيّ للمتجر: قسم «الحسابات المحاسبية» في إعدادات المتجر هو
     * المرجع لبنوده العشرة؛ وما عداها: ربطُ «إعدادات الحسابات» في النظام ←
     * الثابت البرمجي.
     *
     * كانت الحلقة الوسطى مفقودة: ضريبةُ المتجر تذهب إلى الثابت (٣٧) والنظامُ
     * كلُّه يرحّل ضريبته إلى الحساب المربوط في الإعدادات — إقرارٌ ضريبي من
     * حسابٍ لا يحوي مبيعات المتجر.
     */
    protected function storeAccount(string $storeKey, string $systemKey, int $fallbackId): ?Account
    {
        // بنود قسم «الحسابات المحاسبية» في إعدادات المتجر تُقرأ منه وحده (المختار أو افتراضيّ الشاشة).
        // ما لا حقل له هناك (كحساب شركات الشحن والمخزون) يبقى على ربط النظام.
        $id = array_key_exists($storeKey, OnlineStoreSettingsService::STORE_ACCOUNTS)
            ? OnlineStoreSettingsService::storeAccountId($storeKey)
            : (OnlineStoreSettingsService::getAccountId($storeKey) ?: AccountingSettings::id($systemKey, $fallbackId));

        return $id ? Account::find($id) : null;
    }

    /** ذمّة عميل المتجر (حساب «العميل النقدي») — يتوسّط البيع والتحصيل. */
    protected function storeReceivableAccount(): Account
    {
        $account = $this->storeAccount('cash_client_account_id', 'cash_client', SystemAccountIds::CASH_CLIENT);

        if (! $account) {
            throw new \RuntimeException('حساب العميل النقدي للمتجر غير موجود. الرجاء ضبطه من إعدادات الحسابات.');
        }

        return $account;
    }

    /**
     * هل تُحصَّل قيمةُ الطلب لحظةَ إنشائه؟ البوّابات الإلكترونية والبطاقات
     * فقط. النقد/الدفع عند الاستلام، والتحويل البنكي، والشيك: ذمّةٌ حتى
     * يُسجَّل تحصيلُها من شاشة الطلب.
     */
    protected function paymentMethodIsPrepaid(PaymentMethod $method): bool
    {
        if ($method->isCash() || $this->paymentMethodIsBankTransfer($method) || $method->isCheck()) {
            return false;
        }

        return $method->type === 'electronic'
            || (bool) $method->requires_card
            || $method->isElectronic()
            || in_array($method->type, ['card', 'credit_card', 'debit_card', 'wallet', 'e_wallet'], true);
    }

    /**
     * تحويلٌ بنكي؟ `code` فارغٌ في أغلب الصفوف المزروعة، فيُستدلّ بالاسم أيضاً.
     */
    protected function paymentMethodIsBankTransfer(PaymentMethod $method): bool
    {
        return $method->isBankTransfer()
            || in_array($method->type, ['bank_transfer', 'bank'], true)
            || str_contains((string) $method->name, 'تحويل')
            || stripos((string) $method->name_en, 'transfer') !== false;
    }

    /**
     * الحساب الذي يُقبض فيه مبلغُ طلبٍ مدفوعٍ إلكترونياً: حسابُ طريقة الدفع
     * (حساب تسوية البوّابة) إن رُبط بها، وإلا خزينة/بنك المتجر الافتراضي.
     */
    protected function collectionAccountFor(?PaymentMethod $method): ?Account
    {
        if ($method && $method->account_id && ($account = Account::find($method->account_id))) {
            return $account;
        }

        return $this->getDefaultTreasuryAccount();
    }

    /**
     * تقسيمُ مبالغ الطلب على الحسابات — المصدرُ الوحيد لقيد البيع وعكسه.
     *
     * الأسعار الشاملة للضريبة: `subtotal` و`discount_amount` في الطلب بالسعر
     * الشامل، والضريبةُ مستخرَجةٌ منهما (لا مضافة): الإيرادُ والخصمُ يُقيَّدان
     * صافيَين. كانت الضريبة تُحسب صفراً متى كانت الأسعار شاملة، فيُقيَّد
     * كاملُ السعر إيراداً ولا يُرحَّل للضريبة شيء.
     *
     * @param  float  $ratio  نسبة المردود (١ = كامل الطلب)
     * @param  bool  $includeShipping  هل يدخل الشحن في التقسيم
     *                                 ضريبةُ الشحن (`shipping_vat`) مبلغٌ مستقلٌّ مضافٌ فوق رسم الشحن دائماً، يُقيَّد
     *                                 دائناً في «حساب ضريبة الشحن» ويدخل في الذمّة، ويتبع الشحنَ في الاسترداد:
     *                                 يُردّ بنسبة المردود ما دام الشحن قابلاً للردّ، ويبقى كاملاً متى شُحن الطلب.
     * @return array{receivable: float, sales: float, vat: float, discount: float, shipping_revenue: float, shipping_liability: float, shipping_vat: float}
     */
    protected function orderBreakdown(OnlineOrder $order, float $ratio = 1.0, bool $includeShipping = true): array
    {
        $subtotal = (float) $order->subtotal;
        $discount = (float) $order->discount_amount;
        $vat = (float) $order->tax_amount;
        $shipping = (float) $order->shipping_cost;
        $shippingVat = $includeShipping ? (float) ($order->shipping_tax_amount ?? 0) : 0.0;

        if ($order->prices_include_tax && $vat > 0) {
            // الوعاء الشامل = المجموع − الخصم؛ الضريبةُ جزءٌ منه
            $taxableGross = $subtotal - $discount;
            $factor = $taxableGross > 0 ? ($taxableGross - $vat) / $taxableGross : 1;
            $sales = round($subtotal * $factor, 2);
            $discountNet = round($sales + $vat - $taxableGross, 2);
        } else {
            $sales = $subtotal;
            $discountNet = $discount;
        }

        $shippingRevenue = (float) ($order->shipping_revenue_amount ?? 0);
        $shippingLiability = (float) ($order->shipping_company_cost ?? 0);

        // طلبٌ قديم بلا تفصيل الشحن، أو تفصيلٌ لا يساوي رسمَ الشحن المحصَّل
        // (شحنٌ مجاني بكوبون/حدٍّ أدنى): الرسمُ المحصَّل هو المرجع
        if (abs(($shippingRevenue + $shippingLiability) - $shipping) > 0.005) {
            $shippingLiability = min($shippingLiability, $shipping);
            $shippingRevenue = round($shipping - $shippingLiability, 2);
        }

        if (! $includeShipping) {
            $shippingRevenue = 0;
            $shippingLiability = 0;
        }

        $parts = [
            'sales' => round($sales * $ratio, 2),
            'vat' => round($vat * $ratio, 2),
            'discount' => round($discountNet * $ratio, 2),
            'shipping_revenue' => round($shippingRevenue * $ratio, 2),
            'shipping_liability' => round($shippingLiability * $ratio, 2),
            'shipping_vat' => round($shippingVat * $ratio, 2),
        ];

        // الذمّة هي ما يوازن القيد — فلا يختلّ بكسور التقريب
        $parts['receivable'] = round(
            $parts['sales'] + $parts['vat'] + $parts['shipping_revenue'] + $parts['shipping_liability'] + $parts['shipping_vat'] - $parts['discount'],
            2
        );

        return $parts;
    }

    /** رأسُ قيدٍ من قيود الطلب — بفرعه وعملته، وتاريخُه اليوم. */
    protected function orderJournalHeader(OnlineOrder $order, string $reference, string $description, ReferenceType $type, ?int $invoiceId = null): JournalEntry
    {
        return JournalEntry::create([
            'reference_number' => $reference,
            'date' => now(),
            'description' => $description,
            'status' => 1,
            'currency' => $order->documentCurrency(),
            'branch_id' => $order->branch_id,
            'client_id' => $order->client_id,
            'invoice_id' => $invoiceId ?? $order->invoice_id,
            'reference_type' => $type,
            'linking_type' => LinkingType::SINGLE,
        ]);
    }

    /** سطرُ قيد — يُهمَل الصفر. */
    protected function orderJournalLine(JournalEntry $entry, ?Account $account, string $description, float $debit, float $credit): void
    {
        if (! $account || (round($debit, 2) <= 0 && round($credit, 2) <= 0)) {
            return;
        }

        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'is_debit' => $debit > 0,
        ]);
    }

    /** حساب شركة الشحن للطلب: حسابُ طريقة الشحن، وإلا حساب «شركات الشحن». */
    protected function orderShippingCompanyAccount(OnlineOrder $order): ?Account
    {
        $order->loadMissing('shippingMethod');

        if ($order->shippingMethod?->account_id && ($account = Account::find($order->shippingMethod->account_id))) {
            return $account;
        }

        return $this->storeAccount('shipping_companies_account_id', 'shipping_companies', SystemAccountIds::SHIPPING_COMPANIES);
    }

    /**
     * قيدُ البيع: الإجمالي ذمّةً على العميل مقابل الإيراد والضريبة والشحن.
     * يرمي استثناءً إن نقص حسابٌ — طلبٌ بلا قيدٍ أسوأ من طلبٍ لم يُنشأ.
     */
    protected function postOrderSaleEntry(OnlineOrder $order, Invoice $invoice): JournalEntry
    {
        $b = $this->orderBreakdown($order);

        $receivable = $this->storeReceivableAccount();
        $sales = $this->storeAccount('sales_account_id', 'sales', SystemAccountIds::SALES);
        $vat = $this->storeAccount('vat_payable_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE);
        $discount = $this->storeAccount('allowed_discount_account_id', 'allowed_discount', SystemAccountIds::ALLOWED_DISCOUNT);
        $shippingRevenue = $this->storeAccount('shipping_revenue_account_id', 'shipping_revenue', SystemAccountIds::SHIPPING_REVENUE);
        $shippingCompany = $this->orderShippingCompanyAccount($order);
        $shippingVat = $this->storeAccount('shipping_vat_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE);

        $missing = array_keys(array_filter([
            'المبيعات' => ! $sales,
            'ضريبة القيمة المضافة' => $b['vat'] > 0 && ! $vat,
            'الخصم المسموح به' => $b['discount'] > 0 && ! $discount,
            'إيرادات الشحن' => $b['shipping_revenue'] > 0 && ! $shippingRevenue,
            'شركات الشحن' => $b['shipping_liability'] > 0 && ! $shippingCompany,
            'ضريبة الشحن' => $b['shipping_vat'] > 0 && ! $shippingVat,
        ]));

        if ($missing) {
            throw new \RuntimeException('حسابات محاسبية غير مضبوطة للمتجر: '.implode('، ', $missing));
        }

        $entry = $this->orderJournalHeader(
            $order,
            $invoice->code,
            'فاتورة مبيعات أونلاين رقم: '.$invoice->code.' | طلب '.$order->order_number,
            ReferenceType::SALES_INVOICE,
            $invoice->id
        );

        $this->orderJournalLine($entry, $receivable, 'مبيعات أونلاين - فاتورة '.$invoice->code, $b['receivable'], 0);
        $this->orderJournalLine($entry, $discount, 'خصم على فاتورة أونلاين '.$invoice->code, $b['discount'], 0);
        $this->orderJournalLine($entry, $sales, 'إيرادات مبيعات أونلاين - فاتورة '.$invoice->code, 0, $b['sales']);
        $this->orderJournalLine($entry, $vat, 'ضريبة القيمة المضافة - فاتورة '.$invoice->code, 0, $b['vat']);
        $this->orderJournalLine($entry, $shippingRevenue, 'إيرادات شحن - فاتورة '.$invoice->code, 0, $b['shipping_revenue']);
        $this->orderJournalLine($entry, $shippingCompany, 'مستحق لشركة الشحن - فاتورة '.$invoice->code, 0, $b['shipping_liability']);
        $this->orderJournalLine($entry, $shippingVat, 'ضريبة الشحن - فاتورة '.$invoice->code, 0, $b['shipping_vat']);

        return $entry;
    }

    /**
     * قيدُ تكلفة المبيعات — مرّةً واحدة عند البيع، بتكلفة الشراء يومَه.
     */
    protected function postOrderCostEntry(OnlineOrder $order, Invoice $invoice, float $costTotal, ?Account $inventoryAccount): ?JournalEntry
    {
        $costTotal = round($costTotal, 2);

        if ($costTotal <= 0) {
            return null;
        }

        $costOfSales = $this->storeAccount('cost_of_sales_account_id', 'cost_of_sales', SystemAccountIds::COST_OF_SALES);

        if (! $costOfSales || ! $inventoryAccount) {
            throw new \RuntimeException('حساب تكلفة المبيعات أو حساب مخزون المستودع غير مضبوط للمتجر.');
        }

        $entry = $this->orderJournalHeader(
            $order,
            $invoice->code.'-COST',
            'تكلفة مبيعات فاتورة أونلاين رقم '.$invoice->code,
            ReferenceType::SALES_INVOICE,
            $invoice->id
        );

        $this->orderJournalLine($entry, $costOfSales, 'تكلفة مبيعات - فاتورة '.$invoice->code, $costTotal, 0);
        $this->orderJournalLine($entry, $inventoryAccount, 'تخفيض المخزون - فاتورة '.$invoice->code, 0, $costTotal);

        return $entry;
    }

    /**
     * تحصيلُ قيمة الطلب: مدين الخزينة — دائن ذمّة العميل، ويُعلَّم الطلب
     * والفاتورة مدفوعَين. لا يُحصَّل طلبٌ مرّتين ولا طلبٌ ملغي.
     */
    protected function postOrderCollection(OnlineOrder $order, Account $treasury): JournalEntry
    {
        $amount = $order->collectibleAmount();

        if ($amount <= 0) {
            throw new \DomainException('لا يوجد مبلغ مستحق التحصيل على هذا الطلب.');
        }

        $order->loadMissing('invoice');
        $code = $order->invoice?->code ?? $order->order_number;

        $entry = $this->orderJournalHeader(
            $order,
            $code.'-PAY',
            'تحصيل طلب أونلاين #'.$order->order_number.' - فاتورة '.$code,
            ReferenceType::RECEIPT_VOUCHER
        );

        $this->orderJournalLine($entry, $treasury, 'تحصيل مبيعات أونلاين - فاتورة '.$code, $amount, 0);
        $this->orderJournalLine($entry, $this->storeReceivableAccount(), 'سداد العميل - فاتورة '.$code, 0, $amount);

        $order->update([
            'payment_status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'payment_account_id' => $treasury->id,
        ]);

        if ($order->invoice) {
            $order->invoice->forceFill([
                'payment_status' => 1,
                'is_paid' => true,
                'advance_payment' => $amount,
                'due_value' => 0,
                'remaining_amount' => 0,
            ])->save();
        }

        return $entry;
    }

    /**
     * تسويةُ شركة الشحن عند «تم الشحن»: تُسدَّد تكلفةُ الشحن من خزينة المتجر
     * سواءٌ حُمِّل رسمُه على العميل أو كان **مجانياً له** (قرار المالك).
     *
     * `shipping_company_cost` = ما تطلبه الشركة فعلاً (تكلفة طريقة الشحن)،
     * وقد يزيد على ما حُصِّل من العميل:
     *
     *   • ما قُيِّد دائناً لها يومَ البيع (من رسم العميل) يبقى كما هو؛
     *   • الباقي — الشحنُ المجاني بكوبون/حدٍّ أدنى، أو ما بقي بعد استرداد
     *     جزئي — تتحمّله المنشأة: مدين «مصروف الشحن المجاني» دائن شركة الشحن
     *     (`{رقم الطلب}-SHIP-EXP`)، يُقيَّد عند الشحن لا عند البيع لأنه لا
     *     يُستحقّ إلا بالشحن الفعلي (فلا عكسَ له عند الإلغاء قبل الشحن)؛
     *   • ثم يُسدَّد المستحقُّ كلُّه: مدين شركة الشحن دائن الخزينة (`-SHIP-PAY`).
     *
     * تكلفةٌ صفر ⇒ لا قيد. وتعذّرُ السداد (لا خزينة/لا رصيد/لا صلاحية سحب)
     * يُبقي المستحقَّ دائناً لشركة الشحن ليُسدَّد بسند صرف. القيدان idempotent
     * بالرقم المرجعي.
     */
    protected function postOrderCarrierSettlement(OnlineOrder $order): void
    {
        $cost = round(max(0.0, (float) ($order->shipping_company_cost ?? 0)), 2);

        if ($cost <= 0 || $order->is_shipping_paid) {
            return;
        }

        $company = $this->orderShippingCompanyAccount($order);

        if (! $company) {
            return;
        }

        // ما بقي دائناً لها من قيد البيع: مستحقُّ البيع ناقصاً حصّة ما استُرد قبل الشحن
        $total = (float) $order->total_amount;
        $refundedFraction = $total > 0 ? min(1.0, $order->refundedSoFar() / $total) : 0.0;
        $accrued = round($this->orderBreakdown($order)['shipping_liability'] * (1 - $refundedFraction), 2);
        $borne = round($cost - $accrued, 2);

        $expenseReference = $order->order_number.'-SHIP-EXP';

        if ($borne > 0.005 && ! JournalEntry::where('reference_number', $expenseReference)->exists()) {
            $expense = $this->storeAccount('free_shipping_expense_account_id', '', 0);

            if (! $expense) {
                throw new \DomainException('حساب «مصروف الشحن المجاني» غير مضبوط. اضبطه من إعدادات المتجر ← الحسابات المحاسبية ثم أعد المحاولة.');
            }

            $entry = $this->orderJournalHeader(
                $order,
                $expenseReference,
                'مصروف شحن تتحمّله المنشأة ('.($order->shippingMethod?->name ?? 'شحن').") للطلب #{$order->order_number}",
                ReferenceType::JOURNAL_VOUCHER
            );

            $this->orderJournalLine($entry, $expense, "مصروف شحن مجاني - طلب #{$order->order_number}", $borne, 0);
            $this->orderJournalLine($entry, $company, "مستحق لشركة الشحن (شحن مجاني) - طلب #{$order->order_number}", 0, $borne);
        }

        $reference = $order->order_number.'-SHIP-PAY';

        if (JournalEntry::where('reference_number', $reference)->exists()) {
            return;
        }

        $treasury = $this->getDefaultTreasuryAccount();

        if (! $treasury
            || TreasuryAccessGuard::withdrawBlockReason($treasury->id)
            || (float) $treasury->balance + 0.005 < $cost) {
            return;
        }

        $entry = $this->orderJournalHeader(
            $order,
            $reference,
            'تسديد شركة الشحن ('.($order->shippingMethod?->name ?? 'شحن').") للطلب #{$order->order_number}",
            ReferenceType::PAYMENT_VOUCHER
        );

        $this->orderJournalLine($entry, $company, "تسديد تكلفة شحن الطلب #{$order->order_number}", $cost, 0);
        $this->orderJournalLine($entry, $treasury, "دفع تكلفة شحن الطلب #{$order->order_number}", 0, $cost);

        $order->update(['is_shipping_paid' => true, 'shipping_paid_at' => now()]);
    }

    /**
     * عكسُ طلب: إلغاءٌ كامل، أو مرتجعٌ كامل (بضاعةٌ تعود)، أو خصمٌ جزئي
     * (مبلغٌ يُردّ بلا بضاعة).
     *
     * يُنادى **داخل معاملة** وعلى طلبٍ مقفولِ الصفّ. يُنشئ فاتورةَ مرتجع
     * برقمٍ من تسلسل `return-invoice`، ويعكس قيدَ البيع بنسبة المردود، ويردّ
     * من الخزينة ما قُبض فعلاً فقط (طلبٌ لم يُحصَّل تُسقَط ذمّتُه ولا تُمَسّ
     * خزينة)، ويعيد المخزون وتكلفتَه مرّةً واحدة.
     *
     * @param  string  $mode  cancel | refund
     * @param  float|null  $amount  مبلغ الردّ (refund فقط) — null = كامل المتاح
     * @param  Account|null  $refundTreasury  الخزينة التي يُصرف منها الردّ
     * @param  bool|null  $restock  هل عادت البضاعة؟ null = تلقائي (المرتجع الكامل من أول مرّة).
     *                              لا تُعاد إلا بردٍّ يُغلق الطلب، ومرّةً واحدة
     * @return array{return_invoice: ?Invoice, refunded_cash: float, amount: float, restocked: bool}
     */
    protected function reverseStoreOrder(OnlineOrder $order, string $reason, string $mode = 'cancel', ?float $amount = null, ?Account $refundTreasury = null, ?bool $restock = null): array
    {
        $order->loadMissing(['invoice.items', 'items', 'shippingMethod', 'client']);

        $invoice = $order->invoice;
        $includeShipping = $order->shippingIsRefundable();
        $base = $order->refundBase();
        $remaining = $order->refundableAmount();
        $wasPaid = $order->isPaid();

        $amount = $mode === 'cancel' || $amount === null ? $remaining : round($amount, 2);

        if ($amount - $remaining > 0.005) {
            throw new \DomainException('مبلغ الردّ يتجاوز المتاح ردُّه ('.number_format($remaining, 2).').');
        }

        // كامل = يُغلق الطلب كلَّه بلا مردودٍ سابق؛ وحده يعيد البضاعة
        $isFull = $order->refundedSoFar() <= 0 && abs($amount - $base) <= 0.005;
        $closesOrder = abs($amount - $remaining) <= 0.005;
        $ratio = $base > 0 ? $amount / $base : 0;
        $doRestock = ($mode === 'cancel' || ($restock ?? $isFull)) && $closesOrder && ! $order->stock_returned_at;

        $result = ['return_invoice' => null, 'refunded_cash' => 0.0, 'amount' => $amount, 'restocked' => false];

        // طلبٌ بلا فاتورة (قديمٌ جداً): لا قيود تُعكَس — المخزون وحده
        if ($invoice && $amount > 0) {
            $b = $this->orderBreakdown($order, $ratio, $includeShipping);
            // آخرُ ردٍّ يأخذ الباقي بالهللة — لا كسورَ معلّقة على ذمّة العميل
            $b['receivable'] = $amount;
            $b['sales'] = round($b['receivable'] - $b['vat'] - $b['shipping_revenue'] - $b['shipping_liability'] - $b['shipping_vat'] + $b['discount'], 2);

            $returnInvoice = $this->createOrderReturnInvoice($order, $invoice, $reason, $b, $doRestock);
            $result['return_invoice'] = $returnInvoice;

            $salesReturns = $this->storeAccount('sales_returns_account_id', 'sales_returns', SystemAccountIds::SALES_RETURNS);
            $vat = $this->storeAccount('vat_payable_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE);
            $discount = $this->storeAccount('allowed_discount_account_id', 'allowed_discount', SystemAccountIds::ALLOWED_DISCOUNT);
            $shippingReturns = $this->storeAccount('shipping_revenue_returns_account_id', 'shipping_revenue_returns', SystemAccountIds::SHIPPING_REVENUE_RETURNS)
                ?? $this->storeAccount('shipping_revenue_account_id', 'shipping_revenue', SystemAccountIds::SHIPPING_REVENUE);
            $shippingCompany = $this->orderShippingCompanyAccount($order);
            $shippingVat = $this->storeAccount('shipping_vat_account_id', 'vat_payable', SystemAccountIds::VAT_PAYABLE);
            $receivable = $this->storeReceivableAccount();

            if (! $salesReturns || ($b['vat'] > 0 && ! $vat) || ($b['discount'] > 0 && ! $discount)
                || ($b['shipping_revenue'] > 0 && ! $shippingReturns) || ($b['shipping_liability'] > 0 && ! $shippingCompany)
                || ($b['shipping_vat'] > 0 && ! $shippingVat)) {
                throw new \RuntimeException('حسابات المرتجع غير مضبوطة للمتجر (مردودات المبيعات/الضريبة/الخصم/الشحن/ضريبة الشحن).');
            }

            // ===== 1. عكس قيد البيع =====
            $entry = $this->orderJournalHeader(
                $order,
                $returnInvoice->code,
                ($mode === 'cancel' ? 'إلغاء' : 'مرتجع')." طلب أونلاين #{$order->order_number} - {$reason}",
                ReferenceType::SALES_RETURN,
                $returnInvoice->id
            );

            $this->orderJournalLine($entry, $salesReturns, "مردود مبيعات - طلب #{$order->order_number}", $b['sales'], 0);
            $this->orderJournalLine($entry, $vat, "عكس ضريبة القيمة المضافة - طلب #{$order->order_number}", $b['vat'], 0);
            $this->orderJournalLine($entry, $shippingReturns, "مردود إيرادات شحن - طلب #{$order->order_number}", $b['shipping_revenue'], 0);
            $this->orderJournalLine($entry, $shippingCompany, "إسقاط مستحق شركة الشحن - طلب #{$order->order_number}", $b['shipping_liability'], 0);
            $this->orderJournalLine($entry, $shippingVat, "عكس ضريبة الشحن - طلب #{$order->order_number}", $b['shipping_vat'], 0);
            $this->orderJournalLine($entry, $discount, "عكس خصم مسموح به - طلب #{$order->order_number}", 0, $b['discount']);
            $this->orderJournalLine($entry, $receivable, "تخفيض ذمّة العميل - طلب #{$order->order_number}", 0, $b['receivable']);

            // ===== 2. ردّ النقد — فقط إن كان الطلب محصَّلاً =====
            if ($wasPaid) {
                $treasury = $refundTreasury
                    ?? ($order->payment_account_id ? Account::find($order->payment_account_id) : null)
                    ?? $this->getDefaultTreasuryAccount();

                if (! $treasury) {
                    throw new \DomainException('لم تُحدَّد خزينة يُصرف منها مبلغ الردّ. الرجاء ضبط الخزينة الافتراضية من إعدادات المتجر.');
                }

                $refundEntry = $this->orderJournalHeader(
                    $order,
                    $returnInvoice->code.'-REF',
                    "رد مبلغ طلب أونلاين #{$order->order_number} - {$reason}",
                    ReferenceType::PAYMENT_VOUCHER,
                    $returnInvoice->id
                );

                $this->orderJournalLine($refundEntry, $receivable, "رد مبلغ للعميل - طلب #{$order->order_number}", $amount, 0);
                $this->orderJournalLine($refundEntry, $treasury, "صرف مبلغ الرد - طلب #{$order->order_number}", 0, $amount);

                $result['refunded_cash'] = $amount;
            }

            // ===== 3. البضاعة وتكلفتها — بردٍّ يُغلق الطلب فقط، ومرّةً واحدة =====
            if ($doRestock) {
                $this->restoreOrderInventory($order, $invoice);
                $this->reverseOrderCostEntry($order, $invoice, $returnInvoice);
                $result['restocked'] = true;
            }
        } elseif (! $invoice && $mode === 'cancel' && ! $order->stock_returned_at) {
            $this->restoreOrderInventory($order, null);
            $result['restocked'] = true;
        }

        // الإلغاء يعيد للكوبون استعمالَه — طلبٌ أُلغي لم يستهلك الكوبون
        if ($mode === 'cancel' && $order->coupon_id && $invoice) {
            $usage = StoreCouponUsage::where('coupon_id', $order->coupon_id)->where('invoice_id', $invoice->id)->first();

            if ($usage) {
                $usage->delete();
                StoreCoupon::where('id', $order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        }

        $updates = [
            'refund_amount' => round($order->refundedSoFar() + $amount, 2),
        ];

        if ($result['restocked']) {
            $updates['stock_returned_at'] = now();
        }

        if ($mode === 'cancel') {
            $updates['status'] = OrderStatus::CANCELLED;
            $updates['cancel_reason'] = $reason;
        } else {
            $updates['refund_reason'] = $reason;

            if ($closesOrder) {
                $updates['status'] = OrderStatus::REFUNDED;
            }
        }

        if ($wasPaid && $closesOrder) {
            $updates['payment_status'] = PaymentStatus::REFUNDED;
        }

        $order->update($updates);

        // `cancelled_at` خارج fillable — يُكتب مباشرةً
        if ($mode === 'cancel') {
            $order->forceFill(['cancelled_at' => now(), 'cancelled_by' => auth()->id()])->save();
        }

        // طلبٌ لم يُحصَّل وأُغلق: فاتورتُه لم تعد مستحقّة
        if ($invoice && ! $wasPaid) {
            $invoice->forceFill(['due_value' => $order->fresh()->collectibleAmount()])->save();
        }

        OnlineOrderStatusHistory::createRecord(
            $order->id,
            $order->status->value,
            ($mode === 'cancel' ? 'سبب الإلغاء: ' : 'مبلغ الردّ: '.number_format($amount, 2).' - السبب: ').$reason
        );

        return $result;
    }

    /**
     * فاتورةُ المرتجع — رقمُها من تسلسل `return-invoice` (لا يُعاد استعماله).
     */
    protected function createOrderReturnInvoice(OnlineOrder $order, Invoice $original, string $reason, array $b, bool $withItems): Invoice
    {
        $gross = $b['receivable'];
        $shipping = round($b['shipping_revenue'] + $b['shipping_liability'], 2);

        $return = Invoice::create([
            'client_id' => $original->client_id,
            'branch_id' => $order->branch_id,
            'code' => next_document_number('return-invoice'),
            'type' => 'returned',
            // reference_number = معرّف الفاتورة الأصلية: به تعرض شاشة الفواتير شارة «مرتجع»
            'reference_number' => (string) $original->id,
            'invoice_date' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'notes' => "مرتجع طلب أونلاين #{$order->order_number} - السبب: {$reason}",
            'payment_status' => 4,
            'is_paid' => true,
            'currency' => $order->documentCurrency(),
            'created_by' => auth()->id() ?? $original->created_by,
            'employee_id' => $original->employee_id,
            'account_id' => $original->account_id,
            'discount_amount' => $b['discount'],
            'discount_type' => 1,
            'subtotal' => $b['sales'],
            // كما في فواتير المبيعات: ضريبةُ الشحن داخل tax_total وعمودُها المستقل shipping_tax
            'tax_total' => round($b['vat'] + $b['shipping_vat'], 2),
            'shipping_tax' => $b['shipping_vat'],
            'shipping_cost' => $shipping,
            'grand_total' => $gross,
            'tax_type' => $original->tax_type,
            'payment_method' => $original->payment_method,
            'treasury_id' => $original->treasury_id,
            'returned_invoice_id' => $original->id,
            'invoice_id' => $original->id,
        ]);

        // البنود تُنسخ للمرتجع الكامل وحده؛ الخصم الجزئي مبلغٌ بلا بضاعة
        if ($withItems) {
            foreach ($original->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $return->id,
                    'product_id' => $item->product_id,
                    'store_house_id' => $item->store_house_id,
                    'item' => $item->item,
                    'description' => 'مرتجع - '.($item->description ?? ''),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'discount_type' => $item->discount_type,
                    'tax_1' => $item->tax_1,
                    'tax_2' => $item->tax_2,
                    'total' => $item->total,
                ]);
            }
        }

        $original->returned_payment = round((float) ($original->returned_payment ?? 0) + $gross, 2);
        $original->save();

        return $return;
    }

    /**
     * عكسُ تكلفة المبيعات **بما رُحِّل يومَ البيع** — يُقرأ من قيد التكلفة
     * الأصلي لا من سعر الشراء الحالي. كان يُعاد حسابُها بسعر اليوم: سعرٌ
     * تغيّر بين البيع والإرجاع يترك فرقاً معلّقاً في المخزون وتكلفة المبيعات.
     */
    protected function reverseOrderCostEntry(OnlineOrder $order, Invoice $original, Invoice $returnInvoice): void
    {
        $costEntry = JournalEntry::with('details')
            ->where('invoice_id', $original->id)
            ->where('reference_number', $original->code.'-COST')
            ->first();

        if (! $costEntry) {
            return;
        }

        $entry = $this->orderJournalHeader(
            $order,
            $returnInvoice->code.'-COST',
            "عكس تكلفة مبيعات - طلب أونلاين #{$order->order_number}",
            ReferenceType::SALES_RETURN,
            $returnInvoice->id
        );

        // مرآةُ القيد الأصلي: نفس الحسابات، مدينُها دائنٌ ودائنُها مدين
        foreach ($costEntry->details as $line) {
            $this->orderJournalLine(
                $entry,
                Account::find($line->account_id),
                "عكس تكلفة مبيعات - طلب #{$order->order_number}",
                (float) $line->credit,
                (float) $line->debit
            );
        }
    }

    /**
     * إعادةُ كميات الطلب إلى المستودع الذي خرجت منه، بإذن إضافةٍ لكل بند.
     */
    protected function restoreOrderInventory(OnlineOrder $order, ?Invoice $original): void
    {
        $permissionSource = PermissionSource::find(PermissionSources::SALES_RETURN);
        $createdById = auth()->id() ?? $original?->created_by ?? 1;
        $fallbackStorehouseId = $this->getDefaultStorehouse()?->id;

        foreach ($order->items as $item) {
            $storeHouseId = $item->store_house_id ?? $fallbackStorehouseId;
            $quantity = (float) $item->quantity;

            if (! $storeHouseId || $quantity <= 0 || ! $item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);
            $unitCost = (float) ($product?->purchase_price ?? $item->unit_price);

            $details = ProductDetails::where('product_id', $item->product_id)
                ->where('store_house_id', $storeHouseId)
                ->lockForUpdate()
                ->first();

            $stockBefore = (float) ($details?->quantity ?? 0);

            if ($details) {
                $details->increment('quantity', $quantity);
            } else {
                ProductDetails::create([
                    'product_id' => $item->product_id,
                    'store_house_id' => $storeHouseId,
                    'quantity' => $quantity,
                    'unit_price' => $unitCost,
                ]);
            }

            if ($permissionSource) {
                $permit = WarehousePermits::create([
                    'permission_source_id' => $permissionSource->id,
                    'permission_date' => now(),
                    'number' => $original?->id ?? $order->id,
                    'grand_total' => $unitCost * $quantity,
                    'store_houses_id' => $storeHouseId,
                    'reference_type' => 'Invoice',
                    'reference_id' => $original?->id,
                    'created_by' => $createdById,
                ]);

                WarehousePermitsProducts::create([
                    'warehouse_permits_id' => $permit->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitCost,
                    'total' => $unitCost * $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore + $quantity,
                ]);
            }
        }
    }
}
