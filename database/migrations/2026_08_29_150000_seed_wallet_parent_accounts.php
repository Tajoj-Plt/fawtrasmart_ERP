<?php

use App\Models\Account;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إنشاء حسابَي المحافظ في الشجرة وربطهما في «الحسابات المحاسبية».
 *
 * كان المفتاحان يُشحنان غير مربوطَين ليختار المحاسب حسابهما، فكانت أوّل
 * حركةٍ على أيّ محفظة تُردّ برسالة «الحساب غير مربوط». وموضعهما في الشجرة
 * ليس محلَّ اجتهادٍ أصلاً — لكلٍّ منهما مكانٌ واحد يقتضيه تصنيفُه:
 *
 *   • **دفعات مقدَّمة للموردين** — مالٌ سلّمناه للمورد ولم نستهلكه، فهو
 *     أصلٌ لنا لديه. موضعُه تحت **المدينون (124)** إلى جانب «مصروفات
 *     مدفوعة مقدماً (1245)» — وكلاهما دفعٌ مقدَّم يُقيَّد أصلاً لا مصروفاً.
 *
 *   • **أرصدة محافظ العملاء** — مالٌ قبضناه من العميل ولم نُقابله ببيع،
 *     فهو التزامٌ علينا. موضعُه تحت **الدائنون (211)** إلى جانب «إيرادات
 *     مقبوضة مقدماً (2115)» — وكلاهما مقبوضٌ مقدَّماً يبقى ديناً حتى
 *     يُستهلك.
 *
 * وباقي القيود لا يحتاج ربطاً: الإيداع والسحب بين المحفظة و**الخزينة**،
 * والسدادُ بين المحفظة وحساب **العميل/المورد** نفسه — وكلُّها حسابات
 * قائمةٌ في الشجرة يعرفها النظام.
 *
 * **لا تُلمَس الروابط الموجودة**: إن كان المفتاح مربوطاً بحسابٍ اختاره
 * المحاسب فعلاً، تمرّ الهجرة عنه ولا تعيد توجيهه — فاختياره أولى من
 * افتراضنا.
 */
return new class extends Migration
{
    /**
     * الحسابان: المفتاح ← [الاسم، كود الأب، الكود، التصنيف، الطبيعة]
     */
    private const WALLETS = [
        'supplier_wallets_parent' => [
            'name' => 'دفعات مقدَّمة للموردين',
            'parent_code' => 124,   // المدينون
            'code' => 1249,
            'account_type' => 'assets',
            'balance_type' => 'debit',
        ],
        'client_wallets_parent' => [
            'name' => 'أرصدة محافظ العملاء',
            'parent_code' => 211,   // الدائنون
            'code' => 2116,
            'account_type' => 'liabilities',
            'balance_type' => 'credit',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach (self::WALLETS as $key => $spec) {
            $setting = DB::table('accounting_settings')->where('key', $key)->first();

            // مفتاحٌ غير موجود (هجرة المحافظ لم تُشغَّل) — لا شيء يُربط
            if (! $setting) {
                continue;
            }

            // مربوطٌ بحسابٍ قائم: اختيار المحاسب يبقى
            if ($setting->account_id && Account::whereKey($setting->account_id)->exists()) {
                continue;
            }

            $account = $this->resolveAccount($spec);

            if (! $account) {
                continue;
            }

            DB::table('accounting_settings')->where('key', $key)->update([
                'account_id' => $account->id,
                'account_code' => $account->code,
                'updated_at' => now(),
            ]);
        }

        /* خريطةُ الإعدادات مخزَّنةٌ ساعةً كاملة، فربطٌ لا يُتبَع بإفراغها
           يبقى غير مرئيّ للتطبيق حتى تنتهي المدّة — ويُقرأ ذلك رسالةَ
           «الحساب غير مربوط» رغم أنّه مربوطٌ في القاعدة. */
        AccountingSettings::forget();
    }

    /**
     * الحساب — يُستعاد إن وُجد بالكود أو بالاسم تحت الأب نفسه، وإلا يُنشأ.
     * فإعادة تشغيل الهجرة لا تُنشئ نسخةً ثانية.
     */
    private function resolveAccount(array $spec): ?Account
    {
        $existing = Account::where('code', $spec['code'])->first();

        if ($existing) {
            return $existing;
        }

        $parent = Account::where('code', $spec['parent_code'])->first();

        // شجرةٌ مختلفة عمّا نتوقّع — لا يُخمَّن موضعٌ آخر
        if (! $parent) {
            return null;
        }

        $byName = Account::where('parent_id', $parent->id)->where('name', $spec['name'])->first();

        if ($byName) {
            return $byName;
        }

        return Account::create([
            'name' => $spec['name'],
            'code' => $spec['code'],
            'parent_id' => $parent->id,
            'type' => 'sub',
            'account_type' => $spec['account_type'],
            'balance_type' => $spec['balance_type'],
            'balance' => 0,
            'opening_balance' => 0,
            'is_active' => 1,
            // حسابٌ يقوم عليه النظام — لا يُحذف من شاشة الشجرة
            'is_system' => 1,
        ]);
    }

    /**
     * التراجع يفكّ الربط ويحذف الحساب إن كان فارغاً وبلا أبناء.
     * أمّا حسابٌ تحرّك عليه قيدٌ فيبقى — حذفُه يُيتّم قيوده.
     */
    public function down(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach (self::WALLETS as $key => $spec) {
            $account = Account::where('code', $spec['code'])->first();

            DB::table('accounting_settings')->where('key', $key)->update([
                'account_id' => null,
                'account_code' => null,
                'updated_at' => now(),
            ]);

            if (! $account) {
                continue;
            }

            $hasChildren = Account::where('parent_id', $account->id)->exists();
            $hasEntries = Schema::hasTable('journal_entry_details')
                && DB::table('journal_entry_details')->where('account_id', $account->id)->exists();

            if (! $hasChildren && ! $hasEntries) {
                $account->delete();
            }
        }

        AccountingSettings::forget();
    }
};
