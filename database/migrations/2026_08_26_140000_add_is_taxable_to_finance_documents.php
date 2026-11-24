<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * علامةُ «سندٌ ضريبي» على سندات الصرف والقبض والقيود.
 *
 * الضريبةُ كانت مبالغَ متفرّقة في الأعمدة (`tax1_amount`) أو سطوراً في
 * تفاصيل القيد، ولا حقلَ واحداً يقول: **هذا المستند ضريبيّ**. فمن أراد
 * إخراجَ المستندات الضريبية وحدَها لزمه أن يجمع شروطاً مختلفةً في كل
 * جدول، ويعيدها في كل تقرير.
 *
 * والعمودُ يُملأ تلقائياً عند الحفظ متى اختِيرت ضريبة، فلا يعتمد على أن
 * يتذكّر المستخدمُ تأشيرَ خانة.
 *
 * ويُفهرَس لأن كل استعمالٍ له تصفية: «أرِني الضريبية فقط».
 */
return new class extends Migration
{
    private const TABLES = ['expenses', 'receipts', 'journal_entries'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'is_taxable')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_taxable')
                    ->default(false)
                    ->comment('سندٌ ضريبي — يُضبط تلقائياً عند اختيار ضريبة');
                $t->index('is_taxable');
            });
        }

        $this->backfillVouchers();
        $this->backfillJournalEntries();
    }

    /**
     * وسمُ السندات القائمة.
     *
     * بدونه تبدو كلُّ السجلّات السابقة غيرَ ضريبية، فيخرج أوّلُ تقريرٍ
     * مصفّىً بالضريبة فارغاً وفيه ما يملؤه.
     */
    private function backfillVouchers(): void
    {
        foreach (['expenses', 'receipts'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_taxable')) {
                continue;
            }

            DB::table($table)
                ->where(function ($q) {
                    // ضريبةٌ مختارة أو مبلغُ ضريبةٍ مسجَّل — أيُّهما كان
                    $q->where('tax1', '>', 0)
                        ->orWhere('tax2', '>', 0)
                        ->orWhere('tax1_amount', '>', 0)
                        ->orWhere('tax2_amount', '>', 0);
                })
                ->update(['is_taxable' => true]);
        }
    }

    /**
     * القيدُ ضريبيٌّ إن مسّ أحدَ حسابَي ضريبة القيمة المضافة.
     *
     * الحسابان يُقرآن من «الحسابات المحاسبية» لا من ثابتٍ مكتوب، فمن أعاد
     * ترتيبَ شجرته يبقى الوسمُ صحيحاً عنده.
     */
    private function backfillJournalEntries(): void
    {
        if (! Schema::hasTable('journal_entries')
            || ! Schema::hasColumn('journal_entries', 'is_taxable')
            || ! Schema::hasTable('journal_entry_details')) {
            return;
        }

        $vatAccounts = array_values(array_filter([
            $this->settingId('vat_paid'),
            $this->settingId('vat_payable'),
        ]));

        if ($vatAccounts === []) {
            return;
        }

        $ids = DB::table('journal_entry_details')
            ->whereIn('account_id', $vatAccounts)
            ->distinct()
            ->pluck('journal_entry_id');

        // على دفعات: قيدٌ واحدٌ لآلاف المعرّفات يتجاوز حدَّ الاستعلام
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('journal_entries')
                ->whereIn('id', $chunk->all())
                ->update(['is_taxable' => true]);
        }
    }

    /** معرّفُ حسابٍ من إعدادات الحسابات — أو `null` إن لم يُضبط. */
    private function settingId(string $key): ?int
    {
        if (! Schema::hasTable('accounting_settings')) {
            return null;
        }

        // العمودُ `account_id` لا `value`: الجدولُ يربط مفتاحاً بحسابٍ لا بقيمةٍ نصّية
        $value = DB::table('accounting_settings')
            ->where('key', $key)
            ->where('is_active', true)
            ->value('account_id');

        return $value ? (int) $value : null;
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_taxable')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_is_taxable_index');
                $t->dropColumn('is_taxable');
            });
        }
    }
};
