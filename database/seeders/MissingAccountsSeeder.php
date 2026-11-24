<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * زرعُ حسابات الشجرة القياسية الناقصة وحدها — على قاعدةٍ عامرة.
 *
 * **العطل**
 *
 * شجرة الحسابات القياسية معرَّفة في [AccountSeeder] وحده، وكلُّ إصدارٍ
 * يضيف إليها حسابات جديدة (التزامات المكافآت، محافظ العملاء، حسابات
 * الشحن…). لكن تلك البذرة مُستبدِلة لا مُضيفة: تبدأ بـ
 * `Account::query()->delete()`، فتمحو شجرةً حرّرها المحاسب وتقطع كلَّ قيدٍ
 * معلَّقٍ بها. ولذلك يرفض [SystemFoundationSeeder] استدعاءها متى كان
 * `accounts` غيرَ فارغ — وهو الصواب.
 *
 * فكانت النتيجة أن تركيباً قائماً لا يصله حسابٌ جديدٌ أبداً: لا زرَّ في
 * «تحديث النظام» يضيفه، ولا سبيلَ إلا إنشاؤه يدوياً حساباً حساباً من شاشة
 * شجرة الحسابات، بكودٍ وأبٍ ونوعٍ يطابق ما ينتظره الكود.
 *
 * **الحل**
 *
 * تُقرأ القائمةُ نفسها من `AccountSeeder::definitions()` بلا أي كتابة،
 * ويُزرع منها ما لا كودَ له في الجدول. والمفتاح الطبيعيّ هو `code` — عمودٌ
 * فريدٌ في `accounts`، ولا يتكرّر كودٌ واحد في قائمة البذرة.
 *
 * **ثلاث دقائق تلزم الانتباه**
 *
 *   ١. `parent_id` في القائمة يشير إلى *معرّفات البذرة* لا إلى معرّفات هذه
 *      القاعدة — فالمعرّف ٢٦ هنا قد يكون حساباً آخر تماماً. فيُحلّ الأبُ
 *      بكوده: كود الأب في القائمة ⇦ الحساب الحامل لذلك الكود في الجدول.
 *      ولذلك يمرّ الزرعُ أكثر من مرّة: الابنُ الذي لم يُزرع أبوه بعد يُؤجَّل
 *      إلى الدورة التالية، حتى تتوقّف الإضافة.
 *
 *   ٢. يُحتفَظ بمعرّف البذرة متى كان شاغراً. `SystemAccountIds` وبذرةُ
 *      «الحسابات المحاسبية» تربطان بمعرّفاتٍ ثابتة (٣٨ للرصيد الافتتاحي،
 *      ١٤ للخزينة الرئيسية…)، فحسابٌ يحمل معرّفه القياسيّ يجد نفسه مربوطاً.
 *      وإن كان المعرّف مشغولاً بحسابٍ آخر تُركت القاعدةُ تُرقّم — ولا يُمسّ
 *      شاغلُ المعرّف بحال.
 *
 *   ٣. لا يُكتب في صفٍّ قائم حرف: لا اسمٌ ولا كودٌ ولا أبٌ ولا رصيد. الصفُّ
 *      الموجود ملكُ المحاسب، وإن خالف القائمة.
 *
 * @see AccountSeeder::definitions()
 */
class MissingAccountsSeeder extends Seeder
{
    /**
     * معاينةٌ لا تكتب: تُحسَب القائمةُ وتُعرض ولا يُدرَج صفّ.
     *
     * إضافةُ عشراتِ الحسابات إلى شجرةٍ حيّةٍ دفعةً واحدة ليست خطوةً تُضغط
     * على عمياء — فليُر ما سيُضاف أوّلاً.
     */
    public bool $dryRun = false;

    /** عدّادُ المعرّفات الوهمية في المعاينة — لا يلمس القاعدة */
    protected int $simulatedId = 0;

    public function run(): void
    {
        if (! Schema::hasTable('accounts')) {
            $this->command?->warn('جدول «accounts» غير موجود — لا شيء ليُزرع.');

            return;
        }

        $definitions = AccountSeeder::definitions();

        if ($definitions === []) {
            $this->command?->warn('قائمة الشجرة القياسية فارغة — لا شيء ليُزرع.');

            return;
        }

        // الأعمدة الموجودة فعلاً: قاعدةٌ قديمة قد تنقصها أعمدةٌ أضافها تحديثٌ لاحق
        $columns = array_flip(Schema::getColumnListing('accounts'));

        // كود الحساب ⇦ معرّفه في *هذه* القاعدة
        $liveIdByCode = [];
        foreach (DB::table('accounts')->select('id', 'code')->get() as $row) {
            if ($row->code === null || $row->code === '') {
                continue;
            }

            $liveIdByCode[(string) $row->code] = (int) $row->id;
        }

        // معرّف البذرة ⇦ كوده، لحلّ الآباء بالكود لا بالمعرّف
        $codeBySeedId = [];
        foreach ($definitions as $definition) {
            $codeBySeedId[(int) $definition['id']] = (string) $definition['code'];
        }

        $takenIds = array_flip(
            DB::table('accounts')->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $added = [];
        $orphans = [];
        $pending = $definitions;

        do {
            $progressed = false;
            $deferred = [];

            foreach ($pending as $definition) {
                $code = (string) $definition['code'];

                // موجودٌ أصلاً — لا يُمسّ
                if (isset($liveIdByCode[$code])) {
                    continue;
                }

                $parentId = null;

                if ($definition['parent_id'] !== null) {
                    $parentCode = $codeBySeedId[(int) $definition['parent_id']] ?? null;

                    // أبٌ لا تعريفَ له في القائمة: عطبٌ في البذرة، يُبلَّغ ولا يُزرع يتيماً
                    if ($parentCode === null) {
                        $orphans[] = $definition;

                        continue;
                    }

                    // الأبُ لم يُزرع بعد — يُؤجَّل إلى الدورة التالية
                    if (! isset($liveIdByCode[$parentCode])) {
                        $deferred[] = $definition;

                        continue;
                    }

                    $parentId = $liveIdByCode[$parentCode];
                }

                $newId = $this->insertAccount($definition, $parentId, $columns, $takenIds);

                $liveIdByCode[$code] = $newId;
                $takenIds[$newId] = true;
                $added[] = $definition['code'].' — '.$definition['name'];
                $progressed = true;
            }

            $pending = $deferred;
        } while ($progressed && $pending !== []);

        $this->report($added, $pending, $orphans);
    }

    /**
     * إدراجُ الصفّ — بمعرّف البذرة متى كان شاغراً، وإلا بترقيم القاعدة.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, int>  $columns
     * @param  array<int, mixed>  $takenIds
     */
    protected function insertAccount(array $definition, ?int $parentId, array $columns, array $takenIds): int
    {
        $now = now();

        $row = [
            'name' => $definition['name'],
            'code' => $definition['code'],
            'parent_id' => $parentId,
            'type' => $definition['type'],
            'balance_type' => $definition['balance_type'],
            'account_type' => $definition['account_type'],
            'balance' => 0.0,
            'opening_balance' => 0.0,
            'opening_balance_type' => $definition['balance_type'],
            'is_active' => true,
            'is_system' => $definition['is_system'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $row = array_intersect_key($row, $columns);

        $seedId = (int) $definition['id'];
        $keepsSeedId = ! isset($takenIds[$seedId]);

        if ($this->dryRun) {
            // معرّفٌ وهميٌّ لا يُكتب — يكفي أن يكون فريداً ليُعلَّق به الأبناء
            return $keepsSeedId ? $seedId : --$this->simulatedId;
        }

        if ($keepsSeedId) {
            $row['id'] = $seedId;
            DB::table('accounts')->insert($row);

            return $seedId;
        }

        return (int) DB::table('accounts')->insertGetId($row);
    }

    /**
     * التقرير — «لا جديد» رسالةُ نجاحٍ لا صمت.
     *
     * @param  array<int, string>  $added
     * @param  array<int, array<string, mixed>>  $stuck
     * @param  array<int, array<string, mixed>>  $orphans
     */
    protected function report(array $added, array $stuck, array $orphans): void
    {
        if ($added === []) {
            $this->command?->info('شجرة الحسابات مكتملة — لا حساب ناقص.');
        } else {
            $this->command?->info($this->dryRun
                ? 'معاينة: '.count($added).' حساباً ناقصاً سيُضاف إلى شجرة الحسابات (لم يُكتب شيء):'
                : 'أُضيف '.count($added).' حساباً ناقصاً إلى شجرة الحسابات:');

            foreach ($added as $line) {
                $this->command?->line('  • '.$line);
            }
        }

        foreach ($stuck as $definition) {
            $this->command?->warn(
                'تعذّر زرعُ «'.$definition['name'].'» (كود '.$definition['code'].') — أبوه غير موجود ولم يُزرع.'
            );
        }

        foreach ($orphans as $definition) {
            $this->command?->warn(
                'الحساب «'.$definition['name'].'» (كود '.$definition['code'].') يشير إلى أبٍ لا تعريفَ له في البذرة.'
            );
        }

        if ($added !== [] && ! $this->dryRun) {
            $this->command?->info('إن كان بين الجديد حسابٌ تربطه عملية، شغّل بعده زرّ «الحسابات المحاسبية».');
        }
    }
}
