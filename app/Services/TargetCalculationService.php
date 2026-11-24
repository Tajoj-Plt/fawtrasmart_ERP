<?php

namespace App\Services;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingSettings;
use Carbon\Carbon;

/**
 * خدمة مركزية لاحتساب أداء الموظف:
 *   - صافي المبيعات (للأهداف من نوع sales).
 *   - التحصيلات النقدية الفعلية (للأهداف من نوع collections).
 *
 * تُستخدم في:
 *   - SalesTargetController / CollectionsTargetController (تقرير + تسوية يدوية)
 *   - ProcessTargetSettlements cron (تسوية تلقائية)
 */
class TargetCalculationService
{
    /**
     * مبيعات الموظف خلال فترة، حسب طريقة الاحتساب المضبوطة في «إعدادات الأهداف».
     *
     * يحتسب على أساس الإنشاء (الفاتورة الآجلة تدخل فوراً).
     *
     * طريقة الاحتساب (`calculation_method` في `target_settings`) — والتعريف
     * هنا هو نصّ التسمية في الشاشة حرفياً، لا تفسيراً لها:
     *
     *   - `net_sales`   → صافي المبيعات: فواتير − مرتجعات − إشعارات دائنة
     *   - `gross_sales` → إجمالي الفواتير فقط، بلا خصم المرتجع ولا الإشعار
     *   - `paid_only`   → الفواتير المدفوعة بالكامل فقط (`payment_status = 1`)
     *
     * **كانت الطرق الثلاث واحدةً فعلياً**: الدالّة لم تكن تستقبل الطريقة
     * أصلاً، ولا أحدٌ من مستدعيها يمرّرها — لا التسوية اليدوية ولا التقرير
     * ولا `ProcessTargetSettlements`. فمن يختار «إجمالي الفواتير فقط» أو
     * «الفواتير المدفوعة فقط» في الشاشة كان يُحتسب له الصافي كما هو.
     * (نظيرتها `getEmployeeCollections()` كانت تستقبلها وتعمل بها منذ البداية.)
     *
     * @param  int  $userId  users.id
     * @param  string|Carbon  $startDate
     * @param  string|Carbon  $endDate
     * @param  string  $method  net_sales | gross_sales | paid_only
     * @param  bool  $detailed  إن true يرجع breakdown
     * @return float|array
     */
    public function getEmployeeNetSales($userId, $startDate, $endDate, string $method = 'net_sales', bool $detailed = false)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        /*
            التاريخ هنا `journal_entries.date` — وهو يُضبط بـ`now()` وقت ترحيل
            الفاتورة، لا بحقل `invoice_date` القابل للتعديل. فاتورةٌ تُوثَّق
            بتاريخٍ سابق (توثيق متأخر) تُحتسب على فترة إنشائها الفعلية، تماماً
            كبقية التقارير المبنية على القيود في هذا الكود
            (`SalesByEmployeeController`, `CustomerTargetService`).
        */
        $salesAccountId = AccountingSettings::id('sales');
        $salesReturnsAccountId = AccountingSettings::id('sales_returns');

        $invoicesTotal = 0.0;
        $paidTotal = 0.0;
        $returnsTotal = 0.0;
        $creditNotesTotal = 0.0;

        if ($salesAccountId) {
            $salesLines = fn () => JournalEntryDetail::where('account_id', $salesAccountId)
                ->whereHas('journalEntry', function ($q) use ($userId, $start, $end) {
                    $q->where('created_by_employee', $userId)
                        ->where('status', 1)
                        ->where('reference_type', ReferenceType::SALES_INVOICE->value)
                        ->whereBetween('date', [$start, $end]);
                });

            $row = $salesLines()->selectRaw('SUM(credit) as c, SUM(debit) as d')->first();
            $invoicesTotal = (float) ($row->c ?? 0) - (float) ($row->d ?? 0);

            // `payment_status = 1` مدفوعة بالكامل — عبر الفاتورة المرتبطة بالقيد
            $paidRow = $salesLines()
                ->whereHas('journalEntry.invoice', fn ($q) => $q->where('payment_status', 1))
                ->selectRaw('SUM(credit) as c, SUM(debit) as d')->first();
            $paidTotal = (float) ($paidRow->c ?? 0) - (float) ($paidRow->d ?? 0);
        }

        if ($salesReturnsAccountId) {
            $returnsLines = fn (string $refType) => JournalEntryDetail::where('account_id', $salesReturnsAccountId)
                ->whereHas('journalEntry', function ($q) use ($userId, $start, $end, $refType) {
                    $q->where('created_by_employee', $userId)
                        ->where('status', 1)
                        ->where('reference_type', $refType)
                        ->whereBetween('date', [$start, $end]);
                });

            $r = $returnsLines(ReferenceType::SALES_RETURN->value)->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
            $returnsTotal = (float) ($r->d ?? 0) - (float) ($r->c ?? 0);

            $c = $returnsLines(ReferenceType::CREDIT_NOTE->value)->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
            $creditNotesTotal = (float) ($c->d ?? 0) - (float) ($c->c ?? 0);
        }

        $net = max(0, $invoicesTotal - $returnsTotal - $creditNotesTotal);

        $achieved = match ($method) {
            'gross_sales' => $invoicesTotal,
            'paid_only' => $paidTotal,
            default => $net,
        };

        if ($detailed) {
            return [
                'method' => $method,
                'gross_sales' => $invoicesTotal,
                'paid_sales' => $paidTotal,
                'returns' => $returnsTotal,
                'credit_notes' => $creditNotesTotal,
                'net_sales' => $net,
                // ما يُحتسب فعلاً مقابل الهدف بالطريقة المختارة
                'achieved' => $achieved,
            ];
        }

        return $achieved;
    }

    /**
     * التحصيلات الفعلية للموظف خلال فترة، من القيود المحاسبية.
     *
     * القيدُ المؤهَّل: أنشأه الموظف (`created_by_employee`)، معتمَد
     * (`status = 1`)، ضمن الفترة، من نوع `CUSTOMER_COLLECTION` وحده، يحمل
     * تفصيلاً مديناً في حساب خزينة/بنك — وبلا تفصيلٍ دائنٍ في حساب خزينة/بنك
     * على نفس القيد، لاستبعاد **التحويلات الداخلية** بين خزينتين (لا تُعدّ
     * تحصيلاً). نفس فكرة `EmployeeTargetController::getEmployeeCollectionsFromJournal()`،
     * لكن بإضافة فلتر `reference_type`: بلا هذا الفلتر، أي قيدٍ آخر يرحّله
     * نفس المستخدم ويلمس حساب خزينة/بنك بالمدين — كتحصيل مرتجع مشتريات من
     * مورّد (`reference_type = PAY`) — كان يُحتسب خطأً كتحصيلٍ من عميل.
     * ثبت هذا فعلياً على بيانات حقيقية قبل إضافة الفلتر.
     *
     * كل نقاط ترحيل قيد التحصيل (دفعة على فاتورة، دفعة مقدَّمة عند إنشاء
     * الفاتورة، سند قبض عام) تضبط `invoice_id` على القيد صراحةً — فالتفريق
     * بين «مدفوعات فواتير» و«سندات قبض» في التفصيل مصدره القيد نفسه:
     * `invoice_id` موجود ⇐ دفعة فاتورة، و`null` ⇐ سند قبض غير مرتبط بفاتورة.
     *
     * طريقة الاحتساب (calculation_method) — تُقيّد أي الحسابات تُحسب،
     * لا أيّ القيود تُستبعَد كتحويل (ذلك يشمل كل الخزائن والبنوك دائماً):
     *   - total_collected → كل المدفوعات (نقدية + بنكية)
     *   - cash_only → فقط ما تم تحصيله في خزينة نقدية (parent_id=13 وتفرعاته)
     *   - bank_only → فقط ما تم تحصيله في حساب بنكي (parent_id=15 وتفرعاته)
     *
     * @param  int  $userId  users.id
     * @param  string|Carbon  $startDate
     * @param  string|Carbon  $endDate
     * @param  string  $method  total_collected | cash_only | bank_only
     * @param  bool  $detailed  إن true يرجع breakdown
     * @return float|array
     */
    public function getEmployeeCollections($userId, $startDate, $endDate, string $method = 'total_collected', bool $detailed = false)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $allTreasuryIds = array_unique(array_merge(
            $this->descendantsOf(AccountingSettings::id('cash_treasuries_parent')),
            $this->descendantsOf(AccountingSettings::id('bank_accounts_parent'))
        ));

        if (empty($allTreasuryIds)) {
            return $detailed ? ['invoice_payments' => 0.0, 'receipts' => 0.0, 'total_collected' => 0.0] : 0.0;
        }

        $countedIds = $this->resolveTreasuryAccountIds($method) ?? $allTreasuryIds;

        $entries = JournalEntry::query()
            ->where('created_by_employee', $userId)
            ->where('status', 1)
            ->where('reference_type', ReferenceType::CUSTOMER_COLLECTION->value)
            ->whereBetween('date', [$start, $end])
            ->whereHas('details', fn ($q) => $q->whereIn('account_id', $allTreasuryIds)->where('debit', '>', 0))
            ->whereDoesntHave('details', fn ($q) => $q->whereIn('account_id', $allTreasuryIds)->where('credit', '>', 0))
            ->get(['id', 'invoice_id']);

        if ($entries->isEmpty()) {
            return $detailed ? ['invoice_payments' => 0.0, 'receipts' => 0.0, 'total_collected' => 0.0] : 0.0;
        }

        $sumFor = fn ($entryIds) => (float) JournalEntryDetail::whereIn('journal_entry_id', $entryIds)
            ->whereIn('account_id', $countedIds)
            ->where('debit', '>', 0)
            ->sum('debit');

        $total = $sumFor($entries->pluck('id'));

        if ($detailed) {
            $invoicePayments = $sumFor($entries->whereNotNull('invoice_id')->pluck('id'));
            $receiptsTotal = $sumFor($entries->whereNull('invoice_id')->pluck('id'));

            return [
                'invoice_payments' => $invoicePayments,  // قيود مرتبطة بفاتورة
                'receipts' => $receiptsTotal,    // سندات قبض غير مرتبطة بفاتورة
                'total_collected' => $total,
            ];
        }

        return $total;
    }

    /**
     * يُحدد قائمة معرفات الحسابات حسب طريقة الاحتساب:
     *   - total_collected → null (لا فلتر، أي خزينة/بنك)
     *   - cash_only       → IDs الحسابات تحت parent=13 (الخزائن النقدية)
     *   - bank_only       → IDs الحسابات تحت parent=15 (البنوك)
     */
    private function resolveTreasuryAccountIds(string $method): ?array
    {
        return match ($method) {
            'cash_only' => $this->descendantsOf(AccountingSettings::id('cash_treasuries_parent')),
            'bank_only' => $this->descendantsOf(AccountingSettings::id('bank_accounts_parent')),
            default => null,
        };
    }

    /**
     * جلب كل أحفاد حساب (بأي عمق) — لدعم وجود مستويات فرعية تحت parent.
     *
     * `null` تعني «الحساب غير مربوط في إعدادات الحسابات المحاسبية»، فتعود
     * القائمة فارغةً ولا يُحتسب على حسابٍ مخمَّن: كانت أرقام الأبوين
     * مكتوبةً في `SystemAccountIds` (١٣ و١٥)، وهي أرقام هذه القاعدة وحدها.
     */
    private function descendantsOf(?int $parentId): array
    {
        if (! $parentId) {
            return [];
        }

        $ids = [$parentId];
        $stack = [$parentId];
        while (! empty($stack)) {
            $children = Account::whereIn('parent_id', $stack)->pluck('id')->toArray();
            if (empty($children)) {
                break;
            }
            $ids = array_merge($ids, $children);
            $stack = $children;
        }

        return array_unique($ids);
    }
}
