<?php

use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حساب «خصميات الموظفين» (5310) وربطه بمفتاح payroll_deductions.
 *
 * كان المفتاح مزروعاً بلا حساب — فصرف راتب موظفٍ عليه خصم يفشل، وتبقى
 * الصرفة بأصفار لأن الأرقام تُكتب بعد نجاح الصرف فقط.
 *
 * يُنشئ الحساب تحت «الرواتب والأجور» (530) بالكود لا بمعرّف ثابت، ويربط
 * المفتاح إن كان فارغاً — ولا يمسّ اختيار المحاسب.
 *
 * ولا يُنشئ شيئاً متى وُجد الحسابُ سلفاً: مفتاحٌ مربوط، أو كود 5310، أو حسابٌ
 * بالاسم نفسه في المصروفات — فيُربط القائم بدل أن يولد له توأم.
 */
return new class extends Migration
{
    private const CODE = '5310';

    private const NAME = 'خصميات الموظفين';

    private const SETTING_KEY = 'payroll_deductions';

    private const PARENT_CODE = '530';

    public function up(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('accounting_settings')) {
            return;
        }

        // مفتاحٌ مربوطٌ بحسابٍ قائم: اختيارُ المحاسب — لا يُنشأ بجواره حسابٌ ثانٍ
        $linked = DB::table('accounting_settings')->where('key', self::SETTING_KEY)->value('account_id');
        if ($linked && DB::table('accounts')->where('id', $linked)->exists()) {
            return;
        }

        $accountId = $this->ensureAccount();

        if ($accountId) {
            $this->linkSetting($accountId);
            AccountingSettings::forget();
        }
    }

    /**
     * الحسابُ القائم أولاً — بالكود المزروع، ثمّ بالاسم نفسه في أيّ موضعٍ من
     * شجرة المصروفات (شجرةٌ مستوردةٌ بأكوادٍ غير قياسية تحمله بكودٍ آخر).
     * ولا يُنشأ جديدٌ إلا حين لا يوجد أيٌّ منهما — وإلا وُلد حسابان بغرضٍ واحد.
     */
    private function ensureAccount(): ?int
    {
        $existing = DB::table('accounts')->where('code', self::CODE)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $byName = DB::table('accounts as a')
            ->where('a.name', self::NAME)
            ->where('a.account_type', 'expenses')
            ->orderByDesc(DB::raw('(select count(*) from journal_entry_details d where d.account_id = a.id)'))
            ->orderBy('a.id')
            ->value('a.id');

        if ($byName) {
            return (int) $byName;
        }

        $parentId = DB::table('accounts')->where('code', self::PARENT_CODE)->value('id');

        if (! $parentId) {
            return null;
        }

        return (int) DB::table('accounts')->insertGetId([
            'name' => self::NAME,
            'code' => self::CODE,
            'parent_id' => $parentId,
            'type' => 'sub',
            'account_type' => 'expenses',
            'balance_type' => 'debit',
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => 'debit',
            'is_active' => true,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function linkSetting(int $accountId): void
    {
        $setting = DB::table('accounting_settings')->where('key', self::SETTING_KEY)->first();

        if (! $setting) {
            DB::table('accounting_settings')->insert([
                'key' => self::SETTING_KEY,
                'name' => self::NAME,
                'description' => 'الحساب المقابل لخصميات الغياب والجزاءات — يخفّض مصروف الرواتب ولا يُخفي إجماليه',
                'group' => 'الرواتب والموظفين',
                'account_id' => $accountId,
                'account_code' => DB::table('accounts')->where('id', $accountId)->value('code'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($setting->account_id) {
            return;
        }

        DB::table('accounting_settings')->where('key', self::SETTING_KEY)->update([
            'account_id' => $accountId,
            'account_code' => DB::table('accounts')->where('id', $accountId)->value('code'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('accounting_settings')) {
            return;
        }

        $id = DB::table('accounts')->where('code', self::CODE)->value('id');

        if (! $id) {
            return;
        }

        DB::table('accounting_settings')
            ->where('key', self::SETTING_KEY)
            ->where('account_id', $id)
            ->update([
                'account_id' => null,
                'account_code' => null,
                'updated_at' => now(),
            ]);

        $hasEntries = Schema::hasTable('journal_entry_details')
            && DB::table('journal_entry_details')->where('account_id', $id)->exists();

        if (! $hasEntries) {
            DB::table('accounts')->where('id', $id)->delete();
        }

        AccountingSettings::forget();
    }
};
