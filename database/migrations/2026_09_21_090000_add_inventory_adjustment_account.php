<?php

use App\Constants\SystemAccountIds;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حساب «عجز وفائض جرد المخزون» — للشجرات القائمة.
 *
 * ═════════════════════ المسألة ═════════════════════
 *
 * الطرف المقابل لقيد تسوية الجرد (مفتاح `inventory_adjustment`) كان يقع
 * افتراضاً على «تكلفة البضاعة المباعة» (511) — لأنه ما كان مكتوباً في
 * الشيفرة قبل نقله إلى الإعدادات. فيقرأ المحاسب قيد عجز الجرد:
 *
 *     من ح/ تكلفة المبيعات    إلى ح/ المستودع
 *
 * ويختلط في 511 أمران لا يُقرآن معاً: تكلفةُ بضاعةٍ **بيعت** فعلاً،
 * وفروقُ جردٍ لا بيعَ خلفها. فلا تصحّ نسبة مجمل الربح ولا يُعرف حجم
 * الهدر المخزني على حدة.
 *
 * والحلّ حسابٌ مستقلّ: «عجز وفائض جرد المخزون» شقيقاً لأخوته تحت «تكلفة
 * المبيعات» (51) — مصروفٌ بطبيعة مدينة: العجز يزيده مديناً، والزيادة
 * (فائض الجرد) تنقصه دائناً، فرصيده صافي أثر الجرد في الفترة.
 *
 * ═════════════════════ بالكود لا بمعرّفٍ ثابت ═════════════════════
 *
 * كأشقّائه في ترحيل `add_purchase_discount_and_shipping_vat_accounts`:
 * المعرّفات في التركيبات القائمة مأخوذة، فالبحث بالكود والإدراج بمعرّف
 * تلقائي، والترحيل مُتكرِّر التشغيل بلا أثر. والكود 516 أول شاغرٍ تحت
 * «تكلفة المبيعات» في الشجرتين معاً: المزروعة تشغل 511-515، والقائمة
 * 511-513. و[AccountSeeder] يزرعه في التنصيب الجديد بالكود نفسه.
 */
return new class extends Migration
{
    private const CODE = '516';

    private const KEY = 'inventory_adjustment';

    private const NAME = 'عجز وفائض جرد المخزون';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('accounts')
            || ! DB::getSchemaBuilder()->hasTable('accounting_settings')) {
            return;
        }

        $accountId = $this->ensureAccount();

        if ($accountId) {
            $this->repointSetting($accountId);
        }

        // بلا هذا يبقى الربط الجديد مخفيّاً ساعةً كاملة خلف ذاكرة الخريطة
        if (class_exists(AccountingSettings::class)) {
            AccountingSettings::forget();
        }
    }

    /**
     * إيجاد الحساب أو إنشاؤه تحت «تكلفة المبيعات».
     *
     * الأب من مفتاح `cost_of_sales` المضبوط (أبو الحساب المربوط به)، لا
     * من معرّفٍ مثبَّت — ومن لم يضبطه يُستدلّ بالحساب 50 المزروع نفسه.
     */
    private function ensureAccount(): ?int
    {
        $costOfSalesId = DB::table('accounting_settings')->where('key', 'cost_of_sales')->value('account_id')
            ?: SystemAccountIds::COST_OF_SALES;

        $parentId = DB::table('accounts')->where('id', $costOfSalesId)->value('parent_id');

        if (! $parentId) {
            return null;     // لا موضعَ يُستدَلّ به
        }

        $existing = DB::table('accounts')->where('code', self::CODE)->first();

        if ($existing) {
            /*
                الكود مأخوذ: إن كان شقيقاً تحت الأب نفسه فهو حسابُنا (زرعه
                تنصيبٌ جديد أو تشغيلٌ سابق). وإلا فهو حسابٌ آخر صادف الكود
                في شجرةٍ مخصَّصة — لا يُمَسّ ويُبحث عن كودٍ شاغر.
            */
            if ((int) $existing->parent_id === (int) $parentId) {
                return (int) $existing->id;
            }

            $code = $this->firstFreeCode($parentId);

            if ($code === null) {
                return null;
            }
        } else {
            $code = self::CODE;
        }

        return (int) DB::table('accounts')->insertGetId([
            'name' => self::NAME,
            'code' => $code,
            'parent_id' => $parentId,
            'type' => 'sub',
            // مصروفٌ مدين على ما تزرعه [AccountSeeder] لأشقّائه تحت 51
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

    /** أول كودٍ شاغر بصيغة 51x ثم 51xx — والقيد الفريد عالميّ فيُفحص الجدول كله. */
    private function firstFreeCode(int $parentId): ?string
    {
        $parentCode = (string) (DB::table('accounts')->where('id', $parentId)->value('code') ?: '51');

        foreach (range(6, 9) as $i) {
            $candidate = $parentCode.$i;

            if (! DB::table('accounts')->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        foreach (range(10, 99) as $i) {
            $candidate = $parentCode.$i;

            if (! DB::table('accounts')->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * إعادة توجيه المفتاح **فقط** متى كان لا يزال على الافتراض المعطوب:
     * غيرَ مربوطٍ أصلاً، أو مربوطاً بحساب «تكلفة البضاعة المباعة» نفسِه
     * الذي يشير إليه مفتاح `cost_of_sales`. فمن وجّهه بيده إلى حسابٍ
     * اختاره لا يُمَسّ اختياره.
     */
    private function repointSetting(int $accountId): void
    {
        $setting = DB::table('accounting_settings')->where('key', self::KEY)->first();

        $code = DB::table('accounts')->where('id', $accountId)->value('code');

        if (! $setting) {
            // تركيبٌ لم يشغّل السيدر بعد — يُزرع الصف مربوطاً مباشرة
            DB::table('accounting_settings')->insert([
                'key' => self::KEY,
                'name' => 'تسوية الجرد',
                'description' => 'الطرف المقابل لفروق الجرد — حساب «عجز وفائض جرد المخزون»، لا تكلفة البضاعة المباعة',
                'group' => 'inventory',
                'account_id' => $accountId,
                'account_code' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $costOfSalesId = DB::table('accounting_settings')->where('key', 'cost_of_sales')->value('account_id')
            ?: SystemAccountIds::COST_OF_SALES;

        $isBrokenDefault = $setting->account_id === null
            || (int) $setting->account_id === (int) $costOfSalesId;

        if (! $isBrokenDefault) {
            return;     // وجّهه المحاسب بيده — لا يُمَسّ
        }

        DB::table('accounting_settings')->where('key', self::KEY)->update([
            'account_id' => $accountId,
            'account_code' => $code,
            'description' => 'الطرف المقابل لفروق الجرد — حساب «عجز وفائض جرد المخزون»، لا تكلفة البضاعة المباعة',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('accounts')->where('code', self::CODE)->where('name', self::NAME)->value('id');

        if (! $id) {
            return;
        }

        // فكُّ الربط أوّلاً، وإلّا بقي مفتاحٌ يشير إلى حسابٍ محذوف
        DB::table('accounting_settings')
            ->where('key', self::KEY)
            ->where('account_id', $id)
            ->update(['account_id' => null, 'account_code' => null, 'updated_at' => now()]);

        // لا يُحذف حسابٌ تحرّك عليه قيد: التراجعُ لا يمحو أثراً محاسبياً
        if (! DB::table('journal_entry_details')->where('account_id', $id)->exists()) {
            DB::table('accounts')->where('id', $id)->delete();
        }

        if (class_exists(AccountingSettings::class)) {
            AccountingSettings::forget();
        }
    }
};
