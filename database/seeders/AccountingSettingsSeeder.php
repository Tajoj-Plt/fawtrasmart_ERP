<?php

namespace Database\Seeders;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;
use Illuminate\Database\Seeder;

/**
 * تعبئة «إعدادات الحسابات المحاسبية» لأول مرّة من ثوابت `SystemAccountIds`.
 *
 * القيم الأولية تُقرأ من الثوابت فقط — كي تبدأ الشاشة معبَّأة بما يعمل به
 * النظام فعلاً اليوم، لا بجدول فارغ يطلب من المستخدم ضبط ثمانين حساباً.
 * الثوابت تبقى مكانها ويبقى النظام يستخدمها؛ هذا الجدول طبقة مستقلّة.
 *
 * السيدر لا يمسّ صفّاً موجوداً: تشغيله ثانيةً بعد ضبط يدوي يجب ألّا يعيد
 * الحسابات إلى الافتراضي. يضيف الناقص فقط.
 *
 * ترتيبه بعد `AccountSeeder` مقصود — يقرأ أكواد الحسابات من الشجرة، فلا
 * يجد شيئاً إن سبقها.
 */
class AccountingSettingsSeeder extends Seeder
{
    private const GROUP_CORE = 'حسابات رئيسية';

    private const GROUP_CASH = 'الخزائن والبنوك';

    private const GROUP_SALES = 'المبيعات والإيرادات';

    private const GROUP_PURCHASES = 'المشتريات والموردين';

    private const GROUP_INVENTORY = 'المخزون والتصنيع';

    private const GROUP_CLIENTS = 'العملاء والذمم';

    private const GROUP_TAX = 'الضرائب';

    private const GROUP_EXPENSES = 'المصروفات';

    private const GROUP_PAYROLL = 'الرواتب والموظفين';

    private const GROUP_ASSETS = 'الأصول الثابتة والإهلاك';

    private const GROUP_CHEQUES_IN = 'الشيكات — أوراق القبض';

    private const GROUP_CHEQUES_OUT = 'الشيكات — أوراق الدفع';

    private const GROUP_SHIPPING = 'الشحن';

    private const GROUP_REWARDS = 'نظام المكافآت';

    private const GROUP_PENALTIES = 'الغرامات';

    /**
     * مفاتيح الخصم والشحن — تُربط بكودها في الشجرة لا بمعرّفٍ ثابت.
     *
     * **العطل**
     *
     * `purchase_discount` و`purchase_shipping` لم يكن لهما صفٌّ في الجدول
     * أصلاً، بينما شاشة «الحسابات المحاسبية» تعرضهما في مثال قيد فاتورة
     * الشراء، و`InvoicesPurchaseController` يطلبهما وقت الحفظ كلّما كان في
     * الفاتورة خصمٌ أو شحن. فكانت الفاتورة تسقط برسالة «غير مربوط» على
     * مفتاحٍ لا يستطيع المحاسب ربطه لأنه لا يظهر في الشاشة. ونظيراهما في
     * المبيعات غائبان كذلك.
     *
     * **ولماذا بالكود لا بالمعرّف**
     *
     * بقيّة المفاتيح تُزرع من ثوابت `SystemAccountIds`. وهذه الأربعة تُقرأ
     * من شجرة الحسابات بكودها المحاسبي، فلا يدخل النظام ثابتٌ جديد: الحساب
     * يُلتقط إن وُجد في الشجرة، وإلا أُنشئ الصفّ **بلا ربط** فيظهر في
     * الشاشة «غير مضبوط» ويربطه المحاسب بيده. وفي الحالين تبقى «إعدادات
     * الحسابات المحاسبية» هي التي تقرّر أين يذهب القيد.
     *
     * الصيغة: المفتاح => [كود الحساب، الاسم، الوصف، المجموعة].
     *
     * @return array<string, array{0:string,1:string,2:string,3:string}>
     */
    private function definitionsByCode(): array
    {
        return [
            'sales_discount' => ['417', 'خصم المبيعات', 'الخصم على بنود فاتورة البيع — يخفّض الإيراد', self::GROUP_SALES],
            'sales_shipping' => ['5606', 'شحن المبيعات', 'تكلفة شحن الفاتورة المحمَّلة على المنشأة', self::GROUP_SALES],
            /*
                «خصم مكتسب» حسابٌ مستقلّ عن «خصم مسموح به» (513).

                كان المفتاحان — هذا و`allowed_discount` — يشيران إلى 513
                معاً لأنّ ثابتَيهما رقمٌ واحد، فيجتمع في الحساب خصمٌ
                منحناه (مدين) وخصمٌ اكتسبناه (دائن) ويتقاصّان.

                وموضعُه تحت «إيرادات أخرى» حيث تضعه الشجرةُ المزروعة
                أصلاً (151/428 في [AccountSeeder]) — فلا كودَ يُخترَع ولا
                يستقرّ الحسابُ في موضعين بحسب نوع التنصيب. والترحيل
                `add_purchase_discount_and_shipping_vat_accounts` يُنشئه
                في الشجرات القائمة التي لا تحمله.
            */
            'purchase_discount' => ['428', 'خصم مكتسب', 'الخصم الذي يمنحه المورد — حسابٌ مقابل يخفّض كلفة الشراء، غير «خصم مسموح به» في جانب البيع', self::GROUP_PURCHASES],
            'purchase_shipping' => ['512', 'تكاليف شحن المشتريات', 'أجرة إيصال البضاعة المشتراة — تُضاف إلى الكلفة', self::GROUP_PURCHASES],

            /*
                خصميات الموظفين — حسابٌ مقابلٌ يخفّض مصروف الرواتب.

                يزرعه [AccountSeeder] بكود 5310 تحت «الرواتب والأجور»، ويربطه
                الترحيل `add_payroll_deductions_account_and_link` في الشجرات
                القائمة. فإن سبق السيدرُ وجودَ الحساب مُلئ الربط عند إعادة
                تشغيل السيدر (فرع الصف القائم بلا account_id أدناه).
            */
            'payroll_deductions' => ['5310', 'خصميات الموظفين', 'الحساب المقابل لخصميات الغياب والجزاءات — يخفّض مصروف الرواتب ولا يُخفي إجماليه', self::GROUP_PAYROLL],

            /*
                نهاية الخدمة والتأمينات الاجتماعية — كانت في `payroll_settings`
                و`social_insurance_settings` تُضبط من شاشات الرواتب. صارت
                مفاتيحَ هنا (انظر `App\Support\PayrollAccounts`) والترحيلُ
                `move_payroll_accounts_to_accounting_settings` ينقل قيمَها
                القائمة. والأكوادُ هي ما يزرعه [AccountSeeder] في التنصيب
                الجديد (5307، 226، 2161، 2162، 5305).
            */
            'eos_expense' => ['5307', 'مصروف مكافأة نهاية الخدمة', 'مدينٌ بمبلغ المكافأة عند اعتماد مسودة نهاية الخدمة', self::GROUP_PAYROLL],
            'eos_provision' => ['226', 'مخصص مكافأة نهاية الخدمة', 'التزامٌ دائنٌ عند اعتماد المكافأة، ومدينٌ عند تسويتها إلى ذمّة الموظف', self::GROUP_PAYROLL],
            'gosi_employee_payable' => ['2161', 'تأمينات مستحقة — حصة الموظف', 'دائنٌ بحصة الموظف المستقطَعة من راتبه عند الصرف، ويُقفل عند السداد للتأمينات', self::GROUP_PAYROLL],
            'gosi_employer_payable' => ['2162', 'تأمينات مستحقة — حصة المنشأة', 'دائنٌ بمساهمة المنشأة عند صرف الراتب، ويُقفل عند السداد للتأمينات', self::GROUP_PAYROLL],
            'gosi_expense' => ['5305', 'مصروف التأمينات الاجتماعية', 'مدينٌ بحصة المنشأة من التأمينات — تكلفةُ شركةٍ لا تمسّ صافي الموظف', self::GROUP_PAYROLL],

            /*
                قسم الغرامات — يُزرع بكوده لا بمعرّفٍ ثابت، كأخواته أعلاه.

                الحسابان يضعهما [AccountSeeder] في التنصيب الجديد، ويضعهما
                الترحيل `add_late_fee_accounts` في الشجرات القائمة. وإن
                سبق هذا السيدرُ أيَّهما أُنشئ الصفُّ بلا ربطٍ وظهر في
                الشاشة «غير مضبوط»، ثمّ يملؤه الترحيلُ أو إعادةُ التشغيل.
            */
            'late_fees_parent' => ['4212', 'الغرامات والتعويضات', 'الحساب الأب لكلّ ما يُحصَّل غرامةً أو تعويضاً — إيرادٌ عارض لا مبيعات', self::GROUP_PENALTIES],
            /*
                تمويل التقسيط — انظر `InstallmentFinancing` والترحيل
                `add_installment_financing_posting` الذي يُنشئ الحسابات.
            */
            'installment_deferred_profit' => ['2151', 'أرباح مرابحة مؤجلة', 'ربحُ التقسيط عند حفظ الاتفاقية — التزامٌ يُنقل إلى «أرباح المرابحة» مع كلّ تحصيل', self::GROUP_CLIENTS],
            'installment_profit_revenue' => ['4261', 'أرباح المرابحة', 'الربحُ المحقَّق من أقساطٍ حُصِّلت: من ح/ أرباح مرابحة مؤجلة إلى ح/ أرباح المرابحة', self::GROUP_SALES],
            'installment_admin_fees' => ['4262', 'رسوم إدارية على التقسيط', 'الرسوم الإدارية لاتفاقية التقسيط — إيرادٌ عند حفظها', self::GROUP_SALES],
            'installment_late_fees' => ['42121', 'غرامات تأخير الأقساط', 'الحساب الدائن في قيد غرامة القسط المتأخّر: من ح/ العميل إلى ح/ الغرامات', self::GROUP_PENALTIES],

            /*
                ضريبة الشحن حسابٌ مستقلّ عن ضريبة البنود.

                كان طرفا الضريبة يُقيَّدان على `vat_payable` معاً، فيقرأ
                المحاسبُ في القيد سطرين باسمٍ واحد («ضريبة المبيعات») ولا
                يُعرَف أيُّهما ضريبةُ البضاعة وأيُّهما ضريبةُ التوصيل — ولا
                يُفصَلان في الإقرار الضريبي إلّا بجردٍ يدويّ.

                **وهو اختياريّ.** متى بقي المفتاح «غير مضبوط» رجع القيدُ إلى
                `vat_payable` كما كان، فمنشأةٌ لا تريد الفصل لا يتغيّر عليها
                شيء — انظر `ResolvesAccountingAccounts::shippingVatAccountId()`.
            */
            // ضريبة ربح المرابحة والرسوم الإدارية — منفصلةٌ عن ضريبة البنود
            'installment_vat_payable' => ['2146', 'ضريبة التقسيط', 'ضريبة ربح المرابحة والرسوم الإدارية — تُفصَل عن ضريبة بنود الفواتير في القيد وفي الإقرار', self::GROUP_TAX],
            'shipping_vat_payable' => ['2145', 'ضريبة شحن مبيعات', 'الضريبة المحصَّلة على تكلفة الشحن وحدها — تُفصَل عن ضريبة البنود في القيد وفي الإقرار', self::GROUP_TAX],
            // ونظيرُها على جانب الشراء — ضريبةُ مدخلاتٍ لا مخرجات، فموضعُها مع «ضريبة المشتريات»
            'shipping_vat_paid' => ['2148', 'ضريبة شحن مشتريات', 'الضريبة المدفوعة على تكلفة الشحن وحدها — تُفصَل عن ضريبة البنود في القيد وفي الإقرار', self::GROUP_TAX],

            /*
                الطرف المقابل لفروق الجرد — بالكود لا بالمعرّف الثابت.

                كان المفتاح على `SystemAccountIds::COST_OF_SALES` (511)
                «لأنه ما كان مكتوباً في الشيفرة»، فيختلط عجزُ الجرد وزيادتُه
                بتكلفة بضاعةٍ بيعت فعلاً. 516 «عجز وفائض جرد المخزون» يزرعه
                [AccountSeeder] في التنصيب الجديد، والترحيل
                add_inventory_adjustment_account في الشجرات القائمة.
            */
            'inventory_adjustment' => ['516', 'تسوية الجرد', 'الطرف المقابل لفروق الجرد — حساب «عجز وفائض جرد المخزون»، لا تكلفة البضاعة المباعة', self::GROUP_INVENTORY],
        ];
    }

    /**
     * زرع المفاتيح المربوطة بالكود — يُستدعى بعد الحلقة الرئيسية.
     *
     * @return array{0:int,1:int,2:array<int,string>} [المُنشأ، الموجود سلفاً، بلا حساب]
     */
    private function seedByCode(): array
    {
        $definitions = $this->definitionsByCode();

        $accounts = Account::whereIn('code', collect($definitions)->map(fn (array $row) => $row[0])->all())
            ->pluck('id', 'code');

        $created = 0;
        $skipped = 0;
        $missing = [];

        foreach ($definitions as $key => [$code, $name, $description, $group]) {
            $existing = AccountingSetting::where('key', $key)->first();

            if ($existing) {
                if ($existing->group === null) {
                    $existing->update(['group' => $group]);
                }

                /*
                    صفٌّ قائمٌ بلا ربط: يُملأ من الكود إن وُجد حسابُه — كما
                    تفعل الحلقةُ الرئيسية بالمفاتيح المزروعة من الثوابت.

                    وكان هذا الفرعُ يتخطّاه، فمفتاحٌ أُنشئ قبل أن يوجد حسابُه
                    في الشجرة يبقى «غير مضبوط» أبداً وإن أُنشئ الحسابُ بعدُ
                    وأُعيد تشغيلُ السيدر. و`payroll_deductions` كان مثالاً
                    قبل إضافة 5310 للشجرة والترحيل الذي يربطه.

                    ولا مساسَ باختيار المستخدم: الفارغ لا اختيار فيه.
                */
                if ($existing->account_id === null && isset($accounts[$code])) {
                    $existing->update([
                        'account_id' => $accounts[$code],
                        'account_code' => $code,
                    ]);
                }

                $skipped++;

                continue;
            }

            $accountId = $accounts[$code] ?? null;

            if (! $accountId) {
                $missing[] = $key;
            }

            AccountingSetting::create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'group' => $group,
                'account_id' => $accountId,
                'account_code' => $accountId ? $code : null,
                'is_active' => true,
            ]);

            $created++;
        }

        return [$created, $skipped, $missing];
    }

    /**
     * المفتاح => [معرّف الحساب الافتراضي، الاسم، الوصف، المجموعة].
     *
     * @return array<string, array{0:int,1:string,2:string,3:string}>
     */
    public static function definitions(): array
    {
        return [
            // ── حسابات رئيسية ──
            'opening_balance' => [SystemAccountIds::OPENING_BALANCE, 'الرصيد الافتتاحي', 'الحساب المقابل لأرصدة بداية المدّة', self::GROUP_CORE],
            'assets' => [SystemAccountIds::ASSETS, 'الأصول', 'جذر شجرة الأصول', self::GROUP_CORE],
            'equity' => [SystemAccountIds::EQUITY, 'حقوق الملكية', 'جذر شجرة حقوق الملكية', self::GROUP_CORE],
            'revenue' => [SystemAccountIds::REVENUE, 'الإيرادات', 'جذر شجرة الإيرادات', self::GROUP_CORE],
            'expenses' => [SystemAccountIds::EXPENSES, 'المصروفات', 'جذر شجرة المصروفات', self::GROUP_CORE],

            // ── الخزائن والبنوك ──
            'main_treasury' => [SystemAccountIds::MAIN_TREASURY, 'الخزينة الرئيسية', 'الخزينة الافتراضية للقبض والصرف النقدي', self::GROUP_CASH],
            'cash_treasuries_parent' => [SystemAccountIds::CASH_TREASURIES_PARENT, 'الحساب الأب للخزائن النقدية', 'الأصول النقدية — تُنشأ تحته كل خزينة جديدة', self::GROUP_CASH],

            /*
                فرقُ درج الكاشير — صار له حسابان في الشجرة بعد أن كان مفتاحاً
                بلا حساب ينتظر أن يختار له المحاسبُ واحداً.

                واثنان لا واحد: الزيادةُ إيرادٌ عارض (4211) والعجزُ مصروف
                (59011)، ودمجُهما يُخفي حجمَ كلٍّ منهما في ميزان المراجعة —
                وهو بالضبط ما يُراد قياسه.

                ويبقى للمحاسب أن يوجّههما إلى غير الافتراضي من شاشة
                «الحسابات المحاسبية» إن أراد.
            */
            'pos_cash_over' => [SystemAccountIds::POS_CASH_OVER, 'فائض درج الكاشير', 'الحساب الدائن حين يزيد النقد الفعلي عن المتوقَّع عند إقفال جلسة نقطة بيع', self::GROUP_CASH],
            'pos_cash_short' => [SystemAccountIds::POS_CASH_SHORT, 'عجز درج الكاشير', 'الحساب المدين حين ينقص النقد الفعلي عن المتوقَّع عند إقفال جلسة نقطة بيع', self::GROUP_CASH],
            'bank_accounts_parent' => [SystemAccountIds::BANK_ACCOUNTS_PARENT, 'الحساب الأب للحسابات البنكية', 'تُنشأ تحته كل حساب بنكي جديد', self::GROUP_CASH],

            // ── المبيعات والإيرادات ──
            'sales' => [SystemAccountIds::SALES, 'حساب المبيعات', 'الإيراد المسجَّل في قيود فواتير البيع', self::GROUP_SALES],
            'sales_returns' => [SystemAccountIds::SALES_RETURNS, 'مردودات المبيعات', 'الحساب المدين في قيود مرتجع البيع', self::GROUP_SALES],
            'sales_revenue_parent' => [SystemAccountIds::SALES_REVENUE_PARENT, 'الحساب الأب لإيرادات المبيعات', 'تُنشأ تحته حسابات إيرادات المبيعات الفرعية', self::GROUP_SALES],
            'other_revenue_parent' => [SystemAccountIds::OTHER_REVENUE_PARENT, 'الحساب الأب للإيرادات الأخرى', 'إيرادات خارج نشاط البيع الأساسي', self::GROUP_SALES],
            'cost_of_sales' => [SystemAccountIds::COST_OF_SALES, 'تكلفة المبيعات', 'تكلفة البضاعة المباعة في قيود البيع', self::GROUP_SALES],
            'cost_of_sales_root' => [SystemAccountIds::COST_OF_SALES_ROOT, 'جذر شجرة تكلفة المبيعات', 'الحساب الأب الذي تُنشأ تحته حسابات التكلفة الفرعية', self::GROUP_SALES],
            'allowed_discount' => [SystemAccountIds::ALLOWED_DISCOUNT, 'خصم مسموح به', 'الخصم الممنوح للعميل عند السداد — مصروف بطبيعة مدينة', self::GROUP_SALES],
            'sales_discount' => [SystemAccountIds::SALES_DISCOUNT, 'خصم المبيعات', 'الخصم على بنود فاتورة البيع — دائن يخفّض الإيراد', self::GROUP_SALES],
            'sales_shipping' => [SystemAccountIds::SALES_SHIPPING, 'شحن المبيعات', 'تكلفة شحن الفاتورة المحمَّلة على المنشأة', self::GROUP_SALES],

            // ── المشتريات والموردين ──
            'purchases' => [SystemAccountIds::PURCHASES, 'حساب المشتريات', 'الحساب المدين في قيود فواتير الشراء', self::GROUP_PURCHASES],
            'suppliers' => [SystemAccountIds::SUPPLIERS, 'حساب الموردين', 'الحساب الأب لذمم الموردين', self::GROUP_PURCHASES],
            /*
                `purchase_discount` ليس هنا عمداً — انظر `definitionsByCode()`.

                `SystemAccountIds::PURCHASE_DISCOUNT` و`ALLOWED_DISCOUNT`
                رقمٌ واحد (52)، فزرعُه من الثابت يربط خصمَ الشراء بحساب
                خصم البيع نفسِه: مصروفٌ منحناه للعميل ومخفِّضُ كلفةٍ منحه
                لنا المورد في وعاءٍ واحد يتقاصّان فيه. فيُزرع بالكود 428
                («خصم مكتسب») حساباً مستقلّاً.
            */
            'purchase_shipping' => [SystemAccountIds::PURCHASE_SHIPPING, 'تكاليف شحن المشتريات', 'أجرة إيصال البضاعة المشتراة — مدين يضاف إلى الكلفة', self::GROUP_PURCHASES],
            'supplier_wallets_parent' => [SystemAccountIds::SUPPLIER_WALLETS_PARENT, 'دفعات مقدَّمة للموردين', 'الحساب الأب لمحافظ الموردين — يُنشأ تحته حسابُ كل مورد؛ أصلٌ لنا لديه تُخصَم منه فواتير الشراء', self::GROUP_PURCHASES],

            // ── المخزون والتصنيع ──
            'inventory' => [SystemAccountIds::INVENTORY, 'حساب المخزون', 'مخزون البضاعة الجاهزة', self::GROUP_INVENTORY],
            'inventory_parent' => [SystemAccountIds::INVENTORY, 'الحساب الأب لشجرة المخزون', 'يُستخدم في تقرير ميزان مراجعة المخزون', self::GROUP_INVENTORY],
            'raw_materials_inventory' => [SystemAccountIds::RAW_MATERIALS_INVENTORY, 'مخزون المواد الخام', 'مواد التصنيع قبل التشغيل', self::GROUP_INVENTORY],
            'work_in_progress_inventory' => [SystemAccountIds::WORK_IN_PROGRESS_INVENTORY, 'مخزون تحت التشغيل', 'قيمة أوامر التصنيع الجارية', self::GROUP_INVENTORY],
            'finished_goods_inventory' => [SystemAccountIds::FINISHED_GOODS_INVENTORY, 'مخزون البضاعة الجاهزة', 'ناتج أوامر التصنيع المكتملة', self::GROUP_INVENTORY],
            'waste_inventory' => [SystemAccountIds::WASTE_INVENTORY, 'مخزون المواد الهالكة', 'الهالك الناتج عن التصنيع', self::GROUP_INVENTORY],
            'manufacturing_overhead' => [SystemAccountIds::MANUFACTURING_OVERHEAD, 'تكاليف التشغيل والتصنيع', 'المصروفات المحمَّلة على أوامر التصنيع', self::GROUP_INVENTORY],
            /*
                انحراف تكاليف الإنتاج.

                المفتاح تُنشئه هجرة `Modules/Manufacturing/…_000001` وحدها،
                وتبحث عن حساب اسمه يحوي «انحراف» فلا تجد واحداً في الشجرة،
                فتترك الصفّ بلا ربط. ومسار الإصلاح أدناه — الذي يملأ صفّاً
                قائماً بلا ربط من الثابت — لا يبلغه لأنه يمرّ على `definitions()`
                وحدها. فيبقى فارغاً إلى الأبد مهما أُعيد تشغيل السيدر، ويسقط
                كل إتمام أمر تصنيع بـ«الحساب غير مربوط».

                بإدراجه هنا يُربط تلقائياً على التركيبات الجديدة، ويُصلَح
                على القائمة عند أول تشغيل للسيدر.
            */
            'production_variance' => [SystemAccountIds::PRODUCTION_VARIANCE, 'انحراف تكاليف الإنتاج', 'الفرق بين التكلفة المحمَّلة على أمر التصنيع وقيمة ما دخل المخزون — يُقفل به «مخزون تحت التشغيل»', self::GROUP_INVENTORY],

            // حساب كل مستودع يُنشأ تلقائياً عند أول حركة عليه. كان يُنشأ بلا أب
            // فيُعلَّق في جذر الشجرة خارج شجرة المخزون — فيغيب عن ميزان مراجعة
            // المخزون وعن ترحيل الأرصدة الهرمي
            'warehouse_accounts_parent' => [SystemAccountIds::INVENTORY, 'الحساب الأب لحسابات المستودعات', 'يُنشأ تحته حساب كل مستودع تلقائياً', self::GROUP_INVENTORY],

            // ── العملاء والذمم ──
            'clients_account' => [SystemAccountIds::CLIENTS_ACCOUNT, 'حساب العملاء', 'الحساب الأب لذمم العملاء', self::GROUP_CLIENTS],
            'cash_client' => [SystemAccountIds::CASH_CLIENT, 'العميل النقدي', 'عميل البيع النقدي الافتراضي', self::GROUP_CLIENTS],
            'other_debtor_parties' => [SystemAccountIds::OTHER_DEBITOR_PARTIES, 'أطراف مدينة أخرى', 'ذمم مدينة خارج العملاء', self::GROUP_CLIENTS],
            'client_wallets_parent' => [SystemAccountIds::CLIENT_WALLETS_PARENT, 'أرصدة محافظ العملاء', 'الحساب الأب لمحافظ العملاء — يُنشأ تحته حسابُ كل عميل؛ التزامٌ علينا يُخصَم منه سدادُ فواتيره', self::GROUP_CLIENTS],
            'other_creditor_parties' => [SystemAccountIds::OTHER_CREDITOR_PARTIES, 'أطراف دائنة أخرى', 'ذمم دائنة خارج الموردين', self::GROUP_CLIENTS],
            'others_account' => [SystemAccountIds::OTHER_DEBITOR_PARTIES, 'حساب أخرى', 'الحساب العام للأطراف غير المصنَّفة', self::GROUP_CLIENTS],
            'shipping_companies' => [SystemAccountIds::SHIPPING_COMPANIES, 'شركات الشحن', 'ذمم شركات الشحن والتوصيل', self::GROUP_CLIENTS],
            'bad_debts' => [SystemAccountIds::BAD_DEBTS, 'الديون المعدومة', 'الذمم المشطوبة نهائياً', self::GROUP_CLIENTS],
            'allowance_for_doubtful_debts' => [SystemAccountIds::ALLOWANCE_FOR_DOUBTFUL_DEBTS, 'مخصّص الديون المشكوك فيها', 'المخصّص المقابل للذمم المتعثّرة', self::GROUP_CLIENTS],

            // ── الضرائب ──
            'vat_payable' => [SystemAccountIds::VAT_PAYABLE, 'ضريبة القيمة المضافة المستحقّة', 'الضريبة المحصَّلة من العملاء', self::GROUP_TAX],
            'vat_paid' => [SystemAccountIds::VAT_PAID, 'ضريبة القيمة المضافة المدفوعة', 'الضريبة المسدَّدة للموردين', self::GROUP_TAX],

            // ── المصروفات ──
            'admin_expenses_parent' => [SystemAccountIds::ADMIN_EXPENSES_PARENT, 'مصروفات إدارية وعمومية', 'الحساب الأب للمصروفات الإدارية', self::GROUP_EXPENSES],
            'marketing_expenses_parent' => [SystemAccountIds::MARKETING_EXPENSES_PARENT, 'مصروفات التسويق والمبيعات', 'الحساب الأب لمصروفات التسويق', self::GROUP_EXPENSES],
            'transport_expenses_parent' => [SystemAccountIds::TRANSPORT_EXPENSES_PARENT, 'مصروفات النقل والمواصلات', 'الحساب الأب لمصروفات النقل', self::GROUP_EXPENSES],
            'finance_expenses_parent' => [SystemAccountIds::FINANCE_EXPENSES_PARENT, 'مصروفات التمويل', 'فوائد ورسوم التمويل والبنوك', self::GROUP_EXPENSES],
            'manufacturing_expenses_parent' => [SystemAccountIds::MANUFACTURING_EXPENSES_PARENT, 'مصروفات التصنيع', 'الحساب الأب لمصروفات التصنيع', self::GROUP_EXPENSES],
            'other_expenses_parent' => [SystemAccountIds::OTHER_EXPENSES_PARENT, 'مصروفات أخرى', 'الحساب الأب للمصروفات غير المصنَّفة', self::GROUP_EXPENSES],

            // ── الرواتب والموظفين ──
            'salaries_wages_parent' => [SystemAccountIds::SALARIES_WAGES_PARENT, 'الرواتب والأجور', 'الحساب الأب للرواتب والأجور', self::GROUP_PAYROLL],
            'employees_salaries_expense' => [SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE, 'مصروف رواتب الموظفين', 'المصروف الفعلي المحمَّل عند احتساب الرواتب', self::GROUP_PAYROLL],
            'employee_commissions' => [SystemAccountIds::Employee_Commissions, 'عمولات الموظفين', 'عمولات المبيعات المستحقّة للموظفين', self::GROUP_PAYROLL],
            'employee_advances_parent' => [SystemAccountIds::EMPLOYEE_ADVANCES_PARENT, 'الحساب الأب لسلف الموظفين', 'السلف المصروفة للموظفين تحت الأصول', self::GROUP_PAYROLL],
            'employee_payables_parent' => [SystemAccountIds::EMPLOYEE_PAYABLES_PARENT, 'الحساب الأب لمستحقات الموظفين', 'رواتب ومستحقات الموظفين تحت الخصوم', self::GROUP_PAYROLL],

            // ── الأصول الثابتة والإهلاك ──
            'fixed_assets' => [SystemAccountIds::FIXED_ASSETS, 'الأصول الثابتة', 'جذر شجرة الأصول الثابتة', self::GROUP_ASSETS],
            'accumulated_depreciation' => [SystemAccountIds::ACCUMULATED_DEPRECIATION, 'مجمّع إهلاك الأصول الثابتة', 'الحساب المقابل لقيد الإهلاك', self::GROUP_ASSETS],
            'depreciation_expense' => [SystemAccountIds::DEPRECIATION_EXPENSE, 'مصروف الإهلاك', 'الإهلاك المحمَّل على الفترة', self::GROUP_ASSETS],
            'furniture' => [SystemAccountIds::FURNITURE, 'أثاث', 'أصول ثابتة — أثاث', self::GROUP_ASSETS],
            'equipment' => [SystemAccountIds::EQUIPMENT, 'الأجهزة والمعدات', 'أصول ثابتة — أجهزة ومعدّات', self::GROUP_ASSETS],
            'vehicles' => [SystemAccountIds::VEHICLES, 'وسائل النقل', 'أصول ثابتة — سيارات ووسائل نقل', self::GROUP_ASSETS],
            'buildings' => [SystemAccountIds::BUILDINGS, 'مباني', 'أصول ثابتة — مباني', self::GROUP_ASSETS],
            'lands' => [SystemAccountIds::LANDS, 'أراضي', 'أصول ثابتة — أراضي', self::GROUP_ASSETS],
            'computers' => [SystemAccountIds::COMPUTERS, 'أجهزة كمبيوتر', 'أصول ثابتة — حواسيب', self::GROUP_ASSETS],
            'decorations' => [SystemAccountIds::DECORATIONS, 'الديكورات', 'أصول ثابتة — ديكورات', self::GROUP_ASSETS],
            'other_fixed_assets' => [SystemAccountIds::OTHER_FIXED_ASSETS, 'أصول ثابتة أخرى', 'أصول ثابتة غير مصنَّفة', self::GROUP_ASSETS],

            // ── الشيكات: أوراق القبض ──
            'cheques_receivable_parent' => [SystemAccountIds::CHEQUES_RECEIVABLE_PARENT, 'أوراق القبض (الأب)', 'الحساب الأب لشيكات العملاء المستلمة', self::GROUP_CHEQUES_IN],
            'cheques_for_collection' => [SystemAccountIds::CHEQUES_FOR_COLLECTION, 'شيكات برسم التحصيل', 'شيكات مستلمة لم تُودَع بعد', self::GROUP_CHEQUES_IN],
            'cheques_under_collection' => [SystemAccountIds::CHEQUES_UNDER_COLLECTION, 'شيكات تحت التحصيل', 'شيكات أُودعت بانتظار التحصيل', self::GROUP_CHEQUES_IN],
            'cheques_collected' => [SystemAccountIds::CHEQUES_COLLECTED, 'شيكات محصَّلة', 'شيكات دخلت قيمتها الحساب البنكي', self::GROUP_CHEQUES_IN],
            'cheques_returned' => [SystemAccountIds::CHEQUES_RETURNED, 'شيكات مرتجعة', 'شيكات ارتدّت من البنك', self::GROUP_CHEQUES_IN],
            'cheques_deferred_received' => [SystemAccountIds::CHEQUES_DEFERRED_RECEIVED, 'شيكات مؤجلة مستلمة', 'شيكات آجلة لم يحن استحقاقها', self::GROUP_CHEQUES_IN],

            // ── الشيكات: أوراق الدفع ──
            'cheques_payable_parent' => [SystemAccountIds::CHEQUES_PAYABLE_PARENT, 'أوراق الدفع (الأب)', 'الحساب الأب للشيكات الصادرة', self::GROUP_CHEQUES_OUT],
            'cheques_for_payment' => [SystemAccountIds::CHEQUES_FOR_PAYMENT, 'شيكات برسم الدفع', 'شيكات محرَّرة لم تُسلَّم بعد', self::GROUP_CHEQUES_OUT],
            'cheques_issued' => [SystemAccountIds::CHEQUES_ISSUED, 'شيكات صادرة', 'شيكات سُلّمت للمستفيدين', self::GROUP_CHEQUES_OUT],
            'cheques_deferred_issued' => [SystemAccountIds::CHEQUES_DEFERRED_ISSUED, 'شيكات مؤجلة صادرة', 'شيكات صادرة آجلة الاستحقاق', self::GROUP_CHEQUES_OUT],
            'cheques_paid' => [SystemAccountIds::CHEQUES_PAID, 'شيكات مسدَّدة', 'شيكات خُصمت من الحساب البنكي', self::GROUP_CHEQUES_OUT],
            'cheques_bounced' => [SystemAccountIds::CHEQUES_BOUNCED, 'شيكات مرفوضة', 'شيكات صادرة رفضها البنك', self::GROUP_CHEQUES_OUT],

            // ── الشحن ──
            'shipping_revenue' => [SystemAccountIds::SHIPPING_REVENUE, 'إيرادات الشحن', 'ما يُحصَّل من العميل مقابل التوصيل', self::GROUP_SHIPPING],
            'shipping_revenue_returns' => [SystemAccountIds::SHIPPING_REVENUE_RETURNS, 'مردود إيرادات الشحن', 'إيراد شحن مرتدّ مع مرتجع البيع', self::GROUP_SHIPPING],

            // ── نظام المكافآت ──
            'rewards_expenses_parent' => [SystemAccountIds::REWARDS_EXPENSES_PARENT, 'مصروفات نظام المكافآت', 'الحساب الأب لمصروفات المكافآت', self::GROUP_REWARDS],
            'rewards_cash_expense' => [SystemAccountIds::REWARDS_CASH_EXPENSE, 'مكافآت نقدية مستبدَلة', 'قيمة المكافآت المستبدَلة نقداً', self::GROUP_REWARDS],
            'rewards_discount_expense' => [SystemAccountIds::REWARDS_DISCOUNT_EXPENSE, 'خصومات المكافآت', 'الخصم الممنوح مقابل نقاط', self::GROUP_REWARDS],
            'rewards_voucher_expense' => [SystemAccountIds::REWARDS_VOUCHER_EXPENSE, 'قسائم شرائية مستبدَلة', 'قيمة القسائم التي استُخدمت', self::GROUP_REWARDS],
            'rewards_gift_product_expense' => [SystemAccountIds::REWARDS_GIFT_PRODUCT_EXPENSE, 'هدايا ومنتجات مستبدَلة', 'تكلفة المنتجات الممنوحة كمكافأة', self::GROUP_REWARDS],
            'rewards_other_expense' => [SystemAccountIds::REWARDS_OTHER_EXPENSE, 'مصروفات مكافآت أخرى', 'مصروفات مكافآت غير مصنَّفة', self::GROUP_REWARDS],
            'rewards_liability_parent' => [SystemAccountIds::REWARDS_LIABILITY_PARENT, 'التزامات نظام المكافآت', 'الحساب الأب لالتزامات المكافآت', self::GROUP_REWARDS],
            'rewards_pending_liability' => [SystemAccountIds::REWARDS_PENDING_LIABILITY, 'مكافآت معلَّقة مستحقّة', 'النقاط المستحقّة ولم تُستبدَل بعد', self::GROUP_REWARDS],
            'rewards_voucher_liability' => [SystemAccountIds::REWARDS_VOUCHER_LIABILITY, 'قسائم مكافآت صادرة', 'القسائم الصادرة ولم تُستخدَم', self::GROUP_REWARDS],
        ];
    }

    /**
     * مفاتيح بلا حساب طبيعي في الشجرة أصلاً — مفاهيم إدارية جديدة، لا
     * حسابات محاسبية قائمة يُشتقّ لها كودٌ أو ثابت. الصيغة: المفتاح =>
     * [الاسم، الوصف، المجموعة].
     *
     * تُزرع بلا ربطٍ دائماً — القرار بأي حسابٍ تذهب إليه للمحاسب وحده من
     * شاشة «الحسابات المحاسبية»، لا لتخمينٍ برمجي.
     *
     * @return array<string, array{0:string,1:string,2:string}>
     */
    private function definitionsUnlinked(): array
    {
        /*
            حسابُ كلِّ نوع خصمية — اختياريّ: غيرُ المربوط يسقط على
            `payroll_deductions` العام (`DeductionAccountingService`). والأنواعُ
            من `Deduction::types()` كي لا تنحرف القائمةُ عن النموذج.
        */
        $deductionTypes = [];
        foreach (\App\Models\Deduction::types() as $type => $label) {
            $deductionTypes['payroll_deduction_'.$type] = [
                'خصميات '.$label,
                'الحساب الدائن في قيد اعتماد خصمية «'.$label.'» — إن تُرك فارغاً استُعمل حساب «خصميات الموظفين» العام',
                self::GROUP_PAYROLL,
            ];
        }

        return $deductionTypes + [
            'customer_settlement_account' => ['تسوية أرصدة العملاء', 'الحساب المقابل عند تسوية رصيد عميل من شاشة «محفظة العملاء» في المبيعات', self::GROUP_CLIENTS],

            /*
                البنكُ الذي تُرحَّل إليه مدفوعاتُ البطاقات في نقطة البيع.

                كان مفتاحاً في `pos_general_settings.default_bank_account_id`
                وحده — أي إعداداً لوحدةٍ بعينها لحسابٍ محاسبيّ، فلا يظهر في
                شاشة «الحسابات المحاسبية» ولا يعرف المحاسبُ أنه مضبوطٌ في
                مكانٍ آخر. وهو هنا الافتراضُ العام، ويبقى للفرع أن يخصَّه
                بعمودِه إن أراد بنكاً غيره.
            */
            'pos_default_bank' => ['البنك الافتراضي لنقاط البيع', 'الحساب البنكي الذي تُرحَّل إليه مدفوعاتُ البطاقات في نقطة البيع', self::GROUP_CASH],

            /*
                الحجوزات.

                ستّةُ مفاتيحَ تُزرع **بلا ربط** كسائر هذه المجموعة: لا حسابَ
                في الشجرة القياسية يقابل «عربون حجز» أو «رسم إلغاء»، والقرارُ
                بأيّ حسابٍ تذهب إليه للمحاسب من شاشة «الحسابات المحاسبية».

                و`reservation_deposits` أهمُّها وأدقُّها: العربونُ المقبوض قبل
                أداء الخدمة **التزامٌ على المنشأة لا إيراد** — خدمةٌ لم
                تُؤدَّ بعدُ ومالٌ قد يُردّ. فترحيلُه إلى الإيرادات مباشرةً
                يضخّم دخلَ الفترة بما لم يُكتسَب، ويُظهر ربحاً يعود بالردّ.
                والإيرادُ يُعترَف به عند اكتمال الخدمة، وحينها تُصدَر
                الفاتورةُ ويُقفَل الالتزام.
            */
            'reservation_revenue' => ['إيرادات الحجوزات', 'الإيراد المعترَف به عند اكتمال خدمة الحجز', self::GROUP_SALES],
            'reservation_deposits' => ['عرابين الحجوزات المقبوضة', 'التزامٌ على المنشأة — عربونٌ قُبض عن خدمةٍ لم تُؤدَّ بعد', self::GROUP_CLIENTS],
            'reservation_discount' => ['خصم الحجوزات', 'الخصم الممنوح على قيمة الحجز — يخفّض الإيراد', self::GROUP_SALES],
            'reservation_cancellation_fee' => ['إيراد رسوم إلغاء الحجوزات', 'ما يُقتطع من العربون عند الإلغاء المتأخّر', self::GROUP_SALES],
            'reservation_refund' => ['ردّ عرابين الحجوزات', 'الحساب المقابل عند ردّ عربونٍ للعميل', self::GROUP_CLIENTS],
            'reservation_rounding' => ['فروق تقريب الحجوزات', 'فروقُ الكسور بين إجمالي الحجز وما قُبض فعلاً', self::GROUP_SALES],
        ];
    }

    /**
     * زرع المفاتيح بلا حساب — يُستدعى بعد الحلقة الرئيسية.
     *
     * @return array{0:int,1:int,2:array<int,string>} [المُنشأ، الموجود سلفاً، المُنشأ حديثاً بلا ربط]
     */
    private function seedUnlinked(): array
    {
        $created = 0;
        $skipped = 0;
        $missing = [];

        foreach ($this->definitionsUnlinked() as $key => [$name, $description, $group]) {
            if (AccountingSetting::where('key', $key)->exists()) {
                $skipped++;

                continue;
            }

            AccountingSetting::create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'group' => $group,
                'account_id' => null,
                'account_code' => null,
                'is_active' => true,
            ]);

            $created++;
            $missing[] = $key;
        }

        return [$created, $skipped, $missing];
    }

    public function run(): void
    {
        $definitions = self::definitions();

        // الأكواد تُقرأ دفعةً واحدة: ثمانون استعلاماً مفرداً بلا داعٍ
        $codes = Account::whereIn('id', collect($definitions)->map(fn (array $row) => $row[0])->unique()->values()->all())
            ->pluck('code', 'id');

        $created = 0;
        $skipped = 0;
        $missing = [];

        foreach ($definitions as $key => [$accountId, $name, $description, $group]) {
            $existing = AccountingSetting::where('key', $key)->first();

            if ($existing) {
                // ضبط المستخدم لا يُمحى بإعادة تشغيل السيدر — تُستكمل
                // المجموعة فقط إن كان الصفّ من نسخة أقدم بلا مجموعة
                if ($existing->group === null) {
                    $existing->update(['group' => $group]);
                }

                /* صفٌّ قائمٌ بلا ربط: يُملأ من الثابت إن وُجد حسابُه.

                   وهذا ليس مساساً باختيار المستخدم — الفارغ لا اختيار فيه.
                   وبه تُغلق ثغرةُ الترتيب في التركيب الجديد: هجرةٌ تُنشئ
                   المفتاح قبل أن توجد الحسابات، ثمّ تتخطّاه هذه الحلقة
                   لأنّه \موجود\ فيبقى بلا ربط أبداً. */
                if ($existing->account_id === null && $codes->has($accountId)) {
                    $existing->update([
                        'account_id' => $accountId,
                        'account_code' => $codes[$accountId] !== null ? (string) $codes[$accountId] : null,
                    ]);
                }

                $skipped++;

                continue;
            }

            $exists = $codes->has($accountId);

            if (! $exists) {
                // الثابت يشير إلى حساب غير موجود في هذا التركيب: يُنشأ الإعداد
                // بلا ربط فيظهر في الشاشة «غير مضبوط» بدل أن يغيب صفّه
                $missing[] = $key;
            }

            AccountingSetting::create([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'group' => $group,
                'account_id' => $exists ? $accountId : null,
                'account_code' => $exists && $codes[$accountId] !== null ? (string) $codes[$accountId] : null,
                'is_active' => true,
            ]);

            $created++;
        }

        // مفاتيح الخصم والشحن — تُزرع بكودها لا بمعرّفٍ ثابت
        [$createdByCode, $skippedByCode, $missingByCode] = $this->seedByCode();

        $created += $createdByCode;
        $skipped += $skippedByCode;
        $missing = array_merge($missing, $missingByCode);

        // مفاتيح بلا حساب طبيعي في الشجرة — تُزرع بلا ربطٍ دائماً
        [$createdUnlinked, $skippedUnlinked, $missingUnlinked] = $this->seedUnlinked();

        $created += $createdUnlinked;
        $skipped += $skippedUnlinked;
        $missing = array_merge($missing, $missingUnlinked);

        /*
            أنواعُ الخصميات: حسابٌ فرعيٌّ لكلِّ نوعٍ تحت الحساب المربوط بـ
            `payroll_deductions` ويُربط مفتاحُه — لا يمسّ مفتاحاً مربوطاً،
            ولا يُنشئ شيئاً إن لم يُربط العام. (المفاتيحُ أعلاه تُزرع بلا ربط.)
        */
        $deductionTypes = \App\Support\DeductionTypeAccounts::ensure();
        $missing = array_values(array_diff($missing, array_keys($deductionTypes['linked'])));
        if ($deductionTypes['skipped']) {
            $this->command?->warn($deductionTypes['skipped']);
        }

        // خريطة `AccountingSettings` مخزَّنة في الكاش ساعةً كاملة. الشاشة تُبطلها
        // عند الحفظ، والسيدر كان يتركها فتظلّ المفاتيح المزروعة حديثاً «غير
        // مربوطة» في نظر النظام حتى تنقضي المدّة
        AccountingSettings::forget();

        $this->command?->info("إعدادات الحسابات المحاسبية: {$created} إعداداً جديداً، {$skipped} موجوداً سلفاً.");

        if ($missing !== []) {
            $this->command?->warn(
                'إعدادات بلا حساب مقابل في شجرة الحسابات ('.count($missing).'): '.implode('، ', $missing)
            );
        }
    }
}
