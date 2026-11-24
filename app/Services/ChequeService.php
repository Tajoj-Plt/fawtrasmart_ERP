<?php

namespace App\Services;

use App\Models\ReceivedCheque;
use App\Models\PayableCheque;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Models\Invoice;
use App\Enums\ChequeStatus;
use App\Enums\ReferenceType;
use App\Constants\SystemAccountIds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة إدارة الشيكات والقيود المحاسبية
 * Cheque Management Service with Journal Entries
 */
class ChequeService
{
    // ═══════════════════════════════════════════════════════════════════════════
    //                        الشيكات المستلمة (Received Cheques)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * استلام شيك جديد مع إنشاء القيد المحاسبي
     * قيد: من ح/ شيكات تحت التحصيل - إلى ح/ العميل
     * 
     * @param array $data بيانات الشيك
     * @return ReceivedCheque
     */
    public function receiveCheck(array $data): ReceivedCheque
    {
        return DB::transaction(function () use ($data) {
            // تحديد حساب شيكات تحت التحصيل (الشيك في حوزتنا لكن لم يودع بالبنك بعد)
            $chequeAccountId = $data['collection_account_id'] ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION;
            
            // إنشاء الشيك
            $cheque = ReceivedCheque::create([
                'amount' => $data['amount'],
                'cheque_number' => $data['cheque_number'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'endorsement' => $data['endorsement'] ?? 0,
                'collection_account_id' => $chequeAccountId,
                'recipient_account_id' => $data['recipient_account_id'], // حساب العميل
                'client_id' => $data['client_id'] ?? null,
                'payee_name' => $data['payee_name'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'attachment' => $data['attachment'] ?? null,
                'drawer_bank' => $data['drawer_bank'] ?? null,
                'drawer_branch' => $data['drawer_branch'] ?? null,
                // بنك الإيداع المتوقَّع — تسجيلٌ فقط، بلا أثرٍ محاسبي؛ الشيك
                // لم يُودَع فعلياً بعد. القيد الحقيقي (مدين شيكات برسم
                // التحصيل) لا يُكتب إلا في `depositForCollection()`
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'status' => ChequeStatus::PENDING,
                'created_by' => auth()->id(),
            ]);

            // إنشاء قيد الاستلام
            $journalEntry = $this->createReceivedChequeJournalEntry($cheque);
            
            // ربط القيد بالشيك
            $cheque->update(['journal_entry_id' => $journalEntry->id]);

            return $cheque;
        });
    }

    /**
     * إنشاء قيد استلام الشيك
     * من ح/ شيكات تحت التحصيل (الشيك في حوزتنا)
     * إلى ح/ العميل (تخفيض مديونية العميل)
     */
    private function createReceivedChequeJournalEntry(ReceivedCheque $cheque): JournalEntry
    {
        // جلب اسم حساب العميل/المستلم منه
        $recipientAccount = Account::find($cheque->recipient_account_id);
        $recipientName = $recipientAccount->name ?? $cheque->payee_name ?? 'غير محدد';
        
        // جلب اسم حساب شيكات تحت التحصيل
        $collectionAccount = Account::find($cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION);
        $collectionAccountName = $collectionAccount->name ?? 'شيكات تحت التحصيل';

        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE),
            'date' => $cheque->issue_date ?? now(),
            'description' => "استلام شيك رقم {$cheque->cheque_number} بمبلغ " . number_format($cheque->amount, 2) . " من: {$recipientName}" . ($cheque->drawer_bank ? " - بنك الساحب: {$cheque->drawer_bank}" : ''),
            'status' => 1, // معتمد
            'reference_type' => ReferenceType::RECEIVED_CHEQUE,
            'client_id' => $cheque->client_id,
            'created_by_employee' => auth()->id(),
            'is_posted' => 1,
        ]);

        // مدين: شيكات تحت التحصيل (الشيك في حوزتنا)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION,
            'debit' => $cheque->amount,
            'credit' => 0,
            'description' => "شيك مستلم رقم {$cheque->cheque_number} - إلى: {$collectionAccountName}",
        ]);

        // دائن: حساب العميل
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cheque->recipient_account_id,
            'debit' => 0,
            'credit' => $cheque->amount,
            'description' => "شيك مستلم رقم {$cheque->cheque_number} - من: {$recipientName}",
        ]);

        return $journalEntry;
    }

    /**
     * إيداع الشيك للتحصيل في البنك
     * قيد: من ح/ شيكات برسم التحصيل - إلى ح/ شيكات تحت التحصيل
     * (نقل الشيك من حوزتنا إلى البنك للتحصيل)
     * 
     * @param ReceivedCheque $cheque
     * @param int $bankAccountId حساب البنك
     * @return ReceivedCheque
     */
    public function depositForCollection(ReceivedCheque $cheque, int $bankAccountId): ReceivedCheque
    {
        if (!$cheque->canDeposit()) {
            throw new \Exception('لا يمكن إيداع هذا الشيك للتحصيل');
        }

        return DB::transaction(function () use ($cheque, $bankAccountId) {
            // إنشاء قيد الإيداع
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE),
                'date' => now(),
                'description' => "إيداع شيك رقم {$cheque->cheque_number} للتحصيل",
                'status' => 1,
                'reference_type' => ReferenceType::RECEIVED_CHEQUE,
                'client_id' => $cheque->client_id,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // مدين: شيكات برسم التحصيل (الشيك أصبح في البنك للتحصيل)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_FOR_COLLECTION,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "إيداع شيك رقم {$cheque->cheque_number} للتحصيل",
            ]);

            // دائن: شيكات تحت التحصيل (إخراج الشيك من حوزتنا)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "إيداع شيك رقم {$cheque->cheque_number} للتحصيل",
            ]);

            // تحديث الشيك مع حفظ معرف القيد
            $cheque->update([
                'status' => ChequeStatus::UNDER_COLLECTION,
                'bank_account_id' => $bankAccountId,
                'collection_journal_entry_id' => $journalEntry->id,
            ]);

            return $cheque;
        });
    }

    /**
     * تحصيل الشيك
     * قيد: من ح/ البنك - إلى ح/ شيكات تحت التحصيل
     * 
     * @param ReceivedCheque $cheque
     * @param array $invoiceIds الفواتير المراد تسديدها (اختياري)
     * @return ReceivedCheque
     */
    public function collectCheque(ReceivedCheque $cheque, array $invoiceIds = []): ReceivedCheque
    {
        if (!$cheque->canCollect()) {
            throw new \Exception('لا يمكن تحصيل هذا الشيك');
        }

        return DB::transaction(function () use ($cheque, $invoiceIds) {
            /*
                الحسابان كانا مبدَّلين: شيكٌ حالته UNDER_COLLECTION مرّ فعلاً
                بـ`depositForCollection()` التي نقلته من «تحت التحصيل» إلى
                «برسم التحصيل» (مدين برسم / دائن تحت) — فرصيده الآن في
                «برسم التحصيل» حصراً. تحصيلٌ يُدين البنك ويُدين «تحت
                التحصيل» -- الحساب الذي أُفرغ بالفعل من رصيده أول مرة -- كان
                يترك «برسم التحصيل» عالقاً برصيدٍ لن يُصفَّر أبداً، و«تحت
                التحصيل» سالباً بنفس القيمة، رغم أن الشيك حُصِّل فعلاً.
                تحقّقتُ بتشغيل الدورة كاملةً (استلام←إيداع←تحصيل) على نسخة
                تجريبية: قبل الإصلاح ينتهي «برسم التحصيل» عند القيمة نفسها
                بدل الصفر. من لم يُودَع أصلاً (تحصيلٌ مباشر من حالة معلَّق)
                ما زال في «تحت التحصيل» — حيث وضعه `receiveCheck()` أصلاً.
            */
            $sourceAccountId = $cheque->status === ChequeStatus::UNDER_COLLECTION
                ? SystemAccountIds::CHEQUES_FOR_COLLECTION
                : ($cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION);

            // إنشاء قيد التحصيل
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE_COLLECT),
                'date' => now(),
                'description' => "تحصيل شيك رقم {$cheque->cheque_number}",
                'status' => 1,
                'reference_type' => ReferenceType::RECEIVED_CHEQUE_COLLECT,
                'client_id' => $cheque->client_id,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // مدين: حساب البنك
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->bank_account_id ?? SystemAccountIds::BANK_ACCOUNTS_PARENT,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "تحصيل شيك رقم {$cheque->cheque_number}",
            ]);

            // دائن: شيكات تحت التحصيل أو برسم التحصيل
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $sourceAccountId,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "تحصيل شيك رقم {$cheque->cheque_number}",
            ]);

            // تسديد الفواتير إن وجدت
            if (!empty($invoiceIds) && $cheque->client_id) {
                $this->applyPaymentToInvoices($cheque->client_id, $cheque->amount, $invoiceIds, $journalEntry->id);
            }

            // تحديث الشيك
            $cheque->update([
                'status' => ChequeStatus::COLLECTED,
                'collection_journal_entry_id' => $journalEntry->id,
                'collected_at' => now(),
            ]);

            return $cheque;
        });
    }

    /**
     * رفض الشيك
     * قيد: من ح/ العميل - إلى ح/ شيكات تحت التحصيل/برسم التحصيل
     * 
     * @param ReceivedCheque $cheque
     * @param string|null $reason سبب الرفض
     * @return ReceivedCheque
     */
    public function rejectCheque(ReceivedCheque $cheque, ?string $reason = null): ReceivedCheque
    {
        if (!$cheque->canReject()) {
            throw new \Exception('لا يمكن رفض هذا الشيك');
        }

        return DB::transaction(function () use ($cheque, $reason) {
            // تحديد الحساب المصدر — نفس تصحيح `collectCheque()`: UNDER_COLLECTION
            // يعني الشيك في «برسم التحصيل» بعد الإيداع، لا «تحت التحصيل»
            $sourceAccountId = $cheque->status === ChequeStatus::UNDER_COLLECTION
                ? SystemAccountIds::CHEQUES_FOR_COLLECTION
                : ($cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION);

            // إنشاء قيد الرفض
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE_REJECT),
                'date' => now(),
                'description' => "رفض شيك رقم {$cheque->cheque_number}" . ($reason ? " - السبب: {$reason}" : ''),
                'status' => 1,
                'reference_type' => ReferenceType::RECEIVED_CHEQUE_REJECT,
                'client_id' => $cheque->client_id,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // مدين: حساب العميل (إعادة المديونية)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->recipient_account_id,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "رفض شيك رقم {$cheque->cheque_number}",
            ]);

            // دائن: شيكات مرتجعة
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_RETURNED,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "رفض شيك رقم {$cheque->cheque_number}",
            ]);

            // نقل من شيكات تحت التحصيل إلى مرتجعة
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_RETURNED,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "نقل إلى شيكات مرتجعة",
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $sourceAccountId,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "نقل من شيكات تحت التحصيل",
            ]);

            // تحديث الشيك
            $cheque->update([
                'status' => ChequeStatus::REJECTED,
                'rejected_at' => now(),
                'description' => $cheque->description . ($reason ? "\nسبب الرفض: {$reason}" : ''),
            ]);

            return $cheque;
        });
    }

    /**
     * تظهير الشيك لطرف ثالث
     * قيد: من ح/ المورد/الدائن - إلى ح/ شيكات تحت التحصيل
     * (نقل الشيك للمورد لتسديد مديونيتنا له)
     * 
     * @param ReceivedCheque $cheque
     * @param int $endorsedToAccountId حساب المظهر إليه
     * @return ReceivedCheque
     */
    public function endorseCheque(ReceivedCheque $cheque, int $endorsedToAccountId): ReceivedCheque
    {
        if (!$cheque->canEndorse()) {
            throw new \Exception('لا يمكن تظهير هذا الشيك');
        }

        return DB::transaction(function () use ($cheque, $endorsedToAccountId) {
            // إنشاء قيد التظهير
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE_ENDORSE),
                'date' => now(),
                'description' => "تظهير شيك رقم {$cheque->cheque_number}",
                'status' => 1,
                'reference_type' => ReferenceType::RECEIVED_CHEQUE_ENDORSE,
                'client_id' => $cheque->client_id,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // مدين: حساب المورد/الدائن (المظهر إليه)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $endorsedToAccountId,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "تظهير شيك رقم {$cheque->cheque_number}",
            ]);

            // دائن: شيكات تحت التحصيل (إخراج الشيك من حوزتنا)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->collection_account_id ?? SystemAccountIds::CHEQUES_UNDER_COLLECTION,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "تظهير شيك رقم {$cheque->cheque_number}",
            ]);

            // تحديث الشيك مع حفظ معرف القيد
            $cheque->update([
                'status' => ChequeStatus::ENDORSED,
                'endorsed_at' => now(),
                'endorsed_to_account_id' => $endorsedToAccountId,
                'collection_journal_entry_id' => $journalEntry->id,
            ]);

            return $cheque;
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //                        الشيكات الصادرة (Payable Cheques)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * إصدار شيك جديد مع إنشاء القيد المحاسبي
     * قيد: من ح/ المورد/الدائن - إلى ح/ شيكات برسم الدفع
     * 
     * @param array $data بيانات الشيك
     * @return PayableCheque
     */
    public function issueCheque(array $data): PayableCheque
    {
        return DB::transaction(function () use ($data) {
            // إنشاء الشيك
            $cheque = PayableCheque::create([
                'amount' => $data['amount'],
                'cheque_number' => $data['cheque_number'],
                'bank_id' => $data['bank_id'],
                'cheque_book_id' => $data['cheque_book_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'recipient_account_id' => $data['recipient_account_id'], // حساب المورد
                'supplier_id' => $data['supplier_id'] ?? null,
                'payee_name' => $data['payee_name'] ?? null,
                'description' => $data['description'] ?? null,
                'attachment' => $data['attachment'] ?? null,
                'status' => ChequeStatus::ISSUED,
                'created_by' => auth()->id(),
            ]);

            // إنشاء قيد الإصدار
            $journalEntry = $this->createPayableChequeJournalEntry($cheque);
            
            // ربط القيد بالشيك
            $cheque->update(['journal_entry_id' => $journalEntry->id]);

            return $cheque;
        });
    }

    /**
     * إنشاء قيد إصدار الشيك
     * من ح/ المورد (الدائن)
     * إلى ح/ شيكات برسم الدفع
     */
    private function createPayableChequeJournalEntry(PayableCheque $cheque): JournalEntry
    {
        // جلب اسم الحساب المستفيد
        $recipientAccount = Account::find($cheque->recipient_account_id);
        $recipientName = $recipientAccount->name ?? $cheque->payee_name ?? 'غير محدد';
        
        // جلب اسم البنك
        $bankAccount = Account::find($cheque->bank_id);
        $bankName = $bankAccount->name ?? 'البنك';

        $journalEntry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::PAYABLE_CHEQUE),
            'date' => $cheque->issue_date ?? now(),
            'description' => "إصدار شيك رقم {$cheque->cheque_number} بمبلغ " . number_format($cheque->amount, 2) . " لحساب: {$recipientName} - من بنك: {$bankName}",
            'status' => 1,
            'reference_type' => ReferenceType::PAYABLE_CHEQUE,
            'created_by_employee' => auth()->id(),
            'is_posted' => 1,
        ]);

        // مدين: حساب المورد (تخفيض المديونية)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cheque->recipient_account_id,
            'debit' => $cheque->amount,
            'credit' => 0,
            'description' => "شيك صادر رقم {$cheque->cheque_number} لحساب: {$recipientName}",
        ]);

        // دائن: شيكات برسم الدفع
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => SystemAccountIds::CHEQUES_FOR_PAYMENT,
            'debit' => 0,
            'credit' => $cheque->amount,
            'description' => "شيك صادر رقم {$cheque->cheque_number} من بنك: {$bankName}",
        ]);

        return $journalEntry;
    }

    /**
     * صرف الشيك الصادر
     * قيد: من ح/ شيكات برسم الدفع - إلى ح/ البنك
     * 
     * @param PayableCheque $cheque
     * @return PayableCheque
     */
    public function cashPayableCheque(PayableCheque $cheque): PayableCheque
    {
        if (!$cheque->canCash()) {
            throw new \Exception('لا يمكن صرف هذا الشيك');
        }

        return DB::transaction(function () use ($cheque) {
            /*
                `$cheque->bank` **هو** حساب البنك المحدَّد (بنك الراجحي مثلاً) —
                لا حساباً له حساب. `->account_id` عمودٌ غير موجود أصلاً في
                جدول `accounts`، فكان يرجع null دائماً، فيسقط كل صرفٍ إلى
                الحساب الأب العام «البنك» بدل البنك الفعلي المسحوب عليه —
                فتختلط كل البنوك في حسابٍ واحد مهما اختار المستخدم.
            */
            $bankAccountId = $cheque->bank_id ?? SystemAccountIds::BANK_ACCOUNTS_PARENT;

            // إنشاء قيد الصرف
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::PAYABLE_CHEQUE_CASH),
                'date' => now(),
                'description' => "صرف شيك رقم {$cheque->cheque_number}",
                'status' => 1,
                'reference_type' => ReferenceType::PAYABLE_CHEQUE_CASH,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // مدين: شيكات برسم الدفع
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_FOR_PAYMENT,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "صرف شيك رقم {$cheque->cheque_number}",
            ]);

            // دائن: حساب البنك
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $bankAccountId,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "صرف شيك رقم {$cheque->cheque_number}",
            ]);

            // تحديث الشيك
            $cheque->update([
                'status' => ChequeStatus::CASHED,
                'cash_journal_entry_id' => $journalEntry->id,
                'cashed_at' => now(),
            ]);

            return $cheque;
        });
    }

    /**
     * رفض الشيك الصادر (إرجاعه من البنك)
     * عكس قيد الإصدار
     * 
     * @param PayableCheque $cheque
     * @param string|null $reason سبب الرفض
     * @return PayableCheque
     */
    public function rejectPayableCheque(PayableCheque $cheque, ?string $reason = null): PayableCheque
    {
        if (!$cheque->canReject()) {
            throw new \Exception('لا يمكن رفض هذا الشيك');
        }

        return DB::transaction(function () use ($cheque, $reason) {
            // إنشاء قيد عكسي
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::PAYABLE_CHEQUE_REJECT),
                'date' => now(),
                'description' => "رفض شيك رقم {$cheque->cheque_number}" . ($reason ? " - السبب: {$reason}" : ''),
                'status' => 1,
                'reference_type' => ReferenceType::PAYABLE_CHEQUE_REJECT,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // عكس القيد: مدين شيكات برسم الدفع
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_FOR_PAYMENT,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "إلغاء شيك رقم {$cheque->cheque_number}",
            ]);

            // عكس القيد: دائن حساب المورد
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->recipient_account_id,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "إلغاء شيك رقم {$cheque->cheque_number}",
            ]);

            // تحديث الشيك
            $cheque->update([
                'status' => ChequeStatus::REJECTED,
                'rejected_at' => now(),
                'description' => $cheque->description . ($reason ? "\nسبب الرفض: {$reason}" : ''),
            ]);

            return $cheque;
        });
    }

    /**
     * إلغاء الشيك الصادر (قبل الصرف)
     * عكس قيد الإصدار
     * 
     * @param PayableCheque $cheque
     * @param string|null $reason سبب الإلغاء
     * @return PayableCheque
     */
    public function cancelPayableCheque(PayableCheque $cheque, ?string $reason = null): PayableCheque
    {
        if (!$cheque->canCancel()) {
            throw new \Exception('لا يمكن إلغاء هذا الشيك');
        }

        return DB::transaction(function () use ($cheque, $reason) {
            // إنشاء قيد عكسي
            $journalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::PAYABLE_CHEQUE_REJECT),
                'date' => now(),
                'description' => "إلغاء شيك رقم {$cheque->cheque_number}" . ($reason ? " - السبب: {$reason}" : ''),
                'status' => 1,
                'reference_type' => ReferenceType::PAYABLE_CHEQUE_REJECT,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // عكس القيد
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => SystemAccountIds::CHEQUES_FOR_PAYMENT,
                'debit' => $cheque->amount,
                'credit' => 0,
                'description' => "إلغاء شيك رقم {$cheque->cheque_number}",
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $cheque->recipient_account_id,
                'debit' => 0,
                'credit' => $cheque->amount,
                'description' => "إلغاء شيك رقم {$cheque->cheque_number}",
            ]);

            // تحديث الشيك
            $cheque->update([
                'status' => ChequeStatus::CANCELLED,
                'cancelled_at' => now(),
                'description' => $cheque->description . ($reason ? "\nسبب الإلغاء: {$reason}" : ''),
            ]);

            return $cheque;
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //                        دوال مساعدة (Helper Methods)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * تطبيق الدفع على الفواتير
     */
    private function applyPaymentToInvoices(int $clientId, float $amount, array $invoiceIds, int $journalEntryId): void
    {
        $remainingAmount = $amount;

        foreach ($invoiceIds as $invoiceId) {
            if ($remainingAmount <= 0) break;

            $invoice = Invoice::find($invoiceId);
            if (!$invoice) continue;

            $dueAmount = $invoice->total - $invoice->paid;
            $paymentAmount = min($remainingAmount, $dueAmount);

            if ($paymentAmount > 0) {
                // تسجيل الدفعة
                PaymentsProcess::create([
                    'client_id' => $clientId,
                    'invoice_id' => $invoiceId,
                    'amount' => $paymentAmount,
                    'payment_type' => 'cheque',
                    'journal_entry_id' => $journalEntryId,
                    'payment_date' => now(),
                ]);

                // تحديث الفاتورة
                $invoice->increment('paid', $paymentAmount);
                
                if ($invoice->paid >= $invoice->total) {
                    $invoice->update(['payment_status' => 'paid']);
                } else {
                    $invoice->update(['payment_status' => 'partial']);
                }

                $remainingAmount -= $paymentAmount;
            }
        }
    }

    /**
     * جلب الشيكات المستحقة اليوم
     */
    public function getDueTodayCheques(): array
    {
        $received = ReceivedCheque::where('due_date', today())
            ->whereIn('status', [ChequeStatus::PENDING, ChequeStatus::UNDER_COLLECTION])
            ->get();

        $payable = PayableCheque::where('due_date', today())
            ->where('status', ChequeStatus::ISSUED)
            ->get();

        return [
            'received' => $received,
            'payable' => $payable,
        ];
    }

    /**
     * جلب الشيكات المتأخرة
     */
    public function getOverdueCheques(): array
    {
        $received = ReceivedCheque::where('due_date', '<', today())
            ->whereIn('status', [ChequeStatus::PENDING, ChequeStatus::UNDER_COLLECTION])
            ->get();

        $payable = PayableCheque::where('due_date', '<', today())
            ->where('status', ChequeStatus::ISSUED)
            ->get();

        return [
            'received' => $received,
            'payable' => $payable,
        ];
    }

    /**
     * ملخص الشيكات
     */
    public function getChequesSummary(): array
    {
        return [
            'received' => [
                'pending' => ReceivedCheque::pending()->sum('amount'),
                'under_collection' => ReceivedCheque::underCollection()->sum('amount'),
                'collected' => ReceivedCheque::collected()->sum('amount'),
                'rejected' => ReceivedCheque::rejected()->sum('amount'),
            ],
            'payable' => [
                'issued' => PayableCheque::issued()->sum('amount'),
                'cashed' => PayableCheque::cashed()->sum('amount'),
                'rejected' => PayableCheque::rejected()->sum('amount'),
                'cancelled' => PayableCheque::cancelled()->sum('amount'),
            ],
        ];
    }

    /**
     * إنشاء قيد عكسي لشيك صادر (عند الحذف)
     * 
     * @param PayableCheque $cheque
     * @param string $reason سبب القيد العكسي
     * @return JournalEntry|null
     */
    public function createReversalEntry(PayableCheque $cheque, string $reason = ''): ?JournalEntry
    {
        $originalEntry = $cheque->journalEntry;
        
        if (!$originalEntry) {
            return null;
        }

        return DB::transaction(function () use ($cheque, $originalEntry, $reason) {
            // إنشاء قيد عكسي
            $reversalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::PAYABLE_CHEQUE),
                'date' => now(),
                'description' => "قيد عكسي - {$reason} - شيك رقم {$cheque->cheque_number}",
                'status' => 1, // معتمد
                'reference_type' => ReferenceType::PAYABLE_CHEQUE,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // عكس جميع تفاصيل القيد الأصلي
            foreach ($originalEntry->details as $detail) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $detail->account_id,
                    'debit' => $detail->credit, // عكس: الدائن يصبح مدين
                    'credit' => $detail->debit, // عكس: المدين يصبح دائن
                    'description' => "قيد عكسي - {$detail->description}",
                ]);
            }

            Log::info("تم إنشاء قيد عكسي للشيك رقم {$cheque->cheque_number}", [
                'cheque_id' => $cheque->id,
                'original_entry_id' => $originalEntry->id,
                'reversal_entry_id' => $reversalEntry->id,
                'reason' => $reason,
            ]);

            return $reversalEntry;
        });
    }

    /**
     * إنشاء قيد عكسي لشيك مستلم (عند الحذف)
     * 
     * @param ReceivedCheque $cheque
     * @param string $reason سبب القيد العكسي
     * @return JournalEntry|null
     */
    public function createReceivedChequeReversalEntry(ReceivedCheque $cheque, string $reason = ''): ?JournalEntry
    {
        $originalEntry = $cheque->journalEntry;
        
        if (!$originalEntry) {
            return null;
        }

        return DB::transaction(function () use ($cheque, $originalEntry, $reason) {
            // إنشاء قيد عكسي
            $reversalEntry = JournalEntry::create([
                'reference_number' => JournalEntry::generateReferenceNumber(ReferenceType::RECEIVED_CHEQUE),
                'date' => now(),
                'description' => "قيد عكسي - {$reason} - شيك رقم {$cheque->cheque_number}",
                'status' => 1, // معتمد
                'reference_type' => ReferenceType::RECEIVED_CHEQUE,
                'client_id' => $cheque->client_id,
                'created_by_employee' => auth()->id(),
                'is_posted' => 1,
            ]);

            // عكس جميع تفاصيل القيد الأصلي
            foreach ($originalEntry->details as $detail) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $detail->account_id,
                    'debit' => $detail->credit, // عكس: الدائن يصبح مدين
                    'credit' => $detail->debit, // عكس: المدين يصبح دائن
                    'description' => "قيد عكسي - {$detail->description}",
                ]);
            }

            Log::info("تم إنشاء قيد عكسي للشيك المستلم رقم {$cheque->cheque_number}", [
                'cheque_id' => $cheque->id,
                'original_entry_id' => $originalEntry->id,
                'reversal_entry_id' => $reversalEntry->id,
                'reason' => $reason,
            ]);

            return $reversalEntry;
        });
    }
}
