<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حسابُ «ضريبة شحن مبيعات» — للشجرات القائمة.
 *
 * ═════════════════════ المسألة ═════════════════════
 *
 * قيدُ فاتورة البيع صار يفصل ضريبةَ البنود عن ضريبة الشحن في سطرين
 * (مركَّبةً لا مجموعة)، غير أنّ السطرين كانا يُرحَّلان إلى الحساب نفسه —
 * `vat_payable`، واسمُه في الشجرة «ضريبة المبيعات». فمن يفتح القيد يرى:
 *
 *     2144  ضريبة المبيعات        دائن  91.50
 *     2144  ضريبة المبيعات        دائن   7.50
 *
 * سطران باسمٍ واحد لا يُعرَف أيُّهما البضاعة وأيُّهما التوصيل — وشاشةُ
 * القيد تعرض **اسم الحساب** لا بيانَ السطر، فبيانُ السطر وحده لا يكفي.
 *
 * ═════════════════════ والحلّ ═════════════════════
 *
 * حسابٌ شقيقٌ تحت الأب نفسِه («القيمة المضافة المطلوبة») يحمل الضريبةَ
 * المحصَّلة على الشحن وحدها، ومفتاحٌ في «الحسابات المحاسبية» يربطه.
 *
 * **والفصلُ اختياريّ.** متى بقي المفتاح غير مضبوط رجع القيدُ إلى
 * `vat_payable` كما كان — فمنشأةٌ لا تريد الفصل لا يتغيّر عليها شيء، ولا
 * يُفرَض حسابٌ على شجرةٍ لم تطلبه.
 *
 * ═════════════════════ بالكود لا بمعرّفٍ ثابت ═════════════════════
 *
 * كنظير `add_late_fee_accounts`: المعرّفاتُ العليا مأخوذةٌ في التركيبات
 * القائمة بحسابات العملاء، فتثبيتُ معرّفٍ يربط المفتاحَ بحساب عميل.
 * البحثُ بالكود، والإدراجُ بمعرّفٍ تلقائيّ، والترحيلُ مُتَكرِّرُ التشغيل
 * بلا أثر.
 */
return new class extends Migration
{
    private const CODE = '2145';

    private const KEY = 'shipping_vat_payable';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('accounts')) {
            return;
        }

        /* الأبُ هو أبو `vat_payable` نفسِه — لا كودٌ مثبَّت: «214» في
           الشجرة المزروعة، وقد يختلف في تركيبٍ آخر. ومن ربط ضريبتَه
           بحسابٍ في موضعٍ غير قياسيّ يجد شقيقَها إلى جانبها. */
        $vatAccountId = DB::table('accounting_settings')->where('key', 'vat_payable')->value('account_id');

        if (! $vatAccountId) {
            return;     // لا ضريبةَ مربوطة: لا موضعَ يُستدَلّ به
        }

        $parentId = DB::table('accounts')->where('id', $vatAccountId)->value('parent_id');

        if (! $parentId) {
            return;
        }

        $accountId = DB::table('accounts')->where('code', self::CODE)->value('id');

        if (! $accountId) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'ضريبة شحن مبيعات',
                'code' => self::CODE,
                'parent_id' => $parentId,
                'type' => 'sub',
                'account_type' => 'liabilities',
                'balance_type' => 'credit',
                'balance' => 0.0,
                'opening_balance' => 0.0,
                'opening_balance_type' => 'credit',
                'is_active' => true,
                /* يُرحَّل إليه عبر مفتاحه المضبوط، فحذفُه من الشاشة يعيد
                   القيدَ إلى `vat_payable` بلا عطل — ومع ذلك يبقى نظامياً
                   لئلّا يُحذف سهواً وعليه حركة. */
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $setting = DB::table('accounting_settings')->where('key', self::KEY)->first();

        // المفتاح يزرعه [AccountingSettingsSeeder]؛ وإن سبقه هذا الترحيل
        // فلا يُنشَأ هنا — ذاك عملُ السيدر، ويُشغَّل وحده بأمان
        if (! $setting || $setting->account_id) {
            return;     // غير موجود، أو ربطه المحاسبُ بيده — لا يُمَسّ
        }

        DB::table('accounting_settings')->where('key', self::KEY)->update([
            'account_id' => $accountId,
            'account_code' => self::CODE,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('accounts')->where('code', self::CODE)->value('id');

        if (! $id) {
            return;
        }

        // فكُّ الربط أوّلاً، وإلّا بقي مفتاحٌ يشير إلى حسابٍ محذوف
        DB::table('accounting_settings')
            ->where('key', self::KEY)
            ->where('account_id', $id)
            ->update(['account_id' => null, 'account_code' => null, 'updated_at' => now()]);

        // لا يُحذف حسابٌ تحرّك عليه قيد: التراجعُ عن ترحيلٍ لا يمحو أثراً محاسبياً
        $hasEntries = DB::table('journal_entry_details')->where('account_id', $id)->exists();

        if (! $hasEntries) {
            DB::table('accounts')->where('id', $id)->delete();
        }
    }
};
