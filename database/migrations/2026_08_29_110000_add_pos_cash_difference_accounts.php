<?php

use App\Constants\SystemAccountIds;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حسابا فرق درج الكاشير — للشجرة القائمة.
 *
 * [AccountSeeder] يزرعهما في التنصيب الجديد، وهذا الترحيل يضعهما في
 * الشجرات القائمة التي مضى زرعُها — فالسيدر لا يُعاد تشغيله على قاعدةٍ
 * فيها بيانات.
 *
 * ═════════════════════ ولماذا حسابان؟ ═════════════════════
 *
 * الزيادةُ إيرادٌ عارض والعجزُ مصروف. ودمجُهما في حسابٍ واحد يُخفي حجمَ
 * كلٍّ منهما في ميزان المراجعة — وهو بالضبط ما يُراد قياسه: كاشيرٌ يزيد
 * درجُه ألفاً وينقص ألفاً ليس ككاشيرٍ لا يخطئ، ورصيدُهما الصافي في
 * الحالتين صفر.
 *
 * وهما غيرُ «عجز وزيادة الصندوق» (١٢٧): ذاك حسابُ أصولٍ لفروق الخزينة،
 * وهذان نتيجةٌ تُقفَل في قائمة الدخل.
 *
 * ═════════════════════ ويربط المفتاحين ═════════════════════
 *
 * `pos_cash_over` و`pos_cash_short` كانا مزروعين في `accounting_settings`
 * **بلا `account_id`** — مفتاحان في الشاشة ينتظران أن يختار لهما المحاسبُ
 * حساباً. يربطهما هذا الترحيل بالحسابين الجديدين، **ولا يمسّ ما ربطه
 * المحاسبُ بيده**: من وجّههما إلى حسابٍ آخر يبقى اختيارُه.
 *
 * والترحيلُ مُتَكرِّرُ التشغيل بلا أثر: يتخطّى ما وُجد بمعرّفه أو بكوده.
 */
return new class extends Migration
{
    /**
     * [المعرّف، الاسم، الكود، الأب، نوع الحساب، طبيعة الرصيد، مفتاح الإعداد]
     */
    private function accounts(): array
    {
        return [
            [
                'id' => SystemAccountIds::POS_CASH_OVER,
                'name' => 'فائض درج الكاشير',
                'code' => '4211',
                'parent_id' => 46,          // إيرادات أخرى (42)
                'account_type' => 'revenues',
                'balance_type' => 'credit',
                'setting_key' => 'pos_cash_over',
            ],
            [
                'id' => SystemAccountIds::POS_CASH_SHORT,
                'name' => 'عجز درج الكاشير',
                'code' => '59011',
                'parent_id' => 61,          // مصروفات أخرى (590)
                'account_type' => 'expenses',
                'balance_type' => 'debit',
                'setting_key' => 'pos_cash_short',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->accounts() as $definition) {
            $accountId = $this->ensureAccount($definition);

            if ($accountId) {
                $this->linkSetting($definition['setting_key'], $accountId, $definition['code']);
            }
        }
    }

    /**
     * يُنشئ الحساب إن لم يكن موجوداً — بمعرّفه أو بكوده — ويعيد معرّفه.
     *
     * الفحصُ بالاثنين: قاعدةٌ نُقلت أو عُدِّلت بيدٍ قد يكون فيها الكود
     * مأخوذاً بمعرّفٍ آخر، وإدراجٌ أعمى يسقط على قيد التفرّد.
     */
    private function ensureAccount(array $definition): ?int
    {
        $existing = DB::table('accounts')
            ->where('id', $definition['id'])
            ->orWhere('code', $definition['code'])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        // الأبُ شرط: بلا شجرةٍ مزروعة لا موضع للحساب، والتعليقُ في الجذر أسوأ
        if (! DB::table('accounts')->where('id', $definition['parent_id'])->exists()) {
            return null;
        }

        DB::table('accounts')->insertOrIgnore([
            'id' => $definition['id'],
            'name' => $definition['name'],
            'code' => $definition['code'],
            'parent_id' => $definition['parent_id'],
            'type' => 'sub',
            'account_type' => $definition['account_type'],
            'balance_type' => $definition['balance_type'],
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => $definition['balance_type'],
            'is_active' => true,
            // الشيفرة ترحّل إليه بالمعرّف، فحذفُه من الشاشة يكسر إقفال الجلسة
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $definition['id'];
    }

    /**
     * يربط مفتاح الإعداد بالحساب — إن كان المفتاح بلا حساب.
     *
     * ولا يُنشئ المفتاح إن غاب: [AccountingSettingsSeeder] صاحبُ ذلك، وهو
     * يُشغَّل وحده بأمان (`db:seed --class=AccountingSettingsSeeder`).
     */
    private function linkSetting(string $key, int $accountId, string $code): void
    {
        $setting = DB::table('accounting_settings')->where('key', $key)->first();

        if (! $setting || $setting->account_id) {
            return;     // غير موجود، أو ربطه المحاسبُ بيده — لا يُمَسّ
        }

        DB::table('accounting_settings')->where('key', $key)->update([
            'account_id' => $accountId,
            'account_code' => $code,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach ($this->accounts() as $definition) {
            // فكُّ الربط أوّلاً، وإلّا بقي مفتاحٌ يشير إلى حسابٍ محذوف
            DB::table('accounting_settings')
                ->where('key', $definition['setting_key'])
                ->where('account_id', $definition['id'])
                ->update(['account_id' => null, 'account_code' => null, 'updated_at' => now()]);

            /*
                لا يُحذف حسابٌ تحرّك عليه قيد: التراجعُ عن ترحيلٍ لا يجوز أن
                يمحو أثراً محاسبياً. يبقى الحسابُ وتُفكّ الإشارةُ إليه.
            */
            $hasEntries = DB::table('journal_entry_details')
                ->where('account_id', $definition['id'])
                ->exists();

            if (! $hasEntries) {
                DB::table('accounts')->where('id', $definition['id'])->delete();
            }
        }
    }
};
