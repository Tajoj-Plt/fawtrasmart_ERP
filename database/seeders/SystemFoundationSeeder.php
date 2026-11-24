<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\StoreHouse;
use App\Support\AccountingSettings;
use Illuminate\Database\Seeder;

/**
 * بيانات التأسيس — ما لا يقوم النظام بدونه في تركيبٍ جديد.
 *
 * ═════════════════════ لماذا هذا السيدر ═════════════════════
 *
 * أجزاؤه مزروعةٌ سلفاً كلٌّ في سيدرٍ مستقل، لكنّها لا تصل إلى تركيبٍ على خادم:
 *
 *   - `DatabaseSeeder` يجمعها، لكنّه يجرّ معه بيانات تجريبية —
 *     [ClientsTableSeeder] وحده يُدرج ١٥٠٠ عميلاً وهميّاً — فلا يُشغَّل
 *     على تركيبٍ حقيقي.
 *   - وشاشة «تحديث النظام» لا تعرض أيّاً منها: [SystemUpdateRegistry]
 *     يسمح بالصلاحيات والقوالب والبيانات المرجعية، ولا مفتاح فيه للفرع
 *     ولا للعميل الافتراضي ولا للمستودع.
 *
 * فمن رفع قاعدةً جديدة وشغّل `migrate` وجد جداولَ فارغة: لا فرع، فلا
 * موظّف يُضاف ولا عميل؛ ولا مستودع، فلا منتج ولا إذن صرف؛ ولا حسابات،
 * فكل قيدٍ يسقط بـ«الحساب غير مربوط».
 *
 * هذا السيدر هو ذلك الحدّ الأدنى وحده، مجموعاً في أمرٍ واحد.
 *
 * ═════════════════════ ما يفعله بالترتيب ═════════════════════
 *
 *   ١. شجرة الحسابات — [AccountSeeder]، **وشرطُه أن يكون الجدول فارغاً**.
 *   ٢. ربط الحسابات المحاسبية — [AccountingSettingsSeeder]، يضيف الناقص.
 *   ٣. الفرع الرئيسي — [MainBranchSeeder].
 *   ٤. العميل الافتراضي وحسابُه تحت «العملاء» — [DefaultClientSeeder].
 *   ٥. المستودع الرئيسي وحسابُه تحت «المخزون» — [MainStoreHouseSeeder].
 *
 * الترتيب ملزِم: حسابا العميل والمستودع فرعان تحت حسابين يقرأهما السيدران
 * من «إعدادات الحسابات المحاسبية»، فسبقُهما للخطوتين الأولَيَين يُنشئ عميلاً
 * ومستودعاً بلا حسابٍ محاسبي — وهو بالضبط العطل الذي كُتب هذا السيدر لأجله.
 * والعميل يحتاج الفرع قبله لأن [DefaultClientSeeder] يقف إن لم يجده.
 *
 * ═════════════════════ إعادة التشغيل ═════════════════════
 *
 * آمنة: كل خطوة تسأل عمّا تُنشئه قبل أن تُنشئه، والسيدرات نفسها تتخطّى
 * الموجود. وحدها الخطوة الأولى خطِرة بطبعها — `AccountSeeder` يبدأ
 * بـ`Account::query()->delete()`، فيمحو شجرةً حرّرها المحاسب ويقطع كلّ
 * قيدٍ معلّقٍ بها — ولذلك تُشغَّل هنا **متى كان جدول `accounts` فارغاً لا
 * غير**، ويُكتفى بإخبار المستخدم فيما عدا ذلك.
 *
 * وحيث يوجد فرعٌ أو مستودعٌ بلا وسم «رئيسي»، لا يُنشأ ثانٍ: إنشاءُ فرعٍ
 * اسمه «الفرع الرئيسي» بجوار فروع منشأةٍ عاملة إفسادٌ لا إصلاح. تُطبَع
 * ملاحظةٌ تطلب وسمَ القائم من شاشته.
 */
class SystemFoundationSeeder extends Seeder
{
    /** ما تعذّر إنجازه — يُطبع مجموعاً في نهاية التشغيل. */
    protected array $blockers = [];

    public function run(): void
    {
        $this->command?->newLine();
        $this->command?->info('══════════ بيانات التأسيس ══════════');

        $this->seedChartOfAccounts();
        $this->seedAccountingSettings();
        $this->seedMainBranch();
        $this->seedDefaultClient();
        $this->seedMainStoreHouse();

        $this->report();
    }

    /* ═══════════ ١. شجرة الحسابات ═══════════ */

    /**
     * الشجرة كاملةً من [AccountSeeder] — وشرطُه الجدولُ الفارغ.
     *
     * `AccountSeeder` ليس مُضيفاً بل مُستبدِلاً: يحذف كل صفوف `accounts` ثم
     * يعيد زرعها بمعرّفاتٍ ثابتة. تشغيلُه على تركيبٍ عامل يمحو حسابات
     * العملاء والمستودعات المُنشأة من الشاشات، وتبقى القيود تشير إلى
     * معرّفاتٍ لا وجود لها. فالفحص هنا شرطُ سلامة لا تحسين.
     */
    protected function seedChartOfAccounts(): void
    {
        $count = Account::count();

        if ($count > 0) {
            $this->step('شجرة الحسابات', "موجودة ({$count} حساباً) — تُخطّى.");

            return;
        }

        $this->step('شجرة الحسابات', 'الجدول فارغ — تُزرع الشجرة الكاملة…');
        $this->call(AccountSeeder::class);
    }

    /* ═══════════ ٢. ربط الحسابات المحاسبية ═══════════ */

    /**
     * مفاتيح «إعدادات الحسابات المحاسبية» — يضيف الناقص ولا يمسّ ربطاً قائماً.
     *
     * وتُنسى الخريطة المخزَّنة بعده: `AccountingSettings::map()` مُخزَّنة
     * ساعةً كاملة، وبدون هذا النسيان تقرأ الخطوتان التاليتان خريطةً
     * التُقطت قبل الزرع فلا تجدان حسابَي «العملاء» و«المخزون».
     */
    protected function seedAccountingSettings(): void
    {
        $this->step('ربط الحسابات المحاسبية', 'يُضاف الناقص من المفاتيح…');

        $this->call(AccountingSettingsSeeder::class);

        AccountingSettings::forget();
    }

    /* ═══════════ ٣. الفرع الرئيسي ═══════════ */

    protected function seedMainBranch(): void
    {
        if (Branch::where('is_main', true)->exists()) {
            $this->step('الفرع الرئيسي', 'موجود — يُتخطّى.');

            return;
        }

        // فروعٌ قائمة بلا وسم «رئيسي»: منشأةٌ عاملة ينقصها وسمٌ، لا منشأةٌ
        // جديدة. إضافةُ فرعٍ آخر اسمه «الفرع الرئيسي» تزيدها عطلاً.
        if (($existing = Branch::count()) > 0) {
            $this->step('الفرع الرئيسي', "لم يُنشأ — يوجد {$existing} فرعاً بلا وسم «رئيسي».");
            $this->blockers[] = 'عيّن الفرع الرئيسي من شاشة «الفروع» (خيار «فرع رئيسي») ثم أعد تشغيل هذا الأمر — '
                .'العميل الافتراضي والمستودع الرئيسي يُنسبان إليه.';

            return;
        }

        $this->step('الفرع الرئيسي', 'لا فرع في النظام — يُنشأ…');
        $this->call(MainBranchSeeder::class);
    }

    /* ═══════════ ٤. العميل الافتراضي ═══════════ */

    protected function seedDefaultClient(): void
    {
        if (Client::whereKey(Client::DEFAULT_CLIENT_ID)->exists()) {
            $this->step('العميل الافتراضي', 'موجود — يُتحقَّق من حسابه فقط.');
            $this->ensureClientAccount();

            return;
        }

        if (! Branch::where('is_main', true)->exists()) {
            $this->step('العميل الافتراضي', 'لم يُنشأ — لا فرع رئيسي يُنسب إليه.');

            return;
        }

        $this->step('العميل الافتراضي', 'يُنشأ مع حسابه في شجرة الحسابات…');
        $this->call(DefaultClientSeeder::class);
    }

    /**
     * حسابُ عميلٍ قائمٍ بلا حساب.
     *
     * [DefaultClientSeeder] يُنشئ العميل وحسابَه معاً، لكنّه يتخطّى العميلَ
     * كلَّه إن وُجد صفُّه — فعميلٌ أُنشئ يوم كان مفتاح «العملاء» غير مربوط
     * يبقى بلا حسابٍ إلى الأبد مهما أُعيد تشغيل السيدر. هذه هي تلك الحالة.
     */
    protected function ensureClientAccount(): void
    {
        $client = Client::find(Client::DEFAULT_CLIENT_ID);

        if (Account::where('client_id', $client->id)->exists()) {
            return;
        }

        $parent = AccountingSettings::account('clients_account');

        if (! $parent) {
            $this->blockers[] = 'حساب «العملاء» غير مربوط في شاشة «الحسابات المحاسبية» — '
                .'العميل الافتراضي بلا حسابٍ محاسبي.';

            return;
        }

        $code = $this->nextChildCode($parent);

        $account = new Account;
        $account->name = $client->trade_name;
        $account->client_id = $client->id;
        $account->account_type = $parent->account_type;
        $account->code = $code;
        $account->balance_type = 'debit';
        $account->parent_id = $parent->id;
        $account->opening_balance = 0;
        $account->opening_balance_type = $parent->balance_type;
        $account->is_active = true;
        $account->save();

        $this->command?->line("     • أُنشئ حساب العميل الافتراضي: {$account->code}");
    }

    /**
     * رمزٌ فارغ تحت حسابٍ أب — رمزُ الأب متبوعاً بترتيبٍ لا يتكرّر.
     *
     * ولا يُستعمل هنا مولّد `ClientController::generateNextCode()` قصداً،
     * وهو الذي يستنسخه [DefaultClientSeeder]، لأنه يزيد الرمزَ حرفاً كلّما
     * بلغ آخرُه ٩: يُبدل الحرفَ الأخير بـ`intval(آخر حرف) + 1`، فـ«…9»
     * تصير «…10» فيطول النصّ، ثم يدور على التصادم فيطول ثانيةً. جرّبناه
     * على قاعدةٍ قائمة فأخرج لحساب العميل الافتراضي الرمز
     * `account-2111111111111111111116` — خمسةً وعشرين حرفاً من دورةٍ واحدة.
     *
     * وهو في التنصيب الجديد لا يظهر (لا أبناءَ للحساب الأب فيُؤخذ
     * `رمز الأب . '1'`)، فتُرك [DefaultClientSeeder] على حاله موافقاً
     * للشاشة. أمّا هذا المسار فمسارُ إصلاحٍ على قاعدةٍ فيها آلاف العملاء،
     * وهناك يُصيب العطلُ كلَّ مرّة.
     */
    protected function nextChildCode(Account $parent): string
    {
        $base = $parent->code ?: (string) $parent->id;
        $sequence = Account::where('parent_id', $parent->id)->count() + 1;

        while (Account::where('code', $base.$sequence)->exists()) {
            $sequence++;
        }

        return $base.$sequence;
    }

    /* ═══════════ ٥. المستودع الرئيسي ═══════════ */

    protected function seedMainStoreHouse(): void
    {
        if (StoreHouse::where('major', 1)->exists()) {
            $this->step('المستودع الرئيسي', 'موجود — يُتحقَّق من حسابه فقط.');
            // يتخطّى المستودع ويُكمل حسابه إن كان ناقصاً
            $this->call(MainStoreHouseSeeder::class);

            return;
        }

        // كما في الفرع: مستودعاتٌ قائمة بلا وسم «رئيسي» ينقصها الوسم لا مستودعٌ جديد
        if (($existing = StoreHouse::count()) > 0) {
            $this->step('المستودع الرئيسي', "لم يُنشأ — يوجد {$existing} مستودعاً بلا وسم «رئيسي».");
            $this->blockers[] = 'عيّن المستودع الرئيسي من شاشة «المستودعات» ثم أعد تشغيل هذا الأمر.';

            return;
        }

        $this->step('المستودع الرئيسي', 'يُنشأ مع حسابه في شجرة الحسابات…');
        $this->call(MainStoreHouseSeeder::class);
    }

    /* ═══════════ الحصيلة ═══════════ */

    /**
     * حصيلةُ التشغيل — الغرضُ منها أن يرى من شغّل الأمر على خادمٍ ماذا نزل
     * فعلاً وبأيّ رمزٍ محاسبي، بدل أن يفتح الشاشات واحدةً واحدة ليتأكّد.
     */
    protected function report(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $client = Client::find(Client::DEFAULT_CLIENT_ID);
        $storehouse = StoreHouse::where('major', 1)->first();

        $clientAccount = $client ? Account::where('client_id', $client->id)->first() : null;
        $storehouseAccount = $storehouse ? Account::where('storehouse_id', $storehouse->id)->first() : null;

        $clientsParent = AccountingSettings::account('clients_account');
        $inventoryParent = AccountingSettings::account('inventory');

        $this->command?->newLine();
        $this->command?->info('══════════ الحصيلة ══════════');

        $this->command?->table(
            ['العنصر', 'الحالة', 'التفصيل'],
            [
                ['شجرة الحسابات', $this->mark(Account::count() > 0), Account::count().' حساباً'],
                ['حساب «العملاء» الأب', $this->mark((bool) $clientsParent), $clientsParent?->name ?? 'غير مربوط'],
                ['حساب «المخزون» الأب', $this->mark((bool) $inventoryParent), $inventoryParent?->name ?? 'غير مربوط'],
                ['الفرع الرئيسي', $this->mark((bool) $branch), $branch ? $branch->name.' (#'.$branch->id.')' : 'غير موجود'],
                ['العميل الافتراضي', $this->mark((bool) $client), $client ? $client->trade_name.' (#'.$client->id.')' : 'غير موجود'],
                ['حساب العميل الافتراضي', $this->mark((bool) $clientAccount), $clientAccount ? 'رمز '.$clientAccount->code : 'غير موجود'],
                ['المستودع الرئيسي', $this->mark((bool) $storehouse), $storehouse ? $storehouse->name.' (#'.$storehouse->id.')' : 'غير موجود'],
                ['حساب المستودع الرئيسي', $this->mark((bool) $storehouseAccount), $storehouseAccount ? 'رمز '.$storehouseAccount->code : 'غير موجود'],
            ]
        );

        if ($this->blockers === []) {
            $this->command?->info('اكتمل التأسيس.');

            return;
        }

        $this->command?->newLine();
        $this->command?->warn('يحتاج تدخّلاً يدوياً:');
        foreach (array_unique($this->blockers) as $blocker) {
            $this->command?->line('  - '.$blocker);
        }
    }

    protected function mark(bool $ok): string
    {
        return $ok ? '✔' : '✘';
    }

    protected function step(string $title, string $detail): void
    {
        $this->command?->line("  ▸ <options=bold>{$title}</> — {$detail}");
    }
}
