<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ضريبة التقسيط في حسابها — لا في «ضريبة المبيعات».
 *
 * ضريبةُ ربح المرابحة والرسوم الإدارية كانت تُقيَّد على `vat_payable`
 * (2144) مع ضريبة بنود الفواتير، فلا يُفصَل ما استُحقّ على التمويل عمّا
 * استُحقّ على البضاعة إلا بجردٍ يدويّ. صار لها حسابٌ تحت «القيمة المضافة
 * المطلوبة» (214) — كما فُصلت ضريبة الشحن في 2145 — ومفتاحٌ في
 * «الحسابات المحاسبية» `installment_vat_payable`.
 *
 * الحساب بالكود والمعرّف تلقائيّ — انظر `add_late_fee_accounts`.
 */
return new class extends Migration
{
    private const PARENT_CODE = '214';

    private const CODE = '2146';

    private const KEY = 'installment_vat_payable';

    public function up(): void
    {
        $parentId = DB::table('accounts')->where('code', self::PARENT_CODE)->value('id');
        $accountId = DB::table('accounts')->where('code', self::CODE)->value('id');

        if (! $accountId && $parentId) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'ضريبة التقسيط',
                'code' => self::CODE,
                'parent_id' => $parentId,
                'type' => 'sub',
                'account_type' => 'liabilities',
                'balance_type' => 'credit',
                'balance' => 0.0,
                'opening_balance' => 0.0,
                'opening_balance_type' => 'credit',
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $setting = DB::table('accounting_settings')->where('key', self::KEY)->first();

        if (! $setting) {
            DB::table('accounting_settings')->insert([
                'key' => self::KEY,
                'name' => 'ضريبة التقسيط',
                'description' => 'ضريبة ربح المرابحة والرسوم الإدارية — تُفصَل عن ضريبة بنود الفواتير في القيد وفي الإقرار',
                'group' => 'الضرائب',
                'account_id' => $accountId ?: null,
                'account_code' => $accountId ? self::CODE : null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (! $setting->account_id && $accountId) {
            DB::table('accounting_settings')->where('key', self::KEY)->update([
                'account_id' => $accountId,
                'account_code' => self::CODE,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('accounts')->where('code', self::CODE)->value('id');

        DB::table('accounting_settings')->where('key', self::KEY)->delete();

        // لا يُحذف حسابٌ تحرّك عليه قيد
        if ($id && ! DB::table('journal_entry_details')->where('account_id', $id)->exists()) {
            DB::table('accounts')->where('id', $id)->delete();
        }
    }
};
