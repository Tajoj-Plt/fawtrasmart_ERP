<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Location;
use App\Models\Neighborhood;
use App\Support\AccountingSettings;
use Illuminate\Database\Seeder;

/*
 * ملاحظة مهمّة حول جدول statuses: أعمدته الحقيقية section/name/is_default،
 * لا status_type/status_name كما كانت النسخة الأولى من هذا الـ seeder تفترض
 * (كانت الاستعلامات تفشل بـ "Unknown column"). وحالات العملاء بالذات لم
 * تُهاجَر بعد إلى هذا الجدول أصلاً — قسمها فيه NULL ولا صفوف مزروعة، والعمود
 * الفعلي الذي يقرأه بقية النظام لحالة العميل هو عمود clients.status (Enum
 * active/inactive/...)، وهو ما يُضبَط مباشرةً أدناه دون المرور بهذا الجدول.
 */

class DefaultClientSeeder extends Seeder
{
    /**
     * قيمة العميل الافتراضي في النظام — استخدمه كـ fallback للعمليات
     * التي تحتاج عميل افتراضي (فواتير بلا عميل محدد، تحويلات مخزنية، إلخ).
     *
     * مطابقٌ لـ Client::DEFAULT_CLIENT_ID.
     */
    public const DEFAULT_CLIENT_ID = 1;

    /**
     * يُنشئ العميل الافتراضي بنفس التخزين المستعمل في
     * ClientController::store() — لا بحقولٍ عشوائية.
     *
     * السبب: أول نسخة من هذا الـ seeder كانت تمرّر إلى Client::create()
     * حقولاً غير موجودة أصلاً في $fillable ولا في جدول clients (مثل
     * legal_name، commercial_record، address، zip_code، is_active…) —
     * فكانت تُسقَط بصمتٍ عبر Mass Assignment بلا أي خطأ، والعميل الناتج
     * كان يفتقد أهمّ جزء: **الحساب المحاسبي المرتبط** الذي تعتمد عليه بقيّة
     * النظام (كشوف الحساب، القيود، رصيد العميل) عند التعامل مع أي عميل.
     *
     * لذلك هنا:
     *   1) العميل بحقول $fillable الحقيقية فقط، والكود = المعرّف (نفس
     *      اصطلاح `$client->code = $client->id;` في store()).
     *   2) جهة الاتصال الأساسية — `$client->contacts()->create(...)`.
     *   3) الحساب الفرعي تحت «شجرة حسابات العملاء» بنفس منطق توليد الكود
     *      المستعمل في store().
     *
     * الموقع الجغرافي (Location/Neighborhood) لا يُستخرَج من Google هنا —
     * لا سياق طلبٍ فعليّ ولا إحداثيات مُدخَلة من مستخدم. فيُثبَّت على مركز
     * الرياض مباشرةً (المدينة نفسها التي يحملها الفرع الرئيسي في
     * MainBranchSeeder)، والحيّ على فرع الرياض ذاته عبر
     * `resolveTerritoryBranchForNeighborhood()` — نفس الدالة التي يستعملها
     * store() لتحديد فرع الإقليم من فرع العميل.
     *
     * ما يُتعمَّد تخطّيه لأنه يحتاج سياق طلب HTTP لا يتوفر هنا (مستخدمٍ
     * مسجّل دخوله، استدعاء Google API):
     *   - حساب مستخدم دخول (User) — العميل الافتراضي نظامي، لا يسجّل دخوله
     *   - سجلّ النشاط (Log) ونقاط الولاء (PointsService) — auth()->id()
     *     غير متوفر وقت التشغيل عبر artisan db:seed
     *   - جدول زيارات الموظفين — لا موظف مسؤول عن عميلٍ نظامي
     */
    public function run(): void
    {
        if (Client::where('id', self::DEFAULT_CLIENT_ID)->exists()) {
            $this->command->info('العميل الافتراضي موجود بالفعل. تم التخطي.');

            return;
        }

        $mainBranch = Branch::where('is_main', true)->first();
        if (! $mainBranch) {
            $this->command->error('لا توجد فرع رئيسية. يرجى تشغيل BranchSeeder أولاً.');

            return;
        }

        // ═══════════ العميل ═══════════
        // نفس اصطلاح store(): يُبنى بـ fill() على الحقول الحقيقية، ثم يُحفَظ
        // أولاً للحصول على معرّف، ثم يُحدَّث الكود ليطابق المعرّف. والمعرّف هنا
        // يُفرَض صراحةً بخاصية مباشرة (كما تُفرَض status_id في store()) لأن
        // بقية النظام يعتمد على Client::DEFAULT_CLIENT_ID = 1 تحديداً.
        $client = new Client;
        $client->id = self::DEFAULT_CLIENT_ID;
        $client->status = 'active';
        $client->fill([
            'trade_name' => 'عميل افتراضي (نظام)',
            'phone' => '0000000000',
            'mobile' => '0000000000',
            'email' => null,
            'tax_number' => null,
            'commercial_registration' => null,
            'credit_limit' => 0,
            'currency' => 'SAR',
            'client_type' => 1,
            'notes' => 'عميل نظامي يُستعمل كقيمة افتراضية للعمليات التي بلا عميل محدَّد.',
            'branch_id' => $mainBranch->id,
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

        // ═══════════ الموقع الجغرافي والحيّ ═══════════
        // إحداثيات مركز الرياض (الملقا/منطقة الأعمال المركزية) — أيّ نقطة
        // داخل المدينة تكفي؛ الاشتراط الوحيد أن تكون في الرياض تحديداً.
        $client->locations()->create([
            'branch_id' => $mainBranch->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        $territoryBranch = $mainBranch->resolveTerritoryBranchForNeighborhood();
        Neighborhood::create([
            'name' => 'الرياض',
            'region_id' => $territoryBranch->id,
            'client_id' => $client->id,
        ]);

        // ═══════════ الحساب المحاسبي المرتبط ═══════════
        // نفس منطق store(): حساب فرعي تحت حساب «العملاء» في الإعدادات
        // المحاسبية، بكودٍ يتبع آخر حساب فرعي موجود.
        $customers = AccountingSettings::account('clients_account');
        if ($customers) {
            $lastChild = Account::where('parent_id', $customers->id)->orderBy('code', 'desc')->first();
            $newCode = $lastChild ? $this->generateNextCode($lastChild->code) : $customers->code.'1';

            while (Account::where('code', $newCode)->exists()) {
                $newCode = $this->generateNextCode($newCode);
            }

            $customerAccount = new Account;
            $customerAccount->name = $client->trade_name;
            $customerAccount->client_id = $client->id;
            $customerAccount->account_type = $customers->account_type;
            $customerAccount->code = $newCode;
            $customerAccount->balance_type = 'debit';
            $customerAccount->parent_id = $customers->id;
            $customerAccount->opening_balance = 0;
            $customerAccount->opening_balance_type = $customers->balance_type;
            $customerAccount->is_active = true;
            $customerAccount->save();
        } else {
            $this->command->warn('لم يُعثَر على حساب «العملاء» في الإعدادات المحاسبية — لم يُنشأ حساب فرعي للعميل الافتراضي.');
        }

        $this->command->info('✅ تم إنشاء العميل الافتراضي بنجاح:');
        $this->command->line("   • المعرّف: {$client->id}");
        $this->command->line("   • الكود: {$client->code}");
        $this->command->line("   • الاسم: {$client->trade_name}");
    }

    /**
     * نفس ClientController::generateNextCode() — الرقم الأخير من الكود +١.
     */
    private function generateNextCode(string $lastChildCode): string
    {
        $lastNumber = intval(substr($lastChildCode, -1));
        $newNumber = $lastNumber + 1;

        return substr($lastChildCode, 0, -1).$newNumber;
    }
}
