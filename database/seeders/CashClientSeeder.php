<?php

namespace Database\Seeders;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

/**
 * «العميل النقدي» — سجلٌّ في `clients` يقابل حسابَه في شجرة الحسابات.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * الحسابُ وحده كان مزروعاً:
 *
 *     $this->createAccount(482, 'عميل نقدي', '1241408', 18, 'main', …);
 *
 * حسابٌ مدينٌ تحت «العملاء»، تشير إليه `AccountingSettingsSeeder` بمفتاح
 * `cash_client`، ويقرؤه المتجرُ الإلكتروني في قيوده. لكن لا صفَّ يقابله في
 * `clients` — فـ«عميل نقدي» في نقاط البيع نصٌّ يُطبع على الإيصال حين لا
 * عميلَ للفاتورة (`$client->trade_name ?? 'عميل نقدي'`)، لا كيانٌ في النظام.
 *
 * وأثرُ ذلك أنّ البيعَ النقدي يُحفَظ بلا `client_id`: فلا كشفَ حسابٍ يجمعه،
 * ولا تقريرَ مبيعاتٍ يعدّه في العملاء، ولا رصيدَ يُقارَن بحساب ٤٨٢ نفسه.
 *
 * ═════════════════════ ما يفعله هذا السيدر ═════════════════════
 *
 *   ١. عميلٌ بمعرّفٍ ثابت `Client::CASH_CLIENT_ID` — ثابتٌ لأنّ الشيفرة
 *      ستشير إليه كما تشير إلى `DEFAULT_CLIENT_ID`.
 *   ٢. جهةُ اتصالٍ وموقعٌ وحيّ — نفس ما يفعله `ClientController::store()`،
 *      فلا يشذّ هذا العميل عن غيره في شاشةٍ تفترض وجودها.
 *   ٣. **ربطُ الحساب القائم به** (`accounts.client_id = <id>`) بدل إنشاء
 *      حسابٍ فرعيّ جديد — وهذا فرقُه عن [DefaultClientSeeder]: حسابُ العميل
 *      النقدي مزروعٌ سلفاً بمعرّفٍ ثابت تعتمد عليه الإعدادات المحاسبية
 *      والمتجر، فإنشاءُ ثانٍ يجعل للعميل حسابين ورصيدَه موزّعاً بينهما.
 *
 * والسيدر **مُتَكرِّرُ التشغيل بلا أثر** (idempotent): يتخطّى العميلَ إن وُجد،
 * ويربط الحساب إن لم يكن مربوطاً — فتشغيلُه على تركيبٍ قائم آمن.
 */
class CashClientSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::find(Client::CASH_CLIENT_ID);

        if ($client) {
            $this->command->info('العميل النقدي موجود بالفعل — يُتحقَّق من ربط حسابه فقط.');
            $this->linkAccount($client);

            return;
        }

        /*
            الفرعُ مرغوبٌ لا شرط.

            `is_main` غيرُ مضبوطٍ في تركيباتٍ قائمة (لا فرعَ يحمله أصلاً)،
            وردُّ السيدر خائباً عندها يمنع إنشاء العميل النقدي كلَّه من أجل
            عمودٍ يقبل الفراغ. فيُؤخَذ الفرعُ الرئيسيّ إن وُجد، وإلّا أوّلُ
            فرعٍ نشِط، وإلّا يُنشأ العميل بلا فرع — وهي حالةٌ مشروعة.
        */
        $mainBranch = Branch::where('is_main', true)->first()
            ?: Branch::where('status', 0)->orderBy('id')->first();

        /*
            المعرّف يُفرَض صراحةً — كما في [DefaultClientSeeder] — لأنّ الشيفرة
            تشير إليه بثابتٍ لا ببحثٍ بالاسم. والكودُ يُساوي المعرّف بعد الحفظ،
            وهو اصطلاح `ClientController::store()` نفسه.
        */
        $client = new Client;
        $client->id = Client::CASH_CLIENT_ID;
        $client->status = 'active';
        $client->fill([
            'trade_name' => 'عميل نقدي',
            'phone' => '0000000000',
            'mobile' => '0000000000',
            'email' => null,
            'tax_number' => null,
            'commercial_registration' => null,
            // بلا أجل: البيعُ النقدي يُسدَّد لحظتَه، فحدُّ ائتمانٍ له تناقض
            'credit_limit' => 0,
            'credit_period' => 0,
            'currency' => 'SAR',
            'client_type' => 1,
            'notes' => 'عميل نظامي للبيع النقدي — تُنسب إليه فواتير نقاط البيع التي بلا عميل مسجَّل، ويقابله حساب «عميل نقدي» في شجرة الحسابات.',
            'branch_id' => $mainBranch?->id,
            'is_store_client' => false,
        ]);
        $client->code = 0;
        $client->save();

        $client->code = $client->id;
        $client->save();

        // ═══════════ جهة الاتصال الأساسية ═══════════
        $client->contacts()->create([
            'first_name' => $client->trade_name,
            'phone' => $client->phone,
            'mobile' => $client->mobile,
            'email' => $client->email,
        ]);

        /*
            الموقعُ والحيّ نائبان لا بيانات حقيقية: العميل النقدي لا عنوان
            له. ويُكتبان مع ذلك لأنّ شاشات العملاء تفترض وجودهما لكل عميل
            (كما في [DefaultClientSeeder])، فتركُهما يجعل هذا العميل وحده
            يتصرّف تصرّفاً مختلفاً في القوائم والخرائط.
        */
        if ($mainBranch) {
            $client->locations()->create([
                'branch_id' => $mainBranch->id,
                'latitude' => 24.7136,
                'longitude' => 46.6753,
            ]);

            Neighborhood::create([
                'name' => 'الرياض',
                'region_id' => $mainBranch->resolveTerritoryBranchForNeighborhood()->id,
                'client_id' => $client->id,
            ]);
        }

        $this->linkAccount($client);

        $this->command->info('✅ تم إنشاء العميل النقدي:');
        $this->command->line("   • المعرّف: {$client->id}");
        $this->command->line("   • الكود: {$client->code}");
        $this->command->line("   • الاسم: {$client->trade_name}");
    }

    /**
     * ربطُ حساب «عميل نقدي» القائم بالعميل — لا إنشاءُ حسابٍ جديد.
     *
     * الحسابُ مزروعٌ في [AccountSeeder] بمعرّفٍ ثابت، وتشير إليه الإعدادات
     * المحاسبية (`cash_client`) و`Modules\OnlineStore` في قيودها. فربطُه هو
     * ما ينقص، وإنشاءُ حسابٍ فرعيٍّ ثانٍ يفرّق رصيدَ العميل الواحد على
     * حسابين لا يجمعهما كشف.
     */
    private function linkAccount(Client $client): void
    {
        $account = Account::find(SystemAccountIds::CASH_CLIENT);

        if (! $account) {
            $this->command->warn(
                'لم يُعثَر على حساب «عميل نقدي» ('.SystemAccountIds::CASH_CLIENT.') — شغّل AccountSeeder أولاً. '
                .'العميل أُنشئ بلا حسابٍ مربوط.'
            );

            return;
        }

        if ((int) $account->client_id === (int) $client->id) {
            return;
        }

        if ($account->client_id) {
            $this->command->warn(
                'حساب «عميل نقدي» مربوطٌ بعميلٍ آخر (#'.$account->client_id.') — لم يُغيَّر الربط.'
            );

            return;
        }

        $account->client_id = $client->id;
        $account->save();

        $this->command->line("   • رُبِط بالحساب #{$account->id} ({$account->code})");
    }
}
