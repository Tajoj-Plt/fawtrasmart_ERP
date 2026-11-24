<?php

namespace Modules\Purchases\Support;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\TreasuryEmployee;
use App\Support\AccountingSettings;
use Modules\Purchases\Support\PurchaseInvoiceJournal;
use App\Support\CurrencySettings;
use App\Support\StockEligibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * منطق القيود المحاسبية والخزينة المشترك بين تحويل «استلام مشتريات» إلى
 * فاتورة، وكان قبل هذه الوحدة حصراً داخل
 * `PurchaseOrdersController::convertToInvoice()` (التحويل المباشر من أمر
 * شراء، المُعطَّل الآن) — نُقل هنا (لا نُسخ) ليبقى مصدراً واحداً، ويُستدعى
 * من `PurchaseReceivingController::convertToInvoice()`.
 *
 * القيدان اللذان يُنشئهما هذا الصنف مختلفان في طبيعتهما:
 *
 *   • `postSupplierLiabilityEntry` — الالتزام المالي تجاه المورّد؛ من
 *     المشتريات (والضريبة) مديناً والمورّد دائناً، وقيد دفعٍ إضافي إن
 *     وُجدت دفعة مقدَّمة. هذا القيد وحده يثبت الفاتورة.
 *
 *   • `postReceiptReclassEntry` — إعادة تصنيف قيمة البضاعة من حساب
 *     المشتريات إلى حساب المستودع. في النظام القديم كان يُكتب لاحقاً حين
 *     تُعتمَد «استلام» الفاتورة يدوياً (`InvoicesPurchaseController::
 *     approveWarehousePermit`/`createReceiptAccountingEntry`)، لأن
 *     الاستلام كان يقع *بعد* الفاتورة. في الدورة الجديدة الاستلام سابقٌ
 *     على الفاتورة دائماً — فحين تُنشأ فاتورةٌ من استلام، بضاعتُها
 *     بالفعل في المستودع، فيُكتب القيدان معاً فوراً بدل أن يبقى الأول
 *     معلَّقاً بانتظار اعتمادٍ لن يأتي من هذا المسار (لا إذن مخزني جديد
 *     يُفتح للفاتورة المولودة من استلام أصلاً — المخزون تحرّك عند اعتماد
 *     الاستلام نفسه).
 */
class PurchaseInvoiceConversionAccounting
{
    /**
     * الخزينة المستهدفة — خزينة الموظف إن وُجدت، وإلا الخزينة الرئيسية
     * من الإعدادات.
     *
     * @throws \Exception إن لم توجد خزينة متاحة إطلاقاً
     */
    public static function resolveTreasury(): Account
    {
        $user = Auth::user();
        $mainTreasuryAccount = null;

        if ($user && $user->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            $mainTreasuryAccount = ($treasuryEmployee && $treasuryEmployee->treasury_id)
                ? Account::where('id', $treasuryEmployee->treasury_id)->first()
                : AccountingSettings::account('main_treasury');
        } else {
            $mainTreasuryAccount = AccountingSettings::account('main_treasury');
        }

        if (! $mainTreasuryAccount) {
            throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
        }

        return $mainTreasuryAccount;
    }

    /** @throws \Exception إن كان رصيد الخزينة أقل من المبلغ المطلوب سحبه */
    public static function assertSufficientBalance(Account $treasury, float $amount): void
    {
        if ($amount > 0 && $treasury->balance < $amount) {
            throw new \Exception('رصيد الخزينة غير كافي. الرصيد الحالي: '.number_format($treasury->balance, 2).' والمطلوب: '.number_format($amount, 2));
        }
    }

    public static function paymentStatusText(string $status): string
    {
        return match ($status) {
            'paid' => 'مدفوعة بالكامل',
            'partially_paid' => 'مدفوعة جزئياً',
            'unpaid' => 'غير مدفوعة',
            default => 'غير محددة',
        };
    }

    /** حساب مضبوط في «الحسابات المحاسبية» — أو استثناء يسمّي المفتاح الناقص */
    public static function settingsAccount(string $key): Account
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
     * قيد الالتزام تجاه المورّد — منطقٌ منقول بلا تغيير عن
     * `PurchaseOrdersController::createAccountingEntriesForConversion()` القديمة.
     */
    public static function postSupplierLiabilityEntry(
        PurchaseInvoice $purchaseInvoice,
        float $paid_amount,
        Account $mainTreasuryAccount
    ): void {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $supplierAccount = Account::ledger()->where('supplier_id', $purchaseInvoice->supplier_id)->first();
        if (! $supplierAccount) {
            $supplier = Supplier::find($purchaseInvoice->supplier_id);
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.($supplier->name ?? 'مورد غير معروف'),
                'supplier_id' => $purchaseInvoice->supplier_id,
                'account_type' => 'supplier',
                'parent_id' => AccountingSettings::id('suppliers'),
                'balance' => 0,
                'status' => 1,
            ]);
        }

        $journalEntry1 = JournalEntry::create([
            'reference_number' => $purchaseInvoice->code,
            'purchase_invoice_id' => $purchaseInvoice->id,
            'date' => now(),
            'description' => 'فاتورة شراء # '.$purchaseInvoice->code,
            'status' => 1,
            'currency' => 'SAR',
            'client_id' => $purchaseInvoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PURCHASE_INVOICE,
            'linking_type' => LinkingType::SINGLE,
        ]);

        /* السطورُ من الكاتب المشترك — نفسِه الذي يستعمله الإنشاء المباشر
           والتعديل. وكان هنا ثلاثةُ سطور تُكتب يدوياً بمعادلة
           `grand_total − total_tax`: تكلفةُ الشحن تُدفَن في ح/ المشتريات،
           والخصمُ المكتسب بلا سطر، وضريبةُ الشحن غيرُ مفصولة — فتخرج
           الفاتورة المحوَّلة بقيدٍ يخالف قيدَ نظيرتها المُنشأة مباشرةً.
           انظر `PurchaseInvoiceJournal`. */
        PurchaseInvoiceJournal::applyDeltas(
            PurchaseInvoiceJournal::writeDetails($journalEntry1->id, $purchaseInvoice, $supplierAccount)
        );

        if ($paid_amount > 0) {
            $journalEntry2 = JournalEntry::create([
                'reference_number' => $purchaseInvoice->code.'_دفع',
                'date' => now(),
                'description' => 'دفع للمورد # '.$purchaseInvoice->code.' - '.self::paymentStatusText($purchaseInvoice->payment_status),
                'status' => 1,
                'currency' => 'SAR',
                'client_id' => $purchaseInvoice->supplier_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
                'linking_type' => LinkingType::SINGLE,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $supplierAccount->id,
                'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                'debit' => $paid_amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                'debit' => 0,
                'credit' => $paid_amount,
                'is_debit' => false,
            ]);

            $mainTreasuryAccount->balance -= $paid_amount;
            $mainTreasuryAccount->save();

            $supplierAccount->balance -= $paid_amount;
            $supplierAccount->save();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * إعادة تصنيف قيمة البضاعة من «المشتريات» إلى «المستودع» — فوراً،
     * لأن الاستلام سابقٌ على الفاتورة في هذه الدورة (انظر توثيق الصنف).
     * نفس منطق `InvoicesPurchaseController::createReceiptAccountingEntry()`
     * القديم، بمرجع تكرارٍ مبنيٍّ على الفاتورة نفسها بدل إذنٍ مخزني —
     * فلا إذن جديد يُفتح لفاتورةٍ مولودةٍ من استلام.
     */
    public static function postReceiptReclassEntry(PurchaseInvoice $purchaseInvoice, int $storeHouseId): void
    {
        try {
            $totalAmount = 0;
            foreach ($purchaseInvoice->items as $item) {
                if (! $item->product_id || StockEligibility::isStockless($item->product_id)) {
                    continue;
                }

                $totalAmount += ($item->quantity * $item->unit_price);
            }

            if ($totalAmount <= 0) {
                return;
            }

            $batchReference = 'RECEIVING_INVOICE_'.$purchaseInvoice->id;
            if (JournalEntry::where('batch_reference', $batchReference)->exists()) {
                return;
            }

            $storeAccount = Account::where('storehouse_id', $storeHouseId)->first();
            if (! $storeAccount) {
                $store = StoreHouse::find($storeHouseId);
                $storeAccount = Account::create([
                    'name' => 'حساب المستودع - '.($store->name ?? 'مستودع غير معروف'),
                    'storehouse_id' => $storeHouseId,
                    'account_type' => 'assets',
                    'balance' => 0,
                    'status' => 1,
                ]);
            }

            $journalEntry = JournalEntry::create([
                'date' => $purchaseInvoice->received_date ?? now(),
                'description' => 'قيد استلام بضاعة - فاتورة شراء رقم '.$purchaseInvoice->code,
                'reference_type' => ReferenceType::APPROVED_WAREHOUSE,
                'batch_reference' => $batchReference,
                'status' => 1,
                'currency' => CurrencySettings::code(),
                'created_by_employee' => Auth::id(),
                'purchase_invoice_id' => $purchaseInvoice->id,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $storeAccount->id,
                'description' => 'استلام بضاعة فاتورة # '.$purchaseInvoice->code,
                'debit' => $totalAmount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => AccountingSettings::id('purchases'),
                'description' => 'إقفال مشتريات في المخزن فاتورة # '.$purchaseInvoice->code,
                'debit' => 0,
                'credit' => $totalAmount,
                'is_debit' => false,
            ]);

            $storeAccount->balance += $totalAmount;
            $storeAccount->save();

            $purchasesAccount = AccountingSettings::account('purchases');
            if ($purchasesAccount) {
                $purchasesAccount->balance -= $totalAmount;
                $purchasesAccount->save();
                Account::updateBalancesHierarchically($purchasesAccount->id);
            }

            Account::updateBalancesHierarchically($storeAccount->id);
        } catch (\Throwable $e) {
            Log::error('خطأ في إنشاء قيد استلام المخزن (استلام مشتريات): '.$e->getMessage());
        }
    }
}
