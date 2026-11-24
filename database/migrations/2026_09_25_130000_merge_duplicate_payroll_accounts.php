<?php

use App\Models\Account;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * حذفُ حسابات الرواتب والعمولات المكرَّرة — الفارغ منها وحده.
 *
 * ── من أين جاء التكرار؟ ──
 *
 *   - [AccountSeeder] كان يعرّف للغرض الواحد أكثر من حساب: ثلاثةُ آباءٍ للسلف
 *     (1243/1244/1210)، وثلاثةُ حساباتٍ للعمولة (5308/5309/5602)، و«نهاية
 *     خدمة مستحقة» (2155) بجوار «مخصص نهاية الخدمة» (226).
 *   - [MissingAccountsSeeder] يزرع كلَّ كودٍ قياسيٍّ ناقص. فالشجرةُ المستوردة
 *     بأكوادٍ خاصّة (رواتب وأجور = 52522 مثلاً) تتلقّى شجرةً قياسيّةً موازية:
 *     «الرواتب والأجور» 530 وأبناؤه بجوار حساباتها العاملة.
 *   - ترحيلا «خصميات الموظفين» كانا يُنشئان الحساب متى لم يجدا الكود 5310 —
 *     وإن كان في الشجرة حسابٌ بالاسم نفسه بكودٍ آخر.
 *
 * ── ما يفعله ──
 *
 * لكلّ غرض (مجموعةُ أسماءٍ بنوع حسابٍ واحد) تُجمع الحساباتُ الحاملة لها.
 * فإن كانت أكثرَ من واحد اختير «الباقي»: الأكثرُ قيوداً (مع قيود فروعه)، ثمّ
 * المربوطُ في «الحسابات المحاسبية»، ثمّ ذو الأبناء، ثمّ الكودُ القياسيّ. وكلُّ
 * مكرَّرٍ غيره يُحذف **فقط** إن كان:
 *
 *   - بلا أيّ قيدٍ في `journal_entry_details`،
 *   - بلا مرجعٍ في أيّ عمودٍ يحمل معرّف حساب في القاعدة كلّها (خزائن، موظفون،
 *     سندات، إعدادات نقاط البيع…) — سوى جداول الإعدادات أدناه،
 *   - وأبناؤه (إن وُجدوا) فارغون مثله: فيُنقلون إلى الباقي بكودٍ جديد على
 *     قاعدة الشجرة، أو يُحذفون إن كان للباقي ابنٌ بالاسم نفسه.
 *
 * وقبل الحذف يُنقل ربطُه: صفوفُ `accounting_settings` تُربط بالباقي (عدا
 * مجموعة السلف — «سلف» اسمٌ عامٌّ لا يُستدلّ به على الغرض)، والأعمدةُ
 * المتقاعدة في `payroll_settings` و`social_insurance_settings` كذلك.
 *
 * **المكرَّرُ الذي عليه قيدٌ يبقى كما هو** بربطه كلّه، ويُسجَّل في السجلّ:
 * دمجُ قيودٍ مرحَّلة قرارُ محاسب، لا ترحيل.
 *
 * آمنٌ للتكرار: بعد أوّل تشغيلٍ لا يبقى مكرَّرٌ فارغ. وبلا `down()` — الحذفُ
 * لم يمسّ إلا حساباتٍ فارغةً بلا مرجع.
 */
return new class extends Migration
{
    /** معاينة: يُحسب كلُّ شيء ويُتراجع عنه — للفحص من سكربت. */
    public bool $dryRun = false;

    /** @var array<string, array<int, string>> */
    public array $report = ['deleted' => [], 'moved' => [], 'relinked' => [], 'kept' => [], 'warnings' => []];

    /** @var array<int, array{0:string,1:string}>|null [جدول، عمود] */
    private ?array $refColumns = null;

    /** @var array<int, true> آباءُ حساباتٍ حُذفت في هذا التشغيل — يُنظر فيها آخراً */
    private array $touchedParents = [];

    /**
     * الأغراض — الأوراقُ أولاً ثمّ الآباء، كي يُفرَغ الأبُ المكرَّر من أبنائه
     * المكرَّرين قبل أن يُنظر فيه.
     *
     * @return array<string, array{type: string, names: array<int, string>, code: string, relink: bool}>
     */
    private function groups(): array
    {
        return [
            'خصميات الموظفين' => ['type' => 'expenses', 'names' => ['خصميات الموظفين'], 'code' => '5310', 'relink' => true],
            'عمولات الموظفين' => ['type' => 'expenses', 'names' => ['عمولات الموظفين', 'عمولة', 'عمولات مبيعات', 'عمولات المبيعات'], 'code' => '5309', 'relink' => true],
            'رواتب الموظفين' => ['type' => 'expenses', 'names' => ['رواتب الموظفين'], 'code' => '5301', 'relink' => true],
            'مصروف نهاية الخدمة' => ['type' => 'expenses', 'names' => ['مكافأة نهاية الخدمة', 'مصروف مكافأة نهاية الخدمة', 'مصروف نهاية الخدمة'], 'code' => '5307', 'relink' => true],
            'مصروف التأمينات' => ['type' => 'expenses', 'names' => ['تأمينات اجتماعية', 'التأمينات الاجتماعية', 'مصروف التأمينات الاجتماعية', 'مصروف التأمينات'], 'code' => '5305', 'relink' => true],
            'مخصص نهاية الخدمة' => ['type' => 'liabilities', 'names' => ['مخصص نهاية الخدمة', 'مخصص مكافأة نهاية الخدمة', 'نهاية خدمة مستحقة'], 'code' => '226', 'relink' => true],
            'تأمينات حصة الموظف' => ['type' => 'liabilities', 'names' => ['تأمينات - حصة الموظف', 'تأمينات مستحقة — حصة الموظف'], 'code' => '2161', 'relink' => true],
            'تأمينات حصة المنشأة' => ['type' => 'liabilities', 'names' => ['تأمينات - حصة الشركة', 'تأمينات مستحقة — حصة المنشأة'], 'code' => '2162', 'relink' => true],
            // الآباء
            'التأمينات الاجتماعية (الخصوم)' => ['type' => 'liabilities', 'names' => ['التأمينات الاجتماعية'], 'code' => '216', 'relink' => true],
            'الرواتب والأجور' => ['type' => 'expenses', 'names' => ['الرواتب والأجور', 'رواتب وأجور'], 'code' => '530', 'relink' => true],
            'رواتب ومستحقات الموظفين' => ['type' => 'liabilities', 'names' => ['رواتب ومستحقات الموظفين'], 'code' => '215', 'relink' => true],
            'سلف الموظفين' => ['type' => 'assets', 'names' => ['سلف الموظفين', 'السلف', 'سلف'], 'code' => '1243', 'relink' => false],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('journal_entry_details')) {
            return;
        }

        DB::beginTransaction();

        try {
            foreach ($this->groups() as $label => $group) {
                $this->mergeGroup($label, $group);
            }

            $this->dropEmptiedParents();
            $this->checkInstallmentLink();

            $this->dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        Cache::forget('accounts_tree');
        AccountingSettings::forget();

        Log::info('merge_duplicate_payroll_accounts'.($this->dryRun ? ' (معاينة)' : ''), $this->report);
    }

    public function down(): void
    {
        // لا تراجع: لم يُحذف إلا حسابٌ فارغٌ بلا مرجع
    }

    // ───────────────────────── الدمج ─────────────────────────

    /** @param array{type: string, names: array<int, string>, code: string, relink: bool} $group */
    private function mergeGroup(string $label, array $group): void
    {
        $wanted = array_map(fn ($n) => $this->norm($n), $group['names']);

        // المقارنةُ بعد توحيد الهمزات والتاء المربوطة والشرطات — تُقرأ الأسماءُ
        // كلُّها لأنّ LIKE لا يوحّد «الخدمة» و«الخدمه». والنوعُ من جذر الشجرة
        // لا من `account_type`: حساباتٌ أُنشئت يدوياً تتركه فارغاً.
        $all = DB::table('accounts')->get(['id', 'code', 'name', 'parent_id']);
        $parentOf = $all->pluck('parent_id', 'id')->all();
        $codeOf = $all->pluck('code', 'id')->all();

        $members = $all
            ->filter(fn ($a) => in_array($this->norm($a->name), $wanted, true))
            ->filter(fn ($a) => $this->rootType((int) $a->id, $parentOf, $codeOf) === $group['type'])
            ->values();

        if ($members->count() < 2) {
            return;
        }

        $rank = fn ($a) => [
            $this->subtreeLines((int) $a->id),
            $this->settingKeys((int) $a->id) !== [] ? 1 : 0,
            DB::table('accounts')->where('parent_id', $a->id)->exists() ? 1 : 0,
            (string) $a->code === $group['code'] ? 1 : 0,
            -(int) $a->id,
        ];
        $sorted = $members->all();
        usort($sorted, fn ($x, $y) => $rank($y) <=> $rank($x));
        $keeper = $sorted[0];

        foreach ($members as $dup) {
            if ($dup->id === $keeper->id || ! DB::table('accounts')->where('id', $dup->id)->exists()) {
                continue;
            }

            // الباقي من نسل المكرَّر (أو العكس): ليسا توأمين بل أبٌ وابن
            if ($this->isAncestor($dup->id, $keeper->id) || $this->isAncestor($keeper->id, $dup->id)) {
                $this->report['kept'][] = $this->describe($dup)." — أبٌ/ابنٌ لـ{$this->describe($keeper)} لا توأم ({$label})";

                continue;
            }

            $this->mergeInto($dup, $keeper, $label, $group['relink']);
        }
    }

    private function mergeInto(object $dup, object $keeper, string $label, bool $relink): void
    {
        $why = $this->blockers($dup, $keeper, $relink);

        if ($why !== []) {
            $this->report['kept'][] = $this->describe($dup).' — مكرَّرٌ لـ'.$this->describe($keeper).' ('.$label.') وبقي: '.implode('، ', $why);

            return;
        }

        // الأبناء (فارغون — تحقّق blockers): يُنقلون إلى الباقي أو يُحذفون إن كان له توأمُهم
        foreach (DB::table('accounts')->where('parent_id', $dup->id)->orderBy('id')->get(['id', 'code', 'name', 'parent_id']) as $child) {
            $twin = DB::table('accounts')->where('parent_id', $keeper->id)->get(['id', 'code', 'name'])
                ->first(fn ($a) => $this->norm($a->name) === $this->norm($child->name));

            if ($twin) {
                $this->relinkSettings($child->id, $twin);
                $this->repointRetired($child->id, (int) $twin->id);
                DB::table('accounts')->where('id', $child->id)->delete();
                $this->report['deleted'][] = $this->describe($child).' — ابنٌ مكرَّرٌ لـ'.$this->describe($twin);

                continue;
            }

            $code = $this->nextChildCode($keeper);
            DB::table('accounts')->where('id', $child->id)->update([
                'parent_id' => $keeper->id,
                'code' => $code,
                'updated_at' => now(),
            ]);
            if (Schema::hasTable('accounting_settings')) {
                DB::table('accounting_settings')->where('account_id', $child->id)->update(['account_code' => $code, 'updated_at' => now()]);
            }
            $this->report['moved'][] = "#{$child->id} «{$child->name}» {$child->code} → تحت ".$this->describe($keeper)." بكود {$code}";
        }

        if ($relink) {
            $this->relinkSettings($dup->id, $keeper);
        }
        $this->repointRetired($dup->id, (int) $keeper->id);

        DB::table('accounts')->where('id', $dup->id)->delete();
        if ($dup->parent_id) {
            $this->touchedParents[(int) $dup->parent_id] = true;
        }
        $this->report['deleted'][] = $this->describe($dup).' — مكرَّرٌ فارغٌ لـ'.$this->describe($keeper)." ({$label})";

        if (! $this->dryRun) {
            Account::updateParentBalances($keeper->id, true);
            if ($dup->parent_id && DB::table('accounts')->where('id', $dup->parent_id)->exists()) {
                Account::updateParentBalances((int) $dup->parent_id, true);
            }
        }
    }

    /**
     * ما يمنع حذفَ المكرَّر — فارغةٌ تعني أنه يُحذف.
     *
     * @return array<int, string>
     */
    private function blockers(object $dup, object $keeper, bool $relink): array
    {
        $why = [];

        $lines = DB::table('journal_entry_details')->where('account_id', $dup->id)->count();
        if ($lines > 0) {
            $why[] = "عليه {$lines} سطر قيد";
        }

        /*
            المربوطُ في «الحسابات المحاسبية» يُنقل ربطُه إلى الباقي فقط حين يكون
            الباقي باسمه نفسه أو مربوطاً هو أيضاً — أي حين لا شكّ أنّه الحسابُ
            العامل للغرض. أمّا نقلُ مفتاحٍ إلى حسابٍ باسمٍ آخر غيرِ مربوط
            («عمولات مبيعات» مثلاً) فتغييرٌ لوجهة القيود يقرّره المحاسب.
        */
        $keys = $this->settingKeys($dup->id);
        if ($keys !== []) {
            $sameName = $this->norm($dup->name) === $this->norm($keeper->name);
            if (! $relink || (! $sameName && $this->settingKeys((int) $keeper->id) === [])) {
                $why[] = 'مربوطٌ في الحسابات المحاسبية ('.implode('، ', $keys).')';
            }
        }

        // حسابُ طرفٍ (عميل/مورد/مستودع) — ليس من شجرة الرواتب وإن وافق اسمُه
        foreach (['client_id', 'supplier_id', 'storehouse_id'] as $column) {
            if (Schema::hasColumn('accounts', $column) && DB::table('accounts')->where('id', $dup->id)->whereNotNull($column)->exists()) {
                $why[] = "حسابٌ مربوطٌ بطرف ({$column})";
            }
        }

        foreach ($this->references($dup) as $ref) {
            $why[] = 'مرجعٌ في '.$ref;
        }

        foreach (DB::table('accounts')->where('parent_id', $dup->id)->get(['id', 'code', 'name']) as $child) {
            $childLines = $this->subtreeLines($child->id);
            if ($childLines > 0 || DB::table('accounts')->where('parent_id', $child->id)->exists()) {
                $why[] = "ابنُه «{$child->name}» عليه قيود أو له أبناء";

                continue;
            }
            foreach ($this->references($child) as $ref) {
                $why[] = "ابنُه «{$child->name}» له مرجعٌ في {$ref}";
            }
        }

        return $why;
    }

    /**
     * أبٌ من الشجرة القياسية فرغ من أبنائه بما حُذف هنا (مثل «التأمينات
     * الاجتماعية» 216 بعد حذف 2161/2162 المكرَّرَين) — يُحذف بالشروط نفسها.
     */
    private function dropEmptiedParents(): void
    {
        $names = [];
        foreach ($this->groups() as $group) {
            foreach ($group['names'] as $name) {
                $names[] = $this->norm($name);
            }
        }

        foreach (array_keys($this->touchedParents) as $id) {
            $parent = DB::table('accounts')->where('id', $id)->first(['id', 'code', 'name', 'parent_id']);
            if (! $parent || ! in_array($this->norm($parent->name), $names, true)
                || DB::table('accounts')->where('parent_id', $id)->exists()
                || DB::table('journal_entry_details')->where('account_id', $id)->exists()
                || $this->settingKeys($id) !== [] || $this->references($parent) !== []) {
                continue;
            }

            $this->repointRetiredAway($id);
            DB::table('accounts')->where('id', $id)->delete();
            $this->report['deleted'][] = $this->describe($parent).' — أبٌ قياسيٌّ فرغ من أبنائه المكرَّرين';
        }
    }

    /** أبٌ فارغٌ يُحذف ولا باقي له: يُفرَّغ عمودُه المتقاعد إن أشار إليه. */
    private function repointRetiredAway(int $id): void
    {
        foreach ($this->retiredColumns() as [$table, $column]) {
            DB::table($table)->where($column, $id)->update([$column => null]);
        }
    }

    // ───────────────────────── الربط ─────────────────────────

    private function relinkSettings(int $fromId, object $to): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach (DB::table('accounting_settings')->where('account_id', $fromId)->pluck('key') as $key) {
            DB::table('accounting_settings')->where('key', $key)->update([
                'account_id' => $to->id,
                'account_code' => $to->code !== null ? (string) $to->code : null,
                'updated_at' => now(),
            ]);
            $this->report['relinked'][] = "{$key}: #{$fromId} → ".$this->describe($to);
        }
    }

    /** الأعمدةُ المتقاعدة (لا يقرؤها شيء) — تتبع الباقي كي لا تشير إلى محذوف. */
    private function repointRetired(int $fromId, int $toId): void
    {
        foreach ($this->retiredColumns() as [$table, $column]) {
            $n = DB::table($table)->where($column, $fromId)->update([$column => $toId]);
            if ($n) {
                $this->report['relinked'][] = "{$table}.{$column}: #{$fromId} → #{$toId}";
            }
        }
    }

    /** @return array<int, array{0:string,1:string}> */
    private function retiredColumns(): array
    {
        $cols = [];
        foreach (['payroll_settings', 'social_insurance_settings'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (Schema::getColumnListing($table) as $column) {
                if (str_ends_with($column, 'account_id')) {
                    $cols[] = [$table, $column];
                }
            }
        }

        return $cols;
    }

    /** @return array<int, string> */
    private function settingKeys(int $accountId): array
    {
        return Schema::hasTable('accounting_settings')
            ? DB::table('accounting_settings')->where('account_id', $accountId)->pluck('key')->all()
            : [];
    }

    /**
     * كلُّ عمودٍ في القاعدة يحمل معرّف حسابٍ (أو كودَه في الأعمدة النصية)
     * ويشير إلى هذا الحساب — سوى القيود (تُعدّ وحدها) وجداول الإعدادات.
     *
     * @return array<int, string>
     */
    private function references(object $account): array
    {
        $hits = [];

        foreach ($this->refColumns() as [$table, $column, $isText]) {
            $q = DB::table($table)->where($column, $isText ? (string) $account->id : $account->id);
            if ($isText && $account->code !== null && $account->code !== '') {
                $q->orWhere($column, (string) $account->code);
            }
            $n = $q->count();
            if ($n > 0) {
                $hits[] = "{$table}.{$column} ({$n})";
            }
        }

        return $hits;
    }

    /** @return array<int, array{0:string,1:string,2:bool}> */
    private function refColumns(): array
    {
        if ($this->refColumns !== null) {
            return $this->refColumns;
        }

        $skipTables = ['accounts', 'journal_entry_details', 'accounting_settings', 'payroll_settings', 'social_insurance_settings', 'migrations'];
        $rows = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS d FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%account%'
               AND DATA_TYPE IN ('int','bigint','smallint','mediumint','varchar','char')"
        );

        $cols = [];
        foreach ($rows as $row) {
            $c = strtolower($row->c);
            if (in_array($row->t, $skipTables, true)
                || str_contains($c, 'period') || str_contains($c, 'number')
                || str_contains($c, 'holder') || str_contains($c, 'name')) {
                continue;
            }
            $cols[] = [$row->t, $row->c, in_array($row->d, ['varchar', 'char'], true)];
        }

        return $this->refColumns = $cols;
    }

    // ───────────────────────── أدوات ─────────────────────────

    private function subtreeLines(int $id): int
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier !== []) {
            $frontier = DB::table('accounts')->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return DB::table('journal_entry_details')->whereIn('account_id', $ids)->count();
    }

    /**
     * نوعُ الحساب من جذره: 1 أصول، 2 خصوم، 3 حقوق ملكية، 4 إيرادات، 5 مصروفات.
     *
     * @param  array<int, int|null>  $parentOf
     * @param  array<int, string|null>  $codeOf
     */
    private function rootType(int $id, array $parentOf, array $codeOf): ?string
    {
        $seen = [];
        while (! empty($parentOf[$id]) && ! isset($seen[$id])) {
            $seen[$id] = true;
            $id = (int) $parentOf[$id];
        }

        return [
            '1' => 'assets', '2' => 'liabilities', '3' => 'equity', '4' => 'revenues', '5' => 'expenses',
        ][mb_substr((string) ($codeOf[$id] ?? ''), 0, 1)] ?? null;
    }

    private function isAncestor(int $ancestorId, int $id): bool
    {
        $seen = [];
        $parent = DB::table('accounts')->where('id', $id)->value('parent_id');
        while ($parent && ! isset($seen[$parent])) {
            if ((int) $parent === $ancestorId) {
                return true;
            }
            $seen[$parent] = true;
            $parent = DB::table('accounts')->where('id', $parent)->value('parent_id');
        }

        return false;
    }

    /** كودُ الابن التالي — قاعدةُ الشجرة (`AccountingOperations::nextChildAccountCode`). */
    private function nextChildCode(object $parent): string
    {
        $prefix = (int) $parent->code;
        if ($prefix <= 0) {
            throw new \RuntimeException('الحساب «'.$parent->name.'» بلا كود رقمي — لا يُنقل تحته ابن.');
        }

        for ($width = 1; $width <= 4; $width++) {
            $span = 10 ** $width;
            $base = $prefix * $span;
            $taken = DB::table('accounts')->whereBetween('code', [$base + 1, $base + $span - 1])
                ->pluck('code')->map(fn ($c) => (int) $c)->flip();
            for ($i = 1; $i < $span; $i++) {
                if (! $taken->has($base + $i)) {
                    return (string) ($base + $i);
                }
            }
        }

        throw new \RuntimeException('تعذّر توليد كود فرعي تحت '.$prefix);
    }

    private function norm(?string $name): string
    {
        $s = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', '—', '–', '-', 'ـ'], ['ا', 'ا', 'ا', 'ه', 'ي', ' ', ' ', ' ', ''], (string) $name);

        return preg_replace('/\s+/u', ' ', trim($s));
    }

    private function describe(object $a): string
    {
        return "#{$a->id} «{$a->name}» ({$a->code})";
    }

    /**
     * `installment_deferred_profit` مربوطٌ بحساب رواتب؟ — كان ترحيلُ التقسيط
     * يبحث بالكود 2151 وحده، وهو في الشجرة القياسية «رواتب مستحقة الدفع».
     * يُسجَّل ولا يُصلَح آلياً: قد تكون عليه قيودُ تقسيطٍ فعلاً.
     */
    private function checkInstallmentLink(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        $id = DB::table('accounting_settings')->where('key', 'installment_deferred_profit')->value('account_id');
        $account = $id ? DB::table('accounts')->where('id', $id)->first(['id', 'code', 'name']) : null;

        if ($account && (str_contains((string) $account->name, 'رواتب') || str_contains((string) $account->name, 'موظف'))) {
            $this->report['warnings'][] = 'installment_deferred_profit مربوطٌ بحساب رواتب '.$this->describe($account).' — اربطه بـ«أرباح مرابحة مؤجلة» من شاشة الحسابات المحاسبية.';
        }
    }
};
