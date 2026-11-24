<?php

use App\Constants\SystemAccountIds;
use App\Models\JournalEntryDetail;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
    قيودُ سندات صرفٍ بلا سطر ضريبتها.

    **ما كان**

    سندُ صرفٍ ضريبيّ بلا حساب ضريبةٍ مختار كان يُحفظ قيدُه بسطرين: الخزينةُ
    دائنةٌ بالإجمالي (شاملاً الضريبة) والمصروفُ مدينٌ بالأساس، وسطرُ الضريبة
    يُسقَط صامتاً. فيخرج القيدُ غيرَ متوازنٍ بمقدار الضريبة (14 قيداً في يونيو
    وما بعده). الحفظُ اليوم يلتمس «ضريبة المشتريات المدفوعة» أو يرفض السند،
    فلا ينشأ مثلُها — وهذه الهجرةُ تُصلح ما نشأ قبل ذلك.

    **الإصلاح**

    يُضاف سطرُ الضريبة المفقود مديناً — على حساب الضريبة المختار في السند، وإلا
    `vat_paid` — **فقط** حين يساوي فرقُ القيد ضريبةَ السند المحفوظة تماماً. وما
    لا يطابق يُترك ويُسجَّل: قيدٌ مختلٌّ لسببٍ آخر لا يُصلَح بتخمين.

    السطرُ يُنشأ بالمودل ليُحدَّث رصيدُ الحساب وآبائه (`JournalEntryDetail::created`).
    ويظهر ذلك القيدُ سبباً يمنع الإقفال السنوي ما بقي مختلّاً.
*/
return new class extends Migration
{
    public function up(): void
    {
        $vatPaid = AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID);

        $unbalanced = DB::table('journal_entries as j')
            ->join('journal_entry_details as d', 'd.journal_entry_id', '=', 'j.id')
            ->where('j.reference_type', 'EXP')
            ->groupBy('j.id', 'j.reference_number')
            ->havingRaw('ABS(COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0)) > 0.01')
            ->selectRaw('j.id, j.reference_number, COALESCE(SUM(d.credit), 0) - COALESCE(SUM(d.debit), 0) as missing_debit')
            ->get();

        foreach ($unbalanced as $entry) {
            $expense = DB::table('expenses')->where('code', $entry->reference_number)
                ->first(['id', 'tax1_amount', 'tax2_amount', 'tax1_account_id', 'tax2_account_id']);

            $tax = $expense ? round((float) $expense->tax1_amount + (float) $expense->tax2_amount, 2) : null;
            $missing = round((float) $entry->missing_debit, 2);
            $account = $expense ? ($expense->tax1_account_id ?: $expense->tax2_account_id ?: $vatPaid) : null;

            if ($tax === null || $missing <= 0 || abs($tax - $missing) > 0.01 || ! $account || ! DB::table('accounts')->where('id', $account)->exists()) {
                Log::warning('repair_expense_entries_missing_tax_line: skipped', [
                    'journal_entry_id' => $entry->id,
                    'reference' => $entry->reference_number,
                    'missing_debit' => $missing,
                    'expense_tax' => $tax,
                ]);

                continue;
            }

            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account,
                'description' => 'ضريبة القيمة المضافة — سطرٌ مفقود أُضيف بهجرة إصلاح',
                'debit' => $missing,
                'credit' => 0,
                'is_debit' => true,
            ]);

            Log::info('repair_expense_entries_missing_tax_line: repaired', [
                'journal_entry_id' => $entry->id,
                'reference' => $entry->reference_number,
                'tax_account_id' => $account,
                'amount' => $missing,
            ]);
        }
    }

    public function down(): void
    {
        // سطورُ الضريبة المضافة تُعرف بوصفها — حذفُها يُعيد القيود مختلّةً كما كانت
        JournalEntryDetail::where('description', 'ضريبة القيمة المضافة — سطرٌ مفقود أُضيف بهجرة إصلاح')
            ->get()
            ->each->delete();
    }
};
