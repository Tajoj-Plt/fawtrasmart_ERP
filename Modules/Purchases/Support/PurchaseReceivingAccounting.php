<?php

namespace Modules\Purchases\Support;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseReceiving;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\StockEligibility;
use Illuminate\Support\Facades\Auth;

/**
 * قيد اعتماد «استلام مشتريات».
 *
 * كان اعتماد الاستلام يحرّك المخزون ويعتمد الإذن المخزني ولا يكتب في
 * الدفتر شيئاً: تدخل البضاعة المستودعَ فعلاً وكمّيةً، ولا يظهر لها أثرٌ
 * في الحسابات حتى تُحوَّل إلى فاتورة. فإن أُقفلت الفترة قبل الفوترة —
 * أو لم تُفوتَر البضاعة أصلاً — ظهر المستودع أقلَّ ممّا فيه.
 *
 * القيد المكتوب هنا هو نفسه الذي كانت تكتبه فاتورةُ الشراء عند اعتماد
 * إذنها المخزني (`InvoicesPurchaseController::createReceiptAccountingEntry`):
 *
 *     من ح/ المستودع        (مدين)
 *         إلى ح/ المشتريات  (دائن)
 *
 * بالقيمة قبل الضريبة، والأصنافُ ذات الرصيد وحدها — الخدميّ والرقميّ لا
 * يدخلان المستودع فلا تُنقَل قيمتُهما إليه، بل تبقى في حساب المشتريات.
 *
 * **وما لهذا القيد من أثرٍ على الفوترة:** لمّا صار الاستلام يُقفل قيمةَ
 * البضاعة في المستودع بنفسه، لم يعد على الفاتورة المولودة منه أن تعيد
 * التصنيف مرّةً ثانية — وإلا حُسبت القيمة مرّتين. فـ
 * `PurchaseReceivingController::convertToInvoice()` صار يسأل
 * `hasReceiptEntry()` قبل أن يستدعي
 * `PurchaseInvoiceConversionAccounting::postReceiptReclassEntry()`،
 * فيتخطّاها للاستلامات التي كتبت قيدها، ويُبقيها للاستلامات القديمة
 * التي اعتُمدت قبل هذا التغيير ولا قيد لها.
 *
 * يبقى على الفاتورة قيدُ الالتزام وحده: من ح/ المشتريات وضريبة المدخلات
 * إلى ح/ المورد. فمجموع القيدين: من ح/ المستودع والضريبة إلى ح/ المورد.
 *
 * **وحسابُ المشتريات يتقاصّ إلى صفر متى فوتِر المستلَم كاملاً وبلا خصم.**
 * فإن كان للمستند خصم، بقي فيه بمقداره: الفاتورة تُدين المشتريات
 * بالإجمالي قبل الخصم وتُدين «خصم مكتسب» بالخصم، والاستلامُ يُقفل في
 * المستودع صافيَ ما دُفع. والباقيان يتقاصّان في قائمة الدخل — مصروفُ
 * مشترياتٍ بقدر الخصم يقابله إيرادُ خصمٍ بقدره — فالأثرُ صفر. وهذا هو
 * سلوكُ مسار «فاتورة الشراء المباشرة» نفسِه منذ كُتب، لا انفرادَ لهذا
 * المسار به.
 */
class PurchaseReceivingAccounting
{
    /**
     * مرجع تكرارٍ مبنيٌّ على الإذن المخزني — الإذن واحدٌ لكل استلام،
     * وهو نفس الشكل الذي تستعمله فاتورة الشراء لإذنها.
     */
    public static function batchReference(WarehousePermits $permit): string
    {
        return 'WAREHOUSE_'.$permit->id;
    }

    /** هل كُتب قيد الاستلام لهذا الاستلام؟ */
    public static function hasReceiptEntry(PurchaseReceiving $receiving): bool
    {
        if (! $receiving->warehouse_permit_id) {
            return false;
        }

        return JournalEntry::where('batch_reference', 'WAREHOUSE_'.$receiving->warehouse_permit_id)->exists();
    }

    /**
     * قيمة البضاعة الداخلة إلى المستودع — الأصناف ذات الرصيد وحدها.
     *
     * **صافيةً بعد خصم البند والخصم العامّ وبلا الضريبة المتضمَّنة.** كانت
     * `الكمية المستلمة × سعر بند الأمر` — أي الإجماليَّ الخام، فأمرٌ خصمُه
     * ثلاثون يُنتج قيداً يُدخل المستودعَ ثلاثين زيادةً عمّا دُفع فيه،
     * ويترك حسابَ المشتريات لا يتقاصّ إلى صفر بعد الفوترة. وهي نفسُ العلّة
     * التي تحذّر منها فاتورةُ الشراء في
     * `InvoicesPurchaseController::createReceiptAccountingEntry()` وتتجنّبها
     * بقراءة سطور الإذن — وهذا ما يفعله هذا التابع الآن.
     *
     * فالإذنُ المخزنيّ هو المصدر: بنودُه تُكتب بصافي التكلفة
     * (`PurchaseReceivingCost`) وقت إنشاء الاستلام، فما يُقيَّد في الدفتر
     * هو نفسه ما تُقوَّم به البضاعةُ في `Product::averageCost()`.
     *
     * والأصنافُ بلا رصيد مستثناةٌ كما كانت: لا تدخل المستودع فلا تُنقَل
     * قيمتُها إليه. وهي أصلاً لا يُكتب لها بندُ إذنٍ، فاستثناؤها قائمٌ
     * بنفسه — ويبقى الفحص صريحاً للأذون القديمة.
     */
    public static function receiptValue(PurchaseReceiving $receiving): float
    {
        if (! $receiving->warehouse_permit_id) {
            return 0.0;
        }

        $stocklessProductIds = [];

        foreach ($receiving->items as $item) {
            $orderItem = $item->orderItem;

            if ($orderItem && $orderItem->product_id && StockEligibility::isStockless($orderItem->product_id)) {
                $stocklessProductIds[] = (int) $orderItem->product_id;
            }
        }

        $total = WarehousePermitsProducts::where('warehouse_permits_id', $receiving->warehouse_permit_id)
            ->when($stocklessProductIds !== [], fn ($q) => $q->whereNotIn('product_id', $stocklessProductIds))
            ->sum('total');

        return round((float) $total, 2);
    }

    /**
     * حساب المستودع — يُنشأ عند أوّل حركةٍ عليه، كما في المسار القديم.
     */
    public static function storeAccount(int $storeHouseId): Account
    {
        $account = Account::where('storehouse_id', $storeHouseId)->first();

        if ($account) {
            return $account;
        }

        $store = StoreHouse::find($storeHouseId);

        return Account::create([
            'name' => 'حساب المستودع - '.($store->name ?? 'مستودع غير معروف'),
            'storehouse_id' => $storeHouseId,
            'account_type' => 'assets',
            'balance' => 0,
            'status' => 1,
        ]);
    }

    /**
     * كتابة القيد. تُستدعى داخل معاملة `approve()` — فالخطأ هنا يُبطل
     * الاعتماد كلَّه ولا يُبتلع: اعتمادٌ يحرّك المخزون ويسقط قيدُه صامتاً
     * يترك الدفتر مخالفاً للمستودع، وهو أسوأ من اعتمادٍ لم يقع.
     *
     * @throws \Exception إن لم يكن حساب المشتريات مضبوطاً في الإعدادات
     */
    public static function postReceiptEntry(PurchaseReceiving $receiving, WarehousePermits $permit): void
    {
        $totalAmount = self::receiptValue($receiving);

        // استلامٌ كلُّه أصنافٌ بلا رصيد: لا شيء يدخل المستودع فلا قيد
        if ($totalAmount <= 0) {
            return;
        }

        $batchReference = self::batchReference($permit);

        if (JournalEntry::where('batch_reference', $batchReference)->exists()) {
            return;
        }

        $purchasesAccount = AccountingSettings::account('purchases');

        // لا بديل ثابت عن الإعدادات: حسابٌ غير مربوط يوقف الاعتماد
        // برسالةٍ تسمّي المفتاح الناقص، ولا يُرحَّل القيد إلى حسابٍ مخمَّن
        if (! $purchasesAccount) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.purchases'),
            ]));
        }

        $storeAccount = self::storeAccount((int) $receiving->store_house_id);

        $journalEntry = JournalEntry::create([
            'reference_number' => $receiving->code,
            'date' => $receiving->receiving_date ?? now(),
            'description' => 'قيد استلام بضاعة - استلام مشتريات رقم '.$receiving->code,
            'reference_type' => ReferenceType::APPROVED_WAREHOUSE,
            // مُراقِب `JournalEntry` يقرأ `reference_id` مُعرّفَ إذنٍ مخزني
            // ويعتمده متى رُحِّل قيده — فهو الإذن لا الاستلام
            'reference_id' => $permit->id,
            'batch_reference' => $batchReference,
            'branch_id' => $receiving->branch_id,
            'status' => 1,
            'currency' => CurrencySettings::code(),
            'created_by_employee' => Auth::id(),
            // أمر الشراء صفٌّ في `purchase_invoices`، فيربط القيد بمصدره
            'purchase_invoice_id' => $receiving->purchase_order_id,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => 'استلام بضاعة - استلام مشتريات # '.$receiving->code,
            'debit' => $totalAmount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $purchasesAccount->id,
            'description' => 'إقفال مشتريات في المخزن - استلام مشتريات # '.$receiving->code,
            'debit' => 0,
            'credit' => $totalAmount,
            'is_debit' => false,
        ]);

        $storeAccount->balance += $totalAmount;
        $storeAccount->save();

        $purchasesAccount->balance -= $totalAmount;
        $purchasesAccount->save();

        Account::updateBalancesHierarchically($storeAccount->id);
        Account::updateBalancesHierarchically($purchasesAccount->id);
    }
}
