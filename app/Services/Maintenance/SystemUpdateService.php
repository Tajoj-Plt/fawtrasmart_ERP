<?php

namespace App\Services\Maintenance;

use App\Models\Backup;
use App\Services\Backup\BackupService;
use App\Support\Migration\PrimaryKeyRepair;
use App\Support\Migration\SchemaReconciler;
use App\Support\MigrationLedger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * تشغيل الترحيلات ومهام تحديث البيانات من شاشة الإعدادات.
 *
 * لماذا من الواجهة أصلاً؟ لأن التركيب على استضافةٍ مشتركة بلا SSH هو الحالة
 * الغالبة هنا: ملفّات التحديث تُرفَع بالـFTP، ثم تبقى الترحيلات غير مُشغَّلة
 * والبيانات الجديدة غير مزروعة، فتظهر الشاشات الجديدة بأعمدةٍ ناقصة أو
 * بأخطاء «عمود غير موجود». هذه الشاشة هي بديل سطر الأوامر لتلك الحالة.
 *
 * ما لا تفعله هذه الخدمة أبداً:
 * - لا `migrate:fresh` ولا `rollback` ولا `db:wipe` — الترحيل هنا إلى الأمام
 *   فقط. التراجع عن ترحيلٍ في نظامٍ يعمل قرارٌ لا يُتَّخَذ بزرّ في شاشة.
 * - لا تقبل اسم أمرٍ من الطلب: تأخذ مفتاحاً وتطابقه على `SystemUpdateRegistry`.
 */
class SystemUpdateService
{
    /** حدّ زمني للأمر الواحد — الترحيلات على قاعدةٍ كبيرة تتجاوز الافتراضي. */
    private const TIME_LIMIT = 600;

    /**
     * مدّة تخزين جواب «هل يوجد تحديث معلَّق؟».
     *
     * عشر دقائق ليست تأخيراً في اكتشاف تحديثٍ جديد — المفتاح نفسه يتغيّر
     * فور وصول ملفّ ترحيل. وإنما هي سقفٌ لحالةٍ واحدة: ترحيلٌ شُغِّل من
     * سطر الأوامر لا من الشاشة، فلم يُسقِط أحدٌ الكاش.
     */
    private const PENDING_TTL = 600;

    /**
     * حالة الترحيلات: ما نُفِّذ وما ينتظر.
     *
     * تُقرَأ من `Migrator` نفسه لا من `migrate:status`: مخرَج ذلك الأمر جدولٌ
     * مُنسَّق للطرفية، وتحليلُه نصّياً يتكسّر مع كل تغييرٍ في تنسيقه.
     *
     * @return array{ran: int, pending: array<int, string>, repository: bool}
     */
    public function migrationStatus(): array
    {
        $migrator = app('migrator');
        $repository = $migrator->getRepository();

        // القاعدة بلا جدول `migrations` — تركيبٌ لم يُهجَّر قطّ. كل الملفّات
        // معلّقة، ولا يجوز أن يسقط الاستعلام بدل أن يقول ذلك
        if (! $repository->repositoryExists()) {
            return [
                'ran' => 0,
                'pending' => array_keys($migrator->getMigrationFiles($this->paths())),
                'repository' => false,
            ];
        }

        $ran = $repository->getRan();
        $files = array_keys($migrator->getMigrationFiles($this->paths()));

        return [
            'ran' => count($ran),
            'pending' => array_values(array_diff($files, $ran)),
            'repository' => true,
        ];
    }

    /**
     * تقريرُ ما ستفعله الترحيلات المعلّقة قبل تشغيلها.
     *
     * يُحسَب عند الطلب ولا يُخزَّن: هو يُقرأ على شاشةٍ واحدة يفتحها مسؤولٌ
     * مرّةً بعد التحديث، لا على كل طلب — بخلاف `pendingMigrationCount()`.
     * وتخزينُه كان سيُنتج أسوأ ما يمكن هنا: تقريرَ أمانٍ قديماً يُقرأ على أنه
     * وصفُ ما سيقع الآن.
     *
     * @return array{
     *     files: array<int, array<string, mixed>>,
     *     counts: array{safe: int, altering: int, destructive: int},
     *     destructive: bool,
     *     altering: bool,
     *     impact: array<int, array<string, mixed>>
     * }
     */
    public function safetyReport(): array
    {
        $migrator = app('migrator');
        $pending = $this->migrationStatus()['pending'];

        return app(MigrationSafetyScanner::class)->scan(
            $pending,
            $migrator->getMigrationFiles($this->paths())
        );
    }

    /**
     * تشغيل الترحيلات المعلّقة.
     *
     * `--force` لازم: الأمر يرفض العمل في بيئة الإنتاج بلا تأكيدٍ تفاعلي،
     * ولا طرفيةَ هنا تُجيبه. التأكيد أُخِذ من المستخدم في الشاشة قبل الطلب.
     *
     * ── حارسان قبل الأمر، لا تحذيرٌ بعده ──
     *
     * **الأوّل: لا حذف. بتّةً.** كان الزرّ ينادي `migrate --force` مباشرةً،
     * فترحيلٌ يحذف عموداً فيه بيانات يُنفَّذ بلا أن يعرف الضاغطُ أن حذفاً وقع
     * أصلاً. والآن يُفحص المعلَّقُ نصّياً (`MigrationSafetyScanner`)، وما وُجد
     * فيه حذفٌ **له أثرٌ فعلي على هذه القاعدة** لا يُشغَّل من هذه الشاشة بحال —
     * لا بتأكيدٍ ولا بخانةٍ تُعلَّم. وقد كانت هنا خانةُ تأكيدٍ تسمح بذلك فأُزيلت
     * بقرار صاحب النظام: خانةٌ تُعلَّم مرّةً تُعلَّم بعدها بالعادة.
     * وتفصيلُ ما يجري حينئذٍ في `runUpToFirstDestructive()`.
     *
     * **والثاني: نسخةٌ احتياطية قبل التنفيذ.** الترحيل من هذه الشاشة إلى الأمام
     * فقط — لا `rollback` فيها عمداً — فالنسخة هي طريق العودة الوحيد. وكانت
     * الشاشة تكتفي بسؤال «هل أخذت نسخة؟» في نافذة تأكيد، والجواب عنه نقرةٌ
     * لا فعل. صارت تُؤخَذ فعلاً، وفشلُها يُلغي الترحيل: تحديثٌ بلا طريق عودة
     * ليس ما يُقدَم عليه بلا علم.
     *
     * @param  bool  $backupFirst  خُذ نسخة قاعدة بيانات قبل التنفيذ
     * @return array{ok: bool, output: string, blocked?: bool, report?: array<string, mixed>}
     */
    public function runMigrations(bool $backupFirst = true, ?int $userId = null): array
    {
        /* قبل كل شيء: أجدولُ السجلّ نفسه قابلٌ للكتابة؟

           قاعدةٌ مستوردةٌ قد يصلها `migrations`.`id` بلا `AUTO_INCREMENT`،
           فيُنفَّذ الترحيلُ بنجاح ثمّ يسقط تسجيلُه بالخطأ 1364 «Field 'id'
           doesn't have a default value». والأثر أسوأ من رسالةٍ حمراء: الترحيل
           تمّ ولم يُسجَّل، فيبقى «معلَّقاً» أبداً، ويبقى النظام محبوساً على
           هذه الشاشة مهما ضُغط الزرّ. يُصلَح هنا لا هناك — قبل أن يُكتب
           أوّلُ صفّ. انظر [\App\Support\MigrationLedger]. */
        $ledger = MigrationLedger::repair();

        /* ثمّ الجداول كلُّها، بالعلّة نفسها.
           إصلاحُ `migrations` وحده يُمرّر التسجيل ثمّ يسقط أوّلُ إدراجٍ في
           `permissions` بالخطأ 1364 نفسِه — والطرحُ الذي أسقط مفتاحاً أسقط
           مئة. فتُمسح القاعدة مرّةً هنا: كلُّ جدولٍ له `id` صحيحُ النوع بلا
           ترقيمٍ تلقائي يُعاد إليه مفتاحُه — بشرط أن تكون معرّفاتُه صالحةً
           أصلاً، فلا يتبدّل معرّفٌ تشير إليه جداولُ أخرى.
           انظر [\App\Support\Migration\PrimaryKeyRepair]. */
        $keys = PrimaryKeyRepair::repairAll();

        $report = $this->safetyReport();

        if ($backupFirst) {
            $backup = $this->backupBeforeUpdate($userId);

            if (! $backup['ok']) {
                return [
                    'ok' => false,
                    'blocked' => true,
                    'output' => "تعذّر أخذ النسخة الاحتياطية، فلم تُشغَّل الترحيلات.\n\n"
                        .$backup['output']
                        ."\n\nخُذ نسخةً من شاشة النسخ الاحتياطي ثم أعد المحاولة، أو أزل علامة "
                        .'«خذ نسخة احتياطية قبل التشغيل» إن كنت قد أخذتها بنفسك.',
                ];
            }
        }

        /* مصالحةُ المفاتيح الأجنبية مركَّبةٌ طوال التشغيلة.

           السببُ أنّ المفتاح الأجنبي يشترط تطابقَ نوعِ العمودين وإشارتِهما،
           وقواعدُ العملاء بُنيت من مجموعةِ ترحيلاتٍ أقدم ثم رُقّعت: جدولٌ
           قديمٌ مفتاحُه `int unsigned` وجدولٌ جديدٌ يشير إليه بـ`bigint
           unsigned` — فيسقط الترحيل بالخطأ 1005/errno 150 في منتصف التحديث،
           ويقف كلُّ ما بعده. ولا حارسَ في الملفّ يمنع ذلك: `hasColumn` يقول
           «موجود» ولا يقول «لا يجتمعان»، و`try/catch` حول `foreign()` لا
           يلتقط شيئاً لأن الـBlueprint يصفّ الأمر ولا ينفّذه في موضعه.

           فتُوائَم الأنواع قبل الأمر، وما تعذّرت مواءمتُه بلا مساسٍ بالقيم
           يُتخطّى مفتاحُه ويُقال — قيدُ سلامةٍ ناقصٌ أهونُ من نظامٍ مقفل.
           انظر [\App\Support\Migration\SchemaReconciler]. */
        SchemaReconciler::install();

        try {
            // لا حذفَ في المعلَّق ⇒ الأمر المعتاد دفعةً واحدة: أسرع، ودفعةُ ترحيلٍ
            // واحدة في جدول `migrations` كما يتوقّعها أي أداةٍ تقرأه لاحقاً
            $result = $report['destructive']
                ? $this->runUpToFirstDestructive($report)
                : $this->run('migrate', ['--force' => true]);
        } finally {
            $foreignKeys = SchemaReconciler::notes();
            SchemaReconciler::uninstall();
        }

        // النسخة أُخذت وجدولُ سجلّها معدوم، والترحيلُ أنشأه الآن: يُتبنّى
        // ملفُّها كي لا تبقى على القرص نسخةٌ لا يعرفها السجلّ — وهي بالضبط
        // النسخة التي قد يُحتاج إليها إن ساء الترحيل الذي سبقها
        if ($backupFirst && isset($backup) && $backup['ok'] && ($backup['recorded'] ?? true) === false && ($result['ok'] ?? false)) {
            $this->adoptUnrecordedBackup();
        }

        /* ما جرى للمفاتيح الأجنبية يُلحَق بالمخرَج لا يُطوى: مفتاحٌ تُخطّي
           نقصٌ في القاعدة يجب أن يعرفه من يقرأ التقرير، ومواءمةُ نوعٍ تغييرٌ
           في المخطَّط يُعلَن. */
        if ($foreignKeys !== []) {
            $result['output'] .= "\n\nالمفاتيح الأجنبية:\n• ".implode("\n• ", $foreignKeys);
        }

        // إصلاحُ السجلّ يُقال ولا يُخفى: المستخدم يرى ترحيلاتٍ تُسجَّل الآن
        // بعد أن كانت تسقط، فلا بدّ أن يعرف أن شيئاً في قاعدته أُصلح
        $prelude = array_merge($ledger['notes'], $keys['notes']);

        if ($prelude !== []) {
            $result['output'] = "إصلاحُ بنية القاعدة قبل التحديث:\n• "
                .implode("\n• ", $prelude)."\n\n".$result['output'];
        }

        if ($backupFirst && isset($backup) && $backup['ok']) {
            $result['output'] = $backup['output']."\n\n".$result['output'];
        }

        return $result;
    }

    /**
     * تشغيل المعلَّق ملفّاً ملفّاً، والتوقّف **قبل** أوّل ترحيلٍ يحذف.
     *
     * ── لماذا لا يُشغَّل الحذف أصلاً، ولو بتأكيد؟ ──
     *
     * قرارُ صاحب النظام: التحديث من هذه الشاشة لا يحذف شيئاً — لا عموداً ولا
     * جدولاً ولا صفّاً — بحال. وكانت هنا خانةُ تأكيدٍ تسمح بذلك، فأُزيلت: خانةٌ
     * تُعلَّم مرّةً تُعلَّم بعدها بالعادة، وأوّلُ مرّةٍ تُعلَّم فيها بلا قراءةٍ
     * هي المرّة التي يضيع فيها عمود.
     *
     * ── ولماذا التوقّف لا التخطّي؟ ──
     *
     * `migrate` تُشغّل المعلَّق كلَّه دفعةً واحدة، فلا سبيل إلى استثناء واحدٍ
     * من وسطها. والتخطّي — أي تشغيلُ ما بعده وتركُه — أخطر من الحذف نفسه:
     * الترحيلات مرتَّبة، وما بعد المتروك قد يُضيف مفتاحاً على عمودٍ كان يُفترض
     * أنه أُعيد تشكيله، فتُبنى القاعدة على أساسٍ لم يوضع. فيُشغَّل ما قبله
     * كاملاً — وهو الأكثر — ويُوقَف عنده، ويُقال للمستخدم اسمُه وما يفعله.
     *
     * والملفّ الواحد يُشغَّل بـ`--path` مطلقاً مع `--realpath`: هذا هو
     * السبيل الوحيد لتنفيذ ترحيلٍ بعينه دون إخوته.
     *
     * @return array{ok: bool, output: string, blocked?: bool, report?: array<string, mixed>}
     */
    private function runUpToFirstDestructive(array $report): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles($this->paths());

        $ran = [];
        $lines = [];
        $stoppedAt = null;

        foreach ($report['files'] as $file) {
            if ($file['level'] === MigrationSafetyScanner::DESTRUCTIVE) {
                $stoppedAt = $file;

                break;
            }

            $path = $files[$file['name']] ?? null;

            if ($path === null) {
                // اسمٌ في قائمة المعلَّق بلا ملفّ: لا يُقفَز عنه بصمت — الوقوف
                // هنا أصدق من متابعةٍ تترك فجوةً في الترتيب
                $stoppedAt = $file + ['missing' => true];

                break;
            }

            $result = $this->run('migrate', [
                '--force' => true,
                '--path' => $path,
                '--realpath' => true,
            ]);

            $lines[] = ($result['ok'] ? '✔ ' : '✖ ').$file['name'];

            if (! $result['ok']) {
                $lines[] = $result['output'];

                return [
                    'ok' => false,
                    'output' => $this->composeStepwise($ran, $lines, null),
                ];
            }

            $ran[] = $file['name'];
        }

        return [
            'ok' => true,
            // «أُوقف» لا «فشل»: لم يقع عطل، وإنما عمل الحارس. والشاشة تعرضه
            // تنبيهاً لا خطأً — انظر `SystemUpdateController::migrate()`
            'blocked' => $stoppedAt !== null,
            'report' => $report,
            'output' => $this->composeStepwise($ran, $lines, $stoppedAt),
        ];
    }

    /** تقرير التشغيل المتدرّج: ما نُفِّذ، وأين وقف، ولماذا. */
    private function composeStepwise(array $ran, array $lines, ?array $stoppedAt): string
    {
        $out = [];

        $out[] = $ran === []
            ? 'لم يُنفَّذ أي ترحيل.'
            : 'نُفِّذ '.count($ran).' ترحيلاً بأمان:';

        foreach ($lines as $line) {
            $out[] = '    '.$line;
        }

        if ($stoppedAt === null) {
            return implode("\n", $out);
        }

        $out[] = '';
        $out[] = '⛔ أُوقف التنفيذ عند هذا الترحيل ولم يُشغَّل:';
        $out[] = '    '.$stoppedAt['name'];

        if (! empty($stoppedAt['missing'])) {
            $out[] = '    السبب: ملفّ الترحيل غير موجود على القرص.';
        } else {
            $out[] = '    السبب: يحذف بياناتٍ موجودةً في قاعدتك —';

            foreach ($stoppedAt['operations'] as $operation) {
                if (! $operation['applies']) {
                    continue;
                }

                $out[] = '      • '.$this->describeOperation($operation);
            }
        }

        $out[] = '';
        $out[] = 'وما بعده لم يُشغَّل أيضاً: الترحيلات مرتَّبة، وتشغيلُ اللاحق قبل السابق '
            .'يبني القاعدة على أساسٍ لم يوضع.';
        $out[] = 'هذه الشاشة لا تحذف شيئاً بحال. إن كان هذا الحذف مقصوداً من التحديث '
            .'فهو قرارُ من كتب التحديث لا قرارُ زرّ — راجعه معه، ويُشغَّل من سطر الأوامر بعد نسخةٍ احتياطية.';

        return implode("\n", $out);
    }

    /**
     * نسخة قاعدة بيانات قبل التحديث.
     *
     * `database` لا `both`: المطلوب طريقُ عودةٍ للبيانات، ونسخُ ملفّات الرفع
     * معها يضاعف الزمن والحجم بلا أن يحمي من شيءٍ يفعله ترحيل.
     *
     * ── ونسخةٌ بلا سجلّ خيرٌ من لا نسخة ──
     *
     * جدول `backups` ينشأ بترحيلٍ هو نفسه قد يكون معلَّقاً: قاعدةٌ مستوردة من
     * نظام أقدم من ميزة النسخ الاحتياطي تصل إلى هذه الشاشة وجداولُ النسخ غير
     * موجودة، فيفشل تسجيل الصفّ فتفشل النسخة فيُلغى الترحيل — وتبقى القاعدة
     * التي جاءت إلى هنا لتُحدَّث عالقةً بلا مخرج. فصار `BackupService` يأخذ
     * النسخة ويترك الصفّ إن لم يكن له جدول، ويُتبنّى الملفُّ بعد الترحيل.
     *
     * @return array{ok: bool, output: string, recorded?: bool}
     */
    public function backupBeforeUpdate(?int $userId = null): array
    {
        try {
            $backup = app(BackupService::class)->run(
                'database',
                Backup::TRIGGER_PRE_UPDATE,
                $userId
            );

            if ($backup->status !== Backup::STATUS_COMPLETED) {
                return [
                    'ok' => false,
                    'output' => 'فشلت النسخة الاحتياطية: '.($backup->error_message ?: 'سببٌ غير مذكور.'),
                ];
            }

            return [
                'ok' => true,
                'recorded' => $backup->exists,
                'output' => '✔ نسخة احتياطية قبل التحديث: '.$backup->file_name.' ('.$backup->uuid.')'
                    .($backup->exists ? '' : "\n  (الملفّ على القرص، ولم يُسجَّل في سجلّ النسخ لأن جدوله لم يكن موجوداً بعد — يُضاف إليه بعد الترحيل.)"),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => 'فشلت النسخة الاحتياطية: '.$e->getMessage()];
        }
    }

    /**
     * إدخال نسخةِ ما قبل التحديث في السجلّ بعد أن أنشأ الترحيلُ جدولَه.
     *
     * `reconcile()` يمسح القرص ويتبنّى كلّ أرشيفٍ لا صفَّ له، فيكفي نداؤه.
     * وفشلُه لا يُفشل التحديث: الترحيل تمّ والنسخة على القرص، وغيابُ صفٍّ في
     * سجلّ العرض أهونُ من إظهار تحديثٍ ناجح فاشلاً.
     */
    private function adoptUnrecordedBackup(): void
    {
        try {
            if (Schema::hasTable('backups')) {
                app(BackupService::class)->reconcile();
            }
        } catch (Throwable $e) {
            Log::warning('تعذّر إدخال نسخة ما قبل التحديث في السجلّ', ['error' => $e->getMessage()]);
        }
    }

    /**
     * وصفُ الأثر المتلف بعبارةٍ يقرؤها مسؤولٌ لا مطوّر.
     *
     * «سيُحذف العمود X من الجدول Y وفيه ٤٬٢٠٠ صفّاً» تُقرأ ويُتَّخذ عليها قرار؛
     * أمّا «هذا الترحيل متلف» فلا يُقرَّر عليها شيء.
     */
    private function describeOperation(array $operation): string
    {
        $where = $operation['table'] !== null ? ' في الجدول «'.$operation['table'].'»' : '';
        $columns = $operation['columns'] !== []
            ? ' ('.implode('، ', $operation['columns']).')'
            : '';

        // «مطابقة لشرط» لا «كل الصفوف»: الفرق بين تنظيفِ صفَّين قديمين
        // وإفراغِ جدولٍ فيه ألف صفّ — ولا يجوز أن تلتبس إحداهما بالأخرى
        $rows = '';

        if ($operation['rows'] !== null) {
            $rows = $operation['scope'] === 'filtered'
                ? ' — الصفوف المطابقة لشرطٍ فيه، والجدول فيه '.number_format($operation['rows']).' صفّاً'
                : ' — وفيه '.number_format($operation['rows']).' صفّاً';
        }

        $note = $operation['note'] !== null ? ' ('.$operation['note'].')' : '';

        return $operation['label'].$columns.$where.$rows.$note;
    }

    /**
     * تشغيل مهمّة تحديث بيانات من السجلّ.
     *
     * @return array{ok: bool, output: string}
     */
    public function runTask(string $key): array
    {
        $task = SystemUpdateRegistry::find($key);

        if ($task === null) {
            return ['ok' => false, 'output' => "مهمّة غير مسجَّلة: {$key}"];
        }

        if (! $this->isRunnable($task)) {
            return [
                'ok' => false,
                'output' => 'الجدول «'.$task['guard_table'].'» يحتوي بيانات — '
                    .'هذه المهمّة تُدرِج صفوفاً جديدة ولا تحدّث القائم، فتشغيلها الآن يُكرّرها.',
            ];
        }

        $arguments = $task['arguments'] ?? [];

        // `db:seed` يسأل التأكيد في الإنتاج مثل `migrate` تماماً
        if ($task['command'] === 'db:seed') {
            $arguments['--force'] = true;
        }

        return $this->run($task['command'], $arguments);
    }

    /**
     * هل يمكن تشغيل المهمّة الآن؟ — `guard_table` غير فارغ يعني لا.
     *
     * الجدول غير الموجود يعني ترحيلاً لم يُشغَّل بعد؛ نتركها قابلةً للتشغيل
     * ويفشل السيدر برسالته الواضحة بدل أن نخفي الزرّ بلا سبب ظاهر.
     */
    public function isRunnable(array $task): bool
    {
        $table = $task['guard_table'] ?? null;

        if ($table === null) {
            return true;
        }

        return $this->rowCount($table) === 0;
    }

    /**
     * عدد صفوف كل جدولٍ محروس — تعرضه الشاشة تفسيراً لقفل الزرّ.
     *
     * @return array<string, int|null> `null` لجدولٍ غير موجود بعد
     */
    public function guardCounts(): array
    {
        $counts = [];

        foreach (SystemUpdateRegistry::tasks() as $task) {
            $table = $task['guard_table'] ?? null;

            if ($table !== null && ! array_key_exists($table, $counts)) {
                $counts[$table] = Schema::hasTable($table) ? $this->rowCount($table) : null;
            }
        }

        return $counts;
    }

    /**
     * هل يوجد تحديثٌ معلَّق؟ — السؤال الذي يُطرح على كل طلب.
     *
     * يُخزَّن مؤقتاً لأن الجواب يُقرأ في كل صفحة: حسابُه يفتح مجلّدات
     * الترحيلات كلَّها (مسار التطبيق + مسار كل وحدة) ويستعلم جدول
     * `migrations` — كلفةٌ لا تُدفع مرّتين في الثانية.
     *
     * ومفتاح الكاش نفسه مبنيٌّ على تواريخ تعديل تلك المجلّدات: رفعُ ملفّ
     * ترحيلٍ جديد بالـFTP يغيّر تاريخ تعديل المجلّد، فيتغيّر المفتاح،
     * فيُعاد الحساب فوراً بلا انتظار انتهاء المدّة. هذا هو بيت القصيد —
     * أن يُكتشف التحديث لحظةَ وصوله لا بعد ساعة.
     */
    public function pendingMigrationCount(): int
    {
        try {
            return (int) Cache::remember(
                $this->pendingCacheKey(),
                self::PENDING_TTL,
                fn () => count($this->migrationStatus()['pending'])
            );
        } catch (Throwable) {
            // قاعدة بيانات غير متاحة أو كاشٌ معطَّل: ليس هذا موضع الإعلان
            // عن العطل — الوسيط الذي يسأل هذا السؤال يجب أن يمرّ لا أن يسقط
            return 0;
        }
    }

    /**
     * إسقاط الجواب المخزَّن — يُنادى بعد كل ترحيلٍ يُشغَّل من الشاشة.
     *
     * بدونه يبقى شريط «يوجد تحديث» ظاهراً بعد نجاح الترحيل حتى تنتهي مدّة
     * الكاش، فيظنّ المستخدم أن التحديث لم يُنفَّذ فيعيده.
     */
    public function forgetPendingCache(): void
    {
        try {
            Cache::forget($this->pendingCacheKey());
        } catch (Throwable) {
            // كاشٌ معطَّل لا يمنع الترحيل من أن يكون قد نجح
        }
    }

    /**
     * التحديث الشامل: الترحيلات ثم كل مهمّة بياناتٍ آمنة التكرار.
     *
     * لماذا زرٌّ واحد؟ لأن التحديث الحقيقي ليس ترحيلاً وحده: الجدول الجديد
     * يصل فارغاً من صلاحيته ومن مفتاح إعداده ومن قالبه، فتظهر الشاشة بلا
     * صلاحية تفتحها. تشغيلُ الأربعين مهمّة يدوياً واحدةً واحدةً بعد كل
     * تحديثٍ هو ما يجعلها لا تُشغَّل أصلاً.
     *
     * **قاعدة هذا الزرّ: لا يكتب فوق بياناتك.** لا يُشغَّل فيه إلا ما وُسِم
     * بـ`additive` في السجلّ — سيدرٌ فُحِص وثبت أنه يُنشئ الناقص ولا يمسّ صفّاً
     * قائماً. وذلك يغطّي ما يحتاجه التحديثُ فعلاً: صلاحيةٌ جديدة كي تظهر الشاشة،
     * ومفتاح إعدادٍ جديد بقيمته المبدئية، ومهمّةٌ مجدولة جديدة.
     *
     * **ما يُستثنى ولا يُشغَّل هنا أبداً:**
     * - كل ما بلا وسم `additive`: فيه `updateOrCreate` أو `updateOrInsert` أو
     *   `upsert` أو `truncate` على صفٍّ يملكه المستخدم — يعيد تفعيل تطبيقٍ
     *   عطّلتَه، أو يكتب فوق اسمِ وحدةٍ أو سعرِ شحنٍ حرّرته، أو يعيد ربط
     *   الحسابات المحاسبية. تبقى بأزرارها اليدوية ويُقرَّر تشغيلُها منفردةً.
     * - ما وُسِم بـ`caution`: يكتب فوق اختيارٍ للمستخدم (القالب الافتراضي مثلاً).
     * - ما كان جدولُه المحروس ممتلئاً: إدراجٌ بلا فحص = صفوفٌ مكرَّرة.
     *
     * وإن سقط الترحيل تُوقَف البقيّة: زراعةُ بياناتٍ في قاعدةٍ نصفِ مهجَّرة
     * تُضيف عطلاً فوق عطل، وتُخفي رسالة الترحيل الأصلية تحت ركامٍ من المخرَج.
     *
     * @return array{ok: bool, steps: array<int, array{label: string, state: string, output: string}>}
     */
    public function runAll(bool $backupFirst = true, ?int $userId = null): array
    {
        $steps = [];
        $ok = true;

        $pending = $this->migrationStatus()['pending'];

        if ($pending === []) {
            $steps[] = [
                'label' => 'ترحيلات قاعدة البيانات',
                'state' => 'skipped',
                'output' => 'لا ترحيلات معلّقة.',
            ];
        } else {
            $result = $this->runMigrations($backupFirst, $userId);
            $this->forgetPendingCache();

            $halted = ! $result['ok'] || ! empty($result['blocked']);

            $steps[] = [
                'label' => 'ترحيلات قاعدة البيانات ('.count($pending).')',
                'state' => $halted ? 'failed' : 'ok',
                'output' => $result['output'],
            ];

            /*
                تُوقَف البقيّة كذلك حين **نجح** ما شُغِّل ووقف الحارس عند ترحيلٍ
                يحذف: بعضُ المعلَّق لم يُنفَّذ بعد، وقد يكون فيه جدولٌ ينتظره
                سيدر. فزراعةُ بياناتٍ في بنيةٍ نصفِ محدَّثة تُضيف عطلاً على
                انتظار، وتدفن سببَ الوقوف تحت ركامٍ من المخرَج.
            */
            if ($halted) {
                return ['ok' => false, 'blocked' => ! empty($result['blocked']), 'steps' => $steps];
            }
        }

        foreach (SystemUpdateRegistry::tasks() as $key => $task) {
            // الشرط الأوّل والأهمّ: بلا وسم `additive` لا تعمل هنا مهما كان
            // سببُ غيابه — الافتراض أن السيدر يكتب فوق صفٍّ قائم حتى يُفحَص
            // ويُوسَم، لا العكس
            if (empty($task['additive'])) {
                $steps[] = [
                    'label' => $task['label'],
                    'state' => 'skipped',
                    'output' => ! empty($task['caution'])
                        ? 'مستثناة — تكتب فوق اختيارك: '.$task['caution']
                        : 'مستثناة من الشامل — قد تكتب فوق قيمٍ قائمة. شغّلها من زرّها إن أردت.',
                ];

                continue;
            }

            if (! $this->isRunnable($task + ['key' => $key])) {
                $steps[] = [
                    'label' => $task['label'],
                    'state' => 'skipped',
                    'output' => 'الجدول «'.$task['guard_table'].'» مزروعٌ أصلاً.',
                ];

                continue;
            }

            $result = $this->runTask($key);
            $ok = $ok && $result['ok'];

            $steps[] = [
                'label' => $task['label'],
                'state' => $result['ok'] ? 'ok' : 'failed',
                'output' => $result['output'],
            ];
        }

        return ['ok' => $ok, 'steps' => $steps];
    }

    // ==================== الداخل ====================

    /**
     * مسارات الترحيلات: مسار التطبيق وما سجّلته الوحدات بـ`loadMigrationsFrom`.
     *
     * @return array<int, string>
     */
    private function paths(): array
    {
        return array_merge(app('migrator')->paths(), [database_path('migrations')]);
    }

    /**
     * مفتاح الكاش — بصمةُ **ما هو متاح** و**ما هو منفَّذ** معاً.
     *
     * ── الشقّ الأوّل: ملفّات الترحيلات ──
     *
     * تاريخ تعديل المجلّد يتغيّر بإضافة ملفٍّ فيه أو حذفه، فالمفتاح يتبدّل
     * لحظةَ رفع ترحيلٍ جديد بالـFTP ويسقط الجواب القديم من تلقاء نفسه.
     * ولا يُقرأ محتوى المجلّدات هنا: استدعاءُ `stat` لعشرين مجلّداً أرخص
     * كثيراً من فتحها وقراءة أسماء أربعمئة ملفّ في كل طلب.
     *
     * ── الشقّ الثاني: جدول `migrations` — ولماذا أُضيف ──
     *
     * البصمة الأولى وحدها تعرف ما **وصل** ولا تعرف ما **نُفِّذ**. وتشغيلُ
     * الترحيل لا يغيّر تاريخ تعديل المجلّد، فإن نُفِّذ من أيّ طريقٍ لا يمرّ
     * بـ`forgetPendingCache()` — `php artisan migrate` من الطرفية، أو سكربت
     * نشر، أو `composer setup`، أو خادمٌ آخر على القاعدة نفسها — بقي المفتاح
     * كما هو والجواب المخزَّن يقول «١ بانتظار التنفيذ» عشر دقائق كاملة على
     * نظامٍ مُرحَّلٍ تماماً.
     *
     * وأثرُ ذلك ليس شريطاً مزعجاً فحسب: `CheckSystemUpdates` يحوّل كلَّ
     * مستخدمٍ إلى شاشة التحديث ما دام العدد أكبر من صفر. فكذبةُ الكاش تُقفل
     * النظام على الجميع، ومن يضغط «تشغيل الترحيلات» لا يجد ما يُرحَّل فلا
     * يتغيّر شيء — وهو ما بدا «الزرّ لا يسوّي شيئاً».
     *
     * فصار عدد الصفوف المنفَّذة وآخر مُعرّفٍ فيها جزءاً من البصمة: أيُّ
     * ترحيلٍ يُنفَّذ أو يُتراجَع عنه — بأيّ طريقٍ كان — يبدّل المفتاح فيُعاد
     * الحساب من القاعدة فوراً. استعلامان على جدولٍ من مئاتِ الصفوف أرخص من
     * فحص المجلّدات الذي يحرسانه، فلا ثمنَ حقيقيّاً لهذه الدقّة.
     */
    private function pendingCacheKey(): string
    {
        $stamp = '';

        foreach ($this->paths() as $path) {
            $stamp .= $path.':'.(is_dir($path) ? (int) @filemtime($path) : 0).'|';
        }

        return 'system_update.pending:'.md5($stamp.'#'.$this->appliedMigrationsStamp());
    }

    /**
     * بصمةُ ما نُفِّذ فعلاً: «عدد الصفوف:آخر مُعرّف».
     *
     * المعرّف الأقصى مع العدد لا العدد وحده — ترحيلٌ يُتراجَع عنه ثم يُنفَّذ
     * غيرُه يُبقي العدد كما هو ويغيّر المعرّف، فبقاؤهما معاً يمنع تلك الحالة
     * من أن تمرّ بجوابٍ قديم.
     *
     * وعند تعذّر الوصول إلى القاعدة تُرجَع بصمةٌ ثابتة: هذا موضعُ بناء مفتاح
     * لا موضعُ إعلانٍ عن عطل، و`pendingMigrationCount()` تتولّى الاستثناء.
     */
    private function appliedMigrationsStamp(): string
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return 'none';
            }

            return DB::table('migrations')->count().':'.(int) DB::table('migrations')->max('id');
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    /** عدد صفوف جدول، وصفرٌ إن تعذّر الوصول إليه. */
    private function rowCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * تشغيل أمر Artisan وإرجاع مخرَجه.
     *
     * الاستثناء يُلتقَط ولا يُرمى: فشلُ ترحيلٍ في المنتصف خبرٌ يجب أن يقرأه
     * المستخدم كاملاً في الشاشة — لا صفحةَ خطأٍ عامّة تخفي أي ترحيلٍ سقط.
     *
     * @return array{ok: bool, output: string}
     */
    private function run(string $command, array $arguments): array
    {
        @set_time_limit(self::TIME_LIMIT);

        try {
            $status = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());

            return [
                'ok' => $status === 0,
                'output' => $output !== '' ? $output : 'تمّ التنفيذ بلا مخرَج.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'output' => trim(Artisan::output())."\n\n".$e->getMessage(),
            ];
        }
    }
}
