<?php

namespace Modules\Purchases\Support;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\ProductDetails;
use App\Models\PurchaseInvoice;
use App\Models\TaxInvoice;
use App\Models\WarehousePermits;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\AccountingSettings;
use App\Support\StockEligibility;
use Illuminate\Support\Facades\Auth;

/**
 * خروجُ البضاعة بمرتجع شراءٍ أو إشعارٍ مدين — كاتبٌ واحد للمستندين.
 *
 * ══════════════════ لماذا؟ ══════════════════
 *
 * كان لكلٍّ من `ReturnsInvoiceController` و`CityNoticesController` نسخته
 * من ثلاث دوالّ متطابقة (بنود الإذن، وخصم الكمّيات، وقيد الإخراج)، وفي
 * النسختين معاً ثلاثة أخطاء:
 *
 *   ١. **الخصم من المستودع الخطأ.** بنودُ المستند تحمل مستودعَها من
 *      الفاتورة الأصلية (البضاعة تخرج من حيث دخلت)، لكنّ الخصمَ وقيدَ
 *      الإخراج كانا يقرآن مستودعَ **رأس الإذن** — وهو المستودع الرئيسي
 *      افتراضاً. فبضاعةٌ استُلمت في «مستودع المنصوب» تُخصَم من «المستودع
 *      الرئيسي» ويُقيَّد خروجُها على حسابه؛ يبقى الأوّلُ ممتلئاً بما خرج
 *      منه ويهبط الثاني تحت الصفر.
 *
 *   ٢. **قيدُ الإخراج بالإجمالي الخام.** `الكمية × السعر` بلا خصم البند
 *      ولا الخصم العامّ ولا الضريبة المتضمَّنة — بينما دخلت البضاعةُ
 *      المستودعَ بصافي تكلفتها (`PurchaseReceivingCost`). فكلُّ مرتجعٍ
 *      يُخرج من حساب المستودع أكثرَ ممّا أدخلته فاتورتُه.
 *
 *   ٣. **بندُ الإذن كذلك خام** — و`warehouse_permits_products.total` هو
 *      وعاءُ التكلفة الذي يقرؤه `Product::averageCost()`.
 *
 * فصار الثلاثةُ هنا، على وعاء التكلفة نفسه الذي يستعمله الاستلام.
 */
class PurchaseOutboundStock
{
    /**
     * بنودُ الإذن المخزني — الأصنافُ ذات الرصيد وحدها، بصافي التكلفة،
     * وكلٌّ من مستودعه.
     *
     * @return array<int, array<string, mixed>>  صفوفٌ جاهزة لـ`WarehousePermitsProducts::create()`
     *                                            بلا `warehouse_permits_id` ولا `details`
     */
    public static function permitLines(PurchaseInvoice $document, ?int $fallbackStoreHouseId): array
    {
        $items = collect($document->items ?? [])
            ->filter(fn ($item) => $item->product_id && StockEligibility::isStockable($item->product_id))
            ->values();

        if ($items->isEmpty()) {
            return [];
        }

        $costs = PurchaseReceivingCost::netLineCosts(
            $document,
            $items->map(fn ($item) => ['orderItem' => $item, 'quantity' => (float) $item->quantity])->all()
        );

        return $items->map(function ($item, $key) use ($costs, $fallbackStoreHouseId) {
            $quantity = (float) $item->quantity;
            $total = round($costs[$key] ?? 0.0, 2);

            return [
                'product_id' => $item->product_id,
                'quantity' => $quantity,
                // سعرُ الوحدة الصافي — لا سعرُ الفاتورة قبل الخصم
                'unit_price' => $quantity > 0 ? round($total / $quantity, 4) : 0,
                'total' => $total,
                'store_house_id' => $item->store_house_id ?: $fallbackStoreHouseId,
            ];
        })->all();
    }

    /**
     * خصمُ الكمّيات — كلُّ بندٍ من مستودعه، بالوحدة الأساسية.
     */
    public static function deduct(PurchaseInvoice $document, WarehousePermits $permit): void
    {
        foreach ($document->items ?? [] as $item) {
            if (! $item->product_id || ! StockEligibility::isStockable($item->product_id)) {
                continue;
            }

            $storeHouseId = (int) ($item->store_house_id ?: $permit->store_houses_id);

            // `firstOrCreate` لا `first`: صنفٌ بلا سجلٍّ في هذا المستودع كان
            // يُسقِط الخصمَ صامتاً ويُعتمَد الإذنُ وكأنّ شيئاً حدث
            $detail = ProductDetails::firstOrCreate(
                ['store_house_id' => $storeHouseId, 'product_id' => $item->product_id],
                ['quantity' => 0]
            );

            $detail->quantity -= (float) ($item->quantity_in_base_unit ?? $item->quantity);
            $detail->save();
        }
    }

    /**
     * قيدُ الإخراج: من ح/ المشتريات إلى ح/ كلِّ مستودعٍ بصافي ما خرج منه.
     *
     * يُكتب مرّةً واحدة لكلّ إذن (`batch_reference`)، ويعود بالمبلغ المرحَّل
     * — صفراً إن لم يكن ثمّة ما يُقيَّد.
     *
     * @throws \Exception إن لم يكن حساب المشتريات مضبوطاً في «الحسابات المحاسبية»
     */
    public static function postExitEntry(
        PurchaseInvoice $document,
        WarehousePermits $permit,
        string $description,
        string $lineLabel,
        string $currency
    ): float {
        $batchReference = 'WAREHOUSE_'.$permit->id;

        if (JournalEntry::where('batch_reference', $batchReference)->exists()) {
            return 0.0;
        }

        // القيمةُ من سطور الإذن — وعاءُ التكلفة نفسُه الذي دخلت به البضاعة
        $byStore = [];

        // `items()` هي سطورُ الإذن؛ `products()` علاقةُ أصنافٍ لا تحمل مبالغ
        foreach ($permit->items()->get() as $line) {
            $storeId = (int) ($line->store_house_id ?: $permit->store_houses_id);
            $byStore[$storeId] = round(($byStore[$storeId] ?? 0.0) + (float) $line->total, 2);
        }

        $byStore = array_filter($byStore, fn ($amount) => $amount > 0);
        $total = round(array_sum($byStore), 2);

        if ($total <= 0) {
            return 0.0;
        }

        $purchasesAccount = AccountingSettings::account('purchases');

        if (! $purchasesAccount) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.purchases'),
            ]));
        }

        $journalEntry = JournalEntry::create([
            'date' => $document->received_date ?? now(),
            'description' => $description,
            'reference_type' => ReferenceType::APPROVED_WAREHOUSE,
            'batch_reference' => $batchReference,
            'status' => 1,
            'currency' => $currency,
            'created_by_employee' => Auth::id(),
            'purchase_invoice_id' => $document->id,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $purchasesAccount->id,
            'description' => 'إلغاء مشتريات من المخزن - '.$lineLabel,
            'debit' => $total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        foreach ($byStore as $storeId => $amount) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => PurchaseReceivingAccounting::storeAccount($storeId)->id,
                'description' => 'خروج بضاعة - '.$lineLabel,
                'debit' => 0,
                'credit' => $amount,
                'is_debit' => false,
            ]);
        }

        // الأرصدة تتحدّث من مُراقِب `JournalEntryDetail` نفسِه
        return $total;
    }

    /**
     * سطورُ الضريبة المحفوظة (`TaxInvoice`) من الحساب الذي بُنيت عليه
     * الإجماليات — `PurchaseDocumentTotals::compute()['tax_rows']`.
     *
     * كانت تُحسب من جديد بـ`النسبة × الكمية × السعر`: بلا خصم البند ولا
     * الخصم العامّ، وتُكتب سطراً لكلّ ضريبةٍ ولو كانت صفرية — فتخالف
     * المطبوعاتُ إجماليَّ الضريبة الذي رُحِّل في القيد.
     *
     * @param  array<int, array{id: mixed, rate: float, amount: float}>  $taxRows
     */
    public static function writeTaxRows(
        int $documentId,
        array $taxRows,
        string $typeInvoice,
        float $shippingTax = 0.0,
        float $shippingCost = 0.0
    ): void {
        foreach ($taxRows as $detail) {
            $setting = PurchaseTaxCalculator::find($detail['id'] ?? null);

            if (! $setting || round((float) $detail['amount'], 2) == 0.0) {
                continue;
            }

            TaxInvoice::create([
                'name' => $setting->name,
                'invoice_id' => $documentId,
                'type' => $setting->type,
                'rate' => $detail['rate'],
                'value' => round((float) $detail['amount'], 2),
                'type_invoice' => $typeInvoice,
            ]);
        }

        /* ضريبةُ الشحن سطرٌ مستقلّ — كما تكتبها فاتورةُ الشراء وأمرُه —
           وإلا لم يبلغ مجموعُ السطور إجماليَّ الضريبة المرحَّل في القيد */
        $shippingTax = round($shippingTax, 2);

        if ($shippingTax > 0) {
            $rate = $shippingCost > 0 ? round(($shippingTax / $shippingCost) * 100, 2) : 0.0;

            TaxInvoice::create([
                'name' => 'ضريبة الشحن ('.rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.').'%)',
                'invoice_id' => $documentId,
                'type' => 'excluded',
                'rate' => $rate,
                'value' => $shippingTax,
                'type_invoice' => $typeInvoice,
            ]);
        }
    }

    /** حسابُ مستودع رأس الإذن — للشيفرة التي ما زالت تقرؤه بعد القيد */
    public static function headerStoreAccount(WarehousePermits $permit): Account
    {
        return PurchaseReceivingAccounting::storeAccount((int) $permit->store_houses_id);
    }
}
