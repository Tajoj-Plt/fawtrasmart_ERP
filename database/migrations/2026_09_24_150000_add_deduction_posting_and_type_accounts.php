<?php

use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قيدُ الخصمية عند اعتمادها، وحسابٌ لكلِّ نوعٍ من أنواعها.
 *
 * ── ما كان ──
 *
 * الخصميةُ لا قيدَ لها حتى يُصرف الراتب، ثمّ يُقيِّد قيدُ الاستحقاق كلَّ
 * الخصميات على حسابٍ واحد (`payroll_deductions`). فإن لم يُربط ذلك المفتاح —
 * وهو غير مربوطٍ في الشجرات التي لا تحوي الكود 530، إذ يبحث عنه ترحيلُ
 * `add_payroll_deductions_account_and_link` بالكود وحده — رُفض صرفُ الراتب
 * كلُّه لأيِّ موظفٍ عليه خصمية.
 *
 * ── ما صار ──
 *
 *   1. `deductions.posted_amount` و`deductions.journal_entry_id` — ما قُيِّد
 *      من الخصمية خارج قيد الراتب. يُبدأ للقائم بمبلغه المسوّى: ذلك الجزءُ
 *      غطّاه قيدُ الراتب القديم، فلا يُقيَّد ثانيةً.
 *   2. مفتاحٌ لكلِّ نوع: `payroll_deduction_{type}` في «الحسابات المحاسبية»،
 *      يُنشأ بلا ربطٍ فيسقط القيدُ على المفتاح العام حتى يُربط.
 *   3. المفتاحُ العام يُربط إن كان فارغاً: بحسابٍ «خصميات الموظفين» تحت أبِ
 *      الرواتب — بالكود 530 إن وُجد، وإلا بالأب المربوط في المفتاح
 *      `salaries_wages_parent`. لا معرّفَ ثابتاً في الشيفرة.
 *
 * لا يُحذف عمودٌ ولا جدولٌ في `down()` — انظر قاعدة «Retire, do not drop».
 */
return new class extends Migration
{
    private const GENERAL_KEY = 'payroll_deductions';

    private const GROUP = 'الرواتب والموظفين';

    private const NAME = 'خصميات الموظفين';

    /** الأنواعُ كما في `Deduction::types()` — منسوخةٌ لتبقى الهجرةُ مستقلّةً عن الموديل */
    private const TYPES = [
        'absence' => 'غياب',
        'late' => 'تأخير',
        'early_leave' => 'انصراف مبكر',
        'half_day' => 'نصف يوم',
        'violation' => 'مخالفة',
        'custody_damage' => 'تلف عهدة',
        'admin_penalty' => 'عقوبة إدارية',
        'other' => 'أخرى',
    ];

    public function up(): void
    {
        if (Schema::hasTable('deductions')) {
            Schema::table('deductions', function (Blueprint $table) {
                if (! Schema::hasColumn('deductions', 'posted_amount')) {
                    $table->decimal('posted_amount', 12, 2)->default(0)->after('settled_amount');
                }
                if (! Schema::hasColumn('deductions', 'journal_entry_id')) {
                    $table->unsignedBigInteger('journal_entry_id')->nullable()->after('posted_amount');
                }
            });

            // القائمُ: ما سُوِّي منه غطّاه قيدُ الراتب القديم
            DB::table('deductions')
                ->where('posted_amount', 0)
                ->where('settled_amount', '>', 0)
                ->update(['posted_amount' => DB::raw('settled_amount')]);
        }

        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach (self::TYPES as $type => $label) {
            DB::table('accounting_settings')->insertOrIgnore([
                'key' => 'payroll_deduction_'.$type,
                'name' => 'خصميات '.$label,
                'description' => 'الحساب الدائن في قيد اعتماد خصمية «'.$label.'» — إن تُرك فارغاً استُعمل حساب «خصميات الموظفين» العام',
                'group' => self::GROUP,
                'account_id' => null,
                'account_code' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->linkGeneralKey();

        AccountingSettings::forget();
    }

    public function down(): void
    {
        // لا يُحذف عمودٌ ولا مفتاح: قيودٌ قائمةٌ تشير إليها
    }

    private function linkGeneralKey(): void
    {
        $setting = DB::table('accounting_settings')->where('key', self::GENERAL_KEY)->first();

        if ($setting && $setting->account_id && DB::table('accounts')->where('id', $setting->account_id)->exists()) {
            return;
        }

        $account = $this->findOrCreateAccount();
        if (! $account) {
            return;
        }

        if (! $setting) {
            DB::table('accounting_settings')->insert([
                'key' => self::GENERAL_KEY,
                'name' => self::NAME,
                'description' => 'الحساب المقابل لخصميات الموظفين حين لا يُضبط لنوع الخصمية حسابٌ خاص',
                'group' => self::GROUP,
                'account_id' => $account->id,
                'account_code' => $account->code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('accounting_settings')->where('key', self::GENERAL_KEY)->update([
            'account_id' => $account->id,
            'account_code' => $account->code,
            'updated_at' => now(),
        ]);
    }

    private function findOrCreateAccount(): ?object
    {
        if (! Schema::hasTable('accounts')) {
            return null;
        }

        $byCode = DB::table('accounts')->where('code', '5310')->first(['id', 'code']);
        if ($byCode) {
            return $byCode;
        }

        // الحسابُ القائم بالاسم في أيّ موضعٍ من المصروفات — شجرةٌ بأكوادٍ غير
        // قياسية تحمله بكودٍ آخر؛ يُربط هو ولا يُنشأ بجواره توأم.
        $byName = DB::table('accounts as a')
            ->where('a.name', self::NAME)
            ->where('a.account_type', 'expenses')
            ->orderByDesc(DB::raw('(select count(*) from journal_entry_details d where d.account_id = a.id)'))
            ->orderBy('a.id')
            ->first(['a.id', 'a.code']);
        if ($byName) {
            return $byName;
        }

        $parent = DB::table('accounts')->where('code', '530')->first(['id', 'code'])
            ?? $this->parentFromSetting();

        if (! $parent) {
            return null;
        }

        $existing = DB::table('accounts')
            ->where('parent_id', $parent->id)
            ->where('name', self::NAME)
            ->first(['id', 'code']);
        if ($existing) {
            return $existing;
        }

        $code = $this->nextChildCode($parent);

        $id = DB::table('accounts')->insertGetId([
            'name' => self::NAME,
            'code' => $code,
            'parent_id' => $parent->id,
            'type' => 'sub',
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

        return (object) ['id' => $id, 'code' => $code];
    }

    private function parentFromSetting(): ?object
    {
        $id = DB::table('accounting_settings')
            ->where('key', 'salaries_wages_parent')
            ->where('is_active', true)
            ->value('account_id');

        return $id ? DB::table('accounts')->where('id', $id)->first(['id', 'code']) : null;
    }

    /** الكودُ التالي بين الأبناء — على نمط الشجرة القائمة (كود الأب + تسلسل). */
    private function nextChildCode(object $parent): string
    {
        $parentCode = (string) $parent->code;

        $max = DB::table('accounts')
            ->where('parent_id', $parent->id)
            ->where('code', 'like', $parentCode.'%')
            ->pluck('code')
            ->filter(fn ($c) => ctype_digit((string) $c))
            ->map(fn ($c) => (int) $c)
            ->max();

        $candidate = $max ? (string) ($max + 1) : $parentCode.'1';

        while (DB::table('accounts')->where('code', $candidate)->exists()) {
            $candidate = (string) ((int) $candidate + 1);
        }

        return $candidate;
    }
};
