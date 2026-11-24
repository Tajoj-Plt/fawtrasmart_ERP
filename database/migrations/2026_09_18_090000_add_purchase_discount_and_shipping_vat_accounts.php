<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حسابان للمشتريات: «خصم مكتسب» و«ضريبة شحن مشتريات» — للشجرات القائمة.
 *
 * ═════════════════════ المسألة الأولى: الخصم ═════════════════════
 *
 * `SystemAccountIds::PURCHASE_DISCOUNT` و`ALLOWED_DISCOUNT` **رقمٌ واحد**
 * (52). فمفتاحُ خصم المشتريات ومفتاحُ خصم المبيعات يشيران إلى الحساب
 * نفسِه، واسمُه في الشجرة «خصم مسموح به» — وهو مصطلحُ جانب البيع.
 * فيجتمع في حسابٍ واحد أمران متعاكسان:
 *
 *     52  «خصم مسموح به»    SI مدين 179    ← خصمٌ منحناه للعميل (مصروف)
 *                           PI دائن 1150   ← خصمٌ منحه لنا المورد (مخفِّض كلفة)
 *
 * فيتقاصّان، ويخرج رصيدٌ لا يصف أيَّهما. ووصفُ سطر قيد الشراء يقول «خصم
 * مكتسب» أصلاً — لكنّ شاشةَ القيد تعرض **اسم الحساب** لا بيانَ السطر،
 * فالمحاسب يقرأ «خصم مسموح به» على خصمٍ اكتسبناه.
 *
 * والحلّ حسابٌ مستقلّ: «خصم مكتسب» شقيقاً لـ«شحن مشتريات» تحت «تكلفة
 * المبيعات». وموضعُه هذا ليس اعتباطاً — الخصمُ المكتسب **يخفّض كلفة
 * الشراء** ولا يُعدّ إيراداً، فمحلُّه حسابٌ مقابلٌ في المصروفات لا في
 * الإيرادات. ويبقى 52 «خصم مسموح به» لجانب البيع وحده باسمه الصحيح.
 *
 * ═════════════════════ المسألة الثانية: ضريبة الشحن ═════════════════════
 *
 * نظيرُ `add_shipping_vat_account` على جانب الشراء. كانت ضريبةُ الشحن
 * تُجمع مع ضريبة البنود في `total_tax` ثمّ تُرحَّل كلُّها إلى «ضريبة
 * المشتريات»، فلا يُفصَلان في الإقرار إلّا بجردٍ يدويّ.
 *
 * **والفصلُ اختياريّ**: متى بقي المفتاح غير مضبوط رجع القيدُ إلى
 * `vat_paid` كما كان — انظر `ResolvesPurchaseAccounts::shippingVatPaidAccountId()`.
 *
 * ═════════════════════ بالكود لا بمعرّفٍ ثابت ═════════════════════
 *
 * كنظيره في المبيعات: المعرّفاتُ العليا مأخوذةٌ في التركيبات القائمة
 * بحسابات العملاء، فتثبيتُ معرّفٍ يربط المفتاحَ بحساب عميل. البحثُ
 * بالكود، والإدراجُ بمعرّفٍ تلقائيّ، والترحيلُ مُتَكرِّرُ التشغيل بلا أثر.
 */
return new class extends Migration
{
    /*
        الكودان مأخوذان ممّا تعرفه الشجرةُ المزروعة لا مخترعَين:

        • 428 «خصم مكتسب» موجودٌ في [AccountSeeder] أصلاً (تحت «إيرادات
          أخرى»)، فالتنصيبُ الجديد يجده مزروعاً وهذا الترحيلُ يُنشئه في
          الشجرات القائمة التي لا تحمله — فيستقرّ في الموضع نفسِه في
          الحالتين. ولو اختُرع كودٌ جديد (514 مثلاً) لاصطدم بـ«رسوم
          جمركية» في الشجرة المزروعة.

        • 2148 أوّلُ كودٍ خالٍ في نطاق الضرائب في الشجرتين معاً: المزروعة
          تشغل 2141‑2147، والقائمةُ تشغل 2142 و2144‑2146.
    */
    private const DISCOUNT_CODE = '428';

    private const DISCOUNT_KEY = 'purchase_discount';

    private const VAT_CODE = '2148';

    private const VAT_KEY = 'shipping_vat_paid';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('accounts')) {
            return;
        }

        $this->seedDiscountAccount();
        $this->seedShippingVatAccount();

        // بلا هذا يبقى الربطُ الجديد مخفيّاً ساعةً كاملة خلف ذاكرة الخريطة
        if (class_exists(\App\Support\AccountingSettings::class)) {
            \App\Support\AccountingSettings::forget();
        }
    }

    /**
     * «خصم مكتسب» — تحت «إيرادات أخرى»، حيث تضعه الشجرةُ المزروعة.
     *
     * ويُعاد ربطُ `purchase_discount` إليه **فقط** متى كان لا يزال على
     * الافتراض المعطوب: غيرَ مربوطٍ أصلاً، أو مربوطاً بالحساب نفسِه الذي
     * يشير إليه `allowed_discount`. فمن فصلهما بيده لا يُمَسّ اختياره.
     */
    private function seedDiscountAccount(): void
    {
        /* الأبُ من المفتاح لا من معرّفٍ مثبَّت — ومن لم يربط «الإيرادات
           الأخرى» يُستدَلّ بأبي «خصم مسموح به» نفسِه، فهما شقيقان في
           الشجرة المزروعة. */
        $parentId = DB::table('accounting_settings')->where('key', 'other_revenue_parent')->value('account_id');

        if (! $parentId) {
            $allowedAccountId = DB::table('accounting_settings')->where('key', 'allowed_discount')->value('account_id');
            $parentId = $allowedAccountId
                ? DB::table('accounts')->where('id', $allowedAccountId)->value('parent_id')
                : null;
        }

        if (! $parentId) {
            return;     // لا موضعَ يُستدَلّ به
        }

        $accountId = DB::table('accounts')->where('code', self::DISCOUNT_CODE)->value('id');

        if (! $accountId) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'خصم مكتسب',
                'code' => self::DISCOUNT_CODE,
                'parent_id' => $parentId,
                'type' => 'sub',
                /* دائنٌ تحت الإيرادات — على ما تزرعه [AccountSeeder]
                   للحساب نفسِه (151/428). ولا يُخالَف موضعُها هنا وإلّا
                   استقرّ الحسابُ في مكانين بحسب نوع التنصيب. */
                'account_type' => 'revenues',
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

        $setting = DB::table('accounting_settings')->where('key', self::DISCOUNT_KEY)->first();

        if (! $setting) {
            return;     // يزرعه [AccountingSettingsSeeder]
        }

        $allowedId = DB::table('accounting_settings')->where('key', 'allowed_discount')->value('account_id');

        $isBrokenDefault = $setting->account_id === null
            || ($allowedId !== null && (int) $setting->account_id === (int) $allowedId);

        if (! $isBrokenDefault) {
            return;     // فصلهما المحاسبُ بيده — لا يُمَسّ
        }

        DB::table('accounting_settings')->where('key', self::DISCOUNT_KEY)->update([
            'account_id' => $accountId,
            'account_code' => self::DISCOUNT_CODE,
            'name' => 'خصم مكتسب',
            'description' => 'الخصم الذي يمنحه المورد — حسابٌ مقابل يخفّض كلفة الشراء، غير «خصم مسموح به» في جانب البيع',
            'updated_at' => now(),
        ]);
    }

    /** «ضريبة شحن مشتريات» — شقيقُ «ضريبة المشتريات» تحت أبيها نفسِه. */
    private function seedShippingVatAccount(): void
    {
        $vatAccountId = DB::table('accounting_settings')->where('key', 'vat_paid')->value('account_id');

        if (! $vatAccountId) {
            return;
        }

        $vatAccount = DB::table('accounts')->where('id', $vatAccountId)->first();

        if (! $vatAccount || ! $vatAccount->parent_id) {
            return;
        }

        $accountId = DB::table('accounts')->where('code', self::VAT_CODE)->value('id');

        if (! $accountId) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'ضريبة شحن مشتريات',
                'code' => self::VAT_CODE,
                'parent_id' => $vatAccount->parent_id,
                'type' => 'sub',
                /* تتبع شقيقتَها لا قيمةً مفترضة: ضريبةُ المدخلات أصلٌ
                   مدين في هذه الشجرة، وقد تكون في تركيبٍ آخر غير ذلك. */
                'account_type' => $vatAccount->account_type,
                'balance_type' => $vatAccount->balance_type,
                'balance' => 0.0,
                'opening_balance' => 0.0,
                'opening_balance_type' => $vatAccount->balance_type,
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $setting = DB::table('accounting_settings')->where('key', self::VAT_KEY)->first();

        if (! $setting) {
            /* مفتاحٌ جديدٌ كلّياً: لا صفَّ له في التركيبات القائمة، ولا
               يُعاد تشغيل السيدر عليها. فيُنشأ هنا وإلّا بقي الفصلُ
               متعذّراً حتى يُزرع يدوياً. */
            DB::table('accounting_settings')->insert([
                'key' => self::VAT_KEY,
                'name' => 'ضريبة شحن مشتريات',
                'description' => 'الضريبة المدفوعة على تكلفة الشحن وحدها — تُفصَل عن ضريبة البنود في القيد وفي الإقرار',
                'group' => 'tax',
                'account_id' => $accountId,
                'account_code' => self::VAT_CODE,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($setting->account_id) {
            return;     // ربطه المحاسبُ بيده — لا يُمَسّ
        }

        DB::table('accounting_settings')->where('key', self::VAT_KEY)->update([
            'account_id' => $accountId,
            'account_code' => self::VAT_CODE,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach ([[self::DISCOUNT_CODE, self::DISCOUNT_KEY], [self::VAT_CODE, self::VAT_KEY]] as [$code, $key]) {
            $id = DB::table('accounts')->where('code', $code)->value('id');

            if (! $id) {
                continue;
            }

            // فكُّ الربط أوّلاً، وإلّا بقي مفتاحٌ يشير إلى حسابٍ محذوف
            DB::table('accounting_settings')
                ->where('key', $key)
                ->where('account_id', $id)
                ->update(['account_id' => null, 'account_code' => null, 'updated_at' => now()]);

            // لا يُحذف حسابٌ تحرّك عليه قيد: التراجعُ لا يمحو أثراً محاسبياً
            if (! DB::table('journal_entry_details')->where('account_id', $id)->exists()) {
                DB::table('accounts')->where('id', $id)->delete();
            }
        }

        if (class_exists(\App\Support\AccountingSettings::class)) {
            \App\Support\AccountingSettings::forget();
        }
    }
};
