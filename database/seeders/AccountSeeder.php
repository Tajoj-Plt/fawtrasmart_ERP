<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    /**
     * وضعُ القراءة: تُجمع تعريفاتُ الشجرة ولا يُكتب في قاعدة البيانات شيء.
     *
     * هذه البذرة مُستبدِلة لا مُضيفة — تبدأ بـ`Account::query()->delete()`،
     * فلا سبيل لتشغيلها على قاعدةٍ عامرة. لكنّ *قائمتها* هي التعريف الوحيد
     * لشجرة الحسابات القياسية في النظام، وكلُّ إصدارٍ يضيف إليها حسابات
     * جديدة (التزامات المكافآت، محافظ العملاء، …) لا تصل إلى تركيبٍ قائم.
     * فيقرأها [MissingAccountsSeeder] من هنا ويزرع الناقصَ وحده.
     *
     * @see MissingAccountsSeeder
     */
    protected bool $collecting = false;

    /** @var array<int, array<string, mixed>> */
    protected array $collected = [];

    /**
     * تعريفاتُ شجرة الحسابات القياسية كبيانات — بلا أي كتابة.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $seeder = new static;
        $seeder->collecting = true;
        $seeder->run();

        return $seeder->collected;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (! $this->collecting) {
                // تعطيل فحص Foreign Keys
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                // حذف جميع الحسابات الموجودة
                Account::query()->delete();

                // إعادة تعيين Auto Increment
                DB::statement('ALTER TABLE accounts AUTO_INCREMENT = 1;');
            }

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                    الحسابات الرئيسية (المستوى 0)                ║
            // ║                         IDs: 1-5                               ║
            // ╚════════════════════════════════════════════════════════════════╝

            $this->createAccount(1, 'الأصول', '1', null, 'main', 'assets', 'debit', true);
            $this->createAccount(2, 'الخصوم', '2', null, 'main', 'liabilities', 'credit', true);
            $this->createAccount(3, 'حقوق الملكية', '3', null, 'main', 'equity', 'credit', true);
            $this->createAccount(4, 'الإيرادات', '4', null, 'main', 'revenues', 'credit', true);
            $this->createAccount(5, 'المصروفات', '5', null, 'main', 'expenses', 'debit', true);

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                         1. الأصول                              ║
            // ╚════════════════════════════════════════════════════════════════╝

            // ┌────────────────────────────────────────────────────────────────┐
            // │                    1.1 الأصول الثابتة (11)                      │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(6, 'الأصول الثابتة', '11', 1, 'sub', 'assets', 'debit', true);

            $this->createAccount(8, 'أثاث', '111', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(9, 'الأجهزة والمعدات', '112', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(10, 'وسائل النقل', '113', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(11, 'مباني', '114', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(12, 'أراضي', '115', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(66, 'أجهزة كمبيوتر', '116', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(67, 'الديكورات', '117', 6, 'sub', 'assets', 'debit', false);
            $this->createAccount(68, 'أصول ثابتة أخرى', '118', 6, 'sub', 'assets', 'debit', false);

            // مجمع إهلاك الأصول الثابتة
            $this->createAccount(69, 'مجمع إهلاك الأصول الثابتة', '119', 6, 'sub', 'assets', 'credit', true);
            $this->createAccount(70, 'مجمع إهلاك المباني', '1191', 69, 'sub', 'assets', 'credit', false);
            $this->createAccount(71, 'مجمع إهلاك وسائل النقل', '1192', 69, 'sub', 'assets', 'credit', false);
            $this->createAccount(72, 'مجمع إهلاك الأثاث', '1193', 69, 'sub', 'assets', 'credit', false);
            $this->createAccount(73, 'مجمع إهلاك الأجهزة', '1194', 69, 'sub', 'assets', 'credit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                   1.2 الأصول المتداولة (12)                     │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(7, 'الأصول المتداولة', '12', 1, 'sub', 'assets', 'debit', true);

            // الخزينة (121)
            $this->createAccount(13, 'الخزينة', '121', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(14, 'الخزينة الرئيسية', '1211', 13, 'sub', 'assets', 'debit', true);
            $this->createAccount(74, 'خزينة فرعية 1', '1212', 13, 'sub', 'assets', 'debit', false);
            $this->createAccount(75, 'خزينة فرعية 2', '1213', 13, 'sub', 'assets', 'debit', false);
            $this->createAccount(76, 'الخزينة بالعملات الأجنبية', '1214', 13, 'sub', 'assets', 'debit', false);

            // البنك (122)
            $this->createAccount(15, 'البنك', '122', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(77, 'بنك الراجحي', '1221', 15, 'sub', 'assets', 'debit', false);
            $this->createAccount(78, 'بنك الأهلي', '1222', 15, 'sub', 'assets', 'debit', false);
            $this->createAccount(79, 'بنك الرياض', '1223', 15, 'sub', 'assets', 'debit', false);
            $this->createAccount(80, 'بنك سامبا', '1224', 15, 'sub', 'assets', 'debit', false);
            $this->createAccount(81, 'بنوك أخرى', '1225', 15, 'sub', 'assets', 'debit', false);

            // المخزون (123)
            $this->createAccount(16, 'المخزون', '123', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(82, 'مخزون البضاعة', '1231', 16, 'sub', 'assets', 'debit', true);
            $this->createAccount(83, 'مخزون المواد الخام', '1232', 16, 'sub', 'assets', 'debit', false);
            $this->createAccount(3025, 'مواد خام', '1233', 16, 'sub', 'assets', 'debit', false); // نقل من المصروفات
            $this->createAccount(84, 'مخزون قطع الغيار', '1234', 16, 'sub', 'assets', 'debit', false);
            $this->createAccount(85, 'مخزون تحت التشغيل', '1235', 16, 'sub', 'assets', 'debit', false);
            $this->createAccount(86, 'البضاعة المرتجعة', '1236', 16, 'sub', 'assets', 'debit', false);
            $this->createAccount(3201, 'مخزون تحت التشغيل - تصنيع', '1237', 16, 'sub', 'assets', 'debit', false);
            $this->createAccount(3202, 'مخزون المواد الهالكة', '1238', 16, 'sub', 'assets', 'debit', false);

            // المدينون (124)
            $this->createAccount(17, 'المدينون', '124', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(18, 'العملاء', '1241', 17, 'sub', 'assets', 'debit', true);
            $this->createAccount(482, 'عميل نقدي', '1241408', 18, 'main', 'assets', 'debit', false);
            $this->createAccount(20, 'أطراف مدينة أخرى', '1242', 17, 'sub', 'assets', 'debit', false);
            /*
                «سلف الموظفين» هو الحسابُ الأبُ الوحيد للسلف (مفتاح employee_advances_parent)
                — يُفتح تحته حسابُ سلفِ كلِّ موظف. أُزيل من هنا «سلف» (1244) و«السلف»
                (1210): حسابان لنفس الغرض بلا أيّ مرجعٍ في الشيفرة، فكانت الشجرةُ
                الجديدة تُولد بثلاثة آباءٍ للسلف ولا يُعرف أيُّها المعتمد. والترحيل
                merge_duplicate_payroll_accounts يحذف الفارغ منها في الشجرات القائمة.
            */
            $this->createAccount(87, 'سلف الموظفين', '1243', 17, 'sub', 'assets', 'debit', true);
            $this->createAccount(88, 'مصروفات مدفوعة مقدماً', '1245', 17, 'sub', 'assets', 'debit', false);
            $this->createAccount(89, 'تأمينات مستردة', '1246', 17, 'sub', 'assets', 'debit', false);
            $this->createAccount(90, 'إيرادات مستحقة', '1247', 17, 'sub', 'assets', 'debit', false);
            $this->createAccount(3384, 'ذمم مكافآت على الموظفين', '1248', 17, 'sub', 'assets', 'debit', false);
            // محافظ الموردين — مالٌ سلّمناه للمورد ولم نستهلكه بعد، فهو أصلٌ
            // لنا لديه؛ موضعُه هنا إلى جانب «مصروفات مدفوعة مقدماً» وكلاهما
            // دفعٌ مقدَّم. ولكل مورد حسابٌ تحته يُنشأ عند أوّل دفعة.
            $this->createAccount(3432, 'دفعات مقدَّمة للموردين', '1249', 17, 'sub', 'assets', 'debit', true);
            // عهد الموظفين (125)
            $this->createAccount(21, 'عهد الموظفين', '125', 7, 'sub', 'assets', 'debit', true);

            // أوراق القبض (126)
            $this->createAccount(22, 'أوراق القبض', '126', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(3133, 'شيكات برسم التحصيل', '1261', 22, 'sub', 'assets', 'debit', true);
            $this->createAccount(3134, 'شيكات تحت التحصيل', '1262', 22, 'sub', 'assets', 'debit', true);
            $this->createAccount(3135, 'شيكات محصلة', '1263', 22, 'sub', 'assets', 'debit', true);
            $this->createAccount(3136, 'شيكات مرتجعة', '1264', 22, 'sub', 'assets', 'debit', true);
            $this->createAccount(3137, 'شيكات مؤجلة مستلمة', '1265', 22, 'sub', 'assets', 'debit', true);

            // حسابات أخرى تحت الأصول المتداولة
            $this->createAccount(23, 'عجز وزيادة الصندوق', '127', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(24, 'فروقات تحويل العملات', '128', 7, 'sub', 'assets', 'debit', true);

            // المشتريات (129)
            $this->createAccount(25, 'المشتريات', '129', 7, 'sub', 'assets', 'debit', true);
            $this->createAccount(91, 'مشتريات بضاعة', '1291', 25, 'sub', 'assets', 'debit', true);
            $this->createAccount(92, 'مشتريات مواد خام', '1292', 25, 'sub', 'assets', 'debit', false);
            $this->createAccount(93, 'مردودات المشتريات', '1293', 25, 'sub', 'assets', 'credit', true);

            // «السلف» (2177، 1210) أُزيل — مكرَّرٌ لـ«سلف الموظفين» (1243) أعلاه

            // ┌────────────────────────────────────────────────────────────────┐
            // │                   1.3 وحدات تحت التشغيل (13)                    │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(2961, 'وحدات تحت التشغيل', '13', 1, 'sub', 'assets', 'debit', true);

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                         2. الخصوم                              ║
            // ╚════════════════════════════════════════════════════════════════╝

            // ┌────────────────────────────────────────────────────────────────┐
            // │                   2.1 الخصوم المتداولة (21)                     │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(26, 'الخصوم المتداولة', '21', 2, 'sub', 'liabilities', 'credit', true);

            // الدائنون (211)
            $this->createAccount(28, 'الدائنون', '211', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(29, 'الموردون', '2111', 28, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(30, 'شركات الشحن', '2112', 28, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(33, 'أطراف دائنة أخرى', '2113', 28, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(96, 'مصروفات مستحقة', '2114', 28, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(97, 'إيرادات مقبوضة مقدماً', '2115', 28, 'sub', 'liabilities', 'credit', false);
            // محافظ العملاء — مالٌ قبضناه من العميل ولم نُقابله ببيع، فهو
            // التزامٌ علينا؛ موضعُه إلى جانب «إيرادات مقبوضة مقدماً» وكلاهما
            // مقبوضٌ مقدَّماً يبقى ديناً حتى يُستهلك.
            $this->createAccount(3433, 'أرصدة محافظ العملاء', '2116', 28, 'sub', 'liabilities', 'credit', true);

            // أوراق الدفع (212)
            $this->createAccount(34, 'أوراق الدفع', '212', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3138, 'شيكات برسم الدفع', '2121', 34, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3139, 'شيكات صادرة', '2122', 34, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3140, 'شيكات مؤجلة صادرة', '2123', 34, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3141, 'شيكات مسددة', '2124', 34, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3142, 'شيكات مرفوضة', '2125', 34, 'sub', 'liabilities', 'credit', true);

            // مجمع الإهلاك (213)
            $this->createAccount(35, 'مجمع الإهلاك', '213', 26, 'sub', 'liabilities', 'credit', false);

            // الضرائب والرسوم الحكومية (214)
            $this->createAccount(36, 'الضرائب والرسوم الحكومية', '214', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(37, 'ضريبة القيمة المضافة المحصلة', '2141', 36, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(2996, 'القيمة المضافة المدفوعة', '2142', 36, 'sub', 'liabilities', 'debit', true);
            $this->createAccount(98, 'ضريبة القيمة المضافة المستحقة', '2143', 36, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(99, 'ضريبة القيمة المضافة القابلة للخصم', '2144', 36, 'sub', 'liabilities', 'debit', true);
            $this->createAccount(100, 'ضريبة الدخل المستحقة', '2145', 36, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(101, 'الزكاة المستحقة', '2146', 36, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(102, 'رسوم حكومية أخرى', '2147', 36, 'sub', 'liabilities', 'credit', false);

            // رواتب ومستحقات الموظفين (215)
            /*
                مستحقُّ كلِّ موظفٍ حسابٌ باسمه يُفتح تحت هذا الأب (employee_payables_parent)،
                فلا حسابَ عامّاً لـ«رواتب مستحقة الدفع». وأُزيل منه:
                  - «رواتب مستحقة الدفع» (2151): نفسُ غرض الأب ولا يقيّد عليه شيء، وكودُه
                    يصطدم بمفتاح installment_deferred_profit الذي يبحث عن 2151 فيربط
                    أرباحَ التقسيط المؤجلة بحساب رواتب.
                  - «نهاية خدمة مستحقة» (2155): مكرَّرٌ لـ«مخصص نهاية الخدمة» (226)
                    المربوط بمفتاح eos_provision.
            */
            $this->createAccount(3038, 'رواتب ومستحقات الموظفين', '215', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(105, 'بدلات مستحقة', '2152', 3038, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(106, 'حوافز مستحقة', '2153', 3038, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(107, 'إجازات مستحقة', '2154', 3038, 'sub', 'liabilities', 'credit', false);

            // التأمينات الاجتماعية (216)
            $this->createAccount(109, 'التأمينات الاجتماعية', '216', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(110, 'تأمينات - حصة الموظف', '2161', 109, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(111, 'تأمينات - حصة الشركة', '2162', 109, 'sub', 'liabilities', 'credit', false);

            // قروض قصيرة الأجل (217)
            $this->createAccount(112, 'قروض قصيرة الأجل', '217', 26, 'sub', 'liabilities', 'credit', false);

            // أقساط مستحقة (218) - نقل من المصروفات
            $this->createAccount(3033, 'أقساط السيارات', '218', 26, 'sub', 'liabilities', 'credit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                 2.2 الخصوم طويلة الأجل (22)                     │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(27, 'الخصوم طويلة الأجل', '22', 2, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(39, 'الموردين', '221', 27, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(40, 'الذمم الدائنة', '222', 27, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(114, 'قروض طويلة الأجل', '223', 27, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(115, 'قروض بنكية', '224', 27, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(116, 'التزامات إيجار تمويلي', '225', 27, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(117, 'مخصص نهاية الخدمة', '226', 27, 'sub', 'liabilities', 'credit', false);

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                      3. حقوق الملكية                           ║
            // ╚════════════════════════════════════════════════════════════════╝

            // رأس المال (31)
            $this->createAccount(41, 'رأس المال', '31', 3, 'sub', 'equity', 'credit', true);

            // الأرباح والخسائر المرحلة (32)
            $this->createAccount(42, 'الأرباح والخسائر المرحلة', '32', 3, 'sub', 'equity', 'credit', true);

            // مسحوبات المالك (33)
            $this->createAccount(3019, 'مسحوبات المالك', '33', 3, 'sub', 'equity', 'debit', true);

            // أرصدة افتتاحية (34)
            $this->createAccount(38, 'أرصدة افتتاحية', '34', 3, 'sub', 'equity', 'credit', true);

            // الاحتياطيات (35)
            $this->createAccount(118, 'الاحتياطي النظامي', '35', 3, 'sub', 'equity', 'credit', true);
            $this->createAccount(119, 'الاحتياطي الاختياري', '36', 3, 'sub', 'equity', 'credit', true);
            $this->createAccount(120, 'احتياطي عام', '37', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(121, 'احتياطي طوارئ', '38', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(122, 'احتياطي توسعات', '39', 3, 'sub', 'equity', 'credit', false);

            // الأرباح المحتجزة (310)
            $this->createAccount(123, 'أرباح محتجزة', '310', 3, 'sub', 'equity', 'credit', true);
            $this->createAccount(124, 'أرباح العام الحالي', '311', 3, 'sub', 'equity', 'credit', true);
            $this->createAccount(125, 'أرباح السنوات السابقة', '312', 3, 'sub', 'equity', 'credit', true);

            // الخسائر (313)
            $this->createAccount(126, 'خسائر مرحلة', '313', 3, 'sub', 'equity', 'debit', true);
            $this->createAccount(127, 'خسائر العام الحالي', '314', 3, 'sub', 'equity', 'debit', true);
            $this->createAccount(128, 'خسائر السنوات السابقة', '315', 3, 'sub', 'equity', 'debit', true);

            // جاري الشركاء (316)
            $this->createAccount(129, 'جاري الشركاء', '316', 3, 'sub', 'equity', 'credit', true);
            $this->createAccount(130, 'جاري الشريك الأول', '3161', 129, 'sub', 'equity', 'credit', false);
            $this->createAccount(131, 'جاري الشريك الثاني', '3162', 129, 'sub', 'equity', 'credit', false);
            $this->createAccount(132, 'جاري الشريك الثالث', '3163', 129, 'sub', 'equity', 'credit', false);

            // جاري المدير (317)
            $this->createAccount(133, 'جاري المدير', '317', 3, 'sub', 'equity', 'credit', true);

            // حسابات أخرى تحت حقوق الملكية
            $this->createAccount(134, 'فروقات إعادة تقييم', '318', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(135, 'فروقات ترجمة عملات أجنبية', '319', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(136, 'توزيعات أرباح مستحقة', '320', 3, 'sub', 'equity', 'debit', true);
            $this->createAccount(137, 'مكافآت مجلس الإدارة', '321', 3, 'sub', 'equity', 'debit', false);
            $this->createAccount(138, 'حقوق ملكية أخرى', '322', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(139, 'علاوات إصدار', '323', 3, 'sub', 'equity', 'credit', false);
            $this->createAccount(140, 'أسهم خزينة', '324', 3, 'sub', 'equity', 'debit', false);

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                        4. الإيرادات                            ║
            // ╚════════════════════════════════════════════════════════════════╝

            // ┌────────────────────────────────────────────────────────────────┐
            // │                    4.1 إيرادات المبيعات (41)                    │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(43, 'إيرادات المبيعات', '41', 4, 'sub', 'revenues', 'credit', true);
            $this->createAccount(44, 'المبيعات', '411', 43, 'sub', 'revenues', 'credit', true);
            $this->createAccount(45, 'مردودات المبيعات', '412', 43, 'sub', 'revenues', 'debit', true);
            $this->createAccount(141, 'مبيعات نقدية', '413', 43, 'sub', 'revenues', 'credit', true);
            $this->createAccount(142, 'مبيعات آجلة', '414', 43, 'sub', 'revenues', 'credit', true);
            $this->createAccount(143, 'مبيعات جملة', '415', 43, 'sub', 'revenues', 'credit', false);
            $this->createAccount(144, 'مبيعات تقسيط', '416', 43, 'sub', 'revenues', 'credit', false);
            $this->createAccount(145, 'خصم المبيعات', '417', 43, 'sub', 'revenues', 'debit', true);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                     4.2 إيرادات أخرى (42)                       │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(46, 'إيرادات أخرى', '42', 4, 'sub', 'revenues', 'credit', true);
            $this->createAccount(47, 'إيرادات متنوعة', '421', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(48, 'أرباح وخسائر رأسمالية', '422', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(146, 'أرباح بيع أصول ثابتة', '423', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(147, 'أرباح استثمارات', '424', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(148, 'فوائد دائنة', '425', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(149, 'أرباح فروقات عملة', '426', 46, 'sub', 'revenues', 'credit', true);
            $this->createAccount(150, 'إيرادات إيجار', '427', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(151, 'خصم مكتسب', '428', 46, 'sub', 'revenues', 'credit', false);
            $this->createAccount(3210, 'إيرادات الشحن', '429', 46, 'sub', 'revenues', 'credit', false);
            // كان الكود '430' — وهو ابنٌ للكود '43' (إيرادات الخدمات) لا للكود
            // '42'، فيضع مردود الشحن في فرعٍ آخر من شجرة الإيرادات
            $this->createAccount(3299, 'مردود إيرادات الشحن', '4210', 46, 'sub', 'revenues', 'debit', false);
            // فائضُ درج الكاشير: إيرادٌ عارض حين يزيد النقد الفعلي عن المتوقَّع
            // عند إقفال جلسة نقطة بيع. `is_system` لأن الشيفرة ترحّل إليه
            // بالمعرّف (`SystemAccountIds::POS_CASH_OVER`)، فحذفُه يكسر الإقفال
            $this->createAccount(3430, 'فائض درج الكاشير', '4211', 46, 'sub', 'revenues', 'credit', true);

            /* قسم الغرامات — إيرادٌ عارضٌ من تأخّر المدينين لا من بيع.
               الأبُ يجمع كلَّ ما يُحصَّل غرامةً، والابنُ يعزل غرامات
               التقسيط وحدها فلا تُخلَط بإيراد المبيعات فتُظهر النشاطَ
               أكبر ممّا هو.

               `is_system` في الاثنين: مهمّةُ الغرامات ترحّل إليهما
               بالمفتاح المضبوط في «الحسابات المحاسبية».

               ولا ثابتَ لهما في `SystemAccountIds`: المعرّفان هنا صالحان
               للتنصيب الجديد وحده — في التركيبات القائمة يكونان مأخوذَين
               بحسابات عملاء، ولذلك يُنشئهما الترحيل
               `add_late_fee_accounts` **بالكود** لا بالمعرّف. */
            $this->createAccount(3434, 'الغرامات والتعويضات', '4212', 46, 'sub', 'revenues', 'credit', true);
            $this->createAccount(3435, 'غرامات تأخير الأقساط', '42121', 3434, 'sub', 'revenues', 'credit', true);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                    4.3 إيرادات الخدمات (43)                     │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(152, 'إيرادات الخدمات', '43', 4, 'sub', 'revenues', 'credit', false);
            $this->createAccount(153, 'خدمات استشارية', '431', 152, 'sub', 'revenues', 'credit', false);
            $this->createAccount(154, 'خدمات صيانة', '432', 152, 'sub', 'revenues', 'credit', false);
            $this->createAccount(155, 'عمولات محصلة', '433', 152, 'sub', 'revenues', 'credit', false);

            // ╔════════════════════════════════════════════════════════════════╗
            // ║                        5. المصروفات                            ║
            // ╚════════════════════════════════════════════════════════════════╝

            // ┌────────────────────────────────────────────────────────────────┐
            // │                   5.1 تكلفة المبيعات (51)                       │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(49, 'تكلفة المبيعات', '51', 5, 'sub', 'expenses', 'debit', true);
            $this->createAccount(50, 'تكلفة البضاعة المباعة', '511', 49, 'sub', 'expenses', 'debit', true);
            $this->createAccount(51, 'شحن مشتريات', '512', 49, 'sub', 'expenses', 'debit', true);
            $this->createAccount(52, 'خصم مسموح به', '513', 49, 'sub', 'expenses', 'debit', true);
            $this->createAccount(156, 'رسوم جمركية', '514', 49, 'sub', 'expenses', 'debit', false);
            $this->createAccount(157, 'تكاليف استيراد', '515', 49, 'sub', 'expenses', 'debit', false);
            /*
                الطرف المقابل لفروق الجرد — حسابٌ مستقلّ عن «تكلفة البضاعة
                المباعة»: كان مفتاح `inventory_adjustment` يقع افتراضاً على
                511، فيختلط عجزُ الجرد وزيادتُه بتكلفة بضاعةٍ بيعت فعلاً
                ولا يُقرأ أيٌّ منهما على حدة. الشجرات القائمة يزرعه فيها
                ترحيل add_inventory_adjustment_account.
            */
            $this->createAccount(3436, 'عجز وفائض جرد المخزون', '516', 49, 'sub', 'expenses', 'debit', true);

            // ┌────────────────────────────────────────────────────────────────┐
            // │               5.2 مصروفات إدارية وعمومية (52)                   │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(53, 'مصروفات إدارية وعمومية', '52', 5, 'sub', 'expenses', 'debit', true);

            $this->createAccount(54, 'إيجارات', '521', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2894, 'إيجار', '5211', 54, 'sub', 'expenses', 'debit', false); // تم نقله هنا
            $this->createAccount(3020, 'الاستراحة', '5212', 54, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(55, 'كهرباء', '522', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(56, 'هاتف وإنترنت', '523', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3028, 'اشتراكات خدمات', '5231', 56, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(57, 'صيانة', '524', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2808, 'صيانة عامة', '5241', 57, 'sub', 'expenses', 'debit', false); // تم نقله هنا
            $this->createAccount(158, 'صيانة مباني', '5242', 57, 'sub', 'expenses', 'debit', false);
            $this->createAccount(159, 'صيانة أجهزة ومعدات', '5243', 57, 'sub', 'expenses', 'debit', false);
            $this->createAccount(160, 'صيانة سيارات', '5244', 57, 'sub', 'expenses', 'debit', false);

            $this->createAccount(58, 'مياه', '525', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(59, 'مصاريف حكومية', '526', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3101, 'مخالفات مرورية', '5261', 59, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(161, 'قرطاسية ومطبوعات', '527', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3034, 'ورق طابعة', '5271', 161, 'sub', 'expenses', 'debit', false); // تم نقله هنا
            $this->createAccount(3057, 'أدوات قرطاسية', '5272', 161, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(162, 'ضيافة', '528', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2957, 'عزبة', '5281', 162, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(163, 'نظافة', '529', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(164, 'أمن وحراسة', '5210', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(165, 'رسوم بنكية', '5216', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3022, 'رسوم تحويل', '52161', 165, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(166, 'اشتراكات', '5217', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(167, 'تأمين', '5218', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(168, 'مصروفات قانونية', '5219', 53, 'sub', 'expenses', 'debit', false);
            $this->createAccount(169, 'استشارات', '52110', 53, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                  5.3 الرواتب والأجور (530)                      │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(3041, 'الرواتب والأجور', '530', 5, 'sub', 'expenses', 'debit', true);
            /*
                حسابٌ واحدٌ لكلِّ غرضٍ تحت «الرواتب والأجور» — وهو المربوط في «الحسابات
                المحاسبية». أُزيل منه المكرَّر: «راتب» (53011 تحت رواتب الموظفين)،
                و«عمولة» (5308) مع «عمولات الموظفين» (5309)، و«عمولات مبيعات» (5602)
                تحت التسويق — كانت العمولةُ الواحدة تجد ثلاثةَ حساباتٍ في الشجرة.
            */
            $this->createAccount(3079, 'رواتب الموظفين', '5301', 3041, 'sub', 'expenses', 'debit', true);

            $this->createAccount(170, 'أجور عمال', '5302', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(171, 'بدلات', '5303', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(172, 'حوافز ومكافآت', '5304', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(173, 'تأمينات اجتماعية', '5305', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(174, 'تأمين طبي', '5306', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(175, 'مكافأة نهاية الخدمة', '5307', 3041, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3204, 'عمولات الموظفين', '5309', 3041, 'sub', 'expenses', 'debit', true);
            /*
                خصميات الموظفين — الطرف المقابل في قيد استحقاق الراتب عند وجود خصم.
                بلا هذا الحساب يُرفض صرف أي موظف عليه خصم مفتوح.
            */
            $this->createAccount(3205, 'خصميات الموظفين', '5310', 3041, 'sub', 'expenses', 'debit', true);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                 5.4 مصروفات الإهلاك (540)                       │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(60, 'مصروفات الإهلاك', '540', 5, 'sub', 'expenses', 'debit', true);
            $this->createAccount(176, 'إهلاك مباني', '5401', 60, 'sub', 'expenses', 'debit', false);
            $this->createAccount(177, 'إهلاك سيارات', '5402', 60, 'sub', 'expenses', 'debit', false);
            $this->createAccount(178, 'إهلاك أثاث', '5403', 60, 'sub', 'expenses', 'debit', false);
            $this->createAccount(179, 'إهلاك أجهزة ومعدات', '5404', 60, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │              5.5 مصروفات التصنيع (550)                          │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(2962, 'مصروفات التصنيع', '550', 5, 'sub', 'expenses', 'debit', true);
            $this->createAccount(2963, 'مصروفات غير مباشرة', '5501', 2962, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2964, 'مصروفات مباشرة', '5502', 2962, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2965, 'أجور العمالة', '5503', 2962, 'sub', 'expenses', 'debit', false);
            /*
                انحراف تكاليف الإنتاج — الطرف الذي يُقفل «مخزون تحت التشغيل».

                قيود إتمام أمر التصنيع تُدين WIP بالتكاليف وتُقرضه بما دخل
                المخزون، والفرق بينهما لا يصفر إلا بقيد سادس يستوعبه. وبلا
                هذا الحساب يرمي `requireAccountId` فيسقط كل إتمام أمر تصنيع.

                لم يكن مزروعاً في الشجرة ولا معرَّفاً في SystemAccountIds ولا
                مذكوراً في AccountingSettingsSeeder — والهجرة التي تُنشئ مفتاحه
                تبحث عن حساب اسمه يحوي «انحراف» فلا تجد، فتتركه بلا ربط.
            */
            $this->createAccount(3203, 'انحراف تكاليف الإنتاج', '5504', 2962, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │            5.6 مصروفات التسويق والمبيعات (560)                  │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(180, 'مصروفات تسويق ومبيعات', '560', 5, 'sub', 'expenses', 'debit', false);
            $this->createAccount(181, 'دعاية وإعلان', '5601', 180, 'sub', 'expenses', 'debit', false);
            $this->createAccount(183, 'تسويق رقمي', '5603', 180, 'sub', 'expenses', 'debit', false);
            $this->createAccount(184, 'هدايا وعينات', '5604', 180, 'sub', 'expenses', 'debit', false);
            $this->createAccount(185, 'معارض ومؤتمرات', '5605', 180, 'sub', 'expenses', 'debit', false);
            $this->createAccount(186, 'شحن مبيعات', '5606', 180, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2856, 'شحن', '5607', 180, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            // ┌────────────────────────────────────────────────────────────────┐
            // │            5.7 مصروفات النقل والمواصلات (570)                   │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(187, 'مصروفات نقل ومواصلات', '570', 5, 'sub', 'expenses', 'debit', false);
            $this->createAccount(188, 'وقود', '5701', 187, 'sub', 'expenses', 'debit', false);
            $this->createAccount(2801, 'محروقات', '57011', 188, 'sub', 'expenses', 'debit', false); // تم نقله هنا
            $this->createAccount(2857, 'زيت السيارة', '57012', 188, 'sub', 'expenses', 'debit', false); // تم نقله هنا

            $this->createAccount(189, 'تأمين سيارات', '5702', 187, 'sub', 'expenses', 'debit', false);
            $this->createAccount(190, 'رسوم مرور', '5703', 187, 'sub', 'expenses', 'debit', false);
            $this->createAccount(191, 'أجرة نقل', '5704', 187, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │               5.8 مصروفات التمويل (580)                         │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(192, 'مصروفات تمويلية', '580', 5, 'sub', 'expenses', 'debit', false);
            $this->createAccount(193, 'فوائد بنكية', '5801', 192, 'sub', 'expenses', 'debit', false);
            $this->createAccount(194, 'أرباح تمويل إسلامي', '5802', 192, 'sub', 'expenses', 'debit', false);
            $this->createAccount(195, 'رسوم تمويل', '5803', 192, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │                5.9 مصروفات أخرى (590)                           │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(61, 'مصروفات أخرى', '590', 5, 'sub', 'expenses', 'debit', true);
            $this->createAccount(62, 'مصروفات متنوعة', '5901', 61, 'sub', 'expenses', 'debit', false);
            $this->createAccount(63, 'الديون المعدومة', '5902', 61, 'sub', 'expenses', 'debit', true);
            $this->createAccount(64, 'عجز وزيادة المخزون', '5903', 61, 'sub', 'expenses', 'debit', true);
            $this->createAccount(65, 'إعادة تقييم', '5904', 61, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3013, 'ديون مشكوك فيها', '5905', 61, 'sub', 'expenses', 'debit', true);
            $this->createAccount(196, 'مخصص ديون مشكوك فيها', '5906', 61, 'sub', 'expenses', 'debit', false);
            $this->createAccount(197, 'خسائر بيع أصول ثابتة', '5907', 61, 'sub', 'expenses', 'debit', false);
            $this->createAccount(198, 'خسائر فروقات عملة', '5908', 61, 'sub', 'expenses', 'debit', true);
            $this->createAccount(199, 'غرامات وجزاءات', '5909', 61, 'sub', 'expenses', 'debit', false);
            // كان الكود '5910' — يقرأ كابنٍ للكود '591' لا للكود '590'
            $this->createAccount(200, 'تبرعات', '59010', 61, 'sub', 'expenses', 'debit', false);
            // عجزُ درج الكاشير: مصروفٌ حين ينقص النقد الفعلي عن المتوقَّع.
            // مقابلُ «فائض درج الكاشير» في الإيرادات — واثنان لا واحد ليظهر
            // حجمُ كلٍّ منهما في ميزان المراجعة (`SystemAccountIds::POS_CASH_SHORT`)
            $this->createAccount(3431, 'عجز درج الكاشير', '59011', 61, 'sub', 'expenses', 'debit', true);

            // ┌────────────────────────────────────────────────────────────────┐
            // │           5.10 مصروفات نظام النقاط والمكافآت (5100)              │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(3375, 'مصروفات نظام المكافآت', '5100', 5, 'sub', 'expenses', 'debit', true);
            $this->createAccount(3376, 'مكافآت نقدية مستبدلة', '51001', 3375, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3377, 'خصومات مكافآت', '51002', 3375, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3378, 'قسائم شرائية مستبدلة', '51003', 3375, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3379, 'هدايا ومنتجات مستبدلة', '51004', 3375, 'sub', 'expenses', 'debit', false);
            $this->createAccount(3380, 'مصروفات مكافآت أخرى', '51005', 3375, 'sub', 'expenses', 'debit', false);

            // ┌────────────────────────────────────────────────────────────────┐
            // │        2.3 التزامات نظام النقاط والمكافآت (219)                  │
            // └────────────────────────────────────────────────────────────────┘
            $this->createAccount(3381, 'التزامات نظام المكافآت', '219', 26, 'sub', 'liabilities', 'credit', true);
            $this->createAccount(3382, 'مكافآت معلقة مستحقة', '2191', 3381, 'sub', 'liabilities', 'credit', false);
            $this->createAccount(3383, 'قسائم مكافآت صادرة', '2192', 3381, 'sub', 'liabilities', 'credit', false);

            if ($this->collecting) {
                return;
            }

            // إعادة تفعيل فحص Foreign Keys
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->command->info('✅ تم إنشاء شجرة الحسابات بنجاح!');
            $this->command->info('📊 إجمالي الحسابات: '.Account::count());
            $this->command->info('🔒 الحسابات النظامية (غير قابلة للحذف): '.Account::where('is_system', true)->count());
            $this->command->info('✏️ الحسابات القابلة للحذف: '.Account::where('is_system', false)->count());
        } catch (\Exception $e) {
            if (! $this->collecting) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            $this->command?->error('❌ خطأ في إنشاء شجرة الحسابات: '.$e->getMessage());
            $this->command?->error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * إنشاء حساب بجميع التفاصيل
     */
    private function createAccount(int $id, string $name, string $code, ?int $parentId, string $type, string $accountType, string $balanceType, bool $isSystem = false): ?Account
    {
        if ($this->collecting) {
            $this->collected[] = [
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'parent_id' => $parentId,
                'type' => $type,
                'account_type' => $accountType,
                'balance_type' => $balanceType,
                'is_system' => $isSystem,
            ];

            return null;
        }

        return Account::create([
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'type' => $type,
            'balance_type' => $balanceType,
            'account_type' => $accountType,
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => $balanceType,
            'is_active' => true,
            'is_system' => $isSystem,
        ]);
    }
}
