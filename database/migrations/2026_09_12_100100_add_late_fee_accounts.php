<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قسم الغرامات في شجرة الحسابات — للشجرات القائمة.
 *
 * [AccountSeeder] يزرعه في التنصيب الجديد، وهذا الترحيل يضعه في الشجرات
 * التي مضى زرعُها — فالسيدر لا يُعاد تشغيله على قاعدةٍ فيها بيانات (هو
 * يبدأ بـ`Account::query()->delete()`).
 *
 * ═════════════════════ ولماذا حسابان؟ ═════════════════════
 *
 * الأبُ «الغرامات والتعويضات» (4212) موضعُ القسم كلِّه تحت «إيرادات
 * أخرى»، والابنُ «غرامات تأخير الأقساط» (42121) هو ما تُرحَّل إليه غرامةُ
 * القسط المتأخّر. فحين تُضاف غراماتٌ أخرى — إخلالُ عقدٍ أو تأخّرُ
 * تسليم — تجد أباً جاهزاً ولا تُلصَق بحساب الأقساط.
 *
 * وهي **إيراد** لا خصمٌ من مصروف: مالٌ يُستحقّ على العميل مقابل تأخّره،
 * طبيعتُه دائنة، ويُقفَل في قائمة الدخل ضمن الإيرادات العارضة. والقيدُ
 * الذي يكتبه `installments:late-fees`:
 *
 *     من ح/ العميل (ذمّته المدينة)      xx
 *         إلى ح/ غرامات تأخير الأقساط        xx
 *
 * ═════════════════════ بالكود لا بمعرّفٍ ثابت ═════════════════════
 *
 * **لا `SystemAccountIds` هنا، وهذا مقصودٌ ومُختبَر.** أكثرُ ترحيلات
 * الحسابات في هذا المستودع تُنشئ الحساب بمعرّفٍ ثابت، ولا يصحّ ذلك هنا:
 * المعرّفات فوق 3433 — آخرُ ما يزرعه [AccountSeeder] — مأخوذةٌ في
 * التركيبات القائمة **بحسابات العملاء**، إذ تُنشأ تباعاً بعد آخر المزروع.
 *
 * وقد وقع ذلك فعلاً في التركيب الذي كُتب عليه هذا الترحيل: 3434 و3435
 * حسابا عميلَين قائمَين. فترحيلٌ يثبّت المعرّفَين كان يربط مفتاح «غرامات
 * تأخير الأقساط» بحساب عميل، فتُرحَّل إليه إيراداتُ الغرامات كلُّها
 * وتظهر في كشف حسابه.
 *
 * فالبحث هنا **بالكود وحده**، والإدراج بمعرّفٍ تلقائيّ.
 *
 * ═════════════════════ ويربط المفتاحين ═════════════════════
 *
 * `late_fees_parent` و`installment_late_fees` يُزرعان في
 * `accounting_settings` من [AccountingSettingsSeeder] بكودَيهما، ويربطهما
 * هذا الترحيل بالحسابين إن كانا بلا ربط. **ولا يمسّ ما ربطه المحاسبُ
 * بيده**، ولا يُنشئ المفتاح إن غاب — ذاك عملُ السيدر، وهو يُشغَّل وحده
 * بأمان (`db:seed --class=AccountingSettingsSeeder`).
 *
 * والترحيل مُتَكرِّرُ التشغيل بلا أثر: يتخطّى ما وُجد بكوده.
 */
return new class extends Migration
{
    /** الأبُ تحت «إيرادات أخرى» (42) — المعرّف 46 في الشجرة المزروعة. */
    private const OTHER_REVENUE_PARENT = 46;

    private const PARENT_CODE = '4212';

    private const FEE_CODE = '42121';

    public function up(): void
    {
        $parentId = $this->ensureAccount(
            self::PARENT_CODE,
            'الغرامات والتعويضات',
            self::OTHER_REVENUE_PARENT
        );

        if (! $parentId) {
            // شجرةٌ غير مزروعة: لا موضع للحساب، والتعليقُ في الجذر أسوأ
            return;
        }

        $this->linkSetting('late_fees_parent', $parentId, self::PARENT_CODE);

        $feeId = $this->ensureAccount(self::FEE_CODE, 'غرامات تأخير الأقساط', $parentId);

        if ($feeId) {
            $this->linkSetting('installment_late_fees', $feeId, self::FEE_CODE);
        }
    }

    /**
     * يُنشئ الحساب إن لم يوجد بكوده، ويعيد معرّفه.
     *
     * المعرّف تلقائيّ: تثبيتُه يصطدم بحسابات العملاء في التركيبات القائمة
     * — انظر رأس الترحيل.
     */
    private function ensureAccount(string $code, string $name, int $parentId): ?int
    {
        $existing = DB::table('accounts')->where('code', $code)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        if (! DB::table('accounts')->where('id', $parentId)->exists()) {
            return null;
        }

        return (int) DB::table('accounts')->insertGetId([
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'type' => 'sub',
            'account_type' => 'revenues',
            'balance_type' => 'credit',
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => 'credit',
            'is_active' => true,
            /* `is_system`: مهمّةُ الغرامات ترحّل إليه عبر مفتاحه المضبوط،
               فحذفُه من الشاشة يُوقف الترحيل برسالة «غير مربوط». */
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** يربط مفتاح الإعداد بالحساب — إن كان المفتاح موجوداً بلا حساب. */
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
        // الابن أوّلاً: حذفُ الأب قبله يترك ابناً بأبٍ مفقود
        foreach ([
            ['key' => 'installment_late_fees', 'code' => self::FEE_CODE],
            ['key' => 'late_fees_parent', 'code' => self::PARENT_CODE],
        ] as $row) {
            $id = DB::table('accounts')->where('code', $row['code'])->value('id');

            if (! $id) {
                continue;
            }

            // فكُّ الربط أوّلاً، وإلّا بقي مفتاحٌ يشير إلى حسابٍ محذوف
            DB::table('accounting_settings')
                ->where('key', $row['key'])
                ->where('account_id', $id)
                ->update(['account_id' => null, 'account_code' => null, 'updated_at' => now()]);

            /*
                لا يُحذف حسابٌ تحرّك عليه قيد: التراجعُ عن ترحيلٍ لا يجوز أن
                يمحو أثراً محاسبياً. يبقى الحسابُ وتُفكّ الإشارةُ إليه.
            */
            $hasEntries = DB::table('journal_entry_details')->where('account_id', $id)->exists();
            $hasChildren = DB::table('accounts')->where('parent_id', $id)->exists();

            if (! $hasEntries && ! $hasChildren) {
                DB::table('accounts')->where('id', $id)->delete();
            }
        }
    }
};
