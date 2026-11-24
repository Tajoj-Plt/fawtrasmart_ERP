<?php

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * مفاتيح الخصم والشحن في «الحسابات المحاسبية».
 *
 * ── العطل ────────────────────────────────────────────────────────────
 *
 * `InvoicesPurchaseController` يطلب `purchase_discount` و`purchase_shipping`
 * وقت حفظ الفاتورة كلّما كان فيها خصمٌ أو شحن. والمفتاحان لم يكن لهما صفٌّ
 * في `accounting_settings`، فتسقط الفاتورة برسالة:
 *
 *     «لا يوجد مفتاح بهذا الاسم (purchase_discount) في جدول الإعدادات أصلاً»
 *
 * ولا يستطيع المحاسب إصلاحها من الشاشة، لأن ما لا صفَّ له لا يُعرَض فيها.
 *
 * ── لماذا ترحيلٌ لا سيدر ─────────────────────────────────────────────
 *
 * الأربعة مضافةٌ فعلاً إلى `AccountingSettingsSeeder::definitionsByCode()`
 * منذ 2026-08-24، لكن **السيدرات لا تُشغَّل عند رفع نسخةٍ إلى الخادم** —
 * الترحيلات وحدها هي ما يُشغَّل. فبقيت الخوادم المُحدَّثة بلا الصفوف
 * الأربعة، والفاتورة تسقط عليها بينما تعمل محلياً.
 *
 * الترحيل يسدّ ذلك: يعمل مرّةً واحدة على كل تركيب، ويكرّر منطق السيدر
 * حرفياً (الأكواد نفسها، والمجموعات نفسها) فلا يتعارضان — أيّهما سبق،
 * يتخطّى الآخر الصفَّ الموجود.
 *
 * ── الربط ────────────────────────────────────────────────────────────
 *
 * بالكود المحاسبي أولاً (512/513/417/5606 من الشجرة القياسية)، ثم بالاسم
 * **مطابقةً تامّة** لا `LIKE` — «شحن مجاني» و«شركات الشحن» و«إيرادات الشحن»
 * كلّها تحوي «شحن» ولا واحد منها هو المطلوب.
 *
 * وإن لم يُعثر على شيء: يُنشأ الصفّ **بلا ربط**، فيظهر في الشاشة «غير
 * مضبوط» ويربطه المحاسب بيده. لا يخترع هذا الترحيل حساباً في الشجرة.
 */
return new class extends Migration
{
    /**
     * المفتاح => [الكود، أسماء بديلة للمطابقة التامّة، الاسم، الوصف، المجموعة].
     *
     * @var array<string, array{0:string,1:array<int,string>,2:string,3:string,4:string}>
     */
    private const SETTINGS = [
        'purchase_discount' => [
            '513',
            ['خصم مسموح به', 'خصم المشتريات', 'خصم مكتسب'],
            'خصم المشتريات',
            'الخصم الذي يمنحه المورد — يخفّض كلفة الشراء',
            'المشتريات',
        ],
        'purchase_shipping' => [
            '512',
            ['شحن مشتريات', 'شحن المشتريات', 'تكاليف شحن المشتريات'],
            'تكاليف شحن المشتريات',
            'أجرة إيصال البضاعة المشتراة — تُضاف إلى الكلفة',
            'المشتريات',
        ],
        // نظيراهما في المبيعات غائبان بالعطل نفسه ومن الكتلة نفسها في
        // السيدر — فصلُهما هنا يعني تذكرةَ دعمٍ ثانية بعد أول فاتورة بيع
        // فيها خصم
        'sales_discount' => [
            '417',
            ['خصم المبيعات'],
            'خصم المبيعات',
            'الخصم على بنود فاتورة البيع — يخفّض الإيراد',
            'المبيعات',
        ],
        'sales_shipping' => [
            '5606',
            ['شحن مبيعات', 'شحن المبيعات'],
            'شحن المبيعات',
            'تكلفة شحن الفاتورة المحمَّلة على المنشأة',
            'المبيعات',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings') || ! Schema::hasTable('accounts')) {
            return;
        }

        $added = [];
        $unlinked = [];

        foreach (self::SETTINGS as $key => [$code, $names, $name, $description, $group]) {
            // الصفّ الموجود لا يُمَس: ربطُ المحاسب أولى من تخميننا، وقد
            // يكون السيدر سبقنا إليه على تركيبٍ محلّي
            if (AccountingSetting::where('key', $key)->exists()) {
                continue;
            }

            $account = $this->findAccount($code, $names);

            AccountingSetting::create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'group' => $group,
                'account_id' => $account?->id,
                'account_code' => $account?->code !== null ? (string) $account->code : null,
                'is_active' => true,
            ]);

            $account ? $added[] = "{$name} ← #{$account->id} ({$account->code} — {$account->name})"
                : $unlinked[] = $name;
        }

        if ($added === [] && $unlinked === []) {
            return;
        }

        // بلا هذا يبقى المفتاح غائباً عن الخريطة المخزَّنة ساعةً كاملة،
        // فتظلّ الفاتورة تسقط بعد ترحيلٍ نجح
        AccountingSettings::forget();

        foreach ($added as $line) {
            echo "\n  ✓ {$line}";
        }

        foreach ($unlinked as $name) {
            echo "\n  ⚠️  أُضيف «{$name}» بلا حساب مربوط — اربطه من:";
            echo "\n      الإعدادات ← الحسابات المحاسبية.";
        }

        echo "\n\n";
    }

    /**
     * التراجع يحذف ما أضافه هذا الترحيل وحده.
     *
     * الصفّ الذي ربطه المحاسب بحسابٍ غير الذي خمّنّاه يبقى: حذفُه يعيد
     * العطل نفسه ويُضيّع ربطاً يدوياً لا نملك إعادته.
     */
    public function down(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach (self::SETTINGS as $key => [$code, $names]) {
            $setting = AccountingSetting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $guess = $this->findAccount($code, $names);

            if ($setting->account_id === $guess?->id) {
                $setting->delete();
            }
        }

        AccountingSettings::forget();
    }

    /**
     * حساب الشجرة المقابل — بالكود، ثم بالاسم مطابقةً تامّة.
     *
     * @param  array<int, string>  $names
     */
    private function findAccount(string $code, array $names): ?Account
    {
        $account = Account::where('code', $code)->first();

        if ($account) {
            return $account;
        }

        return Account::whereIn('name', $names)->orderBy('id')->first();
    }
};
